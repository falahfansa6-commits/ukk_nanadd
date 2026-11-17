# Catatan Debugging

Dokumen ini berisi dua bagian: (1) error umum beserta solusinya (Bab 17 panduan), dan (2) temuan nyata dari pengujian aplikasi ini beserta perbaikannya.

---

## 1. Error Umum dan Solusinya

| Error | Penyebab | Solusi |
|---|---|---|
| `SQLSTATE[42S02]: Base table not found` | Migration belum dijalankan atau urutan salah | `php artisan migrate:fresh --seed` |
| `Class not found` | Namespace controller salah, atau lupa `use` di `web.php` | Cek kembali namespace di atas file controller dan import di `routes/web.php` |
| `419 Page Expired` | Token CSRF hilang atau lupa `@csrf` di form | Tambahkan `@csrf` di semua `<form>` (method POST/PUT/DELETE) |
| `403 Forbidden` | User login dengan role yang tidak sesuai middleware | Cek middleware `role:` di route, dan pastikan role user benar |
| Halaman blank/putih | Error PHP tersembunyi karena `APP_DEBUG=false` | Set `APP_DEBUG=true` sementara di `.env` untuk melihat pesan error |
| `Access denied for user 'root'@'localhost'` | Password root MySQL di `.env` tidak sesuai | Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan konfigurasi MySQL |
| `Route [admin.xxx] not defined` | Nama route salah atau belum didaftarkan | Cek `php artisan route:list` |
| Tabel `tb_transaksi` tidak bisa dihapus | Data masih dipakai foreign key | Hapus transaksi terkait dulu, atau gunakan nonaktifkan untuk user |

---

## 2. Temuan dari Pengujian dan Perbaikannya

Pengujian dilakukan dua cara: test otomatis (`php artisan test`, 18 skenario) dan pengujian HTTP end-to-end dengan akun tiap role. Test diuji juga pada kode awal untuk memastikan test mampu mendeteksi bug.

| No | Masalah yang ditemukan | Dampak | Perbaikan |
|---|---|---|---|
| 1 | Kendaraan yang sama bisa dicatat masuk dua kali | Dua transaksi aktif untuk satu kendaraan, `terisi` salah | `prosesMasuk` menolak jika kendaraan masih berstatus `masuk` |
| 2 | Keluar diproses dua kali pada transaksi yang sama | `terisi` berkurang dua kali, biaya dihitung ulang | `prosesKeluar` hanya update jika status masih `masuk`, dalam DB transaction |
| 3 | Menghapus kendaraan, tarif, area, atau user yang dipakai transaksi | Error 500 | Penghapusan ditangkap (`QueryException`) dan ditampilkan sebagai pesan |
| 4 | Admin bisa menghapus akunnya sendiri | Sistem bisa kehilangan admin | Ditolak dengan pesan error |
| 5 | Admin bisa menurunkan role atau menonaktifkan akunnya sendiri | Admin terkunci dari sistem | Ditolak dengan pesan error |
| 6 | Kapasitas area bisa diturunkan di bawah jumlah kendaraan terisi | Data kapasitas tidak konsisten | Validasi `kapasitas >= terisi` |
| 7 | Struk bisa dibuka untuk transaksi yang belum keluar | Struk menampilkan jam keluar palsu (waktu sekarang) | Redirect ke daftar transaksi dengan pesan |
| 8 | Form keluar bisa dibuka untuk transaksi yang sudah keluar | Biaya bisa dihitung ulang | Redirect dengan pesan "Transaksi ini sudah selesai" |
| 9 | Dua petugas bisa mengisi slot area terakhir secara bersamaan | Kapasitas bisa terlampaui | Pengecekan kapasitas dilakukan dengan `lockForUpdate()` |
| 10 | Total pendapatan di rekap memakai query yang sudah ber-ORDER BY | Bisa error di MySQL 8 (mode ONLY_FULL_GROUP_BY) | Total dihitung sebelum ORDER BY diterapkan |
| 11 | Route `show` terdaftar tetapi method-nya tidak ada | Membuka URL detail menghasilkan error 500 | `->except('show')` pada resource route |
| 12 | Pesan error dari controller tidak tampil di halaman selain form masuk | Pengguna tidak tahu alasan penolakan | Layout utama menampilkan `session('error')` dan error validasi |
| 13 | `ExampleTest` bawaan Laravel gagal (mengharapkan halaman welcome) | `php artisan test` selalu gagal | Diganti dengan `ParkirFlowTest` yang menguji alur aplikasi |

### Hasil pengujian setelah perbaikan

- `php artisan test`: 18 test lulus, 57 assertion.
- Pada kode awal, 6 dari 18 test gagal (duplikat masuk, double keluar, struk sebelum keluar, hapus akun sendiri, hapus data terpakai, kapasitas di bawah terisi). Ini membuktikan test menangkap bug yang dimaksud.
- Pengujian HTTP end-to-end: login tiap role, CRUD master data, masuk, keluar, cetak struk, rekap pendapatan, dan pembatasan akses 403 berjalan normal.

### Catatan lingkungan pengujian

Saat pengujian, MariaDB 10.11 dipakai sebagai pengganti MySQL. Perilakunya untuk fitur aplikasi ini setara. Untuk pengumpulan, tetap jalankan aplikasi di XAMPP/Laragon sesuai panduan.
