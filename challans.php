<?php
include("db.php");
require_once("challan_helper.php");
ensure_logged_in('admin');

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$violationFilter = trim($_GET['violation'] ?? 'all');
$message = trim($_GET['message'] ?? $_GET['msg'] ?? '');
$msgVehicle = trim($_GET['vno'] ?? '');
$msgUserId = trim($_GET['uid'] ?? '');
$msgChId = trim($_GET['chid'] ?? '');

$baseQuery = "
    SELECT c.*, u.email AS citizen_email, u.id AS citizen_user_id, u.vehicle_no AS user_vno 
    FROM challans c 
    LEFT JOIN users u ON u.id = c.user_id 
    WHERE 1=1
";
$params = [];
$types = '';

if ($search !== '') {
    $baseQuery .= " AND (c.vehicle_no LIKE ? OR CAST(c.id AS CHAR) LIKE ? OR c.challan_no LIKE ? OR c.violation LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sssss';
}

if ($statusFilter !== 'all') {
    $baseQuery .= " AND c.status = ?";
    $params[] = ucfirst(strtolower($statusFilter));
    $types .= 's';
}

if ($violationFilter !== 'all') {
    $baseQuery .= " AND c.violation = ?";
    $params[] = $violationFilter;
    $types .= 's';
}

$baseQuery .= " ORDER BY c.id DESC";

$challans = [];
$filterActive = $search !== '' || strtolower($statusFilter) !== 'all' || $violationFilter !== 'all';
$storedChallanCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM challans"))['c'] ?? 0);
$stmt = $conn->prepare($baseQuery);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $challans[] = $row;
    }
    $stmt->close();
}

