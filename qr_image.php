<?php
/**
 * Gambar QR lokal — tanpa API eksternal.
 * GET: d = data/URL, s = ukuran tampilan px (48–160); PNG di-render retina (3×)
 */
declare(strict_types=1);

require_once __DIR__ . '/inc_verifikasi_qr.php';

$data = trim((string) ($_GET['d'] ?? ''));
$size = sipandang_qr_display_size_clamp((int) ($_GET['s'] ?? 80));

if ($data === '' || strlen($data) > 2048 || !sipandang_qr_data_allowed($data)) {
    http_response_code($data === '' ? 400 : 403);
    exit;
}

$cacheDir = __DIR__ . '/lib/phpqrcode/cache/';
$cacheKey = sipandang_qr_cache_key($data, $size);
$cacheFile = $cacheDir . 'img_' . $cacheKey . '.png';

if (is_file($cacheFile)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800, immutable');
    readfile($cacheFile);
    exit;
}

$png = sipandang_qr_png_bytes($data, $size);
if ($png === null || $png === '') {
    http_response_code(500);
    exit;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $png);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800, immutable');
echo $png;
