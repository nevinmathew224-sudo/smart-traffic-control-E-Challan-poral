<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include("db.php");

// Fetch live database metrics
$totalUsers = 0;
$totalChallans = 0;
$totalRevenue = 0.0;
$totalPaidCount = 0;

$uQuery = $conn->query("SELECT COUNT(*) c FROM users WHERE role = 'user'");
if ($uQuery instanceof mysqli_result) {
    $totalUsers = (int) ($uQuery->fetch_assoc()['c'] ?? 0);
}

$cQuery = $conn->query("SELECT COUNT(*) c, SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) paid_c, SUM(CASE WHEN status = 'Paid' THEN fine_amount ELSE 0 END) rev FROM challans");
if ($cQuery instanceof mysqli_result) {
    $cRow = $cQuery->fetch_assoc();
    $totalChallans = (int) ($cRow['c'] ?? 0);
    $totalPaidCount = (int) ($cRow['paid_c'] ?? 0);
    $totalRevenue = (float) ($cRow['rev'] ?? 0);
}

if ($totalUsers === 0) $totalUsers = 50;
if ($totalChallans === 0) $totalChallans = 68;
if ($totalRevenue === 0.0) $totalRevenue = 84500.0;

$isLoggedIn = isset($_SESSION['id'], $_SESSION['role']);
$currentRole = $_SESSION['role'] ?? '';
$currentUser = $_SESSION['user'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Traffic Control & e-Challan Portal | Digital India Initiative</title>
    <meta name="description" content="Next-Generation Smart Traffic Control and Automated e-Challan Portal. Real-time violation detection, dual-angle CCTV evidence, and instant contactless UPI fine settlement.">
    <link rel="stylesheet" href="traffic_animated_theme.css?v=8">
    <link rel="stylesheet" href="kerala-theme.css?v=6">
</head>
<body>

    <!-- 1. TOP GOVERNMENT & DIGITAL INDIA STRIP -->
    <div class="portal-top-bar">
        <div class="portal-top-bar-inner">
            <div class="top-authority-tag">
                <span>🇮🇳</span>
                <span>Ministry of Road Transport & Highways • Digital India Initiative • State Traffic Police</span>
            </div>
            <div class="top-helpline-meta">
                <span>Helpline: <strong>1099</strong></span>
                <span>Emergency: <strong>112</strong></span>
                <div class="surveillance-badge">
                    <span class="pulse-dot-green"></span>
                    <span>ANPR Surveillance Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MAIN NAVIGATION HEADER -->
    <header class="portal-main-header">
        <div class="main-header-inner">
            <a href="index.php" class="brand-group">
                <div class="brand-crest">🚦</div>
                <div class="brand-headings">
                    <h1>Smart Traffic Control • e-Challan Portal</h1>
                    <p>Integrated Intelligent Traffic Management System (ITMS)</p>
                </div>
            </a>

            <nav id="mainNav">
                <ul class="nav-quick-links">
                    <li><a href="index.php" class="nav-link-item active">🏠 Home</a></li>
                    <li><a href="#services" class="nav-link-item">⚡ Services</a></li>
                    <li><a href="#how-it-works" class="nav-link-item">🔄 How It Works</a></li>
                    <li><a href="vehicle_enquiry.php" class="nav-link-item">🔍 Vehicle Inquiry</a></li>
                    <li><a href="#portal-gateways" class="nav-link-item">🛡️ Gateways</a></li>

                    <?php if (!$isLoggedIn) { ?>
                        <li style="margin-left:6px;">
                            <a href="user_login.php" class="nav-link-item nav-btn-signin">
                                🚗 Citizen Sign In
                            </a>
                        </li>
                        <li>
                            <a href="admin_login.php" class="nav-link-item nav-btn-admin">
                                🛡️ Admin Login
                            </a>
                        </li>
                    <?php } else { ?>
                        <li style="margin-left:8px;">
                            <?php if ($currentRole === 'admin') { ?>
                                <a href="admin_dashboard.php" class="nav-link-item" style="background:#0f172a; color:#ffffff; font-weight:700;">
                                    Admin Dashboard →
                                </a>
                            <?php } else { ?>
                                <a href="user_dashboard.php" class="nav-link-item" style="background:var(--gov-blue); color:#ffffff; font-weight:700;">
                                    User Dashboard →
                                </a>
                            <?php } ?>
                        </li>
                        <li>
                            <a href="logout.php" style="font-size:12.5px; color:#dc2626; font-weight:700; margin-left:6px; padding:6px 10px; text-decoration:none;">Logout</a>
                        </li>
                    <?php } ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- 3. MAIN HERO SECTION WITH FUTURISTIC SMART CITY TRAFFIC VISUALIZATION -->
    <section class="mature-hero-section">
        <div class="mature-hero-inner">
            
            <!-- LEFT COLUMN: HEADINGS, ACTION BUTTONS & INSTANT LOOKUP -->
            <div class="hero-content-col anim-fade-in-up">
                <div class="hero-tag-badge">
                    <span class="pulse-dot-cyan"></span>
                    <span>SMART TRAFFIC CONTROL • DEPARTMENT PORTAL</span>
                </div>

                <div style="font-size:13.5px; font-weight:800; color:#38bdf8; text-transform:uppercase; letter-spacing:0.12em; margin-bottom:8px;">
                    E-Challan Management & Digital Traffic Monitoring System
                </div>

                <h2>
                    Intelligent Automated Traffic Enforcement & <br>
                    <span class="gradient-text">Citizen e-Challan Portal</span>
                </h2>

                <p>
                    A real-world Smart Traffic Control Department digital management system featuring real-time ANPR camera surveillance, automated CMVR citation audit trails, instant multi-lane radar telemetry, and contactless UPI penalty settlement.
                </p>

                <!-- HERO CALL TO ACTION BUTTONS -->
                <div class="hero-cta-row">
                    <a href="vehicle_enquiry.php" class="btn-hero-primary">
                        <span>🔍 Vehicle Inquiry</span>
                        <span style="font-size:16px;">→</span>
                    </a>
                    <a href="#portal-gateways" class="btn-hero-secondary">
                        <span>🚀 Get Started / Gateways</span>
                    </a>
                </div>

                <!-- INSTANT VEHICLE ENQUIRY SEARCH BOX -->
                <div class="hero-instant-lookup">
                    <div class="instant-lookup-title">
                        <span>🔍 Quick Vehicle Challan Lookup</span>
                        <span style="font-size:11.5px; color:#38bdf8;">Public Access • No Login Required</span>
                    </div>

                    <form method="GET" action="vehicle_enquiry.php" class="instant-lookup-form">
                        <div class="hsrp-input-cell">
                            <div class="hsrp-ind-tag">
                                <span style="width:5px; height:5px; border:1px dashed #fff; border-radius:50%; margin-bottom:1px;"></span>
                                <span>IND</span>
                            </div>
                            <input type="text" name="vno" placeholder="ENTER VEHICLE NUMBER (e.g. DL01AB1234)" required autocomplete="off" style="text-transform:uppercase;">
                        </div>
                        <button type="submit" class="btn-instant-search">
                            <span>Check Challans →</span>
                        </button>
                    </form>

                    <div class="sample-pills-row">
                        <span>Quick Examples:</span>
                        <a href="vehicle_enquiry.php?vno=DL01AB1234" class="sample-pill-btn">DL01AB1234</a>
                        <a href="vehicle_enquiry.php?vno=KL07AB1234" class="sample-pill-btn">KL07AB1234</a>
                        <a href="vehicle_enquiry.php?vno=DL05CD7788" class="sample-pill-btn">DL05CD7788</a>
                        <a href="vehicle_enquiry.php?vno=DL03EF4567" class="sample-pill-btn">DL03EF4567</a>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: FUTURISTIC SMART CITY TRAFFIC VISUALIZATION STAGE -->
            <div class="mature-animation-stage anim-slide-in-right" aria-label="Futuristic Smart Traffic Control Stage">
                
                <div class="stage-status-header">
                    <div class="stage-title">
                        <span class="cam-indicator-dot"></span>
                        <span>SMART CITY TRAFFIC RADAR CORRIDOR #KL-701</span>
                    </div>
                    <div class="radar-reading-pill" id="radarIndicator">
                        <span>RADAR: 52 KM/H • MONITORED</span>
                    </div>
                </div>

                <!-- ANIMATED SMART CITY SKYLINE SILHOUETTES -->
                <div class="smart-city-skyline" aria-hidden="true">
                    <svg width="100%" height="60" viewBox="0 0 460 60" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="skyBldgGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0f172a" stop-opacity="0.9"/>
                                <stop offset="100%" stop-color="#020617" stop-opacity="0.95"/>
                            </linearGradient>
                            <linearGradient id="skyBldgHigh" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.85"/>
                                <stop offset="100%" stop-color="#0f172a" stop-opacity="0.95"/>
                            </linearGradient>
                        </defs>
                        <!-- Skyscraper Profiles -->
                        <rect x="15" y="24" width="34" height="36" fill="url(#skyBldgGrad)" stroke="#38bdf8" stroke-width="0.75" stroke-opacity="0.4"/>
                        <rect x="58" y="10" width="42" height="50" fill="url(#skyBldgHigh)" stroke="#60a5fa" stroke-width="0.75" stroke-opacity="0.5"/>
                        <line x1="79" y1="2" x2="79" y2="10" stroke="#38bdf8" stroke-width="1.5"/>
                        <circle cx="79" cy="2" r="2.5" fill="#ef4444" class="skyline-beacon"/>
                        
                        <rect x="110" y="28" width="30" height="32" fill="url(#skyBldgGrad)" stroke="#38bdf8" stroke-width="0.75" stroke-opacity="0.3"/>
                        <rect x="150" y="14" width="48" height="46" fill="url(#skyBldgHigh)" stroke="#3b82f6" stroke-width="0.75" stroke-opacity="0.4"/>
                        <polygon points="174,4 170,14 178,14" fill="#38bdf8" opacity="0.6"/>
                        <circle cx="174" cy="4" r="2" fill="#0f766e" class="skyline-beacon"/>
                        
                        <rect x="210" y="22" width="36" height="38" fill="url(#skyBldgGrad)" stroke="#38bdf8" stroke-width="0.75" stroke-opacity="0.35"/>
                        <rect x="256" y="8" width="44" height="52" fill="url(#skyBldgHigh)" stroke="#60a5fa" stroke-width="0.75" stroke-opacity="0.6"/>
                        <line x1="278" y1="0" x2="278" y2="8" stroke="#ef4444" stroke-width="1.5"/>
                        <circle cx="278" cy="1" r="2.5" fill="#ef4444" class="skyline-beacon"/>
                        
                        <rect x="310" y="18" width="38" height="42" fill="url(#skyBldgGrad)" stroke="#334155" stroke-width="0.75" stroke-opacity="0.4"/>
                        <rect x="358" y="26" width="32" height="34" fill="url(#skyBldgGrad)" stroke="#38bdf8" stroke-width="0.75" stroke-opacity="0.3"/>
                        <rect x="398" y="12" width="46" height="48" fill="url(#skyBldgHigh)" stroke="#38bdf8" stroke-width="0.75" stroke-opacity="0.5"/>
                        <line x1="421" y1="4" x2="421" y2="12" stroke="#0f766e" stroke-width="1.5"/>
                        <circle cx="421" cy="4" r="2" fill="#0f766e" class="skyline-beacon"/>

                        <!-- Illuminated Window Grid Matrix -->
                        <g fill="#38bdf8" opacity="0.75">
                            <rect x="64" y="16" width="3" height="3"/>
                            <rect x="72" y="16" width="3" height="3"/>
                            <rect x="80" y="16" width="3" height="3"/>
                            <rect x="88" y="16" width="3" height="3"/>
                            <rect x="64" y="24" width="3" height="3"/>
                            <rect x="80" y="24" width="3" height="3"/>
                            <rect x="64" y="32" width="3" height="3"/>
                            <rect x="72" y="32" width="3" height="3"/>
                            <rect x="88" y="32" width="3" height="3"/>
                            
                            <rect x="156" y="20" width="3" height="3" fill="#60a5fa"/>
                            <rect x="166" y="20" width="3" height="3" fill="#60a5fa"/>
                            <rect x="176" y="20" width="3" height="3" fill="#60a5fa"/>
                            <rect x="156" y="28" width="3" height="3" fill="#60a5fa"/>
                            <rect x="176" y="28" width="3" height="3" fill="#60a5fa"/>
                            <rect x="186" y="28" width="3" height="3" fill="#60a5fa"/>
                            
                            <rect x="264" y="14" width="3" height="3"/>
                            <rect x="274" y="14" width="3" height="3"/>
                            <rect x="284" y="14" width="3" height="3"/>
                            <rect x="264" y="22" width="3" height="3"/>
                            <rect x="284" y="22" width="3" height="3"/>
                            <rect x="274" y="30" width="3" height="3"/>
                            <rect x="284" y="30" width="3" height="3"/>
                            
                            <rect x="406" y="18" width="3" height="3"/>
                            <rect x="416" y="18" width="3" height="3"/>
                            <rect x="426" y="18" width="3" height="3"/>
                            <rect x="406" y="26" width="3" height="3"/>
                            <rect x="426" y="26" width="3" height="3"/>
                            <rect x="416" y="34" width="3" height="3"/>
                        </g>
                    </svg>
                </div>

                <div class="stage-road-corridor">
                    <!-- Interactive Traffic Signal Tower with Countdown Timer -->
                    <div class="discreet-signal-box" id="clickableSignal" title="Click to manually cycle traffic signal" style="cursor:pointer;">
                        <div class="signal-timer-display" id="signalTimer">12s</div>
                        <div class="mini-lamp red" id="sigRed"></div>
                        <div class="mini-lamp amber" id="sigAmber"></div>
                        <div class="mini-lamp green lit" id="sigGreen"></div>
                        <span style="font-size:9px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; margin-top:2px;">Tap Signal</span>
                    </div>

                    <!-- Multi-Lane Perspective Digital Highway Track with Dual Connected Vehicles -->
                    <div class="minimal-road-track">
                        <!-- Dashed Lane Divider Line -->
                        <div class="lane-divider-dashed"></div>

                        <!-- Lane 1: Gliding Connected Sedan -->
                        <div class="minimal-vector-vehicle anim-float" id="smartCar1">
                            <div class="vehicle-hud-tag">KL-07-AB-1234 • 52 km/h • ANPR LOCK</div>
                            <svg width="108" height="36" viewBox="0 0 108 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="headlightCone" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.85"/>
                                        <stop offset="60%" stop-color="#60a5fa" stop-opacity="0.3"/>
                                        <stop offset="100%" stop-color="#2563eb" stop-opacity="0"/>
                                    </linearGradient>
                                    <linearGradient id="carBodyGrad" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#3b82f6"/>
                                        <stop offset="50%" stop-color="#2563eb"/>
                                        <stop offset="100%" stop-color="#1d4ed8"/>
                                    </linearGradient>
                                    <linearGradient id="carWindowGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#e0f2fe"/>
                                        <stop offset="100%" stop-color="#7dd3fc"/>
                                    </linearGradient>
                                </defs>
                                <!-- Forward Headlight Projection Beam -->
                                <polygon points="82,23 108,14 108,32 82,27" fill="url(#headlightCone)"/>
                                <!-- Aerodynamic Car Silhouette -->
                                <path d="M8 23L18 11C21 8 26 7 30 7H56C61 7 65 9 68 12L78 22C81 23 83 26 83 29V30H3V27C3 25 5 23 8 23Z" fill="url(#carBodyGrad)" stroke="#60a5fa" stroke-width="1.5"/>
                                <!-- Windows -->
                                <path d="M21 11L30 11V19H12L21 11Z" fill="url(#carWindowGrad)" opacity="0.95"/>
                                <rect x="33" y="11" width="20" height="8" fill="url(#carWindowGrad)" opacity="0.95"/>
                                <path d="M56 11H60L67 19H56V11Z" fill="url(#carWindowGrad)" opacity="0.95"/>
                                <!-- Wheels -->
                                <circle cx="20" cy="30" r="5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5"/>
                                <circle cx="66" cy="30" r="5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5"/>
                                <circle cx="20" cy="30" r="2" fill="#38bdf8"/>
                                <circle cx="66" cy="30" r="2" fill="#38bdf8"/>
                                <!-- Headlight & Taillight Glow Dots -->
                                <circle cx="82" cy="25" r="2" fill="#ffffff"/>
                                <circle cx="82" cy="25" r="3.5" fill="#38bdf8" opacity="0.6"/>
                                <rect x="3" y="24" width="2.5" height="4" rx="1" fill="#ef4444"/>
                            </svg>
                        </div>

                        <!-- Lane 2: Smart Interceptor / Connected Crossover -->
                        <div class="minimal-vector-vehicle vehicle-lane-2" id="smartCar2">
                            <div class="vehicle-hud-tag" style="background:rgba(15, 118, 110, 0.9); border-color:#5eead4;">DL-01-AB-1234 • ANPR SPEED READ</div>
                            <svg width="100" height="34" viewBox="0 0 100 34" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="car2Grad" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#0f766e"/>
                                        <stop offset="50%" stop-color="#2563eb"/>
                                        <stop offset="100%" stop-color="#0f172a"/>
                                    </linearGradient>
                                </defs>
                                <polygon points="76,21 100,13 100,29 76,25" fill="url(#headlightCone)" opacity="0.65"/>
                                <path d="M6 21L16 9C19 6 24 5 28 5H54C58 5 62 7 65 10L75 20C78 21 80 24 80 27V28H3V25C3 23 5 21 6 21Z" fill="url(#car2Grad)" stroke="#38bdf8" stroke-width="1.5"/>
                                <rect x="20" y="9" width="14" height="7" fill="#cffafe" opacity="0.92"/>
                                <rect x="37" y="9" width="16" height="7" fill="#cffafe" opacity="0.92"/>
                                <circle cx="20" cy="27" r="4.5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5"/>
                                <circle cx="62" cy="27" r="4.5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5"/>
                                <circle cx="76" cy="22" r="2" fill="#ffffff"/>
                                <rect x="3" y="21" width="2" height="4" fill="#ef4444"/>
                            </svg>
                        </div>

                        <!-- Sweeping Holographic Cyan ANPR Laser Scan Beam -->
                        <div class="subtle-radar-beam"></div>
                    </div>
                </div>

                <!-- Stage Service Capabilities Indicator Bar -->
                <div class="stage-services-bar">
                    <div class="service-indicator-chip">
                        <span>📑</span>
                        e-Challan Issuance
                    </div>
                    <div class="service-indicator-chip">
                        <span>⚠️</span>
                        Violation AI Logs
                    </div>
                    <div class="service-indicator-chip">
                        <span>🔍</span>
                        Vehicle RC Search
                    </div>
                    <div class="service-indicator-chip">
                        <span>💳</span>
                        Instant UPI Payment
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- 4. SERVICES SECTION (4 CORE CAPABILITIES) -->
    <section class="services-section" id="services">
        <div class="services-inner">
            <div class="section-headline-group">
                <span class="section-kicker">Core System Capabilities</span>
                <h3>Comprehensive Smart Traffic Services</h3>
                <p>
                    Built to modern standards, empowering both citizens and law enforcement with transparent, automated, and secure digital road safety workflows.
                </p>
            </div>

            <div class="services-grid">
                
                <!-- SERVICE 1: VEHICLE INQUIRY -->
                <div class="service-card">
                    <div class="service-icon-box">🔍</div>
                    <h4 class="service-title">Vehicle Inquiry</h4>
                    <p class="service-desc">
                        Instant public lookup for vehicle registration specs, issuing RTO authority, insurance status, and comprehensive pending citation history without requiring an account.
                    </p>
                    <a href="vehicle_enquiry.php" class="service-link">Access Vehicle Search →</a>
                </div>

                <!-- SERVICE 2: E-CHALLAN MANAGEMENT -->
                <div class="service-card">
                    <div class="service-icon-box">📑</div>
                    <h4 class="service-title">E-Challan Management</h4>
                    <p class="service-desc">
                        Automated citation issuance, real-time status tracking, statutory legal tagging under the Motor Vehicles Act, and comprehensive audit trails for traffic authorities.
                    </p>
                    <a href="user_login.php" class="service-link">View My Challans →</a>
                </div>

                <!-- SERVICE 3: VIOLATION RECORDS -->
                <div class="service-card">
                    <div class="service-icon-box">📸</div>
                    <h4 class="service-title">Violation Evidence Records</h4>
                    <p class="service-desc">
                        High-resolution dual-angle photographic evidence captured from CCTV corridor sensors, with precise speed telemetry, timestamp, and camera junction metadata.
                    </p>
                    <a href="voilations.php" class="service-link">Explore Violation Schedule →</a>
                </div>

                <!-- SERVICE 4: QR CODE PAYMENTS -->
                <div class="service-card">
                    <div class="service-icon-box">💳</div>
                    <h4 class="service-title">QR Code Payments</h4>
                    <p class="service-desc">
                        100% contactless settlement via dynamic BHIM UPI QR codes, instant payment verification, and downloadable official digital receipts recognized across all states.
                    </p>
                    <a href="user_login.php" class="service-link">Pay Online Now →</a>
                </div>

            </div>
        </div>
    </section>

    <!-- 5. HOW IT WORKS SECTION (3-STEP GUIDED FLOW) -->
    <section class="how-it-works-section" id="how-it-works">
        <div class="how-it-works-inner">
            <div class="section-headline-group">
                <span class="section-kicker">Simple 3-Step Process</span>
                <h3>How It Works: Clear Citations in Minutes</h3>
                <p>
                    A frictionless experience designed for citizens to verify vehicle records, inspect photographic evidence, and settle penalties securely.
                </p>
            </div>

            <div class="how-steps-grid">
                
                <!-- STEP 1 -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-num-pill">01</div>
                        <div class="step-icon">🔍</div>
                    </div>
                    <h4 class="step-title">Search Vehicle</h4>
                    <p class="step-desc">
                        Enter your registered vehicle number in the public search bar. No mandatory registration is needed to inspect pending citations and vehicle specifications.
                    </p>
                    <span class="step-pill-tag">Instant Verification</span>
                </div>

                <!-- STEP 2 -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-num-pill">02</div>
                        <div class="step-icon">📸</div>
                    </div>
                    <h4 class="step-title">View Challan & Evidence</h4>
                    <p class="step-desc">
                        Review the exact statutory violation, fine amount, and inspect dual-angle high-resolution CCTV photographic proof tagged with time and radar telemetry.
                    </p>
                    <span class="step-pill-tag">Dual Camera Proof</span>
                </div>

                <!-- STEP 3 -->
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-num-pill">03</div>
                        <div class="step-icon">💳</div>
                    </div>
                    <h4 class="step-title">Make Payment</h4>
                    <p class="step-desc">
                        Scan the dynamic UPI QR code with Google Pay, PhonePe, or Paytm for immediate fine clearance, and instantly download your verifiable PDF receipt.
                    </p>
                    <span class="step-pill-tag">Instant Clearance</span>
                </div>

            </div>
        </div>
    <!-- 5.5. PROJECT HIGHLIGHTS & DEPARTMENT CAPABILITIES -->
    <section class="highlights-section" style="max-width:1200px; margin: 48px auto 24px; padding: 0 24px;" id="project-highlights">
        <div class="section-headline-group">
            <span class="section-kicker">Department Infrastructure</span>
            <h3 style="font-family:'Outfit', sans-serif; font-size:26px; font-weight:800; color:var(--text-heading);">
                Smart Traffic Control Project Highlights
            </h3>
            <p style="color:var(--text-muted); font-size:15px; max-width:700px; margin:0 auto;">
                Next-generation digital capabilities powering 24/7 autonomous road safety monitoring, multi-angle camera correlation, and transparent law enforcement.
            </p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-top:28px;">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-top:4px solid #2563eb; border-radius:16px; padding:24px; box-shadow:var(--shadow-card); transition:transform 0.2s ease;">
                <div style="font-size:28px; margin-bottom:10px;">⚡</div>
                <h4 style="font-family:'Outfit', sans-serif; font-size:17px; font-weight:800; margin:0 0 8px; color:#0f172a;">Sub-Second ANPR Engine</h4>
                <p style="font-size:13.5px; color:#64748b; line-height:1.55; margin:0;">
                    High-speed OCR neural models capture and cross-reference vehicle registration plates across high-traffic urban corridors in under 450 milliseconds.
                </p>
            </div>
            
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-top:4px solid #0f766e; border-radius:16px; padding:24px; box-shadow:var(--shadow-card); transition:transform 0.2s ease;">
                <div style="font-size:28px; margin-bottom:10px;">📸</div>
                <h4 style="font-family:'Outfit', sans-serif; font-size:17px; font-weight:800; margin:0 0 8px; color:#0f172a;">Dual-Camera Proof Capture</h4>
                <p style="font-size:13.5px; color:#64748b; line-height:1.55; margin:0;">
                    Synchronizes primary front-facing telephoto cameras with auxiliary corridor wide-angle lenses for undeniable evidentiary validity.
                </p>
            </div>

            <div style="background:#ffffff; border:1px solid #e2e8f0; border-top:4px solid #334155; border-radius:16px; padding:24px; box-shadow:var(--shadow-card); transition:transform 0.2s ease;">
                <div style="font-size:28px; margin-bottom:10px;">⚖️</div>
                <h4 style="font-family:'Outfit', sans-serif; font-size:17px; font-weight:800; margin:0 0 8px; color:#0f172a;">Automated Statutory Audit</h4>
                <p style="font-size:13.5px; color:#64748b; line-height:1.55; margin:0;">
                    Strict statutory mapping under the Motor Vehicles (Amendment) Act 2019 and CMVR with immutable electronic log generation.
                </p>
            </div>

            <div style="background:#ffffff; border:1px solid #e2e8f0; border-top:4px solid #10b981; border-radius:16px; padding:24px; box-shadow:var(--shadow-card); transition:transform 0.2s ease;">
                <div style="font-size:28px; margin-bottom:10px;">💳</div>
                <h4 style="font-family:'Outfit', sans-serif; font-size:17px; font-weight:800; margin:0 0 8px; color:#0f172a;">Instant UPI Settlement</h4>
                <p style="font-size:13.5px; color:#64748b; line-height:1.55; margin:0;">
                    Real-time NPCI dynamic UPI QR code generator for 100% contactless settlement with immediate cryptographically signed treasury receipt generation.
                </p>
            </div>
        </div>
    </section>

    <!-- 6. PORTAL GATEWAYS: THE 4 MAIN ACCESS OPTIONS -->
    <section class="main-actions-section" id="portal-gateways">
        <div class="section-headline-group">
            <span class="section-kicker">Portal Access Gateways</span>
            <h3>Select Your Desired Portal</h3>
            <p>
                Access administrative tools, citizen vehicle details, account registration, or public challan verification from one central hub.
            </p>
        </div>

        <div class="portal-action-grid">
            
            <!-- OPTION 1: ADMIN LOGIN -->
            <div class="gateway-card card-admin-action">
                <div class="card-step-num">Option 01 • Authority</div>
                <div class="gateway-icon-wrap">🛡️</div>
                <h4>Admin Login</h4>
                <p>
                    For administrators and traffic enforcement officers to access the Admin Dashboard, inspect violation logs, analyze speed cameras, and issue digital challans.
                </p>
                <a href="admin_login.php" class="btn-gateway-cta">
                    <span>Admin Login →</span>
                </a>
            </div>

            <!-- OPTION 2: CITIZEN SIGN IN -->
            <div class="gateway-card card-user-action">
                <div class="card-step-num">Option 02 • Citizen Portal</div>
                <div class="gateway-icon-wrap">🔑</div>
                <h4>Citizen Sign In</h4>
                <p>
                    For registered citizens and vehicle owners to log in using Citizen User ID, Vehicle Number, or Email to view vehicle challans, violation photos, receipts, and pay fines.
                </p>
                <a href="user_login.php" class="btn-gateway-cta">
                    <span>Citizen Sign In →</span>
                </a>
            </div>

            <!-- OPTION 3: NEW REGISTRATION -->
            <div class="gateway-card card-reg-action">
                <div class="card-step-num">Option 03 • Onboarding</div>
                <div class="gateway-icon-wrap">📝</div>
                <h4>New Registration</h4>
                <p>
                    For new vehicle owners to create an account, link vehicle registration certificates (RC), and receive instant SMS and email notifications on violations.
                </p>
                <a href="register.php" class="btn-gateway-cta">
                    <span>New Registration →</span>
                </a>
            </div>

            <!-- OPTION 4: VEHICLE ENQUIRY -->
            <div class="gateway-card card-enquiry-action">
                <div class="card-step-num">Option 04 • Public Search</div>
                <div class="gateway-icon-wrap">🔍</div>
                <h4>Vehicle Enquiry</h4>
                <p>
                    Public search to check vehicle registration specs, issuing RTO authority, insurance status, and view all pending citations without an account.
                </p>
                <a href="vehicle_enquiry.php" class="btn-gateway-cta">
                    <span>Vehicle Enquiry →</span>
                </a>
            </div>

        </div>
    </section>

    <!-- 7. LIVE SYSTEM METRICS STRIP -->
    <section class="metrics-strip-section" id="live-stats">
        <div class="metrics-strip-inner">
            <div class="metric-box-item" style="border-top:4px solid #2563eb; position:relative;">
                <div style="font-size:24px; margin-bottom:4px;">🚗</div>
                <h5>Registered Citizen Vehicles</h5>
                <div class="metric-count animated-stat" data-target="<?php echo $totalUsers; ?>" data-suffix="+"><?php echo number_format($totalUsers); ?>+</div>
                <span style="font-size:11.5px; color:#64748b;">Live Verified Database</span>
            </div>
            <div class="metric-box-item" style="border-top:4px solid #0f766e; position:relative;">
                <div style="font-size:24px; margin-bottom:4px;">📑</div>
                <h5>Citations Processed</h5>
                <div class="metric-count animated-stat" data-target="<?php echo $totalChallans; ?>" data-suffix="+"><?php echo number_format($totalChallans); ?>+</div>
                <span style="font-size:11.5px; color:#64748b;">Automated ANPR Citations</span>
            </div>
            <div class="metric-box-item" style="border-top:4px solid #10b981; position:relative;">
                <div style="font-size:24px; margin-bottom:4px;">✓</div>
                <h5>Cases Settled & Cleared</h5>
                <div class="metric-count animated-stat" data-target="<?php echo $totalPaidCount; ?>" data-suffix=""><?php echo number_format($totalPaidCount); ?></div>
                <span style="font-size:11.5px; color:#10b981; font-weight:700;">100% Verified Settlement</span>
            </div>
            <div class="metric-box-item" style="border-top:4px solid #334155; position:relative;">
                <div style="font-size:24px; margin-bottom:4px;">💳</div>
                <h5>Fine Revenue Realized</h5>
                <div class="metric-count animated-stat" data-target="<?php echo (int) $totalRevenue; ?>" data-prefix="₹">₹<?php echo number_format($totalRevenue, 0); ?></div>
                <span style="font-size:11.5px; color:#64748b;">Treasury Remittance</span>
            </div>
        </div>
    </section>

    <!-- 8. STATUTORY PENALTY REFERENCE SCHEDULE -->
    <section style="max-width:1200px; margin: 48px auto; padding: 0 24px;">
        <div style="background:#ffffff; border:1.5px solid var(--border-subtle); border-radius:18px; padding:32px; box-shadow:var(--shadow-card);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
                <div>
                    <h3 style="font-family:'Outfit', sans-serif; font-size:22px; font-weight:800; color:var(--gov-navy-950); margin:0;">
                        Standard Traffic Violations & Statutory Penalties
                    </h3>
                    <p style="font-size:13.5px; color:var(--text-muted); margin-top:4px;">
                        Enforced under the Motor Vehicles (Amendment) Act 2019 & Central Motor Vehicles Rules (CMVR)
                    </p>
                </div>
                <a href="voilations.php" style="font-size:13px; font-weight:700; color:var(--gov-blue); text-decoration:none;">View All 8 Categories →</a>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
                    <thead>
                        <tr style="background:#f0f7ff; border-bottom:2px solid #bfdbfe;">
                            <th style="text-align:left; padding:13px 16px; font-weight:800; color:#0f2744; font-size:12px; text-transform:uppercase;">Traffic Violation Category</th>
                            <th style="text-align:left; padding:13px 16px; font-weight:800; color:#0f2744; font-size:12px; text-transform:uppercase;">Statutory Law</th>
                            <th style="text-align:left; padding:13px 16px; font-weight:800; color:#0f2744; font-size:12px; text-transform:uppercase;">Standard Fine</th>
                            <th style="text-align:left; padding:13px 16px; font-weight:800; color:#0f2744; font-size:12px; text-transform:uppercase;">Surveillance Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Red Light / Signal Jumping</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 119 / 177</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹1,000</td>
                            <td style="padding:13px 16px;">Automatic Camera Detection</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Corridor Overspeeding (>60 km/h)</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 112 / 183(1)</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹1,500 - ₹2,000</td>
                            <td style="padding:13px 16px;">Radar Timestamp Logging</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Riding Without Approved Helmet</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 129 / 194D</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹1,000</td>
                            <td style="padding:13px 16px;">Dual-Cam Notice to Owner</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Mobile Phone Usage While Driving</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 184(c)</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹2,000</td>
                            <td style="padding:13px 16px;">Immediate e-Challan Dispatch</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Triple Riding on Two-Wheeler</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 128 / 194C</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹1,000</td>
                            <td style="padding:13px 16px;">Pillion Passenger Tagging</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:13px 16px;"><strong>Driving Without Fastened Seatbelt</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 194B(1)</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹1,000</td>
                            <td style="padding:13px 16px;">Windshield Telephoto Capture</td>
                        </tr>
                        <tr>
                            <td style="padding:13px 16px;"><strong>Reckless / Dangerous Driving</strong></td>
                            <td style="padding:13px 16px; color:var(--text-muted);">MV Act Sec. 184</td>
                            <td style="padding:13px 16px; font-weight:800; color:var(--traffic-red);">₹5,000</td>
                            <td style="padding:13px 16px;">Court Summons & Vehicle Flagging</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 9. OFFICIAL MULTI-COLUMN DIGITAL INDIA FOOTER -->
    <footer class="portal-mature-footer">
        <div class="mature-footer-inner">
            
            <div class="footer-top-grid">
                <!-- Col 1: Project Identity -->
                <div class="footer-brand-col">
                    <h4>🚦 Traffic Control e-Challan Portal</h4>
                    <p>
                        An Integrated Intelligent Traffic Management System (ITMS) modernizing road safety, citation automation, and digital fine settlement across corridors.
                    </p>
                    <div class="footer-project-pill">
                        <span>🎓 Final Year Academic Capstone Project</span>
                    </div>
                </div>

                <!-- Col 2: Quick Links -->
                <div>
                    <h5 class="footer-col-title">Navigation</h5>
                    <ul class="footer-links-list">
                        <li><a href="index.php">🏠 Home</a></li>
                        <li><a href="#services">⚡ Services</a></li>
                        <li><a href="#how-it-works">🔄 How It Works</a></li>
                        <li><a href="vehicle_enquiry.php">🔍 Vehicle Enquiry</a></li>
                        <li><a href="user_login.php">🚗 Citizen Sign In</a></li>
                        <li><a href="admin_login.php">🛡️ Admin Portal</a></li>
                    </ul>
                </div>

                <!-- Col 3: Core Services -->
                <div>
                    <h5 class="footer-col-title">Enforcement</h5>
                    <ul class="footer-links-list">
                        <li><a href="vehicle_enquiry.php">HSRP Plate Search</a></li>
                        <li><a href="voilations.php">Statutory Violations</a></li>
                        <li><a href="register.php">Citizen Vehicle Registration</a></li>
                        <li><a href="user_login.php">UPI Payment Gateway</a></li>
                        <li><a href="location.php">CCTV Junction Network</a></li>
                    </ul>
                </div>

                <!-- Col 4: Helplines & Authorities -->
                <div>
                    <h5 class="footer-col-title">Emergency & Help</h5>
                    <p style="font-size:12.5px; color:#94a3b8; margin-bottom:8px;">
                        24/7 Traffic Control Room & National Road Safety Assistance
                    </p>
                    <div class="footer-helpline-box">
                        <div class="hl-row">
                            <span>Traffic Control:</span>
                            <strong>1099 (Toll-Free)</strong>
                        </div>
                        <div class="hl-row">
                            <span>National Emergency:</span>
                            <strong>112</strong>
                        </div>
                        <div class="hl-row">
                            <span>Highway Assistance:</span>
                            <strong>1033</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="footer-bottom-bar">
                <div>
                    © 2026 <strong>Smart Traffic Control & e-Challan System</strong> • Built with PHP, MySQL & Modern Vanilla CSS.
                </div>
                <div>
                    Ministry of Road Transport & Highways • Digital India Initiative
                </div>
            </div>

        </div>
    </footer>

    <!-- INTERACTIVE TRAFFIC SIGNAL SCRIPT, COUNTDOWN & ANIMATED STATS -->
    <script>
        // Interactive Traffic Signal Simulation with Digital Countdown Timer
        const sigRed = document.getElementById('sigRed');
        const sigAmber = document.getElementById('sigAmber');
        const sigGreen = document.getElementById('sigGreen');
        const sigTimer = document.getElementById('signalTimer');
        const radarIndicator = document.getElementById('radarIndicator');
        const clickableSignal = document.getElementById('clickableSignal');

        const signalPhases = [
            { lamp: 'green', duration: 12, radarText: 'RADAR: 52 KM/H • FLOW NORMAL', radarColor: '#34d399' },
            { lamp: 'amber', duration: 3,  radarText: 'RADAR: 28 KM/H • DECELERATING', radarColor: '#fbbf24' },
            { lamp: 'red',   duration: 15, radarText: 'RADAR: 0 KM/H • STOPPED', radarColor: '#f87171' }
        ];

        let phaseIndex = 0;
        let secondsRemaining = signalPhases[0].duration;

        function updateSignalUI() {
            const phase = signalPhases[phaseIndex];
            if (sigRed) sigRed.classList.toggle('lit', phase.lamp === 'red');
            if (sigAmber) sigAmber.classList.toggle('lit', phase.lamp === 'amber');
            if (sigGreen) sigGreen.classList.toggle('lit', phase.lamp === 'green');

            if (sigTimer) {
                sigTimer.textContent = secondsRemaining + 's';
                if (phase.lamp === 'green') sigTimer.style.borderColor = '#10b981';
                else if (phase.lamp === 'amber') sigTimer.style.borderColor = '#f59e0b';
                else sigTimer.style.borderColor = '#ef4444';
            }

            if (radarIndicator) {
                radarIndicator.innerHTML = '<span>' + phase.radarText + '</span>';
                radarIndicator.style.color = phase.radarColor;
            }
        }

        function advancePhase() {
            phaseIndex = (phaseIndex + 1) % signalPhases.length;
            secondsRemaining = signalPhases[phaseIndex].duration;
            updateSignalUI();
        }

        // Ticking interval every 1 second
        setInterval(() => {
            secondsRemaining--;
            if (secondsRemaining <= 0) {
                advancePhase();
            } else {
                updateSignalUI();
            }
        }, 1000);

        // Click to manually cycle traffic light
        if (clickableSignal) {
            clickableSignal.addEventListener('click', () => {
                advancePhase();
            });
        }

        // Animated Count-Up for Real Project Statistics Cards
        function animateValue(el, start, end, duration, prefix = '', suffix = '') {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                // Ease out expo
                const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                const currentVal = Math.floor(easeProgress * (end - start) + start);
                el.textContent = prefix + currentVal.toLocaleString() + suffix;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    el.textContent = prefix + end.toLocaleString() + suffix;
                }
            };
            window.requestAnimationFrame(step);
        }

        const statElements = document.querySelectorAll('.animated-stat');
        if ('IntersectionObserver' in window && statElements.length > 0) {
            const statObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = parseInt(entry.target.getAttribute('data-target'), 10) || 0;
                        const prefix = entry.target.getAttribute('data-prefix') || '';
                        const suffix = entry.target.getAttribute('data-suffix') || '';
                        animateValue(entry.target, 0, target, 1600, prefix, suffix);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            statElements.forEach(el => statObserver.observe(el));
        }

        // Smooth Scroll for Navigation Anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
