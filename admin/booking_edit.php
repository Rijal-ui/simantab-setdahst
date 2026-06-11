<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;
$message = '';
$error = '';
$booking = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    header("Location: dashboard.php");
    exit;
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
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'];

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET building_id=?, booker_name=?, booker_email=?, booker_phone=?, organization=?, event_name=?, event_description=?, booking_date=?, start_time=?, end_time=?, status=?, admin_notes=? WHERE id=?");
        $stmt->execute([$building_id, $booker_name, $booker_email, $booker_phone, $organization, $event_name, $event_description, $booking_date, $start_time, $end_time, $status, $admin_notes, $id]);
        $message = "Booking berhasil diperbarui!";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
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
    <div class="col-lg-10">
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

        <form method="POST">
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
                            
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-secondary">Deskripsi Acara</label>
                                <textarea name="event_description" rows="4" class="form-control bg-light border-0 py-2 shadow-none"><?= htmlspecialchars($booking['event_description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Jadwal Pelaksanaan</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Tanggal</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                        <input type="date" name="booking_date" value="<?= $booking['booking_date'] ?>" required class="form-control bg-light border-0 py-2 shadow-none">
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
                            </div>
                        </div>
                    </div>
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

<?php 
include '../footer.php';
include 'delete_modal.php';
?>
