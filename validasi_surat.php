<?php
include 'koneksi.php';
$token = $_GET['token'] ?? '';

$q = mysqli_query($koneksi, "SELECT a.*, b.nama_lengkap, c.nama_surat 
    FROM tbl_surat_keluar a 
    LEFT JOIN tbl_penduduk b ON a.nik_pemohon=b.nik 
    LEFT JOIN tbl_format_surat c ON a.id_jenis=c.id_format 
    WHERE a.token_surat='$token'");

$cek = mysqli_num_rows($q);
$d = mysqli_fetch_array($q);
?>
<!DOCTYPE html>
<html class="sp-role-warga">
<head>
    <title>Cek Keaslian Surat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-warga bg-light">
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header text-center bg-primary text-white">
                <h4>VALIDASI DOKUMEN</h4>
                <h6>SISTEM INFORMASI KELURAHAN MEDANG</h6>
            </div>
            <div class="card-body text-center">
                <?php if($cek > 0) { ?>
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e0/Check_green_icon.svg/1200px-Check_green_icon.svg.png" width="80" class="mb-3">
                    <h3 class="text-success">DOKUMEN ASLI</h3>
                    <p>Surat ini tercatat secara sah di database kami.</p>
                    <hr>
                    <table class="table table-bordered text-left">
                        <tr><th>Nomor Surat</th><td><?= $d['no_surat']; ?></td></tr>
                        <tr><th>Jenis Surat</th><td><?= $d['nama_surat']; ?></td></tr>
                        <tr><th>Nama Warga</th><td><?= $d['nama_lengkap']; ?></td></tr>
                        <tr><th>Tanggal Terbit</th><td><?= date('d-m-Y', strtotime($d['tgl_surat'])); ?></td></tr>
                    </table>
                <?php } else { ?>
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/Cross_red_circle.svg/1024px-Cross_red_circle.svg.png" width="80" class="mb-3">
                    <h3 class="text-danger">DATA TIDAK DITEMUKAN!</h3>
                    <p>QR Code ini tidak valid atau dokumen palsu.</p>
                <?php } ?>
            </div>
            <div class="card-footer text-muted text-center">
                &copy; <?= date('Y'); ?> Pemerintah Kelurahan Medang
            </div>
        </div>
    </div>
</body>
</html>