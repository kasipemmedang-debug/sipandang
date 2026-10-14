<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login(false);
sipandang_require_persuratan_kantor(false);

include 'koneksi.php';
require_once __DIR__ . '/inc_persuratan_kantor.php';
require_once __DIR__ . '/inc_berkas_storage.php';
require_once __DIR__ . '/inc_arsip_ui.php';
require_once __DIR__ . '/inc_form_select2_ui.php';
require_once __DIR__ . '/inc_admin_toast.php';
sipandang_persuratan_ensure_schema($koneksi);

// ── SARAN NOMOR AGENDA (hanya referensi, user tetap isi manual) ──
$tahun_ini = date('Y');
$q_last = mysqli_query($koneksi, "SELECT no_agenda FROM tbl_surat_masuk WHERE YEAR(tanggal_terima) = '$tahun_ini' ORDER BY id_surat DESC LIMIT 1");
$d_last = $q_last ? mysqli_fetch_assoc($q_last) : null;
$next_agenda = $d_last ? $d_last['no_agenda'] : '-';

// ── PROSES SIMPAN ──
if (isset($_POST['simpan'])) {
    $no_agenda  = trim(mysqli_real_escape_string($koneksi, $_POST['no_agenda'] ?? ''));
    $tgl_terima = $_POST['tgl_terima'];
    $asal       = mysqli_real_escape_string($koneksi, strtoupper($_POST['asal_surat']));
    $no_surat   = mysqli_real_escape_string($koneksi, $_POST['nomor_surat']);
    $tgl_surat  = $_POST['tgl_surat'];
    $perihal    = mysqli_real_escape_string($koneksi, strtoupper($_POST['perihal']));
    $sifat      = $_POST['sifat'];
    $file_name  = null;
    $ket_pelaksanaan = mysqli_real_escape_string($koneksi, strtoupper(trim($_POST['ket_pelaksanaan'] ?? '')));
    if ($no_agenda === '') {
        header('Location: surat_masuk.php?' . http_build_query([
            'toast_type' => 'error',
            'msg'        => 'Nomor agenda wajib diisi.',
        ]));
        exit;
    }
    if (!empty($_FILES['file_scan']['name'])) {
        $file_name = date('YmdHis').'_'.preg_replace('/[^a-zA-Z0-9_\-]/', '', $_FILES['file_scan']['name']);
        if (!is_dir('berkas_surat')) {
            mkdir('berkas_surat', 0755, true);
        }
        move_uploaded_file($_FILES['file_scan']['tmp_name'], 'berkas_surat/'.$file_name);
    }
    mysqli_query($koneksi, "ALTER TABLE tbl_surat_masuk MODIFY COLUMN no_agenda VARCHAR(50) NOT NULL DEFAULT ''");
    mysqli_query($koneksi, "ALTER TABLE tbl_surat_masuk ADD COLUMN IF NOT EXISTS ket_pelaksanaan VARCHAR(255) DEFAULT ''");
    $sql = "INSERT INTO tbl_surat_masuk (no_agenda, tanggal_terima, asal_surat, nomor_surat_pengirim, tanggal_surat_pengirim, perihal, sifat_surat, file_scan, ket_pelaksanaan)
            VALUES ('$no_agenda','$tgl_terima','$asal','$no_surat','$tgl_surat','$perihal','$sifat','$file_name','$ket_pelaksanaan')";
    if (mysqli_query($koneksi, $sql)) {
        sipandang_redirect_toast('surat_masuk.php', 'surat_masuk_ok');
    }
    header('Location: surat_masuk.php?' . http_build_query([
        'toast_type' => 'error',
        'msg'        => 'Gagal: '.mysqli_error($koneksi),
    ]));
    exit;
}

// ── PROSES HAPUS ──
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM tbl_surat_masuk WHERE id_surat='$id'");
    sipandang_redirect_toast('surat_masuk.php', 'hapus');
}

include 'header.php';
include 'sidebar.php';

