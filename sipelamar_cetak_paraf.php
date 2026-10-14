<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_sipelamar.php';
require_once __DIR__ . '/inc_kop_kelurahan.php';

$bulan = isset($_GET['bulan']) ? max(1, min(12, (int) $_GET['bulan'])) : (int) date('n');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$autoPrint = isset($_GET['auto']) && (string) $_GET['auto'] === '1';

$ctx = sipelamar_cetak_context($koneksi, $bulan, $tahun);
extract($ctx, EXTR_SKIP);
$absensiMap = sipelamar_absensi_hadir_map($koneksi, $bulan, $tahun);
$kopCss = sipandang_kop_kelurahan_css($kop) . sipandang_kop_kelurahan_cetak_fix_css($kop);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lembar Paraf SiPELAMAR — <?= sipelamar_cetak_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?></title>
<style>
<?= $kopCss ?>
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body, .page, .matrix, .ttd, .judul, .subjudul, .meta, .pasangan-box, .footer-note {
    font-family: Arial, Helvetica, sans-serif;
}
body { font-size: 10pt; color: #0f172a; margin: 0; background: #e8edf3; }
.page {
    width: 29.7cm; min-height: 21cm; margin: 12px auto; padding: 5mm 4mm 6mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12);
}
.page .kop-kel-wrap { margin-bottom: 2px; }
.page .kop-kel-logo-td { width: 1.9cm !important; padding-right: 4px !important; }
.page .kop-kel-logo-td img { height: 1.35cm !important; max-width: 1.8cm !important; }
.page .kop-kel-line.kab, .page .kop-kel-line.kec { font-size: 8.5pt !important; }
.page .kop-kel-line.kel { font-size: 11.5pt !important; margin-bottom: 0 !important; }
.page .kop-kel-line.almt, .page .kop-kel-line.kontak { font-size: 6.5pt !important; }
.page .kop-kel-garis { margin-top: 2px !important; margin-bottom: 4px !important; }
.no-print { position: fixed; top: 12px; right: 12px; z-index: 99; display: flex; gap: 8px; }
.no-print a, .no-print button {
    padding: 8px 14px; border: 0; border-radius: 6px; font-weight: 700;
    cursor: pointer; text-decoration: none; font-size: 13px;
}
.no-print button { background: #6366f1; color: #fff; }
.no-print a { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
.judul { text-align: center; margin: 2px 0 1px; font-size: 11pt; font-weight: 800; color: #3730a3; }
.subjudul { text-align: center; font-size: 8.5pt; color: #64748b; margin: 0 0 5px; }
.meta {
    display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 7pt; margin-bottom: 4px;
    padding: 4px 7px; border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 4px; color: #3730a3;
}
.meta strong { font-weight: 800; }
.pasangan-box { margin-bottom: 4px; font-size: 7pt; color: #475569; }
.pasangan-box span {
    display: inline-block; border: 1px solid #c7d2fe; background: #f5f3ff; color: #4338ca;
    border-radius: 4px; padding: 1px 6px; margin: 1px 3px 1px 0; font-weight: 700;
}
.matrix-wrap { width: 100%; overflow-x: auto; }
.matrix { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; }
.matrix th, .matrix td {
    border: 1px solid #334155; padding: 3px 1px; text-align: center;
    vertical-align: middle; line-height: 1.15; overflow: hidden;
}
.matrix th { font-weight: 800; background: #eef2ff; color: #3730a3; }
.matrix th.hdr-main {
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-size: 9pt; padding: 3px 1px;
}
.matrix th.hdr-sub { background: #e0e7ff; color: #4338ca; font-size: 7.5pt; font-weight: 700; }
.matrix th.col-libur { background: #fecaca; color: #991b1b; }
.matrix th.col-kerja { background: #c7d2fe; color: #312e81; }
.matrix .col-no { width: 6mm; }
.matrix .col-nama {
    width: 24mm; text-align: left; padding-left: 5px !important;
    font-size: 8.5pt; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.matrix .col-day { width: 6.8mm; min-width: 6.8mm; }
.matrix .col-ttd { width: 18mm; }
.matrix td.col-nama { background: #f8fafc; color: #0f172a; }
.cell-off { background: #fff; color: #64748b; font-weight: 700; font-size: 7pt; }
.cell-libur { background: #fef2f2 !important; color: #b91c1c !important; font-weight: 800; font-size: 7pt; }
.cell-paraf { background: #eef2ff; padding: 2px 0 !important; }
.paraf-kotak {
    display: inline-flex; align-items: center; justify-content: center;
    width: 5.2mm; height: 4.2mm; min-width: 5.2mm;
    border: 1px solid #4338ca; background: #fff; vertical-align: middle;
}
.paraf-kotak.paraf-auto {
    color: #3730a3; font-weight: 900; font-size: 8.5pt;
    border-color: #6366f1; background: #eef2ff;
}
.ttd-kotak {
    display: block; width: 14mm; height: 11mm; margin: 0 auto;
    border: 1px dashed #4338ca; background: #fff;
}
.footer-note { margin-top: 4px; font-size: 6.5pt; color: #64748b; }
.ttd { margin-top: 8px; display: flex; justify-content: flex-end; font-size: 9pt; }
.ttd .box { text-align: center; width: 52%; min-width: 460px; max-width: 560px; }
.ttd .jabatan { display: block; font-weight: 700; text-transform: uppercase; font-size: 9pt; margin-bottom: 2px; }
.ttd .nama { font-weight: 800; text-decoration: underline; margin: 0; font-size: 9.5pt; line-height: 1.3; white-space: nowrap; }
.ttd .nip { margin: 4px 0 0; font-size: 9pt; font-weight: 700; }
.ttd .spacer { height: 58px; }
@media print {
    @page { size: A4 landscape; margin: 4mm; }
    body { background: #fff; }
    .page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
    .no-print { display: none !important; }
    .ttd { page-break-inside: avoid; }
}
</style>
</head>
<body>
<div class="no-print">
    <a href="sipelamar_jadwal.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>">← Kembali</a>
    <a href="sipelamar_cetak.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>">Cetak Jadwal</a>
    <button type="button" onclick="window.print()">Cetak Paraf / TTD</button>
</div>

<div class="page">
    <?= sipandang_kop_kelurahan_render_html($kop, false) ?>

    <div class="judul">LEMBAR PARAF &amp; TTD PIKET SiPELAMAR</div>
    <div class="subjudul">
        <?= sipelamar_cetak_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?> · Pelayanan Malam · Jam <?= sipelamar_cetak_h($jamLabel) ?>
    </div>

    <div class="meta">
        <div><span class="paraf-kotak paraf-auto">✓</span> = Sudah absen SiPELAMAR (otomatis dari sistem)</div>
        <div><span class="paraf-kotak"></span> = Paraf manual (belum absen)</div>
        <div><strong>OFF</strong> = Libur piket (hari kerja) · <strong style="color:#b91c1c">—</strong> = Sabtu/Minggu &amp; libur nasional (kolom merah)</div>
        <div>Kolom kanan = ruang <strong>TTD</strong> bulanan per operator</div>
        <div><strong>Dicetak:</strong> <?= date('d/m/Y H:i') ?></div>
    </div>

    <?php if ($pasanganList !== []): ?>
    <div class="pasangan-box">
        <strong>Pasangan piket:</strong>
        <?php foreach ($pasanganList as $i => $p): ?>
        <span>#<?= $i + 1 ?> <?= sipelamar_cetak_h($p['nama_1']) ?> + <?= sipelamar_cetak_h($p['nama_2']) ?></span>
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
                    <th class="hdr-main col-ttd" rowspan="2">TTD</th>
                </tr>
                <tr>
                    <?php foreach ($kolom as $c): ?>
                    <th class="hdr-sub col-day <?= !empty($c['kerja']) ? 'col-kerja' : 'col-libur' ?>"><?= sipelamar_cetak_h((string) $c['hari']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($operators === []): ?>
                <tr>
                    <td colspan="<?= count($kolom) + 3 ?>" style="padding:12px;color:#64748b;">Belum ada operator / jadwal.</td>
                </tr>
            <?php else:
                foreach ($operators as $i => $op):
                    $uid = (int) $op['id_user'];
            ?>
                <tr>
                    <td class="col-no"><?= (int) ($op['urutan'] ?: ($i + 1)) ?></td>
                    <td class="col-nama" title="<?= sipelamar_cetak_h($op['nama']) ?>"><?= sipelamar_cetak_h(sipelamar_nama_singkat((string) $op['nama'], 18)) ?></td>
                    <?php foreach ($kolom as $c):
                        $tgl = (string) $c['tanggal'];
                        $val = (string) ($grid[$uid][$tgl] ?? 'LIBUR');
                        if ($val === 'M') {
                            $hadir = !empty($absensiMap[$uid][$tgl]);
                            if ($hadir) {
                                echo '<td class="cell-paraf"><span class="paraf-kotak paraf-auto">✓</span></td>';
                            } else {
                                echo '<td class="cell-paraf"><span class="paraf-kotak"></span></td>';
                            }
                        } elseif ($val === 'OFF') {
                            echo '<td class="cell-off">OFF</td>';
                        } else {
                            echo '<td class="cell-libur">—</td>';
                        }
                    endforeach; ?>
                    <td class="col-ttd"><span class="ttd-kotak"></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="footer-note">
        Centang (✓) muncul otomatis bila operator sudah absen datang SiPELAMAR · Kotak kosong diisi paraf manual · Kolom TTD untuk tanda tangan persetujuan jadwal bulan ini.
    </div>

    <div class="ttd">
        <div class="box">
            <?= sipelamar_cetak_h($namaKel) ?>, <?= sipelamar_cetak_h($tglTtd) ?><br>
            <span class="jabatan"><?= sipelamar_cetak_h($labelJabatan) ?></span>
            <div class="spacer"></div>
            <?php if ($namaLurah !== ''): ?>
            <p class="nama"><?= sipelamar_cetak_nama_pejabat($namaLurah) ?></p>
            <p class="nip">NIP. <?= sipelamar_cetak_h($nipLurah !== '' ? $nipLurah : '—') ?></p>
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
