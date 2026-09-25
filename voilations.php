<?php
include("db.php");
ensure_logged_in('admin');

$violations = [
    ['type' => 'Helmet', 'fine' => 1000],
    ['type' => 'Overspeed', 'fine' => 2000],
    ['type' => 'Signal Jump', 'fine' => 1500],
    ['type' => 'Mobile Usage', 'fine' => 1000],
    ['type' => 'Triple Riding', 'fine' => 1000],
    ['type' => 'No Seatbelt', 'fine' => 1000],
    ['type' => 'Dangerous Driving', 'fine' => 2500],
    ['type' => 'Wrong Side Driving', 'fine' => 1500],
    ['type' => 'Wrong Parking', 'fine' => 1000],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Violation Types</title>
<link rel="stylesheet" href="kerala-theme.css">
<style>
body{font-family:'Segoe UI',Tahoma,sans-serif;margin:0;padding:24px;background:#f4fbf2;color:#182433}
.panel{max-width:900px;margin:auto;background:#fff;border:1px solid #cfe4d4;border-radius:14px;padding:24px}
table{width:100%;border-collapse:collapse;margin-top:16px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}
a{color:#0f8a50;font-weight:700}
</style>
</head>
<body>
<main class="panel">
<h1>Violation Types & Statutory Fines</h1>
<p style="color:#64748b; font-size:14px; margin-top:4px;">Official schedule of traffic violations and penalties administered by the automated enforcement system.</p>
<table>
<tr><th>ID</th><th>Violation Type</th><th>Fine Amount</th><th>Status</th></tr>
<?php foreach ($violations as $index => $violation) { ?>
<tr>
<td><?php echo e((string) ($index + 1)); ?></td>
<td><strong><?php echo e($violation['type']); ?></strong></td>
<td><strong style="color:#0f766e;">₹<?php echo e((string) $violation['fine']); ?></strong></td>
<td><span style="display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#dcfce7; color:#15803d;">Active Enforced</span></td>
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
