<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_siak_ttd.php';
require_once __DIR__ . '/inc_siak_alur_ttd.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
siak_alur_ttd_ensure_schema($koneksi);

/** F-1.01 — overlay TTD digital Kel saja (basah tidak memakai fungsi ini). */
function print_f101_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'f101-kel-ttd-digital', 52);
}

// Ambil ID dari URL
$id = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');

/* =========================
   1. AMBIL DATA UTAMA
========================= */
$query = mysqli_query($koneksi, "SELECT * FROM permohonan_siak WHERE id_siak = '$id' LIMIT 1");
$d = mysqli_fetch_assoc($query);
$tanggal_ttd = sipandang_siak_tanggal_final_ttd($koneksi, $d ?: []);

/* =========================
   2. AMBIL DATA ANGGOTA KELUARGA
========================= */
$anggota = sipandang_f101_load_anggota($koneksi, $id, $d ?: []);

/* =========================
   3. AMBIL DATA PENANDATANGAN (DINAMIS)
========================= */
$id_kec = $d['nama_ttd_kec'] ?? ''; 
$q_kec = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kec'");
$ttd_kec = mysqli_fetch_assoc($q_kec);

$id_kel = $d['nama_ttd_kel'] ?? '';
$q_kel = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kel'");
$ttd_kel = mysqli_fetch_assoc($q_kel);

/* =========================
   KONFIGURASI TOTAL DATA
========================= */
$total_asli = count($anggota);
$total_tampil = str_pad($total_asli, 2, "0", STR_PAD_LEFT);

/* No. HP: kolom permohonan_siak biasanya kosong — fallback dari tbl_penduduk (KK tujuan) */
$f101_no_hp = trim((string) ($d['no_hp'] ?? ''));
if ($f101_no_hp === '') {
    $kk_hp = preg_replace('/\D/', '', (string) ($d['no_kk_lama'] ?? $d['no_kk_asal'] ?? ''));
    if (strlen($kk_hp) === 16) {
        $kk_hp_esc = mysqli_real_escape_string($koneksi, $kk_hp);
        $q_hp = mysqli_query(
            $koneksi,
            "SELECT no_hp FROM tbl_penduduk
             WHERE no_kk='$kk_hp_esc' AND TRIM(COALESCE(no_hp,''))<>''
             AND UPPER(COALESCE(status_dasar,'HIDUP'))='HIDUP'
             ORDER BY (shdk IN ('01','1')) DESC, nik LIMIT 1"
        );
        if ($q_hp && ($r_hp = mysqli_fetch_assoc($q_hp))) {
            $f101_no_hp = trim((string) ($r_hp['no_hp'] ?? ''));
        }
    }
}

/* =========================
   FUNGSI HELPER VISUAL
========================= */
function boxku($text='', $jumlah=1){
    $text = strtoupper(trim($text ?? ''));
    $text = preg_replace("/[^A-Za-z0-9]/", "", $text);
    $arr = str_split($text);
    $html = '<div class="kotak-wrap">';
    for($i=0; $i<$jumlah; $i++){
        $isi = isset($arr[$i]) ? $arr[$i] : '';
        $html .= '<div class="kotak">'.$isi.'</div>';
    }
    $html .= '</div>';
    return $html;
}

function box_opsi($sampai_angka, $terpilih = ''){
    return sipandang_f101_render_box_opsi((int) $sampai_angka, $terpilih);
}

function garis($isi='',$lebar='100%'){
    return '<div class="field-garis" style="width:'.$lebar.';">'.strtoupper($isi ?? '').'</div>';
}


