<?php
/* ============================================================
   DASHBOARD ADMINISTRASI ANALISIS STORY CAFE
   Logika analisis dipisah di includes/analisis.php agar dapat
   dipakai bersama oleh display.php (layar monitor cafe).
   ============================================================ */
require __DIR__ . '/includes/analisis.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analisis Story Cafe - Apriori</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav>
    <div class="inner">
        <a href="#kpi">Ringkasan</a>
        <a href="#upload">Upload Data</a>
        <a href="#rekomendasi">Rekomendasi</a>
        <a href="#tren">Tren Waktu</a>
        <a href="#abc">Pendapatan (ABC)</a>
        <a href="#support">Support</a>
        <a href="#rules">Aturan Asosiasi</a>
        <a href="#penjelasan">Penjelasan</a>
        <a href="display.php" target="_blank" style="border-bottom-color:#dc3545; color:#dc3545">🖥️ Layar Cafe</a>
    </div>
</nav>

<div class="container">
    <header>
        <div class="inner">
            <div>
                <h1>☕ Dashboard Analisis Story Cafe</h1>
                <p>Pola minat beli konsumen → informasi pendukung keputusan pemasaran, layanan & pengelolaan menu</p>
            </div>
            <button class="print-btn" onclick="window.print()">🖨️ Cetak Laporan</button>
        </div>
    </header>

    <?php if ($pesan): ?><div class="alert ok">✔ <?= htmlspecialchars($pesan) ?></div><?php endif ?>
    <?php if ($error): ?><div class="alert err">✖ <?= htmlspecialchars($error) ?></div><?php endif ?>

    <!-- ============ KPI ============ -->
    <div class="kpis" id="kpi">
        <div class="kpi"><div class="label">Total Transaksi</div><div class="value"><?= number_format($kpiTotal, 0, ',', '.') ?></div><div class="sub">dalam rentang filter</div></div>
        <div class="kpi"><div class="label">Total Pendapatan</div><div class="value">Rp <?= number_format($kpiRev, 0, ',', '.') ?></div><div class="sub">dari seluruh menu</div></div>
        <div class="kpi"><div class="label">Rata-rata Item / Transaksi</div><div class="value"><?= $kpiAvgItems ?></div><div class="sub">keranjang belanja</div></div>
        <div class="kpi"><div class="label">Rata-rata Transaksi / Hari</div><div class="value"><?= $kpiPerDay ?></div><div class="sub"><?= $distinctDays ?> hari data</div></div>
        <div class="kpi kpi-green"><div class="label">Menu Terlaris</div><div class="value value-sm"><?= htmlspecialchars($kpiTopMenu) ?></div><div class="sub">berdasarkan pendapatan</div></div>
    </div>

    <!-- ============ UPLOAD ============ -->
    <div class="box" id="upload">
        <h2>📥 Upload Data Transaksi</h2>
        <form method="post" enctype="multipart/form-data" class="row upload-box">
            <input type="file" name="file_excel" accept=".csv,.xlsx,.xls" required>
            <button name="upload_excel">📤 Upload & Analisis</button>
            <input type="hidden" name="dari_h" value="<?= htmlspecialchars($dari) ?>">
            <input type="hidden" name="sampai_h" value="<?= htmlspecialchars($sampai) ?>">
        </form>
        <form method="post" class="row mt-10">
            <button class="btn-danger" name="reset_data" onclick="return confirm('Hapus semua data transaksi? Menu tetap tersimpan.')">🗑️ Hapus Data Transaksi</button>
        </form>
        <p class="hint">
            Format baris (satu baris = satu item): <code>Tanggal, Menu, Harga, Jumlah, Gambar, Kategori(opsional)</code><br>
            Contoh: <code>2024-01-15 10:30, Cappuccino, 25000, 2, , minuman</code><br>
            Kolom Tanggal boleh berisi <b>jam</b> (mis. <code>2024-01-15 14:30</code>) untuk analisis jam ramai/sepi.
            Kategori boleh diisi <b>makanan/minuman</b>; jika kosong dideteksi otomatis. Simpan sebagai <b>.csv</b> agar langsung jalan.
        </p>
    </div>

    <!-- ============ FILTER ============ -->
    <div class="box">
        <h2>🔎 Filter Analisis</h2>
        <form method="get" class="filter-bar">
            <div><label>Dari Tanggal</label><input type="date" name="dari" value="<?= htmlspecialchars($dari) ?>"></div>
            <div><label>Sampai Tanggal</label><input type="date" name="sampai" value="<?= htmlspecialchars($sampai) ?>"></div>
            <div><label>Min Support (%)</label><input type="number" name="min_support" min="0" max="100" step="0.5" value="<?= $min_support ?>"></div>
            <div><label>Min Confidence (%)</label><input type="number" name="min_conf" min="0" max="100" step="0.5" value="<?= $min_conf ?>"></div>
            <button>Terapkan</button>
        </form>
        <p class="hint">Support = seberapa sering menu muncul di seluruh transaksi. Confidence = peluang B dibeli jika A dibeli. Lift &gt; 1 berarti asosiasi lebih kuat dari kebetulan.</p>
    </div>

    <?php if ($total == 0): ?>
        <div class="box"><h2>📭 Belum Ada Data</h2><p>Upload file transaksi di atas untuk memulai analisis.</p></div>
    <?php else: ?>

    <!-- ============ REKOMENDASI ============ -->
    <div class="box" id="rekomendasi">
        <h2>💡 Rekomendasi Keputusan</h2>
        <?php if (!$rekom): ?>
            <p>Tidak ada rekomendasi. Coba turunkan nilai min support / min confidence, atau pastikan rentang tanggal berisi data.</p>
        <?php else: ?>
        <div class="rek-carousel" id="rekCarousel">
            <div class="rek-track">
                <?php foreach ($rekom as $i => $r): ?>
                    <div class="rek-slide<?= $i === 0 ? ' active' : '' ?>">
                        <div class="rek-card">
                            <div class="icon"><?= $r['icon'] ?></div>
                            <div class="isi">
                                <h3><?= $r['judul'] ?></h3>
                                <p class="no-margin"><?= $r['isi'] ?></p>
                                <div class="aksi">⚙️ Tindakan: <?= $r['aksi'] ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="rek-controls">
                <button type="button" class="rek-prev" title="Sebelumnya">‹</button>
                <div class="rek-dots">
                    <?php foreach ($rekom as $i => $r): ?>
                        <button type="button" class="rek-dot<?= $i === 0 ? ' active' : '' ?>" data-i="<?= $i ?>" title="Rekomendasi <?= $i + 1 ?>"></button>
                    <?php endforeach ?>
                </div>
                <button type="button" class="rek-next" title="Berikutnya">›</button>
            </div>
        </div>
        <?php endif ?>
    </div>

    <!-- ============ TREN ============ -->
    <div class="box" id="tren">
        <h2>📈 Tren Waktu</h2>
        <div class="chart-grid">
            <div class="chart-box full"><h3>Pendapatan per Tanggal</h3><canvas id="chartTren"></canvas></div>
            <div class="chart-box"><h3>Jumlah Transaksi per Jam</h3><canvas id="chartJam"></canvas></div>
            <div class="chart-box"><h3>Transaksi per Hari (Mingguan)</h3><canvas id="chartHari"></canvas></div>
            <div class="chart-box full"><h3>Top 10 Menu berdasarkan Pendapatan</h3><canvas id="chartTopMenu"></canvas></div>
            <div class="chart-box"><h3>Porsi Pendapatan per Kategori</h3><canvas id="chartKategori"></canvas></div>
        </div>
    </div>

    <!-- ============ ANALISIS PENDAPATAN ABC ============ -->
    <div class="box" id="abc">
        <h2>💰 Analisis Pendapatan Menu (ABC)</h2>
        <p class="hint">Klasifikasi A = menyumbang s.d. 70% pendapatan (prioritas utama), B = 70–90% (perlu dijaga), C = sisa (evaluasi).</p>
        <table>
            <tr><th>No</th><th>Menu</th><th>Kategori</th><th>Harga</th><th>Qty Terjual</th><th>Pendapatan</th><th>% Kontribusi</th><th>Klasifikasi</th></tr>
            <?php $no = 1; foreach ($revList as $it): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-left"><?= htmlspecialchars($it['nama_menu']) ?></td>
                    <td><span class="badge <?= $it['kategori'] === 'minuman' ? 'mnm' : 'mkn' ?>"><?= $it['kategori'] === 'minuman' ? 'minuman' : 'makanan' ?></span></td>
                    <td>Rp <?= number_format($it['harga'], 0, ',', '.') ?></td>
                    <td><?= $it['qty'] ?></td>
                    <td>Rp <?= number_format($it['rev'], 0, ',', '.') ?></td>
                    <td><?= $it['pct'] ?>%</td>
                    <td><span class="badge <?= strtolower($it['kls']) ?>"><?= $it['kls'] ?></span></td>
                </tr>
            <?php endforeach ?>
        </table>
    </div>

    <!-- ============ SUPPORT ============ -->
    <div class="box" id="support">
        <h2>📊 Tabel Support Menu (<?= $total ?> transaksi)</h2>
        <table>
            <tr><th>No</th><th>Menu</th><th>Kategori</th><th>Jumlah Transaksi</th><th>Support (%)</th></tr>
            <?php $no = 1; foreach ($support as $s): $kat = $kategoriMenu[$s['menu']] ?? ''; ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-left"><?= htmlspecialchars($s['menu']) ?></td>
                    <td><span class="badge <?= $kat === 'minuman' ? 'mnm' : 'mkn' ?>"><?= $kat ? htmlspecialchars($kat) : 'makanan' ?></span></td>
                    <td><?= $s['jumlah'] ?></td>
                    <td><?= $s['support'] ?>%</td>
                </tr>
            <?php endforeach ?>
        </table>
    </div>

    <!-- ============ ATURAN ASOSIASI ============ -->
    <div class="box" id="rules">
        <h2>🔗 Aturan Asosiasi (Confidence ≥ <?= $min_conf ?>%, Lift > 1)</h2>
        <?php if (!$rules): ?>
            <p>Belum ada aturan yang memenuhi filter. Coba turunkan min support / min confidence.</p>
        <?php else: ?>
        <p class="hint">Urut berdasarkan Lift tertinggi (asosiasi paling bermakna). Jumlah aturan yang terpenuhi: <b><?= count($rules) ?></b></p>
        <table>
            <tr><th>No</th><th>Jika Membeli (A)</th><th>→</th><th>Maka Juga Membeli (B)</th><th>Support (AB)</th><th>Confidence (%)</th><th>Lift</th></tr>
            <?php $no = 1; foreach ($rules as $c): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="text-left"><b><?= htmlspecialchars($c['A']) ?></b></td>
                    <td>→</td>
                    <td class="text-left"><b><?= htmlspecialchars($c['B']) ?></b></td>
                    <td><?= $c['supp'] ?>%</td>
                    <td><?= $c['conf'] ?>%</td>
                    <td><?= $c['lift'] ?></td>
                </tr>
            <?php endforeach ?>
        </table>
        <?php endif ?>
    </div>

    <?php endif ?>

    <!-- ============ PENJELASAN ============ -->
    <div class="box" id="penjelasan">
        <h2>📝 Cara Membaca Analisis</h2>
        <ul class="explain">
            <li><b>Support</b> — frekuensi kemunculan sebuah menu (atau kombinasi) terhadap seluruh transaksi. Makin tinggi, makin populer.</li>
            <li><b>Confidence</b> — peluang pelanggan membeli B jika sudah membeli A. Dasar rekomendasi bundling.</li>
            <li><b>Lift</b> — rasio kekuatan asosiasi. Lift &gt; 1 = kombinasi nyata bukan kebetulan; lift = 1 = tidak ada keterkaitan.</li>
            <li><b>Analisis ABC</b> — memetakan kontribusi pendapatan tiap menu untuk prioritas promosi, harga, dan kelayakan menu (efisiensi pengelolaan menu).</li>
            <li><b>Rekomendasi Keputusan</b> — terjemahan langsung hasil analisis menjadi tindakan pemasaran, layanan, dan menu.</li>
        </ul>
    </div>

</div>

<script>
window.CHART_DATA = {
    hasData: <?= $total > 0 ? 'true' : 'false' ?>,
    dateLabels: <?= json_encode($chDateLabels) ?>,
    dateRev: <?= json_encode($chDateRev) ?>,
    dateN: <?= json_encode($chDateN) ?>,
    jamLabels: <?= json_encode($chJamLabels) ?>,
    jamVals: <?= json_encode($chJamVals) ?>,
    hariLabels: <?= json_encode($chHariLabels) ?>,
    hariVals: <?= json_encode($chHariVals) ?>,
    topLabels: <?= json_encode($chTopLabels) ?>,
    topVals: <?= json_encode($chTopVals) ?>,
    katLabels: <?= json_encode($chKatLabels) ?>,
    katVals: <?= json_encode($chKatVals) ?>
};
</script>
<script src="assets/js/main.js"></script>

</body>
</html>
