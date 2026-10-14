<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_sipelamar.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';

sipelamar_ensure_schema($koneksi);

$bulan = isset($_GET['bulan']) ? max(1, min(12, (int) $_GET['bulan'])) : (int) date('n');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$autoPrint = isset($_GET['auto']) && (string) $_GET['auto'] === '1';

$cfg = sipelamar_pengaturan($koneksi);
$kop = sipandang_kop_kelurahan_data($koneksi);
$pasanganList = sipelamar_pasangan_list($koneksi);
$matriks = sipelamar_matriks_cetak_bulan($koneksi, $bulan, $tahun);
$operators = $matriks['operators'];
$kolom = $matriks['kolom'];
$grid = $matriks['grid'];
$totalsHari = $matriks['totals_hari'];
$jamShift = (int) ($matriks['jam_per_shift'] ?? 4);
$jumlahHari = max(1, count($kolom));

$jamLabel = sipelamar_jam_tampil((string) ($cfg['jam_buka'] ?? '18:00:00'))
    . ' – '
    . sipelamar_jam_tampil((string) ($cfg['jam_tutup'] ?? '22:00:00'))
    . ' WIB';

$jumlahKerja = count(array_filter($kolom, static fn ($c) => !empty($c['kerja'])));
$jumlahIsi = count(array_filter($kolom, static fn ($c) => !empty($c['kerja']) && !empty($c['isi'])));

$profilKel = sipelamar_profil_kelurahan($koneksi);
$lurah = sipelamar_pamong_lurah($koneksi);
$namaKel = ucwords(strtolower((string) $profilKel['nama_kelurahan']));
$namaLurah = trim((string) ($lurah['nama_pamong'] ?? ''));
$nipLurah = trim((string) ($lurah['nip'] ?? ''));
$labelJabatan = 'Lurah ' . $namaKel;
$tglTtd = date('d ') . sipelamar_bulan_label((int) date('n')) . ' ' . date('Y');

function sc_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function sc_gelar_cetak(string $gelar): string
{
    $gelar = trim($gelar);
    if ($gelar === '') {
        return '';
    }
    if (preg_match('/^S\.IP$/i', $gelar)) {
        return 'S.IP';
    }
    $bits = explode('.', $gelar);
    $bits = array_map(static function (string $b): string {
        $b = trim($b);
        if ($b === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($b, 0, 1)) . mb_strtolower(mb_substr($b, 1));
    }, $bits);

    return implode('.', array_filter($bits, static fn ($b) => $b !== ''));
}

function sc_nama_cetak(string $nama): string
{
    $nama = trim($nama);
    if ($nama === '') {
        return '';
    }
    $parts = preg_split('/,\s*/u', $nama) ?: [$nama];
    $out = [];
    foreach ($parts as $i => $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }
        $p = $i === 0 ? mb_strtoupper($p, 'UTF-8') : sc_gelar_cetak($p);
        $out[] = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
    }

    return implode(',&nbsp;', $out);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Jadwal Piket SiPELAMAR — <?= sc_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?></title>
