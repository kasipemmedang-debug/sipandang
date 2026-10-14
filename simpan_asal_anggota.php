<?php
// simpan_asal_anggota.php — Simpan data asal per anggota (untuk kasus beda KK asal)
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include 'koneksi.php';

if (!isset($_POST['id_anggota'], $_POST['id_siak'])) {
    header('Location: permohonan_siak.php'); exit;
}

$id_ang  = (int)$_POST['id_anggota'];
$id_siak = (int)$_POST['id_siak'];

$cols = ['no_kk_asal','nama_kk_asal','alamat_asal','rt_asal','rw_asal','kelurahan_asal','kecamatan_asal','kabupaten_asal','provinsi_asal'];

// Pastikan kolom ada
foreach ($cols as $col) {
    $ck = mysqli_query($koneksi,"SHOW COLUMNS FROM siak_anggota LIKE '$col'");
    if ($ck && mysqli_num_rows($ck)===0)
        mysqli_query($koneksi,"ALTER TABLE siak_anggota ADD COLUMN $col VARCHAR(200) DEFAULT NULL");
}

// Bangun SET clause
$set_parts = [];
foreach ($cols as $col) {
    $val = mysqli_real_escape_string($koneksi, strtoupper(trim($_POST[$col]??'')));
    $set_parts[] = "$col='$val'";
}
$set_sql = implode(',',$set_parts);

mysqli_query($koneksi,"UPDATE siak_anggota SET $set_sql WHERE id_anggota='$id_ang'");

// Redirect kembali
echo "<script>
    if(window.opener) { window.opener.location.reload(); window.close(); }
    else { window.location='cetak_opsi.php?id=$id_siak&toast=asal'; }
</script>";
