<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
rt_guard();
rt_ensure_schema($koneksi);
$s     = rt_session();
$label = rt_label_wilayah($s);
$err   = '';

// Ambil data warga (validasi kepemilikan)
$nik_get = trim($_GET['nik'] ?? '');
if ($nik_get === '') { header('Location: rt_portal.php'); exit; }
$d = rt_warga_milik($koneksi, $nik_get, $s);
if (!$d) {
    echo "<script>alert('Data tidak ditemukan atau bukan wilayah Anda.');window.location='rt_portal.php';</script>"; exit;
}

$is_rw = ($s['jabatan'] === 'RW');
$def_rw = str_pad($s['nomor_rw'], 3, '0', STR_PAD_LEFT);

// Jika akun RW: ambil daftar RT dalam wilayahnya
$rt_dalam_rw = [];
if ($is_rw) {
    $rw_e2 = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
    $q_rt2 = mysqli_query($koneksi, "SELECT nomor_rt, nama, lokasi FROM pengurus_wilayah WHERE jabatan='RT' AND nomor_rw='$rw_e2' ORDER BY LPAD(nomor_rt,5,'0') ASC");
    while ($r_rt2 = mysqli_fetch_assoc($q_rt2)) $rt_dalam_rw[] = $r_rt2;
}

// Referensi dropdown
$agama_opts  = sipandang_ref_agama();
$kawin_opts  = sipandang_ref_kawin();
$shdk_opts   = sipandang_ref_shdk();
$pkj_opts    = sipandang_ref_pekerjaan();
$pend_opts   = ['Tidak/Belum Sekolah','Tidak Tamat SD','Tamat SD/Sederajat','SLTP/Sederajat','SLTA/Sederajat','Diploma I/II','Akademi/Diploma III','Diploma IV/Strata I','Strata II','Strata III'];
$darah_opts  = ['','A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-','Tidak Tahu'];
$status_opts = ['HIDUP'=>'HIDUP (Aktif)','PINDAH'=>'PINDAH ke wilayah lain','KELUAR'=>'KELUAR dari Kelurahan','MENINGGAL'=>'MENINGGAL'];
$status_icons= ['HIDUP'=>'fa-heart','PINDAH'=>'fa-truck-moving','KELUAR'=>'fa-door-open','MENINGGAL'=>'fa-cross'];
$status_colors= ['HIDUP'=>'#166534','PINDAH'=>'#92400e','KELUAR'=>'#1e40af','MENINGGAL'=>'#1f2937'];

