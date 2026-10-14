<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_sipelamar.php';
require_once __DIR__ . '/inc_admin_toast.php';

sipelamar_ensure_schema($koneksi);
$cfg = sipelamar_pengaturan($koneksi);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $norm = static function (string $raw, string $def): string {
        $v = trim($raw);
        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            return $v . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }
        return $def;
    };

    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $pengumuman = isset($_POST['pengumuman_aktif']) ? 1 : 0;
    $buka = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jam_buka'] ?? '18:00'), '18:00:00'));
    $tutup = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jam_tutup'] ?? '22:00'), '22:00:00'));
    $jdm = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jendela_datang_mulai'] ?? '17:45'), '17:45:00'));
    $jds = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jendela_datang_selesai'] ?? '18:15'), '18:15:00'));
    $jpm = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jendela_pulang_mulai'] ?? '21:45'), '21:45:00'));
    $jps = mysqli_real_escape_string($koneksi, $norm((string) ($_POST['jendela_pulang_selesai'] ?? '22:15'), '22:15:00'));
    $teks = mysqli_real_escape_string($koneksi, trim((string) ($_POST['teks_pengumuman'] ?? '')));

    mysqli_query($koneksi, "UPDATE tbl_sipelamar_pengaturan SET
        aktif=$aktif,
        jam_buka='$buka',
        jam_tutup='$tutup',
        jendela_datang_mulai='$jdm',
        jendela_datang_selesai='$jds',
        jendela_pulang_mulai='$jpm',
        jendela_pulang_selesai='$jps',
        teks_pengumuman='$teks',
        pengumuman_aktif=$pengumuman
        WHERE id=1");

    sipelamar_sync_pengumuman_berita($koneksi);
    header('Location: sipelamar_pengaturan.php?toast=sipelamar_saved');
    exit;
}

