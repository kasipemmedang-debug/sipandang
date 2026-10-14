<?php
// ======================================================
// sidebar.php — SIPANDANG v4 (Unified Light Sidebar)
// ======================================================
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include_once 'koneksi.php';
require_once __DIR__ . '/auth_admin.php';

if (sipandang_user_is_loket_pelayanan()) { return; }

function is_active($page, $targets) {
    $c = basename($page);
    return is_array($targets) ? in_array($c, $targets) : ($c === $targets);
}
function is_open($page, $targets) {
    return is_array($targets) && in_array(basename($page), $targets);
}

$page      = $_SERVER['PHP_SELF'];
$level_db  = trim($_SESSION['level'] ?? 'Operator');
$nama_user = trim($_SESSION['nama']  ?? 'User');
$cek_level = strtolower($level_db);

$is_admin = in_array($cek_level, ['admin','administrator']);
$is_operator = ($cek_level === 'operator');
$is_pkl = ($cek_level === 'pkl');
$is_loket = $is_operator || $is_pkl;
$is_lurah = in_array($cek_level, ['lurah','kepala desa','kades']);
$is_kasi  = ($cek_level === 'kasi');
$is_sekel = ($cek_level === 'sekel');

$sidebar_level_map = [
    'admin'         => ['label' => 'Administrator', 'icon' => 'fas fa-user-shield'],
    'administrator' => ['label' => 'Administrator', 'icon' => 'fas fa-user-shield'],
    'operator'      => ['label' => 'Operator',        'icon' => 'fas fa-user-cog'],
    'pkl'           => ['label' => 'PKL',             'icon' => 'fas fa-user-graduate'],
    'kasi'          => ['label' => 'Kasi',            'icon' => 'fas fa-user-tie'],
    'sekel'         => ['label' => 'Sekel',           'icon' => 'fas fa-user-tie'],
    'lurah'         => ['label' => 'Lurah',           'icon' => 'fas fa-user-check'],
    'kepala desa'   => ['label' => 'Lurah',           'icon' => 'fas fa-user-check'],
    'kades'         => ['label' => 'Lurah',           'icon' => 'fas fa-user-check'],
];

$lv_cfg = $sidebar_level_map[$cek_level] ?? ['label' => ($level_db !== '' ? $level_db : 'Operator'), 'icon' => 'fas fa-user'];
$label_level = $lv_cfg['label'];
$icon_level  = $lv_cfg['icon'];

$is_kasipem = sipandang_user_is_kasipem();
$is_kasi_pemberdayaan = sipandang_user_is_kasi_pemberdayaan();
if ($is_kasipem) { $label_level = 'Kasi Pemerintahan'; }
elseif ($is_kasi_pemberdayaan) { $label_level = 'Kasi Pemberdayaan'; }
elseif ($is_kasi) { $label_level = 'Kasi'; }
elseif ($is_sekel) { $label_level = 'Sekretaris Kelurahan'; }

$q_inst   = mysqli_query($koneksi,"SELECT * FROM tbl_profil LIMIT 1");
$instansi = mysqli_fetch_assoc($q_inst);
$logo     = 'assets/logokab.png';
$nama_kel = strtoupper($instansi['nama_kelurahan'] ?? 'SIPANDANG');

$jml_pending = 0;
if (!$is_lurah) {
    $r = mysqli_query($koneksi,"SELECT COUNT(*) c FROM tbl_surat_keluar WHERE status_surat='Pending'");
    $jml_pending = mysqli_fetch_assoc($r)['c'] ?? 0;
}
$jml_draft = 0;
$r2 = mysqli_query($koneksi,"SELECT COUNT(*) c FROM permohonan_siak WHERE status_siak='DRAFT'");
if ($r2) $jml_draft = mysqli_fetch_assoc($r2)['c'] ?? 0;

$mabsensi = ['absensi_rekap.php', 'absensi_penilaian.php', 'absensi_izin.php', 'absensi_libur.php', 'absensi_pengaturan.php', 'sipelamar_rekap.php'];
$msipelamar = ['sipelamar_pengaturan.php', 'sipelamar_jadwal.php', 'sipelamar_rekap.php'];

$jml_izin_pending = 0;
if ($is_admin || $is_lurah || $is_kasi || $is_sekel) {
    $qp_izin = @mysqli_query($koneksi, "SELECT COUNT(*) c FROM tbl_absensi_izin WHERE status='MENUNGGU'");
    if ($qp_izin instanceof mysqli_result) { $jml_izin_pending = (int) (mysqli_fetch_assoc($qp_izin)['c'] ?? 0); }
}

$is_pengawas = $is_lurah || $is_kasi || $is_sekel;
$show_pengajuan_menu = $is_admin || $is_lurah || $is_kasi;
$show_kep_penuh = $is_admin || ($is_kasi && !$is_kasi_pemberdayaan);
$show_kep_pantau = $is_lurah || $is_sekel || $is_kasi_pemberdayaan;

