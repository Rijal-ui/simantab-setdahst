<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SI MANTAB BMD</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --bs-primary: #3b82f6;
            --bs-primary-rgb: 59, 130, 246;
        }
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        .navbar-brand img {
            height: 40px;
        }
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            background-image: url('../assets/simantab.jpg');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-right: 1px solid #dee2e6;
        }
        .nav-link {
            color: #4b5563;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        .nav-link:hover {
            background-color: #f3f4f6;
            color: var(--bs-primary);
        }
        .nav-link.active {
            background-color: #eff6ff;
            color: var(--bs-primary);
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: none;
            border-radius: 0.75rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-md navbar-light border-bottom sticky-top" style="background-color: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px);">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
                <img src="../assets/logo-app.png" alt="SI MANTAB BMD" height="30" class="d-none d-sm-inline">
                <span class="badge bg-primary ms-2 small" style="font-size: 0.6rem;">ADMIN</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link px-3" href="../index.php" target="_blank">
                            <i class="bi bi-globe me-1"></i> Lihat Website
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-3" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><a class="dropdown-item py-2 text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse px-3 pt-4">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <?php 
                        $current_role = strtolower($_SESSION['role'] ?? '');
                        if (!in_array($current_role, ['user', 'user_khusus'])): 
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'buildings.php' || basename($_SERVER['PHP_SELF']) == 'building_form.php' ? 'active' : '' ?>" href="buildings.php">
                                <i class="bi bi-building me-2"></i> Kelola Gedung
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'items.php' || basename($_SERVER['PHP_SELF']) == 'item_form.php' ? 'active' : '' ?>" href="items.php">
                                <i class="bi bi-box-seam me-2"></i> Kelola Fasilitas Pendukung
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item mt-3 mb-2 px-3">
                            <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">
                                <?php
                                if ($current_role === 'super_admin') echo 'Super Admin';
                                elseif ($current_role === 'admin') echo 'Admin';
                                elseif ($current_role === 'user_khusus') echo 'User Khusus';
                                else echo 'User';
                                ?>
                            </span>
                        </li>

                        <?php if ($current_role === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'user_form.php' ? 'active' : '' ?>" href="users.php">
                                <i class="bi bi-people me-2"></i> Kelola Akun
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($current_role === 'super_admin' || $current_role === 'user' || $current_role === 'user_khusus'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'booking_manual.php' ? 'active' : '' ?>" href="booking_manual.php">
                                <i class="bi bi-calendar-plus me-2"></i> Input Jadwal Manual
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'user' || $current_role === 'user_khusus'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i> Laporan Penggunaan Gedung
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'user_khusus'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'revenue.php' ? 'active' : '' ?>" href="revenue.php">
                                <i class="bi bi-cash-stack me-2"></i> Laporan Pendapatan
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($current_role === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Pengaturan
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