// ── STATISTIK ──
$total     = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_surat_masuk WHERE YEAR(tanggal_terima)='$tahun_ini'"))['c'];
$segera    = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_surat_masuk WHERE sifat_surat IN('Segera','Sangat Segera') AND YEAR(tanggal_terima)='$tahun_ini'"))['c'];
$bulan_ini = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_surat_masuk WHERE MONTH(tanggal_terima)=MONTH(NOW()) AND YEAR(tanggal_terima)='$tahun_ini'"))['c'];
$ada_file  = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_surat_masuk WHERE file_scan IS NOT NULL AND file_scan!=''"))['c'];

// ── FILTER & SEARCH ──
$cari   = isset($_GET['cari'])   ? mysqli_real_escape_string($koneksi, $_GET['cari'])   : '';
$fsifat = isset($_GET['fsifat']) ? mysqli_real_escape_string($koneksi, $_GET['fsifat']) : '';
$where  = "1=1";
if($cari)   $where .= " AND (asal_surat LIKE '%$cari%' OR perihal LIKE '%$cari%' OR nomor_surat_pengirim LIKE '%$cari%')";
if($fsifat) $where .= " AND sifat_surat='$fsifat'";
$q = mysqli_query($koneksi, "SELECT * FROM tbl_surat_masuk WHERE $where ORDER BY id_surat DESC LIMIT 50");

$bulan_id = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
             '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
function tgl_short($t){ global $bulan_id; if(!$t||$t=='0000-00-00') return '-'; $p=explode('-',$t); return $p[2].' '.$bulan_id[$p[1]].' '.$p[0]; }
?>

<?php arsip_ui_assets(); arsip_ui_styles(); arsip_ui_persuratan_styles(); sipandang_form_select2_styles(); ?>

<div class="content-wrapper" style="background:var(--bg);padding:0;">

<!-- ── HEADER ── -->
<div class="sm-hdr">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <span class="hdr-badge"><i class="fas fa-envelope-open-text mr-1"></i> PERSURATAN</span>
        <h1>Agenda Surat Masuk & Disposisi</h1>
        <p><?= date('l, d F Y'); ?> &nbsp;·&nbsp; Kelurahan Medang</p>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="sm-wrap">

<!-- ── STAT CARDS ── -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-ico navy"><i class="fas fa-envelope"></i></div>
        <div><div class="stat-val"><?= $total; ?></div><div class="stat-lbl">Total Surat <?= $tahun_ini; ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico gold"><i class="fas fa-calendar-alt"></i></div>
        <div><div class="stat-val"><?= $bulan_ini; ?></div><div class="stat-lbl">Bulan Ini</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico danger"><i class="fas fa-bolt"></i></div>
        <div><div class="stat-val"><?= $segera; ?></div><div class="stat-lbl">Segera / Sangat Segera</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico success"><i class="fas fa-paperclip"></i></div>
        <div><div class="stat-val"><?= $ada_file; ?></div><div class="stat-lbl">Ada File Scan</div></div>
    </div>
</div>

