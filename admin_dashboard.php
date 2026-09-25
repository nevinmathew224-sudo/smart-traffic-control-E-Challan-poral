<?php
include("db.php");
ensure_logged_in('admin');

// ===== 6 KEY OVERVIEW METRICS =====
$totalChallans = (int) (mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans"))['c'] ?? 0);
$paidChallans = (int) (mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE status='Paid'"))['c'] ?? 0);
$pendingChallans = (int) (mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE status='Unpaid'"))['c'] ?? 0);

$totalUsers = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role='user'"))['c'] ?? 0);
$totalVehicles = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT vehicle_no) c FROM (SELECT vehicle_no FROM users WHERE vehicle_no IS NOT NULL AND vehicle_no != '' UNION SELECT vehicle_no FROM challans WHERE vehicle_no IS NOT NULL AND vehicle_no != '') v"))['c'] ?? 0);
$totalFineAmount = (float) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fine_amount) s FROM challans"))['s'] ?? 0);
$revenue = (float) (mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(fine_amount) s FROM challans WHERE status='Paid'"))['s'] ?? 0);

// Normalized Violation Distribution for Clean Pie Chart
$categoryCounts = [
    'Helmet' => 0,
    'Overspeed' => 0,
    'Signal Jump' => 0,
    'Wrong Parking' => 0,
    'Mobile Usage' => 0,
    'Other' => 0
];
$vbRes = mysqli_query($conn, "SELECT violation, COUNT(*) c FROM challans GROUP BY violation");
if ($vbRes instanceof mysqli_result) {
    while ($vbRow = mysqli_fetch_assoc($vbRes)) {
        $vName = trim($vbRow['violation']);
        $count = (int)$vbRow['c'];
        if (stripos($vName, 'helmet') !== false) {
            $categoryCounts['Helmet'] += $count;
        } elseif (stripos($vName, 'speed') !== false) {
            $categoryCounts['Overspeed'] += $count;
        } elseif (stripos($vName, 'signal') !== false || stripos($vName, 'light') !== false) {
            $categoryCounts['Signal Jump'] += $count;
        } elseif (stripos($vName, 'park') !== false) {
            $categoryCounts['Wrong Parking'] += $count;
        } elseif (stripos($vName, 'mobile') !== false || stripos($vName, 'phone') !== false) {
            $categoryCounts['Mobile Usage'] += $count;
        } else {
            $categoryCounts['Other'] += $count;
        }
    }
}
$pieLabels = array_keys($categoryCounts);
$pieData = array_values($categoryCounts);
$pieLabelsJson = json_encode($pieLabels);
$pieDataJson = json_encode($pieData);

// Latest challans
$list = mysqli_query($conn,"SELECT * FROM challans ORDER BY id DESC LIMIT 10");
$latestChallans = [];
if ($list instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($list)) {
        $latestChallans[] = $row;
    }
}

// Map markers
$mapCases = [];
foreach ($latestChallans as $c) {
    $mapCases[] = [
        'lat' => 10.0 + (abs(crc32($c['vehicle_no'] ?? '')) % 150) / 100.0,
        'lng' => 76.2 + (abs(crc32($c['violation'] ?? '')) % 100) / 100.0,
        'title' => 'Challan #' . $c['id'] . ' • ' . ($c['vehicle_no'] ?? ''),
        'detail' => ($c['violation'] ?? '') . ' - ₹' . number_format((float)($c['fine_amount'] ?? 0), 2),
        'time' => $c['violation_date'] ?? ($c['created_at'] ?? date('Y-m-d H:i'))
    ];
}
$mapCasesJson = json_encode($mapCases);

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$collectionRate = $totalChallans > 0 ? round(($paidChallans / $totalChallans) * 100) : 0;
$pendingRate = $totalChallans > 0 ? round(($pendingChallans / $totalChallans) * 100) : 0;
$topViolation = 'Helmet';
$topViolationCount = $categoryCounts['Helmet'];
foreach ($categoryCounts as $cat => $cnt) {
    if ($cnt > $topViolationCount) {
        $topViolation = $cat;
        $topViolationCount = $cnt;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Command Center • Smart Traffic Control & e-Challan System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="qrcode.min.js"></script>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

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

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--ink);
    background:#f8fafc;
}

.shell{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:260px;
    background:linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
    color:#fff;
    padding:24px 18px;
    position:sticky;
    top:0;
    height:100vh;
    border-right:1px solid #334155;
    box-shadow:4px 0 24px rgba(0, 0, 0, 0.15);
    display:flex;
    flex-direction:column;
}

.seal{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px;
    margin-bottom:18px;
    border:1px solid #334155;
    border-radius:14px;
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(8px);
}

.seal-mark{
    width:44px;
    height:44px;
    border-radius:12px;
    background:#2563eb;
    display:grid;
    place-items:center;
    color:#fff;
    font-size:20px;
    box-shadow:0 4px 14px rgba(37, 99, 235, 0.3);
}

.seal small,
.status-note small{
    display:block;
    color:rgba(226,232,240,0.75);
    letter-spacing:0.08em;
    text-transform:uppercase;
    font-size:10px;
    font-weight:700;
}

.seal strong{
    display:block;
    margin-top:4px;
    font-size:17px;
    font-family:'Outfit',sans-serif;
    color:#ffffff;
}

.nav-label{
    margin:16px 10px 8px;
    color:rgba(196,181,253,0.7);
    text-transform:uppercase;
    letter-spacing:0.12em;
    font-size:10.5px;
    font-weight:800;
}

.sidebar a{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:11px 14px;
    margin:6px 0;
    color:#e2e8f0;
    text-decoration:none;
    border-radius:12px;
    border:1px solid transparent;
    font-size:13.5px;
    font-weight:600;
    transition:all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.sidebar a span{
    color:rgba(226,232,240,0.6);
    font-size:12px;
}

.sidebar a:hover,
.sidebar a.active{
    background: rgba(37, 99, 235, 0.25);
    border-color: rgba(37, 99, 235, 0.5);
    color:#ffffff;
    transform:translateX(3px);
}

.status-note{
    margin-top:14px;
    padding:14px;
    border-radius:14px;
    background:rgba(255,255,255,0.04);
    border:1px solid #334155;
}

.status-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:10px;
    padding:6px 12px;
    border-radius:999px;
    background:rgba(16, 185, 129, 0.18);
    border:1px solid rgba(16, 185, 129, 0.4);
    color:#6ee7b7;
    font-size:12px;
    font-weight:700;
}

.logout{
    justify-content:center !important;
    background:linear-gradient(135deg, #ef4444, #b91c1c) !important;
    box-shadow:0 4px 14px rgba(239, 68, 68, 0.35);
}
.logout:hover{
    transform:translateY(-2px) !important;
    box-shadow:0 6px 18px rgba(239, 68, 68, 0.5) !important;
}

.sidebar-footer{
    margin-top:auto;
    padding-top:16px;
    display:flex;
    flex-direction:column;
    gap:8px;
}

.sidebar-back-home{
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:8px !important;
    background:#0f172a !important;
    color:#ffffff !important;
    border:1px solid #334155 !important;
    font-weight:700 !important;
    box-shadow:0 2px 8px rgba(0, 0, 0, 0.2) !important;
}
.sidebar-back-home:hover{
    background:#1e293b !important;
    transform:translateY(-2px) !important;
    box-shadow:0 4px 12px rgba(0, 0, 0, 0.3) !important;
}

.main{
    flex:1;
    padding:26px 28px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    margin-bottom:20px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 14px;
    border-radius:999px;
    background:rgba(37, 99, 235, 0.08);
    border:1px solid rgba(37, 99, 235, 0.25);
    color:#2563eb;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.08em;
    text-transform:uppercase;
}

.title-block h1{
    font-family:'Outfit', sans-serif;
    margin:8px 0 6px;
    font-size:32px;
    font-weight:800;
    letter-spacing:-0.02em;
    color:#0f172a;
}

.title-block p{
    margin:0;
    max-width:700px;
    color:var(--muted);
    font-size:14px;
}

.profile-card{
    min-width:280px;
    background:#ffffff;
    border:1px solid #e2e8f0;
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
    background:#2563eb;
    color:#fff;
    font-weight:800;
    font-family:'Outfit',sans-serif;
    font-size:18px;
    box-shadow:0 4px 12px rgba(37, 99, 235, 0.25);
}

.profile-card strong{
    display:block;
    font-size:15px;
    font-weight:700;
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
    padding:8px 14px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
    transition:all 0.2s ease;
}

.toolbar .primary{
    background:#2563eb;
    color:#fff;
    box-shadow:0 4px 12px rgba(37, 99, 235, 0.25);
}
.toolbar .primary:hover{
    background:#1d4ed8;
    transform:translateY(-2px);
    box-shadow:0 6px 16px rgba(37, 99, 235, 0.35);
}

.toolbar .secondary{
    background:#ffffff;
    color:#2563eb;
    border:1px solid #bfdbfe;
}
.toolbar .secondary:hover{
    background:#eff6ff;
    border-color:#3b82f6;
    transform:translateY(-1px);
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.overview-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px 16px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.overview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}

.overview-card .card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.overview-card .card-label {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
}

.overview-card .card-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 16px;
}

.overview-card .card-value {
    font-size: 26px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    color: #0f172a;
    line-height: 1.1;
    margin-bottom: 4px;
}

.overview-card .card-subtext {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    text-decoration: none;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.08);
}

