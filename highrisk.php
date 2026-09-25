<?php
include("db.php");
ensure_logged_in('admin');

$result = mysqli_query($conn, "
    SELECT vehicle_no, COUNT(*) AS total_cases,
           SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid_cases,
           COALESCE(SUM(fine_amount), 0) AS total_fine
    FROM challans
    GROUP BY vehicle_no
    HAVING total_cases >= 3 OR unpaid_cases >= 2 OR total_fine >= 5000
    ORDER BY total_cases DESC, total_fine DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>High Risk Vehicles</title>
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
<h1>High Risk Vehicles</h1>
<p style="color:#64748b; font-size:14px; margin-top:4px;">Vehicles flagged for repeat offenses or critical unpaid fine exposure.</p>
<table>
<tr><th>Vehicle Number</th><th>Total Violations</th><th>Unpaid Cases</th><th>Total Penalty</th></tr>
<?php if ($result instanceof mysqli_result && mysqli_num_rows($result) > 0) { ?>
<?php while ($row = mysqli_fetch_assoc($result)) { ?>
<tr>
<td><span class="plate-tag" style="font-weight:700; background:#fef2f2; color:#b91c1c; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;"><?php echo e($row['vehicle_no']); ?></span></td>
<td><?php echo e((string) $row['total_cases']); ?></td>
<td><strong style="color:#b91c1c;"><?php echo e((string) $row['unpaid_cases']); ?></strong></td>
<td><strong style="color:#1e293b;">₹<?php echo e((string) $row['total_fine']); ?></strong></td>
</tr>
<?php } ?>
<?php } else { ?>
<tr><td colspan="4">No high-risk vehicles found.</td></tr>
<?php } ?>
</table>

<!-- UNIFIED BOTTOM NAVIGATION -->
<div class="portal-bottom-nav">
    <a href="admin_dashboard.php" class="portal-nav-btn portal-nav-btn-dash">← Back to Dashboard</a>
</div>
</main>
</body>
</html>
