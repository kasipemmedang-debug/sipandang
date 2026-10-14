<?php
require_once __DIR__ . '/inc_guard_admin.php';
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_format_surat_resmi.php';
require_once __DIR__ . '/inc_surat_keperluan_paragraf.php';
require_once __DIR__ . '/inc_verifikasi_qr.php';
require_once __DIR__ . '/inc_surat_nikah_pengantar.php';
sipandang_nikah_pengantar_format_ensure($koneksi);
require_once __DIR__ . '/inc_belum_menikah.php';
require_once __DIR__ . '/inc_alur_ttd.php';
$kop_cetak = sipandang_kop_kelurahan_data($koneksi);
alur_ttd_ensure_schema($koneksi);
sipandang_surat_keluar_ensure_revisi_reprint($koneksi);

// --- 1. LOGIKA PENERIMAAN DATA (POST atau GET) ---
$data = null;
$id_pamong_baru_for_save = 0;

if (isset($_POST['id_surat']) && (int) $_POST['id_surat'] > 0) {
    // SKENARIO 1: Akses dari Arsip Detail (pakai ID agar tidak salah ambil jika nomor surat dobel)
    $id_surat = (int) $_POST['id_surat'];
    $query = "SELECT a.*, c.nama_surat, c.tipe_layanan, c.kop_otomatis, c.kode_surat 
              FROM tbl_surat_keluar a 
              LEFT JOIN tbl_format_surat c ON a.id_jenis = c.id_format 
              WHERE a.id_surat = '$id_surat'
              LIMIT 1";
} elseif (isset($_POST['no_surat_lama'])) {
    // SKENARIO 1: Akses dari Arsip Detail
    $no_surat = mysqli_real_escape_string($koneksi, $_POST['no_surat_lama']);
    $query = "SELECT a.*, c.nama_surat, c.tipe_layanan, c.kop_otomatis, c.kode_surat 
              FROM tbl_surat_keluar a 
              LEFT JOIN tbl_format_surat c ON a.id_jenis = c.id_format 
              WHERE a.no_surat = '$no_surat'
              ORDER BY a.id_surat DESC
              LIMIT 1";
} elseif (isset($_GET['id'])) {
    // SKENARIO 2: Akses dari Tombol Cetak Cepat
    $id_surat = intval($_GET['id']);
    $query = "SELECT a.*, c.nama_surat, c.tipe_layanan, c.kop_otomatis, c.kode_surat 
              FROM tbl_surat_keluar a 
              LEFT JOIN tbl_format_surat c ON a.id_jenis = c.id_format 
              WHERE a.id_surat = '$id_surat'
              LIMIT 1";
} else {
    echo "<script>alert('Akses ilegal! Data tidak valid.'); window.close();</script>";
    exit;
}

$q_exec = mysqli_query($koneksi, $query);
$data   = mysqli_fetch_array($q_exec);

if (!$data) {
    echo "<script>alert('Data surat tidak ditemukan di database!'); window.close();</script>";
    exit;
}

if (!alur_ttd_boleh_cetak($data['status_surat'] ?? '')) {
    $lbl = alur_ttd_label_status($data['status_surat'] ?? '');
    echo "<script>alert('Surat belum siap dicetak. Status: " . addslashes($lbl) . "'); window.history.back();</script>";
    exit;
}

// AMBIL ID SURAT & ISI SURAT
$id_surat_db  = $data['id_surat'];
$html_content = $data['isi_surat'];

// --- 2. LOGIKA PERBAIKAN FORMAT OTOMATIS (AUTO FIX) ---

// A. GANTI TTD (Jika ada request ganti pejabat dari Arsip Detail)
if (isset($_POST['ttd_pejabat']) && !empty($_POST['ttd_pejabat'])) {
    $id_pejabat_baru = $_POST['ttd_pejabat'];
    $id_pejabat_lama = $data['id_pamong']; 

    if ($id_pejabat_baru != $id_pejabat_lama) {
        $q_old = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_pejabat_lama'");
        $d_old = mysqli_fetch_array($q_old);
        $q_new = mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_pejabat_baru'");
        $d_new = mysqli_fetch_array($q_new);

        if ($d_old && $d_new) {
            $html_content = str_replace(strtoupper($d_old['nama_pamong']), strtoupper($d_new['nama_pamong']), $html_content);
            $html_content = str_replace($d_old['nama_pamong'], $d_new['nama_pamong'], $html_content);
            $html_content = str_replace($d_old['nip'], $d_new['nip'], $html_content);
            if (trim($d_old['jabatan']) != trim($d_new['jabatan'])) {
                $html_content = str_replace(strtoupper($d_old['jabatan']), strtoupper($d_new['jabatan']), $html_content);
            }
            $id_pamong_baru_for_save = (int) $id_pejabat_baru;
        }
    }
}

