<?php
include 'koneksi.php';

$id   = $_POST['id_format'];
// Kita gunakan real_escape_string agar tanda petik/html tidak merusak database
$isi  = mysqli_real_escape_string($koneksi, $_POST['isi_surat']);

$update = mysqli_query($koneksi, "UPDATE tbl_format_surat SET isi_surat = '$isi' WHERE id_format = '$id'");

if($update){
    echo "<script>alert('Template Berhasil Disimpan!'); window.location='setting_surat.php';</script>";
} else {
    echo "Gagal Update: " . mysqli_error($koneksi);
}
?>