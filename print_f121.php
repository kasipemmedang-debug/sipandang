<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_nomor_surat.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_siak_ttd.php';
require_once __DIR__ . '/inc_siak_alur_ttd.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
siak_alur_ttd_ensure_schema($koneksi);

/** F-1.21 — overlay TTD digital Kel saja (basah tidak memakai fungsi ini). */
function print_f121_ttd_digital_html(mysqli $db, array $data): string
{
    return siak_alur_ttd_kel_form_ttd_html($db, $data, 'f121-kel-ttd-digital', 58);
}

$id_siak = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');
$id_anggota_array = $_GET['id_anggota'] ?? [];
if (!is_array($id_anggota_array)) {
    $id_anggota_array = [$id_anggota_array];
}

if (empty($id_anggota_array) && !empty($id_siak)) {
    $q_check_pemohon = mysqli_query($koneksi, "SELECT id_anggota FROM siak_anggota WHERE id_permohonan = '$id_siak' LIMIT 1");
    $res_pemohon = mysqli_fetch_assoc($q_check_pemohon);
    if ($res_pemohon) {
        $id_anggota_array = [$res_pemohon['id_anggota']];
    }
}

if (empty($id_siak) || empty($id_anggota_array)) {
    echo "<script>alert('Data tidak ditemukan!'); window.close();</script>";
    exit;
}

$query_utama = mysqli_query($koneksi, "SELECT * FROM permohonan_siak WHERE id_siak = '$id_siak'");
$d = mysqli_fetch_assoc($query_utama);

if (!$d) {
    echo "<script>alert('Data permohonan tidak ditemukan!'); window.close();</script>";
    exit;
}
$tanggal_ttd = sipandang_siak_tanggal_final_ttd($koneksi, $d);

$jl_f121 = strtoupper(trim((string) ($d['jenis_layanan'] ?? '')));
$is_f121_pindah_masuk = ($jl_f121 === 'PINDAH_MASUK');
$is_f121_kk_penggantian = in_array($jl_f121, ['KK_BARU', 'KK_PERUBAHAN', 'KK_HILANG_RUSAK'], true);

// Bagian nomor urut dikosongkan untuk ditulis tangan di formulir
$bln_reg = sipandang_bulan_romawi((int) date('n'));
$thn_reg = date('Y');

$id_kel = $d['nama_ttd_kel'] ?? '';
$ttd_kel = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kel'"));
if (!$ttd_kel) {
    $ttd_kel = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE jabatan LIKE '%KASI PEMERINTAHAN%' LIMIT 1"));
}

$id_kec = $d['nama_ttd_kec'] ?? '';
$ttd_kec = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_kec'"));
if (!$ttd_kec) {
    $ttd_kec = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE jabatan LIKE '%KASI PELAYANAN%' LIMIT 1"));
}

