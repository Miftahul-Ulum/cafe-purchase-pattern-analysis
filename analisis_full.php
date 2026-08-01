<?php
/* ============================================================
   SISTEM REKOMENDASI STORY CAFE - APRIORI + DASHBOARD KEPUTUSAN
   - Auto membuat database & tabel
   - Upload CSV (atau XLSX bila PhpSpreadsheet terpasang)
   - Pemisahan makanan & minuman via kolom kategori + deteksi otomatis
   - Filter Apriori: min_support, min_confidence, LIFT
   - Analisis Pendapatan ABC, tren waktu, KPI, dan Rekomendasi Keputusan
   ============================================================ */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

/* ================= KONFIGURASI DATABASE ================= */
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "analisis_apriori";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Database tidak terhubung: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

$conn->query("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);

/* ================= AUTO CREATE TABEL ================= */
$conn->query("CREATE TABLE IF NOT EXISTS menu (
    id_menu   INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(255) NOT NULL UNIQUE,
    harga     INT DEFAULT 0,
    gambar    VARCHAR(255) DEFAULT '',
    kategori  VARCHAR(20) DEFAULT ''
)");

$conn->query("CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    tanggal      DATE,
    jam          INT DEFAULT 0,
    total        INT DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail     INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi  INT NOT NULL,
    id_menu       INT NOT NULL,
    jumlah        INT DEFAULT 1,
    subtotal      INT DEFAULT 0,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
    FOREIGN KEY (id_menu) REFERENCES menu(id_menu) ON DELETE CASCADE
)");

/* pastikan kolom jam ada (untuk database lama) */
$rs = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='transaksi' AND COLUMN_NAME='jam'");
$hasJam = ($rs && $rs->fetch_assoc()['c']) ? true : false;
if (!$hasJam) $conn->query("ALTER TABLE transaksi ADD COLUMN jam INT DEFAULT 0");

/* ================= KATEGORI MENU ================= */
define('MINUMAN_PATTERN', '/kopi|latte|americano|cappuccino|espresso|matcha|milo|teh|juice|lemon|soda|cola|sprit|fanta|mineral|air|ginger|greentea|thai|yakult|float|shake|smoothie|bandrek|jahe|susu|honey|herbal/');

function deteksi_kategori($nama) {
    return preg_match(MINUMAN_PATTERN, strtolower($nama)) ? 'minuman' : 'makanan';
}

/* ================= HELPER TANGGAL ================= */
function parse_tanggal($str) {
    $str = trim($str);
    if ($str === '') return null;
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})(?:[\sT]+(\d{1,2})[:.](\d{2}))?$#', $str, $m)) {
        $a = (int)$m[1]; $b = (int)$m[2]; $y = (int)$m[3];
        if ($a > 12 && $b <= 12)       { $day = $a; $month = $b; }
        elseif ($b > 12 && $a <= 12)   { $day = $b; $month = $a; }
        else                           { $day = $b; $month = $a; } /* default d/m/Y */
        $hour = isset($m[4]) ? (int)$m[4] : 0;
        $min  = isset($m[5]) ? (int)$m[5] : 0;
        if ($hour < 0 || $hour > 23) $hour = 0;
        if ($min < 0 || $min > 59)   $min  = 0;
        if (!checkdate($month, $day, $y)) return null;
        return [$y, $month, $day, $hour, $min];
    }
    $ts = strtotime($str);
    if ($ts === false) return null;
    return [(int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts), (int)date('G', $ts), (int)date('i', $ts)];
}

/* ================= PROSES UPLOAD ================= */
$pesan = "";
$error = "";

