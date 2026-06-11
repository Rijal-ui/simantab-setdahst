# Sistem Booking Gedung (PHP Native)

Aplikasi berbasis web untuk memanajemen peminjaman gedung.

## Fitur
- **User**: Melihat daftar gedung, jadwal, dan melakukan booking.
- **Admin**: Login, melihat daftar booking, menyetujui/menolak booking.
    admin
    admin123

## Persyaratan
- XAMPP (Apache + MySQL)
- PHP >= 7.4

## Instalasi
1. Pastikan folder ini berada di `htdocs` (misal: `C:\xampp\htdocs\simantab-setdahst`).
2. Nyalakan Apache dan MySQL di XAMPP Control Panel.
3. Buka `phpMyAdmin` (biasanya `http://localhost/phpmyadmin`).
4. Buat database baru dengan nama `booking_db`.
5. Import file `database.sql` ke dalam database `booking_db`.
6. Buka aplikasi di browser: `http://localhost/simantab-setdahst`.

## Login Admin
- URL: `http://localhost/simantab-setdahst/login.php`
- Username: `admin`
- Password: `admin123`

## Struktur Folder
- `/admin` - Halaman khusus admin
- `/assets` - Gambar dan aset statis
- `index.php` - Halaman utama
- `booking.php` - Form booking
- `config.php` - Konfigurasi database
