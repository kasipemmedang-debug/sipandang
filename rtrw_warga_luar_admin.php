<?php
/**
 * Rekap admin — warga berdomisili di Medang dengan identitas (KTP) non-Medang.
 * Sumber utama: Surat Keterangan Domisili dari portal RT/RW.
 */
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_rtrw/bootstrap.php';
sipandang_require_admin();

$adminS = [
    'username' => (string) ($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin'),
    'jabatan' => 'Admin',
    'nomor_rw' => '',
    'nomor_rt' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rtrw_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $aksi = (string) ($_POST['aksi'] ?? '');
    $toast = 'err';
    if ($aksi === 'hapus' && $id > 0) {
        if (mysqli_query($koneksi, 'DELETE FROM rtrw_warga_luar WHERE id=' . $id)) {
            rtrw_log($koneksi, 'WARGA_LUAR', $id, 'HAPUS_ADMIN', $adminS, 'Dihapus dari rekap non-Medang');
            $toast = 'hapus_ok';
        }
    }
    header('Location: rtrw_warga_luar_admin.php?toast=' . urlencode($toast));
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$rw = trim((string) ($_GET['rw'] ?? ''));
$rt = trim((string) ($_GET['rt'] ?? ''));
$rekap = rtrw_warga_luar_rekap($koneksi);
$rows = rtrw_warga_luar_list($koneksi, $q, $rw, $rt, 400);

$rwOpts = [];
$qRw = mysqli_query($koneksi, "SELECT DISTINCT rw FROM rtrw_warga_luar WHERE TRIM(rw)<>'' ORDER BY CAST(rw AS UNSIGNED), rw");
if ($qRw) {
    while ($r = mysqli_fetch_assoc($qRw)) {
        $rwOpts[] = (string) $r['rw'];
    }
}

include 'header.php';
include 'sidebar.php';

$toast = (string) ($_GET['toast'] ?? '');
?>
<style>
.wlu-wrap{max-width:1280px;margin:20px auto;padding:0 18px 60px;font-family:'Plus Jakarta Sans',sans-serif}
.wlu-hd{background:#fff;padding:16px 18px;border-radius:14px;border:1px solid #e3e8f3;box-shadow:0 3px 14px rgba(10,22,40,.08);margin-bottom:16px}
.wlu-hd h1{margin:0;font-size:1.08rem;color:var(--role-dark,#05285a)}
.wlu-hd p{margin:6px 0 0;font-size:.78rem;color:#6b7c93;line-height:1.4}
.wlu-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
@media(max-width:700px){.wlu-stats{grid-template-columns:1fr}}
.wlu-stat{background:#fff;border:1px solid #e3e8f3;border-radius:12px;padding:14px 16px}
.wlu-stat b{display:block;font-size:1.35rem;color:var(--role-dark,#05285a)}
.wlu-stat span{font-size:.72rem;color:#566583}
.wlu-filt{background:#fff;border:1px solid #e3e8f3;border-radius:12px;padding:12px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:end}
.wlu-filt label{display:block;font-size:.68rem;color:#566583;margin-bottom:3px}
.wlu-filt input,.wlu-filt select{height:38px;border:1px solid #d0dbea;border-radius:9px;padding:0 10px;font-size:.84rem;min-width:120px}
.wlu-filt .grow{flex:1;min-width:180px}
.wlu-filt .grow input{width:100%}
.wlu-btn{height:38px;border:none;border-radius:9px;padding:0 14px;background:var(--role,#0b5cad);color:#fff;font-weight:600;cursor:pointer;font-size:.82rem}
.wlu-btn.ghost{background:#eef4fb;color:var(--role-dark,#05285a)}
.wlu-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e3e8f3;border-radius:12px;overflow:hidden}
.wlu-table th{background:#f4f8fc;color:#5a6f89;font-size:.68rem;text-transform:uppercase;letter-spacing:.03em;padding:10px 12px;text-align:left;border-bottom:1px solid #e3ebf5}
.wlu-table td{padding:11px 12px;border-bottom:1px solid #eef2f7;font-size:.82rem;color:var(--role-dark,#05285a);vertical-align:top}
.wlu-table tr:last-child td{border-bottom:0}
.wlu-meta{font-size:.7rem;color:#566583;margin-top:3px}
.wlu-empty{padding:36px;text-align:center;color:#566583;font-size:.85rem;background:#fff;border:1px solid #e3e8f3;border-radius:12px}
.wlu-toast{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:.82rem}
.wlu-del{border:none;background:#fef2f2;color:#b91c1c;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:.75rem}
</style>

<div class="content-wrapper">
<div class="wlu-wrap">
  <div class="wlu-hd">
    <h1><i class="fas fa-id-card-alt" style="color:var(--role,#0b5cad);margin-right:6px"></i> Rekap Warga Non-Identitas Medang</h1>
    <p>Daftar orang yang berdomisili di wilayah RT/RW Medang tetapi NIK/identitasnya tidak terdaftar sebagai penduduk Kelurahan Medang. Otomatis terisi saat RT membuat <strong>Surat Keterangan Domisili</strong>.</p>
  </div>

  <?php if ($toast === 'hapus_ok'): ?>
  <div class="wlu-toast">Data berhasil dihapus dari rekap.</div>
  <?php elseif ($toast === 'err'): ?>
  <div class="wlu-toast" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">Aksi gagal. Coba lagi.</div>
  <?php endif; ?>

  <div class="wlu-stats">
    <div class="wlu-stat"><b><?= (int) $rekap['total'] ?></b><span>Total tercatat</span></div>
    <div class="wlu-stat"><b><?= (int) $rekap['wilayah'] ?></b><span>Wilayah RT/RW</span></div>
    <div class="wlu-stat"><b><?= (int) $rekap['bulan_ini'] ?></b><span>Baru bulan ini</span></div>
  </div>

  <form class="wlu-filt" method="get">
    <div class="grow">
      <label>Cari NIK / nama / alamat</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ketik kata kunci…">
    </div>
    <div>
      <label>RW</label>
      <select name="rw">
        <option value="">Semua</option>
        <?php foreach ($rwOpts as $opt): ?>
        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"<?= $rw === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>RT</label>
      <input type="text" name="rt" value="<?= htmlspecialchars($rt, ENT_QUOTES, 'UTF-8') ?>" placeholder="mis. 003" style="width:90px">
    </div>
    <button type="submit" class="wlu-btn"><i class="fas fa-search"></i> Filter</button>
    <a href="rtrw_warga_luar_admin.php" class="wlu-btn ghost">Reset</a>
  </form>

  <?php if (!$rows): ?>
  <div class="wlu-empty">Belum ada data warga non-identitas Medang.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="wlu-table">
    <thead>
      <tr>
        <th>Nama / NIK</th>
        <th>Wilayah</th>
        <th>Alamat KTP</th>
        <th>Domisili saat ini</th>
        <th>Dicatat</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars((string) ($r['nama_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
          <div class="wlu-meta">
            NIK: <?= ($r['nik'] ?? '') !== '' ? htmlspecialchars((string) $r['nik'], ENT_QUOTES, 'UTF-8') : '—' ?>
            <?php if (!empty($r['jenis_kelamin'])): ?> · <?= htmlspecialchars((string) $r['jenis_kelamin'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            <?php if (!empty($r['pekerjaan'])): ?> · <?= htmlspecialchars((string) $r['pekerjaan'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
          </div>
        </td>
        <td>RT <?= htmlspecialchars((string) ($r['rt'] ?? ''), ENT_QUOTES, 'UTF-8') ?> / RW <?= htmlspecialchars((string) ($r['rw'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= nl2br(htmlspecialchars((string) ($r['alamat_ktp'] ?? '—'), ENT_QUOTES, 'UTF-8')) ?></td>
        <td><?= nl2br(htmlspecialchars((string) ($r['alamat_domisili'] ?? '—'), ENT_QUOTES, 'UTF-8')) ?></td>
        <td>
          <div class="wlu-meta"><?= htmlspecialchars((string) ($r['updated_at'] ?? $r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="wlu-meta"><?= htmlspecialchars((string) ($r['dicatat_oleh'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($r['sumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
          <?php if (!empty($r['id_surat'])): ?>
          <div class="wlu-meta">Surat #<?= (int) $r['id_surat'] ?></div>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" onsubmit="return confirm('Hapus dari rekap?')">
            <?= rtrw_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="aksi" value="hapus">
            <button type="submit" class="wlu-del"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
</div>
<?php include 'footer.php'; ?>
