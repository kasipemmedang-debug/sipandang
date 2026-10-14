<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_waris.php';
waris_ensure_schema($koneksi);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nama_almarhum'])) {
    $token = 'pw_' . uniqid();
    $_SESSION['preview_waris'][$token] = $_POST;
    header('Location: preview_waris.php?token=' . urlencode($token));
    exit;
}

$token = $_GET['token'] ?? '';
if ($token === '' || empty($_SESSION['preview_waris'][$token])) {
    header('Location: buat_surat_waris.php');
    exit;
}
$_POST = $_SESSION['preview_waris'][$token];

$D = waris_data_from_post($_POST);
$meta = waris_compute_meta($D);
$html_pages = waris_render_pages($D, $koneksi);
$_SESSION['draft_waris'] = ['post' => $_POST, 'data' => $D, 'token' => $token];

$pamong_nama = '-';
$pamong_jab = '-';
$pamong_kec_nama = '-';
$pamong_kec_jab = '-';
if ((int) ($D['id_pamong'] ?? 0) > 0) {
    $pm = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM tbl_pamong WHERE id_pamong=' . (int) $D['id_pamong'] . ' LIMIT 1'));
    if ($pm) {
        $pamong_nama = $pm['nama_pamong'] ?? '-';
        $pamong_jab = $pm['jabatan'] ?? '-';
    }
}
if ((int) ($D['id_pamong_kec'] ?? 0) > 0) {
    $pmk = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM tbl_pamong WHERE id_pamong=' . (int) $D['id_pamong_kec'] . ' LIMIT 1'));
    if ($pmk) {
        $pamong_kec_nama = $pmk['nama_pamong'] ?? '-';
        $pamong_kec_jab = $pmk['jabatan'] ?? '-';
    }
}

$nama_operator = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin';
$lembar = !empty($D['buat_kuasa']) ? 2 : 1;

