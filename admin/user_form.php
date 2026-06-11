<?php
require_once 'auth_check.php';
require_once '../config.php';

$current_role = strtolower($_SESSION['role'] ?? '');
// Only super_admin can create/edit users
if ($current_role !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'] ?? null;
$user = null;
$error = '';
$message = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: users.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'admin';

    if (empty($username)) {
        $error = "Username tidak boleh kosong.";
    } elseif ($id === null && empty($password)) {
        $error = "Password wajib diisi untuk akun baru.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        try {
            if ($id) {
                // Update existing user
                if (!empty($password)) {
                    // Update username, password, and role
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed_password, $role, $id]);
                } else {
                    // Update only username and role
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $id]);
                }
                
                // If editing self, update session
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                }
                
                $message = "Akun berhasil diperbarui!";
                // Refresh local user data
                $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Insert new user
                // Check if username already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $checkStmt->execute([$username]);
                if ($checkStmt->fetchColumn() > 0) {
                    $error = "Username sudah digunakan, silakan pilih yang lain.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role]);
                    header("Location: users.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Kesalahan database: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold"><?= $id ? 'Edit Akun Admin' : 'Tambah Admin Baru' ?></h1>
    <a href="users.php" class="btn btn-outline-secondary shadow-sm btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
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

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan username">
                        </div>
                        <div class="form-text xsmall">Gunakan username yang unik dan mudah diingat.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Role / Peran</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-shield-check text-muted"></i></span>
                            <select name="role" required class="form-select bg-light border-0 py-2 shadow-none">
                                <option value="user" <?= ($user['role'] ?? '') == 'user' ? 'selected' : '' ?>>User (Hanya Input Jadwal)</option>
                                <option value="user_khusus" <?= ($user['role'] ?? '') == 'user_khusus' ? 'selected' : '' ?>>User (Khusus)</option>
                                <option value="admin" <?= ($user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin (Kelola Data & Dashboard)</option>
                                <option value="super_admin" <?= ($user['role'] ?? '') == 'super_admin' ? 'selected' : '' ?>>Super Admin (Akses Penuh)</option>
                            </select>
                        </div>
                        <div class="form-text xsmall">User (Khusus) memiliki akses Input Jadwal, Laporan Penggunaan, dan Laporan Pendapatan.</div>
                    </div>

                    <hr class="my-4 border-light">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">
                            <?= $id ? 'Ganti Password' : 'Password' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-key text-muted"></i></span>
                            <input type="password" name="password" <?= $id ? '' : 'required' ?> class="form-control bg-light border-0 py-2 shadow-none" placeholder="••••••••">
                        </div>
                        <?php if($id): ?>
                            <div class="form-text xsmall text-info"><i class="bi bi-info-circle me-1"></i> Kosongkan jika tidak ingin mengubah password.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-5">
                        <label class="form-label small fw-bold text-secondary">Konfirmasi Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-key-fill text-muted"></i></span>
                            <input type="password" name="confirm_password" <?= $id ? '' : 'required' ?> class="form-control bg-light border-0 py-2 shadow-none" placeholder="••••••••">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm">
                            <i class="bi bi-person-check me-1"></i> <?= $id ? 'Simpan Perubahan' : 'Buat Akun Admin Sekarang' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control, .form-select { border-radius: 0 0.5rem 0.5rem 0; }
    .xsmall { font-size: 0.75rem; }
</style>

<?php include '../footer.php'; ?>
