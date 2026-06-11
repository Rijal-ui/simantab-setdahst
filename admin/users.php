<?php
require_once 'auth_check.php';
require_once '../config.php';

if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch all admins
$stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Kelola Akun</h1>
    <a href="user_form.php" class="btn btn-primary shadow-sm">
        <i class="bi bi-person-plus me-1"></i> Tambah Admin
    </a>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase fw-bold">
                <tr>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Tanggal Dibuat</th>
                    <th class="px-4 py-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php foreach($admins as $admin): ?>
                <tr>
                    <td class="px-4 py-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center text-primary-emphasis me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <span class="fw-bold"><?= htmlspecialchars($admin['username']) ?></span>
                                <?php if($admin['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-primary rounded-pill xsmall ms-1">ANDA</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <?php if($admin['role'] === 'super_admin'): ?>
                            <span class="badge bg-purple-subtle text-purple-emphasis px-2 py-1 xsmall fw-bold border border-purple">SUPER ADMIN</span>
                        <?php elseif($admin['role'] === 'admin'): ?>
                            <span class="badge bg-light text-dark px-2 py-1 xsmall fw-bold border">ADMIN</span>
                        <?php else: ?>
                            <span class="badge bg-info-subtle text-info-emphasis px-2 py-1 xsmall fw-bold border border-info">USER</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-muted small">
                        <?= date('d M Y, H:i', strtotime($admin['created_at'])) ?>
                    </td>
                    <td class="px-4 py-4 text-end">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <a href="user_form.php?id=<?= $admin['id'] ?>" class="btn btn-white border px-3" title="Edit / Ganti Password">
                                <i class="bi bi-shield-lock text-primary"></i>
                            </a>
                            <?php if($admin['id'] != $_SESSION['user_id']): ?>
                                <a href="user_delete.php?id=<?= $admin['id'] ?>" class="btn btn-white border px-3 delete-trigger" title="Hapus" data-message="Yakin ingin menghapus akun '<?= htmlspecialchars($admin['username']) ?>'?">
                                    <i class="bi bi-trash text-danger"></i>
                                </a>
                            <?php endif; ?>
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
    .btn-white { background-color: #fff; }
    .btn-white:hover { background-color: #f8f9fa; }
    .bg-primary-subtle { background-color: #e7f1ff; }
    .text-primary-emphasis { color: #052c65; }
    .bg-purple-subtle { background-color: #f3e8ff; }
    .text-purple-emphasis { color: #581c87; }
    .border-purple { border-color: #d8b4fe !important; }
    .bg-info-subtle { background-color: #cff4fc; }
    .text-info-emphasis { color: #055160; }
</style>

<?php 
include '../footer.php'; 
include 'delete_modal.php';
?>
