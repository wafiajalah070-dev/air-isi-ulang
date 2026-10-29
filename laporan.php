<?php
require 'config.php';

$pesan = '';
$tipe  = '';

// ── Proses Cancel ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'cancel') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE penjualan SET status = 'dibatalkan' WHERE id = ?")
            ->execute([$id]);
        $pesan = 'Transaksi #' . str_pad($id, 5, '0', STR_PAD_LEFT) . ' berhasil dibatalkan.';
        $tipe  = 'sukses';
    }
}

// ── Proses Pulihkan ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'pulihkan') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE penjualan SET status = 'aktif' WHERE id = ?")
            ->execute([$id]);
        $pesan = 'Transaksi #' . str_pad($id, 5, '0', STR_PAD_LEFT) . ' dipulihkan.';
        $tipe  = 'sukses';
    }
}

// ── Proses Hapus Permanen ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM penjualan WHERE id = ?")->execute([$id]);
        $pesan = 'Transaksi dihapus permanen.';
        $tipe  = 'sukses';
    }
}

// ── Filter periode ────────────────────────────────────────────────
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$tampil = $_GET['tampil'] ?? 'aktif'; // aktif | semua

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   $dari   = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) $sampai = date('Y-m-d');

$statusFilter = $tampil === 'semua' ? '' : "AND p.status = 'aktif'";

// ── Ringkasan (hanya aktif) ───────────────────────────────────────
$perMetode = $pdo->prepare(
    "SELECT metode, COUNT(*) AS tx, SUM(total) AS omzet
     FROM penjualan
     WHERE tanggal BETWEEN ? AND ? AND status = 'aktif'
     GROUP BY metode"
);
$perMetode->execute([$dari, $sampai]);
$metodeData = $perMetode->fetchAll();

$totalOmzet = array_sum(array_column($metodeData, 'omzet'));
$totalTx    = array_sum(array_column($metodeData, 'tx'));

// ── Produk terlaris (hanya aktif) ────────────────────────────────
$terlaris = $pdo->prepare(
    "SELECT pi.nama_produk, SUM(pi.qty) AS total_qty, SUM(pi.subtotal) AS total_omzet
     FROM penjualan_item pi
     JOIN penjualan p ON p.id = pi.penjualan_id
     WHERE p.tanggal BETWEEN ? AND ? AND p.status = 'aktif'
     GROUP BY pi.nama_produk
     ORDER BY total_qty DESC"
);
$terlaris->execute([$dari, $sampai]);
$produkTerlaris = $terlaris->fetchAll();

// ── Detail transaksi ──────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT p.*, GROUP_CONCAT(pi.nama_produk,' x',pi.qty SEPARATOR ', ') AS items
     FROM penjualan p
     LEFT JOIN penjualan_item pi ON pi.penjualan_id = p.id
     WHERE p.tanggal BETWEEN ? AND ? $statusFilter
     GROUP BY p.id
     ORDER BY p.tanggal DESC, p.id DESC"
);
$stmt->execute([$dari, $sampai]);
$transaksi = $stmt->fetchAll();

