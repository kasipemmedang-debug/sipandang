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
require_once __DIR__ . '/inc_himbauan_arsip.php';

sipandang_require_login(true);

$id_arsip = (int) ($_POST['id_arsip'] ?? 0);
$html     = (string) ($_POST['html_cetak'] ?? '');

if ($id_arsip <= 0) {
    json_out(['status' => 'error', 'message' => 'ID arsip tidak valid.']);
}

if (trim(strip_tags($html)) === '') {
    json_out(['status' => 'error', 'message' => 'Isi surat tidak boleh kosong.']);
}

$arsip = sipandang_himbauan_arsip_ambil($koneksi, $id_arsip);
if (!$arsip) {
    json_out(['status' => 'error', 'message' => 'Arsip tidak ditemukan.']);
}

if (!sipandang_himbauan_simpan_html_cetak($koneksi, $id_arsip, $html)) {
    json_out(['status' => 'error', 'message' => 'Gagal menyimpan isi surat.']);
}

json_out([
    'status'      => 'success',
    'message'     => 'Isi surat himbauan berhasil disimpan.',
    'id_arsip'    => $id_arsip,
    'nomor_surat' => $arsip['nomor_surat'] ?? '',
]);
