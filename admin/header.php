<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SI MANTAB BMD</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
            position: fixed;
            top: 56px; /* tinggi navbar */
            left: 0;
            bottom: 0;
            height: calc(100vh - 56px);
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
        }
        /* Adjust width and main content for fixed sidebar */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar {
                width: 25%; /* col-md-3 width */
            }
            main {
                margin-left: 25%; /* col-md-3 width */
            }
        }
        @media (min-width: 992px) {
            .sidebar {
                width: 16.6666667%; /* col-lg-2 width */
            }
            main {
                margin-left: 16.6666667%; /* col-lg-2 width */
            }
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
    <nav class="navbar navbar-expand-md navbar-light border-bottom sticky-top" style="background-color: rgba(255,255,255,0.8); backdrop-filter: blur(10px);">
        <div class="container-fluid px-4">
            <!-- Single Toggler for Sidebar (mobile only) -->
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
                <img src="../assets/logo-app.png" alt="SI MANTAB BMD" height="30" class="d-none d-sm-inline">
                <span class="badge bg-primary ms-2 small" style="font-size: 0.6rem;">ADMIN</span>
            </a>
        </div>
    </nav>

    <!-- Offcanvas Sidebar for Mobile -->
    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold">Menu Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column h-100">
            <ul class="nav flex-column mb-auto" id="sidebarMenuLinks">
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
            
            <!-- User Menu at Bottom of Sidebar -->
            <div class="border-top pt-3 mt-3">
                <ul class="nav flex-column" id="sidebarBottomLinks">
                    <li class="nav-item">
                        <a class="nav-link px-3" href="../index.php" target="_blank">
                            <i class="bi bi-globe me-2"></i> Lihat Website
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 text-danger" href="../logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // Close sidebar when a menu item is clicked
        document.addEventListener('DOMContentLoaded', function() {
            const offcanvasElement = document.getElementById('sidebarOffcanvas');
            if (offcanvasElement) {
                const sidebar = new bootstrap.Offcanvas(offcanvasElement);
                
                // Listen for clicks on all sidebar links
                document.querySelectorAll('#sidebarMenuLinks .nav-link, #sidebarBottomLinks .nav-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        // If link is not target="_blank", close sidebar and navigate
                        if (this.getAttribute('target') !== '_blank') {
                            e.preventDefault(); // Prevent immediate navigation
                            const href = this.getAttribute('href');
                            
                            // Close sidebar first
                            sidebar.hide();
                            
                            // Navigate after sidebar is closed
                            offcanvasElement.addEventListener('hidden.bs.offcanvas', function() {
                                window.location.href = href;
                            }, { once: true });
                        }
                    });
                });
            }
        });
    </script>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar for Desktop -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-none d-md-block sidebar px-3 pt-4">
                <div class="d-flex flex-column h-100">
                    <ul class="nav flex-column mb-auto">
                        <?php 
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
                    
                    <!-- User Menu at Bottom of Desktop Sidebar -->
                    <div class="border-top pt-3 mt-3">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link px-3" href="../index.php" target="_blank">
                                    <i class="bi bi-globe me-2"></i> Lihat Website
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link px-3 text-danger" href="../logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
