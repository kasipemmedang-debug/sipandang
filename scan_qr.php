<?php
include 'koneksi.php';
include 'header.php';
include 'sidebar.php';

// Cari berdasarkan nomor
$nomor = trim($_GET['nomor'] ?? '');
$found = null;
$dokumen = [];
if ($nomor) {
    $s = mysqli_real_escape_string($koneksi, $nomor);
    $q = mysqli_query($koneksi, "
        SELECT p.*, u.nama_lengkap, u.nik, u.no_hp 
        FROM tbl_pengajuan_warga p
        JOIN tbl_user_warga u ON p.id_user = u.id_user
        WHERE p.nomor_pengajuan='$s' LIMIT 1
    ");
    $found = mysqli_fetch_assoc($q);
    if ($found) {
        $id = $found['id_pengajuan'];
        $qd = mysqli_query($koneksi, "SELECT * FROM tbl_dokumen_pengajuan WHERE id_pengajuan=$id");
        while ($r = mysqli_fetch_assoc($qd)) $dokumen[] = $r;
    }
}
?>

<style>
body{font-family:'Plus Jakarta Sans',sans-serif !important;background:#f4f6fb !important;}
.content-wrapper{background:#f4f6fb !important;padding:0 !important;}
.qr-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(10,22,40,.1);padding:24px;border:1px solid #e3e8f3;}
.scan-area{background:#f7f8fd;border:2px dashed #dde3ed;border-radius:10px;padding:30px;text-align:center;}
.btn-big{display:inline-flex;align-items:center;gap:10px;padding:14px 28px;border-radius:10px;font-weight:700;font-size:.95rem;cursor:pointer;border:none;}
.bb-navy{background:var(--role,#0b5cad);color:#fff;}
.bb-navy:hover{background:var(--role-ink,#08428a);}
.bb-gold{background:var(--role-soft,#e8f2fd);color:var(--role-ink,#08428a);border:1px solid var(--role-line,#c6dcf5);}
.search-form{display:flex;gap:10px;align-items:center;}
.search-form input{flex:1;padding:14px 18px;border:1.5px solid #e3e8f3;border-radius:8px;font-size:1rem;font-family:monospace;letter-spacing:1px;outline:none;}
.search-form input:focus{border-color:var(--role-light);box-shadow:0 0 0 .15rem rgba(26,95,160,.12);}
.search-form button{padding:14px 28px;border:none;border-radius:8px;background:var(--role,#0b5cad);color:#fff;font-weight:700;cursor:pointer;}
.search-form button:hover{background:var(--role-ink,#08428a);}
.result-card{background:#d4edda;border:2px solid #c3e6cb;border-radius:12px;padding:20px;margin-top:18px;}
.result-NOT_SIAP{background:#fff3cd;border-color:#f0d080;}
.result-SELESAI,.result-TOLAK{background:#f8d7da;border-color:#f5c6cb;}
.qr-wrap{margin-top:12px;padding:0 18px 24px;position:relative;z-index:10;}
.qr-badge{display:inline-flex;align-items:center;gap:6px;background:var(--role-soft,#e8f2fd);border:1px solid #e0e7f6;color:#56658d;padding:5px 10px;border-radius:999px;font-size:.68rem;font-weight:700;letter-spacing:.35px;margin-bottom:8px;}
.qr-badge i{color:var(--role-light,#a6c7f2);}
.qr-back{color:#4f6380 !important;font-size:.82rem;text-decoration:none;display:inline-block;margin-bottom:6px;}
.qr-title{font-family:'Plus Jakarta Sans',sans-serif;color:var(--role-dark,#05285a);margin:2px 0 0;font-size:1.02rem;font-weight:800;}
.qr-sub{color:#566583;margin:3px 0 0;font-size:.78rem;}
.qr-card h3{font-family:'Plus Jakarta Sans',sans-serif !important;color:var(--role-dark,#05285a) !important;font-size:.84rem !important;font-weight:800 !important;text-transform:uppercase;letter-spacing:.4px;}
@media(max-width:767px){
    .qr-wrap{padding:0 12px 20px;}
}
@media print{.loket-dock-wrap,.loket-fab-desktop-wrap,#loketMenuOverlay,.no-print{display:none!important;}}
</style>

<div class="content-wrapper">
<div class="arsip-header qr-header">
    <div class="container-fluid">
        <span class="qr-badge"><i class="fas fa-qrcode"></i> PENGAMBILAN SURAT</span>
        <a href="pengajuan_warga.php" class="qr-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h1 class="qr-title">
            Scan QR Ambil Surat
        </h1>
        <p class="qr-sub">Scan QR Code dari aplikasi warga atau masukkan nomor pengajuan</p>
    </div>
</div>

<section class="content">
<div class="container-fluid">
<div class="qr-wrap">

<div class="qr-card">
    <h3 style="margin:0 0 16px;">Cari Pengajuan</h3>
    
    <form method="GET" class="search-form">
        <input type="text" name="nomor" placeholder="Masukkan nomor pengajuan (contoh: PG-KK_-20260514-0001)" value="<?= htmlspecialchars($nomor) ?>" autofocus>
        <button type="submit"><i class="fas fa-search"></i> Cari</button>
    </form>

    <div style="margin-top:14px;text-align:center;">
        <button onclick="startScan()" class="btn-big bb-gold">
            <i class="fas fa-camera"></i> Scan QR Code dengan Kamera
        </button>
    </div>

    <div id="scanArea" style="display:none;margin-top:18px;">
        <div id="reader" style="max-width:400px;margin:0 auto;border-radius:10px;overflow:hidden;"></div>
        <button onclick="stopScan()" class="btn-big bb-navy" style="margin-top:12px;">
            <i class="fas fa-stop"></i> Stop Scan
        </button>
    </div>

    <?php if($nomor): ?>
        <?php if(!$found): ?>
            <div class="result-card result-TOLAK">
                <h4 style="color:#721c24;margin:0 0 6px;"><i class="fas fa-times-circle"></i> Tidak Ditemukan</h4>
                <p style="margin:0;color:#721c24;">Nomor pengajuan <strong><?= htmlspecialchars($nomor) ?></strong> tidak ditemukan di database.</p>
            </div>
        <?php elseif($found['status'] != 'SIAP_AMBIL' && $found['status'] != 'SELESAI'): ?>
            <div class="result-card result-NOT_SIAP">
                <h4 style="color:#856404;margin:0 0 6px;"><i class="fas fa-exclamation-triangle"></i> Belum Siap Diambil</h4>
                <p style="margin:0;color:#856404;">Status pengajuan saat ini: <strong><?= str_replace('_',' ',$found['status']) ?></strong>. Surat belum siap diambil.</p>
                <a href="pengajuan_warga_detail.php?id=<?= $found['id_pengajuan'] ?>" class="btn-pro" style="background:#856404;color:#fff;display:inline-flex;margin-top:10px;padding:8px 18px;text-decoration:none;border-radius:7px;">
                    <i class="fas fa-eye"></i> Lihat Detail
                </a>
            </div>
        <?php elseif($found['status'] == 'SELESAI'): ?>
            <div class="result-card result-SELESAI">
                <h4 style="color:#495057;margin:0 0 6px;"><i class="fas fa-check-circle"></i> Sudah Diambil</h4>
                <p style="margin:0;color:#495057;">Surat untuk pengajuan ini sudah pernah diambil pada <?= date('d M Y H:i', strtotime($found['tgl_selesai'])) ?>.</p>
            </div>
        <?php else: ?>
            <div class="result-card">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <i class="fas fa-check-circle" style="color:#0e7050;font-size:1.8rem;"></i>
                    <div>
                        <h4 style="color:#155724;margin:0;">Surat Siap Diambil</h4>
                        <p style="margin:2px 0 0;color:#155724;font-size:.82rem;">Verifikasi data warga di bawah, lalu serahkan surat fisik</p>
                    </div>
                </div>
                
                <div style="background:#fff;border-radius:8px;padding:16px;">
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;font-size:.85rem;">
                        <div>
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">Nama Pemohon</div>
                            <div style="font-weight:700;color:var(--role-dark,#05285a);"><?= htmlspecialchars($found['nama_lengkap']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">NIK</div>
                            <div style="font-weight:700;font-family:monospace;"><?= $found['nik'] ?></div>
                        </div>
                        <div>
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">No. HP</div>
                            <div style="font-weight:700;"><?= $found['no_hp'] ?: '-' ?></div>
                        </div>
                        <div>
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">No. Pengajuan</div>
                            <div style="font-weight:700;font-family:monospace;color:var(--role-dark,#05285a);"><?= $found['nomor_pengajuan'] ?></div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">Jenis Layanan</div>
                            <div style="font-weight:700;color:var(--role-dark,#05285a);"><?= htmlspecialchars($found['nama_layanan']) ?></div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <div style="font-size:.7rem;color:#64708b;font-weight:700;text-transform:uppercase;">Dokumen yang Diupload (<?= count($dokumen) ?>)</div>
                            <div style="margin-top:5px;">
                                <?php foreach($dokumen as $dok): 
                                    $ext = strtolower(pathinfo($dok['path_file'], PATHINFO_EXTENSION));
                                    $icon = in_array($ext,['jpg','jpeg','png']) ? 'image' : 'pdf';
                                ?>
                                <a href="<?= $dok['path_file'] ?>" target="_blank" style="display:inline-block;background:#f7f8fd;border:1px solid #dde3ed;padding:5px 10px;border-radius:6px;font-size:.75rem;text-decoration:none;color:var(--role-dark,#05285a);margin:2px;">
                                    <i class="fas fa-file-<?= $icon ?>"></i> <?= htmlspecialchars($dok['nama_asli']) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="pengajuan_warga_detail.php?id=<?= $found['id_pengajuan'] ?>" class="btn-pro" style="background:var(--role,#0b5cad);color:#fff;padding:10px 20px;border-radius:7px;text-decoration:none;font-weight:700;">
                        <i class="fas fa-edit"></i> Buka Detail & Tandai Selesai
                    </a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</div>
</div>
</section>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
let html5QrcodeScanner = null;

function startScan(){
    document.getElementById('scanArea').style.display = 'block';
    html5QrcodeScanner = new Html5Qrcode("reader");
    html5QrcodeScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            html5QrcodeScanner.stop();
            location.href = 'scan_qr.php?nomor=' + encodeURIComponent(decodedText);
        }
    ).catch(function(err) {
        var msg = 'Tidak bisa akses kamera: ' + err;
        if (typeof window.sipandangShowToast === 'function') {
            window.sipandangShowToast('error', msg);
        } else if (typeof window.showToast === 'function') {
            window.showToast('error', msg);
        }
    });
}

function stopScan(){
    if(html5QrcodeScanner) {
        html5QrcodeScanner.stop();
    }
    document.getElementById('scanArea').style.display = 'none';
}
</script>

<?php include 'footer.php'; ?>