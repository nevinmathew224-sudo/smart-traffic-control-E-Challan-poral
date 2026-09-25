<?php
include("db.php");
require_once("challan_helper.php");
ensure_logged_in('admin');
require_csrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$rawVehicle = trim($_POST['vehicle_no'] ?? '');
$vehicleNo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawVehicle));

if (strlen($vehicleNo) < 4) {
    header("Location: challans.php?message=invalid_vehicle");
    exit();
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

// Resolve Citizen User ID:
// 1. If explicit user_id provided (> 0), verify and update user's vehicle if unset
// 2. If user_id is 0 or empty, search users table by vehicle_no
// 3. If no user exists for this vehicle, automatically provision a user account so citizen can log in
if ($userId > 0) {
    $uCheckStmt = $conn->prepare("SELECT id, vehicle_no FROM users WHERE id = ? LIMIT 1");
    if ($uCheckStmt) {
        $uCheckStmt->bind_param("i", $userId);
        $uCheckStmt->execute();
        $uRow = $uCheckStmt->get_result()->fetch_assoc();
        $uCheckStmt->close();

        if ($uRow && (empty($uRow['vehicle_no']))) {
            $uUpdateStmt = $conn->prepare("UPDATE users SET vehicle_no = ? WHERE id = ?");
            if ($uUpdateStmt) {
                $uUpdateStmt->bind_param("si", $vehicleNo, $userId);
                $uUpdateStmt->execute();
                $uUpdateStmt->close();
            }
        }
    }
} else {
    // Look for user by vehicle
    $findStmt = $conn->prepare("SELECT id FROM users WHERE vehicle_no = ? OR REPLACE(vehicle_no, ' ', '') = ? LIMIT 1");
    if ($findStmt) {
        $findStmt->bind_param("ss", $vehicleNo, $vehicleNo);
        $findStmt->execute();
        $found = $findStmt->get_result()->fetch_assoc();
        $findStmt->close();

        if ($found) {
            $userId = (int) $found['id'];
        }
    }

    // Auto-create user account for this vehicle if none exists
    if ($userId <= 0) {
        $autoEmail = 'citizen_' . strtolower($vehicleNo) . '@traffic.gov.in';
        $defaultPassHash = password_hash('1234', PASSWORD_DEFAULT);
        $role = 'user';

        $insUserStmt = $conn->prepare("INSERT INTO users (email, vehicle_no, password, role) VALUES (?, ?, ?, ?)");
        if ($insUserStmt) {
            $insUserStmt->bind_param("ssss", $autoEmail, $vehicleNo, $defaultPassHash, $role);
            if ($insUserStmt->execute()) {
                $userId = (int) $conn->insert_id;
            }
            $insUserStmt->close();
        }
    }
}

if ($userId <= 0) {
    // Fallback to first citizen user in db
    $uFallback = $conn->query("SELECT id FROM users WHERE role = 'user' ORDER BY id ASC LIMIT 1");
    if ($uFallback instanceof mysqli_result && ($fbRow = $uFallback->fetch_assoc())) {
        $userId = (int) $fbRow['id'];
    } else {
        header("Location: challans.php?message=user_required");
        exit();
    }
}

// Form parameters
$violation = trim($_POST['violation'] ?? 'Helmet');
$fineAmount = isset($_POST['fine_amount']) ? (float) $_POST['fine_amount'] : 1000.0;
if ($fineAmount <= 0) {
    $fineAmount = 1000.0;
}

$status = in_array(trim($_POST['status'] ?? ''), ['Paid', 'Unpaid'], true) ? trim($_POST['status']) : 'Unpaid';

// Violation date & time
$rawViolationDate = trim($_POST['violation_date'] ?? '');
if (!empty($rawViolationDate)) {
    $violationTimestamp = strtotime($rawViolationDate);
    $violationDate = $violationTimestamp ? date('Y-m-d H:i:s', $violationTimestamp) : date('Y-m-d H:i:s');
} else {
    $violationDate = date('Y-m-d H:i:s');
}

// Detailed violation description
$violationDesc = trim($_POST['violation_desc'] ?? '');
if (empty($violationDesc)) {
    $legalDetails = getViolationLegalDetails($violation, $fineAmount, $vehicleNo);
    $violationDesc = $legalDetails['description'] ?? 'Violation of Motor Vehicles Act regulations.';
}

// Challan Number
$challanNo = trim($_POST['challan_no'] ?? '');

// Helper default evidence
$defaultEvidence = getChallanEvidence($vehicleNo, $violation, $id > 0 ? $id : rand(500, 999), $violationDate);

// Handle Evidence Photo 1 Upload / Preset
$evidencePhoto = trim($_POST['evidence_photo_preset'] ?? '');
if (isset($_FILES['evidence_photo_file']) && $_FILES['evidence_photo_file']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['evidence_photo_file']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['evidence_photo_file']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExt, $allowedExts, true)) {
        $uploadDir = __DIR__ . '/assets/evidence/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $newFilename = 'upload_front_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
        $destPath = $uploadDir . $newFilename;
        if (move_uploaded_file($tmpName, $destPath)) {
            $evidencePhoto = 'assets/evidence/' . $newFilename;
        }
    }
}
if (empty($evidencePhoto)) {
    $evidencePhoto = $defaultEvidence['front_image'];
}

