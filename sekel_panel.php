<?php
/**
 * sekel_panel.php — Home khusus Sekretaris Kelurahan.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_sekel(false);

include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_sekel_panel.php';

$P = sipandang_sekel_panel_stats($koneksi);
$S = $P['stats'];
$nama = trim((string) ($_SESSION['nama'] ?? 'Sekretaris Kelurahan'));
$tgl_indo = date('d') . ' ' . [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
][date('m')] . ' ' . date('Y');

include 'header.php';
include 'sidebar.php';
sipandang_admin_dashboard_styles();
?>
<style>
.sk-wrap { padding: 0 18px 32px; }
.sk-kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
@media (max-width: 991px) { .sk-kpi { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575px) { .sk-kpi { grid-template-columns: 1fr; } }
.sk-card {
    background: #fff; border: 1px solid #e3e8f3; border-radius: 12px;
    padding: 16px 18px; box-shadow: 0 3px 14px rgba(10,22,40,.06);
}
.sk-card .lbl { font-size: .68rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #566583; }
.sk-card .val { font-size: 1.65rem; font-weight: 800; color: var(--role-dark,#05285a); line-height: 1.1; margin-top: 4px; }
.sk-card .sub { font-size: .72rem; color: #566583; margin-top: 4px; }
.sk-card.warn { border-left: 4px solid #e67e22; }
.sk-card.alert { border-left: 4px solid #dc3a52; }
.sk-card.teal { border-left: 4px solid var(--role,#17a2b8); }
.sk-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: start; }
@media (max-width: 991px) { .sk-grid { grid-template-columns: 1fr; } }
.sk-panel {
    background: #fff; border: 1px solid #e3e8f3; border-radius: 12px;
    box-shadow: 0 3px 14px rgba(10,22,40,.06); overflow: hidden; margin-bottom: 14px;
}
.sk-panel-hd {
    padding: 12px 16px; border-bottom: 1px solid #edf0f8;
    font-size: .82rem; font-weight: 800; color: var(--role-dark,#05285a); text-transform: uppercase; letter-spacing: .35px;
}
.sk-panel-hd i { color: var(--role,#0973a8); margin-right: 6px; }
.sk-list { list-style: none; margin: 0; padding: 0; }
.sk-list li {
    padding: 10px 16px; border-bottom: 1px solid #edf0f8; font-size: .78rem;
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.sk-list li:last-child { border-bottom: none; }
.sk-list a { color: var(--role-ink,#08428a); font-weight: 600; text-decoration: none; }
.sk-list a:hover { text-decoration: underline; }
.sk-quick { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 14px; margin-bottom: 14px; }
@media (max-width: 767px) { .sk-quick { grid-template-columns: 1fr 1fr; } }
.sk-q {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px;
    border: 1px solid #e3e8f3; border-radius: 8px; text-decoration: none; color: var(--role-dark,#05285a);
    font-size: .78rem; font-weight: 600;
}
.sk-q:hover { border-color: var(--role,#0973a8); background: var(--role-soft,#f0f9ff); color: var(--role-ink,#08428a); text-decoration: none; }
.sk-q i { color: var(--role,#0973a8); width: 18px; text-align: center; }
.sk-st { font-size: .62rem; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: #fff3cd; color: #856404; white-space: nowrap; }
.sk-note {
    background: var(--role-soft,#f0f9ff); border: 1px solid var(--role-line); border-radius: 10px;
    padding: 12px 16px; font-size: .78rem; color: var(--role-ink); margin-bottom: 14px;
}
</style>

<div class="content-wrapper">
<div class="db-header">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge" style="background:rgba(9,115,168,.25);color:#fff;"><i class="fas fa-user-tie mr-1"></i> MEJA SEKEL</span>
                <h1>Sekretariat Kelurahan SIPANDANG</h1>
                <p><?= htmlspecialchars($nama) ?> &nbsp;·&nbsp; <?= $tgl_indo ?> &nbsp;·&nbsp; Persuratan kantor &amp; paraf surat pelayanan</p>
            </div>
            <div class="hdr-actions">
                <a href="dashboard_kinerja.php" class="btn-pro bp-gold"><i class="fas fa-chart-line"></i> Dashboard Kinerja</a>
                <a href="surat_masuk.php" class="btn-pro bp-ghost"><i class="fas fa-envelope"></i> Agenda Masuk</a>
            </div>
        </div>
    </div>
</div>

<section class="content"><div class="container-fluid"><div class="sk-wrap">

<div class="sk-note">
    <i class="fas fa-info-circle mr-1"></i>
    Tugas Sekel: kelola agenda surat kantor, paraf surat pelayanan (<strong>Verifikasi Sekel</strong>), dan pantau kinerja. Input loket dilakukan Operator.
</div>

<div class="sk-kpi">
    <div class="sk-card<?= $P['verifikasi_sekel'] > 0 ? ' alert' : '' ?>">
        <div class="lbl"><i class="fas fa-stamp mr-1"></i> Paraf Sekel</div>
        <div class="val"><?= number_format($P['verifikasi_sekel']) ?></div>
        <div class="sub">Menunggu paraf Anda</div>
    </div>
    <div class="sk-card<?= $P['menunggu_ttd'] > 0 ? ' warn' : '' ?>">
        <div class="lbl"><i class="fas fa-signature mr-1"></i> Menunggu TTD Lurah</div>
        <div class="val"><?= number_format($P['menunggu_ttd']) ?></div>
        <div class="sub">Sudah diparaf Sekel</div>
    </div>
    <div class="sk-card teal">
        <div class="lbl"><i class="fas fa-inbox mr-1"></i> Surat Masuk Bulan Ini</div>
        <div class="val"><?= number_format($P['surat_masuk_bulan']) ?></div>
        <div class="sub">Agenda kantor</div>
    </div>
    <div class="sk-card teal">
        <div class="lbl"><i class="fas fa-paper-plane mr-1"></i> Surat Keluar Bulan Ini</div>
        <div class="val"><?= number_format($P['surat_keluar_bulan']) ?></div>
        <div class="sub">Agenda kantor</div>
    </div>
</div>

<div class="sk-panel">
    <div class="sk-panel-hd"><i class="fas fa-bolt"></i> Aksi Cepat</div>
    <div class="sk-quick">
        <a href="surat_masuk.php" class="sk-q"><i class="fas fa-envelope"></i> Agenda Surat Masuk</a>
        <a href="agenda_surat_keluar.php" class="sk-q"><i class="fas fa-paper-plane"></i> Agenda Surat Keluar</a>
        <a href="arsip_surat_masuk.php" class="sk-q"><i class="fas fa-inbox"></i> Arsip Masuk</a>
        <a href="arsip_surat_keluar_kantor.php" class="sk-q"><i class="fas fa-share-square"></i> Arsip Keluar</a>
        <a href="arsip_surat.php?status=<?= urlencode('Verifikasi Sekel') ?>" class="sk-q"><i class="fas fa-stamp"></i> Antrean Paraf</a>
        <a href="dashboard_kinerja.php" class="sk-q"><i class="fas fa-chart-line"></i> Laporan Kinerja</a>
    </div>
</div>

<div class="sk-grid">
    <div class="sk-panel">
        <div class="sk-panel-hd"><i class="fas fa-stamp"></i> Antrean Paraf Surat Pelayanan</div>
        <ul class="sk-list">
        <?php if (empty($P['paraf_antrean'])): ?>
            <li style="color:#566583;justify-content:center;">Tidak ada surat menunggu paraf.</li>
        <?php else: foreach ($P['paraf_antrean'] as $sr): ?>
            <li>
                <div style="min-width:0;">
                    <a href="arsip_detail.php?id=<?= (int) $sr['id_surat'] ?>">
                        <?= htmlspecialchars($sr['nama_pemohon'] ?? '-') ?>
                    </a>
                    <div style="font-size:.68rem;color:#566583;margin-top:2px;">
                        <?= htmlspecialchars($sr['no_surat'] ?? '-') ?>
                        · <?= htmlspecialchars($sr['nama_surat'] ?? '') ?>
                        · <?= !empty($sr['tgl_surat']) ? date('d/m/Y', strtotime($sr['tgl_surat'])) : '-' ?>
                    </div>
                </div>
                <a href="proses_alur.php?act=acc_sekel&id=<?= (int) $sr['id_surat'] ?>"
                   class="btn-pro bp-sm bp-green" style="font-size:.68rem;padding:4px 10px;"
                   onclick="return confirm('Paraf surat ini dan teruskan ke Lurah (Menunggu TTD)?');">
                    Paraf
                </a>
            </li>
        <?php endforeach; endif; ?>
        </ul>
        <div style="padding:10px 16px;border-top:1px solid #edf0f8;text-align:right;">
            <a href="arsip_surat.php?status=<?= urlencode('Verifikasi Sekel') ?>" class="btn-pro bp-ghost bp-sm"><i class="fas fa-external-link-alt"></i> Semua Antrean</a>
        </div>
    </div>

    <div class="sk-panel">
        <div class="sk-panel-hd"><i class="fas fa-chart-pie"></i> Ringkasan Pelayanan</div>
        <ul class="sk-list">
            <li>
                <span>Pelayanan bulan ini</span>
                <strong><?= number_format($P['surat_bulan']) ?></strong>
            </li>
            <li>
                <span>Pengajuan online pending</span>
                <strong><?= number_format((int) ($S['pengajuan_pending'] ?? 0)) ?></strong>
            </li>
            <li>
                <span>SIAK draft</span>
                <strong><?= number_format((int) ($S['siak_draft'] ?? 0)) ?></strong>
            </li>
            <li>
                <span>Surat pending loket</span>
                <strong><?= number_format((int) ($S['surat_pending'] ?? 0)) ?></strong>
            </li>
        </ul>
        <div style="padding:10px 16px;border-top:1px solid #edf0f8;text-align:right;">
            <a href="dashboard.php" class="btn-pro bp-ghost bp-sm"><i class="fas fa-chart-pie"></i> Statistik Lengkap</a>
        </div>
    </div>
</div>

</div></div></section>
</div>

<?php include 'footer.php'; ?>
