<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include("db.php");
require_once("challan_helper.php");

if (!isset($_SESSION['captcha_reg'])) {
    $_SESSION['captcha_reg'] = rand(1000, 9999);
}

$error = '';
$success = '';
$registeredUserId = null;
$registeredVehicle = '';

$regEmail = '';
$regVehicle = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    require_csrf();

    $regEmail = strtolower(trim($_POST['reg_email'] ?? ''));
    $rawVehicle = trim($_POST['reg_vehicle_no'] ?? '');
    $regVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawVehicle));
    $regPass = $_POST['reg_pass'] ?? '';
    $regConfirm = $_POST['reg_confirm_pass'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    if ($captcha !== (string) $_SESSION['captcha_reg']) {
        $error = 'Incorrect security CAPTCHA code. Please enter the 4 digits shown.';
    } elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($regVehicle) < 4) {
        $error = 'Please enter a valid vehicle registration number (e.g. DL01AB1234).';
    } elseif (strlen($regPass) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif ($regPass !== $regConfirm) {
        $error = 'Passwords do not match. Please re-enter both passwords.';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param("s", $regEmail);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $error = 'An account with this email address already exists. Please sign in instead.';
        } else {
            $passHash = password_hash($regPass, PASSWORD_DEFAULT);
            $regRole = 'user';
            $insertStmt = $conn->prepare("INSERT INTO users (email, vehicle_no, password, role) VALUES (?, NULLIF(?, ''), ?, ?)");
            $insertStmt->bind_param("ssss", $regEmail, $regVehicle, $passHash, $regRole);

            if ($insertStmt->execute()) {
                $registeredUserId = (int) $conn->insert_id;
                $registeredVehicle = $regVehicle;
                $insertStmt->close();
                $success = "Registration successful! Your unique Citizen User ID is #{$registeredUserId}. Please proceed to Sign In.";
            } else {
                $error = 'Failed to create citizen record. Please try again.';
            }
        }
    }

    $_SESSION['captcha_reg'] = rand(1000, 9999);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration | Traffic Control e-Challan Portal</title>
    <link rel="stylesheet" href="traffic_animated_theme.css?v=6">
    <link rel="stylesheet" href="kerala-theme.css?v=5">
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
                <span>Citizen Helpdesk: <strong>1800-11-2026</strong></span>
            </div>
        </div>
    </div>

    <!-- MAIN REGISTRATION CONTAINER -->
    <main class="dedicated-page-container">
        
        <div class="auth-split-layout">
            
            <!-- LEFT: MINIMAL & MEANINGFUL TRAFFIC ANIMATION SHOWCASE -->
            <div class="portal-traffic-showcase">
                <div class="showcase-header">
                    <div class="showcase-badge">
                        <span>📝 SMART TRAFFIC CONTROL • ONBOARDING</span>
                    </div>
                    <div class="showcase-radar-pulse">
                        <span class="radar-blip"></span>
                        <span>RC REGISTRATION</span>
                    </div>
                </div>

                <h3 style="font-family:'Outfit',sans-serif; font-size:20px; font-weight:800; margin:0 0 6px; color:#ffffff;">
                    Citizen Vehicle Registration Desk
                </h3>
                <p style="font-size:13px; color:#94a3b8; margin:0 0 16px; line-height:1.45;">
                    Register your motor vehicle for automated high-security plate tracking, citation alerts & fast digital clearances under CMVR rules.
                </p>

                <!-- Minimal Road Corridor with Moving Vehicle -->
                <div class="showcase-road-strip">
                    <div class="showcase-road-lane">
                        <svg class="showcase-vehicle-anim" viewBox="0 0 80 40" fill="none">
                            <rect x="10" y="16" width="60" height="16" rx="6" fill="#10b981"/>
                            <path d="M22 16L30 6H52L60 16H22Z" fill="#059669"/>
                            <circle cx="25" cy="32" r="6" fill="#0f172a" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="58" cy="32" r="6" fill="#0f172a" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="70" cy="20" r="3" fill="#fef08a"/>
                        </svg>
                    </div>
                    <div class="showcase-road-lane">
                        <svg class="showcase-vehicle-anim delay" viewBox="0 0 80 40" fill="none">
                            <rect x="10" y="16" width="60" height="16" rx="6" fill="#38bdf8"/>
                            <path d="M22 16L30 6H52L60 16H22Z" fill="#0284c7"/>
                            <circle cx="25" cy="32" r="6" fill="#0f172a" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="58" cy="32" r="6" fill="#0f172a" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="70" cy="20" r="3" fill="#fef08a"/>
                        </svg>
                    </div>
                </div>

                <!-- Signal Status & Registration Info -->
                <div class="showcase-signal-row">
                    <div style="font-size:12px; font-weight:700; color:#e2e8f0; display:flex; align-items:center; gap:8px;">
                        <span>System Status:</span>
                        <span style="color:#38bdf8;">Real-Time RC Verification</span>
                    </div>
                    <div class="mini-signal-cluster">
                        <span class="mini-signal-dot red"></span>
                        <span class="mini-signal-dot amber"></span>
                        <span class="mini-signal-dot green active-glow"></span>
                    </div>
                </div>

                <ul class="showcase-features-list">
                    <li>
                        <span class="feat-icon">🛡️</span>
                        <span>High-Security Registration Plate (HSRP) Validation</span>
                    </li>
                    <li>
                        <span class="feat-icon">🔔</span>
                        <span>Automated Email & SMS Traffic Violation Alerts</span>
                    </li>
                    <li>
                        <span class="feat-icon">💳</span>
                        <span>Instant Citizen Dashboard with UPI QR Settlements</span>
                    </li>
                    <li>
                        <span class="feat-icon">📑</span>
                        <span>Official Treasury Payment Receipts & History</span>
                    </li>
                </ul>
            </div>

            <!-- RIGHT: NEW REGISTRATION FORM CARD -->
            <div class="dedicated-auth-card" style="margin:0;">

                <div class="auth-header-block">
                    <div class="auth-crest-icon" style="background:#ecfdf5; color:#047857; border-color:#a7f3d0;">
                        📝
                    </div>
                    <h2>New User Registration</h2>
                    <p>Register your vehicle to view challans, receive violation notices & pay fines</p>
                </div>

            <?php if ($registeredUserId !== null) { ?>
                <!-- REGISTRATION SUCCESS CARD -->
                <div style="background:#ecfdf5; border:1.5px solid #a7f3d0; border-radius:12px; padding:24px 20px; text-align:center; margin-bottom:20px;">
                    <div style="font-size:32px; margin-bottom:4px;">🎉</div>
                    <h3 style="font-size:18px; font-weight:800; color:#065f46;">Account Created Successfully!</h3>
                    <p style="font-size:13px; color:#047857; margin-top:4px;">Your official Citizen User ID has been generated:</p>
                    
                    <div style="display:inline-block; background:#059669; color:#ffffff; font-size:22px; font-weight:800; padding:6px 18px; border-radius:999px; margin:12px 0;">
                        #<?php echo e((string) $registeredUserId); ?>
                    </div>
                    
                    <div style="font-size:13px; color:#334155; margin-bottom:18px;">
                        Registered Vehicle: <strong><?php echo e($registeredVehicle); ?></strong>
                    </div>

                    <a href="user_login.php?user=<?php echo urlencode((string) $registeredUserId); ?>" class="btn-form-submit" style="background:var(--gov-blue); text-decoration:none;">
                        <span>Proceed to Citizen Sign In →</span>
                    </a>
                </div>
            <?php } else { ?>

                <?php if ($error !== '') { ?>
                <div class="clean-alert clean-alert-error" role="alert">
                    <span>⚠️</span>
                    <div><?php echo e($error); ?></div>
                </div>
                <?php } ?>

                <form method="POST" action="register.php">
                    <?php echo csrf_field(); ?>

                    <div class="form-group-row">
                        <label for="reg_email">
                            <span>Citizen Email Address</span>
                            <span style="font-size:11.5px; color:var(--text-muted);">For e-Challan Notices</span>
                        </label>
                        <div class="input-with-controls">
                            <input type="email" id="reg_email" name="reg_email" value="<?php echo e($regEmail); ?>" placeholder="citizen@example.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label for="reg_vehicle_no">
                            <span>Vehicle Registration Number</span>
                            <span style="font-size:11.5px; color:var(--traffic-amber);">RC Plate</span>
                        </label>
                        <div class="input-with-controls">
                            <input type="text" id="reg_vehicle_no" name="reg_vehicle_no" value="<?php echo e($regVehicle); ?>" placeholder="e.g. DL01AB1234 or KL07AB1234" required style="text-transform:uppercase;" oninput="updateHsrp(this.value)">
                        </div>

                        <!-- Live HSRP Number Plate Preview -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <span style="font-size:12px; color:var(--text-muted);">Number Plate Preview:</span>
                            <div class="hsrp-input-cell" style="display:inline-flex; width:auto; border-radius:6px;">
                                <div class="hsrp-ind-tag">
                                    <span style="width:4px; height:4px; border:1px dashed #fff; border-radius:50%; margin-bottom:1px;"></span>
                                    <span>IND</span>
                                </div>
                                <div style="padding:4px 12px; font-family:'Outfit', monospace; font-size:16px; font-weight:800; color:#0f172a;" id="hsrpDisplay">
                                    <?php echo $regVehicle !== '' ? e($regVehicle) : 'DL01AB1234'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label for="reg_pass">
                            <span>Password</span>
                            <span style="font-size:11.5px; color:var(--text-muted);">Minimum 4 characters</span>
                        </label>
                        <div class="input-with-controls">
                            <input type="password" id="reg_pass" name="reg_pass" placeholder="Create password" required autocomplete="new-password" oninput="checkPassMatch()">
                            <button type="button" class="btn-eye-toggle" onclick="togglePass('reg_pass', this)" title="Toggle password visibility">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span>Show</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label for="reg_confirm_pass" style="margin-bottom:0;">
                                <span>Confirm Password</span>
                            </label>
                            <span id="passMatchIndicator" style="font-size:11px; font-weight:700;"></span>
                        </div>
                        <div class="input-with-controls">
                            <input type="password" id="reg_confirm_pass" name="reg_confirm_pass" placeholder="Re-enter password" required autocomplete="new-password" oninput="checkPassMatch()">
                            <button type="button" class="btn-eye-toggle" onclick="togglePass('reg_confirm_pass', this)" title="Toggle password visibility">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span>Show</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label for="reg_captcha">
                            <span>Security Verification (CAPTCHA)</span>
                        </label>
                        <div class="captcha-row-layout">
                            <div class="captcha-code-box"><?php echo e((string) $_SESSION['captcha_reg']); ?></div>
                            <div class="input-with-controls">
                                <input type="text" id="reg_captcha" name="captcha" placeholder="Enter 4 digits" required maxlength="6" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="submitRegBtn" name="register_user" class="btn-form-submit" style="background:var(--traffic-green);">
                        <span>Create Citizen Account →</span>
                    </button>
                </form>

            <?php } ?>

            <div style="margin-top:20px; text-align:center; font-size:13.5px; color:var(--text-muted);">
                Already have an account? <a href="user_login.php" style="color:var(--gov-blue); font-weight:700;">Sign In</a> &nbsp;•&nbsp; <a href="vehicle_enquiry.php" style="color:var(--gov-blue); font-weight:700;">Vehicle Enquiry</a>
            </div>
        </div>

        </div>

        <!-- UNIFIED BOTTOM NAVIGATION -->
        <div class="portal-bottom-nav">
            <a href="index.php" class="portal-nav-btn portal-nav-btn-home">← Back to Home Page</a>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="portal-mature-footer">
        <div class="mature-footer-inner">
            <div>
                <strong style="color:#ffffff;">TRAFFIC CONTROL e-CHALLAN SYSTEM</strong> • Ministry of Road Transport & Highways
            </div>
            <div class="footer-links-row">
                <a href="index.php">Home Page</a>
                <a href="user_login.php">Citizen Sign In</a>
                <a href="vehicle_enquiry.php">Vehicle Enquiry</a>
                <a href="admin_login.php">Admin Login</a>
            </div>
        </div>
    </footer>

    <script>
        const eyeOpenSvg = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeSlashSvg = '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

        function togglePass(inputId, btn) {
            const el = document.getElementById(inputId);
            if (el.type === 'password') {
                el.type = 'text';
                btn.innerHTML = eyeSlashSvg + ' <span>Hide</span>';
            } else {
                el.type = 'password';
                btn.innerHTML = eyeOpenSvg + ' <span>Show</span>';
            }
        }

        function updateHsrp(val) {
            const clean = val.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            document.getElementById('hsrpDisplay').textContent = clean || 'DL01AB1234';
        }

        function checkPassMatch() {
            const p1 = document.getElementById('reg_pass') ? document.getElementById('reg_pass').value : '';
            const p2 = document.getElementById('reg_confirm_pass') ? document.getElementById('reg_confirm_pass').value : '';
            const ind = document.getElementById('passMatchIndicator');
            if (!ind) return;

            if (p2.length === 0) {
                ind.textContent = '';
            } else if (p1 === p2) {
                ind.textContent = '✓ Passwords Match';
                ind.style.color = 'var(--traffic-green)';
            } else {
                ind.textContent = '✗ Passwords Do Not Match';
                ind.style.color = 'var(--traffic-red)';
            }
        }

        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitRegBtn');
                if (btn) {
                    btn.classList.add('is-loading');
                    btn.innerHTML = '<span class="btn-spinner"></span> <span>Creating Citizen Account...</span>';
                }
            });
        }
    </script>
</body>
</html>