$usingDemoData = false;
if (count($challans) === 0 && !$filterActive && $storedChallanCount === 0) {
    $usingDemoData = true;
    $challans = [
        ['id' => 251, 'vehicle_no' => 'KL07AB1234', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Unpaid'],
        ['id' => 250, 'vehicle_no' => 'PB10BB4040', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 249, 'vehicle_no' => 'PB10AA5050', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 248, 'vehicle_no' => 'GJ01DD6060', 'violation' => 'Mobile Usage', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 247, 'vehicle_no' => 'GJ01CC7070', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Paid'],
        ['id' => 246, 'vehicle_no' => 'GJ01BB8080', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Unpaid'],
        ['id' => 245, 'vehicle_no' => 'GJ01AA9090', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 244, 'vehicle_no' => 'KA03FF6802', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 243, 'vehicle_no' => 'KA03EE5791', 'violation' => 'Mobile Usage', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 242, 'vehicle_no' => 'KA03DD4680', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 241, 'vehicle_no' => 'KA03CC3579', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Paid'],
        ['id' => 240, 'vehicle_no' => 'KA03BB2468', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 239, 'vehicle_no' => 'KA03AA1357', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
    ];
}

$totalChallans = count($challans);
$paidCount = 0;
$unpaidCount = 0;
$totalAmount = 0;

foreach ($challans as $challan) {
    $totalAmount += (float) $challan['fine_amount'];
    if (strtolower((string) $challan['status']) === 'paid') {
        $paidCount++;
    } else {
        $unpaidCount++;
    }
}

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$violationOptions = ['Helmet', 'Overspeed', 'Signal Jump', 'Mobile Usage', 'Triple Riding', 'No Seatbelt', 'Dangerous Driving', 'Wrong Side Driving', 'Wrong Parking'];

$userOptions = [];
$userResult = mysqli_query($conn, "SELECT id, email, vehicle_no FROM users WHERE role = 'user' ORDER BY email ASC");
if ($userResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($userResult)) {
        $userOptions[] = $row;
    }
}

$editChallan = null;
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
if ($editId > 0) {
    $editStmt = $conn->prepare("SELECT * FROM challans WHERE id = ? LIMIT 1");
    if ($editStmt) {
        $editStmt->bind_param("i", $editId);
        $editStmt->execute();
        $editChallan = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challan Management</title>
<style>
:root{
    --navy-950:#0d1b2a;
    --navy-900:#13273d;
    --navy-800:#1d4060;
    --blue-500:#3b82f6;
    --blue-400:#60a5fa;
    --panel:#ffffff;
    --panel-soft:#f4f8fd;
    --line:#d8e2ec;
    --ink:#182433;
    --muted:#66788a;
    --success:#15803d;
    --danger:#b33c2f;
    --shadow:0 14px 32px rgba(15, 23, 42, 0.08);
}

*{ box-sizing:border-box; }

body{
    margin:0;
    min-height:100vh;
    font-family:'Segoe UI',Tahoma,sans-serif;
    color:var(--ink);
    background:linear-gradient(180deg, #edf3f9 0%, #dfe8f2 100%);
}

.shell{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:245px;
    background:linear-gradient(180deg, var(--navy-900), var(--navy-950));
    color:#fff;
    padding:22px 16px;
    position:sticky;
    top:0;
    height:100vh;
}

.seal{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px;
    margin-bottom:18px;
    border:1px solid rgba(96, 165, 250, 0.18);
    border-radius:14px;
    background:rgba(255,255,255,0.03);
}

.seal-mark{
    width:44px;
    height:44px;
    border-radius:12px;
    background:linear-gradient(135deg, var(--blue-400), var(--blue-500));
    display:grid;
    place-items:center;
    color:#fff;
    font-size:20px;
}

.seal small,
.status-note small{
    display:block;
    color:rgba(255,255,255,0.65);
    letter-spacing:0.08em;
    text-transform:uppercase;
    font-size:10px;
}

.seal strong{
    display:block;
    margin-top:4px;
    font-size:18px;
}

.nav-label{
    margin:16px 10px 8px;
    color:rgba(255,255,255,0.5);
    text-transform:uppercase;
    letter-spacing:0.12em;
    font-size:11px;
}

.sidebar a{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px 14px;
    margin:8px 0;
    color:#eef4ff;
    text-decoration:none;
    border-radius:12px;
    border:1px solid transparent;
    transition:0.2s ease;
}

.sidebar a span{
    color:rgba(255,255,255,0.56);
    font-size:12px;
}

.sidebar a:hover,
.sidebar a.active{
    background:rgba(59, 130, 246, 0.15);
    border-color:rgba(96, 165, 250, 0.28);
}

.status-note{
    margin-top:18px;
    padding:14px;
    border-radius:14px;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
}

.status-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:10px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(21, 128, 61, 0.18);
    color:#b6f2c7;
    font-size:13px;
}

.logout{
    margin-top:18px;
    justify-content:center !important;
    background:linear-gradient(135deg, #c84235, #9c2d23);
}

.main{
    flex:1;
    padding:24px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    margin-bottom:18px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(59, 130, 246, 0.10);
    color:#2459a8;
    font-size:11px;
    letter-spacing:0.08em;
    text-transform:uppercase;
}

.title-block h1{
    margin:10px 0 8px;
    font-size:34px;
    line-height:1.1;
    color:#0f172a !important;
    font-weight:800;
}

.title-block p{
    margin:0;
    max-width:760px;
    color:var(--muted);
    font-size:14px;
}

.profile-card{
    min-width:270px;
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    padding:16px;
    box-shadow:var(--shadow);
}

.profile-row{
    display:flex;
    align-items:center;
    gap:12px;
}

.avatar{
    width:48px;
    height:48px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
    font-weight:700;
}

.profile-card strong{
    display:block;
    font-size:16px;
}

.profile-card small{
    color:var(--muted);
}

.toolbar{
    display:flex;
    gap:10px;
    margin-top:12px;
}

.toolbar a{
    text-decoration:none;
    padding:9px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
}

.toolbar .primary{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}

.toolbar .secondary{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
}

.cards,
.filters,
.table-panel{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    background:transparent;
    border:none;
    box-shadow:none;
    margin-bottom:16px;
}

.stat-card{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:18px;
    position:relative;
    overflow:hidden;
}

.stat-card:before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, var(--navy-800), var(--blue-400));
}

.stat-card .label{
    margin:0 0 8px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.stat-card .value{
    margin-top:12px;
    font-size:34px;
    font-weight:700;
    color:#0d2238;
}

.stat-card .trend{
    color:var(--muted);
    font-size:13px;
}

.filters,
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

.panel-head p{
    margin:0;
    color:var(--muted);
    font-size:13px;
}

.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr auto;
    gap:12px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.field label{
    color:#5d7084;
    font-size:12px;
    font-weight:600;
}

.field input,
.field select{
    width:100%;
    padding:11px 12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    font-size:14px;
    color:var(--ink);
}

.actions{
    display:flex;
    align-items:flex-end;
    gap:10px;
}

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:100px;
    padding:10px 14px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
}

.btn-primary{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}

.btn-light{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
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
    background:#fbfdff;
}

.status-badge{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.06em;
}

.status-paid{
    background:rgba(21, 128, 61, 0.12);
    color:var(--success);
}

.status-unpaid{
    background:rgba(179, 60, 47, 0.12);
    color:var(--danger);
}

.violation-tag{
    display:inline-flex;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    background:#eef4ff;
    color:#2459a8;
}

.table-note{
    margin-top:14px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
    color:var(--muted);
    font-size:13px;
}

.form-panel{
    margin-bottom:20px;
    background:#ffffff;
    border-radius:18px;
    border:1px solid var(--line);
    padding:20px;
    box-shadow:var(--shadow);
}

.challan-form-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
}

.challan-span-2{
    grid-column:span 2;
}

.challan-span-full{
    grid-column:1 / -1;
}

.form-fieldset{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:16px;
    margin-bottom:12px;
}

.form-fieldset-legend{
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.06em;
    color:#2459a8;
    margin-bottom:12px;
    display:flex;
    align-items:center;
    gap:6px;
}

.evidence-thumb-cell{
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.evidence-thumb-img{
    width:50px;
    height:36px;
    object-fit:cover;
    border-radius:6px;
    border:1px solid #cbd5e1;
    cursor:pointer;
    transition:transform 0.15s ease;
}

.evidence-thumb-img:hover{
    transform:scale(1.15);
    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

.hsrp-pill{
    display:inline-flex;
    align-items:center;
    background:#ffffff;
    border:1.5px solid #111;
    border-radius:6px;
    font-weight:900;
    overflow:hidden;
    font-size:12px;
}

.hsrp-pill-ind{
    background:#003399;
    color:#fff;
    padding:2px 5px;
    font-size:8px;
    letter-spacing:0.5px;
    border-right:1px solid #111;
}

.hsrp-pill-no{
    padding:2px 7px;
    letter-spacing:1px;
    color:#111;
}

.notice{
    margin-bottom:14px;
    padding:11px 12px;
    border-radius:12px;
    background:#eef8f0;
    border:1px solid rgba(21,128,61,0.18);
    color:var(--success);
    font-size:13px;
    font-weight:700;
}

.row-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.danger-btn{
    background:rgba(179,60,47,0.10);
    color:var(--danger);
    border:1px solid rgba(179,60,47,0.18);
}

@media (max-width: 1180px){
    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .filter-grid,
    .form-grid{
        grid-template-columns:1fr 1fr;
    }

    .actions{
        grid-column:1 / -1;
    }

    .topbar{
        flex-direction:column;
    }

    .profile-card{
        min-width:0;
        width:100%;
    }
}

@media (max-width: 920px){
    .shell{
        flex-direction:column;
    }

    .sidebar{
        width:100%;
        height:auto;
        position:relative;
    }

    .main{
        padding:18px;
    }
}

@media (max-width: 640px){
    .cards,
    .filter-grid,
    .form-grid{
        grid-template-columns:1fr;
    }

    .title-block h1{
        font-size:28px;
    }

    .toolbar,
    .actions{
        flex-direction:column;
        align-items:stretch;
    }
}
</style>
<link rel="stylesheet" href="traffic_animated_theme.css?v=5">
<link rel="stylesheet" href="kerala-theme.css?v=5">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">🛡</div>
            <div>
                <small>Traffic Control System</small>
                <strong>Admin Dashboard</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php" class="active"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>
        <a href="index.php"><strong>Portal Home</strong><span>Public Site</span></a>

        <div class="status-note">
            <small>System Status</small>
            <strong>Challan management is active</strong>
            <div class="status-pill">● Admin access enabled</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Challan Management Module</div>
                <h1>Manage Challans and Cases</h1>
                <p>Search, review, and monitor challan records with quick filters and a table layout that matches the main dashboard.</p>
            </div>

            <div class="profile-card">
                <div class="profile-row">
                    <div class="avatar"><?php echo e($adminInitials); ?></div>
                    <div>
                        <strong>Administrator</strong>
                        <small><?php echo e($adminEmail); ?></small><br>
                        <small>Role: Traffic Admin</small>
                    </div>
                </div>
                <div class="toolbar">
                    <a href="report.php" class="primary">View Reports</a>
                    <a href="users.php" class="secondary">Citizen Records</a>
                </div>
            </div>
        </div>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Visible Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">Records matching current filters.</div>
            </div>
            <div class="stat-card">
                <div class="label">Paid Cases</div>
                <div class="value"><?php echo e((string) $paidCount); ?></div>
                <div class="trend">Settled challans in this view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Unpaid Cases</div>
                <div class="value"><?php echo e((string) $unpaidCount); ?></div>
                <div class="trend">Pending challans in this view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Fine Value</div>
                <div class="value">₹<?php echo e((string) $totalAmount); ?></div>
                <div class="trend">Combined amount of visible challans.</div>
            </div>
        </section>

        <?php if ($message !== '') { ?>
        <div class="notice" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div>
                <?php if ($message === 'created') { ?>
                    <strong>✓ Challan Created Successfully!</strong> 
                    Challan <strong>#CH-<?php echo e($msgChId); ?></strong> registered for vehicle <strong><?php echo e($msgVehicle); ?></strong>.
                    <?php if (!empty($msgUserId)) { ?>
                    Citizen (<strong style="color:var(--navy-900);">User ID #<?php echo e($msgUserId); ?></strong>) can now log in to the Citizen Dashboard with password <code>1234</code> to view all citation details and evidence photos.
                    <?php } ?>
                <?php } elseif ($message === 'updated') { ?>
                    <strong>✓ Challan Updated Successfully.</strong> Record details and evidence have been refreshed.
                <?php } elseif ($message === 'deleted') { ?>
                    <strong>✓ Challan Deleted Successfully.</strong>
                <?php } elseif ($message === 'invalid_vehicle') { ?>
                    <strong>⚠️ Invalid Vehicle Number.</strong> Please enter a valid registration number (e.g. KL07AB1234).
                <?php } elseif ($message === 'user_required') { ?>
                    <strong>⚠️ User Assignment Required.</strong> Please link the challan to an existing citizen or register a new user.
                <?php } else { ?>
                    <strong>Notice:</strong> <?php echo e(ucwords(str_replace('_', ' ', $message))); ?>
                <?php } ?>
            </div>
            <?php if (!empty($msgUserId) && $message === 'created') { ?>
            <a href="users.php?search=<?php echo urlencode($msgVehicle); ?>" class="btn btn-light" style="font-size:12px; padding:6px 12px; text-decoration:none;">View Citizen #<?php echo e($msgUserId); ?></a>
            <?php } ?>
        </div>
        <?php } ?>

        <section class="filters form-panel">
            <div class="panel-head">
                <div>
                    <h3><?php echo $editChallan ? 'Edit Challan #' . e((string) $editChallan['id']) : 'Issue & Create Official Traffic Challan'; ?></h3>
                    <p>Enter complete citation details including vehicle, violation date & time, detailed legal statement, statutory fine, photographic evidence, and sensor telemetry.</p>
                </div>
                <?php if ($editChallan) { ?>
                <span class="status-badge <?php echo strtolower($editChallan['status'] ?? '') === 'paid' ? 'status-paid' : 'status-unpaid'; ?>">
                    Status: <?php echo e($editChallan['status'] ?? 'Unpaid'); ?>
                </span>
                <?php } ?>
            </div>

            <form method="POST" action="save_challan.php" enctype="multipart/form-data" style="margin-top:16px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo e((string) ($editChallan['id'] ?? 0)); ?>">

                <!-- SECTION 1: CITATION IDENTIFIERS & CITIZEN -->
                <div class="form-fieldset">
                    <div class="form-fieldset-legend">📋 1. Citation Identifiers & Vehicle Assignment</div>
                    <div class="challan-form-grid">
                        <div class="field">
                            <label for="user_id">Citizen / Account Link</label>
                            <select id="user_id" name="user_id" onchange="onCitizenSelect(this)">
                                <option value="0">⚡ Auto-detect / Auto-register for Vehicle Owner</option>
                                <?php foreach ($userOptions as $userOption) { ?>
                                <?php $isSel = (int) ($editChallan['user_id'] ?? 0) === (int) $userOption['id']; ?>
                                <option value="<?php echo e((string) $userOption['id']); ?>" data-vehicle="<?php echo e($userOption['vehicle_no'] ?? ''); ?>" <?php echo $isSel ? 'selected' : ''; ?>>
                                    User #<?php echo e((string) $userOption['id']); ?>: <?php echo e($userOption['email']); ?> <?php echo !empty($userOption['vehicle_no']) ? '(' . e($userOption['vehicle_no']) . ')' : ''; ?>
                                </option>
                                <?php } ?>
                            </select>
                            <small style="color:var(--muted); font-size:11px; margin-top:3px; display:block;">Selecting a user links the citation to their User ID dashboard.</small>
                        </div>

                        <div class="field">
                            <label for="vehicle_no">Vehicle Registration No <span style="color:var(--danger);">*</span></label>
                            <input id="vehicle_no" name="vehicle_no" value="<?php echo e($editChallan['vehicle_no'] ?? ''); ?>" placeholder="e.g. KL07AB1234 or DL01AB1234" style="text-transform:uppercase; font-weight:800; letter-spacing:1px;" required oninput="handleVehicleInput(this.value)">
                            <small style="color:var(--muted); font-size:11px; margin-top:3px; display:block;">Standard HSRP registration format.</small>
                        </div>

                        <div class="field">
                            <label for="challan_no">Challan Number (Official ID)</label>
                            <input id="challan_no" name="challan_no" value="<?php echo e($editChallan['challan_no'] ?? ''); ?>" placeholder="Auto-generated (e.g. KL-CHN-<?php echo date('Y'); ?>-XXXXXX)">
                            <small style="color:var(--muted); font-size:11px; margin-top:3px; display:block;">Leave blank to auto-generate official citation ID.</small>
                        </div>

                        <div class="field">
                            <label for="violation_date">Date & Time of Violation <span style="color:var(--danger);">*</span></label>
                            <?php
                            $defaultDt = !empty($editChallan['violation_date']) 
                                ? date('Y-m-d\TH:i', strtotime($editChallan['violation_date'])) 
                                : (!empty($editChallan['created_at']) ? date('Y-m-d\TH:i', strtotime($editChallan['created_at'])) : date('Y-m-d\TH:i'));
                            ?>
                            <input id="violation_date" type="datetime-local" name="violation_date" value="<?php echo e($defaultDt); ?>" required>
                        </div>

                        <div class="field">
                            <label for="due_date">Payment Due Date</label>
                            <?php
                            $defaultDue = !empty($editChallan['due_date'])
                                ? date('Y-m-d', strtotime($editChallan['due_date']))
                                : date('Y-m-d', strtotime('+60 days'));
                            ?>
                            <input id="due_date" type="date" name="due_date" value="<?php echo e($defaultDue); ?>">
                        </div>

                        <div class="field">
                            <label for="new_status">Challan Payment Status</label>
                            <select id="new_status" name="status">
                                <option value="Unpaid" <?php echo ($editChallan['status'] ?? 'Unpaid') === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="Paid" <?php echo ($editChallan['status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: OFFENSE DETAILS & FINE -->
                <div class="form-fieldset">
                    <div class="form-fieldset-legend">⚖️ 2. Violation Offense & Statutory Fine</div>
                    <div class="challan-form-grid">
                        <div class="field">
                            <label for="new_violation">Type of Violation <span style="color:var(--danger);">*</span></label>
                            <select id="new_violation" name="violation" required onchange="onViolationChange(this.value)">
                                <?php foreach ($violationOptions as $option) { ?>
                                <option value="<?php echo e($option); ?>" <?php echo ($editChallan['violation'] ?? 'Helmet') === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="fine_amount">Fine Amount (₹) <span style="color:var(--danger);">*</span></label>
                            <input id="fine_amount" type="number" min="100" step="50" name="fine_amount" value="<?php echo e((string) ($editChallan['fine_amount'] ?? '1000')); ?>" required>
                        </div>

                        <div class="field">
                            <label for="camera_id">Enforcement Camera / Radar Node</label>
                            <input id="camera_id" name="camera_id" value="<?php echo e($editChallan['camera_id'] ?? 'KL-ITES-CAM-54 (High-Speed ANPR Node)'); ?>" placeholder="e.g. KL-ITES-CAM-54 (ANPR Node)">
                        </div>

                        <div class="field challan-span-full">
                            <label for="violation_desc">Detailed Violation Description <span style="color:var(--danger);">*</span></label>
                            <textarea id="violation_desc" name="violation_desc" rows="3" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; font-family:inherit; font-size:13px; line-height:1.5;" required placeholder="Enter statutory description of the violation recorded by sensors or patrol officers..."><?php echo e($editChallan['violation_desc'] ?? 'Operating or riding pillion on a two-wheeled motorcycle in a public place without wearing protective headgear conforming to the Bureau of Indian Standards (BIS/ISI standard IS:4151:2015) securely fastened by chin strap.'); ?></textarea>
                            <small style="color:var(--muted); font-size:11px; margin-top:2px; display:block;">This full description is displayed directly on the citizen's user dashboard and official notice receipt.</small>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: EVIDENCE PHOTOS & SENSOR CAPTURES -->
                <div class="form-fieldset">
                    <div class="form-fieldset-legend">📸 3. Evidence Photos & CCTV Surveillance Captures</div>
                    <div class="challan-form-grid">
                        <div class="field">
                            <label for="evidence_photo_file">Evidence Photo 1 (Front View / ANPR Plate)</label>
                            <input id="evidence_photo_file" type="file" name="evidence_photo_file" accept="image/*" style="padding:8px 6px; font-size:12px;">
                            <div style="margin-top:6px;">
                                <label for="evidence_photo_preset" style="font-size:11px; color:var(--muted);">Or Select Camera Preset:</label>
                                <select id="evidence_photo_preset" name="evidence_photo_preset" style="font-size:12px; padding:8px;">
                                    <option value="assets/evidence/helmet_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/helmet_front_cam.jpg' ? 'selected' : ''; ?>>📸 Helmet Front Cam (Rider Without Helmet)</option>
                                    <option value="assets/evidence/car_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/car_front_cam.jpg' ? 'selected' : ''; ?>>📸 Speed Radar Front Cam (High Speed Capture)</option>
                                    <option value="assets/evidence/signal_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/signal_front_cam.jpg' ? 'selected' : ''; ?>>📸 Red Light Stop-Line Cam (Junction Breach)</option>
                                    <option value="assets/evidence/mobile_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/mobile_front_cam.jpg' ? 'selected' : ''; ?>>📸 Mobile Telephoto Cam (In-Cabin Phone Use)</option>
                                    <option value="assets/evidence/triple_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/triple_front_cam.jpg' ? 'selected' : ''; ?>>📸 Triple Riding Front Cam (3 Riders on Two-Wheeler)</option>
                                    <option value="assets/evidence/seatbelt_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/seatbelt_front_cam.jpg' ? 'selected' : ''; ?>>📸 No Seatbelt Telephoto Cam (Driver Unbelted)</option>
                                    <option value="assets/evidence/wrongside_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/wrongside_front_cam.jpg' ? 'selected' : ''; ?>>📸 Wrong Side Vector Cam (Counter-Flow Traffic)</option>
                                    <option value="assets/evidence/parking_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/parking_front_cam.jpg' ? 'selected' : ''; ?>>📸 Wrong Parking ANPR Cam (No-Parking Zone Tow Target)</option>
                                    <option value="assets/evidence/bike_front_cam.jpg" <?php echo ($editChallan['evidence_photo'] ?? '') === 'assets/evidence/bike_front_cam.jpg' ? 'selected' : ''; ?>>📸 Two-Wheeler Urban Cam (Standard Transit)</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="evidence_photo_file_2">Evidence Photo 2 (Lateral View / Context / Radar)</label>
                            <input id="evidence_photo_file_2" type="file" name="evidence_photo_file_2" accept="image/*" style="padding:8px 6px; font-size:12px;">
                            <div style="margin-top:6px;">
                                <label for="evidence_photo_preset_2" style="font-size:11px; color:var(--muted);">Or Select Lateral Camera Preset:</label>
                                <select id="evidence_photo_preset_2" name="evidence_photo_preset_2" style="font-size:12px; padding:8px;">
                                    <option value="assets/evidence/helmet_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/helmet_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Helmet Lateral Cam (Side Profile Bare Head)</option>
                                    <option value="assets/evidence/car_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/car_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Speed Doppler Cam (Lateral Radar Profile)</option>
                                    <option value="assets/evidence/signal_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/signal_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Intersection Lateral Cam (Crossing on Red)</option>
                                    <option value="assets/evidence/mobile_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/mobile_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Cabin Window Cam (Handheld Phone Device)</option>
                                    <option value="assets/evidence/triple_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/triple_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Triple Riding Lateral Cam (Multi-Passenger Profile)</option>
                                    <option value="assets/evidence/seatbelt_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/seatbelt_side_cam.jpg' ? 'selected' : ''; ?>>🚗 No Seatbelt Window Cam (Unfastened Harness on Pillar)</option>
                                    <option value="assets/evidence/wrongside_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/wrongside_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Wrong Side Junction Cam (Against One-Way Signage)</option>
                                    <option value="assets/evidence/parking_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/parking_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Wrong Parking Kerb Cam (Unauthorized Kerbside Obstruction)</option>
                                    <option value="assets/evidence/bike_side_cam.jpg" <?php echo ($editChallan['evidence_photo_2'] ?? '') === 'assets/evidence/bike_side_cam.jpg' ? 'selected' : ''; ?>>🚗 Two-Wheeler Lateral Cam (Highway Profile)</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="location">Violation Location / Road Corridor</label>
                            <input id="location" name="location" value="<?php echo e($editChallan['location'] ?? 'MG Road - Kaloor Junction (Junction Node #04), Ernakulam, Kerala'); ?>" placeholder="e.g. MG Road - Kaloor Junction, Ernakulam, Kerala">
                            <div style="margin-top:8px;">
                                <label for="officer_name">Issuing / Verifying Officer</label>
                                <input id="officer_name" name="officer_name" value="<?php echo e($editChallan['officer_name'] ?? 'MVI K. Suresh (Badge #4082)'); ?>" placeholder="e.g. MVI K. Suresh (Badge #4082)">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top:16px; display:flex; gap:12px; align-items:center;">
                    <button type="submit" class="btn btn-primary" style="padding:12px 24px; font-size:14px; font-weight:800; letter-spacing:0.5px;">
                        <?php echo $editChallan ? '💾 Save & Update Challan' : '🚀 Issue Challan to Citizen'; ?>
                    </button>
                    <?php if ($editChallan) { ?>
                    <a href="challans.php" class="btn btn-light" style="padding:12px 18px; text-decoration:none;">Cancel</a>
                    <?php } ?>
                </div>
            </form>
        </section>

        <section class="filters">
            <div class="panel-head">
                <div>
                    <h3>Search and Filter Challans</h3>
                    <p>Find challans by vehicle number, challan ID, official citation number, citizen email, or payment status.</p>
                </div>
            </div>

            <form method="GET" class="filter-grid">
                <div class="field">
                    <label for="search">Search Records</label>
                    <input id="search" type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by vehicle no, citizen email, challan ID, or violation">
                </div>

                <div class="field">
                    <label for="status">Payment Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                        <option value="Paid" <?php echo strtolower($statusFilter) === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Unpaid" <?php echo strtolower($statusFilter) === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>

                <div class="field">
                    <label for="violation">Violation Category</label>
                    <select id="violation" name="violation">
                        <option value="all" <?php echo $violationFilter === 'all' ? 'selected' : ''; ?>>All violations</option>
                        <?php foreach ($violationOptions as $option) { ?>
                        <option value="<?php echo e($option); ?>" <?php echo $violationFilter === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="challans.php" class="btn btn-light">Reset</a>
                </div>
            </form>
        </section>

        <section class="table-panel" style="margin-top:16px;">
            <div class="panel-head">
                <div>
                    <h3>Challan Records & Evidence Registry</h3>
                    <p>Full-page administrative view showing issued challans, linked citizen accounts, and photographic evidence captures.</p>
                </div>
                <a href="export_csv.php?type=challans" class="btn btn-light">Export CSV</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Challan ID / No</th>
                            <th>Date & Time</th>
                            <th>Vehicle Number</th>
                            <th>Linked Citizen</th>
                            <th>Violation & Details</th>
                            <th>Fine</th>
                            <th>Evidence</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($challans)) { ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:24px; color:var(--muted);">No challans match the current filter selection.</td>
                    </tr>
                    <?php } else { ?>
                    <?php foreach ($challans as $row) { ?>
                    <?php 
                        $isPaid = strtolower((string) $row['status']) === 'paid'; 
                        $chNo = !empty($row['challan_no']) ? $row['challan_no'] : ('KL-CHN-' . date('Y') . '-' . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT));
                        $dt = !empty($row['violation_date']) 
                            ? date('d M Y, h:i A', strtotime($row['violation_date']))
                            : (!empty($row['created_at']) ? date('d M Y, h:i A', strtotime($row['created_at'])) : date('d M Y, h:i A'));
                        $evDefaults = getChallanEvidence((string) $row['vehicle_no'], (string) $row['violation'], (int) $row['id'], $dt);
                        $evImg = !empty($row['evidence_photo']) ? $row['evidence_photo'] : $evDefaults['front_image'];
                    ?>
                    <tr>
                        <td>
                            <strong>#CH-<?php echo e((string) $row['id']); ?></strong><br>
                            <small style="color:var(--muted); font-size:11px; font-family:monospace;"><?php echo e($chNo); ?></small>
                        </td>
                        <td style="font-size:12px; color:#4a6279;"><?php echo e($dt); ?></td>
                        <td>
                            <div class="hsrp-pill">
                                <span class="hsrp-pill-ind">IND</span>
                                <span class="hsrp-pill-no"><?php echo e($row['vehicle_no']); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($row['citizen_user_id'])) { ?>
                            <strong style="color:#047857; font-size:12px;">User #<?php echo e((string) $row['citizen_user_id']); ?></strong><br>
                            <small style="color:var(--muted); font-size:11px;"><?php echo e($row['citizen_email'] ?? ''); ?></small>
                            <?php } else { ?>
                            <small style="color:var(--muted); font-size:12px;">User ID: #<?php echo e((string) ($row['user_id'] ?? '')); ?></small>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="violation-tag"><?php echo e($row['violation']); ?></span>
                            <?php if (!empty($row['violation_desc'])) { ?>
                            <small style="display:block; color:var(--muted); font-size:11px; margin-top:3px; max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo e($row['violation_desc']); ?>">
                                <?php echo e($row['violation_desc']); ?>
                            </small>
                            <?php } ?>
                        </td>
                        <td><strong style="color:var(--navy-900);">₹<?php echo number_format((float) $row['fine_amount'], 2); ?></strong></td>
                        <td>
                            <div class="evidence-thumb-cell">
                                <a href="<?php echo e($evImg); ?>" target="_blank" title="Click to view full photo">
                                    <img src="<?php echo e($evImg); ?>" class="evidence-thumb-img" alt="Evidence" loading="lazy">
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo $isPaid ? '✓ Paid' : '● Unpaid'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="challans.php?edit_id=<?php echo e((string) $row['id']); ?>" class="btn btn-light" style="padding:6px 10px; font-size:11px;">Edit</a>
                                <a href="receipt.php?id=<?php echo e((string) $row['id']); ?>" class="btn btn-light" style="padding:6px 10px; font-size:11px;" title="Official Citation Notice & Evidence Sheet">📄 Notice</a>
                                <?php if (!$isPaid) { ?>
                                <a href="pay.php?id=<?php echo e((string) $row['id']); ?>" target="_blank" class="btn btn-light" style="padding:6px 10px; font-size:11px;" title="QR payment gateway">📲 QR</a>
                                <form method="POST" action="mark_paid.php" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo e((string) $row['id']); ?>">
                                    <button type="submit" class="btn btn-primary" style="padding:6px 10px; font-size:11px;">Mark Paid</button>
                                </form>
                                <?php } ?>
                                <form method="POST" action="delete_challan.php" onsubmit="return confirm('Delete this challan?');" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo e((string) $row['id']); ?>">
                                    <button type="submit" class="btn danger-btn" style="padding:6px 10px; font-size:11px;">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="table-note">
                <?php if ($usingDemoData) { ?>
                Demo challans are currently shown because no matching records were found in the database.
                <?php } else { ?>
                Total <?php echo count($challans); ?> challans listed. All records are connected to the central database and reflect real-time citizen portal views.
                <?php } ?>
            </div>
        </section>

        <!-- UNIFIED BOTTOM NAVIGATION -->
        <div class="portal-bottom-nav">
            <a href="admin_dashboard.php" class="portal-nav-btn portal-nav-btn-dash">← Back to Dashboard</a>
        </div>
    </main>
</div>

<script>
const violationPresets = {
    'Helmet': {
        fine: 1000,
        desc: 'Operating or riding pillion on a two-wheeled motorcycle in a public place without wearing protective headgear conforming to the Bureau of Indian Standards (BIS/ISI standard IS:4151:2015) securely fastened by chin strap.',
        img1: 'assets/evidence/helmet_front_cam.jpg',
        img2: 'assets/evidence/helmet_side_cam.jpg'
    },
    'Overspeed': {
        fine: 2000,
        desc: 'Driving a motor vehicle in excess of the statutory speed limit prescribed by the competent authority or posted speed corridor signboards under Section 112 of the Motor Vehicles Act.',
        img1: 'assets/evidence/car_front_cam.jpg',
        img2: 'assets/evidence/car_side_cam.jpg'
    },
    'Signal Jump': {
        fine: 1500,
        desc: 'Failing to conform to traffic control signals by proceeding past the designated junction stop line and entering the intersection during the illuminated steady red light phase.',
        img1: 'assets/evidence/signal_front_cam.jpg',
        img2: 'assets/evidence/signal_side_cam.jpg'
    },
    'Mobile Usage': {
        fine: 1000,
        desc: 'Operating, holding, or communicating through a handheld mobile telephone or electronic communication device while driving a motor vehicle in motion in a public corridor.',
        img1: 'assets/evidence/mobile_front_cam.jpg',
        img2: 'assets/evidence/mobile_side_cam.jpg'
    },
    'Triple Riding': {
        fine: 1000,
        desc: 'Riding a two-wheeler with more than one pillion rider contrary to Section 128 of the Motor Vehicles Act, 1988.',
        img1: 'assets/evidence/triple_front_cam.jpg',
        img2: 'assets/evidence/triple_side_cam.jpg'
    },
    'No Seatbelt': {
        fine: 1000,
        desc: 'Driving a motor vehicle or seating in a passenger position without fastening the prescribed safety seat belt in contravention of Rule 138(3) of CMVR 1989.',
        img1: 'assets/evidence/seatbelt_front_cam.jpg',
        img2: 'assets/evidence/seatbelt_side_cam.jpg'
    },
    'Dangerous Driving': {
        fine: 2500,
        desc: 'Driving in a dangerous manner, reckless maneuvering, or endangering public safety under Section 184 of the Motor Vehicles Act, 1988.',
        img1: 'assets/evidence/dangerous_front_cam.jpg',
        img2: 'assets/evidence/dangerous_side_cam.jpg'
    },
    'Wrong Side Driving': {
        fine: 1500,
        desc: 'Driving against designated flow of traffic or violating one-way corridor restrictions contrary to Section 119/177 of the Motor Vehicles Act.',
        img1: 'assets/evidence/wrongside_front_cam.jpg',
        img2: 'assets/evidence/wrongside_side_cam.jpg'
    },
    'Wrong Parking': {
        fine: 1000,
        desc: 'Parking, halting, or abandoning a motor vehicle in an unauthorized zone, yellow kerb, or causing obstruction under Section 122 and Section 177A of the Motor Vehicles Act.',
        img1: 'assets/evidence/parking_front_cam.jpg',
        img2: 'assets/evidence/parking_side_cam.jpg'
    }
};

function onViolationChange(val) {
    if (violationPresets[val]) {
        const p = violationPresets[val];
        document.getElementById('fine_amount').value = p.fine;
        document.getElementById('violation_desc').value = p.desc;
        const sel1 = document.getElementById('evidence_photo_preset');
        if (sel1) sel1.value = p.img1;
        const sel2 = document.getElementById('evidence_photo_preset_2');
        if (sel2) sel2.value = p.img2;
    }
}

function onCitizenSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    const v = opt.getAttribute('data-vehicle');
    if (v && v.trim() !== '') {
        document.getElementById('vehicle_no').value = v.trim();
    }
}

function handleVehicleInput(val) {
    const clean = val.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    const sel = document.getElementById('user_id');
    if (!sel || clean.length < 4) return;

    for (let i = 0; i < sel.options.length; i++) {
        const optV = (sel.options[i].getAttribute('data-vehicle') || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (optV === clean && optV !== '') {
            sel.selectedIndex = i;
            break;
        }
    }
}
</script>
</body>
</html>