/* ══ PROSES UPDATE ══ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rt_csrf_check();
    $no_kk       = trim($_POST['no_kk'] ?? '');
    $nama        = strtoupper(trim($_POST['nama_lengkap'] ?? ''));
    $ttl         = trim($_POST['tempat_lahir'] ?? '');
    $tgl         = trim($_POST['tgl_lahir'] ?? '');
    $jk          = $_POST['jenis_kelamin'] ?? '';
    $gol_darah   = $_POST['gol_darah'] ?? '-';
    $agama       = $_POST['agama'] ?? '';
    $kawin       = $_POST['status_perkawinan'] ?? '';
    $shdk        = $_POST['shdk'] ?? '';
    $pendidikan  = $_POST['pendidikan'] ?? '';
    $pekerjaan   = mysqli_real_escape_string($koneksi, sipandang_pekerjaan_normalize((string)($_POST['pekerjaan'] ?? '')));
    $no_akta     = trim($_POST['no_akta'] ?? '');
    $nama_ayah   = strtoupper(trim($_POST['nama_ayah'] ?? ''));
    $nama_ibu    = strtoupper(trim($_POST['nama_ibu'] ?? ''));
    $kwn         = $_POST['kewarganegaraan'] ?? 'WNI';
    $alamat      = strtoupper(trim($_POST['alamat'] ?? ''));
    $kode_pos    = trim($_POST['kode_pos'] ?? '');
    $status_baru = $_POST['status_dasar'] ?? 'HIDUP';
    $tgl_kjd     = trim($_POST['tanggal_kejadian'] ?? '');
    $keterangan  = trim($_POST['keterangan'] ?? '');

    // Validasi status
    if (!array_key_exists($status_baru, $status_opts)) $status_baru = 'HIDUP';

    // Tentukan RT yang digunakan
    if ($is_rw) {
        $rt_baru = str_pad(preg_replace('/\D/', '', $_POST['nomor_rt_pilih'] ?? ''), 3, '0', STR_PAD_LEFT);
    } else {
        $rt_baru = str_pad($s['nomor_rt'], 3, '0', STR_PAD_LEFT);
    }

    if ($nama === '') {
        $err = 'Nama lengkap wajib diisi.';
    } elseif ($is_rw && $rt_baru === '000') {
        $err = 'Pilih nomor RT untuk warga ini.';
    } elseif ($status_baru !== 'HIDUP' && $tgl_kjd === '') {
        $err = 'Tanggal kejadian wajib diisi saat mengubah status warga.';
    } else {
        $f     = fn($v) => mysqli_real_escape_string($koneksi, $v);
        $nik_e = $f($nik_get);
        $rw_s  = $f($s['nomor_rw']);
        $rt_s  = $f($s['nomor_rt']);

        // ── Update data utama ──
        $q = "UPDATE tbl_penduduk SET
            no_kk='{$f($no_kk)}', nama_lengkap='{$f($nama)}',
            tempat_lahir='{$f($ttl)}', tgl_lahir='{$f($tgl)}',
            jenis_kelamin='{$f($jk)}', gol_darah='{$f($gol_darah)}',
            agama='{$f($agama)}', status_perkawinan='{$f($kawin)}', shdk='{$f($shdk)}',
            pendidikan='{$f($pendidikan)}', pekerjaan='$pekerjaan',
            no_akta='{$f($no_akta)}', nama_ayah='{$f($nama_ayah)}', nama_ibu='{$f($nama_ibu)}',
            kewarganegaraan='{$f($kwn)}', alamat='{$f($alamat)}', kode_pos='{$f($kode_pos)}',
            rt='{$f($rt_baru)}', status_dasar='{$f($status_baru)}'
            WHERE nik='$nik_e' AND rw='$rw_s'";
        if (!$is_rw) $q .= " AND rt='$rt_s'";

        if (mysqli_query($koneksi, $q)) {
            // ── Jika status berubah ke non-HIDUP → arsipkan ──
            if ($status_baru !== 'HIDUP') {
                rt_arsipkan_warga($koneksi, [
                    'nik' => $nik_get, 'no_kk' => $no_kk, 'nama_lengkap' => $nama,
                    'tempat_lahir' => $ttl, 'tgl_lahir' => ($tgl ?: ($d['tgl_lahir'] ?? null)),
                    'jenis_kelamin' => $jk, 'gol_darah' => $gol_darah, 'agama' => $agama,
                    'status_perkawinan' => $kawin, 'shdk' => $shdk, 'pendidikan' => $pendidikan,
                    'pekerjaan' => $_POST['pekerjaan'] ?? '', 'no_akta' => $no_akta,
                    'nama_ayah' => $nama_ayah, 'nama_ibu' => $nama_ibu, 'kewarganegaraan' => $kwn,
                    'alamat' => $alamat, 'kode_pos' => $kode_pos, 'rt' => $rt_baru, 'rw' => $s['nomor_rw'],
                ], $status_baru, $tgl_kjd ?: date('Y-m-d'), $keterangan, $s['username']);

                header('Location: rt_portal.php?toast=arsip'); exit;
            }
            header('Location: rt_portal.php?toast=upd'); exit;
        } else {
            $err = 'Gagal menyimpan: ' . mysqli_error($koneksi);
        }
    }
    // reload data terbaru setelah error
    $d = rt_warga_milik($koneksi, $nik_get, $s) ?: $d;
}

// Nilai form: prioritaskan POST (jika ada error), fallback ke DB
$v = fn($k) => htmlspecialchars($_POST[$k] ?? $d[$k] ?? '');
$status_form = $_POST['status_dasar'] ?? $d['status_dasar'] ?? 'HIDUP';
?><!DOCTYPE html>
<html class="sp-role-rtrw" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Warga — <?= htmlspecialchars($d['nama_lengkap']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;}
.hdr{background:linear-gradient(135deg,var(--role-dark,#05285a),var(--role,#0b5cad));padding:14px 20px;position:sticky;top:0;z-index:100;}
.hdr-inner{max-width:680px;margin:0 auto;display:flex;align-items:center;gap:12px;}
.hdr-back{color:rgba(255,255,255,.7);text-decoration:none;font-size:.85rem;display:flex;align-items:center;gap:6px;}
.hdr-back:hover{color:#fff;}
.hdr-title{color:#fff;font-weight:700;font-size:.95rem;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
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
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:12px 16px;font-size:.85rem;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.5;}
.err-box i{flex-shrink:0;margin-top:2px;}
.nik-badge{background:#e8edfa;color:var(--role);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.9rem;font-weight:700;letter-spacing:1px;display:block;margin-bottom:12px;}
.sticky-foot{position:sticky;bottom:0;background:#fff;border-top:1.5px solid #dde3ed;padding:12px 20px;display:flex;gap:10px;justify-content:flex-end;z-index:50;}
.btn-save{background:var(--role,#0b5cad);color:#fff;border:none;padding:11px 24px;border-radius:9px;font-weight:700;font-size:.88rem;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:.2s;}
.btn-save:hover{background:var(--role-ink,#08428a);color:#fff;}
.btn-save:disabled{background:#64708b;color:#fff;cursor:not-allowed;}
.btn-batal{background:#f0f2f5;color:var(--role-dark,#05285a);border:none;padding:11px 20px;border-radius:9px;font-weight:700;font-size:.88rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;}
.badge-lock{background:#e8edfa;color:var(--role);border-radius:6px;padding:9px 12px;font-size:.82rem;font-weight:700;display:block;}
.proses-bar{display:none;position:fixed;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--role,#0b5cad),#f0d48a,var(--role,#0b5cad));background-size:200%;animation:shimmer 1.2s linear infinite;z-index:999;}
@keyframes shimmer{to{background-position:200% 0;}}

/* STATUS WARGA */
.status-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.status-opt{display:none;}
.status-opt + label{
    display:flex;align-items:center;gap:10px;
    padding:11px 14px;border-radius:10px;
    border:2px solid #dde3ed;cursor:pointer;
    font-size:.84rem;font-weight:600;color:#5a6a80;
    transition:.15s;
}
.status-opt:checked + label{border-color:var(--sc);background:var(--sb);color:var(--sc);}
.status-opt + label .sico{width:32px;height:32px;border-radius:8px;background:var(--sb);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:var(--sc);flex-shrink:0;}

