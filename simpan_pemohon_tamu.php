<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_pemohon_tamu.php';

sipandang_require_login(false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Metode tidak valid.']);
    exit;
}

$oleh = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin';
$res  = pemohon_tamu_save_from_post($koneksi, $_POST, $oleh);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
