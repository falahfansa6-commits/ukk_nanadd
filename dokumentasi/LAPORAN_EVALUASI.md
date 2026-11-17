# Laporan Evaluasi Aplikasi Parkir

## 18.1 Fitur yang Sudah Berjalan Baik

- [x] Login & Logout (3 role)
- [x] CRUD User
- [x] CRUD Tarif Parkir
- [x] CRUD Area Parkir
- [x] CRUD Kendaraan
- [x] Log Aktivitas
- [x] Transaksi Masuk
- [x] Transaksi Keluar & Hitung Biaya
- [x] Cetak Struk
- [x] Rekap Transaksi (dengan filter tanggal)

Fitur tambahan yang sudah ditangani: pembatasan akses per role (403), pencegahan kendaraan masuk ganda, pencegahan keluar ganda, validasi kapasitas area, dan penanganan penghapusan data yang masih dipakai.

## 18.2 Bug yang Belum Diperbaiki

- Format angka memakai format bawaan `number_format` (contoh: `Rp2,000`). Untuk pengguna Indonesia, format `Rp2.000` lebih lazim. Perubahan ini mudah dilakukan dengan `number_format($x, 0, ',', '.')`, tetapi belum diterapkan agar tetap sesuai panduan.
- User yang sudah pernah login tidak bisa dihapus, karena setiap login tercatat di log aktivitas. Solusinya saat ini adalah menonaktifkan user (`status_aktif = 0`). Belum ada fitur untuk menghapus log per user.
- Halaman dashboard admin, petugas, dan owner hanya berisi teks sambutan dan tautan.
- Tampilan memakai Bootstrap dari CDN, sehingga perlu koneksi internet saat pertama dibuka.

## 18.3 Rencana Pengembangan Berikutnya

- Export rekap ke PDF dan Excel.
- Dashboard grafik pendapatan harian.
- Notifikasi area parkir hampir penuh (misalnya terisi ≥ 80%).
- Format rupiah Indonesia (pemisah titik).
- Nonaktifkan user otomatis menggantikan hapus, dengan tampilan status di daftar user.
