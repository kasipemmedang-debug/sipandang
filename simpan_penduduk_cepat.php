<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input(true);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_penduduk_simpan.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$errors = sipandang_penduduk_validasi_cepat($_POST);
if ($errors !== []) {
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = sipandang_penduduk_insert($koneksi, $_POST);
echo json_encode([
    'ok'      => (bool) ($result['ok'] ?? false),
    'message' => (string) ($result['message'] ?? ''),
    'nik'     => (string) ($result['nik'] ?? ''),
    'nama'    => (string) ($result['nama'] ?? ''),
], JSON_UNESCAPED_UNICODE);
