<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input(false);
include 'koneksi.php';
require_once __DIR__ . '/inc_titip_berkas.php';
require_once __DIR__ . '/inc_persyaratan.php';

titip_berkas_ensure_schema($koneksi);

/* Nama kelurahan untuk teks WA. */
$nama_kel = 'MEDANG';
$qk = @mysqli_query($koneksi, "SELECT nama_kelurahan FROM tbl_profil LIMIT 1");
if ($qk && ($rk = mysqli_fetch_assoc($qk)) && !empty($rk['nama_kelurahan'])) {
    $nama_kel = strtoupper(preg_replace('/^(KELURAHAN|KEL\.?|DESA)\s+/i', '', trim((string) $rk['nama_kelurahan'])));
}

$msg = '';
$err = '';
$petugas = (string) ($_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Operator'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $esc = static fn ($v) => mysqli_real_escape_string($koneksi, (string) $v);

    if ($aksi === 'buat') {
        $nik = preg_replace('/\D/', '', (string) ($_POST['nik'] ?? '')) ?? '';
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $no_wa = titip_berkas_normalisasi_wa((string) ($_POST['no_wa'] ?? ''));
        $jenis = trim((string) ($_POST['jenis_layanan'] ?? ''));
        $estimasi = trim((string) ($_POST['estimasi'] ?? ''));
        $catatan = trim((string) ($_POST['catatan'] ?? ''));

        // Lengkapi nama dari data penduduk bila kosong.
        if ($nama === '' && $nik !== '') {
            $rp = @mysqli_query($koneksi, "SELECT nama_lengkap FROM tbl_penduduk WHERE nik='" . $esc($nik) . "' LIMIT 1");
            if ($rp && ($pr = mysqli_fetch_assoc($rp))) {
                $nama = trim((string) ($pr['nama_lengkap'] ?? ''));
            }
        }

        if ($no_wa === '' || $jenis === '') {
            $err = 'No. WhatsApp dan jenis layanan wajib diisi.';
        } else {
            $kode = titip_berkas_generate_kode($koneksi);
            $ok = mysqli_query($koneksi, "INSERT INTO tbl_titip_berkas
                (kode, nik, nama, no_wa, jenis_layanan, estimasi, status, catatan, petugas)
                VALUES ('{$esc($kode)}','{$esc($nik)}','{$esc($nama)}','{$esc($no_wa)}','{$esc($jenis)}','{$esc($estimasi)}','DITERIMA','{$esc($catatan)}','{$esc($petugas)}')");
            if ($ok) {
                $row = titip_berkas_cari_by_kode($koneksi, $kode);
                $teks = titip_berkas_pesan_notif($row ?? [], 'DITERIMA', $nama_kel);
                $terkirim = $teks !== '' ? titip_berkas_kirim_wa($no_wa, $teks) : false;
                $msg = "Tiket dibuat. Kode: {$kode}." . ($terkirim ? ' Notifikasi WA terkirim.' : ' (Notifikasi WA gagal/tidak terkirim — cek nomor & token.)');
            } else {
                $err = 'Gagal membuat tiket: ' . mysqli_error($koneksi);
            }
        }
    }

    if ($aksi === 'ubah_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = strtoupper(trim((string) ($_POST['status'] ?? '')));
        $catatan = trim((string) ($_POST['catatan'] ?? ''));
        if ($id < 1 || !titip_berkas_status_valid($status)) {
            $err = 'Status tidak valid.';
        } else {
            $ok = mysqli_query($koneksi, "UPDATE tbl_titip_berkas SET status='{$esc($status)}', catatan='{$esc($catatan)}' WHERE id=$id");
            if ($ok) {
                $q = mysqli_query($koneksi, "SELECT * FROM tbl_titip_berkas WHERE id=$id LIMIT 1");
                $row = $q ? mysqli_fetch_assoc($q) : null;
                $teks = $row ? titip_berkas_pesan_notif($row, $status, $nama_kel) : '';
                $terkirim = ($teks !== '' && $row) ? titip_berkas_kirim_wa((string) $row['no_wa'], $teks) : false;
                $info = titip_berkas_status_meta()[$status]['label'] ?? $status;
                $msg = "Status diperbarui ke \"{$info}\"." . ($teks !== '' ? ($terkirim ? ' Notifikasi WA terkirim.' : ' (Notifikasi WA gagal terkirim.)') : '');
            } else {
                $err = 'Gagal memperbarui: ' . mysqli_error($koneksi);
            }
        }
    }

    if ($aksi === 'hapus') {
        if (!sipandang_user_can_hapus_data()) {
            $err = 'Akun PKL tidak dapat menghapus tiket.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                mysqli_query($koneksi, "DELETE FROM tbl_titip_berkas WHERE id=$id");
                $msg = 'Tiket dihapus.';
            }
        }
    }
}
$meja_panel = sipandang_user_is_pkl() ? 'pkl_panel.php' : (sipandang_user_is_operator() ? 'operator_panel.php' : 'admin_panel.php');
$can_hapus_titip = sipandang_user_can_hapus_data();

