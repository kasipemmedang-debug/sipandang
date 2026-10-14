<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
require_once __DIR__ . '/inc_arsip_ui.php';
require_once __DIR__ . '/inc_register_sktm.php';

register_sktm_ensure_schema($koneksi);

$level_user = $_SESSION['level'] ?? 'Operator';
$is_admin   = in_array(strtolower($level_user), ['admin', 'administrator'], true);

if (isset($_GET['sinkron']) && $is_admin) {
    $hasil = register_sktm_sinkron_arsip($koneksi);
    require_once __DIR__ . '/inc_admin_toast.php';
    $msg = 'Sinkron register: ' . (int) $hasil['berhasil'] . ' berhasil';
    if ((int) ($hasil['lewati'] ?? 0) > 0) {
        $msg .= ', ' . (int) $hasil['lewati'] . ' dilewati';
    }
    header('Location: register_sktm.php?' . http_build_query(['toast_type' => 'success', 'toast_msg' => $msg]));
    exit;
}

$f_tahun  = $_GET['tahun'] ?? date('Y');
$f_bulan  = $_GET['bulan'] ?? 'semua';
$f_jenis  = $_GET['jenis'] ?? 'semua';
$f_cari   = trim($_GET['cari'] ?? '');
$f_rentang = arsip_ui_rentang_tanggal_from_request();

$where = 'WHERE 1=1';
if ($f_jenis === 'BEROBAT' || $f_jenis === 'PENDIDIKAN') {
    $where .= " AND jenis='" . mysqli_real_escape_string($koneksi, $f_jenis) . "'";
}
if ($f_tahun !== 'semua' && $f_tahun !== '') {
    $where .= " AND tahun='" . mysqli_real_escape_string($koneksi, $f_tahun) . "'";
}
if ($f_bulan !== 'semua' && $f_bulan !== '') {
    $where .= " AND MONTH(tgl_daftar)='" . mysqli_real_escape_string($koneksi, $f_bulan) . "'";
}
$where .= arsip_ui_sql_rentang_tanggal($koneksi, 'tgl_daftar', $f_rentang['dari'], $f_rentang['sampai']);
if ($f_cari !== '') {
    $ec = mysqli_real_escape_string($koneksi, $f_cari);
    $where .= " AND (nama_lengkap LIKE '%$ec%' OR nik LIKE '%$ec%' OR no_surat LIKE '%$ec%' OR nama_ayah LIKE '%$ec%' OR nama_ibu LIKE '%$ec%')";
}

$rows = [];
$q = mysqli_query($koneksi, "SELECT * FROM tbl_register_sktm $where ORDER BY tgl_daftar DESC, no_urut DESC");
while ($q && ($r = mysqli_fetch_assoc($q))) {
    $rows[] = $r;
}
$total = count($rows);
$stat_tahun = (int) (mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) c FROM tbl_register_sktm WHERE tahun='" . mysqli_real_escape_string($koneksi, (string) $f_tahun) . "'"
))['c'] ?? 0);
$stat_berobat = (int) (mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) c FROM tbl_register_sktm WHERE tahun='" . mysqli_real_escape_string($koneksi, (string) $f_tahun) . "' AND jenis='BEROBAT'"
))['c'] ?? 0);
$stat_pend = (int) (mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) c FROM tbl_register_sktm WHERE tahun='" . mysqli_real_escape_string($koneksi, (string) $f_tahun) . "' AND jenis='PENDIDIKAN'"
))['c'] ?? 0);

$qPrint = http_build_query(array_filter([
    'tahun' => $f_tahun !== 'semua' ? $f_tahun : null,
    'bulan' => $f_bulan !== 'semua' ? $f_bulan : null,
    'jenis' => $f_jenis !== 'semua' ? $f_jenis : null,
    'cari'  => $f_cari !== '' ? $f_cari : null,
    'tgl_dari' => $f_rentang['dari'] !== '' ? $f_rentang['dari'] : null,
    'tgl_sampai' => $f_rentang['sampai'] !== '' ? $f_rentang['sampai'] : null,
]));

