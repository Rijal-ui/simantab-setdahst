<?php
require_once 'auth_check.php';
require_once '../config.php';

if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'] ?? null;

// Prevent self-deletion
if ($id == $_SESSION['user_id']) {
    header("Location: users.php?error=self_delete");
    exit;
}

if ($id) {
    try {
        // Double check there's at least one other admin before deleting
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmtCount->fetchColumn();

        if ($count > 1) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: users.php?message=deleted");
            exit;
        } else {
            header("Location: users.php?error=min_one_admin");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: users.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

header("Location: users.php");
exit;
