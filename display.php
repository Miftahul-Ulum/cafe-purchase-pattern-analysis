<?php
/* ============================================================
   LAYAR MONITOR CAFE - STORY CAFE  (gaya menu board)
   Tampilan TV/monitor untuk pelanggan: 4 slide berotasi
   (Hero Promo, Menu Makanan, Menu Minuman, Podium Terlaris)
   + ticker berjalan. Hanya menampilkan data publik (menu &
   harga); TIDAK menampilkan pendapatan/transaksi internal.
   Parameter:
     ?detik=60   interval refresh halaman (10-3600)
     ?rotasi=8   interval rotasi slide (2-60 detik)
   ============================================================ */
require __DIR__ . '/includes/analisis.php';

$autoDetik   = isset($_GET['detik'])  ? max(10, min(3600, (int)$_GET['detik'])) : 60;
$rotasiDetik = isset($_GET['rotasi']) ? max(2,  min(60,   (int)$_GET['rotasi'])) : 8;
$hariIni = date('Y-m-d');

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
$makananItems = array_values(array_filter($revList, fn($m) => ($m['kategori'] ?? '') !== 'minuman'));
$minumanItems = array_values(array_filter($revList, fn($m) => ($m['kategori'] ?? '') === 'minuman'));

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

$maxQty = 1;
foreach ($revList as $m) $maxQty = max($maxQty, (int)$m['qty']);
$podiumTop = array_slice($revList, 0, 3);
$podiumRest = array_slice($revList, 3, 6);
$populerSet = [];
foreach (array_slice($revList, 0, 3) as $p) $populerSet[$p['nama_menu']] = true;

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

/* ticker berjalan */
$ticker = [];
if ($todayTopName !== '') $ticker[] = "🔥 PALING LARIS HARI INI: $todayTopName";
foreach (array_slice($rules, 0, 4) as $r) $ticker[] = "🎁 {$r['A']} + {$r['B']}";
if (isset($katLeadNama)) $ticker[] = "📌 Kategori Terpopuler: $katLeadNama";
if (!$ticker) $ticker[] = '☕ Story Cafe — Menu Favorit & Rekomendasi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story Cafe - Digital Menu Board</title>
    <link rel="stylesheet" href="assets/css/display.css">
