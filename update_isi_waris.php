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
require_once __DIR__ . '/inc_waris.php';

sipandang_require_login(true);
waris_ensure_schema($koneksi);

$id = (int) ($_POST['id_waris'] ?? 0);
$isi = (string) ($_POST['isi_html_baru'] ?? '');

if ($id <= 0) {
    json_out(['status' => 'error', 'message' => 'ID waris tidak valid.']);
}
if ($isi === '' || trim(strip_tags($isi)) === '') {
    json_out(['status' => 'error', 'message' => 'Isi dokumen tidak boleh kosong.']);
}

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id_waris FROM tbl_surat_waris WHERE id_waris='$id' LIMIT 1"));
if (!$row) {
    json_out(['status' => 'error', 'message' => 'Data waris tidak ditemukan.']);
}

waris_ensure_token($koneksi, $id);

/* Lepas style/marker lama; ganti QR lama → slot lalu isi ulang */
$inner = (string) preg_replace('/^<!--\s*SIPANDANG-WARIS-TPL:.*?-->\s*/s', '', $isi);
$inner = (string) preg_replace('/^<style[\s\S]*?<\/style>\s*/i', '', $inner);
$inner = waris_strip_qr_footers($inner);
$isi = waris_html_for_cetak($inner, false, $id, $koneksi);

$isi_esc = mysqli_real_escape_string($koneksi, $isi);
$ok = mysqli_query($koneksi, "UPDATE tbl_surat_waris SET isi_html='$isi_esc' WHERE id_waris='$id'");

if (!$ok) {
    json_out(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($koneksi)]);
}

json_out([
    'status'   => 'success',
    'message'  => 'Surat waris berhasil disimpan permanen.',
    'id_waris' => $id,
]);
