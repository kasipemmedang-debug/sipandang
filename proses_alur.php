<?php
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_alur_ttd.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
sipandang_require_login();

include 'koneksi.php';
alur_ttd_ensure_schema($koneksi);

if (!isset($_REQUEST['act']) || !isset($_REQUEST['id'])) {
    header('Location: ' . sipandang_login_destination());
    exit;
}

$id_surat = (int) $_REQUEST['id'];
$act      = trim((string) $_REQUEST['act']);
$now      = date('Y-m-d H:i:s');
$oleh     = trim((string) ($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Petugas'));

if ($id_surat < 1) {
    header('Location: ' . sipandang_login_destination());
    exit;
}

$id_esc = (int) $id_surat;

function alur_ttd_redirect_back(string $act, int $id, string $msg, bool $ok = true): void
{
    $toast = $ok ? 'success' : 'error';
    $q = http_build_query([
        'toast'      => $toast,
        'toast_msg'  => $msg,
    ]);
    $ret = trim((string) ($_REQUEST['ret'] ?? ''));
    if ($ret === 'pejabat') {
        header('Location: pejabat/riwayat.php?' . $q);
        exit;
    }
    if (strpos($act, 'ttd_') === 0 || $act === 'kirim_ulang' || $act === 'kirim_dari_manual') {
        header('Location: arsip_detail.php?id=' . $id . '&' . $q);
        exit;
    }
    if (in_array($act, ['acc_kasi', 'acc_lurah', 'acc_sekel'], true)) {
        $dest = sipandang_login_destination();
        if (function_exists('sipandang_user_is_lurah') && sipandang_user_is_lurah()) {
            $dest = 'lurah_panel.php';
        } elseif (function_exists('sipandang_user_is_kasipem') && sipandang_user_is_kasipem()) {
            $dest = 'kasi_panel.php';
        } elseif (function_exists('sipandang_user_is_kasi') && sipandang_user_is_kasi()) {
            $dest = 'kasi_panel.php';
        } elseif (function_exists('sipandang_user_is_sekel') && sipandang_user_is_sekel()) {
            $dest = 'sekel_panel.php';
        }
        header('Location: ' . $dest . '?' . $q);
        exit;
    }
    header('Location: arsip_detail.php?id=' . $id . '&' . $q);
    exit;
}

// --- Alur TTD digital (penambahan) ---
if ($act === 'ttd_setujui') {
    $res = alur_ttd_setujui($koneksi, $id_esc, $oleh);
    if ($res['ok']) {
        sipandang_log_aktivitas($koneksi, 'SURAT', 'ttd_setujui', $id_esc, 'Surat #' . $id_esc, $res['msg']);
    }
    alur_ttd_redirect_back($act, $id_esc, $res['msg'], $res['ok']);
}

if ($act === 'ttd_kembalikan') {
    $catatan = trim((string) ($_POST['catatan'] ?? $_GET['catatan'] ?? ''));
    $res = alur_ttd_kembalikan($koneksi, $id_esc, $oleh, $catatan);
    if ($res['ok']) {
        sipandang_log_aktivitas($koneksi, 'SURAT', 'ttd_kembalikan', $id_esc, 'Surat #' . $id_esc, $catatan);
    }
    alur_ttd_redirect_back($act, $id_esc, $res['msg'], $res['ok']);
}

if ($act === 'kirim_ulang') {
    if (!sipandang_user_is_operator() && !sipandang_user_is_pkl() && !sipandang_user_is_admin()) {
        alur_ttd_redirect_back('ttd_kembalikan', $id_esc, 'Hanya operator/PKL yang dapat mengirim ulang.', false);
    }
    $mode = trim((string) ($_POST['mode'] ?? $_GET['mode'] ?? ''));
    $res = alur_ttd_kirim_ulang($koneksi, $id_esc, $mode, $oleh);
    if ($res['ok']) {
        sipandang_log_aktivitas($koneksi, 'SURAT', 'kirim_ulang', $id_esc, 'Surat #' . $id_esc, $mode);
    }
    alur_ttd_redirect_back('ttd_kembalikan', $id_esc, $res['msg'], $res['ok']);
}

if ($act === 'kirim_dari_manual') {
    if (!sipandang_user_is_operator() && !sipandang_user_is_pkl() && !sipandang_user_is_admin()) {
        alur_ttd_redirect_back('kirim_dari_manual', $id_esc, 'Hanya operator/PKL yang dapat mengirim ke pejabat.', false);
    }
    $mode = trim((string) ($_POST['mode'] ?? $_GET['mode'] ?? ''));
    $res = alur_ttd_kirim_dari_manual($koneksi, $id_esc, $mode, $oleh);
    if ($res['ok']) {
        $noLog = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT no_surat FROM tbl_surat_keluar WHERE id_surat=$id_esc LIMIT 1"));
        sipandang_log_aktivitas(
            $koneksi,
            'SURAT',
            'kirim_dari_manual',
            $id_esc,
            (string) ($noLog['no_surat'] ?? ('Surat #' . $id_esc)),
            'Konversi cetak manual → TTD digital · ' . $mode
        );
    }
    alur_ttd_redirect_back('kirim_dari_manual', $id_esc, $res['msg'], $res['ok']);
}

// --- 1. PROSES OLEH KASI (PENENTU ALUR) — alur lama tetap ---
if ($act === 'acc_kasi') {
    if (!sipandang_user_is_kasi() && !sipandang_user_is_admin()) {
        echo '<script>alert("Akses ditolak."); window.location="' . sipandang_login_destination() . '";</script>';
        exit;
    }

    $cek_surat = mysqli_query($koneksi, "SELECT a.id_pamong, p.jabatan
                                         FROM tbl_surat_keluar a
                                         JOIN tbl_pamong p ON a.id_pamong = p.id_pamong
                                         WHERE a.id_surat = $id_esc LIMIT 1");
    $data = mysqli_fetch_array($cek_surat);
    if (!$data) {
        echo '<script>alert("Surat tidak ditemukan."); history.back();</script>';
        exit;
    }
    $jabatan_ttd = strtoupper((string) ($data['jabatan'] ?? ''));

    if (strpos($jabatan_ttd, 'LURAH') !== false || strpos($jabatan_ttd, 'KEPALA DESA') !== false) {
        $status_baru = 'Verifikasi Sekel';
    } else {
        $status_baru = 'Siap Cetak';
    }

    mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET status_surat='$status_baru', tgl_paraf_kasi='$now' WHERE id_surat=$id_esc");
    header('Location: kasi_panel.php');
    exit;
}

// --- 2. PROSES OLEH SEKEL ---
if ($act === 'acc_sekel') {
    if (!sipandang_user_can_paraf_sekel()) {
        echo '<script>alert("Paraf Sekel hanya untuk Sekretaris Kelurahan."); window.location="' . sipandang_login_destination() . '";</script>';
        exit;
    }

    mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET status_surat='Menunggu TTD', tgl_paraf_sekel='$now' WHERE id_surat=$id_esc AND status_surat='Verifikasi Sekel'");
    header('Location: sekel_panel.php');
    exit;
}

// --- 3. PROSES OLEH LURAH ---
if ($act === 'acc_lurah') {
    if (!sipandang_user_is_lurah() && !sipandang_user_is_admin()) {
        echo '<script>alert("Akses ditolak."); window.location="' . sipandang_login_destination() . '";</script>';
        exit;
    }

    mysqli_query($koneksi, "UPDATE tbl_surat_keluar SET status_surat='Siap Cetak', tgl_ttd_lurah='$now' WHERE id_surat=$id_esc AND status_surat='Menunggu TTD'");
    header('Location: lurah_panel.php');
    exit;
}

header('Location: ' . sipandang_login_destination());
exit;
