<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

$current_role = strtolower($_SESSION['role'] ?? '');
// Consistency check for access
if (!in_array($current_role, ['super_admin', 'admin', 'user', 'user_khusus'])) {
    die("Access denied");
}

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? '';
$type = $_GET['type'] ?? 'excel';

$query = "
    SELECT b.booking_date, b.start_time, b.end_time, g.name as building_name, 
           b.booker_name, b.organization, b.event_name, b.status
    FROM bookings b 
    JOIN buildings g ON b.building_id = g.id 
    WHERE YEAR(b.booking_date) = ?
";
$params = [$year];

if ($month) {
    $query .= " AND MONTH(b.booking_date) = ?";
    $params[] = $month;
}

$query .= " ORDER BY b.booking_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($type === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_booking_' . $year . ($month ? '_' . $month : '') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th>Tanggal</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Gedung</th><th>Peminjam</th><th>Organisasi</th><th>Acara</th><th>Status</th></tr>';
    
    foreach ($bookings as $row) {
        echo '<tr>';
        echo '<td>' . $row['booking_date'] . '</td>';
        echo '<td>' . date('H:i', strtotime($row['start_time'])) . ' WITA</td>';
        echo '<td>' . date('H:i', strtotime($row['end_time'])) . ' WITA</td>';
        echo '<td>' . htmlspecialchars($row['building_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['booker_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['organization']) . '</td>';
        echo '<td>' . htmlspecialchars($row['event_name']) . '</td>';
        echo '<td>' . ucfirst($row['status']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
} elseif ($type === 'pdf') {
    // Print Friendly View
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Booking</title>
        <style>
            body { font-family: sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
            th { background-color: #f2f2f2; }
            .header { text-align: center; margin-bottom: 20px; }
            @media print {
                .no-print { display: none; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="header">
            <h2>Laporan Peminjaman Gedung</h2>
            <p>Periode: <?= $month ? date("F", mktime(0, 0, 0, $month, 10)) : "Semua Bulan" ?> <?= $year ?></p>
        </div>
        
        <button class="no-print" onclick="window.print()" style="margin-bottom: 10px; padding: 5px 10px; cursor: pointer;">Cetak / Simpan PDF</button>

        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Gedung</th>
                    <th>Peminjam</th>
                    <th>Organisasi</th>
                    <th>Acara</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $row): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['booking_date'])) ?></td>
                    <td><?= date('H:i', strtotime($row['start_time'])) ?> WITA s.d <?= date('H:i', strtotime($row['end_time'])) ?> WITA</td>
                    <td><?= htmlspecialchars($row['building_name']) ?></td>
                    <td><?= htmlspecialchars($row['booker_name']) ?></td>
                    <td><?= htmlspecialchars($row['organization']) ?></td>
                    <td><?= htmlspecialchars($row['event_name']) ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
}
?>
