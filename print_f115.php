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

/** F-1.15 — overlay TTD digital Kel saja (basah tidak memakai fungsi ini). */
function print_f115_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'f115-kel-ttd-digital', 56);
}

$id = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');

$query = mysqli_query($koneksi, "SELECT * FROM permohonan_siak WHERE id_siak = '$id' LIMIT 1");
$d = mysqli_fetch_assoc($query);

if (!$d) {
    die('Data permohonan tidak ditemukan.');
}

$no_kk_cetak = sipandang_siak_no_kk_cetak($koneksi, $d);

$ordAng = sipandang_sql_order_siak_anggota();
$query_anggota = mysqli_query($koneksi, "SELECT * FROM siak_anggota WHERE id_permohonan = '$id' ORDER BY $ordAng LIMIT 10");
$anggota_list = [];
if ($query_anggota) {
    while ($row = mysqli_fetch_assoc($query_anggota)) {
        $anggota_list[] = $row;
    }
}

$id_kec = $d['nama_ttd_kec'] ?? '';
$ttd_kec = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kec'"));
$id_kel = $d['nama_ttd_kel'] ?? '';
$ttd_kel = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kel'"));

function buat_kotak($string, $jumlah_kotak) {
    $string_bersih = str_replace([' ', '.', '-'], '', $string ?? '');
    $split = str_split($string_bersih);
    $output = '<span class="kotak-wrap">';
    for ($i = 0; $i < $jumlah_kotak; $i++) {
        $char = isset($split[$i]) ? htmlspecialchars($split[$i]) : '';
        $output .= "<span class='kotak-karakter'>$char</span>";
    }
    return $output . '</span>';
}

