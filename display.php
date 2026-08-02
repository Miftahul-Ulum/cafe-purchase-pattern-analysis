<?php
/* ============================================================
   LAYAR MONITOR CAFE - STORY CAFE  (satu layar, dibagi panel)
   Satu tampilan statis berisi beberapa panel: Menu Terlaris,
   Promo Bundling & Rekomendasi. Simpel, terang, tidak ramai.
   Foto menu ditampilkan bila sudah diupload di dashboard admin.
   Parameter: ?detik=60  interval auto-refresh (10-3600).
   ============================================================ */
require __DIR__ . '/includes/analisis.php';

$autoDetik = isset($_GET['detik']) ? max(10, min(3600, (int)$_GET['detik'])) : 60;
$hariIni = date('Y-m-d');

/* ================= INFO KAFE (boleh diubah) ================= */
$infoBuka     = 'Setiap Hari';
$infoJamMulai = '08.00';
$infoJamTutup = '22.00';
$infoLokasi   = 'Jl. Raya Contoh No. 12';
$infoWa       = '0812-3456-7890';
$infoIg       = '@storycafe.id';

/* menu paling laris hari ini (nama saja) */
$todayTopName = '';
$q = $conn->query("
    SELECT m.nama_menu
    FROM detail_transaksi d
    JOIN transaksi t ON d.id_transaksi = t.id_transaksi
    JOIN menu m ON d.id_menu = m.id_menu
    WHERE t.tanggal = '$hariIni'
    GROUP BY m.id_menu
    ORDER BY SUM(d.jumlah) DESC
    LIMIT 1
");
if ($q && ($r = $q->fetch_assoc())) $todayTopName = $r['nama_menu'];

/* ================= DATA TAMPILAN ================= */
$topItems = array_slice($revList, 0, 6);

$maxQty = 1;
foreach ($revList as $m) $maxQty = max($maxQty, (int)$m['qty']);

$gambarMap = [];
$q = $conn->query("SELECT nama_menu, gambar FROM menu");
if ($q) while ($r = $q->fetch_assoc()) $gambarMap[$r['nama_menu']] = $r['gambar'];

$heroCombo = $rules[0] ?? null;
if ($heroCombo) {
    $hA = $hargaMap[$heroCombo['A']] ?? 0;
    $hB = $hargaMap[$heroCombo['B']] ?? 0;
    $heroHarga = $hA + $hB;
} else {
    $a = $revList[0] ?? null; $b = $revList[1] ?? null;
    $heroCombo = $a && $b ? ['A' => $a['nama_menu'], 'B' => $b['nama_menu']] : null;
    $heroHarga = ($a['harga'] ?? 0) + ($b['harga'] ?? 0);
}

$andalan = $revList[0] ?? null;
$komboRek = $rules[0] ?? null;

function emoji_menu($nama) {
    $n = strtolower($nama);
    if (preg_match('/cappuccino|latte|americano|espresso|kopi/', $n)) return '☕';
    if (preg_match('/matcha/', $n)) return '🍵';
    if (preg_match('/teh tarik|thai/', $n)) return '🧋';
    if (preg_match('/juice|orange|lemon/', $n)) return '🍊';
    if (preg_match('/chocolate|coklat|brownie/', $n)) return '🍫';
    if (preg_match('/cheese|cake/', $n)) return '🍰';
    if (preg_match('/wings/', $n)) return '🍗';
    if (preg_match('/fries/', $n)) return '🍟';
    if (preg_match('/martabak|pancake/', $n)) return '🥞';
    if (preg_match('/croissant/', $n)) return '🥐';
    if (preg_match('/toast/', $n)) return '🍞';
    if (preg_match('/garlic/', $n)) return '🥖';
    if (preg_match('/salad/', $n)) return '🥗';
    if (preg_match('/sandwich/', $n)) return '🥪';
    if (preg_match('/milo|susu|milk|smoothie|shake/', $n)) return '🥛';
    if (preg_match('/soda|cola|mineral|air/', $n)) return '🥤';
    return '🍽️';
}

function rupiah($n) { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }

function menu_visual($nama, $gambarMap, $class) {
    $g = $gambarMap[$nama] ?? '';
    if ($g !== '') {
        return '<img class="' . $class . '" src="' . htmlspecialchars($g) . '" alt="' . htmlspecialchars($nama) . '">';
    }
    return '<span class="' . $class . '-emoji">' . emoji_menu($nama) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layar Cafe - Story Cafe</title>
    <link rel="stylesheet" href="assets/css/display.css">
</head>
<body>
<div class="screen">

    <header class="topbar">
        <div class="brand">
            <span class="logo-mark">☕</span>
            <div>
                <h1>STORY <span>CAFE</span></h1>
                <p>MENU FAVORIT &amp; REKOMENDASI</p>
            </div>
        </div>
        <div class="clock-area">
            <div class="clock" id="clock">--:--:--</div>
            <div class="date" id="date">&nbsp;</div>
        </div>
    </header>

    <?php if ($total == 0): ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <div class="empty-title">Belum Ada Data Transaksi</div>
            <div class="empty-sub">Upload data pada Dashboard Admin untuk mengaktifkan layar ini.</div>
            <a class="empty-btn" href="analisis_full.php">Buka Dashboard Admin</a>
        </div>
    <?php else: ?>

    <?php if ($todayTopName): ?>
        <div class="banner">🔥 <b>Paling Laris Hari Ini:</b> <?= htmlspecialchars($todayTopName) ?></div>
    <?php endif ?>

    <div class="layout">

        <!-- ============ PANEL KIRI : MENU TERLARIS ============ -->
        <section class="panel panel-populer">
            <h2>🔥 Menu Terlaris</h2>
            <ol class="top-list">
                <?php foreach ($topItems as $i => $m):
                    $rel = $maxQty > 0 ? ($m['qty'] / $maxQty) : 0;
                    $rating = number_format(3 + 2 * $rel, 1);
                    $stars = (int)round(1 + 4 * $rel);
                ?>
                    <li class="top-item">
                        <span class="rank"><?= $i + 1 ?></span>
                        <span class="thumb"><?= menu_visual($m['nama_menu'], $gambarMap, 'thumb-img') ?></span>
                        <span class="top-info">
                            <span class="top-name"><?= htmlspecialchars($m['nama_menu']) ?></span>
                            <span class="top-stars">
                                <span class="top-rating">★ <?= $rating ?></span>
                                <?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?>
                            </span>
                        </span>
                        <span class="top-price"><?= rupiah($m['harga']) ?></span>
                    </li>
                <?php endforeach ?>
            </ol>
        </section>

        <!-- ============ PANEL KANAN : PROMO + REKOMENDASI ============ -->
        <aside class="side">
            <section class="panel panel-promo">
                <h2>🎁 Promo Bundling</h2>
                <?php if ($heroCombo): ?>
                    <div class="combo">
                        <div class="combo-foto">
                            <?= menu_visual($heroCombo['A'], $gambarMap, 'combo-img') ?>
                            <span class="combo-plus">+</span>
                            <?= menu_visual($heroCombo['B'], $gambarMap, 'combo-img') ?>
                        </div>
                        <div class="combo-nama"><?= htmlspecialchars($heroCombo['A']) ?> <span>+</span> <?= htmlspecialchars($heroCombo['B']) ?></div>
                        <div class="combo-desc">Kombinasi favorit pelanggan — cocok dinikmati bersama</div>
                        <div class="combo-harga">Mulai dari <?= rupiah($heroHarga) ?></div>
                    </div>
                <?php else: ?>
                    <p class="muted">Belum ada kombinasi promo. Upload lebih banyak data transaksi untuk menghasilkan rekomendasi.</p>
                <?php endif ?>
            </section>

            <section class="panel panel-rek">
                <h2>💡 Rekomendasi</h2>
                <ul class="rek-list">
                    <?php if ($andalan): ?>
                        <li><span class="rek-icon">⭐</span><span><b>Wajib coba:</b> <?= htmlspecialchars($andalan['nama_menu']) ?></span></li>
                    <?php endif ?>
                    <?php if ($komboRek): ?>
                        <li><span class="rek-icon">🤝</span><span><b>Kombinasi favorit:</b> <?= htmlspecialchars($komboRek['A']) ?> + <?= htmlspecialchars($komboRek['B']) ?></span></li>
                    <?php endif ?>
                    <?php if (isset($katLeadNama)): ?>
                        <li><span class="rek-icon">📌</span><span><b>Kategori terpopuler:</b> <?= htmlspecialchars($katLeadNama) ?></span></li>
                    <?php endif ?>
                </ul>
            </section>

            <section class="panel panel-info">
                <h2>🕒 Jam Operasional</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-icon">🕗</span>
                        <div><b><?= htmlspecialchars($infoBuka) ?></b><small><?= htmlspecialchars($infoJamMulai) ?> – <?= htmlspecialchars($infoJamTutup) ?> WIB</small></div>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">📍</span>
                        <div><b>Lokasi</b><small><?= htmlspecialchars($infoLokasi) ?></small></div>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">📱</span>
                        <div><b>WhatsApp</b><small><?= htmlspecialchars($infoWa) ?></small></div>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">📸</span>
                        <div><b>Instagram</b><small><?= htmlspecialchars($infoIg) ?></small></div>
                    </div>
                </div>
            </section>
        </aside>

    </div>

    <?php endif ?>

    <footer class="foot">
        <span class="foot-item">🔄 Menyegarkan dalam <b id="count"><?= $autoDetik ?></b> detik</span>
        <span class="foot-item">Terakhir diperbarui: <?= date('d/m/Y H:i:s') ?></span>
        <a class="foot-item foot-link" href="analisis_full.php">Dashboard Admin</a>
    </footer>

</div>

<script>
window.AUTO_REFRESH = <?= (int)$autoDetik ?>;
</script>
<script src="assets/js/display.js"></script>
</body>
</html>
