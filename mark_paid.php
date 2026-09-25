<?php
include("db.php");
ensure_logged_in('admin');
require_csrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    exit("Invalid challan ID");
}

$challanStmt = $conn->prepare("SELECT id, fine_amount, status FROM challans WHERE id = ? LIMIT 1");
$challanStmt->bind_param("i", $id);
$challanStmt->execute();
$challan = $challanStmt->get_result()->fetch_assoc();
$challanStmt->close();

if (!$challan) {
    exit("Challan not found");
}

if (strtolower((string) $challan['status']) !== 'paid') {
    $conn->begin_transaction();

    $updateStmt = $conn->prepare("UPDATE challans SET status = 'Paid' WHERE id = ?");
    $updateStmt->bind_param("i", $id);
    $updateStmt->execute();
    $updateStmt->close();

    $paymentStmt = $conn->prepare("INSERT INTO payments (challan_id, amount) VALUES (?, ?)");
    if (!$paymentStmt) {
        $conn->rollback();
        exit("Unable to record payment");
    }

    $amount = (float) $challan['fine_amount'];
    $paymentStmt->bind_param("id", $id, $amount);
    $paymentStmt->execute();
    $paymentStmt->close();

    $conn->commit();
}

header("Location: receipt.php?id=" . $id);
exit();
?>
