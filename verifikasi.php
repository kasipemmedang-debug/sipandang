<?php
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';
require_once __DIR__ . '/inc_pemohon_tamu.php';
require_once __DIR__ . '/inc_surat_pbb.php';
require_once __DIR__ . '/inc_format_surat_resmi.php';
pemohon_tamu_ensure_schema($koneksi);

function verif_romawi(int $n): string
{
    $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
    return $map[$n] ?? (string) $n;
}

function verif_mask_nik(?string $nik): string
{
    $nik = preg_replace('/\D/', '', (string) $nik);
    if (strlen($nik) !== 16) {
        return '—';
    }
    return substr($nik, 0, 4) . '****' . substr($nik, -4);
}

function verif_keperluan(array $data): string
{
    $keperluan_db = trim((string) ($data['keperluan'] ?? ''));
    if ($keperluan_db !== '') {
        return strtoupper($keperluan_db);
    }
    $list_alasan = [
        '1' => 'Membentuk Rumah Tangga Baru',
        '2' => 'Kartu Keluarga Hilang/Rusak',
        '3' => 'Lainnya / Perubahan Data',
        '4' => 'Kepindahan Penduduk',
    ];
    $alasan = trim((string) ($data['alasan_permohonan'] ?? ''));
    if (isset($list_alasan[$alasan])) {
        return strtoupper($list_alasan[$alasan]);
    }
    $jenis = trim((string) ($data['jenis_layanan'] ?? ''));
    return $jenis !== '' ? strtoupper(str_replace('_', ' ', $jenis)) : 'PERMOHONAN DATA';
}

function verif_nomor_surat(array $data): string
{
    $no = trim((string) ($data['nomor_surat'] ?? ''));
    $tgl = (!empty($data['tgl_input']) && $data['tgl_input'] !== '0000-00-00')
        ? strtotime((string) $data['tgl_input']) : time();
    $bulan = verif_romawi((int) date('n', $tgl));
    $tahun = date('Y', $tgl);
    return '400.12.2.1 / ' . ($no !== '' ? $no : '—') . ' / ' . $bulan . ' / Kel. Mdn / ' . $tahun;
}

function verif_alamat_singkat(array $data): string
{
    $parts = [];
    $rt = trim((string) ($data['rt'] ?? ''));
    $rw = trim((string) ($data['rw'] ?? ''));
    if ($rt !== '' || $rw !== '') {
        $parts[] = 'RT.' . ($rt !== '' ? $rt : '—') . ' RW.' . ($rw !== '' ? $rw : '—');
    }
    $alamat = trim((string) ($data['alamat'] ?? ''));
    if ($alamat !== '') {
        $parts[] = strtoupper($alamat);
    }
    return $parts !== [] ? implode(' · ', $parts) : '—';
}

