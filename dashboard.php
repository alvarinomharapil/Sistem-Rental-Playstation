<?php
/**
 * =====================================================================
 * FILE: dashboard.php
 * FUNGSI: Papan monitor utama - ringkasan analitik + kartu visual
 * setiap unit PS (hijau = Tersedia, merah = Disewa dengan timer live
 * berjalan otomatis, kuning = Maintenance).
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

// --- Ringkasan jumlah unit per status ---
$statUnit = $pdo->query("
    SELECT
        COUNT(*) AS total_unit,
        SUM(CASE WHEN status = 'Tersedia' THEN 1 ELSE 0 END) AS tersedia,
        SUM(CASE WHEN status = 'Disewa' THEN 1 ELSE 0 END) AS disewa,
        SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM unit_ps
")->fetch();

// --- Pendapatan hari ini (dari transaksi yang sudah Selesai) ---
$pendapatanHariIni = $pdo->prepare("
    SELECT COALESCE(SUM(total_biaya), 0) AS total
    FROM rental
    WHERE status = 'Selesai' AND DATE(waktu_selesai) = CURDATE()
");
$pendapatanHariIni->execute();
$pendapatan = $pendapatanHariIni->fetch()['total'];

// --- Jumlah transaksi selesai hari ini ---
$transaksiHariIni = $pdo->query("
    SELECT COUNT(*) AS total FROM rental WHERE status = 'Selesai' AND DATE(waktu_selesai) = CURDATE()
")->fetch()['total'];

// --- Ambil seluruh unit beserta info rental aktif (jika sedang disewa) ---
$daftarUnit = $pdo->query("
    SELECT u.id, u.kode_unit, u.nama_unit, u.tipe_konsol, u.harga_per_jam, u.status,
           r.id AS rental_id, r.nama_penyewa, r.waktu_mulai
    FROM unit_ps u
    LEFT JOIN rental r ON r.unit_id = u.id AND r.status = 'Berlangsung'
    ORDER BY u.kode_unit ASC
")->fetchAll();

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(108,92,231,0.15);">
                    <i class="bi bi-tv fs-4" style="color:#a29bfe;"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Total Unit</div>
                    <div class="fs-4 fw-bold"><?= (int) $statUnit['total_unit'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(34,197,94,0.15);">
                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Unit Tersedia</div>
                    <div class="fs-4 fw-bold"><?= (int) $statUnit['tersedia'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(239,68,68,0.15);">
                    <i class="bi bi-joystick fs-4 text-danger"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Sedang Disewa</div>
                    <div class="fs-4 fw-bold"><?= (int) $statUnit['disewa'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(250,204,21,0.15);">
                    <i class="bi bi-cash-coin fs-4" style="color:#facc15;"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Pendapatan Hari Ini</div>
                    <div class="fs-6 fw-bold"><?= formatRupiah($pendapatan) ?></div>
                    <div class="small text-muted-light"><?= (int) $transaksiHariIni ?> transaksi selesai</div>
                </div>
            </div>
        </div>
    </div>
</div>

<h6 class="mb-3 text-muted-light"><i class="bi bi-grid-3x3-gap"></i> Papan Status Unit (real-time)</h6>
<div class="row g-3 mb-4">
    <?php if (empty($daftarUnit)): ?>
        <div class="col-12">
            <div class="card card-stat"><div class="card-body text-center text-muted-light py-5">Belum ada data unit PS. Tambahkan di menu "Data Unit PS".</div></div>
        </div>
    <?php endif; ?>
    <?php foreach ($daftarUnit as $u): ?>
        <div class="col-md-4 col-lg-3">
            <?php
                $kelasWarna = match ($u['status']) {
                    'Tersedia'    => 'unit-tersedia',
                    'Disewa'      => 'unit-disewa',
                    'Maintenance' => 'unit-maintenance',
                    default       => '',
                };
            ?>
            <div class="unit-card <?= $kelasWarna ?> text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small opacity-75"><?= clean($u['tipe_konsol']) ?></div>
                        <div class="fs-5 fw-bold"><?= clean($u['nama_unit']) ?></div>
                        <div class="small opacity-75"><?= clean($u['kode_unit']) ?></div>
                    </div>
                    <i class="bi <?= $u['status'] === 'Disewa' ? 'bi-joystick' : ($u['status'] === 'Maintenance' ? 'bi-tools' : 'bi-check-circle') ?> fs-3 opacity-75"></i>
                </div>

                <?php if ($u['status'] === 'Disewa' && $u['waktu_mulai']): ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Penyewa: <?= clean($u['nama_penyewa']) ?></div>
                    <div class="timer-live mt-1" data-mulai="<?= clean($u['waktu_mulai']) ?>">00:00:00</div>
                    <div class="small opacity-75">mulai <?= formatTanggalJamIndo($u['waktu_mulai']) ?></div>
                <?php elseif ($u['status'] === 'Tersedia'): ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Siap disewakan</div>
                    <div class="fw-semibold"><?= formatRupiah($u['harga_per_jam']) ?> / jam</div>
                <?php else: ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Sedang tidak dapat disewakan</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    // Update timer berjalan setiap detik untuk unit yang sedang Disewa
    function updateTimers() {
        document.querySelectorAll('.timer-live').forEach(function (el) {
            const mulai = new Date(el.dataset.mulai.replace(' ', 'T'));
            const sekarang = new Date();
            let selisihDetik = Math.max(0, Math.floor((sekarang - mulai) / 1000));

            const jam = String(Math.floor(selisihDetik / 3600)).padStart(2, '0');
            const menit = String(Math.floor((selisihDetik % 3600) / 60)).padStart(2, '0');
            const detik = String(selisihDetik % 60).padStart(2, '0');

            el.textContent = jam + ':' + menit + ':' + detik;
        });
    }
    updateTimers();
    setInterval(updateTimers, 1000);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
