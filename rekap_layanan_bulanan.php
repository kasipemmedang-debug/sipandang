<?php
/**
 * Rekap semua pelayanan bulanan — Laporan Bulanan Pemerintahan.
 */
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_surat_nikah_pengantar.php';
require_once __DIR__ . '/inc_arsip_ui.php';
require_once __DIR__ . '/inc_laporan_pemerintahan.php';
sipandang_nikah_pengantar_format_ensure($koneksi);

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('n');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$hari  = isset($_GET['hari']) ? (int) $_GET['hari'] : 0;
$f_rentang = arsip_ui_rentang_tanggal_from_request();
$akhirBulan = (int) date('t', mktime(0, 0, 0, max(1, min(12, $bulan)), 1, max(2000, $tahun)));

if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $lap = sipandang_lp_build_laporan($koneksi, $bulan, $tahun, $hari, $f_rentang['dari'], $f_rentang['sampai']);
    $fn = 'LAPORAN_PELAYANAN_' . strtoupper($lap['meta']['nama_bulan']) . '_' . $lap['meta']['tahun'];
    if ((int) ($lap['meta']['hari_angka'] ?? 0) > 0) {
        $fn .= '_TGL' . (int) $lap['meta']['hari_angka'];
    }
    sipandang_lp_export_xlsx($lap, $fn);
}

$lap = sipandang_lp_build_laporan($koneksi, $bulan, $tahun, $hari, $f_rentang['dari'], $f_rentang['sampai']);
$m   = $lap['meta'];
$bulan_map = sipandang_bulan_map();
$qHari = static function (int $h = 0) use ($bulan, $tahun, $f_rentang): string {
    $q = 'bulan=' . (int) $bulan . '&tahun=' . (int) $tahun;
    if ($h > 0) {
        $q .= '&hari=' . $h;
    }
    $qr = arsip_ui_query_rentang_tanggal($f_rentang);
    if ($qr !== '') {
        $q .= '&' . $qr;
    }
    return $q;
};

