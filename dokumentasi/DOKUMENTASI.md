# Dokumentasi Aplikasi Parkir

Aplikasi Sistem Informasi Parkir berbasis Laravel 12 (PHP 8.2+) dan MySQL. Dokumen ini memuat deskripsi program (Bab 2 panduan), ERD (Bab 3), dan dokumentasi fungsi/prosedur (Bab 16).

---

## 1. Deskripsi Program

### 1.1 Aktor dan hak akses

| Aktor | Peran |
|---|---|
| Admin | Mengelola data master (user, tarif, area parkir, kendaraan) dan melihat log aktivitas |
| Petugas | Mencatat kendaraan masuk dan keluar, serta mencetak struk parkir |
| Owner | Melihat rekap transaksi dan total pendapatan sesuai rentang tanggal |

### 1.2 Matriks fitur

| Fitur | Admin | Petugas | Owner |
|---|:---:|:---:|:---:|
| Login/Logout | ✔ | ✔ | ✔ |
| CRUD User | ✔ | | |
| CRUD Tarif Parkir | ✔ | | |
| CRUD Area Parkir | ✔ | | |
| CRUD Kendaraan | ✔ | | |
| Akses Log Aktivitas | ✔ | | |
| Transaksi masuk/keluar | | ✔ | |
| Cetak Struk Parkir | | ✔ | |
| Rekap Transaksi | | | ✔ |

### 1.3 Aturan bisnis utama

- **Kendaraan masuk**: kendaraan yang masih berstatus parkir tidak bisa dicatat masuk lagi. Masuk ditolak jika kapasitas area sudah penuh.
- **Perhitungan biaya**: selisih waktu masuk dan keluar dalam menit dibagi 60 lalu dibulatkan ke atas. Durasi minimal 1 jam. `biaya_total = durasi_jam × tarif_per_jam`.
- **Kapasitas area**: kolom `terisi` bertambah saat kendaraan masuk dan berkurang saat kendaraan keluar. Kolom ini tidak bisa diubah langsung dari form. Kapasitas tidak boleh diturunkan di bawah `terisi`.
- **Hapus data**: data yang sudah dipakai transaksi tidak bisa dihapus dan akan menampilkan pesan. Admin tidak bisa menghapus atau menurunkan role akunnya sendiri.
- **Struk**: hanya tersedia untuk transaksi yang sudah keluar.

### 1.4 Akun awal (dari seeder)

| Role | Username | Password |
|---|---|---|
| Admin | admin | admin123 |
| Petugas | petugas | petugas123 |
| Owner | owner | owner123 |

Ganti password ini sebelum digunakan sungguhan.

---

## 2. ERD dan Struktur Database

Diagram ERD ada di `erd.png`. Ringkasan 6 tabel utama:

| Tabel | Fungsi | Relasi |
|---|---|---|
| tb_user | Akun admin, petugas, owner | 1 user → N kendaraan, N transaksi, N log |
| tb_kendaraan | Data kendaraan terdaftar | FK `id_user` → tb_user |
| tb_tarif | Tarif per jam per jenis kendaraan | 1 tarif → N transaksi |
| tb_area_parkir | Area parkir, kapasitas, dan jumlah terisi | 1 area → N transaksi |
| tb_transaksi | Catatan parkir masuk dan keluar | FK ke tb_kendaraan, tb_tarif, tb_user, tb_area_parkir |
| tb_log_aktivitas | Riwayat aktivitas pengguna | FK `id_user` → tb_user |

Urutan migrasi sudah benar: tabel induk dibuat lebih dulu sebelum tabel yang memiliki foreign key.

---

## 3. Daftar Route

| Method | URI | Nama route | Middleware |
|---|---|---|---|
| GET | / | (redirect ke login) | - |
| GET/POST | /login | login / login.post | - |
| POST | /logout | logout | auth |
| GET | /admin/dashboard | admin.dashboard | role:admin |
| GET/POST | /admin/user, /admin/user/create, /admin/user/{user}/edit, PUT/DELETE /admin/user/{user} | admin.user.* | role:admin |
| (sama) | /admin/tarif | admin.tarif.* | role:admin |
| (sama) | /admin/area | admin.area.* | role:admin |
| (sama) | /admin/kendaraan | admin.kendaraan.* | role:admin |
| GET | /admin/log-aktivitas | admin.log.index | role:admin |
| GET | /petugas/dashboard | petugas.dashboard | role:petugas |
| GET | /petugas/transaksi | petugas.transaksi.index | role:petugas |
| GET/POST | /petugas/transaksi/masuk | petugas.transaksi.formMasuk / prosesMasuk | role:petugas |
| GET/POST | /petugas/transaksi/keluar/{id} | petugas.transaksi.formKeluar / prosesKeluar | role:petugas |
| GET | /petugas/struk/{id} | petugas.transaksi.struk | role:petugas |
| GET | /owner/dashboard | owner.dashboard | role:owner |
| GET | /owner/rekap | owner.rekap.index | role:owner |

