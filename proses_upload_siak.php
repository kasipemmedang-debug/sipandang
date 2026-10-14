<?php
/**
 * Upload berkas SIAK — form POST biasa (tanpa AJAX).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/inc_siak_berkas.php';
require_once __DIR__ . '/inc_log_aktivitas.php';

$nama_operator = $_SESSION['nama_lengkap'] ?? ($_SESSION['nama'] ?? 'Admin');
sipandang_siak_proses_upload($koneksi, (string) $nama_operator);
