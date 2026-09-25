<?php
require_once("db.php");
require_once("challan_helper.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: index.php?tab=user");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    exit("Invalid receipt request");
}

$sql = "
    SELECT c.*, u.email,
           MAX(p.id) AS payment_id,
           MAX(p.paid_at) AS paid_at,
           MAX(p.amount) AS paid_amount
    FROM challans c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN payments p ON p.challan_id = c.id
    WHERE c.id = ?
";

if ($_SESSION['role'] === 'user') {
    $sessionUserId = (int) $_SESSION['id'];
    $sessionVehicle = $_SESSION['vehicle_no'] ?? '';
    $cleanSessionVehicle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sessionVehicle));
    $sql .= " AND (c.user_id = ? OR c.vehicle_no = ? OR REPLACE(c.vehicle_no, ' ', '') = ? OR c.vehicle_no IN (SELECT vehicle_no FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != '') OR REPLACE(c.vehicle_no, ' ', '') IN (SELECT REPLACE(vehicle_no, ' ', '') FROM users WHERE id = ? AND vehicle_no IS NOT NULL AND vehicle_no != ''))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        exit("Unable to prepare receipt query");
    }
    $stmt->bind_param("iissii", $id, $sessionUserId, $sessionVehicle, $cleanSessionVehicle, $sessionUserId, $sessionUserId);
} else {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        exit("Unable to prepare receipt query");
    }
    $stmt->bind_param("i", $id);
}

$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receipt) {
    exit("Receipt or violation record not found");
}

$paid = strtolower((string) $receipt['status']) === 'paid';
$vehicle = getVehicleDetails((string) $receipt['vehicle_no'], (string) $receipt['violation']);
$violationDetails = getViolationLegalDetails((string) $receipt['violation'], (float) $receipt['fine_amount'], (string) $receipt['vehicle_no']);

$violationDate = !empty($receipt['violation_date']) ? $receipt['violation_date'] : $receipt['created_at'];
$evidence = getChallanEvidence((string) $receipt['vehicle_no'], (string) $receipt['violation'], (int) $receipt['id'], $violationDate);

// Override helper defaults with admin entered values if present
if (!empty($receipt['evidence_photo'])) {
    $evidence['front_image'] = $receipt['evidence_photo'];
}
if (!empty($receipt['evidence_photo_2'])) {
    $evidence['side_image'] = $receipt['evidence_photo_2'];
}
if (!empty($receipt['location'])) {
    $evidence['location'] = $receipt['location'];
}
if (!empty($receipt['camera_id'])) {
    $evidence['front_camera_id'] = $receipt['camera_id'];
}
if (!empty($receipt['officer_name'])) {
    $evidence['verifying_officer'] = $receipt['officer_name'];
}
if (!empty($receipt['violation_desc'])) {
    $violationDetails['description'] = $receipt['violation_desc'];
}

$challanNumber = !empty($receipt['challan_no']) 
    ? $receipt['challan_no'] 
    : ('KL-CHN-' . date('Y', strtotime($violationDate ?? 'now')) . '-' . str_pad((string) $receipt['id'], 6, '0', STR_PAD_LEFT));
$backUrl = ($_SESSION['role'] === 'admin') ? 'challans.php' : 'user_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Official Traffic Challan & Violation Receipt #<?php echo e((string) $receipt['id']); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="kerala-theme.css">
<style>
*, *::before, *::after {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

:root {
    --navy-950: #0f172a;
    --navy-900: #0f172a;
    --navy-850: #1e293b;
    --navy-800: #334155;
    --gold-500: #b45309;
    --gold-400: #d97706;
    --emerald-600: #15803d;
    --emerald-700: #166534;
    --crimson-600: #dc2626;
    --crimson-700: #991b1b;
    --slate-50: #f8fafc;
    --slate-100: #f1f5f9;
    --slate-200: #e2e8f0;
    --slate-300: #cbd5e1;
    --slate-600: #64748b;
    --slate-700: #334155;
    --slate-800: #1e293b;
    --slate-900: #0f172a;
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8fafc;
    color: var(--slate-900);
    padding: 30px 16px 60px;
}

.receipt-container {
    width: min(940px, 100%);
    margin: 0 auto;
}

/* Floating Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    background: #ffffff;
    padding: 12px 20px;
    border-radius: 16px;
    border: 1px solid var(--slate-200);
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
}

.action-bar-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-bar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.btn-back {
    background: var(--slate-100);
    color: var(--slate-700);
    border: 1px solid var(--slate-300);
}
.btn-back:hover {
    background: var(--slate-200);
    color: var(--slate-900);
}

.btn-print {
    background: var(--navy-900);
    color: #ffffff;
}
.btn-print:hover {
    background: var(--navy-800);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 31, 51, 0.25);
}

.btn-pay-now {
    background: #15803d;
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(21, 128, 61, 0.25);
}
.btn-pay-now:hover {
    background: #166534;
    transform: translateY(-1px);
}

/* Main Receipt Paper */
.receipt-paper {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #d1dbe5;
    box-shadow: 0 20px 45px rgba(15, 31, 51, 0.12);
    overflow: hidden;
    position: relative;
}

/* Security Watermark Background */
.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 76px;
    font-weight: 900;
    color: rgba(15, 31, 51, 0.024);
    letter-spacing: 0.18em;
    pointer-events: none;
    user-select: none;
    white-space: nowrap;
    text-transform: uppercase;
    z-index: 1;
}

