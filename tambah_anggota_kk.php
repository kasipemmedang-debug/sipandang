<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include 'koneksi.php';
require_once __DIR__ . '/inc_siak_nik.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
include 'header.php';
include 'sidebar.php';

$no_kk = isset($_GET['no_kk']) ? preg_replace('/\D/', '', (string) $_GET['no_kk']) : '';
if (strlen($no_kk) !== 16) {
    header('Location: keluarga.php');
    exit;
}

$no_kk_esc = mysqli_real_escape_string($koneksi, $no_kk);
$q_all = mysqli_query(
    $koneksi,
    "SELECT * FROM tbl_penduduk WHERE no_kk='$no_kk_esc' AND status_dasar='HIDUP' ORDER BY " . sipandang_sql_order_shdk()
);
$anggota = [];
while ($q_all && ($r = mysqli_fetch_assoc($q_all))) {
    $anggota[] = $r;
}

$ref = !empty($anggota) ? $anggota[0] : null;
foreach ($anggota as $a) {
    $sh = strtoupper(trim((string) ($a['shdk'] ?? '')));
    if ($sh === '01' || strpos($sh, 'KEPALA') !== false) {
        $ref = $a;
        break;
    }
}

$sudah_kepala = false;
foreach ($anggota as $a) {
    $sh = strtoupper(trim((string) ($a['shdk'] ?? '')));
    if ($sh === '01' || strpos($sh, 'KEPALA') !== false) {
        $sudah_kepala = true;
        break;
    }
}

$profil = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM tbl_profil LIMIT 1')) ?: [];