if (isset($_POST['upload_excel'])) {
    $fileTmp  = $_FILES['file_excel']['tmp_name'];
    $fileNama = $_FILES['file_excel']['name'];
    $fileErr  = $_FILES['file_excel']['error'];

    if ($fileErr !== 0 || !$fileTmp) {
        $error = "File tidak ditemukan / gagal diupload.";
    } else {
        $ext = strtolower(pathinfo($fileNama, PATHINFO_EXTENSION));

        /* --- reset data transaksi --- */
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("DELETE FROM detail_transaksi");
        $conn->query("DELETE FROM transaksi");
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        /* --- baca baris data --- */
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($fileTmp, 'r');
            if ($handle) {
                while (($line = fgetcsv($handle)) !== false) {
                    if (count(array_filter($line))) $rows[] = $line;
                }
                fclose($handle);
            }
            array_shift($rows); /* buang baris header */
        } else {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require __DIR__ . '/vendor/autoload.php';
                $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmp)->getActiveSheet()->toArray();
                for ($i = 1; $i < count($sheet); $i++) {
                    if (array_filter((array)$sheet[$i])) $rows[] = $sheet[$i];
                }
            } else {
                $error = "Format file harus CSV, atau install PhpSpreadsheet dulu untuk XLSX (composer require phpoffice/phpspreadsheet).";
            }
        }

        /* --- import ke database --- */
        if (!$error && $rows) {
            $lastKey   = "";
            $id_transaksi = 0;
            $transaksi_total = 0;
            $baris_proses = 0;

            foreach ($rows as $r) {
                $rawDate     = isset($r[0]) ? trim($r[0]) : '';
                $menu        = isset($r[1]) ? trim($r[1]) : '';
                $harga_menu  = isset($r[2]) ? (int)$r[2] : 0;
                $jumlah_menu = isset($r[3]) ? (int)$r[3] : 1;
                $gambar      = isset($r[4]) ? trim($r[4]) : '';
                $kategori    = isset($r[5]) ? strtolower(trim($r[5])) : '';

                if (!$rawDate || !$menu) continue;

                $parsed = parse_tanggal($rawDate);
                if (!$parsed) continue;
                [$y, $m, $d, $hour, $minute] = $parsed;
                $tanggal = sprintf('%04d-%02d-%02d', $y, $m, $d);

                if ($kategori !== 'makanan' && $kategori !== 'minuman') {
                    $kategori = deteksi_kategori($menu);
                }

                /* kelompokkan transaksi per tanggal (atau per tanggal+jam bila ada waktu) */
                $hasTime = (bool)preg_match('/\d{1,2}[:.]\d{2}/', $rawDate);
                $transKey = $hasTime
                    ? sprintf('%04d-%02d-%02d %02d:%02d', $y, $m, $d, $hour, $minute)
                    : $tanggal;

                $menuE    = $conn->real_escape_string($menu);
                $gambarE  = $conn->real_escape_string($gambar);
                $kategoriE = $conn->real_escape_string($kategori);
                $tanggalE = $conn->real_escape_string($tanggal);

                /* cek / insert menu */
                $q = $conn->query("SELECT id_menu, harga, kategori FROM menu WHERE nama_menu='$menuE'");
                if ($q && $q->num_rows > 0) {
                    $rM = $q->fetch_assoc();
                    $id_menu = (int)$rM['id_menu'];
                    if ($rM['harga'] == 0 && $harga_menu > 0) {
                        $conn->query("UPDATE menu SET harga=$harga_menu WHERE id_menu=$id_menu");
                    }
                    if ($rM['kategori'] !== $kategoriE) {
                        $conn->query("UPDATE menu SET kategori='$kategoriE' WHERE id_menu=$id_menu");
                    }
                } else {
                    $conn->query("INSERT INTO menu (nama_menu,harga,gambar,kategori) VALUES ('$menuE',$harga_menu,'$gambarE','$kategoriE')");
                    $id_menu = $conn->insert_id;
                }

                /* transaksi baru per kelompok */
                if ($transKey !== $lastKey) {
                    if ($id_transaksi > 0) {
                        $conn->query("UPDATE transaksi SET total=$transaksi_total WHERE id_transaksi=$id_transaksi");
                    }
                    $conn->query("INSERT INTO transaksi (tanggal,total,jam) VALUES ('$tanggalE',0,$hour)");
                    $id_transaksi = $conn->insert_id;
                    $transaksi_total = 0;
                    $lastKey = $transKey;
                }

                $subtotal = $jumlah_menu * $harga_menu;
                $transaksi_total += $subtotal;

                $conn->query("INSERT INTO detail_transaksi (id_transaksi,id_menu,jumlah,subtotal) VALUES ($id_transaksi,$id_menu,$jumlah_menu,$subtotal)");
                $baris_proses++;
            }

            if ($id_transaksi > 0) {
                $conn->query("UPDATE transaksi SET total=$transaksi_total WHERE id_transaksi=$id_transaksi");
            }

            $pesan = "Data transaksi berhasil diimport: $baris_proses baris.";
        } elseif (!$error && !$rows) {
            $error = "File tidak berisi data yang valid. Pastikan kolom = Tanggal, Menu, Harga, Jumlah.";
        }
    }
}