$cek_pending = 0;
if ($show_pengajuan_menu) {
    $qp = @mysqli_query($koneksi, "SELECT COUNT(*) c FROM tbl_pengajuan_warga WHERE status='PENDING'");
    if ($qp instanceof mysqli_result) { $cek_pending = (int) (mysqli_fetch_assoc($qp)['c'] ?? 0); }
}
?>
<!-- ══════════ ASIDE SIDEBAR ══════════ -->
<aside class="main-sidebar">
    <button type="button" class="sb-edge-toggle" data-widget="pushmenu" title="Ciutkan / perluas menu" aria-label="Ciutkan menu">
        <i class="fas fa-chevron-left"></i>
    </button>

    <a href="index.php" class="brand-link">
        <img src="<?=$logo;?>" alt="Logo Kab">
        <span class="brand-text">SIPANDANG
            <small class="brand-sub"><?=$nama_kel;?></small>
        </span>
    </a>

    <div class="sidebar">
        <div class="user-panel">
            <div class="image">
                <img src="https://ui-avatars.com/api/?name=<?=urlencode($nama_user);?>&background=<?= ltrim(($sp_p['main'] ?? '#0b5cad'), '#') ?>&color=ffffff&bold=true&length=1"
                     class="img-circle elevation-2" alt="User">
            </div>
            <div class="info">
                <a href="#" class="d-block">
                    <?=htmlspecialchars($nama_user);?>
                </a>
                <span class="up-badge">
                    <i class="<?=$icon_level;?>" style="font-size:0.6rem;"></i> <?=$label_level;?>
                </span>
            </div>
        </div>

        <div class="sb-search">
            <i class="fas fa-search"></i>
            <input type="search" id="sbSearch" placeholder="Cari menu..." autocomplete="off" aria-label="Cari menu">
            <kbd>Ctrl K</kbd>
        </div>

        <nav class="pb-4" data-sidebar-version="v4">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
<?php
$minput = ['permohonan_siak.php','edit_siak.php','cetak_opsi.php','lihat_berkas.php','buat_surat.php','preview_surat.php','buat_surat_pbb.php','buat_surat_waris.php','preview_waris.php','proses_waris.php','buat_surat_casip.php','preview_casip.php','proses_casip.php','cetak_lembar_kelengkapan.php','titip_berkas.php','berkas_ttd_eksternal.php','berkas_ttd_eksternal_detail.php'];
$mregister = ['register_sktm.php','cetak_register_sktm.php'];
$marsip = ['data_siak.php','arsip_surat.php','arsip_detail.php','data_pemohon_tamu.php','tambah_pemohon_tamu.php','edit_pemohon_tamu.php','data_waris.php','arsip_detail_waris.php','cetak_waris.php','cetak_rekap_waris.php','proses_upload_waris.php','data_casip.php','arsip_detail_casip.php','cetak_casip.php','cetak_rekap_casip.php','proses_upload_casip.php','arsip_penduduk.php','rekap_layanan_bulanan.php','arsip_laporan_surat_keterangan.php'];
$mpersuratan = ['surat_masuk.php','agenda_surat_keluar.php','arsip_surat_masuk.php','arsip_surat_keluar_kantor.php'];
$mportal = ['pengajuan_warga.php','pengajuan_warga_detail.php','data_berita.php','tambah_berita.php','edit_berita.php','kelola_jenis_layanan.php'];
if ($is_admin) { $mportal[] = 'bantuan.php'; }
$mdb = ['import_penduduk.php'];
$mset = ['identitas.php','kelola_persyaratan.php','kelola_checklist_berkas.php','cetak_lembar_kelengkapan.php','pamong.php','setting_surat.php','pengguna.php','data_kontak.php','log_aktivitas.php','admin_panel.php','data_berita.php','tambah_berita.php','edit_berita.php','kelola_jenis_layanan.php','kelola_wa_bot.php','bantuan.php'];
$mm_mono = ['monografi.php','kelola_rt_rw.php','buat_sk_pengangkatan.php','arsip_sk_pengangkatan.php','surat_himbauan_rt_rw.php','surat_himbauan_rw_gabungan.php','arsip_himbauan_rt_rw.php'];
if ($is_admin) { $mm_mono = array_merge($mm_mono, ['kelola_akun_rtrw.php','rtrw_pendataan_admin.php','rtrw_log.php','kelola_monografi.php','cetak_monografi.php']); }
if ($is_operator) { $mm_mono = array_values(array_diff($mm_mono, ['buat_sk_pengangkatan.php','arsip_sk_pengangkatan.php','arsip_himbauan_rt_rw.php'])); }
$mp = ['penduduk.php','keluarga.php','peta_keluarga.php','laporan_bulanan.php','pendataan_admin.php','arsip_penduduk.php','rtrw_warga_luar_admin.php'];
?>