// Handle Evidence Photo 2 Upload / Preset
$evidencePhoto2 = trim($_POST['evidence_photo_preset_2'] ?? '');
if (isset($_FILES['evidence_photo_file_2']) && $_FILES['evidence_photo_file_2']['error'] === UPLOAD_ERR_OK) {
    $tmpName2 = $_FILES['evidence_photo_file_2']['tmp_name'];
    $fileExt2 = strtolower(pathinfo($_FILES['evidence_photo_file_2']['name'], PATHINFO_EXTENSION));
    $allowedExts2 = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExt2, $allowedExts2, true)) {
        $uploadDir2 = __DIR__ . '/assets/evidence/';
        if (!is_dir($uploadDir2)) {
            mkdir($uploadDir2, 0777, true);
        }
        $newFilename2 = 'upload_side_' . time() . '_' . rand(100, 999) . '.' . $fileExt2;
        $destPath2 = $uploadDir2 . $newFilename2;
        if (move_uploaded_file($tmpName2, $destPath2)) {
            $evidencePhoto2 = 'assets/evidence/' . $newFilename2;
        }
    }
}
if (empty($evidencePhoto2)) {
    $evidencePhoto2 = $defaultEvidence['side_image'];
}

// Other relevant details
$location = trim($_POST['location'] ?? '');
if (empty($location)) {
    $location = $defaultEvidence['location'];
}

$cameraId = trim($_POST['camera_id'] ?? '');
if (empty($cameraId)) {
    $cameraId = $defaultEvidence['front_camera_id'];
}

$officerName = trim($_POST['officer_name'] ?? '');
if (empty($officerName)) {
    $officerName = 'Motor Vehicles Inspector (MVI) / Authorized Digital Signatory';
}

$dueDateRaw = trim($_POST['due_date'] ?? '');
if (!empty($dueDateRaw)) {
    $dueTimestamp = strtotime($dueDateRaw);
    $dueDate = $dueTimestamp ? date('Y-m-d', $dueTimestamp) : date('Y-m-d', strtotime('+60 days', strtotime($violationDate)));
} else {
    $dueDate = date('Y-m-d', strtotime('+60 days', strtotime($violationDate)));
}

if ($id > 0) {
    if (empty($challanNo)) {
        $challanNo = 'KL-CHN-' . date('Y', strtotime($violationDate)) . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    $stmt = $conn->prepare("
        UPDATE challans 
        SET challan_no = ?, user_id = ?, vehicle_no = ?, violation_date = ?, violation = ?, 
            violation_desc = ?, fine_amount = ?, status = ?, evidence_photo = ?, evidence_photo_2 = ?, 
            location = ?, camera_id = ?, officer_name = ?, due_date = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sissssdsssssssi",
        $challanNo,
        $userId,
        $vehicleNo,
        $violationDate,
        $violation,
        $violationDesc,
        $fineAmount,
        $status,
        $evidencePhoto,
        $evidencePhoto2,
        $location,
        $cameraId,
        $officerName,
        $dueDate,
        $id
    );
    $stmt->execute();
    $stmt->close();

    header("Location: challans.php?message=updated&vno=" . urlencode($vehicleNo) . "&uid=" . $userId);
    exit();
}

$createdAt = $violationDate;
$stmt = $conn->prepare("
    INSERT INTO challans (
        challan_no, user_id, vehicle_no, violation_date, violation, 
        violation_desc, fine_amount, status, evidence_photo, evidence_photo_2, 
        location, camera_id, officer_name, due_date, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "sissssdssssssss",
    $challanNo,
    $userId,
    $vehicleNo,
    $violationDate,
    $violation,
    $violationDesc,
    $fineAmount,
    $status,
    $evidencePhoto,
    $evidencePhoto2,
    $location,
    $cameraId,
    $officerName,
    $dueDate,
    $createdAt
);
$stmt->execute();
$newId = (int) $conn->insert_id;
$stmt->close();

// If challan_no was empty, assign official standardized challan number using new ID
if (empty($challanNo) && $newId > 0) {
    $generatedChallanNo = 'KL-CHN-' . date('Y', strtotime($violationDate)) . '-' . str_pad((string) $newId, 6, '0', STR_PAD_LEFT);
    $upStmt = $conn->prepare("UPDATE challans SET challan_no = ? WHERE id = ?");
    if ($upStmt) {
        $upStmt->bind_param("si", $generatedChallanNo, $newId);
        $upStmt->execute();
        $upStmt->close();
    }
}

header("Location: challans.php?message=created&vno=" . urlencode($vehicleNo) . "&uid=" . $userId . "&chid=" . $newId);
exit();
?>