include 'header.php';
include 'sidebar.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* =====================================================
   REKAP LAYANAN BULANAN — diseragamkan dengan Admin Panel, Arsip Surat & Arsip Penduduk
   SIPANDANG UI 2027 — Bento · Soft Glass · Fluid Motion
   (hanya tampilan; tidak menyentuh logika PHP)
   ===================================================== */
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
    --sp-shadow-1: 0 1px 2px rgba(15,23,42,.04), 0 6px 18px -6px rgba(15,23,42,.08);
    --sp-ease: cubic-bezier(.22, 1, .36, 1);

    /* alias lama */
    --sipa-blue: var(--role,#0b5cad);
    --sipa-dark: var(--role-ink,#08428a);
    --ui-bg: #f4f7fb;
    --ui-surface: #ffffff;
    --ui-border: rgba(15, 23, 42, .10);
    --ui-text: #0b1220;
    --ui-text-sub: #5b6b82;
    --ui-radius: 20px;
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

.rl-wrap {
    position: relative; z-index: 10;
    padding: 8px 32px 64px !important;
    max-width: 1680px; margin: 0 auto;
}
@media (max-width: 640px) { .rl-wrap { padding: 8px 16px 48px !important; } }

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
.db-header .btn-pro.bp-light {
    background: #fff !important; color: var(--sp-primary-d) !important;
    box-shadow: 0 8px 20px -8px rgba(0,0,0,.35) !important;
}
.db-header .btn-pro.bp-ghost {
    background: rgba(255,255,255,.12) !important; color: #fff !important;
    border-color: rgba(255,255,255,.3) !important;
    backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
}
.db-header .btn-pro.bp-ghost:hover { background: rgba(255,255,255,.22) !important; }

/* ---------- Tombol (pill) ---------- */
.btn-pro {
    display: inline-flex !important; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 18px !important; border-radius: 999px !important;
    font-family: inherit !important;
    font-size: 13px !important; font-weight: 700 !important;
    text-decoration: none !important; border: 1px solid transparent !important;
    cursor: pointer;
    transition: transform .3s var(--sp-ease), box-shadow .3s var(--sp-ease), background .2s, color .2s, border-color .2s;
}
.btn-pro:hover { transform: translateY(-2px); text-decoration: none !important; }
.btn-pro:active { transform: translateY(0) scale(.98); }

.bp-navy {
    background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)) !important;
    color: #fff !important;
    box-shadow: 0 10px 20px -10px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.bp-navy:hover { box-shadow: 0 14px 26px -10px rgba(var(--role-rgb,11,92,173),.7) !important; color: #fff !important; }
.bp-green {
    background: linear-gradient(135deg, #05875f, #0c855d) !important; color: #fff !important;
    box-shadow: 0 10px 20px -10px rgba(5,150,105,.6) !important;
}
.bp-green:hover { color: #fff !important; box-shadow: 0 14px 26px -10px rgba(5,150,105,.7) !important; }
.bp-gold {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.22) !important;
}
.bp-gold:hover {
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important; box-shadow: 0 10px 20px -8px rgba(var(--role-rgb,11,92,173),.6) !important;
}

/* ---------- Kartu laporan (glass) ---------- */
.rl-card {
    max-width: 1100px; margin: 0 auto;
    background: var(--sp-surface) !important;
    backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line) !important;
    border-radius: var(--sp-radius) !important;
    box-shadow: var(--sp-shadow-1) !important;
    overflow: hidden;
    animation: sp-rise .8s var(--sp-ease) .1s both;
    transition: box-shadow .4s var(--sp-ease);
}
.rl-card:hover { box-shadow: 0 2px 4px rgba(15,23,42,.04), 0 20px 40px -18px rgba(15,23,42,.16) !important; }

/* ---------- Filter ---------- */
.rl-filter {
    padding: 20px 24px !important;
    border-bottom: 1px solid var(--sp-line) !important;
    background: linear-gradient(180deg, rgba(248,250,252,.9), rgba(255,255,255,0)) !important;
}
.rl-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.rl-field { display: grid; gap: 6px; }
.rl-field label {
    margin: 0;
    font-size: 10.5px !important; font-weight: 800 !important; letter-spacing: 1px !important;
    text-transform: uppercase; color: var(--sp-sub) !important;
}
.input-slim {
    width: 100%; outline: none; height: auto;
    border: 1px solid var(--sp-line) !important;
    border-radius: 12px !important;
    padding: 10px 14px !important;
    font-family: inherit !important; font-size: 13px !important; font-weight: 500;
    color: var(--sp-text) !important; background: var(--sp-solid) !important;
    transition: border-color .2s, box-shadow .2s;
}
.input-slim:focus {
    border-color: var(--sp-primary) !important;
    box-shadow: 0 0 0 4px rgba(var(--role-rgb,11,92,173),.14) !important;
}
select.input-slim { cursor: pointer; }

/* ---------- Kop surat ---------- */
.rl-kop { padding: 32px 24px 12px; text-align: center; }
.rl-kop h2 {
    font-family: inherit !important;
    font-size: 18px !important; font-weight: 800 !important;
    color: var(--sp-text) !important; margin: 0 0 6px !important;
    text-transform: uppercase; letter-spacing: -.3px;
}
.rl-kop .sub { font-size: 12px !important; color: var(--sp-sub) !important; line-height: 1.5; font-weight: 600; letter-spacing: .3px; }

/* ---------- Meta ---------- */
.rl-meta {
    display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 12px;
    padding: 12px 20px 22px; font-size: 12px; color: var(--sp-sub);
    border-bottom: 1px solid var(--sp-line);
}
.rl-meta span {
    background: var(--sp-tint); border: 1px solid rgba(var(--role-rgb,11,92,173),.22);
    border-radius: 999px; padding: 5px 14px; font-weight: 600;
}
.rl-meta span b { color: var(--sp-primary-d); font-weight: 800; }

/* ---------- Tabel ---------- */
.table-responsive { padding: 0 !important; overflow-x: auto; }
.rl-tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
.rl-tbl th, .rl-tbl td {
    border: 0; border-bottom: 1px solid rgba(15,23,42,.05);
    padding: 14px 18px; vertical-align: middle;
    color: #2a3a52; font-weight: 500; transition: background .2s;
}
.rl-tbl th {
    background: rgba(248,250,252,.95) !important; text-align: center;
    border-bottom: 1px solid var(--sp-line);
    font-size: 10.5px !important; font-weight: 800 !important;
    letter-spacing: 1px; text-transform: uppercase;
    color: var(--sp-sub) !important;
}
.rl-tbl td.c { text-align: center; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--sp-text); }
.rl-tbl tbody tr:hover td { background: var(--sp-tint); }
.rl-tbl tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--sp-primary); }
.rl-tbl td:last-child { font-size: 11.5px; color: var(--sp-sub); }
.rl-tbl tr.total td {
    font-weight: 800 !important; background: var(--sp-tint) !important;
    color: var(--sp-primary-d) !important; border-top: 2px solid var(--sp-primary);
    border-bottom: none; font-size: 13.5px;
}
.rl-tbl tr.total td.c { color: var(--sp-primary-d); font-size: 16px; }