Catatan: `Route::resource` dipasang dengan `->except('show')` karena halaman detail tidak dipakai. Dengan begitu tidak ada route yang mengarah ke method yang tidak ada.

---

## 4. Dokumentasi Fungsi / Prosedur

| Modul | Fungsi/Method | Input | Proses | Output |
|---|---|---|---|---|
| Auth | `AuthController@login` | username, password | Validasi kredensial, cek `status_aktif`, catat log "Login ke sistem" | Redirect ke dashboard sesuai role, atau kembali dengan pesan error |
| Auth | `AuthController@logout` | session aktif | Catat log "Logout dari sistem", logout, invalidasi session | Redirect ke halaman login |
| Middleware | `CheckRole@handle` | role yang diminta | Cek user login dan role sama | Lanjut, atau 403 |
| Admin User | `UserController@index` | - | Ambil data user terbaru dengan paginate(10) | Tabel user |
| Admin User | `UserController@store` | nama_lengkap, username, password, role | Validasi (username unik, password min 6), hash password, simpan | Redirect ke daftar dengan pesan sukses |
| Admin User | `UserController@update` | field user, password (opsional) | Cegah admin menurunkan/menonaktifkan diri sendiri, update, hash password jika diisi | Redirect dengan pesan sukses atau error |
| Admin User | `UserController@destroy` | user | Cegah hapus akun sendiri, hapus, tangkap error FK | Redirect dengan pesan sukses atau error |
| Admin Tarif | `TarifController@store` | jenis_kendaraan, tarif_per_jam | Validasi (motor/mobil/lainnya, angka ≥ 0), simpan | Redirect dengan pesan sukses |
| Admin Tarif | `TarifController@update` / `destroy` | field tarif | Update, atau hapus dengan penanganan error FK | Redirect dengan pesan |
| Admin Area | `AreaParkirController@update` | nama_area, kapasitas | Tolak jika kapasitas < terisi, update | Redirect dengan pesan |
| Admin Area | `AreaParkirController@destroy` | area | Hapus dengan penanganan error FK | Redirect dengan pesan |
| Admin Kendaraan | `KendaraanController@store` | plat_nomor, jenis_kendaraan, warna, pemilik | Simpan, `id_user` diisi dari user login | Redirect dengan pesan sukses |
| Admin Kendaraan | `KendaraanController@update` / `destroy` | field kendaraan | Update, atau hapus dengan penanganan error FK | Redirect dengan pesan |
| Admin Log | `LogAktivitasController@index` | - | Ambil log terbaru dengan eager loading `user`, paginate(15) | Tabel log |
| Transaksi | `TransaksiController@prosesMasuk` | id_kendaraan, id_tarif, id_area | Cek kendaraan belum parkir, kunci baris area (`lockForUpdate`), cek kapasitas, simpan transaksi, `terisi + 1`, catat log | Redirect ke daftar transaksi aktif, atau kembali dengan pesan error |
| Transaksi | `TransaksiController@formKeluar` | id transaksi | Tolak jika sudah keluar, hitung durasi (ceil, min 1 jam) dan biaya | Form konfirmasi dengan total biaya |
| Transaksi | `TransaksiController@prosesKeluar` | id transaksi | Tolak jika sudah keluar, update status dalam transaksi DB, `terisi - 1`, catat log | Redirect ke halaman struk |
| Transaksi | `TransaksiController@cetakStruk` | id transaksi | Ambil data lengkap dengan relasi, tolak jika belum keluar | Halaman struk siap cetak |
| Rekap | `RekapController@index` | dari, sampai (opsional) | Filter status keluar dan rentang `waktu_keluar`, hitung total sebelum paginate | Tabel rekap dan total pendapatan |

Pola CRUD untuk Tarif, Area, Kendaraan, dan User sama: input dari form → validasi di server (`$request->validate`) → simpan, update, atau hapus → redirect dengan pesan.

---

## 5. Cara Menjalankan (ringkas)

1. Pastikan PHP 8.2+, Composer, dan MySQL (XAMPP/Laragon) berjalan.
2. Buat database `db_parkir` di phpMyAdmin, atau impor `db_parkir.sql`.
3. `composer install`, lalu salin `.env.example` menjadi `.env` jika belum ada, dan jalankan `php artisan key:generate`.
4. Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` di `.env`.
5. `php artisan migrate --seed` (atau `php artisan migrate:fresh --seed` untuk reset total).
6. `php artisan serve`, lalu buka http://127.0.0.1:8000.
