<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_admin.php';
sipandang_require_admin(false);
include __DIR__ . '/koneksi.php';
require_once __DIR__ . '/inc_sipelamar.php';

sipelamar_ensure_schema($koneksi);

$bulan = isset($_GET['bulan']) ? max(1, min(12, (int) $_GET['bulan'])) : (int) date('n');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
$operators = sipelamar_operators_list($koneksi);
$rotasiPool = sipelamar_rotasi_pool($koneksi);
$pasanganList = sipelamar_pasangan_list($koneksi);
$maxPasanganRows = 8;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = trim((string) ($_POST['aksi'] ?? ''));
    if ($aksi === 'simpan_pasangan') {
        $pairs = [];
        for ($i = 1; $i <= $maxPasanganRows; $i++) {
            $pairs[] = [
                (int) ($_POST['p' . $i . '_1'] ?? 0),
                (int) ($_POST['p' . $i . '_2'] ?? 0),
            ];
        }
        $res = sipelamar_pasangan_simpan_batch($koneksi, $pairs);
        if (!$res['ok']) {
            $err = $res['msg'];
        } else {
            header('Location: sipelamar_jadwal.php?bulan=' . $bulan . '&tahun=' . $tahun . '&toast=sipelamar_pasangan_saved');
            exit;
        }
    }
    if ($aksi === 'generate') {
        $timpa = isset($_POST['timpa']);
        $res = sipelamar_generate_jadwal_bulan($koneksi, $bulan, $tahun, $timpa);
        if (!$res['ok']) {
            $err = $res['msg'];
        } else {
            header('Location: sipelamar_jadwal.php?bulan=' . $bulan . '&tahun=' . $tahun . '&toast=sipelamar_jadwal_saved');
            exit;
        }
    }
    if ($aksi === 'hapus_bulan') {
        sipelamar_hapus_jadwal_bulan($koneksi, $bulan, $tahun);
        header('Location: sipelamar_jadwal.php?bulan=' . $bulan . '&tahun=' . $tahun . '&toast=sipelamar_jadwal_hapus');
        exit;
    }
    $tanggal = trim((string) ($_POST['tanggal'] ?? ''));
    if ($aksi === 'hapus' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        sipelamar_hapus_jadwal($koneksi, $tanggal);
        header('Location: sipelamar_jadwal.php?bulan=' . $bulan . '&tahun=' . $tahun . '&toast=sipelamar_jadwal_hapus');
        exit;
    }
    if ($aksi === 'simpan') {
        $res = sipelamar_simpan_jadwal(
            $koneksi,
            $tanggal,
            (int) ($_POST['id_user_1'] ?? 0),
            (int) ($_POST['id_user_2'] ?? 0),
            (string) ($_POST['catatan'] ?? '')
        );
        if (!$res['ok']) {
            $err = $res['msg'];
        } else {
            header('Location: sipelamar_jadwal.php?bulan=' . $bulan . '&tahun=' . $tahun . '&toast=sipelamar_jadwal_saved');
            exit;
        }
    }
}

$kalender = sipelamar_kalender_bulan($koneksi, $bulan, $tahun);
$jadwalList = sipelamar_jadwal_bulan($koneksi, $bulan, $tahun);
$jumlahIsi = count($jadwalList);
$jumlahKerja = count(array_filter($kalender, static fn ($c) => !empty($c['kerja'])));

$warnaNo = ['#ec4899', '#22c55e', '#ef4444', '#3b82f6', '#eab308', '#ec4899', '#22c55e', '#ef4444', '#3b82f6', '#eab308', '#ec4899', '#22c55e', '#ef4444'];

function sj_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

