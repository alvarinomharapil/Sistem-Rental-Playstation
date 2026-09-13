<?php
/**
 * =====================================================================
 * FILE: riwayat.php
 * FUNGSI: Menampilkan riwayat transaksi rental yang sudah Selesai,
 * dengan filter tanggal dan pencarian nama penyewa.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

$keyword     = trim($_GET['q'] ?? '');
$filterMulai = $_GET['dari'] ?? date('Y-m-01');
$filterAkhir = $_GET['sampai'] ?? date('Y-m-d');

$sql = "
    SELECT r.id, r.nama_penyewa, r.no_hp, r.waktu_mulai, r.waktu_selesai, r.total_jam, r.total_biaya, r.catatan,
           u.kode_unit, u.nama_unit, u.tipe_konsol
    FROM rental r
    JOIN unit_ps u ON u.id = r.unit_id
    WHERE r.status = 'Selesai'
      AND DATE(r.waktu_selesai) BETWEEN :dari AND :sampai
";
$params = ['dari' => $filterMulai, 'sampai' => $filterAkhir];

if (!empty($keyword)) {
    $sql .= ' AND (r.nama_penyewa LIKE :keyword OR u.nama_unit LIKE :keyword OR u.kode_unit LIKE :keyword)';
    $params['keyword'] = '%' . $keyword . '%';
}
$sql .= ' ORDER BY r.waktu_selesai DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// Total pendapatan pada rentang filter yang aktif
$totalPendapatan = array_sum(array_column($riwayat, 'total_biaya'));

$pageTitle  = 'Riwayat Transaksi';
$activeMenu = 'riwayat';
require_once __DIR__ . '/header.php';
?>

<div class="card card-glass mb-3">
    <div class="card-body">
        <form method="GET" action="riwayat.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Dari Tanggal</label>
                <input type="date" name="dari" class="form-control" value="<?= clean($filterMulai) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sampai Tanggal</label>
                <input type="date" name="sampai" class="form-control" value="<?= clean($filterAkhir) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Cari Penyewa/Unit</label>
                <input type="text" name="q" class="form-control" placeholder="Nama penyewa atau unit..." value="<?= clean($keyword) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary-gaming w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-glass">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Riwayat Transaksi (<?= count($riwayat) ?> transaksi)</span>
        <span class="fw-bold" style="color:#4ade80;">Total: <?= formatRupiah($totalPendapatan) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Penyewa</th>
                        <th>Unit</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Durasi</th>
                        <th>Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat)): ?>
                        <tr><td colspan="7" class="text-center text-muted-light py-4">Tidak ada riwayat transaksi pada rentang tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riwayat as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= clean($r['nama_penyewa']) ?><?= $r['no_hp'] ? '<br><small class="text-muted-light">' . clean($r['no_hp']) . '</small>' : '' ?></td>
                                <td><?= clean($r['kode_unit']) ?> - <?= clean($r['nama_unit']) ?><br><small class="text-muted-light"><?= clean($r['tipe_konsol']) ?></small></td>
                                <td><?= formatTanggalJamIndo($r['waktu_mulai']) ?></td>
                                <td><?= formatTanggalJamIndo($r['waktu_selesai']) ?></td>
                                <td><?= (int) $r['total_jam'] ?> jam</td>
                                <td class="fw-semibold"><?= formatRupiah($r['total_biaya']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
