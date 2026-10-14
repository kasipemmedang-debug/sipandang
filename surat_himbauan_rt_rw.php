<?php
include 'koneksi.php';
require_once __DIR__ . '/inc_rt_rw.php';
require_once __DIR__ . '/inc_nomor_surat.php';
sipandang_rt_rw_ensure_schema($koneksi);
include 'header.php';
include 'sidebar.php';

$id = (int) ($_GET['id_pengurus'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('Data pengurus tidak valid.'); window.location='kelola_rt_rw.php';</script>";
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pengurus_wilayah WHERE id='$id' LIMIT 1"));
if (!$row) {
    echo "<script>alert('Pengurus tidak ditemukan.'); window.location='kelola_rt_rw.php';</script>";
    exit;
}

$mj = sipandang_rt_rw_masa_jabatan($row);
$label_jab = sipandang_rt_rw_label_jabatan($row);
$penerima = sipandang_rt_rw_label_penerima($row);
$nomor_himbauan = sipandang_rt_rw_nomor_himbauan($koneksi);
$wilayah = $row['jabatan'] === 'RW'
    ? 'RW ' . $row['nomor_rw']
    : 'RT ' . $row['nomor_rt'] . ' / RW ' . $row['nomor_rw'];
?>
<style>
.him-wrap{max-width:720px;margin:24px auto;padding:0 16px 40px;font-family:'Plus Jakarta Sans',sans-serif;}
.him-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(10,22,40,.1);padding:24px;border-left:4px solid var(--role,#4a7dff);}
.him-title{font-size:1.1rem;font-weight:700;color:var(--role-dark,#0f1d40);margin:0 0 8px;}
.him-meta{font-size:.85rem;color:#8593b0;margin-bottom:16px;}
.him-dl{display:grid;grid-template-columns:140px 1fr;gap:8px 12px;font-size:.88rem;margin-bottom:20px;}
.him-dl dt{color:#8593b0;font-weight:600;}
.him-dl dd{margin:0;color:#172036;}
.him-alert{padding:12px 14px;border-radius:8px;font-size:.85rem;margin-bottom:18px;}
.him-alert-warn{background:#fff3cd;border:1px solid #ffeeba;color:#856404;}
.him-alert-exp{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}
.him-actions{display:flex;flex-wrap:wrap;gap:10px;}
.btn-h{background:var(--role,#4a7dff);color:#fff;padding:10px 18px;border-radius:8px;font-weight:700;text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;}
.btn-h-outline{background:#f0f2f5;color:var(--role-dark,#0f1d40);border:1.5px solid #dde3ed;}
.him-note{font-size:.78rem;color:#8593b0;margin-top:16px;padding-top:12px;border-top:1px solid #dde3ed;}
</style>

<div class="content-wrapper">
<div class="him-wrap">
    <p style="margin-bottom:12px;"><a href="kelola_rt_rw.php" style="font-size:.85rem;color:var(--role-dark,#0f1d40);"><i class="fas fa-arrow-left"></i> Kembali ke Pengurus RT/RW</a></p>

    <div class="him-card">
        <h1 class="him-title">Surat Himbauan Pembentukan RT/RW</h1>
        <p class="him-meta">Menyiapkan surat untuk <?= htmlspecialchars($label_jab) ?> — <?= htmlspecialchars($wilayah) ?></p>

        <?php if ($mj['status'] === 'warning'): ?>
        <div class="him-alert him-alert-warn"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($mj['pesan']) ?></div>
        <?php elseif ($mj['status'] === 'expired'): ?>
        <div class="him-alert him-alert-exp"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($mj['pesan']) ?></div>
        <?php endif; ?>

        <dl class="him-dl">
            <dt>Nama Ketua</dt><dd><?= htmlspecialchars($row['nama']) ?></dd>
            <dt>Jabatan</dt><dd><?= htmlspecialchars($label_jab) ?></dd>
            <dt>Lokasi</dt><dd><?= htmlspecialchars($row['lokasi'] ?: '—') ?></dd>
            <dt>Periode</dt><dd><?= htmlspecialchars(sipandang_rt_rw_tampil_periode($row)) ?></dd>
            <dt>Tanggal SK</dt><dd><?= sipandang_rt_rw_format_tgl($row['tanggal_sk'] ?? null) ?></dd>
            <dt>Nomor SK</dt><dd><?= htmlspecialchars($row['nomor_sk'] ?: '—') ?></dd>
            <dt>TMT / Akhir</dt><dd><?= sipandang_rt_rw_format_tgl($row['tanggal_mulai'] ?? null) ?> — <?= sipandang_rt_rw_format_tgl($row['tanggal_selesai'] ?? null) ?></dd>
        </dl>

        <form method="get" action="cetak_himbauan_rt_rw.php" target="_blank" style="margin-bottom:16px;padding:16px;background:var(--bg,#f0f2f5);border-radius:8px;">
            <input type="hidden" name="id_pengurus" value="<?= (int) $id ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label class="lbl" style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">NOMOR SURAT</label>
                    <input type="text" name="nomor" class="inp" value="<?= htmlspecialchars($nomor_himbauan) ?>" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.88rem;">
                </div>
                <div>
                    <label class="lbl" style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">TANGGAL SURAT</label>
                    <input type="date" name="tgl_surat" class="inp" value="<?= date('Y-m-d') ?>" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.88rem;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="lbl" style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">
                    NOMOR SK PENGANGKATAN
                    <?php if (empty($row['nomor_sk'])): ?>
                    <span style="color:#dc3a52;font-style:normal;"> — belum diisi di data pengurus</span>
                    <?php endif; ?>
                </label>
                <input type="text" name="nomor_sk" class="inp" value="<?= htmlspecialchars($row['nomor_sk'] ?? '') ?>" placeholder="Contoh: 149/Kep.056-Kel.Mdn/2024" style="width:100%;padding:9px 12px;border:1.5px solid <?= empty($row['nomor_sk']) ? '#f5c6cb' : '#dde3ed' ?>;border-radius:8px;font-size:.88rem;">
                <div style="font-size:.7rem;color:#8593b0;margin-top:4px;"><i class="fas fa-info-circle"></i> Akan tampil di badan surat. Biarkan kosong jika belum ada SK.</div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="lbl" style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">TEMBUSAN</label>
                <textarea name="tembusan" class="inp" rows="2" placeholder="1. Camat Pagedangan&#10;2. Arsip" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.85rem;resize:vertical;font-family:inherit;">1. Arsip</textarea>
                <div style="font-size:.7rem;color:#8593b0;margin-top:4px;"><i class="fas fa-info-circle"></i> Satu baris per penerima tembusan.</div>
            </div>

            <div class="him-actions" style="margin:0;">
                <button type="submit" class="btn-h"><i class="fas fa-print"></i> Cetak &amp; Simpan</button>
                <a href="kelola_rt_rw.php" class="btn-h btn-h-outline">Batal</a>
            </div>
        </form>

        <p class="him-note">
            <i class="fas fa-info-circle"></i>
            Format surat mengikuti template resmi Kelurahan Medang (kop, nomor B/800/…/Kel.Mdn, perihal Himbauan).
            Setelah cetak, surat otomatis masuk <a href="arsip_himbauan_rt_rw.php" style="color:var(--role-ink,#3157d5);font-weight:700;">Arsip Himbauan</a>.
            Pastikan <strong>Nomor SK</strong>, <strong>TMT</strong>, dan <strong>Akhir Jabatan</strong> sudah diisi di data pengurus.
        </p>
    </div>
</div>
</div>

<?php include 'footer.php'; ?>
