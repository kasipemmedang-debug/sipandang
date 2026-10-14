<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_siak_ttd.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
require_once __DIR__ . '/inc_siak_preview.php';

$id = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');

$id_anggota_pilih = $_GET['id_anggota'] ?? [];
if (!is_array($id_anggota_pilih)) {
    $id_anggota_pilih = [$id_anggota_pilih];
}

if (!empty($id_anggota_pilih)) {
    $ids = implode(',', array_map('intval', $id_anggota_pilih));
    $q_anggota_filter = "SELECT * FROM siak_anggota WHERE id_permohonan = '$id' AND id_anggota IN ($ids) ORDER BY id_anggota ASC";
} else {
    $q_anggota_filter = "SELECT * FROM siak_anggota WHERE id_permohonan = '$id' ORDER BY " . sipandang_sql_order_siak_anggota();
}

$query = mysqli_query($koneksi, "SELECT * FROM permohonan_siak WHERE id_siak = '$id' LIMIT 1");
$data = mysqli_fetch_assoc($query);
if (!$data) {
    die('Data tidak ditemukan di database!');
}

$d_alamat_asal = trim((string) ($data['alamat_asal'] ?? ''));
$d_rt_asal     = trim((string) ($data['rt_asal'] ?? ''));
$d_rw_asal     = trim((string) ($data['rw_asal'] ?? ''));

$alamat_tujuan = trim((string) ($data['alamat'] ?? ''));
$rt_tujuan     = trim((string) ($data['rt'] ?? ''));
$rw_tujuan     = trim((string) ($data['rw'] ?? ''));

$tanggal_db = sipandang_siak_tanggal_final_raw($koneksi, $data);

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

/** Kotak teks wilayah — satu baris; font mengecil otomatis jika nama panjang */
function f139_isi_kotak_wilayah(string $teks): string {
    return sipandang_isi_kotak_wilayah($teks);
}

$qAng = mysqli_query($koneksi, $q_anggota_filter);
$anggota_terpilih = [];
while ($a = mysqli_fetch_assoc($qAng)) {
    $anggota_terpilih[] = $a;
}
if (empty($anggota_terpilih)) {
    die('Tidak ada anggota yang dipilih untuk dicetak.');
}

$kepala_kk_tujuan = sipandang_siak_kepala_kk_tujuan_dari_anggota($anggota_terpilih, $data);

$ang0 = $anggota_terpilih[0];
$no_kk_asal = !empty($ang0['no_kk_asal']) ? $ang0['no_kk_asal'] : ($data['no_kk_asal'] ?? '');
$nama_kk    = !empty($ang0['nama_kk_asal']) ? $ang0['nama_kk_asal'] : ($data['nama_kk_asal'] ?? '');
$alamat_p   = !empty($ang0['alamat_asal']) ? $ang0['alamat_asal'] : ($d_alamat_asal !== '' ? $d_alamat_asal : '');
$rt_p       = !empty($ang0['rt_asal']) ? $ang0['rt_asal'] : $d_rt_asal;
$rw_p       = !empty($ang0['rw_asal']) ? $ang0['rw_asal'] : $d_rw_asal;
$kel_p      = !empty($ang0['kelurahan_asal']) ? $ang0['kelurahan_asal'] : ($data['kelurahan_asal'] ?? '');
$kec_p      = !empty($ang0['kecamatan_asal']) ? $ang0['kecamatan_asal'] : ($data['kecamatan_asal'] ?? '');
$kab_p      = !empty($ang0['kabupaten_asal']) ? $ang0['kabupaten_asal'] : ($data['kabupaten_asal'] ?? '');
$prov_p     = !empty($ang0['provinsi_asal']) ? $ang0['provinsi_asal'] : ($data['provinsi_asal'] ?? '');

$nomor_surat = trim((string) ($data['nomor_surat'] ?? ''));
$status_kk_pindah = trim((string) ($data['status_kk_pindah'] ?? '2'));
if ($status_kk_pindah === '') {
    $status_kk_pindah = '2';
}

