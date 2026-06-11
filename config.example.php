<?php
$host = 'localhost';
$dbname = 'booking_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Auto-fix role 'prokom' and ensure 'user' role exists
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'user', 'user_khusus') DEFAULT 'user'");
        $pdo->exec("UPDATE users SET role = 'user' WHERE username = 'prokom'");
    } catch(Exception $e) {}
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function for JSON response
function jsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}
?>
