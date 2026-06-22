<?php
require_once 'config.php';

$invoice_id = $_GET['id'] ?? null;
$invoice = null;

if ($invoice_id) {
    $stmt = $pdo->prepare("
        SELECT 
            i.id as invoice_id, i.amount, i.status as invoice_status, i.created_at as invoice_date,
            b.id as booking_id, b.event_name, b.booking_date, b.start_time, b.end_time,
            bu.name as building_name, bu.description,
            bk.booker_name, bk.booker_email, bk.organization
        FROM invoices i
        JOIN bookings b ON i.booking_id = b.id
        JOIN buildings bu ON b.building_id = bu.id
        JOIN bookings bk ON i.booking_id = bk.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$invoice) {
    die('Invoice tidak ditemukan.');
}

// Fetch booking items
$booking_items = [];
if ($invoice['booking_id']) {
    $itemsStmt = $pdo->prepare("
        SELECT bi.*, i.name as item_name
        FROM booking_items bi
        JOIN items i ON bi.item_id = i.id
        WHERE bi.booking_id = ?
    ");
    $itemsStmt->execute([$invoice['booking_id']]);
    $booking_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= htmlspecialchars($invoice['invoice_id']) ?></title>

<!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/favicon.png">

    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .receipt {
            width: 80mm;
            margin: 20px auto;
            background: #fff;
            padding: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
            display: block;
        }
        .item-details {
            margin-top: 5px;
        }
        .item-row {
            margin-bottom: 5px;
        }
        .item-main {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
        .item-sub {
            font-size: 11px;
            color: #333;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
        }
        .status-stamp {
            border: 2px solid #000;
            display: inline-block;
            padding: 5px 20px;
            font-weight: bold;
            font-size: 16px;
            margin: 15px 0;
            text-transform: uppercase;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 11px;
        }
        @media print {
            body {
                background-color: #fff;
            }
            .receipt {
                width: 80mm;
                margin: 0;
                box-shadow: none;
                padding: 5px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="receipt">
    <div class="header">
        <h1>SI MANTAB BMD</h1>
        <p>Sistem Informasi</p>
        <p>Pemanfaatan Barang Milik Daerah</p>
    </div>

    <div class="divider"></div>

    <div class="info-row">
        <span>Invoice : #<?= htmlspecialchars($invoice['invoice_id']) ?></span>
    </div>
    <div class="info-row">
        <span>Tanggal : <?= date('d M Y', strtotime($invoice['invoice_date'])) ?></span>
    </div>

    <div class="divider"></div>

    <span class="section-title">DITAGIHKAN KEPADA</span>
    <p style="margin: 2px 0;"><?= htmlspecialchars($invoice['booker_name']) ?></p>
    <p style="margin: 2px 0;"><?= htmlspecialchars($invoice['organization'] ?: '-') ?></p>
    <p style="margin: 2px 0;"><?= htmlspecialchars($invoice['booker_email']) ?></p>

    <div class="divider"></div>

    <span class="section-title">DETAIL TAGIHAN</span>
    <div class="item-details">
        <?php
        $total_item_price = 0;
        if (is_array($booking_items)) {
            foreach ($booking_items as $item) {
                $total_item_price += $item['quantity'] * $item['price_at_booking'];
            }
        }
        $building_cost = $invoice['amount'] - $total_item_price;
        ?>

        <?php if ($building_cost > 0): ?>
        <div class="item-row">
            <div class="item-main">
                <span>Sewa Gedung - <?= htmlspecialchars($invoice['building_name']) ?></span>
                <span>Rp <?= number_format($building_cost, 0, ',', '.') ?></span>
            </div>
            <div class="item-sub">
                <?= date('d M Y', strtotime($invoice['booking_date'])) ?> | <?= date('H:i', strtotime($invoice['start_time'])) ?> WITA s.d <?= date('H:i', strtotime($invoice['end_time'])) ?> WITA
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_array($booking_items)): foreach($booking_items as $item): ?>
        <div class="item-row">
            <div class="item-main">
                <span><?= htmlspecialchars($item['item_name']) ?> (<?= $item['quantity'] ?>x)</span>
                <span>Rp <?= number_format($item['quantity'] * $item['price_at_booking'], 0, ',', '.') ?></span>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="divider"></div>

    <div class="total-row">
        <span>TOTAL</span>
        <span>Rp <?= number_format($invoice['amount'], 0, ',', '.') ?></span>
    </div>

    <div class="divider"></div>

    <div style="text-align: center;">
        <?php if ($invoice['invoice_status'] == 'paid'): ?>
            <div class="status-stamp">LUNAS</div>
        <?php else: ?>
            <div class="status-stamp" style="border-color: #ccc; color: #ccc;">BELUM BAYAR</div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <div class="footer">
        Silakan lakukan pembayaran ke no rekening BANK KALSEL 3208129286 a.n SEWA TANAH BANGUNAN PERUMAHAN/GEDUNG TEMPAT TINGGAL<br>
        Konfirmasi ke nomor 0853-4604-2831<br>
        Terima kasih atas kepercayaan Anda
    </div>
</div>

<div class="no-print" style="text-align: center; margin-top: 20px; padding-bottom: 20px;">
    <button onclick="saveAsPDF()" style="padding: 10px 20px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px; font-weight: bold;">Simpan PDF</button>
    <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #6c757d; color: #fff; border: none; border-radius: 5px; margin-left: 10px;">Tutup</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function saveAsPDF() {
    const element = document.querySelector('.receipt');
    const invoiceId = '<?= str_pad($invoice['invoice_id'], 6, '0', STR_PAD_LEFT) ?>';
    
    // Opsi konfigurasi untuk html2pdf
    const opt = {
        margin: 0,
        filename: 'Invoice-' + invoiceId + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: [80, 250], orientation: 'portrait' } // Ukuran 80mm lebar
    };

    // Jalankan konversi
    html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
        // Otomatis download
    }).save();
}
</script>

</body>
</html>
