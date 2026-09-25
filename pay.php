<?php
include("db.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: index.php?tab=user");
    exit();
}

$userRole = $_SESSION['role'];
$userId = (int) $_SESSION['id'];

// HANDLE POST REQUEST: Record Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        exit("Invalid challan ID");
    }

    if ($userRole === 'user') {
        $userVehicle = $_SESSION['vehicle_no'] ?? '';
        $cleanVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $userVehicle));
        $challanStmt = $conn->prepare("
            SELECT id, fine_amount, status, vehicle_no 
            FROM challans 
            WHERE id = ? 
              AND (
                  user_id = ? 
                  OR vehicle_no = ? 
                  OR REPLACE(vehicle_no, ' ', '') = ? 
                  OR vehicle_no IN (SELECT vehicle_no FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
                  OR REPLACE(vehicle_no, ' ', '') IN (SELECT REPLACE(vehicle_no, ' ', '') FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
              ) 
            LIMIT 1
        ");
        $challanStmt->bind_param("iissii", $id, $userId, $userVehicle, $cleanVehicle, $userId, $userId);
    } else {
        $challanStmt = $conn->prepare("SELECT id, fine_amount, status, vehicle_no FROM challans WHERE id = ? LIMIT 1");
        $challanStmt->bind_param("i", $id);
    }

    $challanStmt->execute();
    $challan = $challanStmt->get_result()->fetch_assoc();
    $challanStmt->close();

    if (!$challan) {
        exit("Challan not found");
    }

    if (strtolower((string) $challan['status']) !== 'paid') {
        $conn->begin_transaction();

        $updateStmt = $conn->prepare("UPDATE challans SET status = 'Paid' WHERE id = ?");
        $updateStmt->bind_param("i", $id);
        $updateStmt->execute();
        $updateStmt->close();

        $paymentStmt = $conn->prepare("INSERT INTO payments (challan_id, amount) VALUES (?, ?)");
        if (!$paymentStmt) {
            $conn->rollback();
            exit("Unable to record payment");
        }

        $amount = (float) $challan['fine_amount'];
        $paymentStmt->bind_param("id", $id, $amount);
        $paymentStmt->execute();
        $paymentStmt->close();

        $conn->commit();
    }

    header("Location: receipt.php?id=" . $id);
    exit();
}

// HANDLE GET REQUEST: Render Dedicated QR Payment Gateway Page
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . ($userRole === 'admin' ? "challans.php" : "user_dashboard.php"));
    exit();
}

