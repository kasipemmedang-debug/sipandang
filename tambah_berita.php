<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include 'koneksi.php';

// ── MODE: EDIT atau TAMBAH ──────────────────────────
$mode = isset($_GET['id']) ? 'edit' : 'tambah';
$id   = (int)($_GET['id'] ?? 0);
$b    = ['judul_berita'=>'','isi_berita'=>'','gambar'=>'','tgl_posting'=>date('Y-m-d'),'kategori'=>'Pengumuman','status'=>'aktif','is_pin'=>0];

if ($mode === 'edit') {
    $q = mysqli_query($koneksi,"SELECT * FROM tbl_berita WHERE id_berita=$id");
    $row = mysqli_fetch_assoc($q);
    if (!$row) { echo "<script>alert('Data tidak ditemukan');location.href='data_berita.php';</script>"; exit; }
    $b = array_merge($b, $row); // merge agar kolom baru tidak error
}

$err = '';

// ── PROSES SIMPAN ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $judul   = trim($_POST['judul']   ?? '');
    $isi     = trim($_POST['isi']     ?? '');
    $tgl     = $_POST['tgl_posting']  ?? date('Y-m-d');
    $kat     = $_POST['kategori']     ?? 'Pengumuman';
    $status  = $_POST['status']       ?? 'aktif';
    $is_pin  = isset($_POST['is_pin']) ? 1 : 0;

    if (empty($judul)) { $err = 'Judul wajib diisi.'; }
    elseif (empty($isi)) { $err = 'Isi pengumuman wajib diisi.'; }

    // Upload gambar
    $gambar_simpan = $b['gambar'] ?: 'default.jpg';
    if (!$err && !empty($_FILES['gambar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png'])) {
            $err = 'Format gambar harus JPG atau PNG.';
        } elseif ($_FILES['gambar']['size'] > 3*1024*1024) {
            $err = 'Ukuran gambar maksimal 3MB.';
        } else {
            $dir = 'berkas_berita/';
            if (!is_dir($dir)) mkdir($dir,0755,true);
            $nama_baru = 'berita_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'],$dir.$nama_baru)) {
                // Hapus gambar lama
                if ($mode==='edit' && $b['gambar']!=='default.jpg' && file_exists($dir.$b['gambar'])) {
                    unlink($dir.$b['gambar']);
                }
                $gambar_simpan = $nama_baru;
            } else {
                $err = 'Gagal menyimpan gambar.';
            }
        }
    }

    if (!$err) {
        $j  = mysqli_real_escape_string($koneksi,$judul);
        $i  = mysqli_real_escape_string($koneksi,$isi);
        $t  = mysqli_real_escape_string($koneksi,$tgl);
        $k  = mysqli_real_escape_string($koneksi,$kat);
        $s  = mysqli_real_escape_string($koneksi,$status);
        $g  = mysqli_real_escape_string($koneksi,$gambar_simpan);

        if ($mode==='tambah') {
            mysqli_query($koneksi,"INSERT INTO tbl_berita (judul_berita,isi_berita,gambar,tgl_posting,kategori,status,is_pin) VALUES ('$j','$i','$g','$t','$k','$s',$is_pin)");
        } else {
            mysqli_query($koneksi,"UPDATE tbl_berita SET judul_berita='$j',isi_berita='$i',gambar='$g',tgl_posting='$t',kategori='$k',status='$s',is_pin=$is_pin WHERE id_berita=$id");
        }
        $label = $mode==='tambah'?'tambah':'simpan';
        echo "<script>location.href='data_berita.php?ok=$label';</script>"; exit;
    }
}

$title = $mode==='edit' ? 'Edit Pengumuman' : 'Tambah Pengumuman';
$img_path = (!empty($b['gambar'])&&$b['gambar']!=='default.jpg'&&file_exists("berkas_berita/".$b['gambar'])) ? "berkas_berita/".$b['gambar'] : null;

