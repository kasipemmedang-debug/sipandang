<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/auth_admin.php';
sipandang_require_login(false);
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_siak_ttd_pilih.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Metode tidak valid.']);
    exit;
}

$idSiak = (int) ($_POST['id_siak'] ?? 0);
if ($idSiak < 1) {
    echo json_encode(['ok' => false, 'msg' => 'ID permohonan tidak valid.']);
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT id_siak FROM permohonan_siak WHERE id_siak=$idSiak LIMIT 1"));
if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Permohonan tidak ditemukan.']);
    exit;
}

if (empty($_FILES['berkas']['tmp_name']) || !is_uploaded_file($_FILES['berkas']['tmp_name'])) {
    echo json_encode(['ok' => false, 'msg' => 'Pilih file PDF berkas terlebih dahulu.']);
    exit;
}

$ext = strtolower(pathinfo((string) ($_FILES['berkas']['name'] ?? ''), PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['ok' => false, 'msg' => 'Format berkas wajib PDF.']);
    exit;
}

if (($_FILES['berkas']['size'] ?? 0) > 15 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'msg' => 'Ukuran file maksimal 15 MB.']);
    exit;
}

$dir = __DIR__ . '/uploads/berkas_temp/';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$fname = 'siak_prev_' . $idSiak . '_' . time() . '.pdf';
$target = $dir . $fname;

if (!move_uploaded_file($_FILES['berkas']['tmp_name'], $target)) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan file.']);
    exit;
}

$origName = (string) ($_FILES['berkas']['name'] ?? $fname);
siak_preview_berkas_set($idSiak, $target, $origName);

echo json_encode([
    'ok'   => true,
    'msg'  => 'Berkas PDF berhasil di-upload.',
    'name' => $origName,
]);