include 'header.php';
include 'sidebar.php';
arsip_ui_assets();
arsip_ui_styles();
?>
<style>
.content-wrapper{background:var(--bg)!important;padding:0!important;}
.rsk-wrap{padding:0 18px 28px;}
.sc{display:flex;align-items:center;gap:12px;background:#fff;border-radius:var(--radius);padding:14px 16px;border:1px solid var(--border);box-shadow:var(--shadow);min-width:0;}
.sc .v{font-size:1.35rem;font-weight:800;color:var(--navy);}
.sc .l{font-size:.72rem;color:var(--muted);font-weight:600;}
.badge-jenis{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.68rem;font-weight:700;}
.bj-berobat{background:var(--role-soft,#e8f2fd);color:var(--role-ink,#08428a);}
.bj-pend{background:#e8f8ee;color:#0e7050;}
.rsk-preview{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-top:8px;font-size:.82rem;}
.rsk-preview table.form-reg{width:100%;border-collapse:collapse;}
.rsk-preview table.form-reg td{padding:2px 0;vertical-align:baseline;}
.rsk-preview .lbl{width:118px;font-weight:700;white-space:nowrap;color:var(--navy);}
.rsk-preview .sep{width:10px;text-align:center;font-weight:700;}
.rsk-preview .val-line{display:inline-block;min-width:55%;border-bottom:1px solid #ccc;padding-bottom:1px;}
.rsk-preview .kep-box{margin-top:6px;font-weight:700;}
.rsk-preview .strike{text-decoration:line-through;color:var(--muted);font-weight:400;}
.rsk-preview .entry{margin:0;padding:0;border:none;}
@media print{.loket-dock-wrap,.loket-fab-desktop-wrap,#loketMenuOverlay,.no-print{display:none!important;}}
</style>

<div class="content-wrapper">
<div class="arsip-header rsk-header">
    <span class="header-badge"><i class="fas fa-book mr-1"></i> REGISTER</span>
    <h1>Register SKTM</h1>
    <p>Otomatis dari surat <strong>SKTM Berobat</strong> &amp; <strong>SKTM Pendidikan</strong> · <?= arsip_ui_tgl_indo(); ?></p>
</div>

<section class="content"><div class="container-fluid"><div class="rsk-wrap">

<div class="row mb-3" style="margin-top:14px;">
    <div class="col-md-4 mb-2"><div class="sc"><i class="fas fa-layer-group" style="color:var(--accent);font-size:1.2rem;"></i><div><div class="v"><?= $stat_tahun; ?></div><div class="l">Total <?= htmlspecialchars((string) $f_tahun); ?></div></div></div></div>
    <div class="col-md-4 mb-2"><div class="sc"><i class="fas fa-hand-holding-heart" style="color:var(--role-ink,#08428a);font-size:1.2rem;"></i><div><div class="v"><?= $stat_berobat; ?></div><div class="l">SKTM Berobat</div></div></div></div>
    <div class="col-md-4 mb-2"><div class="sc"><i class="fas fa-graduation-cap" style="color:#0e7050;font-size:1.2rem;"></i><div><div class="v"><?= $stat_pend; ?></div><div class="l">SKTM Pendidikan</div></div></div></div>
</div>

<div class="filter-card mb-3">
    <div class="fc-title"><i class="fas fa-sliders-h" style="color:var(--accent);"></i> Filter</div>
    <form method="get">
        <div class="row">
            <div class="col-md-2 col-6 mb-3">
                <label class="lbl-f">Tahun</label>
                <select name="tahun" class="inp-f">
                    <option value="semua" <?= $f_tahun === 'semua' ? 'selected' : ''; ?>>— Semua —</option>
                    <?php for ($y = (int) date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y; ?>" <?= (string) $f_tahun === (string) $y ? 'selected' : ''; ?>><?= $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <label class="lbl-f">Bulan</label>
                <select name="bulan" class="inp-f">
                    <option value="semua" <?= $f_bulan === 'semua' ? 'selected' : ''; ?>>— Semua —</option>
                    <?php foreach (sipandang_bulan_map() as $k => $v): ?>
                    <option value="<?= (int) $k; ?>" <?= (string) $f_bulan === (string) $k ? 'selected' : ''; ?>><?= htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6 mb-3">
                <label class="lbl-f">Jenis</label>
                <select name="jenis" class="inp-f">
                    <option value="semua" <?= $f_jenis === 'semua' ? 'selected' : ''; ?>>— Semua —</option>
                    <option value="BEROBAT" <?= $f_jenis === 'BEROBAT' ? 'selected' : ''; ?>>SKTM Berobat</option>
                    <option value="PENDIDIKAN" <?= $f_jenis === 'PENDIDIKAN' ? 'selected' : ''; ?>>SKTM Pendidikan</option>
                </select>
            </div>
            <?php arsip_ui_filter_rentang_tanggal_fields($f_rentang['dari'], $f_rentang['sampai']); ?>
            <div class="col-md-3 col-12 mb-3">
                <label class="lbl-f">Cari Nama / NIK / Surat</label>
                <input type="text" name="cari" class="inp-f" value="<?= htmlspecialchars($f_cari); ?>" placeholder="Kata kunci...">
            </div>
        </div>
        <div class="d-flex flex-wrap" style="gap:8px;">
            <button type="submit" class="btn-pro bp-navy"><i class="fas fa-search"></i> Tampilkan</button>
            <a href="cetak_register_sktm.php?<?= htmlspecialchars($qPrint); ?>" target="_blank" class="btn-pro bp-green"><i class="fas fa-print"></i> Cetak Buku Register</a>
            <?php if ($is_admin): ?>
            <a href="register_sktm.php?sinkron=1" class="btn-pro" style="background:var(--role-soft,#e8f4fd);color:var(--role-ink);border:1px solid var(--role-light);"
               data-sip-confirm="Ambil surat SKTM dari arsip yang belum masuk register?"
               data-sip-confirm-title="Sinkron dari Arsip"
               data-sip-confirm-ok="Ya, Sinkronkan">
                <i class="fas fa-database"></i> Sinkron dari Arsip
            </a>
            <?php endif; ?>
            <a href="buat_surat.php" class="btn-pro bp-gold" style="margin-left:auto;"><i class="fas fa-plus-circle"></i> Buat Surat SKTM</a>
        </div>
        <p style="margin:10px 0 0;font-size:.76rem;color:var(--muted);">
            Setiap surat <strong>SKTM</strong> / <strong>SKTM Pendidikan</strong> yang disimpan otomatis masuk register.
            Berobat = keperluan <em>Berobat</em>; Pendidikan = keperluan <em>Sekolah</em>.
        </p>
    </form>
</div>

<div class="table-card">
    <div class="tc-header">
        <h3><i class="fas fa-list-alt mr-2"></i> Daftar Register SKTM</h3>
        <span class="tc-count"><?= $total; ?> data</span>
    </div>
    <table id="tblRegisterSktm" class="table tbl-arsip" style="margin:0;width:100%;">
        <thead>
            <tr>
                <th>No. Reg</th>
                <th>Tgl Daftar</th>
                <th>Jenis</th>
                <th>Nama</th>
                <th>NIK</th>
                <th>Orang Tua</th>
                <th>Keperluan</th>
                <th>No. Surat</th>
                <th style="width:72px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $d): ?>
            <tr>
                <td class="text-center"><strong><?= (int) $d['no_urut']; ?></strong></td>
                <td><?= register_sktm_format_tgl($d['tgl_daftar'] ?? ''); ?></td>
                <td>
                    <span class="badge-jenis <?= ($d['jenis'] ?? '') === 'PENDIDIKAN' ? 'bj-pend' : 'bj-berobat'; ?>">
                        <?= htmlspecialchars(register_sktm_label_jenis((string) ($d['jenis'] ?? ''))); ?>
                    </span>
                </td>
                <td><strong><?= htmlspecialchars($d['nama_lengkap'] ?? ''); ?></strong></td>
                <td style="font-family:monospace;font-size:.75rem;"><?= htmlspecialchars($d['nik'] ?? ''); ?></td>
                <td style="font-size:.75rem;">
                    <?= htmlspecialchars($d['nama_ayah'] ?? '-'); ?><br>
                    <span style="color:var(--muted);"><?= htmlspecialchars($d['nama_ibu'] ?? '-'); ?></span>
                </td>
                <td style="font-size:.75rem;">
                    <strong><?= htmlspecialchars($d['keperluan_tipe'] ?? ''); ?></strong>
                    <?php if (!empty($d['keperluan_detail'])): ?><br><?= htmlspecialchars($d['keperluan_detail']); ?><?php endif; ?>
                </td>
                <td style="font-size:.75rem;"><?= htmlspecialchars($d['no_surat'] ?? ''); ?></td>
                <td class="text-nowrap">
                    <button type="button" class="act a-view btn-prev-reg" title="Susunan buku register"
                            data-id="<?= (int) $d['id_register']; ?>">
                        <i class="fas fa-book-open"></i>
                    </button>
                    <?php if (!empty($d['id_surat'])): ?>
                    <a href="arsip_detail.php?id=<?= (int) $d['id_surat']; ?>" class="act a-view" title="Arsip surat"><i class="fas fa-folder-open"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($rows as $d): ?>
<div id="tpl-reg-<?= (int) $d['id_register']; ?>" class="d-none"><?= register_sktm_html_entri($d); ?></div>
<?php endforeach; ?>

</div></div></section></div>

<div id="modalPrevReg" style="display:none;position:fixed;inset:0;background:rgba(10,22,40,.55);z-index:9999;padding:24px;overflow:auto;">
    <div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;padding:20px 22px;box-shadow:0 12px 40px rgba(0,0,0,.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <strong style="color:var(--navy);"><i class="fas fa-book-open mr-1"></i> Susunan Buku Register</strong>
            <button type="button" id="btnClosePrevReg" class="btn-pro" style="padding:6px 12px;">Tutup</button>
        </div>
        <div id="modalPrevRegBody" class="rsk-preview"></div>
    </div>
</div>

<script>
$(function(){
    var $tbl = $('#tblRegisterSktm');
    if ($.fn.DataTable && $tbl.find('tbody tr').length) {
        $tbl.DataTable({
            order:[[1,'desc']],
            pageLength:25,
            language:{url:'//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json'},
            columnDefs:[{ orderable:false, targets:-1 }]
        });
    }
    var $modal = $('#modalPrevReg');
    $tbl.on('click', '.btn-prev-reg', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        $('#modalPrevRegBody').html($('#tpl-reg-' + id).html() || '');
        $modal.fadeIn(150);
    });
    $('#btnClosePrevReg, #modalPrevReg').on('click', function(e){
        if (e.target === this) { $modal.fadeOut(150); }
    });
});
</script>
<?php include 'footer.php'; ?>
