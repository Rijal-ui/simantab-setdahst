<?php
require_once 'auth_check.php';
require_once '../config.php';

$booking_id = $_GET['id'] ?? null;
$booking = null;

if ($booking_id) {
    $stmt = $pdo->prepare("
        SELECT b.*, g.name as building_name, i.amount as total_amount, i.id as invoice_id, i.created_at as invoice_date
        FROM bookings b 
        JOIN buildings g ON b.building_id = g.id 
        LEFT JOIN invoices i ON b.id = i.booking_id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking || !$booking['invoice_id']) {
    die('Data SKR-D tidak ditemukan.');
}

function terbilang($nilai) {
    $nilai = abs($nilai);
    $huruf = array("", "Sepuluh", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    if ($nilai == 10) return "Sepuluh"; // Override for "Sepuluh Juta" style
    
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $temp = "";
    if ($nilai < 12) {
        $temp = " ". $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = terbilang($nilai - 10). " Belas";
    } else if ($nilai < 100) {
        $temp = terbilang($nilai / 10). " Puluh". terbilang($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . terbilang($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = terbilang($nilai / 100) . " Ratus" . terbilang($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . terbilang($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = terbilang($nilai / 1000) . " Ribu" . terbilang($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = terbilang($nilai / 1000000) . " Juta" . terbilang($nilai % 1000000);
    }
    return trim($temp);
}

function formatTanggalIndo($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SKR-D - <?= htmlspecialchars($booking['invoice_id']) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; line-height: 1.2; margin: 0; padding: 0; background-color: #f4f4f4; color: #000; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm 15mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; }
        
        /* Kop Surat */
        .kop-surat { border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 15px; text-align: center; position: relative; }
        .kop-surat::after { content: ""; display: block; border-bottom: 1px solid #000; margin-top: 2px; }
        .kop-surat img { position: absolute; left: 0; top: 0; width: 65px; }
        .kop-surat .instansi { font-size: 14pt; margin: 0; font-weight: normal; letter-spacing: 1px; }
        .kop-surat .setda { font-size: 16pt; margin: 0; font-weight: bold; text-transform: uppercase; }
        .kop-surat .alamat { font-size: 9pt; margin: 0; font-weight: normal; }
        .kop-surat .kontak { font-size: 9pt; margin: 0; font-weight: normal; }

        .judul-skrd { text-align: center; margin: 15px 0; }
        .judul-skrd h1 { font-size: 11pt; margin: 0; text-transform: uppercase; font-weight: bold; text-decoration: underline; }
        .judul-skrd p { font-size: 11pt; margin: 0; font-weight: bold; }

        .top-info { width: 100%; position: relative; margin-bottom: 10px; }
        .no-urut-container { position: absolute; right: 0; top: 0; display: flex; align-items: center; }
        .no-urut-label { margin-right: 15px; font-size: 10pt; }
        .no-urut-box { border: 1px solid #000; padding: 3px 20px; font-weight: bold; min-width: 100px; text-align: center; background: #fff; }

        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { vertical-align: top; padding: 1px 0; }
        .label { width: 80px; }
        .colon { width: 15px; }

        table.main-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.main-table th, table.main-table td { border: 1px solid #000; padding: 4px 8px; font-size: 9.5pt; }
        table.main-table th { font-weight: bold; text-align: center; background-color: #e9ecef; }
        
        /* Double line effect for specific rows */
        .double-top { border-top: 3px double #000 !important; }
        
        .right { text-align: right; }
        .center { text-align: center; }
        
        .terbilang-row { display: flex; border: 1px solid #000; border-top: none; }
        .terbilang-label { padding: 4px 8px; border-right: 1px solid #000; font-weight: normal; width: 80px; font-size: 9pt; }
        .terbilang-value { padding: 4px 8px; font-weight: bold; flex-grow: 1; font-size: 9.5pt; }

        .perhatian { margin-top: 15px; font-size: 9pt; }
        .perhatian h5 { margin: 0 0 3px 0; font-weight: normal; }

        .signature-section { margin-top: 25px; text-align: right; }
        .signature-box { display: inline-block; width: 300px; text-align: center; position: relative; }
        .signature-space { height: 70px; display: flex; align-items: center; justify-content: center; }
        .signature-space img { max-height: 70px; max-width: 200px; }
        .signature-name { font-weight: bold; text-decoration: underline; }

        .tanda-terima { margin-top: 30px; border-top: 1px solid #000; padding-top: 15px; }
        .tanda-terima-header { background-color: #dee2e6; border: 1px solid #000; text-align: center; font-weight: bold; padding: 4px; margin-bottom: 15px; text-transform: uppercase; font-size: 9pt; }
        
        .dotted-line { border-bottom: 1px dotted #000; width: 250px; display: inline-block; height: 15px; vertical-align: bottom; }

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

        @media print {
            body { background: none; margin: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
            #signatureModal { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="kop-surat">
            <img src="../assets/hst.png" alt="Logo HST">
            <h1 class="instansi">PEMERINTAH KABUPATEN HULU SUNGAI TENGAH</h1>
            <h2 class="setda">SEKRETARIAT DAERAH</h2>
            <p class="alamat">Jalan Perwira Nomor 01 Barabai, Hulu Sungai Tengah, Kalimantan Selatan 71311</p>
            <p class="kontak">Faxsimile (0517) 41029, Laman https://setda.hstkab.go.id, Pos-el setda@hstkab.go.id</p>
        </div>

        <div class="judul-skrd">
            <h1>SURAT KETETAPAN RETRIBUSI DAERAH</h1>
            <p>(SKR-D)</p>
        </div>

        <div class="top-info">
            <div class="no-urut-container">
                <span class="no-urut-label">No. Urut</span>
                <div class="no-urut-box"><?= htmlspecialchars($booking['invoice_id']) ?></div>
            </div>
            
            <table class="info-table">
                <tr>
                    <td class="label">Masa</td>
                    <td class="colon">:</td>
                    <td><?= formatTanggalIndo($booking['invoice_date']) ?></td>
                </tr>
                <tr>
                    <td class="label">Tahun</td>
                    <td class="colon">:</td>
                    <td><?= date('Y', strtotime($booking['invoice_date'])) ?></td>
                </tr>
                <tr>
                    <td class="label">Nama</td>
                    <td class="colon">:</td>
                    <td><?= htmlspecialchars($booking['booker_name']) ?> (<?= htmlspecialchars($booking['organization'] ?: '-') ?>)</td>
                </tr>
                <tr>
                    <td class="label">Alamat</td>
                    <td class="colon">:</td>
                    <td>BARABAI</td>
                </tr>
            </table>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 140px;">Kode Rekening</th>
                    <th>Uraian</th>
                    <th style="width: 140px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">1</td>
                    <td class="center">4. 1. 2. 02. 01. 0001</td>
                    <td>Retribusi Sewa <?= htmlspecialchars($booking['building_name']) ?></td>
                    <td class="right"><?= number_format($booking['total_amount'], 0, ',', '.') ?></td>
                </tr>
                <!-- Spacer rows -->
                <tr><td style="border-bottom:none; border-top:none;">&nbsp;</td><td style="border-bottom:none; border-top:none;"></td><td style="border-bottom:none; border-top:none;"></td><td style="border-bottom:none; border-top:none;"></td></tr>
                
                <tr class="double-top">
                    <td colspan="2" style="border-bottom:none; border-top:none;"></td>
                    <td>Jumlah Ketetapan Pokok Retribusi</td>
                    <td class="right"><?= number_format($booking['total_amount'], 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="border-bottom:none; border-top:none;"></td>
                    <td>Jumlah Sanksi</td>
                    <td class="right">-</td>
                </tr>
                <tr>
                    <td colspan="2" style="border-bottom:none; border-top:none;"></td>
                    <td>&nbsp;&nbsp;- Bunga</td>
                    <td class="right">-</td>
                </tr>
                <tr>
                    <td colspan="2" style="border-bottom:none; border-top:none;"></td>
                    <td>&nbsp;&nbsp;- Kenaikan</td>
                    <td class="right">-</td>
                </tr>
                <tr style="font-weight: bold;">
                    <td colspan="2" style="border-top: 1px solid #000;"></td>
                    <td style="border-top: 1px solid #000;">Jumlah Keseluruhan</td>
                    <td class="right" style="border-top: 1px solid #000;"><?= number_format($booking['total_amount'], 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="terbilang-row">
            <div class="terbilang-label">Dengan Huruf</div>
            <div class="terbilang-value"><?= terbilang($booking['total_amount']) ?> Rupiah</div>
        </div>

        <div class="perhatian">
            <h5>PERHATIAN :</h5>
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 20px; vertical-align: top;">1.</td>
                    <td>Harap peyetoran dilakukan melalui Bank Kalsel Cabang Barabai dengan menggunakan Surat Tanda Setoran (STS)</td>
                </tr>
                <tr>
                    <td style="width: 20px; vertical-align: top;">2.</td>
                    <td>Apabila wajib pajak tidak membayar tepat waktunya atau kurang membayar dikenakan sanksi administrasi berupa bunga sebesar 2% per bulan</td>
                </tr>
            </table>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <p>Barabai, <?= formatTanggalIndo(date('Y-m-d')) ?></p>
                <p style="margin-top: -10px;">Sekretaris Daerah Kab. Hulu Sungai Tengah</p>
                <div class="signature-space" id="signatureDisplay">
                    <!-- Signature image will appear here -->
                </div>
                <p class="signature-name">Drs. H. MUHAMMAD YANI, M.Si</p>
                <p class="signature-nip">NIP. 19660826 198602 1 003</p>
            </div>
        </div>

        <div class="tanda-terima">
            <div class="tanda-terima-header">TANDA TERIMA</div>
            <table class="info-table" style="margin-left: 10px;">
                <tr>
                    <td class="label">Nama</td>
                    <td class="colon">:</td>
                    <td><?= htmlspecialchars($booking['booker_name']) ?> (<?= htmlspecialchars($booking['organization'] ?: '-') ?>)</td>
                </tr>
                <tr>
                    <td class="label">Alamat</td>
                    <td class="colon">:</td>
                    <td>BARABAI</td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td class="colon">:</td>
                    <td><?= formatTanggalIndo(date('Y-m-d')) ?></td>
                </tr>
                <tr>
                    <td class="label">Tanda Tangan</td>
                    <td class="colon">:</td>
                    <td><div class="dotted-line"></div></td>
                </tr>
            </table>
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
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #007bff; color: #fff; border: none; border-radius: 5px; font-weight: bold; margin-left: 10px;">Cetak SKR-D</button>
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