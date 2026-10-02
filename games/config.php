<?php
// =====================================================
//  KONFIGURASI UTAMA APLIKASI GAME EDUKASI
// =====================================================
date_default_timezone_set('Asia/Jakarta');

define('APP_DIR', __DIR__);

// Lokasi database SQLite. Default: DI LUAR htdocs, yaitu C:\xampp\app_data
// (aman dari akses browser dan tidak ikut tertimpa saat kode diperbarui).
// Jika folder itu tidak bisa dibuat, aplikasi otomatis memakai app/data.
define('DATA_DIR', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app_data');
define('DB_FILE', 'game_edukasi.sqlite');

// Alamat publik default (bisa diubah admin di menu Pengaturan)
define('DEFAULT_PUBLIC_URL', 'https://smp-kartini-dua.my.id/app');
define('APP_NAME', 'Ruang Game Kelas');
