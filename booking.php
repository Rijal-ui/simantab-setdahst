<?php
require_once 'config.php';

$message = '';
$error = '';
$building_id = $_GET['building_id'] ?? null;
$building = null;

if ($building_id) {
    $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $stmt->execute([$building_id]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all available add-on items
$itemsStmt = $pdo->query("SELECT * FROM items ORDER BY name");
$available_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$building) {
        $tmp_building_id = $_POST['building_id'] ?? null;
        if ($tmp_building_id) {
            $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
            $stmt->execute([$tmp_building_id]);
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    $building_id = $_POST['building_id'];
    $booker_name = $_POST['booker_name'];
    $booker_email = $_POST['booker_email'];
    $booker_phone = $_POST['booker_phone'];
    $organization = $_POST['organization'];
    $event_name = $_POST['event_name'];
    $event_description = $_POST['event_description'];
    $is_multi_day = isset($_POST['is_multi_day']) && $_POST['is_multi_day'] === '1';
    $booking_date = $_POST['booking_date'] ?? null;
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $proposal_file = null;

    // Handle file upload
    if (isset($_FILES['proposal_file']) && $_FILES['proposal_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/proposals/';
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

    // Simple validation
    $is_atm_building = stripos($building['name'], 'ATM') !== false;
    
    if (!$building_id || !$booker_name) {
        $error = "Mohon lengkapi semua field yang wajib.";
    }
    
    if ($is_atm_building) {
        // For ATM buildings, only need year
        $booking_year = $_POST['booking_year'] ?? null;
        if (!$booking_year) {
            $error = "Mohon pilih tahun sewa.";
        }
        // Set date to January 1st of selected year, time to 00:00 - 23:59
        $booking_date = $booking_year . '-01-01';
        $start_time = '00:00:00';
        $end_time = '23:59:59';
        $is_multi_day = false;
    } else {
        if (!$error && $is_multi_day) {
            if (!$date_from || !$date_to) {
                $error = "Mohon isi tanggal mulai dan tanggal selesai untuk booking beberapa hari.";
            } elseif (strtotime($date_from) === false || strtotime($date_to) === false) {
                $error = "Format tanggal tidak valid.";
            } elseif (strtotime($date_from) > strtotime($date_to)) {
                $error = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai.";
            }
        }
        if (!$error && !$is_multi_day) {
            if (!$booking_date || !$start_time || !$end_time) {
                $error = "Untuk booking 1 hari, mohon isi tanggal, jam mulai, dan jam selesai.";
            } elseif (strtotime($end_time) <= strtotime($start_time)) {
                $error = "Jam selesai harus lebih besar dari jam mulai.";
            }
        }
    }

    // Restriction: Maximum H-3 (Booking must be at least 3 days before the event) - DISABLED
    if (!$error) {
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $min_booking_date = (clone $today)->modify('+3 days');

        if ($is_multi_day) {
            $booking_start = new DateTime($date_from);
            if ($booking_start < $min_booking_date) {
                $error = "Booking minimal dilakukan H-3 sebelum acara. Tanggal tercepat yang diperbolehkan adalah " . $min_booking_date->format('d M Y') . ".";
            }
        } else {
            $booking_start = new DateTime($booking_date);
            if ($booking_start < $min_booking_date) {
                $error = "Booking minimal dilakukan H-3 sebelum acara. Tanggal tercepat yang diperbolehkan adalah " . $min_booking_date->format('d M Y') . ".";
            }
        }
    }

    if (!$error) {
        // Check availability & create bookings
        try {
            $pdo->beginTransaction();

            $dates = [];
            if ($is_multi_day) {
                $period = new DatePeriod(
                    new DateTime($date_from),
                    new DateInterval('P1D'),
                    (new DateTime($date_to))->modify('+1 day')
                );
                foreach ($period as $dt) {
                    $dates[] = $dt->format('Y-m-d');
                }
                $start_time_use = '00:00:00';
                $end_time_use = '23:59:59';
            } else {
                $dates[] = $booking_date;
                $start_time_use = $start_time;
                $end_time_use = $end_time;
            }

            // Restriction for "Gedung Balai Rakyat (Siang Hari)" on Thursdays (Acara Rutin)
            if (trim($building['name']) === 'Gedung Balai Rakyat (Siang Hari)') {
                foreach ($dates as $date) {
                    if (date('N', strtotime($date)) == 4) { // 4 = Thursday
                        throw new Exception("Mohon maaf, Gedung Balai Rakyat (Siang Hari) tidak dapat dibooking pada hari Kamis karena digunakan untuk Acara Rutin Zumba Isteri Bupati.");
                    }
                }
            }

            // Time restrictions based on building name
            if (trim($building['name']) === 'Gedung Balai Rakyat (Siang Hari)') {
                if ($start_time_use < '07:00:00' || $end_time_use > '17:00:00') {
                    throw new Exception("Jam booking untuk Gedung Balai Rakyat (Siang Hari) hanya tersedia dari pukul 07:00 WITA s.d 17:00 WITA.");
                }
            } elseif (trim($building['name']) === 'Gedung Balai Rakyat (Malam Hari)') {
                if (!($start_time_use >= '18:00:00' || $end_time_use <= '06:00:00')) {
                    throw new Exception("Jam booking untuk Gedung Balai Rakyat (Malam Hari) hanya tersedia dari pukul 18:00 WITA s.d 06:00 WITA.");
                }
            }

            foreach ($dates as $date) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                    WHERE building_id = ? 
                    AND booking_date = ? 
                    AND status = 'approved'
                    AND ((start_time <= ? AND end_time >= ?) OR (start_time <= ? AND end_time >= ?))");
                $checkStmt->execute([$building_id, $date, $start_time_use, $start_time_use, $end_time_use, $end_time_use]);
                $count = $checkStmt->fetchColumn();
                if ($count >= $building['quantity']) {
                    throw new Exception("Jadwal atau unit untuk aset ini sudah penuh pada tanggal " . date('d M Y', strtotime($date)) . ".");
                }
            }

            $primary_booking_id = null;
            foreach ($dates as $idx => $date) {
                $stmt = $pdo->prepare("INSERT INTO bookings (building_id, booker_name, booker_email, booker_phone, organization, event_name, event_description, booking_date, start_time, end_time, proposal_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$building_id, $booker_name, $booker_email, $booker_phone, $organization, $event_name, $event_description, $date, $start_time_use, $end_time_use, $proposal_file]);
                if ($idx === 0) {
                    $primary_booking_id = $pdo->lastInsertId();
                }
            }

            // Process and save add-on items (Skip for Ruang Rapat Setda, Free buildings, and ATM buildings)
            $total_item_price = 0;
            if (trim($building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $building['category'] !== 'gratis' && !$is_atm_building) {
                $ordered_items = $_POST['items'] ?? [];
                foreach ($available_items as $item) {
                    if (isset($ordered_items[$item['id']]) && $ordered_items[$item['id']] > 0) {
                        $quantity = (int)$ordered_items[$item['id']];
                        $price_at_booking = $item['price_per_unit'];
                        $total_item_price += $quantity * $price_at_booking;

                        $itemStmt = $pdo->prepare("INSERT INTO booking_items (booking_id, item_id, quantity, price_at_booking) VALUES (?, ?, ?, ?)");
                        $itemStmt->execute([$primary_booking_id, $item['id'], $quantity, $price_at_booking]);
                    }
                }
            }

            // Calculate final invoice amount
            $number_of_days = count($dates);
            $building_price_per_day = ($building['category'] === 'berbayar' ? $building['price'] : 0);
            
            if ($is_atm_building) {
                // For ATM buildings, price is per year (already stored as price per month in DB, multiply by 12)
                $total_building_cost = $building_price_per_day * 12;
            } else {
                $total_building_cost = $building_price_per_day * $number_of_days;
            }
            
            $final_amount = $total_building_cost + $total_item_price;

            if ($final_amount > 0) {
                // Get current month and year, e.g., "03-2026"
                $current_month_year = date('m-Y');

                // Fetch counter and last month from settings
                $counterStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_counter'");
                $invoice_counter = $counterStmt ? (int)$counterStmt->fetchColumn() : 1;

                $monthStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'last_invoice_month'");
                $last_invoice_month = $monthStmt ? $monthStmt->fetchColumn() : null;

                // If month has changed, reset counter
                if ($current_month_year !== $last_invoice_month) {
                    $invoice_counter = 1;
                    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_invoice_month'")->execute([$current_month_year]);
                }

                // Format the invoice ID, e.g., 032026-0001
                $invoice_id = date('mY') . '-' . str_pad($invoice_counter, 4, '0', STR_PAD_LEFT);

                // Increment counter for the next invoice
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'invoice_counter'")->execute([$invoice_counter + 1]);

                $invoiceStmt = $pdo->prepare("INSERT INTO invoices (id, booking_id, amount) VALUES (?, ?, ?)");
                $invoiceStmt->execute([$invoice_id, $primary_booking_id, $final_amount]);
                
                $message = "Booking untuk $number_of_days hari berhasil diajukan! Total tagihan telah dibuat. <a href='invoice.php?id=$invoice_id' target='_blank' class='fw-bold text-decoration-underline'>Lihat Invoice</a>";
            } else {
                $message = $is_multi_day 
                    ? "Booking untuk $number_of_days hari berhasil diajukan! Menunggu persetujuan admin."
                    : "Booking berhasil diajukan! Menunggu persetujuan admin.";
            }

            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = $ex->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 fw-bold mb-4">Form Booking Gedung</h1>
            
            <?php if ($building): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title fw-bold mb-0">Ketersediaan Jadwal : <?= htmlspecialchars($building['name']) ?></h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#calendarCollapse">
                                <i class="bi bi-calendar3 me-1"></i> Tampilkan/Sembunyikan Kalender
                            </button>
                               <div class="form-text xsmall mt-2 text-end">
                            </div>
                        </div>
                          <div id="calendarCollapse" class="collapse">
                            <div id="calendar" class="bg-light p-3 rounded border" style="min-height: 400px;"></div>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var calendarEl = document.getElementById('calendar');
                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,listMonth'
                        },
                        buttonText: {
                            today: 'Today',
                            month: 'Month',
                            week: 'Week',
                            day: 'Day',
                            list: 'List'
                        },
                        height: 'auto',
                        locale: 'id',
                        firstDay: 0, // Start on Sunday
                        dayHeaderFormat: { weekday: 'long' },
                        listDayFormat: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
                        listDaySideFormat: false,
                        events: 'api_calendar.php?building_id=<?= $building['id'] ?>',
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            meridiem: false
                        },
                        eventClick: function(info) {
                            var start = info.event.start;
                            var end = info.event.end;
                            var startStr = start ? start.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
                            var endStr = end ? end.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
                            var buildingName = info.event.extendedProps && info.event.extendedProps.building_name ? info.event.extendedProps.building_name : '<?= htmlspecialchars($building['name']) ?>';
                            var description = info.event.extendedProps && info.event.extendedProps.description ? info.event.extendedProps.description : '';
                            var msg = `
                                <strong>Acara:</strong> ${info.event.title}<br>
                                <strong>Gedung:</strong> ${buildingName}<br>
                                <strong>Waktu:</strong> ${startStr} WITA s.d ${endStr} WITA
                                ${description ? '<br><div class="mt-2 text-danger small"><em>' + description + '</em></div>' : ''}
                            `;
                            showModal(msg);
                        }
                    });
                    calendar.render();
                    
                    var calendarCollapse = document.getElementById('calendarCollapse');
                    calendarCollapse.addEventListener('shown.bs.collapse', function () {
                        calendar.updateSize();
                    });
                });

                function showModal(message) {
                    var modalBody = document.getElementById('modalMessage');
                    modalBody.innerHTML = message;
                    var myModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                    myModal.show();
                }
                </script>

                <!-- Modal -->
                <div class="modal fade" id="eventDetailModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header border-bottom-0">
                                <h5 class="modal-title fw-bold">Detail Jadwal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body py-4">
                                <p id="modalMessage" class="mb-0 text-secondary"></p>
                            </div>
                            <div class="modal-footer border-top-0">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                const buildingName = "<?= htmlspecialchars(trim($building['name'] ?? '')) ?>";
                const bName = buildingName.toLowerCase();

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
                if(cb) cb.addEventListener('change', sync);
                sync();

                // 1. Hitung Batasan H-3 (Tanggal minimal yang boleh dipilih)
                const minBookingDate = new Date();
                minBookingDate.setDate(minBookingDate.getDate() + 3);

                // 2. Konfigurasi dasar untuk pemilihan TANGGAL (Berlaku untuk semua gedung)
                let datePickerConfig = {
                    minDate: minBookingDate, // Mengaktifkan batasan H-3 secara mutlak di semua gedung
                    locale: "id",
                    dateFormat: "Y-m-d",
                    disableMobile: true,     // Mengunci performa kalender agar valid di iPhone/Safari
                    disable: []              // Tempat menampung filter hari jika diperlukan
                };

                // 3. Konfigurasi dasar untuk pemilihan JAM (Timepicker)
                let flatpickrConfig = {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "H:i",
                    time_24hr: true,
                    locale: "id",
                    allowInput: true,
                    disableMobile: true      // Mengamankan penampang jam di iOS/Android
                };

                const startTimeInput = document.getElementById('start_time');
                const endTimeInput = document.getElementById('end_time');

                // 4. Kondisi Spesifik Berdasarkan Nama Gedung
                if (bName.includes('siang hari')) {
                    flatpickrConfig.minTime = "07:00";
                    flatpickrConfig.maxTime = "17:00";
                    
                    if (startTimeInput && endTimeInput) {
                        startTimeInput.placeholder = "07:00";
                        endTimeInput.placeholder = "17:00";
                    }

                    // 🔥 HANYA UNTUK SIANG HARI: Filter hari Kamis diaktifkan
                    datePickerConfig.disable.push(function(date) {
                        return date.getDay() === 4; // 4 melambangkan hari Kamis
                    });

                } else if (bName.includes('malam hari')) {
                    flatpickrConfig.minTime = "18:00";
                    flatpickrConfig.maxTime = "06:00";
                    
                    if (startTimeInput && endTimeInput) {
                        startTimeInput.placeholder = "18:00";
                        endTimeInput.placeholder = "06:00";
                    }
                    flatpickrConfig.defaultDate = "18:00";
                } else {
                    // Untuk gedung/ruangan lainnya, bersihkan batasan placeholder jam
                    if (startTimeInput && endTimeInput) {
                        startTimeInput.placeholder = "--:--";
                        endTimeInput.placeholder = "--:--";
                    }
                    // Filter hari kamis otomatis kosong ([]), sehingga hari kamis tetap bisa dipilih bebas.
                }

                // 5. Jalankan Flatpickr secara global ke semua input date dan timepicker
                flatpickr(".form-control[type='date']", datePickerConfig);
                flatpickr(".timepicker", flatpickrConfig);
            });
                </script>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($building): ?>
                <div class="card mb-4 border-0 shadow-sm overflow-hidden">
                    <div class="card-header bg-white border-bottom-0 pt-4">
                        <h5 class="fw-bold mb-1">Booking : <?= htmlspecialchars($building['name']) ?></h5>
                        
                        <?php if (stripos($building['name'], 'Auditorium') !== false || stripos($building['name'], 'Pendopo') !== false): ?>
                            <div class="alert alert-warning border-0 py-2 small mb-2 d-inline-block">
                                <i class="bi bi-info-circle-fill me-1"></i> <strong>Catatan:</strong> Khusus acara kedinasan
                            </div>
                        <?php elseif (stripos($building['name'], 'Balai Rakyat') !== false): ?>
                            <div class="alert alert-danger border-0 py-2 small mb-2 d-inline-block">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Penting:</strong> Tidak untuk acara pernikahan
                            </div>
                            <?php elseif (stripos($building['name'], 'Pendopo') !== false): ?>
                            <div class="alert alert-danger border-0 py-2 small mb-2 d-inline-block">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Penting:</strong> Khusus acara kedinasan
                            </div>
                        <?php endif; ?>

                        <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($building['location']) ?> • <i class="bi bi-people me-1"></i> Kapasitas <?= $building['capacity'] ?></p>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($building['requirements'])): ?>
                        <div class="alert alert-primary border-0 bg-primary-subtle text-primary-emphasis small mb-4">
                            <h6 class="fw-bold mb-1 small">Syarat & Ketentuan :</h6>
                            <?= nl2br(htmlspecialchars($building['requirements'])) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!$message): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="building_id" value="<?= $building['id'] ?>">
                            
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Informasi Peminjam</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-medium">Nama Lengkap</label>
                                        <input type="text" name="booker_name" required class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-medium">No. Handphone (WhatsApp)</label>
                                        <input type="tel" name="booker_phone" required class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-medium">Email</label>
                                        <input type="email" name="booker_email" required class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-medium">Organisasi / Instansi</label>
                                        <input type="text" name="organization" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Detail Acara</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-medium">Nama Acara</label>
                                        <input type="text" name="event_name" required class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-medium">Deskripsi Acara</label>
                                        <textarea name="event_description" rows="3" class="form-control"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-medium">Surat Permohonan/Proposal (PDF)</label>
                                        <input type="file" name="proposal_file" class="form-control">
                                        <div class="form-text xsmall mt-1"><i>Upload surat resmi permohonan peminjaman gedung.</i></div>
                                    </div>
                                    
                                    <?php 
                                    $is_atm = stripos($building['name'], 'ATM') !== false;
                                    if (!$is_atm): 
                                    ?>
                                    <div class="col-12 mt-4">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" id="is_multi_day" name="is_multi_day" value="1" class="form-check-input">
                                            <label class="form-check-label small" for="is_multi_day">
                                                Booking untuk Beberapa Hari
                                                <div class="form-text xsmall mt-1"><i>Centang apabila sewa lebih dari 1 (satu) hari.</i></div>
                                            </label>
                                        </div>
                                        
                                        <div id="single-day-fields" class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label small fw-medium">Tanggal</label>
                                                <input type="date" name="booking_date" class="form-control" min="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                                            </div>
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
                                        </div>

                                        <div id="multi-day-fields" class="row g-3 d-none">
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold text-secondary">Tanggal Mulai</label>
                                                <input type="date" name="date_from" class="form-control" min="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold text-secondary">Tanggal Selesai</label>
                                                <input type="date" name="date_to" class="form-control" min="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                                            </div>
                                        </div>
                                        <div class="form-text xsmall mt-1"><i>Catatan: Booking lebih dari 1 (satu) hari tidak membutuhkan jam. Untuk satu hari, jam wajib diisi.</i></div>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-12 mt-4">
                                        <div class="mb-4">
                                            <label class="form-label small fw-bold text-secondary">Tahun Sewa</label>
                                            <select name="booking_year" class="form-select bg-light border-0 py-2 shadow-none" required>
                                                <?php 
                                                $current_year = date('Y');
                                                for ($y = $current_year; $y <= $current_year + 5; $y++): 
                                                ?>
                                                <option value="<?= $y ?>"><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <div class="form-text xsmall mt-1"><i>Pilih tahun sewa Ruang ATM (harga per tahun).</i></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (trim($building['name']) !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $building['category'] !== 'gratis' && !$is_atm): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Tambah Fasilitas Pendukung</h6>
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
                                            <input type="number" name="items[<?= $item['id'] ?>]" id="item_<?= $item['id'] ?>" min="0" placeholder="0" class="form-control form-control-sm text-center" style="width: 80px;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="mt-4">
                                <button type="button" class="btn btn-primary w-100 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#bookingSummaryModal">
                                    Ajukan Booking
                                </button>
                                <a href="index.php" class="btn btn-link w-100 mt-2 text-secondary text-decoration-none small">Kembali ke Beranda</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!$message): ?>
                <div class="alert alert-warning border-0 shadow-sm" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> Silakan pilih gedung terlebih dahulu dari halaman utama.
                    <a href="index.php" class="alert-link fw-bold">Kembali ke Beranda</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($building): ?>
