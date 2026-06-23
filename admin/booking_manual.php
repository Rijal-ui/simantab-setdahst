<?php
require_once 'auth_check.php';
require_once '../config.php';

$current_role = strtolower($_SESSION['role'] ?? '');
if (!in_array($current_role, ['super_admin', 'user', 'user_khusus'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$error = '';

// Fetch buildings
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $building_id = $_POST['building_id'];
    $booker_name = $_POST['booker_name'];
    $booker_phone = $_POST['booker_phone'];
    $organization = $_POST['organization'];
    $event_name = $_POST['event_name'];

    // Handle file upload
    $proposal_file = null;
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
            $proposal_file = $fileName;
        } else {
            $error = "Gagal mengupload file.";
        }
    }

    // Fetch building info
    $bStmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $bStmt->execute([$building_id]);
    $building = $bStmt->fetch(PDO::FETCH_ASSOC);
    $rental_type = $building['rental_type'] ?? 'per_hari';

    // Basic validation
    if (!$building_id || !$booker_name || !$event_name) {
        $error = "Mohon lengkapi semua field yang wajib.";
    } elseif ($rental_type === 'per_tahun') {
        $booking_year = $_POST['booking_year'] ?? null;
        if (!$booking_year) {
            $error = "Mohon pilih tahun sewa.";
        }
    } else {
        $is_multi_day = isset($_POST['is_multi_day']) && $_POST['is_multi_day'] === '1';
        $booking_date = $_POST['booking_date'] ?? null;
        $date_from = $_POST['date_from'] ?? null;
        $date_to = $_POST['date_to'] ?? null;
        $start_time = $_POST['start_time'] ?? null;
        $end_time = $_POST['end_time'] ?? null;
        
        if ($is_multi_day) {
            if (!$date_from || !$date_to) {
                $error = $rental_type === 'per_bulan' 
                    ? "Mohon isi tanggal mulai dan tanggal selesai untuk booking beberapa bulan." 
                    : "Mohon isi tanggal mulai dan tanggal selesai untuk booking beberapa hari.";
            } elseif (strtotime($date_from) > strtotime($date_to)) {
                $error = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai.";
            }
        } else {
            if ($rental_type === 'per_bulan') {
                if (!$booking_date) {
                    $error = "Mohon isi tanggal mulai untuk booking 1 bulan.";
                }
            } else {
                if (!$booking_date || !$start_time || !$end_time) {
                    $error = "Mohon isi tanggal, jam mulai, dan jam selesai.";
                } elseif (strtotime($end_time) <= strtotime($start_time)) {
                    $error = "Jam selesai harus lebih besar dari jam mulai.";
                }
            }
        }
    }
    
    $admin_notes = $_POST['admin_notes'];

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Fetch building info
            $bStmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
            $bStmt->execute([$building_id]);
            $building = $bStmt->fetch(PDO::FETCH_ASSOC);
            $rental_type = $building['rental_type'] ?? 'per_hari';

            $dates = [];
            if ($rental_type === 'per_tahun') {
                $booking_year = $_POST['booking_year'];
                $dates[] = $booking_year . '-01-01';
                $start_time_use = '00:00:00';
                $end_time_use = '23:59:59';
            } else {
                $is_multi_day = isset($_POST['is_multi_day']) && $_POST['is_multi_day'] === '1';
                if ($is_multi_day) {
                    $period = new DatePeriod(
                        new DateTime($_POST['date_from']),
                        new DateInterval('P1D'),
                        (new DateTime($_POST['date_to']))->modify('+1 day')
                    );
                    foreach ($period as $dt) {
                        $dates[] = $dt->format('Y-m-d');
                    }
                    $start_time_use = '00:00:00';
                    $end_time_use = '23:59:59';
                } else {
                    $dates[] = $_POST['booking_date'];
                    if ($rental_type === 'per_bulan') {
                        $start_time_use = '00:00:00';
                        $end_time_use = '23:59:59';
                    } else {
                        $start_time_use = $_POST['start_time'];
                        $end_time_use = $_POST['end_time'];
                    }
                }
            }

            // Restriction for "Gedung Balai Rakyat (Siang Hari)" on Thursdays (Acara Rutin)
            if (stripos($building['name'], 'Balai Rakyat') !== false && stripos($building['name'], 'Siang Hari') !== false) {
                foreach ($dates as $date) {
                    if (date('N', strtotime($date)) == 4) { // 4 = Thursday
                        throw new Exception("Mohon maaf, Gedung Balai Rakyat (Siang Hari) tidak dapat dibooking pada hari Kamis karena digunakan untuk Acara Rutin Zumba Isteri Bupati.");
                    }
                }
            }

            // Time restrictions based on building name
            if (!$is_atm_building) {
                if (stripos($building['name'], 'Balai Rakyat') !== false && stripos($building['name'], 'Siang Hari') !== false) {
                    if ($start_time_use < '07:00:00' || $end_time_use > '17:00:00') {
                        throw new Exception("Jam booking untuk Gedung Balai Rakyat (Siang Hari) hanya tersedia dari pukul 07:00 WITA s.d 17:00 WITA.");
                    }
                } elseif (stripos($building['name'], 'Balai Rakyat') !== false && stripos($building['name'], 'Malam Hari') !== false) {
                    if (!($start_time_use >= '18:00:00' || $end_time_use <= '06:00:00')) {
                        throw new Exception("Jam booking untuk Gedung Balai Rakyat (Malam Hari) hanya tersedia dari pukul 18:00 WITA s.d 06:00 WITA.");
                    }
                }
            }

            // Check availability for each date (only for daily rental)
            if ($rental_type === 'per_hari') {
                foreach ($dates as $date) {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                        WHERE building_id = ? 
                        AND booking_date = ? 
                        AND status = 'approved'
                        AND ((start_time <= ? AND end_time >= ?) OR (start_time <= ? AND end_time >= ?))");
                    $checkStmt->execute([$building_id, $date, $start_time_use, $start_time_use, $end_time_use, $end_time_use]);
                    $count = $checkStmt->fetchColumn();

                    if ($count >= $building['quantity']) {
                        throw new Exception("Jadwal atau unit untuk gedung ini sudah penuh pada tanggal " . date('d M Y', strtotime($date)) . ".");
                    }
                }
            }

            // Insert manual bookings (directly approved)
            $primary_booking_id = null;
            foreach ($dates as $idx => $date) {
                $insertStmt = $pdo->prepare("INSERT INTO bookings (building_id, booker_name, booker_phone, organization, event_name, booking_date, start_time, end_time, status, admin_notes, proposal_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)");
                $insertStmt->execute([$building_id, $booker_name, $booker_phone, $organization, $event_name, $date, $start_time_use, $end_time_use, $admin_notes, $proposal_file]);
                if ($idx === 0) {
                    $primary_booking_id = $pdo->lastInsertId();
                }
            }

            // Create invoice if needed
            if ($primary_booking_id && $building['category'] === 'berbayar') {
                $total_building_cost = $building['price'];
                
                if ($rental_type === 'per_hari') {
                    $total_building_cost = $building['price'] * count($dates);
                } elseif ($rental_type === 'per_bulan') {
                    $is_multi_day = isset($_POST['is_multi_day']) && $_POST['is_multi_day'] === '1';
                    if ($is_multi_day) {
                        $start_date = new DateTime($_POST['date_from']);
                        $end_date = new DateTime($_POST['date_to']);
                        $interval = $start_date->diff($end_date);
                        $number_of_months = $interval->m + ($interval->y * 12);
                        if ($interval->d > 0) $number_of_months += 1;
                    } else {
                        $number_of_months = 1;
                    }
                    $total_building_cost = $building['price'] * $number_of_months;
                } elseif ($rental_type === 'per_tahun') {
                    $total_building_cost = $building['price'];
                }
                
                if ($total_building_cost > 0) {
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
                    $invoiceStmt->execute([$invoice_id, $primary_booking_id, $total_building_cost]);
                }
            }

            $pdo->commit();
            $message = "Jadwal manual berhasil ditambahkan dan otomatis disetujui.";
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = $ex->getMessage();
        }
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <h1 class="h2 fw-bold">Input Jadwal Manual</h1>
</div>

