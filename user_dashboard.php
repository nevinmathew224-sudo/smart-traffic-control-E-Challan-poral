<?php
include("db.php");
require_once("challan_helper.php");
ensure_logged_in('user');

$userId = (int) $_SESSION['id'];
$userEmail = $_SESSION['user'] ?? 'user@trafficdemo.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

$userName = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $userEmail)[0]));
$userVehicle = $_SESSION['vehicle_no'] ?? '';
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
if ($uStmt) {
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    if ($uRow = $uRes->fetch_assoc()) {
        if (!empty($uRow['name'])) {
            $userName = (string) $uRow['name'];
        }
        if (!empty($uRow['vehicle_no'])) {
            $userVehicle = (string) $uRow['vehicle_no'];
            $_SESSION['vehicle_no'] = $userVehicle;
        }
    }
    $uStmt->close();
}

// Fetch all challans associated with this user account OR vehicle
$cleanUserVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $userVehicle));
$stmt = $conn->prepare("
    SELECT * FROM challans
    WHERE user_id = ? 
       OR (vehicle_no = ? AND ? != '')
       OR (REPLACE(vehicle_no, ' ', '') = ? AND ? != '')
       OR vehicle_no IN (SELECT vehicle_no FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
       OR REPLACE(vehicle_no, ' ', '') IN (SELECT REPLACE(vehicle_no, ' ', '') FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '')
    ORDER BY id DESC
");
$stmt->bind_param("issssii", $userId, $userVehicle, $userVehicle, $cleanUserVehicle, $cleanUserVehicle, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$challans = [];
while ($row = $result->fetch_assoc()) {
    $challans[] = $row;
}
$stmt->close();

if ($userVehicle === '' && !empty($challans)) {
    $userVehicle = (string) $challans[0]['vehicle_no'];
    $_SESSION['vehicle_no'] = $userVehicle;
}

// Collect all distinct vehicles linked to this user
$vehicleList = [];
if (!empty($userVehicle)) {
    $vehicleList[$userVehicle] = true;
}
foreach ($challans as $c) {
    if (!empty($c['vehicle_no'])) {
        $vehicleList[$c['vehicle_no']] = true;
    }
}
$vehicleNumbers = array_keys($vehicleList);

// Active vehicle lookup search: when user enters a vehicle number
$searchedVehicle = strtoupper(trim($_GET['vehicle_no'] ?? ($_POST['vehicle_no'] ?? '')));
$cleanSearchedVehicle = preg_replace('/[^A-Z0-9]/', '', $searchedVehicle);
$filterActiveVehicle = ($cleanSearchedVehicle !== '');

$activeVehicleNo = $cleanSearchedVehicle !== '' ? $cleanSearchedVehicle : (!empty($vehicleNumbers) ? $vehicleNumbers[0] : 'DL01AB1234');

// If user entered a vehicle number, search for any additional challans in DB for that vehicle
$searchedVehicleChallans = [];
if ($filterActiveVehicle) {
    $svStmt = $conn->prepare("SELECT * FROM challans WHERE vehicle_no = ? OR REPLACE(vehicle_no, ' ', '') = ? ORDER BY id DESC");
    if ($svStmt) {
        $svStmt->bind_param("ss", $cleanSearchedVehicle, $cleanSearchedVehicle);
        $svStmt->execute();
        $svRes = $svStmt->get_result();
        while ($svRow = $svRes->fetch_assoc()) {
            $searchedVehicleChallans[] = $svRow;
        }
        $svStmt->close();
    }
}

// Get rich vehicle specifications for the active vehicle
$sampleViolation = count($challans) > 0 ? $challans[0]['violation'] : '';
$activeVehicleDetails = getVehicleDetails($activeVehicleNo, $sampleViolation);

// Calculate vehicle breakdown details for active vehicle
$targetVehicleChallans = $filterActiveVehicle ? $searchedVehicleChallans : $challans;
$vCases = 0;
$vPaid = 0;
$vUnpaid = 0;
$vTotalFine = 0.0;
$vPendingFine = 0.0;

foreach ($targetVehicleChallans as $tc) {
    $cleanTc = preg_replace('/[^A-Z0-9]/', '', (string) $tc['vehicle_no']);
    if ($cleanTc === $activeVehicleNo || (string) $tc['vehicle_no'] === $activeVehicleNo) {
        $vCases++;
        $amt = (float) $tc['fine_amount'];
        $vTotalFine += $amt;
        if (strtolower((string) $tc['status']) === 'paid') {
            $vPaid++;
        } else {
            $vUnpaid++;
            $vPendingFine += $amt;
        }
    }
}

$vRiskLevel = ($vCases >= 3 || $vUnpaid >= 2 || $vTotalFine >= 5000) ? 'High' : (($vUnpaid >= 1) ? 'Medium' : 'Low');
$vIsCompliant = ($vUnpaid === 0);

// User overall metrics
$totalChallans = count($challans);
$paidCount = 0;
$unpaidCount = 0;
$totalFine = 0.0;
$pendingAmount = 0.0;

foreach ($challans as $challan) {
    $amount = (float) $challan['fine_amount'];
    $totalFine += $amount;

    if (strtolower((string) $challan['status']) === 'paid') {
        $paidCount++;
    } else {
        $unpaidCount++;
        $pendingAmount += $amount;
    }
}

// Status filter for Challans Table
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$statusFilter = in_array($statusFilter, ['all', 'paid', 'unpaid'], true) ? $statusFilter : 'all';

$displayedChallans = $filterActiveVehicle ? $searchedVehicleChallans : $challans;
if ($statusFilter !== 'all') {
    $displayedChallans = array_filter($displayedChallans, function($item) use ($statusFilter) {
        $isPaid = strtolower((string) $item['status']) === 'paid';
        return ($statusFilter === 'paid') ? $isPaid : !$isPaid;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Citizen Dashboard • Smart Traffic Control & e-Challan Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    --panel-soft:#f8fafc;
    --line:#e2e8f0;
    --ink:#0f172a;
    --muted:#64748b;
    --success:#15803d;
    --danger:#dc2626;
    --warning:#b45309;
    --shadow:0 4px 14px rgba(15, 23, 42, 0.08);
}

*{ box-sizing:border-box; }

body{
    margin:0;
    min-height:100vh;
    font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--ink);
    background:#f8fafc;
}

.shell{
    min-height:100vh;
    display:flex;
    flex-direction:column;
}

.topbar{
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f766e 100%);
    color:#fff;
    padding:28px 24px;
    border-bottom: 1px solid #334155;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.15);
}

.topbar-inner{
    max-width:1200px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 14px;
    border-radius:999px;
    background:rgba(37, 99, 235, 0.15);
    border:1px solid rgba(147, 197, 253, 0.4);
    color:#93c5fd;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.08em;
    text-transform:uppercase;
}

.title-block h1{
    font-family: 'Outfit', sans-serif;
    margin:10px 0 8px;
    font-size:36px;
    font-weight:800;
    line-height:1.1;
    background: linear-gradient(135deg, #ffffff 30%, #93c5fd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.02em;
}

.title-block p{
    margin:0;
    max-width:720px;
    color:rgba(226, 232, 240, 0.85);
    font-size:14.5px;
    line-height: 1.5;
}

.profile-card{
    min-width:290px;
    background:rgba(255,255,255,0.98);
    color:var(--ink);
    border-radius:18px;
    padding:18px;
    border: 1px solid #e2e8f0;
    box-shadow:0 4px 14px rgba(15, 23, 42, 0.08);
}

.profile-row{
    display:flex;
    align-items:center;
    gap:14px;
}

.avatar{
    width:52px;
    height:52px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:#2563eb;
    color:#fff;
    font-size: 22px;
    font-weight:800;
    font-family: 'Outfit', sans-serif;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
}

.profile-card strong{
    display:block;
    font-size:16px;
    font-weight: 700;
}

.profile-card small{
    color:var(--muted);
}

.toolbar{
    display:flex;
    gap:10px;
    margin-top:14px;
}

.toolbar a{
    text-decoration:none;
    padding:9px 14px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
    transition: all 0.2s ease;
}

.toolbar .primary{
    background:#2563eb;
    color:#fff;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
}
.toolbar .primary:hover{
    background:#1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
}

.toolbar .secondary{
    background:#ffffff;
    color:#2563eb;
    border:1px solid #bfdbfe;
}
.toolbar .secondary:hover{
    background:#eff6ff;
    border-color:#3b82f6;
    transform: translateY(-1px);
}

.main{
    max-width:1200px;
    width:100%;
    margin:0 auto;
    padding:26px 24px;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:18px;
    margin-bottom:20px;
}

.stat-card,.table-panel,.notice{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    box-shadow:0 4px 12px rgba(15, 23, 42, 0.06);
}

.stat-card{
    padding:20px;
    position:relative;
    overflow:hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.stat-card:hover{
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.12);
    border-color: #2563eb;
}

.stat-card:nth-child(1):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #2563eb, #38bdf8);
}
.stat-card:nth-child(2):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #15803d, #16a34a);
}
.stat-card:nth-child(3):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #dc2626, #ef4444);
}
.stat-card:nth-child(4):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:#0f766e;
}

.label{
    margin:0 0 8px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#64748b;
    font-weight:700;
}

.value{
    margin-top:10px;
    font-size:36px;
    font-weight:800;
    font-family:'Outfit', sans-serif;
    color:#0f172a;
}

.trend,.panel-head p,.notice p,.table-note{
    color:var(--muted);
    font-size:13px;
}

.notice{
    padding:16px 18px;
    margin-bottom:16px;
}

.notice strong{
    display:block;
    margin-bottom:6px;
    color:#0d2238;
}

.table-panel{
    padding:18px;
}

