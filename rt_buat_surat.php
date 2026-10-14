<?php
/**
 * rt_buat_surat.php — Buat Surat Pengantar (oleh RT)
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);

$s     = rt_session();
$label = rt_label_wilayah($s);
$jab   = $s['jabatan'];
$rw_s  = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt_s  = mysqli_real_escape_string($koneksi, $s['nomor_rt']);
$err   = '';

// Daftar keperluan umum
$keperluan_list = [
    'Akte Kelahiran','KTP / e-KTP','Kartu Keluarga (KK)','SKCK',
    'Surat Keterangan Domisili','Surat Keterangan Tidak Mampu (SKTM)',
    'Surat Keterangan Nikah','Surat Keterangan Usaha',
    'Surat Keterangan Kematian','Surat Keterangan Lahir',
    'Pengurusan BPJS','Pengurusan Beasiswa',
];

// Daftar RT dalam RW (untuk RW)
$rt_dalam_rw = [];
if ($jab === 'RW') {
    $q_rt = mysqli_query($koneksi, "SELECT nomor_rt, nama, lokasi FROM pengurus_wilayah WHERE jabatan='RT' AND nomor_rw='$rw_s' ORDER BY LPAD(nomor_rt,5,'0') ASC");
    while ($r2 = mysqli_fetch_assoc($q_rt)) $rt_dalam_rw[] = $r2;
}

/* ══ PROSES SIMPAN ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rt_csrf_check();
    $f = fn($k) => mysqli_real_escape_string($koneksi, trim($_POST[$k] ?? ''));

    // Tentukan RT
    if ($jab === 'RW') {
        $rt_input = str_pad(preg_replace('/\D/', '', $_POST['nomor_rt_surat'] ?? ''), 3, '0', STR_PAD_LEFT);
    } else {
        $rt_input = str_pad($s['nomor_rt'], 3, '0', STR_PAD_LEFT);
    }

    $nama_pemohon   = strtoupper(trim($_POST['nama_pemohon'] ?? ''));
    $nik            = preg_replace('/\D/', '', $_POST['nik'] ?? '');
    $jk             = $_POST['jenis_kelamin'] ?? '';
    $tempat_lahir   = strtoupper(trim($_POST['tempat_lahir'] ?? ''));
    $tgl_lahir      = trim($_POST['tgl_lahir'] ?? '');
    $agama          = trim($_POST['agama'] ?? '');
    $no_ktp         = preg_replace('/\D/', '', $_POST['no_ktp'] ?? '');
    $pekerjaan      = strtoupper(trim($_POST['pekerjaan'] ?? ''));
    $alamat_skrg    = strtoupper(trim($_POST['alamat_sekarang'] ?? ''));
    $tgl_surat      = trim($_POST['tanggal_surat'] ?? date('Y-m-d'));
    $nama_ketua_rt  = strtoupper(trim($_POST['nama_ketua_rt'] ?? $s['nama_ketua']));

    // Keperluan (checkbox + tambahan)
    $keperluan_arr = array_filter(array_map('trim', (array)($_POST['keperluan'] ?? [])));
    $tambahan      = trim($_POST['keperluan_lain'] ?? '');
    if ($tambahan !== '') $keperluan_arr[] = $tambahan;
    $keperluan_str = implode("\n", $keperluan_arr);

    $warga_nik = null;
    if ($nik !== '') {
        if (strlen($nik) !== 16) {
            $err = 'NIK harus 16 digit jika diisi.';
        } else {
            $warga_nik = rt_warga_milik($koneksi, $nik, $s);
            if (!$warga_nik || str_pad((string) ($warga_nik['rt'] ?? ''), 3, '0', STR_PAD_LEFT) !== $rt_input) {
                $err = 'NIK tidak ditemukan di wilayah RT/RW ini.';
            }
        }
    }

    if ($err !== '') {
        // tampilkan error
    } elseif ($nama_pemohon === '') {
        $err = 'Nama pemohon wajib diisi.';
    } elseif (empty($keperluan_arr)) {
        $err = 'Pilih minimal 1 keperluan surat.';
    } elseif ($jab === 'RW' && $rt_input === '000') {
        $err = 'Pilih nomor RT untuk surat ini.';
    } else {
        $ts_surat = strtotime($tgl_surat) ?: time();
        $tahun = (int) date('Y', $ts_surat);
        $bulan = (int) date('n', $ts_surat);
        $no    = rt_next_no_surat($koneksi, $s['nomor_rw'], $rt_input, 'RT', $tahun, $bulan);

        $rt_e   = mysqli_real_escape_string($koneksi, $rt_input);
        $tgl_e  = $tgl_lahir ?: 'NULL';
        $tgl_s_e = mysqli_real_escape_string($koneksi, $tgl_surat);
        $no_str  = mysqli_real_escape_string($koneksi, $no['nomor']);
        $oleh    = mysqli_real_escape_string($koneksi, $s['username']);
        $kep_e   = mysqli_real_escape_string($koneksi, $keperluan_str);
        $kt_e    = mysqli_real_escape_string($koneksi, $nama_ketua_rt);

        $sql = "INSERT INTO tbl_surat_pengantar
            (no_urut_rt, no_surat_rt, nik, nama_pemohon, jenis_kelamin,
             tempat_lahir, tgl_lahir, agama, no_ktp, pekerjaan, alamat_sekarang,
             keperluan, rt, rw, nama_ketua_rt, tanggal_surat, dibuat_oleh, status)
            VALUES
            ({$no['urut']}, '$no_str', '{$f('nik')}', '{$f('nama_pemohon')}', '{$f('jenis_kelamin')}',
             '{$f('tempat_lahir')}', " . ($tgl_lahir ? "'$tgl_e'" : "NULL") . ", '{$f('agama')}',
             '{$f('no_ktp')}', '{$f('pekerjaan')}', '{$f('alamat_sekarang')}',
             '$kep_e', '$rt_e', '$rw_s', '$kt_e', '$tgl_s_e', '$oleh', 'menunggu')";

        if (mysqli_query($koneksi, $sql)) {
            header('Location: rt_daftar_surat.php?toast=buat');
            exit;
        } else {
            $err = 'Gagal menyimpan: ' . mysqli_error($koneksi);
        }
    }
}

// Auto-fill NIK via AJAX
if (isset($_GET['api_nik'])) {
    header('Content-Type: application/json');
    $nik_q = preg_replace('/\D/', '', $_GET['api_nik'] ?? '');
    if (strlen($nik_q) < 10) { echo json_encode(['ok'=>false]); exit; }
    $nik_e = mysqli_real_escape_string($koneksi, $nik_q);
    $where = "nik='$nik_e' AND rw='$rw_s'" . ($jab==='RT' ? " AND rt='$rt_s'" : '');
    if ($jab === 'RW' && isset($_GET['api_rt'])) {
        $api_rt = mysqli_real_escape_string($koneksi, str_pad(preg_replace('/\D/', '', $_GET['api_rt'] ?? ''), 3, '0', STR_PAD_LEFT));
        if ($api_rt !== '000') {
            $where .= " AND rt='$api_rt'";
        }
    }
    $row   = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE $where LIMIT 1"));
    if ($row) {
        echo json_encode(['ok'=>true,
            'nama'     => $row['nama_lengkap'],
            'jk'       => strtoupper(substr(trim($row['jenis_kelamin']),0,1)) === 'L' ? 'Laki-laki' : 'Perempuan',
            'tempat'   => $row['tempat_lahir'],
            'tgl'      => $row['tgl_lahir'] !== '0000-00-00' ? $row['tgl_lahir'] : '',
            'agama'    => $row['agama'],
            'pekerjaan'=> $row['pekerjaan'],
            'alamat'   => $row['alamat'],
            'rt'       => $row['rt'],
        ]);
    } else {
        echo json_encode(['ok'=>false]);
    }
    exit;
}

$v_post = fn($k) => htmlspecialchars($_POST[$k] ?? '');
$agama_opts = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'];
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buat Surat Pengantar</title>
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
    margin-bottom:14px;padding-bottom:8px;border-bottom:1.5px solid #f0f2f5;display:flex;align-items:center;gap:7px;}
.sec-hd i{color:var(--role,#0b5cad);}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.field{margin-bottom:14px;}
.field:last-child{margin-bottom:0;}
.lbl{display:block;font-size:.7rem;font-weight:700;color:#64708b;margin-bottom:5px;
    text-transform:uppercase;letter-spacing:.4px;}
.req{color:#dc3a52;}
.inp,.sel,.ta{width:100%;padding:10px 13px;border:1.5px solid #dde3ed;border-radius:8px;
    font-size:.88rem;font-family:'Plus Jakarta Sans',sans-serif;color:#172036;outline:none;transition:.2s;}
.inp:focus,.sel:focus,.ta:focus{border-color:var(--role-dark,#05285a);box-shadow:0 0 0 3px rgba(10,22,40,.06);}
.ta{resize:vertical;min-height:65px;}
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;
    padding:12px 16px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.5;}
.err-box i{flex-shrink:0;margin-top:2px;}

/* NIK lookup */
.nik-wrap{position:relative;}
.nik-spin{position:absolute;right:12px;top:50%;transform:translateY(-50%);
    color:#64708b;display:none;font-size:.85rem;}
.nik-ok{position:absolute;right:12px;top:50%;transform:translateY(-50%);
    color:#166534;display:none;font-size:.85rem;}
.autofill-notice{background:#f0fdf4;border:1px solid #86efac;color:#166534;
    border-radius:8px;padding:8px 12px;font-size:.77rem;display:none;margin-top:6px;}

/* KEPERLUAN */
.kep-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
.kep-chk{display:none;}
.kep-chk + label{display:flex;align-items:center;gap:8px;padding:9px 12px;
    border-radius:9px;border:1.5px solid #dde3ed;cursor:pointer;font-size:.8rem;
    color:#5a6a80;transition:.15s;font-weight:600;}
.kep-chk:checked + label{border-color:var(--role-dark,#05285a);background:var(--role-soft);color:var(--role-dark,#05285a);}
.kep-chk + label .kico{width:22px;height:22px;border-radius:6px;background:#e8edfa;
    display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0;}
.kep-chk:checked + label .kico{background:var(--role,#0b5cad);color:#fff;}

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
@media(max-width:480px){
    .hdr{padding:12px 14px;}
    .wrap{padding:14px 12px 92px;}
    .card{padding:16px 14px;border-radius:14px;margin-bottom:12px;}
    .row2,.kep-grid{grid-template-columns:1fr;gap:10px;}
    .inp,.sel,.ta{min-height:44px;font-size:16px;border-radius:10px;}
    .kep-chk + label{min-height:44px;padding:10px 12px;}
    .sticky-foot{position:fixed;left:0;right:0;bottom:0;padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 18px rgba(10,22,40,.08);}
    .btn-save,.btn-batal{flex:1;justify-content:center;padding:13px 12px;border-radius:11px;}
}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">
<div class="proses-bar" id="prosesBar"></div>
<div class="hdr">
<div class="hdr-inner">
    <a href="rt_daftar_surat.php" class="btn-back"><i class="fas fa-arrow-left"></i> Daftar Surat</a>
    <div class="hdr-title"><i class="fas fa-file-alt" style="color:var(--role,#0b5cad);margin-right:6px;"></i>Buat Surat Pengantar</div>
</div>
</div>

<form method="post" id="mainForm">
<?= rt_csrf_field(); ?>
<div class="wrap">

<?php if ($err): ?>
<div class="err-box"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- WILAYAH (RT pilih jika RW) -->
<?php if ($jab === 'RW'): ?>
<div class="card">
    <div class="sec-hd"><i class="fas fa-map-marked-alt"></i> Wilayah RT</div>
    <div class="field">
        <label class="lbl">Surat untuk RT <span class="req">*</span></label>
        <?php if (!empty($rt_dalam_rw)): ?>
        <select name="nomor_rt_surat" class="sel" required>
            <option value="">— Pilih RT —</option>
            <?php foreach ($rt_dalam_rw as $ri):
                $rp = str_pad($ri['nomor_rt'],3,'0',STR_PAD_LEFT);
                $sel= (($_POST['nomor_rt_surat'] ?? '') == $ri['nomor_rt']) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars($ri['nomor_rt']) ?>" <?= $sel ?>>
                RT.<?= $rp ?><?= $ri['lokasi'] ? ' — '.$ri['lokasi'] : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="number" name="nomor_rt_surat" class="inp" placeholder="Nomor RT"
               value="<?= $v_post('nomor_rt_surat') ?>" min="1" max="999" required>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- DATA PEMOHON -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-id-card"></i> Data Pemohon</div>

    <div class="field">
        <label class="lbl">NIK / No. KTP</label>
        <div class="nik-wrap">
            <input type="text" name="nik" id="fNik" class="inp" maxlength="16"
                   placeholder="16 digit NIK (opsional, untuk auto-isi)"
                   value="<?= $v_post('nik') ?>" inputmode="numeric">
            <i class="fas fa-spinner fa-spin nik-spin" id="nikSpin"></i>
            <i class="fas fa-check-circle nik-ok" id="nikOk"></i>
        </div>
        <div class="autofill-notice" id="autofillNotice">
            <i class="fas fa-magic"></i> Data diisi otomatis dari database warga.
        </div>
    </div>

    <div class="field">
        <label class="lbl">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama_pemohon" id="fNama" class="inp" required
               style="text-transform:uppercase;"
               placeholder="Nama sesuai KTP"
               value="<?= $v_post('nama_pemohon') ?>">
    </div>

    <div class="row2">
        <div class="field">
            <label class="lbl">Jenis Kelamin</label>
            <select name="jenis_kelamin" id="fJk" class="sel">
                <option value="Laki-laki" <?= ($v_post('jenis_kelamin')==='Laki-laki')?'selected':'' ?>>Laki-laki</option>
                <option value="Perempuan" <?= ($v_post('jenis_kelamin')==='Perempuan')?'selected':'' ?>>Perempuan</option>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Agama</label>
            <select name="agama" id="fAgama" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($agama_opts as $ag): ?>
                <option value="<?= $ag ?>" <?= ($v_post('agama')===$ag)?'selected':'' ?>><?= $ag ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row2">
        <div class="field">
            <label class="lbl">Tempat Lahir</label>
            <input type="text" name="tempat_lahir" id="fTtl" class="inp"
                   style="text-transform:uppercase;" placeholder="Kota"
                   value="<?= $v_post('tempat_lahir') ?>">
        </div>
        <div class="field">
            <label class="lbl">Tanggal Lahir</label>
            <input type="date" name="tgl_lahir" id="fTgl" class="inp"
                   value="<?= $v_post('tgl_lahir') ?>">
        </div>
    </div>

    <div class="field">
        <label class="lbl">Pekerjaan</label>
        <input type="text" name="pekerjaan" id="fPkj" class="inp"
               style="text-transform:uppercase;" placeholder="Wiraswasta, PNS, dll"
               value="<?= $v_post('pekerjaan') ?>">
    </div>

    <div class="field">
        <label class="lbl">Alamat Sekarang</label>
        <textarea name="alamat_sekarang" id="fAlamat" class="ta"
                  style="text-transform:uppercase;"
                  placeholder="Alamat tinggal saat ini"><?= $v_post('alamat_sekarang') ?></textarea>
    </div>
</div>

<!-- KEPERLUAN -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-list-check"></i> Keperluan Surat <span class="req" style="font-size:.75rem;text-transform:none;letter-spacing:0;">*</span></div>
    <div class="kep-grid">
        <?php foreach ($keperluan_list as $i => $kp): ?>
        <?php $checked = in_array($kp, (array)($_POST['keperluan'] ?? [])) ? 'checked' : ''; ?>
        <div>
            <input type="checkbox" name="keperluan[]" id="kp<?= $i ?>"
                   class="kep-chk" value="<?= htmlspecialchars($kp) ?>" <?= $checked ?>>
            <label for="kp<?= $i ?>">
                <span class="kico"><i class="fas fa-file-alt"></i></span>
                <?= htmlspecialchars($kp) ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="field">
        <label class="lbl">Keperluan Lain (jika tidak ada di atas)</label>
        <input type="text" name="keperluan_lain" class="inp"
               placeholder="Tulis keperluan lainnya…"
               value="<?= $v_post('keperluan_lain') ?>">
    </div>
</div>

<!-- INFO SURAT -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-calendar-alt"></i> Info Surat</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Tanggal Surat</label>
            <input type="date" name="tanggal_surat" class="inp"
                   value="<?= $v_post('tanggal_surat') ?: date('Y-m-d') ?>">
        </div>
        <div class="field">
            <label class="lbl">Nama Ketua RT</label>
            <input type="text" name="nama_ketua_rt" class="inp"
                   style="text-transform:uppercase;"
                   placeholder="Nama Ketua RT"
                   value="<?= $v_post('nama_ketua_rt') ?: htmlspecialchars($s['nama_ketua']) ?>">
        </div>
    </div>
</div>

</div><!-- /wrap -->
<div class="sticky-foot">
    <a href="rt_daftar_surat.php" class="btn-batal">Batal</a>
    <button type="submit" class="btn-save" id="btnSave">
        <i class="fas fa-paper-plane"></i> Kirim ke RW
    </button>
</div>
</form>

<script>
/* ── AUTO-FILL NIK ── */
var nikTimer = null;
document.getElementById('fNik').addEventListener('input', function() {
    var nik = this.value.replace(/\D/g,'');
    document.getElementById('nikOk').style.display   = 'none';
    document.getElementById('autofillNotice').style.display = 'none';
    clearTimeout(nikTimer);
    if (nik.length < 10) return;
    document.getElementById('nikSpin').style.display = 'inline';
    nikTimer = setTimeout(function() {
        var rtEl = document.querySelector('[name="nomor_rt_surat"]');
        var rtParam = rtEl ? '&api_rt=' + encodeURIComponent(rtEl.value || '') : '';
        fetch('rt_buat_surat.php?api_nik=' + nik + rtParam)
        .then(r => r.json()).then(function(data) {
            document.getElementById('nikSpin').style.display = 'none';
            if (data.ok) {
                document.getElementById('fNama').value   = data.nama;
                document.getElementById('fJk').value     = data.jk;
                document.getElementById('fTtl').value    = data.tempat;
                document.getElementById('fTgl').value    = data.tgl;
                document.getElementById('fAgama').value  = data.agama;
                document.getElementById('fPkj').value    = data.pekerjaan;
                document.getElementById('fAlamat').value = data.alamat;
                document.getElementById('nikOk').style.display = 'inline';
                document.getElementById('autofillNotice').style.display = 'block';
            }
        }).catch(function() {
            document.getElementById('nikSpin').style.display = 'none';
        });
    }, 600);
});

/* ── FORM SUBMIT ── */
document.getElementById('mainForm').addEventListener('submit', function(e) {
    var nama = document.getElementById('fNama').value.trim();
    if (!nama) {
        e.preventDefault();
        showAlert('Nama pemohon wajib diisi.');
        return;
    }
    var chk = document.querySelectorAll('input[name="keperluan[]"]:checked');
    var lain = document.querySelector('[name="keperluan_lain"]').value.trim();
    if (chk.length === 0 && !lain) {
        e.preventDefault();
        showAlert('Pilih minimal 1 keperluan surat.');
        return;
    }
    document.getElementById('btnSave').disabled = true;
    document.getElementById('btnSave').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';
    document.getElementById('prosesBar').style.display = 'block';
});

function showAlert(msg) {
    var ex = document.getElementById('alertDyn');
    if (ex) ex.remove();
    var d = document.createElement('div');
    d.id = 'alertDyn'; d.className = 'err-box';
    d.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + msg + '</span>';
    document.querySelector('.wrap').prepend(d);
    d.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>
</body>
</html>