/* ================= RESET DATA ================= */
if (isset($_POST['reset_data'])) {
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("DELETE FROM detail_transaksi");
    $conn->query("DELETE FROM transaksi");
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    $pesan = "Semua data transaksi telah dihapus. Silakan upload ulang.";
}

/* ================= FILTER ANALISIS ================= */
$dari  = isset($_GET['dari']) && $_GET['dari'] !== ''  ? $conn->real_escape_string($_GET['dari'])  : '';
$sampai = isset($_GET['sampai']) && $_GET['sampai'] !== '' ? $conn->real_escape_string($_GET['sampai']) : '';
$min_support = isset($_GET['min_support']) && $_GET['min_support'] !== '' ? max(0, min(100, (float)$_GET['min_support'])) : 1;
$min_conf    = isset($_GET['min_conf']) && $_GET['min_conf'] !== ''    ? max(0, min(100, (float)$_GET['min_conf']))    : 50;

$where = "";
if ($dari  !== '') $where .= " AND t.tanggal >= '$dari'";
if ($sampai !== '') $where .= " AND t.tanggal <= '$sampai'";
$whereClause = "WHERE 1=1" . $where;

/* ================= DATA TRANSAKSI (per transaksi) ================= */
$data = [];
$q = $conn->query("
    SELECT t.id_transaksi, m.nama_menu
    FROM detail_transaksi d
    JOIN transaksi t ON d.id_transaksi = t.id_transaksi
    JOIN menu m ON d.id_menu = m.id_menu
    $whereClause
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $data[$r['id_transaksi']][] = $r['nama_menu'];
    }
}

/* ================= APRIORI - SUPPORT ================= */
$total = count($data);
$itemCount = [];
foreach ($data as $t) {
    foreach (array_unique($t) as $item) {
        $itemCount[$item] = ($itemCount[$item] ?? 0) + 1;
    }
}

$support = [];
if ($total > 0) {
    foreach ($itemCount as $item => $count) {
        $support[] = [
            'menu'   => $item,
            'jumlah' => $count,
            'support'=> round(($count / $total) * 100, 2)
        ];
    }
    usort($support, fn($a, $b) => $b['support'] <=> $a['support']);
}

/* ================= APRIORI - FREKUEN ITEMSET (min_support) ================= */
$freq1 = [];
foreach ($itemCount as $item => $cnt) {
    if ($total > 0 && ($cnt / $total) >= ($min_support / 100)) {
        $freq1[$item] = $cnt;
    }
}

$pair = [];
foreach ($data as $t) {
    $items = array_values(array_unique($t));
    $n = count($items);
    for ($i = 0; $i < $n; $i++) {
        if (!isset($freq1[$items[$i]])) continue;
        for ($j = $i + 1; $j < $n; $j++) {
            if (!isset($freq1[$items[$j]])) continue;
            $pair[$items[$i]][$items[$j]] = ($pair[$items[$i]][$items[$j]] ?? 0) + 1;
            $pair[$items[$j]][$items[$i]] = ($pair[$items[$j]][$items[$i]] ?? 0) + 1;
        }
    }
}

