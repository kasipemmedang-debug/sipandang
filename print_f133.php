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

/** F-1.33 — overlay TTD digital saja (basah tidak memakai fungsi ini). */
function print_f133_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'f133-ttd-digital', 56);
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

function romawi($n) {
    $map = [
        1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI',
        7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'
    ];
    return isset($map[$n]) ? $map[$n] : $n;
}

// Query mengambil data permohonan, penduduk, dan jumlah anggota
$query = mysqli_query($koneksi, "
    SELECT 
        p.*, 
        d.nama_lengkap as nama_dari_penduduk, 
        (SELECT COUNT(*) FROM siak_anggota WHERE id_permohonan = p.id_siak) as total_keluarga
    FROM permohonan_siak p
    LEFT JOIN tbl_penduduk d ON p.nik_pemohon = d.nik
    WHERE p.id_siak = '$id'
");

$d = mysqli_fetch_assoc($query);

if (!$d) {
    die("Data tidak ditemukan");
}

// Logika nama lengkap pemohon
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

// Ambil data penandatangan
$id_ttd = $d['nama_ttd_kel'];
$q_ttd = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_ttd'");
$ttd = mysqli_fetch_assoc($q_ttd);
$nip_pejabat = !empty($ttd['nip']) ? $ttd['nip'] : ($ttd['nip_pamong'] ?? '197809252008011009');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>F-1.33 - <?= $nama_tampil; ?></title>
    <style>
        @page { size: A4 portrait; margin: 10mm 15mm 10mm 15mm; }
        body { font-family: "Arial", sans-serif; font-size: 11pt; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        <?php echo sipandang_kop_kelurahan_cetak_fix_css(sipandang_kop_kelurahan_data($koneksi)); ?>
        .kode-f { float: right; border: 1px solid #000; padding: 2px 15px; font-weight: bold; font-size: 12pt; margin-bottom: 10px; }
        .judul-surat { text-align: center; margin-bottom: 20px; clear: both; }
        .judul-surat b { font-size: 12pt; text-transform: uppercase; }
        .judul-surat .nomor { margin: 5px 0; font-weight: bold; }
        .tabel-data { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .tabel-data td { vertical-align: top; padding: 2px 0; }
        .tabel-data td.label { width: 210px; }
        .tabel-data td.titik { width: 15px; text-align: center; }
        .detail-alamat table { border-collapse: collapse; margin-top: 2px; }
        .detail-alamat table td { padding: 0; vertical-align: top; line-height: 1.35; }
        .detail-alamat .aw-rtrw { white-space: nowrap; padding-bottom: 1px; }
        .detail-alamat .aw-lbl { width: 90px; white-space: nowrap; }
        .detail-alamat .aw-colon { width: 12px; text-align: center; }
        .isi-surat { margin-bottom: 15px; text-align: justify; }
        .footer-surat { margin-top: 30px; width: 100%; }
        .ttd-box { float: right; min-width: 250px; text-align: center; }
        .f133-ttd-digital {
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
        .f133-ttd-digital img {
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
        .keterangan { margin-top: 18px; clear: both; font-size: 9pt; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <?= format_surat_kop_html(['mysqli' => $koneksi]); ?>

    <div class="kode-f">F-1.33</div>

    <div class="judul-surat">
        <b>Surat Pengantar Pindah</b><br>
        <b><u>Antar Kabupaten/Kota Atau Antar Provinsi</u></b><br>
        
        <div class="nomor">
            NOMOR : 400.12.2 / 
            <span style="padding: 0 10px;"><?= $d['nomor_surat'] ?: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' ?></span> 
            / <?= romawi(date('n')) ?> / Kel. Mdn / <?= date('Y') ?>
        </div>
    </div>

    <div class="isi-surat">
        Yang bertanda tangan di bawah ini, menerangkan Permohonan Pindah Penduduk Warga Negara Indonesia (WNI) dengan data sebagai berikut :
    </div>

    <table class="tabel-data">
        <tr>
            <td class="label">NIK</td>
            <td class="titik">:</td>
            <td style="font-weight: bold; letter-spacing: 2px;"><?= $d['nik_pemohon']; ?></td>
        </tr>
        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="titik">:</td>
            <td style="font-weight: bold;"><?= strtoupper($nama_tampil); ?></td>
        </tr>
        <tr>
            <td class="label">Nomor Kartu Keluarga</td>
            <td class="titik">:</td>
            <td style="font-weight: bold; letter-spacing: 2px;"><?= $d['no_kk_lama']; ?></td>
        </tr>
        <tr>
            <td class="label">Nama Kepala Keluarga</td>
            <td class="titik">:</td>
            <td style="font-weight: bold;"><?= strtoupper($nama_kk_tampil); ?></td>
        </tr>
        <tr>
            <td class="label">Alamat Sekarang</td>
            <td class="titik">:</td>
            <td>
                <div class="detail-alamat">
                    <?= strtoupper($d['alamat']); ?><br>
                    <table>
                        <tr><td colspan="3" class="aw-rtrw">RT. <?= htmlspecialchars($d['rt'] ?? ''); ?> &nbsp;&nbsp;&nbsp; RW. <?= htmlspecialchars($d['rw'] ?? ''); ?></td></tr>
                        <tr><td class="aw-lbl">Desa/Kel.</td><td class="aw-colon">:</td><td>MEDANG</td></tr>
                        <tr><td class="aw-lbl">Kec.</td><td class="aw-colon">:</td><td>PAGEDANGAN</td></tr>
                        <tr><td class="aw-lbl">Kab./Kota</td><td class="aw-colon">:</td><td>TANGERANG</td></tr>
                        <tr><td class="aw-lbl">Provinsi</td><td class="aw-colon">:</td><td>BANTEN</td></tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label"><br>Alamat Tujuan Pindah</td>
            <td class="titik"><br>:</td>
            <td>
                <br>
                <div class="detail-alamat">
                    <?= strtoupper($d['alamat_tujuan']); ?><br>
                    <table>
                        <tr><td colspan="3" class="aw-rtrw">RT. <?= htmlspecialchars($d['rt_tujuan'] ?? ''); ?> &nbsp;&nbsp;&nbsp; RW. <?= htmlspecialchars($d['rw_tujuan'] ?? ''); ?></td></tr>
                        <tr><td class="aw-lbl">Desa/Kel.</td><td class="aw-colon">:</td><td><?= strtoupper($d['kelurahan_tujuan']); ?></td></tr>
                        <tr><td class="aw-lbl">Kec.</td><td class="aw-colon">:</td><td><?= strtoupper($d['kecamatan_tujuan']); ?></td></tr>
                        <tr><td class="aw-lbl">Kab./Kota</td><td class="aw-colon">:</td><td><?= strtoupper($d['kabupaten_tujuan']); ?></td></tr>
                        <tr><td class="aw-lbl">Provinsi</td><td class="aw-colon">:</td><td><?= strtoupper($d['provinsi_tujuan']); ?></td></tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label"><br>Jumlah Keluarga yang Pindah</td>
            <td class="titik"><br>:</td>
            <td><br>0<?= $d['total_keluarga']; ?> (<?= terbilang($d['total_keluarga']); ?>) &nbsp;&nbsp; Orang</td>
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
            <?php if (!empty($d['ttd_digital']) && ($ttdDig = print_f133_ttd_digital_html($koneksi, $d)) !== ''): ?>
            <?= $ttdDig ?>
            <?php else: ?>
            <br><br><br><br><br>
            <?php endif; ?>
            <div style="font-weight: bold; text-decoration: underline;">
                <?= strtoupper($ttd['nama_pamong'] ?? 'DENIH AHMAD SOBARI, S.IP,. M.I.P'); ?>
            </div>
            NIP. <?= $nip_pejabat; ?>
        </div>
    </div>

    <div class="keterangan">
        Keterangan :<br>
        Surat Pengantar ini dibawa oleh Pemohon dan diarsipkan di Kecamatan
    </div>

    <?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight: bold;">PRINT SURAT</button>
    </div>

</body>
</html>