.panel-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    margin-bottom:14px;
}

.panel-head h3{
    margin:0 0 6px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.table-wrap{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
}

th,td{
    padding:13px 12px;
    border-bottom:1px solid #e9eef3;
    text-align:left;
    font-size:14px;
}

th{
    background:#f7fafc;
    color:#55687a;
    text-transform:uppercase;
    letter-spacing:0.08em;
    font-size:11px;
}

tbody tr:hover{
    background:#f5f3ff;
}

.status-badge{
    display:inline-flex;
    align-items:center;
    padding:6px 14px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.06em;
}

.status-paid{
    background:rgba(16, 185, 129, 0.15);
    color:#059669;
    border:1px solid rgba(16, 185, 129, 0.3);
}

.status-unpaid{
    background:rgba(239, 68, 68, 0.15);
    color:#dc2626;
    border:1px solid rgba(239, 68, 68, 0.3);
}

.violation-tag{
    display:inline-flex;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    background:rgba(37, 99, 235, 0.08);
    color:#2563eb;
    border:1px solid rgba(37, 99, 235, 0.2);
}

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:90px;
    padding:9px 14px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-pay{
    background:#2563eb !important;
    color:#ffffff !important;
    box-shadow:0 4px 14px rgba(37, 99, 235, 0.25);
}
.btn-pay:hover{
    background:#1d4ed8 !important;
    transform:translateY(-2px);
    box-shadow:0 6px 18px rgba(37, 99, 235, 0.35);
}

.btn-light{
    background:#ffffff;
    color:#2563eb;
    border:1px solid #bfdbfe;
}
.btn-light:hover{
    background:#eff6ff;
    border-color:#3b82f6;
    transform:translateY(-1px);
}

.table-note{
    margin-top:14px;
    padding:12px 16px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    font-size:13px;
    color:var(--muted);
}

.citizen-meta-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 1200px) {
    .citizen-meta-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .citizen-meta-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1180px){
    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .topbar-inner{
        flex-direction:column;
    }

    .profile-card{
        min-width:0;
        width:100%;
    }
}

@media (max-width: 640px){
    .cards{
        grid-template-columns:1fr;
    }

    .title-block h1{
        font-size:28px;
    }

    .toolbar{
        flex-direction:column;
    }
}

