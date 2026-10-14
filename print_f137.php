<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
require_once __DIR__ . '/inc_siak_preview.php';

$id = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');

$query = mysqli_query($koneksi, "
    SELECT
        p.*,
        d.nama_lengkap AS nama_master,
        d.alamat AS alamat_master
    FROM permohonan_siak p
    LEFT JOIN tbl_penduduk d ON p.nik_pemohon = d.nik
    WHERE p.id_siak = '$id'
    LIMIT 1
");
$d = mysqli_fetch_assoc($query);
if (!$d) {
    die('Data tidak ditemukan');
}

$nama_final     = !empty($d['nama_pemohon']) ? $d['nama_pemohon'] : ($d['nama_master'] ?? '');
$nama_kk_tampil = sipandang_siak_nama_kepala_keluarga($koneksi, $d);
if ($nama_kk_tampil === '') {
    $nama_kk_tampil = $nama_final;
}
$asal_keluar   = sipandang_siak_alamat_asal_keluar_cetak($d);
$tujuan_keluar = sipandang_siak_alamat_tujuan_keluar_cetak($koneksi, $d);
$nomor_surat   = trim((string) ($d['nomor_surat'] ?? ''));
$tanggal_db    = sipandang_siak_tanggal_final_raw($koneksi, $d);
$no_kk_cetak   = preg_replace('/\D/', '', (string) ($d['no_kk_lama'] ?? $d['no_kk_asal'] ?? ''));
if (strlen($no_kk_cetak) > 16) {
    $no_kk_cetak = substr($no_kk_cetak, 0, 16);
}

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak F-1.37</title>
    <style>
        :root {
            --f137-font: Arial, Helvetica, sans-serif;
            --f137-size: 9pt;
            --f137-kotak-w: 14px;
            --f137-kotak-h: 18px;
            --f137-label-w: 185px;
            --f137-colon-w: 12px;
            --f137-wilayah-kotak-max: 4;
            --f137-wilayah-gap: 5px;
            --f137-wilayah-teks-w: 220px;
            --f137-wilayah-kotak-slot-w: calc(
                var(--f137-wilayah-kotak-max) * var(--f137-kotak-w) - (var(--f137-wilayah-kotak-max) - 1) * 1px
            );
            --f137-wilayah-strip-w: calc(
                var(--f137-wilayah-kotak-slot-w) + var(--f137-wilayah-gap) + var(--f137-wilayah-teks-w)
            );
        }
        * { box-sizing: border-box; }
        body {
            font-family: var(--f137-font);
            font-size: var(--f137-size);
            margin: 0;
            padding: 15px;
            color: #000;
            line-height: 1.25;
        }
        @media print {
            body { padding: 0; }
            @page { size: A4 portrait; margin: 10mm; }
            .no-print { display: none; }
        }
        .f-header {
            border: 1px solid #000;
            padding: 4px 12px;
            font-weight: bold;
            font-size: var(--f137-size);
            float: right;
        }
        .blok-wilayah {
            clear: both;
            width: 62%;
            max-width: 430px;
            padding-top: 2px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            clear: both;
            margin: 8px 0 10px;
            line-height: 1.3;
        }
        .title-sub { font-size: var(--f137-size); font-weight: bold; margin-top: 2px; }
        .title-nomor { font-size: var(--f137-size); font-weight: bold; margin-top: 4px; }
        .form-row.form-row-akhir { margin-bottom: 12px; }
        .form-row,
        .row-identitas {
            display: grid;
            grid-template-columns: var(--f137-label-w) var(--f137-colon-w) minmax(0, 1fr);
            align-items: center;
            column-gap: 0;
            margin-bottom: 2px;
            font-size: var(--f137-size);
        }
        .lbl {
            width: var(--f137-label-w);
            padding: 1px 0;
            line-height: 1.25;
        }
        .lbl-wilayah { text-transform: uppercase; }
        .lbl-kecil {
            font-size: 8pt;
            white-space: nowrap;
            letter-spacing: -0.15px;
        }
        .dots {
            width: var(--f137-colon-w);
            text-align: center;
        }
        .row-identitas .lbl { grid-column: 1; }
        .row-identitas .dots { grid-column: 2; }
        .row-identitas .kol-isi { grid-column: 3; min-width: 0; }
        .row-identitas.row-pilihan { align-items: start; }
        .row-identitas.row-pilihan .dots { padding-top: 1px; }
        .form-row .lbl,
        .form-row .lbl-wilayah { grid-column: 1; }
        .form-row .dots { grid-column: 2; }
        .form-row .kol-isi { grid-column: 3; min-width: 0; }
        .row-identitas-lanjut {
            display: grid;
            grid-template-columns: var(--f137-label-w) var(--f137-colon-w) minmax(0, 1fr);
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
        .kotak-wrap {
            display: inline-flex;
            vertical-align: middle;
            align-items: stretch;
        }
        .kotak-karakter {
            width: var(--f137-kotak-w);
            height: var(--f137-kotak-h);
            border: 1px solid #000;
            margin-right: -1px;
            text-align: center;
            line-height: var(--f137-kotak-h);
            font-family: var(--f137-font);
            font-weight: bold;
            font-size: var(--f137-size);
            background: #fff;
        }
        .kotak-karakter:last-child { margin-right: 0; }
        .wilayah-isi {
            display: flex;
            align-items: center;
            width: var(--f137-wilayah-strip-w);
            flex: 0 0 var(--f137-wilayah-strip-w);
        }
        .wilayah-isi .kotak-wrap {
            flex: 0 0 var(--f137-wilayah-kotak-slot-w);
            width: var(--f137-wilayah-kotak-slot-w);
            justify-content: flex-start;
        }
        .teks-box {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f137-kotak-h);
            line-height: calc(var(--f137-kotak-h) - 2px);
            text-transform: uppercase;
            font-weight: bold;
            font-size: var(--f137-size);
            flex: 1 1 auto;
            min-width: var(--f137-wilayah-teks-w);
            width: var(--f137-wilayah-teks-w);
            margin-left: var(--f137-wilayah-gap);
            overflow: hidden;
            white-space: nowrap;
        }
        .isi-kotak-panjang {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f137-kotak-h);
            line-height: calc(var(--f137-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f137-size);
            background: #fff;
            width: 100%;
        }
        .isi-kotak-mini {
            border: 1px solid #000;
            padding: 0 4px 0 3px;
            height: var(--f137-kotak-h);
            line-height: calc(var(--f137-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            background: #fff;
            width: 100%;
            min-width: 0;
            display: block;
            overflow: hidden;
            white-space: nowrap;
        }
        .alamat-grid {
            display: grid;
            grid-template-columns: 96px minmax(0, 1fr) 96px minmax(0, 1fr);
            gap: 4px 8px;
            align-items: center;
            font-size: var(--f137-size);
            margin-bottom: 4px;
        }
        .alamat-grid .lbl-alamat {
            white-space: nowrap;
            font-size: 8pt;
        }
        .alamat-grid .kol-kotak-val .kotak-wrap { justify-content: flex-start; }
        .row-alasan-legend {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: nowrap;
        }
        .row-alasan-legend .kotak-wrap {
            flex: 0 0 auto;
            margin-top: 0;
        }
        .row-alasan-legend .legend {
            font-size: var(--f137-size);
            line-height: 1.35;
            min-width: 0;
        }
        .legend-jenis {
            font-size: 8pt;
            line-height: 1.35;
        }
        .legend-jenis .legend-baris { display: block; }
        .legend-jenis .legend-baris-2 { margin-top: 1px; }
        .section-title {
            margin: 10px 0 4px;
            font-weight: bold;
            font-size: var(--f137-size);
            text-transform: uppercase;
        }
        .table-anggota {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: var(--f137-size);
        }
        .table-anggota th,
        .table-anggota td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
            height: calc(var(--f137-kotak-h) + 2px);
        }
        .table-anggota td.nama-isi {
            text-align: left;
            padding-left: 6px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .wrapper { max-width: 760px; margin: 0 auto; }
        .kop-dinas {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .logo-kab { width: 60px; height: auto; margin-right: 15px; }
        .kop-text { text-align: center; flex: 1; margin-right: 60px; }
        .kop-text h2 { margin: 0; font-size: 14pt; text-transform: uppercase; letter-spacing: 1px; }
        .kop-text h1 { margin: 0; font-size: 16pt; text-transform: uppercase; letter-spacing: 1px; }
        .kop-text p { margin: 2px 0; font-size: 9pt; font-weight: normal; }
        .footer-surat { margin-top: 20px; width: 100%; overflow: hidden; }
        .ttd-box-right { float: right; width: 350px; text-align: center; font-size: var(--f137-size); line-height: 1.35; }
        .keterangan { margin-top: 12px; clear: both; font-size: 7pt; line-height: 1.3; margin-bottom: 0; }
        .wrapper .sipandang-qr-footer { margin-top: 6px; }
        .no-print { text-align: center; margin-top: 20px; }
        .no-print button {
            padding: 8px 20px;
            font-size: 12px;
            cursor: pointer;
        }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

<div class="wrapper">

    <div class="kop-dinas">
        <img src="assets/logokab.png" class="logo-kab" alt="">
        <div class="kop-text">
            <h2>PEMERINTAH KABUPATEN TANGERANG</h2>
            <h1>DINAS KEPENDUDUKAN &amp; PENCATATAN SIPIL</h1>
            <p>Gedung Usaha Daerah Komplek Perkantoran Tigaraksa Kabupaten Tangerang</p>
        </div>
    </div>
    <div class="f-header">F-1.37</div>

    <div class="blok-wilayah">
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
        <div class="form-row">
            <div class="lbl lbl-wilayah">KELURAHAN/DESA</div>
            <div class="dots">:</div>
            <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('1004', 4); ?><div class="teks-box">MEDANG</div></div></div>
        </div>
        <div class="form-row form-row-akhir">
            <div class="lbl lbl-wilayah">DUSUN/DUKUH/KAMPUNG</div>
            <div class="dots">:</div>
            <div class="kol-isi"><div class="isi-kotak-panjang"></div></div>
        </div>
    </div>

    <div class="title">
        FORMULIR PERMOHONAN PINDAH WNI
        <div class="title-sub">Antar Kabupaten/Kota atau Antar Provinsi</div>
        <div class="title-nomor">
            Nomor : SKPWNI/ <?php echo $nomor_surat !== '' ? htmlspecialchars($nomor_surat) : '....................................................................................'; ?>
        </div>
    </div>

    <div class="section-title">Data Daerah Asal</div>

    <div class="row-identitas">
        <div class="lbl">1. Nomor Kartu Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($no_kk_cetak, 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">2. Nama Kepala Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($nama_kk_tampil)); ?></div></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">3. Alamat</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($asal_keluar['alamat'])); ?></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="kol-isi">
            RT. <?php echo buat_kotak($asal_keluar['rt'], 3); ?>
            RW. <?php echo buat_kotak($asal_keluar['rw'], 3); ?>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">Dusun/Dukuh/Kampung</div>
        <div class="dots"></div>
        <div class="kol-isi"><div class="isi-kotak-panjang"></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="alamat-grid">
            <span class="lbl-alamat">a. Desa/ Kelurahan :</span><?php echo sipandang_isi_kotak_wilayah('MEDANG'); ?>
            <span class="lbl-alamat">b. Kecamatan :</span><?php echo sipandang_isi_kotak_wilayah('PAGEDANGAN'); ?>
            <span class="lbl-alamat">c. Kabupaten/ Kota :</span><?php echo sipandang_isi_kotak_wilayah('TANGERANG'); ?>
            <span class="lbl-alamat">d. Provinsi :</span><?php echo sipandang_isi_kotak_wilayah('BANTEN'); ?>
            <span class="lbl-alamat">Kode Pos :</span><span class="kol-kotak-val"><?php echo buat_kotak($asal_keluar['kodepos'], 5); ?></span>
            <span class="lbl-alamat">Telepon :</span><span class="kol-kotak-val"><?php echo buat_kotak('', 10); ?></span>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">4. NIK Pemohon</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($d['nik_pemohon'] ?? '', 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">Nama Lengkap</div>
        <div class="dots"></div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($nama_final)); ?></div></div>
    </div>

    <div class="section-title">Data Kepindahan</div>

    <div class="row-identitas row-pilihan">
        <div class="lbl">1. Alasan Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak($d['kode_alasan'] ?? '7', 1); ?>
            <div class="legend">
                1. Pekerjaan &nbsp; 2. Pendidikan &nbsp; 3. Keamanan &nbsp; 4. Kesehatan &nbsp; 5. Perumahan &nbsp; 6. Keluarga &nbsp; 7. Lainnya
            </div>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">2. Alamat Tujuan Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($tujuan_keluar['alamat'])); ?></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="kol-isi">
            RT. <?php echo buat_kotak($tujuan_keluar['rt'], 3); ?>
            RW. <?php echo buat_kotak($tujuan_keluar['rw'], 3); ?>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">Dusun/Dukuh/Kampung</div>
        <div class="dots"></div>
        <div class="kol-isi"><div class="isi-kotak-panjang"></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="alamat-grid">
            <span class="lbl-alamat">a. Desa/ Kelurahan :</span><?php echo sipandang_isi_kotak_wilayah($tujuan_keluar['kelurahan'] ?? ''); ?>
            <span class="lbl-alamat">b. Kecamatan :</span><?php echo sipandang_isi_kotak_wilayah($tujuan_keluar['kecamatan'] ?? ''); ?>
            <span class="lbl-alamat">c. Kabupaten/ Kota :</span><?php echo sipandang_isi_kotak_wilayah($tujuan_keluar['kabupaten'] ?? ''); ?>
            <span class="lbl-alamat">d. Provinsi :</span><?php echo sipandang_isi_kotak_wilayah($tujuan_keluar['provinsi'] ?? ''); ?>
            <span class="lbl-alamat">Kode Pos :</span><span class="kol-kotak-val"><?php echo buat_kotak('', 5); ?></span>
            <span class="lbl-alamat">Telepon :</span><span class="kol-kotak-val"><?php echo buat_kotak('', 10); ?></span>
        </div>
    </div>
    <div class="row-identitas row-pilihan">
        <div class="lbl">3. Jenis Kepindahan</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak($d['jenis_kepindahan'] ?? '4', 1); ?>
            <div class="legend legend-jenis">
                <span class="legend-baris">1. Kepindahan Keluarga &nbsp; 2. Kep. Keluarga dan Seluruh Angg. &nbsp; 3. Kep. Keluarga dan sbg Angg.</span>
                <span class="legend-baris legend-baris-2">4. Angg. Keluarga</span>
            </div>
        </div>
    </div>
    <div class="row-identitas row-pilihan">
        <div class="lbl lbl-kecil">4. Status KK Bagi yang tidak Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak($d['status_kk_tidak_pindah'] ?? '3', 1); ?>
            <div class="legend">1. Numpang KK &nbsp;&nbsp; 2. Membuat KK Baru &nbsp;&nbsp; 3. Nomor KK Tetap</div>
        </div>
    </div>
    <div class="row-identitas row-pilihan">
        <div class="lbl lbl-kecil">5. Status KK Bagi yang Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak($d['status_kk_pindah'] ?? '2', 1); ?>
            <div class="legend">1. Numpang KK &nbsp;&nbsp; 2. Membuat KK Baru &nbsp;&nbsp; 3. Nomor KK Tetap</div>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">6. Keluarga Yang Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi"></div>
    </div>

    <table class="table-anggota">
        <thead>
            <tr>
                <th width="30">No.</th>
                <th width="240">NIK</th>
                <th>Nama Lengkap</th>
                <th width="90">Masa Berlaku KTP S/D</th>
                <th width="40">SHDK**</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $ordAng = sipandang_sql_order_siak_anggota();
            $anggota = mysqli_query($koneksi, "SELECT * FROM siak_anggota WHERE id_permohonan='$id' ORDER BY $ordAng");
            while ($ag = mysqli_fetch_assoc($anggota)):
                $shdk = sipandang_kode_shdk_formulir_kotak($ag['shdk'] ?? '');
            ?>
            <tr>
                <td><?php echo buat_kotak(sprintf('%02d', $no), 2); ?></td>
                <td><?php echo buat_kotak($ag['nik'] ?? '', 16); ?></td>
                <td class="nama-isi" style="font-size:7.5pt;"><?php echo htmlspecialchars(sipandang_siak_nama_anggota_cetak($d, $ag)); ?></td>
                <td><?php echo buat_kotak('', 8); ?></td>
                <td><?php echo $shdk ? buat_kotak($shdk, 2) : buat_kotak('', 2); ?></td>
            </tr>
            <?php $no++; endwhile; ?>
        </tbody>
    </table>

    <div class="footer-surat">
        <div class="ttd-box-right">
            MEDANG, <?php echo date('d-m-Y', strtotime($tanggal_db)); ?><br>
            Dikeluarkan Oleh<br>
            Kepala Dinas Kependudukan dan Pencatatan Sipil<br>
            Kab. Tangerang
            <br><br><br><br><br><br><br>
            __________________________________________<br>
            NIP. ............................................................
        </div>
    </div>

    <div class="keterangan">
        <b>Keterangan :</b><br>
        *) Diisi Oleh Petugas<br>
    </div>

<?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

</div>

<div class="no-print">
    <button onclick="window.print()">CETAK</button>
</div>

</body>
</html>