<!-- Modal Ringkasan Booking -->
<div class="modal fade" id="bookingSummaryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Ringkasan Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <div id="summaryContent" class="text-muted"></div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmBookingBtn">
                    <i class="bi bi-send me-1"></i> Ajukan Booking
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.querySelector('form[enctype="multipart/form-data"]');
    const summaryModal = new bootstrap.Modal(document.getElementById('bookingSummaryModal'));
    const summaryContent = document.getElementById('summaryContent');
    const confirmBookingBtn = document.getElementById('confirmBookingBtn');

    // Format date Indonesia
    function formatDateIndo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('id-ID', options);
    }

    // Format number Indonesia
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Populate summary content when modal is shown
    document.querySelector('[data-bs-target="#bookingSummaryModal"]').addEventListener('click', function() {
        let summaryHTML = '';
        
        // Get building info
        const buildingName = "<?= htmlspecialchars($building['name'] ?? '') ?>";
        
        // Get form values
        const bookerName = document.querySelector('input[name="booker_name"]')?.value;
        const bookerPhone = document.querySelector('input[name="booker_phone"]')?.value;
        const bookerEmail = document.querySelector('input[name="booker_email"]')?.value;
        const organization = document.querySelector('input[name="organization"]')?.value;
        const eventName = document.querySelector('input[name="event_name"]')?.value;
        const eventDesc = document.querySelector('textarea[name="event_description"]')?.value;
        const proposalFile = document.querySelector('input[name="proposal_file"]')?.files[0]?.name;

        const isAtm = "<?= stripos($building['name'] ?? '', 'ATM') !== false ? '1' : '0' ?>";
        
        let dateInfo = '';
        if (isAtm === '1') {
            const bookingYear = document.querySelector('select[name="booking_year"]')?.value;
            dateInfo = `<strong>Tahun Sewa:</strong> ${bookingYear}`;
        } else {
            const isMultiDay = document.getElementById('is_multi_day')?.checked;
            if (isMultiDay) {
                const dateFrom = document.querySelector('input[name="date_from"]')?.value;
                const dateTo = document.querySelector('input[name="date_to"]')?.value;
                dateInfo = `<strong>Tanggal:</strong> ${formatDateIndo(dateFrom)} s.d ${formatDateIndo(dateTo)} (${Math.ceil((new Date(dateTo) - new Date(dateFrom)) / (1000 * 60 * 60 * 24)) + 1} hari)`;
            } else {
                const bookingDate = document.querySelector('input[name="booking_date"]')?.value;
                const startTime = document.querySelector('input[name="start_time"]')?.value;
                const endTime = document.querySelector('input[name="end_time"]')?.value;
                dateInfo = `<strong>Tanggal:</strong> ${formatDateIndo(bookingDate)}<br>`;
                dateInfo += `<strong>Waktu:</strong> ${startTime} WITA s.d ${endTime} WITA`;
            }
        }

        // Build summary
        summaryHTML = `
            <div class="row g-3">
                <div class="col-12">
                    <div class="fw-bold text-primary mb-3">📍 ${buildingName}</div>
                </div>
                <div class="col-12">
                    <div class="border-bottom pb-2 mb-2">
                        <strong class="text-dark">📋 Informasi Peminjam</strong>
                    </div>
                    <div class="small">
                        <div class="mb-1"><strong>Nama:</strong> ${bookerName || '-'}</div>
                        <div class="mb-1"><strong>No. HP:</strong> ${bookerPhone || '-'}</div>
                        <div class="mb-1"><strong>Email:</strong> ${bookerEmail || '-'}</div>
                        <div class="mb-1"><strong>Organisasi:</strong> ${organization || '-'}</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border-bottom pb-2 mb-2">
                        <strong class="text-dark">🎪 Detail Acara</strong>
                    </div>
                    <div class="small">
                        <div class="mb-1"><strong>Nama Acara:</strong> ${eventName || '-'}</div>
                        <div class="mb-1"><strong>Deskripsi:</strong> ${eventDesc || '-'}</div>
                        <div class="mb-1">${dateInfo}</div>
                    </div>
                </div>
            `;

        // Add items if applicable
        if (!isAtm && "<?= trim($building['name'] ?? '') !== 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah' && $building['category'] !== 'gratis' ? '1' : '0' ?>" === '1') {
            let hasItems = false;
            let itemsHTML = `
                <div class="col-12">
                    <div class="border-bottom pb-2 mb-2">
                        <strong class="text-dark">🛠️ Fasilitas Pendukung</strong>
                    </div>
                    <div class="small">
                `;
                <?php foreach($available_items as $item): ?>
                    const item<?= $item['id'] ?> = document.querySelector('input[name="items[<?= $item['id'] ?>]"]')?.value;
                    if (parseInt(item<?= $item['id'] ?>) > 0) {
                        hasItems = true;
                        itemsHTML += `<div class="mb-1"><?= htmlspecialchars($item['name']) ?>: <strong>${item<?= $item['id'] ?>} unit</strong> (Rp ${formatNumber(<?= $item['price_per_unit'] ?>)}/unit)</div>`;
                    }
                <?php endforeach; ?>
                itemsHTML += `</div></div>`;
                if (hasItems) {
                    summaryHTML += itemsHTML;
                }
        }

        if (proposalFile) {
            summaryHTML += `
                <div class="col-12">
                    <div class="border-bottom pb-2 mb-2">
                        <strong class="text-dark">📄 Dokumen</strong>
                    </div>
                    <div class="small"><i class="bi bi-file-earmark-check me-1 text-success"></i>${proposalFile}</div>
                </div>
            `;
        }

        summaryHTML += '</div>';
        summaryContent.innerHTML = summaryHTML;
    });

    // Submit form when confirm button is clicked
    confirmBookingBtn.addEventListener('click', function() {
        bookingForm.submit();
    });
});
</script>
<?php endif; ?>

