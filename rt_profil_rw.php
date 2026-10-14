<?php
/**
 * rt_profil_rw.php — Pengaturan Kop Surat per RW
 * Hanya bisa diakses oleh akun RW
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);

$s = rt_session();
if ($s['jabatan'] !== 'RW') {
    echo "<script>alert('Halaman ini hanya untuk akun RW.');window.location='rt_portal.php';</script>"; exit;
}

$rw_e = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$ok   = '';
$err  = '';

// Ambil profil saat ini
$profil = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tbl_profil_rw WHERE nomor_rw='$rw_e' LIMIT 1"
)) ?: [];

/* ══ PROSES SIMPAN ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rt_csrf_check();
    $f = fn($k) => mysqli_real_escape_string($koneksi, trim($_POST[$k] ?? ''));

    $nama_rw       = $f('nama_rw');
    $nama_sub      = $f('nama_sub');
    $alamat        = $f('alamat');
    $telepon       = $f('telepon');
    $nama_ketua_rw = $f('nama_ketua_rw');
    $kode_dok      = $f('kode_dok');

    if ($nama_rw === '') {
        $err = 'Nama RW wajib diisi.';
    } else {
        // UPSERT
        mysqli_query($koneksi, "INSERT INTO tbl_profil_rw
            (nomor_rw, nama_rw, nama_sub, alamat, telepon, nama_ketua_rw, kode_dok)
            VALUES ('$rw_e','$nama_rw','$nama_sub','$alamat','$telepon','$nama_ketua_rw','$kode_dok')
            ON DUPLICATE KEY UPDATE
                nama_rw='$nama_rw', nama_sub='$nama_sub', alamat='$alamat',
                telepon='$telepon', nama_ketua_rw='$nama_ketua_rw', kode_dok='$kode_dok'");
        $ok = 'Profil kop surat berhasil disimpan!';
        // reload profil
        $profil = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT * FROM tbl_profil_rw WHERE nomor_rw='$rw_e' LIMIT 1")) ?: [];
    }
}

$v = fn($k) => htmlspecialchars($profil[$k] ?? '');
$label = rt_label_wilayah($s);
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profil Kop Surat — <?= htmlspecialchars($label) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;}
.hdr{background:linear-gradient(135deg,var(--role-dark,#05285a),var(--role,#0b5cad));padding:14px 20px;position:sticky;top:0;z-index:100;}
.hdr-inner{max-width:680px;margin:0 auto;display:flex;align-items:center;gap:12px;}
.btn-back{color:rgba(255,255,255,.7);text-decoration:none;font-size:.85rem;display:flex;align-items:center;gap:6px;}
.hdr-title{color:#fff;font-weight:700;font-size:.95rem;flex:1;}
.wrap{max-width:680px;margin:0 auto;padding:20px 16px 80px;}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(10,22,40,.08);padding:20px;margin-bottom:14px;}
.sec-hd{font-size:.7rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#64708b;
    margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid #f0f2f5;display:flex;align-items:center;gap:7px;}
.sec-hd i{color:var(--role,#0b5cad);}
.field{margin-bottom:14px;}
.field:last-child{margin-bottom:0;}
.lbl{display:block;font-size:.7rem;font-weight:700;color:#64708b;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
.req{color:#dc3a52;}
.inp,.ta{width:100%;padding:10px 13px;border:1.5px solid #dde3ed;border-radius:8px;
    font-size:.9rem;font-family:'Plus Jakarta Sans',sans-serif;color:#172036;outline:none;transition:.2s;}
.inp:focus,.ta:focus{border-color:var(--role-dark,#05285a);box-shadow:0 0 0 3px rgba(10,22,40,.06);}
.ta{resize:vertical;min-height:80px;}
.hint{font-size:.68rem;color:#64708b;margin-top:4px;line-height:1.5;}
.ok-box{background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:10px;
    padding:12px 16px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:center;gap:10px;}
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;
    padding:12px 16px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:center;gap:10px;}

/* PREVIEW KOP */
.kop-preview{border:1.5px solid #dde3ed;border-radius:10px;padding:16px;background:#fafbfd;
    font-family:'Times New Roman',serif;margin-top:6px;}