<!-- ── MAIN GRID ── -->
<div class="main-grid">

    <!-- FORM INPUT -->
    <div class="form-card">
        <div class="form-card-hdr">
            <h3><i class="fas fa-plus-circle mr-2"></i> Catat Surat Baru</h3>
        </div>
        <div class="form-card-body">
            <form method="POST" enctype="multipart/form-data" id="formSuratMasuk">

                <div class="mb-3">
                    <label class="lbl">No. Agenda</label>
                    <input type="text" name="no_agenda" id="inputNoAgenda" class="inp" placeholder="Isi nomor agenda (angka atau huruf)" required autocomplete="off">
                    <div class="agenda-preview mt-2">
                        <div class="agenda-num" id="agendaPreviewNum">---</div>
                        <div>
                            <div class="agenda-sub">No. terakhir: <?= htmlspecialchars((string)$next_agenda); ?></div>
                            <div class="agenda-yr">Buku agenda tahun <?= $tahun_ini; ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="lbl">Tanggal Terima <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="tgl_terima" class="inp" value="<?= date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="lbl">Asal Surat (Instansi) <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="asal_surat" class="inp text-uppercase" placeholder="Nama Instansi / Kantor..." required>
                </div>

                <div class="row" style="margin:0 -6px;">
                    <div class="col-7 px-1 mb-3">
                        <label class="lbl">No. Surat <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="nomor_surat" class="inp" placeholder="Nomor surat pengirim" required>
                    </div>
                    <div class="col-5 px-1 mb-3">
                        <label class="lbl">Tgl. Surat <span style="color:var(--danger);">*</span></label>
                        <input type="date" name="tgl_surat" class="inp" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="lbl">Perihal <span style="color:var(--danger);">*</span></label>
                    <textarea name="perihal" class="inp text-uppercase" rows="3" placeholder="Isi perihal surat..." required></textarea>
                </div>

                <div class="mb-3">
                    <label class="lbl">Sifat Surat</label>
                    <select name="sifat" class="inp form-select2">
                        <option value="Biasa">Biasa</option>
                        <option value="Segera">Segera</option>
                        <option value="Sangat Segera">Sangat Segera</option>
                        <option value="Rahasia">Rahasia</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="lbl">Ket / Pelaksanaan</label>
                    <input type="text" name="ket_pelaksanaan" class="inp text-uppercase" placeholder="Contoh: Diteruskan ke Seksi..., Ditindaklanjuti...">
                </div>

                <div class="mb-3">
                    <label class="lbl">Scan File (PDF / JPG / PNG)</label>
                    <div class="file-drop" id="fileDrop" onclick="document.getElementById('file_scan').click()"
                         ondragover="event.preventDefault();this.classList.add('drag')"
                         ondragleave="this.classList.remove('drag')"
                         ondrop="handleDrop(event)">
                        <input type="file" name="file_scan" id="file_scan" accept=".pdf,.jpg,.jpeg,.png"
                               onchange="showFileName(this)">
                        <i class="fas fa-cloud-upload-alt" style="font-size:1.4rem;color:var(--muted);display:block;margin-bottom:4px;"></i>
                        <p>Klik untuk upload atau drag & drop</p>
                        <div class="fn" id="fn-display" style="display:none;"></div>
                    </div>
                </div>

                <button type="submit" name="simpan" class="btn-pro bp-gold">
                    <i class="fas fa-save"></i> Simpan Surat Masuk
                </button>
            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="tbl-card">
        <div class="tbl-hdr">
            <h3><i class="fas fa-list-alt mr-2" style="color:var(--accent);"></i>Data Surat Masuk</h3>
            <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <div class="search-bar">
                    <input type="text" name="cari" value="<?= htmlspecialchars($cari); ?>" placeholder="Cari asal, perihal, nomor...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                <select name="fsifat" class="inp-f form-select2" onchange="this.form.submit()">
                    <option value="">Semua Sifat</option>
                    <option value="Biasa"       <?= $fsifat=='Biasa'?'selected':''; ?>>Biasa</option>
                    <option value="Segera"      <?= $fsifat=='Segera'?'selected':''; ?>>Segera</option>
                    <option value="Sangat Segera" <?= $fsifat=='Sangat Segera'?'selected':''; ?>>Sangat Segera</option>
                    <option value="Rahasia"     <?= $fsifat=='Rahasia'?'selected':''; ?>>Rahasia</option>
                </select>
                <?php if($cari||$fsifat): ?>
                <a href="surat_masuk.php" style="font-size:.75rem;color:var(--danger);text-decoration:none;"><i class="fas fa-times mr-1"></i>Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table class="surat-table">
                <thead>
                    <tr>
                        <th style="width:50px;">Agenda</th>
                        <th>Asal Surat</th>
                        <th style="min-width:140px;">No. & Tgl. Surat</th>
                        <th>Perihal</th>
                        <th style="width:90px;">Sifat</th>
                        <th style="width:90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($q) == 0): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox d-block"></i>
                            <p style="margin:0;">Belum ada surat masuk<?= ($cari||$fsifat)?' yang sesuai filter.':'.'; ?></p>
                        </td>
                    </tr>
                    <?php else: while($d = mysqli_fetch_array($q)): ?>
                    <tr>
                        <td class="text-center">
                            <div class="agenda-badge"><?= $d['no_agenda']; ?></div>
                            <div style="font-size:.65rem;color:var(--muted);margin-top:3px;"><?= tgl_short($d['tanggal_terima']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700;color:var(--navy);font-size:.83rem;"><?= htmlspecialchars($d['asal_surat']); ?></div>
                        </td>
                        <td>
                            <div style="font-size:.78rem;font-family:monospace;color:var(--text);"><?= htmlspecialchars($d['nomor_surat_pengirim']); ?></div>
                            <div style="font-size:.7rem;color:var(--muted);"><?= tgl_short($d['tanggal_surat_pengirim']); ?></div>
                        </td>
                        <td>
                            <div style="font-size:.82rem;color:var(--text);max-width:260px;"><?= htmlspecialchars($d['perihal']); ?></div>
                        </td>
                        <td>
                            <?php
                            $sc = match($d['sifat_surat']){
                                'Segera'       => 'sb-segera',
                                'Sangat Segera'=> 'sb-sangat-segera',
                                'Rahasia'      => 'sb-rahasia',
                                default        => 'sb-biasa'
                            };
                            ?>
                            <span class="sifat-badge <?= $sc; ?>"><?= htmlspecialchars($d['sifat_surat']); ?></span>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;justify-content:center;">
                                <a href="cetak_disposisi.php?id=<?= $d['id_surat']; ?>" target="_blank"
                                   class="btn-sm-ico btn-print" title="Cetak Lembar Disposisi">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if(!empty($d['file_scan'])): ?>
                                <a href="<?= htmlspecialchars(sipandang_berkas_scan_view_url('masuk', (int) ($d['id_surat'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                                   class="btn-sm-ico btn-file" title="Lihat File Scan">
                                    <i class="fas fa-paperclip"></i>
                                </a>
                                <?php endif; ?>
                                <button class="btn-sm-ico btn-del" title="Hapus"
                                    onclick="if(confirm('Hapus surat agenda <?= $d['no_agenda']; ?>?')) window.location='surat_masuk.php?hapus=<?= $d['id_surat']; ?>'">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $total_filter = mysqli_num_rows($q); ?>
        <div style="padding:10px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:.72rem;color:var(--muted);">
                Menampilkan <strong><?= $total_filter; ?></strong> surat<?= ($cari||$fsifat)?' (filter aktif)':' terbaru'; ?>
            </div>
            <div style="font-size:.72rem;color:var(--muted);">
                Total <?= $tahun_ini; ?>: <strong style="color:var(--navy);"><?= $total; ?></strong> surat
            </div>
        </div>
    </div>

</div><!-- end main-grid -->
</div>
</div>
</section>
</div>

<script>
// ── TEXT UPPERCASE ──
$(document).on('blur', '.text-uppercase', function(){
    if($(this).val()) $(this).val($(this).val().toUpperCase());
});

// ── FILE UPLOAD ──
function showFileName(input){
    var fn = document.getElementById('fn-display');
    if(input.files.length > 0){
        fn.textContent = '📎 ' + input.files[0].name;
        fn.style.display = 'block';
    }
}
function handleDrop(e){
    e.preventDefault();
    var inp = document.getElementById('file_scan');
    var dt  = e.dataTransfer;
    if(dt.files.length){
        inp.files = dt.files;
        showFileName(inp);
    }
    document.getElementById('fileDrop').classList.remove('drag');
}

// ── FORM VALIDATION FEEDBACK ──
document.getElementById('formSuratMasuk').addEventListener('submit', function(e){
    var ok = true;
    this.querySelectorAll('[required]').forEach(function(f){
        if(!f.value.trim()){ f.style.borderColor='var(--danger)'; ok=false; }
        else f.style.borderColor='';
    });
    if(!ok){
        e.preventDefault();
        if (typeof window.sipandangShowToast === 'function') {
            window.sipandangShowToast('error', 'Lengkapi semua field yang wajib diisi!');
        }
    }
});
// Preview No. Agenda realtime
var inpAg = document.getElementById('inputNoAgenda');
var prevAg = document.getElementById('agendaPreviewNum');
if(inpAg && prevAg){
    inpAg.addEventListener('input', function(){
        prevAg.textContent = this.value.trim() || '---';
    });
}
</script>

<?php
sipandang_form_select2_script();
include 'footer.php';
?>