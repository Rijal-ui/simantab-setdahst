<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SI MANTAB BMD</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/favicon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Flatpickr (Timepicker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
        }
        html, body {
            height: 100%;
            margin: 0;
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-image: url('assets/simantab.jpg');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar { background-color: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); }
        .btn-primary { padding: 0.6rem 1.5rem; font-weight: 500; border-radius: 8px; }
        .card { border-radius: 15px; transition: transform 0.2s; background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); }
        .xsmall { font-size: 0.75rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light sticky-top border-bottom py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php">
          <!--  <img src="assets/hst.png" alt="Logo" height="40">-->
            <img src="assets/logo-app.png" alt="SI MANTAB BMD" height="60">
        </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link px-3" href="index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="index.php#gedung">Daftar Gedung</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="schedule.php">Lihat Jadwal</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="usage_list.php">Daftar Pemakaian</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1">
