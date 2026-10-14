<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
$s     = rt_session();
$label = rt_label_wilayah($s);

// Hanya ambil STATISTIK (ringan, bukan semua data)
$rw  = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt  = mysqli_real_escape_string($koneksi, $s['nomor_rt']);
$jab = $s['jabatan'];
$where_base = "status_dasar='HIDUP' AND rw='$rw'" . ($jab==='RT' ? " AND rt='$rt'" : '');

$stat = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS jiwa,
        COUNT(DISTINCT no_kk) AS kk,
        SUM(CASE WHEN UPPER(LEFT(TRIM(jenis_kelamin),1))='L' THEN 1 ELSE 0 END) AS jml_l,
        SUM(CASE WHEN UPPER(LEFT(TRIM(jenis_kelamin),1))='P' THEN 1 ELSE 0 END) AS jml_p
    FROM tbl_penduduk WHERE $where_base
")) ?? ['jiwa'=>0,'kk'=>0,'jml_l'=>0,'jml_p'=>0];

$toast_map = [
    'ok'     => ['Warga berhasil ditambahkan!',           'ok'],
    'upd'    => ['Data berhasil diperbarui!',             'ok'],
    'hapus'  => ['Warga telah dihapus.',                  'warn'],
    'arsip'  => ['Warga dipindahkan ke Arsip Penduduk.',  'warn'],
    'err'    => ['Gagal menyimpan.',                      'err'],
    'dup'    => ['NIK sudah terdaftar!',                  'err'],
    'noauth' => ['Akses tidak diizinkan.',                'err'],
];
$toast = $toast_map[$_GET['toast'] ?? ''] ?? null;
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="mobile-web-app-capable" content="yes">
<title>Portal <?= htmlspecialchars($label) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --navy:var(--role-dark,#05285a);--navy2:var(--role-ink,#08428a);--gold:var(--role,#0b5cad);--gold-lt:var(--role-soft,#e8f2fd);
    --bg:#f4f6fb;--white:#fff;--text:#172036;--muted:#566583;
    --border:#e3e8f3;--radius:14px;--shadow:0 3px 14px rgba(10,22,40,.08);
    --green:#0e7050;--red:#dc3a52;
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);}

