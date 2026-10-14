<?php
/**
 * Webhook penerima pesan WhatsApp dari Fonnte → balas otomatis (asisten informasi).
 *
 * URL ini dimasukkan ke kolom "Webhook URL" di dashboard Fonnte, contoh:
 *   https://sipandang.com/api/wa/webhook.php
 *
 * Fitur: AI tanya-jawab layanan kelurahan, memori percakapan, sapaan personal,
 * membaca gambar (multimodal), model cadangan, rate limit, & log pertanyaan.
 */
declare(strict_types=1);

ignore_user_abort(true);
@header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/inc_wa_config.php';

if (!function_exists('wa_log')) {
    function wa_log(string $pesan): void
    {
        global $cfg;
        if (empty($cfg['debug'])) {
            return;
        }
        @file_put_contents(
            __DIR__ . '/wa_debug.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $pesan . "\n",
            FILE_APPEND
        );
    }
}

if (empty($cfg['enabled'])) {
    echo json_encode(['ok' => false, 'msg' => 'disabled']);
    exit;
}

// ── Ambil payload (form-encoded atau JSON) ──────────────────
$payload = $_POST;
if (empty($payload)) {
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $payload = $json;
    }
}
wa_log('Masuk: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

$pengirim = (string) ($payload['sender'] ?? $payload['pengirim'] ?? '');
$pesan    = trim((string) ($payload['message'] ?? $payload['text'] ?? $payload['pesan'] ?? ''));
$grup     = trim((string) ($payload['group'] ?? ''));
$nama     = trim((string) ($payload['pushname'] ?? $payload['name'] ?? ''));
$urlMedia = trim((string) ($payload['url'] ?? ''));
$ext      = strtolower(trim((string) ($payload['extension'] ?? '')));

// Fonnte mengisi text "non-button message" untuk pesan biasa — abaikan sebagai isi.
if (strcasecmp($pesan, 'non-button message') === 0) {
    $pesan = '';
}

$adaGambar = $urlMedia !== '' && (
    in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
    || strpos((string) ($payload['type'] ?? ''), 'image') !== false
);

// Abaikan: dari grup, tanpa pengirim, atau benar-benar kosong (tak ada teks & tak ada gambar).
if ($pengirim === '' || $grup !== '' || ($pesan === '' && !$adaGambar)) {
    echo json_encode(['ok' => true, 'msg' => 'skip']);
    exit;
}

$maxLen = (int) ($cfg['max_input_len'] ?? 1000);
if ($maxLen > 0 && mb_strlen($pesan) > $maxLen) {
    $pesan = mb_substr($pesan, 0, $maxLen);
}

require_once dirname(__DIR__, 2) . '/koneksi.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/context.php';
require_once __DIR__ . '/ai_handler.php';
require_once __DIR__ . '/kirim.php';
require_once dirname(__DIR__, 2) . '/inc_titip_berkas.php';

// ── Rate limit anti-spam ────────────────────────────────────
$rlMax = (int) ($cfg['rate_limit_max'] ?? 0);
$rlWin = (int) ($cfg['rate_limit_window'] ?? 0);
if (!wa_session_rate_ok($koneksi, $pengirim, $rlMax, $rlWin)) {
    wa_log('Rate limit terlampaui untuk ' . $pengirim);
    echo json_encode(['ok' => true, 'msg' => 'rate-limited']);
    exit;
}

// ── Ambil sesi (memori + nama tersimpan) ────────────────────
$useMemory = (int) ($cfg['history_max_turns'] ?? 0) > 0;
$sesi = wa_session_get($koneksi, $pengirim);
$history = $useMemory ? $sesi['history'] : [];
if ($nama === '') {
    $nama = (string) $sesi['nama'];
}

// ── Ambil gambar bila ada & fitur aktif ─────────────────────
$image = null;
if ($adaGambar && !empty($cfg['fitur_baca_gambar'])) {
    $image = wa_ambil_gambar($urlMedia, (int) ($cfg['gambar_max_bytes'] ?? 4194304));
    if ($image === null) {
        wa_log('Gagal mengunduh gambar: ' . $urlMedia);
    }
}

// ── Proses & balas ──────────────────────────────────────────
$hasil = wa_proses_pesan($koneksi, $cfg, $pesan, $nama, $history, $image);
$balasan = $hasil['text'];

// Simpan riwayat hanya untuk jawaban AI yang berhasil.
if ($useMemory && $hasil['ai_status'] === 'ok') {
    $teksUser = $pesan !== '' ? $pesan : '(mengirim gambar)';
    $history[] = ['role' => 'user', 'text' => $teksUser];
    $history[] = ['role' => 'model', 'text' => $balasan];
}
wa_session_save($koneksi, $pengirim, $nama, $history, max(1, (int) ($cfg['history_max_turns'] ?? 6)));

// Catat pertanyaan di luar layanan untuk ditinjau admin (bahan "belajar").
if ($hasil['ai_status'] === 'empty' && $pesan !== '') {
    wa_log_tanya($koneksi, $pengirim, $pesan, 'di_luar_layanan');
}

if ($balasan !== '') {
    wa_fonnte_kirim($pengirim, $balasan, $cfg);
}

echo json_encode(['ok' => true]);

/**
 * Unduh gambar dari URL Fonnte → base64 untuk dikirim ke Gemini.
 *
 * @return array{mime:string,data:string}|null
 */
function wa_ambil_gambar(string $url, int $maxBytes): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $data = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($data === false || $http !== 200 || $data === '') {
        return null;
    }
    if ($maxBytes > 0 && strlen((string) $data) > $maxBytes) {
        return null;
    }
    $mime = strtok($mime, ';') ?: $mime;
    $mime = trim($mime);
    if (stripos($mime, 'image/') !== 0) {
        return null;
    }

    return ['mime' => $mime, 'data' => base64_encode((string) $data)];
}

