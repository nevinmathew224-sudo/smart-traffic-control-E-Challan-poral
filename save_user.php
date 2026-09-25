<?php
include("db.php");
ensure_logged_in('admin');
require_csrf();

$email = strtolower(trim($_POST['email'] ?? ''));
$vehicleNo = strtoupper(trim($_POST['vehicle_no'] ?? ''));
$vehicleNo = preg_replace('/[^A-Z0-9]/', '', $vehicleNo);
$password = trim($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 4) {
    header("Location: users.php?message=invalid");
    exit();
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'user';

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    $existingId = (int) $existing['id'];
    if ($vehicleNo !== '') {
        $stmt = $conn->prepare("UPDATE users SET password = ?, vehicle_no = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $passwordHash, $vehicleNo, $role, $existingId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $passwordHash, $role, $existingId);
    }
    $targetId = $existingId;
} else {
    $stmt = $conn->prepare("INSERT INTO users (email, vehicle_no, password, role) VALUES (?, NULLIF(?, ''), ?, ?)");
    $stmt->bind_param("ssss", $email, $vehicleNo, $passwordHash, $role);
    $targetId = 0;
}

$stmt->execute();
if ($targetId === 0) {
    $targetId = (int) $conn->insert_id;
}
$stmt->close();

header("Location: users.php?message=created&uid=" . $targetId . "&vno=" . urlencode($vehicleNo));
exit();
?>
