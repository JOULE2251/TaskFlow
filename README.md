# TaskFlow

TaskFlow adalah sistem personal untuk mengelola tugas, checklist, progress, deadline, catatan, kategori, dan arsip. Sistem ini menggunakan PHP native (PHP 8.1+), PDO, MySQL/MariaDB, Bootstrap 5, Vanilla JavaScript, dan tidak membutuhkan proses build.

## Instalasi dengan XAMPP

1. Pasang dan jalankan XAMPP; aktifkan **Apache** dan **MySQL**.
2. Salin folder proyek ini ke `C:\xampp\htdocs\taskflow`.
3. Buka `http://localhost/phpmyadmin/`.
4. Pilih tab **Import**, lalu impor `database/taskflow_db.sql`. SQL membuat database `taskflow_db` dan skema TaskFlow.
5. Jika konfigurasi MySQL berbeda dari bawaan XAMPP, edit `config/database.php` atau atur environment variable:
   - `TASKFLOW_DB_HOST` (default `127.0.0.1`)
   - `TASKFLOW_DB_NAME` (default `taskflow_db`)
   - `TASKFLOW_DB_USER` (default `root`)
   - `TASKFLOW_DB_PASS` (default kosong)
6. Buat backup database terlebih dahulu. Untuk database lama yang memiliki task atau kategori, jalankan migrasi dari PowerShell dengan email pemilik data legacy yang ditetapkan:
   ```powershell
   $env:TASKFLOW_LEGACY_OWNER_EMAIL = "owner@example.com"
   & "C:\xampp\php\php.exe" "C:\xampp\htdocs\taskflow\database\migrate.php"
   Remove-Item Env:\TASKFLOW_LEGACY_OWNER_EMAIL
   ```
   Migrasi bersifat aditif dan mempertahankan task serta kategorinya. Data legacy hanya dapat diklaim oleh akun dengan email yang ditetapkan. Untuk database baru yang kosong setelah mengimpor skema ini, jalankan migrasi tanpa mengatur variabel tersebut; kategori default dibuat saat akun pertama didaftarkan.
7. Buka `http://localhost/taskflow/`, lalu daftar atau login.

Untuk nama folder atau base URL lain, letakkan proyek pada direktori tersebut dan gunakan URL yang sesuai (contoh `http://localhost/nama-folder/`). Sistem menggunakan tautan relatif sehingga tidak membutuhkan pengaturan base URL terpisah.

## Fitur

- Dashboard dengan statistik database, tugas terbaru, deadline mendatang, dan daftar overdue.
- Calendar View bulanan untuk melihat tugas berdasarkan deadline, termasuk navigasi bulan dan penambahan tugas langsung dari tanggal pilihan.
- Akun pengguna, Personal Workspace privat, Team Workspace, serta workspace switcher.
- Undangan anggota Team Workspace, penerimaan dari Dashboard atau Notifications, daftar anggota, pembatalan undangan, dan pengeluaran anggota. Undangan ditampilkan di aplikasi, bukan dikirim melalui email.
- Proyek personal/team dengan status, tanggal mulai/selesai, detail tugas, progres, pembuatan, pengubahan, dan penghapusan.
- Multi-assignee, diskusi dan komentar tugas, daily work update, project resources, notifikasi in-app untuk tugas baru/penugasan, komentar, catatan, dan tugas selesai, serta dashboard ringkasan tim.
- CRUD tugas, filter/pencarian, sorting, dan pagination.
- Checklist AJAX dengan urutan, edit, hapus, toggle, dan sinkronisasi progress/status lewat transaksi database.
- Catatan dengan nama penulis, komentar diskusi, activity log, kategori unik, archive/restore, dan penghapusan permanen dengan konfirmasi.
- Tampilan dark mode responsif dan formulir dengan perlindungan CSRF.

## Catatan keamanan

Gunakan HTTPS jika aplikasi diakses melalui jaringan. Session menggunakan cookie HttpOnly, SameSite=Lax, dan secure flag saat HTTPS aktif. Operasi formulir memakai CSRF token dan task/category dibatasi ke workspace aktif yang keanggotaannya divalidasi di backend.

Google Fonts, Bootstrap, Bootstrap Icons, dan SweetAlert2 dimuat dari CDN sehingga koneksi internet diperlukan untuk memuat aset tersebut. Data aplikasi tetap disimpan di MySQL lokal.

## Jika Ingin Akses Online tanpa VPS ##

- Langkah Pertama download Cloudflare Tunnel https://github.com/cloudflare/cloudflared/releases/tag/2026.11.1
- Langkah kedua cek status buat folder C:\cloudflared
- Langkah ketiga pindahkan yang sudah didownload ke folder tersebut, lalu ubah namanya menjadi "cloudflared.exe"
- Langkah keempat cek status "cloudflared.exe --version"
- Langkah kelima jalankan server TaskFlow "C:\xampp\php\php.exe -S 127.0.0.1:8000 -t C:\xampp\htdocs\TaskFlow"
- Langkah keenam buka cmd lain dan jalankan "cloudflared.exe tunnel --url http://127.0.0.1:8000"