.quick-action-icon {
    font-size: 20px;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:16px;
}

.detail-strip{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:16px;
    margin-top:16px;
}

.mini-row,
.charts-row,
.row{
    display:grid;
    gap:16px;
    margin-top:16px;
}

.mini-row{
    grid-template-columns:1fr 1fr;
}

.charts-row{
    grid-template-columns:1fr 1fr;
}

.row{
    grid-template-columns:1.1fr 0.9fr;
}

.mini-panel,
.card,
.box{
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    box-shadow:var(--shadow);
    padding:20px;
    transition:all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.mini-panel:hover,
.card:hover,
.box:hover{
    border-color:#2563eb;
    box-shadow:0 8px 20px rgba(37, 99, 235, 0.12);
}

.mini-panel h3,
.card .label,
.box h3{
    margin:0 0 8px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#64748b;
    font-weight:700;
}

.mini-panel .metric{
    font-size:32px;
    font-weight:800;
    font-family:'Outfit',sans-serif;
    color:#0f172a;
}

.mini-panel .caption,
.card .trend,
.panel-head p{
    color:var(--muted);
    font-size:13px;
}

.card{
    position:relative;
    overflow:hidden;
}

.card:nth-child(1):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #2563eb, #38bdf8);
}
.card:nth-child(2):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #10b981, #059669);
}
.card:nth-child(3):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, #ef4444, #f97316);
}
.card:nth-child(4):before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:#0f766e;
}

