<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);
$s = rt_session();

$nik = trim($_POST['nik'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: rt_portal.php'); exit; }
rt_csrf_check();
if ($nik === '') { header('Location: rt_portal.php'); exit; }

// Validasi kepemilikan sebelum hapus
$d = rt_warga_milik($koneksi, $nik, $s);
if (!$d) {
    header('Location: rt_portal.php?toast=noauth'); exit;
}

$nik_e = mysqli_real_escape_string($koneksi, $nik);
rt_arsipkan_warga($koneksi, $d, 'PINDAH', date('Y-m-d'), 'Dihapus/dipindahkan melalui Portal RT/RW.', $s['username']);
// Soft delete: ubah status_dasar saja (aman, data tidak hilang permanen)
mysqli_query($koneksi, "UPDATE tbl_penduduk SET status_dasar='PINDAH' WHERE nik='$nik_e'");
header('Location: rt_portal.php?toast=hapus'); exit;
