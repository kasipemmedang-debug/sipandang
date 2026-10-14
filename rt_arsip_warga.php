<?php
/**
 * rt_arsip_warga.php — Arsip penduduk yang PINDAH / KELUAR / MENINGGAL
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);

$s     = rt_session();
$label = rt_label_wilayah($s);
$jab   = $s['jabatan'];
$rw    = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt    = mysqli_real_escape_string($koneksi, $s['nomor_rt']);

// Filter
$filter_status = $_GET['status'] ?? '';
$filter_q      = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per           = 20;
$offset        = ($page - 1) * $per;

$valid_status = ['','PINDAH','KELUAR','MENINGGAL'];
if (!in_array($filter_status, $valid_status)) $filter_status = '';

// WHERE dasar
$where = "rw='$rw'";
if ($jab === 'RT') $where .= " AND rt='$rt'";
if ($filter_status !== '') $where .= " AND status_arsip='" . mysqli_real_escape_string($koneksi, $filter_status) . "'";
if ($filter_q !== '') {
    $qe     = mysqli_real_escape_string($koneksi, $filter_q);
    $where .= " AND (nama_lengkap LIKE '%$qe%' OR nik LIKE '%$qe%' OR no_kk LIKE '%$qe%')";
}

// Hitung total
$total = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM tbl_arsip_penduduk WHERE $where"))['c'] ?? 0);
$pages = (int)ceil($total / $per) ?: 1;

// Ambil data
$rows = [];
$res  = mysqli_query($koneksi, "SELECT * FROM tbl_arsip_penduduk WHERE $where ORDER BY diarsipkan_at DESC LIMIT $per OFFSET $offset");
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

// Ringkasan per status
$summary = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT
        SUM(status_arsip='PINDAH')    AS jml_pindah,
        SUM(status_arsip='KELUAR')    AS jml_keluar,
        SUM(status_arsip='MENINGGAL') AS jml_meninggal,
        COUNT(*) AS total
     FROM tbl_arsip_penduduk WHERE rw='$rw'" . ($jab==='RT' ? " AND rt='$rt'" : '')
)) ?? [];

$status_cfg = [
    'PINDAH'    => ['#92400e','#fef3c7','#f59e0b','fa-truck-moving','Pindah'],
    'KELUAR'    => ['#1e40af','#eff6ff','#3b82f6','fa-door-open',   'Keluar'],
    'MENINGGAL' => ['#374151','#f3f4f6','#6b7280','fa-dove',            'Meninggal'],
];
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Arsip Penduduk — <?= htmlspecialchars($label) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --navy:var(--role-dark,#05285a);--navy2:var(--role-ink,#08428a);--gold:var(--role,#0b5cad);--gold-lt:#f0d48a;
    --bg:#f0f2f7;--white:#fff;--text:#172036;--muted:#64708b;
    --border:#e2e8f0;--radius:12px;--shadow:0 2px 12px rgba(10,22,40,.09);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);}

/* TOPBAR */
.topbar{background:var(--navy);position:sticky;top:0;z-index:200;box-shadow:0 2px 16px rgba(0,0,0,.25);}
.topbar-inner{display:flex;align-items:center;padding:12px 16px;gap:12px;}
.btn-back{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;
    padding:8px 14px;border-radius:9px;font-size:.75rem;font-weight:700;text-decoration:none;
    display:flex;align-items:center;gap:6px;white-space:nowrap;}
.topbar-title{color:#fff;font-weight:700;font-size:.92rem;flex:1;}
.topbar-sub{color:rgba(255,255,255,.45);font-size:.68rem;}

/* SUMMARY BANNER */
.summary-banner{background:linear-gradient(135deg,var(--navy),var(--navy2));padding:0 16px 16px;}
.sum-row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.sum-box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
    border-radius:10px;padding:10px 8px;text-align:center;cursor:pointer;transition:.15s;}
