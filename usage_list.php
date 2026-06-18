<?php
require_once 'config.php';

// Fetch approved bookings joined with building names
$query = "SELECT b.*, bl.name as building_name 
          FROM bookings b 
          JOIN buildings bl ON b.building_id = bl.id 
          WHERE b.status = 'approved' AND b.booking_date >= CURDATE()";
$stmt = $pdo->query($query);
$usage_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add single routine event for "Zumba Isteri Bupati"
$routine_events = [
    [
        'booking_date' => '2000-01-01', // Use a past date to keep it at the top after sorting (ASC)
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'building_name' => 'Gedung Balai Rakyat (Siang Hari)',
        'organization' => 'Ketua TP PKK Kabupaten Hulu Sungai Tengah',
        'booker_name' => 'Acara Rutin',
        'booker_phone' => '-',
        'booker_email' => '-',
        'is_routine' => true,
    ]
];

// Merge database data with routine events
$usage_data = array_merge($usage_data, $routine_events);

// Sort the combined data by date and time in ascending order (closest first)
usort($usage_data, function($a, $b) {
    $date_a = strtotime($a['booking_date'] . ' ' . $a['start_time']);
    $date_b = strtotime($b['booking_date'] . ' ' . $b['start_time']);
    return $date_a <=> $date_b;
});

// Helper function to translate day names to Indonesian
function getDayNameInIndonesian($date) {
    $day_name = date('l', strtotime($date));
    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    return $days[$day_name];
}

include 'header.php';
?>

<div class="container-fluid px-4 py-5 d-flex flex-column" style="height: calc(100vh - 120px);">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Data Jadwal Pemakaian Gedung</h2>
        <p class="text-muted">Daftar penggunaan gedung yang telah disetujui</p>
    </div>

    <div class="card shadow-sm border-0 flex-grow-1 d-flex flex-column overflow-hidden">
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive border-bottom">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3" style="width: 50px;">No.</th>
                            <th class="px-4 py-3">Hari</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Gedung</th>
                            <th class="px-4 py-3">Instansi/Organisasi</th>
                            <th class="px-4 py-3">Atas Nama</th>
                            <th class="px-4 py-3">Kontak</th>
                        </tr>
                    </thead>
                </table>
            </div>
            
            <div class="scroll-wrapper overflow-hidden flex-grow-1" style="min-height: 200px;">
                <div class="scroll-content">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                                <?php if (count($usage_data) > 0): ?>
                                    <?php foreach ($usage_data as $index => $row): ?>
                                    <tr class="animate-row">
                                        <td class="px-4 py-3" style="width: 50px;"><?= $index + 1 ?></td>
                                        <td class="px-4 py-3"><?= isset($row['is_routine']) ? 'Setiap Kamis' : getDayNameInIndonesian($row['booking_date']) ?></td>
                                        <td class="px-4 py-3"><?= isset($row['is_routine']) ? '-' : date('d M Y', strtotime($row['booking_date'])) ?></td>
                                        <td class="px-4 py-3"><?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?></td>
                                        <td class="px-4 py-3"><strong><?= htmlspecialchars($row['building_name']) ?></strong></td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($row['organization'] ?: '-') ?></td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($row['booker_name']) ?></td>
                                        <td class="px-4 py-3">
                                            <i class="bi bi-telephone text-primary me-1"></i> <?= htmlspecialchars($row['booker_phone']) ?><br>
                                            <i class="bi bi-envelope text-secondary me-1"></i> <?= htmlspecialchars($row['booker_email']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <!-- Duplicate data for seamless scrolling -->
                                    <?php foreach ($usage_data as $index => $row): ?>
                                    <tr class="animate-row">
                                        <td class="px-4 py-3" style="width: 50px;"><?= $index + 1 ?></td>
                                        <td class="px-4 py-3"><?= isset($row['is_routine']) ? 'Setiap Kamis' : getDayNameInIndonesian($row['booking_date']) ?></td>
                                        <td class="px-4 py-3"><?= isset($row['is_routine']) ? '-' : date('d M Y', strtotime($row['booking_date'])) ?></td>
                                        <td class="px-4 py-3"><?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?></td>
                                        <td class="px-4 py-3"><strong><?= htmlspecialchars($row['building_name']) ?></strong></td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($row['organization'] ?: '-') ?></td>
                                        <td class="px-4 py-3"><?= htmlspecialchars($row['booker_name']) ?></td>
                                        <td class="px-4 py-3">
                                            <i class="bi bi-telephone text-primary me-1"></i> <?= htmlspecialchars($row['booker_phone']) ?><br>
                                            <i class="bi bi-envelope text-secondary me-1"></i> <?= htmlspecialchars($row['booker_email']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">Belum ada data pemakaian gedung.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Beranda
        </a>
    </div>
</div>

<style>
    .table {
        table-layout: fixed;
        width: 100%;
    }
    .table th, .table td {
        vertical-align: middle;
        word-wrap: break-word;
    }
    /* Menyelaraskan lebar kolom antara header dan body */
    .table th:nth-child(1), .table td:nth-child(1) { width: 5%; }
    .table th:nth-child(2), .table td:nth-child(2) { width: 10%; }
    .table th:nth-child(3), .table td:nth-child(3) { width: 12%; }
    .table th:nth-child(4), .table td:nth-child(4) { width: 12%; }
    .table th:nth-child(5), .table td:nth-child(5) { width: 18%; }
    .table th:nth-child(6), .table td:nth-child(6) { width: 13%; }
    .table th:nth-child(7), .table td:nth-child(7) { width: 12%; }
    .table th:nth-child(8), .table td:nth-child(8) { width: 18%; }

    /* Smooth Scroll Animation */
    .scroll-wrapper {
        position: relative;
        background: #fff;
    }

    .scroll-content {
        animation: scrollUp 35s linear infinite;
    }

    .scroll-wrapper:hover .scroll-content {
        animation-play-state: paused;
    }

    @keyframes scrollUp {
        0% {
            transform: translateY(0);
        }
        100% {
            transform: translateY(-50%);
        }
    }

    /* Animate rows for extra effect */
    .animate-row {
        transition: all 0.3s ease;
    }
    .animate-row:hover {
        background-color: #f0fff4 !important;
        transform: scale(1.01);
    }
</style>

<?php include 'footer.php'; ?>
