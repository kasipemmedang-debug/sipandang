<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil data
    $id_format = isset($_POST['id_format']) ? $_POST['id_format'] : null;
    $nama_surat = mysqli_real_escape_string($koneksi, $_POST['nama_surat']);
    $kode_surat = mysqli_real_escape_string($koneksi, $_POST['kode_surat']);
    $kop_surat = mysqli_real_escape_string($koneksi, $_POST['kop_surat']);
    $tipe_layanan = mysqli_real_escape_string($koneksi, $_POST['tipe_layanan']);
    $isi_surat = mysqli_real_escape_string($koneksi, $_POST['isi_surat']);

    if ($id_format) {
        // UPDATE (Edit Template Lama)
        $query = "UPDATE tbl_format_surat SET 
                    nama_surat = '$nama_surat',
                    kode_surat = '$kode_surat',
                    kop_surat = '$kop_surat',
                    tipe_layanan = '$tipe_layanan',
                    isi_surat = '$isi_surat'
                  WHERE id_format = '$id_format'";
    } else {
        // INSERT (Tambah Template Baru)
        $query = "INSERT INTO tbl_format_surat (nama_surat, kode_surat, kop_surat, tipe_layanan, isi_surat) 
                  VALUES ('$nama_surat', '$kode_surat', '$kop_surat', '$tipe_layanan', '$isi_surat')";
    }

    if (mysqli_query($koneksi, $query)) {
        header("Location: setting_surat.php?pesan=sukses");
    } else {
        die("Gagal menyimpan: " . mysqli_error($koneksi));
    }
} else {
    header("Location: setting_surat.php");
}
?>