<?php if ($is_admin || $is_pengawas): ?><li class="nav-header">BERANDA</li><?php endif; ?>
<li class="nav-item">
<?php
if ($is_admin) { $dash_href='admin_panel.php'; $dash_label='Panel Administrator'; $dash_icon='user-shield'; $dash_active=['admin_panel.php']; }
elseif ($is_pkl) { $dash_href='pkl_panel.php'; $dash_label='Meja PKL'; $dash_icon='user-graduate'; $dash_active=['pkl_panel.php']; }
elseif ($is_operator) { $dash_href='operator_panel.php'; $dash_label='Meja Operator'; $dash_icon='user-cog'; $dash_active=['operator_panel.php']; }
elseif ($is_kasi) { $dash_href='kasi_panel.php'; $dash_label='Meja Kasi'; $dash_icon='user-tie'; $dash_active=['kasi_panel.php']; }
elseif ($is_lurah) { $dash_href='lurah_panel.php'; $dash_label='Meja Lurah'; $dash_icon='user-check'; $dash_active=['lurah_panel.php']; }
elseif ($is_sekel) { $dash_href='sekel_panel.php'; $dash_label='Meja Sekel'; $dash_icon='user-tie'; $dash_active=['sekel_panel.php']; }
else { $dash_href='index.php'; $dash_label='Dashboard'; $dash_icon='tachometer-alt'; $dash_active=['index.php','dashboard.php']; }
?>
    <a href="<?= $dash_href ?>" class="nav-link <?= is_active($page, $dash_active) ? 'active' : '' ?>">
        <i class="nav-icon fas fa-<?= $dash_icon ?>"></i>
        <p><?= $dash_label ?></p>
    </a>
</li>

<?php if (!$is_admin && !$is_loket && !$is_kasi && !$is_lurah && !$is_sekel): ?>
<li class="nav-item">
    <a href="dashboard.php" class="nav-link <?=is_active($page,'dashboard.php')?'active':'';?>">
        <i class="nav-icon fas fa-chart-pie"></i><p>Dashboard Pelayanan</p>
    </a>
</li>
<?php elseif ($is_pengawas): ?>
<li class="nav-item">
    <a href="dashboard.php" class="nav-link <?=is_active($page,'dashboard.php')?'active':'';?>">
        <i class="nav-icon fas fa-chart-pie"></i><p>Statistik Pelayanan</p>
    </a>
</li>
<?php endif; ?>

<?php if ($is_admin || $is_pengawas): ?>
<li class="nav-item">
    <a href="dashboard_kinerja.php" class="nav-link <?=is_active($page,'dashboard_kinerja.php')?'active':'';?>">
        <i class="nav-icon fas fa-chart-line"></i><p>Dashboard Kinerja</p>
    </a>
</li>
<?php endif; ?>

<?php if ($is_sekel && !$is_admin): ?>
<li class="nav-header">SURAT KANTOR</li>
<li class="nav-item has-treeview <?=is_open($page,$mpersuraten)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mpersuraten)?'active':'';?>">
        <i class="nav-icon fas fa-envelope-open-text"></i>
        <p>Agenda &amp; Arsip Kantor <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="surat_masuk.php" class="nav-link <?=is_active($page,'surat_masuk.php')?'active':'';?>"><i class="nav-icon fas fa-envelope"></i><p>Agenda Surat Masuk</p></a></li>
        <li class="nav-item"><a href="agenda_surat_keluar.php" class="nav-link <?=is_active($page,'agenda_surat_keluar.php')?'active':'';?>"><i class="nav-icon fas fa-paper-plane"></i><p>Agenda Surat Keluar</p></a></li>
        <li class="nav-item"><a href="arsip_surat_masuk.php" class="nav-link <?=is_active($page,'arsip_surat_masuk.php')?'active':'';?>"><i class="nav-icon fas fa-inbox"></i><p>Arsip Surat Masuk</p></a></li>
        <li class="nav-item"><a href="arsip_surat_keluar_kantor.php" class="nav-link <?=is_active($page,'arsip_surat_keluar_kantor.php')?'active':'';?>"><i class="nav-icon fas fa-share-square"></i><p>Arsip Surat Keluar</p></a></li>
    </ul>
</li>
<?php endif; ?>

<?php if ($show_pengajuan_menu && !$is_admin): ?>
<li class="nav-header">MONITORING</li>
<li class="nav-item">
    <a href="pengajuan_warga.php" class="nav-link <?=is_active($page,['pengajuan_warga.php','pengajuan_warga_detail.php'])?'active':'';?>">
        <i class="nav-icon fas fa-inbox"></i>
        <p>Pengajuan Online<?php if($cek_pending>0): ?><span class="right nav-badge nb-red"><?=$cek_pending;?></span><?php endif; ?></p>
    </a>
</li>
<?php endif; ?>

<?php if ($is_kasi_pemberdayaan): ?>
<li class="nav-header">PEMBERDAYAAN</li>
<li class="nav-item">
    <a href="bantuan.php" class="nav-link <?= is_active($page, 'bantuan.php') ? 'active' : '' ?>">
        <i class="nav-icon fas fa-hand-holding-heart"></i><p>Bantuan Sosial</p>
    </a>
</li>
<?php endif; ?>