/**
 * Tentukan balasan berdasarkan isi pesan warga.
 *
 * @param list<array{role:string,text:string}> $history
 * @param array{mime:string,data:string}|null  $image
 * @return array{text:string,ai_status:string}
 */
function wa_proses_pesan(mysqli $koneksi, array $cfg, string $pesan, string $nama, array $history, ?array $image): array
{
    $lower = mb_strtolower($pesan);

    // 0) Cek status BERKAS DITINGGAL via KODE (mis. MDG-7F3K). Selalu aktif.
    if ($image === null && function_exists('titip_berkas_deteksi_kode')) {
        $kode = titip_berkas_deteksi_kode($pesan);
        if ($kode !== null) {
            $namaKel = wa_kelurahan_info($koneksi, $cfg)['nama_kel'] ?? '';
            $row = titip_berkas_cari_by_kode($koneksi, $kode);
            if ($row) {
                return ['text' => titip_berkas_status_teks_warga($row, (string) $namaKel), 'ai_status' => 'status'];
            }
            return [
                'text' => "Maaf Kak, kode *{$kode}* tidak ditemukan.\n"
                    . "Pastikan kode diketik persis seperti yang diberikan petugas (contoh: " . TITIP_KODE_PREFIX . "-AB12).",
                'ai_status' => 'status',
            ];
        }
    }

    // 1) Cek status pengajuan online (opsional; default mati — layanan online belum jalan).
    if (!empty($cfg['fitur_cek_status'])) {
        $nomor = wa_deteksi_nomor_pengajuan($pesan);
        if ($nomor !== null) {
            $status = wa_cek_status_pengajuan($koneksi, $nomor);
            $teks = $status ?? ("Maaf Kak, nomor pengajuan *{$nomor}* tidak ditemukan. 🙏\n"
                . "Pastikan nomor diketik lengkap dan benar (format: PG-XXX-XXXXXXXX-XXXX).");
            return ['text' => $teks, 'ai_status' => 'status'];
        }
    }

    // 2) Sapaan / menu cepat (hemat kuota AI) — hanya untuk pesan teks murni.
    if ($image === null) {
        $sapaan = ['halo', 'hai', 'hi', 'assalamualaikum', 'pagi', 'siang', 'sore', 'malam', 'menu', 'mulai', 'start', 'p'];
        foreach ($sapaan as $s) {
            if ($lower === $s || $lower === ($s . '!') || (mb_strpos($lower, $s) === 0 && mb_strlen($lower) <= mb_strlen($s) + 3)) {
                return ['text' => wa_menu_teks($koneksi, $cfg, $nama), 'ai_status' => 'menu'];
            }
        }
    }

    // 3) Selain itu → AI (dengan memori + gambar bila ada).
    $sysPrompt = wa_build_system_prompt($koneksi, $cfg, $nama);
    $jwb = wa_ai_jawab($sysPrompt, $history, $pesan, $cfg, $image);

    return ['text' => $jwb['text'], 'ai_status' => $jwb['status']];
}

/** Menu sapaan awal (dengan sapaan personal bila nama diketahui). */
function wa_menu_teks(mysqli $koneksi, array $cfg, string $nama = ''): string
{
    $info = wa_kelurahan_info($koneksi, $cfg);
    $sapa = $nama !== '' ? ('Halo Kak ' . $nama . ',') : 'Halo Kak,';

    return "*Asisten SIPANDANG - Kelurahan {$info['nama_kel']}*\n"
        . "{$sapa} saya siap membantu 24 jam seputar layanan kelurahan.\n\n"
        . "Silakan tanya, misalnya:\n"
        . "- Syarat membuat KTP baru?\n"
        . "- Cara mengurus surat pindah?\n"
        . "- Syarat SKTM / surat tidak mampu?\n"
        . "- Jam & alamat pelayanan kelurahan?\n\n"
        . "Punya kode berkas dari petugas? Kirim kodenya (contoh: " . TITIP_KODE_PREFIX . "-AB12) untuk cek status berkas Anda.\n\n"
        . "Jam pelayanan: {$info['jam']}";
}