include 'header.php';
include 'sidebar.php';
sipandang_admin_dashboard_styles();
?>
<style>
.sp-wrap { margin-top: 12px; padding: 0 18px 32px; max-width: 920px; }
.sj-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e3e8f3;
    box-shadow: 0 3px 14px rgba(10,22,40,.08);
    overflow: hidden;
    margin-bottom: 14px;
}
.sj-card-hd {
    padding: 12px 16px;
    border-bottom: 1px solid #f4f6fb;
    background: #f8f9fe;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sj-card-hd strong {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .82rem;
    font-weight: 800;
    color: var(--role-dark,#05285a);
    margin: 0;
}
.sj-card-hd i {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--role-soft,#e8f2fd);
    color: var(--role-ink,#08428a);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
}
.sj-card-body { padding: 16px 18px; }
.sj-info {
    background: var(--role-soft,#e8f2fd);
    border: 1px solid #e0e7f6;
    border-left: 4px solid var(--role,#0b5cad);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .82rem;
    color: #56658d;
    margin-bottom: 14px;
    line-height: 1.5;
}
.sj-info strong { color: var(--role-dark,#05285a); }
.sp-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border: 1.5px solid #e3e8f3;
    border-radius: 10px;
    background: #f8f9fe;
    margin-bottom: 14px;
}
.sp-check input { margin-top: 3px; flex-shrink: 0; }
.sp-check label {
    margin: 0;
    font-size: .84rem;
    font-weight: 600;
    color: var(--role-dark,#05285a);
    text-transform: none;
    letter-spacing: 0;
}
.sp-check label strong { font-weight: 800; }
.sp-field label {
    font-size: .68rem;
    font-weight: 700;
    color: #566583;
    display: block;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: .35px;
}
.sp-field input[type="time"],
.sp-field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #e3e8f3;
    border-radius: 8px;
    padding: 9px 11px;
    font: inherit;
    font-size: .85rem;
    color: var(--role-dark,#05285a);
    background: #fff;
}
.sp-field input[type="time"] {
    font-family: ui-monospace, Consolas, monospace;
    max-width: 140px;
}
.sp-field textarea { min-height: 110px; resize: vertical; }
.sp-field input:focus,
.sp-field textarea:focus { border-color: var(--role,#0b5cad); outline: none; }
.sp-hint {
    font-size: .75rem;
    color: #566583;
    margin: 8px 0 0;
    line-height: 1.45;
}
.sp-hint a { color: var(--role-ink,#08428a); font-weight: 600; text-decoration: none; }
.sp-hint a:hover { text-decoration: underline; }
@media (max-width: 991px) {
    .sp-wrap { padding: 0 12px 24px; }
}
</style>

<div class="content-wrapper">
<div class="db-header arsip-header">
    <div class="container-fluid">
        <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <span class="hdr-badge"><i class="fas fa-moon mr-1"></i> SiPELAMAR</span>
                <h1>Pengaturan SiPELAMAR</h1>
                <p>Jam pelayanan malam, jendela presensi piket, dan pengumuman warga</p>
            </div>
            <div class="hdr-actions">
                <a href="sipelamar_jadwal.php" class="btn-pro bp-ghost"><i class="fas fa-calendar-alt"></i> Jadwal Piket</a>
                <a href="sipelamar_rekap.php" class="btn-pro bp-ghost"><i class="fas fa-chart-bar"></i> Rekap Malam</a>
            </div>
        </div>
    </div>
</div>

<section class="content"><div class="container-fluid sp-wrap">

<div class="sj-info">
    <strong><i class="fas fa-info-circle mr-1"></i> Kebijakan layanan malam</strong><br>
    Layanan malam setiap hari kerja · 2 operator piket · antrean nomor gabung (A001…) · arsip bertanda SiPELAMAR.
</div>

<form method="post">

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-power-off"></i>
        <strong>Status Modul</strong>
    </div>
    <div class="sj-card-body">
        <div class="sp-check">
            <input type="checkbox" id="aktif" name="aktif" value="1" <?= !empty($cfg['aktif']) ? 'checked' : '' ?>>
            <label for="aktif"><strong>Aktifkan SiPELAMAR</strong> — antrean malam dan presensi piket operator</label>
        </div>
    </div>
</div>

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-clock"></i>
        <strong>Jam Pelayanan Malam</strong>
    </div>
    <div class="sj-card-body">
        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jam_buka">Buka</label>
                    <input type="time" id="jam_buka" name="jam_buka" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jam_buka'] ?? '18:00:00'))) ?>">
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jam_tutup">Tutup</label>
                    <input type="time" id="jam_tutup" name="jam_tutup" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jam_tutup'] ?? '22:00:00'))) ?>">
                </div>
            </div>
        </div>
        <p class="sp-hint">Digunakan untuk antrean warga, penanda arsip, dan referensi presensi piket.</p>
    </div>
</div>

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-user-clock"></i>
        <strong>Jendela Presensi Piket</strong>
    </div>
    <div class="sj-card-body">
        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jendela_datang_mulai">Datang mulai</label>
                    <input type="time" id="jendela_datang_mulai" name="jendela_datang_mulai" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jendela_datang_mulai'] ?? '17:45:00'))) ?>">
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jendela_datang_selesai">Datang selesai</label>
                    <input type="time" id="jendela_datang_selesai" name="jendela_datang_selesai" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jendela_datang_selesai'] ?? '18:15:00'))) ?>">
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jendela_pulang_mulai">Pulang mulai</label>
                    <input type="time" id="jendela_pulang_mulai" name="jendela_pulang_mulai" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jendela_pulang_mulai'] ?? '21:45:00'))) ?>">
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="sp-field">
                    <label for="jendela_pulang_selesai">Pulang selesai</label>
                    <input type="time" id="jendela_pulang_selesai" name="jendela_pulang_selesai" value="<?= htmlspecialchars(sipelamar_jam_tampil((string) ($cfg['jendela_pulang_selesai'] ?? '22:15:00'))) ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-bullhorn"></i>
        <strong>Pengumuman Warga</strong>
    </div>
    <div class="sj-card-body">
        <div class="sp-check">
            <input type="checkbox" id="pengumuman_aktif" name="pengumuman_aktif" value="1" <?= !empty($cfg['pengumuman_aktif']) ? 'checked' : '' ?>>
            <label for="pengumuman_aktif">Tampilkan di berita kelurahan (pin + aktif)</label>
        </div>
        <div class="sp-field">
            <label for="teks_pengumuman">Teks pengumuman</label>
            <textarea id="teks_pengumuman" name="teks_pengumuman" placeholder="Informasi layanan malam untuk warga"><?= htmlspecialchars((string) ($cfg['teks_pengumuman'] ?? '')) ?></textarea>
        </div>
        <p class="sp-hint">Otomatis disinkron ke <a href="data_berita.php">Data Berita</a> kategori SiPELAMAR.</p>
    </div>
</div>

<div class="page-toolbar-card">
    <button type="submit" class="btn-pro bp-navy"><i class="fas fa-save"></i> Simpan Pengaturan</button>
    <a href="sipelamar_jadwal.php" class="btn-pro bp-ghost"><i class="fas fa-calendar-alt"></i> Jadwal Piket</a>
    <a href="sipelamar_rekap.php" class="btn-pro bp-ghost"><i class="fas fa-chart-bar"></i> Rekap Malam</a>
</div>

</form>

</div></section>
</div>
<?php include 'footer.php'; ?>
