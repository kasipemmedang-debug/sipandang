<?php
// ================================================
// preview_surat.php — PREVIEW & EDIT SURAT
// Posisi include HARUS di atas sebelum output apapun
// ================================================
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth_admin.php';
sipandang_require_login();
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_nomor_surat.php';
require_once __DIR__ . '/inc_penduduk_ref.php';
require_once __DIR__ . '/inc_pemohon_tamu.php';
require_once __DIR__ . '/inc_goib.php';
require_once __DIR__ . '/inc_surat_nikah_pengantar.php';
sipandang_nikah_pengantar_format_ensure($koneksi);
require_once __DIR__ . '/inc_belum_menikah.php';
belum_menikah_ensure_format($koneksi);
require_once __DIR__ . '/inc_surat_keperluan_paragraf.php';
require_once __DIR__ . '/inc_format_editor.php';
require_once __DIR__ . '/inc_surat_form_config.php';
require_once __DIR__ . '/inc_surat_keperluan_resolver.php';
sipandang_form_config_ensure_schema($koneksi);
pemohon_tamu_ensure_schema($koneksi);

require_once __DIR__ . '/inc_kop_kelurahan.php';
require_once __DIR__ . '/inc_alur_ttd.php';
require_once __DIR__ . '/inc_kelengkapan_berkas.php';
kelengkapan_berkas_ensure_schema($koneksi);
$_inc_format_surat = __DIR__ . '/inc_format_surat_resmi.php';
if (is_file($_inc_format_surat)) {
    require_once $_inc_format_surat;
} elseif (!function_exists('is_surat_mutasi_pbb')) {
    // Fallback jika file helper belum di-upload ke server hosting
    function is_surat_mutasi_pbb($format_row) {
        if (strtoupper(trim($format_row['tipe_layanan'] ?? '')) === 'MUTASI_PBB') return true;
        $gabung = strtoupper(($format_row['nama_surat'] ?? '') . ' ' . ($format_row['kode_surat'] ?? '') . ' ' . ($format_row['tipe_layanan'] ?? '') . ' ' . ($format_row['isi_surat'] ?? ''));
        foreach (['PBB', 'SPPT', 'MUTASI SUBJEK', 'SUBJEK PAJAK', 'PAJAK BUMI'] as $k) {
            if (strpos($gabung, $k) !== false) return true;
        }
        return false;
    }
    function format_surat_resmi_aktif($tipe_layanan, $kop_otomatis, $is_landscape, array $format_row = []) {
        if ($is_landscape || strtoupper(trim($kop_otomatis ?? '')) !== 'YA') return false;
        if (!empty($format_row)) return is_surat_mutasi_pbb($format_row);
        return strtoupper(trim($tipe_layanan ?? '')) === 'MUTASI_PBB';
    }
    function format_surat_tanggal_kanan($nama_kelurahan, $tgl_indo) {
        $tempat = ucwords(strtolower(trim($nama_kelurahan ?: 'Medang')));
        return '<p style="text-align:right;margin:0 0 14px 0;font-size:11pt;">' . htmlspecialchars($tempat) . ', ' . htmlspecialchars($tgl_indo) . '</p>';
    }
    function format_surat_metadata($no_surat, $hal, $sifat = '-', $lampiran = '-') {
        $hal_html = htmlspecialchars($hal);
        if (stripos($hal_html, 'SURAT KETERANGAN') === 0) $hal_html = '<u><b>' . $hal_html . '</b></u>';
        return '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:11pt;"><tr><td width="70">Nomor</td><td width="12">:</td><td>' . htmlspecialchars($no_surat) . '</td></tr><tr><td>Sifat</td><td>:</td><td>' . htmlspecialchars($sifat) . '</td></tr><tr><td>Lampiran</td><td>:</td><td>' . htmlspecialchars($lampiran) . '</td></tr><tr><td valign="top">Hal</td><td valign="top">:</td><td valign="top">' . $hal_html . '</td></tr></table>';
    }
    function format_surat_strip_judul_lama($html) {
        return preg_replace('#<div[^>]*text-align\s*:\s*center[^>]*>\s*<h3[^>]*>.*?</h3>\s*<p[^>]*>\s*Nomor\s*:.*?</p>\s*</div>#is', '', $html, 1);
    }
    function format_surat_kop_garis() {
        return '<div style="border-top:3px solid #000;margin-top:6px;"></div><div style="border-top:1px solid #000;margin-top:2px;margin-bottom:16px;"></div>';
    }
    function format_surat_ttd_kanan($header_ttd, $nama_pejabat, $nip) {
        $nama_esc = htmlspecialchars((string) $nama_pejabat, ENT_QUOTES, 'UTF-8');
        $nip_esc = htmlspecialchars((string) $nip, ENT_QUOTES, 'UTF-8');
        return '
<table class="sipandang-ttd-dinas" style="width:100%;border-collapse:collapse;margin-top:24px;">
<tr><td style="width:50%;"></td>
<td style="width:50%;text-align:center;vertical-align:top;font-size:11pt;line-height:1.35;">
<div class="sipandang-ttd-inner" style="text-align:center;">
<p class="ttd-jabatan" style="margin:0 0 88px 0;"><b>' . $header_ttd . ',</b></p>
<div class="sipandang-ttd-nama-wrap" style="display:inline-block;line-height:1.1;text-align:center;">
<p class="ttd-nama" style="display:block;margin:0 0 1px 0;padding:0;font-weight:bold;text-decoration:underline;line-height:1.15;white-space:nowrap;">' . $nama_esc . '</p>
<p class="ttd-nip" style="display:block;margin:0;padding:0;line-height:1.05;white-space:nowrap;">NIP. ' . $nip_esc . '</p>
</div></div>
</td></tr></table>';
    }
}

// ── PRG PATTERN ──────────────────────────────────
// Jika POST: simpan ke session → redirect ke GET
// Jika GET dengan token: baca dari session
// Ini mencegah ERR_CACHE_MISS saat back button
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nik_pemohon'])) {
    $token = 'ps_' . uniqid();
    $_SESSION['preview_surat'][$token] = $_POST;
    header('Location: preview_surat.php?token=' . $token);
    exit;
}

// Baca dari session via token GET
$token = $_GET['token'] ?? '';
if (empty($token) || empty($_SESSION['preview_surat'][$token])) {
    header('Location: buat_surat.php');
    exit;
}
$_POST = $_SESSION['preview_surat'][$token];
// Jangan hapus session agar back button tetap bisa render

// --- AMBIL DATA POST ---
$nik            = mysqli_real_escape_string($koneksi, $_POST['nik_pemohon']);
$id_jenis       = mysqli_real_escape_string($koneksi, $_POST['id_jenis_surat']);
$perbaiki_arsip = !empty($_POST['perbaiki_arsip']);
$id_surat_edit_preview = (int) ($_POST['id_surat_edit'] ?? 0);
$catatan_pejabat_perbaiki = trim((string) ($_POST['catatan_pejabat_tampil'] ?? ''));
$arsip_kembali_url = trim((string) ($_POST['arsip_kembali_url'] ?? ''));
if ($arsip_kembali_url === '' && $id_surat_edit_preview > 0) {
    $arsip_kembali_url = 'arsip_detail.php?id=' . $id_surat_edit_preview;
}
$no_surat_asli  = trim((string) ($_POST['no_surat'] ?? ''));
$kecuali_nomor  = $id_surat_edit_preview > 0 ? $id_surat_edit_preview : 0;
$no_surat_fix   = sipandang_nomor_surat_unik_sekarang($koneksi, $no_surat_asli, $kecuali_nomor);
if ($no_surat_fix !== $no_surat_asli) {
    $_POST['no_surat'] = $no_surat_fix;
}
$no_surat       = mysqli_real_escape_string($koneksi, $no_surat_fix);
$id_ttd         = mysqli_real_escape_string($koneksi, $_POST['ttd_pejabat']);
$keterangan     = '';

// Domisili / tempat tinggal
$domisili_alamat   = trim((string) ($_POST['domisili_alamat'] ?? ''));
$domisili_sejak    = $_POST['domisili_sejak'] ?? '';
$domisili_status   = trim((string) ($_POST['domisili_status'] ?? ''));
$domisili_keperluan = trim((string) ($_POST['domisili_keperluan'] ?? ''));

// Penghasilan
$pghslan_tgl_pernyataan = trim((string) ($_POST['pghslan_tgl_pernyataan'] ?? ''));
$pghslan_pekerjaan = trim((string) ($_POST['pghslan_pekerjaan'] ?? ''));
$pghslan_instansi  = trim((string) ($_POST['pghslan_instansi'] ?? ''));
$pghslan_nominal   = trim((string) ($_POST['pghslan_nominal'] ?? ''));
$pghslan_keperluan = trim((string) ($_POST['pghslan_keperluan'] ?? ''));
$pghslan_tujuan    = trim((string) ($_POST['pghslan_tujuan'] ?? ''));

// Beda nama
$bedanama_nama1 = trim((string) ($_POST['bedanama_nama1'] ?? ''));
$bedanama_dok1  = trim((string) ($_POST['bedanama_dok1'] ?? ''));
$bedanama_nama2 = trim((string) ($_POST['bedanama_nama2'] ?? ''));
$bedanama_dok2  = trim((string) ($_POST['bedanama_dok2'] ?? ''));
$bedanama_keperluan = trim((string) ($_POST['bedanama_keperluan'] ?? ''));
$bedanama_keterangan = trim((string) ($_POST['bedanama_keterangan'] ?? ''));

// Duda / janda
$dj_status = trim((string) ($_POST['dj_status'] ?? ''));
$dj_nama_pasangan = trim((string) ($_POST['dj_nama_pasangan'] ?? ''));
$dj_tgl_peristiwa = $_POST['dj_tgl_peristiwa'] ?? '';
$dj_keperluan = trim((string) ($_POST['dj_keperluan'] ?? ''));

// Beda identitas
$bi_nik_dok = trim((string) ($_POST['bi_nik_dok'] ?? ''));
$bi_nik_benar = trim((string) ($_POST['bi_nik_benar'] ?? ''));
$bi_ttl_dok = trim((string) ($_POST['bi_ttl_dok'] ?? ''));
$bi_ttl_benar = trim((string) ($_POST['bi_ttl_benar'] ?? ''));
$bi_jk_dok = trim((string) ($_POST['bi_jk_dok'] ?? ''));
$bi_jk_benar = trim((string) ($_POST['bi_jk_benar'] ?? ''));
$bi_nama_dokumen = trim((string) ($_POST['bi_nama_dokumen'] ?? ''));
$bi_keperluan = trim((string) ($_POST['bi_keperluan'] ?? ''));

// Beda pekerjaan
$bp_pekerjaan_dok = trim((string) ($_POST['bp_pekerjaan_dok'] ?? ''));
$bp_pekerjaan_aktual = trim((string) ($_POST['bp_pekerjaan_aktual'] ?? ''));
$bp_keperluan = trim((string) ($_POST['bp_keperluan'] ?? ''));

// Beda alamat
$ba_alamat_dok = trim((string) ($_POST['ba_alamat_dok'] ?? ''));
$ba_alamat_aktual = trim((string) ($_POST['ba_alamat_aktual'] ?? ''));
$ba_sejak = $_POST['ba_sejak'] ?? '';
$ba_keperluan = trim((string) ($_POST['ba_keperluan'] ?? ''));

