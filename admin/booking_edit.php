<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;
$message = '';
$error = '';
$booking = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT b.*, g.name as building_name, g.category as building_category, g.price as building_price FROM bookings b JOIN buildings g ON b.building_id = g.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    header("Location: dashboard.php");
    exit;
}

// Fetch all available add-on items
$itemsStmt = $pdo->query("SELECT * FROM items ORDER BY name");
$available_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current booking items
$current_items = [];
$current_items_stmt = $pdo->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
$current_items_stmt->execute([$id]);
while ($item_row = $current_items_stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_items[$item_row['item_id']] = $item_row['quantity'];
}

// Fetch buildings for dropdown
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = $_POST['building_id'];
    $booker_name = $_POST['booker_name'];
    $booker_email = $_POST['booker_email'];
    $booker_phone = $_POST['booker_phone'];
    $organization = $_POST['organization'];
    $event_name = $_POST['event_name'];
    $event_description = $_POST['event_description'];
    
    // Get building info
    $temp_building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $temp_building_stmt->execute([$building_id]);
    $temp_building = $temp_building_stmt->fetch(PDO::FETCH_ASSOC);
    $is_atm = stripos($temp_building['name'], 'ATM') !== false;
    
    if ($is_atm) {
        $booking_year = $_POST['booking_year'] ?? date('Y');
        $booking_date = $booking_year . '-01-01';
        $start_time = '00:00:00';
        $end_time = '23:59:59';
    } else {
        $booking_date = $_POST['booking_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
    }
    
    $status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'];
    $proposal_file = $booking['proposal_file']; // Keep existing file if no new upload

    // Handle file upload if new file is provided
    if (isset($_FILES['proposal_file']) && $_FILES['proposal_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/proposals/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['proposal_file']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('proposal_') . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;

        $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($fileExtension), $allowedTypes)) {
            $error = "Tipe file tidak didukung. Harap upload PDF, Word, atau Gambar.";
        } elseif (move_uploaded_file($_FILES['proposal_file']['tmp_name'], $targetPath)) {
            // Delete old file if exists
            if (!empty($booking['proposal_file']) && file_exists('../uploads/proposals/' . $booking['proposal_file'])) {
                unlink('../uploads/proposals/' . $booking['proposal_file']);
            }
            $proposal_file = $fileName;
        } else {
            $error = "Gagal mengupload file.";
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            // Update booking
            $stmt = $pdo->prepare("UPDATE bookings SET building_id=?, booker_name=?, booker_email=?, booker_phone=?, organization=?, event_name=?, event_description=?, booking_date=?, start_time=?, end_time=?, status=?, admin_notes=?, proposal_file=? WHERE id=?");
            $stmt->execute([$building_id, $booker_name, $booker_email, $booker_phone, $organization, $event_name, $event_description, $booking_date, $start_time, $end_time, $status, $admin_notes, $proposal_file, $id]);

            // Get current building info
            $building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
            $building_stmt->execute([$building_id]);
            $building = $building_stmt->fetch(PDO::FETCH_ASSOC);

            // Process add-on items if building is not Ruang Rapat Setda, not free, and not ATM
            $total_item_price = 0;
            if (trim($building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $building['category'] !== 'gratis' && !$is_atm) {
                // Delete old items first
                $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
                
                $ordered_items = $_POST['items'] ?? [];
                foreach ($available_items as $item) {
                    if (isset($ordered_items[$item['id']]) && $ordered_items[$item['id']] > 0) {
                        $quantity = (int)$ordered_items[$item['id']];
                        $price_at_booking = $item['price_per_unit'];
                        $total_item_price += $quantity * $price_at_booking;

                        $itemStmt = $pdo->prepare("INSERT INTO booking_items (booking_id, item_id, quantity, price_at_booking) VALUES (?, ?, ?, ?)");
                        $itemStmt->execute([$id, $item['id'], $quantity, $price_at_booking]);
                    }
                }
            } else {
                // If building is free, Ruang Rapat Setda, or ATM, delete all items
                $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
                $total_item_price = 0;
            }

            // Calculate invoice amount and update
            $building_price_per_day = ($building['category'] === 'berbayar' ? $building['price'] : 0);
            
            if ($is_atm) {
                $total_building_cost = $building_price_per_day * 12;
            } else {
                $total_building_cost = $building_price_per_day; // 1 day for edit
            }
            
            $final_amount = $total_building_cost + $total_item_price;

            // Check if there's an existing invoice
            $invoice_stmt = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ?");
            $invoice_stmt->execute([$id]);
            $existing_invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);

            if ($final_amount > 0) {
                if ($existing_invoice) {
                    // Update existing invoice
                    $update_invoice_stmt = $pdo->prepare("UPDATE invoices SET amount = ? WHERE id = ?");
                    $update_invoice_stmt->execute([$final_amount, $existing_invoice['id']]);
                } else {
                    // Create new invoice if needed
                    $current_month_year = date('m-Y');
                    $counterStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_counter'");
                    $invoice_counter = $counterStmt ? (int)$counterStmt->fetchColumn() : 1;
                    $monthStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'last_invoice_month'");
                    $last_invoice_month = $monthStmt ? $monthStmt->fetchColumn() : null;

                    if ($current_month_year !== $last_invoice_month) {
                        $invoice_counter = 1;
                        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_invoice_month'")->execute([$current_month_year]);
                    }

                    $invoice_id = date('mY') . '-' . str_pad($invoice_counter, 4, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'invoice_counter'")->execute([$invoice_counter + 1]);

                    $invoiceStmt = $pdo->prepare("INSERT INTO invoices (id, booking_id, amount) VALUES (?, ?, ?)");
                    $invoiceStmt->execute([$invoice_id, $id, $final_amount]);
                }
            } else {
                // If final amount is 0, delete invoice if exists
                if ($existing_invoice) {
                    $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$existing_invoice['id']]);
                }
            }

            $pdo->commit();
            $message = "Booking berhasil diperbarui!";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT b.*, g.name as building_name, g.category as building_category, g.price as building_price FROM bookings b JOIN buildings g ON b.building_id = g.id WHERE b.id = ?");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh current items
            $current_items = [];
            $current_items_stmt = $pdo->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
            $current_items_stmt->execute([$id]);
            while ($item_row = $current_items_stmt->fetch(PDO::FETCH_ASSOC)) {
                $current_items[$item_row['item_id']] = $item_row['quantity'];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Edit Booking</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary shadow-sm btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($message): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <i class="bi bi-check-circle me-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                <!-- Main Info -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Informasi Peminjam & Acara</h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nama Lengkap</label>
                                    <input type="text" name="booker_name" value="<?= htmlspecialchars($booking['booker_name']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nomor Telepon</label>
                                    <input type="tel" name="booker_phone" value="<?= htmlspecialchars($booking['booker_phone']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Email</label>
                                    <input type="email" name="booker_email" value="<?= htmlspecialchars($booking['booker_email']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Organisasi / Instansi</label>
                                    <input type="text" name="organization" value="<?= htmlspecialchars($booking['organization']) ?>" class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Nama Acara</label>
                                <input type="text" name="event_name" value="<?= htmlspecialchars($booking['event_name']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Deskripsi Acara</label>
                                <textarea name="event_description" rows="3" class="form-control bg-light border-0 py-2 shadow-none"><?= htmlspecialchars($booking['event_description']) ?></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Ganti Surat Permohonan/Proposal (PDF)</label>
                                <input type="file" name="proposal_file" class="form-control">
                                <div class="form-text xsmall mt-1"><i>Kosongkan jika tidak ingin mengubah file.</i></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Jadwal Pelaksanaan</h5>
                            <div class="row g-3">
                                <?php 
                                $is_atm_booking = stripos($booking['building_name'], 'ATM') !== false;
                                if (!$is_atm_booking): 
                                ?>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Tanggal</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                        <input type="date" name="booking_date" value="<?= htmlspecialchars($booking['booking_date']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Jam Mulai</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-clock text-muted"></i></span>
                                        <input type="text" name="start_time" value="<?= date('H:i', strtotime($booking['start_time'])) ?>" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Jam Selesai</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-clock-history text-muted"></i></span>
                                        <input type="text" name="end_time" value="<?= date('H:i', strtotime($booking['end_time'])) ?>" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">Tahun Sewa</label>
                                    <select name="booking_year" class="form-select bg-light border-0 py-2 shadow-none" required>
                                        <?php 
                                        $current_year = date('Y');
                                        $booking_year = date('Y', strtotime($booking['booking_date']));
                                        for ($y = $current_year - 5; $y <= $current_year + 5; $y++): 
                                        ?>
                                        <option value="<?= $y ?>" <?= $y == $booking_year ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php 
                    // Show add-on items if not Ruang Rapat Setda, building is not free, and not ATM
                    $booking_building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
                    $booking_building_stmt->execute([$booking['building_id']]);
                    $booking_building = $booking_building_stmt->fetch(PDO::FETCH_ASSOC);
                    if (trim($booking_building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $booking_building['category'] !== 'gratis' && !$is_atm_booking): 
                    ?>
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Tambah Fasilitas Pendukung</h5>
                            <div class="list-group">
                                <?php foreach($available_items as $item): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light border-0 rounded-3 mb-2 px-3">
                                        <div>
                                            <label for="item_<?= $item['id'] ?>" class="fw-bold small d-block mb-0"><?= htmlspecialchars($item['name']) ?></label>
                                            <span class="text-muted xsmall">
                                                Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?> 
                                                <?php if (stripos($item['name'], 'videotron') !== false): ?>
                                                    /2 jam penayangan
                                                <?php else: ?>
                                                    /unit
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <input type="number" name="items[<?= $item['id'] ?>]" id="item_<?= $item['id'] ?>" min="0" placeholder="0" class="form-control form-control-sm text-center" style="width: 80px;" value="<?= isset($current_items[$item['id']]) ? htmlspecialchars($current_items[$item['id']]) : 0 ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Status & Aksi</h5>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Status Booking</label>
                                <select name="status" class="form-select bg-light border-0 py-2 shadow-none fw-bold">
                                    <option value="pending" class="text-warning" <?= $booking['status'] == 'pending' ? 'selected' : '' ?>>PENDING</option>
                                    <option value="approved" class="text-success" <?= $booking['status'] == 'approved' ? 'selected' : '' ?>>APPROVED</option>
                                    <option value="rejected" class="text-danger" <?= $booking['status'] == 'rejected' ? 'selected' : '' ?>>REJECTED</option>
                                    <option value="cancelled" class="text-secondary" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>CANCELLED</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Pilih Gedung</label>
                                <select name="building_id" class="form-select bg-light border-0 py-2 shadow-none">
                                    <?php foreach($buildings as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $booking['building_id'] == $b['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Catatan Admin</label>
                                <textarea name="admin_notes" rows="3" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Alasan penolakan atau catatan tambahan..."><?= htmlspecialchars($booking['admin_notes']) ?></textarea>
                            </div>

                            <?php if($booking['proposal_file']): ?>
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-bold text-secondary d-block mb-2">Proposal Acara</label>
                                <a href="../uploads/proposals/<?= htmlspecialchars($booking['proposal_file']) ?>" target="_blank" class="btn btn-sm btn-white border w-100 text-primary fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Lihat Proposal
                                </a>
                            </div>
                            <?php endif; ?>

                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm mb-2">
                                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                                </button>
                                <a href="booking_delete.php?id=<?= $booking['id'] ?>" class="btn btn-outline-danger w-100 py-2 fw-bold rounded-3 delete-trigger" data-message="Yakin ingin menghapus booking untuk acara '<?= htmlspecialchars($booking['event_name']) ?>'?">
                                    <i class="bi bi-trash me-1"></i> Hapus Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../footer.php'; ?>
<?php include 'delete_modal.php'; ?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".timepicker", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        locale: "id",
        allowInput: true
    });
});
</script>

<style>
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control, .form-select { border-radius: 0 0.5rem 0.5rem 0; }
    textarea.form-control { border-radius: 0.5rem; }
    .btn-white { background-color: #fff; }
</style>
