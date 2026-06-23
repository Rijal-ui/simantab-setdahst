<?php
require_once 'auth_check.php';
require_once '../config.php';

$id = $_GET['id'] ?? null;
$message = '';
$error = '';
$booking = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT b.*, g.name as building_name, g.category as building_category, g.price as building_price, g.rental_type as rental_type FROM bookings b JOIN buildings g ON b.building_id = g.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking) {
    header("Location: dashboard.php");
    exit;
}

// Find all other bookings in the same group
$group_stmt = $pdo->prepare("
    SELECT * FROM bookings 
    WHERE event_name = ? 
    AND booker_name = ? 
    AND building_id = ?
    ORDER BY booking_date ASC
");
$group_stmt->execute([$booking['event_name'], $booking['booker_name'], $booking['building_id']]);
$group_bookings = $group_stmt->fetchAll(PDO::FETCH_ASSOC);

$is_multi_day = count($group_bookings) > 1;
$group_dates = [];
foreach ($group_bookings as $gb) {
    $group_dates[] = $gb['booking_date'];
}

$group_start_date = $group_dates[0];
$group_end_date = $group_dates[count($group_dates)-1];

// Fetch all available add-on items
$itemsStmt = $pdo->query("SELECT * FROM items ORDER BY name");
$available_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current booking items
$current_items = [];
$current_items_stmt = $pdo->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
$current_items_stmt->execute([$id]);
while ($item_row = $current_items_stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_items[$item_row['item_id']] = $item_row['quantity'];
}

// Fetch buildings for dropdown
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = $_POST['building_id'];
    $booker_name = $_POST['booker_name'];
    $booker_email = $_POST['booker_email'];
    $booker_phone = $_POST['booker_phone'];
    $organization = $_POST['organization'];
    $event_name = $_POST['event_name'];
    $event_description = $_POST['event_description'];
    
    // Get building info
    $temp_building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $temp_building_stmt->execute([$building_id]);
    $temp_building = $temp_building_stmt->fetch(PDO::FETCH_ASSOC);
    $rental_type = $temp_building['rental_type'] ?? 'per_hari';
    
    // Handle date for multi-day booking
    $is_multi_day_post = isset($_POST['is_multi_day']) && $_POST['is_multi_day'] === '1';
    
    if ($rental_type === 'per_tahun') {
        $booking_year = $_POST['booking_year'] ?? date('Y');
        $booking_date = $booking_year . '-01-01';
        $start_time = '00:00:00';
        $end_time = '23:59:59';
    } elseif ($is_multi_day_post) {
        $date_from = $_POST['date_from'];
        $date_to = $_POST['date_to'];
        $start_time = '00:00:00';
        $end_time = '23:59:59';
        $booking_date = $date_from;
    } else {
        $booking_date = $_POST['booking_date'];
        if ($rental_type === 'per_bulan') {
            $start_time = '00:00:00';
            $end_time = '23:59:59';
        } else {
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
        }
    }
    
    $status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'];
    $proposal_file = $booking['proposal_file']; // Keep existing file if no new upload

    // Handle file upload if new file is provided
    if (isset($_FILES['proposal_file']) && $_FILES['proposal_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/proposals/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['proposal_file']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('proposal_') . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;

        $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($fileExtension), $allowedTypes)) {
            $error = "Tipe file tidak didukung. Harap upload PDF, Word, atau Gambar.";
        } elseif (move_uploaded_file($_FILES['proposal_file']['tmp_name'], $targetPath)) {
            // Delete old file if exists
            if (!empty($booking['proposal_file']) && file_exists('../uploads/proposals/' . $booking['proposal_file'])) {
                unlink('../uploads/proposals/' . $booking['proposal_file']);
            }
            $proposal_file = $fileName;
        } else {
            $error = "Gagal mengupload file.";
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            if ($is_multi_day_post) {
                // For multi-day booking: delete existing and create new range
                // First delete all old booking items
                foreach ($group_bookings as $gb) {
                    $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$gb['id']]);
                }
                
                // Delete old bookings
                $pdo->prepare("DELETE FROM bookings WHERE event_name = ? AND booker_name = ? AND building_id = ?")
                    ->execute([$booking['event_name'], $booking['booker_name'], $booking['building_id']]);
                
                // Create new range of bookings
                $start = new DateTime($date_from);
                $end = new DateTime($date_to);
                $end->modify('+1 day');
                
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end);
                
                $new_booking_ids = [];
                $primary_booking_id = null;
                
                foreach ($period as $dt) {
                    $current_date = $dt->format('Y-m-d');
                    
                    $insertStmt = $pdo->prepare("INSERT INTO bookings (building_id, booker_name, booker_email, booker_phone, organization, event_name, event_description, booking_date, start_time, end_time, status, admin_notes, proposal_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertStmt->execute([$building_id, $booker_name, $booker_email, $booker_phone, $organization, $event_name, $event_description, $current_date, $start_time, $end_time, $status, $admin_notes, $proposal_file]);
                    
                    $new_bid = $pdo->lastInsertId();
                    $new_booking_ids[] = $new_bid;
                    if ($primary_booking_id === null) {
                        $primary_booking_id = $new_bid;
                    }
                }
                
                // Update main booking id for redirect
                $id = $primary_booking_id;
            } else {
                // For single-day booking: update main booking and convert multi-day to single if needed
                if ($is_multi_day && count($group_bookings) > 1) {
                    // Delete other bookings in group except this one
                    foreach ($group_bookings as $gb) {
                        if ($gb['id'] != $id) {
                            $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$gb['id']]);
                            $pdo->prepare("DELETE FROM invoices WHERE booking_id = ?")->execute([$gb['id']]);
                            $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$gb['id']]);
                        }
                    }
                }
                
                // Update the main booking
                $stmt = $pdo->prepare("UPDATE bookings SET building_id=?, booker_name=?, booker_email=?, booker_phone=?, organization=?, event_name=?, event_description=?, booking_date=?, start_time=?, end_time=?, status=?, admin_notes=?, proposal_file=? WHERE id=?");
                $stmt->execute([$building_id, $booker_name, $booker_email, $booker_phone, $organization, $event_name, $event_description, $booking_date, $start_time, $end_time, $status, $admin_notes, $proposal_file, $id]);
            }

            // Get current building info
            $building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
            $building_stmt->execute([$building_id]);
            $building = $building_stmt->fetch(PDO::FETCH_ASSOC);

            // Process add-on items if building is not Ruang Rapat Setda, not free, and is per_hari
            $total_item_price = 0;
            if (trim($building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $building['category'] !== 'gratis' && $rental_type === 'per_hari') {
                // Delete old items first
                if ($is_multi_day_post) {
                    foreach ($new_booking_ids as $bid) {
                        $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$bid]);
                    }
                } else {
                    $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
                }
                
                $ordered_items = $_POST['items'] ?? [];
                foreach ($available_items as $item) {
                    if (isset($ordered_items[$item['id']]) && $ordered_items[$item['id']] > 0) {
                        $quantity = (int)$ordered_items[$item['id']];
                        $price_at_booking = $item['price_per_unit'];
                        $total_item_price += $quantity * $price_at_booking;

                        // Insert for all bookings in group
                        $booking_ids_to_process = $is_multi_day_post ? $new_booking_ids : [$id];
                        foreach ($booking_ids_to_process as $bid) {
                            $itemStmt = $pdo->prepare("INSERT INTO booking_items (booking_id, item_id, quantity, price_at_booking) VALUES (?, ?, ?, ?)");
                            $itemStmt->execute([$bid, $item['id'], $quantity, $price_at_booking]);
                        }
                    }
                }
            } else {
                // If building is free, Ruang Rapat Setda, or ATM, delete all items
                $booking_ids_to_process = $is_multi_day_post ? $new_booking_ids : [$id];
                foreach ($booking_ids_to_process as $bid) {
                    $pdo->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$bid]);
                }
                $total_item_price = 0;
            }

            // Calculate invoice amount
            $building_price = ($building['category'] === 'berbayar' ? $building['price'] : 0);
            
            if ($rental_type === 'per_hari') {
                $num_days = $is_multi_day_post ? count($new_booking_ids) : 1;
                $total_building_cost = $building_price * $num_days;
            } elseif ($rental_type === 'per_bulan') {
                if ($is_multi_day_post) {
                    $start_date = new DateTime($date_from);
                    $end_date = new DateTime($date_to);
                    $interval = $start_date->diff($end_date);
                    $num_months = $interval->m + ($interval->y * 12);
                    if ($interval->d > 0) $num_months += 1;
                } else {
                    $num_months = 1;
                }
                $total_building_cost = $building_price * $num_months;
            } elseif ($rental_type === 'per_tahun') {
                $total_building_cost = $building_price;
            } else {
                $total_building_cost = $building_price;
            }
            
            $final_amount = $total_building_cost + $total_item_price;

            // Find existing invoice
            $existing_invoice = null;
            if ($is_multi_day) {
                foreach ($group_bookings as $gb) {
                    $invoice_stmt = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ?");
                    $invoice_stmt->execute([$gb['id']]);
                    $inv = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($inv) {
                        $existing_invoice = $inv;
                        break;
                    }
                }
            } else {
                $invoice_stmt = $pdo->prepare("SELECT * FROM invoices WHERE booking_id = ?");
                $invoice_stmt->execute([$id]);
                $existing_invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($final_amount > 0) {
                // Determine which booking to attach invoice to
                $invoice_booking_id = $is_multi_day_post ? $primary_booking_id : $id;
                
                if ($existing_invoice) {
                    // Update existing invoice
                    $update_invoice_stmt = $pdo->prepare("UPDATE invoices SET amount = ?, booking_id = ? WHERE id = ?");
                    $update_invoice_stmt->execute([$final_amount, $invoice_booking_id, $existing_invoice['id']]);
                } else {
                    // Create new invoice if needed
                    $current_month_year = date('m-Y');
                    $counterStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_counter'");
                    $invoice_counter = $counterStmt ? (int)$counterStmt->fetchColumn() : 1;
                    $monthStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'last_invoice_month'");
                    $last_invoice_month = $monthStmt ? $monthStmt->fetchColumn() : null;

                    if ($current_month_year !== $last_invoice_month) {
                        $invoice_counter = 1;
                        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_invoice_month'")->execute([$current_month_year]);
                    }

                    $invoice_id = date('mY') . '-' . str_pad($invoice_counter, 4, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'invoice_counter'")->execute([$invoice_counter + 1]);

                    $invoiceStmt = $pdo->prepare("INSERT INTO invoices (id, booking_id, amount) VALUES (?, ?, ?)");
                    $invoiceStmt->execute([$invoice_id, $invoice_booking_id, $final_amount]);
                }
            } else {
                // If final amount is 0, delete invoice if exists
                if ($existing_invoice) {
                    $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$existing_invoice['id']]);
                }
            }

            $pdo->commit();
            $message = "Booking berhasil diperbarui!";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT b.*, g.name as building_name, g.category as building_category, g.price as building_price FROM bookings b JOIN buildings g ON b.building_id = g.id WHERE b.id = ?");
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh group data
            $group_stmt = $pdo->prepare("
                SELECT * FROM bookings 
                WHERE event_name = ? 
                AND booker_name = ? 
                AND building_id = ?
                ORDER BY booking_date ASC
            ");
            $group_stmt->execute([$booking['event_name'], $booking['booker_name'], $booking['building_id']]);
            $group_bookings = $group_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $is_multi_day = count($group_bookings) > 1;
            $group_dates = [];
            foreach ($group_bookings as $gb) {
                $group_dates[] = $gb['booking_date'];
            }
            
            $group_start_date = $group_dates[0];
            $group_end_date = $group_dates[count($group_dates)-1];
            
            // Refresh current items
            $current_items = [];
            $current_items_stmt = $pdo->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
            $current_items_stmt->execute([$id]);
            while ($item_row = $current_items_stmt->fetch(PDO::FETCH_ASSOC)) {
                $current_items[$item_row['item_id']] = $item_row['quantity'];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Edit Booking</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary shadow-sm btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
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
            <div class="row g-4">
                <!-- Main Info -->
                <div class="col-md-8">
                    <?php if ($is_multi_day): ?>
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <i class="bi bi-info-circle me-2"></i> 
                            Booking ini terdiri dari <?= count($group_bookings) ?> hari: 
                            <strong><?= date('d M Y', strtotime($group_start_date)) ?></strong> - <strong><?= date('d M Y', strtotime($group_end_date)) ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Informasi Peminjam & Acara</h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nama Lengkap</label>
                                    <input type="text" name="booker_name" value="<?= htmlspecialchars($booking['booker_name']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nomor Telepon</label>
                                    <input type="tel" name="booker_phone" value="<?= htmlspecialchars($booking['booker_phone']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Email</label>
                                    <input type="email" name="booker_email" value="<?= htmlspecialchars($booking['booker_email']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Organisasi / Instansi</label>
                                    <input type="text" name="organization" value="<?= htmlspecialchars($booking['organization']) ?>" class="form-control bg-light border-0 py-2 shadow-none">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Nama Acara</label>
                                <input type="text" name="event_name" value="<?= htmlspecialchars($booking['event_name']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Deskripsi Acara</label>
                                <textarea name="event_description" rows="3" class="form-control bg-light border-0 py-2 shadow-none"><?= htmlspecialchars($booking['event_description']) ?></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Ganti Surat Permohonan/Proposal (PDF)</label>
                                <input type="file" name="proposal_file" class="form-control">
                                <div class="form-text xsmall mt-1"><i>Kosongkan jika tidak ingin mengubah file.</i></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Jadwal Pelaksanaan</h5>
                            <div class="row g-3" id="date-time-wrapper">
                                <?php 
                                $booking_rental_type = $booking['rental_type'] ?? 'per_hari';
                                if ($booking_rental_type === 'per_tahun'): 
                                ?>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">Tahun Sewa</label>
                                    <select name="booking_year" class="form-select bg-light border-0 py-2 shadow-none" required>
                                        <?php 
                                        $current_year = date('Y');
                                        $booking_year = date('Y', strtotime($booking['booking_date']));
                                        for ($y = $current_year - 5; $y <= $current_year + 5; $y++): 
                                        ?>
                                        <option value="<?= $y ?>" <?= $y == $booking_year ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="col-12 mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" class="form-check-input" <?= $is_multi_day ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="is_multi_day">
                                            Booking untuk Beberapa <?= $booking_rental_type === 'per_bulan' ? 'Bulan' : 'Hari' ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="single-day-fields" class="col-12 <?= $is_multi_day ? 'd-none' : '' ?>">
                                    <div class="row g-3">
                                        <div class="col-md-<?= $booking_rental_type === 'per_bulan' ? '12' : '4' ?>">
                                            <label class="form-label small fw-bold text-secondary"><?= $booking_rental_type === 'per_bulan' ? 'Tanggal Mulai' : 'Tanggal' ?></label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                                <input type="date" name="booking_date" value="<?= htmlspecialchars($booking['booking_date']) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                            </div>
                                        </div>
                                        <?php if ($booking_rental_type === 'per_hari'): ?>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-secondary">Jam Mulai</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock text-muted"></i></span>
                                                <input type="text" name="start_time" value="<?= date('H:i', strtotime($booking['start_time'])) ?>" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-secondary">Jam Selesai</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock-history text-muted"></i></span>
                                                <input type="text" name="end_time" value="<?= date('H:i', strtotime($booking['end_time'])) ?>" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div id="multi-day-fields" class="col-12 <?= $is_multi_day ? '' : 'd-none' ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                                <input type="date" name="date_from" value="<?= htmlspecialchars($group_start_date) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Tanggal Selesai</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                                <input type="date" name="date_to" value="<?= htmlspecialchars($group_end_date) ?>" required class="form-control bg-light border-0 py-2 shadow-none">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php 
                    // Show add-on items if not Ruang Rapat Setda, building is not free, and is per_hari
                    $booking_building_stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
                    $booking_building_stmt->execute([$booking['building_id']]);
                    $booking_building = $booking_building_stmt->fetch(PDO::FETCH_ASSOC);
                    $booking_building_rental_type = $booking_building['rental_type'] ?? 'per_hari';
                    if (trim($booking_building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $booking_building['category'] !== 'gratis' && $booking_building_rental_type === 'per_hari'): 
                    ?>
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Tambah Fasilitas Pendukung</h5>
                            <div class="list-group">
                                <?php foreach($available_items as $item): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center bg-light border-0 rounded-3 mb-2 px-3">
                                        <div>
                                            <label for="item_<?= $item['id'] ?>" class="fw-bold small d-block mb-0"><?= htmlspecialchars($item['name']) ?></label>
                                            <span class="text-muted xsmall">
                                                Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?> 
                                                <?php if (stripos($item['name'], 'videotron') !== false): ?>
                                                    /2 jam penayangan
                                                <?php else: ?>
                                                    /unit
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <input type="number" name="items[<?= $item['id'] ?>]" id="item_<?= $item['id'] ?>" min="0" placeholder="0" class="form-control form-control-sm text-center" style="width: 80px;" value="<?= isset($current_items[$item['id']]) ? htmlspecialchars($current_items[$item['id']]) : 0 ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Info -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Status & Aksi</h5>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Status Booking</label>
                                <select name="status" class="form-select bg-light border-0 py-2 shadow-none fw-bold">
                                    <option value="pending" class="text-warning" <?= $booking['status'] == 'pending' ? 'selected' : '' ?>>PENDING</option>
                                    <option value="approved" class="text-success" <?= $booking['status'] == 'approved' ? 'selected' : '' ?>>APPROVED</option>
                                    <option value="rejected" class="text-danger" <?= $booking['status'] == 'rejected' ? 'selected' : '' ?>>REJECTED</option>
                                    <option value="cancelled" class="text-secondary" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>CANCELLED</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Pilih Gedung</label>
                                <select name="building_id" id="edit-building-select" class="form-select bg-light border-0 py-2 shadow-none">
                                    <?php foreach($buildings as $b): ?>
                                        <option value="<?= $b['id'] ?>" data-rental-type="<?= htmlspecialchars($b['rental_type'] ?? 'per_hari') ?>" <?= $booking['building_id'] == $b['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Catatan Admin</label>
                                <textarea name="admin_notes" rows="3" class="form-control bg-light border-0 py-2 shadow-none" placeholder="Alasan penolakan atau catatan tambahan..."><?= htmlspecialchars($booking['admin_notes']) ?></textarea>
                            </div>

                            <?php if($booking['proposal_file']): ?>
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-bold text-secondary d-block mb-2">Proposal Acara</label>
                                <a href="../uploads/proposals/<?= htmlspecialchars($booking['proposal_file']) ?>" target="_blank" class="btn btn-sm btn-white border w-100 text-primary fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Lihat Proposal
                                </a>
                            </div>
                            <?php endif; ?>

                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm mb-2">
                                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                                </button>
                                <a href="booking_delete.php?id=<?= $booking['id'] ?>" class="btn btn-outline-danger w-100 py-2 fw-bold rounded-3 delete-trigger" data-message="Yakin ingin menghapus booking untuk acara '<?= htmlspecialchars($booking['event_name']) ?>'?">
                                    <i class="bi bi-trash me-1"></i> Hapus Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../footer.php'; ?>
<?php include 'delete_modal.php'; ?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".timepicker", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        locale: "id",
        allowInput: true
    });
    
    // Handle building change
    const editBuildingSelect = document.getElementById('edit-building-select');
    const dateTimeWrapper = document.getElementById('date-time-wrapper');
    
    function updateDateTimeFields() {
        const selectedOption = editBuildingSelect.options[editBuildingSelect.selectedIndex];
        const rentalType = selectedOption.dataset.rentalType || 'per_hari';
        
        let html = '';
        
        if (rentalType === 'per_tahun') {
            const currentYear = new Date().getFullYear();
            let yearOptions = '';
            for (let y = currentYear - 5; y <= currentYear + 5; y++) {
                yearOptions += `<option value="${y}">${y}</option>`;
            }
            html = `
                <div class="col-md-12">
                    <label class="form-label small fw-bold text-secondary">Tahun Sewa</label>
                    <select name="booking_year" class="form-select bg-light border-0 py-2 shadow-none" required>
                        <option value="">-- Pilih Tahun --</option>
                        ${yearOptions}
                    </select>
                </div>
            `;
        } else {
            const multiLabel = rentalType === 'per_bulan' ? 'Bulan' : 'Hari';
            html = `
                <div class="col-12 mb-2">
                    <div class="form-check">
                        <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" class="form-check-input">
                        <label class="form-check-label small" for="is_multi_day">
                            Booking untuk Beberapa ${multiLabel}
                        </label>
                    </div>
                </div>
                
                <div id="single-day-fields" class="col-12">
                    <div class="row g-3">
                        <div class="col-md-${rentalType === 'per_bulan' ? '12' : '4'}">
                            <label class="form-label small fw-bold text-secondary">${rentalType === 'per_bulan' ? 'Tanggal Mulai' : 'Tanggal'}</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                <input type="date" name="booking_date" required class="form-control bg-light border-0 py-2 shadow-none">
                            </div>
                        </div>
                        ${rentalType === 'per_hari' ? `
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Jam Mulai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock text-muted"></i></span>
                                <input type="text" name="start_time" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Jam Selesai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock-history text-muted"></i></span>
                                <input type="text" name="end_time" required class="form-control bg-light border-0 py-2 shadow-none timepicker">
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div id="multi-day-fields" class="col-12 d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                <input type="date" name="date_from" required class="form-control bg-light border-0 py-2 shadow-none">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Tanggal Selesai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-calendar text-muted"></i></span>
                                <input type="date" name="date_to" required class="form-control bg-light border-0 py-2 shadow-none">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        dateTimeWrapper.innerHTML = html;
        
        // Re-initialize timepicker and multi-day logic
        if (rentalType === 'per_hari') {
            flatpickr("#start_time, #end_time", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true,
                locale: "id",
                allowInput: true
            });
        }
        
        const multiDayCheckbox = document.getElementById('is_multi_day');
        if (multiDayCheckbox) {
            const singleDayFields = document.getElementById('single-day-fields');
            const multiDayFields = document.getElementById('multi-day-fields');
            
            function toggleFields() {
                if (multiDayCheckbox.checked) {
                    singleDayFields.classList.add('d-none');
                    multiDayFields.classList.remove('d-none');
                } else {
                    singleDayFields.classList.remove('d-none');
                    multiDayFields.classList.add('d-none');
                }
            }
            
            multiDayCheckbox.addEventListener('change', toggleFields);
        }
    }
    
    if (editBuildingSelect) {
        editBuildingSelect.addEventListener('change', updateDateTimeFields);
    }
    
    // Handle multi-day toggle
    const multiDayCheckbox = document.getElementById('is_multi_day');
    if (multiDayCheckbox) {
        const singleDayFields = document.getElementById('single-day-fields');
        const multiDayFields = document.getElementById('multi-day-fields');
        
        function toggleFields() {
            if (multiDayCheckbox.checked) {
                singleDayFields.classList.add('d-none');
                multiDayFields.classList.remove('d-none');
            } else {
                singleDayFields.classList.remove('d-none');
                multiDayFields.classList.add('d-none');
            }
        }
        
        multiDayCheckbox.addEventListener('change', toggleFields);
    }
});
</script>

<style>
    .input-group-text { border-radius: 0.5rem 0 0 0.5rem; }
    .form-control, .form-select { border-radius: 0 0.5rem 0.5rem 0; }
    textarea.form-control { border-radius: 0.5rem; }
    .btn-white { background-color: #fff; }
</style>
