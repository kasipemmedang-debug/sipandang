<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_nomor_surat.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_surat_pbb.php';
require_once __DIR__ . '/inc_pemohon_tamu.php';
require_once __DIR__ . '/inc_pengajuan_surat.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_register_sktm.php';
require_once __DIR__ . '/inc_kelengkapan_berkas.php';
require_once __DIR__ . '/inc_sipelamar.php';
require_once __DIR__ . '/inc_alur_ttd.php';

sipandang_require_login(false);
kelengkapan_berkas_ensure_schema($koneksi);
pemohon_tamu_ensure_schema($koneksi);
sipelamar_ensure_schema($koneksi);
alur_ttd_ensure_schema($koneksi);

// --- 1. CEK METODE POST (KEAMANAN) ---
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("location:index.php");
    exit;
}

$wajib = ['nik_pemohon', 'id_jenis_surat', 'no_surat', 'ttd_pejabat', 'isi_surat_html'];
foreach ($wajib as $kol) {
    if (!isset($_POST[$kol]) || trim((string) $_POST[$kol]) === '') {
        echo "<script>alert('Data surat belum lengkap: $kol. Silakan ulangi dari preview surat.'); window.history.back();</script>";
        exit;
    }
}

// --- 2. TANGKAP SEMUA DATA DARI FORM PREVIEW ---
$nik            = $_POST['nik_pemohon'];
$id_jenis       = $_POST['id_jenis_surat'];
$no_surat_asli  = trim((string) ($_POST['no_surat'] ?? ''));
$id_pamong      = $_POST['ttd_pejabat'];
$id_pengajuan_w = (int) ($_POST['id_pengajuan_warga'] ?? 0);
$keterangan     = isset($_POST['keterangan']) ? $_POST['keterangan'] : '-';
$tgl_surat      = date('Y-m-d H:i:s');
$pelayanan_sp   = sipelamar_flag_arsip_sekarang($koneksi);
$pemohon_tipe   = strtoupper(trim((string) ($_POST['pemohon_tipe'] ?? 'WARGA')));
$pemohon_tipe   = $pemohon_tipe === 'TAMU' ? 'TAMU' : 'WARGA';
$id_pemohon_tamu = (int) ($_POST['id_pemohon_tamu'] ?? 0);
if ($pemohon_tipe === 'TAMU' && $id_pemohon_tamu <= 0) {
    $tamu = pemohon_tamu_get_by_nik($koneksi, preg_replace('/\D/', '', (string) $nik));
    $id_pemohon_tamu = (int) ($tamu['id_pemohon_tamu'] ?? 0);
}
$no_pengantar_rt = trim((string) ($_POST['no_pengantar_rt'] ?? ''));
$col_pengantar = @mysqli_query($koneksi, "SHOW COLUMNS FROM tbl_surat_keluar LIKE 'no_pengantar_rtrw'");
if ($col_pengantar && mysqli_num_rows($col_pengantar) === 0) {
    @mysqli_query($koneksi, "ALTER TABLE tbl_surat_keluar ADD COLUMN no_pengantar_rtrw VARCHAR(120) NULL DEFAULT NULL");
}
$no_pengantar_sql = $no_pengantar_rt !== ''
    ? "'" . mysqli_real_escape_string($koneksi, $no_pengantar_rt) . "'"
    : 'NULL';

// ── PEMBUAT SURAT dari session user yang login ──
$dibuat_oleh = mysqli_real_escape_string($koneksi,
    $_SESSION['nama_lengkap'] ?? ($_SESSION['nama'] ?? 'Admin')
);

// TANGKAP HTML
$isi_surat_html = $_POST['isi_surat_html'];

// --- 3. LOGIKA CERDAS: CEK INSERT ATAU UPDATE ---
$id_surat_edit = isset($_POST['id_surat_edit']) ? $_POST['id_surat_edit'] : '';
$is_edit_request = !empty($id_surat_edit);
$kecuali_id = $is_edit_request ? (int) $id_surat_edit : 0;
$no_surat = sipandang_nomor_surat_unik_sekarang($koneksi, $no_surat_asli, $kecuali_id);
if ($no_surat !== $no_surat_asli) {
    $isi_surat_html = sipandang_nomor_surat_ganti_di_html($isi_surat_html, $no_surat_asli, $no_surat);
}

$isi_surat_safe = mysqli_real_escape_string($koneksi, $isi_surat_html);

$perbaiki_arsip = !empty($_POST['perbaiki_arsip']);
$mode_simpan_raw = strtolower(trim((string) ($_POST['mode_simpan'] ?? 'manual')));