// B. VALIDASI KOSONG
if (empty($html_content)) {
    $html_content = "<center><br><br><h3>DATA KOSONG</h3><p>Isi surat ini tidak tersimpan dengan benar di database.</p></center>";
}

// C. Kop arsip mengikuti Identitas (PBB tamu & surat pelayanan yang menyimpan kop di isi_surat)
$tipe_layanan = isset($data['tipe_layanan']) ? strtoupper(trim($data['tipe_layanan'])) : 'UMUM';
$pnArsip = sipandang_pengantar_nikah_siapkan_arsip($koneksi, $data, $html_content);
$html_content = $pnArsip['html'];
$is_pengantar_nikah = !empty($pnArsip['is_pengantar']);
$is_belum_menikah = belum_menikah_is_surat($data) && !$is_pengantar_nikah;
$kop_otomatis_reprint = $pnArsip['kop_efektif'];
if (!$is_pengantar_nikah) {
    $html_content = sipandang_surat_terapkan_kop_identitas(
        $html_content,
        $koneksi,
        $kop_otomatis_reprint
    );
}
if (!empty($is_belum_menikah)) {
    if ($id_pamong_baru_for_save > 0) {
        $data['id_pamong'] = $id_pamong_baru_for_save;
    }
    $rerender = null;
    if (function_exists('belum_menikah_rerender_arsip')) {
        $rerender = belum_menikah_rerender_arsip($koneksi, $data);
    }
    if ($rerender !== null && $rerender !== '') {
        $html_content = $rerender;
    } elseif (function_exists('belum_menikah_normalize_saved_html')) {
        $html_content = belum_menikah_normalize_saved_html($html_content);
    }
}
if ($tipe_layanan === 'KEMATIAN') {
    $html_content = sipandang_surat_kematian_siapkan_arsip(
        $html_content,
        $koneksi,
        (string) ($data['nik_pemohon'] ?? '')
    );
}
if ($tipe_layanan !== 'GOIB'
    && $tipe_layanan !== 'KEMATIAN'
    && stripos((string) ($data['nama_surat'] ?? ''), 'GOIB') === false
    && !$is_belum_menikah) {
    $html_content = sipandang_surat_normalisasi_paragraf_keperluan($html_content);
}
if ($tipe_layanan !== 'KEMATIAN' && !$is_pengantar_nikah && function_exists('sipandang_surat_normalisasi_layout_isi')) {
    $html_content = sipandang_surat_normalisasi_layout_isi($html_content);
} elseif ($tipe_layanan !== 'KEMATIAN' && !$is_pengantar_nikah && function_exists('sipandang_surat_normalisasi_indent_isi')) {
    $html_content = sipandang_surat_normalisasi_indent_isi($html_content);
}
if (function_exists('sipandang_surat_pbb_normalisasi_ttd_html')) {
    $html_content = sipandang_surat_pbb_normalisasi_ttd_html($html_content, $koneksi, $data);
}
if (function_exists('sipandang_surat_normalisasi_ttd_nama_html')) {
    $html_content = sipandang_surat_normalisasi_ttd_nama_html($html_content, $koneksi, $data);
}
// TTD digital — setelah footer TTD dinormalisasi agar overlay cocok pola nama
if (!empty($data['ttd_digital'])) {
    $idPmTtd = $id_pamong_baru_for_save > 0
        ? $id_pamong_baru_for_save
        : (int) ($data['id_pamong'] ?? 0);
    $metode = function_exists('alur_ttd_metode_dari_row')
        ? alur_ttd_metode_dari_row($data, $koneksi, $idPmTtd)
        : 'gambar';
    $html_content = alur_ttd_bersihkan_blok_ttd_ganda($html_content);
    if ($metode === 'qr') {
        require_once __DIR__ . '/inc_verifikasi_qr.php';
        $html_content = alur_ttd_hapus_semua_overlay_digital($html_content);
        $qrSrc = alur_ttd_qr_verifikasi_img_src(sipandang_verifikasi_qr_surat_url_from_row($data));
        if ($qrSrc !== '') {
            $html_content = alur_ttd_sisipkan_qr_verifikasi($html_content, $qrSrc);
        }
    } elseif (alur_ttd_html_is_kematian($html_content) || !alur_ttd_sudah_ada_di_html($html_content)) {
        $ttdUrl = alur_ttd_gambar_url_root($koneksi, $idPmTtd);
        if ($ttdUrl !== '') {
            $html_content = alur_ttd_sisipkan_gambar($html_content, $ttdUrl);
        }
    } else {
        $html_content = alur_ttd_bersihkan_duplikat_ttd($html_content);
    }
}
$tampilkan_qr = sipandang_verifikasi_qr_surat_tampilkan($data);
$revisi_qr    = sipandang_verifikasi_qr_surat_revisi($data);

