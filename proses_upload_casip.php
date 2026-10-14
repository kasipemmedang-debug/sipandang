<?php
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/inc_casip.php';
require_once __DIR__ . '/inc_berkas_storage.php';
require_once __DIR__ . '/inc_admin_toast.php';
casip_ensure_schema($koneksi);

if (!isset($_POST['btn_upload']) || empty($_POST['id_casip'])) {
    header('Location: data_casip.php');
    exit;
}

$id = (int) $_POST['id_casip'];
$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_surat_casip WHERE id_casip='$id' LIMIT 1"));
if (!$row) {
    header('Location: data_casip.php?toast=error&toast_msg=' . rawurlencode('Data CASIP tidak ditemukan.'));
    exit;
}

if (empty($_FILES['berkas_scan']['name'])) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-circle mr-1"></i> Pilih file terlebih dahulu.');
}

$max = 5 * 1024 * 1024;
if ($_FILES['berkas_scan']['size'] > $max) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-circle mr-1"></i> Ukuran file maksimal 5MB.');
}

$ext = strtolower(pathinfo($_FILES['berkas_scan']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-circle mr-1"></i> Format file harus PDF, JPG, atau PNG.');
}

$tahun = sipandang_berkas_year_from($row['tgl_surat'] ?? null);
$nama = trim(($row['nama_suami'] ?? '') . '_' . ($row['nama_istri'] ?? ''));
if ($nama === '_' || $nama === '') {
    $nama = $row['nama_suami'] ?? $row['nama_istri'] ?? 'MEMPELAI';
}

$rel_path = sipandang_berkas_save_upload(
    'CASIP',
    $tahun,
    'CASIP',
    $row['no_paket'] ?? ('ID' . $id),
    $nama,
    $_FILES['berkas_scan']['tmp_name'],
    $_FILES['berkas_scan']['name'],
    'SCAN'
);

if (!$rel_path) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-triangle mr-1"></i> Gagal mengunggah file.');
}

sipandang_berkas_delete_stored($row['file_scan'] ?? '');

$rel_esc = mysqli_real_escape_string($koneksi, $rel_path);
mysqli_query($koneksi, "UPDATE tbl_surat_casip SET file_scan='$rel_esc' WHERE id_casip='$id'");

$back = 'data_casip.php?toast=upload';
if (!empty($_POST['redirect_detail'])) {
    $back = 'arsip_detail_casip.php?id=' . $id . '&toast=upload';
}
header('Location: ' . $back);
exit;
