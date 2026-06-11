<?php
require_once 'config.php';

// Temporary fix for roles
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'user', 'user_khusus') DEFAULT 'user'");
    $pdo->exec("UPDATE users SET role = 'user' WHERE username = 'prokom'");
} catch(Exception $e) {}

// Fetch buildings
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<!-- Hero Section -->
<section class="py-5 border-bottom">
    <div class="container py-5 text-center">
        <div class="mb-4">
            <img src="assets/logo-app.png" alt="SI MANTAB BMD" class="img-fluid" style="max-height: 150px;">
        </div>
        <p class="lead text-secondary mb-5 mx-auto" style="max-width: 700px;">
            Mudah, Cepat, dan Transparan 
            <br>Booking Gedung untuk Acara Anda Dalam Hitungan Menit</br>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <span class="badge rounded-pill bg-white text-dark border px-3 py-2 shadow-sm">
                <i class="bi bi-check-circle-fill text-success me-1"></i> Tanpa Login
            </span>
            <span class="badge rounded-pill bg-white text-dark border px-3 py-2 shadow-sm">
                <i class="bi bi-calendar-event text-primary me-1"></i> Jadwal Real-time
            </span>
            <span class="badge rounded-pill bg-white text-dark border px-3 py-2 shadow-sm">
                <i class="bi bi-building text-primary me-1"></i> Pilihan Gedung
            </span>
        </div>
    </div>
</section>

<!-- Buildings Section -->
<section id="gedung" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Pilih Gedung</h2>
            <p class="text-muted">Tersedia Berbagai Pilihan Gedung Sesuai Kebutuhan Acara Anda</p>
        </div>

        <div class="row g-4">
            <?php foreach($buildings as $building): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition-all">
                    <div class="position-relative" style="height: 200px; overflow: hidden;">
                        <?php if(!empty($building['image_url'])): ?>
                        <img src="<?= htmlspecialchars($building['image_url']) ?>" alt="<?= htmlspecialchars($building['name']) ?>" class="card-img-top h-100 w-100 object-fit-cover">
                        <?php else: ?>
                        <div class="h-100 w-100 bg-light d-flex align-items-center justify-center text-muted border-bottom">
                            <i class="bi bi-building display-4"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($building['name']) ?></h5>
                        
                        <?php if (stripos($building['name'], 'Auditorium') !== false || stripos($building['name'], 'Pendopo') !== false): ?>
                            <div class="badge bg-warning-subtle text-warning-emphasis mb-3 shadow-sm border border-warning-subtle">
                                <i class="bi bi-info-circle me-1"></i> Khusus acara kedinasan
                            </div>
                        <?php elseif (stripos($building['name'], 'Balai Rakyat') !== false): ?>
                            <div class="badge bg-danger-subtle text-danger-emphasis mb-3 shadow-sm border border-danger-subtle">
                                <i class="bi bi-exclamation-triangle me-1"></i> Tidak untuk acara pernikahan
                            </div>
                        <?php endif; ?>

                        <p class="card-text text-muted small mb-4 text-truncate-2"><?= htmlspecialchars($building['description']) ?></p>
                        
                        <div class="d-flex align-items-center gap-3 text-muted small mb-4">
                            <div class="d-flex align-items-center gap-1">
                                <i class="bi bi-people"></i>
                                <?= $building['capacity'] ?> Orang
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <i class="bi bi-geo-alt"></i>
                                <?= htmlspecialchars($building['location']) ?>
                            </div>
                        </div>

                        <a href="booking.php?building_id=<?= $building['id'] ?>" class="btn btn-primary w-100 py-2 rounded-3">
                            Pesan Sekarang
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important;
    }
    .transition-all {
        transition: all 0.3s ease;
    }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .object-fit-cover {
        object-fit: cover;
    }
</style>

<?php include 'footer.php'; ?>
