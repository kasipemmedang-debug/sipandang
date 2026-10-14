<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
rt_guard();
$s     = rt_session();
$label = rt_label_wilayah($s);
$err   = '';

// Nilai RW dari session
$def_rw  = str_pad($s['nomor_rw'], 3, '0', STR_PAD_LEFT);
$is_rw   = ($s['jabatan'] === 'RW');

// Jika akun RW: ambil daftar RT dalam wilayah RW-nya untuk pilihan
$rt_dalam_rw = [];
if ($is_rw) {
    $rw_e = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
    $q_rt = mysqli_query($koneksi, "SELECT nomor_rt, nama, lokasi FROM pengurus_wilayah WHERE jabatan='RT' AND nomor_rw='$rw_e' ORDER BY LPAD(nomor_rt,5,'0') ASC");
    while ($r_rt = mysqli_fetch_assoc($q_rt)) $rt_dalam_rw[] = $r_rt;
}
$def_rt = $is_rw ? '' : str_pad($s['nomor_rt'], 3, '0', STR_PAD_LEFT);

// Referensi dropdown
$agama_opts  = sipandang_ref_agama();
$kawin_opts  = sipandang_ref_kawin();
$shdk_opts   = sipandang_ref_shdk();
$pkj_opts    = sipandang_ref_pekerjaan();
$pend_opts   = ['Tidak/Belum Sekolah','Tidak Tamat SD','Tamat SD/Sederajat','SLTP/Sederajat','SLTA/Sederajat','Diploma I/II','Akademi/Diploma III','Diploma IV/Strata I','Strata II','Strata III'];
$darah_opts  = ['','A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-','Tidak Tahu'];