/* Top Tricolor Security Border */
.tricolor-stripe {
    height: 6px;
    background: linear-gradient(90deg, #ff9933 0%, #ff9933 33.3%, #ffffff 33.3%, #ffffff 66.6%, #138808 66.6%, #138808 100%);
}

/* Header Section */
.receipt-header {
    background: linear-gradient(135deg, var(--navy-950) 0%, var(--navy-900) 65%, var(--navy-850) 100%);
    color: #ffffff;
    padding: 26px 30px 22px;
    position: relative;
    z-index: 2;
    border-bottom: 2px solid var(--gold-500);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.gov-branding {
    display: flex;
    align-items: center;
    gap: 16px;
}

.gov-crest {
    width: 62px;
    height: 62px;
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid var(--gold-400);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
    box-shadow: 0 0 16px rgba(250, 204, 21, 0.25);
}

.gov-text h1 {
    margin: 0;
    font-size: 19px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #ffffff;
    text-transform: uppercase;
}

.gov-text h2 {
    margin: 3px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--gold-400);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.gov-text p {
    margin: 3px 0 0;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.72);
}

.header-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.receipt-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.status-pill.paid {
    background: #dcfce7;
    color: #166534;
    border: 1.5px solid #86efac;
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.25);
}

.status-pill.unpaid {
    background: #fee2e2;
    color: #991b1b;
    border: 1.5px solid #fca5a5;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.25);
}

.header-barcodes {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.barcode-text {
    font-family: 'Space Mono', monospace;
    font-size: 13px;
    color: #ffffff;
    font-weight: 700;
    letter-spacing: 0.12em;
}

.barcode-sub {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.65);
}

/* Receipt Body Content */
.receipt-body {
    padding: 28px 30px;
    position: relative;
    z-index: 2;
}

/* Section Headings */
.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 14px;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--navy-900);
    padding-bottom: 6px;
    border-bottom: 2px solid var(--slate-200);
}

.section-icon {
    font-size: 15px;
}

/* Particulars Grid */
.details-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.detail-card {
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: 12px 14px;
    transition: background 0.15s ease;
}

.detail-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
}

.detail-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--slate-600);
    margin-bottom: 4px;
}

.detail-value {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: var(--slate-900);
    line-height: 1.35;
}

.detail-value.mono {
    font-family: 'Space Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.03em;
}

.detail-value.highlight {
    color: var(--navy-850);
    font-weight: 800;
}

/* High Security Registration Plate (HSRP) Graphic Box */
.hsrp-badge {
    display: inline-flex;
    align-items: center;
    border: 2.5px solid #111827;
    border-radius: 7px;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    overflow: hidden;
    vertical-align: middle;
}

.hsrp-flag {
    background: #0284c7;
    color: #ffffff;
    padding: 4px 6px;
    font-size: 9px;
    font-weight: 900;
    text-align: center;
    line-height: 1;
    border-right: 1.5px solid #111827;
    letter-spacing: 0.05em;
}

.hsrp-number {
    font-family: 'Space Mono', monospace;
    font-weight: 900;
    font-size: 15px;
    color: #000000;
    padding: 3px 10px;
    letter-spacing: 0.12em;
}

