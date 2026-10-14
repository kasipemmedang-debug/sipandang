<?php
include 'koneksi.php';
require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_berkas_storage.php';

if (isset($_POST['btn_upload'])) {
    $id_surat = (int) $_POST['id_surat'];

    $query_data = mysqli_query($koneksi, "SELECT a.no_surat, a.tgl_surat, a.nik_pemohon,
        COALESCE(NULLIF(TRIM(b.nama_lengkap), ''), NULLIF(TRIM(pt.nama_lengkap), ''), 'PEMOHON') AS nama_lengkap,
        COALESCE(NULLIF(TRIM(c.kode_surat), ''), NULLIF(TRIM(c.tipe_layanan), ''), 'SURAT') AS inisial_layanan
        FROM tbl_surat_keluar a
        LEFT JOIN tbl_penduduk b ON a.nik_pemohon = b.nik AND (a.pemohon_tipe IS NULL OR a.pemohon_tipe = 'WARGA')
        LEFT JOIN tbl_pemohon_tamu pt ON a.id_pemohon_tamu = pt.id_pemohon_tamu AND a.pemohon_tipe = 'TAMU'
        LEFT JOIN tbl_format_surat c ON a.id_jenis = c.id_format
        WHERE a.id_surat = '$id_surat' LIMIT 1");
    $data = mysqli_fetch_array($query_data);
    if (!$data || empty($data['no_surat'])) {
        echo "<script>alert('Data surat tidak ditemukan.'); window.history.back();</script>";
        exit;
    }

    $file_name = $_FILES['berkas_scan']['name'] ?? '';
    $file_tmp  = $_FILES['berkas_scan']['tmp_name'] ?? '';
    if ($file_name === '' || $file_tmp === '') {
        echo "<script>alert('Pilih file terlebih dahulu.'); window.history.back();</script>";
        exit;
    }

    $tahun = sipandang_berkas_year_from($data['tgl_surat'] ?? null);
    $path_simpan = sipandang_berkas_save_upload(
        'SURAT',
        $tahun,
        $data['inisial_layanan'] ?? 'SURAT',
        $data['no_surat'],
        $data['nama_lengkap'],
        $file_tmp,
        $file_name,
        'SCAN'
    );

    if ($path_simpan) {
        $cek = mysqli_query($koneksi, "SELECT file_scan FROM tbl_surat_keluar WHERE id_surat='$id_surat'");
        $old = mysqli_fetch_array($cek);
        sipandang_berkas_delete_stored($old['file_scan'] ?? '');

        $path_esc = mysqli_real_escape_string($koneksi, $path_simpan);
        mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET file_scan = '$path_esc' WHERE id_surat = '$id_surat'");
        sipandang_log_aktivitas(
            $koneksi,
            'BERKAS',
            'upload',
            $id_surat,
            $data['no_surat'] ?? ('Surat #' . $id_surat),
            $file_name
        );
        if (!empty($_POST['redirect_detail'])) {
            header('Location: arsip_detail.php?id=' . $id_surat . '&toast=upload');
            exit;
        }

        // Kembali ke halaman arsip dengan filter yang sama (tahun, bulan, dll.)
        $kembali = [];
        parse_str((string) ($_POST['kembali_ke'] ?? ''), $kembali);
        unset($kembali['toast'], $kembali['hapus'], $kembali['hapus_berkas'], $kembali['aksi']);
        $url_kembali = 'arsip_surat.php' . ($kembali ? '?' . http_build_query($kembali) : '');
        echo "<script>alert('Berkas berhasil disimpan.'); window.location=" . json_encode($url_kembali) . ";</script>";
    } else {
        echo "<script>alert('Gagal upload.'); window.history.back();</script>";
    }
}
?>