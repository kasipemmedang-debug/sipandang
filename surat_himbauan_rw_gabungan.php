<?php
include 'koneksi.php';
require_once __DIR__ . '/inc_rt_rw.php';
require_once __DIR__ . '/inc_nomor_surat.php';
sipandang_rt_rw_ensure_schema($koneksi);
include 'header.php';
include 'sidebar.php';

$nomor_rw = sipandang_rt_rw_normalize_nomor(trim((string) ($_GET['nomor_rw'] ?? '')));
$daftar_rw = sipandang_rt_rw_himbauan_daftar_rw($koneksi);
$paket = null;

if ($nomor_rw !== '') {
    $paket = sipandang_rt_rw_himbauan_paket_rw($koneksi, $nomor_rw, true);
    if (!$paket || empty($paket['daftar'])) {
        echo "<script>alert('Tidak ada RT/RW di RW " . addslashes($nomor_rw) . " yang perlu surat himbauan saat ini.'); window.location='surat_himbauan_rw_gabungan.php';</script>";
        exit;
    }
}

$nomor_himbauan = sipandang_rt_rw_nomor_himbauan($koneksi);
?>
<style>
.him-wrap{max-width:820px;margin:24px auto;padding:0 16px 40px;font-family:'Plus Jakarta Sans',sans-serif;}
.him-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(10,22,40,.1);padding:24px;border-left:4px solid var(--role,#4a7dff);}
.him-title{font-size:1.1rem;font-weight:700;color:var(--role-dark,#0f1d40);margin:0 0 8px;}
.him-meta{font-size:.85rem;color:#8593b0;margin-bottom:16px;}
.him-table{width:100%;border-collapse:collapse;font-size:.82rem;margin:12px 0 18px;}
.him-table th,.him-table td{border:1px solid #dde3ed;padding:8px 10px;text-align:left;}
.him-table th{background:#f0f2f5;font-weight:700;}
.him-actions{display:flex;flex-wrap:wrap;gap:10px;}
.btn-h{background:var(--role,#4a7dff);color:#fff;padding:10px 18px;border-radius:8px;font-weight:700;text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;}
.btn-h-outline{background:#f0f2f5;color:var(--role-dark,#0f1d40);border:1.5px solid #dde3ed;}
.rw-pick{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:12px;}
.rw-pick a{display:block;padding:12px 14px;border:1.5px solid #dde3ed;border-radius:8px;text-decoration:none;color:var(--role-dark,#0f1d40);font-weight:600;font-size:.88rem;}
.rw-pick a:hover{border-color:var(--role,#4a7dff);background:#fffdf5;}
.rw-pick small{display:block;color:#8593b0;font-weight:400;margin-top:4px;}
.badge-st{font-size:.72rem;padding:2px 8px;border-radius:4px;font-weight:700;}
.badge-akan{background:#fff3cd;color:#856404;}
.badge-exp{background:#f8d7da;color:#721c24;}
</style>

<div class="content-wrapper">
<div class="him-wrap">
    <p style="margin-bottom:12px;"><a href="kelola_rt_rw.php" style="font-size:.85rem;color:var(--role-dark,#0f1d40);"><i class="fas fa-arrow-left"></i> Kembali ke Pengurus RT/RW</a></p>

    <?php if ($nomor_rw === ''): ?>
    <div class="him-card">
        <h1 class="him-title">Surat Himbauan Gabungan per RW</h1>
        <p class="him-meta">Satu surat ditujukan ke <strong>Ketua RW</strong>, berisi semua Ketua RT (dan RW jika perlu) yang masa jabatannya akan atau sudah berakhir di lingkungan RW tersebut.</p>

        <?php if (empty($daftar_rw)): ?>
        <p style="color:#8593b0;font-size:.88rem;">Belum ada RW dengan pengurus yang perlu himbauan saat ini.</p>
        <?php else: ?>
        <p style="font-size:.85rem;margin-bottom:8px;">Pilih RW:</p>
        <div class="rw-pick">
            <?php foreach ($daftar_rw as $item): ?>
            <a href="surat_himbauan_rw_gabungan.php?nomor_rw=<?= urlencode($item['nomor_rw']) ?>">
                RW <?= htmlspecialchars($item['nomor_rw']) ?>
                <small><?= htmlspecialchars($item['nama_rw']) ?> · <?= (int) $item['jml'] ?> jabatan</small>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else:
        $rw = $paket['rw'];
        $items = $paket['daftar'];
        $kepada = sipandang_rt_rw_label_penerima($rw);
    ?>
    <div class="him-card">
        <h1 class="him-title">Himbauan Gabungan — RW <?= htmlspecialchars($nomor_rw) ?></h1>
        <p class="him-meta">Ditujukan ke <?= htmlspecialchars($kepada) ?> · <?= count($items) ?> jabatan dalam satu surat</p>

        <table class="him-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Jabatan</th>
                    <th>Nama</th>
                    <th>No. SK</th>
                    <th>Masa bakti</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['label']) ?></td>
                    <td><?= htmlspecialchars($item['nama'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($item['nomor_sk'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($item['masa_bakti']) ?></td>
                    <td>
                        <span class="badge-st <?= $item['expired'] ? 'badge-exp' : 'badge-akan' ?>">
                            <?= htmlspecialchars($item['status_teks']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="get" action="cetak_himbauan_rw_gabungan.php" target="_blank" style="margin-bottom:16px;padding:16px;background:#f0f2f5;border-radius:8px;">
            <input type="hidden" name="nomor_rw" value="<?= htmlspecialchars($nomor_rw) ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">NOMOR SURAT</label>
                    <input type="text" name="nomor" value="<?= htmlspecialchars($nomor_himbauan) ?>" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.88rem;">
                </div>
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">TANGGAL SURAT</label>
                    <input type="date" name="tgl_surat" value="<?= date('Y-m-d') ?>" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.88rem;">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:.72rem;font-weight:700;color:#8593b0;margin-bottom:5px;">TEMBUSAN</label>
                <textarea name="tembusan" rows="2" placeholder="1. Arsip" style="width:100%;padding:9px 12px;border:1.5px solid #dde3ed;border-radius:8px;font-size:.85rem;resize:vertical;font-family:inherit;">1. Arsip</textarea>
            </div>

            <div class="him-actions">
                <button type="submit" class="btn-h"><i class="fas fa-print"></i> Cetak &amp; Simpan</button>
                <a href="surat_himbauan_rw_gabungan.php" class="btn-h btn-h-outline">Pilih RW lain</a>
            </div>
        </form>

        <p style="font-size:.78rem;color:#8593b0;margin:0;">
            <i class="fas fa-info-circle"></i> Surat satu lembar; kolom Lampiran <strong>-</strong>. Setelah cetak tersimpan di
            <a href="arsip_himbauan_rt_rw.php" style="color:var(--role-ink,#3157d5);font-weight:700;">Arsip Himbauan</a>.
            Untuk satu RT saja, gunakan tombol <strong>Himbauan</strong> per baris di Kelola RT/RW.
        </p>
    </div>
    <?php endif; ?>
</div>
</div>

<?php include 'footer.php'; ?>
