<?php
/**
 * Simpan isi HTML SK hasil edit manual (AJAX dari cetak_sk_pengangkatan.php)
 */
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
require_once __DIR__ . '/inc_sk_pengangkatan_rt_rw.php';

sipandang_require_login(true);

$id_sk = (int) ($_POST['id_sk'] ?? 0);
$html = (string) ($_POST['html_cetak'] ?? '');

if ($id_sk <= 0) {
    json_out(['status' => 'error', 'message' => 'ID SK tidak valid.']);
}

if (trim(strip_tags($html)) === '') {
    json_out(['status' => 'error', 'message' => 'Isi SK tidak boleh kosong.']);
}

$sk = sipandang_sk_pengangkatan_ambil($koneksi, $id_sk);
if (!$sk) {
    json_out(['status' => 'error', 'message' => 'Data SK tidak ditemukan.']);
}

if (!sipandang_sk_simpan_html_cetak($koneksi, $id_sk, $html)) {
    json_out(['status' => 'error', 'message' => 'Gagal menyimpan isi SK.']);
}

json_out([
    'status' => 'success',
    'message' => 'Isi SK berhasil disimpan. Perubahan akan dipakai saat cetak berikutnya.',
    'id_sk' => $id_sk,
    'nomor_sk' => $sk['nomor_sk'] ?? '',
]);