/* Datalist jenis layanan dari persyaratan aktif. */
$jenis_opsi = [];
if (function_exists('persyaratan_kategori_aktif')) {
    foreach (persyaratan_kategori_aktif($koneksi) as $kat) {
        foreach (persyaratan_layanan_aktif($koneksi, (int) ($kat['id_kategori'] ?? 0)) as $lay) {
            $n = trim((string) ($lay['nama_layanan'] ?? ''));
            if ($n !== '') {
                $jenis_opsi[] = $n;
            }
        }
    }
}

/* Filter & daftar. */
$f_status = strtoupper(trim((string) ($_GET['status'] ?? '')));
$where = '';
if (titip_berkas_status_valid($f_status)) {
    $where = "WHERE status='" . mysqli_real_escape_string($koneksi, $f_status) . "'";
} elseif ($f_status === 'AKTIF') {
    $where = "WHERE status IN ('DITERIMA','DIPROSES','PERLU_LENGKAP','SIAP_AMBIL')";
}
$rows = [];
$ql = mysqli_query($koneksi, "SELECT * FROM tbl_titip_berkas $where ORDER BY
    CASE status WHEN 'SIAP_AMBIL' THEN 1 WHEN 'PERLU_LENGKAP' THEN 2 WHEN 'DITERIMA' THEN 3 WHEN 'DIPROSES' THEN 4 ELSE 5 END,
    tgl_update DESC LIMIT 300");
while ($ql && ($r = mysqli_fetch_assoc($ql))) {
    $rows[] = $r;
}

$cnt_aktif = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM tbl_titip_berkas WHERE status IN ('DITERIMA','DIPROSES','PERLU_LENGKAP','SIAP_AMBIL')"))['c'] ?? 0);
$cnt_siap  = (int) (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM tbl_titip_berkas WHERE status='SIAP_AMBIL'"))['c'] ?? 0);

$meta = titip_berkas_status_meta();

$cnt_lengkap = 0; $cnt_selesai = 0;
$qcnt = @mysqli_query($koneksi, "SELECT status, COUNT(*) c FROM tbl_titip_berkas GROUP BY status");
while ($qcnt && ($rc = mysqli_fetch_assoc($qcnt))) {
    $stc = strtoupper((string) $rc['status']);
    if ($stc === 'PERLU_LENGKAP') { $cnt_lengkap = (int) $rc['c']; }
    if ($stc === 'SELESAI')       { $cnt_selesai = (int) $rc['c']; }
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* =====================================================
   BERKAS DITINGGAL — diseragamkan dengan Arsip SIAK
   SIPANDANG UI 2027 — Bento · Soft Glass · Fluid Motion
   (hanya tampilan; tidak menyentuh logika PHP/JS)
   ===================================================== */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root {
    --sp-primary: var(--role,#0b5cad); --sp-primary-d: var(--role-ink,#08428a); --sp-accent: var(--role,#6366f1);
    --sp-danger: #e11d48;
    --sp-bg: #f4f7fb; --sp-surface: rgba(255,255,255,.82); --sp-solid: #fff;
    --sp-line: rgba(15,23,42,.08); --sp-text: #0b1220; --sp-sub: #5b6b82; --sp-tint: var(--role-soft,#e8f2fd);
    --sp-radius: 20px;
    --sp-shadow-1: 0 1px 2px rgba(15,23,42,.04), 0 6px 18px -6px rgba(15,23,42,.08);
    --sp-shadow-2: 0 2px 4px rgba(15,23,42,.04), 0 24px 48px -16px rgba(var(--role-rgb,11,92,173),.28);
    --sp-ease: cubic-bezier(.22,1,.36,1);
    --navy: #0b1220; --bg: #f4f7fb; --muted: #5b6b82; --border: rgba(15,23,42,.10);
    --shadow: var(--sp-shadow-1);
}
body, body .content-wrapper, body .content, body section.content {
    background: var(--sp-bg) !important; color: var(--sp-text) !important;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif !important;
    -webkit-font-smoothing: antialiased;
}
body .content-wrapper {
    background:
        radial-gradient(900px 420px at 8% -8%, rgba(var(--role-rgb,11,92,173),.14), transparent 60%),
        radial-gradient(800px 380px at 100% 0%, rgba(var(--role-rgb,11,92,173),.12), transparent 60%),
        var(--sp-bg) !important;
    padding: 0 !important;
}
.tb-wrapper { position: relative; z-index: 10; padding: 8px 32px 64px !important; max-width: 1680px; margin: 0 auto; }
@media (max-width: 640px) { .tb-wrapper { padding: 8px 16px 48px !important; } }

/* ---------- Hero header ---------- */
.db-header { background: transparent !important; border: 0 !important; padding: 28px 32px 8px !important; margin: 0 auto 12px !important; max-width: 1680px; }
body .content-wrapper > .db-header > .container-fluid {
    position: relative; overflow: hidden; padding: 28px 32px !important; border-radius: 24px !important;
    background-color: var(--role-ink,#08428a) !important;
    background-image:
        radial-gradient(600px 240px at 90% -20%, rgba(255,255,255,.22), transparent 60%),
        linear-gradient(120deg, var(--role-dark2,#0d1928) 0%, var(--role-ink,#08428a) 48%, var(--role,#0b5cad) 100%) !important;
    box-shadow: 0 24px 60px -24px rgba(var(--role-rgb,11,92,173),.55) !important; color: #fff !important;
}
.db-header .container-fluid::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,.14) 1px, transparent 1px); background-size: 22px 22px;
    mask-image: linear-gradient(100deg, transparent 30%, #000 100%);
    -webkit-mask-image: linear-gradient(100deg, transparent 30%, #000 100%);
}
.db-header .container-fluid > * { position: relative; z-index: 1; }
@media (max-width: 640px) { .db-header { padding: 16px 16px 4px !important; } .db-header .container-fluid { padding: 22px 20px !important; } }
.db-header h1 { font-size: clamp(22px,2.4vw,32px) !important; font-weight: 800 !important; letter-spacing: -.8px !important; margin: 0 0 6px !important; }
.db-header p { font-size: 14px !important; font-weight: 500 !important; margin: 0 !important; }
.db-header .hdr-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.14) !important; border: 1px solid rgba(255,255,255,.28) !important;
    color: #fff !important; font-size: 11px !important; font-weight: 700 !important;
    text-transform: uppercase; letter-spacing: .9px; padding: 6px 12px !important;
    border-radius: 999px !important; margin-bottom: 14px !important;
    backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
}
.db-header .hdr-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.db-header .btn-pro { background: rgba(255,255,255,.12) !important; color: #fff !important; border-color: rgba(255,255,255,.3) !important; box-shadow: none !important; }
.db-header .btn-pro:hover { background: rgba(255,255,255,.22) !important; }
html body .content-wrapper .db-header.db-header:not(#_) { background: transparent !important; background-image: none !important; box-shadow: none !important; border: 0 !important; }
html body .content-wrapper .db-header.db-header:not(#_) h1,
html body .content-wrapper .db-header.db-header:not(#_) h1 * { color: #fff !important; -webkit-text-fill-color: #fff !important; background: none !important; opacity: 1 !important; text-shadow: 0 2px 12px rgba(0,0,0,.25) !important; }
html body .content-wrapper .db-header.db-header:not(#_) p { color: rgba(255,255,255,.9) !important; -webkit-text-fill-color: rgba(255,255,255,.9) !important; }
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro,
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro i { color: #fff !important; -webkit-text-fill-color: #fff !important; }
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge {
    display: inline-flex !important; align-items: center; gap: 6px;
    background: rgba(255,255,255,.16) !important; background-image: none !important;
    border: 1px solid rgba(255,255,255,.32) !important;
    color: #ffffff !important; -webkit-text-fill-color: #ffffff !important;
    font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: .9px;
    padding: 6px 12px !important; border-radius: 999px !important; margin-bottom: 14px !important;
    opacity: 1 !important; filter: none !important; mix-blend-mode: normal !important; text-shadow: none !important;
    box-shadow: none !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge *,
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge i {
    color: inherit !important; -webkit-text-fill-color: currentColor !important;
    background: transparent !important; opacity: 1 !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .container-fluid {
    background-color: var(--role-ink,#08428a) !important;
    background-image:
        radial-gradient(600px 240px at 90% -20%, rgba(255,255,255,.22), transparent 60%),
        linear-gradient(120deg, var(--role-dark2,#0d1928) 0%, var(--role-ink,#08428a) 48%, var(--role,#0b5cad) 100%) !important;
    border-radius: 24px !important;
    box-shadow: 0 24px 60px -24px rgba(var(--role-rgb,11,92,173),.55) !important;
}

/* ---------- Tombol (pill) ---------- */
.btn-pro {
    display: inline-flex !important; align-items: center; gap: 8px;
    padding: 10px 18px !important; border-radius: 999px !important; font-family: inherit !important;
    font-size: 13px !important; font-weight: 700 !important; text-decoration: none !important;
    border: 1px solid transparent !important; cursor: pointer;
    transition: transform .3s var(--sp-ease), box-shadow .3s var(--sp-ease), background .2s, color .2s, border-color .2s;
}
.btn-pro:hover { transform: translateY(-2px); text-decoration: none !important; }
.btn-pro:active { transform: translateY(0) scale(.98); }
.btn-pro.bp-sm { padding: 6px 13px !important; font-size: 12px !important; }
.bp-navy { background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)) !important; color: #fff !important; box-shadow: 0 10px 20px -10px rgba(var(--role-rgb,11,92,173),.6) !important; }
.bp-navy:hover { color: #fff !important; box-shadow: 0 14px 26px -10px rgba(var(--role-rgb,11,92,173),.7) !important; }
.bp-gold { background: var(--sp-tint) !important; color: var(--sp-primary-d) !important; border-color: rgba(var(--role-rgb,11,92,173),.22) !important; }
.bp-gold:hover { background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important; color: #fff !important; }
.bp-danger { background: #fff1f3 !important; color: var(--sp-danger) !important; border-color: rgba(225,29,72,.25) !important; }
.bp-danger:hover { background: linear-gradient(135deg, #e11d48, #be5804) !important; color: #fff !important; }

/* ---------- KPI bento ---------- */
.stat-strip { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 18px; margin-bottom: 28px; }
@media (max-width: 991px) { .stat-strip { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; } }
@media (max-width: 420px) { .stat-strip { grid-template-columns: 1fr; } }
.sc {
    position: relative; overflow: hidden; isolation: isolate;
    display: grid; grid-template-columns: 44px minmax(0,1fr); column-gap: 14px; align-items: center;
    background: var(--sp-surface); backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line); border-radius: var(--sp-radius); padding: 20px 20px 18px;
    box-shadow: var(--sp-shadow-1);
    transition: transform .45s var(--sp-ease), box-shadow .45s var(--sp-ease), border-color .3s;
    animation: sp-rise .7s var(--sp-ease) both;
}
.sc:nth-child(2) { animation-delay: .05s; } .sc:nth-child(3) { animation-delay: .10s; } .sc:nth-child(4) { animation-delay: .15s; }
.sc::before {
    content: ''; position: absolute; left: 20px; right: 20px; top: 0; height: 3px; border-radius: 0 0 6px 6px;
    background: linear-gradient(90deg, var(--sp-primary), var(--sp-accent)); opacity: .85;
    transform-origin: left; transform: scaleX(.35); transition: transform .6s var(--sp-ease);
}
.sc:hover { transform: translateY(-6px); border-color: rgba(var(--role-rgb,11,92,173),.45); box-shadow: var(--sp-shadow-2); }
.sc:hover::before { transform: scaleX(1); }
.sc .i {
    grid-row: 1 / span 2; grid-column: 1; width: 44px; height: 44px; display: grid; place-items: center;
    border-radius: 13px; font-size: 16px;
    background: linear-gradient(135deg, var(--role-soft,#e6f3ff), var(--role-soft,#eef0ff)); color: var(--sp-primary-d);
    transition: transform .4s var(--sp-ease), background .3s, color .3s;
}
.sc:hover .i { transform: scale(1.1) rotate(-6deg); background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)); color: #fff; }
.sc .v { grid-column: 2; grid-row: 1; font-size: clamp(26px,2.4vw,34px); font-weight: 800; color: var(--sp-text); line-height: 1.05; letter-spacing: -1.2px; font-variant-numeric: tabular-nums; }
.sc .l { grid-column: 2; grid-row: 2; margin-top: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .9px; color: var(--sp-sub); }
.sc-green .v { color: #059669; }
.sc-green::before { background: linear-gradient(90deg, #10b981, #34d399); }
.sc-green .i { background: #dcfce7; color: #059669; }
.sc-orange .v { color: var(--sp-danger); }
.sc-orange.warn { background: linear-gradient(180deg, rgba(255,241,243,.95), rgba(255,255,255,.85)); border-color: rgba(225,29,72,.25); }
.sc-orange.warn::before { background: linear-gradient(90deg, #f43f5e, #fb923c); transform: scaleX(1); }
.sc-orange .i { background: #ffe4e8; color: var(--sp-danger); }
@keyframes sp-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

/* ---------- Layout & kartu ---------- */
.tb-wrap { display: grid; grid-template-columns: 380px minmax(0,1fr); gap: 22px; align-items: start; padding: 0; }
@media (max-width: 980px) { .tb-wrap { grid-template-columns: 1fr; } }
.tb-card {
    background: var(--sp-surface); backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line); border-radius: var(--sp-radius); box-shadow: var(--sp-shadow-1);
    overflow: hidden; animation: sp-rise .8s var(--sp-ease) .1s both; transition: box-shadow .4s var(--sp-ease);
}
.tb-card:hover { box-shadow: 0 2px 4px rgba(15,23,42,.04), 0 20px 40px -18px rgba(15,23,42,.16); }
.tb-card-hd {
    display: flex; align-items: center; gap: 12px; padding: 18px 24px;
    background: linear-gradient(180deg, rgba(248,250,252,.9), rgba(255,255,255,0));
    border-bottom: 1px solid var(--sp-line);
    font-size: 12px; font-weight: 800; color: var(--sp-text); text-transform: uppercase; letter-spacing: .9px;
}
.tb-card-hd i { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 9px; font-size: 13px; background: var(--sp-tint); color: var(--sp-primary); margin: 0 !important; }
.tb-card-bd { padding: 20px 24px 24px; }

/* ---------- Form ---------- */
.lbl { display: block; margin-bottom: 6px; font-size: 10.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--sp-sub); }
.inp {
    width: 100%; outline: none; margin-bottom: 14px; padding: 10px 14px;
    border: 1px solid var(--sp-line); border-radius: 12px; background: var(--sp-solid); color: var(--sp-text);
    font-family: inherit; font-size: 13px; font-weight: 500; transition: border-color .2s, box-shadow .2s;
}
.inp:focus { border-color: var(--sp-primary); box-shadow: 0 0 0 4px rgba(var(--role-rgb,11,92,173),.14); }
textarea.inp { min-height: 76px; resize: vertical; }
.btn-s {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    border: 1px solid transparent; border-radius: 999px; cursor: pointer;
    background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)); color: #fff;
    font-family: inherit; font-weight: 700; font-size: 13px;
    box-shadow: 0 10px 20px -10px rgba(var(--role-rgb,11,92,173),.6);
    transition: transform .3s var(--sp-ease), box-shadow .3s var(--sp-ease), background .2s, color .2s;
}
.btn-s:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -10px rgba(var(--role-rgb,11,92,173),.7); }
.btn-g { background: var(--sp-tint); color: var(--sp-primary-d); border-color: rgba(var(--role-rgb,11,92,173),.25); box-shadow: none; }
.btn-g:hover { background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)); color: #fff; box-shadow: none; }
.btn-d { background: #fff1f3; color: var(--sp-danger); border-color: rgba(225,29,72,.25); box-shadow: none; }
.btn-d:hover { background: linear-gradient(135deg, #e11d48, #be5804); color: #fff; box-shadow: none; }
.btn-xs { padding: 6px 13px; font-size: 12px; }
.btn-s:focus-visible, .inp:focus-visible { outline: 3px solid rgba(var(--role-rgb,11,92,173),.45); outline-offset: 2px; }

/* ---------- Filter (pill) ---------- */
.filter-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
.filter-row a {
    font-size: 12px; font-weight: 700; text-decoration: none; padding: 7px 14px; border-radius: 999px;
    background: var(--sp-solid); color: var(--sp-sub); border: 1px solid var(--sp-line);
    transition: transform .3s var(--sp-ease), background .2s, color .2s, border-color .2s;
}
.filter-row a:hover { background: var(--sp-tint); color: var(--sp-primary-d); border-color: rgba(var(--role-rgb,11,92,173),.3); transform: translateY(-1px); }
.filter-row a.active { background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)); color: #fff; border-color: transparent; box-shadow: 0 8px 16px -8px rgba(var(--role-rgb,11,92,173),.6); }

/* ---------- Tiket ---------- */
.tk {
    position: relative; background: var(--sp-solid); border: 1px solid var(--sp-line); border-radius: 18px;
    padding: 16px 18px; margin-bottom: 12px;
    transition: transform .35s var(--sp-ease), box-shadow .35s var(--sp-ease), border-color .25s;
}
.tk:hover { transform: translateY(-2px); border-color: rgba(var(--role-rgb,11,92,173),.4); box-shadow: 0 16px 30px -14px rgba(var(--role-rgb,11,92,173),.35); }
.tk-top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
.tk-kode { font-weight: 800; color: var(--sp-text); font-size: 1rem; letter-spacing: .02em; }
.tk-meta { font-size: 12px; font-weight: 500; color: var(--sp-sub); margin-top: 2px; }
.pill { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
.tk-row { font-size: 13px; font-weight: 500; color: #2a3a52; margin-top: 8px; }
.tk-actions { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--sp-line); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.tk-actions select, .tk-actions input {
    border: 1px solid var(--sp-line); border-radius: 12px; padding: 8px 12px; background: var(--sp-solid);
    font-size: 12.5px; font-family: inherit; font-weight: 500; color: var(--sp-text); outline: none;
}
.tk-actions select:focus, .tk-actions input:focus { border-color: var(--sp-primary); box-shadow: 0 0 0 4px rgba(var(--role-rgb,11,92,173),.14); }

/* ---------- Notifikasi & kosong ---------- */
.alert-ok, .alert-er { padding: 12px 16px; border-radius: 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
.alert-ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-er { background: #fff1f3; color: var(--sp-danger); border: 1px solid rgba(225,29,72,.25); }
.empty { text-align: center; font-size: 13px; font-weight: 500; color: var(--sp-sub); padding: 32px 12px; background: rgba(248,250,252,.9); border: 1px dashed var(--sp-line); border-radius: 16px; }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
</style>

<div class="content-wrapper">

<!-- ── HEADER ── -->
<div class="db-header">
    <div class="container-fluid">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:16px;">
            <div style="min-width:0;flex:1;">
                <span class="hdr-badge"><i class="fas fa-search-location mr-1"></i> LACAK BERKAS</span>
                <h1>Berkas Ditinggal</h1>
                <p>Pelacakan &amp; notifikasi WhatsApp — warga dapat KODE &amp; kabar otomatis saat berkas siap diambil.</p>
            </div>
            <div class="hdr-actions">
                <a href="<?= htmlspecialchars($meja_panel); ?>" class="btn-pro"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="tb-wrapper">

    <!-- ── STATISTIK ── -->
    <div class="stat-strip">
        <div class="sc sc-navy"><i class="fas fa-inbox i"></i><div class="v"><?= $cnt_aktif; ?></div><div class="l">Berkas Aktif</div></div>
        <div class="sc sc-green"><i class="fas fa-check-circle i"></i><div class="v"><?= $cnt_siap; ?></div><div class="l">Siap Diambil</div></div>
        <div class="sc sc-orange<?= $cnt_lengkap > 0 ? ' warn' : ''; ?>"><i class="fas fa-exclamation-triangle i"></i><div class="v"><?= $cnt_lengkap; ?></div><div class="l">Perlu Dilengkapi</div></div>
        <div class="sc sc-blue"><i class="fas fa-flag-checkered i"></i><div class="v"><?= $cnt_selesai; ?></div><div class="l">Selesai</div></div>
    </div>

<div class="tb-wrap">

    <!-- Form buat tiket -->
    <div>
    <div class="tb-card">
        <div class="tb-card-hd"><i class="fas fa-plus-circle mr-1"></i> Titip Berkas Baru</div>
        <div class="tb-card-bd">
            <?php if ($msg !== ''): ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg); ?></div><?php endif; ?>
            <?php if ($err !== ''): ?><div class="alert-er"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="aksi" value="buat">
                <label class="lbl">NIK (opsional)</label>
                <input type="text" name="nik" class="inp" inputmode="numeric" placeholder="Auto-isi nama bila terdaftar">
                <label class="lbl">Nama Pemohon</label>
                <input type="text" name="nama" class="inp" placeholder="Nama warga">
                <label class="lbl">No. WhatsApp *</label>
                <input type="text" name="no_wa" class="inp" inputmode="numeric" placeholder="08xxxxxxxxxx" required>
                <label class="lbl">Jenis Layanan *</label>
                <input type="text" name="jenis_layanan" class="inp" list="jenisList" placeholder="Pilih atau ketik..." required>
                <datalist id="jenisList">
                    <?php foreach (array_unique($jenis_opsi) as $jo): ?>
                    <option value="<?= htmlspecialchars($jo); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <label class="lbl">Perkiraan Selesai (opsional)</label>
                <input type="text" name="estimasi" class="inp" placeholder="mis. besok, 3 Juli, 2 hari">
                <label class="lbl">Catatan (opsional)</label>
                <textarea name="catatan" class="inp" placeholder="Alasan ditinggal / catatan internal"></textarea>
                <button type="submit" class="btn-s"><i class="fas fa-paper-plane"></i> Buat &amp; Kirim WA</button>
            </form>
        </div>
    </div>
    </div>

    <!-- Daftar tiket -->
    <div class="tb-card">
        <div class="tb-card-hd"><i class="fas fa-list-ul mr-1"></i> Daftar Berkas Ditinggal</div>
        <div class="tb-card-bd">
            <div class="filter-row">
                <?php
                $filters = ['' => 'Semua', 'AKTIF' => 'Aktif', 'SIAP_AMBIL' => 'Siap Diambil', 'PERLU_LENGKAP' => 'Perlu Lengkap', 'SELESAI' => 'Selesai', 'DITOLAK' => 'Ditolak'];
                foreach ($filters as $fk => $fl):
                    $act = ($f_status === $fk || ($fk === '' && $f_status === '')) ? 'active' : '';
                ?>
                <a class="<?= $act; ?>" href="?status=<?= urlencode($fk); ?>"><?= htmlspecialchars($fl); ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (!$rows): ?>
                <div class="empty">Belum ada berkas ditinggal.</div>
            <?php else: foreach ($rows as $r):
                $st = strtoupper((string) $r['status']);
                $m = $meta[$st] ?? ['label' => $st, 'emoji' => '•', 'warna' => '#334155', 'bg' => '#f4f6fb'];
            ?>
            <div class="tk">
                <div class="tk-top">
                    <div>
                        <div class="tk-kode"><?= htmlspecialchars((string) $r['kode']); ?></div>
                        <div class="tk-meta"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $r['tgl_titip']))); ?> · oleh <?= htmlspecialchars((string) $r['petugas']); ?></div>
                    </div>
                    <span class="pill" style="background:<?= $m['bg']; ?>;color:<?= $m['warna']; ?>;"><?= $m['emoji']; ?> <?= htmlspecialchars($m['label']); ?></span>
                </div>
                <div class="tk-row"><strong><?= htmlspecialchars((string) $r['jenis_layanan']); ?></strong></div>
                <div class="tk-row">
                    <?= htmlspecialchars((string) ($r['nama'] !== '' ? $r['nama'] : '-')); ?>
                    · <i class="fab fa-whatsapp"></i> <?= htmlspecialchars((string) $r['no_wa']); ?>
                    <?php if (trim((string) $r['estimasi']) !== ''): ?> · est: <?= htmlspecialchars((string) $r['estimasi']); ?><?php endif; ?>
                </div>
                <?php if (trim((string) ($r['catatan'] ?? '')) !== ''): ?>
                <div class="tk-row" style="color:#64748b;font-size:.78rem;"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars((string) $r['catatan']); ?></div>
                <?php endif; ?>

                <form method="post" class="tk-actions" onsubmit="return konfirmStatus(this);">
                    <input type="hidden" name="aksi" value="ubah_status">
                    <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                    <select name="status">
                        <?php foreach ($meta as $sk => $sm): ?>
                        <option value="<?= $sk; ?>" <?= $sk === $st ? 'selected' : ''; ?>><?= htmlspecialchars($sm['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="catatan" placeholder="Catatan (utk perlu lengkap/tolak)" value="<?= htmlspecialchars((string) ($r['catatan'] ?? '')); ?>" style="flex:1;min-width:160px;">
                    <button type="submit" class="btn-s btn-g btn-xs"><i class="fas fa-save"></i> Perbarui &amp; Notif</button>
                </form>
                <?php if ($can_hapus_titip): ?>
                <form method="post" onsubmit="return confirm('Hapus tiket ini?');" style="margin-top:6px;">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                    <button type="submit" class="btn-s btn-d btn-xs"><i class="fas fa-trash"></i> Hapus</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>
</div>
</div></section></div>

<script>
function konfirmStatus(f){
    var s = f.status.value;
    if (s === 'SIAP_AMBIL') return confirm('Kirim notifikasi "siap diambil" ke WhatsApp warga?');
    if (s === 'DITOLAK' || s === 'PERLU_LENGKAP') {
        if (!f.catatan.value.trim()) { alert('Mohon isi catatan/alasan dulu.'); f.catatan.focus(); return false; }
    }
    return true;
}
</script>

<?php include 'footer.php'; ?>