<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_absensi.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_admin_toast.php';

sipandang_require_absensi_lihat(false);
absensi_ensure_schema($koneksi);
$isKelola = sipandang_user_can_kelola_absensi();
$filter = 'MENUNGGU';
if (array_key_exists('status', $_GET)) {
    $filter = trim((string) $_GET['status']);
}

if ($isKelola && isset($_POST['proses_izin'])) {
    $id_izin = (int) ($_POST['id_izin'] ?? 0);
    $aksi = trim((string) ($_POST['aksi'] ?? ''));
    $cat = mysqli_real_escape_string($koneksi, trim((string) ($_POST['catatan_admin'] ?? '')));
    $id_admin = (int) ($_SESSION['id_user'] ?? 0);
    if ($id_izin > 0 && in_array($aksi, ['setuju', 'tolak'], true)) {
        $st = $aksi === 'setuju' ? 'DISETUJUI' : 'DITOLAK';
        mysqli_query(
            $koneksi,
            "UPDATE tbl_absensi_izin SET status='$st', id_admin=$id_admin, catatan_admin='$cat', updated_at=NOW()
             WHERE id_izin=$id_izin AND status='MENUNGGU'"
        );
        sipandang_log_aktivitas($koneksi, 'ABSENSI', 'izin_' . $aksi, $id_izin, $_SESSION['nama'] ?? '', $cat);
        sipandang_redirect_toast('absensi_izin.php?status=MENUNGGU', $aksi === 'setuju' ? 'absensi_izin_ok' : 'absensi_izin_tolak');
    }
}

if ($isKelola && isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];
    if ($id_hapus > 0 && absensi_izin_hapus($koneksi, $id_hapus)) {
        sipandang_log_aktivitas($koneksi, 'ABSENSI', 'izin_hapus', $id_hapus, $_SESSION['nama'] ?? '', '');
        $redir = 'absensi_izin.php';
        if (isset($_GET['status'])) {
            $redir .= '?status=' . urlencode((string) $_GET['status']);
        }
        sipandang_redirect_toast($redir, 'absensi_izin_hapus');
    }
}

$daftar = absensi_izin_list_admin($koneksi, in_array($filter, ['MENUNGGU', 'DISETUJUI', 'DITOLAK'], true) ? $filter : '');
$pendingCount = absensi_izin_count_pending($koneksi);

include 'header.php';
include 'sidebar.php';

