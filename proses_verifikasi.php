<?php
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
include 'header.php';
include 'sidebar.php';

$id = $_GET['id'];
$q = mysqli_query($koneksi, "SELECT * FROM tbl_surat_keluar WHERE id_surat='$id'");
$d = mysqli_fetch_array($q);

// PROSES ACC / TOLAK
if(isset($_POST['aksi'])){
    $status = $_POST['aksi']; // Disetujui / Ditolak
    $alasan = $_POST['alasan'] ?? '';
    $tgl = date('Y-m-d H:i:s');
    
    // Update Status
    mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET status_surat='$status', tgl_verifikasi='$tgl', alasan_tolak='$alasan' WHERE id_surat='$id'");
    
    // KIRIM NOTIFIKASI KE WARGA
    try {
        require_once 'api/warga/notif_lib.php';
        $id_user = idUserFromNik($koneksi, $d['nik_pemohon']);
        if ($id_user) {
            $judul_notif = ($status == 'Disetujui') ? 'Permohonan Disetujui' : 'Permohonan Ditolak';
            $pesan_notif = ($status == 'Disetujui')
                ? "Permohonan surat Anda telah disetujui. Silakan cek aplikasi untuk detailnya."
                : "Permohonan surat Anda ditolak. Alasan: " . ($alasan ?: '-');

            kirimNotifikasiWarga($koneksi, $id_user, $judul_notif, $pesan_notif, ($status == 'Disetujui' ? 'SUCCESS' : 'WARNING'));
        }
    } catch (Exception $e) {
        // Abaikan jika gagal agar proses utama tetap jalan
    }

    // JIKA DISETUJUI -> JALANKAN LOGIKA MUTASI (MATI/PINDAH) DI SINI (Agar database penduduk berubah pas disetujui)
    if($status == 'Disetujui'){
        // Ambil Template untuk cek aksi otomatis
        $q_temp = mysqli_query($koneksi, "SELECT aksi_otomatis FROM tbl_format_surat WHERE id_format='$d[id_jenis]'");
        $temp = mysqli_fetch_array($q_temp);
        
        $tgl_surat_evt = sipandang_tanggal_valid($d['tgl_surat'] ?? null)
            ? date('Y-m-d', strtotime($d['tgl_surat']))
            : date('Y-m-d');
        if ($temp['aksi_otomatis'] === 'MATI') {
            $ket = 'Meninggal dunia - verifikasi surat ' . ($d['no_surat'] ?? '');
            sipandang_penduduk_terapkan_mutasi($koneksi, $d['nik_pemohon'], 'MATI', $tgl_surat_evt, $ket);
        } elseif ($temp['aksi_otomatis'] === 'PINDAH') {
            $ket = 'Pindah keluar - verifikasi surat ' . ($d['no_surat'] ?? '');
            sipandang_penduduk_terapkan_mutasi($koneksi, $d['nik_pemohon'], 'PINDAH', $tgl_surat_evt, $ket);
        }
    }

    echo "<script>alert('Status Berhasil Diubah: $status'); window.location='verifikasi_surat.php';</script>";
}
?>

<div class="content-wrapper">
    <section class="content-header"><h1>Verifikasi Surat</h1></section>
    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body" style="background:#525659; padding:20px;">
                        <div style="background:white; width:21cm; padding:2cm; margin:0 auto;">
                            <?php 
                            $instansi = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM tbl_identitas"));
                            ?>
                            <div style="text-align:center; border-bottom:3px double black; margin-bottom:20px;">
                                <h3>PEMERINTAH KABUPATEN <?= strtoupper($instansi['nama_kabupaten']); ?></h3>
                                <h2>KELURAHAN <?= strtoupper($instansi['nama_desa']); ?></h2>
                                <p><?= $instansi['alamat_kantor']; ?></p>
                            </div>
                            
                            <?= $d['isi_surat_final']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Aksi Validasi</h3></div>
                    <div class="card-body">
                        <p>Status Saat Ini: <b><?= $d['status_surat']; ?></b></p>
                        
                        <form method="POST">
                            <button type="submit" name="aksi" value="Disetujui" class="btn btn-success btn-block btn-lg mb-3">
                                <i class="fas fa-check-circle"></i> SETUJUI / TTD
                            </button>
                            
                            <hr>
                            <div class="form-group">
                                <label>Alasan Penolakan (Jika Ditolak):</label>
                                <textarea name="alasan" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" name="aksi" value="Ditolak" class="btn btn-danger btn-block">
                                <i class="fas fa-times-circle"></i> TOLAK PERMOHONAN
                            </button>
                        </form>
                        
                        <hr>
                        <a href="verifikasi_surat.php" class="btn btn-default btn-block">Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include 'footer.php'; ?>