function box_kosong($jumlah){
    $html = '<div class="kotak-wrap border-opsi">';
    for($n=1; $n<=$jumlah; $n++){
        $html .= '<div class="angka-pilih">&nbsp;</div>';
    }
    $html .= '</div>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>F-1.01 - <?= $d['nama_pemohon'] ?></title>
    <style>
        /* 1. PERBAIKAN MARGIN AGAR KIRI KANAN TIDAK TERPOTONG */
        @page { 
            size: legal landscape; 
            /* Diubah dari 0 menjadi 0.6cm agar printer tidak memotong garis luar */
            margin: 0.6cm; 
        }
    
        @media print {
            body { 
                margin: 0; 
                /* Memastikan warna abu-abu tetap muncul */
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
    
            /* Menghilangkan Header/Footer Otomatis (Tanggal/URL) secara paksa */
            header, footer { display: none !important; }
        }
    
        /* 2. PENYESUAIAN AGAR TABEL PAS DENGAN LEBAR KERTAS */
        body { 
            font-family: Arial, sans-serif; 
            font-size: 5.5pt; 
            color: #000; 
            line-height: 1.1; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
            /* Memastikan tabel tidak melampaui batas margin kertas */
            word-wrap: break-word;
        }
    
        td, th { border: 0.7px solid #000; padding: 1px 2px; vertical-align: middle; height: 14px; }
        
        .gray { 
            background: #e0e0e0 !important; 
            background-color: #e0e0e0 !important; 
            font-weight: bold; 
            text-align: center; 
            text-transform: uppercase; 
            font-size: 5pt; 
        }
        
        /* Utility pendukung tetap sama */
        .kotak-wrap { display: inline-flex; vertical-align: middle; }
        .kotak { width: 10.5px; height: 11.5px; border: 0.6px solid #000; margin-right: -0.6px; text-align: center; line-height: 11px; font-weight: bold; font-size: 6.5pt; background: #fff; }
        .border-opsi { border: 0.6px solid #000; padding: 0 1px; background: #fff; }
        .angka-pilih { width: 10px; text-align: center; font-size: 5pt; border-right: 0.4px solid #000; margin-right: 1px; line-height: 9px; }
        .angka-pilih:last-child { border-right: none; }
        .angka-pilih.terpilih { font-weight: bold; border: 1px solid #000; border-radius: 50%; background: #c8c8c8; line-height: 8px; }
        .field-garis { border: 0.7px solid #000; height: 14px; line-height: 13px; padding-left: 4px; font-weight: bold; font-size: 7.5pt; background: #fff; }
        .no-border td { border: none !important; }
        .bold { font-weight: bold; }
        .tabel-data { margin-top: 2px; }
        tr { page-break-inside: avoid; }
        .header-title { font-size: 13pt; font-weight: bold; text-align: center; }
        .form-code { position: absolute; right: 10px; top: 10px; border: 1.5px solid #000; padding: 2px 10px; font-weight: bold; font-size: 10pt; }
        .f101-kel-ttd-digital {
            box-sizing: border-box;
            height: 38px;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 0;
            overflow: visible;
        }
        .f101-kel-ttd-digital img {
            max-height: 34px;
            max-width: 175px;
            width: auto;
            height: auto;
            display: block;
            margin: 0;
            transform: scale(1.85);
            transform-origin: center center;
        }
        <?= siak_alur_ttd_qr_print_css(); ?>
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

<div class="form-code">F-1.01</div>

<table class="no-border">
    <tr>
        <td width="10%"><img src="assets/logokab.png" width="60"></td>
        <td width="80%">
            <div class="header-title">PEMERINTAH KABUPATEN TANGERANG</div>
            <div class="header-title">DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL</div>
            <div class="header-title" style="font-size: 15pt; margin-top: 5px;">FORMULIR BIODATA PENDUDUK WARGA NEGARA INDONESIA</div>
        </td>
        <td width="10%"></td>
    </tr>
</table>

<table class="no-border" style="margin-top: 5px;">
    <tr>
        <td width="62%" valign="top">
            <div class="bold" style="margin-bottom: 3px; font-size: 6.5pt;">DATA KEPALA KELUARGA</div>
            <table class="no-border">
                <tr><td width="115">Nama Kepala Keluarga</td><td width="10">:</td><td><?= garis($d['nama_kk_tujuan'], '380px') ?></td></tr>
                <tr><td>Alamat</td><td>:</td><td><?= garis($d['alamat'], '380px') ?></td></tr>
                <tr><td>Kode Pos / RT / RW</td><td>:</td><td>
                    <?= boxku('15334', 5) ?> &nbsp; 
                    RT <?= boxku($d['rt'], 3) ?> &nbsp; 
                    RW <?= boxku($d['rw'], 3) ?> &nbsp; 
                    Jml Anggota : <?= boxku($total_tampil, 2) ?> Orang
                </td></tr>
                <tr><td>Telepon / HP</td><td>:</td><td><?= boxku($f101_no_hp, 12) ?></td></tr>
            </table>
        </td>
        <td width="38%" valign="top">
            <table style="width: auto; margin-left: auto;"> 
                <tr class="gray">
                    <td colspan="3" align="left">Diisi Oleh Petugas</td>
                </tr>
                <tr>
                    <td width="80">Provinsi</td> <td width="40"><?= boxku('36', 2) ?></td>
                    <td style="padding-left: 10px;">BANTEN</td>
                </tr>
                <tr>
                    <td>Kabupaten/Kota</td>
                    <td><?= boxku('03', 2) ?></td>
                    <td style="padding-left: 10px;">TANGERANG</td>
                </tr>
                <tr>
                    <td>Kecamatan</td>
                    <td><?= boxku('22', 2) ?></td>
                    <td style="padding-left: 10px;">PAGEDANGAN</td>
                </tr>
                <tr>
                    <td>Kelurahan/Desa</td>
                    <td><?= boxku('1004', 4) ?></td>
                    <td style="padding-left: 10px;">MEDANG</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="bold" style="margin: 5px 0 2px 0; font-size: 7pt;">DATA KELUARGA</div>

<table class="tabel-data">
    <tr class="gray">
        <td width="20">No</td>
        <td>Nama Lengkap</td> <td width="40">Gelar</td>
        <td width="200">Nomor NIK</td>
        <td>Alamat Sebelumnya</td> <td width="100">Nomor Paspor</td>
        <td width="100">Tgl Akhir Paspor</td>
    </tr>
    <?php foreach($anggota as $i => $ag): ?>
    <tr>
        <td align="center"><?= $i+1 ?></td>
        <td class="bold" style="padding-left: 5px;"><?= strtoupper($ag['nama']) ?></td>
        <td align="center"><?= box_opsi(3) ?></td>
        <td align="center"><?= boxku($ag['nik'], 16) ?></td>
        <td></td>
        <td align="center"></td>
        <td align="center"><?= boxku('', 8) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<table class="tabel-data">
    <tr class="gray">
        <td width="20" rowspan="2">No</td>
        <td width="28" rowspan="2">JK</td>
        <td width="200" rowspan="2">Tempat Lahir</td>
        <td width="100" rowspan="2">Tanggal Lahir</td>
        <td width="25" rowspan="2">Umur</td>
        <td width="28" rowspan="2">Akta</td>
        <td width="90" rowspan="2">Nomor Akta Kelahiran</td>
        <td width="28" rowspan="2">GD</td>
        <td width="86" rowspan="2">Agama</td>
        <td width="50" rowspan="2">Status Perkawinan</td>
        <td colspan="3">Perkawinan</td>
        <td colspan="3">Perceraian</td>
    </tr>
    <tr class="gray">
        <td width="30">Akta/ Buku Nikah</td>
        <td width="100">Nomor Akta Perkawinan/Buku Nikah</td>
        <td width="90">Tanggal Perkawinan</td>
        <td width="30">Akta Cerai</td>
        <td width="100">Nomor Akta Perceraian</td>
        <td width="90">Tanggal Perceraian</td>
    </tr>
    
    <?php foreach($anggota as $i => $ag):
        $tgl_db = $ag['tanggal_lahir'] ?? '';
        $tgl_val = (!empty($tgl_db) && $tgl_db != '0000-00-00') ? date('dmY', strtotime($tgl_db)) : '';
        $umur_val = '';
        if (!empty($tgl_db) && $tgl_db != '0000-00-00') {
            $umur_val = (new DateTime())->diff(new DateTime($tgl_db))->y;
        }
        $akta_ada = sipandang_f101_normalize_kode($ag['akta_lahir_ada'] ?? '', 2);
        if ($akta_ada === '' && trim((string) ($ag['no_akta_lahir'] ?? '')) !== '') {
            $akta_ada = '2';
        }
        if ($akta_ada === '') {
            $akta_ada = sipandang_f101_default_ada_tidak();
        }
        $no_akta_tampil = sipandang_f101_kode_ada($akta_ada) ? strtoupper(trim((string) ($ag['no_akta_lahir'] ?? ''))) : '';
        $nikah_ada = sipandang_f101_normalize_kode($ag['akta_nikah_ada'] ?? '', 2) ?: sipandang_f101_default_ada_tidak();
        $cerai_ada = sipandang_f101_normalize_kode($ag['akta_cerai_ada'] ?? '', 2) ?: sipandang_f101_default_ada_tidak();
        $no_nikah = sipandang_f101_kode_ada($nikah_ada) ? strtoupper(trim((string) ($ag['no_akta_nikah'] ?? ''))) : '';
        $no_cerai = sipandang_f101_kode_ada($cerai_ada) ? strtoupper(trim((string) ($ag['no_akta_cerai'] ?? ''))) : '';
        $tgl_kawin_db  = $ag['tgl_kawin'] ?? '';
        $tgl_kawin_fmt = (!empty($tgl_kawin_db) && $tgl_kawin_db !== '0000-00-00')
            ? date('dmY', strtotime($tgl_kawin_db)) : '';
        $tgl_cerai_db  = $ag['tgl_cerai'] ?? '';
        $tgl_cerai_fmt = (!empty($tgl_cerai_db) && $tgl_cerai_db !== '0000-00-00')
            ? date('dmY', strtotime($tgl_cerai_db)) : '';
        $kelainan = sipandang_f101_normalize_kode($ag['kelainan_fisik'] ?? '', 2) ?: '1';
        $cacat = ($kelainan === '2') ? sipandang_f101_normalize_kode($ag['penyandang_cacat'] ?? '', 6) : '';
    ?>
    <tr>
        <td align="center"><?= $i+1 ?></td>
        <td align="center"><?= box_opsi(2, sipandang_f101_kode_jk($ag['jk'] ?? '')) ?></td>
        <td><?= strtoupper($ag['tempat_lahir'] ?? '') ?></td>
        <td align="center"><?= boxku($tgl_val, 8) ?></td>
        <td align="center"><?= $umur_val ?></td>
        <td align="center"><?= box_opsi(2, $akta_ada) ?></td>
        <td style="font-size:5.5pt;padding-left:2px;"><?= htmlspecialchars($no_akta_tampil) ?></td>
        <td align="center"><?= boxku(sipandang_kode_gol_darah_cetak($ag['gol_darah'] ?? ''), 2) ?></td>
        <td align="center"><?= box_opsi(7, $ag['agama'] ?? '') ?></td>
        <td align="center"><?= box_opsi(4, $ag['status_perkawinan'] ?? '') ?></td>
        <td align="center"><?= box_opsi(2, $nikah_ada) ?></td>
        <td style="font-size:5.5pt;padding-left:2px;"><?= htmlspecialchars($no_nikah) ?></td>
        <td align="center"><?= boxku($tgl_kawin_fmt, 8) ?></td>
        <td align="center"><?= box_opsi(2, $cerai_ada) ?></td>
        <td style="font-size:5.5pt;padding-left:2px;"><?= htmlspecialchars($no_cerai) ?></td>
        <td align="center"><?= boxku($tgl_cerai_fmt, 8) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<table class="tabel-data">
    <tr class="gray">
        <td width="20">No</td>
        <td width="28">SHDK</td>
        <td width="35">Kelainan</td> 
        <td width="80">Penyandang Cacat</td>
        <td width="125">Pendidikan Terakhir</td>
        <td width="35">Peker jaan</td>
        <td width="200">NIK Ibu</td>
        <td width="280">Nama Lengkap Ibu</td>
        <td width="200">NIK Ayah</td>
        <td>Nama Lengkap Ayah</td>
    </tr>
    
    <?php foreach($anggota as $i => $ag):
        $kelainan2 = sipandang_f101_normalize_kode($ag['kelainan_fisik'] ?? '', 2) ?: '1';
        $cacat2 = ($kelainan2 === '2') ? sipandang_f101_normalize_kode($ag['penyandang_cacat'] ?? '', 6) : '';
    ?>
    <tr>
        <td align="center"><?= $i+1 ?></td>
        <td align="center"><?= boxku(sipandang_kode_shdk_formulir_kotak($ag['shdk'] ?? ''), 2) ?></td>
        <td align="center"><?= box_opsi(2, $kelainan2) ?></td>
        <td align="center"><?= box_opsi(6, $cacat2) ?></td>
        <td align="center"><?= box_opsi(10, $ag['pendidikan'] ?? '') ?></td>
        <td align="center"><?= boxku(sipandang_kode_pekerjaan_cetak($ag['pekerjaan'] ?? ''), 2) ?></td>
        <td align="center"><?= boxku($ag['nik_ibu'], 16) ?></td>
        <td class="bold" style="font-size: 6pt; padding-left: 3px;"><?= strtoupper($ag['nama_ibu']) ?></td>
        <td align="center"><?= boxku($ag['nik_ayah'], 16) ?></td>
        <td class="bold" style="font-size: 6pt; padding-left: 3px;"><?= strtoupper($ag['nama_ayah']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<table class="no-border" style="margin-top: 15px; text-align: center; font-size: 7pt; width: 100%;">
    <tr>
        <td width="25%">
            Kepala Instansi Pelaksana<br>
            Kabupaten Tangerang<br><br><br><br><br>
            [ ............................................... ]
        </td>
        <td width="25%">
            Mengetahui,<br>
            <?= sipandang_siak_ttd_lines_kec($ttd_kec ?: [], null, $koneksi); ?><br><br><br><br>
            <b class="nama-pejabat"><?= strtoupper($ttd_kec['nama_pamong'] ?? 'EKO SUYANTO, SE'); ?></b><br>
            <span class="nip-pejabat">NIP. <?= $ttd_kec['nip'] ?? '197202192006041003'; ?></span>
        </td>
        <td width="25%">
            Medang, <?= $tanggal_ttd ?><br>
            <?= sipandang_siak_ttd_lines_kel($ttd_kel ?: [], null, $koneksi); ?><br>
            <?php if (!empty($d['ttd_digital']) && ($ttdDig = print_f101_ttd_digital_html($koneksi, $d)) !== ''): ?>
            <?= $ttdDig ?>
            <?php else: ?>
            <br><br><br><br>
            <?php endif; ?>
            <b class="nama-pejabat"><?= strtoupper($ttd_kel['nama_pamong'] ?? 'ETI SUHARYATI, A.Md'); ?></b><br>
            <span class="nip-pejabat">NIP. <?= $ttd_kel['nip'] ?? '19707212007012008'; ?></span>
        </td>
        <td width="25%">
            Kepala Keluarga<br><br><br><br><br><br>
            <b>[ <?= strtoupper($d['nama_kk_tujuan'] ?? '') ?> ]</b>
        </td>
    </tr>
</table>

<?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

</body>
</html>