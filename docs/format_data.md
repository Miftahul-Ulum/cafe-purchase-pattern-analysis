# Format Data Transaksi

Aplikasi membaca file **CSV** (atau **XLSX** bila PhpSpreadsheet terpasang) untuk mengimpor data transaksi.

## Kolom

Satu baris mewakili **satu item dalam satu transaksi**.

| Kolom | Tipe | Wajib | Keterangan |
|---|---|---|---|
| Tanggal | Teks | Ya | Tanggal transaksi, boleh disertai jam |
| Menu | Teks | Ya | Nama menu (akan disimpan di tabel `menu`) |
| Harga | Angka | Opsional | Harga satuan (Rupiah) |
| Jumlah | Angka | Opsional | Jumlah item, default 1 |
| Gambar | Teks | Tidak | Nama/path gambar menu (opsional) |
| Kategori | Teks | Tidak | `makanan` / `minuman`; kosong = deteksi otomatis |

**Header baris pertama** (judul kolom) otomatis dilewati oleh aplikasi.

## Format Tanggal yang Didukung

| Format | Contoh |
|---|---|
| `YYYY-MM-DD` | `2026-07-01` |
| `YYYY-MM-DD HH:MM` | `2026-07-01 14:30` |
| `YYYY-MM-DD HH:MM:SS` | `2026-07-01 14:30:00` |
| `DD/MM/YYYY` | `01/07/2026` |
| `DD/MM/YYYY HH:MM` | `01/07/2026 14:30` |

> Jika kolom Tanggal memuat **jam**, aplikasi mengelompokkan transaksi per
> tanggal+jam sehingga dapat dianalisis jam terramai/tersepi. Jika hanya
> tanggal, seluruh menu pada tanggal yang sama dianggap satu transaksi.

## Deteksi Kategori Otomatis

Bila kolom Kategori kosong, sistem mendeteksi dari nama menu menggunakan
pola kata kunci, contoh: *kopi, latte, americano, cappuccino, espresso,
matcha, teh, juice, soda, susu, jahe*, dll. → dianggap **minuman**.
Selain itu dianggap **makanan**.

Disarankan tetap mengisi kolom Kategori agar hasil klasifikasi akurat.

## Contoh Data

```
Tanggal,Menu,Harga,Jumlah,Gambar,Kategori
2026-07-01 08:30,Cappuccino,25000,1,,minuman
2026-07-01 08:30,Croissant,20000,1,,makanan
2026-07-01 10:15,Americano,20000,1,,minuman
2026-07-02 09:00,Espresso,18000,1,,minuman
2026-07-02 09:00,Brownie,22000,1,,makanan
```

File contoh siap pakai: [`contoh_data.csv`](../contoh_data.csv)

## Catatan

- Setiap upload **menghapus data transaksi lama** (tabel `detail_transaksi`
  dan `transaksi`), lalu mengimpor data baru. Tabel `menu` tetap tersimpan.
- Gunakan tombol **Hapus Data Transaksi** pada aplikasi untuk membersihkan
  data tanpa upload.
- Simpan file sebagai `.csv` (delimiter koma, UTF-8) agar berfungsi tanpa
  instalasi tambahan.