if ($perbaiki_arsip && $mode_simpan_raw === 'perbaiki_simpan' && !empty($id_surat_edit)) {
    if (!sipandang_user_is_operator() && !sipandang_user_is_pkl() && !sipandang_user_is_admin()) {
        echo "<script>alert('Akses ditolak.'); window.history.back();</script>";
        exit;
    }
    $id_esc = (int) $id_surat_edit;
    $row_pf = mysqli_fetch_assoc(mysqli_query(
        $koneksi,
        "SELECT status_surat FROM tbl_surat_keluar WHERE id_surat=$id_esc LIMIT 1"
    ));
    if (!$row_pf || ($row_pf['status_surat'] ?? '') !== 'DIKEMBALIKAN') {
        echo "<script>alert('Surat tidak dalam status DIKEMBALIKAN atau tidak ditemukan.'); window.history.back();</script>";
        exit;
    }

    $no_s  = mysqli_real_escape_string($koneksi, $no_surat);
    $ket   = mysqli_real_escape_string($koneksi, $keterangan);
    $pam   = (int) $id_pamong;
    $ptipe = mysqli_real_escape_string($koneksi, $pemohon_tipe);
    $idtamu_sql = $id_pemohon_tamu > 0 ? (string) $id_pemohon_tamu : 'NULL';

    $query_pf = "UPDATE tbl_surat_keluar SET
                    no_surat = '$no_s',
                    pemohon_tipe = '$ptipe',
                    id_pemohon_tamu = $idtamu_sql,
                    id_pamong = '$pam',
                    isi_surat = '$isi_surat_safe',
                    keterangan = '$ket',
                    no_pengantar_rtrw = $no_pengantar_sql
                 WHERE id_surat = $id_esc AND status_surat = 'DIKEMBALIKAN'";
    $eksekusi_pf = mysqli_query($koneksi, $query_pf);

    if ($eksekusi_pf) {
        sipandang_log_aktivitas(
            $koneksi,
            'SURAT',
            'perbaiki',
            $id_esc,
            $no_surat,
            'Perbaikan isi surat dikembalikan · ' . substr($keterangan, 0, 80)
        );

        $kel_err = kelengkapan_berkas_simpan_dari_json(
            $koneksi,
            $id_esc,
            (int) $id_jenis,
            (string) ($_POST['kelengkapan_berkas_json'] ?? ''),
            $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Operator'
        );
        if ($kel_err !== null && $kel_err !== '') {
            $_SESSION['kelengkapan_warn'] = $kel_err;
        }

        $preview_token = trim((string) ($_POST['preview_token'] ?? ''));
        if ($preview_token !== '' && alur_ttd_preview_berkas_get($preview_token) !== null) {
            $nama_pemohon_berkas = trim((string) ($_POST['nama_pemohon'] ?? ''));
            if ($nama_pemohon_berkas === '') {
                $nik_esc = mysqli_real_escape_string($koneksi, $nik);
                $pd = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_lengkap FROM tbl_penduduk WHERE nik='$nik_esc' LIMIT 1"));
                $nama_pemohon_berkas = trim((string) ($pd['nama_lengkap'] ?? $nik));
            }
            $scan_path = alur_ttd_preview_berkas_commit($koneksi, $preview_token, $id_esc, $no_surat, $nama_pemohon_berkas);
            if ($scan_path !== false && $scan_path !== '') {
                $scan_esc = mysqli_real_escape_string($koneksi, (string) $scan_path);
                mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET file_scan='$scan_esc' WHERE id_surat=$id_esc");
            }
        }

        $msg = rawurlencode('Perbaikan surat disimpan. Kirim ulang ke pejabat setelah siap.');
        header("location:arsip_detail.php?id=$id_esc&toast=success&toast_msg=$msg");
        exit;
    }

    echo "<h3>Gagal menyimpan perbaikan surat.</h3>";
    echo "Pesan Error: " . mysqli_error($koneksi);
    exit;
}

// Mode simpan: manual (default/alur lama) atau jalur TTD digital
$mode_simpan = strtolower(trim((string) ($_POST['mode_simpan'] ?? 'manual')));
if ($mode_simpan === 'kirim_pejabat') {
    $mode_simpan = alur_ttd_mode_dari_pamong($koneksi, (int) $id_pamong) ?: 'kirim_kasi_pem';
}
$digital_route = alur_ttd_resolve_mode($mode_simpan);
$status_simpan = 'Selesai';
$alur_ttd_val = 'MANUAL';
$ttd_digital = 0;
$redirect_cetak = true;
$dikirim_oleh_sql = 'NULL';
$tgl_kirim_sql = 'NULL';

