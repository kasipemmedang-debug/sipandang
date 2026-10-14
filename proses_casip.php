<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_casip.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_kelengkapan_berkas.php';
require_once __DIR__ . '/inc_admin_toast.php';
casip_ensure_schema($koneksi);
kelengkapan_casip_ensure_schema($koneksi);

if (!isset($_POST['simpan_casip']) || empty($_POST['suami_nama'])) {
    header('Location: buat_surat_casip.php');
    exit;
}

$D = casip_data_from_post($_POST);
$id_pamong = (int) ($D['id_pamong'] ?? 0);
if ($id_pamong > 0) {
    $pm = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong='$id_pamong' LIMIT 1"));
    if ($pm) {
        $D = casip_apply_pejabat($D, $pm);
    }
}

// Simpan HTML + CSS sama seperti preview (cetak langsung = simpan & cetak).
$pages = casip_render_pages($D, $koneksi);
$edited = trim($_POST['isi_html'] ?? '');
if ($edited !== '' && strpos($edited, 'casip-page') !== false) {
    $isi_html = casip_html_for_cetak($edited, false);
} else {
    $isi_html = casip_html_for_cetak($pages, false);
}

$operator = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin');
$no_paket = mysqli_real_escape_string($koneksi, $D['no_paket']);
$no_surat = mysqli_real_escape_string($koneksi, $D['no_surat']);
$nik_suami = mysqli_real_escape_string($koneksi, $D['suami']['nik'] ?? '');
$nama_suami = mysqli_real_escape_string($koneksi, $D['suami']['nama'] ?? '');
$nama_istri = mysqli_real_escape_string($koneksi, $D['istri']['nama'] ?? '');
$tgl_surat = mysqli_real_escape_string($koneksi, $D['tgl_surat']);
$data_json = mysqli_real_escape_string($koneksi, json_encode($D, JSON_UNESCAPED_UNICODE));
$isi_safe = mysqli_real_escape_string($koneksi, $isi_html);

$alur_casip = strtoupper(substr(trim((string) ($_POST['alur_casip'] ?? 'A')), 0, 1));
if (!in_array($alur_casip, ['A', 'B', 'C'], true)) {
    $alur_casip = 'A';
}
$mempelai_luar = mysqli_real_escape_string($koneksi, trim((string) ($_POST['mempelai_luar'] ?? 'TIDAK')));
$tgl_pemberkatan_raw = trim((string) ($_POST['tgl_pemberkatan'] ?? ''));
$tgl_pemberkatan_sql = 'NULL';
if ($tgl_pemberkatan_raw !== '' && $tgl_pemberkatan_raw !== '0000-00-00') {
    $tgl_pemberkatan_sql = "'" . mysqli_real_escape_string($koneksi, date('Y-m-d', strtotime($tgl_pemberkatan_raw))) . "'";
}
$status_surat = $alur_casip === 'B' ? 'Pending' : 'Selesai';

$sql = "INSERT INTO tbl_surat_casip (
    no_paket, no_surat, nik_suami, nama_suami, nama_istri, tgl_surat, id_pamong, dibuat_oleh, data_json, isi_html, status_surat,
    alur_casip, mempelai_luar, tgl_pemberkatan
) VALUES (
    '$no_paket','$no_surat','$nik_suami','$nama_suami','$nama_istri','$tgl_surat','$id_pamong','$operator','$data_json','$isi_safe','$status_surat',
    '$alur_casip','$mempelai_luar',$tgl_pemberkatan_sql
)";

if (!mysqli_query($koneksi, $sql)) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-triangle mr-1"></i> Gagal simpan: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}

$id = mysqli_insert_id($koneksi);

$json_kelengkapan = trim((string) ($_POST['kelengkapan_berkas_json'] ?? ''));
if ($json_kelengkapan !== '') {
    $kel_err = kelengkapan_casip_simpan_dari_json(
        $koneksi,
        (int) $id,
        $alur_casip,
        trim((string) ($_POST['mempelai_luar'] ?? 'TIDAK')),
        $json_kelengkapan,
        $operator
    );
    if ($kel_err !== null && $kel_err !== '') {
        $_SESSION['casip_kelengkapan_warn'] = $kel_err;
    }
}

sipandang_log_aktivitas($koneksi, 'CASIP', 'buat', (int) $id, $no_paket, $nama_suami . ' & ' . $nama_istri);
header('Location: cetak_casip.php?id=' . $id . '&saved=1');
exit;
