<?php
require 'config.php';

$hari_ini  = date('Y-m-d');
$bulan_ini = date('Y-m');

$omzetHari = $pdo->prepare(
    "SELECT COALESCE(SUM(total),0) FROM penjualan
     WHERE tanggal = ? AND status = 'aktif'"
);
$omzetHari->execute([$hari_ini]);
$omzetHari = $omzetHari->fetchColumn();

$omzetBulan = $pdo->prepare(
    "SELECT COALESCE(SUM(total),0) FROM penjualan
     WHERE DATE_FORMAT(tanggal,'%Y-%m') = ? AND status = 'aktif'"
);
$omzetBulan->execute([$bulan_ini]);
$omzetBulan = $omzetBulan->fetchColumn();

$jualanHari = $pdo->prepare(
    "SELECT COALESCE(SUM(pi.qty),0) FROM penjualan_item pi
     JOIN penjualan p ON p.id = pi.penjualan_id
     WHERE p.tanggal = ? AND p.status = 'aktif'"
);
$jualanHari->execute([$hari_ini]);
$jualanHari = $jualanHari->fetchColumn();

$txHari = $pdo->prepare(
    "SELECT COUNT(*) FROM penjualan
     WHERE tanggal = ? AND status = 'aktif'"
);
$txHari->execute([$hari_ini]);
$txHari = $txHari->fetchColumn();

// 10 transaksi terakhir (hanya aktif)
$terbaru = $pdo->query(
    "SELECT p.*, GROUP_CONCAT(pi.nama_produk,' x',pi.qty SEPARATOR ', ') AS items
     FROM penjualan p
     LEFT JOIN penjualan_item pi ON pi.penjualan_id = p.id
     WHERE p.status = 'aktif'
     GROUP BY p.id
     ORDER BY p.dibuat_pada DESC LIMIT 10"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard – Air Isi Ulang</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">💧 Air Isi Ulang</div>
    <nav>
        <a href="index.php" class="aktif">Dashboard</a>
        <a href="kasir.php">Kasir</a>
        <a href="laporan.php">Laporan</a>
        <a href="produk.php">Produk</a>
    </nav>
</header>

<main>
    <h1>Dashboard</h1>
    <p>Selamat datang — <?= date('l, d F Y') ?></p>

    <section class="cards">
        <article class="card pemasukan">
            <p>Omzet Hari Ini</p>
            <h2><?= rupiah($omzetHari) ?></h2>
        </article>
        <article class="card saldo-positif">
            <p>Omzet Bulan Ini</p>
            <h2><?= rupiah($omzetBulan) ?></h2>
        </article>
        <article class="card">
            <p>Galon Terjual Hari Ini</p>
            <h2><?= number_format($jualanHari) ?> pcs</h2>
        </article>
        <article class="card">
            <p>Transaksi Hari Ini</p>
            <h2><?= number_format($txHari) ?> tx</h2>
        </article>
    </section>

    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2>Transaksi Terbaru</h2>
        <a href="kasir.php" class="btn-primary">+ Transaksi Baru</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Item</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$terbaru): ?>
                <tr><td colspan="5" class="kosong">Belum ada transaksi.</td></tr>
            <?php else: ?>
                <?php foreach ($terbaru as $t): ?>
                <tr>
                    <td><?= e(date('d/m H:i', strtotime($t['dibuat_pada']))) ?></td>
                    <td><?= e($t['items'] ?? '-') ?></td>
                    <td><span class="badge badge-<?= e($t['metode']) ?>"><?= strtoupper(e($t['metode'])) ?></span></td>
                    <td><strong><?= rupiah($t['total']) ?></strong></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="struk.php?id=<?= (int)$t['id'] ?>" class="btn-cetak">Struk</a>
                        <form method="POST" action="laporan.php" style="display:inline"
                              onsubmit="return confirm('Batalkan transaksi ini?')">
                            <input type="hidden" name="aksi" value="cancel">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn-hapus">✕ Cancel</button>
                        </form>
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
