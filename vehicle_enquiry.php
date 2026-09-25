<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include("db.php");
require_once("challan_helper.php");

$rawLookup = trim($_POST['lookup_vehicle_no'] ?? ($_GET['vno'] ?? ''));
$lookupVehicle = strtoupper($rawLookup);
$lookupClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawLookup));

$searched = ($rawLookup !== '');
$lookupError = '';
$lookupResult = null;
$lookupChallans = [];
$lookupUser = null;

$stats = [
    'total_cases' => 0,
    'paid_cases' => 0,
    'unpaid_cases' => 0,
    'total_fine' => 0.0,
    'pending_fine' => 0.0,
];

if ($searched) {
    if (strlen($lookupClean) < 4) {
        $lookupError = 'Please enter a valid vehicle registration number (e.g. DL01AB1234 or KL07AB1234).';
    } else {
        // Query citizen owner linked to this vehicle
        $uLookupStmt = $conn->prepare("
            SELECT u.id, u.email, u.vehicle_no, u.created_at
            FROM users u
            LEFT JOIN challans c ON c.user_id = u.id
            WHERE u.vehicle_no = ? OR REPLACE(u.vehicle_no, ' ', '') = ?
               OR c.vehicle_no = ? OR REPLACE(c.vehicle_no, ' ', '') = ?
            LIMIT 1
        ");
        if ($uLookupStmt) {
            $uLookupStmt->bind_param("ssss", $lookupClean, $lookupClean, $lookupClean, $lookupClean);
            $uLookupStmt->execute();
            $lookupUser = $uLookupStmt->get_result()->fetch_assoc();
            $uLookupStmt->close();
        }

        // Query all challans linked to this vehicle
        $cLookupStmt = $conn->prepare("
            SELECT id, user_id, vehicle_no, challan_no, violation, violation_desc, fine_amount, status, created_at, violation_date, location, camera_id, officer_name, evidence_photo, evidence_photo_2
            FROM challans
            WHERE vehicle_no = ? OR REPLACE(vehicle_no, ' ', '') = ?
            ORDER BY id DESC
        ");
        if ($cLookupStmt) {
            $cLookupStmt->bind_param("ss", $lookupClean, $lookupClean);
            $cLookupStmt->execute();
            $cRes = $cLookupStmt->get_result();
            while ($row = $cRes->fetch_assoc()) {
                $lookupChallans[] = $row;
                $amt = (float) $row['fine_amount'];
                $stats['total_cases']++;
                $stats['total_fine'] += $amt;
                if (strtolower((string) $row['status']) === 'paid') {
                    $stats['paid_cases']++;
                } else {
                    $stats['unpaid_cases']++;
                    $stats['pending_fine'] += $amt;
                }
            }
            $cLookupStmt->close();
        }

        $sampleViolation = count($lookupChallans) > 0 ? $lookupChallans[0]['violation'] : '';
        $lookupResult = getVehicleDetails($lookupClean, $sampleViolation);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Enquiry & Challan Search | Traffic Control e-Challan Portal</title>
    <link rel="stylesheet" href="traffic_animated_theme.css?v=6">
    <link rel="stylesheet" href="kerala-theme.css?v=5">
    <script src="qrcode.min.js"></script>
    <style>
        .enquiry-ev-thumb {
            width: 60px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .enquiry-ev-thumb:hover {
            transform: scale(1.12);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border-color: #2563eb;
        }
        .qr-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 21, 35, 0.85);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .qr-modal-card {
            background: #ffffff;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
            animation: modalIn 0.22s ease-out;
        }
        @keyframes modalIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- TOP GOVERNMENT STRIP -->
    <div class="portal-top-bar">
        <div class="portal-top-bar-inner">
            <div class="top-authority-tag">
                <span>🇮🇳</span>
                <span>Ministry of Road Transport & Highways • State Traffic Police Department</span>
            </div>
            <div class="top-helpline-meta">
                <span>Public Information Line: <strong>1099</strong></span>
            </div>
        </div>
    </div>

    <!-- MAIN ENQUIRY CONTAINER -->
    <main style="max-width:1100px; margin: 36px auto; padding: 0 24px;">

        <!-- SEARCH HERO PANEL -->
        <div style="background:#ffffff; border:1.5px solid var(--border-subtle); border-radius:16px; padding:32px 28px; box-shadow:var(--shadow-card); margin-bottom:28px;">
            <div style="margin-bottom:20px;">
                <span class="section-kicker">SMART TRAFFIC CONTROL • PUBLIC SEARCH</span>
                <h2 style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:var(--gov-navy-950); margin-top:4px;">
                    Vehicle Registration & e-Challan Enquiry
                </h2>
                <p style="font-size:14px; color:var(--text-muted); margin-top:2px;">
                    Enter any motor vehicle registration number to check statutory RC details, active ANPR speed violations, and pending traffic citations under CMVR.
                </p>
            </div>

            <form method="GET" action="vehicle_enquiry.php">
                <div style="display:flex; gap:12px; max-width:680px;">
                    <div class="hsrp-input-cell">
                        <div class="hsrp-ind-tag">
                            <span style="width:5px; height:5px; border:1px dashed #fff; border-radius:50%; margin-bottom:1px;"></span>
                            <span>IND</span>
                        </div>
                        <input type="text" name="vno" value="<?php echo e($lookupVehicle); ?>" placeholder="ENTER VEHICLE NUMBER (e.g. DL01AB1234)" required autocomplete="off" style="text-transform:uppercase;">
                    </div>
                    <button type="submit" class="btn-instant-search" style="padding:0 24px; font-size:14px;">
                        <span>Search Challans →</span>
                    </button>
                </div>
            </form>

            <div class="sample-pills-row" style="margin-top:14px;">
                <span>Quick Test Vehicles:</span>
                <a href="vehicle_enquiry.php?vno=DL01AB1234" class="sample-pill-btn">DL01AB1234</a>
                <a href="vehicle_enquiry.php?vno=DL05CD7788" class="sample-pill-btn">DL05CD7788</a>
                <a href="vehicle_enquiry.php?vno=DL03EF4567" class="sample-pill-btn">DL03EF4567</a>
                <a href="vehicle_enquiry.php?vno=KL07AB1234" class="sample-pill-btn">KL07AB1234</a>
            </div>

            <?php if ($lookupError !== '') { ?>
            <div class="clean-alert clean-alert-error" style="margin-top:16px;">
                <span>⚠️</span>
                <div><?php echo e($lookupError); ?></div>
            </div>
            <?php } ?>
        </div>

        <!-- SEARCH RESULTS -->
        <?php if ($searched && $lookupResult !== null) { ?>
            
            <div style="background:#ffffff; border:1.5px solid var(--border-subtle); border-radius:16px; padding:32px 28px; box-shadow:var(--shadow-card);">
                
                <!-- HSRP Plate & Vehicle Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px; padding-bottom:18px; border-bottom:1.5px solid var(--border-subtle);">
                    <div>
                        <span style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Verified RC Record</span>
                        <h3 style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:var(--gov-navy-950); margin-top:2px;">
                            <?php echo e($lookupResult['make'] . ' ' . $lookupResult['model']); ?>
                        </h3>
                    </div>

                    <div class="hsrp-input-cell" style="display:inline-flex; width:auto;">
                        <div class="hsrp-ind-tag">
                            <span style="width:6px; height:6px; border:1px dashed #fff; border-radius:50%; margin-bottom:2px;"></span>
                            <span>IND</span>
                        </div>
                        <div style="padding:6px 18px; font-family:'Outfit', monospace; font-size:22px; font-weight:800; color:#0f172a;">
                            <?php echo e($lookupResult['formatted_number']); ?>
                        </div>
                    </div>
                </div>

                <!-- 4 Summary Metric Boxes -->
                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:28px;">
                    <div style="background:var(--surface-soft); border:1px solid var(--border-subtle); border-radius:10px; padding:14px;">
                        <span style="font-size:11.5px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Total Citations</span>
                        <div style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:var(--gov-navy-950); margin-top:4px;">
                            <?php echo $stats['total_cases']; ?>
                        </div>
                    </div>
                    <div style="background:var(--traffic-red-soft); border:1px solid var(--traffic-red-border); border-radius:10px; padding:14px;">
                        <span style="font-size:11.5px; color:#b91c1c; font-weight:700; text-transform:uppercase;">Unpaid Citations</span>
                        <div style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:#dc2626; margin-top:4px;">
                            <?php echo $stats['unpaid_cases']; ?>
                        </div>
                    </div>
                    <div style="background:var(--traffic-green-soft); border:1px solid var(--traffic-green-border); border-radius:10px; padding:14px;">
                        <span style="font-size:11.5px; color:#047857; font-weight:700; text-transform:uppercase;">Settled / Cleared</span>
                        <div style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:#059669; margin-top:4px;">
                            <?php echo $stats['paid_cases']; ?>
                        </div>
                    </div>
                    <div style="background:var(--traffic-amber-soft); border:1px solid var(--traffic-amber-border); border-radius:10px; padding:14px;">
                        <span style="font-size:11.5px; color:#b45309; font-weight:700; text-transform:uppercase;">Pending Fine Due</span>
                        <div style="font-family:'Outfit', sans-serif; font-size:24px; font-weight:800; color:#d97706; margin-top:4px;">
                            ₹<?php echo number_format($stats['pending_fine'], 0); ?>
                        </div>
                    </div>
                </div>

                <!-- Two Columns: RC Specs & Challans List -->
                <div style="display:grid; grid-template-columns: 1fr 1.6fr; gap:24px; align-items:start;">
                    
                    <!-- Specifications -->
                    <div style="background:var(--surface-soft); border:1px solid var(--border-subtle); border-radius:12px; padding:20px;">
                        <h4 style="font-family:'Outfit', sans-serif; font-size:15px; font-weight:800; color:var(--gov-navy-950); margin-bottom:14px; text-transform:uppercase; letter-spacing:0.04em;">
                            Registration Certificate (RC)
                        </h4>
                        
                        <table style="width:100%; font-size:13px; border-collapse:collapse;">
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 0; color:var(--text-muted); width:38%;">Vehicle Class:</td>
                                <td style="padding:8px 0; font-weight:600;"><?php echo e($lookupResult['vehicle_class']); ?></td>
                            </tr>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 0; color:var(--text-muted);">Fuel & Color:</td>
                                <td style="padding:8px 0; font-weight:600;"><?php echo e($lookupResult['fuel_type'] . ' • ' . $lookupResult['color']); ?></td>
                            </tr>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 0; color:var(--text-muted);">Issuing RTO:</td>
                                <td style="padding:8px 0; font-weight:600;"><?php echo e($lookupResult['rto_authority']); ?></td>
                            </tr>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 0; color:var(--text-muted);">Insurance:</td>
                                <td style="padding:8px 0;">
                                    <span style="display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; background:var(--traffic-green-soft); color:#047857; border:1px solid var(--traffic-green-border);">
                                        ✓ <?php echo e($lookupResult['insurance_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0; color:var(--text-muted);">Registered Citizen:</td>
                                <td style="padding:8px 0;">
                                    <?php if ($lookupUser) { ?>
                                        <span style="color:#047857; font-weight:700;">Citizen ID #<?php echo e((string) $lookupUser['id']); ?></span>
                                    <?php } else { ?>
                                        <span style="color:var(--text-muted);">Public Record</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        </table>

                        <?php if ($lookupUser) { ?>
                        <div style="margin-top:16px;">
                            <a href="user_login.php?user=<?php echo urlencode((string) $lookupUser['id']); ?>" class="btn-gateway-cta" style="background:var(--gov-blue); color:#ffffff; padding:10px 14px; font-size:13px; text-decoration:none;">
                                <span>Sign In as Citizen #<?php echo e((string) $lookupUser['id']); ?> →</span>
                            </a>
                        </div>
                        <?php } else { ?>
                        <div style="margin-top:16px;">
                            <a href="register.php?vno=<?php echo urlencode($lookupClean); ?>" class="btn-gateway-cta" style="background:#ffffff; border:1.5px solid var(--gov-blue); color:var(--gov-blue); padding:10px 14px; font-size:13px; text-decoration:none;">
                                <span>Register This Vehicle →</span>
                            </a>
                        </div>
                        <?php } ?>
                    </div>

                    <!-- Challans List -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h4 style="font-family:'Outfit', sans-serif; font-size:16px; font-weight:800; color:var(--gov-navy-950);">
                                Citations Registered (<?php echo count($lookupChallans); ?>)
                            </h4>
                            <span style="font-size:13px; color:var(--text-muted);">
                                Due Amount: <strong style="color:var(--traffic-red);">₹<?php echo number_format($stats['pending_fine'], 2); ?></strong>
                            </span>
                        </div>

                        <?php if (count($lookupChallans) === 0) { ?>
                            <div style="background:var(--traffic-green-soft); border:1px dashed var(--traffic-green-border); border-radius:12px; padding:28px; text-align:center; color:#065f46;">
                                <div style="font-size:28px; margin-bottom:4px;">🟢</div>
                                <strong style="font-size:16px;">Zero Violations Found!</strong>
                                <p style="font-size:13px; margin-top:2px;">This vehicle has no pending e-challans or unpaid citations.</p>
                            </div>
                        <?php } else { ?>
                            <div style="border:1px solid var(--border-subtle); border-radius:10px; overflow-x:auto;">
                                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                    <thead>
                                        <tr style="background:#f8fafc; border-bottom:1.5px solid var(--border-subtle);">
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Challan ID / Date</th>
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Violation & Location</th>
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Evidence</th>
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Fine</th>
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Status</th>
                                            <th style="padding:10px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted);">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lookupChallans as $c) { ?>
                                        <?php 
                                            $isPaid = (strtolower((string) $c['status']) === 'paid'); 
                                            $cId = (int) $c['id'];
                                            $cNumber = !empty($c['challan_no']) ? $c['challan_no'] : ('#CH-' . $cId);
                                            $dtRaw = !empty($c['violation_date']) ? $c['violation_date'] : $c['created_at'];
                                            $dtFormatted = !empty($dtRaw) ? date('d M Y, h:i A', strtotime($dtRaw)) : 'Recent';
                                            $cEvDefaults = getChallanEvidence((string) $c['vehicle_no'], (string) $c['violation'], $cId, $dtRaw);
                                            $img1 = !empty($c['evidence_photo']) ? $c['evidence_photo'] : $cEvDefaults['front_image'];
                                            $img2 = !empty($c['evidence_photo_2']) ? $c['evidence_photo_2'] : $cEvDefaults['side_image'];
                                            $camId = !empty($c['camera_id']) ? $c['camera_id'] : $cEvDefaults['front_camera_id'];
                                            $locText = !empty($c['location']) ? $c['location'] : $cEvDefaults['location'];
                                            $descText = !empty($c['violation_desc']) ? $c['violation_desc'] : 'Statutory traffic violation recorded by automated sensor node.';
                                        ?>
                                        <tr style="border-bottom:1px solid var(--border-subtle);">
                                            <td style="padding:10px 12px;">
                                                <strong><?php echo e($cNumber); ?></strong>
                                                <div style="font-size:11px; color:var(--text-muted);"><?php echo e($dtFormatted); ?></div>
                                            </td>
                                            <td style="padding:10px 12px;">
                                                <div style="font-weight:700; color:var(--gov-navy-950);"><?php echo e($c['violation']); ?></div>
                                                <div style="font-size:11px; color:var(--text-muted); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo e($locText); ?>"><?php echo e($locText); ?></div>
                                            </td>
                                            <td style="padding:10px 12px;">
                                                <div style="display:flex; gap:6px; align-items:center;">
                                                    <img src="<?php echo e($img1); ?>" class="enquiry-ev-thumb" alt="Front Camera" title="Front Camera (Click to zoom)" onclick="showFullEvidence('<?php echo e($cNumber); ?>', '<?php echo e($c['violation']); ?>', '<?php echo e($lookupResult['formatted_number']); ?>', '<?php echo e($locText); ?>', '<?php echo e($dtFormatted); ?>', '<?php echo e($img1); ?>', '<?php echo e($img2); ?>', '<?php echo e($camId); ?>', '<?php echo addslashes($descText); ?>', '<?php echo number_format((float)$c['fine_amount'], 2); ?>')">
                                                    <img src="<?php echo e($img2); ?>" class="enquiry-ev-thumb" alt="Side Profile" title="Lateral Camera (Click to zoom)" onclick="showFullEvidence('<?php echo e($cNumber); ?>', '<?php echo e($c['violation']); ?>', '<?php echo e($lookupResult['formatted_number']); ?>', '<?php echo e($locText); ?>', '<?php echo e($dtFormatted); ?>', '<?php echo e($img1); ?>', '<?php echo e($img2); ?>', '<?php echo e($camId); ?>', '<?php echo addslashes($descText); ?>', '<?php echo number_format((float)$c['fine_amount'], 2); ?>')">
                                                </div>
                                            </td>
                                            <td style="padding:10px 12px;">
                                                <strong style="color:<?php echo $isPaid ? 'var(--traffic-green)' : 'var(--traffic-red)'; ?>; font-size:13.5px;">
                                                    ₹<?php echo number_format((float) $c['fine_amount'], 2); ?>
                                                </strong>
                                            </td>
                                            <td style="padding:10px 12px;">
                                                <span style="display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; <?php echo $isPaid ? 'background:var(--traffic-green-soft); color:#047857; border:1px solid var(--traffic-green-border);' : 'background:var(--traffic-red-soft); color:#dc2626; border:1px solid var(--traffic-red-border);'; ?>">
                                                    <?php echo $isPaid ? 'Paid' : 'Unpaid'; ?>
                                                </span>
                                            </td>
                                            <td style="padding:10px 12px;">
                                                <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                                    <button type="button" onclick="showFullEvidence('<?php echo e($cNumber); ?>', '<?php echo e($c['violation']); ?>', '<?php echo e($lookupResult['formatted_number']); ?>', '<?php echo e($locText); ?>', '<?php echo e($dtFormatted); ?>', '<?php echo e($img1); ?>', '<?php echo e($img2); ?>', '<?php echo e($camId); ?>', '<?php echo addslashes($descText); ?>', '<?php echo number_format((float)$c['fine_amount'], 2); ?>')" style="padding:4px 8px; border-radius:6px; background:#f1f5f9; border:1px solid #cbd5e1; font-size:11px; font-weight:700; cursor:pointer; color:#1e293b;">
                                                        📸 Evidence
                                                    </button>
                                                    <?php if ($isPaid) { ?>
                                                        <a href="receipt.php?id=<?php echo $cId; ?>" target="_blank" style="padding:4px 8px; border-radius:6px; background:#eff6ff; color:#1d4ed8; font-size:11px; font-weight:700; text-decoration:none;">
                                                            📄 Receipt
                                                        </a>
                                                    <?php } else { ?>
                                                        <button type="button" onclick="openEnquiryQrModal('<?php echo $cId; ?>', '<?php echo e($c['vehicle_no']); ?>', '<?php echo e($c['violation']); ?>', '<?php echo (float)$c['fine_amount']; ?>')" style="padding:5px 10px; border-radius:6px; background:var(--traffic-red); color:#ffffff; font-size:11px; font-weight:700; border:none; cursor:pointer;">
                                                            📲 Pay QR
                                                        </button>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>

                </div>

            </div>

        <?php } ?>

        <!-- UNIFIED BOTTOM NAVIGATION -->
        <div class="portal-bottom-nav">
            <a href="index.php" class="portal-nav-btn portal-nav-btn-home">← Back to Home Page</a>
        </div>

    </main>

    <!-- REAL EVIDENCE PHOTO MODAL WITH DUAL CAMERA ANGLES -->
    <div id="evidenceModal" style="display:none; position:fixed; inset:0; background:rgba(10,21,35,0.85); z-index:9999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(5px);" onclick="if(event.target === this) closeEvidence()">
        <div style="background:#ffffff; border-radius:18px; max-width:760px; width:100%; max-height:92vh; overflow-y:auto; padding:24px; box-shadow:0 25px 60px rgba(0,0,0,0.45); border:1px solid #e2e8f0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
                <div>
                    <h4 id="evModalTitle" style="font-family:'Outfit',sans-serif; font-size:17px; font-weight:800; color:var(--gov-navy-950); margin:0;">CCTV Photographic Evidence</h4>
                    <small id="evCameraNode" style="color:var(--text-muted); font-size:11.5px;"></small>
                </div>
                <button type="button" onclick="closeEvidence()" style="background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-muted); padding:4px 8px;">✕</button>
            </div>

            <!-- Dual CCTV Cameras -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; margin-bottom:16px;">
                <div style="background:#0f172a; border-radius:12px; overflow:hidden; border:1px solid #334155;">
                    <div style="padding:6px 12px; background:rgba(255,255,255,0.06); color:#94a3b8; font-size:10.5px; font-weight:700; letter-spacing:0.5px; display:flex; justify-content:space-between;">
                        <span>CAM 1 • ANPR FRONT VIEW</span>
                        <span style="color:#38bdf8;">AI CONFIRMED</span>
                    </div>
                    <img id="evImg1" src="" alt="Front Cam Evidence" style="width:100%; height:200px; object-fit:cover; display:block;">
                </div>
                <div style="background:#0f172a; border-radius:12px; overflow:hidden; border:1px solid #334155;">
                    <div style="padding:6px 12px; background:rgba(255,255,255,0.06); color:#94a3b8; font-size:10.5px; font-weight:700; letter-spacing:0.5px; display:flex; justify-content:space-between;">
                        <span>CAM 2 • LATERAL CORRIDOR VIEW</span>
                        <span style="color:#34d399;">VERIFIED</span>
                    </div>
                    <img id="evImg2" src="" alt="Side Cam Evidence" style="width:100%; height:200px; object-fit:cover; display:block;">
                </div>
            </div>

            <!-- Violation Details Info Grid -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:14px;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; font-size:12.5px;">
                    <div>
                        <small style="color:var(--text-muted); display:block; font-size:10.5px; font-weight:700; text-transform:uppercase;">Vehicle Reg Plate</small>
                        <strong id="evPlate" style="color:var(--gov-navy-950); font-family:monospace; font-size:14px;">-</strong>
                    </div>
                    <div>
                        <small style="color:var(--text-muted); display:block; font-size:10.5px; font-weight:700; text-transform:uppercase;">Violation Category</small>
                        <strong id="evViolation" style="color:var(--gov-blue);">-</strong>
                    </div>
                    <div>
                        <small style="color:var(--text-muted); display:block; font-size:10.5px; font-weight:700; text-transform:uppercase;">Date & Time</small>
                        <strong id="evTimestamp" style="color:var(--gov-navy-950);">-</strong>
                    </div>
                    <div>
                        <small style="color:var(--text-muted); display:block; font-size:10.5px; font-weight:700; text-transform:uppercase;">Statutory Fine</small>
                        <strong id="evFine" style="color:var(--traffic-red); font-size:14px;">-</strong>
                    </div>
                </div>
                <div style="margin-top:10px; font-size:12px; color:#475569; border-top:1px dashed #cbd5e1; padding-top:8px;">
                    <strong>Location:</strong> <span id="evLocation"></span><br>
                    <strong>Description:</strong> <span id="evDesc"></span>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeEvidence()" style="padding:9px 20px; border:none; background:#0f172a; color:#fff; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Close Evidence</button>
            </div>
        </div>
    </div>

    <!-- INSTANT UPI QR PAYMENT MODAL -->
    <div id="enquiryQrModal" class="qr-modal-overlay" style="display:none;" onclick="if(event.target===this) closeEnquiryQr()">
        <div class="qr-modal-card">
            <div style="padding:16px 20px; background:linear-gradient(135deg, var(--gov-navy-950), #1e293b); color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0; font-size:16px; font-weight:800;">Challan UPI Payment</h3>
                    <small style="color:#94a3b8; font-size:11px;">Instant online fine settlement</small>
                </div>
                <button type="button" onclick="closeEnquiryQr()" style="background:none; border:none; color:#ffffff; font-size:22px; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px; text-align:center;">
                <div style="margin-bottom:14px;">
                    <span id="eqChBadge" style="display:inline-block; padding:3px 10px; border-radius:6px; background:#eff6ff; color:#1d4ed8; font-weight:800; font-size:12px;">#CH-0</span>
                    <span id="eqPlate" style="font-weight:800; font-family:monospace; margin-left:8px; font-size:13.5px;"></span>
                </div>
                <div style="font-size:24px; font-weight:900; color:var(--traffic-red); margin-bottom:12px;" id="eqAmount">₹0.00</div>

                <div style="display:inline-block; padding:10px; background:#ffffff; border:2px solid #0f172a; border-radius:14px; margin-bottom:12px;">
                    <div id="eqQrCanvas"></div>
                </div>

                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-bottom:12px;">
                    Scan with GPay, PhonePe, Paytm, BHIM, or any UPI App
                </div>

                <div id="eqPayAction"></div>
                <button type="button" onclick="closeEnquiryQr()" style="width:100%; margin-top:8px; padding:9px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer; color:#334155;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="portal-mature-footer">
        <div class="mature-footer-inner">
            <div>
                <strong style="color:#ffffff;">TRAFFIC CONTROL e-CHALLAN SYSTEM</strong> • Ministry of Road Transport & Highways
            </div>
            <div class="footer-links-row">
                <a href="index.php">Home Page</a>
                <a href="user_login.php">Citizen Sign In</a>
                <a href="register.php">New Registration</a>
                <a href="admin_login.php">Admin Login</a>
            </div>
        </div>
    </footer>

    <script>
        function showFullEvidence(challanNo, violation, plate, location, time, img1, img2, camId, desc, fine) {
            document.getElementById('evModalTitle').textContent = 'Photographic Evidence • ' + challanNo;
            document.getElementById('evCameraNode').textContent = camId || 'Automated ANPR Surveillance Node';
            document.getElementById('evPlate').textContent = plate;
            document.getElementById('evViolation').textContent = violation;
            document.getElementById('evLocation').textContent = location || 'Corridor Junction';
            document.getElementById('evTimestamp').textContent = time;
            document.getElementById('evFine').textContent = '₹' + fine;
            document.getElementById('evDesc').textContent = desc || 'Statutory traffic violation recorded by automated sensor node.';
            document.getElementById('evImg1').src = img1;
            document.getElementById('evImg2').src = img2;
            document.getElementById('evidenceModal').style.display = 'flex';
        }

        function closeEvidence() {
            document.getElementById('evidenceModal').style.display = 'none';
        }

        function openEnquiryQrModal(id, vehicleNo, violation, fineAmount) {
            document.getElementById('eqChBadge').textContent = '#CH-' + id;
            document.getElementById('eqPlate').textContent = vehicleNo;
            document.getElementById('eqAmount').textContent = '₹' + parseFloat(fineAmount).toFixed(2);

            const qrBox = document.getElementById('eqQrCanvas');
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

            document.getElementById('eqPayAction').innerHTML = `
                <a href="pay.php?id=${id}" class="btn-gateway-cta" style="background:var(--traffic-red); color:#fff; padding:10px 16px; font-size:13px; text-decoration:none; display:block; border-radius:8px; font-weight:800;">
                    ✓ Proceed to Payment Gateway →
                </a>
            `;

            document.getElementById('enquiryQrModal').style.display = 'flex';
        }

        function closeEnquiryQr() {
            document.getElementById('enquiryQrModal').style.display = 'none';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEvidence();
                closeEnquiryQr();
            }
        });
    </script>
</body>
</html>
