<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM buildings WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Handle error if needed
    }
}

header("Location: buildings.php");
exit;
