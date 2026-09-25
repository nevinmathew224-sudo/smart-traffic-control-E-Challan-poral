<?php
// Database Configuration
// For Local XAMPP: Leave as default (localhost / root / '' / traffic_system)
// For InfinityFree: Replace with values from InfinityFree Control Panel -> MySQL Databases
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'traffic_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("<h3>Database Connection Failed</h3><p>" . htmlspecialchars($conn->connect_error) . "</p><p><small>Tip: If deploying to InfinityFree or cPanel, please check <code>db.php</code> and enter your host's MySQL Hostname, Username, Password, and Database Name.</small></p>");
}

$conn->set_charset("utf8mb4");

$conn->query("
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challan_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payments_challan_id (challan_id),
        CONSTRAINT fk_payments_challan
            FOREIGN KEY (challan_id) REFERENCES challans(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$userCols = $conn->query("SHOW COLUMNS FROM users LIKE 'vehicle_no'");
if ($userCols instanceof mysqli_result && $userCols->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN vehicle_no VARCHAR(20) DEFAULT NULL AFTER email");
    $conn->query("ALTER TABLE users ADD INDEX idx_users_vehicle_no (vehicle_no)");
}

// Auto-migration for challans table columns
$challanColumns = [
    'challan_no' => "ALTER TABLE challans ADD COLUMN challan_no VARCHAR(60) DEFAULT NULL AFTER id",
    'violation_date' => "ALTER TABLE challans ADD COLUMN violation_date DATETIME DEFAULT NULL AFTER vehicle_no",
    'violation_desc' => "ALTER TABLE challans ADD COLUMN violation_desc TEXT DEFAULT NULL AFTER violation",
    'evidence_photo' => "ALTER TABLE challans ADD COLUMN evidence_photo VARCHAR(255) DEFAULT NULL",
    'evidence_photo_2' => "ALTER TABLE challans ADD COLUMN evidence_photo_2 VARCHAR(255) DEFAULT NULL",
    'location' => "ALTER TABLE challans ADD COLUMN location VARCHAR(255) DEFAULT NULL",
    'camera_id' => "ALTER TABLE challans ADD COLUMN camera_id VARCHAR(100) DEFAULT NULL",
    'officer_name' => "ALTER TABLE challans ADD COLUMN officer_name VARCHAR(100) DEFAULT NULL",
    'due_date' => "ALTER TABLE challans ADD COLUMN due_date DATE DEFAULT NULL",
];

foreach ($challanColumns as $colName => $alterSql) {
    $checkCol = $conn->query("SHOW COLUMNS FROM challans LIKE '$colName'");
    if ($checkCol instanceof mysqli_result && $checkCol->num_rows === 0) {
        $conn->query($alterSql);
    }
}

// Backfill challan_no and violation_date for any existing records where null
$conn->query("
    UPDATE challans 
    SET challan_no = CONCAT('KL-CHN-', DATE_FORMAT(COALESCE(created_at, NOW()), '%Y'), '-', LPAD(id, 6, '0'))
    WHERE challan_no IS NULL OR challan_no = ''
");
$conn->query("
    UPDATE challans
    SET violation_date = created_at
    WHERE violation_date IS NULL
");

// Backfill evidence photos, descriptions, locations, and cameras for any challans missing them
$conn->query("
    UPDATE challans
    SET location = 'MG Road - Kaloor Junction (Junction Node #04), Ernakulam, Kerala'
    WHERE location IS NULL OR location = ''
");
$conn->query("
    UPDATE challans
    SET officer_name = 'MVI K. Suresh (Badge #4082)'
    WHERE officer_name IS NULL OR officer_name = ''
");
$conn->query("
    UPDATE challans
    SET due_date = DATE_ADD(COALESCE(violation_date, NOW()), INTERVAL 60 DAY)
    WHERE due_date IS NULL
");

// Fix mismatched evidence photo for wrong side driving if incorrectly assigned helmet photo
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/wrongside_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/wrongside_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-724 (Directional Flow & One-Way Vector Enforcement Node)'
    WHERE violation = 'Wrong Side Driving' AND (evidence_photo LIKE '%helmet%' OR evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Helmet
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/helmet_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/helmet_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-54 (High-Speed ANPR Node)',
        violation_desc = 'Operating or riding pillion on a two-wheeled motorcycle in a public place without wearing protective headgear conforming to IS:4151:2015 securely fastened.'
    WHERE (violation = 'Helmet' OR violation LIKE '%helmet%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Overspeed
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/car_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/car_side_cam.jpg',
        camera_id = 'KL-ITES-ANPR-01 (Automated Highway Speed Scanner)',
        violation_desc = 'Driving a motor vehicle in excess of the statutory speed limit prescribed by the competent authority on public highways under Section 112 of MVA.'
    WHERE (violation = 'Overspeed' OR violation LIKE '%speed%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Signal Jump
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/signal_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/signal_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-04 (Red Light & Stop-Line ANPR Node)',
        violation_desc = 'Failing to conform to traffic control signals by proceeding past the designated junction stop line during steady red light phase.'
    WHERE (violation = 'Signal Jump' OR violation LIKE '%signal%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Mobile Usage
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/mobile_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/mobile_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-04 (Windshield Telephoto In-Cabin Inspection Node)',
        violation_desc = 'Operating or communicating through a handheld mobile phone or electronic device while driving a motor vehicle in motion in a public corridor.'
    WHERE (violation = 'Mobile Usage' OR violation LIKE '%mobile%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Triple Riding
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/triple_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/triple_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-88 (Urban Multi-Occupancy ANPR Headcount Node)',
        violation_desc = 'Riding a two-wheeled motorcycle or scooter with more than one pillion rider contrary to Section 128 of the Motor Vehicles Act, 1988.'
    WHERE (violation = 'Triple Riding' OR violation LIKE '%triple%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill No Seatbelt
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/seatbelt_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/seatbelt_side_cam.jpg',
        camera_id = 'KL-ITES-CABIN-09 (High-Resolution In-Cabin Seatbelt Detection Node)',
        violation_desc = 'Driving a motor vehicle without fastening the prescribed safety seatbelt in contravention of Rule 138(3) of CMVR 1989.'
    WHERE (violation = 'No Seatbelt' OR violation LIKE '%seatbelt%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Dangerous Driving
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/dangerous_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/dangerous_side_cam.jpg',
        camera_id = 'KL-ITES-HWY-07 (Highway Multi-Lane Surveillance & Trajectory Array)',
        violation_desc = 'Driving in a dangerous manner, reckless multi-lane swerving, cutting across road dividers, or endangering public safety.'
    WHERE (violation = 'Dangerous Driving' OR violation LIKE '%dangerous%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Wrong Side Driving
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/wrongside_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/wrongside_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-724 (Directional Flow & One-Way Vector Enforcement Node)',
        violation_desc = 'Driving against designated flow of traffic, violating one-way corridor restrictions, or navigating on opposite lane.'
    WHERE (violation = 'Wrong Side Driving' OR violation LIKE '%wrong side%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// Backfill Wrong Parking
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/parking_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/parking_side_cam.jpg',
        camera_id = 'KL-ITES-CAM-09 (High-Definition Urban Surveillance & No-Parking ANPR Node)',
        violation_desc = 'Leaving or parking a motor vehicle in a designated No-Parking tow-away zone, blocking pedestrian walkways, or obstructing traffic.'
    WHERE (violation = 'Wrong Parking' OR violation LIKE '%park%') AND (evidence_photo IS NULL OR evidence_photo = '')
");

// General catch-all for any remaining challans without photos
$conn->query("
    UPDATE challans
    SET evidence_photo = 'assets/evidence/car_front_cam.jpg',
        evidence_photo_2 = 'assets/evidence/car_side_cam.jpg'
    WHERE evidence_photo IS NULL OR evidence_photo = ''
");


if (!function_exists('ensure_logged_in')) {
    function ensure_logged_in(?string $requiredRole = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['id'], $_SESSION['role'])) {
            header("Location: index.php");
            exit();
        }

        if ($requiredRole !== null && $_SESSION['role'] !== $requiredRole) {
            header("Location: logout.php");
            exit();
        }
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit("Method not allowed");
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            exit("Invalid security token");
        }
    }
}
?>