<style>
<?= sipandang_kop_kelurahan_css($kop) . sipandang_kop_kelurahan_cetak_fix_css($kop) ?>
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body,
.page,
.matrix,
.ttd,
.judul,
.subjudul,
.meta,
.pasangan-box,
.footer-note {
    font-family: Arial, Helvetica, sans-serif;
}
body {
    font-size: 10pt;
    color: #0f172a;
    margin: 0;
    background: #e8edf3;
}
.page {
    width: 29.7cm;
    min-height: 21cm;
    margin: 12px auto;
    padding: 5mm 4mm 6mm;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,.12);
}
.page .kop-kel-wrap { margin-bottom: 2px; }
.page .kop-kel-logo-td { width: 1.9cm !important; padding-right: 4px !important; }
.page .kop-kel-logo-td img { height: 1.35cm !important; max-width: 1.8cm !important; }
.page .kop-kel-line.kab,
.page .kop-kel-line.kec { font-size: 8.5pt !important; }
.page .kop-kel-line.kel { font-size: 11.5pt !important; margin-bottom: 0 !important; }
.page .kop-kel-line.almt,
.page .kop-kel-line.kontak { font-size: 6.5pt !important; }
.page .kop-kel-garis { margin-top: 2px !important; margin-bottom: 4px !important; }
.no-print {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 99;
    display: flex;
    gap: 8px;
}
.no-print a,
.no-print button {
    padding: 8px 14px;
    border: 0;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
}
.no-print button { background: #6366f1; color: #fff; }
.no-print a { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
.judul {
    text-align: center;
    margin: 2px 0 1px;
    font-size: 11pt;
    font-weight: 800;
    color: #3730a3;
    letter-spacing: .3px;
}
.subjudul {
    text-align: center;
    font-size: 8.5pt;
    color: #64748b;
    margin: 0 0 5px;
}
.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    font-size: 7pt;
    margin-bottom: 4px;
    padding: 4px 7px;
    border: 1px solid #c7d2fe;
    background: #eef2ff;
    border-radius: 4px;
    color: #3730a3;
}
.meta strong { font-weight: 800; }
.pasangan-box {
    margin-bottom: 4px;
    font-size: 7pt;
    color: #475569;
}
.pasangan-box span {
    display: inline-block;
    border: 1px solid #c7d2fe;
    background: #f5f3ff;
    color: #4338ca;
    border-radius: 4px;
    padding: 1px 6px;
    margin: 1px 3px 1px 0;
    font-weight: 700;
}
.matrix-wrap { width: 100%; overflow-x: auto; }
.matrix {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 9pt;
}
.matrix th,
.matrix td {
    border: 1px solid #334155;
    padding: 4px 2px;
    text-align: center;
    vertical-align: middle;
    line-height: 1.2;
    overflow: hidden;
}
.matrix th {
    font-weight: 800;
    background: #eef2ff;
    color: #3730a3;
}
.matrix th.hdr-main {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    font-size: 9.5pt;
    padding: 4px 2px;
}
.matrix th.hdr-sub {
    background: #e0e7ff;
    color: #4338ca;
    font-size: 8pt;
    font-weight: 700;
}
.matrix th.col-libur { background: #fecaca; color: #991b1b; }
.matrix th.col-kerja { background: #c7d2fe; color: #312e81; }
.matrix .col-no { width: 7mm; }
.matrix .col-nama {
    width: 28mm;
    text-align: left;
    padding-left: 6px !important;
    font-size: 9pt;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.matrix .col-day { width: 7.4mm; min-width: 7.4mm; }
.matrix .col-sum { width: 8mm; font-weight: 800; }
.matrix td.col-nama { background: #f8fafc; color: #0f172a; }
.matrix td.col-sum-m { background: #eef2ff; color: #3730a3; font-weight: 800; font-size: 9.5pt; }
.cell-m { background: #6366f1 !important; color: #fff !important; font-weight: 900; font-size: 10pt; }
.cell-off { background: #fff; color: #64748b; font-weight: 700; font-size: 7.5pt; letter-spacing: -.2px; }
.cell-libur { background: #fef2f2 !important; color: #b91c1c !important; font-weight: 800; font-size: 8.5pt; }
.row-total td.col-libur { background: #fef2f2; color: #b91c1c; font-weight: 800; }
.matrix tr.row-total td {
    background: #e0e7ff;
    color: #312e81;
    font-weight: 800;
    font-size: 8.5pt;
}
.matrix tr.row-total td.lbl {
    text-align: left;
    padding-left: 6px !important;
    font-size: 8pt;
}
.footer-note {
    margin-top: 4px;
    font-size: 6.5pt;
    color: #64748b;
}
.ttd {
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
    font-size: 9pt;
}
.ttd .box {
    text-align: center;
    width: 52%;
    min-width: 460px;
    max-width: 560px;
}
.ttd .jabatan {
    display: block;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
    margin-bottom: 2px;
    font-size: 9pt;
}
.ttd .nama {
    font-weight: 800;
    text-decoration: underline;
    margin: 0;
    font-size: 9.5pt;
    line-height: 1.3;
    white-space: nowrap;
}
.ttd .nip {
    margin: 4px 0 0;
    font-size: 9pt;
    font-weight: 700;
    letter-spacing: .2px;
}
.ttd .spacer { height: 58px; }
@media print {
    @page { size: A4 landscape; margin: 4mm; }
    body { background: #fff; }
    .page {
        width: auto;
        min-height: auto;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
    .no-print { display: none !important; }
    .matrix { font-size: 9pt; }
    .matrix th.hdr-main { font-size: 9.5pt; }
    .matrix th.hdr-sub { font-size: 8pt; }
    .cell-m { font-size: 10pt; }
    .cell-off { font-size: 7.5pt; }
    .ttd { page-break-inside: avoid; font-size: 9pt; }
    .ttd .box { min-width: 480px; max-width: 580px; }
    .ttd .nama { font-size: 9.5pt; white-space: nowrap; }
    .ttd .nip { font-size: 9pt; }
    .ttd .spacer { height: 62px; }
}
</style>
</head>
<body>
<div class="no-print">
    <a href="sipelamar_jadwal.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>">← Kembali</a>
    <a href="sipelamar_cetak_paraf.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>">Cetak Paraf/TTD</a>
    <button type="button" onclick="window.print()">Cetak A4 Landscape</button>
</div>

<div class="page">
    <?= sipandang_kop_kelurahan_render_html($kop, false) ?>

    <div class="judul">JADWAL PIKET OPERATOR SiPELAMAR</div>
    <div class="subjudul">
        <?= sc_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?> · Pelayanan Malam · Jam <?= sc_h($jamLabel) ?>
    </div>

    <div class="meta">
        <div><strong>M</strong> = Piket Malam</div>
        <div><strong>OFF</strong> = Libur piket (hari kerja)</div>
        <div><strong style="color:#b91c1c">—</strong> = Sabtu/Minggu &amp; libur nasional (kolom merah)</div>
        <div><strong>Terjadwal:</strong> <?= $jumlahIsi ?> / <?= $jumlahKerja ?> malam</div>
        <div><strong>Dicetak:</strong> <?= date('d/m/Y H:i') ?></div>
    </div>

    <?php if ($pasanganList !== []): ?>
    <div class="pasangan-box">
        <strong>Pasangan piket:</strong>
        <?php foreach ($pasanganList as $i => $p): ?>
        <span>#<?= $i + 1 ?> <?= sc_h($p['nama_1']) ?> + <?= sc_h($p['nama_2']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="matrix-wrap">
        <table class="matrix">
            <thead>
                <tr>
                    <th class="hdr-main col-no" rowspan="2">No</th>
                    <th class="hdr-main col-nama" rowspan="2">Nama Operator</th>
                    <?php foreach ($kolom as $c): ?>
                    <th class="hdr-main col-day <?= !empty($c['kerja']) ? 'col-kerja' : 'col-libur' ?>"><?= (int) $c['tgl'] ?></th>
                    <?php endforeach; ?>
                    <th class="hdr-main col-sum" rowspan="2">Jml<br>M</th>
                </tr>
                <tr>
                    <?php foreach ($kolom as $c): ?>
                    <th class="hdr-sub col-day <?= !empty($c['kerja']) ? 'col-kerja' : 'col-libur' ?>"><?= sc_h((string) $c['hari']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($operators === []): ?>
                <tr>
                    <td colspan="<?= count($kolom) + 3 ?>" style="padding:12px;color:#64748b;">Belum ada operator. Tambahkan user level Operator dan generate jadwal.</td>
                </tr>
            <?php else:
                foreach ($operators as $i => $op):
                    $uid = (int) $op['id_user'];
            ?>
                <tr>
                    <td class="col-no"><?= (int) ($op['urutan'] ?: ($i + 1)) ?></td>
                    <td class="col-nama" title="<?= sc_h($op['nama']) ?>"><?= sc_h(sipelamar_nama_singkat((string) $op['nama'], 20)) ?></td>
                    <?php foreach ($kolom as $c):
                        $tgl = (string) $c['tanggal'];
                        $val = (string) ($grid[$uid][$tgl] ?? 'LIBUR');
                        if ($val === 'M') {
                            $cls = 'cell-m';
                            $txt = 'M';
                        } elseif ($val === 'OFF') {
                            $cls = 'cell-off';
                            $txt = 'OFF';
                        } else {
                            $cls = 'cell-libur';
                            $txt = '—';
                        }
                    ?>
                    <td class="<?= $cls ?>"><?= $txt ?></td>
                    <?php endforeach; ?>
                    <td class="col-sum-m"><?= (int) $op['malam'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
                <tr class="row-total">
                    <td colspan="2" class="lbl">Jumlah Tenaga Piket Tiap Malam</td>
                    <?php foreach ($kolom as $c):
                        $tgl = (string) $c['tanggal'];
                        $v = empty($c['kerja']) ? '—' : (string) ($totalsHari[$tgl] ?? 0);
                    ?>
                    <td class="<?= empty($c['kerja']) ? 'col-libur cell-libur' : '' ?>"><?= sc_h($v) ?></td>
                    <?php endforeach; ?>
                    <td colspan="1"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-note">
        SiPELAMAR · 2 operator piket per malam · pasangan tetap bergilir · 1 shift = <?= $jamShift ?> jam.
    </div>

    <div class="ttd">
        <div class="box">
            <?= sc_h($namaKel) ?>, <?= sc_h($tglTtd) ?><br>
            <span class="jabatan"><?= sc_h($labelJabatan) ?></span>
            <div class="spacer"></div>
            <?php if ($namaLurah !== ''): ?>
            <p class="nama"><?= sc_nama_cetak($namaLurah) ?></p>
            <p class="nip">NIP. <?= sc_h($nipLurah !== '' ? $nipLurah : '—') ?></p>
            <?php else: ?>
            <p class="nama">(........................................)</p>
            <p class="nip">NIP. ........................................</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($autoPrint): ?>
<script>setTimeout(function(){ window.print(); }, 400);</script>
<?php endif; ?>
</body>
</html>
