<?php
require_once 'auth_check.php';
require_once '../config.php';

$current_role = strtolower($_SESSION['role'] ?? '');
// Allow super_admin, admin, and user_khusus
if (!in_array($current_role, ['super_admin', 'admin', 'user_khusus'])) {
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

        .table tfoot td {
            background-color: #f8f9fa !important;
            font-weight: bold !important;
            border: 1px solid #000 !important;
            -webkit-print-color-adjust: exact;
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
    <h2 class="fw-bold mb-1">LAPORAN PENDAPATAN</h2>
   <!-- <h4 class="mb-2">SI MANTAB BMD - SETDA HST</h4> -->
    <p class="mb-0 text-muted">Periode: <?= ($date_from && $date_to) ? formatDateIndonesian($date_from) . ' s.d ' . formatDateIndonesian($date_to) : 'Semua Data' ?></p>
    <hr class="my-4">
</div>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Laporan Pendapatan</h1>
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
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    <i class="bi bi-filter me-1"></i> Tampilkan Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Base query for revenue (paid invoices)
$query = "
    SELECT 
        i.*, 
        b.event_name, 
        b.booker_name,
        b.booking_date,
        bu.name as building_name
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN buildings bu ON b.building_id = bu.id
    WHERE i.status = 'paid'
";
$params = [];

if ($date_from && $date_to) {
    $query .= " AND DATE(i.updated_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$query .= " ORDER BY i.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary Stats
$total_revenue = 0;
$total_transactions = count($invoices);
$revenue_by_building = [];

foreach ($invoices as $inv) {
    $total_revenue += $inv['amount'];
    $building_name = $inv['building_name'];
    if (!isset($revenue_by_building[$building_name])) {
        $revenue_by_building[$building_name] = 0;
    }
    $revenue_by_building[$building_name] += $inv['amount'];
}
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4 no-print-row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="text-muted small fw-bold text-uppercase">Total Pendapatan</div>
                    <div class="bg-primary-subtle text-primary p-2 rounded-3">
                        <h2 class="fs-4">Rp</h2>
                    </div>
                </div>
                <h2 class="fw-bold mb-0">Rp <?= number_format($total_revenue, 0, ',', '.') ?></h2>
                <div class="text-muted small mt-2">Periode: <?= ($date_from && $date_to) ? formatDateIndonesian($date_from) . ' s.d ' . formatDateIndonesian($date_to) : 'Semua Data' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="text-muted small fw-bold text-uppercase">Total Transaksi</div>
                    <div class="bg-success-subtle text-success p-2 rounded-3">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                </div>
                <h2 class="fw-bold mb-0"><?= $total_transactions ?> Transaksi</h2>
                <div class="text-muted small mt-2">Invoices yang sudah dibayar</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4 no-print-col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Pendapatan Per Gedung</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($revenue_by_building)): ?>
                    <div class="text-muted small italic">Tidak ada data.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($revenue_by_building as $name => $amount): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-light py-3">
                            <div class="small fw-medium"><?= htmlspecialchars($name) ?></div>
                            <div class="small fw-bold text-primary">Rp <?= number_format($amount, 0, ',', '.') ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="fw-bold mb-0">Rincian Transaksi Terakhir</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase fw-bold">
                        <tr>
                            <th class="px-4 py-3 border-0 text-center">No</th>
                            <th class="px-4 py-3 border-0">Invoice</th>
                            <th class="px-4 py-3 border-0">Penyewa</th>
                            <th class="px-4 py-3 border-0">Gedung</th>
                            <th class="px-4 py-3 border-0">Kegiatan</th>
                            <th class="px-4 py-3 border-0">Tgl Bayar</th>
                            <th class="px-4 py-3 border-0 text-end">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if(empty($invoices)): ?>
                            <tr><td colspan="7" class="px-4 py-5 text-center text-muted italic small">Tidak ada data transaksi.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach($invoices as $inv): ?>
                            <tr>
                                <td class="px-4 py-4 text-center small"><?= $no++ ?></td>
                                <td class="px-4 py-4">
                                    <a href="../invoice.php?id=<?= $inv['id'] ?>" target="_blank" class="fw-bold text-primary text-decoration-none small">
                                        #<?= $inv['id'] ?>
                                    </a>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="fw-bold small"><?= htmlspecialchars($inv['booker_name']) ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="small"><?= htmlspecialchars($inv['building_name']) ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="small text-muted"><?= htmlspecialchars($inv['event_name']) ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="small"><?= date('d M Y', strtotime($inv['updated_at'])) ?></div>
                                    <div class="text-muted xsmall"><?= date('H:i', strtotime($inv['updated_at'])) ?> WITA</div>
                                </td>
                                <td class="px-4 py-4 text-end">
                                    <div class="fw-bold text-dark small">Rp <?= number_format($inv['amount'], 0, ',', '.') ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-end text-uppercase">Total Pendapatan</td>
                            <td class="px-4 py-3 text-end text-primary">
                                Rp <?= number_format($total_revenue, 0, ',', '.') ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

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