function buat_kotak($string, $jumlah_kotak) {
    $string_bersih = str_replace(' ', '', $string ?? '');
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
    <title>Cetak F-1.21 Kolektif</title>
    <style>
        :root {
            --f121-font: Arial, Helvetica, sans-serif;
            --f121-size: 9pt;
            --f121-kotak-w: 14px;
            --f121-kotak-h: 18px;
            --f121-label-w: 180px;
            --f121-indent: 200px;
            --f121-wilayah-kotak-max: 4;
            --f121-wilayah-gap: 5px;
            --f121-wilayah-teks-w: 210px;
            --f121-wilayah-kotak-slot-w: calc(
                var(--f121-wilayah-kotak-max) * var(--f121-kotak-w) - (var(--f121-wilayah-kotak-max) - 1) * 1px
            );
            --f121-wilayah-strip-w: calc(
                var(--f121-wilayah-kotak-slot-w) + var(--f121-wilayah-gap) + var(--f121-wilayah-teks-w)
            );
        }
        * { box-sizing: border-box; }
        body {
            font-family: var(--f121-font);
            font-size: var(--f121-size);
            margin: 0;
            padding: 20px;
            color: #000;
            line-height: 1.25;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            @page { size: legal; margin: 0.5cm; }
        }
        .no-print { background: #f8f9fa; padding: 10px; border-bottom: 2px solid #333; margin-bottom: 20px; text-align: center; }
        .page-wrapper { page-break-after: always; position: relative; }
        .page-wrapper:last-child { page-break-after: never; }
        .f-code {
            border: 1px solid #000;
            padding: 4px 12px;
            font-weight: bold;
            font-size: var(--f121-size);
            float: right;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin: 10px 0;
            line-height: 1.25;
            clear: both;
        }
        .no-reg-kel {
            font-size: var(--f121-size);
            font-weight: bold;
            letter-spacing: 0.2px;
        }
        .urut-manual {
            display: inline-block;
            min-width: 48px;
            border-bottom: 1px solid #000;
            vertical-align: bottom;
            margin: 0 2px;
        }
        .perhatian {
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 15px;
            font-size: var(--f121-size);
            line-height: 1.35;
        }
        .perhatian strong { display: block; margin-bottom: 2px; }
        .perhatian ol { margin: 0; padding-left: 18px; }
        .row-data {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: var(--f121-size);
        }
        /* Grid identitas: kolom isi (nama, KK, NIK, alamat) satu garis vertikal */
        .row-identitas {
            display: grid;
            grid-template-columns: var(--f121-label-w) 20px minmax(0, 1fr);
            align-items: center;
            column-gap: 0;
            margin-bottom: 4px;
            font-size: var(--f121-size);
        }
        .row-identitas .label {
            width: auto;
            flex-shrink: unset;
        }
        .row-identitas .titik {
            width: auto;
            text-align: center;
        }
        .row-identitas .kol-isi {
            min-width: 0;
        }
        .row-identitas-lanjut {
            display: grid;
            grid-template-columns: var(--f121-label-w) 20px minmax(0, 1fr);
            margin-bottom: 4px;
        }
        .row-identitas-lanjut .kol-isi,
        .row-identitas-lanjut .isi-kotak-panjang {
            grid-column: 3;
        }
        .row-identitas-lanjut .kol-isi {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 8px;
            min-width: 0;
        }
        .label {
            width: var(--f121-label-w);
            flex-shrink: 0;
            text-transform: uppercase;
        }
        .titik { width: 20px; flex-shrink: 0; }
        .kotak-wrap {
            display: inline-flex;
            vertical-align: middle;
            align-items: stretch;
        }
        .kotak-karakter {
            width: var(--f121-kotak-w);
            height: var(--f121-kotak-h);
            border: 1px solid #000;
            margin-right: -1px;
            text-align: center;
            line-height: var(--f121-kotak-h);
            font-family: var(--f121-font);
            font-weight: bold;
            font-size: var(--f121-size);
            background: #fff;
        }
        .kotak-karakter:last-child { margin-right: 0; }
        .teks-box {
            border: 1px solid #000;
            padding: 0 8px;
            min-width: 200px;
            height: var(--f121-kotak-h);
            line-height: calc(var(--f121-kotak-h) - 2px);
            text-transform: uppercase;
            font-weight: bold;
            font-size: var(--f121-size);
            display: inline-block;
            vertical-align: middle;
        }
        /* Strip provinsi–kelurahan: slot digit 4 kotak + kotak teks sejajar semua baris */
        .wilayah-isi {
            display: flex;
            align-items: center;
            width: var(--f121-wilayah-strip-w);
            flex: 0 0 var(--f121-wilayah-strip-w);
            max-width: calc(100% - var(--f121-indent));
        }
        .wilayah-isi .kotak-wrap {
            flex: 0 0 var(--f121-wilayah-kotak-slot-w);
            width: var(--f121-wilayah-kotak-slot-w);
            justify-content: flex-start;
        }
        .wilayah-isi .teks-box {
            flex: 0 0 var(--f121-wilayah-teks-w);
            width: var(--f121-wilayah-teks-w);
            min-width: var(--f121-wilayah-teks-w);
            margin-left: var(--f121-wilayah-gap);
            display: block;
        }
        .isi-kotak-panjang {
            border: 1px solid #000;
            padding: 0 6px 0 4px;
            height: var(--f121-kotak-h);
            line-height: calc(var(--f121-kotak-h) - 2px);
            font-weight: bold;
            text-transform: uppercase;
            font-size: var(--f121-size);
            background: #fff;
            box-sizing: border-box;
            width: 100%;
        }
        .row-identitas .isi-kotak-panjang {
            width: 100%;
        }
        .row-alasan {
            margin: 10px 0 10px var(--f121-indent);
            display: flex;
            flex-wrap: wrap;
            gap: 16px 20px;
            font-size: var(--f121-size);
        }
        .pilih-item { display: flex; align-items: center; gap: 5px; }
        .pilih-kotak {
            width: 16px;
            height: 16px;
            border: 1px solid #000;
            text-align: center;
            line-height: 15px;
            font-family: var(--f121-font);
            font-size: var(--f121-size);
            font-weight: bold;
            flex-shrink: 0;
            background: #fff;
        }
        .row-indent {
            margin-left: var(--f121-indent);
            font-size: var(--f121-size);
        }
        .row-indent.row-data {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 8px;
        }
        .footer-sig { width: 100%; margin-top: 15px; border-collapse: collapse; font-size: var(--f121-size); }
        .footer-sig td { border: 1px solid #000; vertical-align: top; padding: 5px; text-align: center; }
        .photo-box { width: 80px; height: 100px; border: 1px dashed #000; margin: 5px auto; }
        .ttd-container { width: 100%; margin-top: 20px; border: none; table-layout: fixed; font-size: var(--f121-size); }
        .ttd-container td { border: none !important; text-align: center; vertical-align: top; }
        .ttd-container .kol-kec { width: 42%; text-align: center; }
        .ttd-container .kol-spacer { width: 8%; }
        .ttd-container .kol-kel { width: 50%; text-align: center; }
        .ttd-blok-kec { text-align: center; line-height: 1.35; }
        .ttd-nama-pejabat {
            white-space: normal;
            display: inline-block;
            font-weight: bold;
            text-decoration: underline;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            font-size: var(--f121-size);
        }
        .ttd-nama-pemohon {
            white-space: normal;
            display: inline-block;
            font-weight: bold;
            text-decoration: underline;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            font-size: var(--f121-size);
        }
        .f121-kel-ttd-digital {
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
        .f121-kel-ttd-digital img {
            max-height: 48px;
            max-width: 235px;
            width: auto;
            height: auto;
            display: block;
            margin: 0;
            transform: scale(2.45);
            transform-origin: center center;
        }
        <?= siak_alur_ttd_qr_print_css(); ?>
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight: bold;">CETAK FORMULIR</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer;">TUTUP</button>
    </div>

    <?php foreach ($id_anggota_array as $id_ag) :
        $id_ag = mysqli_real_escape_string($koneksi, $id_ag);
        $ag = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM siak_anggota WHERE id_anggota = '$id_ag'"));
        if (!$ag) {
            continue;
        }
        if ($is_f121_pindah_masuk) {
            $als = 'A';
        } elseif ($is_f121_kk_penggantian) {
            $als = 'C';
        } else {
            $als = $d['alasan_permohonan'] ?? '';
        }
    ?>

    <div class="page-wrapper">
        <table width="100%">
            <tr>
                <td class="no-reg-kel">
                    No. Reg. Kel :
                    400.12.2.1 /
                    <span class="urut-manual">&nbsp;</span> /
                    <?= htmlspecialchars($bln_reg); ?> /
                    Kel. Mdn /
                    <?= htmlspecialchars($thn_reg); ?>
                </td>
                <td><div class="f-code">F-1.21</div></td>
            </tr>
        </table>

        <div class="title">
            FORMULIR PERMOHONAN KARTU TANDA PENDUDUK [KTP]<br>
            WARGA NEGARA INDONESIA
        </div>

        <div class="perhatian">
            <strong>PERHATIAN :</strong>
            <ol>
                <li>Harap diisi dengan huruf cetak dan menggunakan tinta hitam</li>
                <li>Untuk kolom pilihan, harap memberi tanda silang [X] pada kotak pilihan</li>
                <li>Setelah Formulir ini diisi dan ditandatangani, harap diserahkan kembali ke Kantor Desa/Kelurahan</li>
            </ol>
        </div>

        <div class="row-data"><div class="label">PROVINSI</div><div class="titik">:</div><div class="wilayah-isi"><?php echo buat_kotak('36', 2); ?><div class="teks-box">BANTEN</div></div></div>
        <div class="row-data"><div class="label">KABUPATEN/KOTA</div><div class="titik">:</div><div class="wilayah-isi"><?php echo buat_kotak('03', 2); ?><div class="teks-box">TANGERANG</div></div></div>
        <div class="row-data"><div class="label">KECAMATAN</div><div class="titik">:</div><div class="wilayah-isi"><?php echo buat_kotak('22', 2); ?><div class="teks-box">PAGEDANGAN</div></div></div>
        <div class="row-data"><div class="label">KELURAHAN/DESA</div><div class="titik">:</div><div class="wilayah-isi"><?php echo buat_kotak('1004', 4); ?><div class="teks-box">MEDANG</div></div></div>

        <div class="row-alasan">
            <div class="pilih-item">
                <span class="pilih-kotak"><?php echo ($als === 'A') ? 'X' : '&nbsp;'; ?></span> A. BARU
            </div>
            <div class="pilih-item">
                <span class="pilih-kotak"><?php echo ($als === 'B') ? 'X' : '&nbsp;'; ?></span> B. PERPANJANGAN
            </div>
            <div class="pilih-item">
                <span class="pilih-kotak"><?php echo ($als === 'C') ? 'X' : '&nbsp;'; ?></span> C. PENGGANTIAN
            </div>
        </div>

        <div class="row-identitas">
            <div class="label">1. Nama Lengkap</div>
            <div class="titik">:</div>
            <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo strtoupper($ag['nama'] ?? ''); ?></div></div>
        </div>
        <div class="row-identitas">
            <div class="label">2. No. KK</div>
            <div class="titik">:</div>
            <div class="kol-isi"><?php echo buat_kotak($d['no_kk_lama'] ?? '', 16); ?></div>
        </div>
        <div class="row-identitas">
            <div class="label">3. NIK</div>
            <div class="titik">:</div>
            <div class="kol-isi"><?php echo buat_kotak($ag['nik'] ?? '', 16); ?></div>
        </div>
        <div class="row-identitas">
            <div class="label">4. Alamat</div>
            <div class="titik">:</div>
            <div class="kol-isi"><div class="isi-kotak-panjang"><?php echo strtoupper($d['alamat'] ?? ''); ?></div></div>
        </div>
        <div class="row-identitas-lanjut">
            <div class="isi-kotak-panjang">KELURAHAN MEDANG KECAMATAN PAGEDANGAN</div>
        </div>
        <div class="row-identitas-lanjut">
            <div class="kol-isi">
                RT <?php echo buat_kotak($d['rt'] ?? '000', 3); ?>
                RW <?php echo buat_kotak($d['rw'] ?? '000', 3); ?>
                KODE POS : <?php echo buat_kotak('15334', 5); ?>
            </div>
        </div>

        <table class="footer-sig">
            <tr>
                <td width="25%">Pas Photo [2x3]<div class="photo-box"></div></td>
                <td width="25%">Cap Jempol<div style="height:100px;"></div><small>Cap Jempol/TTD</small></td>
                <td width="50%">Specimen Tanda Tangan<div style="height:100px;"></div></td>
            </tr>
        </table>

        <table class="ttd-container">
            <tr>
                <td class="kol-kec"></td>
                <td class="kol-spacer"></td>
                <td class="kol-kel">MEDANG, <?php echo $tanggal_ttd; ?></td>
            </tr>
            <tr>
                <td class="kol-kec"></td>
                <td class="kol-spacer"></td>
                <td class="kol-kel">Pemohon,</td>
            </tr>
            <tr><td colspan="3" style="height: 40px;"></td></tr>
            <tr>
                <td class="kol-kec"></td>
                <td class="kol-spacer"></td>
                <td class="kol-kel"><span class="ttd-nama-pemohon"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ag['nama'] ?? '')); ?></span></td>
            </tr>
            <tr><td colspan="3" style="height: 15px;"></td></tr>
            <tr>
                <td class="kol-kec ttd-blok-kec">
                    Mengetahui,<br>
                    <?= sipandang_siak_ttd_lines_kec($ttd_kec ?: [], null, $koneksi); ?>
                </td>
                <td class="kol-spacer"></td>
                <td class="kol-kel ttd-blok-kec">
                    <?= sipandang_siak_ttd_lines_kel($ttd_kel ?: [], null, $koneksi); ?>
                </td>
            </tr>
            <?php if (!empty($d['ttd_digital']) && ($ttdDig = print_f121_ttd_digital_html($koneksi, $d)) !== ''): ?>
            <tr class="f121-row-ttd-spacer">
                <td class="kol-kec"></td>
                <td class="kol-spacer"></td>
                <td class="kol-kel"><?= $ttdDig ?></td>
            </tr>
            <?php else: ?>
            <tr><td colspan="3" style="height: 50px;"></td></tr>
            <?php endif; ?>
            <tr>
                <td class="kol-kec">
                    <span class="ttd-nama-pejabat"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ttd_kec['nama_pamong'] ?? '')); ?></span><br>
                    NIP. <?php echo htmlspecialchars($ttd_kec['nip'] ?? '197202192006041003'); ?>
                </td>
                <td class="kol-spacer"></td>
                <td class="kol-kel">
                    <span class="ttd-nama-pejabat"><?php echo htmlspecialchars(sipandang_siak_nama_cetak($ttd_kel['nama_pamong'] ?? '')); ?></span><br>
                    NIP. <?php echo htmlspecialchars($ttd_kel['nip'] ?? '197007212007012008'); ?>
                </td>
            </tr>
        </table>
        <?php echo sipandang_verifikasi_qr_siak_footer_html($id_siak, 80); ?>
    </div>
    <?php endforeach; ?>
</body>
</html>