/* ================= ATURAN ASOSIASI (confidence + LIFT) ================= */
$rules = [];
foreach ($pair as $A => $v) {
    foreach ($v as $B => $ab) {
        $supA = $itemCount[$A] ?? 0;
        $supB = $itemCount[$B] ?? 0;
        if ($supA == 0 || $supB == 0) continue;
        $conf = ($ab / $supA) * 100;
        $lift = ($total > 0) ? ($total * $ab) / ($supA * $supB) : 0;
        if ($conf >= $min_conf && $lift > 1) {
            $rules[] = [
                'A'    => $A,
                'B'    => $B,
                'sup'  => $ab,
                'supp' => $total ? round(($ab / $total) * 100, 2) : 0,
                'conf' => round($conf, 2),
                'lift' => round($lift, 2)
            ];
        }
    }
}
usort($rules, fn($a, $b) => $b['lift'] <=> $a['lift']);

/* ================= ANALISIS PENDAPATAN / ABC ================= */
$revList = [];
$totalRev = 0;
$q = $conn->query("
    SELECT m.nama_menu, m.kategori, m.harga, SUM(d.jumlah) AS qty, SUM(d.subtotal) AS rev
    FROM detail_transaksi d
    JOIN transaksi t ON d.id_transaksi = t.id_transaksi
    JOIN menu m ON d.id_menu = m.id_menu
    $whereClause
    GROUP BY m.id_menu
    ORDER BY rev DESC
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $revList[] = $r;
        $totalRev += (int)$r['rev'];
    }
}

$cum = 0;
foreach ($revList as &$it) {
    $it['pct'] = $totalRev > 0 ? round($it['rev'] / $totalRev * 100, 2) : 0;
    $cum += $it['pct'];
    $it['cum'] = round($cum, 2);
    $it['kls'] = ($cum <= 70) ? 'A' : (($cum <= 90) ? 'B' : 'C');
}
unset($it);

/* ================= TREN WAKTU ================= */
$trendDate = [];
$q = $conn->query("SELECT t.tanggal, COUNT(*) AS n, SUM(t.total) AS rev FROM transaksi t $whereClause GROUP BY t.tanggal ORDER BY t.tanggal");
if ($q) while ($r = $q->fetch_assoc()) $trendDate[] = ['tanggal' => $r['tanggal'], 'n' => (int)$r['n'], 'rev' => (int)$r['rev']];

$trendJam = array_fill(0, 24, 0);
$q = $conn->query("SELECT t.jam, COUNT(*) AS n FROM transaksi t $whereClause GROUP BY t.jam");
if ($q) while ($r = $q->fetch_assoc()) { $j = (int)$r['jam']; if ($j >= 0 && $j <= 23) $trendJam[$j] = (int)$r['n']; }

$trendHari = array_fill(0, 7, 0);
$q = $conn->query("SELECT DAYOFWEEK(t.tanggal) AS dw, COUNT(*) AS n FROM transaksi t $whereClause GROUP BY dw");
if ($q) while ($r = $q->fetch_assoc()) { $dw = (int)$r['dw'] - 1; if ($dw >= 0 && $dw <= 6) $trendHari[$dw] = (int)$r['n']; }

$topMenu = array_slice($revList, 0, 10);

$katRev = ['makanan' => 0, 'minuman' => 0];
foreach ($revList as $it) {
    $k = ($it['kategori'] === 'minuman') ? 'minuman' : 'makanan';
    $katRev[$k] += (int)$it['rev'];
}