<?php if ($is_admin || $is_pengawas): ?>
<li class="nav-header">SDM &amp; ABSENSI</li>
<li class="nav-item has-treeview <?=is_open($page,$mabsensi)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mabsensi)?'active':'';?>">
        <i class="nav-icon fas fa-user-clock"></i>
        <p>Absensi Pegawai <?php if ($jml_izin_pending > 0): ?><span class="right nav-badge nb-amber"><?=$jml_izin_pending;?></span><?php endif; ?><i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="absensi_rekap.php" class="nav-link <?=is_active($page,'absensi_rekap.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-list"></i><p>Rekap Absensi</p></a></li>
        <?php if (!$is_admin): ?><li class="nav-item"><a href="sipelamar_rekap.php" class="nav-link <?=is_active($page,'sipelamar_rekap.php')?'active':'';?>"><i class="nav-icon fas fa-moon"></i><p>Rekap SiPELAMAR</p></a></li><?php endif; ?>
        <li class="nav-item"><a href="absensi_penilaian.php" class="nav-link <?=is_active($page,'absensi_penilaian.php')?'active':'';?>"><i class="nav-icon fas fa-star"></i><p>Penilaian Absensi</p></a></li>
        <li class="nav-item"><a href="absensi_izin.php" class="nav-link <?=is_active($page,'absensi_izin.php')?'active':'';?>"><i class="nav-icon fas fa-file-medical"></i><p>Persetujuan Izin<?php if ($jml_izin_pending > 0): ?><span class="right nav-badge nb-amber"><?=$jml_izin_pending;?></span><?php endif; ?></p></a></li>
        <?php if ($is_admin): ?>
        <li class="nav-item"><a href="absensi_libur.php" class="nav-link <?=is_active($page,'absensi_libur.php')?'active':'';?>"><i class="nav-icon fas fa-calendar-day"></i><p>Kalender Libur</p></a></li>
        <li class="nav-item"><a href="absensi_pengaturan.php" class="nav-link <?=is_active($page,'absensi_pengaturan.php')?'active':'';?>"><i class="nav-icon fas fa-fingerprint"></i><p>Pengaturan Absensi</p></a></li>
        <li class="nav-item"><a href="pegawai/login.php" target="_blank" class="nav-link"><i class="nav-icon fas fa-mobile-alt"></i><p>Portal Pegawai</p></a></li>
        <?php endif; ?>
    </ul>
</li>
<?php if ($is_admin): ?>
<li class="nav-item has-treeview <?= is_open($page, $msipelamar) ? 'menu-open' : '' ?>">
    <a href="#" class="nav-link <?= is_active($page, $msipelamar) ? 'active' : '' ?>">
        <i class="nav-icon fas fa-moon"></i><p>SiPELAMAR<i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="sipelamar_pengaturan.php" class="nav-link <?= is_active($page, 'sipelamar_pengaturan.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-cog"></i><p>Pengaturan &amp; Jam</p></a></li>
        <li class="nav-item"><a href="sipelamar_jadwal.php" class="nav-link <?= is_active($page, 'sipelamar_jadwal.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-calendar-alt"></i><p>Jadwal Piket</p></a></li>
        <li class="nav-item"><a href="sipelamar_rekap.php" class="nav-link <?= is_active($page, 'sipelamar_rekap.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-chart-bar"></i><p>Rekap Malam</p></a></li>
    </ul>
</li>
<?php endif; ?>
<?php endif; ?>

<?php if ($is_admin): ?>
<li class="nav-header">ADMINISTRATOR</li>
<li class="nav-item"><a href="log_aktivitas.php" class="nav-link <?=is_active($page,'log_aktivitas.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-list"></i><p>Log Aktivitas</p></a></li>
<li class="nav-item"><a href="pengajuan_warga.php" class="nav-link <?=is_active($page,['pengajuan_warga.php','pengajuan_warga_detail.php'])?'active':'';?>"><i class="nav-icon fas fa-inbox"></i><p>Pengajuan Online<?php if($cek_pending>0): ?><span class="right nav-badge nb-red"><?=$cek_pending;?></span><?php endif; ?></p></a></li>
<li class="nav-item has-treeview <?=is_open($page,$mset)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mset)?'active':'';?>">
        <i class="nav-icon fas fa-tools"></i><p>Pengaturan Sistem <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="pengguna.php" class="nav-link <?=is_active($page,'pengguna.php')?'active':'';?>"><i class="nav-icon fas fa-users-cog"></i><p>Manajemen Pengguna</p></a></li>
        <li class="nav-item"><a href="identitas.php" class="nav-link <?=is_active($page,'identitas.php')?'active':'';?>"><i class="nav-icon fas fa-building"></i><p>Identitas Kelurahan</p></a></li>
        <li class="nav-item"><a href="kelola_persyaratan.php" class="nav-link <?=is_active($page,'kelola_persyaratan.php')?'active':'';?>"><i class="nav-icon fas fa-link"></i><p>Persyaratan &amp; Link IG</p></a></li>
        <li class="nav-item"><a href="kelola_checklist_berkas.php" class="nav-link <?=is_active($page,'kelola_checklist_berkas.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-check"></i><p>Checklist Berkas Surat</p></a></li>
        <li class="nav-item"><a href="pamong.php" class="nav-link <?=is_active($page,'pamong.php')?'active':'';?>"><i class="nav-icon fas fa-user-friends"></i><p>Perangkat / TTD</p></a></li>
        <li class="nav-item"><a href="setting_surat.php" class="nav-link <?=is_active($page,'setting_surat.php')?'active':'';?>"><i class="nav-icon fas fa-envelope-open-text"></i><p>Template Surat</p></a></li>
        <li class="nav-item"><a href="data_kontak.php" class="nav-link <?=is_active($page,'data_kontak.php')?'active':'';?>"><i class="nav-icon fas fa-phone"></i><p>Kontak Darurat</p></a></li>
        <li class="nav-item"><a href="kelola_jenis_layanan.php" class="nav-link <?=is_active($page,'kelola_jenis_layanan.php')?'active':'';?>"><i class="nav-icon fas fa-cogs"></i><p>Jenis Layanan App</p></a></li>
        <li class="nav-item"><a href="kelola_wa_bot.php" class="nav-link <?=is_active($page,'kelola_wa_bot.php')?'active':'';?>"><i class="nav-icon fab fa-whatsapp"></i><p>Asisten WhatsApp</p></a></li>
        <li class="nav-item"><a href="data_berita.php" class="nav-link <?=is_active($page,['data_berita.php','tambah_berita.php','edit_berita.php'])?'active':'';?>"><i class="nav-icon fas fa-bullhorn"></i><p>Info &amp; Pengumuman</p></a></li>
        <li class="nav-item"><a href="bantuan.php" class="nav-link <?=is_active($page,'bantuan.php')?'active':'';?>"><i class="nav-icon fas fa-hand-holding-heart"></i><p>Bantuan Sosial</p></a></li>
    </ul>
