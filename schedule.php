<?php
require_once 'config.php';
include 'header.php';

// Fetch buildings for filter
$stmt = $pdo->query("SELECT * FROM buildings ORDER BY name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0">Jadwal Penggunaan Gedung</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <div class="row mb-4 align-items-center">
                <div class="col-md-4">
                    <select id="buildingFilter" class="form-select bg-light border-0 shadow-none">
                        <option value="">Semua Gedung</option>
                        <?php foreach($buildings as $building): ?>
                            <option value="<?= $building['id'] ?>"><?= htmlspecialchars($building['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text xsmall mt-1"><i>Pilih salah satu gedung yang ingin dilihat jadwalnya.</i></div>
                </div>
            </div>
          <div id="calendar" class="bg-light p-3 rounded border" style="min-height: 600px;"></div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Modal -->
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
    var calendarEl = document.getElementById('calendar');
    var buildingFilter = document.getElementById('buildingFilter');
    
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
        events: 'api_calendar.php',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
            meridiem: false
        },
        eventClick: function(info) {
            var start = info.event.start;
            var end = info.event.end;
            var startStr = start ? start.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
            var endStr = end ? end.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', hour12: false}) : '';
            var buildingName = info.event.extendedProps && info.event.extendedProps.building_name ? info.event.extendedProps.building_name : '';
            
            var msg = `
                <strong>Acara:</strong> ${info.event.title}<br>
                ${buildingName ? '<strong>Gedung:</strong> ' + buildingName + '<br>' : ''}
                <strong>Waktu:</strong> ${startStr} WITA s.d ${endStr} WITA
            `;
            
            showModal(msg);
        }
    });
    calendar.render();

    // Filter change handler
    buildingFilter.addEventListener('change', function() {
        var buildingId = this.value;
        var url = 'api_calendar.php';
        if (buildingId) {
            url += '?building_id=' + buildingId;
        }
        
        var oldSource = calendar.getEventSources()[0];
        if (oldSource) oldSource.remove();
        calendar.addEventSource(url);
    });
});

function showModal(message) {
    var modalBody = document.getElementById('modalMessage');
    modalBody.innerHTML = message;
    var myModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
    myModal.show();
}
</script>

<style>
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