/* TOPBAR */
.topbar{background:var(--navy);position:sticky;top:0;z-index:200;box-shadow:0 2px 16px rgba(0,0,0,.25);}
.topbar-inner{display:flex;align-items:center;padding:12px 16px;gap:12px;}
.topbar-ico{width:38px;height:38px;border-radius:10px;background:rgba(var(--role-rgb,11,92,173),.15);border:1.5px solid rgba(var(--role-rgb,11,92,173),.35);display:flex;align-items:center;justify-content:center;color:var(--role-light,#a6c7f2);font-size:.95rem;flex-shrink:0;}
.topbar-info{flex:1;min-width:0;}
.topbar-title{color:#fff;font-weight:700;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.topbar-sub{color:rgba(255,255,255,.45);font-size:.68rem;margin-top:1px;}
.btn-logout{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.7);padding:7px 12px;border-radius:9px;font-size:.72rem;font-weight:700;text-decoration:none;white-space:nowrap;display:flex;align-items:center;gap:5px;}

/* STATS */
.stats-banner{background:linear-gradient(135deg,var(--navy),var(--navy2));padding:0 16px 16px;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.stat-box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:10px 8px;text-align:center;}
.stat-num{font-size:1.35rem;font-weight:700;color:#fff;line-height:1;}
.stat-lbl{font-size:.52rem;color:rgba(255,255,255,.45);margin-top:3px;font-weight:600;letter-spacing:.3px;}
.stat-box.gold .stat-num{color:var(--gold-lt);}

/* SEARCH */
.search-bar{padding:14px 16px 10px;background:var(--bg);}
.search-wrap{position:relative;}
.search-wrap input{
    width:100%;padding:13px 48px 13px 44px;
    border:1.5px solid var(--border);border-radius:14px;
    font-size:.92rem;font-family:'Plus Jakarta Sans',sans-serif;
    background:var(--white);color:var(--text);outline:none;
    box-shadow:0 2px 8px rgba(0,0,0,.06);transition:border-color .2s,box-shadow .2s;
}
.search-wrap input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(10,22,40,.08);}
.s-icon{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.9rem;pointer-events:none;}
.s-clear{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:var(--border);border:none;border-radius:50%;width:24px;height:24px;display:none;align-items:center;justify-content:center;cursor:pointer;color:var(--text);font-size:.65rem;}
.s-clear.show{display:flex;}
.search-tip{text-align:center;font-size:.75rem;color:var(--muted);padding:6px 0 2px;}

/* CONTENT */
.content{padding:4px 16px 100px;}

/* INFO CARD (default state) */
.info-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px 20px;text-align:center;margin-bottom:12px;}
.info-ico{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--role-ink,#08428a),var(--role-dark,#05285a));margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--role-soft,#e8f2fd);}
.info-card h3{font-size:.95rem;font-weight:700;margin-bottom:6px;}
.info-card p{font-size:.8rem;color:var(--muted);line-height:1.6;}
.btn-lihat-semua{display:inline-flex;align-items:center;gap:7px;margin-top:16px;background:var(--role,#0b5cad);color:#fff;padding:11px 22px;border-radius:10px;font-weight:700;font-size:.85rem;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}

/* RESULT HEADER */
.result-hdr{display:flex;align-items:center;justify-content:space-between;padding:8px 0 6px;font-size:.72rem;color:var(--muted);font-weight:600;}
.result-hdr span{color:var(--navy);font-weight:700;}

/* KK GROUP */
.kk-label{display:flex;align-items:center;gap:8px;margin:12px 0 5px;}
.kk-label::before,.kk-label::after{content:'';flex:1;height:1px;background:var(--border);}
.kk-label span{background:var(--white);border:1px solid var(--border);padding:3px 10px;border-radius:20px;font-size:.62rem;color:var(--muted);white-space:nowrap;}

/* WARGA CARD */
.warga-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:8px;display:flex;align-items:center;gap:12px;padding:13px 14px;border-left:3px solid transparent;}
.warga-card.is-kk{border-left-color:var(--gold);}
.warga-ava{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem;flex-shrink:0;}
.ava-l{background:var(--role-soft,#dbeafe);color:var(--role,#1d4ed8);} .ava-p{background:#fce7f3;color:#be185d;}
.warga-body{flex:1;min-width:0;}
.warga-nama{font-weight:700;font-size:.9rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.warga-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;}
.chip{display:inline-flex;align-items:center;gap:3px;font-size:.6rem;padding:2px 7px;border-radius:20px;font-weight:600;white-space:nowrap;}
.chip-kk  {background:#fef9e7;color:#92650a;border:1px solid #fde68a;}
.chip-jk-l{background:var(--role-soft,#eff6ff);color:var(--role-ink,#1e40af);}
.chip-jk-p{background:#fdf2f8;color:#86198f;}
.chip-rt  {background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
.chip-usia{background:#f5f3ff;color:#6b21a8;}
.chip-hub {background:#fff7ed;color:#9a3412;}
.warga-nik{font-family:monospace;font-size:.68rem;color:var(--muted);margin-top:3px;}
mark{background:#fef08a;border-radius:2px;padding:0 1px;}

/* AKSI */
.warga-acts{display:flex;flex-direction:column;gap:5px;flex-shrink:0;}
.act-btn{width:36px;height:36px;border-radius:9px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.82rem;}
.act-edit{background:var(--role-soft,#eff6ff);color:var(--role,#1d4ed8);}
.act-del {background:#fff2f2;color:var(--red);}
.act-btn:active{transform:scale(.9);}

/* SKELETON LOADER */
.skeleton-wrap{padding:4px 0;}
.sk-card{background:var(--white);border-radius:var(--radius);padding:14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;}
.sk-ava{width:44px;height:44px;border-radius:50%;background:#e2e8f0;flex-shrink:0;animation:pulse 1.2s ease-in-out infinite;}
.sk-lines{flex:1;}
.sk-line{height:10px;border-radius:5px;background:#e2e8f0;margin-bottom:8px;animation:pulse 1.2s ease-in-out infinite;}
.sk-line.w80{width:80%;} .sk-line.w55{width:55%;} .sk-line.w40{width:40%;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.45;}}

/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:center;gap:8px;padding:16px 0 8px;flex-wrap:wrap;}
.pg-btn{padding:9px 16px;border-radius:9px;border:1.5px solid var(--border);background:var(--white);font-weight:700;font-size:.8rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);display:flex;align-items:center;gap:5px;}
.pg-btn:disabled{opacity:.35;cursor:not-allowed;}
.pg-btn.active{background:var(--navy);color:var(--role-light);border-color:var(--navy);}
.pg-info{font-size:.75rem;color:var(--muted);font-weight:600;padding:0 4px;}

/* ERROR STATE */
.err-state{background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius);padding:16px;text-align:center;color:#7f1d1d;font-size:.83rem;}
.err-state i{font-size:1.4rem;display:block;margin-bottom:8px;}

/* EMPTY */
.empty{text-align:center;padding:40px 20px;color:var(--muted);}
.empty-ico{width:60px;height:60px;border-radius:50%;background:var(--border);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.empty h3{font-size:.9rem;color:var(--text);margin-bottom:5px;}
.empty p{font-size:.78rem;line-height:1.6;}

/* FAB */
.fab{position:fixed;bottom:calc(76px + env(safe-area-inset-bottom,0px));right:18px;width:56px;height:56px;border-radius:50%;background:var(--navy);color:var(--role-light);border:none;cursor:pointer;box-shadow:0 6px 24px rgba(10,22,40,.35);display:flex;align-items:center;justify-content:center;font-size:1.3rem;z-index:150;text-decoration:none;}
.fab:active{transform:scale(.92);}

/* BOTTOM NAV */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;background:var(--white);border-top:1px solid var(--border);display:flex;padding:6px 0 calc(6px + env(safe-area-inset-bottom,0px));z-index:200;box-shadow:0 -4px 16px rgba(0,0,0,.08);}
.bnav-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;font-size:.52rem;font-weight:700;color:var(--muted);text-decoration:none;padding:2px 0;cursor:pointer;border:none;background:none;font-family:'Plus Jakarta Sans',sans-serif;}
.bnav-item.active{color:var(--navy);}
.bnav-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.88rem;}
.bnav-item.active .bnav-ico{background:rgba(10,22,40,.07);}

/* MODAL HAPUS */
.modal-over{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;align-items:flex-end;justify-content:center;}
.modal-over.show{display:flex;}
.modal-sheet{background:var(--white);border-radius:20px 20px 0 0;padding:20px 20px calc(24px + env(safe-area-inset-bottom,0px));width:100%;max-width:480px;animation:slideUp .22s ease;}
@keyframes slideUp{from{transform:translateY(50px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.modal-handle{width:36px;height:4px;background:var(--border);border-radius:2px;margin:0 auto 16px;}
.modal-icon{width:52px;height:52px;border-radius:50%;background:#fff2f2;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:var(--red);}
.modal-title{text-align:center;font-size:1rem;font-weight:700;margin-bottom:6px;}
.modal-sub{text-align:center;font-size:.82rem;color:var(--muted);margin-bottom:20px;line-height:1.5;}
.modal-btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.mbtn{padding:13px;border-radius:12px;font-weight:700;font-size:.88rem;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.mbtn-cancel{background:var(--bg);color:var(--text);}
.mbtn-del{background:var(--red);color:#fff;}
.mbtn-del:disabled{background:#aaa;cursor:not-allowed;}

/* PROSES OVERLAY */
.proses-overlay{display:none;position:fixed;inset:0;background:rgba(10,22,40,.6);z-index:600;align-items:center;justify-content:center;flex-direction:column;gap:14px;}
.proses-overlay.show{display:flex;}
.proses-spin{width:52px;height:52px;border:4px solid rgba(255,255,255,.2);border-top-color:var(--gold);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.proses-label{color:#fff;font-weight:700;font-size:.88rem;}

/* TOAST */
.toast{position:fixed;top:16px;left:50%;transform:translateX(-50%);padding:11px 20px;border-radius:12px;font-weight:700;font-size:.83rem;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.18);display:flex;align-items:center;gap:8px;white-space:nowrap;animation:fadeDown .3s ease;}
@keyframes fadeDown{from{opacity:0;transform:translateX(-50%) translateY(-10px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
.toast.ok{background:var(--green);color:#fff;}
.toast.warn{background:var(--gold);color:#fff;}
.toast.err{background:var(--red);color:#fff;}

@media (max-width:480px){
    body{font-size:15px;}
    .topbar{border-radius:0 0 18px 18px;}
    .topbar-inner{padding:14px 14px 10px;gap:10px;}
    .topbar-ico{width:40px;height:40px;font-size:1rem;}
    .topbar-title{font-size:.98rem;line-height:1.25;}
    .topbar-sub{font-size:.72rem;}
    .btn-logout{padding:8px 10px;font-size:.72rem;border-radius:10px;}
    .stats-banner{padding:0 14px 14px;}
    .stats-row{grid-template-columns:repeat(2,1fr);gap:8px;}
    .stat-box{padding:11px 8px;}
    .stat-num{font-size:1.25rem;}
    .search-bar{position:sticky;top:0;z-index:90;padding:10px 12px;background:rgba(244,246,250,.96);backdrop-filter:blur(8px);}
    .search-wrap input{min-height:46px;font-size:16px;border-radius:13px;}
    .content{padding:8px 12px 108px;}
    .warga-card{align-items:flex-start;padding:12px;gap:10px;border-radius:14px;}
    .warga-ava{width:42px;height:42px;font-size:1rem;}
    .warga-nama{white-space:normal;line-height:1.3;font-size:.94rem;}
    .warga-nik{font-size:.72rem;word-break:break-all;}
    .chip{font-size:.62rem;padding:3px 8px;}
    .warga-acts{flex-direction:row;align-self:center;}
    .act-btn{width:42px;height:42px;border-radius:12px;font-size:.92rem;}
    .pagination{gap:6px;padding-top:14px;}
    .pg-btn{min-height:40px;padding:9px 13px;}
    .fab{width:58px;height:58px;right:16px;bottom:calc(82px + env(safe-area-inset-bottom,0px));}
    .bottom-nav{padding-top:8px;}
    .bnav-item{font-size:.58rem;min-height:48px;}
    .modal-btns{grid-template-columns:1fr;gap:8px;}
    .mbtn{min-height:46px;}
}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">

<!-- PROSES OVERLAY -->
<div class="proses-overlay" id="prosesOverlay">
    <div class="proses-spin"></div>
    <div class="proses-label" id="prosesLabel">Memproses…</div>
</div>

<!-- TOPBAR -->
<div class="topbar">
<div class="topbar-inner">
    <div class="topbar-ico"><i class="fas fa-map-marked-alt"></i></div>
    <div class="topbar-info">
        <div class="topbar-title">Portal <?= htmlspecialchars($label) ?></div>
        <div class="topbar-sub"><?= htmlspecialchars($s['nama_ketua'] ?: 'Kelurahan Medang') ?></div>
    </div>
    <a href="rt_logout.php" class="btn-logout" onclick="return confirm('Keluar dari portal?')">
        <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
</div>
<div class="stats-banner">
    <div class="stats-row">
        <div class="stat-box"><div class="stat-num"><?= (int)$stat['jiwa'] ?></div><div class="stat-lbl">JIWA</div></div>
        <div class="stat-box gold"><div class="stat-num"><?= (int)$stat['kk'] ?></div><div class="stat-lbl">KK</div></div>
        <div class="stat-box"><div class="stat-num"><?= (int)$stat['jml_l'] ?></div><div class="stat-lbl">LAKI-LAKI</div></div>
        <div class="stat-box"><div class="stat-num"><?= (int)$stat['jml_p'] ?></div><div class="stat-lbl">PEREMPUAN</div></div>
    </div>
</div>
</div>

<!-- SEARCH -->
<div class="search-bar">
    <div class="search-wrap">
        <i class="fas fa-search s-icon" id="sIcon"></i>
        <input type="text" id="inputCari"
               placeholder="Cari nama, NIK, atau No. KK…"
               autocomplete="off" inputmode="search">
        <button class="s-clear" id="btnClear" onclick="clearSearch()"><i class="fas fa-times"></i></button>
    </div>
    <div class="search-tip" id="searchTip">Ketik minimal 3 huruf untuk mencari</div>
</div>

<!-- CONTENT -->
<div class="content" id="contentArea">

    <!-- DEFAULT STATE -->
    <div id="stateDefault">
        <div class="info-card">
            <div class="info-ico"><i class="fas fa-users"></i></div>
            <h3>Data Warga <?= htmlspecialchars($label) ?></h3>
            <p>Total <strong><?= (int)$stat['jiwa'] ?> jiwa</strong> terdaftar.<br>
               Cari nama/NIK di atas, atau tampilkan semua warga.</p>
            <button class="btn-lihat-semua" onclick="lihatSemua()">
                <i class="fas fa-list"></i> Tampilkan Semua Warga
            </button>
        </div>
    </div>

    <!-- LOADING STATE -->
    <div id="stateLoading" style="display:none;">
        <div class="skeleton-wrap">
            <?php for ($i=0;$i<5;$i++): ?>
            <div class="sk-card">
                <div class="sk-ava"></div>
                <div class="sk-lines">
                    <div class="sk-line w80"></div>
                    <div class="sk-line w55"></div>
                    <div class="sk-line w40"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- RESULT STATE -->
    <div id="stateResult" style="display:none;">
        <div class="result-hdr">
            <div id="resultInfo">—</div>
            <div id="resultFilter" style="font-size:.7rem;color:var(--muted);"></div>
        </div>
        <div id="resultList"></div>
        <div class="pagination" id="pagination"></div>
    </div>

    <!-- ERROR STATE -->
    <div id="stateError" style="display:none;">
        <div class="err-state">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Gagal memuat data</strong><br>
            <span id="errMsg">Periksa koneksi internet Anda.</span><br><br>
            <button onclick="retryLast()" style="background:var(--red);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;">
                <i class="fas fa-redo"></i> Coba Lagi
            </button>
        </div>
    </div>

    <!-- EMPTY RESULT -->
    <div id="stateEmpty" style="display:none;">
        <div class="empty">
            <div class="empty-ico"><i class="fas fa-search"></i></div>
            <h3>Tidak ditemukan</h3>
            <p id="emptyTxt">Tidak ada warga yang cocok.</p>
        </div>
    </div>

</div><!-- /content -->

<!-- FAB -->
<a href="rt_tambah_warga.php" class="fab"><i class="fas fa-plus"></i></a>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <button class="bnav-item active" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <div class="bnav-ico"><i class="fas fa-home"></i></div>Beranda
    </button>
    <button class="bnav-item" onclick="focusSearch()">
        <div class="bnav-ico"><i class="fas fa-search"></i></div>Cari
    </button>
    <a href="rt_tambah_warga.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-user-plus"></i></div>Tambah
    </a>
    <a href="rt_arsip_warga.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-archive"></i></div>Arsip
    </a>
    <a href="rt_daftar_surat.php" class="bnav-item">
        <div class="bnav-ico"><i class="fas fa-file-signature"></i></div>Surat
    </a>
    <a href="rt_logout.php" class="bnav-item" onclick="return confirm('Keluar?')">
        <div class="bnav-ico"><i class="fas fa-sign-out-alt"></i></div>Keluar
    </a>
</nav>

<!-- MODAL HAPUS -->
<div class="modal-over" id="modalHapus" onclick="if(event.target===this)tutupModal()">
<div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="modal-title">Hapus Data Warga?</div>
    <div class="modal-sub" id="modalSub">Data akan dihapus dari wilayah Anda.</div>
    <div class="modal-btns">
        <button class="mbtn mbtn-cancel" onclick="tutupModal()">Batal</button>
        <button class="mbtn mbtn-del" id="btnHapusOk" onclick="prosesHapus()">
            <i class="fas fa-trash"></i> Hapus
        </button>
    </div>
</div>
</div>


<script>
/* ══════════ STATE ══════════ */
var _state      = 'default'; // default | loading | result | error | empty
var _lastQ      = '';
var _lastPage   = 1;
var _totalPages = 1;
var _totalRows  = 0;
var _hapusNik   = '';
var _searchTimer= null;
var PER_PAGE    = 25;
var RT_CSRF     = <?= json_encode(rt_csrf_token(), JSON_UNESCAPED_UNICODE); ?>;

function setState(s) {
    _state = s;
    ['Default','Loading','Result','Error','Empty'].forEach(function(n) {
        document.getElementById('state'+n).style.display = (s === n.toLowerCase() ? '' : 'none');
    });
}

/* ══════════ SEARCH ══════════ */
var inputEl   = document.getElementById('inputCari');
var btnClear  = document.getElementById('btnClear');
var searchTip = document.getElementById('searchTip');

inputEl.addEventListener('input', function() {
    var q = this.value.trim();
    btnClear.classList.toggle('show', q !== '');
    clearTimeout(_searchTimer);

    if (q === '') { clearSearch(); return; }

    if (q.length < 3) {
        searchTip.textContent = 'Ketik ' + (3 - q.length) + ' huruf lagi untuk mencari…';
        searchTip.style.color = 'var(--muted)';
        return;
    }

    searchTip.textContent = 'Mencari…';
    searchTip.style.color = 'var(--gold)';
    _searchTimer = setTimeout(function() { fetchData(q, 1); }, 350);
});

function clearSearch() {
    inputEl.value = '';
    btnClear.classList.remove('show');
    searchTip.textContent = 'Ketik minimal 3 huruf untuk mencari';
    searchTip.style.color = '';
    setState('default');
    _lastQ = '';
}

function focusSearch() {
    inputEl.scrollIntoView({behavior:'smooth', block:'center'});
    setTimeout(() => inputEl.focus(), 300);
}

function lihatSemua() { fetchData('', 1); }
function retryLast()  { fetchData(_lastQ, _lastPage); }

/* ══════════ FETCH AJAX ══════════ */
var _xhr = null;
function fetchData(q, page) {
    _lastQ    = q;
    _lastPage = page;

    if (_xhr) { _xhr.abort(); }
    setState('loading');
    window.scrollTo({top:0, behavior:'smooth'});

    var url = 'rt_cari_warga.php?q=' + encodeURIComponent(q)
            + '&page=' + page + '&per_page=' + PER_PAGE;

    _xhr = new XMLHttpRequest();
    _xhr.open('GET', url, true);
    _xhr.timeout = 15000;

    _xhr.onload = function() {
        if (_xhr.status === 200) {
            try {
                var data = JSON.parse(_xhr.responseText);
                renderResult(data, q);
            } catch(e) {
                showError('Respons server tidak valid.');
            }
        } else {
            showError('Server error ' + _xhr.status + '. Coba lagi.');
        }
    };
    _xhr.onerror   = function() { showError('Tidak dapat terhubung ke server.'); };
    _xhr.ontimeout = function() { showError('Waktu habis. Periksa koneksi internet.'); };
    _xhr.send();
}

function showError(msg) {
    document.getElementById('errMsg').textContent = msg;
    setState('error');
}

/* ══════════ RENDER HASIL ══════════ */
function renderResult(data, q) {
    _totalPages = data.pages || 1;
    _totalRows  = data.total || 0;

    if (!data.rows || data.rows.length === 0) {
        document.getElementById('emptyTxt').textContent = q
            ? 'Tidak ada warga dengan nama, NIK, atau No. KK "' + q + '".'
            : 'Belum ada data warga terdaftar.';
        setState('empty');
        return;
    }

    // update info
    var dari  = ((_lastPage-1)*PER_PAGE)+1;
    var sampai= Math.min(_lastPage*PER_PAGE, _totalRows);
    var infoEl= document.getElementById('resultInfo');
    infoEl.innerHTML = 'Menampilkan <span>' + dari + '–' + sampai + '</span> dari <span>' + _totalRows + '</span> warga';

    var filtEl = document.getElementById('resultFilter');
    filtEl.textContent = q ? 'Hasil: "' + q + '"' : 'Semua warga';

    // render kartu
    var html   = '';
    var prevKK = null;
    data.rows.forEach(function(w) {
        if (w.no_kk !== prevKK) {
            prevKK = w.no_kk;
            html += '<div class="kk-label"><span><i class="fas fa-home" style="margin-right:4px;"></i>'
                  + (w.no_kk ? 'KK ' + esc(w.no_kk) : 'No KK belum diisi') + '</span></div>';
        }
        var inisial = w.nama ? w.nama.charAt(0).toUpperCase() : '?';
        var namaHtml= q ? highlight(w.nama, q) : esc(w.nama);
        var nikHtml = (q && w.nik) ? highlight(w.nik, q) : esc(w.nik);

        html += '<div class="warga-card' + (w.is_kk ? ' is-kk' : '') + '">'
              + '<div class="warga-ava ' + (w.jk==='L'?'ava-l':'ava-p') + '">' + inisial + '</div>'
              + '<div class="warga-body">'
              + '<div class="warga-nama">' + namaHtml + '</div>'
              + '<div class="warga-chips">'
              + (w.is_kk ? '<span class="chip chip-kk"><i class="fas fa-home"></i> Kepala KK</span>' : '')
              + (!w.is_kk && w.hub ? '<span class="chip chip-hub">' + esc(w.hub) + '</span>' : '')
              + '<span class="chip ' + (w.jk==='L'?'chip-jk-l':'chip-jk-p') + '">' + (w.jk==='L'?'♂ L':'♀ P') + '</span>'
              + (w.usia ? '<span class="chip chip-usia">' + esc(w.usia) + '</span>' : '')
              + '<span class="chip chip-rt">RT.' + esc(w.rt) + '</span>'
              + '</div>'
              + (w.nik ? '<div class="warga-nik">' + nikHtml + '</div>' : '')
              + '</div>'
              + '<div class="warga-acts">'
              + '<a href="rt_edit_warga.php?nik=' + encodeURIComponent(w.nik) + '" class="act-btn act-edit"><i class="fas fa-pen"></i></a>'
              + '<button class="act-btn act-del" data-nama="' + escAttr(w.nama) + '" data-nik="' + escAttr(w.nik) + '" onclick="konfirmHapusBtn(this)"><i class="fas fa-trash"></i></button>'
              + '</div>'
              + '</div>';
    });

    document.getElementById('resultList').innerHTML = html;
    renderPagination(data.page, data.pages, data.total);
    setState('result');
}

/* ══════════ PAGINATION ══════════ */
function renderPagination(page, pages, total) {
    var el  = document.getElementById('pagination');
    if (pages <= 1) { el.innerHTML = ''; return; }

    var html = '<button class="pg-btn" onclick="gotoPage(' + (page-1) + ')" ' + (page<=1?'disabled':'') + '>'
             + '<i class="fas fa-chevron-left"></i></button>';

    // tampilkan max 5 nomor halaman
    var start = Math.max(1, page-2);
    var end   = Math.min(pages, page+2);
    if (start > 1) html += '<button class="pg-btn" onclick="gotoPage(1)">1</button>'
                         + (start > 2 ? '<span class="pg-info">…</span>' : '');
    for (var i=start;i<=end;i++) {
        html += '<button class="pg-btn' + (i===page?' active':'') + '" onclick="gotoPage('+i+')">' + i + '</button>';
    }
    if (end < pages) {
        html += (end < pages-1 ? '<span class="pg-info">…</span>' : '')
              + '<button class="pg-btn" onclick="gotoPage('+pages+')">' + pages + '</button>';
    }

    html += '<button class="pg-btn" onclick="gotoPage(' + (page+1) + ')" ' + (page>=pages?'disabled':'') + '>'
          + '<i class="fas fa-chevron-right"></i></button>';

    html += '<div style="width:100%;text-align:center;margin-top:4px;" class="pg-info">Hal. '+page+' / '+pages+'</div>';
    el.innerHTML = html;
}

function gotoPage(p) {
    if (p < 1 || p > _totalPages) return;
    fetchData(_lastQ, p);
}

/* ══════════ HAPUS ══════════ */
function konfirmHapusBtn(btn) {
    konfirmHapus(btn.getAttribute('data-nama') || '', btn.getAttribute('data-nik') || '');
}
function konfirmHapus(nama, nik) {
    _hapusNik = nik;
    document.getElementById('modalSub').innerHTML = 'Warga <strong>' + esc(nama) + '</strong><br>akan dihapus dari data wilayah Anda.';
    var btn = document.getElementById('btnHapusOk');
    btn.innerHTML = '<i class="fas fa-trash"></i> Hapus';
    btn.disabled  = false;
    document.getElementById('modalHapus').classList.add('show');
}
function tutupModal() { document.getElementById('modalHapus').classList.remove('show'); }
function prosesHapus() {
    if (!_hapusNik) return;
    var btn = document.getElementById('btnHapusOk');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus…';
    btn.disabled  = true;
    document.getElementById('prosesLabel').textContent = 'Menghapus data warga…';
    document.getElementById('prosesOverlay').classList.add('show');
    setTimeout(function() {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'rt_hapus_warga.php';
        form.innerHTML = '<input type="hidden" name="rt_csrf" value="' + escAttr(RT_CSRF) + '">'
                       + '<input type="hidden" name="nik" value="' + escAttr(_hapusNik) + '">';
        document.body.appendChild(form);
        form.submit();
    }, 400);
}

/* ══════════ HELPERS ══════════ */
function esc(s)     { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escAttr(s) { return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function highlight(text, q) {
    var tl=text.toLowerCase(), ql=q.toLowerCase(), idx=tl.indexOf(ql);
    if(idx<0) return esc(text);
    return esc(text.substring(0,idx))+'<mark>'+esc(text.substring(idx,idx+q.length))+'</mark>'+esc(text.substring(idx+q.length));
}
</script>
<?php
require_once __DIR__ . '/inc_admin_toast.php';
sipandang_admin_toast_styles();
sipandang_admin_toast_container();
sipandang_admin_toast_script();
?>
</body>
</html>
