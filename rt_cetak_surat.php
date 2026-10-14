<?php
/**
 * rt_cetak_surat.php — Cetak Surat Pengantar (Format Resmi)
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_rt_session.php';
rt_guard();
rt_ensure_schema($koneksi);

$s    = rt_session();
$jab  = $s['jabatan'];
$id   = (int)($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? '';

if ($id <= 0) { header('Location: rt_daftar_surat.php'); exit; }

$rw_e = mysqli_real_escape_string($koneksi, $s['nomor_rw']);
$rt_e = mysqli_real_escape_string($koneksi, $s['nomor_rt']);
$where = "id=$id AND rw='$rw_e'";
if ($jab === 'RT') $where .= " AND rt='$rt_e'";

$sur = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tbl_surat_pengantar WHERE $where LIMIT 1"));
if (!$sur) {
    echo "<script>alert('Surat tidak ditemukan.');window.location='rt_daftar_surat.php';</script>"; exit;
}

if ($mode !== 'draft' && $sur['status'] !== 'disetujui') {
    echo "<script>alert('Surat belum disetujui RW.\nGunakan tombol Lihat untuk preview draft.');window.history.back();</script>"; exit;
}

// Profil kop RW
$profil = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tbl_profil_rw WHERE nomor_rw='$rw_e' LIMIT 1")) ?: [];

$nama_rw     = $profil['nama_rw']       ?: 'PENGURUS RUKUN WARGA ' . str_pad($sur['rw'], 2, '0', STR_PAD_LEFT);
$nama_sub    = $profil['nama_sub']      ?: '';
$alamat_rw   = $profil['alamat']        ?: 'Kelurahan Medang, Kecamatan Pagedangan, Kabupaten Tangerang';
$telp_rw     = $profil['telepon']       ?: '';
$nama_krt    = $profil['nama_ketua_rw'] ?: '';
$kode_dok    = $profil['kode_dok']      ?: '';

$rt_pad  = str_pad($sur['rt'], 3, '0', STR_PAD_LEFT);
$rw_pad  = str_pad($sur['rw'], 3, '0', STR_PAD_LEFT);
$rt_num  = (int)$sur['rt'];
$rw_num  = (int)$sur['rw'];
$is_draft = ($sur['status'] !== 'disetujui');

// Format tanggal Indonesia
$bulan_id = ['','Januari','Februari','Maret','April','Mei','Juni',
             'Juli','Agustus','September','Oktober','November','Desember'];

function fmt_tgl(string $ymd, array $bln): string {
    if (!$ymd || $ymd === '0000-00-00') return '';
    try { $d = new DateTime($ymd); return $d->format('d') . ' ' . $bln[(int)$d->format('n')] . ' ' . $d->format('Y'); }
    catch(Exception $e) { return $ymd; }
}

$tgl_surat   = $sur['tanggal_surat'] && $sur['tanggal_surat'] !== '0000-00-00'
               ? $sur['tanggal_surat'] : date('Y-m-d');
$tgl_fmt     = fmt_tgl($tgl_surat, $bulan_id);

$tgl_lahir_fmt = '';
if ($sur['tgl_lahir'] && $sur['tgl_lahir'] !== '0000-00-00') {
    try {
        $dl = new DateTime($sur['tgl_lahir']);
        $tgl_lahir_fmt = $dl->format('d-m-Y');
    } catch(Exception $e) {}
}
$ttl = trim(strtoupper($sur['tempat_lahir'] ?? ''));
if ($tgl_lahir_fmt) $ttl .= ($ttl ? ' / ' : '') . $tgl_lahir_fmt;

// Nomor RW tanggal validasi
$tgl_rw_fmt = '';
if ($sur['divalidasi_at']) {
    try { $dv = new DateTime($sur['divalidasi_at']); $tgl_rw_fmt = $dv->format('d-m-Y'); }
    catch(Exception $e) {}
}
if (!$tgl_rw_fmt) $tgl_rw_fmt = (new DateTime($tgl_surat))->format('d-m-Y');

$kep_arr = array_values(array_filter(array_map('trim', explode("\n", $sur['keperluan'] ?? ''))));
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Surat Pengantar — <?= htmlspecialchars($sur['nama_pemohon']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet">
<style>
/* ════════ RESET ════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ════════ SCREEN ════════ */
body {
    background: #d4d4d4;
    font-family: "Times New Roman", Times, serif;
    font-size: 12pt;
    color: #000;
}