</li>
<li class="nav-item has-treeview <?=is_open($page,$mdb)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mdb)?'active':'';?>">
        <i class="nav-icon fas fa-database"></i><p>Database System <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="import_penduduk.php" class="nav-link <?=is_active($page,'import_penduduk.php')?'active':'';?>"><i class="nav-icon far fa-file-excel"></i><p>Import Excel</p></a></li>
    </ul>
</li>
<?php endif; ?>

<?php if ($is_admin): ?>
<li class="nav-header">SURAT KANTOR</li>
<li class="nav-item has-treeview <?=is_open($page,$mpersuraten)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mpersuraten)?'active':'';?>">
        <i class="nav-icon fas fa-envelope-open-text"></i><p>Agenda &amp; Arsip Kantor <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="surat_masuk.php" class="nav-link <?=is_active($page,'surat_masuk.php')?'active':'';?>"><i class="nav-icon fas fa-envelope"></i><p>Agenda Surat Masuk</p></a></li>
        <li class="nav-item"><a href="agenda_surat_keluar.php" class="nav-link <?=is_active($page,'agenda_surat_keluar.php')?'active':'';?>"><i class="nav-icon fas fa-paper-plane"></i><p>Agenda Surat Keluar</p></a></li>
        <li class="nav-item"><a href="arsip_surat_masuk.php" class="nav-link <?=is_active($page,'arsip_surat_masuk.php')?'active':'';?>"><i class="nav-icon fas fa-inbox"></i><p>Arsip Surat Masuk</p></a></li>
        <li class="nav-item"><a href="arsip_surat_keluar_kantor.php" class="nav-link <?=is_active($page,'arsip_surat_keluar_kantor.php')?'active':'';?>"><i class="nav-icon fas fa-share-square"></i><p>Arsip Surat Keluar</p></a></li>
    </ul>
</li>
<?php endif; ?>

<?php if ($is_loket || $is_admin): ?>
<li class="nav-header">OPERASIONAL LOKET</li>
<li class="nav-item">
    <a href="antrian_loket.php" class="nav-link <?= is_active($page, ['antrian_loket.php', 'antrian_tv.php', 'antrian_daftar.php', 'antrian_pengaturan.php', 'antrian_kelola.php', 'cetak_antrian_hadir.php']) ? 'active' : '' ?>">
        <i class="nav-icon fas fa-ticket-alt"></i><p>Antrean Loket</p>
    </a>
</li>
<?php if ($is_pkl): ?>
<li class="nav-item"><a href="pegawai/login.php" target="_blank" class="nav-link"><i class="nav-icon fas fa-mobile-alt"></i><p>App Absensi</p></a></li>
<?php endif; ?>
<?php endif; ?>

<?php if(!$is_lurah && !$is_kasi && !$is_sekel): ?>
<?php if ($is_admin): ?><li class="nav-header">PELAYANAN (BACKUP)</li><?php else: ?><li class="nav-header">PENGINPUTAN</li><?php endif; ?>
<li class="nav-item has-treeview <?=is_open($page,$minput)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$minput)?'active':'';?>">
        <i class="nav-icon fas fa-edit"></i><p>Form Pelayanan <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="permohonan_siak.php" class="nav-link <?=is_active($page,['permohonan_siak.php','edit_siak.php'])?'active':'';?>"><i class="nav-icon fas fa-id-card"></i><p>Input Permohonan SIAK<?php if($jml_draft>0): ?><span class="right nav-badge nb-amber"><?=$jml_draft;?></span><?php endif; ?></p></a></li>
        <li class="nav-item"><a href="buat_surat.php" class="nav-link <?=is_active($page,['buat_surat.php','preview_surat.php'])?'active':'';?>"><i class="nav-icon fas fa-file-signature"></i><p>Buat Surat Keluar</p></a></li>
        <li class="nav-item"><a href="buat_surat_pbb.php" class="nav-link <?=is_active($page,'buat_surat_pbb.php')?'active':'';?>"><i class="nav-icon fas fa-file-invoice"></i><p>Mutasi PBB / SPPT</p></a></li>
        <li class="nav-item"><a href="buat_surat_waris.php" class="nav-link <?=is_active($page,['buat_surat_waris.php','preview_waris.php','proses_waris.php'])?'active':'';?>"><i class="nav-icon fas fa-users"></i><p>Surat Waris</p></a></li>
        <li class="nav-item"><a href="buat_surat_casip.php" class="nav-link <?=is_active($page,['buat_surat_casip.php','preview_casip.php','proses_casip.php'])?'active':'';?>"><i class="nav-icon fas fa-ring"></i><p>Paket Nikah CASIP</p></a></li>
        <li class="nav-item"><a href="cetak_lembar_kelengkapan.php" class="nav-link <?=is_active($page,'cetak_lembar_kelengkapan.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-list"></i><p>Cetak Lembar Kelengkapan</p></a></li>
        <li class="nav-item"><a href="titip_berkas.php" class="nav-link <?=is_active($page,'titip_berkas.php')?'active':'';?>"><i class="nav-icon fas fa-box-open"></i><p>Berkas Ditinggal</p></a></li>
        <li class="nav-item"><a href="berkas_ttd_eksternal.php" class="nav-link <?=is_active($page,['berkas_ttd_eksternal.php','berkas_ttd_eksternal_detail.php'])?'active':'';?>"><i class="nav-icon fas fa-stamp"></i><p>Berkas TTD Eksternal</p></a></li>
    </ul>
