# TaskFlow

TaskFlow adalah sistem personal untuk mengelola tugas, checklist, progress, deadline, catatan, kategori, dan arsip. Sistem ini menggunakan PHP native (PHP 8.1+), PDO, MySQL/MariaDB, Bootstrap 5, Vanilla JavaScript, dan tidak membutuhkan proses build.

## Instalasi dengan XAMPP

1. Pasang dan jalankan XAMPP; aktifkan **Apache** dan **MySQL**.
2. Salin folder proyek ini ke `C:\xampp\htdocs\taskflow`.
3. Buka `http://localhost/phpmyadmin/`.
4. Pilih tab **Import**, lalu impor `database/taskflow_db.sql`. SQL akan membuat database `taskflow_db` dan kategori contoh.
5. Jika konfigurasi MySQL berbeda dari bawaan XAMPP, edit `config/database.php` atau atur environment variable:
   - `TASKFLOW_DB_HOST` (default `127.0.0.1`)
   - `TASKFLOW_DB_NAME` (default `taskflow_db`)
   - `TASKFLOW_DB_USER` (default `root`)
   - `TASKFLOW_DB_PASS` (default kosong)
6. Buka `http://localhost/taskflow/`.

Untuk nama folder atau base URL lain, letakkan proyek pada direktori tersebut dan gunakan URL yang sesuai (contoh `http://localhost/nama-folder/`). Sistem menggunakan tautan relatif sehingga tidak membutuhkan pengaturan base URL terpisah.

## Fitur

- Dashboard dengan statistik database, tugas terbaru, deadline mendatang, dan daftar overdue.
- Calendar View bulanan untuk melihat tugas berdasarkan deadline, termasuk navigasi bulan dan penambahan tugas langsung dari tanggal pilihan.
- CRUD tugas, filter/pencarian, sorting, dan pagination.
- Checklist AJAX dengan urutan, edit, hapus, toggle, dan sinkronisasi progress/status lewat transaksi database.
- Catatan, activity log, kategori unik, archive/restore, dan penghapusan permanen dengan konfirmasi.
- Tampilan dark mode responsif dan formulir dengan perlindungan CSRF.

## Catatan keamanan

Aplikasi ini tidak menyediakan login. Jangan memublikasikannya secara terbuka tanpa menambahkan autentikasi dan pembatasan akses (misalnya melalui VPN atau konfigurasi Apache). Pengguna yang dapat mengakses aplikasi dapat membaca dan mengubah semua data TaskFlow.

Google Fonts, Bootstrap, Bootstrap Icons, dan SweetAlert2 dimuat dari CDN sehingga koneksi internet diperlukan untuk memuat aset tersebut. Data aplikasi tetap disimpan di MySQL lokal.
