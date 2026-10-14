<?php
require_once __DIR__ . '/inc_rtrw/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? ''));
$sur = $token !== '' ? rtrw_surat_by_token($koneksi, $token) : null;

$bulan_id = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$no_gabung = '';
$keperluan_txt = '';
if ($sur) {
    $rt_pad = rtrw_pad_rt((string) ($sur['rt'] ?? ''));
    $rw_pad = rtrw_pad_rw((string) ($sur['rw'] ?? ''));
    $tgl_surat = (!empty($sur['tanggal_surat']) && $sur['tanggal_surat'] !== '0000-00-00') ? $sur['tanggal_surat'] : (string) ($sur['created_at'] ?? 'now');
    $ts = strtotime($tgl_surat) ?: time();
    $roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    $no_urut = (int) ($sur['no_urut_rt'] ?? 0) ?: 1;
    $no_gabung = sprintf('%03d', $no_urut) . '/SP/RT' . $rt_pad . '/RW' . $rw_pad . '/' . $roman[(int) date('n', $ts)] . '/' . date('Y', $ts);

    $kep_arr = array_values(array_filter(array_map('trim', explode("\n", (string) ($sur['keperluan'] ?? '')))));
    $keperluan_txt = implode(', ', $kep_arr);
}
?><!DOCTYPE html>
<html class="sp-role-warga" lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verifikasi Surat Pengantar — SIPANDANG</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--navy:var(--role-dark,#05285a);--ok:#166534;--wait:#b45309;--bad:#b91c1c;--muted:#64748b}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:480px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(10,22,40,.12);overflow:hidden}
.head{background:linear-gradient(135deg,var(--navy),var(--role-ink,#1e3a5f));color:#fff;padding:24px;text-align:center}
.head h1{font-size:1rem;margin:8px 0 0}
.badge{display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;font-weight:800;font-size:.85rem;margin:20px auto;width:calc(100% - 40px);justify-content:center;text-align:center}
.badge.ok{background:#ecfdf5;color:var(--ok);border:1px solid #bbf7d0}
.badge.wait{background:#fffbeb;color:var(--wait);border:1px solid #fde68a}
.badge.bad{background:#fef2f2;color:var(--bad);border:1px solid #fecaca}
.body{padding:0 24px 24px}
.row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:.82rem}
.row span:first-child{color:var(--muted);font-weight:600;white-space:nowrap}
.row span:last-child{text-align:right}
.note{margin-top:16px;padding:12px;background:#f8fafc;border-radius:10px;font-size:.75rem;color:var(--muted);line-height:1.6}
</style>
<link rel="stylesheet" href="assets/sipandang-modern.css?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-modern.css') ?>">
    <script src="assets/sipandang-contrast.js?v=<?= (int) @filemtime(__DIR__ . '/assets/sipandang-contrast.js') ?>" defer></script>
</head>
<body class="sp-ui sp-role-warga">
<div class="card">
  <div class="head">
    <i class="fas fa-qrcode" style="font-size:1.5rem;opacity:.8"></i>
    <h1>Verifikasi Surat Pengantar RT/RW</h1>
    <p style="font-size:.75rem;opacity:.7;margin-top:6px">SIPANDANG · Kelurahan Medang</p>
  </div>
  <?php if (!$sur): ?>
  <div class="badge bad"><i class="fas fa-times-circle"></i> Dokumen Tidak Ditemukan</div>
  <div class="body">
    <p style="font-size:.85rem;color:var(--muted);text-align:center">Token QR tidak terdaftar atau tidak valid.</p>
    <div class="note">Surat pengantar <strong>kertas manual</strong> dari RT/RW tetap dapat diproses di kelurahan dengan verifikasi fisik.</div>
  </div>
  <?php else:
    $st = (string) ($sur['status'] ?? '');
    if ($st === 'sah') {
        $cls = 'ok'; $ico = 'fa-check-circle'; $lbl = 'SURAT SAH — RT & RW';
    } elseif ($st === 'menunggu_rw') {
        $cls = 'wait'; $ico = 'fa-clock'; $lbl = 'Menunggu Persetujuan RW';
    } elseif ($st === 'ditolak') {
        $cls = 'bad'; $ico = 'fa-ban'; $lbl = 'Surat Ditolak RW';
    } else {
        $cls = 'bad'; $ico = 'fa-exclamation-triangle'; $lbl = rtrw_status_label($st);
    }
  ?>
  <div class="badge <?= $cls ?>"><i class="fas <?= $ico ?>"></i> <?= htmlspecialchars($lbl) ?></div>
  <div class="body">
    <div class="row"><span>Pemohon</span><span><strong><?= htmlspecialchars(strtoupper($sur['nama_pemohon'] ?? '')) ?></strong></span></div>
    <div class="row"><span>No. Surat</span><span><?= htmlspecialchars($no_gabung ?: ($sur['no_surat_rt'] ?? '—')) ?></span></div>
    <?php if ($keperluan_txt !== ''): ?>
    <div class="row"><span>Keperluan</span><span style="text-align:right;max-width:60%"><?= htmlspecialchars($keperluan_txt) ?></span></div>
    <?php endif; ?>
    <div class="row"><span>Wilayah</span><span>RT.<?= htmlspecialchars(rtrw_pad_rt($sur['rt'] ?? '')) ?> / RW.<?= htmlspecialchars(rtrw_pad_rw($sur['rw'] ?? '')) ?></span></div>
    <div class="row"><span>Ditandatangani RT</span><span><?= !empty($sur['ttd_rt_at']) ? date('d/m/Y H:i', strtotime((string)$sur['ttd_rt_at'])) : '—' ?></span></div>
    <div class="row"><span>Disahkan RW</span><span><?= !empty($sur['ttd_rw_at']) ? date('d/m/Y H:i', strtotime((string)$sur['ttd_rw_at'])) : '—' ?></span></div>
    <?php if ($st === 'ditolak' && !empty($sur['catatan_rw'])): ?>
    <div class="row"><span>Catatan RW</span><span style="text-align:right;max-width:60%"><?= htmlspecialchars($sur['catatan_rw']) ?></span></div>
    <?php endif; ?>
    <div class="note">
      <?php if ($st === 'sah'): ?>
      Dokumen digital ini telah ditandatangani RT dan disetujui RW. Petugas kelurahan dapat mencocokkan dengan identitas pemohon.
      <?php elseif ($st === 'menunggu_rw'): ?>
      Surat belum disetujui Ketua RW — <strong>belum sah</strong> untuk keperluan resmi.
      <?php else: ?>
      Surat tidak valid untuk diproses sebagai pengantar digital.
      <?php endif; ?>
      <br><br>Surat pengantar RT/RW bukan surat resmi kelurahan. Surat kertas manual tetap valid.
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