$nama_pemohon_ttd = strtoupper(trim((string) ($ang0['nama_lengkap'] ?? $ang0['nama'] ?? $data['nama_pemohon'] ?? '')));
$garis_ttd = '....................................';
$garis_nip = '....................................';
$profil_instansi = sipandang_siak_profil_instansi($koneksi);
$label_camat_f139 = 'CAMAT ' . htmlspecialchars($profil_instansi['nama_kecamatan'], ENT_QUOTES, 'UTF-8');
$nik_asal = $ang0['nik'] ?? $data['nik_pemohon'] ?? '';
$nama_lengkap_asal = strtoupper(trim((string) ($ang0['nama_lengkap'] ?? $ang0['nama'] ?? '')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak F-1.39</title>
    <style>
        :root {
            --f139-font: Arial, Helvetica, sans-serif;
            --f139-size: 9pt;
            --f139-kotak-w: 14px;
            --f139-kotak-h: 18px;
            --f139-label-w: 185px;
            --f139-colon-w: 12px;
            --f139-wilayah-kotak-max: 4;
            --f139-wilayah-gap: 5px;
            --f139-wilayah-teks-w: 220px;
            --f139-wilayah-kotak-slot-w: calc(
                var(--f139-wilayah-kotak-max) * var(--f139-kotak-w) - (var(--f139-wilayah-kotak-max) - 1) * 1px
            );
            --f139-wilayah-strip-w: calc(
                var(--f139-wilayah-kotak-slot-w) + var(--f139-wilayah-gap) + var(--f139-wilayah-teks-w)
            );
        }
        * { box-sizing: border-box; }
        body {
            font-family: var(--f139-font);
            font-size: var(--f139-size);
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
            font-size: var(--f139-size);
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
        .title-nomor { font-size: var(--f139-size); font-weight: bold; margin-top: 4px; }
        .form-row.form-row-akhir { margin-bottom: 12px; }
        .form-row,
        .row-identitas {
            display: grid;
            grid-template-columns: var(--f139-label-w) var(--f139-colon-w) minmax(0, 1fr);
            align-items: center;
            column-gap: 0;
            margin-bottom: 2px;
            font-size: var(--f139-size);
        }
        .lbl {
            width: var(--f139-label-w);
            padding: 1px 0;
            line-height: 1.25;
        }
        .lbl-wilayah { text-transform: uppercase; }
        .dots {
            width: var(--f139-colon-w);
            text-align: center;
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
            grid-template-columns: var(--f139-label-w) var(--f139-colon-w) minmax(0, 1fr);
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
            width: var(--f139-kotak-w);
            height: var(--f139-kotak-h);
            border: 1px solid #000;
            margin-right: -1px;
            text-align: center;
            line-height: var(--f139-kotak-h);
            font-family: var(--f139-font);
            font-weight: bold;
            font-size: var(--f139-size);
            background: #fff;
        }
        .kotak-karakter:last-child { margin-right: 0; }
        .wilayah-isi {
            display: flex;
            align-items: center;
            width: var(--f139-wilayah-strip-w);
            flex: 0 0 var(--f139-wilayah-strip-w);
        }
        .wilayah-isi .kotak-wrap {
            flex: 0 0 var(--f139-wilayah-kotak-slot-w);
            width: var(--f139-wilayah-kotak-slot-w);
            justify-content: flex-start;
        }
        .teks-box {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f139-kotak-h);
            line-height: calc(var(--f139-kotak-h) - 2px);
            text-transform: uppercase;
            font-weight: bold;
            font-size: var(--f139-size);
            flex: 1 1 auto;
            min-width: var(--f139-wilayah-teks-w);
            width: var(--f139-wilayah-teks-w);
            margin-left: var(--f139-wilayah-gap);
            overflow: hidden;
            white-space: nowrap;
        }
        .isi-kotak-panjang {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f139-kotak-h);
            line-height: calc(var(--f139-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f139-size);
            background: #fff;
            width: 100%;
        }
        .isi-kotak-mini {
            border: 1px solid #000;
            padding: 0 4px 0 3px;
            height: var(--f139-kotak-h);
            line-height: calc(var(--f139-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f139-size);
            background: #fff;
            width: 100%;
            min-width: 0;
            display: block;
            overflow: hidden;
            white-space: nowrap;
        }
        .alamat-grid {
            display: grid;
            grid-template-columns: 118px minmax(0, 1fr) 118px minmax(0, 1fr);
            gap: 4px 10px;
            align-items: center;
            font-size: var(--f139-size);
            margin-bottom: 4px;
        }
        .alamat-grid .lbl-alamat {
            white-space: nowrap;
            font-size: 8.5pt;
        }
        .alamat-grid .kol-kotak-val .kotak-wrap { justify-content: flex-start; }
        .row-alasan-legend {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: wrap;
        }
        .row-alasan-legend .legend {
            font-size: var(--f139-size);
            line-height: 1.35;
        }
        .section-title {
            margin: 10px 0 4px;
            font-weight: bold;
            font-size: var(--f139-size);
            text-transform: uppercase;
        }
        .table-anggota {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: var(--f139-size);
        }
        .table-anggota th,
        .table-anggota td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
            height: calc(var(--f139-kotak-h) + 2px);
        }
        .table-anggota td.nama-isi {
            text-align: left;
            padding-left: 6px;
            text-transform: uppercase;
            font-weight: bold;
        }
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
        .footer {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: var(--f139-size);
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
        .footer .col-ttd-kiri { width: 38%; text-align: center; word-wrap: normal; overflow-wrap: normal; overflow: visible; }
        .footer .col-ttd-kanan { width: 34%; }
        .col-ttd-kiri .garis-ttd { text-align: center; margin-left: auto; margin-right: auto; }
        .garis-ttd {
            display: block;
            margin-top: 55px;
            font-size: 7pt;
            line-height: 1.2;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            max-width: 100%;
        }
        .keterangan-form {
            margin-top: 24px;
            font-size: 7pt;
            line-height: 1.3;
            text-align: left;
        }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <div class="f-header">F-1.39</div>

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
        <div class="form-row form-row-akhir">
            <div class="lbl lbl-wilayah">KELURAHAN/DESA</div>
            <div class="dots">:</div>
            <div class="kol-isi"><div class="wilayah-isi"><?php echo buat_kotak('1004', 4); ?><div class="teks-box">MEDANG</div></div></div>
        </div>
    </div>

    <div class="title">
        SURAT KETERANGAN PINDAH DATANG WNI
        <div class="title-nomor">
            Nomor :
            <?php echo $nomor_surat !== '' ? htmlspecialchars($nomor_surat) : '............................................................................'; ?>
        </div>
    </div>

    <div class="section-title">Data Daerah Asal</div>

    <div class="row-identitas">
        <div class="lbl">1. Nomor Kartu Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($no_kk_asal, 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">2. Nama Kepala Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($nama_kk)); ?></div></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">3. Alamat</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($alamat_p)); ?></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="kol-isi">
            RT. <?php echo buat_kotak($rt_p, 3); ?>
            RW. <?php echo buat_kotak($rw_p, 3); ?>
        </div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="alamat-grid">
            <span class="lbl-alamat">a. Desa/ Kelurahan :</span><?php echo f139_isi_kotak_wilayah($kel_p); ?>
            <span class="lbl-alamat">b. Kecamatan :</span><?php echo f139_isi_kotak_wilayah($kec_p); ?>
            <span class="lbl-alamat">c. Kabupaten/ Kota :</span><?php echo f139_isi_kotak_wilayah($kab_p); ?>
            <span class="lbl-alamat">d. Provinsi :</span><?php echo f139_isi_kotak_wilayah($prov_p); ?>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">4. NIK Pemohon</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($nik_asal, 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">5. Nama Lengkap</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars($nama_lengkap_asal); ?></div></div>
    </div>

    <div class="section-title">Data Daerah Tujuan</div>

    <div class="row-identitas">
        <div class="lbl">1. Status Nomor KK Pindah</div>
        <div class="dots">:</div>
        <div class="kol-isi row-alasan-legend">
            <?php echo buat_kotak($status_kk_pindah, 1); ?>
            <div class="legend">
                1. Numpang KK &nbsp;&nbsp;&nbsp; 2. Membuat KK Baru &nbsp;&nbsp;&nbsp; 3. Nomor KK Tetap
            </div>
        </div>
    </div>
    <div class="row-identitas">
        <div class="lbl">2. Nomor Kartu Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($data['no_kk_lama'] ?? '', 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">3. NIK Kepala Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak($kepala_kk_tujuan['nik'], 16); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">4. Nama Kepala Keluarga</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars($kepala_kk_tujuan['nama']); ?></div></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">5. Tanggal Kedatangan</div>
        <div class="dots">:</div>
        <div class="kol-isi"><?php echo buat_kotak(date('dmY', strtotime($tanggal_db)), 8); ?></div>
    </div>
    <div class="row-identitas">
        <div class="lbl">6. Alamat</div>
        <div class="dots">:</div>
        <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo htmlspecialchars(strtoupper($alamat_tujuan)); ?></div></div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="kol-isi">
            RT. <?php echo buat_kotak($rt_tujuan, 3); ?>
            RW. <?php echo buat_kotak($rw_tujuan, 3); ?>
        </div>
    </div>
    <div class="row-identitas-lanjut">
        <div class="alamat-grid">
            <span class="lbl-alamat">a. Desa/ Kelurahan :</span><span class="isi-kotak-mini">MEDANG</span>
            <span class="lbl-alamat">b. Kecamatan :</span><span class="isi-kotak-mini">PAGEDANGAN</span>
            <span class="lbl-alamat">c. Kabupaten/ Kota :</span><span class="isi-kotak-mini">TANGERANG</span>
            <span class="lbl-alamat">d. Provinsi :</span><span class="isi-kotak-mini">BANTEN</span>
            <span class="lbl-alamat">Kode Pos :</span><span class="kol-kotak-val"><?php echo buat_kotak($data['kodepos'] ?? '15334', 5); ?></span>
        </div>
    </div>

    <div class="section-title">7. Keluarga Yang Datang</div>
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
            <?php foreach ($anggota_terpilih as $i => $a):
                $shdk = sipandang_kode_shdk_formulir_kotak($a['shdk'] ?? '');
            ?>
            <tr>
                <td><?php echo buat_kotak(sprintf('%02d', $i + 1), 2); ?></td>
                <td><?php echo buat_kotak($a['nik'] ?? '', 16); ?></td>
                <td class="nama-isi" style="font-size:7.5pt;"><?php echo htmlspecialchars(strtoupper($a['nama_lengkap'] ?? $a['nama'] ?? '')); ?></td>
                <td><?php echo $shdk ? buat_kotak($shdk, 2) : buat_kotak('', 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td colspan="2"></td>
            <td class="td-tgl col-ttd-kanan">MEDANG, <?php echo date('d-m-Y', strtotime($tanggal_db)); ?></td>
        </tr>
        <tr>
            <td class="col-ttd">Petugas Registrasi,</td>
            <td class="col-ttd">Mengetahui,<br><?php echo $label_camat_f139; ?></td>
            <td class="col-ttd-kanan">Pemohon,</td>
        </tr>
        <tr class="footer-row-nama">
            <td class="col-ttd col-ttd-kiri">
                <span class="garis-ttd"><?php echo htmlspecialchars($garis_ttd); ?></span>
                <div class="keterangan-form">
                    <b>Keterangan:</b><br>
                    *) Diisi oleh petugas
                </div>
            </td>
            <td class="col-ttd">
                <span class="garis-ttd"><?php echo htmlspecialchars($garis_ttd); ?></span>
                <span class="nip-pejabat">NIP. <?php echo htmlspecialchars($garis_nip); ?></span>
            </td>
            <td class="col-ttd-kanan">
                <span class="nama-pejabat"><?php echo htmlspecialchars($nama_pemohon_ttd); ?></span>
            </td>
        </tr>
    </table>

<?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

</body>
</html>
