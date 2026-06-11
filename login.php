<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: admin/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower($user['role']); // Store role in lowercase
        $_SESSION['last_activity'] = time();
        
        // Redirect based on role
        if (in_array($_SESSION['role'], ['user', 'user_khusus'])) {
            header("Location: admin/booking_manual.php");
        } else {
            header("Location: admin/dashboard.php");
        }
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-body p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <img src="assets/logo-app.png" alt="Logo" class="mb-2" style="height: 100px;">
                        <h4 class="fw-bold text-dark">Login</h4>
                    </div>
                    
                    <?php if (isset($_GET['message']) && $_GET['message'] === 'timeout'): ?>
                        <div class="alert alert-warning border-0 small py-2 mb-4">
                            <i class="bi bi-clock-history me-2"></i> Sesi Anda telah berakhir. Silakan login kembali.
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 small py-2 mb-4">
                            <i class="bi bi-exclamation-circle me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" name="username" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan username">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" required class="form-control bg-light border-0 py-2 shadow-none" placeholder="Masukkan password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm mt-2">
                            Masuk Ke Dashboard
                        </button>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="text-decoration-none small text-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-4 text-muted xsmall">
                &copy; <?= date('Y') ?> SI MANTAB BMD
            </div>
        </div>
    </div>
</div>

<style>
    .min-vh-75 { min-height: 75vh; }
    .xsmall { font-size: 0.75rem; }
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control { border-radius: 0 0.5rem 0.5rem 0; }
</style>

<?php include 'footer.php'; ?>
