<?php
require 'config.php';

$pesan = '';
$tipe  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal    = $_POST['tanggal']    ?? date('Y-m-d');
    $metode     = $_POST['metode']     ?? 'cash';
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (!in_array($metode, ['cash','qris','transfer'])) $metode = 'cash';

    $produk_id = $_POST['produk_id'] ?? [];
    $qty       = $_POST['qty']       ?? [];

    $items = [];
    foreach ($produk_id as $k => $pid) {
        $pid = (int) $pid;
        $q   = (int) ($qty[$k] ?? 0);
        if ($pid > 0 && $q > 0) $items[] = ['produk_id' => $pid, 'qty' => $q];
    }

    if ($items) {
        $total      = 0;
        $itemDetail = [];
        foreach ($items as $item) {
            $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ? AND aktif = 1");
            $stmt->execute([$item['produk_id']]);
            $produk = $stmt->fetch();
            if ($produk) {
                $subtotal     = $produk['harga'] * $item['qty'];
                $total       += $subtotal;
                $itemDetail[] = [
                    'produk_id'   => $produk['id'],
                    'nama_produk' => $produk['nama'],
                    'harga'       => $produk['harga'],
                    'qty'         => $item['qty'],
                    'subtotal'    => $subtotal,
                ];
            }
        }

        if ($total > 0) {
            // Simpan penjualan
            $orderId = 'AIR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $payStatus = in_array($metode, ['qris','transfer']) ? 'pending' : null;

            $stmt = $pdo->prepare(
                "INSERT INTO penjualan (tanggal, metode, total, keterangan, payment_status, order_id)
                 VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$tanggal, $metode, $total, $keterangan ?: null, $payStatus, $orderId]);
            $penjualanId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                "INSERT INTO penjualan_item (penjualan_id, produk_id, nama_produk, harga, qty, subtotal)
                 VALUES (?,?,?,?,?,?)"
            );
            foreach ($itemDetail as $d) {
                $stmtItem->execute([
                    $penjualanId, $d['produk_id'], $d['nama_produk'],
                    $d['harga'], $d['qty'], $d['subtotal']
                ]);
            }

            // Kalau QRIS → pakai Charge API untuk dapat QR string
            // Kalau Transfer → pakai Snap Token untuk popup VA
            if ($metode === 'qris') {
                $qrData = buatQRIS($orderId, (int)$total);
                if ($qrData) {
                    $pdo->prepare("UPDATE penjualan SET qr_string=? WHERE id=?")
                        ->execute([$qrData['qr_string'], $penjualanId]);
                }
            } elseif ($metode === 'transfer') {
                $itemsMidtrans = [];
                foreach ($itemDetail as $d) {
                    $itemsMidtrans[] = [
                        'id'       => (string) $d['produk_id'],
                        'price'    => (int) $d['harga'],
                        'quantity' => $d['qty'],
                        'name'     => $d['nama_produk'],
                    ];
                }
                $params = [
                    'transaction_details' => [
                        'order_id'     => $orderId,
                        'gross_amount' => (int) $total,
                    ],
                    'item_details'     => $itemsMidtrans,
                    'customer_details' => [
                        'first_name' => $keterangan ?: 'Pelanggan',
                    ],
                    'enabled_payments' => ['bca_va','bni_va','bri_va','mandiri_bill','permata_va'],
                ];
                $snapToken = buatSnapToken($params);
                if ($snapToken) {
                    $pdo->prepare("UPDATE penjualan SET snap_token=? WHERE id=?")
                        ->execute([$snapToken, $penjualanId]);
                }
            }

            header("Location: struk.php?id=$penjualanId");
            exit;
        } else {
            $pesan = 'Tidak ada produk valid.';
            $tipe  = 'error';
        }
    } else {
        $pesan = 'Pilih minimal 1 produk.';
        $tipe  = 'error';
    }
}