include 'header.php';
include 'sidebar.php';
sipandang_admin_dashboard_styles();
?>
<style>
.sj-wrap { margin-top: 12px; padding: 0 18px 32px; }
.sj-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 14px;
}
.sj-field label {
    font-size: .68rem;
    font-weight: 700;
    color: #566583;
    display: block;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: .35px;
}
.sj-field select,
.sj-field input[type="number"] {
    box-sizing: border-box;
    padding: 9px 11px;
    border: 1.5px solid #e3e8f3;
    border-radius: 9px;
    font: inherit;
    font-size: .85rem;
    color: var(--role-dark,#05285a);
    background: #fff;
    min-width: 0;
}
.sj-field select { min-width: 130px; }
.sj-field input[type="number"] { width: 96px; }
.sj-filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-left: auto;
    padding-top: 2px;
}
.sj-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e3e8f3;
    box-shadow: 0 3px 14px rgba(10,22,40,.08);
    overflow: hidden;
    margin-bottom: 14px;
}
.sj-card-hd {
    padding: 12px 16px;
    border-bottom: 1px solid #f4f6fb;
    background: #f8f9fe;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sj-card-hd strong {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .82rem;
    font-weight: 800;
    color: var(--role-dark,#05285a);
    margin: 0;
}
.sj-card-hd i {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--role-soft,#e8f2fd);
    color: var(--role-ink,#08428a);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
}
.sj-card-body { padding: 16px 18px; }
.sj-note {
    font-size: .82rem;
    color: #56658d;
    line-height: 1.55;
    margin-bottom: 14px;
}
.sj-note strong { color: var(--role-dark,#05285a); }
.sj-info {
    background: var(--role-soft,#e8f2fd);
    border: 1px solid #e0e7f6;
    border-left: 4px solid var(--role,#0b5cad);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .82rem;
    color: #56658d;
    margin-bottom: 14px;
    line-height: 1.5;
}
.sj-info strong { color: var(--role-dark,#05285a); }
.sj-warn {
    background: #fff8eb;
    border: 1px solid #f4ddb0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .82rem;
    color: #7a5800;
    margin-bottom: 14px;
}
.sj-divider {
    border-top: 1px dashed #e3e8f3;
    margin: 16px 0;
    padding-top: 16px;
}
.sj-pairs-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.sj-pairs-foot label {
    font-size: .78rem;
    color: #56658d;
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sj-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.sj-leg {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .72rem;
    font-weight: 700;
    background: #fff;
    border: 1px solid #e3e8f3;
    border-radius: 8px;
    padding: 4px 10px;
    box-shadow: 0 2px 8px rgba(10,22,40,.04);
}
.sj-leg-num {
    width: 22px;
    height: 22px;
    border-radius: 4px;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .68rem;
}
.sj-cal-wrap {
    overflow-x: auto;
    background: #fff;
    border-radius: 10px;
    border: 1px solid #f4f6fb;
    margin-bottom: 0;
}
.sj-cal { border-collapse: collapse; min-width: 100%; font-size: .68rem; }
.sj-cal th, .sj-cal td { border: 1px solid #e2e8f0; text-align: center; vertical-align: middle; padding: 4px 2px; min-width: 34px; }
.sj-cal thead th { background: #f8f9fe; font-weight: 800; color: #566583; position: sticky; top: 0; }
.sj-cal .sj-lbl { writing-mode: vertical-rl; transform: rotate(180deg); font-weight: 800; background: #f8fafc; min-width: 28px; }
.sj-off { background: #1e293b !important; color: #64748b; }
.sj-empty { background: #fef2f2; color: #5c6f8a; }
.sj-filled { background: var(--role-soft,#e8f2fd); }
.sj-today { outline: 2px solid var(--role,#0b5cad); outline-offset: -2px; }
.sj-num {
    display: inline-flex; width: 20px; height: 20px; border-radius: 3px;
    align-items: center; justify-content: center; color: #fff;
    font-weight: 800; font-size: .62rem; margin: 1px;
}
.sj-names {
    font-size: .58rem; line-height: 1.25; color: #334155; margin-top: 2px;
    max-width: 52px; margin-left: auto; margin-right: auto;
}
.sj-table { width: 100%; border-collapse: collapse; }
.sj-table th {
    background: #f8f9fe;
    color: #566583;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    padding: 10px 12px;
    border-bottom: 1px solid #f4f6fb;
}
.sj-table td {
    font-size: .82rem;
    vertical-align: middle;
    padding: 10px 12px;
    border-bottom: 1px solid #f4f6fb;
    color: #334155;
}
.sj-table tbody tr:last-child td { border-bottom: none; }
.sj-table tbody tr:hover { background: #fafcff; }
.sj-table .form-control {
    border: 1.5px solid #e3e8f3;
    border-radius: 8px;
    font-size: .82rem;
    padding: 7px 10px;
}
.sj-table .form-control:focus {
    border-color: var(--role,#0b5cad);
    box-shadow: none;
}
.sj-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--role-soft,#e8f2fd);
    color: var(--role-ink,#08428a);
    border: 1px solid var(--role-line,#c6dcf5);
    border-radius: 20px;
    padding: 3px 10px;
    font-size: .68rem;
    font-weight: 700;
}
.sj-form-lbl {
    font-size: .72rem;
    font-weight: 700;
    color: #566583;
    text-transform: uppercase;
    letter-spacing: .35px;
    display: block;
    margin-bottom: 5px;
}
.sj-form-inp {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #e3e8f3;
    border-radius: 8px;
    padding: 9px 11px;
    font: inherit;
    font-size: .85rem;
    color: var(--role-dark,#05285a);
    background: #fff;
    margin-bottom: 12px;
}
.sj-form-inp:focus { border-color: var(--role,#0b5cad); outline: none; }
.sj-foot-links { font-size: .78rem; color: #566583; margin-top: 8px; }
.sj-foot-links a { color: var(--role-ink,#08428a); font-weight: 600; text-decoration: none; }
.sj-foot-links a:hover { text-decoration: underline; }
@media (max-width: 991px) {
    .sj-wrap { padding: 0 12px 24px; }
    .sj-filter-actions { margin-left: 0; width: 100%; }
}
</style>

<div class="content-wrapper">
<div class="db-header arsip-header">
    <div class="container-fluid">
        <span class="hdr-badge"><i class="fas fa-calendar-alt mr-1"></i> SiPELAMAR</span>
        <h1>Jadwal Piket SiPELAMAR</h1>
        <p>Definisikan pasangan (A+B, C+D, …) → generate → bergilir per hari kerja, pasangan tetap bareng</p>
    </div>
</div>

<section class="content"><div class="container-fluid sj-wrap">
<?php if ($err !== ''): ?>
<div class="alert alert-danger"><?= sj_h($err) ?></div>
<?php endif; ?>

<?php if ($operators === []): ?>
<div class="sj-warn">
    <strong>Belum ada user level Operator.</strong> Tambahkan dulu di menu <a href="pengguna.php">Pengguna</a>, lalu refresh halaman ini.
</div>
<?php endif; ?>

<div class="filter-bar sj-filter no-print">
    <form method="get" class="d-flex flex-wrap align-items-end gap-3" style="width:100%;margin:0;">
        <div class="sj-field">
            <label>Bulan</label>
            <select name="bulan">
                <?php for ($b = 1; $b <= 12; $b++): ?>
                <option value="<?= $b ?>" <?= $b === $bulan ? 'selected' : '' ?>><?= sj_h(sipelamar_bulan_label($b)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="sj-field">
            <label>Tahun</label>
            <input type="number" name="tahun" value="<?= $tahun ?>">
        </div>
        <div class="sj-filter-actions">
            <button type="submit" class="btn-pro bp-navy"><i class="fas fa-search"></i> Tampilkan Bulan</button>
            <a href="sipelamar_cetak.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-pro bp-ghost">
                <i class="fas fa-print"></i> Cetak Jadwal
            </a>
            <a href="sipelamar_cetak_paraf.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-pro bp-ghost">
                <i class="fas fa-signature"></i> Cetak Paraf/TTD
            </a>
            <?php if ($jumlahIsi > 0): ?>
            <button type="button" class="btn-pro bp-danger" onclick="if(confirm('Hapus semua jadwal piket bulan ini?'))document.getElementById('formHapusBulan').submit();">
                <i class="fas fa-trash"></i> Kosongkan Bulan
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if ($jumlahIsi > 0): ?>
<form method="post" id="formHapusBulan" style="display:none;">
    <input type="hidden" name="aksi" value="hapus_bulan">
</form>
<?php endif; ?>

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-user-friends"></i>
        <strong>Pasangan Piket SiPELAMAR</strong>
    </div>
    <div class="sj-card-body">
        <p class="sj-note">
            Isi beberapa pasangan yang <strong>selalu bareng</strong> (mis. A+B, C+D, E+F).
            Satu operator hanya boleh masuk satu pasangan.
            Generate mengisi hari kerja bulan ini <strong>bergilir</strong>: hari 1 pasangan 1, hari 2 pasangan 2, … lalu ulang.
        </p>
        <form method="post">
            <input type="hidden" name="aksi" value="simpan_pasangan">
            <div class="table-responsive mb-3">
                <table class="sj-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:70px">Pasangan</th>
                            <th>Operator 1</th>
                            <th>Operator 2</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    for ($i = 1; $i <= $maxPasanganRows; $i++):
                        $saved = $pasanganList[$i - 1] ?? null;
                        $sel1 = (int) ($saved['id_user_1'] ?? 0);
                        $sel2 = (int) ($saved['id_user_2'] ?? 0);
                    ?>
                        <tr>
                            <td><strong>#<?= $i ?></strong></td>
                            <td>
                                <select name="p<?= $i ?>_1" class="form-control form-control-sm">
                                    <option value="">— kosongkan jika tidak dipakai —</option>
                                    <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int) $op['id_user'] ?>" <?= $sel1 === (int) $op['id_user'] ? 'selected' : '' ?>><?= sj_h((string) $op['nama_lengkap']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="p<?= $i ?>_2" class="form-control form-control-sm">
                                    <option value="">— kosongkan jika tidak dipakai —</option>
                                    <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int) $op['id_user'] ?>" <?= $sel2 === (int) $op['id_user'] ? 'selected' : '' ?>><?= sj_h((string) $op['nama_lengkap']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn-pro bp-navy" <?= count($operators) < 2 ? 'disabled' : '' ?>>
                <i class="fas fa-save"></i> Simpan Pasangan
            </button>
        </form>

        <?php if ($pasanganList !== []): ?>
        <div class="sj-divider">
            <p class="sj-note" style="margin-bottom:10px;">
                Pasangan tersimpan:
                <?php foreach ($pasanganList as $p): ?>
                <span class="sj-badge ml-1"><?= sj_h($p['nama_1']) ?> + <?= sj_h($p['nama_2']) ?></span>
                <?php endforeach; ?>
            </p>
            <form method="post" class="sj-pairs-foot">
                <input type="hidden" name="aksi" value="generate">
                <label>
                    <input type="checkbox" name="timpa" checked> Timpa jadwal lama bulan ini
                </label>
                <button type="submit" class="btn-pro bp-green"
                    onclick="return confirm('Generate jadwal <?= sj_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?> dengan rotasi pasangan?');">
                    <i class="fas fa-magic"></i> Generate Jadwal Sebulan
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="sj-info">
    <strong>Kalender di bawah</strong> menampilkan pasangan piket per tanggal.
    Hitam = libur · Biru muda = sudah dijadwalkan · Merah muda = hari kerja belum diisi.
    Saat ini: <strong><?= $jumlahIsi ?></strong> malam terjadwal dari <strong><?= $jumlahKerja ?></strong> hari kerja.
</div>

<?php if ($rotasiPool !== []): ?>
<div class="sj-legend">
    <?php foreach ($rotasiPool as $op):
        $no = (int) $op['urutan'];
        $bg = $warnaNo[($no - 1) % count($warnaNo)];
    ?>
    <span class="sj-leg"><span class="sj-leg-num" style="background:<?= sj_h($bg) ?>"><?= $no ?></span><?= sj_h(sipelamar_nama_singkat((string) $op['nama_lengkap'], 18)) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-th"></i>
        <strong>Kalender Piket — <?= sj_h(sipelamar_bulan_label($bulan)) ?> <?= $tahun ?></strong>
    </div>
    <div class="sj-card-body" style="padding:12px;">
        <div class="sj-cal-wrap">
            <table class="sj-cal">
                <thead>
                    <tr>
                        <th class="sj-lbl">Tgl</th>
                        <?php foreach ($kalender as $c): ?>
                        <th class="<?= empty($c['kerja']) ? 'sj-off' : '' ?>"><?= (int) date('j', strtotime((string) $c['tanggal'])) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="sj-lbl">Op&nbsp;1</td>
                        <?php
                        $today = date('Y-m-d');
                        foreach ($kalender as $c):
                            $cls = empty($c['kerja']) ? 'sj-off' : (empty($c['isi']) ? 'sj-empty' : 'sj-filled');
                            if ($c['tanggal'] === $today) {
                                $cls .= ' sj-today';
                            }
                            $no1 = (int) ($c['no_1'] ?? 0);
                            $bg1 = $no1 > 0 ? $warnaNo[($no1 - 1) % count($warnaNo)] : '';
                        ?>
                        <td class="<?= $cls ?>" title="<?= sj_h($c['nama_1'] ?? '') ?>">
                            <?php if (!empty($c['kerja']) && $no1 > 0): ?>
                            <span class="sj-num" style="background:<?= sj_h($bg1) ?>"><?= $no1 ?></span>
                            <?php elseif (!empty($c['kerja'])): ?>—<?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sj-lbl">Op&nbsp;2</td>
                        <?php foreach ($kalender as $c):
                            $cls = empty($c['kerja']) ? 'sj-off' : (empty($c['isi']) ? 'sj-empty' : 'sj-filled');
                            if ($c['tanggal'] === $today) {
                                $cls .= ' sj-today';
                            }
                            $no2 = (int) ($c['no_2'] ?? 0);
                            $bg2 = $no2 > 0 ? $warnaNo[($no2 - 1) % count($warnaNo)] : '';
                        ?>
                        <td class="<?= $cls ?>" title="<?= sj_h($c['nama_2'] ?? '') ?>">
                            <?php if (!empty($c['kerja']) && $no2 > 0): ?>
                            <span class="sj-num" style="background:<?= sj_h($bg2) ?>"><?= $no2 ?></span>
                            <?php elseif (!empty($c['kerja'])): ?>—<?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sj-lbl">Nama</td>
                        <?php foreach ($kalender as $c):
                            $cls = empty($c['kerja']) ? 'sj-off' : (empty($c['isi']) ? 'sj-empty' : 'sj-filled');
                        ?>
                        <td class="<?= $cls ?>">
                            <?php if (!empty($c['kerja']) && !empty($c['isi'])): ?>
                            <div class="sj-names"><?= sj_h(sipelamar_nama_singkat((string) $c['nama_1'], 8)) ?></div>
                            <div class="sj-names"><?= sj_h(sipelamar_nama_singkat((string) $c['nama_2'], 8)) ?></div>
                            <?php elseif (!empty($c['kerja'])): ?><span style="color:#cbd5e1">—</span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
<div class="col-lg-5 mb-3">
<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-edit"></i>
        <strong>Input Manual (1 tanggal)</strong>
    </div>
    <div class="sj-card-body">
        <form method="post">
            <input type="hidden" name="aksi" value="simpan">
            <label class="sj-form-lbl">Tanggal</label>
            <input type="date" name="tanggal" class="sj-form-inp" required value="<?= sj_h(date('Y-m-d')) ?>">
            <label class="sj-form-lbl">Operator 1</label>
            <select name="id_user_1" class="sj-form-inp" required>
                <option value="">— pilih —</option>
                <?php foreach ($operators as $op): ?>
                <option value="<?= (int) $op['id_user'] ?>"><?= sj_h((string) $op['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sj-form-lbl">Operator 2</label>
            <select name="id_user_2" class="sj-form-inp" required>
                <option value="">— pilih —</option>
                <?php foreach ($operators as $op): ?>
                <option value="<?= (int) $op['id_user'] ?>"><?= sj_h((string) $op['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sj-form-lbl">Catatan (opsional)</label>
            <input type="text" name="catatan" class="sj-form-inp" maxlength="255">
            <button type="submit" class="btn-pro bp-navy"><i class="fas fa-save"></i> Simpan Piket</button>
        </form>
    </div>
</div>
</div>

<div class="col-lg-7 mb-3">
<div class="sj-card">
    <div class="sj-card-hd">
        <i class="fas fa-list"></i>
        <strong>Daftar Piket (Tabel)</strong>
    </div>
    <div class="sj-card-body" style="padding:0;">
        <div class="table-responsive">
        <table class="sj-table mb-0">
            <thead><tr><th>Tanggal</th><th>Operator Piket</th><th></th></tr></thead>
            <tbody>
            <?php if ($jadwalList === []): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada jadwal. Simpan pasangan lalu klik <strong>Generate Jadwal Sebulan</strong>.</td></tr>
            <?php else: foreach ($jadwalList as $j): ?>
            <tr>
                <td><strong><?= date('d/m/Y', strtotime((string) $j['tanggal'])) ?></strong></td>
                <td>
                    <span class="sj-badge"><i class="fas fa-user"></i> <?= sj_h($j['nama_1'] ?? '') ?></span>
                    <span class="sj-badge ml-1"><i class="fas fa-user"></i> <?= sj_h($j['nama_2'] ?? '') ?></span>
                </td>
                <td class="text-right">
                    <form method="post" style="display:inline" onsubmit="return confirm('Hapus jadwal tanggal ini?');">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="tanggal" value="<?= sj_h((string) $j['tanggal']) ?>">
                        <button type="submit" class="btn-pro bp-danger bp-sm"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<p class="sj-foot-links">
    <a href="sipelamar_pengaturan.php"><i class="fas fa-cog"></i> Pengaturan jam</a>
    · <a href="sipelamar_rekap.php"><i class="fas fa-chart-bar"></i> Rekap malam</a>
</p>
</div>
</div>
</div></section>
</div>
<?php include 'footer.php'; ?>
