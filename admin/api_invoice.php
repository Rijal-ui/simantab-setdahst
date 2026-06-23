<?php
require_once 'auth_check.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['invoice' => null]);
    exit;
}

$booking_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['invoice' => $invoice]);
} catch (Exception $e) {
    echo json_encode(['invoice' => null]);
}
?>
