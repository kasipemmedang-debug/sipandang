<?php
/**
 * Verifikasi TTD digital SIAK — panel desktop Kasi/Lurah (selaras app pejabat).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
if (!alur_ttd_user_boleh_akses_pejabat()) {
    header('Location: login_portal.php?pesan=akses_ditolak');
    exit;
}

include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_siak_alur_ttd.php';
require_once __DIR__ . '/inc_siak_preview.php';
require_once __DIR__ . '/inc_kelengkapan_berkas.php';

siak_alur_ttd_ensure_schema($koneksi);
kelengkapan_siak_ensure_schema($koneksi);

$id = (int) ($_GET['id'] ?? 0);
$row = siak_alur_ttd_detail($koneksi, $id);
if (!$row) {
    header('Location: ' . sipandang_siak_ttd_panel_back_url());
    exit;
}

$st = trim((string) ($row['status_ttd'] ?? ''));
$boleh = siak_alur_ttd_user_boleh_proses($koneksi, $row);
$idPm = alur_ttd_pamong_id_untuk_session($koneksi);
$ttdAda = $idPm > 0 && alur_ttd_gambar_url_root($koneksi, $idPm) !== '';

$kel = kelengkapan_siak_get($koneksi, $id);
$kelItems = $kel['items'] ?? [];
$kelStatus = $kel ? kelengkapan_siak_status_dari_items($koneksi, $kelItems) : 'LENGKAP';
$kelLengkap = $kelStatus === 'LENGKAP';
$kelTotal = count($kelItems);
$kelAda = 0;
foreach ($kelItems as $it) {
    if (!empty($it['ada']) || !empty($it['na'])) {
        $kelAda++;
    }
}

$berkasCek = siak_alur_ttd_berkas_siap_kirim_atau_proses($koneksi, $id);
$bolehSetujui = $boleh && $ttdAda && $berkasCek['ok'];

$fileScan = trim((string) ($row['file_scan_siak'] ?? ''));
$scanUrl = $fileScan !== '' ? 'buka_berkas.php?jenis=siak_scan&id=' . $id : '';

$formKey = trim((string) ($_GET['form'] ?? $row['formulir_utama'] ?? ''));
$formInfo = siak_preview_resolve_form($row, $formKey !== '' ? $formKey : null);
$formKey = $formInfo['key'];
$formChoices = siak_preview_form_choices($row);
$formHtml = siak_preview_capture_html($koneksi, $id, $formKey);
$layananLabel = str_replace('_', ' ', trim((string) ($row['jenis_layanan'] ?? 'SIAK')));
$panelBack = sipandang_siak_ttd_panel_back_url();

include 'header.php';
include 'sidebar.php';
?>
<style>
.siak-ttd-wrap { padding: 0 18px 32px; }
.siak-ttd-grid { display: grid; grid-template-columns: 1fr 340px; gap: 16px; align-items: start; }
@media (max-width: 991px) { .siak-ttd-grid { grid-template-columns: 1fr; } }
.siak-ttd-preview {
    background: #fff; border: 1px solid #e3e8f3; border-radius: 12px;
    box-shadow: 0 3px 14px rgba(10,22,40,.06); overflow: hidden;
}
.siak-ttd-preview-hd {
    padding: 12px 16px; border-bottom: 1px solid #edf0f8;
    display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    font-size: .82rem; font-weight: 800; color: var(--role-dark,#05285a);
}
.siak-ttd-preview-body { padding: 12px; overflow: auto; max-height: 75vh; background: #f4f6fb; }
.siak-ttd-side .side-card {
    background: #fff; border: 1px solid #e3e8f3; border-radius: 12px;
    padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 3px 14px rgba(10,22,40,.06);
}
.siak-ttd-side h5 { font-size: .88rem; font-weight: 800; color: var(--role-dark,#05285a); margin: 0 0 10px; }
.siak-ttd-meta { font-size: .78rem; color: var(--role-ink); line-height: 1.55; margin-bottom: 8px; }
.siak-ttd-meta b { color: var(--role-dark,#05285a); }
.siak-ttd-check { list-style: none; margin: 0; padding: 0; font-size: .76rem; }
.siak-ttd-check li { padding: 6px 0; border-bottom: 1px solid #edf0f8; display: flex; gap: 8px; align-items: flex-start; }
.siak-ttd-check li:last-child { border-bottom: none; }
.siak-ttd-form-pick { max-width: 260px; padding: 6px 10px; border: 1px solid #e3e8f3; border-radius: 8px; font-size: .78rem; }
</style>

<div class="content-wrapper">
<div class="db-header">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge"><i class="fas fa-id-card mr-1"></i> VERIFIKASI SIAK</span>
                <h1><?= htmlspecialchars($layananLabel) ?></h1>
                <p><?= htmlspecialchars(siak_alur_ttd_label_status($st)) ?> · <?= htmlspecialchars($row['nama_pemohon'] ?? '-') ?></p>
            </div>
            <div class="hdr-actions">
                <a href="<?= htmlspecialchars($panelBack) ?>" class="btn-pro bp-ghost"><i class="fas fa-arrow-left"></i> Kembali ke Panel</a>
                <a href="pejabat/detail_siak.php?id=<?= $id ?>&form=<?= rawurlencode($formKey) ?>" target="_blank" class="btn-pro bp-ghost"><i class="fas fa-mobile-alt"></i> Buka di App Pejabat</a>
            </div>
        </div>
    </div>
</div>

<section class="content"><div class="container-fluid"><div class="siak-ttd-wrap">
<div class="siak-ttd-grid">
    <div class="siak-ttd-preview">
        <div class="siak-ttd-preview-hd">
            <span><i class="fas fa-file-alt" style="color:var(--role,#0b5cad);margin-right:6px;"></i> Preview Formulir</span>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <?php if (count($formChoices) > 1): ?>
                <select class="siak-ttd-form-pick" onchange="window.location='siak_ttd_detail.php?id=<?= $id ?>&form='+encodeURIComponent(this.value)">
                    <?php foreach ($formChoices as $fc): ?>
                    <option value="<?= htmlspecialchars($fc['key']) ?>" <?= $fc['key'] === $formKey ? 'selected' : '' ?>><?= htmlspecialchars($fc['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <a href="pejabat/preview_siak.php?id=<?= $id ?>&form=<?= rawurlencode($formKey) ?>" target="_blank" class="btn-pro bp-sm bp-ghost">Fullscreen</a>
            </div>
        </div>
        <div class="siak-ttd-preview-body">
            <?= $formHtml !== '' ? $formHtml : '<p style="color:#999;text-align:center;padding:24px">Preview tidak tersedia.</p>' ?>
        </div>
    </div>

    <div class="siak-ttd-side">
        <div class="side-card">
            <h5><i class="fas fa-info-circle mr-1"></i> Data Permohonan</h5>
            <div class="siak-ttd-meta">Pemohon: <b><?= htmlspecialchars($row['nama_pemohon'] ?? '-') ?></b></div>
            <div class="siak-ttd-meta">NIK: <b><?= htmlspecialchars($row['nik_pemohon'] ?? '-') ?></b></div>
            <div class="siak-ttd-meta">Formulir: <b><?= htmlspecialchars($formInfo['label'] ?? '-') ?></b></div>
            <div class="siak-ttd-meta">TTD Kel: <b><?= htmlspecialchars(trim(($row['nama_pamong'] ?? '') . ' — ' . ($row['jabatan'] ?? ''))) ?></b></div>
            <?php if (!empty($row['dikirim_oleh'])): ?>
            <div class="siak-ttd-meta">Operator: <b><?= htmlspecialchars($row['dikirim_oleh']) ?></b>
                <?= !empty($row['tgl_kirim_pejabat']) ? ' · ' . date('d/m/Y H:i', strtotime($row['tgl_kirim_pejabat'])) : '' ?></div>
            <?php endif; ?>
            <?php if (trim((string) ($row['catatan_pejabat'] ?? '')) !== ''): ?>
            <div class="siak-ttd-meta" style="margin-top:8px;padding:8px 10px;background:#fff8e6;border-radius:8px;border:1px solid #f0e6c8;">
                <i class="fas fa-comment mr-1"></i> <?= htmlspecialchars($row['catatan_pejabat']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="side-card">
            <h5><i class="fas fa-clipboard-check mr-1"></i> Kelengkapan Berkas</h5>
            <?php if ($kelTotal === 0): ?>
            <p class="siak-ttd-meta mb-0">Belum ada checklist kelengkapan.</p>
            <?php else: ?>
            <p class="siak-ttd-meta"><?= (int) $kelAda ?> dari <?= (int) $kelTotal ?> item<?= $kelLengkap ? '' : ' — belum lengkap' ?></p>
            <ul class="siak-ttd-check">
                <?php foreach ($kelItems as $it):
                    $ada = !empty($it['ada']) || !empty($it['na']);
                    $namaItem = trim((string) ($it['nama_berkas'] ?? $it['nama'] ?? 'Item'));
                ?>
                <li>
                    <i class="fas fa-<?= $ada ? 'check-circle' : 'times-circle' ?>" style="color:<?= $ada ? '#0e7050' : '#dc3a52' ?>;margin-top:2px;"></i>
                    <span><?= htmlspecialchars($namaItem) ?><?= !empty($it['na']) ? ' (N/A)' : '' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($scanUrl !== ''): ?>
            <a href="<?= htmlspecialchars($scanUrl) ?>" target="_blank" class="btn-pro bp-navy bp-sm" style="width:100%;justify-content:center;margin-top:10px;">
                <i class="fas fa-file-pdf"></i> Lihat Berkas PDF
            </a>
            <?php else: ?>
            <p class="siak-ttd-meta" style="color:#dc3a52;margin-top:10px;margin-bottom:0;">Berkas PDF belum di-upload operator.</p>
            <?php endif; ?>
        </div>

        <?php if ($boleh): ?>
        <div class="side-card" style="border-left:4px solid var(--role,#0b5cad);">
            <h5><i class="fas fa-stamp mr-1"></i> Verifikasi Pejabat</h5>
            <?php if (!$ttdAda): ?>
            <p class="siak-ttd-meta">TTD gambar belum diatur. <a href="pejabat/ttd.php" target="_blank">Atur TTD</a> dulu.</p>
            <?php elseif (!$berkasCek['ok']): ?>
            <p class="siak-ttd-meta" style="color:#dc3a52;"><?= htmlspecialchars($berkasCek['msg']) ?></p>
            <?php else: ?>
            <p class="siak-ttd-meta">Tinjau formulir dan berkas lalu setujui atau kembalikan ke operator.</p>
            <?php endif; ?>
            <a href="proses_siak_alur.php?act=siak_ttd_setujui&id=<?= $id ?>&ret=panel"
               class="btn-pro bp-gold" style="width:100%;justify-content:center;margin-bottom:8px;<?= $bolehSetujui ? '' : 'opacity:.55;pointer-events:none;' ?>"
               <?= $bolehSetujui ? 'onclick="return confirm(\'Setujui TTD digital Kelurahan?\')"' : '' ?>>
                <i class="fas fa-check-circle"></i> Setujui TTD Kelurahan
            </a>
            <form action="proses_siak_alur.php" method="post" onsubmit="var c=document.getElementById('catatan_kembali');if(!c.value.trim()){alert('Catatan wajib diisi.');return false;}return confirm('Kembalikan ke operator?');">
                <input type="hidden" name="act" value="siak_ttd_kembalikan">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="ret" value="panel">
                <label class="siak-ttd-meta" style="display:block;margin-bottom:4px;">Catatan untuk operator</label>
                <textarea name="catatan" id="catatan_kembali" class="form-control mb-2" rows="3" placeholder="Contoh: Lampiran KTP kurang jelas" required></textarea>
                <button type="submit" class="btn-pro bp-danger" style="width:100%;justify-content:center;">
                    <i class="fas fa-undo"></i> Kembalikan ke Operator
                </button>
            </form>
        </div>
        <?php elseif ($st === 'MENUNGGU_PJ'): ?>
        <div class="side-card">
            <p class="siak-ttd-meta mb-0">Permohonan ini menunggu pejabat penandatangan Kelurahan yang ditunjuk.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div></div></section>
</div>

<?php include 'footer.php'; ?>
