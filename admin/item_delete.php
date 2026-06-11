<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Optional: Log error or show a message
    }
}

header("Location: items.php");
exit;
