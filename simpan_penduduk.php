<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_simpan.php';
require_once __DIR__ . '/inc_admin_toast.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = sipandang_penduduk_insert($koneksi, $_POST);

    if (!empty($result['ok'])) {
        sipandang_redirect_toast('penduduk.php', 'tambah');
    }

    header('Location: tambah_penduduk.php?' . http_build_query([
        'toast_type' => 'error',
        'msg'        => (string) ($result['message'] ?? 'Gagal menyimpan.'),
    ]));
    exit;
}

header('Location: tambah_penduduk.php');
