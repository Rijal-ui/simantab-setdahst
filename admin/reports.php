<?php
require_once 'auth_check.php';
require_once '../config.php';

$current_role = strtolower($_SESSION['role'] ?? '');
// Allow super_admin, admin, user, and user_khusus
if (!in_array($current_role, ['super_admin', 'admin', 'user', 'user_khusus'])) {
    header("Location: dashboard.php");
    exit;
}

// Helper function to format date in Indonesian
function formatDateIndonesian($dateString) {
    if (!$dateString) return '';
    
    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    
    $timestamp = strtotime($dateString);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $months[$month] . ' ' . $year;
}

// Get date range from GET parameters
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

include 'header.php';
?>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<style>
    .xsmall { font-size: 0.7rem; }
    .bg-primary-subtle { background-color: #e7f1ff; }
    .bg-success-subtle { background-color: #d1fae5; }

    @media print {
        @page {
            size: landscape;
            margin: 1cm;
        }

        /* Hide everything not related to the report */
        .navbar, #sidebar, .btn-group, .card:has(form), .card-header, .btn, footer, .d-flex.justify-content-between.flex-wrap, .no-print-row, .no-print-col {
            display: none !important;
        }
        
        /* Reset main content layout */
        main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 0 !important;
        }
        
        .container-fluid {
            padding: 0 !important;
        }

        /* Adjust cards for print */
        .card {
            border: none !important;
            box-shadow: none !important;
            margin-bottom: 20px !important;
        }
        
        .shadow-sm {
            box-shadow: none !important;
        }

        /* Ensure table is readable */
        .table {
            width: 100% !important;
            border: 1px solid #000 !important;
        }
        
        .table thead th {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border: 1px solid #000 !important;
            -webkit-print-color-adjust: exact;
        }
        
        .table td, .table th {
            padding: 10px !important;
            border: 1px solid #000 !important;
        }

        /* Show print-only header */
        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 30px;
        }

        /* Make the table container full width on print */
        .col-lg-8 {
            width: 100% !important;
            flex: 0 0 100% !important;
            max-width: 100% !important;
        }
    }

    /* Hide print header on screen */
    .print-header {
        display: none;
    }
</style>

<div class="print-header text-center">
    <h2 class="fw-bold mb-1">LAPORAN PENGGUNAAN GEDUNG</h2>
    <p class="mb-0 text-muted">Periode: <?= ($date_from && $date_to) ? formatDateIndonesian($date_from) . ' s.d ' . formatDateIndonesian($date_to) : 'Semua Data' ?></p>
    <hr class="my-4">
</div>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Laporan Penggunaan Gedung</h1>
    <div class="btn-group shadow-sm">
        <button onclick="window.print()" class="btn btn-success px-4 py-2 fw-bold">
            <i class="bi bi-printer me-2"></i> Cetak Laporan
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Dari</label>
                <input type="text" id="date_from_display" value="<?= $date_from ? date('d/m/Y', strtotime($date_from)) : '' ?>" class="form-control border-0 bg-light shadow-none">
                <input type="hidden" name="date_from" id="date_from" value="<?= $date_from ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Sampai</label>
                <input type="text" id="date_to_display" value="<?= $date_to ? date('d/m/Y', strtotime($date_to)) : '' ?>" class="form-control border-0 bg-light shadow-none">
                <input type="hidden" name="date_to" id="date_to" value="<?= $date_to ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-dark w-100 shadow-sm">
                    <i class="bi bi-filter me-1"></i> Tampilkan Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4 no-print-row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Grafik Penggunaan Gedung</h5>
            </div>
            <div class="card-body p-4">
                <!-- Container with responsive dimensions -->
                <div style="height: 350px; width: 100%;">
                    <canvas id="buildingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Grafik Pendapatan</h5>
            </div>
            <div class="card-body p-4 d-flex align-items-center justify-content-center">
                <!-- Container with responsive dimensions -->
                <div style="height: 300px; width: 100%;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Table -->
