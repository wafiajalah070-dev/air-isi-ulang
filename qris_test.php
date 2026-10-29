<?php
require 'config.php';

$pesan = '';
$result = null;

// Trigger accept/settlement QRIS via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $aksi    = $_POST['aksi'] ?? 'status';

    if ($orderId) {
        $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

        if ($aksi === 'status') {
            // Cek status
            $url = "https://api.sandbox.midtrans.com/v2/$orderId/status";
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Basic ' . $auth,
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp   = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($resp, true);
            $pesan  = 'Status transaksi:';

        } elseif ($aksi === 'settle') {
            // Coba trigger settlement via API dulu
            $url = "https://api.sandbox.midtrans.com/v2/$orderId/settlement";
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '{}',
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $auth,
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp   = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($resp, true);

            // Apapun hasilnya, langsung update database ke paid
            $affected = $pdo->prepare(
                "UPDATE penjualan SET payment_status='paid', status='aktif' WHERE order_id=?"
            );
            $affected->execute([$orderId]);

            if ($affected->rowCount() > 0) {
                $pesan = '✅ Transaksi ' . $orderId . ' berhasil disimulasikan PAID!';
            } else {
                $pesan = '⚠️ Order ID tidak ditemukan di database.';
            }
        }
    }
}

// Ambil daftar transaksi pending
$pending = $pdo->query(
    "SELECT id, order_id, total, metode, dibuat_pada FROM penjualan
     WHERE payment_status = 'pending' AND order_id IS NOT NULL
     ORDER BY dibuat_pada DESC LIMIT 20"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QRIS Test – Sandbox</title>
    <link rel="stylesheet" href="style.css">
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

<main>
    <h1>🧪 QRIS Sandbox Tester</h1>
    <p style="color:#64748b;margin-bottom:20px">Gunakan halaman ini untuk mensimulasikan pembayaran QRIS di Sandbox.</p>

    <?php if ($pesan): ?>
        <div class="pesan sukses"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div style="background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;margin-bottom:20px;font-family:monospace;font-size:13px;overflow-x:auto">
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
        </div>
    <?php endif; ?>

    <!-- Form manual -->
    <div class="form-card-simple" style="margin-bottom:24px">
        <h2 style="margin-bottom:16px">Input Order ID Manual</h2>
        <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="flex:2;min-width:200px">
                <label>Order ID</label>
                <input type="text" name="order_id" placeholder="AIR-YYYYMMDD-XXXXXX" required>
            </div>
            <div class="form-group">
                <label>Aksi</label>
                <select name="aksi">
                    <option value="status">Cek Status</option>
                    <option value="settle">Simulate Paid ✅</option>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit">Jalankan</button>
            </div>
        </form>
    </div>

    <!-- Daftar transaksi pending -->
    <h2>Transaksi Pending</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Order ID</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pending): ?>
                <tr><td colspan="6" class="kosong">Tidak ada transaksi pending.</td></tr>
            <?php else: ?>
                <?php foreach ($pending as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><code><?= e($t['order_id']) ?></code></td>
                    <td><span class="badge badge-<?= e($t['metode']) ?>"><?= strtoupper(e($t['metode'])) ?></span></td>
                    <td><?= rupiah($t['total']) ?></td>
                    <td><?= e(date('d/m H:i', strtotime($t['dibuat_pada']))) ?></td>
                    <td style="display:flex;gap:6px">
                        <!-- Cek Status -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="order_id" value="<?= e($t['order_id']) ?>">
                            <input type="hidden" name="aksi" value="status">
                            <button type="submit" class="btn-cetak">Cek</button>
                        </form>
                        <!-- Simulate Paid -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Simulate pembayaran berhasil untuk <?= e($t['order_id']) ?>?')">
                            <input type="hidden" name="order_id" value="<?= e($t['order_id']) ?>">
                            <input type="hidden" name="aksi" value="settle">
                            <button type="submit" class="btn-lunas">✅ Simulate Paid</button>
                        </form>
                        <!-- Lihat Struk -->
                        <a href="struk.php?id=<?= (int)$t['id'] ?>" class="btn-cetak">Struk</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
