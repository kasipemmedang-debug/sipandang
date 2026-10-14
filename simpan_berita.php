<?php
include 'koneksi.php';

$judul  = $_POST['judul'];
$isi    = $_POST['isi'];
$tgl    = date('Y-m-d');

// --- PROSES UPLOAD FOTO ---
$nama_file_db = "default.jpg";

if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0){
    $target_dir = "berkas_berita/"; // Folder penyimpanan
    
    // Buat folder jika belum ada
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

    $file_ext   = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));
    $file_name  = "berita_" . time() . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    $allowed = array("jpg", "jpeg", "png");
    if(in_array($file_ext, $allowed)){
        if(move_uploaded_file($_FILES["gambar"]["tmp_name"], $target_file)){
            $nama_file_db = $file_name;
        } else {
            echo "<script>alert('Gagal upload gambar!'); window.history.back();</script>";
            exit;
        }
    }
}

// --- SIMPAN KE DATABASE ---
$query = "INSERT INTO tbl_berita (judul_berita, isi_berita, gambar, tgl_posting) 
          VALUES ('$judul', '$isi', '$nama_file_db', '$tgl')";

if(mysqli_query($koneksi, $query)) {
    echo "<script>alert('Berita Berhasil Diposting!'); window.location='data_berita.php';</script>";
} else {
    echo "Gagal: " . mysqli_error($koneksi);
}
?>