.sum-box:hover{background:rgba(255,255,255,.13);}
.sum-box.active{background:rgba(var(--role-rgb,11,92,173),.2);border-color:var(--gold);}
.sum-num{font-size:1.3rem;font-weight:700;color:#fff;line-height:1;}
.sum-lbl{font-size:.52rem;color:rgba(255,255,255,.5);margin-top:3px;font-weight:600;letter-spacing:.3px;}
.sum-box.gold .sum-num{color:var(--gold-lt);}

/* SEARCH + FILTER */
.toolbar{padding:12px 16px 8px;background:var(--bg);display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.search-wrap{position:relative;flex:1;min-width:180px;}
.search-wrap input{
    width:100%;padding:10px 40px 10px 38px;
    border:1.5px solid var(--border);border-radius:10px;
    font-size:.88rem;font-family:'Plus Jakarta Sans',sans-serif;
    background:var(--white);color:var(--text);outline:none;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.search-wrap input:focus{border-color:var(--navy);}
.s-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.85rem;pointer-events:none;}
.s-clear{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:var(--border);
    border:none;border-radius:50%;width:22px;height:22px;display:none;align-items:center;
    justify-content:center;cursor:pointer;color:var(--text);font-size:.6rem;}
.s-clear.show{display:flex;}

/* CONTENT */
.content{padding:4px 16px 100px;}

/* RESULT HEADER */
.result-hdr{display:flex;align-items:center;justify-content:space-between;
    padding:8px 0 6px;font-size:.72rem;color:var(--muted);font-weight:600;}
.result-hdr span{color:var(--navy);font-weight:700;}

/* ARSIP CARD */
.arsip-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);
    margin-bottom:10px;overflow:hidden;border-left:4px solid var(--c);}
.arsip-head{display:flex;align-items:center;gap:12px;padding:13px 14px 8px;}
.arsip-ava{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;
    justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;
    background:var(--bg-ava);color:var(--c);}
.arsip-nama{font-weight:700;font-size:.9rem;color:var(--text);}
.arsip-nik{font-family:monospace;font-size:.68rem;color:var(--muted);margin-top:2px;}
.arsip-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
    font-size:.62rem;font-weight:700;background:var(--bg-ava);color:var(--c);margin-top:4px;}
.arsip-body{padding:0 14px 12px;display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;}
.arsip-row{font-size:.75rem;color:var(--muted);}
.arsip-row strong{color:var(--text);font-weight:600;}
.arsip-ket{padding:0 14px 12px;font-size:.76rem;color:var(--muted);
    border-top:1px solid var(--border);padding-top:8px;line-height:1.5;}
.arsip-ket i{margin-right:5px;}

/* EMPTY */
.empty{text-align:center;padding:50px 20px;color:var(--muted);}
.empty-ico{width:64px;height:64px;border-radius:50%;background:var(--border);
    margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.empty h3{font-size:.95rem;color:var(--text);margin-bottom:6px;}

/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;padding:16px 0 8px;flex-wrap:wrap;}
.pg-btn{padding:8px 14px;border-radius:9px;border:1.5px solid var(--border);background:var(--white);
    font-weight:700;font-size:.8rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.pg-btn.active{background:var(--navy);color:var(--role-light);border-color:var(--navy);}
.pg-btn.disabled{opacity:.35;pointer-events:none;}
.pg-info{font-size:.75rem;color:var(--muted);font-weight:600;}

/* BOTTOM NAV */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:var(--white);
    border-top:1px solid var(--border);display:flex;
    padding:8px 0 calc(8px + env(safe-area-inset-bottom,0px));z-index:200;
    box-shadow:0 -4px 16px rgba(0,0,0,.08);}
.bnav-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;
    font-size:.58rem;font-weight:700;color:var(--muted);text-decoration:none;
    padding:2px 0;cursor:pointer;border:none;background:none;font-family:'Plus Jakarta Sans',sans-serif;}
.bnav-item.active{color:var(--navy);}
.bnav-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem;}
.bnav-item.active .bnav-ico{background:rgba(10,22,40,.07);}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">

<!-- TOPBAR -->
<div class="topbar">
<div class="topbar-inner">
    <a href="rt_portal.php" class="btn-back"><i class="fas fa-arrow-left"></i> Portal</a>
    <div style="flex:1;">
        <div class="topbar-title"><i class="fas fa-archive" style="color:var(--gold);margin-right:6px;"></i>Arsip Penduduk</div>
        <div class="topbar-sub"><?= htmlspecialchars($label) ?></div>
    </div>
</div>

