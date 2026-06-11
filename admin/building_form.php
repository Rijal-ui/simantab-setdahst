<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;
$building = null;
$error = '';
$message = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $stmt->execute([$id]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $capacity = $_POST['capacity'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $requirements = $_POST['requirements'];
    $category = $_POST['category'];
    $price = $_POST['price'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;

    $image_url = $building['image_url'] ?? null;

    // Handle image upload to a folder
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/buildings/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $image_info = getimagesize($_FILES['image_file']['tmp_name']);
        if (!$image_info) {
            $error = "File yang diupload bukan gambar yang valid.";
        } else {
            // Delete old image if a new one is uploaded
            if ($id && !empty($image_url) && file_exists('../' . $image_url)) {
                unlink('../' . $image_url);
            }

            $fileExtension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('building_') . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                $image_url = 'uploads/buildings/' . $fileName; // Store relative path
            } else {
                $error = "Gagal mengupload gambar.";
            }
        }
    }

    if ($category === 'gratis') {
        $price = 0;
    }

    if (!$error) {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE buildings SET name=?, capacity=?, location=?, description=?, requirements=?, category=?, price=?, quantity=?, image_url=? WHERE id=?");
                $stmt->execute([$name, $capacity, $location, $description, $requirements, $category, $price, $quantity, $image_url, $id]);
                $message = "Aset berhasil diperbarui!";
                $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
                $stmt->execute([$id]);
                $building = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("INSERT INTO buildings (name, capacity, location, description, requirements, category, price, quantity, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $capacity, $location, $description, $requirements, $category, $price, $quantity, $image_url]);
                header("Location: buildings.php");
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
    <h1 class="h2 fw-bold"><?= $id ? 'Edit Gedung' : 'Tambah Gedung' ?></h1>
    <a href="buildings.php" class="btn btn-outline-secondary shadow-sm btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4 p-sm-5">
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
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Nama Gedung</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-building text-muted"></i></span>
                            <input type="text" name="name" value="<?= htmlspecialchars($building['name'] ?? '') ?>" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan nama gedung">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Kapasitas (Orang)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-people text-muted"></i></span>
                                <input type="number" name="capacity" value="<?= htmlspecialchars($building['capacity'] ?? '') ?>" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Contoh: 100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Lokasi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-geo-alt text-muted"></i></span>
                                <input type="text" name="location" value="<?= htmlspecialchars($building['location'] ?? '') ?>" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan lokasi gedung">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Gambar Aset</label>
                        <input type="file" name="image_file" accept="image/jpeg,image/png,image/gif" class="form-control bg-light border-0 py-2 shadow-none">
                        <?php if ($id && !empty($building['image_url'])): ?>
                            <div class="mt-3 p-3 bg-light rounded-3 border">
                                <p class="xsmall fw-bold text-muted text-uppercase mb-2">Gambar Saat Ini:</p>
                                <img src="../<?= htmlspecialchars($building['image_url']) ?>" alt="Gambar saat ini" class="img-fluid rounded-2 shadow-sm border" style="max-height: 150px;">
                                <div class="form-text xsmall mt-2 text-info"><i class="bi bi-info-circle me-1"></i> Upload gambar baru untuk menggantinya.</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Deskripsi</label>
                        <textarea name="description" rows="3" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Jelaskan mengenai gedung ini"><?= htmlspecialchars($building['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Syarat & Ketentuan Booking</label>
                        <textarea name="requirements" rows="4" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Tuliskan syarat dan ketentuan peminjaman..."><?= htmlspecialchars($building['requirements'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Kategori</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-list-stars text-muted"></i></span>
                                <select name="category" id="category" class="form-select bg-light border-0 py-2 shadow-none">
                                    <option value="gratis" <?= ($building['category'] ?? 'gratis') == 'gratis' ? 'selected' : '' ?>>Gratis</option>
                                    <option value="berbayar" <?= ($building['category'] ?? 'gratis') == 'berbayar' ? 'selected' : '' ?>>Berbayar</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="price-field-wrapper">
                            <div id="price-field" class="<?= ($building['category'] ?? 'gratis') == 'gratis' ? 'd-none' : '' ?>">
                                <label class="form-label small fw-bold text-secondary">Harga Sewa (Rp / hari)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-tag text-muted"></i></span>
                                    <input type="number" name="price" value="<?= htmlspecialchars($building['price'] ?? 0) ?>" step="1" class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label small fw-bold text-secondary">Jumlah Unit Tersedia</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-stack text-muted"></i></span>
                            <input type="number" name="quantity" value="<?= htmlspecialchars($building['quantity'] ?? 1) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                        </div>
                        <div class="form-text xsmall">Tentukan berapa kali gedung ini bisa dibooking pada waktu yang sama.</div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm">
                            <i class="bi bi-save me-1"></i> <?= $id ? 'Update Aset Gedung' : 'Tambah Aset Gedung' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category');
        const priceField = document.getElementById('price-field');

        categorySelect.addEventListener('change', function() {
            if (this.value === 'berbayar') {
                priceField.classList.remove('d-none');
            } else {
                priceField.classList.add('d-none');
            }
        });
    });
</script>

<style>
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control, .form-select { border-radius: 0 0.5rem 0.5rem 0; }
    textarea.form-control { border-radius: 0.5rem; }
    .xsmall { font-size: 0.75rem; }
</style>

<?php include '../footer.php'; ?>
