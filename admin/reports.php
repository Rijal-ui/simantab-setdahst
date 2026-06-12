<?php
require_once 'auth_check.php';
require_once '../config.php';

$current_role = strtolower($_SESSION['role'] ?? '');
// Allow super_admin, admin, user, and user_khusus
if (!in_array($current_role, ['super_admin', 'admin', 'user', 'user_khusus'])) {
    header("Location: dashboard.php");
    exit;
}

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? ''; // Empty means all year

// Generate years for dropdown
$years = range(date('Y'), date('Y') - 5);

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Laporan Penggunaan Gedung</h1>
    <div class="btn-group shadow-sm">
        <a href="export_report.php?type=excel&year=<?= $year ?>&month=<?= $month ?>" target="_blank" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </a>
        <a href="export_report.php?type=pdf&year=<?= $year ?>&month=<?= $month ?>" target="_blank" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i> PDF / Print
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Tahun</label>
                <select name="year" class="form-select border-0 bg-light shadow-none">
                    <?php foreach($years as $y): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Bulan</label>
                <select name="month" class="form-select border-0 bg-light shadow-none">
                    <option value="">Semua Bulan</option>
                    <?php
                    $months = [
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                    ];
                    foreach($months as $num => $name):
                    ?>
                        <option value="<?= $num ?>" <?= $month == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
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
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Grafik Penggunaan Gedung</h5>
            </div>
            <div class="card-body p-4">
                <canvas id="buildingChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Grafik Pendapatan</h5>
            </div>
            <div class="card-body p-4 d-flex align-items-center justify-content-center">
                <canvas id="categoryChart" style="max-height: 300px;"></canvas>
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
    WHERE YEAR(b.booking_date) = ?
        " . ($month ? "AND MONTH(b.booking_date) = ?" : "") . "
        AND b.status = 'approved'
";
$stats_params = [$year];
if ($month) $stats_params[] = $month;

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

    // Building Usage Chart
    const ctxBuilding = document.getElementById('buildingChart').getContext('2d');
    new Chart(ctxBuilding, {
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
                        weight: 'bold'
                    },
                    color: '#333'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grace: '10%' // Add space for labels at the top
                }
            }
        }
    });

    // Category Usage Chart
    const ctxCategory = document.getElementById('categoryChart').getContext('2d');
    const categoryData = [<?= $category_stats['gratis'] ?>, <?= $category_stats['berbayar'] ?>];
    const totalUsage = categoryData.reduce((a, b) => a + b, 0);
    
    new Chart(ctxCategory, {
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
                    position: 'bottom'
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 12
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
});
</script>

<?php include '../footer.php'; ?>