/* ---------- TTD cetak ---------- */
.rl-ttd-cetak { display: none; }

.btn-pro:focus-visible, .input-slim:focus-visible {
    outline: 3px solid rgba(var(--role-rgb,11,92,173),.45); outline-offset: 2px;
}
@keyframes sp-rise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
}
@media (max-width: 767px) {
    .rl-filter { padding: 16px !important; }
    .rl-field { flex: 1 1 140px; }
    .rl-tbl { min-width: 560px; }
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
html body .content-wrapper .db-header.db-header:not(#_) .btn-pro.bp-light {
    background: #ffffff !important;
    color: var(--role-ink,#08428a) !important;
    -webkit-text-fill-color: var(--role-ink,#08428a) !important;
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

/* =====================================================
   CETAK — laporan resmi: putih, tanpa efek, garis tabel tegas
   ===================================================== */
@media print {
    .main-sidebar, .main-header, .main-footer, .rl-filter, .no-print, .db-header,
    .loket-dock-wrap, .loket-fab-desktop-wrap, #loketMenuOverlay { display: none !important; }
    body, body .content-wrapper, body .content, body section.content {
        background: #fff !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .content-wrapper { margin: 0 !important; max-width: 100% !important; padding: 0 !important; }
    .rl-wrap { padding: 0 !important; max-width: 100%; }
    .rl-card {
        max-width: 100%; box-shadow: none !important; border: none !important; border-radius: 0 !important;
        background: #fff !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important;
        animation: none !important;
    }
    .rl-meta { border-bottom: none; }
    .rl-meta span { background: none; border: none; padding: 0 8px; border-radius: 0; color: #333; }
    .rl-meta span b { color: #000; }
    .rl-tbl { border-collapse: collapse; }
    .rl-tbl th, .rl-tbl td { border: 1px solid #66738f !important; padding: 8px 12px; color: #000; }
    .rl-tbl th { background: #f1f5f9 !important; color: #334155 !important; }
    .rl-tbl tr.total td { background: var(--role-soft,#eff6ff) !important; color: var(--role-ink,#08428a) !important; border-top: 2px solid var(--role-ink,#08428a) !important; }
    .rl-tbl tbody tr:hover td { background: transparent; box-shadow: none; }
    .rl-ttd-cetak {
        display: block; text-align: center; padding: 40px 20px 8px;
        page-break-inside: avoid;
    }
    .rl-ttd-cetak .jab {
        font-size: 11px; font-weight: 800; letter-spacing: 0.6px;
        color: #333; text-transform: uppercase; margin-bottom: 60px;
    }
    .rl-ttd-cetak .nama {
        font-weight: 800; font-size: 14px; color: #0a1628;
        text-transform: uppercase; margin-bottom: 4px;
    }
    .rl-ttd-cetak .nip { font-size: 12px; color: #0a1628; }
}
</style>

<div class="content-wrapper">

<!-- PAGE HEADER -->
<div class="db-header no-print">
    <div class="container-fluid">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge"><i class="fas fa-clipboard-list mr-1"></i> REKAP PELAYANAN</span>
                <h1>Rekap Layanan Bulanan</h1>
                <p>Kelurahan <?= htmlspecialchars($m['nama_kelurahan']); ?> &nbsp;·&nbsp; <?= htmlspecialchars($m['label_periode'] ?? ''); ?></p>
            </div>
            <div class="hdr-actions">
                <a href="data_siak.php" class="btn-pro bp-light"><i class="fas fa-arrow-left"></i> Arsip SIAK</a>
                <a href="laporan_bulanan.php?bulan=<?= urlencode($m['bulan']); ?>&tahun=<?= (int) $m['tahun']; ?>" class="btn-pro bp-ghost"><i class="fas fa-chart-line"></i> Laporan Mutasi Penduduk</a>
            </div>
        </div>
    </div>
</div>

<section class="content"><div class="container-fluid"><div class="rl-wrap">

    <div class="rl-card">
        <div class="rl-filter no-print">
            <form method="get" class="rl-actions">
                <div class="rl-field">
                    <label>Bulan</label>
                    <select name="bulan" class="input-slim">
                        <?php foreach ($bulan_map as $k => $v): ?>
                        <option value="<?= (int) $k; ?>" <?= (int) $bulan === (int) $k ? 'selected' : ''; ?>><?= htmlspecialchars($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rl-field">
                    <label>Tahun</label>
                    <select name="tahun" class="input-slim">
                        <?php for ($y = (int) date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y; ?>" <?= $tahun === $y ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="rl-field">
                    <label>Hari</label>
                    <select name="hari" class="input-slim">
                        <option value="0" <?= $hari === 0 ? 'selected' : ''; ?>>Semua hari</option>
                        <?php for ($d = 1; $d <= $akhirBulan; $d++): ?>
                        <option value="<?= $d; ?>" <?= $hari === $d ? 'selected' : ''; ?>><?= $d; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="rl-field">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_dari" class="input-slim" value="<?= htmlspecialchars($f_rentang['dari']); ?>">
                </div>
                <div class="rl-field">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" class="input-slim" value="<?= htmlspecialchars($f_rentang['sampai']); ?>">
                </div>
                <button type="submit" class="btn-pro bp-navy"><i class="fas fa-sync-alt"></i> Tampilkan</button>
                <button type="button" onclick="window.print()" class="btn-pro bp-green"><i class="fas fa-print"></i> Cetak</button>
                <a href="rekap_layanan_bulanan.php?<?= htmlspecialchars($qHari($hari)); ?>&export=xlsx" class="btn-pro bp-gold"><i class="fas fa-file-excel"></i> Unduh Excel</a>
            </form>
        </div>

        <div class="rl-kop">
            <h2>DATA PELAYANAN KELURAHAN <?= htmlspecialchars($m['nama_kelurahan']); ?></h2>
            <div class="sub">PEMERINTAH KABUPATEN <?= htmlspecialchars($m['nama_kab']); ?> · KECAMATAN <?= htmlspecialchars($m['nama_kec']); ?></div>
        </div>

        <div class="rl-meta">
            <?php if ((int) ($m['hari_angka'] ?? 0) > 0): ?>
            <span>Hari : <b><?= htmlspecialchars($m['hari_nama'] ?? ''); ?></b></span>
            <span>Tanggal : <b><?= htmlspecialchars($m['tgl_laporan'] ?? ''); ?></b></span>
            <?php endif; ?>
            <span>Periode : <b><?= htmlspecialchars($m['label_periode'] ?? ''); ?></b></span>
        </div>

        <div class="table-responsive">
            <table class="rl-tbl">
                <thead>
                    <tr>
                        <th width="50">NO.</th>
                        <th>URAIAN JENIS PERMOHONAN</th>
                        <th width="90">JUMLAH</th>
                        <th>KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lap['baris'] as $r): ?>
                    <tr>
                        <td class="c"><?= (int) $r['no']; ?></td>
                        <td><?= htmlspecialchars($r['uraian']); ?></td>
                        <td class="c"><?= (int) $r['jumlah']; ?></td>
                        <td style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['ket']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td></td>
                        <td>TOTAL PELAYANAN</td>
                        <td class="c"><?= (int) $lap['total']; ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rl-ttd-cetak">
            <div class="jab"><?= htmlspecialchars(strtoupper($m['kasi_jabatan'])); ?></div>
            <div class="nama"><?= htmlspecialchars($m['kasi_nama']); ?></div>
            <?php if (($m['kasi_nip'] ?? '') !== ''): ?>
            <div class="nip">NIP. <?= htmlspecialchars($m['kasi_nip']); ?></div>
            <?php endif; ?>
        </div>
    </div>

</div></div></section></div>

<?php include 'footer.php'; ?>