</li>
<li class="nav-header">REGISTER</li>
<li class="nav-item has-treeview <?=is_open($page,$mregister)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mregister)?'active':'';?>">
        <i class="nav-icon fas fa-book"></i><p>Register <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="register_sktm.php" class="nav-link <?=is_active($page,['register_sktm.php','cetak_register_sktm.php'])?'active':'';?>"><i class="nav-icon fas fa-hand-holding-heart"></i><p>Register SKTM</p></a></li>
    </ul>
</li>
<?php endif; ?>

<li class="nav-header">ARSIP PELAYANAN</li>
<li class="nav-item has-treeview <?=is_open($page,$marsip)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$marsip)?'active':'';?>">
        <i class="nav-icon fas fa-archive"></i><p>Arsip Layanan <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="data_siak.php" class="nav-link <?=is_active($page,'data_siak.php')?'active':'';?>"><i class="nav-icon fas fa-id-card"></i><p>Arsip Permohonan SIAK</p></a></li>
        <li class="nav-item"><a href="arsip_surat.php" class="nav-link <?=is_active($page,'arsip_surat.php')?'active':'';?>"><i class="nav-icon fas fa-file-signature"></i><p>Arsip Surat Pelayanan</p></a></li>
        <li class="nav-item"><a href="data_pemohon_tamu.php" class="nav-link <?=is_active($page,['data_pemohon_tamu.php','tambah_pemohon_tamu.php','edit_pemohon_tamu.php'])?'active':'';?>"><i class="nav-icon fas fa-file-invoice"></i><p>Arsip Mutasi PBB</p></a></li>
        <li class="nav-item"><a href="data_waris.php" class="nav-link <?=is_active($page,['data_waris.php','arsip_detail_waris.php'])?'active':'';?>"><i class="nav-icon fas fa-users"></i><p>Arsip Surat Waris</p></a></li>
        <li class="nav-item"><a href="data_casip.php" class="nav-link <?=is_active($page,['data_casip.php','arsip_detail_casip.php'])?'active':'';?>"><i class="nav-icon fas fa-ring"></i><p>Arsip Paket CASIP</p></a></li>
        <li class="nav-item"><a href="arsip_penduduk.php" class="nav-link <?=is_active($page,'arsip_penduduk.php')?'active':'';?>"><i class="nav-icon fas fa-user-slash"></i><p>Arsip Mutasi Penduduk</p></a></li>
        <li class="nav-item"><a href="rekap_layanan_bulanan.php" class="nav-link <?=is_active($page,'rekap_layanan_bulanan.php')?'active':'';?>"><i class="nav-icon fas fa-table"></i><p>Rekap Semua Pelayanan</p></a></li>
        <li class="nav-item"><a href="arsip_laporan_surat_keterangan.php" class="nav-link <?=is_active($page,'arsip_laporan_surat_keterangan.php')?'active':'';?>"><i class="nav-icon fas fa-file-excel"></i><p>Rekap Surat Keterangan dan Pengantar</p></a></li>
    </ul>
</li>