<!-- SUMMARY -->
<div class="summary-banner">
    <div class="sum-row">
        <?php
        $sum_items = [
            [''          , (int)($summary['total']      ?? 0), 'TOTAL ARSIP', 'gold'],
            ['PINDAH'    , (int)($summary['jml_pindah']    ?? 0), 'PINDAH',  ''],
            ['KELUAR'    , (int)($summary['jml_keluar']    ?? 0), 'KELUAR',  ''],
            ['MENINGGAL' , (int)($summary['jml_meninggal'] ?? 0), 'MENINGGAL',''],
        ];
        foreach ($sum_items as [$sv, $sn, $sl, $scls]):
            $isActive = ($filter_status === $sv);
        ?>
        <div class="sum-box <?= $scls ?> <?= $isActive ? 'active' : '' ?>"
             onclick="filterStatus('<?= $sv ?>')">
            <div class="sum-num"><?= $sn ?></div>
            <div class="sum-lbl"><?= $sl ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<!-- SEARCH -->
<div class="toolbar">
    <div class="search-wrap">
        <i class="fas fa-search s-icon"></i>
        <input type="text" id="inputCari" placeholder="Cari nama, NIK…"
               value="<?= htmlspecialchars($filter_q) ?>" autocomplete="off"
               onkeydown="if(event.key==='Enter')doSearch()">
        <button class="s-clear <?= $filter_q ? 'show' : '' ?>" id="btnClear"
                onclick="clearSearch()"><i class="fas fa-times"></i></button>
    </div>
    <button onclick="doSearch()" style="background:var(--navy);color:var(--gold);border:none;
        padding:10px 16px;border-radius:10px;font-weight:700;font-size:.8rem;cursor:pointer;
        font-family:'Plus Jakarta Sans',sans-serif;white-space:nowrap;">
        <i class="fas fa-search"></i> Cari
    </button>
</div>

<!-- CONTENT -->
<div class="content">

<?php if (empty($rows)): ?>
<div class="empty">
    <div class="empty-ico"><i class="fas fa-archive"></i></div>
    <h3>Arsip Kosong</h3>
    <p style="font-size:.8rem;line-height:1.6;margin-top:4px;">
        <?= $filter_q || $filter_status
            ? 'Tidak ada data yang cocok dengan filter.'
            : 'Belum ada warga yang diarsipkan.' ?>
    </p>
</div>
<?php else: ?>

<div class="result-hdr">
    <div>Menampilkan <span><?= count($rows) ?></span> dari <span><?= $total ?></span> data</div>
    <?php if ($filter_status): ?>
    <div style="font-size:.7rem;color:var(--muted);">Filter: <?= htmlspecialchars($filter_status) ?></div>
    <?php endif; ?>
</div>

<?php foreach ($rows as $r):
    [$fc,$fbg,$fbrd,$fico,$flbl] = $status_cfg[$r['status_arsip']] ?? ['#555','#eee','#aaa','fa-archive','Arsip'];
    $inisial_src = (string) ($r['nama_lengkap'] ?? '?');
    $inisial = function_exists('mb_substr') && function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr($inisial_src, 0, 1))
        : strtoupper(substr($inisial_src, 0, 1));
    $jk_l    = (strtoupper(trim($r['jenis_kelamin'][0] ?? '')) === 'L');
    $usia    = '';
    if (!empty($r['tgl_lahir']) && $r['tgl_lahir'] !== '0000-00-00') {
        try { $usia = (new DateTime($r['tgl_lahir']))->diff(new DateTime())->y . ' thn'; } catch(Exception $e){}
    }
    $tgl_kjd = $r['tanggal_kejadian'] && $r['tanggal_kejadian'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($r['tanggal_kejadian'])) : '—';
    $tgl_arsip = date('d/m/Y', strtotime($r['diarsipkan_at']));
