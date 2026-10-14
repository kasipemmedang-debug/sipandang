<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_admin_toast.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['nik'])) {
    header('Location: penduduk.php');
    exit;
}

$nik_lama = mysqli_real_escape_string($koneksi, (string) ($_POST['nik_lama'] ?? ''));
$nik_baru = preg_replace('/\D/', '', (string) ($_POST['nik'] ?? ''));
$edit_url = 'edit_penduduk.php?nik=' . urlencode($nik_lama !== '' ? preg_replace('/\D/', '', $nik_lama) : $nik_baru);

function upd_penduduk_back(string $url, string $type, string $msg): void
{
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    header('Location: ' . $url . $sep . http_build_query([
        'toast_type' => $type,
        'msg'        => $msg,
    ]));
    exit;
}

if ($nik_lama === '' || strlen($nik_baru) !== 16) {
    upd_penduduk_back($edit_url, 'error', 'NIK wajib 16 digit.');
}

if ($nik_baru !== preg_replace('/\D/', '', $nik_lama)) {
    $cek = mysqli_query($koneksi, "SELECT nik FROM tbl_penduduk WHERE nik='" . mysqli_real_escape_string($koneksi, $nik_baru) . "' LIMIT 1");
    if ($cek && mysqli_num_rows($cek) > 0) {
        upd_penduduk_back($edit_url, 'error', 'NIK ' . $nik_baru . ' sudah terdaftar.');
    }
}

$no_kk = preg_replace('/\D/', '', (string) ($_POST['no_kk'] ?? ''));
if (strlen($no_kk) !== 16) {
    upd_penduduk_back($edit_url, 'error', 'No. KK wajib 16 digit.');
}

$nama = strtoupper(trim((string) ($_POST['nama_lengkap'] ?? '')));
if ($nama === '') {
    upd_penduduk_back($edit_url, 'error', 'Nama lengkap wajib diisi.');
}

$status_dasar = strtoupper(trim((string) ($_POST['status_dasar'] ?? 'HIDUP')));
if (!in_array($status_dasar, ['HIDUP', 'MATI', 'PINDAH'], true)) {
    $status_dasar = 'HIDUP';
}

$jk = sipandang_jk_normalize((string) ($_POST['jenis_kelamin'] ?? 'L'));

$fields = [
    'nik'               => $nik_baru,
    'no_kk'             => $no_kk,
    'nama_lengkap'      => $nama,
    'tempat_lahir'      => strtoupper(trim((string) ($_POST['tempat_lahir'] ?? ''))),
    'tgl_lahir'         => (string) ($_POST['tgl_lahir'] ?? ''),
    'jenis_kelamin'     => $jk,
    'gol_darah'         => (string) ($_POST['gol_darah'] ?? ''),
    'agama'             => sipandang_agama_normalize((string) ($_POST['agama'] ?? '')),
    'status_perkawinan' => sipandang_kawin_normalize((string) ($_POST['status_perkawinan'] ?? '')),
    'shdk'              => sipandang_shdk_normalize((string) ($_POST['shdk'] ?? '')),
    'pendidikan'        => trim((string) ($_POST['pendidikan'] ?? '')),
    'pekerjaan'         => sipandang_pekerjaan_normalize((string) ($_POST['pekerjaan'] ?? '')),
    'nama_ibu'          => strtoupper(trim((string) ($_POST['nama_ibu'] ?? ''))),
    'nama_ayah'         => strtoupper(trim((string) ($_POST['nama_ayah'] ?? ''))),
    'nik_ibu'           => preg_replace('/\D/', '', (string) ($_POST['nik_ibu'] ?? '')),
    'nik_ayah'          => preg_replace('/\D/', '', (string) ($_POST['nik_ayah'] ?? '')),
    'no_akta'           => trim((string) ($_POST['no_akta'] ?? '')),
    'alamat'            => strtoupper(trim((string) ($_POST['alamat'] ?? ''))),
    'rt'                => trim((string) ($_POST['rt'] ?? '')),
    'rw'                => trim((string) ($_POST['rw'] ?? '')),
    'kelurahan'         => strtoupper(trim((string) ($_POST['kelurahan'] ?? ''))),
    'kecamatan'         => strtoupper(trim((string) ($_POST['kecamatan'] ?? ''))),
    'kode_pos'          => trim((string) ($_POST['kode_pos'] ?? '')),
    'no_reg_rt'         => trim((string) ($_POST['no_reg_rt'] ?? '')),
    'no_hp'             => sipandang_normalize_hp((string) ($_POST['no_hp'] ?? '')),
    'kewarganegaraan'   => (string) ($_POST['kewarganegaraan'] ?? 'WNI'),
    'status_dasar'      => $status_dasar,
    'tgl_peristiwa'     => (string) ($_POST['tgl_peristiwa'] ?? ''),
    'tgl_pelaporan'     => (string) ($_POST['tgl_pelaporan'] ?? ''),
    'catatan_peristiwa' => trim((string) ($_POST['catatan_peristiwa'] ?? '')),
];

$col_ok = [];
$qc = mysqli_query($koneksi, 'SHOW COLUMNS FROM tbl_penduduk');
while ($qc && ($r = mysqli_fetch_assoc($qc))) {
    $col_ok[$r['Field']] = true;
}

$sets = [];
foreach ($fields as $col => $val) {
    if (!isset($col_ok[$col])) {
        continue;
    }
    $sets[] = "$col='" . mysqli_real_escape_string($koneksi, (string) $val) . "'";
}

if (empty($sets)) {
    upd_penduduk_back($edit_url, 'error', 'Tidak ada kolom yang dapat diupdate.');
}

$query = 'UPDATE tbl_penduduk SET ' . implode(', ', $sets) . " WHERE nik='$nik_lama'";

if (mysqli_query($koneksi, $query)) {
    sipandang_redirect_toast('edit_penduduk.php?nik=' . urlencode($nik_baru), 'upd');
}

upd_penduduk_back($edit_url, 'error', 'Gagal update: ' . mysqli_error($koneksi));
