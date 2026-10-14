<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_absensi_kelola(false);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_absensi.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_admin_toast.php';

absensi_ensure_schema($koneksi);

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if (isset($_POST['sync_libur_nasional'])) {
    $syncTahun = isset($_POST['sync_tahun']) ? (int) $_POST['sync_tahun'] : $tahun;
    $cutiBersama = isset($_POST['sync_cuti_bersama']);
    if (!isset(libur_nasional_id_data()[$syncTahun])) {
        libur_nasional_id_cache_refresh($koneksi, $syncTahun);
    }
    $hasil = absensi_libur_seed_nasional($koneksi, $syncTahun, $cutiBersama);
    sipandang_log_aktivitas(
        $koneksi,
        'ABSENSI',
        'libur_sync',
        0,
        $_SESSION['nama'] ?? '',
        'Tahun ' . $syncTahun . ': +' . $hasil['added'] . ' / skip ' . $hasil['skipped']
    );
    sipandang_redirect_toast(
        'absensi_libur.php?tahun=' . $syncTahun
            . '&sync_added=' . (int) $hasil['added']
            . '&sync_skip=' . (int) $hasil['skipped']
            . '&sync_sumber=' . urlencode((string) ($hasil['sumber'] ?? '')),
        'absensi_libur_sync'
    );
}

if (isset($_POST['tambah_libur'])) {
    $tgl = trim((string) ($_POST['tanggal'] ?? ''));
    $ket = trim((string) ($_POST['keterangan'] ?? ''));
    $jenis = trim((string) ($_POST['jenis'] ?? 'NASIONAL'));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) && $ket !== '') {
        absensi_libur_tambah($koneksi, $tgl, $ket, $jenis);
        sipandang_log_aktivitas($koneksi, 'ABSENSI', 'libur_tambah', 0, $_SESSION['nama'] ?? '', $tgl . ' ' . $ket);
        sipandang_redirect_toast('absensi_libur.php?tahun=' . (int) date('Y', strtotime($tgl)), 'absensi_libur');
    }
}

if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    if ($id > 0) {
        absensi_libur_hapus($koneksi, $id);
        sipandang_log_aktivitas($koneksi, 'ABSENSI', 'libur_hapus', $id, $_SESSION['nama'] ?? '', '');
        sipandang_redirect_toast('absensi_libur.php?tahun=' . $tahun, 'absensi_libur_hapus');
    }
}

$libur = absensi_libur_semua($koneksi, null, $tahun);
$tahunStatic = libur_nasional_id_tahun_static();
$syncAdded = isset($_GET['sync_added']) ? (int) $_GET['sync_added'] : null;
$syncSkip = isset($_GET['sync_skip']) ? (int) $_GET['sync_skip'] : null;
$syncSumber = isset($_GET['sync_sumber']) ? (string) $_GET['sync_sumber'] : '';

include 'header.php';
include 'sidebar.php';

