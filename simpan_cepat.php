<?php
/**
 * Legacy handler — delegasi ke inc_penduduk_simpan (status_dasar=HIDUP).
 */
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_penduduk_simpan.php';
require_once __DIR__ . '/inc_admin_toast.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: buat_surat.php');
    exit;
}

$post = $_POST;
if (!empty($post['alamat_tujuan']) && empty($post['alamat'])) {
    $post['alamat'] = $post['alamat_tujuan'];
}

$result = sipandang_penduduk_insert($koneksi, $post);
if (!empty($result['ok'])) {
    $nik = urlencode((string) ($result['nik'] ?? ''));
    header('Location: buat_surat.php?nik=' . $nik . '&' . http_build_query([
        'toast_type' => 'success',
        'msg'        => 'Warga berhasil ditambahkan.',
    ]));
    exit;
}

header('Location: buat_surat.php?' . http_build_query([
    'toast_type' => 'error',
    'msg'        => (string) ($result['message'] ?? 'Gagal menyimpan.'),
]));
exit;