.vehicle-section{
    margin-bottom:24px;
}
.v-details-table-wrap{
    margin-top:16px;
    border-radius:18px;
    border:1px solid var(--line);
    overflow:hidden;
    background:#ffffff;
    box-shadow:var(--shadow);
}
.v-specs-table th, .v-specs-table td{
    font-size:13px;
}
.vehicle-quick-pill{
    transition:all 0.18s ease;
}
.vehicle-quick-pill:hover{
    transform:translateY(-1px);
    box-shadow:0 4px 10px rgba(0,0,0,0.06);
}
.hsrp-pill{
    display:inline-flex;
    align-items:center;
    background:#fff;
    border:1.5px solid #111;
    border-radius:6px;
    padding:2px 8px;
    font-weight:900;
    font-size:13px;
    letter-spacing:1px;
    color:#111;
}
.filter-tabs{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
    margin-top:10px;
}
.filter-tab{
    padding:7px 14px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    text-decoration:none;
    border:1px solid var(--line);
    background:#fff;
    color:var(--navy-900);
    transition:0.18s ease;
}
.filter-tab.active{
    background:var(--navy-900);
    color:#fff;
    border-color:var(--navy-900);
}
.filter-tab:hover{
    transform:translateY(-1px);
}
.vehicle-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));
    gap:16px;
    margin-top:14px;
}
.vehicle-card{
    background:rgba(255,255,255,0.98);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    padding:18px;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    gap:12px;
    position:relative;
    overflow:hidden;
}
.vehicle-card:before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    width:5px;
    height:100%;
    background:linear-gradient(180deg, var(--blue-500), var(--navy-800));
}
.hsrp-box{
    display:inline-flex;
    align-items:center;
    background:#ffffff;
    border:2px solid #111;
    border-radius:8px;
    box-shadow:0 3px 8px rgba(0,0,0,0.10);
    overflow:hidden;
    max-width:fit-content;
}
.hsrp-ind{
    background:#003399;
    color:#fff;
    padding:5px 6px;
    font-size:9px;
    font-weight:900;
    letter-spacing:1px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    border-right:1px solid #111;
    line-height:1;
}
.hsrp-ind span{
    font-size:8px;
    opacity:0.8;
}
.hsrp-no{
    padding:5px 12px;
    font-size:18px;
    font-weight:900;
    letter-spacing:2px;
    color:#111;
    text-transform:uppercase;
}
.vehicle-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
}
.rto-badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:7px;
    background:#eef4fb;
    color:var(--navy-800);
    font-size:11px;
    font-weight:700;
}
.vehicle-metrics{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:8px;
    padding:10px;
    border-radius:12px;
    background:#f4f8fd;
    border:1px solid #dce6f2;
}
.v-metric{
    text-align:center;
}
.v-metric small{
    display:block;
    font-size:11px;
    color:var(--muted);
    margin-bottom:2px;
}
.v-metric strong{
    font-size:15px;
    color:var(--navy-950);
}
.tag-pill{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:4px 9px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
}
.tag-pill.low{ background:rgba(21,128,61,0.12); color:var(--success); }
.tag-pill.med{ background:rgba(183,121,31,0.14); color:#b7791f; }
.tag-pill.high{ background:rgba(179,60,47,0.14); color:var(--danger); }
.tag-pill.clear{ background:rgba(21,128,61,0.14); color:var(--success); }
.tag-pill.due{ background:rgba(179,60,47,0.14); color:var(--danger); }

/* QR Payment Modal Styles */
.qr-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(13, 27, 42, 0.70);
    backdrop-filter:blur(4px);
    z-index:9999;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.qr-modal-card{
    width:min(450px, 100%);
    background:#ffffff;
    border-radius:24px;
    box-shadow:0 24px 60px rgba(0,0,0,0.30);
    overflow:hidden;
    border:1px solid rgba(255,255,255,0.2);
    animation:modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalPop{
    0%{ transform:scale(0.92); opacity:0; }
    100%{ transform:scale(1); opacity:1; }
}
.qr-modal-head{
    background:linear-gradient(135deg, var(--navy-900), var(--navy-950));
    color:#fff;
    padding:18px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.qr-modal-head h3{
    margin:0 0 2px;
    font-size:18px;
    color:#fff;
}
.qr-modal-head p{
    margin:0;
    font-size:12px;
    color:rgba(255,255,255,0.7);
}
.qr-modal-close{
    background:rgba(255,255,255,0.14);
    border:none;
    color:#fff;
    font-size:22px;
    width:32px;
    height:32px;
    border-radius:50%;
    cursor:pointer;
    display:grid;
    place-items:center;
    line-height:1;
}
.qr-modal-close:hover{
    background:rgba(255,255,255,0.28);
}
.qr-modal-body{
    padding:20px 22px;
}
.modal-challan-strip{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 14px;
    border-radius:14px;
    background:#f4f8fd;
    border:1px solid #dce6f2;
    margin-bottom:16px;
}
.modal-ch-badge{
    padding:4px 9px;
    border-radius:8px;
    background:#13273d;
    color:#fff;
    font-size:12px;
    font-weight:800;
}
.qr-box-wrap{
    text-align:center;
    padding:16px;
    border-radius:18px;
    background:#ffffff;
    border:2px solid #e2ebf5;
    margin-bottom:14px;
    display:flex;
    flex-direction:column;
    align-items:center;
}
.qr-code-frame{
    padding:10px;
    background:#fff;
    border:2px solid #0d1b2a;
    border-radius:14px;
    display:inline-block;
}
#qrcodeCanvasModal{
    display:flex;
    align-items:center;
    justify-content:center;
}
#qrcodeCanvasModal img, #qrcodeCanvasModal canvas{
    display:block;
    margin:0 auto;
}
.modal-upi-apps{
    display:flex;
    gap:6px;
    justify-content:center;
    margin-top:8px;
    flex-wrap:wrap;
}
.modal-upi-apps span{
    padding:3px 7px;
    border-radius:6px;
    background:#eef4fc;
    color:#2b435d;
    font-size:10px;
    font-weight:700;
}
.pulse-indicator{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-bottom:14px;
    font-size:12px;
    color:#536a82;
    font-weight:600;
}
.pulse-dot{
    width:8px;
    height:8px;
    border-radius:50%;
    background:#15803d;
    animation:pulse 1.5s infinite;
}
.btn-verify{
    background:linear-gradient(135deg, #15803d, #166534);
    color:#fff;
    box-shadow:0 8px 20px rgba(21, 128, 61, 0.25);
    width:100%;
}

/* Challan Dossier Modal */
.dossier-overlay{
    position:fixed;
    inset:0;
    background:rgba(10, 21, 35, 0.82);
    backdrop-filter:blur(6px);
    z-index:9999;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:16px;
}
.dossier-card{
    background:#ffffff;
    border-radius:24px;
    width:min(900px, 100%);
    max-height:92vh;
    overflow-y:auto;
    box-shadow:0 24px 60px rgba(0,0,0,0.35);
    border:1px solid rgba(255,255,255,0.2);
    animation:modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalPop{
    0%{ transform:scale(0.94); opacity:0; }
    100%{ transform:scale(1); opacity:1; }
}
.dossier-head{
    padding:18px 24px;
    background:linear-gradient(135deg, var(--navy-900), var(--navy-950));
    color:#ffffff;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-top-left-radius:24px;
    border-top-right-radius:24px;
}
.dossier-body{
    padding:24px;
}
.dossier-strip{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:14px;
    padding:14px 18px;
    background:#f4f8fd;
    border:1px solid #d8e2ec;
    border-radius:14px;
    margin-bottom:18px;
}
.dossier-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
    gap:16px;
    margin-bottom:18px;
}
.dossier-info-box{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px 16px;
}
.dossier-info-box small{
    display:block;
    color:var(--muted);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.04em;
    margin-bottom:4px;
}
.dossier-info-box strong{
    color:var(--navy-900);
    font-size:14px;
    display:block;
}
.evidence-gallery{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));
    gap:16px;
    margin-bottom:18px;
}
.evidence-card{
    background:#0d1b2a;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #233b55;
    box-shadow:0 8px 24px rgba(0,0,0,0.18);
}
.evidence-card-header{
    padding:10px 14px;
    background:rgba(255,255,255,0.06);
    color:#cbd5e1;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.05em;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid rgba(255,255,255,0.08);
}
.evidence-frame{
    position:relative;
    width:100%;
    height:210px;
    background:#000000;
    cursor:pointer;
    overflow:hidden;
}
.evidence-frame img{
    width:100%;
    height:100%;
    object-fit:cover;
    transition:transform 0.3s ease;
}
.evidence-frame:hover img{
    transform:scale(1.05);
}
.evidence-hud-bar{
    position:absolute;
    bottom:8px;
    left:8px;
    right:8px;
    display:flex;
    justify-content:space-between;
    gap:6px;
    pointer-events:none;
}
.evidence-hud-tag{
    background:rgba(10, 21, 35, 0.82);
    color:#93c5fd;
    font-family:monospace;
    font-size:10px;
    font-weight:700;
    padding:3px 8px;
    border-radius:6px;
    border:1px solid rgba(56, 189, 248, 0.3);
    backdrop-filter:blur(4px);
}
.evidence-card-footer{
    padding:9px 14px;
    background:rgba(255,255,255,0.04);
    font-size:11px;
    color:#94a3b8;
    display:flex;
    justify-content:space-between;
}
.desc-box{
    background:#f8fafc;
    border-left:4px solid var(--blue-500);
    border-radius:8px;
    padding:12px 16px;
    font-size:13px;
    line-height:1.6;
    color:var(--ink);
    margin-bottom:18px;
}
.evidence-thumb-mini{
    width:54px;
    height:38px;
    object-fit:cover;
    border-radius:6px;
    border:1px solid #cbd5e1;
    cursor:pointer;
    transition:transform 0.15s ease;
}
.evidence-thumb-mini:hover{
    transform:scale(1.15);
    box-shadow:0 4px 10px rgba(0,0,0,0.2);
}
.lightbox-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.92);
    z-index:100000;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.lightbox-box{
    position:relative;
    max-width:92vw;
    max-height:92vh;
}
.lightbox-box img{
    max-width:100%;
    max-height:85vh;
    border-radius:12px;
    box-shadow:0 12px 40px rgba(0,0,0,0.5);
    display:block;
    margin:0 auto;
}
.lightbox-close{
    position:absolute;
    top:-36px;
    right:0;
    background:none;
    border:none;
    color:#ffffff;
    font-size:28px;
    font-weight:700;
    cursor:pointer;
}
/* Table QR Badge */
.table-qr-badge{
    display:inline-flex;
    flex-direction:column;
    align-items:center;
    padding:5px 6px;
    background:#ffffff;
    border:1.5px solid #dce6f2;
    border-radius:12px;
    cursor:pointer;
    transition:all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow:0 2px 6px rgba(15,23,42,0.06);
    user-select:none;
}
.table-qr-badge:hover{
    transform:translateY(-2px) scale(1.04);
    border-color:var(--blue-500);
    box-shadow:0 8px 18px rgba(59,130,246,0.18);
}
.row-qr-frame{
    width:56px;
    height:56px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff;
    border-radius:6px;
    overflow:hidden;
}
.row-qr-frame img, .row-qr-frame canvas{
    display:block;
    max-width:100%;
    max-height:100%;
}
.row-qr-label{
    font-size:9.5px;
    font-weight:700;
    margin-top:3px;
    color:var(--navy-900);
    letter-spacing:0.02em;
    text-transform:uppercase;
}
.btn-receipt{
    background:#ffffff;
    color:#1d4ed8;
    border:1px solid #bfdbfe;
    font-weight:700;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:4px;
    border-radius:8px;
    padding:7px 11px;
    font-size:12px;
    transition:all 0.15s ease;
}
.btn-receipt:hover{
    background:#eff6ff;
    border-color:#3b82f6;
    color:#1e40af;
}
.dossier-section-title{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:12.5px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.04em;
    color:var(--navy-900);
    margin:18px 0 10px;
    padding-bottom:6px;
    border-bottom:1px solid #e2e8f0;
}
.dossier-vehicle-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:10px;
    margin-bottom:16px;
}
.dossier-vehicle-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:10px 12px;
}
.dossier-vehicle-card small{
    display:block;
    color:var(--muted);
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.04em;
    margin-bottom:3px;
}
.dossier-vehicle-card strong{
    display:block;
    color:var(--navy-900);
    font-size:12.5px;
}
.dossier-qr-wrap{
    display:flex;
    gap:18px;
    align-items:center;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px 20px;
    margin-bottom:18px;
    flex-wrap:wrap;
}
.dossier-qr-box{
    padding:8px;
    background:#ffffff;
    border:2px solid #0d1b2a;
    border-radius:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
#dossierQrCanvas{
    width:120px;
    height:120px;
    display:flex;
    align-items:center;
    justify-content:center;
}
#dossierQrCanvas img, #dossierQrCanvas canvas{
    display:block;
    max-width:100%;
    max-height:100%;
}
</style>
<link rel="stylesheet" href="kerala-theme.css">
<script src="qrcode.min.js"></script>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="title-block">
                <div class="eyebrow">
                    <span style="width:6px; height:6px; border-radius:50%; background:#0f766e; box-shadow:0 0 8px rgba(15, 118, 110, 0.6); display:inline-block;"></span>
                    <span>SMART TRAFFIC CONTROL • CITIZEN DESK</span>
                </div>
                <h1>Citizen e-Challan & Monitoring Portal</h1>
                <p>Real-time vehicle citation registry, dual-camera evidence verification, and instant UPI fine settlement under Central Motor Vehicles Rules.</p>
            </div>

            <div class="profile-card">
                <div class="profile-row">
                    <div class="avatar"><?php echo e($userInitial); ?></div>
                    <div>
                        <strong>Registered Citizen</strong>
                        <small style="font-weight:800; color:var(--blue-500); font-size:12px;">User ID: #<?php echo e((string) $userId); ?></small><br>
                        <small><?php echo e($userEmail); ?></small><br>
                        <?php if ($userVehicle !== '') { ?>
                        <small>Vehicle: <strong style="color:var(--navy-900);"><?php echo e($userVehicle); ?></strong></small><br>
                        <?php } ?>
                        <small>Role: Citizen/User</small>
                    </div>
                </div>
                <div class="toolbar">
                    <?php if ($pendingAmount > 0) { ?>
                    <button type="button" class="btn btn-pay" style="padding:8px 14px; font-size:12px; gap:6px; cursor:pointer;" onclick="showCitizenQrModal('<?php echo e((string) $userId); ?>', '<?php echo e($userEmail); ?>', '<?php echo e($userVehicle); ?>', '<?php echo e((string) $pendingAmount); ?>', <?php echo (int) $unpaidCount; ?>)">
                        📲 Show Payment QR
                    </button>
                    <?php } ?>
                    <a href="logout.php" class="primary">Logout</a>
                    <a href="user_login.php" class="secondary">Switch Account</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <!-- CITIZEN PROFILE CARD (POINT 7) -->
        <section class="citizen-profile-banner" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:22px; margin-bottom:20px; box-shadow:0 4px 16px rgba(15,23,42,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid #e2e8f0;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:54px; height:54px; border-radius:14px; background:linear-gradient(135deg, #1d4ed8, #0f172a); color:#fff; display:grid; place-items:center; font-size:24px; font-weight:800; font-family:'Outfit',sans-serif; box-shadow:0 4px 12px rgba(29,78,216,0.25);">
                        <?php echo e($userInitial); ?>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <h2 style="margin:0; font-size:22px; font-weight:800; color:#0f172a; font-family:'Outfit',sans-serif;"><?php echo e($userName); ?></h2>
                            <span style="font-size:11px; font-weight:800; background:#eff6ff; color:#1d4ed8; padding:3px 9px; border-radius:999px; border:1px solid #bfdbfe;">
                                #CIT-<?php echo str_pad((string)$userId, 4, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>
                        <p style="margin:3px 0 0; font-size:13px; color:#64748b;"><?php echo e($userEmail); ?> &bull; Verified Citizen Account</p>
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center;">
                    <?php if ($pendingAmount > 0) { ?>
                    <button type="button" class="btn btn-pay" style="padding:10px 18px; font-size:13px; font-weight:800;" onclick="showCitizenQrModal('<?php echo e((string) $userId); ?>', '<?php echo e($userEmail); ?>', '<?php echo e($activeVehicleNo); ?>', '<?php echo e((string) $pendingAmount); ?>', <?php echo (int) $unpaidCount; ?>)">
                        📲 Pay Now / QR Code
                    </button>
                    <?php } ?>
                    <a href="logout.php" class="btn btn-light" style="padding:10px 16px; font-size:13px; font-weight:700;">Logout</a>
                </div>
            </div>

            <!-- 7 CITIZEN PROFILE ATTRIBUTES (POINT 7) -->
            <div class="citizen-meta-grid">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">User Name</small>
                    <strong style="color:#0f172a; font-size:14px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo e($userName); ?></strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">User ID</small>
                    <strong style="color:#1d4ed8; font-size:14px; display:block; font-family:monospace;">#CIT-<?php echo str_pad((string)$userId, 4, '0', STR_PAD_LEFT); ?></strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Vehicle Number</small>
                    <strong style="color:#0f172a; font-size:14px; display:block; font-family:monospace;"><?php echo e($activeVehicleDetails['formatted_number'] ?: $activeVehicleNo); ?></strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Vehicle Type</small>
                    <strong style="color:#0f172a; font-size:13px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo e($activeVehicleDetails['vehicle_class']); ?></strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Challan Count</small>
                    <strong style="color:#0f172a; font-size:16px; font-weight:800; font-family:'Outfit',sans-serif; display:block;"><?php echo number_format($totalChallans); ?> Cases</strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Pending Amount</small>
                    <strong style="color:<?php echo $pendingAmount > 0 ? '#b45309' : '#047857'; ?>; font-size:16px; font-weight:800; font-family:'Outfit',sans-serif; display:block;">
                        ₹<?php echo number_format($pendingAmount, 2); ?>
                    </strong>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                    <small style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Payment Status</small>
                    <?php if ($pendingAmount <= 0) { ?>
                        <span class="status-badge status-paid" style="padding:4px 8px; font-size:11px;">✓ Cleared</span>
                    <?php } else { ?>
                        <span class="status-badge status-pending" style="padding:4px 8px; font-size:11px;">● <?php echo $unpaidCount; ?> Due</span>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Total Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">All citations linked to your vehicles.</div>
            </div>
            <div class="stat-card">
                <div class="label">Paid Challans</div>
                <div class="value"><?php echo e((string) $paidCount); ?></div>
                <div class="trend">Cleared and settled citations.</div>
            </div>
            <div class="stat-card">
                <div class="label">Pending Challans</div>
                <div class="value"><?php echo e((string) $unpaidCount); ?></div>
                <div class="trend">Cases awaiting settlement.</div>
            </div>
            <div class="stat-card">
                <div class="label">Pending Amount</div>
                <div class="value">₹<?php echo e((string) $pendingAmount); ?></div>
                <div class="trend">Total fine due for payment.</div>
                <?php if ($pendingAmount > 0) { ?>
                <button type="button" class="btn btn-pay" style="margin-top:10px; width:100%; font-size:12px; padding:8px 10px; gap:6px; cursor:pointer;" onclick="showCitizenQrModal('<?php echo e((string) $userId); ?>', '<?php echo e($userEmail); ?>', '<?php echo e($userVehicle); ?>', '<?php echo e((string) $pendingAmount); ?>', <?php echo (int) $unpaidCount; ?>)">
                    📲 Show Payment QR (₹<?php echo e((string) $pendingAmount); ?>)
                </button>
                <?php } ?>
            </div>
        </section>

        <!-- REGISTERED VEHICLE DETAILS SECTION -->
        <section class="table-panel vehicle-section">
            <div class="panel-head">
                <div>
                    <h3>Registered Vehicle Details & Specifications</h3>
                    <p>Enter any vehicle number or select from your registered vehicles to view official RTO records, specifications, and compliance breakdown in an organized table.</p>
                </div>
                <span class="eyebrow" style="background:#eef4fb; color:var(--navy-800); border:1px solid #d0deec;">
                    <?php echo count($vehicleNumbers); ?> Registered Vehicle<?php echo count($vehicleNumbers) !== 1 ? 's' : ''; ?>
                </span>
            </div>

            <!-- Vehicle Number Entry Form -->
            <div style="margin-top:14px;">
                <form method="GET" action="user_dashboard.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <div style="flex:1; min-width:260px;">
                        <input type="text" name="vehicle_no" value="<?php echo e($activeVehicleNo); ?>" placeholder="Enter Vehicle Number (e.g. KL07AB1234 or DL01AB1234)" style="width:100%; padding:13px 16px; border-radius:12px; border:1px solid var(--line); font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:1px; outline:none;" required>
                    </div>
                    <button type="submit" class="btn btn-pay" style="padding:13px 22px; font-size:13px; font-weight:800; cursor:pointer;">
                        🔍 Fetch Vehicle Details
                    </button>
                    <?php if ($filterActiveVehicle) { ?>
                    <a href="user_dashboard.php" class="btn btn-light" style="padding:13px 18px; text-decoration:none; font-size:13px;">Reset to My Vehicle</a>
                    <?php } ?>
                </form>

                <?php if (!empty($vehicleNumbers)) { ?>
                <div style="display:flex; align-items:center; gap:8px; margin-top:12px; flex-wrap:wrap;">
                    <span style="font-size:12px; color:var(--muted); font-weight:700;">Quick Select Your Vehicles:</span>
                    <?php foreach ($vehicleNumbers as $vQuick) { ?>
                    <?php $isActive = ($activeVehicleNo === $vQuick); ?>
                    <a href="user_dashboard.php?vehicle_no=<?php echo urlencode($vQuick); ?>" class="vehicle-quick-pill" style="padding:6px 14px; border-radius:8px; font-size:12px; font-weight:800; text-decoration:none; border:1.5px solid <?php echo $isActive ? '#2563eb' : 'var(--line)'; ?>; background:<?php echo $isActive ? '#2563eb' : '#ffffff'; ?>; color:<?php echo $isActive ? '#ffffff' : 'var(--navy-900)'; ?>; box-shadow:<?php echo $isActive ? '0 4px 12px rgba(37, 99, 235, 0.25)' : 'none'; ?>;">
                        🚗 <?php echo e($vQuick); ?>
                    </a>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>

            <!-- VEHICLE DETAILS TABLE -->
            <div class="v-details-table-wrap">
                <div style="padding:16px 20px; background:#0f172a; border-bottom:1px solid #334155; color:#ffffff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-radius:16px 16px 0 0;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div class="hsrp-box" style="box-shadow:none;">
                            <div class="hsrp-ind"><span>🇮🇳</span>IND</div>
                            <div class="hsrp-no"><?php echo e($activeVehicleDetails['formatted_number']); ?></div>
                        </div>
                        <div>
                            <strong style="font-size:17px; display:block; color:#ffffff; font-family:'Outfit',sans-serif;"><?php echo e($activeVehicleDetails['make'] . ' ' . $activeVehicleDetails['model']); ?></strong>
                            <small style="color:rgba(226,232,240,0.85); font-size:12px;">🏛️ <?php echo e($activeVehicleDetails['rto_authority']); ?></small>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span class="tag-pill <?php echo $vIsCompliant ? 'clear' : 'due'; ?>">
                            <?php echo $vIsCompliant ? '● Clear / Compliant' : '● Action Due'; ?>
                        </span>
                        <span class="tag-pill <?php echo strtolower($vRiskLevel) === 'high' ? 'high' : (strtolower($vRiskLevel) === 'medium' ? 'med' : 'low'); ?>">
                            <?php echo e($vRiskLevel); ?> Risk
                        </span>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="v-specs-table" style="width:100%; border-collapse:collapse;">
                        <tbody>
                            <tr>
                                <th style="width:22%; background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Registration Number</th>
                                <td style="width:28%; padding:12px 16px; border-bottom:1px solid #eef3f8;"><strong><?php echo e($activeVehicleDetails['formatted_number']); ?></strong></td>
                                <th style="width:22%; background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Registered Citizen</th>
                                <td style="width:28%; padding:12px 16px; border-bottom:1px solid #eef3f8;"><strong style="color:#2563eb;">User ID #<?php echo e((string) $userId); ?></strong> (<?php echo e($userEmail); ?>)</td>
                            </tr>
                            <tr>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Vehicle Class & Body</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8;"><?php echo e($activeVehicleDetails['vehicle_class']); ?> • <?php echo e($activeVehicleDetails['vehicle_body']); ?></td>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Fuel Type & Color</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8;"><?php echo e($activeVehicleDetails['fuel_type']); ?> • <?php echo e($activeVehicleDetails['color']); ?></td>
                            </tr>
                            <tr>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Engine & Chassis</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8; font-family:monospace;"><?php echo e($activeVehicleDetails['engine_no']); ?> / <?php echo e($activeVehicleDetails['chassis_no']); ?></td>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Registration Date</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8;"><?php echo e($activeVehicleDetails['registration_date']); ?></td>
                            </tr>
                            <tr>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">Insurance Status</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8;">
                                    <span class="status-badge status-paid">✓ <?php echo e($activeVehicleDetails['insurance_status']); ?></span><br>
                                    <small style="color:var(--muted); font-size:11px;"><?php echo e($activeVehicleDetails['insurance_policy']); ?></small>
                                </td>
                                <th style="background:#f8fafc; padding:12px 16px; border-bottom:1px solid #eef3f8; color:#4a6279; font-weight:700;">PUCC Certificate</th>
                                <td style="padding:12px 16px; border-bottom:1px solid #eef3f8;">
                                    <span class="status-badge status-paid">✓ <?php echo e($activeVehicleDetails['pucc_status']); ?></span><br>
                                    <small style="color:var(--muted); font-size:11px;"><?php echo e($activeVehicleDetails['pucc_number']); ?></small>
                                </td>
                            </tr>
                            <tr>
                                <th style="background:#f8fafc; padding:12px 16px; color:#4a6279; font-weight:700;">Vehicle Challan Status</th>
                                <td colspan="3" style="padding:12px 16px;">
                                    <div style="display:flex; gap:18px; flex-wrap:wrap; align-items:center;">
                                        <span>Total Citations: <strong><?php echo e((string) $vCases); ?></strong></span>
                                        <span>Cleared: <strong style="color:var(--success);"><?php echo e((string) $vPaid); ?></strong></span>
                                        <span>Pending: <strong style="color:var(--danger);"><?php echo e((string) $vUnpaid); ?></strong></span>
                                        <span>Pending Fine: <strong style="color:var(--danger); font-size:15px;">₹<?php echo number_format($vPendingFine, 2); ?></strong></span>
                                        <?php if ($vPendingFine > 0) { ?>
                                        <button type="button" class="btn btn-pay" style="padding:6px 14px; font-size:12px; cursor:pointer;" onclick="showVehicleQrModal('<?php echo e((string) $userId); ?>', '<?php echo e($activeVehicleNo); ?>', '<?php echo e((string) $vPendingFine); ?>', <?php echo (int) $vUnpaid; ?>)">
                                            📲 Show Payment QR
                                        </button>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- COMPLETE CHALLANS DISPLAY SECTION -->
        <section class="table-panel">
            <div class="panel-head" style="flex-wrap:wrap; gap:12px;">
                <div>
                    <h3>Challans Associated with Vehicle</h3>
                    <p>Complete official challan records including challan number, date, violation type, fine amount, and payment status.</p>
                </div>
                <div class="filter-tabs">
                    <a href="user_dashboard.php?vehicle_no=<?php echo urlencode($activeVehicleNo); ?>&status=all" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All (<?php echo count($targetVehicleChallans); ?>)</a>
                    <a href="user_dashboard.php?vehicle_no=<?php echo urlencode($activeVehicleNo); ?>&status=unpaid" class="filter-tab <?php echo $statusFilter === 'unpaid' ? 'active' : ''; ?>">Unpaid (<?php echo $vUnpaid; ?>)</a>
                    <a href="user_dashboard.php?vehicle_no=<?php echo urlencode($activeVehicleNo); ?>&status=paid" class="filter-tab <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">Paid (<?php echo $vPaid; ?>)</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Challan Number</th>
                            <th>Date & Time</th>
                            <th>Vehicle No</th>
                            <th>Violation & Details</th>
                            <th>Fine Amount</th>
                            <th>Evidence Photos</th>
                            <th>Challan QR</th>
                            <th>Challan Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayedChallans)) { ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:24px; color:var(--muted); font-size:14px;">
                                No challan records found matching this vehicle or filter selection.
                            </td>
                        </tr>
                        <?php } else { ?>
                        <?php foreach ($displayedChallans as $row) { ?>
                        <?php
                            $isPaid = strtolower((string) $row['status']) === 'paid';
                            $vLower = strtolower((string) $row['violation']);
                            $vIcon = '⚠️';
                            if (strpos($vLower, 'helmet') !== false) $vIcon = '🪖';
                            elseif (strpos($vLower, 'speed') !== false) $vIcon = '⚡';
                            elseif (strpos($vLower, 'signal') !== false || strpos($vLower, 'red') !== false) $vIcon = '🚦';
                            elseif (strpos($vLower, 'mobile') !== false || strpos($vLower, 'phone') !== false) $vIcon = '📱';
                            elseif (strpos($vLower, 'belt') !== false) $vIcon = '🛡️';
                            elseif (strpos($vLower, 'triple') !== false) $vIcon = '👥';

                            $dateDisplay = !empty($row['violation_date'])
                                ? date('d M Y, h:i A', strtotime($row['violation_date']))
                                : (!empty($row['created_at']) ? date('d M Y, h:i A', strtotime($row['created_at'])) : date('d M Y, h:i A'));

                            $chNo = !empty($row['challan_no']) ? $row['challan_no'] : ('KL-CHN-' . date('Y') . '-' . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT));
                            // Fetch rich vehicle specifications, statutory provisions, and violation-specific evidence
                            $vDetails = getVehicleDetails((string) $row['vehicle_no'], (string) $row['violation']);
                            $violLegal = getViolationLegalDetails((string) $row['violation'], (float) $row['fine_amount'], (string) $row['vehicle_no']);
                            $evData = getChallanEvidence((string) $row['vehicle_no'], (string) $row['violation'], (int) $row['id'], $dateDisplay);

                            $evImg1 = !empty($row['evidence_photo']) ? $row['evidence_photo'] : $evData['front_image'];
                            $evImg2 = !empty($row['evidence_photo_2']) ? $row['evidence_photo_2'] : $evData['side_image'];
                            $evLocation = !empty($row['location']) ? $row['location'] : $evData['location'];
                            $evCamera = !empty($row['camera_id']) ? $row['camera_id'] : $evData['front_camera_id'];
                            $evOfficer = !empty($row['officer_name']) ? $row['officer_name'] : 'Motor Vehicles Inspector (MVI) / Authorized Digital Signatory';
                            $evDueDate = !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : date('d M Y', strtotime('+60 days'));
                            $evDesc = !empty($row['violation_desc']) ? $row['violation_desc'] : $violLegal['description'];

                            // Comprehensive JSON encode for Dossier Modal
                            $dossierJson = htmlspecialchars(json_encode([
                                'id' => (string) $row['id'],
                                'challan_no' => $chNo,
                                'vehicle_no' => (string) $row['vehicle_no'],
                                'violation' => (string) $row['violation'],
                                'violation_desc' => $evDesc,
                                'fine_amount' => (string) $row['fine_amount'],
                                'status' => (string) $row['status'],
                                'violation_date' => $dateDisplay,
                                'evidence_photo' => $evImg1,
                                'evidence_photo_2' => $evImg2,
                                'location' => $evLocation,
                                'camera_id' => $evCamera,
                                'officer_name' => $evOfficer,
                                'due_date' => $evDueDate,
                                'is_paid' => $isPaid,
                                'vehicle_make_model' => $vDetails['make'] . ' ' . $vDetails['model'],
                                'vehicle_class' => $vDetails['vehicle_class'],
                                'vehicle_color' => $vDetails['color'],
                                'vehicle_fuel' => $vDetails['fuel_type'],
                                'engine_no' => $vDetails['engine_no'],
                                'chassis_no' => $vDetails['chassis_no'],
                                'rto_authority' => $vDetails['rto_authority'],
                                'insurance_policy' => $vDetails['insurance_policy'],
                                'pucc_status' => $vDetails['pucc_status'],
                                'statutory_section' => $violLegal['statutory_section'],
                                'cmvr_rule' => $violLegal['cmvr_rule'],
                                'radar_telemetry' => $violLegal['radar_telemetry'],
                                'prohibited_act' => $violLegal['prohibited_act'],
                                'gps_coordinates' => $evData['gps_coordinates'],
                                'front_camera_id' => $evData['front_camera_id'],
                                'anpr_score' => $evData['anpr_score'],
                                'receipt_url' => 'receipt.php?id=' . $row['id'],
                                'pay_url' => 'pay.php?id=' . $row['id'],
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--navy-900); font-size:13px;">#CH-<?php echo e((string) $row['id']); ?></strong><br>
                                <small style="color:var(--muted); font-size:11px; font-family:monospace;"><?php echo e($chNo); ?></small>
                            </td>
                            <td style="color:#334d65; font-size:13px;"><?php echo e($dateDisplay); ?></td>
                            <td>
                                <div class="hsrp-pill">
                                    <span class="hsrp-pill-ind">IND</span>
                                    <span class="hsrp-pill-no"><?php echo e($row['vehicle_no']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="violation-tag"><?php echo $vIcon . ' ' . e($row['violation']); ?></span>
                                <div style="font-size:12px; color:#475569; margin-top:4px; max-width:260px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;" title="<?php echo e($evDesc); ?>">
                                    <?php echo e($evDesc); ?>
                                </div>
                            </td>
                            <td><strong style="color:var(--navy-900); font-size:14px;">₹<?php echo number_format((float) $row['fine_amount'], 2); ?></strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <img src="<?php echo e($evImg1); ?>" class="evidence-thumb-mini" alt="Front Camera" title="Click to enlarge Front Camera" onclick="openImageZoom('<?php echo e($evImg1); ?>', 'Front Camera Evidence • <?php echo e($row['vehicle_no']); ?>')">
                                    <img src="<?php echo e($evImg2); ?>" class="evidence-thumb-mini" alt="Lateral Profile" title="Click to enlarge Lateral Camera" onclick="openImageZoom('<?php echo e($evImg2); ?>', 'Lateral Profile Evidence • <?php echo e($row['vehicle_no']); ?>')">
                                </div>
                            </td>
                            <td>
                                <div class="table-qr-badge" data-row-qr="1" data-id="<?php echo e((string) $row['id']); ?>" data-vno="<?php echo e($row['vehicle_no']); ?>" data-amt="<?php echo e((string) $row['fine_amount']); ?>" data-paid="<?php echo $isPaid ? '1' : '0'; ?>" onclick="openQrModal('<?php echo e((string) $row['id']); ?>', '<?php echo e($row['vehicle_no']); ?>', '<?php echo e($row['violation']); ?>', '<?php echo e((string) $row['fine_amount']); ?>', '<?php echo $isPaid ? 'paid' : 'unpaid'; ?>')" title="Click to view & scan unique QR code for Challan #<?php echo e((string) $row['id']); ?>">
                                    <div id="rowQr_<?php echo e((string) $row['id']); ?>" class="row-qr-frame"></div>
                                    <div class="row-qr-label"><?php echo $isPaid ? '✓ Verified' : '📲 Scan QR'; ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                    <?php echo $isPaid ? '✓ Paid' : '● Unpaid'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="btn btn-light" style="font-size:12px; padding:7px 11px; font-weight:700; color:#1d4ed8; border:1px solid #cbd5e1;" 
                                        data-dossier="<?php echo $dossierJson; ?>"
                                        onclick="openDossierModalFromBtn(this)">
                                        View Details
                                    </button>
                                    <a href="receipt.php?id=<?php echo e((string) $row['id']); ?>" target="_blank" class="btn-receipt" title="Download Official Receipt (PDF)">
                                        Download Receipt
                                    </a>
                                    <?php if (!$isPaid) { ?>
                                    <button type="button" class="btn btn-pay" style="padding:7px 11px; font-size:12px; font-weight:700;"
                                        onclick="openQrModal('<?php echo e((string) $row['id']); ?>', '<?php echo e($row['vehicle_no']); ?>', '<?php echo e($row['violation']); ?>', '<?php echo e((string) $row['fine_amount']); ?>', 'unpaid')">
                                        Pay Now / QR Code
                                    </button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="table-note">
                <?php if (count($displayedChallans) === 0) { ?>
                No challans have been assigned to your account or this vehicle yet.
                <?php } else { ?>
                This table is securely connected to your registered vehicle record and updates automatically after payment.
                <?php } ?>
            </div>
        </section>

        <!-- UNIFIED BOTTOM NAVIGATION -->
        <div class="portal-bottom-nav">
            <a href="index.php" class="portal-nav-btn portal-nav-btn-home">← Back to Home Page</a>
        </div>
    </main>
</div>

<!-- UPI QR PAYMENT MODAL -->
<div id="qrModal" class="qr-modal-overlay" style="display:none;" onclick="if(event.target===this) closeQrModal()">
    <div class="qr-modal-card">
        <div class="qr-modal-head">
            <div>
                <h3 id="modalTitle">Challan QR Payment</h3>
                <p id="modalSubtitle">Scan with any UPI app to pay</p>
            </div>
            <button type="button" class="qr-modal-close" onclick="closeQrModal()">&times;</button>
        </div>
        <div class="qr-modal-body">
            <div class="modal-challan-strip">
                <div>
                    <span id="modalChallanBadge" class="modal-ch-badge">#CH0</span>
                    <span id="modalVehiclePlate" class="plate-tag" style="margin-left:6px; font-size:13px;"></span>
                </div>
                <div style="text-align:right;">
                    <small id="modalViolation" style="display:block; color:var(--muted); font-size:11px;"></small>
                    <strong id="modalFineAmount" style="font-size:20px; color:var(--danger);">₹0.00</strong>
                </div>
            </div>

            <div class="qr-box-wrap">
                <div class="qr-code-frame">
                    <div id="qrcodeCanvasModal"></div>
                </div>
                <div style="font-size:13px; font-weight:700; color:var(--navy-900); margin-top:10px;">
                    Scan with Google Pay, PhonePe, Paytm or BHIM
                </div>
                <div class="modal-upi-apps">
                    <span>GPay</span>
                    <span>PhonePe</span>
                    <span>Paytm</span>
                    <span>BHIM</span>
                    <span>Any UPI</span>
                </div>
            </div>

            <div class="pulse-indicator">
                <div class="pulse-dot"></div>
                <span id="modalActiveText">Active payment session • Verify after scanning</span>
            </div>

            <div id="modalPayFormWrap">
                <form method="POST" action="pay.php" id="modalPayForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="modalChallanId" value="0">
                    <button type="submit" class="btn btn-verify" style="margin-bottom:8px;">✓ I HAVE PAID / VERIFY PAYMENT</button>
                </form>
            </div>

            <button type="button" class="btn btn-light" style="width:100%;" onclick="closeQrModal()">Close</button>
        </div>
    </div>
</div>

<!-- OFFICIAL CHALLAN & EVIDENCE DOSSIER MODAL -->
<div id="dossierModal" class="dossier-overlay" style="display:none;" onclick="if(event.target===this) closeDossierModal()">
    <div class="dossier-card">
        <div class="dossier-head">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:18px;">🛡️</span>
                    <strong style="font-size:16px; letter-spacing:0.5px;">Traffic Enforcement Directorate • Citation & Evidence Dossier</strong>
                </div>
                <small style="color:rgba(255,255,255,0.7); font-size:11px;">Automated Intelligent Traffic Enforcement System (ITES Tier-3 AI Enforced)</small>
            </div>
            <button type="button" class="qr-modal-close" style="color:#fff;" onclick="closeDossierModal()">&times;</button>
        </div>

        <div class="dossier-body">
            <!-- Strip: Challan No, Plate, Status -->
            <div class="dossier-strip">
                <div>
                    <span id="dosChallanBadge" class="modal-ch-badge">#CH-0</span>
                    <span id="dosChallanNo" style="margin-left:8px; font-size:13px; font-family:monospace; font-weight:800; color:var(--navy-900);"></span>
                </div>
                <div>
                    <div class="hsrp-pill">
                        <span class="hsrp-pill-ind">IND</span>
                        <span id="dosVehiclePlate" class="hsrp-pill-no"></span>
                    </div>
                </div>
                <div>
                    <span id="dosStatusBadge" class="status-badge"></span>
                </div>
            </div>

            <!-- SECTION 1: Complete Vehicle Particulars (RC Record) -->
            <div class="dossier-section-title">
                <span>🚗</span> Vehicle Particulars (RC Record & Registration Details)
            </div>
            <div class="dossier-vehicle-grid">
                <div class="dossier-vehicle-card">
                    <small>Make & Model</small>
                    <strong id="dosVehMakeModel">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Category & Class</small>
                    <strong id="dosVehClass">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Color & Fuel Type</small>
                    <strong id="dosVehColorFuel">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Engine Number</small>
                    <strong id="dosVehEngine" style="font-family:monospace;">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Chassis (VIN) Number</small>
                    <strong id="dosVehChassis" style="font-family:monospace;">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Registering Authority (RTO)</small>
                    <strong id="dosVehRto">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Insurance Policy Status</small>
                    <strong id="dosVehInsurance" style="color:#047857;">-</strong>
                </div>
                <div class="dossier-vehicle-card">
                    <small>Pollution (PUCC) Status</small>
                    <strong id="dosVehPucc" style="color:#047857;">-</strong>
                </div>
            </div>

            <!-- SECTION 2: Challan & Enforcement Particulars Grid -->
            <div class="dossier-section-title">
                <span>📋</span> Challan & Enforcement Particulars
            </div>
            <div class="dossier-grid">
                <div class="dossier-info-box">
                    <small>Violation Date & Time</small>
                    <strong id="dosViolationDate">-</strong>
                </div>
                <div class="dossier-info-box">
                    <small>Total Fine Amount</small>
                    <strong id="dosFineAmount" style="color:var(--danger); font-size:17px;">₹0.00</strong>
                </div>
                <div class="dossier-info-box">
                    <small>Corridor & Detection Location</small>
                    <strong id="dosLocation">-</strong>
                </div>
                <div class="dossier-info-box">
                    <small>GPS Coordinates</small>
                    <strong id="dosGps" style="font-family:monospace;">-</strong>
                </div>
                <div class="dossier-info-box">
                    <small>Camera / Radar Node</small>
                    <strong id="dosCameraNode">-</strong>
                </div>
                <div class="dossier-info-box">
                    <small>Issuing / Verifying Officer</small>
                    <strong id="dosOfficer">-</strong>
                </div>
                <div class="dossier-info-box">
                    <small>Statutory Payment Due Date</small>
                    <strong id="dosDueDate">-</strong>
                </div>
            </div>

            <!-- SECTION 3: Detailed Violation & Statutory Provisions -->
            <div class="dossier-section-title">
                <span>⚖️</span> Traffic Violation & Statutory Provisions
            </div>
            <div style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">
                    <strong id="dosStatuteSection" style="font-size:12px; color:#1e40af; font-family:monospace;"></strong>
                    <span id="dosViolationType" class="violation-tag" style="font-size:12px;"></span>
                </div>
                <div id="dosViolationDesc" class="desc-box"></div>
                <div id="dosTelemetryBox" style="font-size:11px; color:#475569; background:#f1f5f9; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; margin-top:6px;"></div>
            </div>

            <!-- SECTION 4: Photographic Evidence Gallery: Dual High-Definition Cameras -->
            <div class="dossier-section-title">
                <span>📸</span> Photographic & Sensor Evidence (AI-Captured CCTV Enforcement Grid)
            </div>
            <div style="margin-bottom:18px;">
                <div class="evidence-gallery">
                    <!-- Photo 1: Front ANPR View -->
                    <div class="evidence-card">
                        <div class="evidence-card-header">
                            <span>🔍 FRONT OPTICAL CAMERA / ANPR CAPTURE</span>
                            <span style="color:#93c5fd;" id="dosAnprScore">CONFIDENCE: 99.4%</span>
                        </div>
                        <div class="evidence-frame" onclick="openImageZoom(document.getElementById('dosImg1').src, 'Front Optical Camera / Plate OCR Match')">
                            <img id="dosImg1" src="" alt="Front View Evidence">
                            <div class="evidence-hud-bar">
                                <span id="dosHud1" class="evidence-hud-tag">OCR: MATCHED</span>
                                <span class="evidence-hud-tag">AI VERIFIED</span>
                            </div>
                        </div>
                        <div class="evidence-card-footer">
                            <span id="dosCameraId">Node: Primary ANPR Cam</span>
                            <span>Front View (Click to Zoom)</span>
                        </div>
                    </div>

                    <!-- Photo 2: Lateral / Context View -->
                    <div class="evidence-card">
                        <div class="evidence-card-header">
                            <span>🚗 LATERAL CCTV / DOPPLER RADAR VIEW</span>
                            <span style="color:#93c5fd;">ENFORCEMENT VERIFIED</span>
                        </div>
                        <div class="evidence-frame" onclick="openImageZoom(document.getElementById('dosImg2').src, 'Lateral Corridor / Context Camera Capture')">
                            <img id="dosImg2" src="" alt="Side View Evidence">
                            <div class="evidence-hud-bar">
                                <span class="evidence-hud-tag">CORRIDOR CAMERA</span>
                                <span class="evidence-hud-tag">OFFENSE CONFIRMED</span>
                            </div>
                        </div>
                        <div class="evidence-card-footer">
                            <span>Lateral Profile Sensor</span>
                            <span>Roadside Context (Click to Zoom)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 5: Unique Challan Payment / Verification QR Code -->
            <div class="dossier-section-title">
                <span>📲</span> Challan Payment & Official Verification QR Code
            </div>
            <div class="dossier-qr-wrap">
                <div class="dossier-qr-box">
                    <div id="dossierQrCanvas"></div>
                </div>
                <div style="flex:1; min-width:240px;">
                    <div id="dosQrTitle" style="font-weight:800; font-size:15px; color:var(--navy-900); margin-bottom:4px;">Unique Challan QR Code</div>
                    <p id="dosQrSubtitle" style="margin:0 0 10px; font-size:12px; color:var(--muted); line-height:1.5;">Scan with any UPI app (Google Pay, PhonePe, Paytm, BHIM) or mobile camera to access your challan and complete payment.</p>
                    <div class="modal-upi-apps" style="justify-content:flex-start; margin-bottom:12px;">
                        <span>GPay</span>
                        <span>PhonePe</span>
                        <span>Paytm</span>
                        <span>BHIM</span>
                        <span>Any UPI</span>
                    </div>
                    <div id="dosQrFormWrap"></div>
                </div>
            </div>

            <!-- Action Toolbar in Modal -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:10px; padding-top:16px; border-top:1px solid #e2e8f0;">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <a id="dosReceiptBtn" href="#" target="_blank" class="btn-receipt" style="padding:10px 18px; font-size:13px; font-weight:800; text-decoration:none;">
                        🖨️ Download / Print Official Receipt (PDF)
                    </a>
                    <div id="dosPayActions" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;"></div>
                </div>
                <button type="button" class="btn btn-light" style="padding:10px 20px; font-weight:700;" onclick="closeDossierModal()">Close Dossier</button>
            </div>
        </div>
    </div>
</div>

<!-- IMAGE LIGHTBOX ZOOM MODAL -->
<div id="imageLightbox" class="lightbox-overlay" style="display:none;" onclick="if(event.target===this) closeImageZoom()">
    <div class="lightbox-box">
        <button type="button" class="lightbox-close" onclick="closeImageZoom()">&times;</button>
        <img id="lightboxImg" src="" alt="Enlarged Evidence">
        <div id="lightboxCaption" style="text-align:center; color:#ffffff; font-size:13px; font-weight:700; margin-top:10px; background:rgba(0,0,0,0.6); padding:6px 12px; border-radius:8px;"></div>
    </div>
</div>

<script>
let qrInstance = null;
let dossierQrInstance = null;

function renderQr(upiUri) {
    const qrContainer = document.getElementById('qrcodeCanvasModal');
    qrContainer.innerHTML = '';

    try {
        if (typeof QRCode !== 'undefined') {
            qrInstance = new QRCode(qrContainer, {
                text: upiUri,
                width: 190,
                height: 190,
                colorDark: "#0d1b2a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=190x190&data=' + encodeURIComponent(upiUri);
            img.alt = 'UPI QR Code';
            img.width = 190;
            img.height = 190;
            qrContainer.appendChild(img);
        }
    } catch(e) {
        console.error('QR rendering error:', e);
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=190x190&data=' + encodeURIComponent(upiUri);
        img.alt = 'UPI QR Code';
        img.width = 190;
        img.height = 190;
        qrContainer.appendChild(img);
    }
}

function renderDossierQr(dataText) {
    const qrBox = document.getElementById('dossierQrCanvas');
    if (!qrBox) return;
    qrBox.innerHTML = '';

    try {
        if (typeof QRCode !== 'undefined') {
            dossierQrInstance = new QRCode(qrBox, {
                text: dataText,
                width: 120,
                height: 120,
                colorDark: "#0d1b2a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' + encodeURIComponent(dataText);
            img.alt = 'Challan QR Code';
            img.width = 120;
            img.height = 120;
            qrBox.appendChild(img);
        }
    } catch(e) {
        console.error('Dossier QR error:', e);
    }
}

function openQrModal(id, vehicleNo, violation, fineAmount, status) {
    const isPaid = (status === 'paid');
    const qrContainer = document.getElementById('qrcodeCanvasModal');
    qrContainer.innerHTML = '';

    document.getElementById('modalChallanBadge').textContent = '#CH-' + id;
    document.getElementById('modalVehiclePlate').textContent = vehicleNo;
    document.getElementById('modalViolation').textContent = violation;
    document.getElementById('modalFineAmount').textContent = '₹' + parseFloat(fineAmount).toFixed(2);

    if (isPaid) {
        document.getElementById('modalTitle').textContent = 'Official Challan Receipt QR';
        document.getElementById('modalSubtitle').textContent = 'Scan to view & verify official paid receipt';
        document.getElementById('modalActiveText').textContent = '✓ Payment Settled & Discharged • Authenticated by ITES';

        const verifyUrl = window.location.origin + window.location.pathname.replace('user_dashboard.php', '') + 'receipt.php?id=' + id;
        renderQr(verifyUrl);

        document.getElementById('modalPayFormWrap').innerHTML = `
            <a href="receipt.php?id=${id}" target="_blank" class="btn btn-verify" style="margin-bottom:8px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px;">
                🖨️ View & Print Official Paid Receipt (PDF)
            </a>
        `;
    } else {
        document.getElementById('modalTitle').textContent = 'Challan UPI QR Payment';
        document.getElementById('modalSubtitle').textContent = 'Scan with any UPI app (GPay, PhonePe, Paytm, BHIM) to pay';
        document.getElementById('modalActiveText').textContent = 'Active payment session • Verify after scanning';

        const upiPa = 'keralatraffic.treasury@gov.in';
        const upiPn = 'Traffic Police Department';
        const upiTn = 'Challan CH' + id + ' ' + vehicleNo;
        const formattedAmount = parseFloat(fineAmount).toFixed(2);
        const upiUri = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=CH' + id + '&tr=' + id + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);

        renderQr(upiUri);

        document.getElementById('modalPayFormWrap').innerHTML = `
            <form method="POST" action="pay.php" id="modalPayForm" style="margin-bottom:8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="modalChallanId" value="${id}">
                <button type="submit" class="btn btn-verify">✓ I HAVE PAID / VERIFY PAYMENT</button>
            </form>
            <div style="display:flex; gap:8px;">
                <a href="pay.php?id=${id}" target="_blank" class="btn btn-light" style="flex:1; text-align:center; text-decoration:none; padding:9px; font-size:12px; font-weight:700;">
                    💳 Gateway
                </a>
                <a href="receipt.php?id=${id}" target="_blank" class="btn btn-light" style="flex:1; text-align:center; text-decoration:none; padding:9px; font-size:12px; font-weight:700;">
                    📄 Notice
                </a>
            </div>
        `;
    }

    document.getElementById('qrModal').style.display = 'flex';
}

function showCitizenQrModal(userId, userEmail, vehicleNo, totalAmount, count) {
    document.getElementById('modalTitle').textContent = 'Citizen Payment QR';
    document.getElementById('modalSubtitle').textContent = 'Present this QR code to Traffic Officer or scan with UPI';
    document.getElementById('modalChallanBadge').textContent = 'User #' + userId;
    document.getElementById('modalVehiclePlate').textContent = vehicleNo ? vehicleNo : userEmail;
    document.getElementById('modalViolation').textContent = count + ' Pending Citation' + (count !== 1 ? 's' : '');
    document.getElementById('modalFineAmount').textContent = '₹' + parseFloat(totalAmount).toFixed(2);
    document.getElementById('modalActiveText').textContent = 'Present screen to Traffic Police on-duty or scan to pay';

    document.getElementById('modalPayFormWrap').innerHTML = `
        <div style="background:#f4f8fd; border:1px solid #dce6f2; border-radius:12px; padding:10px 14px; text-align:center; font-size:12px; color:var(--navy-900); font-weight:600; margin-bottom:10px;">
            📱 Present this QR code on your phone screen to on-duty traffic police officers for verification.
        </div>
    `;

    const upiPa = 'keralatraffic.treasury@gov.in';
    const upiPn = 'Traffic Police Department';
    const upiTn = 'Citizen Dues User ' + userId + (vehicleNo ? ' ' + vehicleNo : '');
    const formattedAmount = parseFloat(totalAmount).toFixed(2);
    const upiUri = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=USER' + userId + '&tr=' + userId + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);

    renderQr(upiUri);
    document.getElementById('qrModal').style.display = 'flex';
}

function showVehicleQrModal(userId, vehicleNo, pendingAmount, count) {
    document.getElementById('modalTitle').textContent = 'Vehicle Fine QR Code';
    document.getElementById('modalSubtitle').textContent = 'Present to Traffic Police or scan via UPI app';
    document.getElementById('modalChallanBadge').textContent = vehicleNo;
    document.getElementById('modalVehiclePlate').textContent = 'User #' + userId;
    document.getElementById('modalViolation').textContent = count + ' Unpaid Violation' + (count !== 1 ? 's' : '');
    document.getElementById('modalFineAmount').textContent = '₹' + parseFloat(pendingAmount).toFixed(2);
    document.getElementById('modalActiveText').textContent = 'Vehicle fine dues • Present screen for roadside check';

    document.getElementById('modalPayFormWrap').innerHTML = `
        <div style="background:#f4f8fd; border:1px solid #dce6f2; border-radius:12px; padding:10px 14px; text-align:center; font-size:12px; color:var(--navy-900); font-weight:600; margin-bottom:10px;">
            🚗 Outstanding fine balance for vehicle <strong>${vehicleNo}</strong>. Present to traffic police or scan to settle.
        </div>
    `;

    const upiPa = 'keralatraffic.treasury@gov.in';
    const upiPn = 'Traffic Police Department';
    const upiTn = 'Vehicle Dues ' + vehicleNo;
    const formattedAmount = parseFloat(pendingAmount).toFixed(2);
    const upiUri = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=VEH' + vehicleNo + '&tr=' + vehicleNo + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);

    renderQr(upiUri);
    document.getElementById('qrModal').style.display = 'flex';
}

function openDossierModalFromBtn(btn) {
    try {
        const d = JSON.parse(btn.getAttribute('data-dossier'));
        openDossierModal(d);
    } catch(e) {
        console.error('Failed to parse dossier JSON:', e);
    }
}

function openDossierModal(d) {
    // Header & identifiers
    document.getElementById('dosChallanBadge').textContent = '#CH-' + d.id;
    document.getElementById('dosChallanNo').textContent = d.challan_no;
    document.getElementById('dosVehiclePlate').textContent = d.vehicle_no;
    document.getElementById('dosViolationType').textContent = d.violation;
    document.getElementById('dosViolationDesc').textContent = d.violation_desc;
    document.getElementById('dosViolationDate').textContent = d.violation_date;
    document.getElementById('dosFineAmount').textContent = '₹' + parseFloat(d.fine_amount).toFixed(2);
    document.getElementById('dosLocation').textContent = d.location;
    document.getElementById('dosGps').textContent = d.gps_coordinates || '9.9816° N, 76.2999° E';
    document.getElementById('dosCameraNode').textContent = d.camera_id;
    document.getElementById('dosCameraId').textContent = d.camera_id;
    document.getElementById('dosOfficer').textContent = d.officer_name;
    document.getElementById('dosDueDate').textContent = d.due_date;
    document.getElementById('dosHud1').textContent = 'OCR: ' + d.vehicle_no;
    if (document.getElementById('dosAnprScore')) {
        document.getElementById('dosAnprScore').textContent = 'CONFIDENCE: ' + (d.anpr_score || '99.4%');
    }

    // Vehicle specifications (RC Record)
    document.getElementById('dosVehMakeModel').textContent = d.vehicle_make_model || 'Vehicle Record Synchronized';
    document.getElementById('dosVehClass').textContent = d.vehicle_class || 'LMV - Light Motor Vehicle';
    document.getElementById('dosVehColorFuel').textContent = (d.vehicle_color || 'White') + (d.vehicle_fuel ? ' • ' + d.vehicle_fuel : '');
    document.getElementById('dosVehEngine').textContent = d.engine_no || 'ENG-KLD' + d.id + '904';
    document.getElementById('dosVehChassis').textContent = d.chassis_no || 'VIN-KL' + d.vehicle_no + '77';
    document.getElementById('dosVehRto').textContent = d.rto_authority || 'Regional Transport Authority, Kerala';
    document.getElementById('dosVehInsurance').textContent = '✓ ' + (d.insurance_policy || 'Active & Validated');
    document.getElementById('dosVehPucc').textContent = '✓ ' + (d.pucc_status || 'Certified Active');

    // Statutory Provisions & Telemetry
    document.getElementById('dosStatuteSection').textContent = d.statutory_section 
        ? ('Statute: ' + d.statutory_section + (d.cmvr_rule ? ' • Rule: ' + d.cmvr_rule : '')) 
        : 'Motor Vehicles Act, 1988 & Central Motor Vehicles Rules';
    document.getElementById('dosTelemetryBox').textContent = '📡 SENSOR TELEMETRY: ' + (d.radar_telemetry || 'Automated CCTV ANPR & Doppler sensor triggers confirmed infraction.');

    // Photographic Evidence
    const img1 = d.evidence_photo ? d.evidence_photo : 'assets/evidence/car_front_cam.jpg';
    const img2 = d.evidence_photo_2 ? d.evidence_photo_2 : 'assets/evidence/car_side_cam.jpg';
    document.getElementById('dosImg1').src = img1;
    document.getElementById('dosImg2').src = img2;

    // Status Badge
    const statusBadge = document.getElementById('dosStatusBadge');
    statusBadge.className = 'status-badge ' + (d.is_paid ? 'status-paid' : 'status-unpaid');
    statusBadge.textContent = d.is_paid ? '✓ Paid & Discharged' : '● Unpaid (Legal Notice)';

    // Receipt Button
    const receiptBtn = document.getElementById('dosReceiptBtn');
    receiptBtn.href = 'receipt.php?id=' + d.id;
    receiptBtn.innerHTML = d.is_paid ? '🖨️ Download Official Paid Receipt (PDF)' : '📄 View / Print Official Citation Notice (PDF)';

    // QR Code generation for this specific challan
    const qrTitle = document.getElementById('dosQrTitle');
    const qrSubtitle = document.getElementById('dosQrSubtitle');
    const qrFormWrap = document.getElementById('dosQrFormWrap');
    const actionsContainer = document.getElementById('dosPayActions');

    let qrContent = '';
    if (d.is_paid) {
        qrTitle.textContent = 'Official Payment Verification QR Code';
        qrSubtitle.textContent = 'Scan this QR code with any smartphone to instantly verify payment settlement, audit hash, and case discharge.';
        qrContent = window.location.origin + window.location.pathname.replace('user_dashboard.php', '') + 'receipt.php?id=' + d.id;

        qrFormWrap.innerHTML = `
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px 14px; color:#15803d; font-size:12.5px; font-weight:700;">
                ✓ Compounding penalty ₹${parseFloat(d.fine_amount).toFixed(2)} is settled in full. Statutory legal discharge granted.
            </div>
        `;

        actionsContainer.innerHTML = `
            <button type="button" class="btn btn-light" style="padding:10px 16px; font-size:13px;" onclick="window.open('receipt.php?id=${d.id}', '_blank')">
                🖨️ Print
            </button>
        `;
    } else {
        qrTitle.textContent = 'Challan UPI Payment QR Code';
        qrSubtitle.textContent = 'Scan with Google Pay, PhonePe, Paytm, BHIM, or any UPI app to pay the ₹' + parseFloat(d.fine_amount).toFixed(2) + ' penalty.';

        const upiPa = 'keralatraffic.treasury@gov.in';
        const upiPn = 'Traffic Police Department';
        const upiTn = 'Challan CH' + d.id + ' ' + d.vehicle_no;
        const formattedAmount = parseFloat(d.fine_amount).toFixed(2);
        qrContent = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=CH' + d.id + '&tr=' + d.id + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);

        qrFormWrap.innerHTML = `
            <form method="POST" action="pay.php" style="margin-bottom:8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="${d.id}">
                <button type="submit" class="btn btn-verify" style="padding:10px 18px; font-size:13px; font-weight:800; cursor:pointer;">
                    ✓ I HAVE PAID / VERIFY PAYMENT
                </button>
            </form>
            <a href="pay.php?id=${d.id}" target="_blank" class="btn btn-light" style="font-size:12px; padding:8px 14px; text-decoration:none; display:inline-block; font-weight:700;">
                💳 Open Dedicated Gateway
            </a>
        `;

        actionsContainer.innerHTML = `
            <button type="button" class="btn btn-pay" style="padding:10px 18px; font-size:13px; font-weight:800; cursor:pointer;" onclick="closeDossierModal(); openQrModal('${d.id}', '${d.vehicle_no}', '${d.violation}', '${d.fine_amount}', 'unpaid')">
                📲 Pay via QR Modal (₹${parseFloat(d.fine_amount).toFixed(2)})
            </button>
        `;
    }

    renderDossierQr(qrContent);
    document.getElementById('dossierModal').style.display = 'flex';
}

function closeDossierModal() {
    document.getElementById('dossierModal').style.display = 'none';
}

function openImageZoom(src, caption) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxCaption').textContent = caption || 'Enlarged CCTV Camera Evidence';
    document.getElementById('imageLightbox').style.display = 'flex';
}

function closeImageZoom() {
    document.getElementById('imageLightbox').style.display = 'none';
}

// Generate unique QR codes for every challan row on page load
document.addEventListener('DOMContentLoaded', function() {
    const qrElements = document.querySelectorAll('[data-row-qr="1"]');
    qrElements.forEach(function(el) {
        const id = el.getAttribute('data-id');
        const vno = el.getAttribute('data-vno');
        const amt = el.getAttribute('data-amt');
        const isPaid = el.getAttribute('data-paid') === '1';
        const frame = el.querySelector('.row-qr-frame');
        if (!frame) return;

        let qrData = '';
        if (isPaid) {
            qrData = window.location.origin + window.location.pathname.replace('user_dashboard.php', '') + 'receipt.php?id=' + id;
        } else {
            const upiPa = 'keralatraffic.treasury@gov.in';
            const upiPn = 'Traffic Police Department';
            const upiTn = 'Challan CH' + id + ' ' + vno;
            const formattedAmount = parseFloat(amt).toFixed(2);
            qrData = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=CH' + id + '&tr=' + id + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);
        }

        try {
            if (typeof QRCode !== 'undefined') {
                new QRCode(frame, {
                    text: qrData,
                    width: 54,
                    height: 54,
                    colorDark: "#0d1b2a",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } else {
                const img = document.createElement('img');
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=54x54&data=' + encodeURIComponent(qrData);
                img.width = 54;
                img.height = 54;
                img.alt = 'Challan QR';
                frame.appendChild(img);
            }
        } catch(e) {
            console.error('Row QR generation error:', e);
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQrModal();
        closeDossierModal();
        closeImageZoom();
    }
});
</script>
</body>
</html>
