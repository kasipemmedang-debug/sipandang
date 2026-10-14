<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';

// Cek Input
if (!isset($_POST['id_surat_batch']) || empty($_POST['id_surat_batch'])) {
    die("Error: Belum ada surat yang dipilih.");
}

$daftar_id   = $_POST['id_surat_batch'];
$nik_warga   = $_POST['nik'];
$id_pamong   = $_POST['ttd_pejabat'];
$keperluan   = $_POST['keperluan'];
$no_pengantar= $_POST['no_pengantar_rt'];

// Variabel Nomor Otomatis
$seq_start   = (int)$_POST['seq_start'];
$roman_month = $_POST['roman_month'];
$year        = $_POST['current_year'];

// Ambil Data Warga & Pejabat Sekali Saja
$q_warga = mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE nik='$nik_warga'");
$warga   = mysqli_fetch_array($q_warga);
$q_pamong = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong='$id_pamong'");
$pamong   = mysqli_fetch_array($q_pamong);
$q_instansi = mysqli_query($koneksi, "SELECT * FROM tbl_identitas WHERE id_identitas=1");
$instansi = mysqli_fetch_array($q_instansi);

// Format Data
$bulan_indo = ["","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tgl_lahir = date('d', strtotime($warga['tgl_lahir'])) . " " . $bulan_indo[(int)date('m', strtotime($warga['tgl_lahir']))] . " " . date('Y', strtotime($warga['tgl_lahir']));
$ttl_fix = ucwords(strtolower($warga['tempat_lahir'])) . ", " . $tgl_lahir;
$jk_fix = ($warga['jenis_kelamin'] == 'L') ? 'Laki-Laki' : 'Perempuan';
$agama_db = trim($warga['agama']); if($agama_db=='1')$ag="Isam"; else $ag=ucwords(strtolower($agama_db));
$status_db = trim($warga['status_perkawinan']); if($status_db=='1')$st="Belum Kawin"; else $st=ucwords(strtolower($status_db));
$tgl_surat_indo = date('d F Y');

// OUTPUT CONTAINER
$html_output = "";

// LOOPING SURAT
foreach($daftar_id as $id_format){
    
    // 1. Ambil Template
    $q_temp = mysqli_query($koneksi, "SELECT * FROM tbl_format_surat WHERE id_format='$id_format'");
    $template = mysqli_fetch_array($q_temp);

    // 2. Generate Nomor Surat (Urut: 006, 007, 008...)
    // Atau ambil dari input manual jika admin mengubahnya di form awal
    $input_name = "nomor_surat_" . $id_format;
    $nomor_raw = $_POST[$input_name]; // Masih ada "..."

    // Jika nomor masih format default "...", kita ganti dengan nomor urut otomatis
    if(strpos($nomor_raw, '...') !== false){
        $seq_str = str_pad($seq_start, 3, '0', STR_PAD_LEFT);
        $nomor_final = str_replace('...', $seq_str, $nomor_raw);
        $seq_start++; // Naikkan counter untuk surat berikutnya
    } else {
        $nomor_final = $nomor_raw; // Pakai nomor manual admin
    }

    // 3. Log ke Arsip
    $ket_log = mysqli_real_escape_string($koneksi, $keperluan . " (Paket Layanan)");
    mysqli_query($koneksi, "INSERT INTO tbl_surat_keluar (no_surat, nik_pemohon, id_jenis, tgl_surat, keterangan, petugas, no_pengantar_rtrw) VALUES ('$nomor_final', '$nik_warga', '$id_format', '".date('Y-m-d')."', '$ket_log', 'Admin', '$no_pengantar')");

    // 4. Replace Isi
    $cari = ['[nama]', '[nik]', '[no_kk]', '[ttl]', '[jk]', '[agama]', '[status]', '[pendidikan]', '[pekerjaan]', '[alamat]', '[rt]', '[rw]', '[alamat_lengkap]', '[nomor_surat]', '[keperluan]', '[no_pengantar_rt]', '[desa]', '[kecamatan]', '[kabupaten]'];
    
    $ganti = [
        ucwords(strtolower($warga['nama_lengkap'])), $warga['nik'], $warga['no_kk'], $ttl_fix, $jk_fix, $ag, $st,
        ucwords(strtolower($warga['pendidikan'])), ucwords(strtolower(sipandang_tampil_pekerjaan($warga['pekerjaan'] ?? ''))), 
        ucwords(strtolower($warga['alamat'])), $warga['rt'], $warga['rw'], 
        ucwords(strtolower($warga['alamat'])) . " RT " . $warga['rt'] . " / RW " . $warga['rw'],
        $nomor_final, $keperluan, $no_pengantar,
        $instansi['nama_desa'], $instansi['nama_kecamatan'], $instansi['nama_kabupaten']
    ];

    $isi_surat = str_ireplace($cari, $ganti, $template['isi_surat']);

    // 5. Susun HTML Per Halaman
    $html_output .= "
    <div class='page-container'>
        ". ($template['kop_surat']!='Tidak' ? "
        <div class='kop-header'>
            <img src='assets/$instansi[logo]' class='logo-kop'>
            <div class='teks-kop'>
                <h3>PEMERINTAH KABUPATEN ".strtoupper($instansi['nama_kabupaten'])."</h3>
                <h3>KECAMATAN ".strtoupper($instansi['nama_kecamatan'])."</h3>
                <h2>KELURAHAN ".strtoupper($instansi['nama_desa'])."</h2>
                <p>$instansi[alamat_kantor]</p>
            </div>
            <div style='clear:both;'></div>
        </div>" : "") ."
        
        <div class='isi-surat'>$isi_surat</div>
        
        <div class='ttd-area'>
            <p>$instansi[nama_desa], $tgl_surat_indo</p>
            <p>". (strtoupper($pamong['jabatan'])=='LURAH' ? "LURAH" : "An. LURAH<br>$pamong[jabatan]") ." ".strtoupper($instansi['nama_desa'])."</p>
            <br><br><br><br>
            <p style='font-weight:bold; text-decoration:underline;'>$pamong[nama_pamong]</p>
            <p>NIP. $pamong[nip]</p>
        </div>
    </div>
    <div style='page-break-after: always;'></div>
    ";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cetak Paket Surat</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; margin: 0; }
        .page-container { margin: 1.5cm 2cm 1.5cm 2cm; min-height: 28cm; position: relative; }
        .kop-header { border-bottom: 4px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-kop { width: 90px; float: left; margin-right: 20px; }
        .teks-kop { width: 100%; text-align: center; overflow: hidden; }
        .teks-kop h3 { margin: 0; font-size: 14pt; font-weight: bold; }
        .teks-kop h2 { margin: 0; font-size: 18pt; font-weight: bold; }
        .teks-kop p { margin: 0; font-size: 11pt; font-style: italic; }
        .isi-surat { line-height: 1.5; text-align: justify; }
        .ttd-area { float: right; width: 45%; text-align: center; margin-top: 50px; }
        @media print { @page { margin: 0; } body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <?= $html_output; ?>
</body>
</html>