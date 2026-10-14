<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';
require_once __DIR__ . '/inc_format_surat_resmi.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_siak_ttd.php';
require_once __DIR__ . '/inc_siak_alur_ttd.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
siak_alur_ttd_ensure_schema($koneksi);

/** Surat Pengantar — TTD digital Kel (gambar atau QR); basah tidak memakai fungsi ini. */
function print_pengantar_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'pengantar-ttd-digital', 56);
}

function romawi($n) {
    $map = [
        1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI',
        7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'
    ];
    return isset($map[$n]) ? $map[$n] : $n;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$query = mysqli_query($koneksi, "
    SELECT p.*, d.nama_lengkap as nama_dari_penduduk, 
    (SELECT COUNT(*) FROM siak_anggota WHERE id_permohonan = p.id_siak) as total_keluarga
    FROM permohonan_siak p
    LEFT JOIN tbl_penduduk d ON p.nik_pemohon = d.nik
    WHERE p.id_siak = '$id'
");
$d = mysqli_fetch_assoc($query);
if (!$d) { die("Data tidak ditemukan"); }

$nama_tampil = !empty($d['nama_pemohon']) ? $d['nama_pemohon'] : $d['nama_dari_penduduk'];
$nama_kk_tampil = sipandang_siak_nama_kepala_keluarga($koneksi, $d);
if ($nama_kk_tampil === '') {
    $nama_kk_tampil = $nama_tampil;
}
function terbilang($angka) {
    $bilangan = array("", "SATU", "DUA", "TIGA", "EMPAT", "LIMA", "ENAM", "TUJUH", "DELAPAN", "SEMBILAN", "SEPULUH", "SEBELAS");
    if ($angka < 12) return $bilangan[$angka];
    return $angka; 
}
$id_ttd = $d['nama_ttd_kel'];
$q_ttd = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_ttd'");
$ttd = mysqli_fetch_assoc($q_ttd);
$nip_pejabat = !empty($ttd['nip']) ? $ttd['nip'] : ($ttd['nip_pamong'] ?? '197002032007011018');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Pengantar Pindah - <?= $nama_tampil; ?></title>
    <style>
        @page { 
            size: A4 portrait; 
            margin: 10mm 15mm 10mm 15mm; 
        }
        body { 
            font-family: "Arial", sans-serif; 
            font-size: 11pt; 
            line-height: 1.3; 
            color: #000; 
            margin: 0;
            padding: 0;
            position: relative;
            min-height: auto;
        }
        <?php echo sipandang_kop_cetak_head_css($koneksi); ?>
        
        .judul-surat { text-align: center; margin-bottom: 20px; }
        .judul-surat b { font-size: 12pt; text-decoration: underline; text-transform: uppercase; }
        .judul-surat p { margin: 3px 0; font-weight: bold; font-size: 11pt; }

        .isi-surat { margin-bottom: 10px; text-align: justify; }
        
        .tabel-data { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .tabel-data td { vertical-align: top; padding: 2px 0; }
        .tabel-data td.label { width: 200px; }
        .tabel-data td.titik { width: 15px; }

        .footer-surat { margin-top: 30px; width: 100%; overflow: hidden; }
        .ttd-box {
            float: right;
            min-width: 250px;
            width: auto;
            text-align: center;
        }
        .pengantar-ttd-digital {
            box-sizing: border-box;
            height: 50px;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 0;
            overflow: visible;
        }
        .pengantar-ttd-digital img {
            max-height: 48px;
            max-width: 295px;
            width: auto;
            height: auto;
            display: block;
            margin: 0;
            transform: scale(2.05);
            transform-origin: center center;
        }
        <?= siak_alur_ttd_qr_print_css(); ?>
        
        .keterangan { 
            margin-top: 18px;
            clear: both;
            font-size: 9pt; 
            border-top: 1px dashed #aaa;
            padding-top: 8px;
        }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <?= sipandang_kop_cetak_html($koneksi); ?>

    <div class="judul-surat" style="text-align: center; text-transform: uppercase;">
        <b>Surat Pengantar Pindah</b><br>
        <b>Antar Kecamatan Dalam Daerah Kabupaten/Kota</b><br>
        <p>NOMOR : 475.21 / <span style="padding: 0 20px;"><?= $d['nomor_surat'] ?: '&nbsp;&nbsp;' ?></span> / <?= romawi(date('n')) ?> / Kel. Mdn. <?= date('Y') ?></p>
    </div>

    <div class="isi-surat">
        Yang bertanda tangan di bawah ini, menerangkan Permohonan Pindah Penduduk Warga Negara Indonesia (WNI) dengan data sebagai berikut :
    </div>

    <table class="tabel-data">
        <tr>
            <td class="label">NIK</td>
            <td class="titik">:</td>
            <td style="font-weight: bold; letter-spacing: 1px;"><?= $d['nik_pemohon']; ?></td>
        </tr>
        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="titik">:</td>
            <td style="font-weight: bold;"><?= strtoupper($nama_tampil); ?></td>
        </tr>
        <tr>
            <td class="label">Nomor Kartu Keluarga</td>
            <td class="titik">:</td>
            <td style="font-weight: bold; letter-spacing: 1px;"><?= $d['no_kk_lama']; ?></td>
        </tr>
        <tr>
            <td class="label">Nama Kepala Keluarga</td>
            <td class="titik">:</td>
            <td style="font-weight: bold;">
                <?= strtoupper($nama_kk_tampil); ?>
            </td>
        </tr>
        <tr>
            <td class="label">Alamat Sekarang</td>
            <td class="titik">:</td>
            <td>
                <?= strtoupper($d['alamat']); ?><br>
                RT. <?= $d['rt']; ?> RW. <?= $d['rw']; ?><br>
                Desa/Kel. : MEDANG<br>
                Kec. : PAGEDANGAN<br>
                Kab/Kota. : TANGERANG<br>
                Prov. : BANTEN<br>
            </td>
        </tr>
        <tr>
            <td class="label"><br>Alamat Tujuan Pindah</td>
            <td class="titik"><br>:</td>
            <td>
                <br><?= strtoupper($d['alamat_tujuan']); ?><br>
                RT. <?= $d['rt_tujuan']; ?> RW. <?= $d['rw_tujuan']; ?><br>
                Desa/Kel. : <?= strtoupper($d['kelurahan_tujuan']); ?><br>
                Kec. : <?= strtoupper($d['kecamatan_tujuan']); ?><br>
                Kab/Kota. : <?= strtoupper($d['kabupaten_tujuan']); ?><br>
                Prov. : <?= strtoupper($d['provinsi_tujuan']); ?><br>
            </td>
        </tr>
        <tr>
            <td class="label"><br>Jumlah Keluarga yang Pindah</td>
            <td class="titik"><br>:</td>
            <td><br>0<?= $d['total_keluarga']; ?> (<?= terbilang($d['total_keluarga']); ?>) Orang</td>
        </tr>
    </table>

    <div class="isi-surat">
        Adapun Permohonan Pindah Penduduk WNI yang bersangkutan sebagaimana terlampir.<br>
        Demikian Surat Pengantar Pindah ini dibuat agar dipergunakan sebagaimana mestinya.
    </div>

    <div class="footer-surat">
        <div class="ttd-box">
            MEDANG, <?= sipandang_siak_tanggal_final_ttd($koneksi, $d); ?><br>
            <?= sipandang_siak_ttd_lines_kel($ttd ?: [], null, $koneksi); ?>
            <?php if (!empty($d['ttd_digital']) && ($ttdDig = print_pengantar_ttd_digital_html($koneksi, $d)) !== ''): ?>
            <?= $ttdDig ?>
            <?php else: ?>
            <br><br><br><br><br>
            <?php endif; ?>
            <div style="white-space: normal; overflow-wrap: anywhere; word-break: break-word; max-width: 100%; font-weight: bold; text-decoration: underline; display: inline-block;">
                <?= strtoupper($ttd['nama_pamong'] ?? 'EKA CHAEDAR KUSUMA WIJAYA, S.IP, M.TR.I.P'); ?>
            </div><br>
            NIP. <?= $nip_pejabat; ?>
        </div>
    </div>

    <?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

    <div class="keterangan">
        Keterangan :<br>
        - Surat Pengantar ini dibawa oleh Pemohon dan diarsipkan di Kecamatan
    </div>

</body>
</html>