<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_absensi.php';
require_once __DIR__ . '/inc_sipelamar.php';
require_once __DIR__ . '/inc_admin_toast.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_berkas_storage.php';

if (!function_exists('sipandang_require_absensi_lihat')) {
    http_response_code(500);
    die('Modul auth belum lengkap.');
}
sipandang_require_absensi_lihat(false);

sipelamar_ensure_schema($koneksi);
absensi_ensure_schema($koneksi);

$bulanMap = absensi_bulan_map();
$bulan = isset($_GET['bulan']) ? sprintf('%02d', (int) $_GET['bulan']) : date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$idFilter = isset($_GET['id_user']) ? (int) $_GET['id_user'] : 0;
$tab = isset($_GET['tab']) && $_GET['tab'] === 'layanan' ? 'layanan' : 'absensi';
$isPrint = isset($_GET['print']) && $_GET['print'] === '1';
$isKelola = sipandang_user_can_kelola_absensi();
$isKasi = sipandang_user_is_kasi();

function sr_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function sr_row_attr(array $row): string
{
    $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = '{}';
    }
    return sr_h(base64_encode($json));
}

function sr_status_label(string $st, int $detik = 0): string
{
    if ($st === 'TELAT' && $detik > 0) {
        return 'Telat · ' . absensi_format_durasi($detik);
    }
    if ($st === 'CEPAT' && $detik > 0) {
        return 'Pulang awal · ' . absensi_format_durasi($detik);
    }
    if ($st === 'TEPAT') {
        return 'Tepat waktu';
    }
    if ($st === 'MANUAL') {
        return 'Koreksi manual';
    }
    return $st !== '' ? $st : '—';
}

function sr_status_cls(string $st): string
{
    if ($st === 'TELAT') {
        return 'late';
    }
    if ($st === 'CEPAT') {
        return 'early';
    }
    if ($st === 'TEPAT') {
        return 'ok';
    }
    if ($st === 'MANUAL') {
        return 'manual';
    }
    return 'muted';
}

function sr_rekap_url(string $bulan, int $tahun, int $idFilter, string $tab): string
{
    return 'sipelamar_rekap.php?bulan=' . urlencode($bulan)
        . '&tahun=' . $tahun
        . '&id_user=' . $idFilter
        . '&tab=' . urlencode($tab);
}

if ($isKelola && isset($_GET['hapus'])) {
    $id_abs = (int) $_GET['hapus'];
    if ($id_abs > 0 && sipelamar_hapus_presensi($koneksi, $id_abs)) {
        sipandang_log_aktivitas($koneksi, 'SIPELAMAR', 'hapus_presensi', $id_abs, $_SESSION['nama'] ?? '', '');
        sipandang_redirect_toast(sr_rekap_url($bulan, $tahun, $idFilter, 'absensi'), 'sipelamar_hapus');
    }
}

if ($isKelola && isset($_GET['hapus_surat'])) {
    $id_s = (int) $_GET['hapus_surat'];
    if ($id_s > 0) {
        $ck = @mysqli_query(
            $koneksi,
            "SELECT id_surat, no_surat FROM tbl_surat_keluar
             WHERE id_surat=$id_s AND pelayanan_sipelamar=1 LIMIT 1"
        );
        $row = ($ck instanceof mysqli_result && mysqli_num_rows($ck) > 0)
            ? (mysqli_fetch_assoc($ck) ?: null) : null;
        if ($row) {
            sipandang_berkas_purge_surat_keluar($koneksi, $id_s);
            @mysqli_query($koneksi, "DELETE FROM tbl_surat_keluar WHERE id_surat=$id_s");
            sipandang_log_aktivitas(
                $koneksi,
                'SIPELAMAR',
                'hapus_surat',
                $id_s,
                (string) ($row['no_surat'] ?? ''),
                'Hapus arsip layanan malam'
            );
            sipandang_redirect_toast(sr_rekap_url($bulan, $tahun, $idFilter, 'layanan'), 'sipelamar_hapus_surat');
        }
    }
}

