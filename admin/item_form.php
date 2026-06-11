<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;
$item = null;
$error = '';
$message = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price_per_unit = $_POST['price_per_unit'];
    $description = $_POST['description'];

    if (empty($name) || !is_numeric($price_per_unit)) {
        $error = "Nama item dan harga harus diisi dengan benar.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE items SET name=?, price_per_unit=?, description=? WHERE id=?");
                $stmt->execute([$name, $price_per_unit, $description, $id]);
                $message = "Item berhasil diperbarui!";
                $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("INSERT INTO items (name, price_per_unit, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $price_per_unit, $description]);
                header("Location: items.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan database: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold"><?= $id ? 'Edit Fasilitas Pendukung' : 'Tambah Fasilitas Pendukung' ?></h1>
    <a href="items.php" class="btn btn-outline-secondary shadow-sm btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4 p-sm-5">
                <?php if ($message): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4">
                        <i class="bi bi-check-circle me-2"></i> Fasilitas berhasil diperbarui!
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Nama Fasilitas Pendukung</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-box-seam text-muted"></i></span>
                            <input type="text" name="name" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan nama fasilitas">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Harga per Unit (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-tag text-muted"></i></span>
                            <input type="number" name="price_per_unit" value="<?= htmlspecialchars($item['price_per_unit'] ?? '0') ?>" step="1" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Contoh: 50000">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Deskripsi</label>
                        <textarea name="description" rows="4" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Berikan keterangan singkat mengenai fasilitas ini"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm">
                            <i class="bi bi-save me-1"></i> <?= $id ? 'Simpan Perubahan' : 'Tambah Fasilitas Sekarang' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control { border-radius: 0 0.5rem 0.5rem 0; }
    textarea.form-control { border-radius: 0.5rem; }
</style>

<?php include '../footer.php'; ?>