<li class="nav-header">MONOGRAFI &amp; RT/RW</li>
<li class="nav-item has-treeview <?=is_open($page,$mm_mono)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mm_mono)?'active':'';?>">
        <i class="nav-icon fas fa-atlas"></i><p>Data Monografi <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="monografi.php" class="nav-link <?=is_active($page,'monografi.php')?'active':'';?>"><i class="nav-icon fas fa-chart-pie"></i><p>Dashboard Statistik</p></a></li>
        <?php if ($is_admin): ?>
        <li class="nav-item"><a href="kelola_monografi.php" class="nav-link <?=is_active($page,'kelola_monografi.php')?'active':'';?>"><i class="nav-icon fas fa-keyboard"></i><p>Input Data Monografi</p></a></li>
        <li class="nav-item"><a href="cetak_monografi.php" target="_blank" class="nav-link"><i class="nav-icon fas fa-print"></i><p>Cetak Laporan</p></a></li>
        <?php endif; ?>
        <li class="nav-item"><a href="kelola_rt_rw.php" class="nav-link <?=is_active($page,'kelola_rt_rw.php')?'active':'';?>"><i class="nav-icon fas fa-sitemap"></i><p>Pengurus RT/RW</p></a></li>
        <?php if ($is_admin): ?>
        <li class="nav-item"><a href="kelola_akun_rtrw.php" class="nav-link <?=is_active($page,'kelola_akun_rtrw.php')?'active':'';?>"><i class="nav-icon fas fa-key"></i><p>Akun Portal RT/RW</p></a></li>
        <li class="nav-item"><a href="rtrw_pendataan_admin.php" class="nav-link <?=is_active($page,'rtrw_pendataan_admin.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-list"></i><p>Antrean RT/RW</p></a></li>
        <li class="nav-item"><a href="rtrw_log.php" class="nav-link <?=is_active($page,'rtrw_log.php')?'active':'';?>"><i class="nav-icon fas fa-history"></i><p>Log Portal RT/RW</p></a></li>
        <?php endif; ?>
        <?php if (!$is_lurah && !$is_sekel && !$is_operator): ?>
        <li class="nav-item"><a href="buat_sk_pengangkatan.php" class="nav-link <?=is_active($page,'buat_sk_pengangkatan.php')?'active':'';?>"><i class="nav-icon fas fa-file-signature"></i><p>Buat SK Pengangkatan</p></a></li>
        <?php endif; ?>
        <?php if (!$is_operator): ?>
        <li class="nav-item"><a href="arsip_sk_pengangkatan.php" class="nav-link <?=is_active($page,'arsip_sk_pengangkatan.php')?'active':'';?>"><i class="nav-icon fas fa-archive"></i><p>Arsip SK Pengangkatan</p></a></li>
        <li class="nav-item"><a href="arsip_himbauan_rt_rw.php" class="nav-link <?=is_active($page,'arsip_himbauan_rt_rw.php')?'active':'';?>"><i class="nav-icon fas fa-envelope-open-text"></i><p>Arsip Himbauan</p></a></li>
        <?php endif; ?>
    </ul>
</li>

<?php if ($show_kep_penuh): ?>
<li class="nav-header">KEPENDUDUKAN</li>
<li class="nav-item has-treeview <?=is_open($page,$mp)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mp)?'active':'';?>">
        <i class="nav-icon fas fa-users"></i><p>Data Penduduk <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="penduduk.php" class="nav-link <?=is_active($page,'penduduk.php')?'active':'';?>"><i class="nav-icon fas fa-user"></i><p>Penduduk Aktif</p></a></li>
        <?php if ($is_admin): ?><li class="nav-item"><a href="rtrw_warga_luar_admin.php" class="nav-link <?=is_active($page,'rtrw_warga_luar_admin.php')?'active':'';?>"><i class="nav-icon fas fa-id-card-alt"></i><p>Warga Non-Medang</p></a></li><?php endif; ?>
        <li class="nav-item"><a href="pendataan_admin.php" class="nav-link <?=is_active($page,'pendataan_admin.php')?'active':'';?>"><i class="nav-icon fas fa-clipboard-check"></i><p>Pendataan Warga</p></a></li>
        <li class="nav-item"><a href="keluarga.php" class="nav-link <?=is_active($page,'keluarga.php')?'active':'';?>"><i class="nav-icon fas fa-address-card"></i><p>Kartu Keluarga</p></a></li>
        <li class="nav-item"><a href="peta_keluarga.php" class="nav-link <?=is_active($page,'peta_keluarga.php')?'active':'';?>"><i class="nav-icon fas fa-map-marked-alt"></i><p>Peta Sebaran Keluarga</p></a></li>
        <li class="nav-item"><a href="laporan_bulanan.php" class="nav-link <?=is_active($page,'laporan_bulanan.php')?'active':'';?>"><i class="nav-icon fas fa-chart-line"></i><p>Laporan Bulanan</p></a></li>
    </ul>
</li>
<?php elseif ($show_kep_pantau): ?>
<li class="nav-header">DATA &amp; LAPORAN</li>
<li class="nav-item has-treeview <?=is_open($page,$mp)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mp)?'active':'';?>">
        <i class="nav-icon fas fa-chart-bar"></i><p>Monitoring Data <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <?php if ($is_lurah || ($is_kasi && $is_kasipem)): ?><li class="nav-item"><a href="penduduk.php" class="nav-link <?=is_active($page,'penduduk.php')?'active':'';?>"><i class="nav-icon fas fa-user"></i><p>Penduduk Aktif</p></a></li><?php endif; ?>
        <li class="nav-item"><a href="laporan_bulanan.php" class="nav-link <?=is_active($page,'laporan_bulanan.php')?'active':'';?>"><i class="nav-icon fas fa-chart-line"></i><p>Laporan Bulanan</p></a></li>
        <?php if ($is_lurah || $is_sekel || ($is_kasi && $is_kasipem)): ?><li class="nav-item"><a href="arsip_penduduk.php" class="nav-link <?=is_active($page,'arsip_penduduk.php')?'active':'';?>"><i class="nav-icon fas fa-user-slash"></i><p>Arsip Mutasi Penduduk</p></a></li><?php endif; ?>
    </ul>
</li>
<?php endif; ?>