/* ================= DATA CHART ================= */
$chDateLabels = []; $chDateRev = []; $chDateN = [];
foreach ($trendDate as $d) { $chDateLabels[] = $d['tanggal']; $chDateRev[] = $d['rev']; $chDateN[] = $d['n']; }
$chJamLabels = []; $chJamVals = [];
for ($h = 0; $h < 24; $h++) { $chJamLabels[] = sprintf('%02d:00', $h); $chJamVals[] = $trendJam[$h]; }
$chHariLabels = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$chHariVals = $trendHari;
$chTopLabels = []; $chTopVals = [];
foreach ($topMenu as $t) { $chTopLabels[] = $t['nama_menu']; $chTopVals[] = (int)$t['rev']; }
$chKatLabels = ['Makanan', 'Minuman'];
$chKatVals = [$katRev['makanan'], $katRev['minuman']];

/* ================= KPI ================= */
$kpiTotal = $total;
$kpiRev = array_sum(array_column($revList, 'rev'));
$kpiItems = 0;
$q = $conn->query("SELECT COUNT(*) AS c FROM detail_transaksi d JOIN transaksi t ON d.id_transaksi = t.id_transaksi $whereClause");
if ($q) $kpiItems = (int)$q->fetch_assoc()['c'];
$kpiAvgItems = $kpiTotal > 0 ? round($kpiItems / $kpiTotal, 2) : 0;
$kpiTopMenu = $revList[0]['nama_menu'] ?? '-';
$distinctDays = count($trendDate);
$kpiPerDay = $distinctDays > 0 ? round($kpiTotal / $distinctDays, 1) : 0;

/* ================= REKOMENDASI KEPUTUSAN ================= */
$rekom = [];
$hargaMap = [];
foreach ($revList as $it) $hargaMap[$it['nama_menu']] = (int)$it['harga'];