/* TOOLBAR */
.toolbar {
    background: #0a1628;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.toolbar a, .toolbar button {
    padding: 7px 16px;
    border-radius: 8px;
    border: none;
    font-family: 'DM Sans', Arial, sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-cetak  { background: var(--role); color:#fff; }
.btn-kembali{ background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.2) !important; }
.tb-info    { color: rgba(255,255,255,.5); font-size: 12px; font-family: Arial, sans-serif; flex: 1; }

<?php if ($is_draft): ?>
.draft-banner {
    background: #fef3c7;
    border-bottom: 2px solid #f59e0b;
    padding: 8px 20px;
    text-align: center;
    font-family: Arial, sans-serif;
    font-size: 13px;
    font-weight: 700;
    color: #854d0e;
}
<?php endif; ?>

/* PAGE WRAPPER */
.page-wrap {
    max-width: 820px;
    margin: 24px auto;
    padding: 0 16px 40px;
}

/* SURAT */
.surat {
    background: #fff;
    padding: 28mm 22mm 20mm 22mm;
    box-shadow: 0 4px 32px rgba(0,0,0,.2);
    position: relative;
    min-height: 297mm;
}

/* ════════ KOP SURAT ════════ */
.kop {
    display: flex;
    align-items: center;
    gap: 0;
    padding-bottom: 8px;
    border-bottom: 4px solid #000;
    margin-bottom: 2px;
}
.kop-logo {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    border: 2.5px solid #000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 8pt;
    text-align: center;
    letter-spacing: 0.5px;
    flex-shrink: 0;
    margin-right: 14px;
    line-height: 1.3;
}
.kop-teks {
    flex: 1;
    text-align: center;
}
.kop-rw {
    font-size: 16pt;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    line-height: 1.15;
    font-family: Arial, sans-serif;
}
.kop-sub {
    font-size: 12pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 2px;
    font-family: Arial, sans-serif;
}
.kop-alamat {
    font-size: 9pt;
    margin-top: 5px;
    color: #333;
    font-family: Arial, sans-serif;
    line-height: 1.5;
}
.kode-dok {
    position: absolute;
    top: 28mm;
    right: 22mm;
    font-size: 8pt;
    font-family: Arial, sans-serif;
    border: 1px solid #999;
    padding: 3px 8px;
    color: #555;
}

/* ════════ JUDUL ════════ */
.judul-wrap {
    text-align: center;
    margin: 18px 0 4px;
}
.judul {
    font-size: 13pt;
    font-weight: 700;
    text-decoration: underline;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-family: Arial, sans-serif;
}
.no-surat {
    font-size: 11pt;
    font-family: Arial, sans-serif;
    margin-top: 3px;
}

/* ════════ BADAN SURAT ════════ */
.pembuka {
    font-size: 11.5pt;
    line-height: 1.85;
    margin: 14px 0 10px;
    text-align: justify;
}

/* TABEL DATA WARGA */
.tbl-warga {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5pt;
    margin: 4px 0 14px;
}
.tbl-warga td {
    padding: 3px 0;
    vertical-align: top;
    line-height: 1.6;
}
.tbl-warga td.col-label { width: 150px; }
.tbl-warga td.col-sep   { width: 14px; text-align: center; }
.tbl-warga td.col-val   { font-weight: 400; }
.tbl-warga td.col-val strong { font-weight: 700; }
.underline-val {
    display: inline-block;
    border-bottom: 1px solid #555;
    min-width: 220px;
    padding-bottom: 1px;
}

/* KEPERLUAN */
.kep-title {
    font-size: 11.5pt;
    margin: 2px 0 6px;
}
.kep-ol {
    font-size: 11.5pt;
    margin-left: 30px;
    line-height: 1.85;
}
.kep-ol li {
    padding-bottom: 2px;
}
.kep-kosong {
    display: block;
    border-bottom: 1px dotted #aaa;
    min-height: 20px;
    margin-bottom: 4px;
    margin-left: 30px;
    color: transparent;
}

/* PENUTUP */
.penutup {
    font-size: 11.5pt;
    line-height: 1.85;
    margin: 18px 0 0;
    text-align: justify;
}

/* ════════ TANDA TANGAN ════════ */
.ttd-section {
    margin-top: 22px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

/* KIRI: Info RW */
.ttd-kiri {
    flex: 0 0 46%;
}
.ttd-tbl {
    border-collapse: collapse;
    font-size: 10.5pt;
    line-height: 1.75;
}
.ttd-tbl td { vertical-align: top; padding: 0; }
.ttd-tbl td:first-child { width: 65px; font-weight: 600; }
.ttd-tbl td:nth-child(2){ width: 12px; text-align: center; }

.ttd-jabatan-label {
    font-size: 11pt;
    font-weight: 700;
    margin-top: 10px;
    font-family: Arial, sans-serif;
}
.ttd-cap-box {
    width: 90px;
    height: 90px;
    border: 1.5px solid #bbb;
    margin: 6px 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8pt;
    color: #bbb;
    text-align: center;
    line-height: 1.5;
    font-family: Arial, sans-serif;
}
.ttd-garis {
    display: inline-block;
    border-bottom: 1.5px solid #000;
    min-width: 160px;
    padding-bottom: 1px;
    font-size: 11pt;
    font-weight: 700;
    font-family: Arial, sans-serif;
    margin-top: 2px;
}

/* KANAN: Tanggal + RT */
.ttd-kanan {
    flex: 0 0 46%;
    text-align: center;
}
.ttd-kota-tgl {
    font-size: 11pt;
    margin-bottom: 4px;
    font-family: Arial, sans-serif;
}

/* CATATAN KAKI */
.catatan-kaki {
    margin-top: 20px;
    font-size: 9pt;
    color: #555;
    border-top: 1px solid #ccc;
    padding-top: 6px;
    line-height: 1.8;
    font-family: Arial, sans-serif;
}

/* DRAFT WATERMARK */
<?php if ($is_draft): ?>
.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-35deg);
    font-size: 80pt;
    font-weight: 900;
    color: rgba(220, 38, 38, 0.07);
    pointer-events: none;
    white-space: nowrap;
    z-index: 0;
    font-family: Arial, sans-serif;
    letter-spacing: 6px;
    user-select: none;
}
<?php endif; ?>