/* Violation Specifics Box */
.violation-box {
    background: #fffbeb;
    border: 1.5px solid #fde68a;
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 24px;
}

.violation-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.violation-name {
    font-size: 17px;
    font-weight: 800;
    color: #92400e;
}

.violation-fine-tag {
    font-size: 20px;
    font-weight: 900;
    color: #b45309;
    font-family: 'Space Mono', monospace;
}

.violation-statute {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--slate-800);
    margin-bottom: 6px;
}

.violation-desc {
    font-size: 12.5px;
    color: var(--slate-700);
    line-height: 1.5;
    margin: 0;
}

.violation-telemetry-chip {
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    color: #78350f;
    font-family: 'Space Mono', monospace;
}

/* Evidence Gallery Section (AI-Generated Captures) */
.evidence-section {
    margin-bottom: 24px;
}

.evidence-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.evidence-card {
    background: #ffffff;
    border: 1.5px solid var(--slate-200);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}

.evidence-header {
    background: var(--navy-900);
    color: #ffffff;
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.evidence-header span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.camera-badge {
    background: rgba(255, 255, 255, 0.15);
    padding: 2px 7px;
    border-radius: 4px;
    font-family: 'Space Mono', monospace;
    font-size: 10px;
}

.evidence-frame {
    position: relative;
    background: #000000;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    cursor: zoom-in;
}

.evidence-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.evidence-frame:hover img {
    transform: scale(1.03);
}

.hud-overlay {
    position: absolute;
    bottom: 8px;
    left: 8px;
    right: 8px;
    background: rgba(10, 21, 35, 0.85);
    backdrop-filter: blur(4px);
    color: #ffffff;
    padding: 6px 10px;
    border-radius: 6px;
    font-family: 'Space Mono', monospace;
    font-size: 9.5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.hud-tag {
    color: var(--gold-400);
    font-weight: 700;
}

.evidence-footer {
    padding: 10px 14px;
    background: var(--slate-50);
    border-top: 1px solid var(--slate-200);
    font-size: 11px;
    color: var(--slate-600);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Legal Consequences & Statutory Notice */
.legal-box {
    background: #fff5f5;
    border: 1.5px solid #fecaca;
    border-left: 5px solid var(--crimson-600);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 24px;
}

.legal-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 800;
    color: var(--crimson-700);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 10px;
}

.legal-list {
    margin: 0;
    padding-left: 20px;
    font-size: 12px;
    color: var(--slate-800);
    line-height: 1.6;
}

.legal-list li {
    margin-bottom: 7px;
}

.legal-list strong {
    color: #7f1d1d;
}

/* Financial Ledger Breakdown */
.fee-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
    background: #ffffff;
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    overflow: hidden;
}

.fee-table th {
    background: var(--slate-100);
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--slate-700);
    text-align: left;
    border-bottom: 1.5px solid var(--slate-200);
}

.fee-table td {
    padding: 10px 14px;
    font-size: 13px;
    border-bottom: 1px solid var(--slate-200);
    color: var(--slate-800);
}

.fee-table td.amount {
    text-align: right;
    font-family: 'Space Mono', monospace;
    font-weight: 700;
}

.fee-table tr.total-row td {
    background: var(--slate-50);
    font-weight: 900;
    font-size: 15px;
    color: var(--navy-900);
    border-top: 2px solid var(--slate-300);
    border-bottom: none;
}

.fee-table tr.total-row td.amount {
    color: #b45309;
    font-size: 17px;
}

/* Verification Strip & QR Seal */
.verification-strip {
    background: #f0fdf4;
    border: 1.5px solid #bbf7d0;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.verification-strip.unpaid-strip {
    background: #fff7ed;
    border-color: #fed7aa;
}

.verification-info h4 {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 800;
    color: var(--navy-900);
}

.verification-info p {
    margin: 0;
    font-size: 12px;
    color: var(--slate-600);
    line-height: 1.45;
}

.qr-box {
    padding: 8px;
    background: #ffffff;
    border: 2px solid var(--navy-900);
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
}

/* Official Digital Signatory Seal */
.audit-seal-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    padding-top: 18px;
    border-top: 1px solid var(--slate-200);
    gap: 16px;
    flex-wrap: wrap;
}

.audit-meta {
    font-size: 11px;
    color: var(--slate-600);
    line-height: 1.5;
}