function ai_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<style>
/* =====================================================
   ABSENSI IZIN — diseragamkan dengan Admin Panel
   SIPANDANG UI 2027 — Bento · Soft Glass · Fluid Motion
   (hanya tampilan; tidak menyentuh logika PHP)
   ===================================================== */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root {
    --sp-primary: var(--role,#0b5cad);
    --sp-primary-d: var(--role-ink,#08428a);
    --sp-accent: var(--role,#6366f1);
    --sp-danger: #e11d48;

    --sp-bg: #f4f7fb;
    --sp-surface: rgba(255, 255, 255, .82);
    --sp-solid: #ffffff;
    --sp-line: rgba(15, 23, 42, .08);
    --sp-text: #0b1220;
    --sp-sub: #5b6b82;
    --sp-tint: var(--role-soft,#e8f2fd);

    --sp-radius: 20px;
    --sp-radius-sm: 12px;
    --sp-shadow-1: 0 1px 2px rgba(15,23,42,.04), 0 6px 18px -6px rgba(15,23,42,.08);
    --sp-shadow-2: 0 2px 4px rgba(15,23,42,.04), 0 24px 48px -16px rgba(var(--role-rgb,11,92,173),.28);
    --sp-ease: cubic-bezier(.22, 1, .36, 1);
}

/* ---------- Base & ambient background ---------- */
body, body .content-wrapper, body .content, body section.content {
    background: var(--sp-bg) !important;
    color: var(--sp-text) !important;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif !important;
    -webkit-font-smoothing: antialiased;
}
body .content-wrapper {
    background:
        radial-gradient(900px 420px at 8% -8%, rgba(var(--role-rgb,11,92,173),.14), transparent 60%),
        radial-gradient(800px 380px at 100% 0%, rgba(var(--role-rgb,11,92,173),.12), transparent 60%),
        var(--sp-bg) !important;
}

.ap-wrap {
    position: relative; z-index: 1;
    padding: 8px 32px 64px !important;
    max-width: 1680px; margin: 0 auto;
}
@media (max-width: 640px) { .ap-wrap { padding: 8px 16px 48px !important; } }

/* ---------- Hero header ---------- */
.db-header {
    background: transparent !important;
    border: 0 !important;
    padding: 28px 32px 8px !important;
    margin: 0 auto 12px !important;
    max-width: 1680px;
}
body .content-wrapper > .db-header > .container-fluid {
    position: relative; overflow: hidden;
    padding: 28px 32px !important;
    border-radius: 24px !important;
    background-color: var(--role-ink,#08428a) !important;
    background-image:
        radial-gradient(600px 240px at 90% -20%, rgba(255,255,255,.22), transparent 60%),
        linear-gradient(120deg, var(--role-dark2,#0d1928) 0%, var(--role-ink,#08428a) 48%, var(--role,#0b5cad) 100%) !important;
    box-shadow: 0 24px 60px -24px rgba(var(--role-rgb,11,92,173),.55) !important;
    color: #fff !important;
}
body .content-wrapper > .db-header > .container-fluid h1,
body .content-wrapper > .db-header > .container-fluid p {
    color: #fff !important;
    text-shadow: 0 1px 2px rgba(0,0,0,.15);
}
body .content-wrapper > .db-header > .container-fluid p { color: rgba(255,255,255,.88) !important; }
.db-header .container-fluid::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,.14) 1px, transparent 1px);
    background-size: 22px 22px;
    mask-image: linear-gradient(100deg, transparent 30%, #000 100%);
    -webkit-mask-image: linear-gradient(100deg, transparent 30%, #000 100%);
}
.db-header .container-fluid > * { position: relative; z-index: 1; }
@media (max-width: 640px) {
    .db-header { padding: 16px 16px 4px !important; }
    .db-header .container-fluid { padding: 22px 20px !important; }
}

.db-header h1 {
    font-size: clamp(22px, 2.4vw, 32px) !important; font-weight: 800 !important;
    color: #fff !important; letter-spacing: -.8px !important; margin: 0 0 6px !important;
}
.db-header p {
    font-size: 14px !important; color: rgba(255,255,255,.8) !important;
    font-weight: 500 !important; margin: 0 !important;
}
.db-header .hdr-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.14) !important;
    border: 1px solid rgba(255,255,255,.28) !important;
    color: #fff !important; font-size: 11px !important; font-weight: 700 !important;
    text-transform: uppercase; letter-spacing: .9px;
    padding: 6px 12px !important; border-radius: 999px !important;
    margin-bottom: 14px !important;
    backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
}
.db-header .hdr-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.db-header .btn-pro.bp-gold {
    background: #fff !important; color: var(--sp-primary-d) !important;
    box-shadow: 0 8px 20px -8px rgba(0,0,0,.35) !important;
}
.db-header .btn-pro.bp-ghost {
    background: rgba(255,255,255,.12) !important; color: #fff !important;
    border-color: rgba(255,255,255,.3) !important;
    backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
}
.db-header .btn-pro:hover { transform: translateY(-2px); }
.db-header .btn-pro.bp-ghost:hover { background: rgba(255,255,255,.22) !important; }

/* ---------- Tombol (pill) ---------- */
.btn-pro {
    display: inline-flex !important; align-items: center; gap: 8px;
    padding: 10px 18px !important; border-radius: 999px !important;
    font-family: inherit !important;
    font-size: 13px !important; font-weight: 700 !important;
    text-decoration: none !important; border: 1px solid transparent !important;
    cursor: pointer;
    transition: transform .3s var(--sp-ease), box-shadow .3s var(--sp-ease), background .2s, color .2s;
}
.btn-pro:hover { transform: translateY(-2px); text-decoration: none !important; }

/* ---------- Tabs ---------- */
.ar-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; align-items: center; }
.ar-tabs a {
    padding: 10px 18px !important; border-radius: 999px !important;
    font-size: 13px !important; font-weight: 700 !important;
    text-decoration: none !important;
    border: 1px solid var(--sp-line) !important;
    background: var(--sp-solid) !important; color: var(--sp-sub) !important;
    transition: transform .3s var(--sp-ease), background .2s, color .2s, box-shadow .3s var(--sp-ease);
}
.ar-tabs a.active {
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important; border-color: transparent !important;
    box-shadow: 0 12px 24px -10px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.ar-tabs a:hover:not(.active) {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    transform: translateY(-2px);
}

/* ---------- Panel ---------- */
.panel {
    background: var(--sp-surface) !important;
    backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line) !important;
    border-radius: var(--sp-radius) !important;
    overflow: hidden; margin-bottom: 22px;
    box-shadow: var(--sp-shadow-1) !important;
    transition: box-shadow .4s var(--sp-ease);
    animation: sp-rise .8s var(--sp-ease) .1s both;
}
.panel:hover { box-shadow: 0 2px 4px rgba(15,23,42,.04), 0 20px 40px -18px rgba(15,23,42,.16) !important; }

.panel-title {
    padding: 18px 24px !important; margin: 0 !important;
    display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    background: linear-gradient(180deg, rgba(248,250,252,.9), rgba(255,255,255,0)) !important;
    border-bottom: 1px solid var(--sp-line) !important;
}
.panel-title h3 {
    display: flex; align-items: center; gap: 12px; margin: 0;
    font-size: 12px !important; font-weight: 800 !important; color: var(--sp-text) !important;
    text-transform: uppercase !important; letter-spacing: .9px !important;
}
.panel-title h3 i {
    width: 30px; height: 30px; display: grid; place-items: center;
    border-radius: 9px; font-size: 13px !important;
    background: var(--sp-tint); color: var(--sp-primary) !important;
}

/* ---------- Tabel ---------- */
.tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
.tbl th {
    position: sticky; top: 0; z-index: 1;
    background: rgba(248,250,252,.95) !important;
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    color: var(--sp-sub) !important; font-size: 10.5px !important; font-weight: 800 !important;
    text-transform: uppercase !important; letter-spacing: 1px !important;
    padding: 13px 24px !important; text-align: left !important;
    border-bottom: 1px solid var(--sp-line) !important;
}
.tbl td {
    padding: 16px 24px !important; border-bottom: 1px solid rgba(15,23,42,.05) !important;
    color: #2a3a52 !important; vertical-align: middle !important; font-weight: 500;
    transition: background .2s;
}
.tbl tbody tr:hover td { background: var(--sp-tint) !important; }
.tbl tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--sp-primary); }
.tbl tbody tr:last-child td { border-bottom: none !important; }

/* ---------- Badge status ---------- */
.badge-status {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px !important; font-weight: 700 !important;
    padding: 5px 11px !important; border-radius: 999px !important;
    line-height: 1.35; white-space: nowrap;
    border: 1px solid transparent;
}
.badge-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .8; }
.badge-MENUNGGU  { background: #fef3c7 !important; color: #92400e !important; border-color: rgba(146,64,14,.15) !important; }
.badge-DISETUJUI { background: #dcfce7 !important; color: #166534 !important; border-color: rgba(22,101,52,.15) !important; }
.badge-DITOLAK   { background: #ffe4e8 !important; color: #be123c !important; border-color: rgba(225,29,72,.2) !important; }

/* ---------- Form aksi & input ---------- */
.ai-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 4px; }
.dk-inp-sm {
    border: 1px solid var(--sp-line) !important;
    border-radius: 12px !important;
    padding: 8px 14px !important; outline: none;
    font-family: inherit !important; font-size: 12px !important; font-weight: 500;
    color: var(--sp-text) !important; background: var(--sp-solid) !important;
    min-width: 150px; flex: 1;
    transition: border-color .2s, box-shadow .2s;
}
.dk-inp-sm:focus {
    border-color: var(--sp-primary) !important;
    box-shadow: 0 0 0 4px rgba(var(--role-rgb,11,92,173),.14) !important;
}

/* ---------- Tombol kecil ---------- */
.btn-xs {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 13px !important; font-size: 11.5px !important; font-weight: 700 !important;
    border-radius: 999px !important;
    border: 1px solid var(--sp-line); cursor: pointer; text-decoration: none !important;
    transition: transform .3s var(--sp-ease), background .2s, color .2s, border-color .2s, box-shadow .3s var(--sp-ease);
}
.btn-xs:hover { transform: translateY(-2px); text-decoration: none !important; }
.btn-xs:active { transform: translateY(0) scale(.97); }

.btn-xs.btn-success { background: #dcfce7 !important; color: #166534 !important; border-color: rgba(22,101,52,.2) !important; }
.btn-xs.btn-success:hover {
    background: linear-gradient(135deg, #05875f, #0c855d) !important; color: #fff !important;
    border-color: transparent !important; box-shadow: 0 10px 18px -8px rgba(5,150,105,.6);
}
.btn-xs.btn-danger { background: #ffe4e8 !important; color: #be123c !important; border-color: rgba(225,29,72,.22) !important; }
.btn-xs.btn-danger:hover {
    background: linear-gradient(135deg, #e11d48, #be5804) !important; color: #fff !important;
    border-color: transparent !important; box-shadow: 0 10px 18px -8px rgba(225,29,72,.6);
}
.btn-xs.btn-outline-secondary { background: var(--sp-solid) !important; color: var(--sp-sub) !important; border-color: var(--sp-line) !important; }
.btn-xs.btn-outline-secondary:hover {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.35) !important; box-shadow: 0 10px 18px -10px rgba(var(--role-rgb,11,92,173),.5);
}
.btn-xs.btn-outline-danger { background: var(--sp-solid) !important; color: var(--sp-danger) !important; border-color: rgba(225,29,72,.25) !important; }
.btn-xs.btn-outline-danger:hover {
    background: #fff1f3 !important; border-color: var(--sp-danger) !important;
    box-shadow: 0 10px 18px -10px rgba(225,29,72,.5);
}

/* ---------- Empty state ---------- */
.ar-empty { text-align: center; color: var(--sp-sub) !important; padding: 48px 16px; font-weight: 500; }
.ar-empty i { font-size: 2.2rem !important; color: #c7d3e3 !important; margin-bottom: 14px; display: block; }

/* ---------- Scrollbar & fokus ---------- */
div[style*="overflow-x:auto"]::-webkit-scrollbar { height: 8px; width: 8px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-track { background: transparent; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb { background: #c7d3e3; border-radius: 10px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb:hover { background: #a9b9cf; }

.btn-pro:focus-visible, .btn-xs:focus-visible, .ar-tabs a:focus-visible, .dk-inp-sm:focus-visible {
    outline: 3px solid rgba(var(--role-rgb,11,92,173),.45); outline-offset: 2px;
}

/* ---------- Motion ---------- */
@keyframes sp-rise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
}

/* =====================================================
   ANTI-BENTROK: header dikunci dengan spesifisitas tinggi
   supaya tidak ditimpa CSS dari header.php / sidebar.php
   ===================================================== */
html body .content-wrapper .db-header.db-header:not(#_) {
    background: transparent !important;
    background-image: none !important;
    border: 0 !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .container-fluid {
    background-color: var(--role-ink,#08428a) !important;
    background-image:
        radial-gradient(600px 240px at 90% -20%, rgba(255,255,255,.22), transparent 60%),
        linear-gradient(120deg, var(--role-dark2,#0d1928) 0%, var(--role-ink,#08428a) 48%, var(--role,#0b5cad) 100%) !important;
    border-radius: 24px !important;
    box-shadow: 0 24px 60px -24px rgba(var(--role-rgb,11,92,173),.55) !important;
}
html body .content-wrapper .db-header.db-header:not(#_) h1,
html body .content-wrapper .db-header.db-header:not(#_) h1 * {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    background: none !important;
    -webkit-background-clip: border-box !important;
            background-clip: border-box !important;
    opacity: 1 !important;
    filter: none !important;
    mix-blend-mode: normal !important;
    text-shadow: 0 2px 12px rgba(0,0,0,.25) !important;
}
html body .content-wrapper .db-header.db-header:not(#_) p {
    color: rgba(255,255,255,.9) !important;
    -webkit-text-fill-color: rgba(255,255,255,.9) !important;
    opacity: 1 !important;
    filter: none !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge {
    background: rgba(255,255,255,.16) !important;
    border: 1px solid rgba(255,255,255,.32) !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge i,
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro i {
    color: inherit !important;
    -webkit-text-fill-color: currentColor !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro.bp-gold {
    background: #ffffff !important;
    color: var(--role-ink,#08428a) !important;
    -webkit-text-fill-color: var(--role-ink,#08428a) !important;
    border-color: transparent !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro.bp-ghost {
    background: rgba(255,255,255,.14) !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    border: 1px solid rgba(255,255,255,.34) !important;
}
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro.bp-ghost:hover {
    background: rgba(255,255,255,.26) !important;
}
</style>

<div class="content-wrapper">

<div class="db-header">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge"><i class="fas fa-file-medical mr-1"></i> ABSENSI</span>
                <h1>Persetujuan Izin Pegawai</h1>
                <p>Sakit, izin, dinas luar, cuti &nbsp;·&nbsp; <?= (int) $pendingCount ?> menunggu persetujuan</p>
            </div>
            <div class="hdr-actions">
                <a href="absensi_rekap.php" class="btn-pro bp-ghost"><i class="fas fa-arrow-left"></i> Rekap Absensi</a>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="ap-wrap">

    <!-- Tabs -->
    <div class="ar-tabs no-print">
        <a href="absensi_izin.php?status=MENUNGGU" class="<?= $filter === 'MENUNGGU' ? 'active' : '' ?>">Menunggu<?= $pendingCount ? ' (' . $pendingCount . ')' : '' ?></a>
        <a href="absensi_izin.php?status=DISETUJUI" class="<?= $filter === 'DISETUJUI' ? 'active' : '' ?>">Disetujui</a>
        <a href="absensi_izin.php?status=DITOLAK" class="<?= $filter === 'DITOLAK' ? 'active' : '' ?>">Ditolak</a>
        <a href="absensi_izin.php?status=" class="<?= $filter === '' ? 'active' : '' ?>">Semua</a>
    </div>

    <!-- Table Panel -->
    <div class="panel">
        <div class="panel-title">
            <h3><i class="fas fa-list-alt mr-1"></i> Daftar Pengajuan Izin</h3>
        </div>
        <div style="overflow-x:auto;">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Pegawai</th>
                        <th>Rentang Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th style="width:280px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="6"><div class="ar-empty"><i class="fas fa-inbox"></i>Tidak ada pengajuan.</div></td></tr>
                <?php else: foreach ($daftar as $iz):
                    $idI = (int) $iz['id_izin'];
                    $rentang = date('d M Y', strtotime((string) $iz['tanggal_mulai']));
                    if ($iz['tanggal_selesai'] !== $iz['tanggal_mulai']) {
                        $rentang .= ' – ' . date('d M Y', strtotime((string) $iz['tanggal_selesai']));
                    }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700;color:var(--sp-text);"><?= ai_h((string) ($iz['nama_lengkap'] ?? '')) ?></div>
                            <div style="font-size:11px;color:var(--sp-sub);"><?= ai_h((string) ($iz['level'] ?? '')) ?></div>
                        </td>
                        <td style="white-space:nowrap;"><?= ai_h($rentang) ?></td>
                        <td><?= ai_h(absensi_jenis_izin_label((string) ($iz['jenis'] ?? 'IZIN'))) ?></td>
                        <td>
                            <div style="max-width:250px;">
                                <?= ai_h((string) ($iz['keterangan'] ?? '')) ?>
                                <?php if (!empty($iz['lampiran'])): ?>
                                <br><a href="<?= ai_h((string) $iz['lampiran']) ?>" target="_blank" style="font-size:11px;font-weight:700;color:var(--sp-primary-d);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:4px;"><i class="fas fa-paperclip"></i> Lihat lampiran</a>
                                <?php endif; ?>
                                <?php if (!empty($iz['catatan_admin'])): ?>
                                <br><em style="font-size:11px;color:var(--sp-sub);display:block;margin-top:4px;padding-top:4px;border-top:1px dashed var(--sp-line);">Admin: <?= ai_h((string) $iz['catatan_admin']) ?></em>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge-status badge-<?= ai_h((string) ($iz['status'] ?? '')) ?>"><?= ai_h((string) ($iz['status'] ?? '')) ?></span></td>
                        <td>
                            <?php if ($isKelola && ($iz['status'] ?? '') === 'MENUNGGU'): ?>
                            <form method="post" class="ai-form">
                                <input type="hidden" name="proses_izin" value="1">
                                <input type="hidden" name="id_izin" value="<?= $idI ?>">
                                <input type="text" name="catatan_admin" class="dk-inp-sm" placeholder="Catatan (opsional)">
                                <button type="submit" name="aksi" value="setuju" class="btn-xs btn-success"><i class="fas fa-check"></i> Setujui</button>
                                <button type="submit" name="aksi" value="tolak" class="btn-xs btn-danger" onclick="return confirm('Tolak pengajuan ini?')"><i class="fas fa-times"></i> Tolak</button>
                            </form>
                            <div style="margin-top:8px;">
                                <a href="absensi_izin.php?hapus=<?= $idI ?>&amp;status=<?= urlencode($filter) ?>" class="btn-xs btn-outline-danger" onclick="return confirm('Hapus pengajuan izin ini permanen?')"><i class="fas fa-trash"></i> Hapus Permanen</a>
                            </div>
                            <?php elseif ($isKelola): ?>
                            <a href="absensi_izin.php?hapus=<?= $idI ?>&amp;status=<?= urlencode($filter) ?>" class="btn-xs btn-outline-danger" onclick="return confirm('Hapus pengajuan izin ini permanen?')"><i class="fas fa-trash"></i> Hapus</a>
                            <?php else: ?>
                            <span style="color:var(--sp-sub);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>
</section>
</div>

<?php include 'footer.php'; ?>