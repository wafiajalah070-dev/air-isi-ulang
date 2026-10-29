<?php
require 'config.php';

$pesan = '';
$tipe  = '';

// Tambah produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nama   = trim($_POST['nama']   ?? '');
    $harga  = (float) ($_POST['harga']  ?? 0);
    $satuan = trim($_POST['satuan'] ?? 'galon');
    if ($nama && $harga > 0) {
        $pdo->prepare("INSERT INTO produk (nama, harga, satuan) VALUES (?,?,?)")
            ->execute([$nama, $harga, $satuan]);
        $pesan = 'Produk berhasil ditambahkan.';
        $tipe  = 'sukses';
    } else {
        $pesan = 'Nama dan harga wajib diisi.';
        $tipe  = 'error';
    }
}

// Edit produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id     = (int) ($_POST['id']    ?? 0);
    $nama   = trim($_POST['nama']   ?? '');
    $harga  = (float) ($_POST['harga']  ?? 0);
    $satuan = trim($_POST['satuan'] ?? 'galon');
    if ($id && $nama && $harga > 0) {
        $pdo->prepare("UPDATE produk SET nama=?, harga=?, satuan=? WHERE id=?")
            ->execute([$nama, $harga, $satuan, $id]);
        $pesan = 'Produk diperbarui.';
        $tipe  = 'sukses';
    }
}

// Toggle aktif/nonaktif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE produk SET aktif = 1 - aktif WHERE id=?")->execute([$id]);
        $pesan = 'Status produk diubah.';
        $tipe  = 'sukses';
    }
}

// Hapus produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM produk WHERE id=?")->execute([$id]);
        $pesan = 'Produk dihapus.';
        $tipe  = 'sukses';
    }
}

$daftar = $pdo->query("SELECT * FROM produk ORDER BY aktif DESC, harga ASC")->fetchAll();

// Produk yang diedit
$editProduk = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProduk = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produk – Air Isi Ulang</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">💧 Air Isi Ulang</div>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="kasir.php">Kasir</a>
        <a href="laporan.php">Laporan</a>
        <a href="produk.php" class="aktif">Produk</a>
    </nav>
</header>

<main>
    <h1>Kelola Produk</h1>

    <?php if ($pesan): ?>
        <div class="pesan <?= $tipe ?>"><?= e($pesan) ?></div>
    <?php endif; ?>

    <!-- Form Tambah / Edit -->
    <div class="form-card-simple">
        <h2><?= $editProduk ? 'Edit Produk' : 'Tambah Produk' ?></h2>
        <form method="POST" class="form-transaksi">
            <input type="hidden" name="aksi" value="<?= $editProduk ? 'edit' : 'tambah' ?>">
            <?php if ($editProduk): ?>
                <input type="hidden" name="id" value="<?= (int)$editProduk['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Nama Produk</label>
                <input type="text" name="nama"
                       value="<?= e($editProduk['nama'] ?? '') ?>"
                       placeholder="Contoh: Air Isi Ulang Galon" required>
            </div>
            <div class="form-group">
                <label>Harga (Rp)</label>
                <input type="number" name="harga" min="1" step="any"
                       value="<?= $editProduk['harga'] ?? '' ?>"
                       placeholder="5000" required>
            </div>
            <div class="form-group">
                <label>Satuan</label>
                <input type="text" name="satuan"
                       value="<?= e($editProduk['satuan'] ?? 'galon') ?>"
                       placeholder="galon / botol / cup">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit"><?= $editProduk ? 'Simpan Perubahan' : 'Tambah' ?></button>
            </div>
            <?php if ($editProduk): ?>
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="produk.php" class="btn-secondary" style="text-decoration:none;display:inline-block;padding:10px 18px;border-radius:7px">Batal</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Daftar Produk -->
    <h2>Daftar Produk</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Satuan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?>
                <tr><td colspan="6" class="kosong">Belum ada produk.</td></tr>
            <?php else: ?>
                <?php foreach ($daftar as $i => $p): ?>
                <tr <?= !$p['aktif'] ? 'style="opacity:.5"' : '' ?>>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($p['nama']) ?></td>
                    <td><?= rupiah($p['harga']) ?></td>
                    <td><?= e($p['satuan']) ?></td>
                    <td>
                        <span class="badge <?= $p['aktif'] ? 'badge-lunas' : 'badge-batal' ?>">
                            <?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap">
                        <a href="produk.php?edit=<?= $p['id'] ?>" class="btn-cetak">Edit</a>

                        <form method="POST" style="display:inline">
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn-secondary" style="padding:5px 10px;font-size:12px">
                                <?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>

                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Hapus produk ini?')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn-hapus">Hapus</button>
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
