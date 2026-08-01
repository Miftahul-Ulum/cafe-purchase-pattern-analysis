# Sistem Analisis Pola Minat Beli - Story Cafe

Aplikasi berbasis PHP untuk menganalisis **pola minat beli konsumen di Story Cafe** menggunakan algoritma **Apriori**, dilengkapi dashboard pendukung keputusan untuk:

- **Strategi pemasaran** — aturan asosiasi, bundling, promosi tertarget
- **Peningkatan layanan** — analisis jam ramai/sepi, tren harian
- **Efisiensi pengelolaan menu** — analisis pendapatan ABC, evaluasi menu

## Fitur

- 📥 Upload data transaksi **CSV / XLSX** (database dibuat otomatis)
- 🔗 Algoritma **Apriori** dengan filter **min support**, **min confidence**, dan **Lift**
- 💰 **Analisis pendapatan menu (ABC)** untuk prioritas menu
- 📈 **Tren waktu** — pendapatan per tanggal, transaksi per jam & per hari
- 📊 **Dashboard KPI** — total transaksi, pendapatan, rata-rata keranjang, menu terlaris
- 💡 **Rekomendasi keputusan** — saran aksi konkret dari hasil analisis
- 🖨️ Laporan dapat dicetak (print-friendly)

## Kebutuhan

| Kebutuhan | Keterangan |
|---|---|
| PHP | 8.0+ (XAMPP) |
| MySQL / MariaDB | dibundel di XAMPP |
| Internet | hanya untuk CDN Chart.js (opsional; tabel tetap berfungsi tanpa internet) |
| Composer | hanya jika ingin dukungan XLSX (`composer require phpoffice/phpspreadsheet`) |

## Cara Menjalankan

1. Salin folder ini ke `C:\xampp\htdocs\`.
2. Jalankan XAMPP (Apache + MySQL).
3. Buka browser: `http://localhost/Analisis/analisis_full.php`
4. Pada form **Upload Data Transaksi**, pilih `contoh_data.csv` lalu klik **Upload & Analisis**.
5. Gunakan **Filter Analisis** untuk mengubah rentang tanggal, min support, dan min confidence.

## Format Data

Kolom (satu baris = satu item dalam transaksi):

```
Tanggal, Menu, Harga, Jumlah, Gambar, Kategori
```

Contoh:

```
2026-07-01 08:30, Cappuccino, 25000, 1, , minuman
2026-07-01 08:30, Croissant, 20000, 1, , makanan
```

- **Tanggal** — boleh berisi jam (`YYYY-MM-DD HH:MM`) untuk analisis jam ramai/sepi.
- **Kategori** — isi `makanan` / `minuman`; jika kosong, sistem mendeteksi otomatis dari nama menu.
- **Gambar** — opsional, bisa dikosongkan.

Detail lengkap: [docs/format_data.md](docs/format_data.md)

## Struktur Repository

```
Analisis/
├── analisis_full.php      # Aplikasi utama (backend + markup)
├── contoh_data.csv        # Contoh data transaksi (14 hari)
├── README.md
├── .gitignore
├── assets/
│   ├── css/
│   │   └── style.css      # Seluruh styling dashboard
│   └── js/
│       └── main.js        # Render grafik (Chart.js) dari window.CHART_DATA
├── docs/
│   └── format_data.md     # Dokumentasi format data CSV/XLSX
└── sql/
    └── schema.sql         # Skema database (otomatis dibuat aplikasi)
```

## Skema Database

Aplikasi membuat database `analisis_apriori` secara otomatis saat diakses. Skema lengkap ada di [sql/schema.sql](sql/schema.sql).

## Contoh Keluaran

- **Aturan Asosiasi** — "Jika membeli Cappuccino, peluang juga membeli Croissant = X% (Lift Y)" → dasar paket bundling.
- **Analisis ABC** — menu kelas A (70% pendapatan) menjadi prioritas; kelas C dievaluasi.
- **Jam Terramai/Tersepi** — dasar penjadwalan staf dan promo *happy hour*.