<div class="row justify-content-center">
    <!-- Calendar Modal -->
    <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="calendarModalLabel">Ketersediaan Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Detail Modal -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold">Detail Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4 px-4">
                    <p id="modalMessage" class="mb-0 text-secondary" style="line-height: 1.8;"></p>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar View (Tampilkan via Modal) -->
    <div class="col-lg-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0" id="calendar-title-text">Ketersediaan Jadwal : Silakan pilih gedung</h5>
                <button class="btn btn-outline-primary btn-sm d-flex align-items-center" type="button" id="btnShowCalendar">
                    <i class="bi bi-calendar3 me-2"></i> Tampilkan Kalender
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($message): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="bookingForm">
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Informasi Peminjam</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Nama Peminjam</label>
                                <input type="text" name="booker_name" required class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">No. HP (WhatsApp)</label>
                                <input type="tel" name="booker_phone" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Organisasi / Instansi</label>
                                <input type="text" name="organization" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Detail Jadwal</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-medium">Pilih Gedung</label>
                                <select name="building_id" required class="form-select" id="buildingSelect">
                                    <option value="">-- Pilih Gedung --</option>
                                    <?php foreach($buildings as $b): ?>
                                        <option value="<?= $b['id'] ?>" data-rental-type="<?= htmlspecialchars($b['rental_type'] ?? 'per_hari') ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Nama Acara</label>
                                <input type="text" name="event_name" required class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Surat Permohonan/Proposal (PDF)</label>
                                <input type="file" name="proposal_file" class="form-control">
                                <div class="form-text xsmall mt-1"><i>Upload surat resmi permohonan peminjaman gedung.</i></div>
                            </div>

                            <!-- Date/time fields container -->
                            <div class="col-12 mt-4" id="dateTimeContainer">
                            </div>

                            <div class="col-12 mt-3">
                                <label class="form-label small fw-medium">Catatan Admin</label>
                                <textarea name="admin_notes" rows="3" class="form-control" placeholder="Contoh: Agenda Rutin, Kegiatan Pemkab, dll."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Jadwal
                        </button>
                        <a href="dashboard.php" class="btn btn-light px-4 ms-2">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<!-- FullCalendar Dependencies -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

