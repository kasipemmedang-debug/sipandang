<?php
require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include 'koneksi.php';
require_once __DIR__ . '/inc_format_editor.php';

$isi_default = (isset($_GET['preset']) && $_GET['preset'] === 'keramaian')
    ? format_editor_keramaian_isi()
    : format_editor_default_isi();
$prefill_kode = (isset($_GET['preset']) && $_GET['preset'] === 'keramaian') ? '300' : '';
$prefill_nama = (isset($_GET['preset']) && $_GET['preset'] === 'keramaian') ? 'Surat Pengantar Izin Keramaian' : '';

include 'header.php';
include 'sidebar.php';
format_editor_print_styles();
?>

<div class="content-wrapper">
<div class="pg-hdr">
    <div class="container-fluid" style="position:relative;z-index:1;">
        <span class="pg-badge"><i class="fas fa-plus-circle"></i> TEMPLATE SURAT</span>
        <h1>Tambah Format Surat Baru</h1>
        <p>Buat template surat keluar — variabel otomatis terisi saat cetak dari Buat Surat</p>
    </div>
</div>

<section class="content"><div class="container-fluid"><div class="pg-wrap">

    <form action="proses_tambah_format.php" method="POST" id="formTambahFormat">
        <div class="fmt-grid">
            <div>
                <div class="fmt-card">
                    <div class="fmt-card-hd"><h3><i class="fas fa-file-alt mr-2"></i>Data &amp; Naskah Template</h3></div>
                    <div class="fmt-card-bd">
                        <div class="row-f">
                            <div>
                                <label class="lbl-fld">Kode Surat</label>
                                <input type="text" name="kode_surat" class="inp-f" placeholder="470" value="<?= htmlspecialchars($prefill_kode); ?>" required>
                            </div>
                            <div>
                                <label class="lbl-fld">Nama Surat</label>
                                <input type="text" name="nama_surat" class="inp-f" placeholder="Surat Keterangan ..." value="<?= htmlspecialchars($prefill_nama); ?>" required>
                            </div>
                            <div>
                                <label class="lbl-fld">Tipe Layanan</label>
                                <select name="tipe_layanan" class="inp-f"><?= format_editor_tipe_options('UMUM'); ?></select>
                            </div>
                            <div>
                                <label class="lbl-fld">Kop Surat?</label>
                                <select name="kop_otomatis" class="inp-f">
                                    <option value="Ya">Ya</option>
                                    <option value="Tidak">Tidak</option>
                                </select>
                            </div>
                        </div>

                        <?php format_editor_render_isi_field($isi_default); ?>

                        <div class="fmt-actions">
                            <button type="button" class="btn-p bp-fix" onclick="formatEditorBersihkanWilayah()">
                                <i class="fas fa-broom"></i> Bersihkan KEL/KEC ganda
                            </button>
                            <button type="button" class="btn-p bp-prev" onclick="formatEditorPreview()">
                                <i class="fas fa-eye"></i> Lihat Preview
                            </button>
                            <button type="submit" class="btn-p bp-save">
                                <i class="fas fa-save"></i> Simpan Format Baru
                            </button>
                            <a href="setting_surat.php" class="btn-p bp-back">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php format_editor_render_variable_panel(); ?>
        </div>
    </form>

</div></div></section>
</div>

<div class="modal fade modal-preview" id="modalPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Preview Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="kertas-preview" id="areaSimulasi"></div>
            </div>
        </div>
    </div>
</div>

<?php
format_editor_render_scripts($koneksi);
include 'footer.php';
?>