?>
<div class="arsip-card" style="--c:<?= $fc ?>;--bg-ava:<?= $fbg ?>;">
    <div class="arsip-head">
        <div class="arsip-ava"><?= $inisial ?></div>
        <div style="flex:1;min-width:0;">
            <div class="arsip-nama"><?= htmlspecialchars($r['nama_lengkap'] ?? '') ?></div>
            <div class="arsip-nik"><?= htmlspecialchars($r['nik'] ?? '') ?></div>
            <span class="arsip-badge">
                <i class="fas <?= $fico ?>"></i> <?= $flbl ?>
            </span>
        </div>
        <div style="text-align:right;font-size:.65rem;color:var(--muted);flex-shrink:0;">
            <div><?= $tgl_kjd ?></div>
            <div style="margin-top:2px;">diarsip <?= $tgl_arsip ?></div>
        </div>
    </div>
    <div class="arsip-body">
        <div class="arsip-row"><i class="fas fa-home" style="width:14px;color:var(--muted);"></i>
            KK: <strong><?= htmlspecialchars($r['no_kk'] ?? '—') ?></strong></div>
        <div class="arsip-row"><i class="fas fa-<?= $jk_l?'mars':'venus' ?>" style="width:14px;color:var(--muted);"></i>
            <strong><?= $jk_l ? 'Laki-laki' : 'Perempuan' ?></strong><?= $usia ? ' · '.$usia : '' ?></div>
        <div class="arsip-row"><i class="fas fa-map-marker-alt" style="width:14px;color:var(--muted);"></i>
            RT.<strong><?= str_pad($r['rt'] ?? '', 3, '0', STR_PAD_LEFT) ?></strong>
            / RW.<strong><?= str_pad($r['rw'] ?? '', 3, '0', STR_PAD_LEFT) ?></strong></div>
        <div class="arsip-row"><i class="fas fa-user-tie" style="width:14px;color:var(--muted);"></i>
            Oleh: <strong><?= htmlspecialchars($r['diarsipkan_oleh'] ?? '—') ?></strong></div>
    </div>
    <?php if (!empty($r['keterangan'])): ?>
    <div class="arsip-ket" style="border-top:1px solid var(--border);margin:0 14px;padding:8px 0 10px;">
        <i class="fas fa-comment-alt" style="color:var(--muted);"></i>
        <?= htmlspecialchars($r['keterangan']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- PAGINATION -->
<?php if ($pages > 1): ?>
<div class="pagination">
    <?php
    $base = '?status=' . urlencode($filter_status) . '&q=' . urlencode($filter_q) . '&page=';
    ?>
    <a href="<?= $base.max(1,$page-1) ?>" class="pg-btn <?= $page<=1?'disabled':'' ?>">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php
    $start = max(1, $page-2); $end = min($pages, $page+2);
    if ($start > 1) { echo '<a href="'.$base.'1" class="pg-btn">1</a>'; if ($start > 2) echo '<span class="pg-info">…</span>'; }
    for ($i = $start; $i <= $end; $i++):
    ?>
    <a href="<?= $base.$i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor;
    if ($end < $pages) { if ($end < $pages-1) echo '<span class="pg-info">…</span>'; echo '<a href="'.$base.$pages.'" class="pg-btn">'.$pages.'</a>'; }
    ?>
    <a href="<?= $base.min($pages,$page+1) ?>" class="pg-btn <?= $page>=$pages?'disabled':'' ?>">
        <i class="fas fa-chevron-right"></i>
    </a>
    <div style="width:100%;text-align:center;" class="pg-info">Hal. <?= $page ?> / <?= $pages ?></div>
</div>
<?php endif; ?>

<?php endif; ?>
</div><!-- /content -->

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="rt_portal.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-home"></i></div>Beranda
    </a>
    <button class="bnav-item active">
        <div class="bnav-ico"><i class="fas fa-archive"></i></div>Arsip
    </button>
    <a href="rt_tambah_warga.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-user-plus"></i></div>Tambah
    </a>
    <a href="rt_logout.php" class="bnav-item" onclick="return confirm('Keluar?')">
        <div class="bnav-ico"><i class="fas fa-sign-out-alt"></i></div>Keluar
    </a>
</nav>

<script>
function filterStatus(val) {
    var q = document.getElementById('inputCari').value.trim();
    window.location = '?status=' + encodeURIComponent(val) + '&q=' + encodeURIComponent(q) + '&page=1';
}
function doSearch() {
    var q = document.getElementById('inputCari').value.trim();
    window.location = '?status=<?= urlencode($filter_status) ?>&q=' + encodeURIComponent(q) + '&page=1';
}
function clearSearch() {
    document.getElementById('inputCari').value = '';
    document.getElementById('btnClear').classList.remove('show');
    window.location = '?status=<?= urlencode($filter_status) ?>&page=1';
}
document.getElementById('inputCari').addEventListener('input', function() {
    document.getElementById('btnClear').classList.toggle('show', this.value !== '');
});
</script>
</body>
</html>