if ($total > 0) {
    /* 1. Bundling dari aturan dengan lift tertinggi */
    $topRules = array_slice($rules, 0, 3);
    foreach ($topRules as $r) {
        $hB = $hargaMap[$r['B']] ?? 0;
        $nonBuy = $itemCount[$r['A']] * (1 - $r['conf'] / 100);
        $pot = (int)round($nonBuy * 0.10 * $hB);
        $rekom[] = [
            'icon'  => '🎯',
            'judul' => "Paket Bundling: {$r['A']} + {$r['B']}",
            'isi'   => "Pelanggan yang membeli <b>{$r['A']}</b> punya peluang <b>{$r['conf']}%</b> untuk juga membeli <b>{$r['B']}</b> (Lift {$r['lift']} — asosiasi lebih kuat dari kebetulan). Perkiraan potensi tambahan pendapatan ≈ <b>Rp " . number_format($pot, 0, ',', '.') . "</b> jika 10% pembeli {$r['A']} yang belum membeli {$r['B']} ikut mengambil paket.",
            'aksi'  => "Buat paket gabungan dengan harga sedikit lebih murah dan tampilkan di menu utama / kartu promo."
        ];
    }

    /* 2. Menu unggulan */
    if (isset($revList[0])) {
        $top = $revList[0];
        $rekom[] = [
            'icon'  => '⭐',
            'judul' => "Menu Unggulan: {$top['nama_menu']}",
            'isi'   => "Menyumbang <b>{$top['pct']}%</b> dari total pendapatan ({$top['qty']} pcs terjual). Ini adalah menu andalan yang paling diminati.",
            'aksi'  => "Jadikan menu andalan: posisikan paling atas di daftar menu, jaga kualitas & stok bahan baku selalu tersedia."
        ];
    }

    /* 3. Menu perlu evaluasi (kelas C, pendapatan kecil) */
    $cItems = array_values(array_filter($revList, fn($it) => $it['kls'] === 'C'));
    $dead = array_slice($cItems, 0, 3);
    foreach ($dead as $dm) {
        $rekom[] = [
            'icon'  => '⚠️',
            'judul' => "Evaluasi Menu: {$dm['nama_menu']}",
            'isi'   => "Pendapatan hanya <b>Rp " . number_format($dm['rev'], 0, ',', '.') . "</b> ({$dm['pct']}% dari total). Menu ini kurang diminati dan membebani efisiensi pengelolaan.",
            'aksi'  => "Uji promosi/harga selama 2 minggu; jika tidak ada peningkatan, hapus atau ganti dengan varian baru."
        ];
    }

    /* 4. Jam ramai / sepi */
    $maxJam = array_keys($trendJam, max($trendJam))[0];
    $rekom[] = [
        'icon'  => '🕒',
        'judul' => "Jam Terramai: {$maxJam}:00 – " . (($maxJam + 1) % 24) . ":00",
        'isi'   => "<b>{$trendJam[$maxJam]}</b> transaksi terjadi pada jam ini. Di sinilah puncak antrean dan permintaan pelayanan.",
        'aksi'  => "Tambah staf/kasir pada jam ini, siapkan stok bahan baku cukup, dan pertimbangkan layanan pre-order."
    ];
    $positiveJam = array_filter($trendJam, fn($v) => $v > 0);
    if ($positiveJam) {
        $minN = min($positiveJam);
        $minJamVals = array_keys($trendJam, $minN);
        $minJam = $minJamVals[0];
        if ($minN < max($trendJam) / 2) {
            $rekom[] = [
                'icon'  => '🌙',
                'judul' => "Jam Sepi: {$minJam}:00 – " . (($minJam + 1) % 24) . ":00",
                'isi'   => "Hanya <b>{$minN}</b> transaksi di jam ini. Kapasitas layanan menganggur.",
                'aksi'  => "Tawarkan promo happy hour / paket spesial di jam sepi untuk menarik lebih banyak pelanggan."
            ];
        }
    }

    /* 5. Hari terramai */
    $busyIdx = array_keys($trendHari, max($trendHari))[0];
    $hariNama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $rekom[] = [
        'icon'  => '📅',
        'judul' => "Hari Terramai: {$hariNama[$busyIdx]}",
        'isi'   => "<b>{$trendHari[$busyIdx]}</b> transaksi terjadi pada hari {$hariNama[$busyIdx]}. Hari ini menjadi penentu performa mingguan.",
        'aksi'  => "Jadwalkan staf lebih banyak & promosi khusus di hari {$hariNama[$busyIdx]}; manfaatkan untuk kampanye pelanggan baru."
    ];

    /* 6. Kategori unggulan */
    $katLead = ($katRev['minuman'] >= $katRev['makanan']) ? 'minuman' : 'makanan';
    $katLeadNama = ($katLead === 'minuman') ? 'Minuman' : 'Makanan';
    $katShare = $totalRev > 0 ? round($katRev[$katLead] / $totalRev * 100, 1) : 0;
    $rekom[] = [
        'icon'  => '🧋',
        'judul' => "Kategori Terkuat: {$katLeadNama}",
        'isi'   => "Kategori <b>{$katLeadNama}</b> menyumbang <b>{$katShare}%</b> dari total pendapatan.",
        'aksi'  => ($katLead === 'minuman')
            ? "Perbanyak variasi menu minuman & jaga kualitas barista sebagai daya tarik utama."
            : "Perbanyak variasi menu makanan & kelola dapur sebagai keunggulan kompetitif."
    ];
}

/* ================= KATEGORI MENU UNTUK BADGE ================= */
$kategoriMenu = [];
$q = $conn->query("SELECT nama_menu, kategori FROM menu");
if ($q) while ($r = $q->fetch_assoc()) $kategoriMenu[$r['nama_menu']] = $r['kategori'];