if ($digital_route !== null) {
    $status_simpan = $digital_route['status'];
    $alur_ttd_val = $digital_route['alur'];
    $ttd_digital = 0;
    $redirect_cetak = false;
    $dikirim_oleh_sql = "'" . mysqli_real_escape_string($koneksi, $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Operator') . "'";
    $tgl_kirim_sql = "'" . mysqli_real_escape_string($koneksi, date('Y-m-d H:i:s')) . "'";

    $preview_token = trim((string) ($_POST['preview_token'] ?? ''));
    if ($preview_token === '') {
        echo "<script>alert('Sesi preview tidak valid. Buka ulang preview surat.'); window.history.back();</script>";
        exit;
    }
    $kelCek = kelengkapan_siap_kirim_pejabat_dari_json($koneksi, (string) ($_POST['kelengkapan_berkas_json'] ?? ''));
    if (!$kelCek['ok']) {
        echo "<script>alert(" . json_encode($kelCek['msg'] ?: 'Kelengkapan berkas belum lengkap.') . "); window.history.back();</script>";
        exit;
    }
    if (alur_ttd_preview_berkas_get($preview_token) === null) {
        echo "<script>alert('Upload berkas PDF di preview surat terlebih dahulu sebelum kirim ke pejabat.'); window.history.back();</script>";
        exit;
    }
}
$alur_esc = mysqli_real_escape_string($koneksi, $alur_ttd_val);
$status_esc = mysqli_real_escape_string($koneksi, $status_simpan);

// GENERATE TOKEN BARU
$token_baru = md5(uniqid(rand(), true));

// --- 4. EKSEKUSI DATABASE ---
if (!empty($id_surat_edit)) {

    // ── UPDATE ────────────────────────────────────────────────
    $id_esc    = intval($id_surat_edit);
    $cek_token = mysqli_fetch_array(mysqli_query($koneksi,
        "SELECT token_surat FROM tbl_surat_keluar WHERE id_surat = '$id_esc'"));
    $token_fix = (!empty($cek_token['token_surat'])) ? $cek_token['token_surat'] : $token_baru;

    $no_s  = mysqli_real_escape_string($koneksi, $no_surat);
    $tgl_s = mysqli_real_escape_string($koneksi, $tgl_surat);
    $pam   = intval($id_pamong);
    $ket   = mysqli_real_escape_string($koneksi, $keterangan);
    $tok   = mysqli_real_escape_string($koneksi, $token_fix);
    $ptipe = mysqli_real_escape_string($koneksi, $pemohon_tipe);
    $idtamu_sql = $id_pemohon_tamu > 0 ? (string) $id_pemohon_tamu : 'NULL';

    $query = "UPDATE tbl_surat_keluar SET 
                no_surat     = '$no_s',
                pemohon_tipe = '$ptipe',
                id_pemohon_tamu = $idtamu_sql,
                tgl_surat    = '$tgl_s',
                id_pamong    = '$pam',
                isi_surat    = '$isi_surat_safe',
                keterangan   = '$ket',
                no_pengantar_rtrw = $no_pengantar_sql,
                status_surat = '$status_esc',
                alur_ttd     = '$alur_esc',
                ttd_digital  = $ttd_digital,
                dikirim_oleh = $dikirim_oleh_sql,
                tgl_kirim_pejabat = $tgl_kirim_sql,
                catatan_pejabat = NULL,
                token_surat  = '$tok',
                dibuat_oleh  = '$dibuat_oleh'
              WHERE id_surat = '$id_esc'";

    $eksekusi  = mysqli_query($koneksi, $query);
    $id_target = $id_esc;
    $log_aksi_surat = 'edit';

} else {

    // ── INSERT ────────────────────────────────────────────────
    $id_j  = intval($id_jenis);
    $nik_e = mysqli_real_escape_string($koneksi, $nik);
    $no_s  = mysqli_real_escape_string($koneksi, $no_surat);
    $tgl_s = mysqli_real_escape_string($koneksi, $tgl_surat);
    $ket   = mysqli_real_escape_string($koneksi, $keterangan);
    $pam   = intval($id_pamong);
    $tok   = mysqli_real_escape_string($koneksi, $token_baru);
    $ptipe = mysqli_real_escape_string($koneksi, $pemohon_tipe);
    $idtamu_sql = $id_pemohon_tamu > 0 ? (string) $id_pemohon_tamu : 'NULL';

    $query = "INSERT INTO tbl_surat_keluar 
              (id_jenis, nik_pemohon, pemohon_tipe, id_pemohon_tamu, no_surat, tgl_surat, keterangan,
               no_pengantar_rtrw, id_pamong, isi_surat, status_surat, alur_ttd, ttd_digital, dikirim_oleh, tgl_kirim_pejabat,
               token_surat, dibuat_oleh, pelayanan_sipelamar)
              VALUES 
              ('$id_j', '$nik_e', '$ptipe', $idtamu_sql, '$no_s', '$tgl_s', '$ket',
               $no_pengantar_sql, '$pam', '$isi_surat_safe', '$status_esc', '$alur_esc', $ttd_digital, $dikirim_oleh_sql, $tgl_kirim_sql,
               '$tok', '$dibuat_oleh', $pelayanan_sp)";

    $eksekusi  = mysqli_query($koneksi, $query);
    $id_target = mysqli_insert_id($koneksi);
    $log_aksi_surat = 'buat';
}

// --- 5. REDIRECT KE CETAK ---
if ($eksekusi) {

    if (!isset($log_aksi_surat)) {
        $log_aksi_surat = 'simpan';
    }
    sipandang_log_aktivitas(
        $koneksi,
        'SURAT',
        $log_aksi_surat,
        (int) $id_target,
        $no_surat,
        'NIK ' . $nik . ' · ' . substr($keterangan, 0, 80)
    );

    $pbb_data = surat_pbb_from_post($_POST);
    if (surat_pbb_has_data($pbb_data)) {
        surat_pbb_save($koneksi, (int) $id_target, $pbb_data);
    }

    register_sktm_upsert_dari_surat($koneksi, (int) $id_target, $_POST);

    // ===== OTOMATIS UPDATE STATUS PENDUDUK (cetak langsung & TTD digital) =====
    $id_jenis_int = intval($id_jenis);
    $q_tipe = mysqli_query($koneksi,
        "SELECT tipe_layanan FROM tbl_format_surat WHERE id_format='$id_jenis_int' LIMIT 1");
    $tipe_layanan_surat = ($q_tipe && mysqli_num_rows($q_tipe)>0)
        ? (mysqli_fetch_assoc($q_tipe)['tipe_layanan'] ?? '') : '';

    if ($tipe_layanan_surat === 'KEMATIAN') {
        $ket_u = 'Meninggal dunia - Surat No. ' . $no_surat;
        $tgl_mati = sipandang_mutasi_tgl_dari_post($_POST, 'MATI');
        sipandang_penduduk_terapkan_mutasi($koneksi, $nik, 'MATI', $tgl_mati, $ket_u);
    }
    // ===== END AUTO UPDATE =====

    if ($id_pengajuan_w > 0 && empty($id_surat_edit)) {
        // Cetak langsung → SIAP_AMBIL segera. TTD digital → tautkan saja; notif saat SIAP_CETAK.
        sipandang_pengajuan_hubungkan_surat(
            $koneksi,
            $id_pengajuan_w,
            (int) $id_target,
            $no_surat,
            $dibuat_oleh,
            $digital_route === null
        );
    }

    if ($digital_route !== null) {
        $preview_token = trim((string) ($_POST['preview_token'] ?? ''));
        if ($preview_token !== '') {
            $kel_err = kelengkapan_berkas_simpan_dari_json(
                $koneksi,
                (int) $id_target,
                (int) $id_jenis,
                (string) ($_POST['kelengkapan_berkas_json'] ?? ''),
                $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Operator'
            );
            if ($kel_err !== null && $kel_err !== '') {
                $_SESSION['kelengkapan_warn'] = $kel_err;
            }
            $nama_pemohon_berkas = trim((string) ($_POST['nama_pemohon'] ?? ''));
            if ($nama_pemohon_berkas === '') {
                $nik_esc = mysqli_real_escape_string($koneksi, $nik);
                $pd = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_lengkap FROM tbl_penduduk WHERE nik='$nik_esc' LIMIT 1"));
                $nama_pemohon_berkas = trim((string) ($pd['nama_lengkap'] ?? $nik));
            }
            $scan_path = alur_ttd_preview_berkas_commit($koneksi, $preview_token, (int) $id_target, $no_surat, $nama_pemohon_berkas);
            if ($scan_path !== false && $scan_path !== '') {
                $scan_esc = mysqli_real_escape_string($koneksi, (string) $scan_path);
                mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET file_scan='$scan_esc' WHERE id_surat=" . (int) $id_target);
            }
        }
    } else {
        $kel_err = kelengkapan_berkas_simpan_dari_json(
            $koneksi,
            (int) $id_target,
            (int) $id_jenis,
            (string) ($_POST['kelengkapan_berkas_json'] ?? ''),
            $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Operator'
        );
        if ($kel_err !== null && $kel_err !== '') {
            $_SESSION['kelengkapan_warn'] = $kel_err;
        }
    }

    if ($redirect_cetak) {
        header("location:cetak_surat.php?id=$id_target");
    } else {
        if (!function_exists('kirimPushInboxPejabat')) {
            require_once __DIR__ . '/inc_fcm_pejabat.php';
        }
        kirimPushInboxPejabat($koneksi, (int) $id_target);
        $msg = rawurlencode($digital_route['label'] ?? 'Surat dikirim ke pejabat.');
        header("location:arsip_detail.php?id=$id_target&toast=success&toast_msg=$msg");
    }
    exit;
} else {
    echo "<h3>Gagal Menyimpan Database!</h3>";
    echo "Pesan Error: " . mysqli_error($koneksi);
    echo "<br><br>Query: " . $query;
}
?>