$ar_agama      = ['1' => 'Islam', '2' => 'Kristen', '3' => 'Katholik', '4' => 'Hindu', '5' => 'Budha', '6' => 'Khonghucu', '7' => 'Kepercayaan'];
$ar_goldar     = ['A', 'B', 'AB', 'O', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Tidak Tahu'];
$ar_status     = ['1' => 'Belum Kawin', '2' => 'Kawin', '3' => 'Cerai Hidup', '4' => 'Cerai Mati'];
$ar_shdk       = sipandang_ref_shdk();
$ar_pendidikan = ['Tidak/Belum Sekolah', 'Tidak Tamat SD', 'Tamat SD/Sederajat', 'SLTP/Sederajat', 'SLTA/Sederajat', 'Diploma I/II', 'Akademi/Diploma III', 'Diploma IV/Strata I', 'Strata II', 'Strata III'];

$def_alamat = strtoupper(trim((string) ($ref['alamat'] ?? '')));
$def_rt     = trim((string) ($ref['rt'] ?? ''));
$def_rw     = trim((string) ($ref['rw'] ?? ''));
$def_kel    = strtoupper(trim((string) ($ref['kelurahan'] ?? ($profil['nama_kelurahan'] ?? 'MEDANG'))));
$def_kec    = strtoupper(trim((string) ($ref['kecamatan'] ?? ($profil['nama_kecamatan'] ?? 'PAGEDANGAN'))));
$def_kpos   = trim((string) ($ref['kode_pos'] ?? ($profil['kode_pos'] ?? '')));
$nama_kepala = strtoupper(trim((string) ($ref['nama_lengkap'] ?? '-')));
$jml_anggota = count($anggota);
?>
<style>
:root{
    --navy:var(--role-dark,#05285a);--navy-soft:var(--role-ink,#08428a);--accent:var(--role,#0b5cad);--accent-lt:var(--role-soft,#e8f2fd);
    --bg:#f4f6fb;--white:#fff;--text:#172036;--muted:#566583;--border:#e3e8f3;
    --success:#0e7050;--danger:#dc3a52;--radius:12px;--shadow:0 3px 14px rgba(10,22,40,.08);
}
body{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--bg)!important;}
.content-wrapper{background:var(--bg)!important;padding:0!important;}
.page-hdr{background:#fff;margin:18px 18px 0;padding:14px 16px;border-radius:14px;box-shadow:var(--shadow);}
.page-hdr h1{font-size:1.02rem;font-weight:800;color:var(--navy);margin:0 0 4px;}
.page-hdr p{font-size:.78rem;color:var(--muted);margin:0;}
.page-wrap{padding:12px 18px 32px;}
.info-box{background:var(--role-soft,#e8f2fd);border:1px solid #e0e7f6;border-radius:10px;padding:12px 14px;font-size:.78rem;color:#56658d;margin-bottom:14px;}
.form-card{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}
.form-hd{padding:14px 18px;border-bottom:1px solid #edf0f8;font-size:.84rem;font-weight:800;color:var(--navy);text-transform:uppercase;letter-spacing:.4px;}
.form-body{padding:18px;}
.page-wrap .lbl{font-size:.68rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:5px;}
.page-wrap .lbl .req{color:var(--danger);}
.inp{width:100%;border:1.5px solid var(--border);border-radius:8px;padding:10px 13px;font-size:.85rem;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:#fff;outline:none;}
.inp:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(15,29,64,.07);}
.inp[readonly]{background:#f8f9fe;color:var(--muted);}
.jk-wrap{display:flex;border:1.5px solid var(--border);border-radius:8px;overflow:hidden;}
.jk-radio{display:none;}
.jk-label{flex:1;padding:10px;text-align:center;cursor:pointer;font-size:.82rem;font-weight:700;background:var(--bg);color:var(--muted);}
.jk-radio:checked+.jk-label{background:var(--navy);color:#fff;}
.jk-sep{width:1px;background:var(--border);}
.dup-warn{background:#fff3cd;border:1.5px solid #f0d080;border-radius:8px;padding:10px 14px;font-size:.78rem;color:#7a5800;display:none;margin-top:6px;}
.dup-warn.show{display:flex;align-items:center;gap:8px;}
.form-footer{padding:14px 18px;background:#f8f9fe;border-top:1px solid #edf0f8;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-pro{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:8px;font-size:.85rem;font-weight:700;border:none;cursor:pointer;text-decoration:none;}
.bp-navy{background:var(--accent);color:#fff;}
.bp-navy:hover{background:var(--navy-soft);color:#fff;}
.bp-ghost{background:#fff;color:var(--muted);border:1.5px solid var(--border);}
.bp-ghost:hover{border-color:var(--navy);color:var(--navy);}
.sec-divider{border:none;border-top:1.5px dashed var(--border);margin:16px 0;}
</style>

<div class="content-wrapper">
<section class="content">
<div class="page-hdr arsip-header">
    <h1><i class="fas fa-user-plus mr-2" style="color:var(--accent);"></i>Tambah Anggota Keluarga</h1>
    <p>No. KK <span style="font-family:monospace;font-weight:700;color:var(--navy);"><?= htmlspecialchars($no_kk); ?></span>
        · Kepala: <?= htmlspecialchars($nama_kepala); ?> · <?= (int) $jml_anggota; ?> anggota hidup</p>
</div>

<div class="page-wrap">
    <div class="info-box">
        <i class="fas fa-info-circle mr-1"></i>
        No. KK dan alamat domisili mengikuti kartu keluarga ini. Setelah disimpan, Anda kembali ke detail KK.
    </div>

    <div class="form-card">
        <div class="form-hd">Data Anggota Baru</div>
        <form action="simpan_anggota_kk.php" method="POST" id="formAnggota">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="lbl">No. Kartu Keluarga</label>
                        <input type="text" name="no_kk" class="inp" value="<?= htmlspecialchars($no_kk); ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="lbl">NIK <span class="req">*</span></label>
                        <input type="text" name="nik" id="inp_nik" class="inp" maxlength="16" required
                               placeholder="16 digit" oninput="formatAngka(this);cekNIK(this);"
                               style="font-family:monospace;letter-spacing:1px;">
                        <div class="dup-warn" id="dupWarn">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>NIK sudah terdaftar.</span>
                        </div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="lbl">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama_lengkap" class="inp text-uppercase" required
                               placeholder="Sesuai dokumen kependudukan">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Jenis Kelamin <span class="req">*</span></label>
                        <div class="jk-wrap">
                            <input type="radio" name="jenis_kelamin" value="L" id="jk_l" class="jk-radio" checked>
                            <label class="jk-label" for="jk_l">Laki-Laki</label>
                            <div class="jk-sep"></div>
                            <input type="radio" name="jenis_kelamin" value="P" id="jk_p" class="jk-radio">
                            <label class="jk-label" for="jk_p">Perempuan</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="inp text-uppercase">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Tanggal Lahir</label>
                        <input type="date" name="tgl_lahir" class="inp">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Golongan Darah</label>
                        <select name="gol_darah" class="inp">
                            <option value="">— Pilih —</option>
                            <?php foreach ($ar_goldar as $g): ?>
                            <option value="<?= htmlspecialchars($g); ?>"><?= htmlspecialchars($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr class="sec-divider">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Agama</label>
                        <select name="agama" class="inp">
                            <option value="">— Pilih —</option>
                            <?php foreach ($ar_agama as $k => $v): ?>
                            <option value="<?= htmlspecialchars((string) $k); ?>"><?= htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Status Perkawinan</label>
                        <select name="status_perkawinan" class="inp">
                            <option value="">— Pilih —</option>
                            <?php foreach ($ar_status as $k => $v): ?>
                            <option value="<?= htmlspecialchars((string) $k); ?>"><?= htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Status Hubungan (SHDK) <span class="req">*</span></label>
                        <select name="shdk" class="inp" required>
                            <option value="">— Pilih SHDK —</option>
                            <?php foreach ($ar_shdk as $k => $v):
                                if ($sudah_kepala && ($k === '01' || stripos($v, 'Kepala') !== false)) {
                                    continue;
                                }
                            ?>
                            <option value="<?= htmlspecialchars((string) $k); ?>"><?= htmlspecialchars($k . ' — ' . $v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Pendidikan</label>
                        <select name="pendidikan" class="inp">
                            <option value="">— Pilih —</option>
                            <?php foreach ($ar_pendidikan as $p): ?>
                            <option value="<?= htmlspecialchars($p); ?>"><?= htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Pekerjaan</label>
                        <select name="pekerjaan" class="inp">
                            <?= sipandang_html_opts_pekerjaan(); ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Kewarganegaraan</label>
                        <select name="kewarganegaraan" class="inp">
                            <option value="WNI">WNI</option>
                            <option value="WNA">WNA</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="lbl">Nama Ayah</label>
                        <input type="text" name="nama_ayah" class="inp text-uppercase">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="lbl">Nama Ibu</label>
                        <input type="text" name="nama_ibu" class="inp text-uppercase">
                    </div>
                </div>

                <hr class="sec-divider">

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="lbl">Alamat Domisili</label>
                        <textarea name="alamat" class="inp text-uppercase" rows="2"><?= htmlspecialchars($def_alamat); ?></textarea>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="lbl">RT</label>
                        <input type="text" name="rt" class="inp" maxlength="3" value="<?= htmlspecialchars($def_rt); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="lbl">RW</label>
                        <input type="text" name="rw" class="inp" maxlength="3" value="<?= htmlspecialchars($def_rw); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Kelurahan</label>
                        <input type="text" name="kelurahan" class="inp text-uppercase" value="<?= htmlspecialchars($def_kel); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Kecamatan</label>
                        <input type="text" name="kecamatan" class="inp text-uppercase" value="<?= htmlspecialchars($def_kec); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="lbl">Kode Pos</label>
                        <input type="text" name="kode_pos" class="inp" value="<?= htmlspecialchars($def_kpos); ?>">
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="detail_kk.php?no_kk=<?= urlencode($no_kk); ?>" class="btn-pro bp-ghost">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn-pro bp-navy" id="btnSimpan">
                    <i class="fas fa-save"></i> Simpan Anggota
                </button>
            </div>
        </form>
    </div>
</div>
</section>
</div>

<script>
function formatAngka(el){ el.value = el.value.replace(/\D/g,''); }
var nikTimer = null;
function cekNIK(el){
    var nik = (el.value || '').replace(/\D/g,'');
    var warn = document.getElementById('dupWarn');
    if (nik.length !== 16) { warn.classList.remove('show'); return; }
    clearTimeout(nikTimer);
    nikTimer = setTimeout(function(){
        if (typeof $ === 'undefined') return;
        $.ajax({
            url: 'cek_nik.php', type: 'POST', data: { nik: nik }, dataType: 'json',
            success: function(r){ warn.classList.toggle('show', !!(r && r.ada)); }
        });
    }, 350);
}
document.getElementById('formAnggota').addEventListener('submit', function(e){
    if (document.getElementById('dupWarn').classList.contains('show')) {
        e.preventDefault();
        if (typeof window.sipandangShowToast === 'function') {
            window.sipandangShowToast('error', 'NIK sudah terdaftar. Periksa kembali.');
        }
    }
});
</script>

<?php include 'footer.php'; ?>
