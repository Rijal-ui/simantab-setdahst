<?php
require_once 'config.php';

try {
    // Add rental_type column to buildings table
    $pdo->exec("ALTER TABLE buildings ADD COLUMN rental_type ENUM('per_hari', 'per_bulan', 'per_tahun') DEFAULT 'per_hari' AFTER category");
    echo "Kolom 'rental_type' berhasil ditambahkan ke tabel buildings.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Kolom 'rental_type' sudah ada.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>