.card .value{
    margin-top:10px;
    font-size:36px;
    font-weight:800;
    font-family:'Outfit',sans-serif;
    color:#0f172a;
}

.panel-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:14px;
}

#map{
    height:270px;
    border-radius:14px;
    overflow:hidden;
    border:1px solid var(--line);
}

.chart-box{
    height:280px;
    position:relative;
}

.summary-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:10px;
}

.detail-chip{
    padding:12px 14px;
    border-radius:14px;
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    box-shadow:var(--shadow);
}

.detail-chip small{
    display:block;
    color:#6e7f90;
    text-transform:uppercase;
    letter-spacing:0.08em;
    font-size:11px;
}

.detail-chip strong{
    display:block;
    margin-top:8px;
    font-size:18px;
    color:#11253a;
}

.detail-chip span{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
}

.box-note{
    margin-top:12px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
    color:var(--muted);
    font-size:13px;
}

.summary-chip{
    padding:12px;
    border-radius:14px;
    background:var(--panel-soft);
    border:1px solid var(--line);
}

.summary-chip strong{
    display:block;
    font-size:18px;
    color:#0d2238;
}

.summary-chip span{
    color:var(--muted);
    font-size:13px;
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
    padding:6px 12px;
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

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:92px;
    padding:9px 14px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    transition:all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pay{
    background:#2563eb !important;
    color:#ffffff !important;
    box-shadow:0 4px 12px rgba(37, 99, 235, 0.25);
}
.pay:hover{
    background:#1d4ed8 !important;
    transform:translateY(-2px);
    box-shadow:0 6px 18px rgba(37, 99, 235, 0.35);
}

.delete{
    background:#fee2e2;
    color:#b91c1c;
    border:1px solid #fecaca;
}

.admin-ev-thumbs {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.admin-ev-thumb {
    width: 44px;
    height: 32px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid #cbd5e1;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.admin-ev-thumb:hover {
    transform: scale(1.18);
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.btn-qr-view {
    background: #0f172a;
    color: #ffffff !important;
    padding: 7px 11px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    border: none;
    transition: all 0.15s ease;
}
.btn-qr-view:hover {
    background: #1e293b;
    box-shadow: 0 2px 8px rgba(15,23,42,0.3);
}

@media (max-width: 1180px){
    .overview-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }

    .quick-actions-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }

    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .detail-strip{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .mini-row,
    .charts-row,
    .row{
        grid-template-columns:1fr;
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
    .overview-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .quick-actions-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .cards{
        grid-template-columns:1fr;
    }

    .detail-strip{
        grid-template-columns:1fr;
    }

    .title-block h1{
        font-size:28px;
    }

    .toolbar{
        flex-direction:column;
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
            <div class="seal-mark">🛡️</div>
            <div>
                <small>Department Command</small>
                <strong>Traffic Control Center</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php" class="active"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Manage Challans</strong><span>Records</span></a>
        <a href="vechile.php"><strong>Manage Vehicles</strong><span>Registry</span></a>
        <a href="voilations.php"><strong>Manage Violations</strong><span>Schedule</span></a>
        <a href="users.php"><strong>Citizen Users</strong><span>Desk</span></a>
        <a href="report.php"><strong>Reports & Analytics</strong><span>Stats</span></a>
        <a href="settings.php"><strong>System Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>System Status</small>
            <strong>E-Challan Engine Active</strong>
            <div class="status-pill">● Operational</div>
        </div>

        <div class="sidebar-footer">
            <a href="index.php" class="sidebar-back-home">
                <span>←</span> <strong>Back to Home</strong>
            </a>
            <a href="logout.php" class="logout"><strong>Logout</strong></a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">
                    <span style="width:6px; height:6px; border-radius:50%; background:#0f766e; box-shadow:0 0 8px rgba(15, 118, 110, 0.6); display:inline-block;"></span>
                    <span>SMART TRAFFIC CONTROL • COMMAND CENTER</span>
                </div>
                <h1>Traffic Control Center Dashboard</h1>
                <p>Centralized traffic surveillance, automated statutory citation issuance, high-resolution evidence review, and real-time treasury analytics.</p>
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
                    <a href="challans.php" class="secondary">Review Challans</a>
                </div>
            </div>
        </div>

        <!-- 6 KEY OVERVIEW CARDS (POINT 5) -->
        <section class="overview-grid">
            <div class="overview-card">
                <div class="card-top">
                    <span class="card-label">Total Users</span>
                    <span class="card-icon" style="background:#eff6ff; color:#1d4ed8;">👥</span>
                </div>
                <div class="card-value"><?php echo number_format($totalUsers); ?></div>
                <div class="card-subtext">Registered Citizens</div>
            </div>

            <div class="overview-card">
                <div class="card-top">
                    <span class="card-label">Total Vehicles</span>
                    <span class="card-icon" style="background:#f0fdf4; color:#15803d;">🚗</span>
                </div>
                <div class="card-value"><?php echo number_format($totalVehicles); ?></div>
                <div class="card-subtext">Monitored Registry</div>
            </div>

            <div class="overview-card">
                <div class="card-top">
                    <span class="card-label">Total Challans</span>
                    <span class="card-icon" style="background:#f8fafc; color:#0f172a;">📋</span>
                </div>
                <div class="card-value"><?php echo number_format($totalChallans); ?></div>
                <div class="card-subtext">Cumulative Citations</div>
            </div>

            <div class="overview-card" style="border-left: 3px solid #f59e0b;">
                <div class="card-top">
                    <span class="card-label" style="color:#b45309;">Pending Challans</span>
                    <span class="card-icon" style="background:#fffbeb; color:#b45309;">⏳</span>
                </div>
                <div class="card-value" style="color:#b45309;"><?php echo number_format($pendingChallans); ?></div>
                <div class="card-subtext">Awaiting Settlement</div>
            </div>

            <div class="overview-card" style="border-left: 3px solid #10b981;">
                <div class="card-top">
                    <span class="card-label" style="color:#047857;">Paid Challans</span>
                    <span class="card-icon" style="background:#ecfdf5; color:#047857;">✅</span>
                </div>
                <div class="card-value" style="color:#047857;"><?php echo number_format($paidChallans); ?></div>
                <div class="card-subtext">Successfully Cleared</div>
            </div>

            <div class="overview-card" style="border-left: 3px solid #1d4ed8;">
                <div class="card-top">
                    <span class="card-label" style="color:#1d4ed8;">Total Fine Amount</span>
                    <span class="card-icon" style="background:#eff6ff; color:#1d4ed8;">💰</span>
                </div>
                <div class="card-value" style="color:#0f172a; font-size:22px;">₹<?php echo number_format($totalFineAmount, 2); ?></div>
                <div class="card-subtext">₹<?php echo number_format($revenue, 2); ?> Collected</div>
            </div>
        </section>

        <!-- GLOBAL REGISTRY SEARCH -->
        <section style="margin-bottom: 20px;">
            <form method="GET" action="global_search.php" style="display:flex; gap:10px; background:#ffffff; padding:10px; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(15,23,42,0.04);">
                <input name="q" placeholder="Search vehicle registration (e.g. KL-07-BW-1234), challan ID, citizen email, or violation..." style="flex:1; padding:10px 14px; border:1px solid #cbd5e1; border-radius:10px; font-size:14px; outline:none; font-family:inherit;">
                <button type="submit" style="padding:10px 22px; border:none; border-radius:10px; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; font-size:13.5px; transition:background 0.15s ease;">Search Records</button>
            </form>
        </section>

        <!-- CHARTS SECTION (POINT 6) -->
        <section class="charts-row" style="margin-bottom: 20px;">
            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0 0 4px; text-transform:none; letter-spacing:0;">Violations Distribution</h3>
                        <p style="margin:0; font-size:13px; color:#64748b;">Category-wise breakdown across all recorded citations</p>
                    </div>
                    <span style="font-size:12px; font-weight:700; color:#1d4ed8; background:#eff6ff; padding:4px 10px; border-radius:20px; border:1px solid #bfdbfe;">
                        <?php echo number_format($totalChallans); ?> Total Cases
                    </span>
                </div>
                <div class="chart-box" style="height:290px;">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>

            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0 0 4px; text-transform:none; letter-spacing:0;">Category Summary & Compliance</h3>
                        <p style="margin:0; font-size:13px; color:#64748b;">Key enforcement metrics and settlement progress</p>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Settlement Rate</span>
                        <div style="font-size:24px; font-weight:800; font-family:'Outfit',sans-serif; color:#047857; margin-top:4px;"><?php echo $collectionRate; ?>%</div>
                        <span style="font-size:12px; color:#64748b;"><?php echo number_format($paidChallans); ?> paid of <?php echo number_format($totalChallans); ?></span>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Pending Ratio</span>
                        <div style="font-size:24px; font-weight:800; font-family:'Outfit',sans-serif; color:#b45309; margin-top:4px;"><?php echo $pendingRate; ?>%</div>
                        <span style="font-size:12px; color:#64748b;"><?php echo number_format($pendingChallans); ?> pending settlement</span>
                    </div>
                </div>
                <div class="summary-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                    <?php 
                    $catColors = [
                        'Helmet' => '#0f172a',
                        'Overspeed' => '#2563eb',
                        'Signal Jump' => '#b45309',
                        'Wrong Parking' => '#dc2626',
                        'Mobile Usage' => '#0f766e',
                        'Other' => '#64748b'
                    ];
                    foreach ($categoryCounts as $cat => $cnt) { 
                        $dotColor = $catColors[$cat] ?? '#64748b';
                        $pct = $totalChallans > 0 ? round(($cnt / $totalChallans) * 100, 1) : 0;
                    ?>
                    <div class="summary-chip" style="padding:10px 12px; background:#ffffff; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                            <span style="width:8px; height:8px; border-radius:50%; background:<?php echo $dotColor; ?>; display:inline-block;"></span>
                            <span style="font-size:12px; font-weight:700; color:#0f172a;"><?php echo e($cat); ?></span>
                        </div>
                        <strong style="font-size:16px; color:#0f172a;"><?php echo number_format($cnt); ?></strong>
                        <span style="font-size:11px; color:#64748b;"> (<?php echo $pct; ?>%)</span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="row">
            <div class="box" style="grid-column:1 / -1;">
                <div class="panel-head">
                    <div>
                        <h3>Latest Challan Actions</h3>
                        <p>Recent challans available for review and update.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle</th>
                            <th>Violation</th>
                            <th>CCTV Evidence</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php foreach($latestChallans as $row){ ?>
                        <?php 
                        $isPaid = strtolower((string) ($row['status'] ?? '')) === 'paid';
                        $evImg1 = !empty($row['evidence_photo']) ? $row['evidence_photo'] : 'assets/evidence/helmet_front_cam.jpg';
                        $evImg2 = !empty($row['evidence_photo_2']) ? $row['evidence_photo_2'] : 'assets/evidence/helmet_side_cam.jpg';
                        $vDate = !empty($row['violation_date']) ? $row['violation_date'] : ($row['created_at'] ?? date('Y-m-d H:i'));
                        $vLoc = !empty($row['location']) ? $row['location'] : 'Kerala Traffic Corridor Node';
                        $vCam = !empty($row['camera_id']) ? $row['camera_id'] : 'ANPR Camera Node #04';
                        $vDesc = !empty($row['violation_desc']) ? $row['violation_desc'] : 'Statutory traffic violation recorded by automated sensor node.';
                        $vFine = (float) ($row['fine_amount'] ?? 0);
                        ?>
                        <tr>
                            <td><strong>#<?php echo e((string) $row['id']) ?></strong></td>
                            <td>
                                <span style="font-family:monospace; font-weight:800; background:#f1f5f9; padding:3px 8px; border-radius:6px; border:1px solid #cbd5e1;">
                                    <?php echo e($row['vehicle_no']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:700; color:#1e3a8a;">
                                    <?php echo e($row['violation']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-ev-thumbs" title="Click to view full surveillance evidence" onclick="showAdminEvidence(<?php echo (int) $row['id']; ?>, <?php echo json_encode($row['violation']); ?>, <?php echo json_encode($row['vehicle_no']); ?>, <?php echo json_encode($vLoc); ?>, <?php echo json_encode($vDate); ?>, <?php echo json_encode($evImg1); ?>, <?php echo json_encode($evImg2); ?>, <?php echo json_encode($vCam); ?>, <?php echo json_encode($vDesc); ?>, <?php echo json_encode((string) $vFine); ?>)">
                                    <img src="<?php echo e($evImg1); ?>" class="admin-ev-thumb" alt="Front Cam">
                                    <img src="<?php echo e($evImg2); ?>" class="admin-ev-thumb" alt="Lateral Cam">
                                    <span style="font-size:11px; color:#2563eb; font-weight:700; margin-left:2px; cursor:pointer;">📸 2 Views</span>
                                </div>
                            </td>
                            <td><strong style="color:var(--danger); font-size:14.5px;">₹<?php echo e((string) $row['fine_amount']) ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                    <?php echo e($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isPaid) { ?>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <span class="btn delete" style="cursor:default; padding:6px 10px;">Cleared</span>
                                        <a href="receipt.php?id=<?php echo e((string) $row['id']); ?>" target="_blank" class="secondary" style="padding:6px 10px; border-radius:8px; font-size:12px; font-weight:700; text-decoration:none;">🖨️ Receipt</a>
                                    </div>
                                <?php } else { ?>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <button type="button" class="btn-qr-view" onclick="openAdminQrModal(<?php echo (int) $row['id']; ?>, <?php echo json_encode($row['vehicle_no']); ?>, <?php echo json_encode($row['violation']); ?>, <?php echo json_encode((string) $vFine); ?>)">
                                            📲 QR
                                        </button>
                                        <form method="POST" action="mark_paid.php" style="margin:0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo e((string) $row['id']); ?>">
                                            <button type="submit" class="btn pay" style="padding:7px 11px;">Mark Paid</button>
                                        </form>
                                        <a href="pay.php?id=<?php echo e((string) $row['id']); ?>" target="_blank" style="font-size:11.5px; color:#2563eb; font-weight:700; text-decoration:none; margin-left:2px;">Gateway →</a>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="box-note">Action buttons allow the administrator to update payment status directly from the dashboard.</div>
            </div>
        </section>

        <!-- QUICK ACTIONS (POINT 11) -->
        <section class="box" style="margin-bottom:20px;">
            <div class="panel-head" style="margin-bottom:12px;">
                <div>
                    <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0 0 4px; text-transform:none; letter-spacing:0;">Quick Administrative Actions</h3>
                    <p style="margin:0; font-size:13px; color:#64748b;">Direct administrative shortcuts to department modules</p>
                </div>
            </div>
            <div class="quick-actions-grid">
                <a href="challans.php" class="quick-action-btn">
                    <span class="quick-action-icon">➕</span>
                    <span>Issue Challan</span>
                </a>
                <a href="challans.php" class="quick-action-btn">
                    <span class="quick-action-icon">📋</span>
                    <span>All Challans</span>
                </a>
                <a href="vechile.php" class="quick-action-btn">
                    <span class="quick-action-icon">🚗</span>
                    <span>Vehicle Registry</span>
                </a>
                <a href="voilations.php" class="quick-action-btn">
                    <span class="quick-action-icon">⚖️</span>
                    <span>Violations List</span>
                </a>
                <a href="users.php" class="quick-action-btn">
                    <span class="quick-action-icon">👥</span>
                    <span>Citizen Users</span>
                </a>
                <a href="report.php" class="quick-action-btn">
                    <span class="quick-action-icon">📊</span>
                    <span>Analytics Reports</span>
                </a>
            </div>
        </section>

        <!-- URBAN SURVEILLANCE CORRIDOR MAP -->
        <section class="box" style="margin-bottom:20px;">
            <div class="panel-head">
                <div>
                    <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0 0 4px; text-transform:none; letter-spacing:0;">Live Corridor Surveillance Map</h3>
                    <p style="margin:0; font-size:13px; color:#64748b;">Geographic distribution of recent ANPR camera detections across Kerala corridors</p>
                </div>
            </div>
            <div id="map" style="height:260px; border-radius:12px; border:1px solid #e2e8f0;"></div>
        </section>

        <!-- UNIFIED BOTTOM NAVIGATION -->
        <div class="portal-bottom-nav">
            <a href="index.php" class="portal-nav-btn portal-nav-btn-home">← Back to Home Page</a>
        </div>
    </main>
</div>

<script>
// ===== MAP WITH OFFLINE SAFEGUARD =====
try {
    if (typeof L !== 'undefined' && document.getElementById('map')) {
        var map = L.map('map').setView([10.1632,76.6413],7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const mapCases = <?php echo $mapCasesJson; ?>;
        if (Array.isArray(mapCases)) {
            mapCases.forEach(function(item){
                L.marker([item.lat, item.lng]).addTo(map).bindPopup(
                    '<strong>' + item.title + '</strong><br>' + item.detail + '<br>Time: ' + item.time
                );
            });
        }
    }
} catch (e) {
    console.warn('Leaflet map initialization skipped or failed:', e);
}

// ===== CLEAN PIE CHART (POINT 6) =====
try {
    if (typeof Chart !== 'undefined') {
        const pieLabels = <?php echo $pieLabelsJson; ?>;
        const pieData = <?php echo $pieDataJson; ?>;

        const pieEl = document.getElementById('pieChart');
        if (pieEl) {
            new Chart(pieEl, {
                type:'doughnut',
                data:{
                    labels:pieLabels,
                    datasets:[{
                        data:pieData,
                        backgroundColor:['#0f172a','#2563eb','#b45309','#dc2626','#0f766e','#64748b'],
                        borderWidth:2,
                        borderColor:'#ffffff',
                        hoverOffset:6
                    }]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{
                        legend:{
                            position:'bottom',
                            labels:{
                                padding:14,
                                boxWidth:12,
                                boxHeight:12,
                                usePointStyle:true,
                                pointStyle:'circle',
                                color:'#0f172a',
                                font:{size:12, weight:'600', family:"'Plus Jakarta Sans', sans-serif"}
                            }
                        },
                        tooltip:{
                            backgroundColor:'#0f172a',
                            titleColor:'#ffffff',
                            bodyColor:'#f8fafc',
                            padding:10,
                            cornerRadius:8,
                            callbacks:{
                                label:function(context){
                                    var total = context.dataset.data.reduce(function(a, b){ return a + b; }, 0);
                                    var val = context.raw || 0;
                                    var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return ' ' + context.label + ': ' + val + ' cases (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout:'62%'
                }
            });
        }
    }
} catch (e) {
    console.warn('Chart.js initialization skipped or failed:', e);
}

// ===== EVIDENCE MODAL CONTROL =====
function showAdminEvidence(id, violation, plate, location, time, img1, img2, camId, desc, fine) {
    document.getElementById('admEvTitle').textContent = 'Photographic CCTV Surveillance • Challan #' + id;
    document.getElementById('admEvCam').textContent = camId || 'Automated ANPR Sensor Node';
    document.getElementById('admEvPlate').textContent = plate;
    document.getElementById('admEvViolation').textContent = violation;
    document.getElementById('admEvLocation').textContent = location;
    document.getElementById('admEvTime').textContent = time;
    document.getElementById('admEvFine').textContent = '₹' + parseFloat(fine).toFixed(2);
    document.getElementById('admEvDesc').textContent = desc;
    document.getElementById('admEvImg1').src = img1;
    document.getElementById('admEvImg2').src = img2;
    document.getElementById('adminEvidenceModal').style.display = 'flex';
}

function closeAdminEvidence() {
    document.getElementById('adminEvidenceModal').style.display = 'none';
}

// ===== QR MODAL CONTROL =====
function openAdminQrModal(id, vehicleNo, violation, fineAmount) {
    document.getElementById('admQrBadge').textContent = '#CH-' + id;
    document.getElementById('admQrPlate').textContent = vehicleNo;
    document.getElementById('admQrAmount').textContent = '₹' + parseFloat(fineAmount).toFixed(2);

    const qrBox = document.getElementById('admQrCanvas');
    qrBox.innerHTML = '';

    const upiPa = 'keralatraffic.treasury@gov.in';
    const upiPn = 'Traffic Police Department';
    const upiTn = 'Challan CH' + id + ' ' + vehicleNo;
    const formattedAmount = parseFloat(fineAmount).toFixed(2);
    const upiUri = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=CH' + id + '&tr=' + id + '&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);

    try {
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrBox, {
                text: upiUri,
                width: 170,
                height: 170,
                colorDark: "#0d1b2a",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=' + encodeURIComponent(upiUri);
            img.width = 170;
            img.height = 170;
            qrBox.appendChild(img);
        }
    } catch(e) {
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=' + encodeURIComponent(upiUri);
        img.width = 170;
        img.height = 170;
        qrBox.appendChild(img);
    }

    document.getElementById('admPayLink').href = 'pay.php?id=' + id;
    document.getElementById('adminQrModal').style.display = 'flex';
}

function closeAdminQrModal() {
    document.getElementById('adminQrModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminEvidence();
        closeAdminQrModal();
    }
});
</script>

<!-- MODALS -->
<!-- 1. CCTV DUAL EVIDENCE MODAL -->
<div id="adminEvidenceModal" class="help-modal-overlay" style="display:none;" onclick="if(event.target===this) closeAdminEvidence()">
    <div class="help-modal-card" style="width:min(720px, 94vw);">
        <div class="help-modal-head" style="background:#0f172a; border-bottom:1px solid #334155;">
            <div>
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#fff;" id="admEvTitle">CCTV Surveillance Evidence</h3>
                <small style="color:#93c5fd; font-size:11px;" id="admEvCam">Camera Sensor Node</small>
            </div>
            <button type="button" onclick="closeAdminEvidence()" style="background:none; border:none; color:#ffffff; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        <div class="help-modal-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div style="background:#0f172a; border-radius:10px; overflow:hidden; border:1px solid #334155; text-align:center;">
                    <div style="background:#1e293b; padding:4px 8px; font-size:10.5px; font-weight:700; color:#38bdf8;">CAM 1 • FRONT ANPR MATCH</div>
                    <img id="admEvImg1" src="" alt="Front Cam" style="width:100%; height:180px; object-fit:cover; display:block;">
                </div>
                <div style="background:#0f172a; border-radius:10px; overflow:hidden; border:1px solid #334155; text-align:center;">
                    <div style="background:#1e293b; padding:4px 8px; font-size:10.5px; font-weight:700; color:#38bdf8;">CAM 2 • LATERAL RADAR CONTEXT</div>
                    <img id="admEvImg2" src="" alt="Lateral Cam" style="width:100%; height:180px; object-fit:cover; display:block;">
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:13px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                    <div><small style="color:#64748b; font-size:11px; display:block;">Vehicle Registration</small><strong id="admEvPlate" style="font-family:monospace; font-size:13px;">-</strong></div>
                    <div><small style="color:#64748b; font-size:11px; display:block;">Violation Type</small><strong id="admEvViolation" style="color:#1d4ed8;">-</strong></div>
                    <div><small style="color:#64748b; font-size:11px; display:block;">Fine Amount</small><strong id="admEvFine" style="color:var(--danger);">-</strong></div>
                </div>
                <div style="border-top:1px dashed #cbd5e1; padding-top:8px; font-size:12px; color:#475569;">
                    <strong>Location:</strong> <span id="admEvLocation"></span> &nbsp;•&nbsp; <strong>Time:</strong> <span id="admEvTime"></span><br>
                    <strong>Statutory Offense:</strong> <span id="admEvDesc"></span>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button type="button" onclick="closeAdminEvidence()" style="padding:8px 20px; border:none; background:#0f172a; color:#fff; border-radius:8px; font-weight:700; cursor:pointer;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. INSTANT QR MODAL -->
<div id="adminQrModal" class="help-modal-overlay" style="display:none;" onclick="if(event.target===this) closeAdminQrModal()">
    <div class="help-modal-card" style="width:360px; text-align:center;">
        <div class="help-modal-head" style="background:#0f172a; border-bottom:1px solid #334155;">
            <div>
                <h3 style="margin:0; font-size:15px; font-weight:800; color:#fff;">Instant Citizen UPI QR</h3>
                <small style="color:#94a3b8; font-size:11px;">Direct scan or counter settlement</small>
            </div>
            <button type="button" onclick="closeAdminQrModal()" style="background:none; border:none; color:#ffffff; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        <div class="help-modal-body" style="padding:20px;">
            <div style="margin-bottom:10px;">
                <span id="admQrBadge" style="display:inline-block; padding:2px 8px; border-radius:6px; background:#eff6ff; color:#1d4ed8; font-weight:800; font-size:12px;">#CH-0</span>
                <span id="admQrPlate" style="font-weight:800; font-family:monospace; margin-left:6px; font-size:13px;"></span>
            </div>
            <div style="font-size:24px; font-weight:900; color:var(--danger); margin-bottom:12px;" id="admQrAmount">₹0.00</div>

            <div style="display:inline-block; padding:10px; background:#ffffff; border:2px solid #0f172a; border-radius:14px; margin-bottom:12px;">
                <div id="admQrCanvas"></div>
            </div>

            <div style="font-size:12px; color:#64748b; margin-bottom:14px;">
                Scan with any UPI app (GPay, PhonePe, Paytm, BHIM)
            </div>

            <div style="display:flex; flex-direction:column; gap:8px;">
                <a id="admPayLink" href="#" target="_blank" style="padding:9px; background:var(--danger); color:#ffffff; font-weight:800; text-decoration:none; border-radius:8px; font-size:13px;">
                    Open Official Payment Gateway →
                </a>
                <button type="button" onclick="closeAdminQrModal()" style="padding:8px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; font-weight:700; cursor:pointer; font-size:12.5px; color:#475569;">Close</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
