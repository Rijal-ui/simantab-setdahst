<?php
require_once 'auth_check.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'] ?? null;

    if ($invoice_id) {
        try {
            $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ? AND status = 'unpaid'");
            $stmt->execute([$invoice_id]);
        } catch (PDOException $e) {
            // Optional: Log error or show a message
        }
    }
}

header("Location: dashboard.php");
exit;