$goib_keperluan = trim((string) ($_POST['goib_keperluan'] ?? ''));
// ── Field Mutasi PBB / SPPT ──
$pbb_alamat_tanah   = $_POST['pbb_alamat_tanah']   ?? '';
$pbb_rt             = $_POST['pbb_rt']             ?? '';
$pbb_rw             = $_POST['pbb_rw']             ?? '';
$pbb_no_ajb         = $_POST['pbb_no_ajb']         ?? '';
$pbb_tgl_ajb        = $_POST['pbb_tgl_ajb']        ?? '';
$pbb_nama_pemilik   = $_POST['pbb_nama_pemilik']   ?? '';
$pbb_luas_bumi      = $_POST['pbb_luas_bumi']      ?? '';
$pbb_luas_bangunan  = $_POST['pbb_luas_bangunan']  ?? '';
$pbb_no_sppt        = $_POST['pbb_no_sppt']        ?? '';
$no_rt_rw       = $_POST['no_pengantar_rt'] ?? '-';
$usaha_nama     = $_POST['usaha_nama'] ?? '';
$usaha_alamat   = $_POST['usaha_alamat'] ?? '';
$lingkunganData = sipandang_izin_lingkungan_from_post($_POST);
$pindah_tujuan  = $_POST['pindah_tujuan'] ?? '';
$pindah_alasan  = $_POST['pindah_alasan'] ?? '';
$pindah_pengikut= $_POST['pindah_pengikut'] ?? '';
$mati_tgl       = $_POST['mati_tgl'] ?? date('Y-m-d');
$mati_jam       = $_POST['mati_jam'] ?? '-';
$mati_tempat    = $_POST['mati_tempat'] ?? '-';
$mati_sebab     = $_POST['mati_sebab'] ?? '-';
$mati_penentu   = $_POST['mati_penentu'] ?? '....................';
$mati_pelapor   = $_POST['mati_pelapor'] ?? '....................';
$mati_hubungan  = $_POST['mati_hubungan'] ?? '....................';
$ayah_nama      = $_POST['ayah_nama'] ?? '';
$ayah_nik       = $_POST['ayah_nik'] ?? '';
$ayah_tmp_lahir = $_POST['ayah_tmp_lahir'] ?? '';
$ayah_tgl_lahir = $_POST['ayah_tgl_lahir'] ?? '';
$ayah_agama     = $_POST['ayah_agama'] ?? '';
$ayah_pekerjaan = $_POST['ayah_pekerjaan'] ?? '';
$ayah_alamat    = $_POST['ayah_alamat'] ?? '';
$ibu_nama       = $_POST['ibu_nama'] ?? '';
$ibu_nik        = $_POST['ibu_nik'] ?? '';
$ibu_tmp_lahir  = $_POST['ibu_tmp_lahir'] ?? '';
$ibu_tgl_lahir  = $_POST['ibu_tgl_lahir'] ?? '';
$ibu_agama      = $_POST['ibu_agama'] ?? '';
$ibu_pekerjaan  = $_POST['ibu_pekerjaan'] ?? '';
$ibu_alamat     = $_POST['ibu_alamat'] ?? '';

// ── FIELD SUAMI ISTRI ──────────────────────────────────────
$si_nama_istri    = strtoupper($_POST['si_nama_istri']    ?? '');
$si_tmp_istri     = strtoupper($_POST['si_tmp_istri']     ?? '');
$si_tgl_istri     = $_POST['si_tgl_istri']     ?? '';
$si_wn_istri      = strtoupper($_POST['si_wn_istri']      ?? 'INDONESIA');
$si_agama_istri   = strtoupper($_POST['si_agama_istri']   ?? '');
$si_pkj_istri     = strtoupper($_POST['si_pkj_istri']     ?? '');
$si_alamat_istri  = strtoupper($_POST['si_alamat_istri']  ?? '');
$si_rt_istri      = $_POST['si_rt_istri']      ?? '';
$si_rw_istri      = $_POST['si_rw_istri']      ?? '';
$si_no_buku_nikah = $_POST['si_no_buku_nikah'] ?? '';
// Jika form kenal lahir diisi, override nilai ayah/ibu
$lahir_nama_ayah   = $_POST['lahir_nama_ayah']   ?? '';
$lahir_ttl_ayah_tmp= $_POST['lahir_ttl_ayah_tmp'] ?? '';
$lahir_ttl_ayah_tgl= $_POST['lahir_ttl_ayah_tgl'] ?? '';
$lahir_wn_ayah     = $_POST['lahir_wn_ayah']     ?? 'INDONESIA';
$lahir_agama_ayah  = $_POST['lahir_agama_ayah']  ?? '';
$lahir_pkj_ayah    = $_POST['lahir_pkj_ayah']    ?? '';
$lahir_nama_ibu    = $_POST['lahir_nama_ibu']    ?? '';
$lahir_ttl_ibu_tmp = $_POST['lahir_ttl_ibu_tmp'] ?? '';
$lahir_ttl_ibu_tgl = $_POST['lahir_ttl_ibu_tgl'] ?? '';
$lahir_wn_ibu      = $_POST['lahir_wn_ibu']      ?? 'INDONESIA';
$lahir_agama_ibu   = $_POST['lahir_agama_ibu']   ?? '';
$lahir_pkj_ibu     = $_POST['lahir_pkj_ibu']     ?? '';
$lahir_alamat_ayah = $_POST['lahir_alamat_ayah'] ?? ''; // alamat bapak jika beda
$lahir_rt_ayah     = $_POST['lahir_rt_ayah']     ?? ''; // RT bapak jika beda
$lahir_rw_ayah     = $_POST['lahir_rw_ayah']     ?? ''; // RW bapak jika beda
$lahir_alamat_ibu  = $_POST['lahir_alamat_ibu']  ?? ''; // alamat ibu jika beda
$lahir_rt_ibu      = $_POST['lahir_rt_ibu']      ?? ''; // RT ibu jika beda
$lahir_rw_ibu      = $_POST['lahir_rw_ibu']      ?? ''; // RW ibu jika beda
$lahir_tgl_sptjm   = $_POST['lahir_tgl_sptjm']   ?? '';
$lahir_keperluan   = $_POST['lahir_keperluan']   ?? '';

// Override: jika field lahir_ diisi, pakai itu
if (!empty($lahir_nama_ayah))    $ayah_nama      = $lahir_nama_ayah;
if (!empty($lahir_ttl_ayah_tmp)) $ayah_tmp_lahir = $lahir_ttl_ayah_tmp;
if (!empty($lahir_ttl_ayah_tgl)) $ayah_tgl_lahir = $lahir_ttl_ayah_tgl;
if (!empty($lahir_agama_ayah))   $ayah_agama     = $lahir_agama_ayah;
if (!empty($lahir_pkj_ayah))     $ayah_pekerjaan = $lahir_pkj_ayah;
if (!empty($lahir_alamat_ayah))  $ayah_alamat    = $lahir_alamat_ayah;
if (!empty($lahir_nama_ibu))     $ibu_nama       = $lahir_nama_ibu;
if (!empty($lahir_ttl_ibu_tmp))  $ibu_tmp_lahir  = $lahir_ttl_ibu_tmp;
if (!empty($lahir_ttl_ibu_tgl))  $ibu_tgl_lahir  = $lahir_ttl_ibu_tgl;
if (!empty($lahir_agama_ibu))    $ibu_agama      = $lahir_agama_ibu;
if (!empty($lahir_pkj_ibu))      $ibu_pekerjaan  = $lahir_pkj_ibu;
if (!empty($lahir_alamat_ibu))   $ibu_alamat     = $lahir_alamat_ibu;

// --- AMBIL DATA DATABASE ---
$instansi = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_profil LIMIT 1"));
$pemohon_tipe = strtoupper(trim((string) ($_POST['pemohon_tipe'] ?? 'WARGA')));
$id_pemohon_tamu = (int) ($_POST['id_pemohon_tamu'] ?? 0);
$w = null;
if ($pemohon_tipe === 'TAMU') {
    $row_tamu = $id_pemohon_tamu > 0
        ? pemohon_tamu_get_by_id($koneksi, $id_pemohon_tamu)
        : pemohon_tamu_get_by_nik($koneksi, preg_replace('/\D/', '', $_POST['nik_pemohon'] ?? ''));
    $w = pemohon_tamu_as_penduduk_row($row_tamu);
} else {
    $w = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE nik = '$nik'"));
}
if (!$w || empty($w['nik'])) {
    header('Location: ' . ($pemohon_tipe === 'TAMU' ? 'buat_surat_pbb.php' : 'buat_surat.php') . '?err=pemohon');
    exit;
}
$j        = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_format_surat WHERE id_format = '$id_jenis'"));
$p        = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_pamong WHERE id_pamong = '$id_ttd'"));
if (!$j || !$p) {
    header('Location: ' . ($pemohon_tipe === 'TAMU' ? 'buat_surat_pbb.php' : 'buat_surat.php') . '?err=format');
    exit;
}
if ($pemohon_tipe === 'TAMU' && !pemohon_tamu_format_is_pbb($j)) {
    header('Location: buat_surat_pbb.php?err=format');
    exit;
}

$alur_ttd_modes = alur_ttd_mode_tersedia_untuk_jabatan($p['jabatan'] ?? '');
$alur_ttd_bidang = alur_ttd_bidang_dari_jabatan($p['jabatan'] ?? '');
$kelengkapan_json_preview = trim((string) ($_POST['kelengkapan_berkas_json'] ?? ''));
$kelengkapan_cek_preview = kelengkapan_siap_kirim_pejabat_dari_json($koneksi, $kelengkapan_json_preview);
$label_kirim_pejabat = alur_ttd_label_kirim_dari_pamong($koneksi, (int) $id_ttd);
$preview_berkas_info = ($token !== '') ? alur_ttd_preview_berkas_get($token) : null;
$kirim_mode_auto = alur_ttd_mode_dari_pamong($koneksi, (int) $id_ttd) ?: 'kirim_kasi_pem';
$kel_parsed_preview = kelengkapan_parse_items_from_json($kelengkapan_json_preview, $koneksi);
$kel_items_preview = $kel_parsed_preview['items'] ?? [];
$berkas_gate_siap = $kelengkapan_cek_preview['ok'] && $preview_berkas_info !== null;

$form_cfg = sipandang_form_config_effective($j);
$keperluan_resolved = sipandang_surat_resolve_keperluan($j, $_POST, $form_cfg);
$keterangan = $keperluan_resolved['value'];
$form_cfg_errors = sipandang_form_config_validate_post($form_cfg, $_POST);
if (!empty($form_cfg_errors)) {
    $_SESSION['buat_surat_err'] = $form_cfg_errors[0];
    header('Location: buat_surat.php?err=form');
    exit;
}

sipandang_pengantar_nikah_preview_kop_siapkan($j);

// Pengaturan Kop
$lebar_logo   = !empty($instansi['lebar_logo'])        ? $instansi['lebar_logo']        : 100;
$fs_judul     = !empty($instansi['font_size_judul'])    ? $instansi['font_size_judul']    : 18;
$fs_subjudul  = !empty($instansi['font_size_subjudul']) ? $instansi['font_size_subjudul'] : 14;
$fs_alamat    = !empty($instansi['font_size_alamat'])   ? $instansi['font_size_alamat']   : 11;
$nama_kab     = strtoupper($instansi['nama_kabupaten']);
$nama_kec     = strtoupper($instansi['nama_kecamatan']);
$nama_kel     = strtoupper($instansi['nama_kelurahan']);
$alamat_kantor= $instansi['alamat_kantor'];
$email_kantor = $instansi['email_kantor'];
$kode_pos     = $instansi['kode_pos'];
$path_logo    = "dist/img/" . $instansi['logo'];