/* ════════ PRINT ════════ */
@media print {
    .toolbar, .draft-banner { display: none !important; }
    body { background: #fff; }
    .page-wrap { max-width: 100%; margin: 0; padding: 0; }
    .surat {
        box-shadow: none;
        padding: 15mm 18mm 12mm 18mm;
        min-height: auto;
    }
    .kode-dok { top: 15mm; right: 18mm; }
    @page {
        size: A4;
        margin: 0;
    }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- TOOLBAR (layar saja) -->
<div class="toolbar">
    <a href="rt_daftar_surat.php" class="btn-kembali">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
    <?php if (!$is_draft): ?>
    <button class="btn-cetak" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak / Simpan PDF
    </button>
    <?php endif; ?>
    <div class="tb-info"><?= htmlspecialchars($sur['nama_pemohon']) ?> &mdash; <?= htmlspecialchars($sur['no_surat_rt']) ?></div>
</div>

<?php if ($is_draft): ?>
<div class="draft-banner">
    ⚠&nbsp; DRAFT — Surat belum disetujui RW. Tampilan ini hanya untuk preview, belum bisa dicetak resmi.
</div>
<?php endif; ?>

<div class="page-wrap">
<div class="surat">

<?php if ($is_draft): ?>
<div class="watermark">DRAFT</div>
<?php endif; ?>

<?php if ($kode_dok): ?>
<div class="kode-dok">No. Dok: <?= htmlspecialchars($kode_dok) ?></div>
<?php endif; ?>

<!-- ══════════ KOP SURAT ══════════ -->
<div class="kop">
    <div class="kop-logo">
        RW<br><?= $rw_pad ?><br>
        <span style="font-size:6pt;font-weight:600;">KEL.<br>MEDANG</span>
    </div>
    <div class="kop-teks">
        <div class="kop-rw"><?= htmlspecialchars($nama_rw) ?></div>
        <?php if ($nama_sub): ?>
        <div class="kop-sub"><?= htmlspecialchars($nama_sub) ?></div>
        <?php endif; ?>
        <div class="kop-alamat">
            <?= htmlspecialchars($alamat_rw) ?>
            <?php if ($telp_rw): ?>&nbsp;&bull;&nbsp; Tel: <?= htmlspecialchars($telp_rw) ?><?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════ JUDUL ══════════ -->
<div class="judul-wrap">
    <div class="judul">Surat Pengantar</div>
    <div class="no-surat">No. :&nbsp;<?= htmlspecialchars($sur['no_surat_rt']) ?></div>
</div>

<!-- ══════════ PEMBUKA ══════════ -->
<p class="pembuka">
    Yang bertanda tangan di bawah ini ketua RT.<?= $rt_pad ?>/RW.<?= $rw_pad ?><?= $nama_sub ? ', ' . htmlspecialchars($nama_sub) : '' ?>,
    Kelurahan Medang, Kecamatan Pagedangan, Kabupaten Tangerang, Propinsi Banten.
    Dengan ini mohon kepada Bapak Lurah Medang untuk Warga kami&nbsp;:
</p>

<!-- ══════════ DATA WARGA ══════════ -->
<table class="tbl-warga">
    <tr>
        <td class="col-label">Nama</td>
        <td class="col-sep">:</td>
        <td class="col-val"><strong><?= htmlspecialchars(strtoupper($sur['nama_pemohon'])) ?></strong></td>
    </tr>
    <tr>
        <td class="col-label">Jenis Kelamin</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= htmlspecialchars($sur['jenis_kelamin'] ?: '—') ?></td>
    </tr>
    <tr>
        <td class="col-label">Agama</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= htmlspecialchars($sur['agama'] ?: '—') ?></td>
    </tr>
    <tr>
        <td class="col-label">Pekerjaan</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= htmlspecialchars(strtoupper($sur['pekerjaan'] ?: '—')) ?></td>
    </tr>
    <tr>
        <td class="col-label">Tempat / Tgl. Lahir</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= htmlspecialchars($ttl ?: '—') ?></td>
    </tr>
    <tr>
        <td class="col-label">No. KTP</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= htmlspecialchars($sur['no_ktp'] ?: ($sur['nik'] ?: '—')) ?></td>
    </tr>
    <tr>
        <td class="col-label">Alamat</td>
        <td class="col-sep">:</td>
        <td class="col-val"><?= nl2br(htmlspecialchars(strtoupper($sur['alamat_sekarang'] ?: '—'))) ?></td>
    </tr>
</table>

<!-- ══════════ KEPERLUAN ══════════ -->
<p class="kep-title">Untuk dibuatkan surat-surat berupa&nbsp;:</p>
<ol class="kep-ol">
    <?php foreach ($kep_arr as $kp): ?>
    <li><?= htmlspecialchars($kp) ?></li>
    <?php endforeach; ?>
    <?php for ($i = count($kep_arr); $i < 3; $i++): ?>
    <li><span style="color:transparent; border-bottom:1px dotted #aaa; display:inline-block; width:80%;">&nbsp;</span></li>
    <?php endfor; ?>
</ol>

<!-- ══════════ PENUTUP ══════════ -->
<p class="penutup">
    Demikian surat pengantar ini kami buat agar dapat dipergunakan sebagaimana mestinya.
    Atas perhatiannya kami ucapkan terima kasih.
</p>

<!-- ══════════ TANDA TANGAN ══════════ -->
<div class="ttd-section">

    <!-- KIRI: Mengetahui Ketua RW -->
    <div class="ttd-kiri">
        <table class="ttd-tbl">
            <tr>
                <td>Nomor</td>
                <td>:</td>
                <td><?= $sur['no_surat_rw'] ? htmlspecialchars($sur['no_surat_rw']) : '&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;' ?></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>:</td>
                <td><?= $is_draft ? '&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;' : htmlspecialchars($tgl_rw_fmt) ?></td>
            </tr>
        </table>
        <div class="ttd-jabatan-label">Ketua RW.<?= $rw_pad ?></div>
        <div class="ttd-cap-box">CAP &amp;<br>TTD<br>RW</div>
        <div class="ttd-garis"><?= $nama_krt ? htmlspecialchars(strtoupper($nama_krt)) : '&nbsp;' ?></div>
    </div>

    <!-- KANAN: Tempat/Tgl + Ketua RT -->
    <div class="ttd-kanan">
        <div class="ttd-kota-tgl">Medang <?= $tgl_fmt ?></div>
        <div class="ttd-jabatan-label">Ketua RT.<?= $rt_pad ?></div>
        <div class="ttd-cap-box" style="margin:6px auto 4px;">CAP &amp;<br>TTD<br>RT</div>
        <div class="ttd-garis"><?= htmlspecialchars(strtoupper($sur['nama_ketua_rt'] ?: '&nbsp;')) ?></div>
    </div>

</div>

<!-- CATATAN KAKI -->
<div class="catatan-kaki">
    Surat ini dibuat oleh akun: <strong><?= htmlspecialchars($sur['dibuat_oleh']) ?></strong>
    &nbsp;&bull;&nbsp; Tanggal buat: <?= htmlspecialchars(date('d-m-Y', strtotime($sur['created_at']))) ?>
    <?php if ($sur['divalidasi_oleh']): ?>
    &nbsp;&bull;&nbsp; Disetujui oleh: <strong><?= htmlspecialchars($sur['divalidasi_oleh']) ?></strong>
    <?php endif; ?>
</div>

</div><!-- /surat -->
</div><!-- /page-wrap -->

</body>
</html>
