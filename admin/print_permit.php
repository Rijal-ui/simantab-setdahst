<?php
require_once 'auth_check.php';
require_once '../config.php';

$booking_id = $_GET['id'] ?? null;
$booking = null;

if ($booking_id) {
    $stmt = $pdo->prepare("
        SELECT b.*, g.name as building_name, g.location as building_location
        FROM bookings b 
        JOIN buildings g ON b.building_id = g.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking || $booking['status'] !== 'approved') {
    die('Data booking tidak ditemukan atau belum disetujui.');
}

// Format date to Indonesian
function formatTanggalIndo($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

function getHariIndo($tanggal) {
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $date = date('l', strtotime($tanggal));
    return $hari[$date];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Izin Pemakaian Gedung - <?= htmlspecialchars($booking['event_name']) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.3; margin: 0; padding: 0; background-color: #f4f4f4; color: #000; }
        .page { width: 210mm; min-height: 297mm; padding: 15mm 20mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; }
        
        /* Kop Surat */
        .kop-surat { border-bottom: 4px double #000; padding-bottom: 5px; margin-bottom: 15px; text-align: center; position: relative; }
        .kop-surat img { position: absolute; left: 0; top: 0; width: 65px; }
        .kop-surat .instansi { font-size: 14pt; margin: 0; font-weight: normal; }
        .kop-surat .setda { font-size: 16pt; margin: 0; font-weight: bold; text-transform: uppercase; }
        .kop-surat .alamat { font-size: 9pt; margin: 0; font-weight: normal; }
        .kop-surat .kontak { font-size: 9pt; margin: 0; font-weight: normal; }
        .kop-surat .kontak a { color: #000; text-decoration: none; }

        /* Header Info */
        .header-info { width: 100%; margin-bottom: 20px; }
        .header-info td { vertical-align: top; }
        .info-kiri { width: 60%; }
        .info-kanan { width: 40%; }
        .tgl-surat { text-align: right; margin-bottom: 15px; }

        /* Isi Surat */
        .isi-surat { text-align: justify; margin-bottom: 15px; }
        .detail-kegiatan { margin: 10px 0 10px 50px; }
        .detail-kegiatan td { padding: 2px 5px; }
        .detail-kegiatan td:first-child { width: 100px; font-weight: bold; }

        /* List Persyaratan */
        .persyaratan { margin-left: 0; padding-left: 20px; list-style-type: decimal; }
        .persyaratan li { margin-bottom: 5px; text-align: justify; }

        /* Tanda Tangan */
        .ttd-container { margin-top: 30px; width: 100%; }
        .ttd-box { float: right; width: 300px; text-align: center; position: relative; }
        .ttd-box .jabatan { margin-bottom: 10px; font-weight: normal; }
        .ttd-box .signature-space { height: 80px; display: flex; align-items: center; justify-content: center; margin-bottom: 5px; }
        .ttd-box .signature-space img { max-height: 80px; max-width: 200px; }
        .ttd-box .nama { font-weight: bold; margin-bottom: 0; }

        /* Signature Modal */
        #signatureModal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 90%;
            max-width: 500px;
        }
        #signatureCanvas {
            border: 1px solid #ccc;
            background: #f9f9f9;
            cursor: crosshair;
            touch-action: none;
            width: 100%;
            height: 200px;
        }
        .modal-buttons { margin-top: 15px; display: flex; gap: 10px; justify-content: center; }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }

        /* Tembusan & Catatan */
        .tembusan { margin-top: 30px; font-size: 10pt; }
        .tembusan h5 { margin: 0; text-decoration: underline; font-weight: normal; }
        .catatan { margin-top: 30px; font-size: 10pt; }
        .catatan h5 { margin: 0; font-weight: bold; }

        @media print {
            body { background: none; margin: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 10mm 15mm; }
            .no-print { display: none; }
            #signatureModal { display: none !important; }
        }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="page">
        <!-- Kop Surat -->
        <div class="kop-surat">
            <img src="../assets/hst.png" alt="Logo HST">
            <h1 class="instansi">PEMERINTAH KABUPATEN HULU SUNGAI TENGAH</h1>
            <h2 class="setda">SEKRETARIAT DAERAH</h2>
            <p class="alamat">Jalan Perwira Nomor 01 Barabai, Hulu Sungai Tengah, Kalimantan Selatan 71311</p>
            <p class="kontak">Faxsimile (0517) 41029, Laman <a href="https://setda.hstkab.go.id">https://setda.hstkab.go.id</a>, Pos-el <a href="mailto:setda@hstkab.go.id">setda@hstkab.go.id</a></p>
        </div>

        <div class="tgl-surat">
            Barabai, <?= formatTanggalIndo(date('Y-m-d')) ?>
        </div>

        <table class="header-info">
            <tr>
                <td class="info-kiri">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 80px;">Nomor</td>
                            <td style="width: 10px;">:</td>
                            <td>900.1.13.1 / &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /Umum/2026</td>
                        </tr>
                        <tr>
                            <td>Lampiran</td>
                            <td>:</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>Hal</td>
                            <td>:</td>
                            <td>Izin Pemakaian Gedung <?= htmlspecialchars($booking['building_name']) ?></td>
                        </tr>
                    </table>
                </td>
                <td class="info-kanan">
                    Kepada<br>
                    Yth. <?= htmlspecialchars($booking['booker_name']) ?><br>
                    <br>
                    di -<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Barabai
                </td>
            </tr>
        </table>

        <div class="isi-surat">
            Sehubungan dengan Surat Permohonan Saudara Nomor : - Tanggal <?= formatTanggalIndo(date('Y-m-d', strtotime($booking['created_at']))) ?> Perihal Mohon Ijin Pemakaian Gedung <?= htmlspecialchars($booking['building_name']) ?> pada Acara Kegiatan <?= htmlspecialchars($booking['event_name']) ?> diberitahukan bahwa pada prinsipnya permohonan Saudara untuk Pelaksanaan tersebut dapat kami setujui.
        </div>

        <table class="detail-kegiatan">
            <tr>
                <td>Hari</td>
                <td>:</td>
                <td><?= getHariIndo($booking['booking_date']) ?></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>:</td>
                <td><?= formatTanggalIndo($booking['booking_date']) ?></td>
            </tr>
            <tr>
                <td>Waktu</td>
                <td>:</td>
                <td>Jam <?= date('H:i', strtotime($booking['start_time'])) ?> s/d selesai</td>
            </tr>
        </table>

        <div class="isi-surat">
            Untuk itu diharapkan Saudara dapat memenuhi beberapa persyaratan sebagai berikut :
        </div>

        <ol class="persyaratan">
            <li>Menggunakan tempat sesuai dengan kegiatan tersebut di atas;</li>
            <li>Menjaga, memelihara kebersihan, dan bertanggung jawab terhadap fasilitas yang tersedia;</li>
            <li>Bersedia mengganti apabila terjadi kerusakan sebagai akibat dari kegiatan tersebut;</li>
            <li>Membayar Tarif Retribusi Pemakaian Kekayaan Daerah Sesuai dengan Peraturan Daerah Kabupaten Hulu Sungai Tengah Nomor 1 Tahun 2025 tentang Pajak Daerah dan Retribusi Daerah;</li>
            <li>Melaksanakan ketentuan sebagaimana tercantum pada Peraturan Bupati Hulu Sungai Tengah Nomor 1 Tahun 2025 tentang Pajak Daerah dan Retribusi Daerah Kabupaten Hulu Sungai Tengah.</li>
        </ol>

        <div class="isi-surat" style="margin-top: 15px;">
            Demikian disampaikan untuk dapat dipergunakan sebagaimana mestinya.
        </div>

        <div class="ttd-container">
            <div class="ttd-box">
                <div class="jabatan">
                    Sekretaris Daerah<br>
                    Kabupaten Hulu Sungai Tengah,
                </div>
                <div class="signature-space" id="signatureDisplay">
                    <!-- Signature image will appear here -->
                </div>
                <div class="nama">
                    Muhammad Yani
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <div class="tembusan">
            <h5><u>Tembusan :</u></h5>
            Kepala Bagian Umum Sekretariat Daerah Kab. HST di Barabai
        </div>

        <div class="catatan">
            <h5>Catatan :</h5>
            Videotron jangan di tempel Spanduk
        </div>
    </div>

    <!-- Signature Modal -->
    <div id="signatureModal">
        <div class="modal-content">
            <h3 style="margin-top: 0;">Tanda Tangan</h3>
            <canvas id="signatureCanvas"></canvas>
            <div class="modal-buttons">
                <button type="button" class="btn btn-danger" id="clearBtn">Hapus</button>
                <button type="button" class="btn btn-secondary" id="closeModalBtn">Batal</button>
                <button type="button" class="btn btn-primary" id="saveBtn">Simpan</button>
            </div>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px; padding-bottom: 20px;">
        <button onclick="openSignatureModal()" style="padding: 10px 20px; cursor: pointer; background: #28a745; color: #fff; border: none; border-radius: 5px; font-weight: bold;">Tanda Tangani</button>
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #007bff; color: #fff; border: none; border-radius: 5px; font-weight: bold; margin-left: 10px;">Cetak Surat</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #6c757d; color: #fff; border: none; border-radius: 5px; margin-left: 10px;">Tutup</button>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        const modal = document.getElementById('signatureModal');
        const signatureDisplay = document.getElementById('signatureDisplay');
        let isDrawing = false;

        // Set canvas size
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            ctx.scale(ratio, ratio);
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
        }

        function openSignatureModal() {
            modal.style.display = 'flex';
            resizeCanvas();
        }

        function closeSignatureModal() {
            modal.style.display = 'none';
        }

        function startDrawing(e) {
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        window.addEventListener('mouseup', stopDrawing);

        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            startDrawing(e);
        }, { passive: false });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            draw(e);
        }, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        document.getElementById('clearBtn').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });

        document.getElementById('closeModalBtn').addEventListener('click', closeSignatureModal);

        document.getElementById('saveBtn').addEventListener('click', () => {
            const dataURL = canvas.toDataURL();
            signatureDisplay.innerHTML = `<img src="${dataURL}" alt="Signature">`;
            closeSignatureModal();
        });
    </script>
</body>
</html>