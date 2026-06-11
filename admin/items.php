<?php
require_once 'auth_check.php';
require_once '../config.php';

if (strtolower($_SESSION['role'] ?? '') === 'user') {
    header("Location: booking_manual.php");
    exit;
}

// Fetch items
$stmt = $pdo->query("SELECT * FROM items ORDER BY name");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Kelola Fasilitas Pendukung</h1>
    <a href="item_form.php" class="btn btn-primary shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Tambah Fasilitas
    </a>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="px-4 py-3">Nama Fasilitas</th>
                    <th class="px-4 py-3">Harga per Unit</th>
                    <th class="px-4 py-3">Deskripsi</th>
                    <th class="px-4 py-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php foreach($items as $item): ?>
                <tr>
                    <td class="px-4 py-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-2 bg-light d-flex align-items-center justify-content-center text-muted me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <span class="fw-bold"><?= htmlspecialchars($item['name']) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="fw-bold text-primary">Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?></div>
                    </td>
                    <td class="px-4 py-4 text-muted small">
                        <?= htmlspecialchars($item['description']) ?>
                    </td>
                    <td class="px-4 py-4 text-end">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <a href="item_form.php?id=<?= $item['id'] ?>" class="btn btn-white border px-3" title="Edit">
                                <i class="bi bi-pencil-square text-primary"></i>
                            </a>
                            <a href="item_delete.php?id=<?= $item['id'] ?>" class="btn btn-white border px-3 delete-trigger" title="Hapus" data-message="Yakin ingin menghapus item '<?= htmlspecialchars($item['name']) ?>'?">
                                <i class="bi bi-trash text-danger"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .btn-white { background-color: #fff; }
    .btn-white:hover { background-color: #f8f9fa; }
</style>

<?php 
include '../footer.php'; 
include 'delete_modal.php';
?>
