<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_siak_ttd_pilih.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_siak.php');
    exit;
}

$id_siak = (int) ($_POST['id_siak'] ?? 0);
$id_kec  = (int) ($_POST['id_ttd_kec'] ?? 0);
$id_kel  = (int) ($_POST['id_ttd_kel'] ?? 0);
$redirect = sipandang_siak_safe_redirect_url(
    (string) ($_POST['redirect'] ?? ''),
    'edit_siak.php?id=' . $id_siak
);

if ($id_siak < 1 || $id_kec < 1 || $id_kel < 1) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'toast=ttd_error');
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_pemohon, jenis_layanan, nama_ttd_kec, nama_ttd_kel FROM permohonan_siak WHERE id_siak='$id_siak' LIMIT 1"));
if (!$row) {
    header('Location: data_siak.php?toast=ttd_error');
    exit;
}

$kec_baru = sipandang_siak_pejabat_by_id($koneksi, $id_kec);
$kel_baru = sipandang_siak_pejabat_by_id($koneksi, $id_kel);
if (!$kec_baru || !$kel_baru) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'toast=ttd_error');
    exit;
}

$ok = sipandang_siak_update_ttd_pejabat($koneksi, $id_siak, $id_kec, $id_kel);
if ($ok) {
    $kec_lama = sipandang_siak_pejabat_by_id($koneksi, (int) ($row['nama_ttd_kec'] ?? 0));
    $kel_lama = sipandang_siak_pejabat_by_id($koneksi, (int) ($row['nama_ttd_kel'] ?? 0));
    $detail = 'Kec: ' . ($kec_lama['nama_pamong'] ?? '-') . ' → ' . ($kec_baru['nama_pamong'] ?? '-')
        . ' · Kel: ' . ($kel_lama['nama_pamong'] ?? '-') . ' → ' . ($kel_baru['nama_pamong'] ?? '-');
    sipandang_log_aktivitas(
        $koneksi,
        'SIAK',
        'ganti_ttd',
        $id_siak,
        strtoupper($row['nama_pemohon'] ?? '') . ' #' . $id_siak,
        $detail
    );
    $sep = (strpos($redirect, '?') !== false) ? '&' : '?';
    header('Location: ' . $redirect . $sep . 'toast=ttd_updated');
    exit;
}

header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'toast=ttd_error');
exit;
