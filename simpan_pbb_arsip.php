<?php
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_guard_admin.php';
require_once __DIR__ . '/inc_surat_pbb.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: arsip_surat.php');
    exit;
}

$id_surat = (int) ($_POST['id_surat'] ?? 0);
if ($id_surat <= 0) {
    header('Location: arsip_surat.php');
    exit;
}

$data = surat_pbb_from_post($_POST);
if (!surat_pbb_has_data($data)) {
    header("Location: arsip_detail.php?id=$id_surat&pbb=kosong");
    exit;
}

$old = surat_pbb_load($koneksi, $id_surat);
surat_pbb_save($koneksi, $id_surat, $data);
surat_pbb_sync_isi_surat($koneksi, $id_surat, $old, $data);
header("Location: arsip_detail.php?id=$id_surat&toast=pbb");
exit;
