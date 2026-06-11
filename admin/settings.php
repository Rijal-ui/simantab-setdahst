<?php
require_once 'auth_check.php';
require_once '../config.php';

if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_invoice'])) {
        try {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = '1' WHERE setting_key = 'invoice_counter'");
            $stmt->execute();
            // Also clear all existing invoices and booking items for a clean start
            $pdo->query("TRUNCATE TABLE booking_items");
            $pdo->query("TRUNCATE TABLE invoices");
            $message = "Nomor urut invoice telah berhasil direset ke 1, dan semua data invoice lama telah dihapus.";
        } catch (PDOException $e) {
            $error = "Gagal mereset nomor invoice: " . $e->getMessage();
        }
    }
}

// Fetch current counter
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_counter'");
$current_counter = $stmt->fetchColumn();

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Pengaturan</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Reset Invoice & Data Transaksi</h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-3">
                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center text-primary-emphasis me-3" style="width: 50px; height: 50px;">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Nomor Invoice Berikutnya</div>
                        <?php
                            $current_month_year = date('m-Y');
                            $counterStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_counter'");
                            $invoice_counter = $counterStmt ? (int)$counterStmt->fetchColumn() : 1;
                            $monthStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'last_invoice_month'");
                            $last_invoice_month = $monthStmt ? $monthStmt->fetchColumn() : null;

                            if ($current_month_year !== $last_invoice_month) {
                                $invoice_counter = 1;
                            }
                            $next_invoice_id = date('mY') . '-' . str_pad($invoice_counter, 4, '0', STR_PAD_LEFT);
                        ?>
                        <div class="h4 fw-bold mb-0 text-primary"><?= htmlspecialchars($next_invoice_id) ?></div>
                        <div class="small text-muted">Nomor urut akan direset setiap awal bulan.</div>
                    </div>
                </div>
                
                <div class="border-top pt-4">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-octagon me-1"></i> Area Berbahaya</h6>
                    <p class="text-muted small mb-4">
                        Tindakan ini akan mengembalikan nomor urut invoice ke <strong>1</strong>. 
                        <br>
                        <span class="text-danger fw-bold">PERINGATAN:</span> Ini juga akan menghapus <strong>semua data invoice dan item booking</strong> yang sudah ada secara permanen dari database. Lakukan hanya jika Anda benar-benar yakin ingin memulai dari awal.
                    </p>

                    <form action="" method="POST" class="requires-confirm" data-message="PERINGATAN KERAS: Anda akan menghapus semua data invoice yang ada dan mereset counter ke 1. Yakin ingin melanjutkan?">
    
                        <input type="hidden" name="reset_invoice" value="1">
                        
                        <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                            <i class="bi bi-trash3 me-1"></i> Reset Nomor Invoice & Hapus Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Informasi Sistem</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Versi PHP</div>
                            <div class="fw-bold"><?= phpversion() ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Server Time</div>
                            <div class="fw-bold"><?= date('d M Y, H:i:s') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-primary-subtle { background-color: #e7f1ff; }
    .text-primary-emphasis { color: #052c65; }
</style>

<?php 
include '../footer.php';
include 'delete_modal.php';
?>