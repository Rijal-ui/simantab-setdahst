<?php
require_once 'auth_check.php';
require_once '../config.php';

$booking_id = $_GET['id'] ?? null;
$booking = null;

if ($booking_id) {
    $stmt = $pdo->prepare("
        SELECT b.*, g.name as building_name, i.amount as total_amount, i.id as invoice_id, i.updated_at as payment_date
        FROM bookings b 
        JOIN buildings g ON b.building_id = g.id 
        LEFT JOIN invoices i ON b.id = i.booking_id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$booking || !$booking['invoice_id']) {
    die('Data pembayaran tidak ditemukan.');
}

function terbilang($nilai) {
    $nilai = abs($nilai);
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
    return $temp;
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
    <title>Tanda Bukti Pembayaran - <?= htmlspecialchars($booking['invoice_id']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; margin: 0; padding: 0; background-color: #f4f4f4; color: #000; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; border: 1px solid #000; }
        
        .header-box { border: 1px solid #000; padding: 10px; display: flex; align-items: center; margin-bottom: 20px; }
        .header-logo { width: 60px; margin-right: 20px; }
        .header-text { flex-grow: 1; text-align: center; }
        .header-text h1 { font-size: 12pt; margin: 0; font-weight: bold; }
        .header-text h2 { font-size: 14pt; margin: 5px 0; font-weight: bold; text-transform: uppercase; }
        .header-text p { font-size: 11pt; margin: 0; font-weight: bold; }

        .content-section { margin-bottom: 20px; }
        .row-data { display: flex; margin-bottom: 5px; }
        .label { width: 180px; }
        .colon { width: 20px; }
        .value { flex-grow: 1; }

        .terbilang-box { font-style: italic; font-weight: bold; padding: 5px 0; }

        table.rincian { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #000; }
        table.rincian th, table.rincian td { border: 1px solid #000; padding: 8px; text-align: center; }
        table.rincian th { background-color: #f2f2f2; font-weight: bold; font-size: 10pt; }
        table.rincian td.left { text-align: left; }
        table.rincian td.right { text-align: right; }
        
        .footer-section { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-box { width: 250px; text-align: center; }
        .signature-space { height: 80px; }
        .signature-name { font-weight: bold; text-decoration: underline; margin-bottom: 0; }
        .signature-nip { margin-top: 0; }

        .bottom-info { margin-top: 50px; font-size: 9pt; }
        .bottom-info div { margin-bottom: 2px; }

        @media print {
            body { background: none; margin: 0; }
            .page { margin: 0; box-shadow: none; border: 1px solid #000; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header-box">
            <img src="../assets/hst.png" alt="Logo" class="header-logo">
            <div class="header-text">
                <h1>PEMERINTAH KABUPATEN HULU SUNGAI TENGAH</h1>
                <h2>TANDA BUKTI PEMBAYARAN</h2>
                <p>NOMOR BUKTI <?= htmlspecialchars($booking['invoice_id']) ?></p>
            </div>
        </div>

        <div class="content-section">
            <div class="row-data">
                <div class="label">Sekretariat Daerah</div>
            </div>
            <div class="row-data">
                <div class="label">Telah menerima uang sebesar</div>
                <div class="colon">:</div>
                <div class="value">Rp. <?= number_format($booking['total_amount'], 0, ',', '.') ?></div>
            </div>
            <div class="row-data">
                <div class="label">(dengan huruf)</div>
                <div class="colon">:</div>
                <div class="value terbilang-box"><?= terbilang($booking['total_amount']) ?> Rupiah</div>
            </div>
            <div class="row-data" style="margin-top: 15px;">
                <div class="label">dari Nama</div>
                <div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($booking['booker_name']) ?> (<?= htmlspecialchars($booking['organization'] ?: '-') ?>)</div>
            </div>
            <div class="row-data">
                <div class="label">Alamat</div>
                <div class="colon">:</div>
                <div class="value">BARABAI</div>
            </div>
            <div class="row-data" style="margin-top: 15px;">
                <div class="label">sebagai pembayaran</div>
                <div class="colon">:</div>
                <div class="value">Retribusi Sewa <?= htmlspecialchars($booking['building_name']) ?></div>
            </div>
        </div>

        <table class="rincian">
            <thead>
                <tr>
                    <th style="width: 50px;">NO.</th>
                    <th style="width: 150px;">KODE REKENING</th>
                    <th>URAIAN RINCIAN OBYEK</th>
                    <th style="width: 150px;">JUMLAH (Rp.)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>4. 1. 2. 02. 01. 0001</td>
                    <td class="left">Retribusi Sewa <?= htmlspecialchars($booking['building_name']) ?></td>
                    <td class="right"><?= number_format($booking['total_amount'], 0, ',', '.') ?></td>
                </tr>
                <tr style="font-weight: bold;">
                    <td colspan="3" style="text-align: right;">JUMLAH</td>
                    <td class="right"><?= number_format($booking['total_amount'], 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            Tanggal diterima uang &nbsp;&nbsp;&nbsp; : &nbsp;&nbsp;&nbsp; <?= formatTanggalIndo($booking['payment_date'] ?: date('Y-m-d')) ?>
        </div>

        <div class="footer-section">
            <div class="signature-box">
                <p>Mengetahui,</p>
                <p style="font-weight: bold; margin-top: -10px;">Bendahara Penerimaan,</p>
                <div class="signature-space"></div>
                <p class="signature-name">TINI</p>
                <p class="signature-nip">NIP. 19830425 201406 1 005</p>
            </div>
            <div class="signature-box">
                <p>Pembayar / Penyetor,</p>
                <div class="signature-space" style="height: 100px;"></div>
                <p class="signature-name"><?= htmlspecialchars(strtoupper($booking['booker_name'])) ?></p>
            </div>
        </div>

        <div class="bottom-info">
            <div class="row-data">
                <div style="width: 100px;">Lembar Asli</div>
                <div style="width: 20px;">:</div>
                <div>untuk pembayar / penyetor / pihak ketiga</div>
            </div>
            <div class="row-data">
                <div style="width: 100px;">Salinan 1</div>
                <div style="width: 20px;">:</div>
                <div>untuk Bendahara Penerimaan / Bendahara Penerimaan Pembantu</div>
            </div>
            <div class="row-data">
                <div style="width: 100px;">Salinan 2</div>
                <div style="width: 20px;">:</div>
                <div>Arsip</div>
            </div>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px; padding-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #007bff; color: #fff; border: none; border-radius: 5px; font-weight: bold;">Cetak Bukti</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #6c757d; color: #fff; border: none; border-radius: 5px; margin-left: 10px;">Tutup</button>
    </div>
</body>
</html>