.audit-hash {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--slate-700);
    word-break: break-all;
}

.signatory-stamp {
    text-align: right;
    min-width: 200px;
}

.digital-sign-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
    margin-bottom: 4px;
}

.signatory-name {
    font-size: 12px;
    font-weight: 700;
    color: var(--slate-900);
}

.signatory-title {
    font-size: 10.5px;
    color: var(--slate-600);
}

/* Modal for Image Zoom */
.image-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(10, 21, 35, 0.92);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.image-modal.active {
    display: flex;
}

.modal-content-wrapper {
    max-width: 900px;
    width: 100%;
    background: #000000;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.2);
    box-shadow: 0 25px 60px rgba(0,0,0,0.6);
}

.modal-header {
    padding: 12px 18px;
    background: var(--navy-900);
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 14px;
}

.modal-close {
    background: none;
    border: none;
    color: #ffffff;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}

.modal-img-container {
    max-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0d131a;
}

.modal-img-container img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
}

.modal-footer {
    padding: 10px 18px;
    background: var(--navy-950);
    color: var(--slate-300);
    font-size: 11.5px;
    font-family: 'Space Mono', monospace;
    display: flex;
    justify-content: space-between;
}

/* Print Optimization */
@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    html, body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        color: #000000 !important;
    }

    .action-bar,
    .image-modal,
    .btn-action {
        display: none !important;
    }

    .receipt-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    .receipt-paper {
        border: 1px solid #94a3b8 !important;
        box-shadow: none !important;
        border-radius: 8px !important;
    }

    .receipt-header {
        background: #0f1f33 !important;
        color: #ffffff !important;
        padding: 16px 20px !important;
    }

    .gov-crest {
        border-color: #facc15 !important;
    }

    .receipt-body {
        padding: 16px 20px !important;
    }

    .evidence-frame {
        aspect-ratio: 4 / 3 !important;
    }

    .evidence-frame img {
        max-height: 200px !important;
    }

    .watermark {
        display: none !important;
    }

    .details-grid {
        gap: 8px !important;
        margin-bottom: 16px !important;
    }

    .detail-card {
        padding: 8px 10px !important;
    }

    .violation-box {
        padding: 12px 14px !important;
        margin-bottom: 16px !important;
    }

    .legal-box {
        padding: 12px 14px !important;
        margin-bottom: 16px !important;
    }

    .break-inside-avoid {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .evidence-grid {
        grid-template-columns: 1fr;
    }
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .action-bar-right, .action-bar-left {
        justify-content: space-between;
    }
    .verification-strip {
        flex-direction: column;
        align-items: flex-start;
    }
    .qr-box {
        align-self: center;
    }
}