<?php
// Statistics for charts
// First get all buildings
$buildings_query = "SELECT id, name, category FROM buildings ORDER BY name";
$stmt_buildings = $pdo->query($buildings_query);
$all_buildings = $stmt_buildings->fetchAll(PDO::FETCH_ASSOC);

// Initialize building usage with 0 for all buildings
$building_usage = [];
foreach ($all_buildings as $building) {
    $building_usage[$building['name']] = 0;
}

// Now get all approved bookings with building and invoice info
$stats_query = "
    SELECT 
        b.id as booking_id,
        g.name as building_name,
        g.category,
        i.id as invoice_id
    FROM bookings b
    JOIN buildings g ON b.building_id = g.id
    LEFT JOIN invoices i ON b.id = i.booking_id
    WHERE b.status = 'approved'
";
$stats_params = [];

if ($date_from && $date_to) {
    $stats_query .= " AND DATE(b.booking_date) BETWEEN ? AND ?";
    $stats_params[] = $date_from;
    $stats_params[] = $date_to;
}

$stmt_stats = $pdo->prepare($stats_query);
$stmt_stats->execute($stats_params);
$stats_data = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

$category_stats = ['gratis' => 0, 'berbayar' => 0];

// Process each booking
foreach ($stats_data as $row) {
    // Count building usage
    if (isset($building_usage[$row['building_name']])) {
        $building_usage[$row['building_name']]++;
    }
    
    // Determine category for donut chart
    if ($row['category'] == 'gratis') {
        $category_stats['gratis']++;
    } else {
        // If building is berbayar but no invoice, count as gratis
        if (empty($row['invoice_id'])) {
            $category_stats['gratis']++;
        } else {
            $category_stats['berbayar']++;
        }
    }
}

// Prepare building chart data
$building_labels = array_keys($building_usage);
$building_counts = array_values($building_usage);

$query = "
    SELECT b.*, g.name as building_name 
    FROM bookings b 
    JOIN buildings g ON b.building_id = g.id
";
$params = [];

