<?php
require 'config.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: kasir.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id = ?");
$stmt->execute([$id]);
$jual = $stmt->fetch();
if (!$jual) { header('Location: kasir.php'); exit; }

// Polling status Midtrans untuk QRIS/Transfer yang masih pending
if (in_array($jual['metode'], ['qris','transfer'])
    && $jual['payment_status'] === 'pending'
    && $jual['order_id']) {

    $result = cekStatusMidtrans($jual['order_id']);
    if ($result) {
        $txStatus = $result['transaction_status'] ?? '';
        $newStatus = null;
        if (in_array($txStatus, ['capture','settlement'])) $newStatus = 'paid';
        elseif ($txStatus === 'expire')                    $newStatus = 'expired';
        elseif (in_array($txStatus, ['deny','cancel']))    $newStatus = 'failed';

        if ($newStatus && $newStatus !== $jual['payment_status']) {
            $pdo->prepare("UPDATE penjualan SET payment_status=? WHERE id=?")
                ->execute([$newStatus, $id]);
            $jual['payment_status'] = $newStatus;
        }
    }
}

$items = $pdo->prepare("SELECT * FROM penjualan_item WHERE penjualan_id = ?");
$items->execute([$id]);
$daftarItem = $items->fetchAll();

$metodeLabel  = ['cash' => 'Cash', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
$payLabel     = [
    'pending' => ['text' => '⏳ Menunggu Pembayaran', 'class' => 'pay-pending'],
    'paid'    => ['text' => '✅ Lunas',               'class' => 'pay-paid'],
    'failed'  => ['text' => '❌ Gagal',               'class' => 'pay-failed'],
    'expired' => ['text' => '⌛ Kadaluarsa',          'class' => 'pay-expired'],
];
$snapToken    = $jual['snap_token'] ?? null;
$payStatus    = $jual['payment_status'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk #<?= $id ?></title>
    <link rel="stylesheet" href="style.css">
    <?php if ($snapToken && $payStatus === 'pending'): ?>
    <script src="<?= MIDTRANS_SNAP_URL ?>"
            data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
    <?php endif; ?>
    <style>
        @media print { header, .no-print { display:none !important; } body { background:#fff; } }
        .struk-box { background:#fff; max-width:400px; margin:24px auto; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,.1); overflow:hidden; }
        .struk-head { background:#0ea5e9; color:#fff; text-align:center; padding:24px 16px 16px; }
        .struk-head h2 { font-size:20px; margin-bottom:4px; }
        .struk-head p  { font-size:13px; opacity:.85; margin:0; }
        .struk-body { padding:20px; }
        .struk-info { display:flex; justify-content:space-between; font-size:13px; color:#64748b; margin-bottom:16px; padding-bottom:12px; border-bottom:1px dashed #e2e8f0; }
        .struk-item { display:flex; justify-content:space-between; align-items:center; padding:8px 0; font-size:14px; border-bottom:1px solid #f1f5f9; }
        .struk-item .nama { color:#334155; font-weight:600; }
        .struk-item .qty  { color:#94a3b8; font-size:12px; }
        .struk-item .sub  { font-weight:700; color:#1e293b; }
        .struk-total { display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:16px; border-top:2px solid #0ea5e9; font-size:18px; font-weight:700; color:#1e293b; }
        .struk-footer { text-align:center; padding:14px; background:#f0f9ff; font-size:12px; color:#0ea5e9; font-weight:600; border-top:1px dashed #bae6fd; }

        .pay-status { text-align:center; padding:12px; border-radius:8px; font-size:14px; font-weight:700; margin-top:14px; }
        .pay-pending { background:#fef9c3; color:#854d0e; }
        .pay-paid    { background:#dcfce7; color:#166534; }
        .pay-failed  { background:#fee2e2; color:#991b1b; }
        .pay-expired { background:#f1f5f9; color:#475569; }

        .btn-pay { display:block; width:100%; margin-top:12px; padding:13px; background:#0ea5e9; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; transition:background .15s; }
        .btn-pay:hover { background:#0284c7; }
        .btn-cek { display:block; width:100%; margin-top:8px; padding:10px; background:#e2e8f0; color:#334155; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
<header>
    <div class="brand">💧 Air Isi Ulang</div>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="kasir.php">Kasir</a>
        <a href="laporan.php">Laporan</a>
        <a href="produk.php">Produk</a>
    </nav>
</header>

<div class="no-print" style="max-width:400px;margin:24px auto 0;padding:0 16px;display:flex;gap:10px;flex-wrap:wrap">
    <a href="kasir.php" style="flex:1;text-align:center;padding:10px;background:#e2e8f0;border-radius:7px;text-decoration:none;color:#334155;font-weight:600;font-size:14px">
        + Transaksi Baru
    </a>
    <button onclick="window.print()" style="flex:1;padding:10px;background:#0ea5e9;border:none;border-radius:7px;color:#fff;font-weight:600;font-size:14px;cursor:pointer">
        🖨 Cetak Struk
    </button>
    <?php if ($jual['status'] === 'aktif'): ?>
    <form method="POST" action="laporan.php" style="flex:1" onsubmit="return confirm('Batalkan transaksi ini?')">
        <input type="hidden" name="aksi" value="cancel">
        <input type="hidden" name="id" value="<?= (int)$jual['id'] ?>">
        <button type="submit" style="width:100%;padding:10px;background:#ef4444;border:none;border-radius:7px;color:#fff;font-weight:600;font-size:14px;cursor:pointer">
            ✕ Cancel
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="struk-box">
    <div class="struk-head">
        <h2>💧 Air Isi Ulang</h2>
        <p>Terima kasih atas pembelian Anda</p>
    </div>

    <div class="struk-body">
        <div class="struk-info">
            <span><?= e(date('d/m/Y H:i', strtotime($jual['dibuat_pada']))) ?></span>
            <span>No. <?= str_pad($jual['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </div>

        <?php if ($jual['status'] === 'dibatalkan'): ?>
        <div style="text-align:center;background:#fee2e2;color:#991b1b;font-weight:700;padding:10px;border-radius:6px;margin-bottom:12px">
            ✕ TRANSAKSI DIBATALKAN
        </div>
        <?php endif; ?>

        <?php foreach ($daftarItem as $item): ?>
        <div class="struk-item">
            <div>
                <div class="nama"><?= e($item['nama_produk']) ?></div>
                <div class="qty"><?= $item['qty'] ?> × <?= rupiah($item['harga']) ?></div>
            </div>
            <div class="sub"><?= rupiah($item['subtotal']) ?></div>
        </div>
        <?php endforeach; ?>

        <div class="struk-total">
            <span>Total</span>
            <span><?= rupiah($jual['total']) ?></span>
        </div>

        <div style="text-align:center;margin-top:10px;font-size:13px;color:#64748b">
            Metode: <strong><?= $metodeLabel[$jual['metode']] ?? strtoupper(e($jual['metode'])) ?></strong>
            <?php if ($jual['keterangan']): ?>
            &nbsp;·&nbsp; <?= e($jual['keterangan']) ?>
            <?php endif; ?>
        </div>

        <?php if ($payStatus && isset($payLabel[$payStatus])): ?>
        <div class="pay-status <?= $payLabel[$payStatus]['class'] ?>">
            <?= $payLabel[$payStatus]['text'] ?>
        </div>
        <?php endif; ?>

        <?php if ($snapToken && $payStatus === 'pending'): ?>
        <button class="btn-pay" id="btn-bayar-sekarang"
                onclick="bayarSekarang()">
            💳 Bayar Sekarang
        </button>
        <button class="btn-cek" onclick="window.location.reload()">
            🔄 Cek Status Pembayaran
        </button>
        <?php endif; ?>        <?php if ($jual['metode'] === 'qris' && $payStatus === 'pending' && !empty($jual['qr_string'])): ?>
        <div style="background:#f8fafc;border-radius:8px;padding:14px;margin-top:14px;text-align:center">
            <p style="font-size:13px;color:#64748b;margin-bottom:8px">Scan QR di aplikasi e-wallet kamu</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($jual['qr_string']) ?>"
                 alt="QR Code" style="width:200px;height:200px;border-radius:8px">
            <p style="font-size:11px;color:#94a3b8;margin-top:8px">
                Untuk Sandbox, gunakan simulator:<br>
                <a href="https://simulator.sandbox.midtrans.com/qris/index" target="_blank">
                    simulator.sandbox.midtrans.com/qris/index
                </a><br>
                <button onclick="copyQR()" style="margin-top:6px;padding:6px 14px;background:#0ea5e9;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">
                    📋 Copy QR String
                </button>
                <span id="copy-status" style="font-size:11px;color:#22c55e;display:none"> ✓ Tersalin!</span>
                <textarea id="qr-string-text" style="position:absolute;left:-9999px"><?= e($jual['qr_string']) ?></textarea>
            </p>
        </div>
        <button class="btn-cek" onclick="window.location.reload()" style="margin-top:8px">
            🔄 Cek Status Pembayaran
        </button>
        <?php endif; ?>
    </div>

    <div class="struk-footer">Selamat datang kembali! 💧</div>
</div>

<?php if ($snapToken && $payStatus === 'pending'): ?>
<script>
function bayarSekarang() {
    window.snap.pay('<?= e($snapToken) ?>', {
        onSuccess: function(result) { window.location.reload(); },
        onPending: function(result) { window.location.reload(); },
        onError:   function(result) { alert('Pembayaran gagal.'); },
        onClose:   function() {}
    });
}
</script>
<?php endif; ?>

<script>
function copyQR() {
    const el = document.getElementById('qr-string-text');
    if (!el) return;
    el.select();
    document.execCommand('copy');
    const status = document.getElementById('copy-status');
    if (status) { status.style.display = 'inline'; setTimeout(() => status.style.display = 'none', 2000); }
}
</script>
</body>
</html>