<style>
    .xsmall { font-size: 0.75rem; }
    .bg-primary-subtle { background-color: #e7f1ff; }
    .text-primary-emphasis { color: #052c65; }
    /* Ensure modal is on top */
    #bookingSummaryModal { z-index: 99999 !important; }

    /* Custom FullCalendar Mint Green Theme */
    #calendar {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #eee;
        overflow: hidden;
    }
    .fc .fc-toolbar-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
    }
    .fc .fc-button-primary {
        background-color: #fff;
        border-color: #ddd;
        color: #666;
        text-transform: capitalize;
        font-weight: 500;
        padding: 6px 16px;
        box-shadow: none !important;
        transition: all 0.2s ease;
    }
    .fc .fc-button-primary:hover {
        background-color: #f8f9fa;
        border-color: #ccc;
        color: #333;
    }
    .fc .fc-button-active {
        background-color: #3eb489 !important; /* Mint Green */
        border-color: #3eb489 !important;
        color: #fff !important;
    }
    .fc .fc-today-button {
        background-color: #6c757d;
        color: #fff;
        border-color: #6c757d;
        font-weight: 600;
    }
    .fc .fc-today-button:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }
    .fc-theme-standard .fc-scrollgrid {
        border: none;
    }
    .fc .fc-col-header-cell {
        background-color: #3eb489; /* Mint Green Header */
        border-color: #3eb489;
        padding: 8px 0;
    }
    .fc .fc-col-header-cell-cushion {
        display: inline-block;
        background: #fff;
        color: #3eb489 !important;
        padding: 4px 15px;
        border-radius: 8px;
        font-weight: 700;
        text-decoration: none !important;
        font-size: 0.85rem;
    }
    .fc .fc-daygrid-day-number {
        color: #666;
        padding: 8px 12px;
        font-size: 0.95rem;
        text-decoration: underline !important;
        font-weight: 500;
    }
    .fc .fc-event {
        background-color: #3eb489; /* Mint Green Events */
        border: none;
        padding: 5px 10px;
        border-radius: 20px; /* Capsule shape */
        font-size: 0.75rem;
        font-weight: 600;
        margin: 2px 4px;
        box-shadow: 0 2px 4px rgba(62,180,137,0.2);
    }
    .fc .fc-day-today {
        background-color: #f0fff4 !important; /* Very light mint */
    }
    .fc .fc-day-other {
        background-color: #fafafa;
    }
    .fc .fc-day-other .fc-daygrid-day-number {
        color: #ccc;
    }

    /* List View Customization */
    .fc .fc-list-day-cushion {
        background-color: #fafafa !important;
        text-align: left !important;
        padding: 12px 20px !important;
    }
    .fc .fc-list-day-text {
        color: #3eb489 !important; /* Mint Green Date Heading */
        font-weight: 700;
        text-decoration: none !important;
    }
    .fc .fc-list-event-dot {
        border-color: #ef4444 !important; /* Red Dot */
    }
</style>

<?php include 'footer.php'; ?>