if ($date_from && $date_to) {
    $query .= " WHERE DATE(b.booking_date) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$query .= " ORDER BY b.booking_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Gedung</th>
                    <th class="px-4 py-3">Pengguna Gedung</th>
                    <th class="px-4 py-3">Acara</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php if(empty($bookings)): ?>
                    <tr><td colspan="5" class="px-4 py-5 text-center text-muted italic small">Tidak ada data booking untuk periode ini.</td></tr>
                <?php else: ?>
                    <?php foreach($bookings as $booking): ?>
                    <tr>
                        <td class="px-4 py-4">
                            <div class="fw-bold small"><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></div>
                            <div class="text-muted xsmall"><?= date('H:i', strtotime($booking['start_time'])) ?> WITA s.d <?= date('H:i', strtotime($booking['end_time'])) ?> WITA</div>
                        </td>
                        <td class="px-4 py-4 fw-medium small">
                            <?= htmlspecialchars($booking['building_name']) ?>
                        </td>
                        <td class="px-4 py-4">
                            <div class="fw-bold small"><?= htmlspecialchars($booking['booker_name']) ?></div>
                            <div class="text-muted xsmall"><?= htmlspecialchars($booking['organization']) ?></div>
                        </td>
                        <td class="px-4 py-4 small">
                            <?= htmlspecialchars($booking['event_name']) ?>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <?php
                            $statusColors = [
                                'pending' => 'bg-warning-subtle text-warning-emphasis',
                                'approved' => 'bg-success-subtle text-success-emphasis',
                                'rejected' => 'bg-danger-subtle text-danger-emphasis',
                                'cancelled' => 'bg-secondary-subtle text-secondary-emphasis'
                            ];
                            $colorClass = $statusColors[$booking['status']] ?? 'bg-light text-dark';
                            ?>
                            <span class="badge rounded-pill <?= $colorClass ?> px-3 py-2 small fw-bold">
                                <?= ucfirst($booking['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .xsmall { font-size: 0.7rem; }
    .bg-success-subtle { background-color: #d1fae5; }
    .text-success-emphasis { color: #065f46; }
    .bg-warning-subtle { background-color: #fef3c7; }
    .text-warning-emphasis { color: #92400e; }
    .bg-danger-subtle { background-color: #fee2e2; }
    .text-danger-emphasis { color: #991b1b; }
    .bg-secondary-subtle { background-color: #f3f4f6; }
    .text-secondary-emphasis { color: #374151; }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Register the datalabels plugin to all charts
    Chart.register(ChartDataLabels);
    
    // Function to check if mobile view
    function isMobileView() {
        return window.innerWidth < 768;
    }

    // Building Usage Chart
    const ctxBuilding = document.getElementById('buildingChart').getContext('2d');
    const buildingChart = new Chart(ctxBuilding, {
        type: 'bar',
        data: {
            labels: <?= json_encode($building_labels) ?>,
            datasets: [{
                label: 'Jumlah Penggunaan',
                data: <?= json_encode($building_counts) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    anchor: 'end',
                    align: 'top',
                    formatter: Math.round,
                    font: {
                        weight: 'bold',
                        size: isMobileView() ? 10 : 12
                    },
                    color: '#333'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        stepSize: 1,
                        font: {
                            size: isMobileView() ? 10 : 12
                        }
                    },
                    grace: '10%' // Add space for labels at the top
                },
                x: {
                    ticks: {
                        maxRotation: isMobileView() ? 90 : 45,
                        minRotation: isMobileView() ? 90 : 0,
                        font: {
                            size: isMobileView() ? 10 : 12
                        }
                    }
                }
            }
        }
    });

    // Category Usage Chart
    const ctxCategory = document.getElementById('categoryChart').getContext('2d');
    const categoryData = [<?= $category_stats['gratis'] ?>, <?= $category_stats['berbayar'] ?>];
    const totalUsage = categoryData.reduce((a, b) => a + b, 0);
    
    const categoryChart = new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: ['Gratis', 'Berbayar'],
            datasets: [{
                data: categoryData,
                backgroundColor: ['#10b981', '#f59e0b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: isMobileView() ? 11 : 14
                        },
                        padding: isMobileView() ? 15 : 20
                    }
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: isMobileView() ? 10 : 12
                    },
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        if (sum === 0) return "";
                        let percentage = (value * 100 / sum).toFixed(1) + "%";
                        return value > 0 ? percentage : "";
                    }
                }
            },
            cutout: '70%'
        },
        plugins: [{
            id: 'centerText',
            beforeDraw: function(chart) {
                const width = chart.width;
                const height = chart.height;
                const ctx = chart.ctx;
                
                ctx.restore();
                
                const fontSize = (height / 114).toFixed(2);
                ctx.textBaseline = "middle";
                ctx.fillStyle = '#3b82f6';
                
                const text = totalUsage;
                const textX = Math.round((width - ctx.measureText(text).width) / 2);
                
                ctx.font = (fontSize * 1.5) + "em sans-serif";
                ctx.fillText(text, textX, height / 2);
                
                ctx.save();
            }
        }]
    });
    
    // Re-update charts on window resize
    window.addEventListener('resize', function() {
        buildingChart.update();
        categoryChart.update();
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Flatpickr for "Dari"
    flatpickr("#date_from_display", {
        locale: "id",
        dateFormat: "d/m/Y",
        altInput: false,
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                // Convert to YYYY-MM-DD for hidden input
                const year = selectedDates[0].getFullYear();
                const month = String(selectedDates[0].getMonth() + 1).padStart(2, '0');
                const day = String(selectedDates[0].getDate()).padStart(2, '0');
                document.getElementById('date_from').value = `${year}-${month}-${day}`;
            }
        }
    });

    // Initialize Flatpickr for "Sampai"
    flatpickr("#date_to_display", {
        locale: "id",
        dateFormat: "d/m/Y",
        altInput: false,
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                // Convert to YYYY-MM-DD for hidden input
                const year = selectedDates[0].getFullYear();
                const month = String(selectedDates[0].getMonth() + 1).padStart(2, '0');
                const day = String(selectedDates[0].getDate()).padStart(2, '0');
                document.getElementById('date_to').value = `${year}-${month}-${day}`;
            }
        }
    });
});
</script>
<?php include '../footer.php'; ?>
