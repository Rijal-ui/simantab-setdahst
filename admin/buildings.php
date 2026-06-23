<?php
require_once 'auth_check.php';
require_once '../config.php';

if (strtolower($_SESSION['role'] ?? '') === 'user') {
    header("Location: booking_manual.php");
    exit;
}

// Fetch buildings
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Kelola Gedung</h1>
    <a href="building_form.php" class="btn btn-primary shadow-sm">
        <i class="bi bi-plus-lg me-1"></i> Tambah Gedung
    </a>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="px-4 py-3">Gedung</th>
                    <th class="px-4 py-3">Kategori & Harga</th>
                    <th class="px-4 py-3 text-center">Unit</th>
                    <th class="px-4 py-3 text-center">Kapasitas</th>
                    <th class="px-4 py-3">Lokasi</th>
                    <th class="px-4 py-3">Informasi</th>
                    <th class="px-4 py-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php foreach($buildings as $building): ?>
                <tr>
                    <td class="px-4 py-4">
                        <div class="d-flex align-items-center">
                            <?php if(!empty($building['image_url'])): ?>
                                <img src="../<?= htmlspecialchars($building['image_url']) ?>" alt="<?= htmlspecialchars($building['name']) ?>" class="rounded-2 me-3 object-fit-cover" style="width: 50px; height: 50px;">
                            <?php else: ?>
                                <div class="rounded-2 bg-light d-flex align-items-center justify-content-center text-muted me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-building"></i>
                                </div>
                            <?php endif; ?>
                            <span class="fw-bold"><?= htmlspecialchars($building['name']) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <?php if($building['category'] == 'berbayar'): ?>
                            <span class="badge bg-primary-subtle text-primary-emphasis px-2 py-1 xsmall fw-bold mb-1 d-inline-block">BERBAYAR</span>
                            <?php 
                            $rental_text = [
                                'per_hari' => '/hari',
                                'per_bulan' => '/bulan',
                                'per_tahun' => '/tahun'
                            ];
                            ?>
                            <div class="fw-bold text-primary small">Rp <?= number_format($building['price'], 0, ',', '.') ?> <?= $rental_text[$building['rental_type']] ?></div>
                            <span class="badge bg-light text-dark px-2 py-1 xsmall fw-bold d-inline-block mt-1">
                                <?= strtoupper(str_replace('per_', '', $building['rental_type'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success-emphasis px-2 py-1 xsmall fw-bold d-inline-block">GRATIS</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-center fw-medium">
                        <?= $building['quantity'] ?>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="small fw-medium"><i class="bi bi-people me-1"></i><?= $building['capacity'] ?></span>
                    </td>
                    <td class="px-4 py-4">
                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($building['location']) ?></div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="xsmall fw-bold text-dark mb-1">DESKRIPSI:</div>
                        <p class="xsmall text-muted mb-2 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($building['description']) ?></p>
                        <?php if(!empty($building['requirements'])): ?>
                            <div class="xsmall fw-bold text-dark mb-1">SYARAT:</div>
                            <p class="xsmall text-muted mb-0 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($building['requirements']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-end">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <a href="building_form.php?id=<?= $building['id'] ?>" class="btn btn-white border px-3" title="Edit">
                                <i class="bi bi-pencil-square text-primary"></i>
                            </a>
                            <a href="building_delete.php?id=<?= $building['id'] ?>" class="btn btn-white border px-3 delete-trigger" title="Hapus" data-message="Yakin ingin menghapus gedung '<?= htmlspecialchars($building['name']) ?>'?">
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
    .xsmall { font-size: 0.7rem; }
    .object-fit-cover { object-fit: cover; }
    .btn-white { background-color: #fff; }
    .btn-white:hover { background-color: #f8f9fa; }
    .bg-primary-subtle { background-color: #e7f1ff; }
    .text-primary-emphasis { color: #052c65; }
    .bg-success-subtle { background-color: #d1fae5; }
    .text-success-emphasis { color: #065f46; }
</style>

<?php 
include '../footer.php';
include 'delete_modal.php';
?>
