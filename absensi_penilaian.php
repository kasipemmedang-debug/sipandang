<?php
require_once __DIR__ . '/auth_admin.php';
include __DIR__ . '/koneksi.php';

if (!is_readable(__DIR__ . '/inc_absensi.php')) {
    http_response_code(500);
    die('Modul absensi belum lengkap: upload file inc_absensi.php ke server.');
}
require_once __DIR__ . '/inc_absensi.php';
require_once __DIR__ . '/inc_admin_toast.php';

if (!function_exists('sipandang_require_absensi_lihat')) {
    http_response_code(500);
    die('Modul absensi belum lengkap: upload auth_admin.php terbaru ke server.');
}

sipandang_require_absensi_lihat(false);

absensi_ensure_schema($koneksi);

$bulanMap = absensi_bulan_map();
$bulan = isset($_GET['bulan']) ? sprintf('%02d', (int) $_GET['bulan']) : date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$idFilter = isset($_GET['id_user']) ? (int) $_GET['id_user'] : 0;
$isPrint = isset($_GET['print']) && $_GET['print'] === '1';

function apn_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('absensi_penilaian_bulan')) {
    http_response_code(500);
    die('Modul penilaian belum lengkap: upload inc_absensi.php terbaru ke server.');
}

$rowsPenilaian = absensi_penilaian_bulan($koneksi, (int) $bulan, $tahun, $idFilter);

if (function_exists('absensi_daftar_wajib_aktif')) {
    $users = absensi_daftar_wajib_aktif($koneksi);
} else {
    $users = [];
    foreach (absensi_daftar_wajib($koneksi) as $u) {
        if ((int) ($u['wajib_aktif'] ?? 0) === 1) {
            $users[] = $u;
        }
    }
}

