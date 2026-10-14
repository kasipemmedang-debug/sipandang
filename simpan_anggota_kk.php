<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_simpan.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_admin_toast.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: keluarga.php');
    exit;
}

$no_kk_raw = preg_replace('/\D/', '', (string) ($_POST['no_kk'] ?? ''));
$back_url  = strlen($no_kk_raw) === 16
    ? 'tambah_anggota_kk.php?no_kk=' . urlencode($no_kk_raw)
    : 'keluarga.php';

if (strlen($no_kk_raw) !== 16) {
    header('Location: keluarga.php?' . http_build_query([
        'toast_type' => 'error',
        'msg'        => 'No. KK tidak valid.',
    ]));
    exit;
}

$no_kk_esc = mysqli_real_escape_string($koneksi, $no_kk_raw);
$shdk = trim((string) ($_POST['shdk'] ?? ''));
$shdk_up = strtoupper($shdk);

if ($shdk_up === '01' || strpos($shdk_up, 'KEPALA') !== false) {
    $qKepala = mysqli_query(
        $koneksi,
        "SELECT nik FROM tbl_penduduk
         WHERE no_kk='$no_kk_esc' AND status_dasar='HIDUP'
           AND (shdk='01' OR UPPER(shdk) LIKE '%KEPALA%')
         LIMIT 1"
    );
    if ($qKepala && mysqli_num_rows($qKepala) > 0) {
        header('Location: ' . $back_url . (strpos($back_url, '?') !== false ? '&' : '?') . http_build_query([
            'toast_type' => 'error',
            'msg'        => 'Kartu keluarga ini sudah memiliki Kepala Keluarga. Pilih SHDK lain.',
        ]));
        exit;
    }
}

$result = sipandang_penduduk_insert($koneksi, $_POST);
$redirect = 'detail_kk.php?no_kk=' . urlencode($no_kk_raw);

if (!empty($result['ok'])) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . http_build_query([
        'toast_type' => 'success',
        'msg'        => 'Anggota keluarga berhasil ditambahkan.',
    ]));
    exit;
}

header('Location: ' . $back_url . (strpos($back_url, '?') !== false ? '&' : '?') . http_build_query([
    'toast_type' => 'error',
    'msg'        => (string) ($result['message'] ?? 'Gagal menyimpan.'),
]));
exit;
