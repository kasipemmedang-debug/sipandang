<?php
/**
 * rt_daftar_surat.php — Daftar Surat Pengantar + Validasi RW
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);

$s   = rt_session();
$jab = $s['jabatan'];
$rw_e = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt_e = mysqli_real_escape_string($koneksi, $s['nomor_rt']);

/* ══ AKSI RW: SETUJUI ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    rt_csrf_check();
    if ($jab !== 'RW') { header('Location: rt_daftar_surat.php?toast=noauth'); exit; }

    $id_s  = (int)($_POST['surat_id'] ?? 0);
    $aksi  = $_POST['aksi'];
    $oleh  = mysqli_real_escape_string($koneksi, $s['username']);
    $ctt   = mysqli_real_escape_string($koneksi, trim($_POST['catatan_rw'] ?? ''));

    if ($aksi === 'setujui') {
        // Ambil data surat
        $row_s = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT * FROM tbl_surat_pengantar WHERE id=$id_s AND rw='$rw_e' AND status='menunggu' LIMIT 1"));
        if ($row_s) {
            $ts_surat = strtotime((string) $row_s['tanggal_surat']) ?: time();
            $tahun = (int) date('Y', $ts_surat);
            $bulan = (int) date('n', $ts_surat);
            $no_rw = rt_next_no_surat($koneksi, $s['nomor_rw'], $row_s['rt'], 'RW', $tahun, $bulan);
            $no_str = mysqli_real_escape_string($koneksi, $no_rw['nomor']);
            mysqli_query($koneksi,
                "UPDATE tbl_surat_pengantar SET
                    status='disetujui', no_urut_rw={$no_rw['urut']}, no_surat_rw='$no_str',
                    catatan_rw='$ctt', divalidasi_oleh='$oleh', divalidasi_at=NOW()
                 WHERE id=$id_s AND rw='$rw_e' AND status='menunggu'");
        } else {
            header('Location: rt_daftar_surat.php?toast=invalid'); exit;
        }
        header('Location: rt_daftar_surat.php?toast=setuju'); exit;

    } elseif ($aksi === 'tolak') {
        mysqli_query($koneksi,
            "UPDATE tbl_surat_pengantar SET
                status='ditolak', catatan_rw='$ctt',
                divalidasi_oleh='$oleh', divalidasi_at=NOW()
             WHERE id=$id_s AND rw='$rw_e' AND status='menunggu'");
        if (mysqli_affected_rows($koneksi) <= 0) {
            header('Location: rt_daftar_surat.php?toast=invalid'); exit;
        }
        header('Location: rt_daftar_surat.php?toast=tolak'); exit;
    }
}

// Filter & paginasi
$filter = $_GET['filter'] ?? 'semua';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15;
$offset = ($page - 1) * $per;

$where = "rw='$rw_e'";
if ($jab === 'RT') $where .= " AND rt='$rt_e'";
if ($filter === 'menunggu')  $where .= " AND status='menunggu'";
if ($filter === 'disetujui') $where .= " AND status='disetujui'";
if ($filter === 'ditolak')   $where .= " AND status='ditolak'";

$total = (int)(mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tbl_surat_pengantar WHERE $where"))['c'] ?? 0);
$pages = max(1, (int)ceil($total / $per));

$rows = [];
$res  = mysqli_query($koneksi,
    "SELECT * FROM tbl_surat_pengantar WHERE $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

// Summary
$summary = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT SUM(status='menunggu') AS jml_tunggu,
            SUM(status='disetujui') AS jml_setuju,
            SUM(status='ditolak') AS jml_tolak,
            COUNT(*) AS total
     FROM tbl_surat_pengantar WHERE rw='$rw_e'" . ($jab==='RT' ? " AND rt='$rt_e'" : '')
)) ?? [];

$label = rt_label_wilayah($s);
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Surat Pengantar — <?= htmlspecialchars($label) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:var(--role-dark,#05285a);--navy2:var(--role-ink,#08428a);--gold:var(--role,#0b5cad);--gold-lt:var(--role-soft,#e8f2fd);--bg:#f4f6fb;--white:#fff;
    --text:#172036;--muted:#64708b;--border:#e2e8f0;--radius:12px;--shadow:0 2px 12px rgba(10,22,40,.09);}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);}
.topbar{background:var(--navy);position:sticky;top:0;z-index:200;box-shadow:0 2px 16px rgba(0,0,0,.25);}
.topbar-inner{display:flex;align-items:center;padding:12px 16px;gap:12px;}
.btn-back{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;
    padding:8px 14px;border-radius:9px;font-size:.75rem;font-weight:700;text-decoration:none;
    display:flex;align-items:center;gap:6px;}
.topbar-title{color:#fff;font-weight:700;font-size:.92rem;flex:1;}
.topbar-sub{color:rgba(255,255,255,.45);font-size:.68rem;}
.btn-buat{background:var(--gold);color:#fff;border:none;padding:8px 16px;border-radius:9px;
    font-weight:700;font-size:.78rem;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:6px;white-space:nowrap;}

/* SUMMARY */
.summary-banner{background:linear-gradient(135deg,var(--navy),var(--navy2));padding:0 16px 14px;}
.sum-row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.sum-box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
    border-radius:10px;padding:10px 8px;text-align:center;cursor:pointer;transition:.15s;}