/* arsip warning */
.arsip-warn{display:none;background:#fef9e7;border:1.5px solid #f6d860;border-radius:10px;padding:14px 16px;font-size:.83rem;color:#7d5a00;line-height:1.6;margin-bottom:14px;}
.arsip-warn i{margin-right:6px;}
.arsip-warn strong{display:block;margin-bottom:4px;font-size:.87rem;}

/* arsip detail card */
#cardArsipDetail{display:none;}

@media(max-width:480px){.row2,.row3{grid-template-columns:1fr;}.status-grid{grid-template-columns:1fr 1fr;}}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-rtrw">
<div class="proses-bar" id="prosesBar"></div>

<div class="hdr">
<div class="hdr-inner">
    <a href="rt_portal.php" class="hdr-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="hdr-title">Edit: <?= htmlspecialchars($d['nama_lengkap']) ?></div>
</div>
</div>

<form method="post" id="mainForm">
<?= rt_csrf_field(); ?>
<div class="wrap">

<?php if ($err): ?>
<div class="err-box"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- ══ STATUS WARGA ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-heartbeat"></i> Status Warga</div>

    <div class="status-grid">
        <?php
        $sc_map = [
            'HIDUP'    => ['#166534','#dcfce7','fa-heart'],
            'PINDAH'   => ['#92400e','#fef3c7','fa-truck-moving'],
            'KELUAR'   => ['#1e40af','#eff6ff','fa-door-open'],
            'MENINGGAL'=> ['#1f2937','#f3f4f6','fa-dove'],
        ];
        foreach ($sc_map as $sk => [$sc,$sb,$ico]): ?>
        <div>
            <input type="radio" name="status_dasar" id="st_<?= $sk ?>"
                   class="status-opt" value="<?= $sk ?>"
                   style="--sc:<?= $sc ?>;--sb:<?= $sb ?>;"
                   <?= $status_form === $sk ? 'checked' : '' ?>>
            <label for="st_<?= $sk ?>" style="--sc:<?= $sc ?>;--sb:<?= $sb ?>;">
                <span class="sico"><i class="fas <?= $ico ?>"></i></span>
                <?= $status_opts[$sk] ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Warning saat non-HIDUP -->
    <div class="arsip-warn" id="arsipWarn">
        <i class="fas fa-archive"></i>
        <strong>Data akan dipindahkan ke Arsip Penduduk</strong>
        Warga ini tidak akan muncul lagi di daftar aktif. Data tetap tersimpan di arsip dan bisa dilihat kapan saja melalui menu Arsip.
    </div>
</div>

<!-- ══ DETAIL ARSIP (muncul saat non-HIDUP) ══ -->
<div class="card" id="cardArsipDetail">
    <div class="sec-hd"><i class="fas fa-folder-open"></i> Detail Perubahan Status</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Tanggal Kejadian <span class="req">*</span></label>
            <input type="date" name="tanggal_kejadian" id="tglKejadian" class="inp"
                   value="<?= htmlspecialchars($_POST['tanggal_kejadian'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="field">
            <label class="lbl" id="lblKeterangan">Keterangan</label>
            <input type="text" name="keterangan" id="inpKeterangan" class="inp" maxlength="200"
                   placeholder="Tujuan pindah / keterangan…"
                   value="<?= htmlspecialchars($_POST['keterangan'] ?? '') ?>">
        </div>
    </div>
</div>

<!-- ══ NIK & WILAYAH ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-fingerprint"></i> NIK & Wilayah</div>
    <span class="nik-badge"><i class="fas fa-lock" style="opacity:.4;margin-right:6px;"></i><?= htmlspecialchars($d['nik']) ?></span>
    <div class="row2" style="margin-top:12px;">
        <div>
            <label class="lbl">Nomor RT <?= $is_rw ? '<span class="req">*</span>' : '' ?></label>
            <?php if ($is_rw): ?>
                <?php $rt_current = $_POST['nomor_rt_pilih'] ?? ltrim($d['rt'], '0') ?: '0'; ?>
                <?php if (!empty($rt_dalam_rw)): ?>
                <select name="nomor_rt_pilih" class="sel" required>
                    <option value="">— Pilih RT —</option>
                    <?php foreach ($rt_dalam_rw as $rt_item):
                        $rt_pad = str_pad($rt_item['nomor_rt'], 3, '0', STR_PAD_LEFT);
                        $sel    = ((string)ltrim($rt_item['nomor_rt'],'0') === (string)ltrim($rt_current,'0')) ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($rt_item['nomor_rt']) ?>" <?= $sel ?>>
                        RT.<?= $rt_pad ?><?= $rt_item['lokasi'] ? ' — '.$rt_item['lokasi'] : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="number" name="nomor_rt_pilih" class="inp" placeholder="Nomor RT"
                       value="<?= htmlspecialchars($rt_current) ?>" min="1" max="999" required>
                <div style="font-size:.68rem;color:#64708b;margin-top:4px;"><i class="fas fa-info-circle"></i> Masukkan nomor RT secara manual.</div>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge-lock"><?= str_pad($s['nomor_rt'],3,'0',STR_PAD_LEFT) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <label class="lbl">Nomor RW</label>
            <span class="badge-lock">RW.<?= $def_rw ?></span>
        </div>
    </div>
</div>

<!-- ══ DATA KELUARGA ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-home"></i> Data Keluarga</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">No. KK</label>
            <input type="text" name="no_kk" class="inp" maxlength="16" value="<?= $v('no_kk') ?>">
        </div>
        <div class="field">
            <label class="lbl">Status dalam Keluarga</label>
            <select name="shdk" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($shdk_opts as $k=>$vv): ?>
                <option value="<?= $k ?>" <?= ($v('shdk')===$k)?'selected':'' ?>><?= $vv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- ══ DATA PRIBADI ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-id-card"></i> Data Pribadi</div>
    <div class="field">
        <label class="lbl">Nama Lengkap <span class="req">*</span></label>
        <input type="text" name="nama_lengkap" class="inp" required style="text-transform:uppercase;" value="<?= $v('nama_lengkap') ?>">
    </div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Tempat Lahir</label>
            <input type="text" name="tempat_lahir" class="inp" value="<?= $v('tempat_lahir') ?>">
        </div>
        <div class="field">
            <label class="lbl">Tanggal Lahir</label>
            <input type="date" name="tgl_lahir" class="inp" value="<?= htmlspecialchars($d['tgl_lahir'] !== '0000-00-00' ? $d['tgl_lahir'] : '') ?>">
        </div>
    </div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="sel">
                <option value="Laki-laki" <?= $v('jenis_kelamin')==='Laki-laki'?'selected':'' ?>>Laki-laki</option>
                <option value="Perempuan" <?= $v('jenis_kelamin')==='Perempuan'?'selected':'' ?>>Perempuan</option>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Golongan Darah</label>
            <select name="gol_darah" class="sel">
                <?php foreach ($darah_opts as $dd): ?>
                <option value="<?= $dd ?>" <?= $v('gol_darah')===$dd?'selected':'' ?>><?= $dd ?: '— Tidak Tahu —' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row3">
        <div class="field">
            <label class="lbl">Agama</label>
            <select name="agama" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($agama_opts as $k=>$vv): ?>
                <option value="<?= $k ?>" <?= $v('agama')===$k?'selected':'' ?>><?= $vv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Status Perkawinan</label>
            <select name="status_perkawinan" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($kawin_opts as $k=>$vv): ?>
                <option value="<?= $k ?>" <?= $v('status_perkawinan')===$k?'selected':'' ?>><?= $vv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Kewarganegaraan</label>
            <select name="kewarganegaraan" class="sel">
                <option value="WNI" <?= $v('kewarganegaraan')==='WNI'?'selected':'' ?>>WNI</option>
                <option value="WNA" <?= $v('kewarganegaraan')==='WNA'?'selected':'' ?>>WNA</option>
            </select>
        </div>
    </div>
</div>

<!-- ══ PENDIDIKAN & PEKERJAAN ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-briefcase"></i> Pendidikan & Pekerjaan</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Pendidikan Terakhir</label>
            <select name="pendidikan" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($pend_opts as $p): ?>
                <option value="<?= $p ?>" <?= $v('pendidikan')===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="lbl">Pekerjaan</label>
            <select name="pekerjaan" class="sel">
                <option value="">— Pilih —</option>
                <?php foreach ($pkj_opts as $k=>$vv): ?>
                <option value="<?= $k ?>" <?= $v('pekerjaan')===$k?'selected':'' ?>><?= $vv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- ══ ORANG TUA & AKTA ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-users"></i> Orang Tua & Akta</div>
    <div class="row2">
        <div class="field">
            <label class="lbl">Nama Ayah</label>
            <input type="text" name="nama_ayah" class="inp" style="text-transform:uppercase;" value="<?= $v('nama_ayah') ?>">
        </div>
        <div class="field">
            <label class="lbl">Nama Ibu</label>
            <input type="text" name="nama_ibu" class="inp" style="text-transform:uppercase;" value="<?= $v('nama_ibu') ?>">
        </div>
    </div>
    <div class="field">
        <label class="lbl">No. Akta Lahir</label>
        <input type="text" name="no_akta" class="inp" value="<?= $v('no_akta') ?>">
    </div>
</div>

<!-- ══ ALAMAT ══ -->
<div class="card">
    <div class="sec-hd"><i class="fas fa-map-pin"></i> Alamat</div>
    <div class="field">
        <label class="lbl">Alamat Lengkap</label>
        <textarea name="alamat" class="ta" style="text-transform:uppercase;"><?= $v('alamat') ?></textarea>
    </div>
    <div class="field">
        <label class="lbl">Kode Pos</label>
        <input type="text" name="kode_pos" class="inp" maxlength="10" value="<?= $v('kode_pos') ?>">
    </div>
</div>

</div><!-- /wrap -->

<div class="sticky-foot">
    <a href="rt_portal.php" class="btn-batal">Batal</a>
    <button type="submit" class="btn-save" id="btnSimpan">
        <i class="fas fa-save"></i> <span id="btnTxt">Simpan Perubahan</span>
    </button>
</div>

<script>
/* ══ STATUS TOGGLE ══ */
var keterPlaceholder = {
    'PINDAH'   : 'Contoh: Pindah ke Kecamatan Rajeg',
    'KELUAR'   : 'Contoh: Keluar dari wilayah Kelurahan Medang',
    'MENINGGAL': 'Contoh: Meninggal di rumah / RS Medika',
};
var lblKet = {
    'PINDAH'   : 'Keterangan Pindah',
    'KELUAR'   : 'Keterangan Keluar',
    'MENINGGAL': 'Keterangan Meninggal',
};
var btnTxt = {
    'HIDUP'    : '<i class="fas fa-save"></i> Simpan Perubahan',
    'PINDAH'   : '<i class="fas fa-truck-moving"></i> Simpan & Arsipkan (Pindah)',
    'KELUAR'   : '<i class="fas fa-door-open"></i> Simpan & Arsipkan (Keluar)',
    'MENINGGAL': '<i class="fas fa-cross"></i> Simpan & Arsipkan (Meninggal)',
};

function onStatusChange() {
    var val = document.querySelector('input[name="status_dasar"]:checked')?.value || 'HIDUP';
    var warn  = document.getElementById('arsipWarn');
    var card  = document.getElementById('cardArsipDetail');
    var btn   = document.getElementById('btnSimpan');
    var inpK  = document.getElementById('inpKeterangan');
    var lblK  = document.getElementById('lblKeterangan');
    var tglK  = document.getElementById('tglKejadian');

    if (val !== 'HIDUP') {
        warn.style.display  = 'block';
        card.style.display  = 'block';
        tglK.required       = true;
        inpK.placeholder    = keterPlaceholder[val] || '';
        if (lblK) lblK.textContent = lblKet[val] || 'Keterangan';
    } else {
        warn.style.display  = 'none';
        card.style.display  = 'none';
        tglK.required       = false;
    }
    btn.innerHTML = btnTxt[val] || btnTxt['HIDUP'];
}

document.querySelectorAll('input[name="status_dasar"]').forEach(function(el) {
    el.addEventListener('change', onStatusChange);
});
onStatusChange(); // jalankan saat load

/* ══ FORM SUBMIT ══ */
document.getElementById('mainForm').addEventListener('submit', function(e) {
    var nama = document.querySelector('[name="nama_lengkap"]').value.trim();
    if (!nama) {
        e.preventDefault();
        showAlert('Nama lengkap wajib diisi.');
        return;
    }
    var status = document.querySelector('input[name="status_dasar"]:checked')?.value || 'HIDUP';
    if (status !== 'HIDUP') {
        var tgl = document.getElementById('tglKejadian').value;
        if (!tgl) {
            e.preventDefault();
            showAlert('Tanggal kejadian wajib diisi.');
            return;
        }
        if (!confirm('Anda akan mengubah status warga ini menjadi ' + status + '.\nData akan dipindahkan ke Arsip Penduduk.\n\nLanjutkan?')) {
            e.preventDefault();
            return;
        }
    }
    var btn = document.getElementById('btnSimpan');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…';
    btn.disabled  = true;
    document.getElementById('prosesBar').style.display = 'block';
});

function showAlert(msg) {
    var ex = document.getElementById('alertDyn');
    if (ex) ex.remove();
    var d2 = document.createElement('div');
    d2.id = 'alertDyn';
    d2.className = 'err-box';
    d2.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + msg + '</span>';
    document.querySelector('.wrap').prepend(d2);
    d2.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>
</form>
</body>
</html>