function verif_tanggal_id(?string $tgl): string
{
    if ($tgl === null || $tgl === '' || $tgl === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($tgl);
    if ($ts === false) {
        return '—';
    }
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function verif_query_surat_sql(): string
{
    return "
        SELECT a.*,
               COALESCE(NULLIF(TRIM(b.nama_lengkap), ''), NULLIF(TRIM(pt.nama_lengkap), '')) AS nama_pemohon_tampil,
               c.nama_pamong AS nama_pejabat,
               c.nip AS nip_pejabat,
               c.jabatan AS jab_pejabat,
               d.nama_surat,
               d.tipe_layanan
        FROM tbl_surat_keluar a
        LEFT JOIN tbl_penduduk b ON a.nik_pemohon = b.nik AND (a.pemohon_tipe IS NULL OR a.pemohon_tipe = 'WARGA')
        LEFT JOIN tbl_pemohon_tamu pt ON a.id_pemohon_tamu = pt.id_pemohon_tamu AND a.pemohon_tipe = 'TAMU'
        LEFT JOIN tbl_pamong c ON a.id_pamong = c.id_pamong
        LEFT JOIN tbl_format_surat d ON a.id_jenis = d.id_format
    ";
}

function verif_load_surat(mysqli $koneksi, string $where_sql): ?array
{
    $sql = verif_query_surat_sql() . ' WHERE ' . $where_sql . ' LIMIT 1';
    $query = mysqli_query($koneksi, $sql);
    if (!$query) {
        return null;
    }
    $row = mysqli_fetch_assoc($query);
    return is_array($row) && $row !== [] ? $row : null;
}

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$surat_id = isset($_GET['surat']) ? trim((string) $_GET['surat']) : '';
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$waris_id = isset($_GET['waris']) ? trim((string) $_GET['waris']) : '';
$wtoken = isset($_GET['wtoken']) ? trim((string) $_GET['wtoken']) : '';
$mode = '';
$data = null;
$invalid = false;
$found = false;
$pbb_verif = null;
$waris_ahli = [];
$waris_meta = [];

if ($wtoken !== '' || ($waris_id !== '' && ctype_digit($waris_id))) {
    require_once __DIR__ . '/inc_waris.php';
    waris_ensure_schema($koneksi);
    $mode = 'waris';
    if ($wtoken !== '') {
        $tok_esc = mysqli_real_escape_string($koneksi, $wtoken);
        $qw = mysqli_query($koneksi, "SELECT * FROM tbl_surat_waris WHERE token_waris='$tok_esc' LIMIT 1");
    } else {
        $wid = (int) $waris_id;
        $qw = mysqli_query($koneksi, "SELECT * FROM tbl_surat_waris WHERE id_waris=$wid LIMIT 1");
    }
    $data = ($qw && ($rowW = mysqli_fetch_assoc($qw))) ? $rowW : null;
    $found = $data !== null;
    $invalid = !$found;
    if ($found) {
        $waris_ahli = waris_load_detail_rows($koneksi, (int) ($data['id_waris'] ?? 0));
        $Dtmp = waris_data_from_db_row($data, $koneksi);
        $waris_meta = waris_compute_meta($Dtmp);
    }
} elseif ($token !== '') {
    $mode = 'surat';
    $token_esc = mysqli_real_escape_string($koneksi, $token);
    $data = verif_load_surat($koneksi, "a.token_surat = '$token_esc'");
    $found = $data !== null;
    $invalid = !$found;
} elseif ($surat_id !== '' && ctype_digit($surat_id)) {
    $id_esc = mysqli_real_escape_string($koneksi, $surat_id);
    $data = verif_load_surat($koneksi, "a.id_surat = '$id_esc'");
    if ($data !== null) {
        $mode = 'surat';
        $found = true;
        $invalid = false;
    } else {
        $found = false;
        $invalid = true;
    }
} elseif ($id !== '' && ctype_digit($id)) {
    $id_esc = mysqli_real_escape_string($koneksi, $id);
    // QR surat lama memakai ?id= — prioritaskan surat keluar agar tidak tertukar data SIAK
    $data = verif_load_surat($koneksi, "a.id_surat = '$id_esc'");
    if ($data !== null) {
        $mode = 'surat';
        $found = true;
        $invalid = false;
    } else {
        $query = mysqli_query($koneksi, "
        SELECT ps.*,
               p.nama_pamong AS nama_pejabat,
               p.nip AS nip_pejabat,
               p.jabatan AS jab_pejabat
        FROM permohonan_siak ps
        LEFT JOIN tbl_pamong p ON ps.nama_ttd_kel = p.id_pamong
        WHERE ps.id_siak = '$id_esc'
        LIMIT 1
    ");
        $data = $query ? mysqli_fetch_assoc($query) : null;
        if (is_array($data) && $data !== []) {
            $mode = 'siak';
            $found = true;
            $invalid = false;
        } else {
            $found = false;
            $invalid = true;
        }
    }
} else {
    $invalid = true;
    $found = false;
}

$kop = sipandang_kop_kelurahan_data($koneksi);
$nama_kel = htmlspecialchars((string) ($kop['nama_kel'] ?? 'Medang'));
$logo = htmlspecialchars((string) ($kop['logo'] ?? ''));

if ($found && $mode === 'siak') {
    $nama_pemohon = htmlspecialchars(strtoupper(trim((string) ($data['nama_pemohon'] ?? '—'))));
    $jenis_layanan = htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($data['jenis_layanan'] ?? '—'))));
    $nik_mask = htmlspecialchars(verif_mask_nik($data['nik_pemohon'] ?? ''));
    $keperluan = htmlspecialchars(verif_keperluan($data));
    $nomor_surat = htmlspecialchars(verif_nomor_surat($data));
    $alamat_singkat = htmlspecialchars(verif_alamat_singkat($data));
    $tgl_terbit = verif_tanggal_id($data['tgl_input'] ?? null);
    $id_dok = htmlspecialchars('#SIAK-' . str_pad($id, 5, '0', STR_PAD_LEFT));
    $ref_code = $id;

    $nama_pejabat = trim((string) ($data['nama_pejabat'] ?? ''));
    $jab_pejabat = trim((string) ($data['jab_pejabat'] ?? ''));
    if ($nama_pejabat !== '') {
        $pejabat_tampil = htmlspecialchars(strtoupper($nama_pejabat));
        $jabatan_tampil = $jab_pejabat !== '' ? htmlspecialchars($jab_pejabat) : 'Pejabat Kelurahan';
    } else {
        $pejabat_tampil = '—';
        $jabatan_tampil = 'Pejabat Kelurahan';
    }
    $hero_sub = 'NIK ' . $nik_mask . ' · ' . $jenis_layanan;
    $show_alamat = true;
} elseif ($found && $mode === 'surat') {
    $nama_raw = trim((string) ($data['nama_pemohon_tampil'] ?? ''));
    $nama_pemohon = htmlspecialchars($nama_raw !== '' ? strtoupper($nama_raw) : '—');
    $jenis_layanan = htmlspecialchars(trim((string) ($data['nama_surat'] ?? 'Surat Kelurahan')));
    $nik_mask = htmlspecialchars(verif_mask_nik($data['nik_pemohon'] ?? ''));
    $ket = trim((string) ($data['keterangan'] ?? ''));
    $keperluan = htmlspecialchars($ket !== '' ? strtoupper($ket) : strtoupper(trim((string) ($data['nama_surat'] ?? 'SURAT KELURAHAN'))));
    $nomor_surat = htmlspecialchars(trim((string) ($data['no_surat'] ?? '—')));
    $alamat_singkat = '—';
    $tgl_terbit = verif_tanggal_id($data['tgl_surat'] ?? null);
    $id_surat = (int) ($data['id_surat'] ?? $id);
    $revisi_scan = isset($_GET['r']) && ctype_digit((string) $_GET['r']) ? (int) $_GET['r'] : 0;
    $revisi_db = max(0, (int) ($data['revisi_reprint'] ?? 0));
    $revisi_tampil = $revisi_scan > 0 ? $revisi_scan : $revisi_db;
    $id_dok = htmlspecialchars('#SURAT-' . str_pad((string) $id_surat, 5, '0', STR_PAD_LEFT)
        . ($revisi_tampil > 0 ? ' · Rev.' . $revisi_tampil : ''));
    $ref_code = $revisi_tampil > 0 ? ($id_surat . '-R' . $revisi_tampil) : (string) $id_surat;

    $nama_pejabat = trim((string) ($data['nama_pejabat'] ?? ''));
    $jab_pejabat = trim((string) ($data['jab_pejabat'] ?? ''));
    if ($nama_pejabat !== '') {
        $pejabat_tampil = htmlspecialchars(strtoupper($nama_pejabat));
        $jabatan_tampil = $jab_pejabat !== '' ? htmlspecialchars($jab_pejabat) : 'Pejabat Kelurahan';
    } else {
        $pejabat_tampil = '—';
        $jabatan_tampil = 'Pejabat Kelurahan';
    }
    $hero_sub = $nik_mask !== '—' ? ('NIK ' . $nik_mask . ' · ' . $jenis_layanan) : $jenis_layanan;
    $show_alamat = false;
    if (function_exists('is_surat_mutasi_pbb') && is_surat_mutasi_pbb($data)) {
        $pbb_verif = surat_pbb_load($koneksi, (int) ($data['id_surat'] ?? 0));
        if ($pbb_verif) {
            $keperluan = htmlspecialchars('MUTASI PBB / SPPT');
            $alamat_parts = [];
            $alm = trim((string) ($pbb_verif['alamat_tanah'] ?? ''));
            if ($alm !== '') {
                $alamat_parts[] = $alm;
            }
            $rt = trim((string) ($pbb_verif['rt'] ?? ''));
            $rw = trim((string) ($pbb_verif['rw'] ?? ''));
            if ($rt !== '' || $rw !== '') {
                $alamat_parts[] = 'RT ' . ($rt !== '' ? $rt : '—') . ' RW ' . ($rw !== '' ? $rw : '—');
            }
            if ($alamat_parts !== []) {
                $alamat_singkat = htmlspecialchars(strtoupper(implode(' · ', $alamat_parts)));
                $show_alamat = true;
            }
        }
    }
} elseif ($found && $mode === 'waris') {
    $jenisAlm = trim((string) ($data['jenis_almarhum'] ?? 'ALMARHUM'));
    $namaAlm = trim((string) ($data['nama_almarhum'] ?? ''));
    $nama_pemohon = htmlspecialchars($namaAlm !== '' ? strtoupper($jenisAlm . ' ' . $namaAlm) : '—');
    $jenis_layanan = !empty($data['buat_kuasa'])
        ? 'Surat Pernyataan Waris + Surat Kuasa'
        : 'Surat Pernyataan Waris';
    $jenis_layanan = htmlspecialchars($jenis_layanan);
    $nik_mask = htmlspecialchars(verif_mask_nik($data['nik_almarhum'] ?? ''));
    $keperluan = htmlspecialchars('KETERANGAN AHLI WARIS');
    $nomor_surat = htmlspecialchars(trim((string) ($data['no_register'] ?? '—')) ?: '—');
    $alamat_parts = [];
    $alm = trim((string) ($data['alamat_almarhum'] ?? ''));
    if ($alm !== '') {
        $alamat_parts[] = $alm;
    }
    $rt = trim((string) ($data['rt_almarhum'] ?? ''));
    $rw = trim((string) ($data['rw_almarhum'] ?? ''));
    if ($rt !== '' || $rw !== '') {
        $alamat_parts[] = 'RT ' . ($rt !== '' ? $rt : '—') . ' / RW ' . ($rw !== '' ? $rw : '—');
    }
    $kel = trim((string) ($data['kel_almarhum'] ?? ''));
    $kec = trim((string) ($data['kec_almarhum'] ?? ''));
    if ($kel !== '' || $kec !== '') {
        $alamat_parts[] = trim($kel . ($kel && $kec ? ' · ' : '') . ($kec !== '' ? 'Kec. ' . $kec : ''));
    }
    $alamat_singkat = $alamat_parts !== []
        ? htmlspecialchars(strtoupper(implode(' · ', $alamat_parts)))
        : '—';
    $show_alamat = true;
    $tgl_terbit = verif_tanggal_id($data['tgl_register'] ?? ($data['tgl_surat'] ?? null));
    $id_waris_n = (int) ($data['id_waris'] ?? 0);
    $id_dok = htmlspecialchars('#WARIS-' . str_pad((string) $id_waris_n, 5, '0', STR_PAD_LEFT));
    $ref_code = 'W-' . $id_waris_n;

    $id_pm = (int) ($data['id_pamong'] ?? 0);
    $pm = $id_pm > 0 ? mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_pamong, jabatan FROM tbl_pamong WHERE id_pamong=$id_pm LIMIT 1")) : null;
    $nama_pejabat = trim((string) ($pm['nama_pamong'] ?? ''));
    $jab_pejabat = trim((string) ($pm['jabatan'] ?? ''));
    if ($nama_pejabat !== '') {
        $pejabat_tampil = htmlspecialchars(strtoupper($nama_pejabat));
        $jabatan_tampil = $jab_pejabat !== '' ? htmlspecialchars($jab_pejabat) : 'Lurah';
    } else {
        $pejabat_tampil = 'LURAH';
        $jabatan_tampil = 'Kelurahan';
    }
    $hero_sub = $nik_mask !== '—' ? ('NIK ' . $nik_mask . ' · ' . $jenis_layanan) : $jenis_layanan;

    $waris_tgl_mati = verif_tanggal_id($data['tgl_meninggal'] ?? null);
    $waris_akta = trim((string) ($data['no_akta_mati'] ?? ''));
    $waris_pasangan = trim((string) ($data['nama_pasangan'] ?? ''));
    $waris_jml = count($waris_ahli);
    $waris_kuasa_nama = trim((string) ($data['nama_penerima_kuasa'] ?? ''));
    $waris_kuasa_nik = verif_mask_nik($data['nik_penerima_kuasa'] ?? '');
    $waris_status = waris_status_label($data['status_surat'] ?? 'Selesai');
}
?><!DOCTYPE html>
<html class="sp-role-warga" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Dokumen — SIPANDANG</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
    --ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--bg:#f1f5f9;
    --card:#ffffff;--ok:#059669;--ok-bg:#ecfdf5;--ok-bd:#a7f3d0;
    --navy:var(--role-ink,#0c4a6e);--accent:var(--role,#0284c7);--warn:#dc2626;--warn-bg:#fef2f2;
    --shadow:0 24px 48px -12px rgba(15,23,42,.12);
    --radius:20px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;
    background:var(--bg);
    color:var(--ink);
    min-height:100vh;
    line-height:1.5;
}
.bg{
    position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none;
    background:
        radial-gradient(ellipse 80% 60% at 10% -10%, rgba(2,132,199,.12), transparent 55%),
        radial-gradient(ellipse 60% 50% at 100% 0%, rgba(5,150,105,.10), transparent 50%),
        var(--bg);
}
.wrap{
    position:relative;z-index:1;
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    padding:28px 16px 40px;
}
.card{
    width:100%;max-width:520px;
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    border:1px solid rgba(226,232,240,.9);
    overflow:hidden;
    animation:rise .45s cubic-bezier(.22,1,.36,1);
}
@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.top{
    padding:28px 28px 22px;
    background:linear-gradient(160deg,var(--role-ink,#0c4a6e) 0%,var(--role-ink,#0369a1) 55%,var(--role,#0284c7) 100%);
    color:#fff;text-align:center;position:relative;
}
.top::after{
    content:'';position:absolute;inset:auto 0 0 0;height:1px;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);
}
.org-logo{
    width:52px;height:52px;border-radius:14px;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 14px;overflow:hidden;
}
.org-logo img{width:100%;height:100%;object-fit:contain;padding:4px;background:#fff;border-radius:12px}
.org-logo i{font-size:1.35rem;opacity:.9}
.top h1{font-size:1rem;font-weight:800;letter-spacing:.3px;margin-bottom:4px}
.top p{font-size:.78rem;opacity:.82;font-weight:500}
.badge{
    display:inline-flex;align-items:center;gap:8px;
    margin:20px 28px 0;padding:11px 16px;border-radius:999px;
    font-size:.78rem;font-weight:800;letter-spacing:.4px;text-transform:uppercase;
}
.badge.ok{background:var(--ok-bg);color:var(--ok);border:1px solid var(--ok-bd)}
.badge.bad{background:var(--warn-bg);color:var(--warn);border:1px solid #fecaca;width:calc(100% - 56px);justify-content:center}
.badge i{font-size:.95rem}
.body{padding:22px 28px 28px}
.section{margin-bottom:20px}
.section-title{
    font-size:.68rem;font-weight:800;color:var(--muted);
    text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;
}
.hero-name{
    font-size:1.15rem;font-weight:800;color:var(--ink);
    letter-spacing:.2px;line-height:1.35;margin-bottom:4px;
}
.hero-sub{font-size:.82rem;color:var(--muted);font-weight:500}
.purpose{
    background:linear-gradient(135deg,var(--role-soft,#f0f9ff),#ecfdf5);
    border:1px solid var(--role-line,#bae6fd);border-radius:14px;
    padding:14px 16px;font-size:.88rem;font-weight:700;
    color:var(--navy);line-height:1.45;text-align:center;
}
.grid{display:grid;gap:0;border:1px solid var(--line);border-radius:14px;overflow:hidden}
.row{
    display:flex;justify-content:space-between;align-items:flex-start;gap:16px;
    padding:12px 14px;background:#fff;border-bottom:1px solid var(--line);
    font-size:.82rem;
}
.row:last-child{border-bottom:none}
.row:nth-child(even){background:#f8fafc}
.row .lbl{color:var(--muted);font-weight:600;flex-shrink:0;max-width:42%}
.row .val{color:var(--ink);font-weight:700;text-align:right;word-break:break-word}
.row .val.mono{font-family:ui-monospace,'Cascadia Code',monospace;font-size:.78rem;letter-spacing:.3px}
.sign-block{
    margin-top:4px;padding:14px 16px;border-radius:14px;
    background:#f8fafc;border:1px dashed var(--line);
}
.sign-block .name{font-weight:800;font-size:.88rem;color:var(--ink)}
.sign-block .role{font-size:.75rem;color:var(--muted);margin-top:2px;font-weight:600}
.foot{
    padding:18px 28px 24px;border-top:1px solid var(--line);
    background:#fafbfc;text-align:center;
}
.foot p{font-size:.72rem;color:var(--muted);line-height:1.65;max-width:380px;margin:0 auto}
.foot strong{color:var(--ink)}
.foot .ref{
    margin-top:10px;font-family:ui-monospace,monospace;
    font-size:.68rem;color:#66738f;letter-spacing:.4px;
}
.back{
    display:inline-flex;align-items:center;gap:6px;margin-top:18px;
    font-size:.78rem;font-weight:700;color:var(--accent);text-decoration:none;
}
.back:hover{text-decoration:underline}
.empty-msg{
    text-align:center;padding:8px 0 4px;
    font-size:.88rem;color:var(--muted);line-height:1.6;
}
@media(max-width:480px){
    .top,.body,.foot{padding-left:20px;padding-right:20px}
    .badge{margin-left:20px;margin-right:20px;width:calc(100% - 40px)}
    .hero-name{font-size:1.05rem}
    .row{flex-direction:column;gap:4px}
    .row .val{text-align:left}
}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-warga">
<div class="bg"></div>
<div class="wrap">
<div class="card">

    <div class="top">
        <?php if ($logo !== '' && is_file(__DIR__ . '/' . str_replace('\\', '/', $kop['logo'] ?? ''))): ?>
        <div class="org-logo"><img src="<?= $logo ?>" alt="Logo"></div>
        <?php else: ?>
        <div class="org-logo"><i class="fas fa-landmark"></i></div>
        <?php endif; ?>
        <h1>SIPANDANG · Verifikasi Dokumen</h1>
        <p>Kelurahan <?= $nama_kel ?> · Sistem Resmi Pelayanan Administrasi</p>
    </div>

    <?php if ($invalid || !$found): ?>
    <div class="badge bad"><i class="fas fa-circle-xmark"></i> Dokumen Tidak Ditemukan</div>
    <div class="body">
        <p class="empty-msg">
            <?php if ($invalid): ?>
            Kode verifikasi tidak valid. Pastikan QR code pada surat asli terbaca dengan benar.
            <?php else: ?>
            Dokumen dengan kode tersebut tidak terdaftar dalam sistem SIPANDANG.
            <?php endif; ?>
        </p>
        <div style="text-align:center">
            <a href="index.php" class="back"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
    </div>
    <?php else: ?>

    <div class="badge ok"><i class="fas fa-circle-check"></i> Dokumen Terverifikasi</div>

    <div class="body">
        <div class="section">
            <div class="section-title"><?= $mode === 'waris' ? 'Almarhum / Almarhumah' : 'Pemohon' ?></div>
            <div class="hero-name"><?= $nama_pemohon ?></div>
            <div class="hero-sub"><?= $hero_sub ?></div>
        </div>

        <div class="section">
            <div class="section-title"><?= $mode === 'waris' ? 'Jenis Dokumen' : 'Keperluan Surat' ?></div>
            <div class="purpose"><?= $keperluan ?></div>
        </div>

        <div class="section">
            <div class="section-title">Detail Dokumen</div>
            <div class="grid">
                <div class="row">
                    <span class="lbl"><?= $mode === 'waris' ? 'No. Register' : 'Nomor Surat' ?></span>
                    <span class="val mono"><?= $nomor_surat ?></span>
                </div>
                <div class="row">
                    <span class="lbl"><?= $mode === 'waris' ? 'Tanggal Register' : 'Tanggal Terbit' ?></span>
                    <span class="val"><?= htmlspecialchars($tgl_terbit) ?></span>
                </div>
                <?php if ($mode === 'waris'): ?>
                <div class="row">
                    <span class="lbl">Jenis Surat</span>
                    <span class="val"><?= $jenis_layanan ?></span>
                </div>
                <div class="row">
                    <span class="lbl">Tanggal Meninggal</span>
                    <span class="val"><?= htmlspecialchars($waris_tgl_mati ?? '—') ?></span>
                </div>
                <?php if (!empty($waris_akta)): ?>
                <div class="row">
                    <span class="lbl">Akta Kematian</span>
                    <span class="val mono"><?= htmlspecialchars($waris_akta) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($waris_pasangan)): ?>
                <div class="row">
                    <span class="lbl">Pasangan</span>
                    <span class="val"><?= htmlspecialchars(strtoupper($waris_pasangan)) ?></span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span class="lbl">Jumlah Ahli Waris</span>
                    <span class="val"><?= (int) ($waris_jml ?? 0) ?> orang</span>
                </div>
                <div class="row">
                    <span class="lbl">Status</span>
                    <span class="val"><?= htmlspecialchars($waris_status ?? 'Selesai') ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($show_alamat)): ?>
                <div class="row">
                    <span class="lbl"><?= $mode === 'waris' ? 'Alamat Terakhir' : 'Alamat / Wilayah' ?></span>
                    <span class="val"><?= $alamat_singkat ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pbb_verif)): ?>
                <?php if (!empty($pbb_verif['no_sppt'])): ?>
                <div class="row">
                    <span class="lbl">No. SPPT</span>
                    <span class="val mono"><?= htmlspecialchars((string) $pbb_verif['no_sppt']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pbb_verif['nama_pemilik'])): ?>
                <div class="row">
                    <span class="lbl">Nama Pemilik</span>
                    <span class="val"><?= htmlspecialchars(strtoupper((string) $pbb_verif['nama_pemilik'])) ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="row">
                    <span class="lbl">ID Dokumen</span>
                    <span class="val mono"><?= $id_dok ?></span>
                </div>
            </div>
        </div>

        <?php if ($mode === 'waris' && !empty($waris_ahli)): ?>
        <div class="section">
            <div class="section-title">Daftar Ahli Waris</div>
            <div class="grid">
                <?php foreach ($waris_ahli as $i => $aw):
                    $nm = strtoupper(trim((string) ($aw['nama'] ?? '')));
                    $hub = trim((string) ($aw['hubungan'] ?? ''));
                    $line = $nm !== '' ? $nm : '—';
                    if ($hub !== '') {
                        $line .= ' · ' . strtoupper($hub);
                    }
                ?>
                <div class="row">
                    <span class="lbl"><?= (int) $i + 1 ?>.</span>
                    <span class="val"><?= htmlspecialchars($line) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!empty($data['buat_kuasa']) && $waris_kuasa_nama !== ''): ?>
        <div class="section">
            <div class="section-title">Penerima Kuasa</div>
            <div class="grid">
                <div class="row">
                    <span class="lbl">Nama</span>
                    <span class="val"><?= htmlspecialchars(strtoupper($waris_kuasa_nama)) ?></span>
                </div>
                <?php if ($waris_kuasa_nik !== '—'): ?>
                <div class="row">
                    <span class="lbl">NIK</span>
                    <span class="val mono"><?= htmlspecialchars($waris_kuasa_nik) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($data['keperluan_kuasa'])): ?>
                <div class="row">
                    <span class="lbl">Keperluan</span>
                    <span class="val"><?= htmlspecialchars((string) $data['keperluan_kuasa']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="section">
            <div class="section-title"><?= $mode === 'waris' ? 'Dicatat / Ditandatangani' : 'Ditandatangani Oleh' ?></div>
            <div class="sign-block">
                <div class="name"><?= $pejabat_tampil ?></div>
                <div class="role"><?= $jabatan_tampil ?></div>
            </div>
        </div>
    </div>

    <div class="foot">
        <p>Dokumen ini <strong>terdaftar resmi</strong> di SIPANDANG Kelurahan <?= $nama_kel ?>. Cocokkan data di atas dengan surat fisik yang dibawa pemohon.</p>
        <div class="ref">REF · <?= htmlspecialchars($ref_code ?? '') ?> · SCAN <?= date('d/m/Y H:i') ?></div>
    </div>

    <?php endif; ?>

</div>
</div>
</body>
</html>
