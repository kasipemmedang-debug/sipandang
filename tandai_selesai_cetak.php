<?php
declare(strict_types=1);

require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_alur_ttd.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['ok' => false, 'msg' => 'ID surat tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$oleh = trim((string) ($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Operator'));
$result = alur_ttd_tandai_selesai_cetak($koneksi, $id, $oleh);

echo json_encode([
    'ok'      => $result['ok'],
    'msg'     => $result['msg'],
    'changed' => $result['changed'] ?? false,
], JSON_UNESCAPED_UNICODE);