$absensiRows = sipelamar_rekap_absensi_bulan($koneksi, (int) $bulan, $tahun, $idFilter);
$layananRows = sipelamar_rekap_layanan_bulan($koneksi, (int) $bulan, $tahun);
$jadwalRows = sipelamar_jadwal_bulan($koneksi, (int) $bulan, $tahun);
$operators = sipelamar_operators_list($koneksi);

$statTotal = count($absensiRows);
$statTelat = 0;
$statCepat = 0;
$statBelumPulang = 0;
foreach ($absensiRows as $sr) {
    if (($sr['status_datang_sipelamar'] ?? '') === 'TELAT') {
        $statTelat++;
    }
    if (($sr['status_pulang_sipelamar'] ?? '') === 'CEPAT') {
        $statCepat++;
    }
    if (empty($sr['jam_pulang_sipelamar'])) {
        $statBelumPulang++;
    }
}

if ($isPrint && $isKasi) {
    echo '<script>alert("Akses ditolak."); window.location="sipelamar_rekap.php";</script>';
    exit;
}

if ($isPrint) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Rekap SiPELAMAR ' . sr_h($bulanMap[$bulan] ?? $bulan) . ' ' . $tahun . '</title>';
    echo '<style>body{font-family:sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#f4f6fb}</style></head><body>';
    echo '<h2>Rekap SiPELAMAR — ' . sr_h($bulanMap[$bulan] ?? $bulan) . ' ' . $tahun . '</h2>';
    echo '<table><tr><th>Tanggal</th><th>Operator</th><th>Datang</th><th>Pulang</th><th>Status</th></tr>';
    foreach ($absensiRows as $r) {
        echo '<tr><td>' . sr_h((string) $r['tanggal']) . '</td><td>' . sr_h((string) ($r['nama_lengkap'] ?? '')) . '</td>';
        echo '<td>' . sr_h(substr((string) ($r['jam_datang_sipelamar'] ?? '—'), 0, 8)) . '</td>';
        echo '<td>' . sr_h(substr((string) ($r['jam_pulang_sipelamar'] ?? '—'), 0, 8)) . '</td>';
        $sd = (string) ($r['status_datang_sipelamar'] ?? '');
        $sp = (string) ($r['status_pulang_sipelamar'] ?? '');
        echo '<td>' . sr_h($sd) . ' / ' . sr_h($sp) . '</td></tr>';
    }
    echo '</table><script>window.print()</script></body></html>';
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $tab === 'absensi' && !$isKasi) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap_sipelamar_' . $bulan . '_' . $tahun . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tanggal', 'Operator', 'Datang', 'Pulang', 'Status Datang', 'Status Pulang', 'Telat Detik', 'Jarak Datang (m)']);
    foreach ($absensiRows as $r) {
        fputcsv($out, [
            $r['tanggal'], $r['nama_lengkap'] ?? '',
            $r['jam_datang_sipelamar'] ?? '', $r['jam_pulang_sipelamar'] ?? '',
            $r['status_datang_sipelamar'] ?? '', $r['status_pulang_sipelamar'] ?? '',
            $r['telat_sipelamar_detik'] ?? 0, $r['jarak_datang_sipelamar'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

include 'header.php';
include 'sidebar.php';
?>
<style>
/* =====================================================
   SIPELAMAR REKAP — diseragamkan dengan Admin Panel
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
.btn-pro:active { transform: translateY(0) scale(.98); }

.bp-navy {
    background: linear-gradient(135deg, var(--sp-primary-d), var(--sp-primary)) !important;
    color: #fff !important;
    box-shadow: 0 10px 20px -10px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.bp-navy:hover { box-shadow: 0 14px 26px -10px rgba(var(--role-rgb,11,92,173),.7) !important; color: #fff !important; }

.dk-filter .bp-gold, .ar-tabs .bp-gold {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.22) !important;
}
.dk-filter .bp-gold:hover {
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important; box-shadow: 0 10px 20px -8px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.dk-filter .bp-ghost, .ar-tabs .bp-ghost {
    background: var(--sp-solid) !important; color: var(--sp-sub) !important;
    border-color: var(--sp-line) !important;
}
.dk-filter .bp-ghost:hover, .ar-tabs .bp-ghost:hover {
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

/* state peringatan */
.hk:has(.hk-v.danger) {
    background: linear-gradient(180deg, rgba(255,241,243,.95), rgba(255,255,255,.85)) !important;
    border-color: rgba(225,29,72,.25) !important;
}
.hk:has(.hk-v.danger)::before { background: linear-gradient(90deg, #f43f5e, #fb923c); transform: scaleX(1); }
.hk:has(.hk-v.danger)::after  { background: radial-gradient(closest-side, rgba(225,29,72,.18), transparent); }
.hk:has(.hk-v.danger) .hk-l i { background: #ffe4e8 !important; color: var(--sp-danger) !important; }
.hk:has(.hk-v.danger):hover   { border-color: var(--sp-danger) !important; box-shadow: 0 24px 48px -16px rgba(225,29,72,.3) !important; }
.hk:has(.hk-v.danger):hover .hk-l i {
    background: linear-gradient(135deg, #e80d33, #be5804) !important; color: #fff !important;
    box-shadow: 0 10px 20px -6px rgba(225,29,72,.5);
}
.hk-v.danger { color: var(--sp-danger) !important; }
.hk-v.warn   { color: #d97706 !important; }

/* ---------- Tabs ---------- */
.ar-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; align-items: center; }
.ar-tabs > a:not(.btn-pro) {
    padding: 10px 18px !important; border-radius: 999px !important;
    font-size: 13px !important; font-weight: 700 !important;
    text-decoration: none !important;
    border: 1px solid var(--sp-line) !important;
    background: var(--sp-solid) !important; color: var(--sp-sub) !important;
    transition: transform .3s var(--sp-ease), background .2s, color .2s, box-shadow .3s var(--sp-ease);
}
.ar-tabs > a:not(.btn-pro).active {
    background: linear-gradient(135deg, var(--sp-primary), var(--sp-accent)) !important;
    color: #fff !important; border-color: transparent !important;
    box-shadow: 0 12px 24px -10px rgba(var(--role-rgb,11,92,173),.6) !important;
}
.ar-tabs > a:not(.btn-pro):hover:not(.active) {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    transform: translateY(-2px);
}

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

/* ---------- Panels ---------- */
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
    padding: 18px 24px !important;
    margin: 0 !important;
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
.meta { font-size: 12px !important; color: var(--sp-sub) !important; font-weight: 500 !important; }

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
    transition: background .2s;
}
.tbl tbody tr:hover td { background: var(--sp-tint) !important; }
.tbl tbody tr:hover td:first-child { box-shadow: inset 3px 0 0 var(--sp-primary); }
.tbl tbody tr:last-child td { border-bottom: none !important; }
.tbl code {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    padding: 3px 8px !important; border-radius: 8px !important; font-size: 11px !important;
    font-weight: 700;
}

/* ---------- Badges ---------- */
.ar-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px !important; font-weight: 700 !important;
    padding: 5px 11px !important; border-radius: 999px !important;
    line-height: 1.35; white-space: nowrap;
    border: 1px solid transparent;
}
.ar-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .8; }
.ar-badge.ok     { background: #dcfce7 !important; color: #166534 !important; border-color: rgba(22,101,52,.15); }
.ar-badge.late   { background: #ffe4e8 !important; color: #be123c !important; border-color: rgba(225,29,72,.2); }
.ar-badge.early  { background: #fef3c7 !important; color: #92400e !important; border-color: rgba(146,64,14,.15); }
.ar-badge.manual { background: #f3e8ff !important; color: #6b21a8 !important; border-color: rgba(107,33,168,.15); }
.ar-badge.muted  { background: var(--sp-tint) !important; color: var(--sp-primary-d) !important; border-color: rgba(var(--role-rgb,11,92,173),.22); }

/* ---------- Aksi baris ---------- */
.ar-row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.btn-xs {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px !important; font-size: 11.5px !important; font-weight: 700 !important;
    border-radius: 999px !important;
    border: 1px solid var(--sp-line) !important; background: var(--sp-solid) !important;
    color: var(--sp-sub) !important; cursor: pointer; text-decoration: none !important;
    transition: transform .3s var(--sp-ease), background .2s, color .2s, border-color .2s, box-shadow .3s var(--sp-ease);
}
.btn-xs:hover {
    background: var(--sp-tint) !important; color: var(--sp-primary-d) !important;
    border-color: rgba(var(--role-rgb,11,92,173),.35) !important; transform: translateY(-2px);
    box-shadow: 0 10px 18px -10px rgba(var(--role-rgb,11,92,173),.5);
}
.btn-xs.btn-outline-danger { border-color: rgba(225,29,72,.25) !important; color: var(--sp-danger) !important; }
.btn-xs.btn-outline-danger:hover {
    background: #fff1f3 !important; border-color: var(--sp-danger) !important;
    box-shadow: 0 10px 18px -10px rgba(225,29,72,.5);
}

/* ---------- Empty state ---------- */
.ar-empty { text-align: center; color: var(--sp-sub) !important; padding: 44px 16px; font-weight: 500; }
.ar-empty i { font-size: 2rem !important; color: #c7d3e3 !important; margin-bottom: 12px; display: block; }

/* ---------- Modal ---------- */
.modal-content {
    border: 1px solid var(--sp-line) !important; border-radius: var(--sp-radius) !important;
    box-shadow: 0 30px 70px -20px rgba(15,23,42,.35) !important; overflow: hidden;
    font-family: inherit;
}
.modal-header {
    background: linear-gradient(180deg, rgba(248,250,252,.95), #fff) !important;
    border-bottom: 1px solid var(--sp-line) !important; padding: 18px 24px !important;
}
.modal-title { font-weight: 800 !important; color: var(--sp-text) !important; font-size: 15px !important; letter-spacing: -.2px; }
.modal-body { padding: 24px !important; }

.ar-detail-hd { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--sp-line); }
.ar-detail-hd .nama { font-size: 20px !important; font-weight: 800 !important; color: var(--sp-text) !important; letter-spacing: -.5px; margin-bottom: 4px; }
.ar-detail-hd .sub { font-size: 13px !important; color: var(--sp-sub) !important; }
.ar-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
@media (max-width: 767px) { .ar-detail-grid { grid-template-columns: 1fr; } }
.ar-detail-box {
    background: linear-gradient(180deg, var(--sp-tint), #fff) !important;
    border: 1px solid var(--sp-line) !important;
    border-radius: 16px !important; padding: 18px;
}
.ar-detail-box h6 {
    font-size: 10.5px !important; font-weight: 800 !important; color: var(--sp-sub) !important;
    text-transform: uppercase; letter-spacing: 1px; margin: 0 0 10px;
}
.ar-detail-box .jam {
    font-size: 26px !important; font-weight: 800 !important;
    color: var(--sp-text) !important; letter-spacing: -.8px; margin-bottom: 10px;
    font-variant-numeric: tabular-nums;
}
.ar-detail-box img {
    width: 100%; max-width: 220px; border-radius: 12px; border: 1px solid var(--sp-line);
    margin-top: 12px; display: block; box-shadow: var(--sp-shadow-1);
}
.ar-detail-meta a { color: var(--sp-primary-d); font-weight: 700; text-decoration: none; border-bottom: 1px solid rgba(var(--role-rgb,11,92,173),.35); }
.ar-detail-meta a:hover { border-bottom-color: var(--sp-primary-d); }

/* ---------- Scrollbar & fokus ---------- */
div[style*="overflow-x:auto"]::-webkit-scrollbar { height: 8px; width: 8px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-track { background: transparent; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb { background: #c7d3e3; border-radius: 10px; }
div[style*="overflow-x:auto"]::-webkit-scrollbar-thumb:hover { background: #a9b9cf; }

.btn-pro:focus-visible, .btn-xs:focus-visible, .ar-tabs a:focus-visible, .dk-inp:focus-visible {
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
    .main-header, .main-sidebar, .main-footer, .dk-filter, .no-print, .ar-tabs { display: none !important; }
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
                <span class="hdr-badge"><i class="fas fa-moon mr-1"></i> SIPELAMAR</span>
                <h1>Rekap SiPELAMAR</h1>
                <p><?= sr_h($bulanMap[$bulan] ?? $bulan) ?> <?= (int) $tahun ?> &nbsp;·&nbsp; Shift malam operator piket &nbsp;·&nbsp; Kelurahan Medang</p>
            </div>
            <div class="hdr-actions">
                <?php if ($isKelola): ?>
                <a class="btn-pro bp-gold" href="sipelamar_pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a>
                <a class="btn-pro bp-ghost" target="_blank" href="sipelamar_cetak.php?bulan=<?= (int) $bulan ?>&tahun=<?= (int) $tahun ?>"><i class="fas fa-print"></i> Cetak Jadwal</a>
                <?php endif; ?>
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
                <div class="hk-l"><i class="fas fa-clipboard-list mr-1"></i> Total Rekam</div>
                <div class="hk-v"><?= (int) $statTotal ?></div>
            </div>
            <div class="hk">
                <div class="hk-l"><i class="fas fa-calendar-alt mr-1"></i> Jadwal Piket</div>
                <div class="hk-v"><?= count($jadwalRows) ?></div>
            </div>
            <div class="hk">
                <div class="hk-l"><i class="fas fa-exclamation-triangle mr-1"></i> Telat Datang</div>
                <div class="hk-v warn"><?= (int) $statTelat ?></div>
            </div>
            <div class="hk">
                <div class="hk-l"><i class="fas fa-clock mr-1"></i> Belum Pulang</div>
                <div class="hk-v danger"><?= (int) $statBelumPulang ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="ar-tabs no-print">
            <a href="sipelamar_rekap.php?bulan=<?= urlencode($bulan) ?>&tahun=<?= (int) $tahun ?>&id_user=<?= $idFilter ?>&tab=absensi" class="<?= $tab === 'absensi' ? 'active' : '' ?>">Absensi Shift (<?= (int) $statTotal ?>)</a>
            <a href="sipelamar_rekap.php?bulan=<?= urlencode($bulan) ?>&tahun=<?= (int) $tahun ?>&id_user=<?= $idFilter ?>&tab=layanan" class="<?= $tab === 'layanan' ? 'active' : '' ?>">Arsip Layanan Malam (<?= count($layananRows) ?>)</a>
            <?php if ($isKelola): ?>
            <a href="sipelamar_jadwal.php" class="btn-pro bp-ghost" style="margin-left:auto"><i class="fas fa-calendar-alt"></i> Jadwal Piket</a>
            <?php endif; ?>
        </div>

        <!-- Filter Form -->
        <form class="dk-filter no-print" method="get">
            <input type="hidden" name="tab" value="<?= sr_h($tab) ?>">
            <div class="dk-field">
                <label>Bulan</label>
                <select class="dk-inp" name="bulan"><?php foreach ($bulanMap as $k => $v): ?>
                    <option value="<?= sr_h($k) ?>" <?= $k === $bulan ? 'selected' : '' ?>><?= sr_h($v) ?></option>
                <?php endforeach; ?></select>
            </div>
            <div class="dk-field">
                <label>Tahun</label>
                <input class="dk-inp" type="number" name="tahun" value="<?= (int) $tahun ?>" min="2020" max="2099">
            </div>
            <div class="dk-field" style="flex:2;">
                <label>Operator</label>
                <select class="dk-inp" name="id_user">
                    <option value="0">Semua operator</option>
                    <?php foreach ($operators as $u): ?>
                    <option value="<?= (int) $u['id_user'] ?>" <?= $idFilter === (int) $u['id_user'] ? 'selected' : '' ?>><?= sr_h((string) ($u['nama_lengkap'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                <button type="submit" class="btn-pro bp-navy"><i class="fas fa-search"></i> Tampilkan</button>
                <?php if ($tab === 'absensi' && !$isKasi): ?>
                <a class="btn-pro bp-gold" href="sipelamar_rekap.php?bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&tab=absensi&export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>
                <a class="btn-pro bp-ghost" target="_blank" href="sipelamar_rekap.php?bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&print=1"><i class="fas fa-print"></i> Cetak</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Jadwal Piket Panel -->
        <div class="panel">
            <div class="panel-title">
                <h3><i class="fas fa-calendar-check mr-1"></i> Jadwal Piket Bulan Ini</h3>
                <span class="meta"><?= count($jadwalRows) ?> malam terjadwal</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="tbl" style="min-width:480px">
                    <thead>
                        <tr><th>Tanggal</th><th>Operator Piket</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($jadwalRows === []): ?>
                        <tr><td colspan="2"><div class="ar-empty"><i class="fas fa-calendar-times"></i>Belum ada jadwal piket bulan ini.</div></td></tr>
                    <?php else: foreach ($jadwalRows as $j): ?>
                        <tr>
                            <td><?= sr_h(date('d M Y', strtotime((string) $j['tanggal']))) ?></td>
                            <td><strong><?= sr_h($j['nama_1'] ?? '') ?></strong> &amp; <strong><?= sr_h($j['nama_2'] ?? '') ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($tab === 'absensi'): ?>
        <!-- Absensi Table -->
        <div class="panel">
            <div class="panel-title">
                <h3><i class="fas fa-table mr-1"></i> Data Absensi SiPELAMAR</h3>
                <span class="meta"><?= (int) $statTotal ?> baris · Telat: <?= (int) $statTelat ?> · Pulang awal: <?= (int) $statCepat ?></span>
            </div>
            <div style="overflow-x:auto;">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Operator</th>
                            <th>Datang</th>
                            <th>Pulang</th>
                            <th>Status Datang</th>
                            <th>Status Pulang</th>
                            <th>GPS</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($absensiRows === []): ?>
                        <tr><td colspan="8"><div class="ar-empty"><i class="fas fa-inbox"></i>Belum ada presensi SiPELAMAR untuk periode ini.</div></td></tr>
                    <?php else: foreach ($absensiRows as $r):
                        $stD = (string) ($r['status_datang_sipelamar'] ?? '');
                        $stP = (string) ($r['status_pulang_sipelamar'] ?? '');
                        $telatDetik = (int) ($r['telat_sipelamar_detik'] ?? 0);
                        $cepatDetik = (int) ($r['pulang_cepat_sipelamar_detik'] ?? 0);
                        $rowData = [
                            'id' => (int) ($r['id_absensi'] ?? 0),
                            'nama' => $r['nama_lengkap'] ?? '',
                            'tanggal' => $r['tanggal'] ?? '',
                            'jam_datang' => $r['jam_datang_sipelamar'] ?? '',
                            'jam_pulang' => $r['jam_pulang_sipelamar'] ?? '',
                            'status_datang' => $stD,
                            'status_pulang' => $stP,
                            'telat_detik' => $telatDetik,
                            'pulang_cepat_detik' => $cepatDetik,
                            'foto_datang' => $r['foto_datang_sipelamar'] ?? '',
                            'foto_pulang' => $r['foto_pulang_sipelamar'] ?? '',
                            'lat_datang' => $r['lat_datang_sipelamar'] ?? '',
                            'lng_datang' => $r['lng_datang_sipelamar'] ?? '',
                            'lat_pulang' => $r['lat_pulang_sipelamar'] ?? '',
                            'lng_pulang' => $r['lng_pulang_sipelamar'] ?? '',
                            'jarak_datang' => $r['jarak_datang_sipelamar'] ?? '',
                            'jarak_pulang' => $r['jarak_pulang_sipelamar'] ?? '',
                            'gps_mock_datang' => (int) ($r['gps_mock_sipelamar_datang'] ?? 0),
                            'gps_mock_pulang' => (int) ($r['gps_mock_sipelamar_pulang'] ?? 0),
                        ];
                        $jamD = !empty($r['jam_datang_sipelamar']) ? substr((string) $r['jam_datang_sipelamar'], 0, 8) : '';
                        $jamP = !empty($r['jam_pulang_sipelamar']) ? substr((string) $r['jam_pulang_sipelamar'], 0, 8) : '';
                        $idAbs = (int) ($r['id_absensi'] ?? 0);
                        ?>
                        <tr>
                            <td><?= sr_h(date('d M Y', strtotime((string) $r['tanggal']))) ?></td>
                            <td><strong><?= sr_h((string) ($r['nama_lengkap'] ?? '')) ?></strong></td>
                            <td><?= $jamD !== '' ? sr_h($jamD) : '<span style="color:#cbd5e1">—</span>' ?></td>
                            <td><?= $jamP !== '' ? sr_h($jamP) : '<span style="color:#cbd5e1">—</span>' ?></td>
                            <td><span class="ar-badge <?= sr_status_cls($stD) ?>"><?= sr_h(sr_status_label($stD, $telatDetik)) ?></span></td>
                            <td><span class="ar-badge <?= sr_status_cls($stP) ?>"><?= sr_h(sr_status_label($stP, $cepatDetik)) ?></span></td>
                            <td><?php
                                $jd = isset($r['jarak_datang_sipelamar']) && $r['jarak_datang_sipelamar'] !== '' && $r['jarak_datang_sipelamar'] !== null
                                    ? (int) $r['jarak_datang_sipelamar'] . ' m' : '—';
                                $mockD = (int) ($r['gps_mock_sipelamar_datang'] ?? 0) === 1;
                                echo sr_h($jd);
                                if ($mockD) {
                                    echo ' <span class="ar-badge late" style="font-size:10px;padding:2px 8px" title="GPS palsu terdeteksi">Mock</span>';
                                }
                            ?></td>
                            <td>
                                <div class="ar-row-actions">
                                    <button type="button" class="btn-xs btn-detail" data-row="<?= sr_row_attr($rowData) ?>"><i class="fas fa-eye"></i> Detail</button>
                                    <?php if ($isKelola): ?>
                                    <a href="sipelamar_rekap.php?hapus=<?= $idAbs ?>&bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&tab=absensi" class="btn-xs btn-outline-danger" onclick="return confirm('Hapus presensi SiPELAMAR ini?')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <!-- Layanan Table -->
        <div class="panel">
            <div class="panel-title">
                <h3><i class="fas fa-file-alt mr-1"></i> Arsip Layanan Malam</h3>
                <span class="meta"><?= count($layananRows) ?> surat · sama dengan Arsip Surat Pelayanan (badge SiPELAMAR)</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="tbl" style="min-width:720px">
                    <thead>
                        <tr><th>Tanggal</th><th>No Surat</th><th>Petugas</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($layananRows === []): ?>
                        <tr><td colspan="5"><div class="ar-empty"><i class="fas fa-inbox"></i>Belum ada arsip layanan SiPELAMAR bulan ini.</div></td></tr>
                    <?php else: foreach ($layananRows as $r):
                        $idSurat = (int) ($r['id_surat'] ?? 0);
                        ?>
                        <tr>
                            <td><?= sr_h(date('d M Y H:i', strtotime((string) $r['tgl_surat']))) ?></td>
                            <td><code><?= sr_h($r['no_surat'] ?? '') ?></code> <span class="ar-badge muted" style="font-size:10px;">SiPELAMAR</span></td>
                            <td><strong><?= sr_h($r['dibuat_oleh'] ?? '') ?></strong></td>
                            <td><span class="ar-badge muted"><?= sr_h($r['status_surat'] ?? '') ?></span></td>
                            <td>
                                <div class="ar-row-actions">
                                    <a href="arsip_detail.php?id=<?= $idSurat ?>" class="btn-xs" title="Detail arsip"><i class="fas fa-eye"></i></a>
                                    <a href="arsip_surat.php" class="btn-xs" title="Buka Arsip Surat Pelayanan"><i class="fas fa-archive"></i></a>
                                    <?php if ($isKelola): ?>
                                    <a href="sipelamar_rekap.php?hapus_surat=<?= $idSurat ?>&bulan=<?= urlencode($bulan) ?>&tahun=<?= $tahun ?>&id_user=<?= $idFilter ?>&tab=layanan" class="btn-xs btn-outline-danger" onclick="return confirm('Hapus arsip layanan malam ini? Surat akan dihapus dari Arsip Surat Pelayanan.')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

</div>
</div>
</section>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Absensi SiPELAMAR</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>
</div>

<script>
function srFmtJam(v){
    if(!v) return '—';
    return String(v).substring(0,8);
}
function srStatusLabel(st, detik){
    if(st === 'TELAT' && detik > 0) return 'Telat · ' + srFmtDurasi(detik);
    if(st === 'CEPAT' && detik > 0) return 'Pulang awal · ' + srFmtDurasi(detik);
    if(st === 'TEPAT') return 'Tepat waktu';
    if(st === 'MANUAL') return 'Koreksi manual';
    return st || '—';
}
function srFmtDurasi(detik){
    detik = parseInt(detik,10) || 0;
    if(detik < 60) return detik + ' detik';
    const m = Math.floor(detik / 60), s = detik % 60;
    if(m < 60) return s > 0 ? (m + ' menit ' + s + ' detik') : (m + ' menit');
    const h = Math.floor(m / 60), rm = m % 60;
    return h + ' jam ' + rm + ' menit';
}
function srStatusCls(st){
    if(st === 'TELAT') return 'late';
    if(st === 'CEPAT') return 'early';
    if(st === 'TEPAT') return 'ok';
    if(st === 'MANUAL') return 'manual';
    return 'muted';
}
function srEsc(s){
    const el = document.createElement('span');
    el.textContent = s == null ? '' : String(s);
    return el.innerHTML;
}
document.querySelectorAll('.btn-detail').forEach(btn => {
    btn.addEventListener('click', () => {
        let d = {};
        try {
            d = JSON.parse(atob(btn.getAttribute('data-row') || ''));
        } catch (e) {
            alert('Data detail tidak valid.');
            return;
        }
        const tgl = d.tanggal ? new Date(d.tanggal + 'T00:00:00').toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}) : d.tanggal;
        let html = '<div class="ar-detail-hd">';
        html += '<div class="nama">'+srEsc(d.nama)+'</div>';
        html += '<div class="sub">'+srEsc(tgl)+' · SiPELAMAR';
        if(d.jarak_datang) html += ' · Datang: '+srEsc(d.jarak_datang)+' m';
        if(d.jarak_pulang) html += ' · Pulang: '+srEsc(d.jarak_pulang)+' m';
        html += '</div></div>';
        html += '<div class="ar-detail-grid">';
        html += '<div class="ar-detail-box"><h6>Datang SiPELAMAR</h6>';
        html += '<div class="jam">'+srEsc(srFmtJam(d.jam_datang))+'</div>';
        html += '<span class="ar-badge '+srStatusCls(d.status_datang)+'">'+srEsc(srStatusLabel(d.status_datang, d.telat_detik))+'</span>';
        if(d.foto_datang) html += '<img src="'+srEsc(d.foto_datang)+'" alt="Foto datang SiPELAMAR">';
        if(d.gps_mock_datang) html += '<p class="text-danger small mb-0 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>GPS palsu terdeteksi saat datang</p>';
        html += '</div>';
        html += '<div class="ar-detail-box"><h6>Pulang SiPELAMAR</h6>';
        html += '<div class="jam">'+srEsc(srFmtJam(d.jam_pulang))+'</div>';
        html += '<span class="ar-badge '+srStatusCls(d.status_pulang)+'">'+srEsc(srStatusLabel(d.status_pulang, d.pulang_cepat_detik))+'</span>';
        if(d.foto_pulang) html += '<img src="'+srEsc(d.foto_pulang)+'" alt="Foto pulang SiPELAMAR">';
        if(d.gps_mock_pulang) html += '<p class="text-danger small mb-0 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>GPS palsu terdeteksi saat pulang</p>';
        html += '</div></div>';
        html += '<div class="ar-detail-meta mt-3">';
        if(d.lat_datang && d.lng_datang) html += '<p><i class="fas fa-map-marker-alt mr-1"></i><a target="_blank" href="https://www.google.com/maps?q='+encodeURIComponent(d.lat_datang)+','+encodeURIComponent(d.lng_datang)+'">Lihat lokasi datang di peta</a></p>';
        if(d.lat_pulang && d.lng_pulang) html += '<p><i class="fas fa-map-marker-alt mr-1"></i><a target="_blank" href="https://www.google.com/maps?q='+encodeURIComponent(d.lat_pulang)+','+encodeURIComponent(d.lng_pulang)+'">Lihat lokasi pulang di peta</a></p>';
        html += '</div>';
        document.getElementById('detailBody').innerHTML = html;
        $('#modalDetail').modal('show');
    });
});
</script>
<?php include 'footer.php'; ?>