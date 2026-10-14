<?php
include 'koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_rtrw/bootstrap.php';
sipandang_require_admin();

$f_cari = trim((string) ($_GET['cari'] ?? ''));
$f_modul = trim((string) ($_GET['modul'] ?? ''));
$f_dari = trim((string) ($_GET['dari'] ?? ''));
$f_sampai = trim((string) ($_GET['sampai'] ?? ''));

$where = '1=1';
if ($f_cari !== '') {
    $ce = mysqli_real_escape_string($koneksi, $f_cari);
    $where .= " AND (oleh LIKE '%$ce%' OR keterangan LIKE '%$ce%' OR aksi LIKE '%$ce%')";
}
if (in_array($f_modul, ['SURAT', 'PENDATAAN', 'PINDAH'], true)) {
    $where .= " AND modul='" . $f_modul . "'";
}
if ($f_dari !== '') {
    $where .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($koneksi, $f_dari) . "'";
}
if ($f_sampai !== '') {
    $where .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($koneksi, $f_sampai) . "'";
}

$rows = [];
$q = mysqli_query($koneksi, "SELECT * FROM rtrw_log WHERE $where ORDER BY created_at DESC LIMIT 500");
while ($q && $r = mysqli_fetch_assoc($q)) {
    $rows[] = $r;
}

$modulList = mysqli_query($koneksi, "SELECT modul, COUNT(*) c FROM rtrw_log GROUP BY modul");
$modulCount = [];
while ($modulList && $m = mysqli_fetch_assoc($modulList)) {
    $modulCount[$m['modul']] = (int) $m['c'];
}

include 'header.php';
include 'sidebar.php';

function rtrw_log_badge(string $aksi): string
{
    $a = strtoupper($aksi);
    if (strpos($a, 'SETUJU') !== false || strpos($a, 'AJU') !== false || strpos($a, 'BUAT') !== false) return 'b-ok';
    if (strpos($a, 'TOLAK') !== false || strpos($a, 'HAPUS') !== false) return 'b-no';
    return 'b-info';
}
?>
<style>
.lg-wrap{max-width:1180px;margin:20px auto;padding:0 18px 60px;font-family:'Plus Jakarta Sans',sans-serif}
.lg-hd{background:linear-gradient(135deg,var(--role-dark,#05285a),var(--role,#0b5cad));padding:20px 26px;border-radius:14px;color:#fff;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.lg-hd h1{font-size:1.05rem;margin:0}
.lg-filter{background:#fff;border-radius:12px;padding:12px 14px;box-shadow:0 4px 18px rgba(10,22,40,.07);display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.fi{border:1.5px solid #dde3ed;border-radius:8px;padding:8px 11px;font-size:.82rem;font-family:inherit;outline:none;color:#0a1628}
.fi:focus{border-color:var(--role-ink,#1e3a5f)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-weight:700;border:none;cursor:pointer;font-size:.78rem;text-decoration:none;font-family:inherit}
.bn{background:var(--role-ink,#1e3a5f);color:#fff}.br{background:#eef1f6;color:#0a1628}.bgld{background:var(--role,#0b5cad);color:#fff}
.card{background:#fff;border-radius:12px;border:1px solid #dde3ed;overflow:auto;box-shadow:0 4px 18px rgba(10,22,40,.06)}
.tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.tbl thead th{background:#f8f9fe;color:#566583;border-bottom:1px solid #edf0f8;padding:10px 12px;text-align:left;font-size:.64rem;text-transform:uppercase;letter-spacing:.4px;position:sticky;top:0}
.tbl tbody td{padding:9px 12px;border-bottom:1px solid #f0f2f5;vertical-align:top}
.tbl tbody tr:hover{background:#f8fafc}
.badge{display:inline-block;padding:3px 9px;border-radius:10px;font-size:.62rem;font-weight:800}
.b-ok{background:#dcfce7;color:#166534}.b-no{background:#fee2e2;color:#991b1b}.b-info{background:var(--role-soft,#e0f2fe);color:var(--role-ink)}
.b-mod{background:var(--role,#0b5cad);color:#fff}
.empty{text-align:center;color:#64708b;padding:30px;font-size:.85rem}
</style>

<div class="content-wrapper"><div class="lg-wrap">
<div class="lg-hd">
  <h1><i class="fas fa-history" style="color:var(--role,#0b5cad);margin-right:8px"></i> Log Aktivitas Portal RT/RW</h1>
  <a href="rtrw_pendataan_admin.php" class="btn bgld"><i class="fas fa-clipboard-list"></i> Antrean & Arsip</a>
</div>

<form method="get" class="lg-filter">
  <input type="text" name="cari" class="fi" style="flex:1;min-width:200px" placeholder="Cari pelaku / aksi / keterangan…" value="<?= htmlspecialchars($f_cari) ?>">
  <select name="modul" class="fi">
    <option value="">Semua Modul</option>
    <option value="SURAT" <?= $f_modul==='SURAT'?'selected':'' ?>>Surat<?= isset($modulCount['SURAT'])?' ('.$modulCount['SURAT'].')':'' ?></option>
    <option value="PENDATAAN" <?= $f_modul==='PENDATAAN'?'selected':'' ?>>Pendataan<?= isset($modulCount['PENDATAAN'])?' ('.$modulCount['PENDATAAN'].')':'' ?></option>
    <option value="PINDAH" <?= $f_modul==='PINDAH'?'selected':'' ?>>Pindah<?= isset($modulCount['PINDAH'])?' ('.$modulCount['PINDAH'].')':'' ?></option>
  </select>
  <input type="date" name="dari" class="fi" value="<?= htmlspecialchars($f_dari) ?>" title="Dari tanggal">
  <input type="date" name="sampai" class="fi" value="<?= htmlspecialchars($f_sampai) ?>" title="Sampai tanggal">
  <button class="btn bn"><i class="fas fa-search"></i> Cari</button>
  <?php if ($f_cari!==''||$f_modul!==''||$f_dari!==''||$f_sampai!==''): ?><a href="rtrw_log.php" class="btn br">Reset</a><?php endif; ?>
</form>

<div class="card">
<table class="tbl">
<thead><tr><th>Waktu</th><th>Modul</th><th>Aksi</th><th>Oleh</th><th>Wilayah</th><th>Keterangan</th></tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="6" class="empty">Tidak ada log sesuai filter.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td style="white-space:nowrap;color:#64748b"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? 'now'))) ?></td>
<td><span class="badge b-mod"><?= htmlspecialchars($r['modul'] ?? '') ?></span></td>
<td><span class="badge <?= rtrw_log_badge((string)($r['aksi'] ?? '')) ?>"><?= htmlspecialchars($r['aksi'] ?? '') ?></span></td>
<td><?= htmlspecialchars($r['oleh'] ?? '') ?><br><span style="font-size:.68rem;color:#64708b"><?= htmlspecialchars($r['jabatan'] ?? '') ?></span></td>
<td style="white-space:nowrap">RT.<?= htmlspecialchars(rtrw_pad_rt($r['rt'] ?? '')) ?> / RW.<?= htmlspecialchars(rtrw_pad_rw($r['rw'] ?? '')) ?></td>
<td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
</div>
<p style="margin-top:12px;font-size:.75rem;color:#64708b">Menampilkan <?= count($rows) ?> log terbaru (maks 500) · <a href="kelola_akun_rtrw.php">← Kelola akun RT/RW</a></p>
</div></div>
<?php include 'footer.php'; ?>