.sum-box.active{background:rgba(var(--role-rgb,11,92,173),.18);border-color:var(--gold);}
.sum-num{font-size:1.25rem;font-weight:700;color:#fff;line-height:1;}
.sum-lbl{font-size:.52rem;color:rgba(255,255,255,.5);margin-top:3px;font-weight:600;letter-spacing:.3px;}
.sum-box.gold .sum-num{color:var(--role-light,#a6c7f2);}

.content{padding:12px 16px 100px;}

/* SURAT CARD */
.surat-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);
    margin-bottom:10px;overflow:hidden;border-left:4px solid var(--bc);}
.sc-head{padding:13px 14px 8px;display:flex;align-items:flex-start;gap:10px;}
.sc-ico{width:40px;height:40px;border-radius:10px;background:var(--bg-ico);
    display:flex;align-items:center;justify-content:center;font-size:.9rem;color:var(--bc);flex-shrink:0;}
.sc-info{flex:1;min-width:0;}
.sc-nama{font-weight:700;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sc-no{font-family:monospace;font-size:.68rem;color:var(--muted);margin-top:2px;}
.sc-kep{font-size:.75rem;color:var(--text);margin-top:4px;line-height:1.4;}
.sc-meta{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;}
.badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;
    font-size:.6rem;font-weight:700;}
.badge-tunggu{background:#fef9c3;color:#854d0e;}
.badge-setuju{background:#dcfce7;color:#166534;}
.badge-tolak {background:#fee2e2;color:#991b1b;}
.badge-rt    {background:var(--role-soft,#eff6ff);color:var(--role-ink,#1e40af);}
.badge-tgl   {background:#f5f3ff;color:#6b21a8;}

.sc-btns{display:flex;gap:8px;padding:0 14px 12px;flex-wrap:wrap;}
.sc-btn{padding:8px 14px;border-radius:9px;border:none;font-weight:700;font-size:.76rem;
    cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;display:flex;align-items:center;gap:5px;text-decoration:none;}
.sc-btn-cetak{background:var(--role-soft,#eff6ff);color:var(--role-ink,#1e40af);}
.sc-btn-setuju{background:#dcfce7;color:#166534;}
.sc-btn-tolak{background:#fee2e2;color:#991b1b;}
.sc-btn-lihat{background:#f5f5f5;color:#374151;}

.catatan{padding:0 14px 10px;font-size:.75rem;color:#7f1d1d;background:#fff7f7;
    border-top:1px solid #fecaca;padding-top:8px;line-height:1.5;}

/* MODAL VALIDASI */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;
    align-items:flex-end;justify-content:center;}
.modal-ov.show{display:flex;}
.modal-sh{background:#fff;border-radius:20px 20px 0 0;padding:20px 20px calc(24px + env(safe-area-inset-bottom,0px));
    width:100%;max-width:480px;animation:slideUp .2s ease;}
@keyframes slideUp{from{transform:translateY(40px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.mhandle{width:36px;height:4px;background:var(--border);border-radius:2px;margin:0 auto 16px;}
.mico{width:48px;height:48px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
.mico.ok{background:#dcfce7;color:#166534;}
.mico.err{background:#fee2e2;color:#991b1b;}
.mtitle{text-align:center;font-size:1rem;font-weight:700;margin-bottom:5px;}
.msub{text-align:center;font-size:.82rem;color:var(--muted);margin-bottom:14px;line-height:1.5;}
.mfield label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;
    letter-spacing:.3px;display:block;margin-bottom:5px;}
.mfield textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;
    font-size:.85rem;font-family:'Plus Jakarta Sans',sans-serif;outline:none;resize:vertical;min-height:70px;}
.mfield textarea:focus{border-color:var(--navy);}
.mbtns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;}
.mbtn{padding:13px;border-radius:12px;font-weight:700;font-size:.88rem;border:none;
    cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.mbtn-cancel{background:#f0f2f5;color:var(--text);}
.mbtn-ok{color:#fff;}
.mbtn-ok.ok{background:#166534;}
.mbtn-ok.err{background:#991b1b;}

/* BOTTOM NAV */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:var(--white);
    border-top:1px solid var(--border);display:flex;
    padding:6px 0 calc(6px + env(safe-area-inset-bottom,0px));z-index:200;box-shadow:0 -4px 16px rgba(0,0,0,.08);}
.bnav-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;
    font-size:.52rem;font-weight:700;color:var(--muted);text-decoration:none;
    padding:2px 0;cursor:pointer;border:none;background:none;font-family:'Plus Jakarta Sans',sans-serif;}
.bnav-item.active{color:var(--navy);}
.bnav-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;}
.bnav-item.active .bnav-ico{background:rgba(10,22,40,.07);}

.empty{text-align:center;padding:50px 20px;color:var(--muted);}
.empty-ico{width:60px;height:60px;border-radius:50%;background:var(--border);margin:0 auto 12px;
    display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
@media (max-width:480px){
    .topbar-inner{padding:13px 12px 10px;gap:8px;}
    .btn-back,.btn-buat{padding:9px 11px;border-radius:10px;font-size:.74rem;}
    .summary-banner{padding:0 12px 12px;}
    .sum-row{grid-template-columns:repeat(2,1fr);}
    .sum-box{padding:11px 8px;}
    .content{padding:10px 12px 108px;}
    .sc-head{padding:13px 12px 9px;}
    .sc-nama{white-space:normal;line-height:1.3;font-size:.94rem;}
    .sc-no{word-break:break-all;}
    .sc-btns{display:grid;grid-template-columns:1fr 1fr;padding:0 12px 12px;}
    .sc-btn{justify-content:center;min-height:42px;padding:9px 10px;}
    .mbtns{grid-template-columns:1fr;}
    .mbtn{min-height:46px;}
    .bnav-item{font-size:.58rem;min-height:48px;}
}
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
        <div class="topbar-title"><i class="fas fa-file-signature" style="color:var(--gold);margin-right:5px;"></i>Surat Pengantar</div>
        <div class="topbar-sub"><?= htmlspecialchars($label) ?></div>
    </div>
    <a href="rt_buat_surat.php" class="btn-buat"><i class="fas fa-plus"></i> Buat</a>
</div>
<!-- SUMMARY -->
<div class="summary-banner">
    <div class="sum-row">
        <?php
        $sum_tabs = [
            ['semua',    (int)($summary['total']      ?? 0), 'TOTAL',    'gold'],
            ['menunggu', (int)($summary['jml_tunggu']  ?? 0), 'MENUNGGU', ''],
            ['disetujui',(int)($summary['jml_setuju']  ?? 0), 'DISETUJUI',''],
            ['ditolak',  (int)($summary['jml_tolak']   ?? 0), 'DITOLAK',  ''],
        ];
        foreach ($sum_tabs as [$fv,$fn,$fl,$fc]):
        ?>
        <div class="sum-box <?= $fc ?> <?= $filter===$fv?'active':'' ?>"
             onclick="window.location='?filter=<?= $fv ?>'">
            <div class="sum-num"><?= $fn ?></div>
            <div class="sum-lbl"><?= $fl ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<!-- CONTENT -->
<div class="content">

<?php if (empty($rows)): ?>
<div class="empty">
    <div class="empty-ico"><i class="fas fa-file-alt"></i></div>
    <h3 style="font-size:.92rem;color:var(--text);margin-bottom:5px;">Belum ada surat</h3>
    <p style="font-size:.8rem;line-height:1.6;">
        <?= $filter !== 'semua' ? 'Tidak ada surat dengan status ini.' : 'Buat surat pengantar pertama dengan tombol Buat di atas.' ?>
    </p>
</div>
<?php else: ?>

<?php foreach ($rows as $r):
    $sts = $r['status'];
    $bc  = $sts==='disetujui' ? '#166534' : ($sts==='ditolak' ? '#991b1b' : '#d97706');
    $bg  = $sts==='disetujui' ? '#f0fdf4' : ($sts==='ditolak' ? '#fff1f2' : '#fffbeb');
    $kep = array_filter(explode("\n", $r['keperluan'] ?? ''));
    $tgl = date('d/m/Y', strtotime($r['tanggal_surat']));
    $rt_p= str_pad($r['rt'], 3, '0', STR_PAD_LEFT);
?>
<div class="surat-card" style="--bc:<?= $bc ?>;--bg-ico:<?= $bg ?>;">
    <div class="sc-head">
        <div class="sc-ico"><i class="fas fa-file-signature"></i></div>
        <div class="sc-info">
            <div class="sc-nama"><?= htmlspecialchars($r['nama_pemohon']) ?></div>
            <div class="sc-no"><?= htmlspecialchars($r['no_surat_rt']) ?></div>
            <div class="sc-kep">
                <?php foreach (array_slice($kep,0,2) as $k): ?>
                <i class="fas fa-circle" style="font-size:.4rem;vertical-align:middle;margin-right:4px;"></i><?= htmlspecialchars($k) ?><br>
                <?php endforeach; ?>
                <?php if (count($kep) > 2): ?>
                <span style="color:var(--muted);font-size:.72rem;">+<?= count($kep)-2 ?> lainnya</span>
                <?php endif; ?>
            </div>
            <div class="sc-meta">
                <span class="badge badge-rt">RT.<?= $rt_p ?></span>
                <span class="badge badge-tgl"><i class="fas fa-calendar" style="font-size:.55rem;"></i> <?= $tgl ?></span>
                <span class="badge badge-<?= $sts ?>">
                    <?= $sts==='disetujui'?'✓ Disetujui':($sts==='ditolak'?'✗ Ditolak':'⏳ Menunggu RW') ?>
                </span>
            </div>
            <?php if ($r['no_surat_rw']): ?>
            <div style="font-size:.67rem;color:var(--muted);margin-top:3px;">
                No. RW: <?= htmlspecialchars($r['no_surat_rw']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($r['catatan_rw'] && $sts === 'ditolak'): ?>
    <div class="catatan"><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($r['catatan_rw']) ?></div>
    <?php endif; ?>

    <div class="sc-btns">
        <?php if ($sts === 'disetujui'): ?>
        <a href="rt_cetak_surat.php?id=<?= $r['id'] ?>" target="_blank" class="sc-btn sc-btn-cetak">
            <i class="fas fa-print"></i> Cetak Surat
        </a>
        <?php endif; ?>
        <?php if ($jab === 'RW' && $sts === 'menunggu'): ?>
        <button class="sc-btn sc-btn-setuju" data-aksi="setujui" data-id="<?= (int) $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama_pemohon'], ENT_QUOTES, 'UTF-8') ?>" onclick="bukaMdlBtn(this)">
            <i class="fas fa-check"></i> Setujui
        </button>
        <button class="sc-btn sc-btn-tolak" data-aksi="tolak" data-id="<?= (int) $r['id'] ?>" data-nama="<?= htmlspecialchars($r['nama_pemohon'], ENT_QUOTES, 'UTF-8') ?>" onclick="bukaMdlBtn(this)">
            <i class="fas fa-times"></i> Tolak
        </button>
        <?php endif; ?>
        <a href="rt_cetak_surat.php?id=<?= $r['id'] ?>&mode=draft" target="_blank" class="sc-btn sc-btn-lihat">
            <i class="fas fa-eye"></i> Lihat
        </a>
    </div>
</div>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;padding:12px 0;flex-wrap:wrap;">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?filter=<?= urlencode($filter) ?>&page=<?= $i ?>"
       style="padding:8px 14px;border-radius:8px;border:1.5px solid var(--border);background:<?= $i===$page?'var(--navy)':'var(--white)' ?>;color:<?= $i===$page?'var(--gold)':'var(--text)' ?>;text-decoration:none;font-weight:700;font-size:.8rem;">
        <?= $i ?>
    </a>
    <?php endfor; ?>
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
        <div class="bnav-ico"><i class="fas fa-file-signature"></i></div>Surat
    </button>
    <a href="rt_buat_surat.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-plus"></i></div>Buat
    </a>
    <?php if ($jab === 'RW'): ?>
    <a href="rt_profil_rw.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-stamp"></i></div>Kop Surat
    </a>
    <?php endif; ?>
    <a href="rt_logout.php" class="bnav-item" onclick="return confirm('Keluar?')">
        <div class="bnav-ico"><i class="fas fa-sign-out-alt"></i></div>Keluar
    </a>
</nav>

<!-- MODAL VALIDASI -->
<div class="modal-ov" id="modalVal" onclick="if(event.target===this)tutupMdl()">
<div class="modal-sh">
    <div class="mhandle"></div>
    <div class="mico" id="mIco"></div>
    <div class="mtitle" id="mTitle">—</div>
    <div class="msub" id="mSub">—</div>
    <form method="post">
        <?= rt_csrf_field(); ?>
        <input type="hidden" name="surat_id" id="mSuratId">
        <input type="hidden" name="aksi"     id="mAksi">
        <div class="mfield">
            <label>Catatan (opsional)</label>
            <textarea name="catatan_rw" id="mCatatan" placeholder="Catatan untuk pemohon…"></textarea>
        </div>
        <div class="mbtns">
            <button type="button" class="mbtn mbtn-cancel" onclick="tutupMdl()">Batal</button>
            <button type="submit" class="mbtn mbtn-ok" id="mBtnOk">Konfirmasi</button>
        </div>
    </form>
</div>
</div>

<script>
function bukaMdlBtn(btn) {
    bukaMdl(btn.getAttribute('data-aksi'), btn.getAttribute('data-id'), btn.getAttribute('data-nama') || '');
}
function bukaMdl(aksi, id, nama) {
    document.getElementById('mSuratId').value = id;
    document.getElementById('mAksi').value    = aksi;
    document.getElementById('mCatatan').value = '';
    var ico  = document.getElementById('mIco');
    var title= document.getElementById('mTitle');
    var sub  = document.getElementById('mSub');
    var btn  = document.getElementById('mBtnOk');
    if (aksi === 'setujui') {
        ico.className  = 'mico ok'; ico.innerHTML = '<i class="fas fa-check"></i>';
        title.textContent = 'Setujui Surat?';
        sub.textContent   = 'Surat atas nama ' + nama + ' akan disetujui dan nomor RW dibuat otomatis.';
        btn.className  = 'mbtn mbtn-ok ok'; btn.textContent = 'Ya, Setujui';
    } else {
        ico.className  = 'mico err'; ico.innerHTML = '<i class="fas fa-times"></i>';
        title.textContent = 'Tolak Surat?';
        sub.textContent   = 'Surat atas nama ' + nama + ' akan ditolak.';
        btn.className  = 'mbtn mbtn-ok err'; btn.textContent = 'Ya, Tolak';
    }
    document.getElementById('modalVal').classList.add('show');
}
function tutupMdl() { document.getElementById('modalVal').classList.remove('show'); }
</script>
<?php
require_once __DIR__ . '/inc_admin_toast.php';
sipandang_admin_toast_styles();
sipandang_admin_toast_container();
sipandang_admin_toast_script();
?>
</body>
</html>