$produkList = $pdo->query("SELECT * FROM produk WHERE aktif = 1 ORDER BY harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kasir – Air Isi Ulang</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">💧 Air Isi Ulang</div>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="kasir.php" class="aktif">Kasir</a>
        <a href="laporan.php">Laporan</a>
        <a href="produk.php">Produk</a>
    </nav>
</header>

<main>
    <h1>Kasir</h1>

    <?php if ($pesan): ?>
        <div class="pesan <?= $tipe ?>"><?= e($pesan) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-kasir">
        <div class="kasir-wrap">

            <div class="kasir-produk">
                <h2>Pilih Produk</h2>
                <?php if (!$produkList): ?>
                    <div class="pesan error">Belum ada produk. <a href="produk.php">Tambah dulu</a>.</div>
                <?php else: ?>
                <div class="produk-grid">
                    <?php foreach ($produkList as $p): ?>
                    <div class="produk-card" onclick="tambahItem(<?= $p['id'] ?>, '<?= e(addslashes($p['nama'])) ?>', <?= $p['harga'] ?>)">
                        <div class="produk-icon">💧</div>
                        <div class="produk-nama"><?= e($p['nama']) ?></div>
                        <div class="produk-harga"><?= rupiah($p['harga']) ?> / <?= e($p['satuan']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="kasir-keranjang">
                <h2>Keranjang</h2>
                <div id="keranjang-items">
                    <p id="keranjang-kosong" class="kosong" style="padding:20px;text-align:center">
                        Klik produk untuk menambahkan
                    </p>
                </div>

                <div class="kasir-total">
                    <span>Total</span>
                    <strong id="display-total">Rp 0</strong>
                </div>

                <div class="form-group" style="margin-top:16px">
                    <label>Metode Pembayaran</label>
                    <div class="metode-group">
                        <label class="metode-btn">
                            <input type="radio" name="metode" value="cash" checked>
                            <span>💵 Cash</span>
                        </label>
                        <label class="metode-btn">
                            <input type="radio" name="metode" value="qris">
                            <span>📱 QRIS</span>
                        </label>
                        <label class="metode-btn">
                            <input type="radio" name="metode" value="transfer">
                            <span>🏦 Transfer</span>
                        </label>
                    </div>
                    <p id="info-metode" style="font-size:12px;color:#0ea5e9;margin-top:6px;display:none">
                        Pembayaran akan diproses via Midtrans
                    </p>
                </div>

                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Keterangan <small>(opsional)</small></label>
                    <input type="text" name="keterangan" placeholder="Nama pelanggan, catatan, dll">
                </div>

                <button type="submit" id="btn-bayar" class="btn-bayar" disabled>
                    Proses Pembayaran
                </button>
            </div>
        </div>
    </form>
</main>

<script>
let keranjang = {};

document.querySelectorAll('input[name="metode"]').forEach(r => {
    r.addEventListener('change', () => {
        const info = document.getElementById('info-metode');
        info.style.display = (r.value === 'qris' || r.value === 'transfer') ? '' : 'none';
    });
});

function tambahItem(id, nama, harga) {
    keranjang[id] = keranjang[id]
        ? { ...keranjang[id], qty: keranjang[id].qty + 1 }
        : { nama, harga, qty: 1 };
    renderKeranjang();
}

function ubahQty(id, delta) {
    if (!keranjang[id]) return;
    keranjang[id].qty += delta;
    if (keranjang[id].qty <= 0) delete keranjang[id];
    renderKeranjang();
}

function renderKeranjang() {
    const wrap   = document.getElementById('keranjang-items');
    const kosong = document.getElementById('keranjang-kosong');
    const ids    = Object.keys(keranjang);

    wrap.querySelectorAll('.item-baris').forEach(el => el.remove());
    document.querySelectorAll('.hidden-input').forEach(el => el.remove());

    if (ids.length === 0) {
        kosong.style.display = '';
        document.getElementById('display-total').textContent = 'Rp 0';
        document.getElementById('btn-bayar').disabled = true;
        return;
    }

    kosong.style.display = 'none';
    document.getElementById('btn-bayar').disabled = false;

    let total = 0;
    const form = document.getElementById('form-kasir');

    ids.forEach(id => {
        const item = keranjang[id];
        const sub  = item.harga * item.qty;
        total += sub;

        const div = document.createElement('div');
        div.className = 'item-baris';
        div.innerHTML = `
            <div class="item-info">
                <span class="item-nama">${item.nama}</span>
                <span class="item-sub">${fmt(item.harga)} × ${item.qty} = <strong>${fmt(sub)}</strong></span>
            </div>
            <div class="item-qty">
                <button type="button" onclick="ubahQty(${id},-1)">−</button>
                <span>${item.qty}</span>
                <button type="button" onclick="ubahQty(${id},1)">+</button>
            </div>`;
        wrap.appendChild(div);

        ['produk_id[]', 'qty[]'].forEach((name, i) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name;
            inp.value = i === 0 ? id : item.qty;
            inp.className = 'hidden-input';
            form.appendChild(inp);
        });
    });

    document.getElementById('display-total').textContent = fmt(total);
}

function fmt(n) {
    return 'Rp ' + n.toLocaleString('id-ID');
}
</script>
</body>
</html>