if ($userRole === 'user') {
    $userVehicle = $_SESSION['vehicle_no'] ?? '';
    $cleanVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $userVehicle));
    $stmt = $conn->prepare("
        SELECT * FROM challans 
        WHERE id = ? 
          AND (
              user_id = ? 
              OR vehicle_no = ? 
              OR REPLACE(vehicle_no, ' ', '') = ? 
              OR vehicle_no IN (SELECT vehicle_no FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
              OR REPLACE(vehicle_no, ' ', '') IN (SELECT REPLACE(vehicle_no, ' ', '') FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
          ) 
        LIMIT 1
    ");
    $stmt->bind_param("iissii", $id, $userId, $userVehicle, $cleanVehicle, $userId, $userId);
} else {
    $stmt = $conn->prepare("SELECT * FROM challans WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
}

$stmt->execute();
$challan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$challan) {
    exit("Challan not found or access denied.");
}

if (strtolower((string) $challan['status']) === 'paid') {
    header("Location: receipt.php?id=" . $id);
    exit();
}

$fineAmount = (float) $challan['fine_amount'];
$formattedAmount = number_format($fineAmount, 2, '.', '');
$vehicleNo = strtoupper($challan['vehicle_no']);
$violation = $challan['violation'];

// Standard NPCI UPI URI
$upiPa = 'keralatraffic.treasury@gov.in';
$upiPn = 'Traffic Police Department';
$upiTn = 'Challan CH' . $id . ' ' . $vehicleNo;
$upiUri = "upi://pay?pa=" . urlencode($upiPa) . "&pn=" . urlencode($upiPn) . "&mc=9399&tid=CH" . $id . "&tr=" . $id . "&am=" . $formattedAmount . "&cu=INR&tn=" . urlencode($upiTn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UPI QR Payment - Challan #<?php echo e((string) $id); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="kerala-theme.css">
<script src="qrcode.min.js"></script>
<style>
:root{
    --navy-950:#0f172a;
    --navy-900:#0f172a;
    --navy-800:#1e293b;
    --blue-500:#2563eb;
    --blue-400:#3b82f6;
    --purple-600:#2563eb;
    --purple-500:#1d4ed8;
    --cyan-400:#0f766e;
    --panel:#ffffff;
    --line:#e2e8f0;
    --ink:#0f172a;
    --muted:#64748b;
    --success:#15803d;
    --danger:#dc2626;
    --shadow:0 12px 32px rgba(15, 23, 42, 0.08);
}
*{ box-sizing:border-box; }
body{
    margin:0;
    min-height:100vh;
    font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--ink);
    background:#f8fafc;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:32px 20px;
}
.pay-card{
    width:min(520px, 100%);
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:24px;
    box-shadow:0 12px 32px rgba(15, 23, 42, 0.08);
    overflow:hidden;
}
.pay-header{
    background:#0f172a;
    border-bottom:1px solid #334155;
    color:#fff;
    padding:24px 26px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.pay-header h1{
    font-family:'Outfit',sans-serif;
    margin:0 0 4px;
    font-size:23px;
    font-weight:800;
}
.pay-header p{
    margin:0;
    font-size:13px;
    color:rgba(226,232,240,0.85);
}
.badge-ch{
    padding:6px 14px;
    border-radius:999px;
    background:rgba(37, 99, 235, 0.15);
    border:1px solid rgba(147, 197, 253, 0.4);
    color:#93c5fd;
    font-size:12px;
    font-weight:800;
    font-family:'Outfit',sans-serif;
}
.pay-body{
    padding:26px;
}
.challan-summary{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    margin-bottom:20px;
    padding:16px;
    border-radius:16px;
    background:#f4f8fd;
    border:1px solid #dce6f2;
}
.sum-item small{
    display:block;
    color:var(--muted);
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:0.06em;
    margin-bottom:4px;
}
.sum-item strong{
    font-size:16px;
    color:var(--navy-950);
}
.amount-row{
    grid-column:span 2;
    padding-top:10px;
    border-top:1px dashed #cbdbe9;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.amount-row .total-label{
    font-size:14px;
    font-weight:700;
    color:#35516c;
}
.amount-row .total-val{
    font-size:26px;
    font-weight:900;
    color:var(--danger);
}
.qr-container{
    text-align:center;
    padding:20px;
    border-radius:20px;
    background:#ffffff;
    border:2px solid #e2ebf5;
    box-shadow:0 8px 24px rgba(15,23,42,0.06);
    margin-bottom:20px;
    display:flex;
    flex-direction:column;
    align-items:center;
}
.qr-frame{
    padding:14px;
    background:#ffffff;
    border:2px solid #0d1b2a;
    border-radius:16px;
    display:inline-block;
    position:relative;
}
#qrcodeCanvas{
    display:flex;
    align-items:center;
    justify-content:center;
}
#qrcodeCanvas img, #qrcodeCanvas canvas{
    display:block;
    margin:0 auto;
}
.scan-hint{
    margin-top:14px;
    font-size:13px;
    font-weight:700;
    color:var(--navy-900);
}
.upi-apps{
    display:flex;
    gap:8px;
    justify-content:center;
    margin-top:8px;
    flex-wrap:wrap;
}
.upi-app-pill{
    padding:4px 8px;
    border-radius:6px;
    background:#eef4fc;
    color:#2b435d;
    font-size:11px;
    font-weight:700;
}
.timer-strip{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-bottom:18px;
    color:#536a82;
    font-size:13px;
    font-weight:600;
}
.pulse-dot{
    width:8px;
    height:8px;
    border-radius:50%;
    background:#15803d;
    animation:pulse 1.5s infinite;
}
@keyframes pulse{
    0%{ transform:scale(0.9); opacity:0.6; }
    50%{ transform:scale(1.3); opacity:1; }
    100%{ transform:scale(0.9); opacity:0.6; }
}
.btn{
    display:block;
    width:100%;
    padding:14px;
    border-radius:14px;
    border:none;
    cursor:pointer;
    font-weight:900;
    font-size:15px;
    letter-spacing:0.02em;
    text-align:center;
    text-decoration:none;
    transition:0.2s ease;
}
.btn-verify{
    background:#2563eb;
    color:#fff;
    box-shadow:0 4px 14px rgba(37, 99, 235, 0.25);
    margin-bottom:10px;
}
.btn-verify:hover{
    background:#1d4ed8;
    transform:translateY(-2px);
    box-shadow:0 6px 18px rgba(37, 99, 235, 0.35);
}
.btn-back{
    background:#eef4fb;
    color:#35516c;
    border:1px solid #d8e2ec;
}
.upi-id-box{
    margin-top:12px;
    padding:8px 12px;
    border-radius:10px;
    background:#f4f8fd;
    border:1px solid #dce6f2;
    font-size:12px;
    color:var(--muted);
    text-align:center;
}
</style>
<script src="qrcode.min.js"></script>
</head>
<body>

<div class="pay-card">
    <div class="pay-header">
        <div>
            <h1>Official Challan Payment</h1>
            <p>Traffic Police Department e-Challan Gateway</p>
        </div>
        <div class="badge-ch">#CH<?php echo e((string) $id); ?></div>
    </div>

    <div class="pay-body">
        <div class="challan-summary">
            <div class="summary-row">
                <span class="label">Challan ID</span>
                <span class="val">#CH<?php echo e((string) $id); ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Vehicle Number</span>
                <span class="val" style="font-family:monospace; font-weight:900; letter-spacing:1px;"><?php echo e($challan['vehicle_no']); ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Violation</span>
                <span class="val"><?php echo e($challan['violation']); ?></span>
            </div>
            <div class="summary-row total-row">
                <span class="total-label">Total Fine Payable</span>
                <span class="total-val">₹<?php echo e($formattedAmount); ?></span>
            </div>
        </div>

        <div class="qr-container">
            <div class="qr-frame">
                <div id="qrcodeCanvas"></div>
            </div>
            <div class="scan-hint">📲 Scan with any UPI app to complete payment</div>
            <div class="upi-apps">
                <span class="upi-app-pill">GPay</span>
                <span class="upi-app-pill">PhonePe</span>
                <span class="upi-app-pill">Paytm</span>
                <span class="upi-app-pill">BHIM</span>
                <span class="upi-app-pill">Any Bank UPI</span>
            </div>
            <div class="upi-id-box">
                Official Treasury UPI: <strong><?php echo e($upiPa); ?></strong>
            </div>
        </div>

        <div class="timer-strip">
            <div class="pulse-dot"></div>
            <span>Payment session active • Ready to scan</span>
        </div>

        <form method="POST" action="pay.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
            <button type="submit" class="btn btn-verify">✓ I HAVE PAID / VERIFY PAYMENT</button>
        </form>
    </div>
</div>

<!-- UNIFIED BOTTOM NAVIGATION -->
<div class="portal-bottom-nav">
    <a href="<?php echo $userRole === 'admin' ? 'challans.php' : 'user_dashboard.php'; ?>" class="portal-nav-btn portal-nav-btn-dash">← Back to Dashboard</a>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
    const upiString = <?php echo json_encode($upiUri, JSON_UNESCAPED_SLASHES); ?>;
    const qrElem = document.getElementById('qrcodeCanvas');

    try {
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrElem, {
                text: upiString,
                width: 210,
                height: 210,
                colorDark: "#0d1b2a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            // Fallback image
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=210x210&data=' + encodeURIComponent(upiString);
            img.alt = 'Payment QR Code';
            img.width = 210;
            img.height = 210;
            qrElem.appendChild(img);
        }
    } catch(err) {
        console.error('QR code generation error:', err);
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=210x210&data=' + encodeURIComponent(upiString);
        img.alt = 'Payment QR Code';
        img.width = 210;
        img.height = 210;
        qrElem.appendChild(img);
    }
});
</script>
</body>
</html>