function cek_kode_alasan_f115($teks_alasan) {
    $teks = strtoupper($teks_alasan ?? '');
    if ($teks === '1' || $teks === '2' || $teks === '3') {
        return $teks;
    }
    if (strpos($teks, 'MEMBENTUK') !== false) {
        return '1';
    }
    if (strpos($teks, 'HILANG') !== false || strpos($teks, 'RUSAK') !== false) {
        return '2';
    }
    return '3';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak F-1.15</title>
    <style>
        :root {
            --f115-font: Arial, Helvetica, sans-serif;
            --f115-size: 9pt;
            --f115-kotak-w: 14px;
            --f115-kotak-h: 18px;
            --f115-label-w: 185px;
            --f115-colon-w: 12px;
            --f115-indent: calc(var(--f115-label-w) + var(--f115-colon-w));
            --f115-wilayah-kotak-max: 4;
            --f115-wilayah-gap: 5px;
            --f115-wilayah-teks-w: 220px;
            --f115-wilayah-kotak-slot-w: calc(
                var(--f115-wilayah-kotak-max) * var(--f115-kotak-w) - (var(--f115-wilayah-kotak-max) - 1) * 1px
            );
            --f115-wilayah-strip-w: calc(
                var(--f115-wilayah-kotak-slot-w) + var(--f115-wilayah-gap) + var(--f115-wilayah-teks-w)
            );
        }
        * { box-sizing: border-box; }
        body {
            font-family: var(--f115-font);
            font-size: var(--f115-size);
            margin: 0;
            padding: 15px;
            color: #000;
            line-height: 1.25;
        }
        @media print {
            body { padding: 0; }
            @page { size: legal; margin: 0.5cm; }
        }
        .f-header {
            border: 1px solid #000;
            padding: 4px 12px;
            font-weight: bold;
            font-size: var(--f115-size);
            float: right;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            clear: both;
            margin: 8px 0;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            line-height: 1.25;
        }
        .perhatian {
            font-size: var(--f115-size);
            line-height: 1.35;
            margin-bottom: 10px;
        }
        .form-row.form-row-akhir { margin-bottom: 20px; }
        .form-row,
        .row-identitas {
            display: grid;
            grid-template-columns: var(--f115-label-w) var(--f115-colon-w) minmax(0, 1fr);
            align-items: center;
            column-gap: 0;
            margin-bottom: 2px;
            font-size: var(--f115-size);
        }
        .lbl {
            width: var(--f115-label-w);
            padding: 1px 0;
            line-height: 1.25;
        }
        .lbl-wilayah { text-transform: uppercase; }
        .dots {
            width: var(--f115-colon-w);
            text-align: center;
        }
        .kotak-wrap {
            display: inline-flex;
            vertical-align: middle;
            align-items: stretch;
        }
        .kotak-karakter {
            width: var(--f115-kotak-w);
            height: var(--f115-kotak-h);
            border: 1px solid #000;
            margin-right: -1px;
            text-align: center;
            line-height: var(--f115-kotak-h);
            font-family: var(--f115-font);
            font-weight: bold;
            font-size: var(--f115-size);
            background: #fff;
        }
        .kotak-karakter:last-child { margin-right: 0; }
        .wilayah-isi {
            display: flex;
            align-items: center;
            width: var(--f115-wilayah-strip-w);
            flex: 0 0 var(--f115-wilayah-strip-w);
        }
        .wilayah-isi .kotak-wrap {
            flex: 0 0 var(--f115-wilayah-kotak-slot-w);
            width: var(--f115-wilayah-kotak-slot-w);
            justify-content: flex-start;
        }
        .teks-box {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f115-kotak-h);
            line-height: calc(var(--f115-kotak-h) - 2px);
            text-transform: uppercase;
            font-weight: bold;
            font-size: var(--f115-size);
            flex: 1 1 auto;
            min-width: var(--f115-wilayah-teks-w);
            width: var(--f115-wilayah-teks-w);
            margin-left: var(--f115-wilayah-gap);
            overflow: hidden;
            white-space: nowrap;
        }
        .row-identitas .lbl { grid-column: 1; }
        .row-identitas .dots { grid-column: 2; }
        .row-identitas .kol-isi { grid-column: 3; min-width: 0; }
        .form-row .lbl,
        .form-row .lbl-wilayah { grid-column: 1; }
        .form-row .dots { grid-column: 2; }
        .form-row .kol-isi { grid-column: 3; min-width: 0; }
        .row-identitas-lanjut {
            display: grid;
            grid-template-columns: var(--f115-label-w) var(--f115-colon-w) minmax(0, 1fr);
            margin-bottom: 2px;
        }
        .row-identitas-lanjut .kol-isi,
        .row-identitas-lanjut .alamat-grid { grid-column: 3; }
        .row-identitas-lanjut .kol-isi {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 8px;
        }
        .isi-kotak-panjang {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f115-kotak-h);
            line-height: calc(var(--f115-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f115-size);
            background: #fff;
            width: 100%;
        }
        .isi-kotak-mini {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f115-kotak-h);
            line-height: calc(var(--f115-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f115-size);
            background: #fff;
            width: 100%;
            min-width: 0;
            display: block;
            overflow: hidden;
            white-space: nowrap;
        }
        .alamat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 14px;
            font-size: var(--f115-size);
            margin-bottom: 4px;
        }
        .alamat-item {
            display: grid;
            grid-template-columns: 118px minmax(0, 1fr);
            align-items: center;
            column-gap: 4px;
        }
        .alamat-item .lbl-alamat { white-space: nowrap; }
        .alamat-item .kol-kotak-val .kotak-wrap { justify-content: flex-start; }
        .row-alasan-legend {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: wrap;
        }
        .row-alasan-legend .legend {
            font-size: var(--f115-size);
            line-height: 1.35;
        }
        .section-title {
            margin: 8px 0 4px;
            font-weight: bold;
            font-size: var(--f115-size);
        }
        .table-anggota {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: var(--f115-size);
        }
        .table-anggota th,
        .table-anggota td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
            height: calc(var(--f115-kotak-h) + 2px);
        }
        .table-anggota td.nama-isi {
            text-align: left;
            padding-left: 6px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .footer {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: var(--f115-size);
        }
        .footer td {
            vertical-align: top;
            text-align: center;
            line-height: 1.25;
            padding: 2px 5px;
            overflow: hidden;
            word-wrap: break-word;
        }
        .footer .td-tgl { text-align: center; }
        .footer-row-nama td { vertical-align: top; }
        .footer .col-ttd { width: 33%; }
        .footer .col-ttd-kanan { width: 34%; }
        .footer .col-ttd-kel-ttd .nama-pejabat-digital { margin-top: 0; }
        .f115-kel-ttd-digital {
            box-sizing: border-box;
            height: 55px;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 0;
            overflow: visible;
        }
        .f115-kel-ttd-digital img {
            max-height: 48px;
            max-width: 185px;
            width: auto;
            height: auto;
            display: block;
            margin: 0;
            transform: scale(1.85);
            transform-origin: center center;
        }
        <?= siak_alur_ttd_qr_print_css(); ?>
        .nama-pejabat {
            text-decoration: underline;
            font-weight: bold;
            display: block;
            margin-top: 55px;
            font-size: 7pt;
            line-height: 1.2;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            max-width: 100%;
        }
        .nip-pejabat {
            display: block;
            font-size: 6.5pt;
            line-height: 1.2;
            white-space: normal;
            word-wrap: break-word;
            margin-top: 2px;
        }
        .box-pemasukan {
            float: right;
            margin-top: 15px;
            border: 1px solid #000;
            padding: 5px 8px;
            width: 230px;
            font-size: var(--f115-size);
            line-height: 1.35;
        }
        .box-pemasukan .paraf-kotak {
            border: 1px solid #000;
            width: 110px;
            height: 25px;
            display: inline-block;
            vertical-align: middle;
            margin-left: 5px;
        }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <div class="f-header">F-1.15</div>
    <div class="title">FORMULIR PERMOHONAN KARTU KELUARGA [KK] BARU WARGA NEGARA INDONESIA</div>

    <div class="perhatian">
        <strong>PERHATIAN :</strong><br>
        1. Harap diisi dengan huruf cetak dan menggunakan tinta hitam<br>
        2. Setelah Formulir ini diisi dan ditandatangani, harap diserahkan kembali ke Kantor Desa/Kelurahan
    </div>

    <div class="form-row">
        <div class="lbl lbl-wilayah">PROVINSI</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('36', 2); ?><div class="teks-box">BANTEN</div></div></div>
    </div>
    <div class="form-row">
        <div class="lbl lbl-wilayah">KABUPATEN/KOTA</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('03', 2); ?><div class="teks-box">TANGERANG</div></div></div>
    </div>
    <div class="form-row">
        <div class="lbl lbl-wilayah">KECAMATAN</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('22', 2); ?><div class="teks-box">PAGEDANGAN</div></div></div>
    </div>
    <div class="form-row form-row-akhir">
        <div class="lbl lbl-wilayah">KELURAHAN/DESA</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('1004', 4); ?><div class="teks-box">MEDANG</div></div></div>
    </div>

    <div class="row-identitas">
        <div class="lbl">1. Nama Lengkap Pemohon</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo strtoupper($d['nama_pemohon'] ?? ''); ?></div></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">2. NIK Pemohon</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($d['nik_pemohon'] ?? '', 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">3. No. KK Semula</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($no_kk_cetak, 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">4. Alamat Pemohon</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo strtoupper($d['alamat'] ?? ''); ?></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="kol-isi">
            RT. <?php echo buat_kotak($d['rt'] ?? '', 3); ?>
            RW. <?php echo buat_kotak($d['rw'] ?? '', 3); ?>
        </div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="alamat-grid">
            <div class="alamat-item"><span class="lbl-alamat">a. Desa/ Kelurahan :</span><span class="isi-kotak-mini">MEDANG</span></div>
            <div class="alamat-item"><span class="lbl-alamat">b. Kecamatan :</span><span class="isi-kotak-mini">PAGEDANGAN</span></div>
            <div class="alamat-item"><span class="lbl-alamat">c. Kabupaten/ Kota :</span><span class="isi-kotak-mini">TANGERANG</span></div>
            <div class="alamat-item"><span class="lbl-alamat">d. Provinsi :</span><span class="isi-kotak-mini">BANTEN</span></div>
            <div class="alamat-item"><span class="lbl-alamat">Kode Pos :</span><span class="kol-kotak-val"><?php echo buat_kotak('15334', 5); ?></span></div>
            <div class="alamat-item"><span class="lbl-alamat">Telepon :</span><span class="kol-kotak-val"><?php echo buat_kotak('', 10); ?></span></div>
        </div>
    </div>

    <div class="row-identitas">
        <div class="lbl">5. Alasan Permohonan</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak(cek_kode_alasan_f115($d['alasan_permohonan'] ?? ''), 1); ?>
            <div class="legend">
                1. Karena Membentuk Rumah Tangga Baru &nbsp;&nbsp;&nbsp; 3. Lainnya<br>
                2. Karena Kartu Keluarga Hilang/Rusak
            </div>
        </div>
    </div>

    <div class="row-identitas">
        <div class="lbl">6. Jumlah Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi">
            <?php echo buat_kotak(str_pad((string) count($anggota_list), 2, '0', STR_PAD_LEFT), 2); ?> Orang
        </div>
    </div>

    <div class="section-title">7. DATA ANGGOTA KELUARGA PEMOHON [Hanya diisi anggota keluarga saja]</div>
    <table class="table-anggota">
        <thead>
            <tr>
                <th width="30">No.</th>
                <th width="240">NIK</th>
                <th>Nama Lengkap</th>
                <th width="40">SHDK**</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($anggota_list as $i => $ag):
                $nik = $ag['nik'] ?? '';
                $nama = $ag['nama'] ?? '';
                $shdk = sipandang_kode_shdk_formulir_kotak($ag['shdk'] ?? '');
            ?>
            <tr>
                <td><?php echo buat_kotak(sprintf('%02d', $i + 1), 2); ?></td>
                <td><?php echo buat_kotak($nik, 16); ?></td>
                <td class="nama-isi" style="font-size:7.5pt;"><?php echo htmlspecialchars(strtoupper($nama)); ?></td>
                <td><?php echo $shdk ? buat_kotak($shdk, 2) : buat_kotak('', 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td colspan="2">Mengetahui,</td>
            <td class="td-tgl col-ttd-kanan">MEDANG, <?php echo sipandang_siak_tanggal_final_ttd($koneksi, $d); ?></td>
        </tr>
        <tr>
            <td class="col-ttd"><?= sipandang_siak_ttd_lines_kec($ttd_kec ?: [], null, $koneksi); ?></td>
            <td class="col-ttd"><?= sipandang_siak_ttd_lines_kel($ttd_kel ?: [], null, $koneksi); ?></td>
            <td class="col-ttd-kanan">Pemohon</td>
        </tr>
        <tr class="footer-row-nama">
            <td class="col-ttd">
                <span class="nama-pejabat"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ttd_kec['nama_pamong'] ?? 'EKO SUYANTO, SE')); ?></span>
                <span class="nip-pejabat">NIP. <?php echo htmlspecialchars($ttd_kec['nip'] ?? '197202192006041003'); ?></span>
            </td>
            <td class="col-ttd<?= !empty($d['ttd_digital']) ? ' col-ttd-kel-ttd f115-kel-ttd-wrap' : '' ?>">
                <?php if (!empty($d['ttd_digital']) && ($ttdDig = print_f115_ttd_digital_html($koneksi, $d)) !== ''): ?>
                <?= $ttdDig ?>
                <span class="nama-pejabat nama-pejabat-digital"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ttd_kel['nama_pamong'] ?? 'ETI SUHARYATI, A.Md')); ?></span>
                <?php else: ?>
                <span class="nama-pejabat"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ttd_kel['nama_pamong'] ?? 'ETI SUHARYATI, A.Md')); ?></span>
                <?php endif; ?>
                <span class="nip-pejabat">NIP. <?php echo htmlspecialchars($ttd_kel['nip'] ?? '19707212007012008'); ?></span>
            </td>
            <td class="col-ttd-kanan">
                <span class="nama-pejabat"><?php echo htmlspecialchars(strtoupper($d['nama_pemohon'] ?? '')); ?></span>
            </td>
        </tr>
    </table>

    <div class="box-pemasukan">
        Tanggal Pemasukan Data<br>
        Tgl. <?php echo buat_kotak('', 2); ?> Bln. <?php echo buat_kotak('', 2); ?> Thn. <?php echo buat_kotak('', 2); ?><br>
        Paraf Petugas <span class="paraf-kotak"></span>
    </div>

<?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

</body>
</html>