// --- 3. ATUR UKURAN KERTAS & MARGIN ---
$layout_surat = function_exists('sipandang_surat_keluar_layout')
    ? sipandang_surat_keluar_layout($tipe_layanan, $data)
    : [
        'landscape' => ($tipe_layanan === 'KEMATIAN'),
        'page_css' => ($tipe_layanan === 'KEMATIAN') ? '330mm 215mm' : '210mm 297mm',
        'width_css' => ($tipe_layanan === 'KEMATIAN') ? '330mm' : '210mm',
        'height_css' => ($tipe_layanan === 'KEMATIAN') ? '215mm' : '297mm',
        'padding_css' => ($tipe_layanan === 'KEMATIAN') ? '5mm 8mm' : (function_exists('sipandang_kertas_padding_surat') ? sipandang_kertas_padding_surat() : '12mm 20mm 12mm 20mm'),
        'class_kertas' => ($tipe_layanan === 'KEMATIAN') ? 'kertas-surat' : 'kertas-surat surat-keluar-a4',
    ];
$page_size_css = $layout_surat['page_css'];
$width_css     = $layout_surat['width_css'];
$min_height    = $layout_surat['height_css'];
$padding_css   = $layout_surat['padding_css'];
$class_kertas  = $layout_surat['class_kertas'];
$is_landscape  = $layout_surat['landscape'];
$font_surat_a4 = function_exists('sipandang_surat_keluar_font_size') ? sipandang_surat_keluar_font_size() : '12pt';
$lh_surat_a4   = function_exists('sipandang_surat_keluar_line_height_padat') ? sipandang_surat_keluar_line_height_padat() : '1.25';
$pad_surat_print = function_exists('sipandang_kertas_padding_surat_print') ? sipandang_kertas_padding_surat_print() : $padding_css;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reprint - <?= htmlspecialchars($data['no_surat']); ?></title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        <?php if (empty($is_pengantar_nikah)) { echo sipandang_kop_kelurahan_cetak_fix_css($kop_cetak); } ?>
        <?php if (!empty($is_pengantar_nikah)) { echo sipandang_pengantar_nikah_css(); } ?>
        <?php if (!empty($is_pengantar_nikah) && strpos($html_content, 'pengantar-nikah-n6') !== false && function_exists('sipandang_pengantar_nikah_n6_css')) {
            echo sipandang_pengantar_nikah_n6_css();
        } ?>
        <?php echo alur_ttd_overlay_css(); ?>
        /* === 1. RESET & UMUM === */
        * { box-sizing: border-box; }
        body { 
            background-color: #2c2f33; 
            margin: 0; padding: 0;
            font-family: 'Times New Roman', Times, serif;
            -webkit-print-color-adjust: exact;
        }

        /* === 2. TOOLBAR === */
        .toolbar {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 10px 20px;
            text-align: center;
            z-index: 9999;
            color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-label {
            font-weight: bold;
            font-size: 13px;
            color: #a0c4ff;
            letter-spacing: 1px;
            margin-right: 10px;
        }

        /* === 3. TOMBOL === */
        .btn-toolbar {
            padding: 8px 18px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            font-size: 13px;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-toolbar:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }

        .btn-print   { background: #28a745; color: white; }
        .btn-print:hover { background: #218838; }

        .btn-edit    { background: #ffc107; color: #333; }
        .btn-edit:hover { background: #e0a800; }

        .btn-done    { background: var(--role,#17a2b8); color: white; }
        .btn-done:hover { background: #138496; }

        .btn-save    { background: #e83e8c; color: white; }
        .btn-save:hover { background: #c82372; }

        .btn-close   { background: #dc3545; color: white; }
        .btn-close:hover { background: #c82333; }

        /* === INDIKATOR STATUS === */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .status-view   { background: #495057; color: #adb5bd; }
        .status-edit   { background: #ffc107; color: #333; animation: pulse-badge 1.5s infinite; }

        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.6; }
        }

        /* === 4. NOTIFIKASI === */
        .notif {
            position: fixed;
            top: 65px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            z-index: 10000;
            opacity: 0;
            transition: all 0.4s ease;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        .notif.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .notif-success { background: #28a745; color: white; }
        .notif-error   { background: #dc3545; color: white; }
        .notif-info    { background: var(--role,#17a2b8); color: white; }

        /* === 5. KERTAS SURAT === */
        .kertas-surat { 
            background: white; 
            width: 100%; 
            min-height: <?= $min_height; ?>; 
            padding: <?= $padding_css; ?>; 
            margin: 0; 
            box-shadow: 0 0 30px rgba(0,0,0,0.6); 
            position: relative;
            font-family: <?= function_exists('sipandang_surat_keluar_font_family') ? sipandang_surat_keluar_font_family() : 'Arial, Helvetica, sans-serif'; ?>;
            font-size: <?= $is_landscape ? '11pt' : (function_exists('is_surat_mutasi_pbb') && is_surat_mutasi_pbb($data) ? '11pt' : $font_surat_a4); ?>; 
            line-height: <?= $is_landscape ? '1.4' : (function_exists('is_surat_mutasi_pbb') && is_surat_mutasi_pbb($data) ? '1.55' : $lh_surat_a4); ?>;
            transition: outline 0.3s ease;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        <?php if (function_exists('sipandang_surat_keluar_css_for_format')) {
            echo sipandang_surat_keluar_css_for_format($tipe_layanan, $data, $is_landscape);
        } ?>
        .kertas-surat-isi {
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow-wrap: break-word;
            word-break: normal;
            box-sizing: border-box;
        }
        .sipandang-surat-kolom-isi {
            min-width: 0;
            margin-left: 0;
            margin-right: 0;
            width: 100%;
            box-sizing: border-box;
            overflow-wrap: break-word;
            word-break: normal;
        }
        .kertas-surat .kop-kel-wrap,
        .kertas-surat table.kop-header,
        .kertas-surat table.kop-kel-tbl {
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .kertas-surat-qr {
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 5px;
            font-family: Arial, sans-serif;
        }

        /* === 6. MODE EDIT + TOOLBAR (seperti preview surat) === */
        .surat-editor-wrap {
            width: <?= $width_css; ?>;
            max-width: calc(100% - 24px);
            margin: 80px auto 30px auto;
        }
        .edit-toolbar {
            display: none;
            background: linear-gradient(135deg, #0a1628 0%, var(--role-ink,#1e3a5f) 100%);
            padding: 10px 14px;
            border-radius: 8px 8px 0 0;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            box-shadow: 0 2px 12px rgba(0,0,0,.35);
        }
        .edit-toolbar.show { display: flex; }
        .toolbar-btn {
            background: rgba(255,255,255,.1);
            color: var(--role,#0b5cad);
            border: 1px solid rgba(var(--role-rgb,11,92,173),.35);
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: .78rem;
            transition: background .15s;
            font-family: Arial, sans-serif;
        }
        .toolbar-btn:hover { background: rgba(var(--role-rgb,11,92,173),.22); }
        .toolbar-sep { width: 1px; height: 20px; background: rgba(255,255,255,.15); margin: 0 3px; }
        .edit-mode-badge {
            background: var(--role,#0b5cad);
            color:#fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .5px;
            margin-left: auto;
            white-space: nowrap;
        }
        .mode-edit-aktif,
        .kertas-surat.edit-aktif {
            outline: 3px dashed var(--role,#0b5cad) !important;
            background-color: #fff !important;
            cursor: text;
            border-radius: 0 0 0 0;
        }
        .edit-toolbar.show + .kertas-surat.mode-edit-aktif {
            border-radius: 0;
            margin-top: 0;
            box-shadow: 0 0 30px rgba(0,0,0,.6);
        }

        /* === 7. TABEL === */
        .kertas-surat table:not(.sipandang-tabel-poin):not(.sipandang-tabel-biodata):not(.kop-kel-tbl):not(.kop-header) {
            width: 100% !important;
            border-collapse: collapse !important;
            border-spacing: 0;
        }
        .kertas-surat table.bordered, 
        .kertas-surat table.bordered th, 
        .kertas-surat table.bordered td {
            border: 1px solid black !important;
        }
        .kertas-surat table:not(.sipandang-tabel-poin):not(.sipandang-tabel-biodata):not(.kop-kel-tbl):not(.kop-header) td {
            vertical-align: top;
            padding: 2px 4px;
        }
        .kertas-surat .sipandang-indent-kiri {
            display: block !important;
            box-sizing: border-box !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            margin: 0 0 8px 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: <?= (int) (function_exists('sipandang_surat_indent_isi_px') ? sipandang_surat_indent_isi_px() : 40); ?>px !important;
            padding-right: 0 !important;
        }
        .kertas-surat .sipandang-indent-kiri > table {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            margin: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding: 0 !important;
            table-layout: fixed !important;
            box-sizing: border-box !important;
        }

        /* === 8. LAINNYA === */
        .kertas-surat.surat-mutasi-pbb p { margin: 0 0 16px 0; }
        .kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) p { margin: 0 0 7px 0; }
        .kertas-surat:not(.surat-keluar-a4) p { margin-top: 0; margin-bottom: 8px; }
        .kertas-surat .kop-kel-wrap p,
        .kertas-surat .kop-kel-line { margin: 0 !important; padding: 0 !important; }
        .kertas-surat img { max-width: 100%; height: auto; }

        /* === MODAL KONFIRMASI === */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 99999;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            animation: modal-in 0.3s ease;
        }
        @keyframes modal-in {
            from { transform: scale(0.8); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .modal-icon { font-size: 48px; margin-bottom: 15px; }
        .modal-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; font-family: Arial, sans-serif; }
        .modal-desc  { font-size: 13px; color: #666; margin-bottom: 25px; line-height: 1.5; font-family: Arial, sans-serif; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-btn { padding: 10px 25px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .modal-btn-confirm { background: #e83e8c; color: white; }
        .modal-btn-confirm:hover { background: #c82372; }
        .modal-btn-cancel  { background: #6c757d; color: white; }
        .modal-btn-cancel:hover  { background: #545b62; }

        /* === LOADING OVERLAY === */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 99998;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 15px;
            color: white;
            font-family: Arial, sans-serif;
        }
        .loading-overlay.show { display: flex; }
        .spinner {
            width: 50px; height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-top-color: #e83e8c;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* === 9. SETTING PRINTER === */
        @media print {
            body { background: none; }
            .toolbar, .edit-toolbar, .no-print, .notif, .modal-overlay, .loading-overlay { display: none !important; }
            @page { size: <?= $page_size_css; ?>; margin: 0; }
            .kertas-surat { 
                margin: 0; 
                box-shadow: none; 
                width: 100%;
                page-break-after: auto;
                break-after: auto;
                outline: none !important;
            }
            .kertas-surat:not(.surat-keluar-a4),
            .kertas-surat.surat-mutasi-pbb {
                min-height: 0;
            }
            .kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) {
                padding: <?= $pad_surat_print; ?> !important;
                min-height: <?= $min_height; ?> !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                page-break-after: avoid;
                break-after: avoid-page;
            }
            .kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) .kertas-surat-isi {
                flex: 1 1 auto;
                min-width: 0;
                min-height: 0;
            }
            .kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) .kertas-surat-qr {
                margin-top: auto !important;
                padding-top: 5px !important;
                flex-shrink: 0;
                page-break-inside: avoid;
                break-inside: avoid-page;
            }
            .kertas-surat.surat-mutasi-pbb .kertas-surat-qr {
                margin-top: 14px !important;
                padding-top: 5px !important;
                flex-shrink: 0;
                page-break-inside: avoid;
                break-inside: avoid-page;
            }
            .mode-edit-aktif, .edit-aktif { outline: none !important; }
            .surat-editor-wrap { margin: 0 auto !important; width: 100% !important; }
        }
    </style>
</head>
<body>

    <!-- NOTIFIKASI -->
    <div id="notifBox" class="notif"></div>

    <!-- MODAL KONFIRMASI SIMPAN PERMANEN -->
    <div id="modalKonfirmasi" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">💾</div>
            <div class="modal-title">Simpan Perubahan Permanen?</div>
            <div class="modal-desc">
                Perubahan yang Anda buat akan <strong>disimpan ke database</strong> dan menggantikan isi surat yang lama secara permanen.<br><br>
                <strong>Nomor Surat:</strong> <?= htmlspecialchars($data['no_surat']); ?><br>
                Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-confirm" onclick="eksekusiSimpan()">✅ Ya, Simpan</button>
                <button class="modal-btn modal-btn-cancel" onclick="tutupModal()">✖ Batal</button>
            </div>
        </div>
    </div>

    <!-- LOADING OVERLAY -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <div style="font-size: 16px; font-weight: bold;">Menyimpan ke database...</div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <span class="toolbar-label">🖨️ REPRINT</span>
        <span id="statusBadge" class="status-badge status-view">● MODE LIHAT</span>

        <button onclick="window.print()" class="btn-toolbar btn-print">🖨️ Cetak</button>

        <button onclick="aktifkanEdit()" id="btnEdit" class="btn-toolbar btn-edit">✏️ Edit Teks</button>
        <button onclick="matikanEdit()" id="btnSelesaiEdit" class="btn-toolbar btn-done" style="display:none;">✔ Selesai Edit</button>
        <button onclick="bukaModalSimpan()" id="btnSimpan" class="btn-toolbar btn-save" style="display:none;">💾 Simpan Permanen</button>

        <a href="javascript:window.close()" class="btn-toolbar btn-close">✖ Tutup</a>
    </div>

    <!-- KERTAS SURAT + TOOLBAR EDIT -->
    <div class="surat-editor-wrap">
        <div class="edit-toolbar" id="editToolbar">
            <button type="button" class="toolbar-btn" onclick="execCmd('bold')" title="Tebal"><i class="fas fa-bold"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('italic')" title="Miring"><i class="fas fa-italic"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('underline')" title="Garis bawah"><i class="fas fa-underline"></i></button>
            <div class="toolbar-sep"></div>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyLeft')" title="Rata kiri"><i class="fas fa-align-left"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyCenter')" title="Tengah"><i class="fas fa-align-center"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyRight')" title="Rata kanan"><i class="fas fa-align-right"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('justifyFull')" title="Rata kanan-kiri"><i class="fas fa-align-justify"></i></button>
            <div class="toolbar-sep"></div>
            <button type="button" class="toolbar-btn" onclick="execCmd('insertUnorderedList')" title="Daftar"><i class="fas fa-list-ul"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('undo')" title="Urungkan"><i class="fas fa-undo"></i></button>
            <button type="button" class="toolbar-btn" onclick="execCmd('redo')" title="Ulangi"><i class="fas fa-redo"></i></button>
            <div class="toolbar-sep"></div>
            <select onchange="execCmd('fontSize', this.value); this.value=''" class="toolbar-btn" style="padding:4px 8px;" title="Ukuran huruf">
                <option value="">Ukuran</option>
                <option value="1">8pt</option>
                <option value="2">10pt</option>
                <option value="3">12pt</option>
                <option value="4">14pt</option>
                <option value="5">18pt</option>
                <option value="6">24pt</option>
            </select>
            <span class="edit-mode-badge"><i class="fas fa-pen"></i> EDIT AKTIF</span>
        </div>
        <div id="area_surat" class="<?= htmlspecialchars($class_kertas) ?>">
            <div class="kertas-surat-isi" id="area_surat_isi">
            <?= $html_content; ?>
            </div>
            <?php if ($tampilkan_qr): ?>
            <div class="kertas-surat-qr" id="suratQrFooter">
                <?= sipandang_verifikasi_qr_surat_footer_from_row($data); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORM TERSEMBUNYI UNTUK UPDATE DATABASE -->
    <form id="formUpdatePermanen" action="update_isi_surat.php" method="POST" style="display:none;">
        <input type="hidden" name="id_surat" value="<?= $id_surat_db; ?>">
        <input type="hidden" name="no_surat" value="<?= htmlspecialchars($data['no_surat']); ?>">
        <input type="hidden" name="id_pamong_baru" value="<?= (int) $id_pamong_baru_for_save; ?>">
        <input type="hidden" name="isi_surat_baru" id="inputIsiSuratBaru">
    </form>

    <script>
        var kertas      = document.getElementById('area_surat');
        var kertasIsi   = document.getElementById('area_surat_isi');
        var qrFooter    = document.getElementById('suratQrFooter');
        var btnEdit     = document.getElementById('btnEdit');
        var btnSelesai  = document.getElementById('btnSelesaiEdit');
        var btnSimpan   = document.getElementById('btnSimpan');
        var statusBadge = document.getElementById('statusBadge');
        var notifBox    = document.getElementById('notifBox');
        var modal       = document.getElementById('modalKonfirmasi');
        var loading     = document.getElementById('loadingOverlay');
        var editToolbar = document.getElementById('editToolbar');

        function getEditTarget() {
            return kertasIsi || kertas;
        }

        function execCmd(cmd, val) {
            var target = getEditTarget();
            if (!target || target.contentEditable !== 'true') return;
            document.execCommand(cmd, false, val || null);
            target.focus();
        }

        function cekPerubahanIsi() {
            var editTarget = getEditTarget();
            adaPerubahan = adaGantiTtd || (editTarget && editTarget.innerHTML !== kontenOriginal);
        }

        var kontenOriginal = <?= json_encode($html_content, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var revisiQr       = <?= (int) $revisi_qr; ?>;
        var idSuratDb      = <?= (int) $id_surat_db; ?>;
        var tokenSuratDb   = <?= json_encode((string) ($data['token_surat'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        var tampilkanQr    = <?= $tampilkan_qr ? 'true' : 'false'; ?>;
        var qrFooterPx     = <?= (int) sipandang_verifikasi_qr_surat_keluar_footer_size(); ?>;
        var qrFooterWrapStyle = <?= json_encode(sipandang_verifikasi_qr_surat_keluar_footer_wrap_style(), JSON_UNESCAPED_UNICODE); ?>;
        var qrFooterImgStyle  = <?= json_encode(sipandang_verifikasi_qr_surat_keluar_footer_img_style(), JSON_UNESCAPED_UNICODE); ?>;
        var adaGantiTtd    = <?= $id_pamong_baru_for_save > 0 ? 'true' : 'false'; ?>;
        var adaPerubahan   = adaGantiTtd;
        if (adaGantiTtd) {
            btnSimpan.style.display = 'inline-flex';
        }

        // ==============================
        // FUNGSI NOTIFIKASI
        // ==============================
        function tampilNotif(pesan, tipe) {
            notifBox.textContent = pesan;
            notifBox.className   = 'notif notif-' + tipe + ' show';
            setTimeout(function() {
                notifBox.classList.remove('show');
            }, 3000);
        }

        // ==============================
        // FUNGSI EDIT
        // ==============================
        function aktifkanEdit() {
            var editTarget = getEditTarget();
            editTarget.contentEditable = 'true';
            kertas.classList.add('mode-edit-aktif', 'edit-aktif');
            if (editToolbar) editToolbar.classList.add('show');
            editTarget.focus();

            btnEdit.style.display    = 'none';
            btnSelesai.style.display = 'inline-flex';
            btnSimpan.style.display  = 'inline-flex';

            statusBadge.textContent  = '✏ MODE EDIT';
            statusBadge.className    = 'status-badge status-edit';

            tampilNotif('Mode Edit Aktif — Gunakan toolbar atau klik teks surat', 'info');

            if (!editTarget.dataset.kelengkapanBound) {
                editTarget.dataset.kelengkapanBound = '1';
                editTarget.addEventListener('input', cekPerubahanIsi);
            }
        }

        function matikanEdit() {
            var editTarget = getEditTarget();
            editTarget.contentEditable = 'false';
            kertas.classList.remove('mode-edit-aktif', 'edit-aktif');
            if (editToolbar) editToolbar.classList.remove('show');

            btnEdit.style.display    = 'inline-flex';
            btnSelesai.style.display = 'none';

            if (!adaPerubahan) {
                btnSimpan.style.display = 'none';
            }

            statusBadge.textContent = '● MODE LIHAT';
            statusBadge.className   = 'status-badge status-view';

            if (adaPerubahan) {
                tampilNotif('✔ Edit selesai — Klik 💾 Simpan Permanen untuk menyimpan ke database', 'info');
            } else {
                tampilNotif('Tidak ada perubahan yang dilakukan', 'info');
            }
        }

        // ==============================
        // FUNGSI MODAL & SIMPAN
        // ==============================
        function bukaModalSimpan() {
            if (!adaPerubahan) {
                tampilNotif('⚠️ Belum ada perubahan yang dibuat', 'error');
                return;
            }
            // Pastikan mode edit dimatikan dulu sebelum simpan
            if (kertasIsi && kertasIsi.contentEditable === 'true') {
                matikanEdit();
            } else if (kertas.contentEditable === 'true') {
                matikanEdit();
            }
            modal.classList.add('show');
        }

        function tutupModal() {
            modal.classList.remove('show');
        }

        function eksekusiSimpan() {
            tutupModal();
            loading.classList.add('show');

            // Masukkan HTML terbaru ke input tersembunyi
            var editTarget = getEditTarget();
            document.getElementById('inputIsiSuratBaru').value = editTarget ? editTarget.innerHTML : '';

            // Kirim via AJAX agar halaman tidak reload (tetap bisa langsung cetak)
            var formData = new FormData(document.getElementById('formUpdatePermanen'));

            var updateUrl = new URL('update_isi_surat.php', window.location.href).href;

            fetch(updateUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.text().then(function(text) {
                    var result = null;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        var cuplikan = (text || '').replace(/\s+/g, ' ').trim().substring(0, 120);
                        throw new Error(
                            'Server tidak mengembalikan JSON (HTTP ' + response.status + '). ' +
                            (cuplikan ? cuplikan : 'Pastikan file update_isi_surat.php sudah di-upload.')
                        );
                    }
                    if (!response.ok && result && result.status !== 'success') {
                        throw new Error(result.message || ('HTTP ' + response.status));
                    }
                    return result;
                });
            })
            .then(function(result) {
                loading.classList.remove('show');

                if (result && result.status === 'success') {
                    kontenOriginal = editTarget.innerHTML;
                    adaGantiTtd    = false;
                    adaPerubahan   = false;
                    btnSimpan.style.display = 'none';
                    if (typeof result.revisi_reprint !== 'undefined') {
                        revisiQr = parseInt(result.revisi_reprint, 10) || revisiQr;
                        refreshQrFooter();
                    }
                    tampilNotif('✅ ' + result.message, 'success');
                } else {
                    tampilNotif('❌ ' + ((result && result.message) ? result.message : 'Gagal menyimpan.'), 'error');
                }
            })
            .catch(function(err) {
                loading.classList.remove('show');
                var pesan = (err && err.message) ? err.message : 'Gagal terhubung ke server.';
                tampilNotif('❌ ' + pesan, 'error');
                console.error(err);
            });
        }

        function qrImgSrc(verifUrl, px) {
            return window.location.origin + '/qr_image.php?s=' + px + '&d=' + encodeURIComponent(verifUrl);
        }

        function refreshQrFooter() {
            if (!tampilkanQr || !qrFooter || idSuratDb < 1) {
                return;
            }
            var ref = revisiQr > 0 ? (String(idSuratDb) + '-R' + revisiQr) : String(idSuratDb);
            var url = window.location.origin + '/verifikasi.php?';
            if (tokenSuratDb) {
                url += 'token=' + encodeURIComponent(tokenSuratDb);
            } else {
                url += 'surat=' + idSuratDb;
            }
            if (revisiQr > 0) {
                url += '&r=' + revisiQr;
            }
            var img = qrImgSrc(url, qrFooterPx);
            qrFooter.innerHTML =
                '<div class="sipandang-qr-footer" style="' + qrFooterWrapStyle + '">'
                + '<img src="' + img + '" width="' + qrFooterPx + '" height="' + qrFooterPx + '" alt="QR Verifikasi" style="' + qrFooterImgStyle + '">'
                + '<div><strong style="font-size:8.5pt;">Verifikasi dokumen SIPANDANG</strong><br>'
                + 'Pindai QR untuk memastikan keaslian dokumen ini.<br>'
                + 'ID Dokumen: <code style="font-size:7.5pt;">' + ref + '</code></div></div>';
        }

        // ==============================
        // CEGAH MENUTUP TAB SAAT ADA PERUBAHAN
        // ==============================
        window.addEventListener('beforeunload', function(e) {
            if (adaPerubahan) {
                e.preventDefault();
                e.returnValue = 'Ada perubahan yang belum disimpan. Yakin ingin menutup?';
            }
        });

        // Tutup modal jika klik di luar modal
        modal.addEventListener('click', function(e) {
            if (e.target === modal) tutupModal();
        });

        (function() {
            var stAwal = <?= json_encode(trim((string) ($data['status_surat'] ?? ''))); ?>;
            if (!idSuratDb || !['SIAP_CETAK', 'Siap Cetak'].includes(stAwal)) return;
            window.addEventListener('afterprint', function() {
                fetch('tandai_selesai_cetak.php?id=' + idSuratDb, { credentials: 'same-origin' }).catch(function() {});
            });
        })();
    </script>

</body>
</html>
