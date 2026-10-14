<?php
/**
 * rt_cari_warga.php — AJAX endpoint untuk portal RT/RW
 * GET params: q (kata cari), page (halaman), per_page (default 25)
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();

header('Content-Type: application/json; charset=utf-8');

$s       = rt_session();
$q       = trim($_GET['q']       ?? '');
$page    = max(1, (int)($_GET['page']     ?? 1));
$per     = max(10, min(50, (int)($_GET['per_page'] ?? 25)));
$offset  = ($page - 1) * $per;

$rw  = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt  = mysqli_real_escape_string($koneksi, $s['nomor_rt']);
$jab = $s['jabatan'];

// WHERE dasar: wilayah + status HIDUP
$where = "status_dasar='HIDUP' AND rw='$rw'";
if ($jab === 'RT') $where .= " AND rt='$rt'";

// Filter pencarian
if ($q !== '') {
    $qe     = mysqli_real_escape_string($koneksi, $q);
    $where .= " AND (nama_lengkap LIKE '%$qe%' OR nik LIKE '%$qe%' OR no_kk LIKE '%$qe%')";
}

// Total data
$total_q = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM tbl_penduduk WHERE $where"));
$total   = (int)($total_q['c'] ?? 0);

// Data halaman ini
$rows = [];
$res  = mysqli_query($koneksi, "SELECT nik, no_kk, nama_lengkap, jenis_kelamin, tgl_lahir, shdk, rt, rw
        FROM tbl_penduduk WHERE $where
        ORDER BY no_kk, shdk ASC
        LIMIT $per OFFSET $offset");

$shdk_map = ['01'=>'Kepala KK','02'=>'Suami','03'=>'Istri','04'=>'Anak','05'=>'Menantu',
             '06'=>'Cucu','07'=>'Orang Tua','08'=>'Mertua','09'=>'Famili','10'=>'Pembantu','11'=>'Lainnya'];

while ($r = mysqli_fetch_assoc($res)) {
    $jk_up = strtoupper(trim($r['jenis_kelamin'] ?? ''));
    $is_l  = substr($jk_up, 0, 1) === 'L';
    $usia  = '';
    if (!empty($r['tgl_lahir']) && $r['tgl_lahir'] !== '0000-00-00') {
        try { $usia = (new DateTime($r['tgl_lahir']))->diff(new DateTime())->y . ' thn'; } catch(Exception $e){}
    }
    $rows[] = [
        'nik'   => $r['nik']         ?? '',
        'nama'  => $r['nama_lengkap'] ?? '',
        'no_kk' => $r['no_kk']       ?? '',
        'jk'    => $is_l ? 'L' : 'P',
        'usia'  => $usia,
        'shdk'  => $r['shdk']        ?? '',
        'hub'   => $shdk_map[$r['shdk'] ?? ''] ?? '',
        'rt'    => str_pad($r['rt']  ?? '', 3, '0', STR_PAD_LEFT),
        'is_kk' => ($r['shdk'] === '01'),
    ];
}

echo json_encode([
    'ok'       => true,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $per,
    'pages'    => (int)ceil($total / $per),
    'q'        => $q,
    'rows'     => $rows,
], JSON_UNESCAPED_UNICODE);