function al_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<style>
body{font-family:'Plus Jakarta Sans',sans-serif!important;background:#f4f6fb!important}
.content-wrapper{background:#f4f6fb!important;padding:0!important}
.pg-hdr{background:#fff;margin:18px 18px 0;padding:14px 16px;border-radius:14px;box-shadow:0 3px 14px rgba(10,22,40,.08);border:1px solid #e3e8f3;color:var(--role-dark,#05285a)}
.pg-badge{display:inline-flex;align-items:center;gap:6px;background:var(--role-soft);border:1px solid var(--role-line);color:var(--role);padding:4px 12px;border-radius:999px;font-size:.64rem;font-weight:800;letter-spacing:.55px;margin-bottom:8px}
.pg-hdr h1{font-size:1.02rem;font-weight:800;margin:2px 0 0}
.pg-hdr p{color:#566583;font-size:.74rem;margin:3px 0 0}
.al-wrap{padding:0 18px 24px;margin-top:12px}
.al-card{background:#fff;border-radius:14px;border:1px solid #e3e8f3;box-shadow:0 3px 14px rgba(10,22,40,.08);padding:16px;margin-bottom:14px}
.al-card h3{font-size:.85rem;font-weight:800;color:var(--role-dark,#05285a);margin:0 0 12px}
.al-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end}
.al-field label{display:block;font-size:.68rem;font-weight:700;color:#566583;margin-bottom:4px;text-transform:uppercase}
.al-field input,.al-field select{width:100%;padding:9px 11px;border:1.5px solid #e3e8f3;border-radius:9px;font:inherit;font-size:.85rem}
.al-btn{background:var(--role,#0b5cad);color:#fff;border:none;padding:10px 16px;border-radius:9px;font-weight:700;cursor:pointer;font-size:.82rem}
.al-table{width:100%;border-collapse:collapse;font-size:.82rem}
.al-table th{background:var(--role-soft,#e8f2fd);padding:10px 12px;text-align:left;font-size:.7rem;text-transform:uppercase;color:var(--role-dark,#05285a)}
.al-table td{padding:10px 12px;border-top:1px solid #f4f6fb}
.al-badge{font-size:.68rem;font-weight:700;padding:3px 8px;border-radius:6px}
.al-badge.nas{background:var(--role-soft,#e8f4fd);color:var(--role-ink,#08428a)}
.al-badge.kel{background:#fff4e5;color:#98620b}
</style>

<div class="content-wrapper">
    <div class="pg-hdr">
        <div class="pg-badge"><i class="fas fa-calendar-times"></i> ABSENSI</div>
        <h1><i class="fas fa-calendar-day mr-2"></i> Kalender Libur</h1>
        <p>Hari libur nasional & kelurahan — pegawai tidak wajib absen. Libur nasional terisi otomatis (tahun berjalan + tahun depan).</p>
    </div>
    <div class="al-wrap">
        <div class="al-card">
            <h3><i class="fas fa-sync-alt mr-1"></i> Libur Nasional Indonesia (Otomatis)</h3>
            <p style="font-size:.75rem;color:#566583;margin-bottom:12px">
                <strong>Tahun berjalan &amp; tahun depan</strong> otomatis diisi saat modul absensi diakses.
                Sumber: SKB embedded (2025–2026) atau <strong>API otomatis</strong>
                (<a href="https://api-hari-libur.vercel.app/" target="_blank" rel="noopener">api-hari-libur.vercel.app</a>, update bulanan dari tanggalan.com).
                Cache API 30 hari — hosting perlu akses internet (HTTPS).
            </p>
            <form method="post" class="al-grid">
                <input type="hidden" name="sync_libur_nasional" value="1">
                <div class="al-field"><label>Tahun</label>
                    <select name="sync_tahun">
                        <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 3; $y++):
                            $lbl = libur_nasional_id_sumber_label($y, $koneksi);
                            ?>
                            <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?> · <?= al_h($lbl) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="al-field" style="display:flex;align-items:flex-end;padding-bottom:10px">
                    <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;text-transform:none;color:var(--role-dark,#05285a)">
                        <input type="checkbox" name="sync_cuti_bersama" value="1" checked> Sertakan cuti bersama
                    </label>
                </div>
                <div><button type="submit" class="al-btn" style="background:var(--role-dark,#05285a)"><i class="fas fa-download mr-1"></i> Sinkronkan Sekarang</button></div>
            </form>
            <?php if ($syncAdded !== null): ?>
                <p style="font-size:.78rem;color:var(--role-ink,#08428a);margin:10px 0 0">
                    <i class="fas fa-check-circle mr-1"></i><?= (int) $syncAdded ?> hari ditambahkan, <?= (int) $syncSkip ?> dilewati.
                    <?php if ($syncSumber !== ''): ?> Sumber: <strong><?= al_h($syncSumber) ?></strong>.<?php endif; ?>
                </p>
            <?php endif; ?>
            <p style="font-size:.72rem;color:#9aabbe;margin:10px 0 0">
                SKB embedded: <?= al_h(implode(', ', $tahunStatic)) ?>. Tahun lain via API (mis. 2027 otomatis saat dibuka).
            </p>
        </div>

        <div class="al-card">
            <h3>Tambah Hari Libur</h3>
            <form method="post" class="al-grid">
                <input type="hidden" name="tambah_libur" value="1">
                <div class="al-field"><label>Tanggal</label><input type="date" name="tanggal" required></div>
                <div class="al-field"><label>Keterangan</label><input type="text" name="keterangan" placeholder="Contoh: Idul Fitri" required></div>
                <div class="al-field"><label>Jenis</label>
                    <select name="jenis"><option value="NASIONAL">Nasional</option><option value="KELURAHAN">Kelurahan</option></select>
                </div>
                <div><button type="submit" class="al-btn"><i class="fas fa-plus mr-1"></i> Simpan</button></div>
            </form>
        </div>

        <div class="al-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
                <h3 style="margin:0">Daftar Libur <?= (int) $tahun ?></h3>
                <form method="get" style="display:flex;gap:8px;align-items:center">
                    <input type="number" name="tahun" value="<?= (int) $tahun ?>" min="2020" max="2099" style="width:90px;padding:8px;border:1.5px solid #e3e8f3;border-radius:8px">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Tampilkan</button>
                </form>
            </div>
            <table class="al-table">
                <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th></th></tr></thead>
                <tbody>
                <?php if (!$libur): ?>
                    <tr><td colspan="4" style="text-align:center;color:#566583;padding:24px">Belum ada hari libur untuk tahun ini.</td></tr>
                <?php else: foreach ($libur as $lb): ?>
                    <tr>
                        <td><?= al_h(date('d M Y', strtotime((string) $lb['tanggal']))) ?></td>
                        <td><?= al_h((string) ($lb['keterangan'] ?? '')) ?></td>
                        <td><span class="al-badge <?= ($lb['jenis'] ?? '') === 'KELURAHAN' ? 'kel' : 'nas' ?>"><?= al_h((string) ($lb['jenis'] ?? 'NASIONAL')) ?></span></td>
                        <td><a href="absensi_libur.php?hapus=<?= (int) $lb['id_libur'] ?>&tahun=<?= (int) $tahun ?>" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hapus libur ini?')">Hapus</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <a href="absensi_pengaturan.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Pengaturan</a>
    </div>
</div>
<?php include 'footer.php'; ?>