.kop-top{display:flex;align-items:center;gap:12px;border-bottom:3px double var(--role-dark,#05285a);padding-bottom:10px;}
.kop-logo{width:60px;height:60px;border-radius:50%;border:2px solid var(--role,#0b5cad);
    background:linear-gradient(135deg,var(--role,#0b5cad),var(--role-ink,#08428a));display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:.65rem;font-weight:800;text-align:center;line-height:1.3;flex-shrink:0;}
.kop-text{flex:1;text-align:center;}
.kop-rw{font-size:1.1rem;font-weight:900;text-transform:uppercase;color:var(--role-dark,#05285a);letter-spacing:1px;}
.kop-sub{font-size:.8rem;font-weight:700;text-transform:uppercase;color:var(--role-dark,#05285a);}
.kop-alamat{font-size:.65rem;color:#444;margin-top:3px;}

/* STICKY FOOTER */
.sticky-foot{position:sticky;bottom:0;background:#fff;border-top:1.5px solid #dde3ed;
    padding:12px 20px;display:flex;gap:10px;justify-content:flex-end;z-index:50;}
.btn-save{background:var(--role,#0b5cad);color:#fff;border:none;padding:12px 28px;border-radius:9px;
    font-weight:700;font-size:.9rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;}
.btn-save:disabled{background:#64708b;color:#fff;cursor:not-allowed;}
.btn-batal{background:#f0f2f5;color:var(--role-dark,#05285a);border:none;padding:12px 20px;border-radius:9px;
    font-weight:700;font-size:.88rem;cursor:pointer;text-decoration:none;
    display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;}
.proses-bar{display:none;position:fixed;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,var(--role,#0b5cad),#f0d48a,var(--role,#0b5cad));background-size:200%;
    animation:shimmer 1.2s linear infinite;z-index:999;}
@keyframes shimmer{to{background-position:200% 0;}}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">
<div class="proses-bar" id="prosesBar"></div>
<div class="hdr">
<div class="hdr-inner">
    <a href="rt_portal.php" class="btn-back"><i class="fas fa-arrow-left"></i> Portal</a>
    <div class="hdr-title"><i class="fas fa-stamp" style="color:var(--role,#0b5cad);margin-right:6px;"></i>Kop Surat <?= htmlspecialchars($label) ?></div>
</div>
</div>

<form method="post">
<?= rt_csrf_field(); ?>
<div class="wrap">

<?php if ($ok): ?>
<div class="ok-box"><i class="fas fa-check-circle"></i><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="err-box"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- PREVIEW -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-eye"></i> Preview Kop Surat</div>
    <div class="kop-preview">
        <div class="kop-top">
            <div class="kop-logo" id="prevLogo">RW<br><?= str_pad($s['nomor_rw'],2,'0',STR_PAD_LEFT) ?></div>
            <div class="kop-text">
                <div class="kop-rw" id="prevNamaRw"><?= $v('nama_rw') ?: 'RUKUN WARGA '.str_pad($s['nomor_rw'],2,'0',STR_PAD_LEFT) ?></div>
                <div class="kop-sub" id="prevNamaSub"><?= $v('nama_sub') ?: '— nama cluster / wilayah —' ?></div>
                <div class="kop-alamat" id="prevAlamat"><?= $v('alamat') ?: 'Alamat sekretariat…' ?><?= $v('telepon') ? ' &nbsp;·&nbsp; Tel: ' . $v('telepon') : '' ?></div>
            </div>
        </div>
        <?php if ($v('kode_dok')): ?>
        <div style="text-align:right;font-size:.6rem;color:#888;margin-top:4px;">No. Dok: <?= $v('kode_dok') ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- FORM PROFIL -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-edit"></i> Data Kop Surat</div>

    <div class="field">
        <label class="lbl">Nama RW <span class="req">*</span></label>
        <input type="text" name="nama_rw" id="fNamaRw" class="inp"
               placeholder="Contoh: RUKUN WARGA 06"
               value="<?= $v('nama_rw') ?>" style="text-transform:uppercase;"
               oninput="document.getElementById('prevNamaRw').textContent=this.value||'RUKUN WARGA <?= str_pad($s['nomor_rw'],2,'0',STR_PAD_LEFT) ?>'">
        <div class="hint">Akan tampil di baris pertama kop surat.</div>
    </div>

    <div class="field">
        <label class="lbl">Nama Sub / Cluster</label>
        <input type="text" name="nama_sub" id="fNamaSub" class="inp"
               placeholder="Contoh: CLUSTER TGS CATALINA"
               value="<?= $v('nama_sub') ?>" style="text-transform:uppercase;"
               oninput="document.getElementById('prevNamaSub').textContent=this.value||'— nama cluster / wilayah —'">
        <div class="hint">Baris kedua kop surat (nama cluster, perumahan, dll).</div>
    </div>

    <div class="field">
        <label class="lbl">Alamat Sekretariat</label>
        <textarea name="alamat" id="fAlamat" class="ta" rows="2"
                  placeholder="Jl. Danau Bratan I, Blok AD …"
                  oninput="updatePreviewAlamat()"><?= $v('alamat') ?></textarea>
    </div>

    <div class="field">
        <label class="lbl">Nomor Telepon</label>
        <input type="text" name="telepon" id="fTelp" class="inp"
               placeholder="0813-xxxx-xxxx"
               value="<?= $v('telepon') ?>"
               oninput="updatePreviewAlamat()">
    </div>

    <div class="field">
        <label class="lbl">Nama Ketua RW</label>
        <input type="text" name="nama_ketua_rw" class="inp"
               placeholder="Nama lengkap Ketua RW"
               value="<?= $v('nama_ketua_rw') ?>" style="text-transform:uppercase;">
        <div class="hint">Akan muncul di bagian tanda tangan surat.</div>
    </div>

    <div class="field">
        <label class="lbl">No. Dokumen Template</label>
        <input type="text" name="kode_dok" class="inp"
               placeholder="Contoh: 001/RW06/Rev.00/2024"
               value="<?= $v('kode_dok') ?>">
        <div class="hint">Nomor dokumen format surat (pojok kanan atas). Boleh kosong.</div>
    </div>
</div>

<!-- INFO -->
<div class="card" style="background:#fffbeb;border:1px solid #fde68a;">
    <div style="display:flex;gap:10px;align-items:flex-start;font-size:.82rem;color:#7d5a00;line-height:1.6;">
        <i class="fas fa-lightbulb" style="margin-top:2px;color:#f59e0b;"></i>
        <div>
            <strong>Cara pakai:</strong> Isi profil kop surat ini sekali, lalu setiap surat pengantar yang dibuat oleh RT di wilayah RW <?= str_pad($s['nomor_rw'],2,'0',STR_PAD_LEFT) ?> akan otomatis menggunakan kop ini.
        </div>
    </div>
</div>

</div><!-- /wrap -->
<div class="sticky-foot">
    <a href="rt_portal.php" class="btn-batal">Batal</a>
    <button type="submit" class="btn-save" id="btnSave">
        <i class="fas fa-save"></i> Simpan Kop Surat
    </button>
</div>
</form>

<script>
function updatePreviewAlamat() {
    var alamat = document.getElementById('fAlamat').value.trim();
    var telp   = document.getElementById('fTelp').value.trim();
    var txt    = alamat || 'Alamat sekretariat…';
    if (telp) txt += '  ·  Tel: ' + telp;
    document.getElementById('prevAlamat').textContent = txt;
}
document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('btnSave').disabled = true;
    document.getElementById('prosesBar').style.display = 'block';
});
</script>
</body>
</html>
