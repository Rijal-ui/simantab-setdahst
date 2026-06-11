<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Inactivity timeout logic (15 minutes = 900 seconds)
$timeout_duration = 900;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Last activity was more than 15 minutes ago
    session_unset();
    session_destroy();
    header("Location: ../login.php?message=timeout");
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Sync role from database to ensure accuracy
if (isset($_SESSION['user_id'])) {
    require_once '../config.php';
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $db_role = $stmt->fetchColumn();
    if ($db_role) {
        $_SESSION['role'] = strtolower($db_role);
    }
}

// Role-based access control
$current_page = basename($_SERVER['PHP_SELF']);
$user_allowed_pages = ['booking_manual.php', 'reports.php', 'export_report.php', 'logout.php'];
$user_khusus_allowed_pages = ['booking_manual.php', 'reports.php', 'export_report.php', 'revenue.php', 'logout.php'];

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'user') {
        if (!in_array($current_page, $user_allowed_pages)) {
            header("Location: booking_manual.php");
            exit;
        }
    } elseif ($role === 'user_khusus') {
        if (!in_array($current_page, $user_khusus_allowed_pages)) {
            header("Location: booking_manual.php");
            exit;
        }
    }
}
?>
