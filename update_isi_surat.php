<?php
/**
 * update_isi_surat.php — simpan isi surat (AJAX dari reprint_surat.php)
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

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status'  => 'error',
            'message' => 'Kesalahan server: ' . $err['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['status' => 'error', 'message' => 'Method tidak diizinkan.'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (empty($_POST) && $contentLength > 0) {
    json_out([
        'status'  => 'error',
        'message' => 'Data terlalu besar untuk dikirim (batas post_max_size hosting). Coba kurangi gambar di surat atau hubungi admin hosting.',
    ], 413);
}

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';

sipandang_require_login(true);
sipandang_surat_keluar_ensure_revisi_reprint($koneksi);

if (empty($_POST['id_surat']) || !isset($_POST['isi_surat_baru'])) {
    json_out(['status' => 'error', 'message' => 'Data tidak lengkap. id_surat dan isi_surat_baru wajib diisi.']);
}

$id_surat       = (int) $_POST['id_surat'];
$no_surat       = (string) ($_POST['no_surat'] ?? '');
$isi_surat_baru = (string) $_POST['isi_surat_baru'];
$id_pamong_baru = (int) ($_POST['id_pamong_baru'] ?? 0);

if ($id_surat <= 0) {
    json_out(['status' => 'error', 'message' => 'ID surat tidak valid.']);
}

if ($isi_surat_baru === '' || trim(strip_tags($isi_surat_baru)) === '') {
    json_out(['status' => 'error', 'message' => 'Isi surat tidak boleh kosong.']);
}

$q_cek = mysqli_prepare($koneksi, 'SELECT id_surat, no_surat, isi_surat, revisi_reprint FROM tbl_surat_keluar WHERE id_surat = ? LIMIT 1');
if (!$q_cek) {
    json_out(['status' => 'error', 'message' => 'Gagal cek data: ' . mysqli_error($koneksi)]);
}
mysqli_stmt_bind_param($q_cek, 'i', $id_surat);
mysqli_stmt_execute($q_cek);
$res_cek = mysqli_stmt_get_result($q_cek);
$row     = $res_cek ? mysqli_fetch_assoc($res_cek) : null;
mysqli_stmt_close($q_cek);

if (!$row) {
    json_out(['status' => 'error', 'message' => 'Surat dengan ID ' . $id_surat . ' tidak ditemukan.']);
}

$isi_lama = (string) ($row['isi_surat'] ?? '');
$revisi_sekarang = max(0, (int) ($row['revisi_reprint'] ?? 0));
$isi_berubah = ($isi_surat_baru !== $isi_lama);
$revisi_baru = $revisi_sekarang;
if ($isi_berubah || $id_pamong_baru > 0) {
    $revisi_baru = $revisi_sekarang + 1;
}

$sql_update = 'UPDATE tbl_surat_keluar SET isi_surat = ?, revisi_reprint = ?';
if ($id_pamong_baru > 0) {
    $sql_update .= ', id_pamong = ?';
}
$sql_update .= ' WHERE id_surat = ?';
$stmt = mysqli_prepare($koneksi, $sql_update);
if (!$stmt) {
    json_out(['status' => 'error', 'message' => 'Gagal menyiapkan query: ' . mysqli_error($koneksi)]);
}

if ($id_pamong_baru > 0) {
    mysqli_stmt_bind_param($stmt, 'siii', $isi_surat_baru, $revisi_baru, $id_pamong_baru, $id_surat);
} else {
    mysqli_stmt_bind_param($stmt, 'sii', $isi_surat_baru, $revisi_baru, $id_surat);
}
$ok = mysqli_stmt_execute($stmt);
if (!$ok) {
    $msg = mysqli_stmt_error($stmt) ?: mysqli_error($koneksi);
    mysqli_stmt_close($stmt);
    json_out(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $msg]);
}

$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

$message = $affected > 0
    ? 'Isi surat berhasil disimpan secara permanen ke database.'
    : 'Tidak ada perubahan yang berbeda dari data sebelumnya.';

$detail_log = 'Panjang HTML: ' . strlen($isi_surat_baru) . ' byte';
if ($id_pamong_baru > 0) {
    $detail_log .= ' · id_pamong=' . $id_pamong_baru;
}
if ($affected > 0 && $revisi_baru > $revisi_sekarang) {
    $detail_log .= ' · revisi_reprint=' . $revisi_baru;
}

sipandang_audit_log($koneksi, $id_surat, 'edit_isi_reprint', $detail_log);
sipandang_log_aktivitas(
    $koneksi,
    'REPRINT',
    'edit_isi',
    $id_surat,
    $no_surat !== '' ? $no_surat : ($row['no_surat'] ?? ''),
    $detail_log
);

json_out([
    'status'         => 'success',
    'message'        => $message,
    'id_surat'       => $id_surat,
    'no_surat'       => $no_surat !== '' ? $no_surat : ($row['no_surat'] ?? ''),
    'revisi_reprint' => $revisi_baru,
]);
