<?php
require_once __DIR__ . '/inc_guard_admin.php';
include "koneksi.php";
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';
require_once __DIR__ . '/inc_format_surat_resmi.php';
require_once __DIR__ . '/inc_siak_ttd.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_siak_alur_ttd.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_cetak_register_siak.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
siak_alur_ttd_ensure_schema($koneksi);

function romawi($n) {
    $map = [
        1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI',
        7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'
    ];
    return isset($map[$n]) ? $map[$n] : $n;
}

/** SK Internal — TTD digital Kel (gambar atau QR); basah tidak memakai fungsi ini. */
function print_sk_internal_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'sk-internal-ttd-digital', 56);
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : '';
$nik_cetak = isset($_GET['nik']) ? mysqli_real_escape_string($koneksi, $_GET['nik']) : '';

if (empty($id)) {
    die("ID tidak ditemukan!");
}

// [1] Ambil data utama permohonan
$query = mysqli_query($koneksi, "
    SELECT 
        ps.*, 
        p.nama_pamong AS nama_pejabat, p.nip AS nip_pejabat, p.jabatan AS jab_pejabat
    FROM permohonan_siak ps
    LEFT JOIN tbl_pamong p ON ps.nama_ttd_kel = p.id_pamong
    WHERE ps.id_siak='$id'
");
$data = mysqli_fetch_array($query);

if (!$data) { 
    die("Data permohonan tidak ditemukan!"); 
}

if (empty($nik_cetak)) {
    $nik_cetak = $data['nik_pemohon'];
}

// [2] Ambil detail biodata
$query_detail = mysqli_query($koneksi, "SELECT * FROM siak_anggota WHERE id_permohonan = '$id' AND nik = '$nik_cetak' LIMIT 1");
$detail = mysqli_fetch_array($query_detail);

if (!$detail) {
    $query_pend = mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE nik = '$nik_cetak' LIMIT 1");
    $detail = mysqli_fetch_array($query_pend);
}

// --- FUNGSI KONVERSI DATA ---
$nama_tampil = strtoupper($detail['nama_lengkap'] ?? $detail['nama'] ?? $data['nama_pemohon'] ?? '-');
$jk_raw = isset($detail['jk']) ? strtoupper($detail['jk']) : (isset($detail['jenis_kelamin']) ? strtoupper($detail['jenis_kelamin']) : '-');
if ($jk_raw == 'L' || $jk_raw == 'LAKI-LAKI') { $jk = "LAKI-LAKI"; }
elseif ($jk_raw == 'P' || $jk_raw == 'PEREMPUAN') { $jk = "PEREMPUAN"; }
else { $jk = "-"; }

$agama_raw = isset($detail['agama']) ? $detail['agama'] : '-';
$list_agama = ['1' => 'ISLAM', '2' => 'KRISTEN', '3' => 'KATHOLIK', '4' => 'HINDU', '5' => 'BUDHA', '6' => 'KHONGHUCU', '7' => 'PENGHAYAT KEPERCAYAAN'];
$agama = isset($list_agama[$agama_raw]) ? $list_agama[$agama_raw] : strtoupper($agama_raw);

$tmp_lahir = strtoupper($detail['tempat_lahir'] ?? $detail['tmp_lahir'] ?? '-');
$tgl_lahir_val = $detail['tanggal_lahir'] ?? $detail['tgl_lahir'] ?? '0000-00-00';
$tgl_lahir = ($tgl_lahir_val != '0000-00-00') ? date('d-m-Y', strtotime($tgl_lahir_val)) : '-';
$pekerjaan = sipandang_tampil_pekerjaan($detail['pekerjaan'] ?? '', true);

// --- LOGIKA KEPERLUAN ---
$keperluan_db = isset($data['keperluan']) ? trim($data['keperluan']) : "";
$alasan_db = isset($data['alasan_permohonan']) ? trim($data['alasan_permohonan']) : "";
$list_alasan = ['1' => 'Membentuk Rumah Tangga Baru', '2' => 'Kartu Keluarga Hilang/Rusak', '3' => 'Lainnya / Perubahan Data', '4' => 'Kepindahan Penduduk'];

if (!empty($keperluan_db)) { $keperluan_surat = $keperluan_db; }
elseif (isset($list_alasan[$alasan_db])) { $keperluan_surat = $list_alasan[$alasan_db]; }
else { $keperluan_surat = isset($data['jenis_layanan']) ? str_replace('_', ' ', $data['jenis_layanan']) : "PERMOHONAN DATA"; }

$keperluan_final = strtoupper($keperluan_surat);
$tanggal_cetak = sipandang_siak_tanggal_final_ttd($koneksi, $data);

$nama_pejabat_ttd = preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', trim((string) ($data['nama_pejabat'] ?? ''))));
$nama_pejabat_ttd = $nama_pejabat_ttd !== '' ? strtoupper($nama_pejabat_ttd) : '....................................';

$sk_internal_ttd_digital = !empty($data['ttd_digital']);
$id_siak_int = (int) preg_replace('/\D/', '', (string) ($data['id_siak'] ?? $id));
?>

<!DOCTYPE html>
<html>
<head>
    <title>SK Internal - <?= htmlspecialchars($nama_tampil) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { background-color: #f0f0f0; margin: 0; padding: 0; }
        .page {
            width: 210mm; height: 297mm; padding: 1.5cm 2cm; margin: 0 auto;
            background: white; box-sizing: border-box; font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt; line-height: 1.3; color: #000; position: relative;
        }
        <?php echo sipandang_kop_kelurahan_cetak_fix_css(sipandang_kop_kelurahan_data($koneksi)); ?>
        .judul-surat { text-align: center; margin-bottom: 20px; }
        .judul-surat h3 { margin: 0; text-decoration: underline; font-size: 14pt; font-weight: bold; }
        .table-data { width: 100%; margin: 15px 0 15px 40px; border-collapse: collapse; }
        .table-data td { vertical-align: top; padding: 2px 0; }
        .paragraf-isi {
            text-align: justify;
            margin: 0 0 12px;
            text-indent: 40px;
            padding-left: 0;
            line-height: 1.5;
        }
        .keperluan { text-align: center; font-weight: bold; text-decoration: underline; margin: 16px 0; font-size: 12pt; }
        .ttd-container { float: right; min-width: 340px; text-align: center; margin-top: 20px; }
        .ttd-container p { margin: 6px 0; }
        .nama-pejabat {
            margin-top: 70px;
            font-weight: bold;
            text-decoration: underline;
            font-size: 12pt;
            white-space: normal;
            display: inline-block;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .nama-pejabat-digital {
            margin-top: 0;
        }
        .sk-internal-ttd-digital {
            box-sizing: border-box;
            height: 70px;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 0;
            overflow: visible;
        }
        .sk-internal-ttd-digital img {
            max-height: 64px;
            max-width: 360px;
            width: auto;
            height: auto;
            display: block;
            margin: 0;
            transform: scale(1.85);
            transform-origin: center center;
        }
        <?= siak_alur_ttd_qr_print_css(); ?>
        .sk-internal-qr-pojok { float: left; margin-left: 30px; text-align: center; width: 100px; }
        .nip-pejabat { margin: 4px 0 0; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
        p { margin: 10px 0; }
        @media print { body { background: none; } .page { margin: 0; box-shadow: none; width: 210mm; height: 297mm; } }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <div class="page">
        <?= format_surat_kop_html(['mysqli' => $koneksi]); ?>

        <div class="judul-surat">
            <h3>SURAT KETERANGAN</h3>
            <p>Nomor : 400.12.2.1 / <span style="padding: 0 5px;"><?= $data['nomor_surat'] ?: '       ' ?></span> / <?= romawi(date('n')) ?> / Kel. Mdn / <?= date('Y') ?></p>
        </div>

        <p class="paragraf-isi">Yang bertanda tangan di bawah ini Lurah Medang Kecamatan Pagedangan Kabupaten Tangerang, dengan ini menerangkan bahwa :</p>

        <table class="table-data">
            <tr><td width="160">Nama</td><td width="20">:</td><td><b><?= $nama_tampil ?></b></td></tr>
            <tr><td>NIK</td><td>:</td><td><?= $nik_cetak ?></td></tr>
            <tr><td>Tempat Tgl. Lahir</td><td>:</td><td><?= $tmp_lahir ?>, <?= $tgl_lahir ?></td></tr>
            <tr><td>Jenis Kelamin</td><td>:</td><td><?= $jk ?></td></tr>
            <tr><td>Kewarganegaraan</td><td>:</td><td>INDONESIA</td></tr>
            <tr><td>Agama</td><td>:</td><td><?= $agama ?></td></tr>
            <tr><td>Pekerjaan</td><td>:</td><td><?= $pekerjaan ?></td></tr>
            <tr>
                <td>Alamat</td><td>:</td>
                <td><?= sipandang_siak_sk_alamat_baris_html($data) ?></td>
            </tr>
        </table>

        <p class="paragraf-isi"><?= htmlspecialchars(sipandang_siak_paragraf_pengantar_sk($data, 'Medang')) ?></p>

        <div class="keperluan">
            <?= $keperluan_final ?>
        </div>

        <p class="paragraf-isi">Demikian Surat Keterangan ini dibuat, agar yang berkepentingan dapat membantu sepenuhnya.</p>

        <div style="margin-top: 30px;">
            <?php if ($id_siak_int > 0 && sipandang_verifikasi_qr_siak_footer_tampilkan($id_siak_int)): ?>
            <div class="sk-internal-qr-pojok">
                <?= sipandang_verifikasi_qr_siak_inline_html($id_siak_int, 84) ?>
            </div>
            <?php endif; ?>

            <div class="ttd-container">
                <p>MEDANG, <?= $tanggal_cetak ?></p>
                <p><?= sipandang_siak_ttd_lines_kel(['jabatan' => $data['jab_pejabat'] ?? ''], null, $koneksi); ?></p>
                <?php if ($sk_internal_ttd_digital && ($ttdDigitalHtml = print_sk_internal_ttd_digital_html($koneksi, $data)) !== ''): ?>
                <?= $ttdDigitalHtml ?>
                <div class="nama-pejabat nama-pejabat-digital"><?= htmlspecialchars($nama_pejabat_ttd) ?></div>
                <?php else: ?>
                <div class="nama-pejabat"><?= htmlspecialchars($nama_pejabat_ttd) ?></div>
                <?php endif; ?>
                <p class="nip-pejabat">NIP. <?= htmlspecialchars($data['nip_pejabat'] ?: '....................................') ?></p>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

</body>
</html>