<?php
include("db.php");
ensure_logged_in('admin');

$locations = [
    ['area' => 'Kochi - MG Road', 'focus' => 'Signal Jump'],
    ['area' => 'Thiruvananthapuram - Palayam', 'focus' => 'Helmet'],
    ['area' => 'Kozhikode - Mavoor Road', 'focus' => 'Overspeed'],
    ['area' => 'Thrissur - Swaraj Round', 'focus' => 'Mobile Usage'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kerala Locations</title>
<link rel="stylesheet" href="kerala-theme.css">
<style>
body{font-family:'Segoe UI',Tahoma,sans-serif;margin:0;padding:24px;background:#f4fbf2;color:#182433}
.panel{max-width:900px;margin:auto;background:#fff;border:1px solid #cfe4d4;border-radius:14px;padding:20px}
table{width:100%;border-collapse:collapse;margin-top:16px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}
a{color:#0f8a50;font-weight:700}
</style>
</head>
<body>
<main class="panel">
<h1>Kerala Monitoring Locations</h1>
<p style="color:#64748b; font-size:14px; margin-top:4px;">Strategic surveillance corridors monitored by automated cameras and sensors.</p>
<table>
<tr><th>ID</th><th>Monitoring Corridor</th><th>Primary Violation Monitored</th><th>Status</th></tr>
<?php foreach ($locations as $index => $location) { ?>
<tr>
<td><?php echo e((string) ($index + 1)); ?></td>
<td><strong><?php echo e($location['area']); ?></strong></td>
<td><?php echo e($location['focus']); ?></td>
<td><span style="display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#e0f2fe; color:#0369a1;">Active Radar</span></td>
</tr>
<?php } ?>
</table>

<!-- UNIFIED BOTTOM NAVIGATION -->
<div class="portal-bottom-nav">
    <a href="admin_dashboard.php" class="portal-nav-btn portal-nav-btn-dash">← Back to Dashboard</a>
</div>
</main>
</body>
</html>
