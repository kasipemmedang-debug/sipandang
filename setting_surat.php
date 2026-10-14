<?php
include 'koneksi.php';
require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
require_once __DIR__ . '/inc_surat_form.php';
require_once __DIR__ . '/inc_surat_nikah_pengantar.php';
sipandang_nikah_pengantar_format_ensure($koneksi);
require_once __DIR__ . '/inc_belum_menikah.php';
belum_menikah_ensure_format($koneksi);
require_once __DIR__ . '/inc_surat_form_config.php';
sipandang_form_config_ensure_schema($koneksi);
include 'header.php';
include 'sidebar.php';

// Proteksi Admin

// LOGIKA PENCARIAN — tidak diubah
$cari = isset($_GET['cari']) ? $_GET['cari'] : '';
$where = "";
if($cari){
    $where = "WHERE nama_surat LIKE '%$cari%' OR kode_surat LIKE '%$cari%'";
}

// Statistik (tambahan ringan, tidak mengubah logika utama)
$s_total  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_format_surat"))['c'] ?? 0;
$s_aktif  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_format_surat WHERE status_aktif='1'"))['c'] ?? 0;
$s_nonaktif = $s_total - $s_aktif;
?>
<style>
body{font-family:'Plus Jakarta Sans',sans-serif !important;background:#f4f6fb !important;}
.content-wrapper{background:#f4f6fb !important;padding:0 !important;}

/* ── HEADER ── */
.pg-hdr{background:#fff;margin:18px 18px 0;padding:14px 16px;border-radius:14px;box-shadow:0 3px 14px rgba(10,22,40,.08);border:1px solid #e3e8f3;color:var(--role-dark,#05285a);position:relative;overflow:visible;}
.pg-hdr::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 60L60 0' stroke='rgba(var(--role-rgb,11,92,173),.05)' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");}
.pg-hdr h1{font-family:'Plus Jakarta Sans',sans-serif;color:var(--role-dark,#05285a);font-size:1.02rem;font-weight:800;position:relative;margin:6px 0 4px;}
.pg-hdr p{color:#566583;font-size:.78rem;position:relative;}
.pg-badge{display:inline-flex;align-items:center;gap:6px;background:var(--role-soft,#e8f2fd);border:1px solid #e0e7f6;color:#56658d;padding:4px 13px;border-radius:20px;font-size:.7rem;font-weight:700;margin-bottom:8px;position:relative;}

.pg-wrap{margin-top:12px;padding:0 18px 24px;position:relative;z-index:10;}

/* ── STAT ── */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;}
.sc{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 4px 18px rgba(10,22,40,.09);border-left:4px solid;}
.sc .sv{font-size:1.6rem;font-weight:800;line-height:1;}
.sc .sl{font-size:.65rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#64708b;margin-top:3px;}
.sc-total{border-color:var(--role-dark,#05285a);}.sc-total .sv{color:var(--role-dark,#05285a);}
.sc-aktif{border-color:#0e7050;}.sc-aktif .sv{color:#0e7050;}
.sc-off  {border-color:#64708b;}.sc-off   .sv{color:#64708b;}

/* ── TOOLBAR ── */
.toolbar{background:#fff;border-radius:12px;padding:12px 16px;margin-bottom:14px;box-shadow:0 4px 18px rgba(10,22,40,.08);display:flex;gap:10px;flex-wrap:wrap;align-items:center;border-top:3px solid var(--role,#0b5cad);}
.fi{border:1.5px solid #dde3ed;border-radius:7px;padding:8px 12px;font-size:.85rem;font-family:inherit;outline:none;color:#172036;background:#fff;}
.fi:focus{border-color:var(--role,#0b5cad);}
.btn-p{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:7px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:all .12s;font-family:inherit;white-space:nowrap;}
.bp-add {background:var(--role,#0b5cad);color:#fff;}.bp-add:hover{background:var(--role-ink,#08428a);color:#fff;}
.bp-info{background:var(--role-soft,#d1ecf1);color:var(--role-ink,#0c5460);}.bp-info:hover{background:var(--role-ink,#0c5460);color:#fff;}
.bp-cari{background:var(--role,#0b5cad);color:#fff;border:none;}.bp-cari:hover{background:var(--role-ink,#1e3a5f);}
.bp-rst {background:#f8d7da;color:#721c24;border:none;}.bp-rst:hover{background:#dc3a52;color:#fff;}
.bp-del {background:#f8d7da;color:#721c24;border:none;}.bp-del:hover{background:#dc3a52;color:#fff;}
.bp-del:disabled{opacity:.45;cursor:not-allowed;}

#batchBar{display:none;background:var(--role-dark,#05285a);padding:10px 16px;gap:10px;align-items:center;flex-wrap:wrap;border-top:1px solid rgba(255,255,255,.08);}
#batchBar.show{display:flex;}
#batchCount{font-size:.78rem;color:rgba(255,255,255,.75);}
.chk-fmt{width:16px;height:16px;cursor:pointer;accent-color:var(--role,#0b5cad);}

/* ── TABLE ── */
.tbl-card{background:#fff;border-radius:13px;box-shadow:0 4px 18px rgba(10,22,40,.09);overflow:hidden;}
.tbl-hd{background:#f8f9fe;border-bottom:1px solid #edf0f8;padding:13px 20px;display:flex;align-items:center;justify-content:space-between;}
.tbl-hd h3{font-family:'Plus Jakarta Sans',sans-serif;color:var(--role-dark,#05285a);font-size:.95rem;margin:0;}
.tbl-hd span{color:rgba(255,255,255,.5);font-size:.78rem;}
table.dt{width:100%;border-collapse:collapse;}
.dt thead th{background:#f8f9fe;color:#566583;border-bottom:1px solid #edf0f8;font-size:.63rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;padding:11px 14px;text-align:left;border:none;white-space:nowrap;}
.dt tbody td{padding:10px 14px;font-size:.83rem;border-bottom:1px solid #eef0f8;vertical-align:middle;}
.dt tbody tr:last-child td{border-bottom:none;}
.dt tbody tr:hover{background:#fafbff;}
.dt tbody tr.row-nonaktif td{opacity:.5;}

/* BADGE TIPE */
.btipe{font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:4px;display:inline-block;margin-bottom:2px;}
.btipe-UMUM    {background:var(--role-soft,#d1ecf1);color:var(--role-ink,#0c5460);}
.btipe-KEMATIAN{background:#f8d7da;color:#721c24;}
.btipe-PINDAH  {background:#fff3cd;color:#856404;}
.btipe-USAHA   {background:#e8f5e9;color:#2e7d32;}
.btipe-NIKAH   {background:#f3e5f5;color:#6a1b9a;}
.btipe-MUTASI_PBB{background:var(--role-soft);color:var(--role);}
.btipe-GOIB    {background:#ede7f6;color:#6c3483;}

/* BADGE STATUS */
.bst{font-size:.65rem;font-weight:700;padding:3px 9px;border-radius:12px;display:inline-block;}
.bst-on {background:#d4edda;color:#155724;}
.bst-off{background:#e9ecef;color:#6c757d;}

/* BADGE KOP */
.bkop-ya    {background:#fff3cd;color:#856404;font-size:.65rem;padding:2px 7px;border-radius:4px;font-weight:700;}
.bkop-tidak {background:#f1efea;color:#5b6c8d; font-size:.65rem;padding:2px 7px;border-radius:4px;font-weight:700;}

/* TOMBOL AKSI */
.ba{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;font-size:.78rem;text-decoration:none;transition:all .12s;border:none;cursor:pointer;font-family:inherit;}
.ba-edit {background:var(--role-soft,#d1ecf1);color:var(--role-ink,#0c5460);}.ba-edit:hover{background:var(--role-ink,#0c5460);color:#fff;}
.ba-on   {background:#fff3cd;color:#856404;}.ba-on:hover{background:#856404;color:#fff;}
.ba-off  {background:#e9ecef;color:#6c757d;}.ba-off:hover{background:#6c757d;color:#fff;}
.ba-del  {background:#f8d7da;color:#721c24;}.ba-del:hover{background:#dc3a52;color:#fff;}

.tbl-foot{padding:12px 20px;background:#f7f8fd;border-top:1px solid #eef0f8;font-size:.82rem;color:#5f7093;}
.empty{text-align:center;padding:40px;color:#64708b;}
.empty i{font-size:2.5rem;display:block;margin-bottom:10px;opacity:.2;}
</style>

<div class="content-wrapper">
<div class="pg-hdr">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <span class="pg-badge"><i class="fas fa-envelope-open-text"></i>PENGATURAN SISTEM</span>
        <h1>Template Surat</h1>
        <p>Kelola format dan template surat keluar kelurahan</p>
    </div>
</div>

<section class="content"><div class="container-fluid"><div class="pg-wrap">

    <!-- STAT -->
    <div class="stat-row">
        <div class="sc sc-total"><div class="sv"><?=$s_total?></div><div class="sl"><i class="fas fa-file-alt mr-1"></i>Total Template</div></div>
        <div class="sc sc-aktif"><div class="sv"><?=$s_aktif?></div><div class="sl"><i class="fas fa-check-circle mr-1"></i>Aktif</div></div>
        <div class="sc sc-off">  <div class="sv"><?=$s_nonaktif?></div><div class="sl"><i class="fas fa-lock mr-1"></i>Nonaktif</div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <a href="tambah_format.php" class="btn-p bp-add">
            <i class="fas fa-plus-circle"></i>Tambah Template
        </a>
        <a href="tambah_format.php?preset=keramaian" class="btn-p bp-info" title="Template izin keramaian siap pakai">
            <i class="fas fa-music"></i>Izin Keramaian
        </a>
        <a href="manual_sipandang.php" class="btn-p bp-info">
            <i class="fas fa-code"></i>Panduan Kode
        </a>
        <form method="POST" action="aksi_surat.php" style="display:inline;"
              onsubmit="return confirm('Hapus otomatis semua template pengantar nikah NONAKTIF yang tidak dipakai arsip? Template aktif (Daftar & Numpang) tetap aman.')">
            <input type="hidden" name="act" value="bersihkan_duplikat">
            <?php if ($cari): ?><input type="hidden" name="cari" value="<?= htmlspecialchars($cari) ?>"><?php endif; ?>
            <button type="submit" class="btn-p bp-del" title="Bersihkan duplikat nikah nonaktif">
                <i class="fas fa-broom"></i>Bersihkan Duplikat Nikah
            </button>
        </form>
        <form method="GET" style="display:flex;gap:8px;align-items:center;margin-left:auto;flex-wrap:wrap;">
            <input type="text" name="cari" class="fi" placeholder="Cari nama atau kode surat..."
                   value="<?=htmlspecialchars($cari)?>" style="min-width:220px;">
            <button type="submit" class="btn-p bp-cari"><i class="fas fa-search"></i>Cari</button>
            <?php if($cari): ?>
            <a href="setting_surat.php" class="btn-p bp-rst"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABLE -->
    <form method="POST" action="aksi_surat.php" id="formFmtMasal">
        <input type="hidden" name="act" value="hapus_masal">
        <?php if ($cari): ?>
        <input type="hidden" name="cari" value="<?= htmlspecialchars($cari) ?>">
        <?php endif; ?>

    <div class="tbl-card">
        <div class="tbl-hd">
            <h3><i class="fas fa-list-alt mr-2"></i>Daftar Template Surat</h3>
            <span><?=mysqli_num_rows($query = mysqli_query($koneksi,"SELECT * FROM tbl_format_surat $where ORDER BY kode_surat ASC"))?> template</span>
        </div>
        <div id="batchBar">
            <span id="batchCount">0 dipilih</span>
            <div style="flex:1;"></div>
            <button type="submit" class="btn-p bp-del" id="btnHapusMasal" disabled
                    onclick="return confirm('Hapus permanen semua template yang dicentang? Tindakan ini tidak dapat dibatalkan.')">
                <i class="fas fa-trash"></i> Hapus Terpilih
            </button>
        </div>
        <div style="overflow-x:auto;">
        <table class="dt">
            <thead><tr>
                <th width="36" class="text-center"><input type="checkbox" id="checkAllFmt" class="chk-fmt" title="Pilih semua"></th>
                <th width="40" class="text-center">No</th>
                <th>Kode &amp; Nama Surat</th>
                <th>Tipe Layanan</th>
                <th>Kop Otomatis</th>
                <th>Status</th>
                <th class="text-center" width="110">Aksi</th>
            </tr></thead>
            <tbody>
            <?php
            $no = 1;
            if (!mysqli_num_rows($query)):
            ?>
            <tr><td colspan="7"><div class="empty">
                <i class="fas fa-inbox"></i>
                <p>Tidak ada template ditemukan<?=$cari?' untuk pencarian "<b>'.htmlspecialchars($cari).'</b>"':''?>.</p>
            </div></td></tr>
            <?php else: while($d = mysqli_fetch_array($query)):
                $aktif   = $d['status_aktif'] == '1';
                $tipe    = sipandang_tipe_layanan_label($d);
                $tipe_opts = sipandang_surat_tipe_layanan_options();
                $kop     = $d['kop_otomatis'] ?? 'Tidak';
            ?>
            <tr class="<?=$aktif?'':'row-nonaktif'?>">
                <td class="text-center">
                    <input type="checkbox" name="pilih_id[]" value="<?=(int)$d['id_format']?>" class="chk-fmt checkItemFmt">
                </td>
                <td class="text-center" style="color:#64708b;font-size:.78rem;"><?=$no++?></td>
                <td>
                    <div style="font-family:monospace;font-size:.78rem;color:#64708b;margin-bottom:2px;"><?=htmlspecialchars($d['kode_surat'])?></div>
                    <div style="font-weight:700;color:var(--role-dark,#05285a);"><?=htmlspecialchars($d['nama_surat'])?></div>
                </td>
                <td>
                    <span class="btipe btipe-<?=htmlspecialchars($tipe)?>"><?=htmlspecialchars($tipe_opts[$tipe] ?? $tipe)?></span>
                </td>
                <td>
                    <span class="<?=$kop==='Ya'?'bkop-ya':'bkop-tidak'?>">
                        <?=$kop==='Ya'?'✓ Ya':'— Tidak'?>
                    </span>
                </td>
                <td>
                    <span class="bst <?=$aktif?'bst-on':'bst-off'?>">
                        <?=$aktif?'● Aktif':'○ Nonaktif'?>
                    </span>
                </td>
                <td class="text-center" style="white-space:nowrap;">
                    <!-- Tombol Edit — link sama persis seperti aslinya -->
                    <a href="edit_format.php?id=<?=$d['id_format']?>" class="ba ba-edit" title="Edit Template">
                        <i class="fas fa-pencil-alt"></i>
                    </a>
                    <!-- Toggle Aktif/Nonaktif — link sama persis seperti aslinya -->
                    <a href="aksi_surat.php?act=lock&id=<?=$d['id_format']?>&val=<?=($aktif?'0':'1')?>"
                       class="ba <?=$aktif?'ba-on':'ba-off'?>"
                       title="<?=$aktif?'Nonaktifkan':'Aktifkan'?>">
                        <i class="fas <?=$aktif?'fa-unlock':'fa-lock'?>"></i>
                    </a>
                    <!-- Hapus — link sama persis seperti aslinya -->
                    <a href="aksi_surat.php?act=hapus&id=<?=$d['id_format']?><?=$cari ? '&cari=' . rawurlencode($cari) : ''?>"
                       class="ba ba-del"
                       title="Hapus"
                       onclick="return confirm('Hapus template \'<?=addslashes($d['nama_surat'])?>\'?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
        </div>
        <div class="tbl-foot">
            Total <b><?=$s_total?></b> template &nbsp;·&nbsp;
            <b style="color:#0e7050;"><?=$s_aktif?></b> aktif &nbsp;·&nbsp;
            <b style="color:#64708b;"><?=$s_nonaktif?></b> nonaktif
            &nbsp;·&nbsp; Centang beberapa baris lalu <b>Hapus Terpilih</b>
        </div>
    </div>
    </form>

</div></div></section>
</div>
<script>
(function () {
    var checkAll = document.getElementById('checkAllFmt');
    var batchBar = document.getElementById('batchBar');
    var batchCount = document.getElementById('batchCount');
    var btnHapus = document.getElementById('btnHapusMasal');

    function items() {
        return document.querySelectorAll('.checkItemFmt');
    }

    function updateBatch() {
        var n = document.querySelectorAll('.checkItemFmt:checked').length;
        if (batchCount) {
            batchCount.textContent = n + ' dipilih';
        }
        if (batchBar) {
            batchBar.classList.toggle('show', n > 0);
        }
        if (btnHapus) {
            btnHapus.disabled = n < 1;
        }
        if (checkAll && items().length) {
            checkAll.indeterminate = n > 0 && n < items().length;
            checkAll.checked = n > 0 && n === items().length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            items().forEach(function (el) {
                el.checked = checkAll.checked;
            });
            updateBatch();
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('checkItemFmt')) {
            updateBatch();
        }
    });

    updateBatch();
})();
</script>
<?php include 'footer.php'; ?>