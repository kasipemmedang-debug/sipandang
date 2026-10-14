<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include 'koneksi.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
include 'header.php';
include 'sidebar.php';

$ar_agama      = ['1'=>'Islam','2'=>'Kristen','3'=>'Katholik','4'=>'Hindu','5'=>'Budha','6'=>'Khonghucu','7'=>'Kepercayaan'];
$ar_goldar     = ['A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-','Tidak Tahu'];
$ar_status     = ['1'=>'Belum Kawin','2'=>'Kawin','3'=>'Cerai Hidup','4'=>'Cerai Mati'];
$ar_shdk       = sipandang_ref_shdk();
$ar_pendidikan = ['Tidak/Belum Sekolah','Tidak Tamat SD','Tamat SD/Sederajat','SLTP/Sederajat','SLTA/Sederajat','Diploma I/II','Akademi/Diploma III','Diploma IV/Strata I','Strata II','Strata III'];

$profil = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT * FROM tbl_profil LIMIT 1")) ?? [];
$bulan_id=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$tgl_indo=date('d').' '.$bulan_id[date('m')].' '.date('Y');
?>
<style>
:root{
    --navy:var(--role-dark,#05285a);--navy-soft:var(--role-ink,#08428a);--accent:var(--role,#0b5cad);--accent-lt:var(--role-soft,#e8f2fd);
    --bg:#f4f6fb;--white:#fff;--text:#172036;--muted:#64708b;--border:#dde3ed;
    --success:#0e7050;--danger:#dc3a52;
    --radius:12px;--shadow:0 4px 20px rgba(10,22,40,.10);--shadow-lg:0 12px 40px rgba(10,22,40,.18);
}
*{box-sizing:border-box;}
body{font-family:'Plus Jakarta Sans',sans-serif !important;background:var(--bg) !important;}
.content-wrapper{background:var(--bg) !important;padding:0 !important;}

/* ── HEADER ── */
.ap-hdr{background:var(--role,#0b5cad);padding:28px 32px 90px;position:relative;overflow:hidden;}
.ap-hdr::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 60L60 0' stroke='rgba(var(--role-rgb,11,92,173),.05)' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");}
.hdr-badge{background:rgba(var(--role-rgb,11,92,173),.18);border:1px solid rgba(var(--role-rgb,11,92,173),.35);color:var(--accent-lt);padding:4px 14px;border-radius:20px;font-size:.72rem;font-weight:700;letter-spacing:.8px;display:inline-block;margin-bottom:12px;}
.ap-hdr h1{font-family: 'Plus Jakarta Sans', sans-serif;color:#fff;font-size:1.7rem;margin:0;}
.ap-hdr p{color:rgba(255,255,255,.5);font-size:.82rem;margin:6px 0 0;}
.ap-wrap{margin-top:-62px;padding:0 24px 60px;position:relative;z-index:10;}

/* ── PROGRESS STEPS ── */
.step-nav{background:var(--white);border-radius:var(--radius) var(--radius) 0 0;display:flex;overflow:hidden;box-shadow:var(--shadow);}
.sn-item{flex:1;display:flex;align-items:center;gap:10px;padding:15px 20px;position:relative;transition:background .2s;}
.sn-item+.sn-item::before{content:'';position:absolute;left:0;top:25%;height:50%;width:1px;background:var(--border);}
.sn-item.active{background:var(--navy);}
.sn-item.done{background:rgba(26,122,74,.07);}
.sn-ball{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex-shrink:0;}
.sn-item.active  .sn-ball{background:var(--accent);color:#fff;}
.sn-item.done    .sn-ball{background:var(--success);color:#fff;}
.sn-item.pending .sn-ball{background:var(--border);color:var(--muted);}
.sn-lbl b{font-size:.8rem;display:block;}
.sn-lbl span{font-size:.68rem;}
.sn-item.active  .sn-lbl b,.sn-item.active  .sn-lbl span{color:rgba(255,255,255,.85);}
.sn-item.done    .sn-lbl b{color:var(--success);}
.sn-item.done    .sn-lbl span{color:var(--muted);}
.sn-item.pending .sn-lbl b,.sn-item.pending .sn-lbl span{color:var(--muted);}

/* ── BODY ── */
.form-body{background:var(--white);border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.step-panel{display:none;padding:28px 30px 20px;animation:fadeUp .3s ease;}
.step-panel.active{display:block;}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

/* ── SECTION TITLE ── */
.sec-ttl{font-family: 'Plus Jakarta Sans', sans-serif;font-size:1rem;color:var(--navy);margin:0 0 18px;padding-bottom:10px;border-bottom:2px solid var(--border);display:flex;align-items:center;gap:10px;}
.sec-ico{width:32px;height:32px;border-radius:8px;background:var(--navy);color:var(--role-light);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.sec-divider{border:none;border-top:1.5px dashed var(--border);margin:18px 0;}

/* ── FORM ELEMENTS ── */
.ap-wrap .lbl{font-size:.68rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:5px;}
.ap-wrap .lbl .req{color:var(--danger);}
.inp{width:100%;border:1.5px solid var(--border);border-radius:8px;padding:10px 13px;font-size:.85rem;font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;}
.inp:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(10,22,40,.07);}
.inp.has-val{border-color:rgba(26,122,74,.4);background:#f8fffc;}
select.inp{cursor:pointer;}
textarea.inp{resize:vertical;min-height:60px;}
.inp-hint{font-size:.68rem;color:var(--muted);margin-top:3px;}

/* JK Toggle */
.jk-wrap{display:flex;border:1.5px solid var(--border);border-radius:8px;overflow:hidden;}
.jk-radio{display:none;}
.jk-label{flex:1;padding:10px;text-align:center;cursor:pointer;font-size:.82rem;font-weight:700;background:var(--bg);color:var(--muted);transition:all .15s;}
.jk-radio:checked+.jk-label{background:var(--navy);color:var(--role-light);}
.jk-radio[value="P"]:checked+.jk-label{background:#c2185b;color:#fff;}
.jk-sep{width:1px;background:var(--border);}

/* NIK Preview */
.nik-preview{background:var(--navy);border-radius:8px;padding:10px 14px;margin-top:6px;display:none;}
.nik-preview.show{display:flex;align-items:center;gap:10px;}
.np-val{font-family:monospace;font-size:1rem;color:var(--accent);font-weight:700;letter-spacing:2px;flex:1;}
.np-info{font-size:.7rem;color:rgba(255,255,255,.5);}

/* Duplikat warning */
.dup-warn{background:#fff3cd;border:1.5px solid #f0d080;border-radius:8px;padding:10px 14px;font-size:.78rem;color:#7a5800;display:none;margin-top:6px;}
.dup-warn.show{display:flex;align-items:center;gap:8px;}

/* Info box */
.info-box{background:var(--bg);border:1.5px solid var(--border);border-left:4px solid var(--accent);border-radius:8px;padding:12px 16px;font-size:.78rem;color:var(--text);margin-bottom:16px;}
.info-box i{color:var(--accent);}

/* Footer bar */
.form-footer{padding:16px 30px;background:var(--bg);border-top:1.5px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;position:sticky;bottom:0;z-index:100;box-shadow:0 -4px 20px rgba(10,22,40,.06);}
.ff-info{font-size:.75rem;color:var(--muted);}
.ff-info b{color:var(--navy);}

/* Buttons */
.btn-pro{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:8px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;font-weight:700;border:none;cursor:pointer;transition:all .15s;text-decoration:none;}
.bp-navy{background:var(--navy);color:var(--role-light);}
.bp-navy:hover{background:var(--navy-soft);}
.bp-gold{background:var(--accent);color:#fff;}
.bp-gold:hover{background:var(--accent-lt);}
.bp-ghost{background:var(--bg);color:var(--muted);border:1.5px solid var(--border);}
.bp-ghost:hover{border-color:var(--navy);color:var(--navy);}
.bp-success{background:var(--success);color:#fff;}

/* Complete badge */
.field-done{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--success);font-size:.75rem;}

/* Summary preview step 3 */
.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.sum-card{background:var(--bg);border-radius:10px;padding:14px 16px;border:1.5px solid var(--border);}
.sum-hd{font-size:.65rem;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.sum-row{display:flex;gap:8px;margin-bottom:6px;font-size:.8rem;}
.sum-key{color:var(--muted);min-width:120px;flex-shrink:0;}
.sum-val{font-weight:600;color:var(--text);}

@media(max-width:767px){.ap-wrap{padding:0 12px 40px;}.step-panel{padding:18px 16px;}.summary-grid{grid-template-columns:1fr;}.sn-lbl span{display:none;}}
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
.content-wrapper{background:var(--bg)!important;padding:0!important;}
.ap-hdr{
    background:#fff !important;
    margin:18px 18px 0;
    padding:14px 16px !important;
    border-radius:14px;
    box-shadow:0 3px 14px rgba(10,22,40,.08);
}
.ap-hdr::before{display:none !important;}
.ap-hdr h1{
    font-family:'Plus Jakarta Sans',sans-serif !important;
    font-size:1.02rem !important;
    font-weight:800 !important;
    color:var(--navy) !important;
}
.ap-hdr p{font-size:.78rem !important;color:var(--muted) !important;}
.hdr-badge{
    background:var(--role-soft,#e8f2fd) !important;
    border:1px solid #e0e7f6 !important;
    color:#56658d !important;
}
.ap-wrap{margin-top:12px !important;padding:0 18px 24px !important;}
.step-nav,.form-wrap{
    border:1px solid var(--border) !important;
    box-shadow:0 3px 14px rgba(10,22,40,.08) !important;
}
.sn-item.active{background:var(--accent) !important;}
.sn-item.active .sn-ball{background:#fff !important;color:var(--accent) !important;}
.sec-ttl{
    font-family:'Plus Jakarta Sans',sans-serif !important;
    color:var(--navy) !important;
    font-size:.84rem !important;
    font-weight:800 !important;
    text-transform:uppercase;
    letter-spacing:.4px;
}
.sec-ico{
    background:var(--role-soft,#e8f2fd) !important;
    color:var(--role-ink,#08428a) !important;
    border-radius:8px !important;
}
.inp:focus{border-color:var(--role-light) !important;box-shadow:0 0 0 .15rem rgba(26,95,160,.12) !important;}
.jk-radio:checked + .jk-label{background:var(--accent) !important;color:#fff !important;}
.nik-preview{background:var(--role,#0b5cad) !important;}
.bp-navy{background:var(--accent) !important;color:#fff !important;}
.bp-navy:hover{background:var(--navy-soft) !important;color:#fff !important;}
.bp-gold{background:var(--role-soft,#e8f2fd) !important;color:var(--role-ink,#08428a) !important;border:1px solid var(--role-line,#c6dcf5) !important;}
.bp-gold:hover{background:var(--role-soft,#dfe7ff) !important;}
.bp-ghost:hover{border-color:var(--accent) !important;color:var(--navy-soft) !important;}

@media(max-width:991px){
    .ap-hdr{margin:12px 12px 0;}
    .ap-wrap{padding:0 12px 20px !important;}
}
</style>

<div class="content-wrapper">

<!-- ── HEADER ── -->
<div class="ap-hdr arsip-header">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="header-badge"><i class="fas fa-user-plus mr-1"></i> KEPENDUDUKAN</span>
                <h1>Input Data Penduduk Baru</h1>
                <p><?=$tgl_indo;?> &nbsp;·&nbsp; Kelurahan <?=strtoupper($profil['nama_kelurahan']??'Medang');?></p>
            </div>
            <div class="hdr-actions">
                <a href="penduduk.php" class="btn-pro bp-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="ap-wrap">

    <!-- STEP NAV -->
    <div class="step-nav">
        <div class="sn-item active" id="snav-1">
            <div class="sn-ball"><i class="fas fa-id-card"></i></div>
            <div class="sn-lbl"><b>Identitas</b><span>NIK, Nama, TTL</span></div>
        </div>
        <div class="sn-item pending" id="snav-2">
            <div class="sn-ball">2</div>
            <div class="sn-lbl"><b>Status & Keluarga</b><span>Agama, SHDK, Ortu</span></div>
        </div>
        <div class="sn-item pending" id="snav-3">
            <div class="sn-ball">3</div>
            <div class="sn-lbl"><b>Alamat</b><span>Domisili, RT/RW</span></div>
        </div>
        <div class="sn-item pending" id="snav-4">
            <div class="sn-ball">4</div>
            <div class="sn-lbl"><b>Konfirmasi</b><span>Periksa & Simpan</span></div>
        </div>
    </div>

    <form action="simpan_penduduk.php" method="POST" id="formTambah">
    <div class="form-body">

        <!-- ══════════ STEP 1: IDENTITAS ══════════ -->
        <div class="step-panel active" id="panel-1">
            <h2 class="sec-ttl"><span class="sec-ico"><i class="fas fa-id-card"></i></span>Identitas Utama</h2>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="lbl">No. Kartu Keluarga <span class="req">*</span></label>
                    <input type="text" name="no_kk" id="inp_no_kk" class="inp track" maxlength="16" placeholder="16 digit angka" required oninput="formatAngka(this)" style="font-family:monospace;letter-spacing:1px;">
                    <div class="inp-hint">Nomor KK 16 digit</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="lbl">NIK <span class="req">*</span></label>
                    <input type="text" name="nik" id="inp_nik" class="inp track" maxlength="16" placeholder="16 digit angka" required oninput="formatAngka(this);cekNIK(this);" style="font-family:monospace;letter-spacing:1px;">
                    <div class="nik-preview" id="nikPreview">
                        <div>
                            <div class="np-val" id="nikDisplay">—</div>
                            <div class="np-info">NIK valid — 16 digit</div>
                        </div>
                        <i class="fas fa-check-circle" style="color:var(--success);font-size:1.2rem;"></i>
                    </div>
                    <div class="dup-warn" id="dupWarn">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>NIK ini sudah terdaftar! <a href="#" id="dupLink" style="color:#7a5800;font-weight:700;">Lihat data →</a></div>
                    </div>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="lbl">Nama Lengkap <span class="req">*</span></label>
                    <input type="text" name="nama_lengkap" class="inp track text-uppercase" placeholder="Nama sesuai dokumen kependudukan" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Jenis Kelamin <span class="req">*</span></label>
                    <div class="jk-wrap">
                        <input type="radio" name="jenis_kelamin" value="L" id="jk_l" class="jk-radio" checked>
                        <label class="jk-label" for="jk_l"><i class="fas fa-mars mr-1"></i>Laki-Laki</label>
                        <div class="jk-sep"></div>
                        <input type="radio" name="jenis_kelamin" value="P" id="jk_p" class="jk-radio">
                        <label class="jk-label" for="jk_p"><i class="fas fa-venus mr-1"></i>Perempuan</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="inp text-uppercase" placeholder="Nama kota/kabupaten">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Tanggal Lahir</label>
                    <input type="date" name="tgl_lahir" class="inp" id="inp_tgl_lahir" onchange="hitungUsia()">
                    <div class="inp-hint" id="usia-display"></div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Golongan Darah</label>
                    <select name="gol_darah" class="inp">
                        <option value="">— Pilih —</option>
                        <?php foreach($ar_goldar as $g): ?>
                        <option value="<?=$g;?>"><?=$g;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- ══════════ STEP 2: STATUS & KELUARGA ══════════ -->
        <div class="step-panel" id="panel-2">
            <h2 class="sec-ttl"><span class="sec-ico"><i class="fas fa-users"></i></span>Status & Data Keluarga</h2>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="lbl">Agama</label>
                    <select name="agama" class="inp">
                        <option value="">— Pilih Agama —</option>
                        <?php foreach($ar_agama as $k=>$v): ?>
                        <option value="<?=$k;?>"><?=$v;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Status Perkawinan</label>
                    <select name="status_perkawinan" class="inp">
                        <option value="">— Pilih Status —</option>
                        <?php foreach($ar_status as $k=>$v): ?>
                        <option value="<?=$k;?>"><?=$v;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Status Hubungan (SHDK)</label>
                    <select name="shdk" class="inp">
                        <option value="">— Pilih SHDK —</option>
                        <?php foreach($ar_shdk as $k=>$v): ?>
                        <option value="<?=$k;?>"><?=$k;?> — <?=$v;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Pendidikan Terakhir</label>
                    <select name="pendidikan" class="inp">
                        <option value="">— Pilih Pendidikan —</option>
                        <?php foreach($ar_pendidikan as $p): ?>
                        <option value="<?=$p;?>"><?=$p;?></option>
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
                        <option value="WNI">WNI — Warga Negara Indonesia</option>
                        <option value="WNA">WNA — Warga Negara Asing</option>
                    </select>
                </div>
            </div>

            <hr class="sec-divider">
            <h2 class="sec-ttl" style="font-size:.88rem;"><span class="sec-ico" style="width:26px;height:26px;font-size:.7rem;"><i class="fas fa-people-arrows"></i></span>Data Orang Tua</h2>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="lbl">Nama Ayah</label>
                    <input type="text" name="nama_ayah" class="inp text-uppercase" placeholder="Nama lengkap ayah kandung">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="lbl">Nama Ibu Kandung</label>
                    <input type="text" name="nama_ibu" class="inp text-uppercase" placeholder="Nama lengkap ibu kandung">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="lbl">No. Akta Kelahiran</label>
                    <input type="text" name="no_akta" class="inp" placeholder="Opsional">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="lbl">No. HP (Opsional)</label>
                    <input type="text" name="no_hp" class="inp" placeholder="08xx-xxxx-xxxx">
                </div>
            </div>
        </div>

        <!-- ══════════ STEP 3: ALAMAT ══════════ -->
        <div class="step-panel" id="panel-3">
            <h2 class="sec-ttl"><span class="sec-ico"><i class="fas fa-map-marker-alt"></i></span>Alamat Domisili</h2>

            <div class="info-box mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                Isi alamat sesuai tempat tinggal saat ini di wilayah
                <strong>Kelurahan <?=strtoupper($profil['nama_kelurahan']??'Medang');?></strong>.
            </div>

            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="lbl">Alamat Jalan / Kampung</label>
                    <textarea name="alamat" class="inp text-uppercase" rows="2" placeholder="Nama jalan, gang, blok, atau nama kampung..."></textarea>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="lbl">RT <span class="req">*</span></label>
                    <input type="text" name="rt" class="inp" maxlength="3" inputmode="numeric" placeholder="001" oninput="formatRT(this)" onblur="padRT(this)">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="lbl">RW <span class="req">*</span></label>
                    <input type="text" name="rw" class="inp" maxlength="3" inputmode="numeric" placeholder="001" oninput="formatRT(this)" onblur="padRT(this)">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Kelurahan</label>
                    <input type="text" name="kelurahan" class="inp text-uppercase" value="<?=strtoupper($profil['nama_kelurahan']??'MEDANG');?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Kecamatan</label>
                    <input type="text" name="kecamatan" class="inp text-uppercase" value="<?=strtoupper($profil['nama_kecamatan']??'PAGEDANGAN');?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="lbl">Kode Pos</label>
                    <input type="text" name="kode_pos" class="inp" value="<?=htmlspecialchars($profil['kode_pos']??'');?>" placeholder="15334">
                </div>
            </div>
        </div>

        <!-- ══════════ STEP 4: KONFIRMASI ══════════ -->
        <div class="step-panel" id="panel-4">
            <h2 class="sec-ttl"><span class="sec-ico"><i class="fas fa-check-double"></i></span>Konfirmasi Data</h2>

            <div class="info-box" style="border-left-color:var(--success);background:#f0faf4;">
                <i class="fas fa-eye mr-2" style="color:var(--success);"></i>
                Periksa kembali semua data berikut sebelum disimpan. Klik <strong>Simpan Data</strong> jika sudah benar.
            </div>

            <div class="summary-grid" id="summaryGrid">
                <!-- diisi oleh JS -->
            </div>
        </div>

        <!-- ── FOOTER ── -->
        <div class="form-footer">
            <div class="ff-info" id="ff-info">Langkah <b id="curr-step">1</b> dari 4</div>
            <div style="display:flex;gap:8px;">
                <button type="button" id="btn-back" class="btn-pro bp-ghost" onclick="stepBack()" style="display:none;"><i class="fas fa-arrow-left"></i> Kembali</button>
                <button type="button" id="btn-next" class="btn-pro bp-navy" onclick="stepNext()">Selanjutnya <i class="fas fa-arrow-right"></i></button>
                <button type="submit" id="btn-save" class="btn-pro bp-gold" style="display:none;"><i class="fas fa-save"></i> Simpan Data Penduduk</button>
            </div>
        </div>

    </div>
    </form>

</div>
</div>
</section>
</div>

<script>
var step = 1;

// ── STEP NAVIGATION ──────────────────────────────
function goStep(n) {
    document.querySelectorAll('.step-panel').forEach(function(p){ p.classList.remove('active'); });
    document.getElementById('panel-'+n).classList.add('active');

    document.querySelectorAll('.sn-item').forEach(function(el, i){
        el.classList.remove('active','done','pending');
        if (i+1 < n)       el.classList.add('done');
        else if (i+1 === n) el.classList.add('active');
        else                el.classList.add('pending');
        // Update ball
        var ball = el.querySelector('.sn-ball');
        if (i+1 < n)       ball.innerHTML = '<i class="fas fa-check"></i>';
        else if (i+1 === n) ball.innerHTML = (i===0)?'<i class="fas fa-id-card"></i>':n;
        else                ball.innerHTML = (i+1);
    });

    document.getElementById('btn-back').style.display = n > 1 ? 'inline-flex' : 'none';
    document.getElementById('btn-next').style.display = n < 4 ? 'inline-flex' : 'none';
    document.getElementById('btn-save').style.display = n === 4 ? 'inline-flex' : 'none';
    document.getElementById('curr-step').textContent = n;

    if (n === 4) buildSummary();
    step = n;
}

function stepNext() {
    if (step === 1) {
        var nik = document.getElementById('inp_nik').value;
        var nokk = document.getElementById('inp_no_kk').value;
        var nama = document.querySelector('[name="nama_lengkap"]').value;
        if (!nik || nik.length < 16) { showToast('error','NIK harus 16 digit!'); return; }
        if (!nokk || nokk.length < 16) { showToast('error','No. KK harus 16 digit!'); return; }
        if (!nama.trim()) { showToast('error','Nama Lengkap wajib diisi!'); return; }
    }
    if (step === 3) {
        document.querySelectorAll('[name="rt"],[name="rw"]').forEach(function(el){ padRT(el); });
        var rt = (document.querySelector('[name="rt"]') || {}).value || '';
        var rw = (document.querySelector('[name="rw"]') || {}).value || '';
        if (!rt.trim()) { showToast('error','RT wajib diisi!'); return; }
        if (!rw.trim()) { showToast('error','RW wajib diisi!'); return; }
    }
    if (step < 4) goStep(step + 1);
}

function stepBack() {
    if (step > 1) goStep(step - 1);
}

// ── CEK DUPLIKAT NIK ─────────────────────────────
var nikTimer = null;
function cekNIK(el) {
    var nik = el.value.replace(/\D/g,'');
    var prev = document.getElementById('nikPreview');
    var warn = document.getElementById('dupWarn');
    if (nik.length === 16) {
        prev.classList.add('show');
        document.getElementById('nikDisplay').textContent = nik;
        clearTimeout(nikTimer);
        nikTimer = setTimeout(function(){
            $.ajax({ url:'cek_nik.php', type:'POST', data:{nik:nik}, dataType:'json',
                success:function(d){
                    if(d && d.ada){
                        warn.classList.add('show');
                        document.getElementById('dupLink').href='edit_penduduk.php?nik='+nik;
                        prev.classList.remove('show');
                    } else {
                        warn.classList.remove('show');
                        prev.classList.add('show');
                    }
                }
            });
        }, 600);
    } else {
        prev.classList.remove('show');
        warn.classList.remove('show');
    }
}

// ── HITUNG USIA ──────────────────────────────────
function hitungUsia() {
    var tgl = document.getElementById('inp_tgl_lahir').value;
    if (!tgl) return;
    var lahir = new Date(tgl);
    var now = new Date();
    var usia = now.getFullYear() - lahir.getFullYear();
    var m = now.getMonth() - lahir.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < lahir.getDate())) usia--;
    var el = document.getElementById('usia-display');
    el.textContent = 'Usia: '+usia+' tahun';
    el.style.color = usia >= 17 ? 'var(--success)' : '#e67e22';
}

// ── FORMAT INPUT ─────────────────────────────────
function formatAngka(el) { el.value = el.value.replace(/\D/g,''); }
function formatRT(el) { el.value = el.value.replace(/\D/g,'').substring(0,3); }
function padRT(el) {
    var v = el.value.replace(/\D/g,'');
    el.value = v ? v.padStart(3,'0') : '';
}

// ── BUILD SUMMARY ────────────────────────────────
var agamaMap = <?= json_encode(sipandang_ref_agama(), JSON_UNESCAPED_UNICODE); ?>;
var statusMap = <?= json_encode(sipandang_ref_kawin(), JSON_UNESCAPED_UNICODE); ?>;
var shdkMap  = <?= json_encode(sipandang_ref_shdk(), JSON_UNESCAPED_UNICODE); ?>;
var pekerjaanMap = <?= json_encode(sipandang_ref_pekerjaan(), JSON_UNESCAPED_UNICODE); ?>;

function gv(name) {
    var checked = document.querySelector('[name="'+name+'"]:checked');
    if (checked) return checked.value;
    var el = document.querySelector('[name="'+name+'"]');
    return el && el.value ? el.value : '—';
}
function gvSelectLabel(name) {
    var el = document.querySelector('[name="'+name+'"]');
    if (!el || el.tagName !== 'SELECT') return gv(name);
    var opt = el.options[el.selectedIndex];
    return (opt && opt.value) ? opt.text.trim() : '—';
}
function gvt(name){ var v = gv(name); return (v && v !== '—') ? v.toUpperCase() : '—'; }

function buildSummary() {
    var nik   = gv('nik');
    var nama  = gvt('nama_lengkap');
    var nokk  = gv('no_kk');
    var jk    = gv('jenis_kelamin') === 'L' ? 'Laki-Laki' : 'Perempuan';
    var tl    = gvt('tempat_lahir');
    var ttl_tgl = gv('tgl_lahir');
    var ttl   = ttl_tgl ? tl+', '+formatTgl(ttl_tgl) : tl;
    var agama = agamaMap[gv('agama')] || gv('agama');
    var kawin = statusMap[gv('status_perkawinan')] || gv('status_perkawinan');
    var shdk  = shdkMap[gv('shdk')] || gv('shdk');
    var pend  = gv('pendidikan');
    var pkjCode = gv('pekerjaan');
    var pkj   = pekerjaanMap[pkjCode] || gvSelectLabel('pekerjaan');
    var ayah  = gvt('nama_ayah');
    var ibu   = gvt('nama_ibu');
    var alm   = gvt('alamat');
    var rt    = gv('rt'); var rw = gv('rw');
    var kel   = gvt('kelurahan');
    var kec   = gvt('kecamatan');
    var wn    = gv('kewarganegaraan');

    document.getElementById('summaryGrid').innerHTML =
        sumCard('fas fa-id-card','Identitas Utama',[
            ['NIK','<span style="font-family:monospace;font-weight:800;">'+nik+'</span>'],
            ['No. KK','<span style="font-family:monospace;">'+nokk+'</span>'],
            ['Nama','<b>'+nama+'</b>'],
            ['Jenis Kelamin',jk],
            ['Tempat, Tgl Lahir',ttl],
        ])+
        sumCard('fas fa-users','Status & Keluarga',[
            ['Agama',agama],['Status Kawin',kawin],['SHDK',shdk],
            ['Pendidikan',pend],['Pekerjaan',pkj],['Kewarganegaraan',wn],
        ])+
        sumCard('fas fa-people-arrows','Orang Tua',[
            ['Nama Ayah',ayah],['Nama Ibu',ibu],
        ])+
        sumCard('fas fa-map-marker-alt','Alamat',[
            ['Alamat',alm],['RT / RW','RT.'+rt+' / RW.'+rw],
            ['Kelurahan',kel],['Kecamatan',kec],
        ]);
}

function sumCard(ico, ttl, rows) {
    var html = '<div class="sum-card"><div class="sum-hd"><i class="'+ico+'" style="color:var(--accent);"></i>'+ttl+'</div>';
    rows.forEach(function(r){ html += '<div class="sum-row"><span class="sum-key">'+r[0]+'</span><span class="sum-val">'+r[1]+'</span></div>'; });
    return html + '</div>';
}

function formatTgl(d) {
    if (!d) return '—';
    var b=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    var p=d.split('-'); return p[2]+' '+b[parseInt(p[1])-1]+' '+p[0];
}

// ── UPPERCASE ────────────────────────────────────
$(document).on('blur','.text-uppercase',function(){ if($(this).val()) $(this).val($(this).val().toUpperCase()); });
</script>

<?php include 'footer.php'; ?>