<style>
        #calendar {
            background: #fff;
            border-radius: 8px;
        }
        .fc .fc-toolbar-title { font-size: 1.2rem; font-weight: 700; }
        .fc .fc-button-primary { background-color: #3b82f6; border-color: #3b82f6; }
        .fc .fc-button-active { background-color: #3eb489 !important; border-color: #3eb489 !important; color: #fff !important; }
        .fc .fc-col-header-cell { background-color: #3eb489; padding: 5px 0; }
        .fc .fc-col-header-cell-cushion { color: #fff !important; text-decoration: none !important; font-weight: bold; }
        .fc .fc-daygrid-day-number { font-size: 0.9rem; color: #333; text-decoration: none; }
        .fc .fc-event { border: none; padding: 4px 8px; font-size: 0.75rem; border-radius: 20px; cursor: pointer; }
        .xsmall { font-size: 0.75rem; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const buildingSelect = document.getElementById('buildingSelect');
        const dateTimeContainer = document.getElementById('dateTimeContainer');
        
        function updateDateTimeFields() {
            const selectedOption = buildingSelect.options[buildingSelect.selectedIndex];
            const buildingName = selectedOption.text;
            const rentalType = selectedOption.dataset.rentalType || 'per_hari';
            
            if (rentalType === 'per_tahun') {
                const currentYear = new Date().getFullYear();
                let yearOptions = '';
                for (let y = currentYear - 5; y <= currentYear + 5; y++) {
                    yearOptions += `<option value="${y}">${y}</option>`;
                }
                dateTimeContainer.innerHTML = `
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Tahun Sewa</label>
                        <select name="booking_year" class="form-select bg-light border-0 py-2 shadow-none" required>
                            <option value="">-- Pilih Tahun --</option>
                            ${yearOptions}
                        </select>
                    </div>
                `;
            } else {
                const multiLabel = rentalType === 'per_bulan' ? 'Booking untuk Beberapa Bulan' : 'Booking untuk Beberapa Hari';
                const multiNote = rentalType === 'per_bulan' 
                    ? 'Catatan: Booking lebih dari 1 (satu) bulan tidak membutuhkan jam. Untuk satu bulan, hanya butuh tanggal mulai.' 
                    : 'Catatan: Booking lebih dari 1 (satu) hari tidak membutuhkan jam. Untuk satu hari, jam wajib diisi.';
                
                dateTimeContainer.innerHTML = `
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" class="form-check-input">
                        <label class="form-check-label small" for="is_multi_day">
                            ${multiLabel}
                            <div class="form-text xsmall mt-1"><i>Centang apabila sewa lebih dari 1 (satu) ${rentalType === 'per_bulan' ? 'bulan' : 'hari'}.</i></div>
                        </label>
                    </div>
                    
                    <div id="single-day-fields" class="row g-3">
                        <div class="col-md-${rentalType === 'per_bulan' ? '12' : '4'}">
                            <label class="form-label small fw-medium">${rentalType === 'per_bulan' ? 'Tanggal Mulai' : 'Tanggal'}</label>
                            <input type="date" name="booking_date" class="form-control">
                        </div>
                        ${rentalType === 'per_hari' ? `
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Jam Mulai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock text-muted"></i></span>
                                <input type="text" name="start_time" id="start_time" class="form-control bg-light border-0 py-2 shadow-none timepicker" placeholder="--:--">
                            </div>
                        </div>
                        <div class="col-md-4">
                           <label class="form-label small fw-medium">Jam Selesai</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-clock-history text-muted"></i></span>
                                <input type="text" name="end_time" id="end_time" class="form-control bg-light border-0 py-2 shadow-none timepicker" placeholder="--:--">
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    <div id="multi-day-fields" class="row g-3 d-none">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Tanggal Selesai</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                    </div>
                    <div class="form-text xsmall mt-2"><i>${multiNote}</i></div>
                `;
                
                // Re-attach multi-day sync logic
                var cb = document.getElementById('is_multi_day');
                var single = document.getElementById('single-day-fields');
                var multi = document.getElementById('multi-day-fields');
                
                function sync() {
                    if (cb.checked) {
                        multi.classList.remove('d-none');
                        single.classList.add('d-none');
                        single.querySelectorAll('input').forEach(i => i.required = false);
                        multi.querySelectorAll('input').forEach(i => i.required = true);
                    } else {
                        multi.classList.add('d-none');
                        single.classList.remove('d-none');
                        single.querySelectorAll('input').forEach(i => i.required = true);
                        multi.querySelectorAll('input').forEach(i => i.required = false);
                    }
                }
                cb.addEventListener('change', sync);
                sync();
                
                // Re-initialize pickers
                updatePickers(buildingName);
            }
        }
        
        buildingSelect.addEventListener('change', function() {
            updateDateTimeFields();
            
            if (this.value) {
                calendarTitleText.innerText = "Ketersediaan Jadwal : " + this.options[this.selectedIndex].text;
            } else {
                calendarTitleText.innerText = "Ketersediaan Jadwal : Silakan pilih gedung";
            }
        });
        
        // Flatpickr initialization with dynamic restrictions
        let timePickerConfig = {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            locale: "id",
            allowInput: true
        };

        let datePickerConfig = {
            locale: "id",
            dateFormat: "Y-m-d"
        };

        let startTimePicker, endTimePicker, bookingDatePicker, dateFromPicker, dateToPicker;

        function updatePickers(buildingName) {
            if (!buildingName) return;
            
            const name = buildingName.toLowerCase();
            let currentTimeConfig = { ...timePickerConfig };
            let currentDateConfig = { ...datePickerConfig };

            // Reset restrictions
            delete currentTimeConfig.minTime;
            delete currentTimeConfig.maxTime;
            delete currentDateConfig.disable;

            if (name.includes('balai rakyat') && name.includes('siang hari')) {
                currentTimeConfig.minTime = "07:00";
                currentTimeConfig.maxTime = "17:00";
                currentDateConfig.disable = [
                    function(date) { return (date.getDay() === 4); } // 4 = Thursday
                ];
                const st = document.getElementById('start_time');
                const et = document.getElementById('end_time');
                if (st) st.placeholder = "07:00";
                if (et) et.placeholder = "17:00";
            } else if (name.includes('balai rakyat') && name.includes('malam hari')) {
                currentTimeConfig.minTime = "18:00";
                currentTimeConfig.maxTime = "06:00";
                const st = document.getElementById('start_time');
                const et = document.getElementById('end_time');
                if (st) st.placeholder = "18:00";
                if (et) et.placeholder = "06:00";
            } else {
                const st = document.getElementById('start_time');
                const et = document.getElementById('end_time');
                if (st) st.placeholder = "--:--";
                if (et) et.placeholder = "--:--";
            }

            // Re-init pickers
            if (startTimePicker) startTimePicker.destroy();
            if (endTimePicker) endTimePicker.destroy();
            if (bookingDatePicker) bookingDatePicker.destroy();
            if (dateFromPicker) dateFromPicker.destroy();
            if (dateToPicker) dateToPicker.destroy();

            if (document.getElementById('start_time')) {
                startTimePicker = flatpickr("#start_time", currentTimeConfig);
                endTimePicker = flatpickr("#end_time", currentTimeConfig);
                bookingDatePicker = flatpickr('input[name="booking_date"]', currentDateConfig);
                dateFromPicker = flatpickr('input[name="date_from"]', currentDateConfig);
                dateToPicker = flatpickr('input[name="date_to"]', currentDateConfig);
            }
        }

        const calendarTitleText = document.getElementById('calendar-title-text');
        const calendarModalLabel = document.getElementById('calendarModalLabel');
        const btnShowCalendar = document.getElementById('btnShowCalendar');
        const calendarEl = document.getElementById('calendar');
        const calendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));
        const eventDetailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
        const modalMessage = document.getElementById('modalMessage');
        let calendar = null;

        function initCalendar(buildingId, buildingName) {
            if (calendar) {
                calendar.destroy();
            }
            
            calendarModalLabel.innerText = buildingId ? "Jadwal : " + buildingName : "Semua Jadwal Gedung";
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth'
                },
                locale: 'id',
                events: '../api_calendar.php' + (buildingId ? '?building_id=' . buildingId : ''),
                eventClick: function(info) {
                    const start = info.event.start;
                    const end = info.event.end;
                    const startStr = start ? start.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
                    const endStr = end ? end.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
                    const bName = info.event.extendedProps && info.event.extendedProps.building_name ? info.event.extendedProps.building_name : '';
                    const description = info.event.extendedProps && info.event.extendedProps.description ? info.event.extendedProps.description : '';

                    const msg = `
                        <strong>Acara:</strong> ${info.event.title}<br>
                        <strong>Gedung:</strong> ${bName}<br>
                        <strong>Waktu:</strong> ${startStr} WITA s.d ${endStr ? endStr : 'selesai'} WITA
                        ${description ? '<br><div class="mt-2 text-danger small"><em>' + description + '</em></div>' : ''}
                    `;
                    
                    modalMessage.innerHTML = msg;
                    eventDetailModal.show();
                }
            });
            
            calendarModal.show();
            setTimeout(() => {
                calendar.render();
            }, 200);
        }

        btnShowCalendar.addEventListener('click', function() {
            const buildingId = buildingSelect.value;
            const buildingName = buildingSelect.options[buildingSelect.selectedIndex].text;
            initCalendar(buildingId, buildingName);
        });
    });
</script>
