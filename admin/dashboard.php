<?php
require_once 'auth_check.php';
require_once '../config.php';

if (in_array(strtolower($_SESSION['role'] ?? ''), ['user', 'user_khusus'])) {
    header("Location: booking_manual.php");
    exit;
}

// Handle status updates
if (isset($_POST['action']) && isset($_POST['booking_id'])) {
    $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $booking_id = $_POST['booking_id'];
    
    // First get the booking details to find other bookings in the same group
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        // Update all bookings that belong to the same group
        $updateStmt = $pdo->prepare("
            UPDATE bookings 
            SET status = ? 
            WHERE event_name = ? 
            AND booker_name = ? 
            AND building_id = ?
        ");
        $updateStmt->execute([$status, $booking['event_name'], $booking['booker_name'], $booking['building_id']]);
    }
}

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch bookings with search filter
if ($search_query) {
    $stmt = $pdo->prepare("
        SELECT b.*, g.name as building_name, g.image_url, g.category as building_category
        FROM bookings b 
        JOIN buildings g ON b.building_id = g.id 
        WHERE g.name LIKE ? OR b.booker_name LIKE ?
        ORDER BY b.created_at DESC
    ");
    $search_param = '%' . $search_query . '%';
    $stmt->execute([$search_param, $search_param]);
} else {
    $stmt = $pdo->query("
        SELECT b.*, g.name as building_name, g.image_url, g.category as building_category
        FROM bookings b 
        JOIN buildings g ON b.building_id = g.id 
        ORDER BY b.created_at DESC
    ");
}
$all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group bookings that are part of multi-day booking
$grouped_bookings = [];
$processed_ids = [];

foreach ($all_bookings as $booking) {
    if (in_array($booking['id'], $processed_ids)) {
        continue;
    }
    
    // Find all bookings that belong to the same group
    $group = [
        'main_booking' => $booking,
        'dates' => [$booking['booking_date']],
        'booking_ids' => [$booking['id']]
    ];
    $processed_ids[] = $booking['id'];
    
    foreach ($all_bookings as $other_booking) {
        if ($other_booking['id'] == $booking['id']) continue;
        if (in_array($other_booking['id'], $processed_ids)) continue;
        
        // Same event name, same booker name, same building id
        if (
            $other_booking['event_name'] == $booking['event_name'] &&
            $other_booking['booker_name'] == $booking['booker_name'] &&
            $other_booking['building_id'] == $booking['building_id']
        ) {
            $group['dates'][] = $other_booking['booking_date'];
            $group['booking_ids'][] = $other_booking['id'];
            $processed_ids[] = $other_booking['id'];
        }
    }
    
    // Sort dates
    sort($group['dates']);
    $grouped_bookings[] = $group;
}

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Dashboard</h1>
    <form method="GET" class="d-flex align-items-center gap-2">
        <div class="input-group">
            <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control border-0 bg-light" placeholder="Pencarian" value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <?php if ($search_query): ?>
            <a href="dashboard.php" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="px-4 py-3">Tanggal & Waktu</th>
                    <th class="px-4 py-3">Gedung</th>
                    <th class="px-4 py-3">Peminjam</th>
                    <th class="px-4 py-3">Acara</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php if (count($grouped_bookings) > 0): ?>
                    <?php foreach($grouped_bookings as $group): ?>
                    <?php $booking = $group['main_booking']; $dates = $group['dates']; $is_multi_day = count($dates) > 1; ?>
                    <tr>
                        <td class="px-4 py-4">
                            <?php if ($is_multi_day): ?>
                                <?php 
                                $start_date = $dates[0];
                                $end_date = $dates[count($dates)-1];
                                $start_month_year = date('M Y', strtotime($start_date));
                                $end_month_year = date('M Y', strtotime($end_date));
                                
                                if ($start_month_year == $end_month_year) {
                                    $date_range = date('d', strtotime($start_date)) . '-' . date('d M Y', strtotime($end_date));
                                } else {
                                    $date_range = date('d M', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
                                }
                                ?>
                                <div class="fw-bold"><?= $date_range ?></div>
                                <div class="text-muted small">
                                    Beberapa hari (<?= count($dates) ?> hari)
                                </div>
                            <?php else: ?>
                                <div class="fw-bold"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                                <div class="text-muted small">
                                    <?= date('H:i', strtotime($booking['start_time'])) ?> WITA s.d <?= date('H:i', strtotime($booking['end_time'])) ?> WITA
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($booking['image_url'])): ?>
                                    <img src="../<?= htmlspecialchars($booking['image_url']) ?>" alt="<?= htmlspecialchars($booking['building_name']) ?>" class="rounded-2 me-3 object-fit-cover" style="width: 45px; height: 45px;">
                                <?php else: ?>
                                    <div class="rounded-2 bg-light d-flex align-items-center justify-content-center text-muted me-3" style="width: 45px; height: 45px;">
                                        <i class="bi bi-building"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="fw-medium"><?= htmlspecialchars($booking['building_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="fw-bold small"><?= htmlspecialchars($booking['booker_name']) ?></div>
                            <div class="text-muted xsmall"><?= htmlspecialchars($booking['booker_email']) ?></div>
                            <div class="text-muted xsmall"><?= htmlspecialchars($booking['booker_phone']) ?></div>
                            <?php if($booking['organization']): ?>
                                <div class="badge bg-light text-dark fw-normal mt-1 xsmall border"><?= htmlspecialchars($booking['organization']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4">
                            <div class="fw-bold small mb-1">
                                <a href="#" class="text-decoration-none text-dark hover-primary event-details-trigger"
                                   data-eventname="<?= htmlspecialchars($booking['event_name']) ?>"
                                   data-eventdesc="<?= htmlspecialchars($booking['event_description']) ?>"
                                   data-bookername="<?= htmlspecialchars($booking['booker_name']) ?>"
                                   data-bookerphone="<?= htmlspecialchars($booking['booker_phone']) ?>"
                                   data-bookeremail="<?= htmlspecialchars($booking['booker_email']) ?>"
                                   data-organization="<?= htmlspecialchars($booking['organization']) ?>"
                                   data-proposal="<?= !empty($booking['proposal_file']) ? '../uploads/proposals/' . htmlspecialchars($booking['proposal_file']) : '' ?>">
                                    <?= htmlspecialchars($booking['event_name']) ?>
                                </a>
                            </div>
                            <?php if(!empty($booking['proposal_file'])): ?>
                                <a href="../uploads/proposals/<?= htmlspecialchars($booking['proposal_file']) ?>" target="_blank" class="xsmall text-primary text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-file-earmark-pdf"></i> Lihat Proposal
                                </a>
                            <?php endif; ?>
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
                            
                            <?php 
                            // Find invoice for any of the booking IDs in the group
                            $invoice = null;
                            foreach ($group['booking_ids'] as $bid) {
                                $invoiceStmt = $pdo->prepare("SELECT id, status FROM invoices WHERE booking_id = ?");
                                $invoiceStmt->execute([$bid]);
                                $inv = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
                                if ($inv) {
                                    $invoice = $inv;
                                    break;
                                }
                            }
                            if ($invoice): 
                                $invColors = [
                                    'unpaid' => 'text-warning',
                                    'paid' => 'text-success',
                                    'cancelled' => 'text-danger'
                                ];
                                $invIcon = [
                                    'unpaid' => 'bi-clock-history',
                                    'paid' => 'bi-check-circle',
                                    'cancelled' => 'bi-x-circle'
                                ];
                                $color = $invColors[$invoice['status']] ?? 'text-muted';
                                $icon = $invIcon[$invoice['status']] ?? 'bi-receipt';
                            ?>
                                <div class="mt-2">
                                    <a href="../invoice.php?id=<?= $invoice['id'] ?>" target="_blank" class="text-decoration-none <?= $color ?> xsmall fw-bold">
                                        <i class="bi <?= $icon ?> me-1"></i> Invoice: <?= ucfirst($invoice['status']) ?>
                                    </a>
                                    <?php if ($invoice['status'] === 'unpaid'): ?>
                                        <div class="mt-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 xsmall fw-bold btn-confirm" data-id="<?= $invoice['id'] ?>">Konfirmasi Bayar</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="booking_edit.php?id=<?= $booking['id'] ?>" class="btn btn-outline-light text-primary border-0" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="booking_delete.php?id=<?= $booking['id'] ?>" class="btn btn-outline-light text-danger border-0 delete-trigger" title="Hapus" data-message="Yakin ingin menghapus booking untuk acara '<?= htmlspecialchars($booking['event_name']) ?>'?">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php if ($invoice || $booking['status'] === 'approved'): ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-light text-success border-0 dropdown-toggle no-caret" data-bs-toggle="dropdown" title="Cetak">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <?php if ($invoice): ?>
                                        <li><a class="dropdown-item xsmall fw-bold" href="../invoice.php?id=<?= $invoice['id'] ?>" target="_blank"><i class="bi bi-receipt me-2"></i> CETAK INVOICE</a></li>
                                        <?php endif; ?>
                                        <?php if ($booking['status'] === 'approved'): ?>
                                         <li><a class="dropdown-item xsmall fw-bold" href="print_permit.php?id=<?= $booking['id'] ?>" target="_blank"><i class="bi bi-file-earmark-check me-2"></i> CETAK SURAT IZIN</a></li>
                                         <?php endif; ?>
                                         <?php if ($invoice && $invoice['status'] === 'paid'): ?>
                                         <li><a class="dropdown-item xsmall fw-bold" href="print_receipt.php?id=<?= $booking['id'] ?>" target="_blank"><i class="bi bi-patch-check me-2"></i> CETAK BUKTI BAYAR</a></li>
                                         <?php endif; ?>
                                         <?php if ($invoice && $booking['status'] !== 'pending' && $booking['building_category'] === 'berbayar'): ?>
                                         <li><a class="dropdown-item xsmall fw-bold" href="print_skrd.php?id=<?= $booking['id'] ?>" target="_blank"><i class="bi bi-file-earmark-text me-2"></i> CETAK SKR-D</a></li>
                                         <?php endif; ?>
                                     </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($booking['status'] === 'pending'): ?>
                            <div class="mt-2 d-flex justify-content-end gap-1">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success px-2 py-0 xsmall fw-bold" title="Setujui">SETUJUI</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-danger px-2 py-0 xsmall fw-bold" title="Tolak">TOLAK</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-5 text-center">
                            <div class="text-muted">
                                <i class="bi bi-search fs-1 mb-3 d-block"></i>
                                <?php if ($search_query): ?>
                                    <p class="fw-medium">Tidak ada hasil pencarian untuk "<?= htmlspecialchars($search_query) ?>"</p>
                                <?php else: ?>
                                    <p class="fw-medium">Belum ada data booking</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .xsmall { font-size: 0.7rem; }
    .no-caret::after { display: none !important; }
    .object-fit-cover { object-fit: cover; }
    .hover-primary:hover { color: var(--bs-primary) !important; }
    .table-hover tbody tr:hover { background-color: #f9fafb; }
    .bg-success-subtle { background-color: #d1fae5; }
    .text-success-emphasis { color: #065f46; }
    .bg-warning-subtle { background-color: #fef3c7; }
    .text-warning-emphasis { color: #92400e; }
    .bg-danger-subtle { background-color: #fee2e2; }
    .text-danger-emphasis { color: #991b1b; }
    .bg-secondary-subtle { background-color: #f3f4f6; }
    .text-secondary-emphasis { color: #374151; }
</style>

<?php 
include '../footer.php'; 
include 'delete_modal.php';
?>