<?php if(!$is_lurah && !$is_admin && !$is_sekel && !$is_kasi): ?>
<li class="nav-header">PORTAL WARGA</li>
<li class="nav-item has-treeview <?=is_open($page,$mportal)?'menu-open':'';?>">
    <a href="#" class="nav-link <?=is_active($page,$mportal)?'active':'';?>">
        <i class="nav-icon fas fa-mobile-alt"></i><p>Layanan Online <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="pengajuan_warga.php" class="nav-link <?=is_active($page,['pengajuan_warga.php','pengajuan_warga_detail.php'])?'active':'';?>"><i class="nav-icon fas fa-inbox"></i><p>Pengajuan Online<?php if($cek_pending>0): ?><span class="right nav-badge nb-red"><?=$cek_pending;?></span><?php endif; ?></p></a></li>
    </ul>
</li>
<?php endif; ?>

<div class="sb-divider"></div>
<li class="nav-header">AKUN SAYA</li>
<li class="nav-item"><a href="pengaturan_user.php" class="nav-link <?=is_active($page,'pengaturan_user.php')?'active':'';?>"><i class="nav-icon fas fa-user-cog"></i><p>Pengaturan Akun</p></a></li>
<li class="nav-item mb-5">
    <a href="logout.php" class="nav-link" onclick="return confirm('Yakin ingin keluar dari sistem?')">
        <i class="nav-icon fas fa-power-off"></i><p>Keluar / Logout</p>
    </a>
</li>
            </ul>
        </nav>
    </div>
</aside>

<script>
(function () {
    var aside = document.querySelector('.main-sidebar');
    if (!aside) return;
    function textOf(link) {
        var p = link.querySelector('p');
        if (!p) return '';
        var clone = p.cloneNode(true);
        clone.querySelectorAll('i, .nav-badge, span').forEach(function (el) { el.remove(); });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }
    function collapsed() {
        return document.body.classList.contains('sidebar-collapse') && window.innerWidth >= 992;
    }
    var fly = document.createElement('div');
    fly.className = 'sb-flyout';
    fly.setAttribute('hidden', '');
    document.body.appendChild(fly);
    var hideTimer = null;
    var activeItem = null;
    function hideFly() {
        fly.classList.remove('is-open', 'is-tip');
        fly.setAttribute('hidden', '');
        fly.innerHTML = '';
        activeItem = null;
    }
    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hideFly, 140);
    }
    function placeFly(item) {
        var link = item.querySelector(':scope > .nav-link') || item;
        var rail = aside.getBoundingClientRect();
        var rect = link.getBoundingClientRect();
        var top = Math.max(12, Math.min(rect.top, window.innerHeight - fly.offsetHeight - 12));
        fly.style.left = Math.round(rail.right + 4) + 'px';
        fly.style.top = top + 'px';
    }
    function showFly(item) {
        if (!collapsed()) { hideFly(); return; }
        var link = item.querySelector(':scope > .nav-link');
        if (!link) return;
        clearTimeout(hideTimer);
        activeItem = item;
        var tree = item.querySelector(':scope > .nav-treeview');
        var label = textOf(link);
        fly.removeAttribute('hidden');
        fly.classList.remove('is-tip');
        if (tree) {
            var hd = document.createElement('div');
            hd.className = 'sb-flyout-hd';
            hd.textContent = label || 'Menu';
            fly.innerHTML = '';
            fly.appendChild(hd);
            tree.querySelectorAll(':scope > .nav-item > .nav-link').forEach(function (sub) {
                var a = document.createElement('a');
                a.href = sub.getAttribute('href') || '#';
                if (sub.getAttribute('target')) a.target = sub.getAttribute('target');
                if (sub.classList.contains('active')) a.classList.add('is-active');
                var ico = sub.querySelector('.nav-icon');
                if (ico) a.appendChild(ico.cloneNode(true));
                a.appendChild(document.createTextNode(textOf(sub)));
                var badge = sub.querySelector('.nav-badge');
                if (badge) a.appendChild(badge.cloneNode(true));
                fly.appendChild(a);
            });
        } else {
            fly.classList.add('is-tip');
            fly.textContent = label;
        }
        fly.classList.add('is-open');
        placeFly(item);
        requestAnimationFrame(function () { placeFly(item); });
    }
    aside.querySelectorAll('.nav-sidebar > .nav-item').forEach(function (item) {
        var link = item.querySelector(':scope > .nav-link');
        if (!link) return;
        link.addEventListener('mouseenter', function () { if (collapsed()) showFly(item); });
        link.addEventListener('mouseleave', scheduleHide);
        link.addEventListener('focusin', function () { if (collapsed()) showFly(item); });
        link.addEventListener('focusout', scheduleHide);
        if (item.classList.contains('has-treeview')) {
            link.addEventListener('click', function (e) {
                if (!collapsed()) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                showFly(item);
            }, true);
        }
    });
    fly.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
    fly.addEventListener('mouseleave', scheduleHide);
    aside.addEventListener('mouseenter', function () { aside.classList.remove('sidebar-focused'); }, true);
    document.addEventListener('click', function (e) { if (!fly.contains(e.target)) hideFly(); });
    window.addEventListener('scroll', hideFly, true);
    window.addEventListener('resize', hideFly);
    if (window.jQuery) { jQuery(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', hideFly); }
})();
</script>