@media (max-width: 520px) {
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="receipt-container">
    <!-- Top Action Bar -->
    <div class="action-bar">
        <div class="action-bar-left">
            <span style="font-size: 13px; color: var(--slate-700); font-weight: 600;">
                Official Enforcement Citation: <strong style="color: var(--slate-900); font-family: monospace;"><?php echo e($challanNumber); ?></strong>
            </span>
        </div>
        <div class="action-bar-right">
            <?php if (!$paid) { ?>
            <a href="pay.php?id=<?php echo e((string) $receipt['id']); ?>" class="btn-action btn-pay-now">
                💳 Pay Penalty Now (₹<?php echo e(number_format((float) $receipt['fine_amount'], 2)); ?>)
            </a>
            <?php } ?>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                🖨️ Print Official Receipt / PDF
            </button>
        </div>
    </div>

    <!-- Main Printable Receipt Card -->
    <main class="receipt-paper">
        <!-- Security Watermark -->
        <div class="watermark">Official e-Challan</div>

        <!-- National Tricolor Security Accent -->
        <div class="tricolor-stripe"></div>

        <!-- Government Header -->
        <header class="receipt-header">
            <div class="header-top">
                <div class="gov-branding">
                    <div class="gov-crest" title="State Transport Crest">🏛️</div>
                    <div class="gov-text">
                        <h1>Government of Kerala</h1>
                        <h2>Motor Vehicles Department &bull; Traffic Enforcement Directorate</h2>
                        <p>Intelligent Traffic Enforcement System (ITES) &bull; National eChallan Integrated Portal</p>
                    </div>
                </div>

                <div class="header-right">
                    <div class="receipt-type-badge">
                        <?php echo $paid ? 'Official Payment Receipt & Certificate' : 'Statutory Traffic Violation Notice'; ?>
                    </div>
                    <div class="status-pill <?php echo $paid ? 'paid' : 'unpaid'; ?>">
                        <?php if ($paid) { ?>
                            <span>✓ PAID &amp; CLEARED</span>
                        <?php } else { ?>
                            <span>● UNPAID &bull; AWAITING PAYMENT</span>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="header-barcodes">
                <div>
                    <div class="barcode-text">CHALLAN NO: <?php echo e($challanNumber); ?></div>
                    <div class="barcode-sub">Issued under Section 133 of Motor Vehicles Act, 1988 &bull; Authenticated Digital Notice</div>
                </div>
                <div>
                    <span class="barcode-text" style="font-size:11px; opacity:0.9;">
                        NOTICE ID: #<?php echo e((string) $receipt['id']); ?> &bull; CITIZEN ID: #<?php echo e((string) $receipt['user_id']); ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- Receipt Body Content -->
        <div class="receipt-body">

            <!-- SECTION 1: Vehicle Details -->
            <section>
                <div class="section-title">
                    <span class="section-icon">🚗</span> Vehicle Particulars (RC Record)
                </div>
                <div class="details-grid">
                    <div class="detail-card">
                        <span class="detail-label">Registration Number</span>
                        <div style="margin-top: 4px;">
                            <div class="hsrp-badge">
                                <div class="hsrp-flag">IND</div>
                                <div class="hsrp-number"><?php echo e($vehicle['formatted_number']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Vehicle Make & Model</span>
                        <span class="detail-value highlight"><?php echo e($vehicle['make'] . ' ' . $vehicle['model']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Vehicle Category & Body</span>
                        <span class="detail-value"><?php echo e($vehicle['vehicle_class']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Color & Fuel Type</span>
                        <span class="detail-value"><?php echo e($vehicle['color']); ?> &bull; <?php echo e($vehicle['fuel_type']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Engine Number</span>
                        <span class="detail-value mono"><?php echo e($vehicle['engine_no']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Chassis (VIN) Number</span>
                        <span class="detail-value mono"><?php echo e($vehicle['chassis_no']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Registering Authority (RTO)</span>
                        <span class="detail-value"><?php echo e($vehicle['rto_authority']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Insurance Policy Status</span>
                        <span class="detail-value" style="color: #047857; font-size:12.5px;">
                            ✓ <?php echo e($vehicle['insurance_policy']); ?>
                        </span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Pollution (PUCC) Status</span>
                        <span class="detail-value" style="color: #047857; font-size:12.5px;">
                            ✓ <?php echo e($vehicle['pucc_status']); ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: Challan & Detection Details -->
            <section>
                <div class="section-title">
                    <span class="section-icon">📋</span> Challan & Enforcement Particulars
                </div>
                <div class="details-grid">
                    <div class="detail-card">
                        <span class="detail-label">Challan Reference Number</span>
                        <span class="detail-value mono highlight"><?php echo e($challanNumber); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Date & Time of Offense</span>
                        <span class="detail-value"><?php echo e($evidence['offense_time']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Corridor & Detection Location</span>
                        <span class="detail-value"><?php echo e($evidence['location']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Geographic Coordinates (GPS)</span>
                        <span class="detail-value mono"><?php echo e($evidence['gps_coordinates']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Enforcement Grid Node</span>
                        <span class="detail-value mono"><?php echo e($evidence['front_camera_id']); ?></span>
                    </div>
                    <div class="detail-card">
                        <span class="detail-label">Citizen / Registered Owner Email</span>
                        <span class="detail-value mono"><?php echo e($receipt['email'] ?? 'Not Linked / Verified'); ?></span>
                    </div>
                </div>
            </section>

            <!-- SECTION 3: Traffic Violation & Statutory Rules -->
            <section>
                <div class="section-title">
                    <span class="section-icon">⚖️</span> Traffic Violation & Statutory Provisions
                </div>
                <div class="violation-box">
                    <div class="violation-title-row">
                        <div>
                            <div class="violation-name"><?php echo e($violationDetails['title']); ?></div>
                            <div class="violation-statute"><?php echo e($violationDetails['statutory_section']); ?></div>
                            <div style="font-size:11.5px; color:var(--slate-600); font-weight:600; margin-bottom:6px;">
                                Applicable Rule: <?php echo e($violationDetails['cmvr_rule']); ?>
                            </div>
                        </div>
                        <div class="violation-fine-tag">
                            ₹<?php echo e(number_format((float) $receipt['fine_amount'], 2)); ?>
                        </div>
                    </div>
                    <p class="violation-desc"><?php echo e($violationDetails['description']); ?></p>
                    <div class="violation-telemetry-chip">
                        <span>📡 SENSOR TELEMETRY:</span> <?php echo e($violationDetails['radar_telemetry']); ?>
                    </div>
                </div>
            </section>

            <!-- SECTION 4: Photographic Evidence Section (AI-Generated Front & Side Views) -->
            <section class="evidence-section break-inside-avoid">
                <div class="section-title">
                    <span class="section-icon">📸</span> Photographic & Sensor Evidence (AI-Captured CCTV Enforcement Grid)
                </div>
                <div class="evidence-grid">
                    <!-- Front View Card -->
                    <div class="evidence-card">
                        <div class="evidence-header">
                            <span>🔍 <?php echo e($evidence['front_label'] ?? 'FRONT OPTICAL CAMERA CAPTURE'); ?></span>
                            <span class="camera-badge">NODE-01 / 4K</span>
                        </div>
                        <div class="evidence-frame" onclick="openImageModal('<?php echo e($evidence['front_image']); ?>', '<?php echo e(addslashes($evidence['front_label'])); ?>', '<?php echo e($evidence['front_camera_id']); ?> &bull; <?php echo e($evidence['anpr_score']); ?>')">
                            <img src="<?php echo e($evidence['front_image']); ?>" alt="Vehicle Front View Evidence" loading="lazy">
                            <div class="hud-overlay">
                                <div><span class="hud-tag"><?php echo e($evidence['hud_1'] ?? 'PLATE OCR: ' . $receipt['vehicle_no']); ?></span></div>
                                <div><span class="hud-tag"><?php echo e($evidence['hud_2'] ?? 'CONFIDENCE: ' . $evidence['anpr_score']); ?></span></div>
                            </div>
                        </div>
                        <div class="evidence-footer">
                            <span><strong>Node:</strong> <?php echo e($evidence['front_camera_id']); ?></span>
                            <span style="color:var(--navy-900); font-weight:700; cursor:pointer;">🔍 Enlarge</span>
                        </div>
                    </div>

                    <!-- Side View Card -->
                    <div class="evidence-card">
                        <div class="evidence-header">
                            <span>🚗 <?php echo e($evidence['side_label'] ?? 'LATERAL PROFILE ENFORCEMENT CAPTURE'); ?></span>
                            <span class="camera-badge">NODE-02 / RADAR</span>
                        </div>
                        <div class="evidence-frame" onclick="openImageModal('<?php echo e($evidence['side_image']); ?>', '<?php echo e(addslashes($evidence['side_label'])); ?>', '<?php echo e($evidence['side_camera_id']); ?> &bull; <?php echo e($evidence['lane']); ?>')">
                            <img src="<?php echo e($evidence['side_image']); ?>" alt="Vehicle Side View Evidence" loading="lazy">
                            <div class="hud-overlay">
                                <div><span class="hud-tag">CORRIDOR:</span> <?php echo e($evidence['lane']); ?></div>
                                <div><span class="hud-tag">EVIDENCE:</span> VIOLATION CONFIRMED</div>
                            </div>
                        </div>
                        <div class="evidence-footer">
                            <span><strong>Sensor:</strong> <?php echo e($evidence['radar_node']); ?></span>
                            <span style="color:var(--navy-900); font-weight:700; cursor:pointer;">🔍 Enlarge</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 5: Legal Information & Statutory Consequences -->
            <section class="break-inside-avoid">
                <div class="section-title">
                    <span class="section-icon">⚠️</span> Legal Information & Statutory Consequences of Violation
                </div>
                <div class="legal-box">
                    <div class="legal-header">
                        <span>⚖️ STATUTORY NOTICE UNDER SECTIONS 133 & 208 OF THE MOTOR VEHICLES ACT, 1988</span>
                    </div>
                    <div style="margin-bottom: 10px; font-size:12px; color:var(--slate-800); line-height:1.5;">
                        <strong>Prohibited Action under Law:</strong> <?php echo e($violationDetails['prohibited_act'] ?? 'Infractions contrary to established traffic safety regulations.'); ?>
                    </div>
                    <ul class="legal-list">
                        <?php foreach (($violationDetails['legal_consequences'] ?? []) as $pointTitle => $pointText) { ?>
                        <li>
                            <strong><?php echo e($pointTitle); ?>:</strong> <?php echo e($pointText); ?>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </section>

            <!-- SECTION 6: Penalty Financial Ledger Breakdown -->
            <section class="break-inside-avoid">
                <div class="section-title">
                    <span class="section-icon">💳</span> Penalty Assessment & Financial Breakdown
                </div>
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Description of Assessment</th>
                            <th>Statutory Head / Authority</th>
                            <th style="text-align: right;">Amount (INR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $compoundingFee = (float) ($violationDetails['compound_fine'] ?? $violationDetails['base_fine'] ?? $receipt['fine_amount'] ?? 0);
                        $roadSafetyCess = (float) ($violationDetails['road_safety_cess'] ?? $violationDetails['cess'] ?? 0);
                        $processingFee = (float) ($violationDetails['processing_fee'] ?? $violationDetails['portal_processing'] ?? 0);
                        ?>
                        <tr>
                            <td>Compounding Fee for <?php echo e($violationDetails['title'] ?? 'Traffic Violation'); ?></td>
                            <td>Section 200, Motor Vehicles Act, 1988</td>
                            <td class="amount">₹<?php echo e(number_format($compoundingFee, 2)); ?></td>
                        </tr>
                        <?php if ($roadSafetyCess > 0) { ?>
                        <tr>
                            <td>State Road Safety Authority Cess (Safety Infrastructure Fund)</td>
                            <td>Kerala Road Safety Authority Act, 2007</td>
                            <td class="amount">₹<?php echo e(number_format($roadSafetyCess, 2)); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if ($processingFee > 0) { ?>
                        <tr>
                            <td>Electronic Enforcement Network & Digital Processing Levy</td>
                            <td>ITES Electronic Transmission Tariff</td>
                            <td class="amount">₹<?php echo e(number_format($processingFee, 2)); ?></td>
                        </tr>
                        <?php } ?>
                        <tr class="total-row">
                            <td colspan="2">TOTAL STATUTORY PENALTY <?php echo $paid ? '(PAID & SETTLED)' : '(PAYABLE)'; ?></td>
                            <td class="amount">₹<?php echo e(number_format((float) $receipt['fine_amount'], 2)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- SECTION 7: Digital Verification & Payment Clearance Status -->
            <section class="break-inside-avoid">
                <div class="verification-strip <?php echo $paid ? '' : 'unpaid-strip'; ?>">
                    <div class="verification-info">
                        <?php if ($paid) { ?>
                            <h4>✓ Official Payment Remittance Acknowledged</h4>
                            <p>
                                Payment of <strong>₹<?php echo e(number_format((float) ($receipt['paid_amount'] ?? $receipt['fine_amount']), 2)); ?></strong>
                                was successfully processed and compounded. Transaction Reference:
                                <strong>TXN-KL-<?php echo e((string) ($receipt['payment_id'] ?? $receipt['id'])); ?>-<?php echo strtoupper(substr(md5($receipt['id'] . 'salt'), 0, 8)); ?></strong>.
                                Payment Date: <strong><?php echo e($receipt['paid_at'] ? date('d M Y, h:i A', strtotime($receipt['paid_at'])) : date('d M Y, h:i A')); ?></strong>.
                                Case status is <strong>DISCHARGED</strong>.
                            </p>
                        <?php } else { ?>
                            <h4 style="color: #9a3412;">● Penalty Settlement Pending</h4>
                            <p>
                                This official challan notice is currently pending payment. Please discharge the compounding fine of
                                <strong>₹<?php echo e(number_format((float) $receipt['fine_amount'], 2)); ?></strong> before the statutory 60-day window
                                to avoid referral to Virtual Traffic Court, vehicle blacklisting, or license suspension.
                            </p>
                        <?php } ?>
                        <p style="margin-top: 6px; font-size:11px; color:var(--slate-500);">
                            <?php if ($paid) { ?>
                            Scan the adjacent QR code with any smartphone camera or scanner to verify real-time status on the official Treasury portal.
                            <?php } else { ?>
                            Scan the adjacent QR code with any UPI app (Google Pay, PhonePe, Paytm, BHIM) to immediately compound and discharge this penalty.
                            <?php } ?>
                        </p>
                    </div>

                    <div class="qr-box" style="display:flex; flex-direction:column; align-items:center;">
                        <div id="receiptQr"></div>
                        <div style="font-size:10px; font-weight:800; color:var(--navy-900); margin-top:5px; text-align:center; letter-spacing:0.02em;">
                            <?php echo $paid ? '✓ Official Verification QR' : '📲 Scan with UPI to Pay'; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 8: Audit Digital Signature Seal -->
            <footer class="audit-seal-row break-inside-avoid">
                <div class="audit-meta">
                    <div><strong>Enforcement Entity:</strong> <?php echo e($evidence['enforcement_system']); ?></div>
                    <div><strong>Digital Audit Hash:</strong> <span class="audit-hash"><?php echo e($evidence['digital_hash']); ?></span></div>
                    <div style="margin-top: 4px; font-size: 10px; color: var(--slate-500);">
                        Document issued under the provisions of the Information Technology Act, 2000 &bull; Requires no physical signature.
                    </div>
                </div>

                <div class="signatory-stamp">
                    <div class="digital-sign-badge">
                        🔒 DIGITALLY AUTHENTICATED
                    </div>
                    <div class="signatory-name"><?php echo e($evidence['verifying_officer']); ?></div>
                    <div class="signatory-title">Enforcement Control Room, Kerala MVD</div>
                </div>
            </footer>

        </div>
    </main>

    <!-- UNIFIED BOTTOM NAVIGATION (SCREEN ONLY) -->
    <div class="portal-bottom-nav no-print">
        <a href="<?php echo e($backUrl); ?>" class="portal-nav-btn portal-nav-btn-dash">← Back to Dashboard</a>
    </div>
</div>

<!-- Lightbox Modal for Evidence Zoom -->
<div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
    <div class="modal-content-wrapper" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">Evidence Image</h3>
            <button type="button" class="modal-close" onclick="closeImageModal()">&times;</button>
        </div>
        <div class="modal-img-container">
            <img id="modalImg" src="" alt="Evidence Enlarged View">
        </div>
        <div class="modal-footer">
            <span id="modalMeta">Telemetry Details</span>
            <span>PRESS ESC OR CLICK OUTSIDE TO CLOSE</span>
        </div>
    </div>
</div>

<script src="qrcode.min.js"></script>
<script>
window.addEventListener('DOMContentLoaded', function() {
    const qrContainer = document.getElementById('receiptQr');
    const isPaid = <?php echo $paid ? 'true' : 'false'; ?>;
    
    let qrData = '';
    if (isPaid) {
        qrData = window.location.origin + window.location.pathname + '?id=' + <?php echo json_encode($receipt['id']); ?>;
    } else {
        const upiPa = 'keralatraffic.treasury@gov.in';
        const upiPn = 'Traffic Police Department';
        const upiTn = 'Challan CH<?php echo $receipt['id']; ?> <?php echo $receipt['vehicle_no']; ?>';
        const formattedAmount = '<?php echo number_format((float)$receipt['fine_amount'], 2, '.', ''); ?>';
        qrData = 'upi://pay?pa=' + encodeURIComponent(upiPa) + '&pn=' + encodeURIComponent(upiPn) + '&mc=9399&tid=CH<?php echo $receipt['id']; ?>&tr=<?php echo $receipt['id']; ?>&am=' + formattedAmount + '&cu=INR&tn=' + encodeURIComponent(upiTn);
    }

    try {
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: qrData,
                width: 96,
                height: 96,
                colorDark: "#0a1523",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            fallbackQr(qrContainer, qrData);
        }
    } catch(e) {
        fallbackQr(qrContainer, qrData);
    }

    function fallbackQr(container, url) {
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=96x96&data=' + encodeURIComponent(url);
        img.width = 96;
        img.height = 96;
        img.alt = 'Challan QR';
        container.appendChild(img);
    }
});

function openImageModal(src, title, meta) {
    document.getElementById('modalImg').src = src;
    document.getElementById('modalTitle').textContent = title || 'Evidence Capture';
    document.getElementById('modalMeta').textContent = meta || '';
    document.getElementById('imageModal').classList.add('active');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>
</body>
</html>