</head>
<body>
<div class="screen">

    <header class="topbar">
        <div class="brand">
            <div class="logo-mark">☕</div>
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

    <div class="stage" id="carousel">

        <div class="slides">

            <!-- ============ SLIDE 1 : HERO PROMO ============ -->
            <section class="slide hero-slide slide-active">
                <div class="blob blob-1"></div>
                <div class="blob blob-2"></div>
                <div class="blob blob-3"></div>
                <div class="hero-inner">
                    <div class="hero-tag">🎁 PROMO BUNDLING FAVORIT</div>
                    <div class="hero-combo">
                        <?php if ($heroCombo): ?>
                            <span class="hero-item"><span class="hero-emoji"><?= emoji_menu($heroCombo['A']) ?></span><span class="hero-name"><?= htmlspecialchars($heroCombo['A']) ?></span></span>
                            <span class="hero-plus">+</span>
                            <span class="hero-item"><span class="hero-emoji"><?= emoji_menu($heroCombo['B']) ?></span><span class="hero-name"><?= htmlspecialchars($heroCombo['B']) ?></span></span>
                        <?php endif ?>
                    </div>
                    <div class="hero-title">KOMBINASI<br>FAVORIT PELANGGAN</div>
                    <div class="hero-sub">Nikmati bersama orang tersayang</div>
                    <div class="hero-price" data-count="<?= (int)$heroHarga ?>">Rp <span class="num">0</span></div>
                </div>
            </section>

            <!-- ============ SLIDE 2 : MENU MAKANAN ============ -->
            <section class="slide board-slide">
                <div class="board-head">
                    <h2>🍽️ Menu Makanan</h2>
                    <span class="board-count"><?= count($makananItems) ?> ITEM</span>
                </div>
                <div class="menu-grid">
                    <?php foreach ($makananItems as $i => $m): ?>
                        <div class="food-card" style="--d:<?= $i * 0.05 ?>s">
                            <div class="card-emoji"><?= emoji_menu($m['nama_menu']) ?></div>
                            <?php if (isset($populerSet[$m['nama_menu']])): ?>
                                <span class="card-badge">🔥 POPULER</span>
                            <?php endif ?>
                            <div class="card-name"><?= htmlspecialchars($m['nama_menu']) ?></div>
                            <div class="card-price"><?= rupiah($m['harga']) ?></div>
                        </div>
                    <?php endforeach ?>
                </div>
            </section>

            <!-- ============ SLIDE 3 : MENU MINUMAN ============ -->
            <section class="slide board-slide">
                <div class="board-head">
                    <h2>🥤 Menu Minuman</h2>
                    <span class="board-count"><?= count($minumanItems) ?> ITEM</span>
                </div>
                <div class="menu-grid">
                    <?php foreach ($minumanItems as $i => $m): ?>
                        <div class="drink-card" style="--d:<?= $i * 0.05 ?>s">
                            <div class="card-emoji"><?= emoji_menu($m['nama_menu']) ?></div>
                            <?php if (isset($populerSet[$m['nama_menu']])): ?>
                                <span class="card-badge">🔥 POPULER</span>
                            <?php endif ?>
                            <div class="card-name"><?= htmlspecialchars($m['nama_menu']) ?></div>
                            <div class="card-price"><?= rupiah($m['harga']) ?></div>
                        </div>
                    <?php endforeach ?>
                </div>
            </section>

            <!-- ============ SLIDE 4 : PODIUM TERLARIS ============ -->
            <section class="slide podium-slide">
                <div class="board-head">
                    <h2>🔥 Menu Terlaris</h2>
                    <span class="board-count">PILIHAN PELANGGAN</span>
                </div>
                <?php if ($podiumTop): ?>
                <div class="podium">
                    <?php $order = [1, 0, 2]; $medal = ['🥇', '🥈', '🥉']; ?>
                    <?php foreach ($order as $k): if (!isset($podiumTop[$k])) continue;
                        $p = $podiumTop[$k];
                        $rel = $maxQty > 0 ? ($p['qty'] / $maxQty) : 0;
                        $stars = (int)round(1 + 4 * $rel);
                    ?>
                        <div class="podium-col rank-<?= $k + 1 ?>" style="--d:<?= $k * 0.1 ?>s">
                            <div class="podium-stars"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></div>
                            <div class="podium-plate">
                                <span class="podium-emoji"><?= emoji_menu($p['nama_menu']) ?></span>
                                <span class="podium-name"><?= htmlspecialchars($p['nama_menu']) ?></span>
                                <span class="podium-price"><?= rupiah($p['harga']) ?></span>
                            </div>
                            <div class="podium-medal"><?= $medal[$k] ?></div>
                            <div class="podium-rank">#<?= $k + 1 ?></div>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php endif ?>
                <div class="top-rest">
                    <?php foreach ($podiumRest as $m): ?>
                        <span class="rest-chip"><b><?= emoji_menu($m['nama_menu']) ?></b> <?= htmlspecialchars($m['nama_menu']) ?></span>
                    <?php endforeach ?>
                </div>
            </section>

        </div>

        <div class="dots" id="dots">
            <button class="dot dot-active" data-i="0" title="Promo"></button>
            <button class="dot" data-i="1" title="Makanan"></button>
            <button class="dot" data-i="2" title="Minuman"></button>
            <button class="dot" data-i="3" title="Terlaris"></button>
        </div>
    </div>

    <div class="ticker">
        <div class="ticker-label">ℹ️</div>
        <div class="ticker-window">
            <div class="ticker-track">
                <?php for ($r = 0; $r < 2; $r++): foreach ($ticker as $t): ?>
                    <span class="ticker-item"><?= $t ?></span>
                <?php endforeach; endfor ?>
            </div>
        </div>
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
window.DISPLAY_ROTASI = <?= (int)$rotasiDetik ?>;
</script>
<script src="assets/js/display.js"></script>
</body>
</html>