$statA = 0;
$statB = 0;
$statCDE = 0;
$totalSkor = 0.0;
foreach ($rowsPenilaian as $rp) {
    $nilai = strtoupper((string) ($rp['nilai'] ?? ''));
    if ($nilai === 'A') {
        $statA++;
    } elseif ($nilai === 'B') {
        $statB++;
    } else {
        $statCDE++;
    }
    $totalSkor += (float) ($rp['skor'] ?? 0);
}
$rataSkor = count($rowsPenilaian) > 0 ? round($totalSkor / count($rowsPenilaian), 1) : 0.0;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="penilaian_absensi_' . $bulan . '_' . $tahun . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Nama', 'Hadir', 'Hari Kerja', 'Kehadiran %', 'Tepat %', 'Telat %', 'Pulang Awal %', 'Alpha', 'Skor', 'Nilai', 'Keterangan']);
    foreach ($rowsPenilaian as $r) {
        fputcsv($out, [
            $r['nama'] ?? '',
            $r['hadir'] ?? 0,
            $r['hari_kerja'] ?? 0,
            $r['pct_kehadiran'] ?? 0,
            $r['pct_tepat'] ?? 0,
            $r['pct_telat'] ?? 0,
            $r['pct_cepat_pulang'] ?? 0,
            $r['alpha'] ?? 0,
            $r['skor'] ?? 0,
            $r['nilai'] ?? '',
            $r['label_nilai'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if ($isPrint) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Penilaian Absensi ' . apn_h($bulanMap[$bulan] ?? $bulan) . ' ' . $tahun . '</title>';
    echo '<style>body{font-family:sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#f4f6fb}</style></head><body>';
    echo '<h2>Penilaian Absensi Pegawai — ' . apn_h($bulanMap[$bulan] ?? $bulan) . ' ' . $tahun . '</h2>';
    echo '<table><tr><th>No</th><th>Nama</th><th>Kehadiran</th><th>Tepat</th><th>Telat</th><th>Alpha</th><th>Skor</th><th>Nilai</th></tr>';
    $no = 1;
    foreach ($rowsPenilaian as $r) {
        echo '<tr><td>' . $no++ . '</td><td>' . apn_h((string) ($r['nama'] ?? '')) . '</td>';
        echo '<td>' . apn_h((string) ($r['pct_kehadiran'] ?? 0)) . '%</td>';
        echo '<td>' . apn_h((string) ($r['pct_tepat'] ?? 0)) . '%</td>';
        echo '<td>' . apn_h((string) ($r['pct_telat'] ?? 0)) . '%</td>';
        echo '<td>' . apn_h((string) ($r['alpha'] ?? 0)) . '</td>';
        echo '<td>' . apn_h((string) ($r['skor'] ?? 0)) . '</td>';
        echo '<td>' . apn_h((string) ($r['nilai'] ?? '')) . ' · ' . apn_h((string) ($r['label_nilai'] ?? '')) . '</td></tr>';
    }
    echo '</table><script>window.print()</script></body></html>';
    exit;
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* =====================================================
   ABSENSI PENILAIAN — diseragamkan dengan Admin Panel
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
.btn-pro:active { transform: translateY(0) scale(.98); }

.bp-navy {
    background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)) !important;
    color: #fff !important;
    box-shadow: 0 10px 20px -10px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.bp-navy:hover { box-shadow: 0 14px 26px -10px rgba(var(--role-rgb,11,92,173),.7) !important; color: #fff !important; }

.dk-filter .bp-gold {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.22) !important;
}
.dk-filter .bp-gold:hover {
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important; box-shadow: 0 10px 20px -8px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.dk-filter .bp-ghost {
    background: var(--sp-solid) !important; color: var(--sp-sub) !important;
    border-color: var(--sp-line) !important;
}
.dk-filter .bp-ghost:hover {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.3) !important;
}

/* ---------- KPI bento ---------- */
.hero-kpi {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 18px; margin-bottom: 28px;
}
@media (max-width: 1200px) { .hero-kpi { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .hero-kpi { grid-template-columns: 1fr; } }

.hk {
    position: relative; overflow: hidden; isolation: isolate;
    background: var(--sp-surface) !important;
    backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line) !important;
    border-radius: var(--sp-radius) !important;
    padding: 22px 22px 20px !important;
    box-shadow: var(--sp-shadow-1) !important;
    transition: transform .45s var(--sp-ease), box-shadow .45s var(--sp-ease), border-color .3s;
    animation: sp-rise .7s var(--sp-ease) both;
}
.hk:nth-child(2) { animation-delay: .06s; }
.hk:nth-child(3) { animation-delay: .12s; }
.hk:nth-child(4) { animation-delay: .18s; }

.hk::before {
    content: ''; position: absolute; left: 22px; right: 22px; top: 0; height: 3px;
    border-radius: 0 0 6px 6px;
    background: linear-gradient(90deg, var(--sp-primary), var(--sp-accent));
    opacity: .85; transform-origin: left; transform: scaleX(.35);
    transition: transform .6s var(--sp-ease);
}
.hk::after {
    content: ''; position: absolute; inset: auto -30% -60% -30%; height: 90%;
    background: radial-gradient(closest-side, rgba(var(--role-rgb,11,92,173),.22), transparent);
    opacity: 0; transition: opacity .4s ease; z-index: -1; pointer-events: none;
}
.hk:hover {
    transform: translateY(-6px);
    border-color: rgba(var(--role-rgb,11,92,173),.45) !important;
    box-shadow: var(--sp-shadow-2) !important;
}
.hk:hover::before { transform: scaleX(1); }
.hk:hover::after  { opacity: 1; }

.hk-l {
    display: flex; align-items: center; gap: 12px;
    font-size: 11.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .9px; color: var(--sp-sub) !important; margin-bottom: 18px;
}
.hk-l i {
    width: 40px !important; height: 40px !important;
    display: grid !important; place-items: center;
    border-radius: 12px !important; font-size: 15px !important;
    background: linear-gradient(135deg, var(--role-soft,#e6f3ff), var(--role-soft,#eef0ff)) !important;
    color: var(--sp-primary-d) !important;
    transition: transform .4s var(--sp-ease), background .3s, color .3s, box-shadow .3s;
}
.hk:hover .hk-l i {
    transform: scale(1.1) rotate(-6deg);
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important;
    box-shadow: 0 10px 20px -6px rgba(var(--role-rgb,11,92,173),.55);
}
.hk-v {
    font-size: clamp(28px, 2.6vw, 38px) !important; font-weight: 800 !important;
    color: var(--sp-text) !important; line-height: 1.05 !important;
    letter-spacing: -1.4px !important; margin-bottom: 8px !important;
    font-variant-numeric: tabular-nums;
}
.hk-s { font-size: 13px !important; color: var(--sp-sub) !important; font-weight: 500 !important; }
.hk-v.ok   { color: #059669 !important; }
.hk-v.warn { color: #d97706 !important; }

/* kartu positif (nilai A) */
.hk:has(.hk-v.ok)::before { background: linear-gradient(90deg, #10b981, #34d399); }
.hk:has(.hk-v.ok):hover { border-color: rgba(16,185,129,.5) !important; box-shadow: 0 24px 48px -16px rgba(16,185,129,.3) !important; }
.hk:has(.hk-v.ok) .hk-l i { background: #dcfce7 !important; color: #059669 !important; }
.hk:has(.hk-v.ok):hover .hk-l i {
    background: linear-gradient(135deg, #0c855d, #1d845f) !important; color: #fff !important;
    box-shadow: 0 10px 20px -6px rgba(16,185,129,.5);
}
/* kartu peringatan (nilai C/D/E) */
.hk:has(.hk-v.warn) {
    background: linear-gradient(180deg, rgba(255,247,237,.95), rgba(255,255,255,.85)) !important;
    border-color: rgba(217,119,6,.25) !important;
}
.hk:has(.hk-v.warn)::before { background: linear-gradient(90deg, #f59e0b, #fb923c); transform: scaleX(1); }
.hk:has(.hk-v.warn) .hk-l i { background: #fef3c7 !important; color: #b45309 !important; }
.hk:has(.hk-v.warn):hover { border-color: #d97706 !important; box-shadow: 0 24px 48px -16px rgba(217,119,6,.3) !important; }
.hk:has(.hk-v.warn):hover .hk-l i {
    background: linear-gradient(135deg, #a36907, #be5804) !important; color: #fff !important;
    box-shadow: 0 10px 20px -6px rgba(217,119,6,.5);
}

/* ---------- Note box ---------- */
.ks-note {
    background: linear-gradient(135deg, var(--sp-tint), rgba(238,240,255,.9)) !important;
    border: 1px solid rgba(var(--role-rgb,11,92,173),.22) !important;
    border-radius: var(--sp-radius) !important;
    padding: 16px 22px !important;
    font-size: 13px !important; line-height: 1.6;
    color: var(--sp-primary-d) !important;
    margin-bottom: 22px !important;
    display: flex; align-items: flex-start; gap: 14px;
    box-shadow: var(--sp-shadow-1);
    animation: sp-rise .8s var(--sp-ease) .1s both;
}
.ks-note > i {
    flex: none; width: 30px; height: 30px; display: grid; place-items: center;
    border-radius: 9px; font-size: 13px; margin-top: 0;
    background: #fff; color: var(--sp-primary) !important;
}
.ks-note strong { color: var(--sp-text) !important; font-weight: 800; }

/* ---------- Filter ---------- */
.dk-filter {
    background: var(--sp-surface) !important;
    backdrop-filter: blur(14px) saturate(160%); -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid var(--sp-line) !important;
    border-radius: var(--sp-radius) !important;
    padding: 20px 24px !important;
    margin-bottom: 22px !important;
    box-shadow: var(--sp-shadow-1) !important;
    display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;
}
.dk-field { display: grid; gap: 6px; flex: 1; min-width: 150px; }
.dk-field label {
    font-size: 10.5px !important; font-weight: 800 !important;
    color: var(--sp-sub) !important; text-transform: uppercase;
    letter-spacing: 1px; margin: 0;
}
.dk-inp {
    border: 1px solid var(--sp-line) !important;
    border-radius: 12px !important;
    padding: 10px 14px !important; width: 100%; outline: none;
    font-family: inherit !important; font-size: 13px !important; font-weight: 500;
    color: var(--sp-text) !important; background: var(--sp-solid) !important;
    transition: border-color .2s, box-shadow .2s;
}
.dk-inp:focus {
    border-color: var(--sp-primary) !important;
    box-shadow: 0 0 0 4px rgba(var(--role-rgb,11,92,173),.14) !important;
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
    animation: sp-rise .8s var(--sp-ease) .2s both;
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
    padding: 14px 24px !important; border-bottom: 1px solid rgba(15,23,42,.05) !important;
    color: #2a3a52 !important; vertical-align: middle !important; font-weight: 500;
    font-variant-numeric: tabular-nums;
    transition: background .2s;
}
.tbl tbody tr:hover td { background: var(--sp-tint) !important; }
.tbl tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--sp-primary); }
.tbl tbody tr:last-child td { border-bottom: none !important; }

/* ---------- Badge nilai ---------- */
.ap-badge {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 11px !important; font-weight: 800 !important;
    padding: 5px 12px !important; border-radius: 999px !important;
    line-height: 1.35; white-space: nowrap; min-width: 34px;
    border: 1px solid transparent;
}
.ap-badge.ok     { background: #dcfce7 !important; color: #166534 !important; border-color: rgba(22,101,52,.15); }
.ap-badge.late   { background: #ffe4e8 !important; color: #be123c !important; border-color: rgba(225,29,72,.2); }
.ap-badge.early  { background: #fef3c7 !important; color: #92400e !important; border-color: rgba(146,64,14,.15); }
.ap-badge.manual { background: #f3e8ff !important; color: #6b21a8 !important; border-color: rgba(107,33,168,.15); }
.ap-badge.muted  { background: var(--sp-tint) !important; color: var(--sp-primary-d) !important; border-color: rgba(var(--role-rgb,11,92,173),.22); }

/* ---------- Rank & skor ---------- */
.ap-rank {
    width: 30px; height: 30px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px !important; font-weight: 800 !important;
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    transition: transform .4s var(--sp-ease);
}
.tbl tbody tr:hover .ap-rank { transform: scale(1.08) rotate(-6deg); }
.ap-rank.top {
    background: linear-gradient(135deg, #fcd34d, #f59e0b) !important;
    color: #78350f !important;
    box-shadow: 0 8px 16px -6px rgba(245,158,11,.55);
}
.ap-skor {
    font-weight: 800 !important; color: var(--sp-text) !important;
    font-size: 14px !important; letter-spacing: -.3px;
}

/* ---------- Empty state ---------- */
.ar-empty { text-align: center; color: var(--sp-sub) !important; padding: 44px 16px; font-weight: 500; }
.ar-empty i { font-size: 2rem !important; color: #c7d3e3 !important; margin-bottom: 12px; display: block; }

/* ---------- Scrollbar & fokus ---------- */
div[style*="overflow-x:auto"]::-webkit-scrollbar { height: 8px; width: 8px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-track { background: transparent; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb { background: #c7d3e3; border-radius: 10px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb:hover { background: #a9b9cf; }

.btn-pro:focus-visible, .dk-inp:focus-visible {
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

/* ---------- Print ---------- */
.print-only { display: none; }
@media print {
    .main-header, .main-sidebar, .main-footer, .dk-filter, .no-print { display: none !important; }
    .content-wrapper { margin-left: 0 !important; background: #fff !important; }
    .db-header { margin: 0 !important; padding: 0 !important; }
    .ap-wrap { padding: 12px !important; margin-top: 0 !important; }
    .hk, .panel { box-shadow: none !important; border: 1px solid #999 !important; break-inside: avoid; backdrop-filter: none !important; animation: none !important; }
    .print-only { display: block; }
    .hero-kpi { grid-template-columns: repeat(4, 1fr); }
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
html body .content-wrapper .db-header.db-header:not(#_) .hdr-badge i {
    color: inherit !important;
    -webkit-text-fill-color: currentColor !important;
}
</style>

<div class="content-wrapper">

<div class="db-header">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge"><i class="fas fa-star mr-1"></i> PENILAIAN KINERJA KEHADIRAN</span>
                <h1>Penilaian Absensi Pegawai</h1>
                <p>Skor dihitung otomatis dari rekap kehadiran bulanan — kehadiran, ketepatan waktu, telat, pulang awal, dan alpha.</p>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="ap-wrap">

    <!-- Hero KPI -->
    <div class="hero-kpi">
        <div class="hk">
            <div class="hk-l"><i class="fas fa-chart-line mr-1"></i> Rata-rata Skor</div>
            <div class="hk-v"><?= apn_h((string) $rataSkor) ?></div>
        </div>
        <div class="hk">
            <div class="hk-l"><i class="fas fa-medal mr-1"></i> Nilai A</div>
            <div class="hk-v ok"><?= number_format($statA) ?></div>
        </div>
        <div class="hk">
            <div class="hk-l"><i class="fas fa-award mr-1"></i> Nilai B</div>
            <div class="hk-v"><?= number_format($statB) ?></div>
        </div>
        <div class="hk">
            <div class="hk-l"><i class="fas fa-exclamation-circle mr-1"></i> Nilai C/D/E</div>
            <div class="hk-v warn"><?= number_format($statCDE) ?></div>
        </div>
    </div>

    <!-- Note Box -->
    <div class="ks-note">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Rumus skor:</strong> Kehadiran 35% · Tepat waktu 25% · Tidak telat 20% · Tidak pulang awal 10% · Tidak alpha 10%.
            Nilai: A ≥90 · B ≥80 · C ≥70 · D ≥60 · E &lt;60.
        </div>
    </div>

    <!-- Filter Form -->
    <form class="dk-filter no-print" method="get">
        <div class="dk-field">
            <label>Bulan</label>
            <select class="dk-inp" name="bulan">
                <?php foreach ($bulanMap as $k => $v): ?>
                <option value="<?= apn_h($k) ?>"<?= $bulan === $k ? ' selected' : '' ?>><?= apn_h($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="dk-field">
            <label>Tahun</label>
            <select class="dk-inp" name="tahun">
                <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>"<?= $tahun === $y ? ' selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="dk-field" style="flex:2;">
            <label>Pegawai</label>
            <select class="dk-inp" name="id_user">
                <option value="0">Semua pegawai wajib absen</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int) ($u['id_user'] ?? 0) ?>"<?= $idFilter === (int) ($u['id_user'] ?? 0) ? ' selected' : '' ?>>
                    <?= apn_h((string) ($u['nama_lengkap'] ?? '')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
            <button type="submit" class="btn-pro bp-navy"><i class="fas fa-search"></i> Tampilkan</button>
            <a href="?bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&export=csv" class="btn-pro bp-gold"><i class="fas fa-file-csv"></i> Export CSV</a>
            <a href="?bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&print=1" target="_blank" class="btn-pro bp-ghost"><i class="fas fa-print"></i> Cetak</a>
            <a href="absensi_rekap.php?bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>" class="btn-pro bp-ghost"><i class="fas fa-clipboard-list"></i> Rekap Detail</a>
        </div>
    </form>

    <!-- Table Panel -->
    <div class="panel">
        <div class="panel-title">
            <h3><i class="fas fa-medal mr-1"></i> Daftar Penilaian — <?= apn_h($bulanMap[$bulan] ?? $bulan) ?> <?= (int) $tahun ?></h3>
        </div>
        <div style="overflow-x:auto;">
            <?php if (empty($rowsPenilaian)): ?>
            <div class="ar-empty"><i class="fas fa-inbox"></i>Belum ada data penilaian untuk periode ini.</div>
            <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Nama Pegawai</th>
                        <th>Hadir</th>
                        <th>Kehadiran</th>
                        <th>Tepat</th>
                        <th>Telat</th>
                        <th>Pulang Awal</th>
                        <th>Alpha</th>
                        <th>Skor</th>
                        <th>Nilai</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; foreach ($rowsPenilaian as $r): ?>
                    <?php
                    $nilaiCls = function_exists('absensi_nilai_badge_cls')
                        ? absensi_nilai_badge_cls((string) ($r['nilai'] ?? ''))
                        : 'muted';
                    ?>
                    <tr>
                        <td><span class="ap-rank<?= $no <= 3 ? ' top' : '' ?>"><?= $no ?></span></td>
                        <td><strong><?= apn_h((string) ($r['nama'] ?? '')) ?></strong></td>
                        <td><?= (int) ($r['hadir'] ?? 0) ?> / <?= (int) ($r['hari_kerja'] ?? 0) ?></td>
                        <td><?= apn_h((string) ($r['pct_kehadiran'] ?? 0)) ?>%</td>
                        <td><?= apn_h((string) ($r['pct_tepat'] ?? 0)) ?>%</td>
                        <td><?= apn_h((string) ($r['pct_telat'] ?? 0)) ?>%</td>
                        <td><?= apn_h((string) ($r['pct_cepat_pulang'] ?? 0)) ?>%</td>
                        <td><?= (int) ($r['alpha'] ?? 0) ?></td>
                        <td><span class="ap-skor"><?= apn_h((string) ($r['skor'] ?? 0)) ?></span></td>
                        <td>
                            <span class="ap-badge <?= apn_h($nilaiCls) ?>"><?= apn_h((string) ($r['nilai'] ?? '')) ?></span>
                            <span style="font-size:11px;color:var(--sp-sub);margin-left:4px"><?= apn_h((string) ($r['label_nilai'] ?? '')) ?></span>
                        </td>
                    </tr>
                <?php $no++; endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>
</section>
</div>

<?php include 'footer.php'; ?>