$metodeLabel = ['cash' => 'Cash', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan – Air Isi Ulang</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">💧 Air Isi Ulang</div>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="kasir.php">Kasir</a>
        <a href="laporan.php" class="aktif">Laporan</a>
        <a href="produk.php">Produk</a>
    </nav>
</header>

<main>
    <h1>Laporan Penjualan</h1>

    <?php if ($pesan): ?>
        <div class="pesan <?= $tipe ?>"><?= e($pesan) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <form method="GET" class="form-transaksi" style="margin-bottom:24px">
        <div class="form-group">
            <label>Dari</label>
            <input type="date" name="dari" value="<?= e($dari) ?>">
        </div>
        <div class="form-group">
            <label>Sampai</label>
            <input type="date" name="sampai" value="<?= e($sampai) ?>">
        </div>
        <div class="form-group">
            <label>Tampilkan</label>
            <select name="tampil">
                <option value="aktif"  <?= $tampil === 'aktif'  ? 'selected' : '' ?>>Aktif saja</option>
                <option value="semua"  <?= $tampil === 'semua'  ? 'selected' : '' ?>>Semua (termasuk dibatalkan)</option>
            </select>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit">Tampilkan</button>
        </div>
    </form>

    <!-- Ringkasan -->
    <section class="cards">
        <article class="card pemasukan">
            <p>Total Omzet</p>
            <h2><?= rupiah($totalOmzet) ?></h2>
        </article>
        <article class="card saldo-positif">
            <p>Total Transaksi Aktif</p>
            <h2><?= number_format($totalTx) ?> tx</h2>
        </article>
        <?php foreach ($metodeData as $m): ?>
        <article class="card">
            <p><?= $metodeLabel[$m['metode']] ?? strtoupper($m['metode']) ?></p>
            <h2><?= rupiah($m['omzet']) ?></h2>
            <small style="color:#64748b"><?= $m['tx'] ?> transaksi</small>
        </article>
        <?php endforeach; ?>
    </section>

    <!-- Produk Terlaris -->
    <?php if ($produkTerlaris): ?>
    <h2>Produk Terlaris</h2>
    <div class="table-wrap" style="margin-bottom:28px">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Produk</th>
                    <th>Qty Terjual</th>
                    <th>Total Omzet</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produkTerlaris as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($p['nama_produk']) ?></td>
                    <td><?= number_format($p['total_qty']) ?></td>
                    <td><?= rupiah($p['total_omzet']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Detail Transaksi -->
    <h2>Detail Transaksi
        <small><?= e($dari) ?> s/d <?= e($sampai) ?></small>
    </h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Waktu</th>
                    <th>Item</th>
                    <th>Metode</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$transaksi): ?>
                <tr><td colspan="7" class="kosong">Tidak ada transaksi pada periode ini.</td></tr>
            <?php else: ?>
                <?php foreach ($transaksi as $i => $t): ?>
                <tr <?= $t['status'] === 'dibatalkan' ? 'class="baris-batal"' : '' ?>>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(date('d/m H:i', strtotime($t['dibuat_pada']))) ?></td>
                    <td><?= e($t['items'] ?? '-') ?></td>
                    <td><span class="badge badge-<?= e($t['metode']) ?>"><?= $metodeLabel[$t['metode']] ?? strtoupper(e($t['metode'])) ?></span></td>
                    <td><?= $t['status'] === 'dibatalkan' ? '<s>' . rupiah($t['total']) . '</s>' : rupiah($t['total']) ?></td>
                    <td>
                        <?php if ($t['status'] === 'aktif'): ?>
                            <span class="badge badge-lunas">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-batal">Dibatalkan</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <!-- Lihat struk -->
                        <a href="struk.php?id=<?= (int)$t['id'] ?>" class="btn-cetak">Struk</a>

                        <?php if ($t['status'] === 'aktif'): ?>
                        <!-- Cancel -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Batalkan transaksi ini?')">
                            <input type="hidden" name="aksi" value="cancel">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="dari" value="<?= e($dari) ?>">
                            <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
                            <input type="hidden" name="tampil" value="<?= e($tampil) ?>">
                            <button type="submit" class="btn-hapus">✕ Cancel</button>
                        </form>
                        <?php else: ?>
                        <!-- Pulihkan -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="aksi" value="pulihkan">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="dari" value="<?= e($dari) ?>">
                            <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
                            <input type="hidden" name="tampil" value="<?= e($tampil) ?>">
                            <button type="submit" class="btn-lunas">↩ Pulihkan</button>
                        </form>
                        <!-- Hapus permanen -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Hapus permanen? Data tidak bisa dikembalikan.')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="dari" value="<?= e($dari) ?>">
                            <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
                            <input type="hidden" name="tampil" value="<?= e($tampil) ?>">
                            <button type="submit" class="btn-hapus">🗑 Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($tampil === 'aktif'): ?>
                <tr class="total-row">
                    <td colspan="4"><strong>Total Omzet</strong></td>
                    <td><strong><?= rupiah($totalOmzet) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
