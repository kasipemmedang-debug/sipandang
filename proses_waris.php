<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_waris.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_kelengkapan_berkas.php';
require_once __DIR__ . '/inc_admin_toast.php';
waris_ensure_schema($koneksi);
kelengkapan_waris_ensure_schema($koneksi);

if (!isset($_POST['simpan_waris']) || empty($_POST['nama_almarhum'])) {
    header('Location: buat_surat_waris.php');
    exit;
}

$D = waris_data_from_post($_POST);
$pages = waris_render_pages($D, $koneksi, 0);
$edited = trim($_POST['isi_html'] ?? '');
if ($edited !== '' && strpos($edited, 'waris-page') !== false) {
    $isi_html_draft = $edited;
} else {
    $isi_html_draft = $pages;
}

$operator = mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin');
$data_json = mysqli_real_escape_string($koneksi, json_encode($D, JSON_UNESCAPED_UNICODE));
$isi_safe = ''; // diisi ulang setelah insert + QR

function waris_sd($v)
{
    if (empty($v) || $v === '0000-00-00') {
        return 'NULL';
    }
    return "'" . date('Y-m-d', strtotime($v)) . "'";
}
function waris_e(mysqli $k, $v)
{
    return mysqli_real_escape_string($k, strtoupper(trim((string) $v)));
}
function waris_er(mysqli $k, $v)
{
    return mysqli_real_escape_string($k, trim((string) $v));
}

$jenisRaw = $_POST['jenis_almarhum'] ?? 'ALMARHUM';
$jenis = in_array($jenisRaw, ['ALMARHUM', 'ALMARHUMAH'], true) ? $jenisRaw : 'ALMARHUM';
$buat_kuasa = isset($_POST['buat_kuasa']) ? 1 : 0;

$sql = "INSERT INTO tbl_surat_waris (
    no_register,tgl_register,tgl_surat,jenis_almarhum,nik_almarhum,nama_almarhum,
    alamat_almarhum,rt_almarhum,rw_almarhum,kel_almarhum,kec_almarhum,
    tgl_meninggal,no_akta_mati,instansi_akta,jml_nikah,nama_pasangan,status_pasangan,
    nama_saksi1,nik_saksi1,nama_saksi2,nik_saksi2,id_pamong,id_pamong_kec,kota_terbit,catatan,
    dibuat_oleh,status_surat,buat_kuasa,nama_penerima_kuasa,nik_penerima_kuasa,ttl_penerima_kuasa,
    pekerjaan_penerima_kuasa,alamat_penerima_kuasa,keperluan_kuasa,data_json,isi_html
) VALUES (
    '" . waris_e($koneksi, $_POST['no_register'] ?? '') . "',
    " . waris_sd($_POST['tgl_register'] ?? date('Y-m-d')) . ",
    " . waris_sd($_POST['tgl_surat'] ?? date('Y-m-d')) . ",
    '$jenis',
    '" . waris_er($koneksi, $_POST['nik_almarhum'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['nama_almarhum'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['alamat_almarhum'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['rt_almarhum'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['rw_almarhum'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['kel_almarhum'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['kec_almarhum'] ?? '') . "',
    " . waris_sd($_POST['tgl_meninggal'] ?? '') . ",
    '" . waris_e($koneksi, $_POST['no_akta_mati'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['instansi_akta'] ?? '') . "',
    '" . (int) ($_POST['jml_nikah'] ?? 1) . "',
    '" . waris_e($koneksi, $_POST['nama_pasangan'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['status_pasangan'] ?? 'MASIH HIDUP') . "',
    '" . waris_e($koneksi, $_POST['nama_saksi1'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['nik_saksi1'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['nama_saksi2'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['nik_saksi2'] ?? '') . "',
    '" . (int) ($_POST['id_pamong'] ?? 0) . "',
    '" . (int) ($_POST['id_pamong_kec'] ?? $_POST['id_ttd_kec'] ?? 0) . "',
    '" . waris_e($koneksi, $_POST['kota_terbit'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['catatan'] ?? '') . "',
    '$operator',
    'Selesai',
    '$buat_kuasa',
    '" . waris_e($koneksi, $_POST['nama_penerima_kuasa'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['nik_penerima_kuasa'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['ttl_penerima_kuasa'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['pekerjaan_penerima_kuasa'] ?? '') . "',
    '" . waris_e($koneksi, $_POST['alamat_penerima_kuasa'] ?? '') . "',
    '" . waris_er($koneksi, $_POST['keperluan_kuasa'] ?? '') . "',
    '$data_json',
    '$isi_safe'
)";

if (!mysqli_query($koneksi, $sql)) {
    sipandang_admin_toast_flash_back('error', '<i class="fas fa-exclamation-triangle mr-1"></i> Gagal simpan: ' . htmlspecialchars(mysqli_error($koneksi), ENT_QUOTES, 'UTF-8'));
}
$id_waris = mysqli_insert_id($koneksi);

foreach ($D['waris'] as $i => $w) {
    $nm = mysqli_real_escape_string($koneksi, $w['nama']);
    $hub = mysqli_real_escape_string($koneksi, $w['hubungan']);
    $jk = mysqli_real_escape_string($koneksi, $w['jk']);
    $tmp = mysqli_real_escape_string($koneksi, $w['tmp_lahir']);
    $pkj = mysqli_real_escape_string($koneksi, $w['pekerjaan']);
    $alm = mysqli_real_escape_string($koneksi, $w['alamat']);
    $tl = waris_sd($w['tgl_lahir']);
    $urt = $i + 1;
    mysqli_query($koneksi, "INSERT INTO tbl_waris_detail (id_waris,urutan,nama_waris,hubungan_waris,jk_waris,tmp_lahir_waris,tgl_lahir_waris,pekerjaan_waris,alamat_waris)
        VALUES ('$id_waris','$urt','$nm','$hub','$jk','$tmp',$tl,'$pkj','$alm')");
}

waris_ensure_token($koneksi, (int) $id_waris);
$isi_html = waris_html_for_cetak($isi_html_draft, false, (int) $id_waris, $koneksi);
$isi_upd = mysqli_real_escape_string($koneksi, $isi_html);
mysqli_query($koneksi, "UPDATE tbl_surat_waris SET isi_html='$isi_upd' WHERE id_waris=" . (int) $id_waris . " LIMIT 1");

$json_kelengkapan = trim((string) ($_POST['kelengkapan_berkas_json'] ?? ''));
if ($json_kelengkapan !== '') {
    $kel_err = kelengkapan_waris_simpan_dari_json(
        $koneksi,
        (int) $id_waris,
        $buat_kuasa,
        $json_kelengkapan,
        $operator
    );
    if ($kel_err !== null) {
        $_SESSION['waris_kelengkapan_warn'] = $kel_err;
    }
}

sipandang_log_aktivitas(
    $koneksi,
    'WARIS',
    'buat',
    (int) $id_waris,
    waris_e($koneksi, $_POST['no_register'] ?? '') ?: ('Waris #' . $id_waris),
    waris_e($koneksi, $_POST['nama_almarhum'] ?? '')
);

header('Location: cetak_waris.php?id=' . $id_waris . '&saved=1');
exit;