include 'header.php';
include 'sidebar.php';
?>
<style>
body{font-family:'Plus Jakarta Sans',sans-serif !important;background:#f4f6fb !important;}
.content-wrapper{background:#f4f6fb !important;padding:0 !important;}
.fb-hdr{background:linear-gradient(135deg,var(--role-dark,#05285a),var(--role,#0b5cad));padding:24px 32px 70px;color:#fff;position:relative;overflow:hidden;}
.fb-hdr::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 60L60 0' stroke='rgba(var(--role-rgb,11,92,173),.05)' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");}
.fb-hdr h1{font-family: 'Plus Jakarta Sans', sans-serif;color:#fff;font-size:1.4rem;margin:6px 0 3px;position:relative;}
.fb-hdr p{color:rgba(255,255,255,.5);font-size:.8rem;position:relative;}
.fb-hdr .back{color:rgba(255,255,255,.55);font-size:.82rem;text-decoration:none;position:relative;}
.fb-hdr .back:hover{color:var(--role,#0b5cad);}
.hdr-badge{display:inline-flex;align-items:center;gap:6px;background:var(--role-soft,#e8f2fd);border:1px solid #e0e7f6;color:#56658d;padding:4px 13px;border-radius:20px;font-size:.7rem;font-weight:700;margin-bottom:8px;position:relative;}
.fb-wrap{margin-top:-48px;padding:0 24px 48px;position:relative;z-index:10;}
.fb-card{background:#fff;border-radius:14px;box-shadow:0 4px 20px rgba(10,22,40,.1);padding:24px;margin-bottom:16px;}
.fb-card h3{font-family: 'Plus Jakarta Sans', sans-serif;color:var(--role-dark,#05285a);font-size:.98rem;margin:0 0 18px;padding-bottom:12px;border-bottom:2px solid #dde3ed;display:flex;align-items:center;gap:8px;}
.fb-card h3 i{color:var(--role,#0b5cad);}
.f-lbl{font-size:.72rem;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#64708b;margin-bottom:6px;display:block;}
.f-ctrl{width:100%;border:1.5px solid #dde3ed;border-radius:9px;padding:11px 14px;font-size:.9rem;font-family:inherit;outline:none;color:#172036;background:#fff;transition:border-color .2s;}
.f-ctrl:focus{border-color:var(--role-dark,#05285a);box-shadow:0 0 0 3px rgba(10,22,40,.05);}
textarea.f-ctrl{resize:vertical;min-height:130px;}
.f-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.err-box{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;border-radius:9px;padding:11px 14px;font-size:.82rem;margin-bottom:16px;display:flex;align-items:center;gap:8px;}

/* Preview gambar */
.img-preview-wrap{border:1.5px dashed #dde3ed;border-radius:10px;background:#fafbff;overflow:hidden;text-align:center;padding:14px;cursor:pointer;transition:border-color .2s;position:relative;}
.img-preview-wrap:hover{border-color:var(--role-dark,#05285a);}
.img-preview-wrap.has-img{padding:0;border-style:solid;border-color:var(--role,#0b5cad);}
.img-preview{width:100%;max-height:200px;object-fit:cover;border-radius:8px;display:block;}
.img-placeholder{color:#c0c8d8;padding:20px 0;}
.img-placeholder i{font-size:2rem;display:block;margin-bottom:6px;}
.img-placeholder span{font-size:.78rem;}
.img-overlay{position:absolute;inset:0;background:rgba(10,22,40,.5);display:none;align-items:center;justify-content:center;border-radius:8px;color:#fff;font-size:.85rem;font-weight:600;gap:6px;}
.img-preview-wrap.has-img:hover .img-overlay{display:flex;}

/* Toggle pin */
.toggle-wrap{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#fffdf5;border:1px solid #f0d080;border-radius:10px;cursor:pointer;}
.toggle-wrap input[type=checkbox]{width:18px;height:18px;accent-color:var(--role,#0b5cad);cursor:pointer;}
.toggle-wrap .tl{font-size:.85rem;font-weight:600;color:#7a5800;}
.toggle-wrap .ts{font-size:.72rem;color:#64708b;margin-top:1px;}

/* Char counter */
.char-counter{font-size:.7rem;color:#64708b;text-align:right;margin-top:3px;}
.char-counter.warn{color:#dc3a52;}

/* Buttons */
.btn-p{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:9px;font-size:.88rem;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:all .15s;}
.bp-save{background:var(--role,#0b5cad);color:#fff;}.bp-save:hover{background:var(--role-ink,#08428a);color:#fff;}
.bp-ghost{background:transparent;border:1.5px solid #dde3ed;color:#64708b;}.bp-ghost:hover{color:var(--role-dark,#05285a);border-color:var(--role-dark,#05285a);}
</style>
<div class="content-wrapper">
<div class="fb-hdr">
    <div class="container-fluid">
        <a href="data_berita.php" class="back"><i class="fas fa-arrow-left mr-1"></i>Kembali ke Daftar</a>
        <span class="hdr-badge" style="margin-top:8px;"><i class="fas fa-bullhorn"></i> INFO &amp; PENGUMUMAN</span>
        <h1><?=$title?></h1>
        <p><?=$mode==='edit'?'Ubah konten yang sudah ada':'Buat pengumuman baru untuk portal warga'?></p>
    </div>
</div>
<section class="content"><div class="container-fluid"><div class="fb-wrap">

<?php if($err): ?><div class="err-box"><i class="fas fa-exclamation-circle"></i><?=$err?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="row">

    <!-- KOLOM KIRI (form utama) -->
    <div class="col-md-8">
        <div class="fb-card">
            <h3><i class="fas fa-pen"></i>Konten Pengumuman</h3>

            <label class="f-lbl">Judul Pengumuman *</label>
            <input type="text" name="judul" class="f-ctrl" maxlength="150"
                   value="<?=htmlspecialchars($b['judul_berita'])?>"
                   placeholder="Contoh: Jadwal Posyandu Balita RW 05" required
                   oninput="updateCounter('judul',this.value,150)">
            <div class="char-counter" id="ctr-judul"><?=mb_strlen($b['judul_berita'])?>/150</div>

            <div style="margin-top:14px;">
                <label class="f-lbl">Isi / Detail Pengumuman *</label>
                <textarea name="isi" class="f-ctrl" maxlength="3000" required
                          placeholder="Tulis detail informasi di sini..."
                          oninput="updateCounter('isi',this.value,3000)"><?=htmlspecialchars($b['isi_berita'])?></textarea>
                <div class="char-counter" id="ctr-isi"><?=mb_strlen($b['isi_berita'])?>/3000</div>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN (meta + gambar) -->
    <div class="col-md-4">
        <!-- Gambar -->
        <div class="fb-card">
            <h3><i class="fas fa-image"></i>Gambar / Poster</h3>
            <div class="img-preview-wrap <?=$img_path?'has-img':''?>" id="previewWrap" onclick="document.getElementById('gambarInput').click()">
                <?php if($img_path): ?>
                <img src="<?=$img_path?>" class="img-preview" id="previewImg" alt="Preview">
                <div class="img-overlay"><i class="fas fa-edit"></i>Ganti Gambar</div>
                <?php else: ?>
                <div class="img-placeholder" id="imgPlaceholder">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Klik untuk pilih gambar<br><span style="font-size:.66rem;">JPG/PNG · Maks 3MB</span></span>
                </div>
                <img src="" class="img-preview" id="previewImg" alt="Preview" style="display:none;">
                <?php endif; ?>
            </div>
            <input type="file" id="gambarInput" name="gambar" accept="image/jpeg,image/png" style="display:none;" onchange="previewGambar(this)">
            <p style="font-size:.7rem;color:#64708b;margin-top:8px;text-align:center;">
                <?=$mode==='edit'&&$img_path?'Kosongkan jika tidak ingin mengganti.':'Opsional. Format landscape lebih baik.'?>
            </p>
        </div>

        <!-- Meta -->
        <div class="fb-card">
            <h3><i class="fas fa-sliders-h"></i>Pengaturan</h3>

            <div class="f-row" style="margin-bottom:14px;">
                <div>
                    <label class="f-lbl">Kategori</label>
                    <select name="kategori" class="f-ctrl">
                        <?php foreach(['Pengumuman','Berita','Agenda','Info Layanan'] as $k): ?>
                        <option value="<?=$k?>" <?=$b['kategori']===$k?'selected':''?>><?=$k?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="f-lbl">Status</label>
                    <select name="status" class="f-ctrl">
                        <option value="aktif" <?=$b['status']==='aktif'?'selected':''?>>● Aktif</option>
                        <option value="draft" <?=$b['status']==='draft'?'selected':''?>>○ Draft</option>
                    </select>
                </div>
            </div>

            <label class="f-lbl">Tanggal Posting</label>
            <input type="date" name="tgl_posting" class="f-ctrl" value="<?=$b['tgl_posting']?>" style="margin-bottom:14px;">

            <!-- Pin -->
            <label class="toggle-wrap" for="chkPin">
                <input type="checkbox" id="chkPin" name="is_pin" <?=$b['is_pin']?'checked':''?>>
                <div>
                    <div class="tl"><i class="fas fa-thumbtack mr-1" style="color:var(--role,#0b5cad);font-size:.8rem;"></i>Sematkan di atas</div>
                    <div class="ts">Muncul pertama di portal warga</div>
                </div>
            </label>
        </div>

        <!-- Aksi -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" class="btn-p bp-save" style="flex:1;justify-content:center;">
                <i class="fas fa-save"></i> <?=$mode==='edit'?'Simpan Perubahan':'Posting Sekarang'?>
            </button>
            <a href="data_berita.php" class="btn-p bp-ghost"><i class="fas fa-times"></i></a>
        </div>
    </div>
</div>
</form>

</div></div></section></div>

<script>
function previewGambar(inp) {
    if (!inp.files[0]) return;
    var file = inp.files[0];
    if (file.size > 3*1024*1024) { alert('Ukuran gambar maks 3MB!'); inp.value=''; return; }
    var reader = new FileReader();
    reader.onload = function(e) {
        var img   = document.getElementById('previewImg');
        var wrap  = document.getElementById('previewWrap');
        var ph    = document.getElementById('imgPlaceholder');
        img.src   = e.target.result;
        img.style.display = 'block';
        wrap.classList.add('has-img');
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function updateCounter(id, val, max) {
    var el  = document.getElementById('ctr-' + id);
    var len = val.length;
    el.textContent = len + '/' + max;
    el.classList.toggle('warn', len > max * 0.9);
}
</script>
<?php include 'footer.php'; ?>