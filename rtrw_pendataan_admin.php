<?php
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/inc_rtrw/bootstrap.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
sipandang_require_admin();

$admin = (string) ($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin');
$adminS = ['username' => $admin, 'jabatan' => 'Admin', 'nomor_rw' => '', 'nomor_rt' => ''];

$tab = $_GET['tab'] ?? 'pendataan';
if (!in_array($tab, ['pendataan', 'pindah', 'surat'], true)) {
    $tab = 'pendataan';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rtrw_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $modul = $_POST['modul'] ?? '';
    $aksi = $_POST['aksi'] ?? '';
    $toast = 'err';

    if ($modul === 'pendataan' && $aksi === 'approve') {
        $res = rtrw_pendataan_admin_setuju($koneksi, $id, $admin, $catatan);
        $toast = $res['ok'] ? 'pd_ok' : 'err';
    } elseif ($modul === 'pendataan' && $aksi === 'reject') {
        $toast = rtrw_pendataan_admin_tolak($koneksi, $id, $admin, $catatan) ? 'pd_no' : 'err';
    } elseif ($modul === 'pindah' && $aksi === 'approve') {
        $res = rtrw_pindah_admin_setuju($koneksi, $id, $admin, $catatan);
        $toast = $res['ok'] ? 'ph_ok' : 'err';
    } elseif ($modul === 'pindah' && $aksi === 'reject') {
        $toast = rtrw_pindah_admin_tolak($koneksi, $id, $admin, $catatan) ? 'ph_no' : 'err';
    } elseif ($aksi === 'hapus' && $id > 0) {
        $tableMap = ['pendataan' => 'rtrw_pendataan', 'pindah' => 'rtrw_pindah', 'surat' => 'rtrw_surat'];
        $logMap = ['pendataan' => 'PENDATAAN', 'pindah' => 'PINDAH', 'surat' => 'SURAT'];
        if (isset($tableMap[$modul])) {
            if (mysqli_query($koneksi, "DELETE FROM {$tableMap[$modul]} WHERE id=$id")) {
                rtrw_log($koneksi, $logMap[$modul], $id, 'HAPUS_ADMIN', $adminS, 'Dihapus admin');
                $toast = 'hapus_ok';
            }
        }
    }
    header('Location: rtrw_pendataan_admin.php?tab=' . urlencode($modul ?: $tab) . '&toast=' . urlencode($toast));
    exit;
}

$rekapPd = rtrw_pendataan_rekap($koneksi);
$rekapPh = rtrw_pindah_rekap($koneksi);
$rekapSr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT
    COUNT(*) total,
    SUM(status='menunggu_rw') menunggu_rw,
    SUM(status='sah') sah,
    SUM(status='ditolak') ditolak
    FROM rtrw_surat")) ?: ['total' => 0, 'menunggu_rw' => 0, 'sah' => 0, 'ditolak' => 0];

$q = trim((string) ($_GET['q'] ?? ''));
$qe = mysqli_real_escape_string($koneksi, $q);
$status = trim((string) ($_GET['status'] ?? ''));
$dari = trim((string) ($_GET['dari'] ?? ''));
$sampai = trim((string) ($_GET['sampai'] ?? ''));

/** Bangun WHERE status berdasarkan filter UI. */
function rpa_status_where(string $modul, string $status): string
{
    if ($status === '') return '';
    if ($status === 'ditolak') {
        if ($modul === 'pendataan') return " AND status IN ('ditolak_rw','ditolak_admin')";
        return " AND status='ditolak'";
    }
    $allowed = [
        'pendataan' => ['menunggu_rw', 'menunggu_admin', 'selesai'],
        'pindah'    => ['menunggu_rw_asal', 'menunggu_rw_tujuan', 'menunggu_admin', 'selesai'],
        'surat'     => ['menunggu_rw', 'sah'],
    ];
    if (in_array($status, $allowed[$modul] ?? [], true)) {
        return " AND status='" . $status . "'";
    }
    return '';
}

$rows = [];
$detail = null;

if ($tab === 'pendataan') {
    $where = '1=1' . rpa_status_where('pendataan', $status);
    if ($q !== '') $where .= " AND (nik LIKE '%$qe%' OR nama_lengkap LIKE '%$qe%' OR kode LIKE '%$qe%')";
    $res = mysqli_query($koneksi, "SELECT * FROM rtrw_pendataan WHERE $where ORDER BY created_at DESC LIMIT 300");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    if (isset($_GET['view'])) $detail = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM rtrw_pendataan WHERE id=' . (int) $_GET['view'] . ' LIMIT 1'));
} elseif ($tab === 'pindah') {
    $where = '1=1' . rpa_status_where('pindah', $status);
    if ($q !== '') $where .= " AND (nik LIKE '%$qe%' OR nama_lengkap LIKE '%$qe%' OR kode LIKE '%$qe%')";
    $res = mysqli_query($koneksi, "SELECT * FROM rtrw_pindah WHERE $where ORDER BY created_at DESC LIMIT 300");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    if (isset($_GET['view'])) $detail = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM rtrw_pindah WHERE id=' . (int) $_GET['view'] . ' LIMIT 1'));
} else { // surat
    $where = '1=1' . rpa_status_where('surat', $status);
    if ($q !== '') $where .= " AND (nik LIKE '%$qe%' OR nama_pemohon LIKE '%$qe%' OR no_surat_rt LIKE '%$qe%' OR no_surat_rw LIKE '%$qe%')";
    if ($dari !== '') $where .= " AND DATE(created_at) >= '" . mysqli_real_escape_string($koneksi, $dari) . "'";
    if ($sampai !== '') $where .= " AND DATE(created_at) <= '" . mysqli_real_escape_string($koneksi, $sampai) . "'";
    $res = mysqli_query($koneksi, "SELECT * FROM rtrw_surat WHERE $where ORDER BY created_at DESC LIMIT 300");
    while ($res && $r = mysqli_fetch_assoc($res)) $rows[] = $r;
    if (isset($_GET['view'])) $detail = mysqli_fetch_assoc(mysqli_query($koneksi, 'SELECT * FROM rtrw_surat WHERE id=' . (int) $_GET['view'] . ' LIMIT 1'));
}

include 'header.php';
include 'sidebar.php';

$qs = static function (array $over) use ($tab, $q, $status, $dari, $sampai): string {
    $base = ['tab' => $tab, 'q' => $q, 'status' => $status, 'dari' => $dari, 'sampai' => $sampai];
    return 'rtrw_pendataan_admin.php?' . http_build_query(array_merge($base, $over));
};
?>
<style>
.rpa-wrap{max-width:1320px;margin:20px auto;padding:0 18px 60px;font-family:'Plus Jakarta Sans',sans-serif}
.rpa-hd{background:#fff;padding:14px 16px;border-radius:14px;color:var(--role-dark,#05285a);margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border:1px solid #e3e8f3;box-shadow:0 3px 14px rgba(10,22,40,.08)}
.rpa-hd h1{font-size:1.05rem;margin:0}
.rpa-hd p{opacity:.6;font-size:.76rem;margin:5px 0 0}
.rpa-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.rpa-tab{padding:10px 18px;border-radius:10px;font-weight:700;font-size:.83rem;text-decoration:none;background:#fff;border:1.5px solid #dde3ed;color:var(--role-dark,#05285a);display:inline-flex;align-items:center;gap:7px}
.rpa-tab.active{background:var(--role-dark,#05285a);color:#fff;border-color:var(--role-dark,#05285a)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.stat{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 4px 18px rgba(10,22,40,.07);border-left:4px solid var(--role-ink,#1e3a5f)}
.stat.w{border-color:#d97706}.stat.o{border-color:#166534}.stat.n{border-color:#991b1b}
.stat b{font-size:1.5rem;display:block;color:var(--role-dark,#05285a);line-height:1}
.stat span{font-size:.64rem;color:#64708b;font-weight:800;text-transform:uppercase;letter-spacing:.4px}
.filter-bar{background:#fff;border-radius:12px;padding:12px 14px;box-shadow:0 4px 18px rgba(10,22,40,.07);display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.fpill{padding:7px 13px;border-radius:999px;font-size:.74rem;font-weight:700;text-decoration:none;border:1.5px solid #dde3ed;background:#fff;color:#64748b}
.fpill.on{background:var(--role-ink,#1e3a5f);border-color:var(--role-ink,#1e3a5f);color:#fff}
.fsearch{flex:1;min-width:200px;display:flex;gap:8px;align-items:center;margin-left:auto}
.fi{border:1.5px solid #dde3ed;border-radius:8px;padding:8px 11px;font-size:.82rem;font-family:inherit;outline:none;color:#0a1628}
.fi:focus{border-color:var(--role-ink,#1e3a5f)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 13px;border-radius:8px;font-weight:700;border:none;cursor:pointer;font-size:.76rem;text-decoration:none;font-family:inherit}
.bn{background:var(--role,#0b5cad);color:#fff}.bg{background:#166534;color:#fff}.br{background:#eef1f6;color:var(--role-dark,#05285a)}.bd{background:#fee2e2;color:#991b1b}.bgld{background:var(--role-soft,#e8f2fd);color:var(--role-ink,#08428a);border:1px solid var(--role-line,#c6dcf5)}
.layout{display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start}
@media(max-width:1050px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 18px rgba(10,22,40,.07);padding:16px}
.tbl{width:100%;border-collapse:collapse;font-size:.8rem}
.tbl th,.tbl td{padding:9px 11px;border-bottom:1px solid #edf1f7;text-align:left;vertical-align:middle}
.tbl th{background:#f8f9fe;color:#566583;border-bottom:1px solid #edf0f8;font-size:.64rem;text-transform:uppercase;letter-spacing:.4px;position:sticky;top:0}
.tbl tbody tr:hover{background:#f8fafc}
.tbl code{font-size:.72rem;color:var(--role-ink,#1e3a5f)}
.badge{display:inline-block;padding:3px 9px;border-radius:10px;font-size:.62rem;font-weight:800}
.b-wait{background:#fef3c7;color:#92400e}.b-ok{background:#dcfce7;color:#166534}.b-no{background:#fee2e2;color:#991b1b}.b-info{background:var(--role-soft,#e0f2fe);color:var(--role-ink)}
.toast{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:700;font-size:.85rem}
.toast.ok{background:#dcfce7;color:#166534}.toast.err{background:#fee2e2;color:#991b1b}
.detail-line{display:grid;grid-template-columns:130px 1fr;gap:8px;font-size:.82rem;margin-bottom:6px;padding-bottom:6px;border-bottom:1px dashed #eef1f7}
.detail-line span{color:#64708b;font-weight:700}
.act-row{display:flex;gap:5px;flex-wrap:wrap}
.empty-row{text-align:center;color:#64708b;padding:30px;font-size:.85rem}
.txt-area{width:100%;padding:10px;border:1px solid #dde3ed;border-radius:8px;margin-bottom:10px;font-family:inherit;font-size:.82rem}
</style>
<style>
/* BPJS tone — header & tab */
.rpa-hd{background:#fff!important;color:var(--role-dark,#05285a)!important;border:1px solid #e3e8f3!important;box-shadow:0 3px 14px rgba(10,22,40,.08)!important;padding:14px 16px!important;margin-bottom:12px!important;}
.rpa-hd h1{font-size:1.02rem!important;font-weight:800!important;color:var(--role-dark,#05285a)!important;}
.rpa-hd p{color:#566583!important;opacity:1!important;}
.rpa-tab.active{background:var(--role-dark,#05285a)!important;color:#fff!important;border-color:var(--role-dark,#05285a)!important;}
.btn.bgld{background:var(--role,#0b5cad)!important;color:#fff!important;}
.tbl th{background:#f8f9fe!important;color:#566583!important;}
.btn.bn{background:var(--role,#0b5cad)!important;color:#fff!important;}
</style>

<div class="content-wrapper"><div class="rpa-wrap">
<div class="rpa-hd">
  <div>
    <h1><i class="fas fa-clipboard-list" style="color:var(--role,#0b5cad);margin-right:8px"></i> Antrean & Arsip RT/RW</h1>
    <p>Pendataan · Pindah Domisili · Surat Pengantar — kelola, sinkron & arsip</p>
  </div>
  <a href="rtrw_log.php" class="btn bgld"><i class="fas fa-history"></i> Log Portal</a>
</div>

<div class="rpa-tabs">
  <a href="rtrw_pendataan_admin.php?tab=pendataan" class="rpa-tab <?= $tab === 'pendataan' ? 'active' : '' ?>"><i class="fas fa-user-edit"></i> Pendataan <?php if ($rekapPd['menunggu_admin'] > 0): ?><span class="badge b-wait"><?= (int)$rekapPd['menunggu_admin'] ?></span><?php endif; ?></a>
  <a href="rtrw_pendataan_admin.php?tab=pindah" class="rpa-tab <?= $tab === 'pindah' ? 'active' : '' ?>"><i class="fas fa-exchange-alt"></i> Pindah <?php if ($rekapPh['menunggu_admin'] > 0): ?><span class="badge b-wait"><?= (int)$rekapPh['menunggu_admin'] ?></span><?php endif; ?></a>
  <a href="rtrw_pendataan_admin.php?tab=surat" class="rpa-tab <?= $tab === 'surat' ? 'active' : '' ?>"><i class="fas fa-file-signature"></i> Surat Pengantar</a>
</div>

<?php
// ===== STATS per tab =====
if ($tab === 'pendataan'):
?>
<div class="stats">
  <div class="stat w"><b><?= (int)$rekapPd['menunggu_rw'] ?></b><span>Menunggu RW</span></div>
  <div class="stat w"><b><?= (int)$rekapPd['menunggu_admin'] ?></b><span>Menunggu Admin</span></div>
  <div class="stat o"><b><?= (int)$rekapPd['selesai'] ?></b><span>Selesai</span></div>
  <div class="stat n"><b><?= (int)$rekapPd['ditolak'] ?></b><span>Ditolak</span></div>
</div>
<?php elseif ($tab === 'pindah'): ?>
<div class="stats">
  <div class="stat w"><b><?= (int)$rekapPh['menunggu_rw_asal'] + (int)$rekapPh['menunggu_rw_tujuan'] ?></b><span>Antrean RW</span></div>
  <div class="stat w"><b><?= (int)$rekapPh['menunggu_admin'] ?></b><span>Menunggu Admin</span></div>
  <div class="stat o"><b><?= (int)$rekapPh['selesai'] ?></b><span>Selesai</span></div>
  <div class="stat n"><b><?= (int)$rekapPh['ditolak'] ?></b><span>Ditolak</span></div>
</div>
<?php else: ?>
<div class="stats">
  <div class="stat"><b><?= (int)$rekapSr['total'] ?></b><span>Total Surat</span></div>
  <div class="stat w"><b><?= (int)$rekapSr['menunggu_rw'] ?></b><span>Menunggu RW</span></div>
  <div class="stat o"><b><?= (int)$rekapSr['sah'] ?></b><span>Sah</span></div>
  <div class="stat n"><b><?= (int)$rekapSr['ditolak'] ?></b><span>Ditolak</span></div>
</div>
<?php endif; ?>

<?php
// ===== FILTER BAR =====
$pills = $tab === 'surat'
    ? ['' => 'Semua', 'menunggu_rw' => 'Menunggu RW', 'sah' => 'Sah', 'ditolak' => 'Ditolak']
    : ['' => 'Semua', 'menunggu_admin' => 'Menunggu Admin', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
?>
<form method="get" class="filter-bar">
  <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
  <?php foreach ($pills as $val => $lbl): ?>
  <a href="<?= htmlspecialchars($qs(['status' => $val, 'view' => null])) ?>" class="fpill <?= $status === $val ? 'on' : '' ?>"><?= htmlspecialchars($lbl) ?></a>
  <?php endforeach; ?>
  <div class="fsearch">
    <?php if ($tab === 'surat'): ?>
    <input type="date" name="dari" class="fi" value="<?= htmlspecialchars($dari) ?>" title="Dari tanggal">
    <input type="date" name="sampai" class="fi" value="<?= htmlspecialchars($sampai) ?>" title="Sampai tanggal">
    <?php endif; ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="text" name="q" class="fi" style="flex:1" placeholder="Cari NIK / nama / nomor / kode…" value="<?= htmlspecialchars($q) ?>">
    <button class="btn bn"><i class="fas fa-search"></i> Cari</button>
    <?php if ($q !== '' || $dari !== '' || $sampai !== ''): ?><a href="rtrw_pendataan_admin.php?tab=<?= $tab ?>" class="btn br">Reset</a><?php endif; ?>
  </div>
</form>

<div class="layout">
<div class="card" style="overflow:auto">
  <table class="tbl">
    <?php if ($tab === 'pendataan'): ?>
    <thead><tr><th>Kode</th><th>NIK / Nama</th><th>Jenis</th><th>Wilayah</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty-row">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $st = (string)($r['status'] ?? ''); $bc = $st==='selesai'?'b-ok':(strpos($st,'ditolak')!==false?'b-no':'b-wait'); ?>
    <tr>
      <td><code><?= htmlspecialchars($r['kode'] ?? '') ?></code></td>
      <td><?= htmlspecialchars($r['nik'] ?? '') ?><br><strong><?= htmlspecialchars(strtoupper($r['nama_lengkap'] ?? '')) ?></strong></td>
      <td><span class="badge b-info"><?= ($r['jenis'] ?? '')==='ubah'?'Ubah':'Baru' ?></span></td>
      <td>RT.<?= htmlspecialchars(rtrw_pad_rt($r['rt'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($r['rw'] ?? '')) ?></td>
      <td><span class="badge <?= $bc ?>"><?= htmlspecialchars(rtrw_pendataan_status_label($st)) ?></span></td>
      <td style="font-size:.72rem;color:#64748b"><?= htmlspecialchars(date('d/m/y H:i', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
      <td><div class="act-row">
        <a href="<?= htmlspecialchars($qs(['view' => (int)$r['id']])) ?>" class="btn br">Detail</a>
        <form method="post" onsubmit="return confirm('Hapus data ini permanen?')" style="display:inline">
          <?= rtrw_csrf_field() ?><input type="hidden" name="modul" value="pendataan"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button name="aksi" value="hapus" class="btn bd"><i class="fas fa-trash"></i></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>

    <?php elseif ($tab === 'pindah'): ?>
    <thead><tr><th>Kode</th><th>NIK / Nama</th><th>Perpindahan</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty-row">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $st = (string)($r['status'] ?? ''); $bc = $st==='selesai'?'b-ok':($st==='ditolak'?'b-no':'b-wait'); ?>
    <tr>
      <td><code><?= htmlspecialchars($r['kode'] ?? '') ?></code></td>
      <td><?= htmlspecialchars($r['nik'] ?? '') ?><br><strong><?= htmlspecialchars(strtoupper($r['nama_lengkap'] ?? '')) ?></strong></td>
      <td style="font-size:.74rem">RT.<?= htmlspecialchars(rtrw_pad_rt($r['rt_asal'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($r['rw_asal'] ?? '')) ?> → RT.<?= htmlspecialchars(rtrw_pad_rt($r['rt_tujuan'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($r['rw_tujuan'] ?? '')) ?></td>
      <td><span class="badge <?= $bc ?>"><?= htmlspecialchars(rtrw_pindah_status_label($st)) ?></span></td>
      <td style="font-size:.72rem;color:#64748b"><?= htmlspecialchars(date('d/m/y H:i', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
      <td><div class="act-row">
        <a href="<?= htmlspecialchars($qs(['view' => (int)$r['id']])) ?>" class="btn br">Detail</a>
        <form method="post" onsubmit="return confirm('Hapus data ini permanen?')" style="display:inline">
          <?= rtrw_csrf_field() ?><input type="hidden" name="modul" value="pindah"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button name="aksi" value="hapus" class="btn bd"><i class="fas fa-trash"></i></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>

    <?php else: ?>
    <thead><tr><th>No. Surat</th><th>Pemohon</th><th>Wilayah</th><th>Keperluan</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty-row">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $st = (string)($r['status'] ?? ''); $bc = $st==='sah'?'b-ok':($st==='ditolak'?'b-no':'b-wait'); $kep = trim(str_replace("\n",', ',(string)($r['keperluan'] ?? ''))); ?>
    <tr>
      <td><code><?= htmlspecialchars($r['no_surat_rt'] ?? '-') ?></code><?php if (!empty($r['no_surat_rw'])): ?><br><code style="color:#166534"><?= htmlspecialchars($r['no_surat_rw']) ?></code><?php endif; ?></td>
      <td><?= htmlspecialchars($r['nik'] ?? '') ?><br><strong><?= htmlspecialchars(strtoupper($r['nama_pemohon'] ?? '')) ?></strong></td>
      <td>RT.<?= htmlspecialchars(rtrw_pad_rt($r['rt'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($r['rw'] ?? '')) ?></td>
      <td style="font-size:.74rem;max-width:180px"><?= htmlspecialchars(mb_strimwidth($kep, 0, 60, '…')) ?></td>
      <td><span class="badge <?= $bc ?>"><?= htmlspecialchars(rtrw_status_label($st)) ?></span></td>
      <td style="font-size:.72rem;color:#64748b"><?= htmlspecialchars(date('d/m/y H:i', strtotime((string)($r['created_at'] ?? 'now')))) ?></td>
      <td><div class="act-row">
        <a href="<?= htmlspecialchars($qs(['view' => (int)$r['id']])) ?>" class="btn br">Detail</a>
        <?php if (!empty($r['token'])): ?><a href="verifikasi_pengantar.php?t=<?= urlencode((string)$r['token']) ?>" target="_blank" class="btn bn"><i class="fas fa-qrcode"></i></a><?php endif; ?>
        <form method="post" onsubmit="return confirm('Hapus surat ini permanen?')" style="display:inline">
          <?= rtrw_csrf_field() ?><input type="hidden" name="modul" value="surat"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button name="aksi" value="hapus" class="btn bd"><i class="fas fa-trash"></i></button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php endif; ?>
  </table>
</div>

<!-- ===== PANEL DETAIL ===== -->
<div class="card">
<?php if (!$detail): ?>
  <p class="empty-row" style="padding:40px 10px"><i class="fas fa-hand-pointer" style="font-size:1.6rem;display:block;margin-bottom:8px;opacity:.3"></i>Pilih <strong>Detail</strong> untuk melihat & memproses.</p>
<?php elseif ($tab === 'pendataan'): ?>
  <h3 style="font-size:.9rem;margin-bottom:12px">Detail <?= htmlspecialchars($detail['kode'] ?? '') ?></h3>
  <?php
  $fields = ['nik'=>'NIK','no_kk'=>'No KK','nama_lengkap'=>'Nama','tempat_lahir'=>'Tempat Lahir','tgl_lahir'=>'Tgl Lahir','jenis_kelamin'=>'JK','agama'=>'Agama','status_perkawinan'=>'Kawin','shdk'=>'SHDK','pendidikan'=>'Pendidikan','pekerjaan'=>'Pekerjaan','alamat'=>'Alamat','rt'=>'RT','rw'=>'RW'];
  foreach ($fields as $k => $lbl): ?>
  <div class="detail-line"><span><?= $lbl ?></span><div><?= htmlspecialchars((string)($detail[$k] ?? '—')) ?></div></div>
  <?php endforeach; ?>
  <?php if (!empty($detail['catatan_rw'])): ?><div class="detail-line"><span>Catatan RW</span><div><?= htmlspecialchars($detail['catatan_rw']) ?></div></div><?php endif; ?>
  <?php if (!empty($detail['catatan_admin'])): ?><div class="detail-line"><span>Catatan Admin</span><div><?= htmlspecialchars($detail['catatan_admin']) ?></div></div><?php endif; ?>
  <div class="detail-line"><span>Status</span><div><strong><?= htmlspecialchars(rtrw_pendataan_status_label((string)($detail['status'] ?? ''))) ?></strong></div></div>
  <?php if (($detail['status'] ?? '') === 'menunggu_admin'): ?>
  <form method="post" style="margin-top:14px" onsubmit="return confirm('Sinkronkan ke tbl_penduduk?')">
    <?= rtrw_csrf_field() ?><input type="hidden" name="modul" value="pendataan"><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
    <textarea name="catatan" class="txt-area" placeholder="Catatan admin (opsional)"></textarea>
    <div class="act-row">
      <button name="aksi" value="approve" class="btn bg"><i class="fas fa-check"></i> Setujui & Sinkron</button>
      <button name="aksi" value="reject" class="btn bd" onclick="return confirm('Tolak pengajuan?')"><i class="fas fa-times"></i> Tolak</button>
    </div>
  </form>
  <?php endif; ?>
<?php elseif ($tab === 'pindah'): ?>
  <h3 style="font-size:.9rem;margin-bottom:12px">Detail <?= htmlspecialchars($detail['kode'] ?? '') ?></h3>
  <div class="detail-line"><span>NIK</span><div><?= htmlspecialchars($detail['nik'] ?? '') ?></div></div>
  <div class="detail-line"><span>Nama</span><div><?= htmlspecialchars(strtoupper($detail['nama_lengkap'] ?? '')) ?></div></div>
  <div class="detail-line"><span>Asal</span><div>RT.<?= htmlspecialchars(rtrw_pad_rt($detail['rt_asal'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($detail['rw_asal'] ?? '')) ?></div></div>
  <div class="detail-line"><span>Tujuan</span><div>RT.<?= htmlspecialchars(rtrw_pad_rt($detail['rt_tujuan'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($detail['rw_tujuan'] ?? '')) ?></div></div>
  <div class="detail-line"><span>Alamat Tujuan</span><div><?= htmlspecialchars($detail['alamat_tujuan'] ?? '—') ?></div></div>
  <div class="detail-line"><span>Alasan</span><div><?= htmlspecialchars($detail['alasan'] ?? '') ?></div></div>
  <div class="detail-line"><span>Status</span><div><strong><?= htmlspecialchars(rtrw_pindah_status_label((string)($detail['status'] ?? ''))) ?></strong></div></div>
  <?php if (($detail['status'] ?? '') === 'menunggu_admin'): ?>
  <form method="post" style="margin-top:14px" onsubmit="return confirm('Eksekusi pindah domisili di database?')">
    <?= rtrw_csrf_field() ?><input type="hidden" name="modul" value="pindah"><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
    <textarea name="catatan" class="txt-area" placeholder="Catatan admin (opsional)"></textarea>
    <div class="act-row">
      <button name="aksi" value="approve" class="btn bg"><i class="fas fa-check"></i> Setujui & Pindahkan</button>
      <button name="aksi" value="reject" class="btn bd" onclick="return confirm('Tolak ajuan?')"><i class="fas fa-times"></i> Tolak</button>
    </div>
  </form>
  <?php endif; ?>
<?php else: ?>
  <h3 style="font-size:.9rem;margin-bottom:12px">Detail Surat</h3>
  <div class="detail-line"><span>No. RT</span><div><?= htmlspecialchars($detail['no_surat_rt'] ?? '—') ?></div></div>
  <div class="detail-line"><span>No. RW</span><div><?= htmlspecialchars($detail['no_surat_rw'] ?? '—') ?></div></div>
  <div class="detail-line"><span>Pemohon</span><div><strong><?= htmlspecialchars(strtoupper($detail['nama_pemohon'] ?? '')) ?></strong></div></div>
  <div class="detail-line"><span>NIK</span><div><?= htmlspecialchars($detail['nik'] ?? '—') ?></div></div>
  <div class="detail-line"><span>Wilayah</span><div>RT.<?= htmlspecialchars(rtrw_pad_rt($detail['rt'] ?? '')) ?>/RW.<?= htmlspecialchars(rtrw_pad_rw($detail['rw'] ?? '')) ?></div></div>
  <div class="detail-line"><span>Keperluan</span><div><?= nl2br(htmlspecialchars($detail['keperluan'] ?? '—')) ?></div></div>
  <div class="detail-line"><span>Ketua RT</span><div><?= htmlspecialchars(strtoupper($detail['nama_ketua_rt'] ?? '—')) ?></div></div>
  <div class="detail-line"><span>Dibuat oleh</span><div><?= htmlspecialchars($detail['dibuat_oleh'] ?? '—') ?></div></div>
  <div class="detail-line"><span>Disahkan RW</span><div><?= htmlspecialchars($detail['divalidasi_oleh'] ?? '—') ?></div></div>
  <div class="detail-line"><span>Status</span><div><strong><?= htmlspecialchars(rtrw_status_label((string)($detail['status'] ?? ''))) ?></strong></div></div>
  <?php if (!empty($detail['catatan_rw'])): ?><div class="detail-line"><span>Catatan RW</span><div><?= htmlspecialchars($detail['catatan_rw']) ?></div></div><?php endif; ?>
  <?php if (!empty($detail['token'])): ?>
  <a href="verifikasi_pengantar.php?t=<?= urlencode((string)$detail['token']) ?>" target="_blank" class="btn bn" style="width:100%;justify-content:center;margin-top:10px"><i class="fas fa-qrcode"></i> Buka Halaman Verifikasi</a>
  <?php endif; ?>
<?php endif; ?>
</div>
</div>

</div></div>
<?php include 'footer.php'; ?>
