<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
require_once __DIR__ . '/inc_siak_preview.php';

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>F-1.35 - <?= $nama_tampil; ?></title>
    <style>
        @page { 
            size: A4 portrait; 
            margin: 15mm 20mm 15mm 20mm; 
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11pt; 
            line-height: 1.4; 
            color: #000; 
            margin: 0;
            padding: 0;
        }
        
        .header-kode { overflow: hidden; margin-bottom: 20px; }
        .kode-f { float: right; border: 1px solid #000; padding: 2px 20px; font-weight: bold; font-size: 13pt; }

        .judul-surat { text-align: center; margin-bottom: 30px; margin-top: 20px; }
        .judul-surat b { font-size: 13pt; text-transform: uppercase; display: block; }
        .judul-surat .garis-bawah { text-decoration: underline; }
        .judul-surat .nomor { margin-top: 5px; }

        .isi-surat { margin-bottom: 15px; text-align: justify; }
        
        .tabel-data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .tabel-data td { vertical-align: top; padding: 2px 0; }
        .tabel-data td.label { width: 220px; }
        .tabel-data td.titik { width: 20px; text-align: center; }

        .detail-alamat table { width: 100%; border-collapse: collapse; }
        .detail-alamat td { padding: 0; line-height: 1.3; }

        .footer-surat { margin-top: 40px; width: 100%; }
        .ttd-box { float: right; width: 300px; text-align: center; }
        
        /* Nama Pejabat Camat Statis */
        .nama-camat {
            margin-top: 80px;
            font-weight: bold;
            text-decoration: underline;
        }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body<?= siak_preview_is_embed() ? '' : ' onload="window.print()"' ?>>

    <div class="header-kode">
        <div class="kode-f">F-1.35</div>
    </div>

    <div class="judul-surat">
        <b>SURAT PENGANTAR PINDAH</b>
        <b class="garis-bawah">ANTAR KABUPATEN/KOTA ATAU ANTAR PROVINSI</b>
        <div class="nomor">NOMOR : ............................................................................................</div>
    </div>

    <div class="isi-surat">
        Yang bertanda tangan di bawah ini, menerangkan Permohonan Pindah Penduduk Warga Negara Indonesia (WNI) dengan data sebagai berikut :
    </div>

    <table class="tabel-data">
        <tr>
            <td class="label">NIK</td>
            <td class="titik">:</td>
            <td style="letter-spacing: 2px; font-weight: bold;"><?= $d['nik_pemohon']; ?></td>
        </tr>
        <tr>
            <td class="label">Nama Lengkap</td>
            <td class="titik">:</td>
            <td style="font-weight: bold;"><?= strtoupper($nama_tampil); ?></td>
        </tr>
        <tr>
            <td class="label">Nomor Kartu Keluarga</td>
            <td class="titik">:</td>
            <td style="letter-spacing: 2px; font-weight: bold;"><?= $d['no_kk_lama']; ?></td>
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
                        <tr>
                            <td width="70">RT. <?= $d['rt']; ?></td>
                            <td>RW. <?= $d['rw']; ?></td>
                        </tr>
                        <tr><td width="90">Desa/Kel.</td><td>: MEDANG</td></tr>
                        <tr><td>Kec.</td><td>: PAGEDANGAN</td></tr>
                        <tr><td>Kab./Kota</td><td>: TANGERANG</td></tr>
                        <tr><td>Provinsi</td><td>: BANTEN</td></tr>
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
                        <tr>
                            <td width="70">RT. <?= $d['rt_tujuan']; ?></td>
                            <td>RW. <?= $d['rw_tujuan']; ?></td>
                        </tr>
                        <tr><td width="90">Desa/Kel.</td><td>: <?= strtoupper($d['kelurahan_tujuan']); ?></td></tr>
                        <tr><td>Kec.</td><td>: <?= strtoupper($d['kecamatan_tujuan']); ?></td></tr>
                        <tr><td>Kab./Kota</td><td>: <?= strtoupper($d['kabupaten_tujuan']); ?></td></tr>
                        <tr><td>Provinsi</td><td>: <?= strtoupper($d['provinsi_tujuan']); ?></td></tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label"><br>Jumlah Keluarga yang Pindah</td>
            <td class="titik"><br>:</td>
            <td><br>0<?= $d['total_keluarga']; ?> (<?= terbilang($d['total_keluarga']); ?>) &nbsp;&nbsp;&nbsp; Orang</td>
        </tr>
    </table>

    <div class="isi-surat">
        Adapun Permohonan Pindah Penduduk WNI yang bersangkutan sebagaimana terlampir.<br>
        Demikian Surat Pengantar Pindah ini dibuat agar dipergunakan sebagaimana mestinya.
    </div>

    <div class="footer-surat">
        <div class="ttd-box">
            MEDANG, <?= sipandang_siak_tanggal_final_ttd($koneksi, $d); ?><br>
            CAMAT PAGEDANGAN
            <br><br><br><br>
            <div class="nama-camat">
                ............................................................
            </div>
        </div>
    </div>

    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight: bold;">PRINT F-1.35</button>
    </div>

<?php echo sipandang_verifikasi_qr_siak_footer_html($id, 80); ?>

</body>
</html>