/* ══ PROSES SIMPAN ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rt_csrf_check();
    $nik       = trim($_POST['nik'] ?? '');
    $no_kk     = trim($_POST['no_kk'] ?? '');
    $nama      = strtoupper(trim($_POST['nama_lengkap'] ?? ''));
    $ttl       = trim($_POST['tempat_lahir'] ?? '');
    $tgl       = trim($_POST['tgl_lahir'] ?? '');
    $jk        = $_POST['jenis_kelamin'] ?? '';
    $gol_darah = $_POST['gol_darah'] ?? '-';
    $agama     = $_POST['agama'] ?? '';
    $kawin     = $_POST['status_perkawinan'] ?? '';
    $shdk      = $_POST['shdk'] ?? '';
    $pendidikan= $_POST['pendidikan'] ?? '';
    $pekerjaan = mysqli_real_escape_string($koneksi, sipandang_pekerjaan_normalize((string) ($_POST['pekerjaan'] ?? '')));
    $no_akta   = trim($_POST['no_akta'] ?? '');
    $nama_ayah = strtoupper(trim($_POST['nama_ayah'] ?? ''));
    $nama_ibu  = strtoupper(trim($_POST['nama_ibu'] ?? ''));
    $kwn       = $_POST['kewarganegaraan'] ?? 'WNI';
    $alamat    = strtoupper(trim($_POST['alamat'] ?? ''));
    $kode_pos  = trim($_POST['kode_pos'] ?? '');

    // RW dari session, RT: dari session (jika akun RT) atau dari pilihan form (jika akun RW)
    $rw = str_pad($s['nomor_rw'], 3, '0', STR_PAD_LEFT);
    if ($is_rw) {
        $rt = str_pad(preg_replace('/\D/', '', $_POST['nomor_rt_pilih'] ?? ''), 3, '0', STR_PAD_LEFT);
    } else {
        $rt = str_pad($s['nomor_rt'], 3, '0', STR_PAD_LEFT);
    }

    if (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $err = 'NIK harus 16 digit angka.';
    } elseif ($is_rw && $rt === '000') {
        $err = 'Pilih nomor RT untuk warga ini.';
    } elseif ($nama === '') {
        $err = 'Nama lengkap wajib diisi.';
    } else {
        // Cek duplikat NIK
        $nik_e = mysqli_real_escape_string($koneksi, $nik);
        $cek = mysqli_query($koneksi, "SELECT nik FROM tbl_penduduk WHERE nik='$nik_e' LIMIT 1");
        if (mysqli_num_rows($cek) > 0) {
            $err = 'NIK ' . $nik . ' sudah terdaftar dalam sistem!';
        } else {
            $f = fn($v) => mysqli_real_escape_string($koneksi, $v);
            $q = "INSERT INTO tbl_penduduk
                (nik,no_kk,nama_lengkap,tempat_lahir,tgl_lahir,jenis_kelamin,gol_darah,
                 agama,status_perkawinan,shdk,pendidikan,pekerjaan,no_akta,
                 nama_ayah,nama_ibu,kewarganegaraan,alamat,rt,rw,kode_pos,status_dasar)
                VALUES('{$f($nik)}','{$f($no_kk)}','{$f($nama)}','{$f($ttl)}','{$f($tgl)}',
                '{$f($jk)}','{$f($gol_darah)}','{$f($agama)}','{$f($kawin)}','{$f($shdk)}',
                '{$f($pendidikan)}','$pekerjaan','{$f($no_akta)}',
                '{$f($nama_ayah)}','{$f($nama_ibu)}','{$f($kwn)}','{$f($alamat)}',
                '{$f($rt)}','{$f($rw)}','{$f($kode_pos)}','HIDUP')";
            if (mysqli_query($koneksi, $q)) {
                header('Location: rt_portal.php?toast=ok'); exit;
            } else {
                $err = 'Gagal menyimpan: ' . mysqli_error($koneksi);
            }
        }
    }
}
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tambah Warga — <?= htmlspecialchars($label) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;}
.hdr{background:linear-gradient(135deg,var(--role-dark,#05285a),var(--role,#0b5cad));padding:14px 20px;position:sticky;top:0;z-index:100;}
.hdr-inner{max-width:680px;margin:0 auto;display:flex;align-items:center;gap:12px;}
.hdr-back{color:rgba(255,255,255,.7);text-decoration:none;font-size:.85rem;display:flex;align-items:center;gap:6px;}
.hdr-back:hover{color:#fff;}
.hdr-title{color:#fff;font-weight:700;font-size:.95rem;flex:1;}
.wrap{max-width:680px;margin:0 auto;padding:20px 16px 60px;}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(10,22,40,.08);padding:20px;margin-bottom:14px;}
.sec-hd{font-size:.7rem;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#64708b;margin-bottom:14px;padding-bottom:8px;border-bottom:1.5px solid #f0f2f5;display:flex;align-items:center;gap:7px;}
.sec-hd i{color:var(--role,#0b5cad);}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.field{margin-bottom:14px;}
.field:last-child{margin-bottom:0;}
.lbl{display:block;font-size:.7rem;font-weight:700;color:#64708b;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
.req{color:#dc3a52;}
.inp,.sel,.ta{width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.88rem;font-family:'Plus Jakarta Sans',sans-serif;color:#172036;outline:none;transition:.2s;}
.inp:focus,.sel:focus,.ta:focus{border-color:var(--role-dark,#05285a);box-shadow:0 0 0 3px rgba(10,22,40,.06);}
.inp[readonly]{background:#f8f9fc;color:#617396;cursor:not-allowed;}
.ta{resize:vertical;min-height:70px;}
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;padding:10px 14px;font-size:.82rem;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sticky-foot{position:sticky;bottom:0;background:#fff;border-top:1.5px solid #dde3ed;padding:12px 20px;display:flex;gap:10px;justify-content:flex-end;z-index:50;}
.btn-save{background:var(--role,#0b5cad);color:#fff;border:none;padding:11px 24px;border-radius:9px;font-weight:700;font-size:.88rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:.2s;}
.btn-save:hover{background:var(--role-ink,#08428a);color:#fff;}
.btn-save:disabled{background:#64708b;color:#fff;cursor:not-allowed;}
.proses-bar{display:none;position:fixed;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--role,#0b5cad),#f0d48a,var(--role,#0b5cad));background-size:200%;animation:shimmer 1.2s linear infinite;z-index:999;}
@keyframes shimmer{to{background-position:200% 0;}}
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:12px 16px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.5;}
.err-box i{flex-shrink:0;margin-top:2px;}
.btn-batal{background:#f0f2f5;color:var(--role-dark,#05285a);border:none;padding:11px 20px;border-radius:9px;font-weight:700;font-size:.88rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;}
.badge-lock{background:#e8edfa;color:var(--role);border-radius:6px;padding:9px 12px;font-size:.82rem;font-weight:700;display:block;}
@media(max-width:480px){.row2,.row3{grid-template-columns:1fr;}}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">
<div class="hdr">
<div class="hdr-inner">
    <a href="rt_portal.php" class="hdr-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="hdr-title">Tambah Warga — <?= htmlspecialchars($label) ?></div>
</div>
</div>

<form method="post">
<?= rt_csrf_field(); ?>
<div class="wrap">

<?php if ($err): ?>
<div class="err-box"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- WILAYAH -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-map-marker-alt"></i> Wilayah</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Nomor RT <?= $is_rw ? '<span class="req">*</span>' : '' ?></label>
            <?php if ($is_rw): ?>
                <?php if (!empty($rt_dalam_rw)): ?>
                <select name="nomor_rt_pilih" class="sel" required>
                    <option value="">— Pilih RT —</option>
                    <?php foreach ($rt_dalam_rw as $rt_item):
                        $rt_pad = str_pad($rt_item['nomor_rt'], 3, '0', STR_PAD_LEFT);
                        $sel    = ($_POST['nomor_rt_pilih'] ?? '') === $rt_item['nomor_rt'] ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($rt_item['nomor_rt']) ?>" <?= $sel ?>>
                        RT.<?= $rt_pad ?><?= $rt_item['lokasi'] ? ' — ' . $rt_item['lokasi'] : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="number" name="nomor_rt_pilih" class="inp" placeholder="Nomor RT (misal: 1)" min="1" max="999"
                       value="<?= htmlspecialchars($_POST['nomor_rt_pilih'] ?? '') ?>" required>
                <div style="font-size:.68rem;color:#64708b;margin-top:4px;"><i class="fas fa-info-circle"></i> Belum ada data RT terdaftar di RW ini. Masukkan nomor RT secara manual.</div>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge-lock"><i class="fas fa-lock" style="opacity:.4;margin-right:5px;"></i><?= $def_rt ?></span>
                <input type="hidden" name="rt" value="<?= $def_rt ?>">
            <?php endif; ?>
        </div>
        <div class="field">
            <label class="lbl">Nomor RW</label>
            <span class="badge-lock"><i class="fas fa-lock" style="opacity:.4;margin-right:5px;"></i>RW.<?= $def_rw ?></span>
            <input type="hidden" name="rw" value="<?= $def_rw ?>">
        </div>
    </div>
</div>

<!-- DATA KELUARGA -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-home"></i> Data Keluarga</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">No. KK <span class="req">*</span></label>
            <input type="text" name="no_kk" class="inp" placeholder="16 digit" maxlength="16"
                   value="<?= htmlspecialchars($_POST['no_kk']??'') ?>" pattern="\d{16}" title="16 digit angka">
        </div>
        <div class="field">
            <label class="lbl">Status dalam Keluarga <span class="req">*</span></label>
            <select name="shdk" class="sel" required>
                <option value="">— Pilih —</option>
                <?php foreach ($shdk_opts as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['shdk']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- DATA PRIBADI -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-id-card"></i> Data Pribadi</div>
    <div class="field">
        <label class="lbl">NIK <span class="req">*</span></label>
        <input type="text" name="nik" class="inp" placeholder="16 digit NIK" maxlength="16" required
               pattern="\d{16}" title="16 digit angka" value="<?= htmlspecialchars($_POST['nik']??'') ?>">
    </div>
    <div class="field">
        <label class="lbl">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama_lengkap" class="inp" placeholder="Nama sesuai KTP" required
               value="<?= htmlspecialchars($_POST['nama_lengkap']??'') ?>" style="text-transform:uppercase;">
    </div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Tempat Lahir <span class="req">*</span></label>
            <input type="text" name="tempat_lahir" class="inp" placeholder="Kota lahir" required
                   value="<?= htmlspecialchars($_POST['tempat_lahir']??'') ?>">
        </div>
        <div class="field">
            <label class="lbl">Tanggal Lahir <span class="req">*</span></label>
            <input type="date" name="tgl_lahir" class="inp" required value="<?= htmlspecialchars($_POST['tgl_lahir']??'') ?>">
        </div>
    </div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Jenis Kelamin <span class="req">*</span></label>
            <select name="jenis_kelamin" class="sel" required>
                <option value="">— Pilih —</option>
                <option value="Laki-laki" <?= ($_POST['jenis_kelamin']??'')==='Laki-laki'?'selected':'' ?>>Laki-laki</option>
                <option value="Perempuan" <?= ($_POST['jenis_kelamin']??'')==='Perempuan'?'selected':'' ?>>Perempuan</option>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Golongan Darah</label>
            <select name="gol_darah" class="sel">
                <?php foreach ($darah_opts as $d): ?>
                <option value="<?= $d ?>" <?= ($_POST['gol_darah']??'')===$d?'selected':'' ?>><?= $d ?: '— Tidak Tahu —' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row3">
        <div class="field">
            <label class="lbl">Agama <span class="req">*</span></label>
            <select name="agama" class="sel" required>
                <option value="">— Pilih —</option>
                <?php foreach ($agama_opts as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['agama']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Status Perkawinan <span class="req">*</span></label>
            <select name="status_perkawinan" class="sel" required>
                <option value="">— Pilih —</option>
                <?php foreach ($kawin_opts as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['status_perkawinan']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Kewarganegaraan</label>
            <select name="kewarganegaraan" class="sel">
                <option value="WNI" <?= ($_POST['kewarganegaraan']??'WNI')==='WNI'?'selected':'' ?>>WNI</option>
                <option value="WNA" <?= ($_POST['kewarganegaraan']??'')==='WNA'?'selected':'' ?>>WNA</option>
            </select>
        </div>
    </div>
</div>

<!-- PENDIDIKAN & PEKERJAAN -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-briefcase"></i> Pendidikan & Pekerjaan</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Pendidikan Terakhir</label>
            <select name="pendidikan" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($pend_opts as $p): ?>
                <option value="<?= $p ?>" <?= ($_POST['pendidikan']??'')===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Pekerjaan</label>
            <select name="pekerjaan" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($pkj_opts as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($_POST['pekerjaan']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- NAMA ORANG TUA & AKTA -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-users"></i> Orang Tua & Akta</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Nama Ayah</label>
            <input type="text" name="nama_ayah" class="inp" placeholder="Nama ayah kandung"
                   value="<?= htmlspecialchars($_POST['nama_ayah']??'') ?>" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="lbl">Nama Ibu</label>
            <input type="text" name="nama_ibu" class="inp" placeholder="Nama ibu kandung"
                   value="<?= htmlspecialchars($_POST['nama_ibu']??'') ?>" style="text-transform:uppercase;">
        </div>
    </div>
    <div class="field">
        <label class="lbl">No. Akta Lahir</label>
        <input type="text" name="no_akta" class="inp" placeholder="Nomor akta kelahiran"
               value="<?= htmlspecialchars($_POST['no_akta']??'') ?>">
    </div>
</div>

<!-- ALAMAT -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-map-pin"></i> Alamat</div>
    <div class="field">
        <label class="lbl">Alamat Lengkap <span class="req">*</span></label>
        <textarea name="alamat" class="ta" placeholder="Nama jalan, nomor rumah, cluster…" required
                  style="text-transform:uppercase;"><?= htmlspecialchars($_POST['alamat']??'') ?></textarea>
    </div>
    <div class="field">
        <label class="lbl">Kode Pos</label>
        <input type="text" name="kode_pos" class="inp" placeholder="12345" maxlength="10"
               value="<?= htmlspecialchars($_POST['kode_pos']??'') ?>">
    </div>
</div>

</div><!-- /wrap -->

<div class="proses-bar" id="prosesBar"></div>
<div class="sticky-foot">
    <a href="rt_portal.php" class="btn-batal">Batal</a>
    <button type="submit" class="btn-save" id="btnSimpan"><i class="fas fa-save"></i> Simpan Warga</button>
</div>
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    var btn = document.getElementById('btnSimpan');
    // validasi cepat NIK
    var nik = document.querySelector('[name="nik"]').value.trim();
    if (nik.length !== 16 || isNaN(nik)) {
        e.preventDefault();
        showAlert('NIK harus tepat 16 digit angka. Periksa kembali.');
        return;
    }
    var nama = document.querySelector('[name="nama_lengkap"]').value.trim();
    if (!nama) { e.preventDefault(); showAlert('Nama lengkap wajib diisi.'); return; }

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';
    btn.disabled = true;
    document.getElementById('prosesBar').style.display = 'block';
});

function showAlert(msg) {
    var ex = document.getElementById('alertDyn');
    if (ex) ex.remove();
    var d = document.createElement('div');
    d.id = 'alertDyn';
    d.className = 'err-box';
    d.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + msg + '</span>';
    document.querySelector('.wrap').prepend(d);
    d.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>
</form>
</body>
</html>