$makanan = [];
$minuman = [];
foreach ($support as $s) {
    $kat = $kategoriMenu[$s['menu']] ?? '';
    if ($kat === 'minuman' || ($kat === '' && preg_match(MINUMAN_PATTERN, strtolower($s['menu']))))
        $minuman[] = $s;
    else
        $makanan[] = $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analisis Story Cafe - Apriori</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 0; color: #1f2937; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px 40px; }
        header { background: linear-gradient(135deg, #0d6efd, #6f42c1); color: #fff; padding: 28px 0 22px; margin-bottom: 24px; }
        header .inner { max-width: 1100px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        header h1 { margin: 0; font-size: 24px; }
        header p { margin: 4px 0 0; opacity: .85; font-size: 13px; }
        .print-btn { background: #fff; color: #0d6efd; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        nav { background: #fff; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 10; }
        nav .inner { max-width: 1100px; margin: 0 auto; padding: 0 20px; display: flex; gap: 6px; overflow-x: auto; }
        nav a { padding: 12px 14px; color: #4b5563; text-decoration: none; font-size: 13px; white-space: nowrap; border-bottom: 3px solid transparent; }
        nav a:hover { color: #0d6efd; border-bottom-color: #0d6efd; }
        .box { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        h2 { color: #111827; font-size: 18px; margin: 0 0 14px; padding-bottom: 8px; border-bottom: 2px solid #0d6efd; }
        h3 { font-size: 15px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #dbe2ea; padding: 7px 8px; text-align: center; }
        th { background: #0d6efd; color: #fff; }
        tr:nth-child(even) { background: #f7fafc; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: bold; font-size: 14px; }
        .alert.ok { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert.err { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .hint { font-size: 12px; color: #6b7280; margin-top: 10px; line-height: 1.6; }
        .hint code, code { background: #eef2f7; padding: 1px 5px; border-radius: 3px; }
        button, .btn { background: #0d6efd; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        button:hover, .btn:hover { background: #0b5ed7; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #bb2d3b; }
        .btn-light { background: #eef2f7; color: #374151; }
        .btn-light:hover { background: #dde5ee; }
        input[type=file], input[type=date], input[type=number] { padding: 7px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .kpi { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07); border-left: 4px solid #0d6efd; }
        .kpi .label { font-size: 12px; color: #6b7280; margin-bottom: 6px; }
        .kpi .value { font-size: 20px; font-weight: bold; color: #111827; }
        .kpi .sub { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
        .badge.mkn { background: #cfe2ff; color: #084298; }
        .badge.mnm { background: #d1e7dd; color: #0f5132; }
        .badge.a { background: #198754; color: #fff; }
        .badge.b { background: #ffc107; color: #111; }
        .badge.c { background: #dc3545; color: #fff; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .chart-grid .full { grid-column: 1 / -1; }
        .chart-box { background: #fafcff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .chart-box h3 { text-align: center; font-size: 13px; color: #374151; }
        .chart-box canvas { max-height: 260px; }
        .rek-card { display: flex; gap: 12px; padding: 14px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #0d6efd; background: #f8faff; }
        .rek-card .icon { font-size: 22px; flex-shrink: 0; }
        .rek-card .isi { font-size: 13px; line-height: 1.55; color: #374151; flex: 1; }
        .rek-card .aksi { margin-top: 8px; font-size: 12px; background: #e7f0ff; border-left: 3px solid #0d6efd; padding: 8px 10px; border-radius: 4px; color: #084298; }
        .filter-bar { background: #eef2f7; border-radius: 8px; padding: 12px 14px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filter-bar label { font-size: 11px; color: #6b7280; display: block; margin-bottom: 3px; }
        @media (max-width: 760px) { .chart-grid { grid-template-columns: 1fr; } }
        @media print { nav, .print-btn, .upload-box, .filter-bar { display: none !important; } body { background: #fff; } }
    </style>
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
    </div>
</nav>

<div class="container">
    <header style="border-radius:0 0 0 0;">
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
        <div class="kpi" style="border-left-color:#198754"><div class="label">Menu Terlaris</div><div class="value" style="font-size:15px"><?= htmlspecialchars($kpiTopMenu) ?></div><div class="sub">berdasarkan pendapatan</div></div>
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
        <form method="post" class="row" style="margin-top:10px">
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
            <?php foreach ($rekom as $r): ?>
                <div class="rek-card">
                    <div class="icon"><?= $r['icon'] ?></div>
                    <div class="isi">
                        <h3><?= $r['judul'] ?></h3>
                        <p style="margin:0"><?= $r['isi'] ?></p>
                        <div class="aksi">⚙️ Tindakan: <?= $r['aksi'] ?></div>
                    </div>
                </div>
            <?php endforeach ?>
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
                    <td style="text-align:left"><?= htmlspecialchars($it['nama_menu']) ?></td>
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
                    <td style="text-align:left"><?= htmlspecialchars($s['menu']) ?></td>
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
                    <td style="text-align:left"><b><?= htmlspecialchars($c['A']) ?></b></td>
                    <td>→</td>
                    <td style="text-align:left"><b><?= htmlspecialchars($c['B']) ?></b></td>
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
        <ul style="font-size:13px; line-height:1.8; color:#374151">
            <li><b>Support</b> — frekuensi kemunculan sebuah menu (atau kombinasi) terhadap seluruh transaksi. Makin tinggi, makin populer.</li>
            <li><b>Confidence</b> — peluang pelanggan membeli B jika sudah membeli A. Dasar rekomendasi bundling.</li>
            <li><b>Lift</b> — rasio kekuatan asosiasi. Lift &gt; 1 = kombinasi nyata bukan kebetulan; lift = 1 = tidak ada keterkaitan.</li>
            <li><b>Analisis ABC</b> — memetakan kontribusi pendapatan tiap menu untuk prioritas promosi, harga, dan kelayakan menu (efisiensi pengelolaan menu).</li>
            <li><b>Rekomendasi Keputusan</b> — terjemahan langsung hasil analisis menjadi tindakan pemasaran, layanan, dan menu.</li>
        </ul>
    </div>

</div>

<script>
<?php if ($total > 0): ?>
const colors = ['#0d6efd','#6f42c1','#198754','#ffc107','#dc3545','#0dcaf0','#fd7e14','#20c997','#e83e8c','#6c757d'];
Chart.defaults.font.family = 'Segoe UI, Arial, sans-serif';
Chart.defaults.font.size = 11;

new Chart(document.getElementById('chartTren'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chDateLabels) ?>,
        datasets: [
            { label: 'Pendapatan (Rp)', data: <?= json_encode($chDateRev) ?>, backgroundColor: '#0d6efd', borderRadius: 3 },
            { label: 'Transaksi', data: <?= json_encode($chDateN) ?>, type: 'line', borderColor: '#198754', backgroundColor: '#198754', yAxisID: 'y1', tension: .3 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: 'right', grid: { display: false } } } }
});

new Chart(document.getElementById('chartJam'), {
    type: 'bar',
    data: { labels: <?= json_encode($chJamLabels) ?>, datasets: [{ label: 'Transaksi', data: <?= json_encode($chJamVals) ?>, backgroundColor: '#6f42c1', borderRadius: 3 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartHari'), {
    type: 'bar',
    data: { labels: <?= json_encode($chHariLabels) ?>, datasets: [{ label: 'Transaksi', data: <?= json_encode($chHariVals) ?>, backgroundColor: '#20c997', borderRadius: 3 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartTopMenu'), {
    type: 'bar',
    data: { labels: <?= json_encode($chTopLabels) ?>, datasets: [{ label: 'Pendapatan (Rp)', data: <?= json_encode($chTopVals) ?>, backgroundColor: colors.slice(0, <?= count($chTopVals) ?>), borderRadius: 3 }] },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartKategori'), {
    type: 'doughnut',
    data: { labels: <?= json_encode($chKatLabels) ?>, datasets: [{ data: <?= json_encode($chKatVals) ?>, backgroundColor: ['#ffc107', '#0d6efd'] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
<?php endif ?>
</script>

</body>
</html>