// --- FUNGSI BANTUAN ---
function tgl_indo($tgl) {
    if (empty($tgl) || $tgl == "0000-00-00" || strlen($tgl) < 10) return "-";
    $bulan = [1=>"Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
    $pecah = explode("-", $tgl);
    if (count($pecah) < 3 || !is_numeric($pecah[1])) return "-";
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}
function nama_hari($tgl) {
    if (empty($tgl) || $tgl === '0000-00-00') {
        return '-';
    }
    $tgl = substr(trim($tgl), 0, 10);
    $dt  = DateTime::createFromFormat('Y-m-d', $tgl);
    if (!$dt) {
        $ts = strtotime($tgl);
        if (!$ts) {
            return '-';
        }
        $arr = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'];
        return $arr[date('D', $ts)] ?? '-';
    }
    $nama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return $nama[(int) $dt->format('w')] ?? '-';
}
function hitung_umur($lahir, $mati) {
    if (empty($lahir) || $lahir == "0000-00-00") return "-";
    return (new DateTime($lahir))->diff(new DateTime($mati))->y;
}
function konversi_agama($kode) {
    $list = ['1'=>'ISLAM','2'=>'KRISTEN','3'=>'KATOLIK','4'=>'HINDU','5'=>'BUDHA','6'=>'KONGHUCU','7'=>'KEPERCAYAAN'];
    $kode = trim((string)$kode);
    return !is_numeric($kode) ? strtoupper($kode) : ($list[$kode] ?? strtoupper($kode));
}
function get_ortu_val($manual, $db, $field, $upper=false, $is_tgl=false) {
    if (!empty($manual)) return $upper ? strtoupper($manual) : $manual;
    if ($db) {
        if ($is_tgl) return tgl_indo($db[$field]);
        return $upper ? strtoupper($db[$field] ?? '') : ($db[$field] ?? '');
    }
    return "............................................";
}

// Variabel utama
$ttl_format     = strtoupper($w['tempat_lahir'].', '.tgl_indo($w['tgl_lahir']));
$tgl_surat_indo = tgl_indo(date('Y-m-d'));
$tgl_mati_indo  = tgl_indo($mati_tgl);
$hari_mati      = nama_hari($mati_tgl);
$umur_jenazah   = hitung_umur($w['tgl_lahir'], $mati_tgl);
$umur_pemohon   = hitung_umur($w['tgl_lahir'] ?? '', date('Y-m-d'));

$keramaian_nama_acara = strtoupper(trim($_POST['keramaian_nama_acara'] ?? ''));
$keramaian_jenis      = strtoupper(trim($_POST['keramaian_jenis_acara'] ?? 'RESEPSI PERNIKAHAN'));
$keramaian_tgl_raw = trim($_POST['keramaian_tgl'] ?? '');
$keramaian_tgl     = $keramaian_tgl_raw !== '' ? strtoupper(tgl_indo($keramaian_tgl_raw)) : '';
/* Hari selalu dari tanggal acara (hindari field hari manual yang tertinggal) */
$keramaian_hari = $keramaian_tgl_raw !== '' ? strtoupper(nama_hari($keramaian_tgl_raw)) : strtoupper(trim($_POST['keramaian_hari'] ?? ''));
$keramaian_waktu   = strtoupper(trim($_POST['keramaian_waktu'] ?? ''));
$keramaian_hiburan = strtoupper(trim($_POST['keramaian_hiburan'] ?? ''));
$keramaian_tempat_raw = trim($_POST['keramaian_tempat'] ?? '');
$alamat_surat_html    = sipandang_alamat_html_for_surat($w, '', $instansi);
$tempat_acara_html    = sipandang_alamat_html_for_surat($w, $keramaian_tempat_raw, $instansi);
$dot = '............................................';

// Data Orang Tua (hanya warga dengan KK)
$no_kk_pemohon = $w['no_kk'] ?? '';
$ayah_db = null;
$ibu_db  = null;
if ($pemohon_tipe !== 'TAMU' && $no_kk_pemohon !== '' && $no_kk_pemohon !== '-') {
    $kk_esc = mysqli_real_escape_string($koneksi, $no_kk_pemohon);
    $ayah_db = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE no_kk='$kk_esc' AND shdk='KEPALA KELUARGA'"));
    $ibu_db  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tbl_penduduk WHERE no_kk='$kk_esc' AND shdk='ISTRI'"));
}

$f_ayah_nama = get_ortu_val($ayah_nama, $ayah_db, 'nama_lengkap', true);
$f_ayah_nik  = get_ortu_val($ayah_nik, $ayah_db, 'nik');
$f_ayah_ttl  = strtoupper((!empty($ayah_tmp_lahir)?$ayah_tmp_lahir:($ayah_db['tempat_lahir']??'...')).','.(!empty($ayah_tgl_lahir)?tgl_indo($ayah_tgl_lahir):($ayah_db?tgl_indo($ayah_db['tgl_lahir']):'...')));
$f_ayah_agm  = konversi_agama(!empty($ayah_agama)?$ayah_agama:($ayah_db['agama']??''));
$f_ayah_pkj  = sipandang_tampil_pekerjaan(get_ortu_val($ayah_pekerjaan, $ayah_db, 'pekerjaan', false), true);

// Alamat ayah/ibu — surat kenal lahir: format sama pemohon (KEL/KEC/KAB); surat lain tidak diubah
$alamat_warga_jalan = strtoupper($w['alamat']);
$lahir_lengkap_ortu  = sipandang_surat_is_kenal_lahir($j);

$f_rt_ayah = !empty($lahir_rt_ayah) ? $lahir_rt_ayah : (!empty($ayah_db['rt']) ? $ayah_db['rt'] : $w['rt']);
$f_rw_ayah = !empty($lahir_rw_ayah) ? $lahir_rw_ayah : (!empty($ayah_db['rw']) ? $ayah_db['rw'] : $w['rw']);
$f_ayah_alm = sipandang_alamat_ortu_for_surat(
    $ayah_alamat,
    $ayah_db,
    $alamat_warga_jalan,
    (string) $lahir_rt_ayah,
    (string) $lahir_rw_ayah,
    $w,
    $instansi,
    $lahir_lengkap_ortu
);

$f_ibu_nama  = get_ortu_val($ibu_nama, $ibu_db, 'nama_lengkap', true);
$f_ibu_nik   = get_ortu_val($ibu_nik, $ibu_db, 'nik');
$f_ibu_ttl   = strtoupper((!empty($ibu_tmp_lahir)?$ibu_tmp_lahir:($ibu_db['tempat_lahir']??'...')).','.(!empty($ibu_tgl_lahir)?tgl_indo($ibu_tgl_lahir):($ibu_db?tgl_indo($ibu_db['tgl_lahir']):'...')));
$f_ibu_agm   = konversi_agama(!empty($ibu_agama)?$ibu_agama:($ibu_db['agama']??''));
$f_ibu_pkj   = sipandang_tampil_pekerjaan(get_ortu_val($ibu_pekerjaan, $ibu_db, 'pekerjaan', false), true);

$f_rt_ibu = !empty($lahir_rt_ibu) ? $lahir_rt_ibu : (!empty($ibu_db['rt']) ? $ibu_db['rt'] : $w['rt']);
$f_rw_ibu = !empty($lahir_rw_ibu) ? $lahir_rw_ibu : (!empty($ibu_db['rw']) ? $ibu_db['rw'] : $w['rw']);
$f_ibu_alm = sipandang_alamat_ortu_for_surat(
    $ibu_alamat,
    $ibu_db,
    $alamat_warga_jalan,
    (string) $lahir_rt_ibu,
    (string) $lahir_rw_ibu,
    $w,
    $instansi,
    $lahir_lengkap_ortu
);

$pnPreview = sipandang_pengantar_nikah_preview_prepare($koneksi, $j, [
    'post'               => $_POST,
    'w'                  => $w,
    'instansi'           => $instansi,
    'dot'                => $dot,
    'ayah_tmp_lahir'     => $ayah_tmp_lahir,
    'ayah_tgl_lahir'     => $ayah_tgl_lahir,
    'ayah_db'            => $ayah_db,
    'ibu_tmp_lahir'      => $ibu_tmp_lahir,
    'ibu_tgl_lahir'      => $ibu_tgl_lahir,
    'ibu_db'             => $ibu_db,
    'ayah_alamat'        => $ayah_alamat,
    'ibu_alamat'         => $ibu_alamat,
    'alamat_warga_jalan' => $alamat_warga_jalan,
    'lahir_rt_ayah'      => $lahir_rt_ayah,
    'lahir_rw_ayah'      => $lahir_rw_ayah,
    'lahir_rt_ibu'       => $lahir_rt_ibu,
    'lahir_rw_ibu'       => $lahir_rw_ibu,
]);
$_is_pengantar_nikah = !empty($pnPreview['is_pengantar']);
if ($_is_pengantar_nikah) {
    $ttl_format        = $pnPreview['ttl_format'];
    $alamat_surat_html = $pnPreview['alamat_surat_html'];
    $f_ayah_ttl        = $pnPreview['f_ayah_ttl'];
    $f_ibu_ttl         = $pnPreview['f_ibu_ttl'];
    $f_ayah_alm        = $pnPreview['f_ayah_alm'];
    $f_ibu_alm         = $pnPreview['f_ibu_alm'];
}

$n1_nik_ayah_post = preg_replace('/\D/', '', trim((string) ($_POST['n1_nik_ayah'] ?? '')));
if ($n1_nik_ayah_post !== '') {
    $f_ayah_nik = $n1_nik_ayah_post;
}
$n1_nik_ibu_post = preg_replace('/\D/', '', trim((string) ($_POST['n1_nik_ibu'] ?? '')));
if ($n1_nik_ibu_post !== '') {
    $f_ibu_nik = $n1_nik_ibu_post;
}

// Tanggal SPTJM — debug: paksa baca dari POST langsung jika variabel kosong
if (empty($lahir_tgl_sptjm)) $lahir_tgl_sptjm = $_POST['lahir_tgl_sptjm'] ?? '';
$f_tgl_sptjm = (!empty($lahir_tgl_sptjm) && $lahir_tgl_sptjm !== '0000-00-00')
    ? tgl_indo($lahir_tgl_sptjm)
    : '............................................';

// TTD
$jabatan_pejabat    = strtoupper($p['jabatan']);
$nama_instansi_ttd  = "LURAH " . $nama_kel;
$header_ttd = ($jabatan_pejabat == "LURAH" || $jabatan_pejabat == "KEPALA DESA")
              ? $nama_instansi_ttd
              : "a.n. {$nama_instansi_ttd}<br>{$jabatan_pejabat}";

$ff_surat = function_exists('sipandang_surat_keluar_font_family') ? sipandang_surat_keluar_font_family() : 'Arial, Helvetica, sans-serif';
$blok_ttd_kecil = '
<div class="ttd-kematian" style="text-align:center; font-family:'.$ff_surat.'; font-size:8pt;">
    <p style="margin-bottom:0; line-height:1.2;">'.ucwords(strtolower($instansi['nama_kelurahan'])).', '.$tgl_surat_indo.'<br><b>'.$header_ttd.'</b></p>
    <br><br>
    <div class="ttd-kematian-nip" style="display:inline-block; line-height:1.1;">
        <p style="margin:0 0 1px 0;line-height:1.1;font-weight:bold;text-decoration:underline;white-space:nowrap;">'.$p['nama_pamong'].'</p>
        <p style="margin:0;line-height:1.05;white-space:nowrap;">NIP. '.$p['nip'].'</p>
    </div>
</div>';

// Ukuran kertas
$tipe_layanan = strtoupper(trim($j['tipe_layanan']));
$is_mutasi_pbb = is_surat_mutasi_pbb($j);
$layout_surat = sipandang_surat_keluar_layout($tipe_layanan, $j);
$is_landscape = $layout_surat['landscape'];
$pbb_metadata_di_template = $is_mutasi_pbb
    && preg_match('/>\s*Nomor\s*</i', (string) $j['isi_surat'])
    && preg_match('/>\s*Sifat\s*</i', (string) $j['isi_surat']);
// Layout Nomor/Sifat/Lampiran/Hal + tanggal kanan: hanya PBB/SPPT (bukan surat keterangan umum)
$format_resmi = format_surat_resmi_aktif($tipe_layanan, $j['kop_otomatis'] ?? '', $is_landscape, $j);

$blok_ttd_tanpa_tanggal = ($format_resmi && function_exists('format_surat_ttd_pbb_html'))
    ? format_surat_ttd_pbb_html($header_ttd, $p['nama_pamong'], $p['nip'], $ff_surat)
    : format_surat_ttd_kanan($header_ttd, $p['nama_pamong'], $p['nip']);

$blok_ttd_besar = $format_resmi
    ? $blok_ttd_tanpa_tanggal
    : (function_exists('format_surat_ttd_keterangan_inner_html')
        ? format_surat_ttd_keterangan_inner_html(
            $instansi['nama_kelurahan'] ?? $nama_kel,
            $tgl_surat_indo,
            $header_ttd,
            $p['nama_pamong'],
            $p['nip'],
            $ff_surat
        )
        : '<div style="text-align:center;font-family:'.$ff_surat.';font-size:12pt;">'
            . '<p style="margin-bottom:0;line-height:1.2;">'.ucwords(strtolower($instansi['nama_kelurahan'])).', '.$tgl_surat_indo.'<br><b>'.$header_ttd.'</b></p>'
            . '<br><br><br><br>'
            . (function_exists('sipandang_surat_ttd_nama_nip_wrap_keterangan_html')
                ? sipandang_surat_ttd_nama_nip_wrap_keterangan_html($p['nama_pamong'], $p['nip'])
                : sipandang_surat_ttd_nama_nip_wrap_html($p['nama_pamong'], $p['nip']))
            . '</div>');
$width_css     = $layout_surat['width_css'];
$height_css    = $layout_surat['height_css'];
$padding_css   = $layout_surat['padding_css'];
$page_size     = $layout_surat['page_size'];
$class_kertas  = $layout_surat['class_kertas'];
$font_surat_a4 = function_exists('sipandang_surat_keluar_font_size') ? sipandang_surat_keluar_font_size() : '12pt';
$lh_surat_a4   = function_exists('sipandang_surat_keluar_line_height_padat') ? sipandang_surat_keluar_line_height_padat() : '1.25';
$pad_surat_print = function_exists('sipandang_kertas_padding_surat_print') ? sipandang_kertas_padding_surat_print() : $padding_css;

if ($_is_pengantar_nikah) {
    $format_resmi = false;
}

// Replace placeholder surat (bersihkan KEL/KEC statis di template agar tidak dobel dengan [alamat])
$surat = sipandang_format_template_bersihkan_wilayah_ganda((string) ($j['isi_surat'] ?? ''));
$surat = sipandang_pengantar_nikah_preview_siapkan_isi($j, $surat);
if (!goib_is_surat($j)) {
    $surat = sipandang_surat_normalisasi_paragraf_keperluan($surat);
}
$replacements = [
    '[nama]'       => strtoupper($w['nama_lengkap']),
    '[umur]'       => ($umur_pemohon !== '-' && $umur_pemohon !== '') ? (string) $umur_pemohon : $dot,
    '[nik]'        => $w['nik'], '[no_kk]' => $w['no_kk'],
    '[ttl]'        => $ttl_format,
    '[jk]'         => ($w['jenis_kelamin']=='L'?'LAKI-LAKI':'PEREMPUAN'),
    '[kewarganegaraan]' => 'INDONESIA',
    '[agama]'      => konversi_agama($w['agama']),
    '[pekerjaan]'  => strtoupper(
        (
            (strtoupper($j['tipe_layanan'] ?? '') === 'PENGHASILAN'
             || (stripos($j['nama_surat'] ?? '', 'PENGHASILAN') !== false && stripos($j['nama_surat'] ?? '', 'BELUM') === false))
            && !empty($pghslan_pekerjaan)
        )
        ? $pghslan_pekerjaan
        : sipandang_tampil_pekerjaan($w['pekerjaan'] ?? '', true)
    ),
    '[alamat]'          => $alamat_surat_html,
    '[alamat_lengkap]'  => $alamat_surat_html,
    '[instansi_kelurahan]' => $nama_kel,
    '[instansi_kecamatan]' => $nama_kec,
    '[instansi_kabupaten]' => $nama_kab,
    '[rt]'         => !empty($pbb_rt) ? $pbb_rt : $w['rt'],
    '[rw]'         => !empty($pbb_rw) ? $pbb_rw : $w['rw'],
    '[no_pengantar]'=> $no_rt_rw !== '-' && $no_rt_rw !== '' ? strtoupper($no_rt_rw) : $dot,
    '[jenis_acara]'    => $keramaian_jenis !== '' ? $keramaian_jenis : 'RESEPSI PERNIKAHAN',
    '[nama_acara]'     => $keramaian_nama_acara !== '' ? $keramaian_nama_acara : $dot,
    '[hari_acara]'     => $keramaian_hari !== '' ? $keramaian_hari : $dot,
    '[tgl_acara]'      => $keramaian_tgl !== '' ? $keramaian_tgl : $dot,
    '[waktu_acara]'    => $keramaian_waktu !== '' ? $keramaian_waktu : $dot,
    '[hiburan_acara]'  => $keramaian_hiburan !== '' ? $keramaian_hiburan : $dot,
    '[tempat_acara]'   => $tempat_acara_html !== '' ? $tempat_acara_html : $dot,
    '[nama_ayah]'  => $f_ayah_nama, '[nik_ayah]' => $f_ayah_nik,
    '[ttl_ayah]'   => $f_ayah_ttl,  '[agama_ayah]' => $f_ayah_agm,
    '[pekerjaan_ayah]' => $f_ayah_pkj, '[alamat_ayah]' => $f_ayah_alm,
    '[nama_ibu]'   => $f_ibu_nama,  '[nik_ibu]' => $f_ibu_nik,
    '[ttl_ibu]'    => $f_ibu_ttl,   '[agama_ibu]' => $f_ibu_agm,
    '[pekerjaan_ibu]'  => $f_ibu_pkj,  '[alamat_ibu]' => $f_ibu_alm,
    '[no_surat]'   => $no_surat,
    '[keterangan]' => strtoupper($keterangan),
    '[tgl_surat]'  => $tgl_surat_indo,
    '[pindah_tujuan]'  => strtoupper($pindah_tujuan),
    '[pindah_alasan]'  => strtoupper($pindah_alasan),
    '[pindah_pengikut]'=> $pindah_pengikut,
    '[usaha_nama]'     => strtoupper($usaha_nama),
    '[usaha_alamat]'   => strtoupper($usaha_alamat),
    '[lingkungan_nama_perusahaan]'   => $lingkunganData['lingkungan_nama_perusahaan'],
    '[lingkungan_jenis_usaha]'       => $lingkunganData['lingkungan_jenis_usaha'],
    '[lingkungan_alamat_perusahaan]' => $lingkunganData['lingkungan_alamat_perusahaan'],
    '[lingkungan_status_bangunan]'   => $lingkunganData['lingkungan_status_bangunan'],
    '[lingkungan_no_nib]'            => $lingkunganData['lingkungan_no_nib'],
    '[lingkungan_jumlah_karyawan]'   => $lingkunganData['lingkungan_jumlah_karyawan'],
    '[tabel_lingkungan]'             => sipandang_izin_lingkungan_tabel_html($lingkunganData),
    '[tanda_tangan]'   => $blok_ttd_besar,
    '[nama_pejabat]'   => strtoupper($p['nama_pamong']),
    '[jabatan_pejabat]' => $header_ttd,
    '[nip_pejabat]'    => $p['nip'],
    // ── Placeholder Surat Kenal Lahir ──
    '[tgl_sptjm]'      => $f_tgl_sptjm,
    '[keperluan_lahir]'=> strtoupper($lahir_keperluan),
    '[wn_ayah]'        => strtoupper($lahir_wn_ayah ?: 'INDONESIA'),
    '[wn_ibu]'         => strtoupper($lahir_wn_ibu  ?: 'INDONESIA'),
    '[rt_ayah]'        => $f_rt_ayah,
    '[rw_ayah]'        => $f_rw_ayah,
    '[rt_ibu]'         => $f_rt_ibu,
    '[rw_ibu]'         => $f_rw_ibu,
];
if (!empty($pnPreview['extra_replacements'])) {
    $replacements = array_merge($replacements, $pnPreview['extra_replacements']);
}
$replacements += [
    // ── Placeholder Surat Keterangan Suami Istri ──
    '[nama_istri]'     => !empty($si_nama_istri)   ? $si_nama_istri   : '............................................',
    '[ttl_istri]'      => !empty($si_tmp_istri)    ? strtoupper($si_tmp_istri.', '.tgl_indo($si_tgl_istri)) : '............................................',
    '[wn_istri]'       => $si_wn_istri ?: 'INDONESIA',
    '[agama_istri]'    => !empty($si_agama_istri)  ? $si_agama_istri  : '............................................',
    '[pekerjaan_istri]'=> !empty($si_pkj_istri)    ? $si_pkj_istri    : '............................................',
    '[alamat_istri]'   => (!empty($si_alamat_istri) ? $si_alamat_istri : strtoupper($w['alamat']))
                          .' RT.'.(!empty($si_rt_istri) ? $si_rt_istri : $w['rt'])
                          .' RW.'.(!empty($si_rw_istri) ? $si_rw_istri : $w['rw']),
    '[no_buku_nikah]'  => !empty($si_no_buku_nikah) ? $si_no_buku_nikah : '............................................',
    // ── Mutasi PBB / SPPT ──
    '[alamat_tanah]'   => strtoupper($pbb_alamat_tanah ?: ($w['alamat'] ?? '............................................')),
    '[nama_pemilik]'   => strtoupper($pbb_nama_pemilik ?: ($w['nama_lengkap'] ?? '............................................')),
    '[no_ajb]'         => strtoupper($pbb_no_ajb ?: '............................................'),
    '[tgl_ajb]'        => !empty($pbb_tgl_ajb) ? tgl_indo($pbb_tgl_ajb) : '............................................',
    '[luas_bumi]'      => $pbb_luas_bumi ?: '........',
    '[luas_bangunan]'  => $pbb_luas_bangunan ?: '........',
    '[no_sppt]'        => $pbb_no_sppt ?: '............................................',
    '[domisili_alamat]'   => strtoupper($domisili_alamat ?: ($w['alamat'] ?? '')),
    '[domisili_sejak]'    => !empty($domisili_sejak) ? tgl_indo($domisili_sejak) : '............................................',
    '[domisili_status]'   => strtoupper($domisili_status ?: 'MILIK SENDIRI'),
    '[pghslan_pekerjaan]' => strtoupper($pghslan_pekerjaan ?: ($w['pekerjaan'] ?? '')),
    '[pghslan_instansi]'  => strtoupper($pghslan_instansi ?: '............................................'),
    '[pghslan_nominal]'   => $pghslan_nominal ?: '............................................',
    '[pghslan_tgl_pernyataan]' => (!empty($pghslan_tgl_pernyataan) && $pghslan_tgl_pernyataan !== '0000-00-00')
        ? tgl_indo($pghslan_tgl_pernyataan) : '............................................',
    '[pghslan_tujuan]'    => strtoupper($pghslan_tujuan ?: ''),
    '[bedanama_nama1]'    => strtoupper($bedanama_nama1 ?: '............................................'),
    '[bedanama_dok1]'     => strtoupper($bedanama_dok1 ?: '............................................'),
    '[bedanama_nama2]'    => strtoupper($bedanama_nama2 ?: '............................................'),
    '[bedanama_dok2]'     => strtoupper($bedanama_dok2 ?: '............................................'),
    '[bedanama_keterangan]' => strtoupper($bedanama_keterangan ?: '............................................'),
    '[dj_status]'         => strtoupper($dj_status ?: '............................................'),
    '[dj_nama_pasangan]'  => strtoupper($dj_nama_pasangan ?: '............................................'),
    '[dj_tgl_peristiwa]'  => !empty($dj_tgl_peristiwa) ? tgl_indo($dj_tgl_peristiwa) : '............................................',
    '[bi_nik_dok]'        => $bi_nik_dok ?: '............................................',
    '[bi_nik_benar]'      => $bi_nik_benar ?: ($w['nik'] ?? '............................................'),
    '[bi_ttl_dok]'        => strtoupper($bi_ttl_dok ?: '............................................'),
    '[bi_ttl_benar]'      => strtoupper($bi_ttl_benar ?: $ttl_format),
    '[bi_jk_dok]'         => strtoupper($bi_jk_dok ?: '............................................'),
    '[bi_jk_benar]'       => strtoupper($bi_jk_benar ?: (($w['jenis_kelamin'] ?? '') === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN')),
    '[bi_nama_dokumen]'   => strtoupper($bi_nama_dokumen ?: 'KTP/KK'),
    '[bp_pekerjaan_dok]'  => strtoupper($bp_pekerjaan_dok ?: '............................................'),
    '[bp_pekerjaan_aktual]'=> strtoupper($bp_pekerjaan_aktual ?: '............................................'),
    '[ba_alamat_dok]'     => strtoupper($ba_alamat_dok ?: ($w['alamat'] ?? '')),
    '[ba_alamat_aktual]'  => strtoupper($ba_alamat_aktual ?: '............................................'),
    '[ba_sejak]'          => !empty($ba_sejak) ? tgl_indo($ba_sejak) : '............................................',
    '[keperluan]'         => strtoupper($keterangan !== '' ? $keterangan : '............................................'),
];

$_is_goib = goib_is_surat($j);
if ($_is_goib) {
    $goibD = goib_data_from_post($_POST, $w, $instansi ?: []);
    if ($keterangan === '' && !empty($goibD['keperluan'])) {
        $keterangan = $goibD['keperluan'];
        $replacements['[keterangan]'] = strtoupper($goibD['keperluan']);
        $replacements['[keperluan]'] = strtoupper($goibD['keperluan']);
    }
    $replacements = array_merge($replacements, goib_replacement_map($goibD, $keterangan));
    $format_resmi = false;
}

$_is_belum_menikah = belum_menikah_is_surat($j) && !$_is_pengantar_nikah;
if ($_is_belum_menikah) {
    $bmD = belum_menikah_data_from_post($_POST, $w, $instansi ?: [], $p, $koneksi);
    if ($keterangan === '' && !empty($bmD['pemohon']['keperluan'])) {
        $keterangan = $bmD['pemohon']['keperluan'];
    }
    $no_surat = belum_menikah_perbaiki_nomor($no_surat);
    $bmD['no_surat'] = $no_surat;
    $surat = belum_menikah_render_html($bmD);
    $format_resmi = false;
    $blok_ttd_besar = '
<div style="text-align:center;">'
    . belum_menikah_ttd_inner_html($instansi ?: [], $p, $tgl_surat_indo, $header_ttd)
    . '</div>';
}

foreach ($replacements as $key => $val) { $surat = str_replace($key, $val, $surat); }

$_is_izin_lingkungan = sipandang_surat_is_izin_lingkungan_fmt($j);
if ($_is_izin_lingkungan) {
    $surat = sipandang_izin_lingkungan_patch_template_html($surat, $lingkunganData, [
        'pekerjaan' => (string) ($replacements['[pekerjaan]'] ?? ''),
    ]);
}

if (!$_is_goib && !$_is_belum_menikah) {
    $surat = sipandang_surat_normalisasi_paragraf_keperluan($surat);
}

// ── Deteksi apakah ini form PENGHASILAN (tipe atau nama surat) ──
$_is_penghasilan = strtoupper($j['tipe_layanan'] ?? '') === 'PENGHASILAN'
    || (stripos($j['nama_surat'] ?? '', 'PENGHASILAN') !== false
        && stripos($j['nama_surat'] ?? '', 'BELUM') === false);

// ── Post-processing PENGHASILAN: ganti titik-titik literal di template ──
// Template memakai <b>Rp. ......</b> /Bulan dan atas nama <b>.....</b>
if ($_is_penghasilan) {
    $tgl_pernyataan_indo = (!empty($pghslan_tgl_pernyataan) && $pghslan_tgl_pernyataan !== '0000-00-00')
        ? tgl_indo($pghslan_tgl_pernyataan) : '';

    // ── Tanggal surat pernyataan ──
    if ($tgl_pernyataan_indo !== '') {
        $surat = preg_replace(
            '/(surat\s+pernyataan\s+(?:dari\s+)?(?:yang\s+bersangkutan\s+)?Tanggal\s+)(?:<b>)?[.]{3,}(?:<\/b>)?/iu',
            '$1' . $tgl_pernyataan_indo,
            $surat
        );
        $surat = str_replace('[pghslan_tgl_pernyataan]', $tgl_pernyataan_indo, $surat);
        $surat = str_replace('[tgl_surat_pernyataan]', $tgl_pernyataan_indo, $surat);
    }

    // ── Nominal penghasilan ──
    // Template: <b>Rp. ..............................</b> /Bulan
    if (!empty($pghslan_nominal)) {
        $nom = $pghslan_nominal;
        // 1. Ada <b>...</b> di sekitar titik-titik, spasi, lalu /Bulan
        $surat = preg_replace('/<b>\s*Rp\.?\s*[.]{3,}\s*<\/b>\s*\/\s*[Bb]ulan/u',
                              '<b>Rp. '.$nom.'</b> /Bulan', $surat);
        // 2. Ada <u>...<b>Rp. .....</b> /Bulan</u> (dengan underline)
        $surat = preg_replace('/(Berpenghasilan\s+)<b>Rp\.?\s*[.]{3,}<\/b>(\s*\/\s*[Bb]ulan)/u',
                              '$1<b>Rp. '.$nom.'</b>$2', $surat);
        // 3. Tanpa tag: Rp. ...... /Bulan
        $surat = preg_replace('/Rp\.?\s*[.]{3,}\s*\/\s*[Bb]ulan/u', 'Rp. '.$nom.'/Bulan', $surat);
        $surat = preg_replace('/Rp\.?\s*[.]{3,}\s+[Bb]ulan/u',      'Rp. '.$nom.'/Bulan', $surat);
        // 4. Placeholder langsung
        $surat = str_replace('[pghslan_nominal]', $nom, $surat);
    }

    // ── Atas nama ──
    // Template: atas nama <b>............................................................</b>
    if (!empty($pghslan_tujuan)) {
        $tuj = strtoupper($pghslan_tujuan);
        // 1. Ada <b>...</b> setelah "atas nama"
        $surat = preg_replace('/atas\s+nama\s+<b>[.]{3,}<\/b>/iu',
                              'atas nama <b>'.$tuj.'</b>', $surat);
        // 2. Tanpa tag
        $surat = preg_replace('/atas\s+nama\s+[.]{3,}/iu', 'atas nama '.$tuj, $surat);
        // 3. Placeholder langsung
        $surat = str_replace('[pghslan_tujuan]', $tuj, $surat);
    } else {
        // Kosong → hapus seluruh "atas nama <b>.....</b>" dari kalimat
        $surat = preg_replace('/\s*atas\s+nama\s+<b>[.]{3,}<\/b>\s*\.?/iu', '', $surat);
        $surat = preg_replace('/\s*atas\s+nama\s+[.]{3,}\.*/iu', '', $surat);
        $surat = str_replace('[pghslan_tujuan]', '', $surat);
    }

    // ── Instansi (jika template punya titik-titik untuk instansi) ──
    if (!empty($pghslan_instansi)) {
        $ins = strtoupper($pghslan_instansi);
        $surat = preg_replace('/(instansi|perusahaan|tempat\s+kerja)\s*[:\-]?\s*(<b>)?[.]{3,}(<\/b>)?/iu',
                              '$1 : $2'.$ins.'$3', $surat);
        $surat = str_replace('[pghslan_instansi]', $ins, $surat);
    }

    // ── Pastikan pekerjaan dari form terganti juga (fallback) ──
    if (!empty($pghslan_pekerjaan)) {
        $surat = str_replace('[pghslan_pekerjaan]', strtoupper($pghslan_pekerjaan), $surat);
    }
}

if ($format_resmi) {
    $surat = format_surat_strip_judul_lama($surat);
    $surat = str_replace('[metadata_surat]', '', $surat);
}
if (!$is_landscape && empty($_is_pengantar_nikah) && function_exists('sipandang_surat_normalisasi_layout_isi')) {
    $surat = sipandang_surat_normalisasi_layout_isi($surat);
} elseif (!$is_landscape && empty($_is_pengantar_nikah) && function_exists('sipandang_surat_normalisasi_indent_isi')) {
    $surat = sipandang_surat_normalisasi_indent_isi($surat);
}

$isi_prefill = trim((string) ($_POST['isi_surat_html_prefill'] ?? ''));
if ($isi_prefill !== '') {
    $surat = $isi_prefill;
}

$_n6_preview = ['need' => false, 'html' => ''];
if (!empty($_is_pengantar_nikah) && function_exists('sipandang_pengantar_nikah_n6_dari_preview')) {
    $_n6_preview = sipandang_pengantar_nikah_n6_dari_preview(
        $_POST,
        $w,
        $instansi ?: [],
        $no_surat,
        $tgl_surat_indo,
        $header_ttd,
        (string) ($p['nama_pamong'] ?? ''),
        (string) ($p['nip'] ?? ''),
        (string) $ttl_format,
        (string) $alamat_surat_html
    );
}

// Setelah semua logic, baru include header/sidebar
include 'header.php';
include 'sidebar.php';
?>
<style>
<?php if (empty($_is_pengantar_nikah)): ?>
<?php
if (!function_exists('sipandang_kop_kelurahan_cetak_form_css')) {
    require_once __DIR__ . '/inc_kop_kelurahan.php';
}
if (function_exists('sipandang_kop_kelurahan_cetak_form_css')) {
    echo sipandang_kop_kelurahan_cetak_form_css(sipandang_kop_kelurahan_data($koneksi));
} elseif (function_exists('sipandang_kop_kelurahan_css')) {
    echo sipandang_kop_kelurahan_css(sipandang_kop_kelurahan_data($koneksi));
}
?>
<?php endif; ?>
<?php if (!empty($_is_pengantar_nikah)) { echo sipandang_pengantar_nikah_css(); } ?>
<?php if (!empty($_n6_preview['need']) && function_exists('sipandang_pengantar_nikah_n6_css')) { echo sipandang_pengantar_nikah_n6_css(); } ?>
:root {
    --navy     : #0a1628; --navy-mid: #142240; --navy-soft: var(--role-ink,#1e3a5f);
    --accent   : var(--role,#0b5cad); --accent-lt: var(--role-soft,#e8f2fd);
    --bg       : #eceff5; --white: #fff;
    --text     : #172036; --muted: #64708b; --border: #dde3ed;
    --success  : #0e7050; --danger: #dc3a52;
    --radius   : 12px;
    --shadow-md: 0 6px 28px rgba(10,22,40,.14);
}

/* ---- LAYOUT ---- */
.preview-layout { display: flex; gap: 0; height: calc(100vh - 57px); overflow: hidden; }
.sidebar-panel {
    width: 310px; flex-shrink: 0;
    background: var(--white);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow-y: auto;
}
.preview-area {
    flex: 1; overflow: auto;
    background: #525659;
    padding: 30px 20px 60px;
    position: relative;
}

/* ---- SIDEBAR PANEL ---- */
.sidebar-header {
    background: var(--navy); color: var(--white);
    padding: 18px 20px;
    border-bottom: 3px solid var(--accent);
    flex-shrink: 0;
}
.sidebar-header h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; margin: 0; color: var(--accent); }
.sidebar-header p  { font-size: .72rem; color: rgba(255,255,255,.55); margin: 3px 0 0; }

.sidebar-section { padding: 18px 20px; border-bottom: 1px solid var(--border); }
.sidebar-section:last-child { border-bottom: none; }
.sidebar-section-title {
    font-size: .68rem; font-weight: 700; letter-spacing: 1px;
    text-transform: uppercase; color: var(--muted);
    margin-bottom: 12px;
}

/* Surat info chips */
.info-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.info-row .info-icon { width: 28px; height: 28px; border-radius: 6px; background: var(--navy); color:var(--role-light); display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
.info-row .info-body .label { font-size: .68rem; color: var(--muted); letter-spacing: .3px; }
.info-row .info-body .value { font-size: .82rem; font-weight: 600; color: var(--text); line-height: 1.3; }

/* ---- ACTION BUTTONS ---- */
.action-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 8px;
    cursor: pointer; margin-bottom: 8px;
    transition: all .18s; border: none;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .82rem; font-weight: 600;
    text-align: left; width: 100%;
    text-decoration: none;
}
.action-btn i { width: 20px; text-align: center; font-size: .9rem; }
.action-btn-primary { background: var(--navy); color:var(--role-light); }
.action-btn-primary:hover { background: var(--navy-soft); color: var(--accent-lt); transform: translateX(2px); }
.action-btn-warning { background: #fff8e6; color: #7a5800; border: 1.5px solid #f0d080; }
.action-btn-warning:hover { background: #f0d080; }
.action-btn-danger  { background: #fef2f2; color: var(--danger); border: 1.5px solid #fca5a5; }
.action-btn-danger:hover  { background: #ec0808; color: #fff; }
.action-btn-ghost   { background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); }
.action-btn-ghost:hover   { border-color: var(--navy); color: var(--navy); }
.action-btn-success { background: #f0faf4; color: var(--success); border: 1.5px solid #86efac; }
.action-btn-success:hover { background: var(--success); color: #fff; }

/* ---- EDIT TOOLBAR ---- */
.edit-toolbar {
    display: none;
    background: var(--navy);
    padding: 10px 14px;
    border-radius: 8px;
    flex-wrap: wrap; gap: 6px;
    margin-bottom: 10px;
    align-items: center;
}
.edit-toolbar.show { display: flex; }
.toolbar-btn {
    background: rgba(255,255,255,.1); color: var(--accent);
    border: 1px solid rgba(var(--role-rgb,11,92,173),.3);
    border-radius: 5px; padding: 4px 10px;
    cursor: pointer; font-size: .78rem;
    transition: background .15s;
}
.toolbar-btn:hover { background: rgba(var(--role-rgb,11,92,173),.2); }
.toolbar-sep { width: 1px; height: 20px; background: rgba(255,255,255,.15); margin: 0 3px; }
.edit-mode-badge {
    background: var(--accent); color:#fff;
    padding: 3px 10px; border-radius: 20px;
    font-size: .7rem; font-weight: 800; letter-spacing: .5px;
    margin-left: auto;
}

/* ---- KERTAS SURAT ---- */
.kertas-surat {
    background: white;
    width: <?= $width_css; ?>;
    min-height: <?= $height_css; ?>;
    padding: <?= $padding_css; ?>;
    margin: 0 auto;
    font-family: <?= function_exists('sipandang_surat_keluar_font_family') ? sipandang_surat_keluar_font_family() : 'Arial, Helvetica, sans-serif'; ?>;
    font-size: <?= $is_landscape ? '11pt' : ($is_mutasi_pbb ? '11pt' : $font_surat_a4); ?>; color: black;
    line-height: <?= $is_landscape ? '1.4' : ($is_mutasi_pbb ? '1.55' : $lh_surat_a4); ?>;
    box-shadow: 0 4px 30px rgba(0,0,0,.5);
    box-sizing: border-box;
    position: relative;
    transition: outline .2s;
}
<?php echo sipandang_surat_keluar_css_for_format($tipe_layanan, $j, $is_landscape); ?>
.kertas-surat.edit-aktif {
    outline: 3px dashed var(--accent);
    cursor: text;
}
.kertas-surat.surat-mutasi-pbb p { margin-bottom: 16px; }
.kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) p { margin-bottom: 6px; }
.kertas-surat:not(.surat-keluar-a4) p { margin-bottom: 8px; }
.kertas-surat .kop-kel-wrap p,
.kertas-surat .kop-kel-line { margin: 0 !important; padding: 0 !important; margin-bottom: 0 !important; }

/* KOP */
.kop-header { margin-bottom: 0; width: 100%; border-collapse: collapse; border: none; line-height: 1.08; }
.kop-header td { vertical-align: middle; padding: 0 !important; line-height: 1.08; }
.kop-header span { margin: 0 !important; margin-top: 0 !important; line-height: inherit; }

/* Print Preview Overlay */
.print-hint {
    position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
    background: var(--navy); color: rgba(255,255,255,.8);
    padding: 7px 18px; border-radius: 20px; font-size: .75rem;
    pointer-events: none;
    border: 1px solid rgba(var(--role-rgb,11,92,173),.3);
}

/* Mode indicator */
.mode-bar {
    text-align: center; margin-bottom: 12px;
}
.mode-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.12); color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.15);
    padding: 4px 14px; border-radius: 20px; font-size: .72rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.mode-chip.editing { background: rgba(var(--role-rgb,11,92,173),.2); color: var(--accent-lt); border-color: rgba(var(--role-rgb,11,92,173),.4); }

@media print {
    @page { size: <?= htmlspecialchars($layout_surat['page_css'] ?? $page_size, ENT_QUOTES, 'UTF-8'); ?>; margin: 0; }
    body, html { margin:0; padding:0; background:white; }
    .sidebar-panel, .main-footer, .navbar, .main-sidebar, .edit-toolbar, .mode-bar, .print-hint { display:none !important; }
    .preview-layout { height: auto; overflow: visible; }
    .preview-area { background: white; padding: 0; overflow: visible; }
    .kertas-surat { margin:0; box-shadow:none; width:100%; min-height:0; outline:none !important; }
    .kertas-surat.surat-keluar-a4:not(.surat-mutasi-pbb) { padding: <?= $pad_surat_print; ?> !important; }
}

@media (max-width: 767px) {
    .preview-layout { flex-direction: column; height: auto; }
    .sidebar-panel { width: 100%; max-height: 40vh; }
    .preview-area { padding: 16px 8px 40px; }
}
</style>
<style>
/* BPJS tone alignment (UI only) */
:root{
    --navy:var(--role-dark,#05285a) !important;
    --navy-mid:var(--role-ink,#243a8a) !important;
    --navy-soft:var(--role-ink,#08428a) !important;
    --accent:var(--role,#0b5cad) !important;
    --accent-lt:var(--role-soft,#e8f2fd) !important;
    --bg:#f4f6fb !important;
    --muted:#566583 !important;
    --border:#e3e8f3 !important;
}
.content-wrapper{background:var(--bg)!important;}
.preview-sidebar{
    border-right:1px solid var(--border) !important;
    background:#fff !important;
}
.sidebar-header{
    background:#fff !important;
    color:var(--navy) !important;
    border-bottom:1px solid var(--border) !important;
}
.sidebar-header h2{
    font-family:'Plus Jakarta Sans',sans-serif !important;
    color:var(--navy) !important;
    font-size:.95rem !important;
    font-weight:800 !important;
}
.sidebar-header p{color:var(--muted) !important;}
.info-row .info-icon{
    background:var(--role-soft,#e8f2fd) !important;
    color:var(--navy-soft) !important;
}
.action-btn-primary{background:var(--accent) !important;color:#fff !important;}
.action-btn-primary:hover{background:var(--navy-soft) !important;color:#fff !important;}
.action-btn-warning{background:var(--role-soft,#e8f2fd) !important;color:var(--role-ink,#08428a) !important;border:1.5px solid var(--role-line,#c6dcf5) !important;}
.action-btn-warning:hover{background:var(--role-soft,#dfe7ff) !important;}
.action-btn-ghost:hover{border-color:var(--accent) !important;color:var(--navy-soft) !important;}
.action-btn-success{background:var(--role-soft,#e8f2fd) !important;color:var(--role-ink,#08428a) !important;border:1.5px solid var(--role-line,#c6dcf5) !important;}
.action-btn-success:hover{background:var(--accent) !important;color:#fff !important;}
.ttd-flow-box{
    background:#f7fbff;border:1px solid #cfe3f7;border-radius:10px;padding:12px;margin-top:4px;
}
.ttd-flow-box .ttd-flow-title{
    font-size:.68rem;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#355a7a;margin-bottom:8px;
}
.ttd-flow-btn{
    display:flex;align-items:flex-start;gap:10px;width:100%;text-align:left;
    border:1.5px solid #e3e8f3;border-radius:10px;background:#fff;padding:10px 12px;
    margin-bottom:8px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:.15s;
}
.ttd-flow-btn:hover{border-color:var(--role,#0b5cad);background:var(--role-soft,#f0f9ff);}
.ttd-flow-btn.recommended{border-color:var(--role,#0b5cad);background:var(--role-soft,#e8f2fd);}
.ttd-flow-btn .tf-ico{width:28px;text-align:center;color:var(--role,#0b5cad);padding-top:2px;}
.ttd-flow-btn .tf-t{font-size:.8rem;font-weight:700;color:var(--role-dark,#05285a);display:block;}
.ttd-flow-btn .tf-s{font-size:.68rem;color:#566583;font-weight:500;display:block;margin-top:2px;line-height:1.35;}
.ttd-flow-btn-lurah{border-color:#c9a227;background:#fffdf5;}
.ttd-flow-btn-lurah:hover{border-color:#c9a227;background:#fff8e6;}
.ttd-flow-btn-lurah .tf-ico{color:#b8860b;}
.berkas-panel { font-size: .78rem; }
.berkas-status { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; margin-bottom:10px; font-weight:600; }
.berkas-status.ok { background:#e8f5e9; color:#2e7d32; }
.berkas-status.warn { background:#fff3e0; color:#e65100; }
.berkas-status.err { background:#ffebee; color:#c62828; }
.berkas-checklist { max-height:140px; overflow-y:auto; margin:0 0 10px; padding:0; list-style:none; }
.berkas-checklist li { display:flex; align-items:flex-start; gap:6px; padding:4px 0; font-size:.72rem; color:var(--muted); line-height:1.35; }
.berkas-checklist li i { margin-top:2px; font-size:.65rem; }
.berkas-checklist li.ok i { color:#2e7d32; }
.berkas-checklist li.no i { color:#c62828; }
.berkas-upload-zone {
    border:2px dashed var(--border); border-radius:10px; padding:12px; text-align:center;
    background:#fafbfc; cursor:pointer; transition:border-color .2s;
}
.berkas-upload-zone:hover, .berkas-upload-zone.drag { border-color:var(--role,#0b5cad); background:#f0f7ff; }
.berkas-upload-zone input[type=file] { display:none; }
.berkas-uploaded { font-size:.72rem; color:#2e7d32; margin-top:8px; word-break:break-all; }
.berkas-item-row{
  border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin-bottom:8px;background:#fafbfc;
}
.berkas-item-row.ok{border-color:#a5d6a7;background:#f1f8f2}
.berkas-item-row .bir-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px}
.berkas-item-row .bir-nama{font-size:.72rem;font-weight:600;color:var(--role-dark,#05285a);line-height:1.35;flex:1}
.berkas-item-row .bir-status{font-size:.65rem;color:var(--muted)}
.berkas-item-row .bir-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.berkas-item-row input[type=file]{display:none}
.berkas-btn-mini{
  font-size:.65rem;font-weight:600;padding:4px 8px;border-radius:6px;border:1px solid var(--border);
  background:#fff;cursor:pointer;color:var(--role-dark,#05285a);
}
.berkas-manual-add{margin-top:8px;padding-top:8px;border-top:1px dashed var(--border)}
.berkas-manual-row{display:flex;gap:6px;margin-bottom:8px;align-items:center}
.berkas-uploaded.none { color:#c62828; }
.btn-kirim-pejabat { opacity:.55; pointer-events:none; }
.btn-kirim-pejabat.siap { opacity:1; pointer-events:auto; }
.stepper-head,.preview-foot{
    background:var(--navy-soft) !important;
    color:#fff !important;
}
</style>

<div class="content-wrapper" style="padding:0;">
    <div class="preview-layout">

        <!-- ====================== SIDEBAR PANEL ====================== -->
        <div class="sidebar-panel">
            <div class="sidebar-header">
                <h2><i class="fas fa-<?= $perbaiki_arsip ? 'tools' : 'file-contract'; ?> mr-2"></i><?= $perbaiki_arsip ? 'Perbaiki Surat' : 'Preview Surat'; ?></h2>
                <p><?= $perbaiki_arsip ? 'Edit isi surat yang dikembalikan pejabat' : 'Pratinjau sebelum simpan & cetak'; ?></p>
            </div>

            <?php if ($perbaiki_arsip): ?>
            <div class="sidebar-section" style="background:#fff8f0;border-bottom:1px solid #f0d9b8;">
                <div class="sidebar-section-title" style="color:#b45309;">Mode Perbaikan</div>
                <p style="font-size:.75rem;color:#7a5800;margin:0 0 8px;line-height:1.45;">
                    Surat dikembalikan pejabat. Perbaiki teks di sini, simpan, lalu kirim ulang dari arsip atau langsung ke pejabat.
                </p>
                <?php if ($catatan_pejabat_perbaiki !== ''): ?>
                <div style="font-size:.72rem;background:#fff;border:1px solid #f0d9b8;border-radius:8px;padding:10px;line-height:1.45;color:#5c4a32;">
                    <strong>Catatan pejabat:</strong> <?= htmlspecialchars($catatan_pejabat_perbaiki); ?>
                </div>
                <?php endif; ?>
                <?php if ($arsip_kembali_url !== ''): ?>
                <a href="<?= htmlspecialchars($arsip_kembali_url); ?>" class="action-btn action-btn-ghost" style="margin-top:10px;margin-bottom:0;">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Arsip</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- INFO SURAT -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Informasi Surat</div>

                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="info-body">
                        <div class="label">Jenis Surat</div>
                        <div class="value"><?= htmlspecialchars($j['nama_surat']); ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-hashtag"></i></div>
                    <div class="info-body">
                        <div class="label">Nomor Surat</div>
                        <div class="value" style="font-family:monospace; font-size:.78rem;"><?= htmlspecialchars($no_surat); ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-calendar"></i></div>
                    <div class="info-body">
                        <div class="label">Tanggal Surat</div>
                        <div class="value"><?= $tgl_surat_indo; ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-user"></i></div>
                    <div class="info-body">
                        <div class="label">Pemohon</div>
                        <div class="value"><?= htmlspecialchars(strtoupper($w['nama_lengkap'])); ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-pen-nib"></i></div>
                    <div class="info-body">
                        <div class="label">Penandatangan</div>
                        <div class="value"><?= htmlspecialchars($p['nama_pamong']); ?><br><span style="font-size:.7rem; color:var(--muted);"><?= htmlspecialchars($p['jabatan']); ?></span></div>
                    </div>
                </div>
            </div>

            <!-- BERKAS KELENGKAPAN -->
            <div class="sidebar-section berkas-panel">
                <div class="sidebar-section-title">Berkas Kelengkapan</div>
                <?php if ($kelengkapan_cek_preview['ok']): ?>
                <div class="berkas-status ok"><i class="fas fa-check-circle"></i> Checklist lengkap</div>
                <?php else: ?>
                <div class="berkas-status err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($kelengkapan_cek_preview['msg'] ?: 'Checklist belum lengkap'); ?></div>
                <?php endif; ?>
                <?php if (!empty($kel_items_preview)): ?>
                <ul class="berkas-checklist">
                    <?php foreach ($kel_items_preview as $ki):
                        $kiAda = !empty($ki['ada']);
                        $kiNama = trim((string) ($ki['nama_berkas'] ?? $ki['nama'] ?? 'Item'));
                    ?>
                    <li class="<?= $kiAda ? 'ok' : 'no' ?>">
                        <i class="fas fa-<?= $kiAda ? 'check' : 'times' ?>"></i>
                        <span><?= htmlspecialchars($kiNama); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="font-size:.7rem;color:var(--muted);margin:0 0 10px;line-height:1.45;">Tidak ada checklist wajib untuk jenis surat ini.</p>
                <?php endif; ?>
                <div class="berkas-upload-zone" id="berkasDropZone" onclick="document.getElementById('berkasFileInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem;color:var(--accent);margin-bottom:6px"></i>
                    <div style="font-weight:600;font-size:.75rem">Upload Berkas PDF</div>
                    <div style="font-size:.68rem;color:var(--muted);margin-top:4px">1 file PDF gabungan — maks. 15 MB</div>
                    <input type="file" id="berkasFileInput" accept=".pdf,application/pdf">
                </div>
                <div id="berkasUploadedInfo" class="berkas-uploaded <?= $preview_berkas_info ? '' : 'none' ?>">
                    <?php if ($preview_berkas_info): ?>
                    <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($preview_berkas_info['orig_name']); ?>
                    <?php else: ?>
                    <i class="fas fa-info-circle"></i> Belum ada berkas PDF di-upload
                    <?php endif; ?>
                </div>
            </div>

            <!-- AKSI UTAMA -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Aksi Utama</div>
                <form id="formSimpan" action="simpan_surat.php" method="POST">
                    <?php foreach($_POST as $key => $val): if(is_array($val)) continue; ?>
                        <input type="hidden" name="<?= htmlspecialchars($key); ?>" value="<?= htmlspecialchars($val); ?>">
                    <?php endforeach; ?>
                    <textarea name="isi_surat_html" id="html_content" style="display:none;"></textarea>
                    <input type="hidden" name="mode_simpan" id="mode_simpan" value="<?= $perbaiki_arsip ? 'perbaiki_simpan' : 'manual'; ?>">
                    <input type="hidden" name="preview_token" id="preview_token" value="<?= htmlspecialchars($token); ?>">
                    <?php if ($perbaiki_arsip): ?>
                    <button type="button" onclick="simpanDanCetak('perbaiki_simpan')" class="action-btn action-btn-primary">
                        <i class="fas fa-save"></i>
                        <span>
                            <span style="display:block; font-size:.88rem;">Simpan Perbaikan</span>
                            <span style="font-size:.7rem; opacity:.7; font-weight:400;">Status tetap DIKEMBALIKAN — kirim ulang nanti</span>
                        </span>
                    </button>
                    <?php else: ?>
                    <button type="button" onclick="simpanDanCetak('manual')" class="action-btn action-btn-primary">
                        <i class="fas fa-save"></i>
                        <span>
                            <span style="display:block; font-size:.88rem;">Cetak Manual</span>
                            <span style="font-size:.7rem; opacity:.7; font-weight:400;">Alur lama — simpan & cetak (TTD basah)</span>
                        </span>
                    </button>
                    <?php endif; ?>
                </form>
                <?php if (!$perbaiki_arsip): ?>
                <button type="button" onclick="window.print()" class="action-btn action-btn-success">
                    <i class="fas fa-print"></i>
                    <span>
                        <span style="display:block;">Cetak Langsung</span>
                        <span style="font-size:.7rem; opacity:.7; font-weight:400;">Tanpa simpan ke arsip</span>
                    </span>
                </button>
                <?php endif; ?>
            </div>

            <!-- ALUR TTD DIGITAL -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Alur TTD Digital</div>
                <div class="ttd-flow-box">
                    <div class="ttd-flow-title"><i class="fas fa-route mr-1"></i> Kirim ke Pejabat</div>
                    <p style="font-size:.72rem;color:var(--muted);margin:0 0 10px;line-height:1.45;">
                        Akan dikirim ke: <strong id="hintTujuanKirim"><?= htmlspecialchars($label_kirim_pejabat); ?></strong>
                    </p>
                    <button type="button"
                            id="btnKirimPejabat"
                            class="ttd-flow-btn recommended btn-kirim-pejabat <?= $berkas_gate_siap ? 'siap' : '' ?>"
                            onclick="simpanDanCetak('<?= htmlspecialchars($kirim_mode_auto); ?>')">
                        <span class="tf-ico"><i class="fas fa-paper-plane"></i></span>
                        <span>
                            <span class="tf-t">Kirim ke Pejabat untuk TTD Digital</span>
                            <span class="tf-s" id="hintGateKirim"><?= $berkas_gate_siap ? 'Checklist & berkas siap — klik untuk kirim' : 'Lengkapi checklist dan upload scan berkas dulu'; ?></span>
                        </span>
                    </button>
                    <p style="font-size:.68rem;color:#566583;margin:8px 0 0;line-height:1.45;">
                        <?php if ($perbaiki_arsip): ?>
                        Simpan perbaikan lalu kirim ke pejabat — atau gunakan panel <strong>Kirim Ulang</strong> di arsip.
                        <?php else: ?>
                        Surat disimpan ke arsip dan menunggu persetujuan pejabat. Operator cetak setelah status <strong>Siap Cetak</strong>.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- EDIT SECTION -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Mode Edit Manual</div>
                <button type="button" onclick="aktifkanEdit()" id="btnEdit" class="action-btn action-btn-warning">
                    <i class="fas fa-edit"></i>
                    <span>
                        <span style="display:block;">Aktifkan Edit Teks</span>
                        <span style="font-size:.7rem; opacity:.7; font-weight:400;">Klik teks di surat untuk mengubah</span>
                    </span>
                </button>
                <button type="button" onclick="matikanEdit()" id="btnSelesai" class="action-btn action-btn-ghost" style="display:none;">
                    <i class="fas fa-check"></i>
                    <span>Selesai Edit</span>
                </button>
                <p style="font-size:.7rem; color:var(--muted); margin:8px 0 0; line-height:1.5;">
                    Mode edit memungkinkan perubahan teks langsung di tampilan surat sebelum dicetak.
                </p>
            </div>

            <!-- NAVIGASI -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Navigasi</div>
                <form action="buat_surat.php" method="POST">
                    <?php foreach($_POST as $key => $val): if(is_array($val)) continue; ?>
                        <input type="hidden" name="<?= htmlspecialchars($key); ?>" value="<?= htmlspecialchars($val); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="action-btn action-btn-ghost">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali ke Form</span>
                    </button>
                </form>
                <a href="buat_surat.php" class="action-btn action-btn-danger mt-1">
                    <i class="fas fa-times"></i>
                    <span>Batalkan & Mulai Baru</span>
                </a>
            </div>
        </div>

        <!-- ====================== PREVIEW AREA ====================== -->
        <div class="preview-area">

            <!-- Mode indicator -->
            <div class="mode-bar">
                <div class="mode-chip" id="mode-chip">
                    <i class="fas fa-eye"></i> Mode Preview
                </div>
            </div>

            <!-- Edit Toolbar -->
            <div class="edit-toolbar" id="editToolbar">
                <button class="toolbar-btn" onclick="execCmd('bold')"><i class="fas fa-bold"></i></button>
                <button class="toolbar-btn" onclick="execCmd('italic')"><i class="fas fa-italic"></i></button>
                <button class="toolbar-btn" onclick="execCmd('underline')"><i class="fas fa-underline"></i></button>
                <div class="toolbar-sep"></div>
                <button class="toolbar-btn" onclick="execCmd('justifyLeft')"><i class="fas fa-align-left"></i></button>
                <button class="toolbar-btn" onclick="execCmd('justifyCenter')"><i class="fas fa-align-center"></i></button>
                <button class="toolbar-btn" onclick="execCmd('justifyRight')"><i class="fas fa-align-right"></i></button>
                <button class="toolbar-btn" onclick="execCmd('justifyFull')"><i class="fas fa-align-justify"></i></button>
                <div class="toolbar-sep"></div>
                <button class="toolbar-btn" onclick="execCmd('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
                <button class="toolbar-btn" onclick="execCmd('undo')"><i class="fas fa-undo"></i></button>
                <button class="toolbar-btn" onclick="execCmd('redo')"><i class="fas fa-redo"></i></button>
                <div class="toolbar-sep"></div>
                <select onchange="execCmd('fontSize', this.value); this.value=''" class="toolbar-btn" style="padding:3px 6px;">
                    <option value="">Ukuran</option>
                    <option value="1">8pt</option><option value="2">10pt</option>
                    <option value="3">12pt</option><option value="4">14pt</option>
                    <option value="5">18pt</option><option value="6">24pt</option>
                </select>
                <span class="edit-mode-badge"><i class="fas fa-pen mr-1"></i> EDIT AKTIF</span>
            </div>

            <!-- KERTAS SURAT -->
            <div id="area_surat" class="<?= htmlspecialchars($class_kertas) ?>">

                <?php if ($is_landscape && $tipe_layanan == 'KEMATIAN'): ?>
                    <?php
                    $status_kawin_kematian = strtoupper(sipandang_label_kawin($w['status_perkawinan'] ?? ''));
                    $gender         = ($w['jenis_kelamin']=='L') ? 'LAKI-LAKI' : 'PEREMPUAN';
                    $alamat_singkat = strtoupper($w['alamat']).' RT.'.$w['rt'].' RW.'.$w['rw'];
                    $alamat_lengkap = $alamat_singkat.'<br>KEL. '.strtoupper($instansi['nama_kelurahan']).' KEC. '.strtoupper($instansi['nama_kecamatan']);
                    $ttl_lengkap    = strtoupper($w['tempat_lahir']).', '.tgl_indo($w['tgl_lahir']);
                    $style_box_judul = "border:1px solid #000; padding:3px; text-align:center; font-weight:bold; margin-bottom:5px; font-family:Arial,sans-serif; font-size:9pt; width:90%; margin-left:auto; margin-right:auto;";
                    $style_table_data = "width:100%; font-size:8pt; line-height:1.6; border:0;";
                    ?>
                    <table style="width:100%; height:100%; border-collapse:collapse; font-family:'Times New Roman',serif; font-size:8pt;">
                        <tr>
                            <td style="width:33%; padding-right:10px; border-right:1px dashed #000; vertical-align:top;">
                                <div style="<?= $style_box_judul ?>">ARSIP DESA / KELURAHAN</div>
                                <div style="text-align:center; margin-bottom:15px;"><b style="font-size:10pt; text-decoration:underline;">SURAT KEMATIAN</b><br><span>No. <?= $no_surat; ?></span></div>
                                <p style="margin-bottom:10px;">Yang bertanda tangan dibawah ini, menerangkan bahwa :</p>
                                <table style="<?= $style_table_data ?>">
                                    <tr><td width="70">Nama</td><td width="5">:</td><td><b><?= strtoupper($w['nama_lengkap']); ?></b></td></tr>
                                    <tr><td>Jenis Kelamin</td><td>:</td><td><?= $gender; ?></td></tr>
                                    <tr><td valign="top">Alamat</td><td valign="top">:</td><td style="line-height:1.2;"><?= $alamat_lengkap; ?></td></tr>
                                    <tr><td>Umur</td><td>:</td><td><b><?= $umur_jenazah; ?> TAHUN</b></td></tr>
                                </table>
                                <p style="margin-top:15px; margin-bottom:10px;">Telah meninggal dunia pada :</p>
                                <table style="<?= $style_table_data ?>">
                                    <tr><td width="70">Hari</td><td width="5">:</td><td><?= strtoupper($hari_mati); ?></td></tr>
                                    <tr><td>Tanggal</td><td>:</td><td><?= $tgl_mati_indo; ?></td></tr>
                                    <tr><td>Di</td><td>:</td><td><?= strtoupper($mati_tempat); ?></td></tr>
                                    <tr><td>Sebab</td><td>:</td><td><?= strtoupper($mati_sebab); ?></td></tr>
                                </table>
                                <p style="margin-top:15px; text-indent:20px;">Surat Kematian ini dibuat berdasarkan Surat Pengantar Ketua <b><?= strtoupper('RT.'.$w['rt'].' / RW.'.$w['rw']); ?></b> dan Surat Pernyataan dari Ahli Waris Almarhum <b><?= strtoupper($w['nama_lengkap']); ?></b>.</p>
                                <div style="margin-top:25px;">
                                    <table style="width:100%; font-size:9pt; border:0;">
                                        <tr>
                                            <td width="45%" valign="top">Nama Pelapor:<br><b><?= strtoupper($mati_pelapor); ?></b><br><br>Hubungan:<br><b><?= strtoupper($mati_hubungan); ?></b></td>
                                            <td width="55%" align="center" valign="top"><?= $blok_ttd_kecil; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                            <td style="width:34%; padding-left:10px; padding-right:10px; border-right:1px dashed #000; vertical-align:top;">
                                <div style="<?= $style_box_judul ?>">ARSIP KECAMATAN</div>
                                <div style="border:2px solid #000; padding:5px; height:75%; margin-bottom:10px;">
                                    <div style="text-align:center; margin-bottom:15px;"><b style="font-size:10pt; text-decoration:underline;">SURAT KEMATIAN</b><br><span>No. <?= $no_surat; ?></span></div>
                                    <table style="width:100%; font-size:8.5pt; line-height:1.6;">
                                        <tr><td width="15">1.</td><td width="85">Nama lengkap</td><td width="5">:</td><td><b><?= strtoupper($w['nama_lengkap']); ?></b></td></tr>
                                        <tr><td>2.</td><td>Jenis Kelamin</td><td>:</td><td><?= $gender; ?></td></tr>
                                        <tr><td valign="top">3.</td><td valign="top">Alamat</td><td valign="top">:</td><td><?= $alamat_singkat; ?><br>KEL.<?= strtoupper($instansi['nama_kelurahan']); ?></td></tr>
                                        <tr><td>4.</td><td>Dilahirkan</td><td>:</td><td><?= $ttl_lengkap; ?></td></tr>
                                        <tr><td>5.</td><td>Tgl. kematian</td><td>:</td><td><?= $tgl_mati_indo; ?></td></tr>
                                        <tr><td>6.</td><td>Umur</td><td>:</td><td><b><?= $umur_jenazah; ?> TAHUN</b></td></tr>
                                        <tr><td>7.</td><td>Kewarganegaraan</td><td>:</td><td>INDONESIA</td></tr>
                                        <tr><td>8.</td><td>Agama</td><td>:</td><td><?= konversi_agama($w['agama']); ?></td></tr>
                                        <tr><td>9.</td><td>Status perkawinan</td><td>:</td><td><?= htmlspecialchars($status_kawin_kematian ?: '-'); ?></td></tr>
                                        <tr><td>10.</td><td>Pekerjaan</td><td>:</td><td><?= htmlspecialchars(sipandang_tampil_pekerjaan($w['pekerjaan'] ?? '', true)); ?></td></tr>
                                        <tr><td>11.</td><td>Tempat kematian</td><td>:</td><td><?= strtoupper($mati_tempat); ?></td></tr>
                                        <tr><td>12.</td><td>Yang menentukan</td><td>:</td><td><?= strtoupper($mati_penentu); ?></td></tr>
                                        <tr><td>13.</td><td>No. KK / KTP</td><td>:</td><td><?= $w['no_kk']; ?> / <?= $w['nik']; ?></td></tr>
                                    </table>
                                </div>
                                <div style="text-align:center;">Mengetahui,<br><?= $blok_ttd_kecil; ?></div>
                            </td>
                            <td style="width:33%; padding-left:10px; vertical-align:top;">
                                <div style="<?= $style_box_judul ?>">UNTUK YANG BERSANGKUTAN</div>
                                <div style="text-align:center; margin-bottom:10px; margin-top:15px;"><b style="font-size:11pt; text-decoration:underline;">SURAT KEMATIAN</b><br><span>No. <?= $no_surat; ?></span></div>
                                <p>Yang bertanda tangan dibawah ini, menerangkan bahwa :</p>
                                <table style="<?= $style_table_data ?>">
                                    <tr><td width="70">Nama</td><td width="5">:</td><td><b><?= strtoupper($w['nama_lengkap']); ?></b></td></tr>
                                    <tr><td>NIK / KK</td><td>:</td><td><?= $w['nik']; ?> / <?= $w['no_kk']; ?></td></tr>
                                    <tr><td>Jenis Kelamin</td><td>:</td><td><?= $gender; ?></td></tr>
                                    <tr><td valign="top">Alamat</td><td valign="top">:</td><td><?= $alamat_lengkap; ?></td></tr>
                                    <tr><td>Umur</td><td>:</td><td><b><?= $umur_jenazah; ?> TAHUN</b></td></tr>
                                </table>
                                <p style="margin-top:15px;">Telah meninggal dunia pada :</p>
                                <table style="<?= $style_table_data ?>">
                                    <tr><td width="70">Hari</td><td width="5">:</td><td><?= strtoupper($hari_mati); ?></td></tr>
                                    <tr><td>Tanggal</td><td>:</td><td><?= $tgl_mati_indo; ?></td></tr>
                                    <tr><td>Di</td><td>:</td><td><?= strtoupper($mati_tempat); ?></td></tr>
                                    <tr><td>Sebab</td><td>:</td><td><?= strtoupper($mati_sebab); ?></td></tr>
                                </table>
                                <p style="margin-top:15px; text-indent:25px;">Surat Kematian ini dibuat berdasarkan Surat Pengantar Ketua <b><?= strtoupper('RT.'.$w['rt'].' / RW.'.$w['rw']); ?></b> dan Surat Pernyataan dari Ahli Waris Almarhum <b><?= strtoupper($w['nama_lengkap']); ?></b>.</p>
                                <div style="margin-top:25px;">
                                    <table style="width:100%; font-size:9pt; border:0;">
                                        <tr>
                                            <td width="45%" valign="top">Nama Pelapor:<br><b><?= strtoupper($mati_pelapor); ?></b><br><br>Hubungan:<br><b><?= strtoupper($mati_hubungan); ?></b></td>
                                            <td width="55%" align="center" valign="top"><?= $blok_ttd_kecil; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>

                <?php else: ?>

                    <?php if (empty($_is_pengantar_nikah) && strtoupper(trim((string) $j['kop_otomatis'])) === 'YA'): ?>
                    <?= format_surat_kop_html(['mysqli' => $koneksi]); ?>
                    <?php endif; ?>

                    <?php if ($format_resmi && !$pbb_metadata_di_template): ?>
                    <?= format_surat_tanggal_kanan($instansi['nama_kelurahan'], $tgl_surat_indo); ?>
                    <?= format_surat_metadata($no_surat, strtoupper($j['nama_surat'])); ?>
                    <?php endif; ?>

                    <div class="isi-surat-content"><?= $surat; ?></div>

                    <?php
                    $surat_sudah_ada_ttd = strpos($surat, '[tanda_tangan]') !== false
                        || stripos($surat, 'casip-ttd-block') !== false
                        || stripos($surat, 'casip-nama-pejabat') !== false;
                    ?>
                    <?php if (!$surat_sudah_ada_ttd): ?>
                    <?php if ($format_resmi): ?>
                    <?= $blok_ttd_besar; ?>
                    <?php else: ?>
                    <?= function_exists('format_surat_ttd_keterangan_tabel_html')
                        ? format_surat_ttd_keterangan_tabel_html(
                            $instansi['nama_kelurahan'] ?? $nama_kel,
                            $tgl_surat_indo,
                            $header_ttd,
                            $p['nama_pamong'],
                            $p['nip'],
                            $ff_surat
                        )
                        : '<table width="100%" border="0" style="margin-top:30px;"><tr><td width="50%"></td>'
                            . '<td width="50%" align="center">'.$blok_ttd_besar.'</td></tr></table>'; ?>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($_n6_preview['need']) && !empty($_n6_preview['html'])): ?>
                    <?= $_n6_preview['html']; ?>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <div class="print-hint" id="printHint">
                <i class="fas fa-print mr-1"></i> Ctrl+P atau gunakan tombol Simpan & Cetak
            </div>
        </div>

    </div>
</div>

<script>
var kertas = document.getElementById('area_surat');
var formSimpan = document.getElementById('formSimpan');
var isEditing = false;
var previewToken = <?= json_encode($token); ?>;
var kelengkapanSiap = <?= $kelengkapan_cek_preview['ok'] ? 'true' : 'false'; ?>;
var berkasSudahUpload = <?= $preview_berkas_info ? 'true' : 'false'; ?>;
function updateGateKirim() {
    var btn = document.getElementById('btnKirimPejabat');
    var hint = document.getElementById('hintGateKirim');
    if (!btn) return;
    var siap = kelengkapanSiap && berkasSudahUpload;
    btn.classList.toggle('siap', siap);
    if (hint) {
        hint.textContent = siap
            ? 'Checklist & berkas siap — klik untuk kirim'
            : (!kelengkapanSiap ? 'Lengkapi checklist berkas wajib di form buat surat' : 'Upload berkas PDF terlebih dahulu');
    }
}

function uploadBerkasPreview(file) {
    if (!file || !previewToken) return;
    if (file.type && file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name || '')) {
        alert('Format berkas wajib PDF.');
        return;
    }
    var fd = new FormData();
    fd.append('token', previewToken);
    fd.append('berkas', file);
    var info = document.getElementById('berkasUploadedInfo');
    if (info) info.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
    fetch('upload_berkas_preview.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                berkasSudahUpload = true;
                if (info) {
                    info.className = 'berkas-uploaded';
                    info.innerHTML = '<i class="fas fa-file-pdf"></i> ' + (d.name || 'Berkas PDF terupload');
                }
                updateGateKirim();
            } else {
                berkasSudahUpload = false;
                if (info) {
                    info.className = 'berkas-uploaded none';
                    info.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (d.msg || 'Gagal upload');
                }
                alert(d.msg || 'Gagal upload berkas.');
            }
        })
        .catch(function() {
            if (info) {
                info.className = 'berkas-uploaded none';
                info.innerHTML = '<i class="fas fa-exclamation-circle"></i> Gagal upload';
            }
            alert('Gagal upload berkas. Coba lagi.');
        });
}

(function initBerkasUpload() {
    var inp = document.getElementById('berkasFileInput');
    var zone = document.getElementById('berkasDropZone');
    if (inp) {
        inp.addEventListener('change', function() {
            if (inp.files && inp.files[0]) uploadBerkasPreview(inp.files[0]);
        });
    }
    if (zone) {
        zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('drag'); });
        zone.addEventListener('dragleave', function() { zone.classList.remove('drag'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) uploadBerkasPreview(e.dataTransfer.files[0]);
        });
    }
    updateGateKirim();
})();

// ---- EDIT FUNCTIONS ----
function aktifkanEdit() {
    isEditing = true;
    kertas.contentEditable = 'true';
    kertas.classList.add('edit-aktif');
    document.getElementById('editToolbar').classList.add('show');
    document.getElementById('btnEdit').style.display = 'none';
    document.getElementById('btnSelesai').style.display = 'flex';
    document.getElementById('mode-chip').innerHTML = '<i class="fas fa-pen mr-1"></i> Mode Edit Aktif';
    document.getElementById('mode-chip').classList.add('editing');
    kertas.focus();
}

function matikanEdit() {
    isEditing = false;
    kertas.contentEditable = 'false';
    kertas.classList.remove('edit-aktif');
    document.getElementById('editToolbar').classList.remove('show');
    document.getElementById('btnEdit').style.display = 'flex';
    document.getElementById('btnSelesai').style.display = 'none';
    document.getElementById('mode-chip').innerHTML = '<i class="fas fa-eye mr-1"></i> Mode Preview';
    document.getElementById('mode-chip').classList.remove('editing');
}

function execCmd(cmd, val) {
    document.execCommand(cmd, false, val || null);
    kertas.focus();
}

function simpanDanCetak(mode) {
    mode = mode || 'manual';
    matikanEdit();
    var html = kertas.innerHTML;
    if (!html.trim()) {
        if (typeof window.sipandangShowToast === 'function') {
            window.sipandangShowToast('error', 'Isi surat kosong!');
        } else if (typeof window.showToast === 'function') {
            window.showToast('error', 'Isi surat kosong!');
        }
        return;
    }
    if (mode === 'perbaiki_simpan') {
        if (!confirm('Simpan perbaikan isi surat? Status tetap DIKEMBALIKAN sampai Anda kirim ulang ke pejabat.')) return;
    } else if (mode !== 'manual') {
        if (!kelengkapanSiap) {
            alert('Checklist kelengkapan berkas belum lengkap. Kembali ke form buat surat dan centang semua berkas wajib.');
            return;
        }
        if (!berkasSudahUpload) {
            alert('Upload berkas PDF kelengkapan terlebih dahulu di panel Berkas Kelengkapan.');
            return;
        }
        var labels = {
            kirim_kasi_pem: 'Kirim ke Kasi Pemerintahan untuk TTD digital?',
            kirim_kasi_pemb: 'Kirim ke Kasi Pemberdayaan untuk TTD digital?',
            kirim_kasi_trantib: 'Kirim ke Kasi Trantibum Linmas untuk TTD digital?',
            kirim_lurah_via_kasi_pem: 'Kirim untuk verifikasi Kasi Pemerintahan lalu TTD Lurah?'
        };
        if (!confirm(labels[mode] || 'Kirim surat ke pejabat untuk verifikasi?')) return;
    }
    document.getElementById('mode_simpan').value = mode;
    document.getElementById('html_content').value = html;
    formSimpan.submit();
}

// ---- AUTO HIDE PRINT HINT ----
setTimeout(function() {
    var h = document.getElementById('printHint');
    if (h) { h.style.opacity = '0'; h.style.transition = 'opacity .8s'; setTimeout(function() { h.remove(); }, 800); }
}, 4000);
</script>

<?php include 'footer.php'; ?>