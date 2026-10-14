<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_pelayanan_input();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_pemohon_tamu.php';
require_once __DIR__ . '/inc_pemohon_tamu_form.php';
require_once __DIR__ . '/inc_arsip_ui.php';

pemohon_tamu_ensure_schema($koneksi);

$err = trim((string) ($_GET['err'] ?? ''));

include 'header.php';
include 'sidebar.php';
arsip_ui_assets();
arsip_ui_styles();
pemohon_tamu_form_styles();
?>
<div class="content-wrapper">
<?php arsip_ui_header('fa-user-plus', 'TAMBAH PEMOHON TAMU', 'Registrasi pemohon luar wilayah untuk PBB / SPPT', arsip_ui_tgl_indo()); ?>

<section class="content"><div class="container-fluid"><div class="arsip-wrapper">

<?php if ($err !== ''): ?>
<div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;"><?= htmlspecialchars($err); ?></div>
<?php endif; ?>

<div class="filter-card" style="margin-bottom:16px;">
    <p style="margin:0;font-size:.82rem;color:var(--muted);">
        <i class="fas fa-info-circle" style="color:var(--accent);"></i>
        NIK 16 digit wajib unik. Jika NIK sudah terdaftar sebagai warga Medang, gunakan menu pencarian warga biasa.
    </p>
</div>

<?php pemohon_tamu_form_render($koneksi, null, 'proses_pemohon_tamu.php', false); ?>

</div></div></section></div>

<?php
pemohon_tamu_form_script([]);
include 'footer.php';
