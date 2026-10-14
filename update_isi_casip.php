<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['status' => 'error', 'message' => 'Method tidak diizinkan.'], 405);
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_casip.php';

sipandang_require_login(true);
casip_ensure_schema($koneksi);

$id = (int) ($_POST['id_casip'] ?? 0);
$isi = (string) ($_POST['isi_html_baru'] ?? '');

if ($id <= 0) {
    json_out(['status' => 'error', 'message' => 'ID CASIP tidak valid.']);
}
if ($isi === '' || trim(strip_tags($isi)) === '') {
    json_out(['status' => 'error', 'message' => 'Isi dokumen tidak boleh kosong.']);
}

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id_casip FROM tbl_surat_casip WHERE id_casip='$id' LIMIT 1"));
if (!$row) {
    json_out(['status' => 'error', 'message' => 'Data CASIP tidak ditemukan.']);
}

$isi_esc = mysqli_real_escape_string($koneksi, $isi);
$ok = mysqli_query($koneksi, "UPDATE tbl_surat_casip SET isi_html='$isi_esc' WHERE id_casip='$id'");

if (!$ok) {
    json_out(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($koneksi)]);
}

json_out([
    'status'  => 'success',
    'message' => 'Paket CASIP berhasil disimpan permanen.',
    'id_casip' => $id,
]);
