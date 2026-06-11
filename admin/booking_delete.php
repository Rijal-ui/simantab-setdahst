<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        // Get file info first to delete it
        $stmt = $pdo->prepare("SELECT proposal_file FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking && $booking['proposal_file']) {
            $filePath = '../uploads/proposals/' . $booking['proposal_file'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Handle error silently or log it
    }
}

header("Location: dashboard.php");
exit;
