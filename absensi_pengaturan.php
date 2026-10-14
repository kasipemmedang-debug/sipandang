<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_absensi_kelola(false);
include 'koneksi.php';
require_once __DIR__ . '/inc_absensi.php';
require_once __DIR__ . '/inc_log_aktivitas.php';
require_once __DIR__ . '/inc_admin_toast.php';

absensi_ensure_schema($koneksi);
$err = '';

if (isset($_POST['simpan_pengaturan'])) {
    $lat = (float) ($_POST['lat_kantor'] ?? -6.3012);
    $lng = (float) ($_POST['lng_kantor'] ?? 106.6350);
    $radius = max(20, min(500, (int) ($_POST['radius_meter'] ?? 100)));
    $nama = mysqli_real_escape_string($koneksi, trim((string) ($_POST['nama_lokasi'] ?? 'Kelurahan Medang')));
    $toleransi = max(0, min(120, (int) ($_POST['toleransi_telat'] ?? 0)));
    $gps_wajib = isset($_POST['gps_wajib']) ? 1 : 0;
    $gps_acc = max(10, min(500, (int) ($_POST['gps_akurasi_max'] ?? 100)));
    $gps_cek_mock = isset($_POST['gps_cek_mock']) ? 1 : 0;
    $gps_mock_tolak = isset($_POST['gps_mock_tolak']) ? 1 : 0;
    $pulang_gps_wajib = isset($_POST['pulang_gps_wajib']) ? 1 : 0;
    $pulang_selfie_wajib = isset($_POST['pulang_selfie_wajib']) ? 1 : 0;
    $liv_wajib = isset($_POST['liveness_wajib']) ? 1 : 0;
    $liv_langkah = max(2, min(3, (int) ($_POST['liveness_langkah'] ?? 3)));
    $pesan_luar = mysqli_real_escape_string($koneksi, trim((string) ($_POST['pesan_luar_radius'] ?? '')));
    $pesan_libur = mysqli_real_escape_string($koneksi, trim((string) ($_POST['pesan_hari_libur'] ?? '')));
    $foto_kb = max(100, min(2000, (int) ($_POST['foto_max_kb'] ?? 500)));
    $normTime = static function ($raw, string $def): string {
        $v = trim((string) $raw);
        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            return $v . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }
        return $def;
    };
    $jdm = mysqli_real_escape_string($koneksi, $normTime($_POST['jam_datang_mulai'] ?? '', '06:00:00'));
    $jds = mysqli_real_escape_string($koneksi, $normTime($_POST['jam_datang_selesai'] ?? '', '11:00:00'));
    $jpm = mysqli_real_escape_string($koneksi, $normTime($_POST['jam_pulang_mulai'] ?? '', '15:00:00'));
    $jps = mysqli_real_escape_string($koneksi, $normTime($_POST['jam_pulang_selesai'] ?? '', '20:00:00'));

    mysqli_query($koneksi, "UPDATE tbl_absensi_pengaturan SET
        lat_kantor=$lat, lng_kantor=$lng, radius_meter=$radius, nama_lokasi='$nama',
        toleransi_telat=$toleransi, gps_wajib=$gps_wajib, gps_akurasi_max=$gps_acc,
        gps_cek_mock=$gps_cek_mock, gps_mock_tolak=$gps_mock_tolak,
        pulang_gps_wajib=$pulang_gps_wajib, pulang_selfie_wajib=$pulang_selfie_wajib,
        liveness_wajib=$liv_wajib, liveness_langkah=$liv_langkah,
        pesan_luar_radius='$pesan_luar', pesan_hari_libur='$pesan_libur', foto_max_kb=$foto_kb,
        jam_datang_mulai='$jdm', jam_datang_selesai='$jds', jam_pulang_mulai='$jpm', jam_pulang_selesai='$jps'
        WHERE id=1");

    for ($h = 1; $h <= 7; $h++) {
        $aktif = isset($_POST['jadwal_aktif'][$h]) ? 1 : 0;
        $jmRaw = trim((string) ($_POST['jam_masuk'][$h] ?? '07:30'));
        $jpRaw = trim((string) ($_POST['jam_pulang'][$h] ?? '16:00'));
        if (strlen($jmRaw) === 5) {
            $jmRaw .= ':00';
        }
        if (strlen($jpRaw) === 5) {
            $jpRaw .= ':00';
        }
        $jm = mysqli_real_escape_string($koneksi, $jmRaw);
        $jp = mysqli_real_escape_string($koneksi, $jpRaw);
        mysqli_query($koneksi, "INSERT INTO tbl_absensi_jadwal (hari,jam_masuk,jam_pulang,aktif) VALUES ($h,'$jm','$jp',$aktif)
            ON DUPLICATE KEY UPDATE jam_masuk='$jm', jam_pulang='$jp', aktif=$aktif");
    }

    $wajib = $_POST['wajib'] ?? [];
    if (!is_array($wajib)) {
        $wajib = [];
    }
    mysqli_query($koneksi, 'UPDATE tbl_absensi_wajib SET aktif=0');
    foreach ($wajib as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
            mysqli_query($koneksi, "INSERT INTO tbl_absensi_wajib (id_user,aktif) VALUES ($uid,1)
                ON DUPLICATE KEY UPDATE aktif=1");
        }
    }
    absensi_sinkron_wajib_operator($koneksi);

    sipandang_log_aktivitas($koneksi, 'ABSENSI', 'pengaturan', 0, $_SESSION['nama'] ?? '', 'Update pengaturan absensi');
    sipandang_redirect_toast('absensi_pengaturan.php', 'absensi_set');
}

$cfg = absensi_pengaturan($koneksi);
$jadwal = absensi_jadwal_semua($koneksi);
$daftar = absensi_daftar_wajib($koneksi);

include 'header.php';
include 'sidebar.php';

function ap_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
body{font-family:'Plus Jakarta Sans',sans-serif!important}
.ap-wrap{padding:0 18px 24px;margin-top:12px}
.ap-card{background:#fff;border-radius:14px;box-shadow:0 3px 14px rgba(10,22,40,.08);border:1px solid #e3e8f3;padding:18px;margin-bottom:14px}
.ap-card h3{font-size:.92rem;font-weight:800;color:var(--role-dark,#05285a);margin-bottom:14px}
.ap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.ap-field label{display:block;font-size:.72rem;font-weight:700;color:#566583;margin-bottom:5px}
.ap-field input,.ap-field textarea,.ap-field select{width:100%;padding:10px 12px;border:1.5px solid #e3e8f3;border-radius:9px;font:inherit;font-size:.88rem}
#mapPilih{height:280px;border-radius:10px;border:1px solid #e3e8f3;margin-top:10px}
.ap-jadwal{width:100%;border-collapse:collapse;font-size:.82rem}
.ap-jadwal th,.ap-jadwal td{padding:8px;border-bottom:1px solid #f4f6fb;text-align:left}
.ap-wajib{max-height:260px;overflow:auto;border:1px solid #f4f6fb;border-radius:10px;padding:8px}
.ap-wajib label{display:flex;align-items:center;gap:8px;padding:6px 8px;font-size:.82rem}
.ap-btn-save{background:var(--role,#0b5cad);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-weight:700;cursor:pointer}
</style>

<div class="content-wrapper">
    <div class="pg-hdr">
        <div class="pg-badge"><i class="fas fa-cog"></i> ADMINISTRATOR</div>
        <h1><i class="fas fa-fingerprint mr-2"></i> Pengaturan Absensi</h1>
        <p>Koordinat kantor, jadwal kerja, dan pegawai wajib absen</p>
    </div>
    <div class="ap-wrap">
        <form method="post">
            <input type="hidden" name="simpan_pengaturan" value="1">

            <div class="ap-card">
                <h3><i class="fas fa-map-marker-alt mr-1"></i> Lokasi Kantor (Geo-fence)</h3>
                <div class="ap-grid">
                    <div class="ap-field"><label>Latitude</label><input type="text" name="lat_kantor" id="latKantor" value="<?= ap_h((string) $cfg['lat_kantor']) ?>" required></div>
                    <div class="ap-field"><label>Longitude</label><input type="text" name="lng_kantor" id="lngKantor" value="<?= ap_h((string) $cfg['lng_kantor']) ?>" required></div>
                    <div class="ap-field"><label>Radius (meter)</label><input type="number" name="radius_meter" min="20" max="500" value="<?= (int) ($cfg['radius_meter'] ?? 100) ?>"></div>
                    <div class="ap-field"><label>Nama lokasi</label><input type="text" name="nama_lokasi" value="<?= ap_h((string) ($cfg['nama_lokasi'] ?? '')) ?>"></div>
                </div>
                <p style="font-size:.75rem;color:#566583;margin-top:8px">Klik peta untuk memindahkan titik kantor.</p>
                <div id="mapPilih"></div>
            </div>

            <div class="ap-card">
                <h3><i class="fas fa-clock mr-1"></i> Jadwal Kerja</h3>
                <table class="ap-jadwal">
                    <thead><tr><th>Hari</th><th>Aktif</th><th>Jam Masuk</th><th>Jam Pulang</th></tr></thead>
                    <tbody>
                    <?php for ($h = 1; $h <= 7; $h++):
                        $j = $jadwal[$h] ?? ['jam_masuk' => '07:30:00', 'jam_pulang' => '16:00:00', 'aktif' => 0];
                        ?>
                        <tr>
                            <td><?= ap_h(absensi_nama_hari($h)) ?></td>
                            <td><input type="checkbox" name="jadwal_aktif[<?= $h ?>]" value="1" <?= (int) ($j['aktif'] ?? 0) ? 'checked' : '' ?>></td>
                            <td><input type="time" name="jam_masuk[<?= $h ?>]" value="<?= ap_h(substr((string) $j['jam_masuk'], 0, 5)) ?>" style="padding:6px"></td>
                            <td><input type="time" name="jam_pulang[<?= $h ?>]" value="<?= ap_h(substr((string) $j['jam_pulang'], 0, 5)) ?>" style="padding:6px"></td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="ap-card">
                <h3><i class="fas fa-sliders-h mr-1"></i> Aturan Presensi</h3>
                <div class="ap-grid">
                    <div class="ap-field"><label>Toleransi telat (menit)</label><input type="number" name="toleransi_telat" min="0" max="120" value="<?= (int) ($cfg['toleransi_telat'] ?? 0) ?>"></div>
                    <div class="ap-field"><label>Akurasi GPS maks (meter)</label><input type="number" name="gps_akurasi_max" min="10" max="500" value="<?= (int) ($cfg['gps_akurasi_max'] ?? 100) ?>"></div>
                    <div class="ap-field"><label>Maks ukuran foto (KB)</label><input type="number" name="foto_max_kb" min="100" max="2000" value="<?= (int) ($cfg['foto_max_kb'] ?? 500) ?>"></div>
                    <div class="ap-field"><label>Langkah liveness</label><select name="liveness_langkah"><option value="2" <?= (int)($cfg['liveness_langkah']??3)===2?'selected':'' ?>>2</option><option value="3" <?= (int)($cfg['liveness_langkah']??3)===3?'selected':'' ?>>3</option></select></div>
                </div>
                <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:16px;font-size:.85rem">
                    <label><input type="checkbox" name="gps_wajib" value="1" <?= (int)($cfg['gps_wajib']??1)?'checked':'' ?>> GPS wajib (datang)</label>
                    <label><input type="checkbox" name="pulang_gps_wajib" value="1" <?= (int)($cfg['pulang_gps_wajib']??1)?'checked':'' ?>> GPS wajib (pulang)</label>
                    <label><input type="checkbox" name="liveness_wajib" value="1" <?= (int)($cfg['liveness_wajib']??1)?'checked':'' ?>> Selfie wajib (datang)</label>
                    <label><input type="checkbox" name="pulang_selfie_wajib" value="1" <?= (int)($cfg['pulang_selfie_wajib']??1)?'checked':'' ?>> Selfie wajib (pulang)</label>
                </div>
                <div style="margin-top:10px;padding:12px;background:#f4f8fc;border-radius:10px;border:1px solid #e3ecf5">
                    <div style="font-size:.78rem;font-weight:700;color:var(--role-dark,#05285a);margin-bottom:8px"><i class="fas fa-shield-alt mr-1"></i> Deteksi GPS Palsu</div>
                    <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:.85rem">
                        <label><input type="checkbox" name="gps_cek_mock" value="1" <?= (int)($cfg['gps_cek_mock']??1)?'checked':'' ?>> Aktifkan deteksi GPS palsu</label>
                        <label><input type="checkbox" name="gps_mock_tolak" value="1" <?= (int)($cfg['gps_mock_tolak']??1)?'checked':'' ?>> Tolak presensi jika terdeteksi (uncheck = catat saja)</label>
                    </div>
                    <p style="font-size:.72rem;color:#566583;margin:8px 0 0">Heuristik: flag browser <code>mocked</code>, koordinat (0,0), akurasi 0, loncat &gt;50 km. Tidak 100% — kombinasikan dengan geo-fence.</p>
                </div>
                <div class="ap-grid" style="margin-top:12px">
                    <div class="ap-field"><label>Pesan luar radius</label><textarea name="pesan_luar_radius" rows="2"><?= ap_h((string) ($cfg['pesan_luar_radius'] ?? '')) ?></textarea></div>
                    <div class="ap-field"><label>Pesan hari libur</label><textarea name="pesan_hari_libur" rows="2"><?= ap_h((string) ($cfg['pesan_hari_libur'] ?? '')) ?></textarea></div>
                </div>
            </div>

            <div class="ap-card">
                <h3><i class="fas fa-hourglass-half mr-1"></i> Batas Waktu Absen</h3>
                <p style="font-size:.75rem;color:#566583;margin-bottom:10px">Presensi hanya bisa dilakukan dalam rentang jam berikut.</p>
                <div class="ap-grid">
                    <div class="ap-field"><label>Datang — mulai</label><input type="time" name="jam_datang_mulai" value="<?= ap_h(substr((string) ($cfg['jam_datang_mulai'] ?? '06:00:00'), 0, 5)) ?>"></div>
                    <div class="ap-field"><label>Datang — selesai</label><input type="time" name="jam_datang_selesai" value="<?= ap_h(substr((string) ($cfg['jam_datang_selesai'] ?? '11:00:00'), 0, 5)) ?>"></div>
                    <div class="ap-field"><label>Pulang — mulai</label><input type="time" name="jam_pulang_mulai" value="<?= ap_h(substr((string) ($cfg['jam_pulang_mulai'] ?? '15:00:00'), 0, 5)) ?>"></div>
                    <div class="ap-field"><label>Pulang — selesai</label><input type="time" name="jam_pulang_selesai" value="<?= ap_h(substr((string) ($cfg['jam_pulang_selesai'] ?? '20:00:00'), 0, 5)) ?>"></div>
                </div>
            </div>

            <div class="ap-card">
                <h3><i class="fas fa-calendar-times mr-1"></i> Kalender Libur</h3>
                <p style="font-size:.75rem;color:#566583;margin-bottom:10px">Kelola hari libur nasional & kelurahan. Pegawai tidak perlu absen pada tanggal libur.</p>
                <a href="absensi_libur.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-calendar-day mr-1"></i> Kelola Kalender Libur</a>
                <a href="absensi_izin.php" class="btn btn-sm btn-outline-secondary ml-2"><i class="fas fa-file-medical mr-1"></i> Persetujuan Izin</a>
            </div>

            <div class="ap-card">
                <h3><i class="fas fa-users mr-1"></i> Pegawai Wajib Absen</h3>
                <p style="font-size:.75rem;color:#566583;margin-bottom:8px">Operator &amp; PKL baru otomatis tercentang wajib absen. Kasi/Lurah tidak perlu absen mobile.</p>
                <div class="ap-wajib">
                    <?php foreach ($daftar as $d):
                        $uid = (int) $d['id_user'];
                        $checked = (int) ($d['wajib_aktif'] ?? 0) === 1;
                        ?>
                        <label>
                            <input type="checkbox" name="wajib[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?>>
                            <?= ap_h($d['nama_lengkap'] ?? '') ?> <span style="color:#566583">(<?= ap_h($d['level'] ?? '') ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="ap-btn-save"><i class="fas fa-save mr-1"></i> Simpan Pengaturan</button>
            <a href="absensi_rekap.php" class="btn btn-sm btn-outline-secondary ml-2">Lihat Rekap</a>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
    const lat = parseFloat(document.getElementById('latKantor').value) || -6.3012;
    const lng = parseFloat(document.getElementById('lngKantor').value) || 106.6350;
    const map = L.map('mapPilih').setView([lat,lng], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
    const marker = L.marker([lat,lng],{draggable:true}).addTo(map);
    const radius = <?= (int) ($cfg['radius_meter'] ?? 100) ?>;
    let circle = L.circle([lat,lng],{radius:radius,color:SP_ROLE.main,fillOpacity:.15}).addTo(map);
    function sync(pos){
        document.getElementById('latKantor').value = pos.lat.toFixed(7);
        document.getElementById('lngKantor').value = pos.lng.toFixed(7);
        circle.setLatLng(pos);
    }
    marker.on('dragend', ()=> sync(marker.getLatLng()));
    map.on('click', e => { marker.setLatLng(e.latlng); sync(e.latlng); });
})();
</script>
<?php include 'footer.php'; ?>