include 'header.php';
include 'sidebar.php';
echo waris_page_styles(true);
?>
<style>
:root{--navy:var(--role-dark,#05285a);--navy-soft:var(--role-ink,#08428a);--accent:var(--role,#0b5cad);--accent-lt:var(--role-soft,#e8f2fd);--bg:#f4f6fb;--white:#fff;--text:#172036;--muted:#64708b;--border:#dde3ed;--success:#0e7050;--danger:#dc3a52;}
body{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--bg)!important;}
.content-wrapper{background:var(--bg)!important;padding:0!important;}
.preview-layout{display:flex;height:calc(100vh - 57px);overflow:hidden;}
.sidebar-panel{width:310px;flex-shrink:0;background:var(--white);border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
.preview-area{flex:1;overflow:auto;background:#525659;padding:30px 20px 60px;}
.sidebar-header{background:var(--navy);color:#fff;padding:18px 20px;border-bottom:3px solid var(--accent);}
.sidebar-header h2{font-family: 'Plus Jakarta Sans', sans-serif;font-size:1rem;margin:0;color:var(--accent);}
.sidebar-section{padding:18px 20px;border-bottom:1px solid var(--border);}
.sidebar-section-title{font-size:.68rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:12px;}
.info-row{display:flex;gap:10px;margin-bottom:10px;font-size:.82rem;}
.info-row .lbl{color:var(--muted);min-width:88px;font-size:.72rem;}
.info-row .val{font-weight:600;color:var(--text);flex:1;}
.action-btn{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;margin-bottom:8px;border:none;font-weight:600;width:100%;cursor:pointer;text-decoration:none;font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;}
.action-btn-primary{background:var(--navy);color:var(--role-light);}
.action-btn-success{background:#f0faf4;color:var(--success);border:1.5px solid #86efac;}
.action-btn-warning{background:#fff8e6;color:#7a5800;border:1.5px solid #f0d080;}
.action-btn-ghost{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);}
.action-btn-danger{background:#fef2f2;color:var(--danger);border:1.5px solid #fca5a5;}
.edit-toolbar{display:none;background:var(--navy);padding:10px 14px;border-radius:8px;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.edit-toolbar.show{display:flex;}
.toolbar-btn{background:rgba(255,255,255,.1);color:var(--accent);border:1px solid rgba(var(--role-rgb,11,92,173),.3);border-radius:5px;padding:4px 10px;cursor:pointer;}
.mode-bar{text-align:center;margin-bottom:12px;}
.mode-chip{display:inline-flex;gap:6px;background:rgba(255,255,255,.12);color:rgba(255,255,255,.75);padding:4px 14px;border-radius:20px;font-size:.72rem;border:1px solid rgba(255,255,255,.15);}
.tpl-badge{display:inline-block;background:rgba(var(--role-rgb,11,92,173),.15);color:var(--accent-lt);border:1px solid rgba(var(--role-rgb,11,92,173),.35);padding:2px 8px;border-radius:4px;font-size:.65rem;font-family:monospace;margin-top:6px;}
@media print{
    .sidebar-panel,.edit-toolbar,.mode-bar,.main-header,.main-sidebar,.main-footer{display:none!important;}
    .preview-layout{height:auto;}
    .preview-area{background:#fff!important;padding:0!important;}
    #area_surat{outline:none!important;}
}
</style>
<style>
/* BPJS tone alignment (UI only) */
:root{
    --navy:var(--role-dark,#05285a) !important;
    --navy-soft:var(--role-ink,#08428a) !important;
    --accent:var(--role,#0b5cad) !important;
    --accent-lt:var(--role-soft,#e8f2fd) !important;
    --bg:#f4f6fb !important;
    --muted:#566583 !important;
    --border:#e3e8f3 !important;
}
.content-wrapper{background:var(--bg)!important;}
.sidebar-panel{
    border-right:1px solid var(--border) !important;
    background:#fff !important;
}
.sidebar-header{
    background:#fff !important;
    color:var(--navy) !important;
    border-bottom:1px solid var(--border) !important;
}
.sidebar-header h2{
    font-family:'Plus Jakarta Sans',sans-serif !important;
    color:var(--navy) !important;
    font-size:.95rem !important;
    font-weight:800 !important;
}
.sidebar-header p{color:var(--muted) !important;}
.action-btn-primary{background:var(--accent) !important;color:#fff !important;}
.action-btn-primary:hover{background:var(--navy-soft) !important;color:#fff !important;}
.action-btn-success{
    background:var(--role-soft,#e8f2fd) !important;
    color:var(--role-ink,#08428a) !important;
    border:1.5px solid var(--role-line,#c6dcf5) !important;
}
.action-btn-success:hover{background:var(--accent) !important;color:#fff !important;}
.action-btn-warning{
    background:var(--role-soft,#e8f2fd) !important;
    color:var(--role-ink,#08428a) !important;
    border:1.5px solid var(--role-line,#c6dcf5) !important;
}
.action-btn-warning:hover{background:var(--role-soft,#dfe7ff) !important;}
.action-btn-ghost:hover{border-color:var(--accent) !important;color:var(--navy-soft) !important;}
.edit-toolbar{background:var(--navy-soft) !important;}
</style>

<div class="content-wrapper">
<div class="preview-layout">
    <div class="sidebar-panel">
        <div class="sidebar-header">
            <h2><i class="fas fa-users mr-2"></i>Preview Surat Waris</h2>
            <p style="font-size:.72rem;opacity:.7;margin:4px 0 0;"><?= $lembar; ?> lembar A4</p>
            <span class="tpl-badge">tpl <?= htmlspecialchars(WARIS_TEMPLATE_VERSION); ?></span>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Data Utama</div>
            <div class="info-row"><span class="lbl">Register</span><span class="val" style="font-family:monospace;"><?= htmlspecialchars($D['no_register']); ?></span></div>
            <div class="info-row"><span class="lbl">Almarhum</span><span class="val"><?= htmlspecialchars($D['nama_almarhum']); ?> (<?= htmlspecialchars($D['jenis_almarhum']); ?>)</span></div>
            <div class="info-row"><span class="lbl">Ahli waris</span><span class="val"><?= (int) $meta['jml_waris']; ?> orang</span></div>
            <div class="info-row"><span class="lbl">Camat</span><span class="val"><?= htmlspecialchars($pamong_kec_nama); ?><br><small style="color:var(--muted);"><?= htmlspecialchars($pamong_kec_jab); ?></small></span></div>
            <div class="info-row"><span class="lbl">Lurah</span><span class="val"><?= htmlspecialchars($pamong_nama); ?><br><small style="color:var(--muted);"><?= htmlspecialchars($pamong_jab); ?></small></span></div>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Aksi</div>
            <form id="formSimpan" action="proses_waris.php" method="POST">
                <input type="hidden" name="simpan_waris" value="1">
                <?= waris_hidden_post_inputs($_POST); ?>
                <textarea name="isi_html" id="html_content" style="display:none;"></textarea>
                <button type="button" onclick="simpanDanCetak()" class="action-btn action-btn-primary">
                    <i class="fas fa-save"></i><span>Simpan & Cetak</span>
                </button>
            </form>
            <button type="button" onclick="window.print()" class="action-btn action-btn-success">
                <i class="fas fa-print"></i><span>Cetak Langsung</span>
            </button>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Edit & Navigasi</div>
            <button type="button" onclick="aktifkanEdit()" id="btnEdit" class="action-btn action-btn-warning">
                <i class="fas fa-edit"></i><span>Aktifkan Edit Teks</span>
            </button>
            <button type="button" onclick="matikanEdit()" id="btnSelesai" class="action-btn action-btn-ghost" style="display:none;">
                <i class="fas fa-check"></i><span>Selesai Edit</span>
            </button>
            <form action="buat_surat_waris.php" method="POST">
                <?= waris_hidden_post_inputs($_POST); ?>
                <button type="submit" class="action-btn action-btn-ghost"><i class="fas fa-arrow-left"></i><span>Kembali ke Form</span></button>
            </form>
            <a href="buat_surat_waris.php" class="action-btn action-btn-danger"><i class="fas fa-times"></i><span>Batalkan</span></a>
            <a href="data_waris.php" class="action-btn action-btn-ghost"><i class="fas fa-archive"></i><span>Arsip Waris</span></a>
        </div>
    </div>
    <div class="preview-area">
        <div class="mode-bar"><div class="mode-chip" id="mode-chip"><i class="fas fa-eye"></i> Preview — <?= $lembar; ?> lembar</div></div>
        <div class="edit-toolbar" id="editToolbar">
            <button type="button" class="toolbar-btn" onclick="execCmd('bold')"><i class="fas fa-bold"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('italic')"><i class="fas fa-italic"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('underline')"><i class="fas fa-underline"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyLeft')"><i class="fas fa-align-left"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyCenter')"><i class="fas fa-align-center"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('undo')"><i class="fas fa-undo"></i></button>
        </div>
        <div id="area_surat"><?= $html_pages; ?></div>
    </div>
</div>
</div>
<script>
var kertas = document.getElementById('area_surat');
var formSimpan = document.getElementById('formSimpan');
function aktifkanEdit(){
    kertas.contentEditable='true'; kertas.classList.add('edit-aktif');
    document.getElementById('editToolbar').classList.add('show');
    document.getElementById('btnEdit').style.display='none';
    document.getElementById('btnSelesai').style.display='flex';
    document.getElementById('mode-chip').innerHTML='<i class="fas fa-pen"></i> Mode Edit';
}
function matikanEdit(){
    kertas.contentEditable='false'; kertas.classList.remove('edit-aktif');
    document.getElementById('editToolbar').classList.remove('show');
    document.getElementById('btnEdit').style.display='flex';
    document.getElementById('btnSelesai').style.display='none';
}
function execCmd(c){ document.execCommand(c,false,null); kertas.focus(); }
function simpanDanCetak(){
    matikanEdit();
    document.getElementById('html_content').value = kertas.innerHTML;
    formSimpan.submit();
}
</script>
<?php include 'footer.php'; ?>
