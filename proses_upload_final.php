<?php
/**
 * Upload berkas final surat — wajib login loket/admin.
 * Tidak mengubah status digital TTD (hanya simpan file; Selesai hanya jika status manual).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_alur_ttd.php';

if (!isset($_POST['id_surat'])) {
    header('Location: arsip_surat.php');
    exit;
}

$id_surat = (int) $_POST['id_surat'];
if ($id_surat < 1 || empty($_FILES['file_final']['tmp_name'])) {
    echo "<script>alert('Data upload tidak lengkap.'); window.history.back();</script>";
    exit;
}

$cek = mysqli_query($koneksi, "SELECT status_surat FROM tbl_surat_keluar WHERE id_surat = $id_surat LIMIT 1");
$row = $cek ? mysqli_fetch_assoc($cek) : null;
if (!$row) {
    echo "<script>alert('Surat tidak ditemukan.'); window.location='arsip_surat.php';</script>";
    exit;
}

$st_now = (string) ($row['status_surat'] ?? '');
$is_digital = alur_ttd_is_digital_status($st_now);

$nama_file = (string) ($_FILES['file_final']['name'] ?? '');
$tmp_file  = (string) ($_FILES['file_final']['tmp_name'] ?? '');
$ekstensi  = strtolower((string) pathinfo($nama_file, PATHINFO_EXTENSION));

$allowed = ['pdf', 'jpg', 'jpeg', 'png'];
if (!in_array($ekstensi, $allowed, true)) {
    echo "<script>alert('Format file harus PDF atau Gambar!'); window.history.back();</script>";
    exit;
}

$nama_baru = 'surat_' . $id_surat . '_final.' . $ekstensi;
$tujuan    = __DIR__ . '/dist/arsip/' . $nama_baru;

if (!is_dir(__DIR__ . '/dist/arsip')) {
    @mkdir(__DIR__ . '/dist/arsip', 0777, true);
}

if (!move_uploaded_file($tmp_file, $tujuan)) {
    echo "<script>alert('Gagal upload file!'); window.history.back();</script>";
    exit;
}

$nama_esc = mysqli_real_escape_string($koneksi, $nama_baru);
if ($is_digital) {
    // Alur digital: jangan paksa Selesai — status tetap dikontrol pejabat/proses_alur.
    $update = mysqli_query($koneksi, "
        UPDATE tbl_surat_keluar SET file_surat = '$nama_esc'
        WHERE id_surat = $id_surat
    ");
} else {
    $update = mysqli_query($koneksi, "
        UPDATE tbl_surat_keluar SET
            file_surat = '$nama_esc',
            status_surat = 'Selesai'
        WHERE id_surat = $id_surat
    ");
}

if ($update) {
    echo "<script>
            alert('Berhasil! Berkas final tersimpan.');
            window.location = 'arsip_detail.php?id=$id_surat';
          </script>";
    exit;
}

echo "<script>alert('Gagal update database!'); window.history.back();</script>";
