<?php
/**
 * =====================================================================
 * FILE: rental.php
 * FUNGSI: Core system transaksi rental. Dua aksi utama, masing-masing
 * ATOMIC menggunakan DB Transaction:
 *   1. MULAI SEWA  -> insert baris rental (status Berlangsung) + unit
 *                     jadi status 'Disewa'
 *   2. SELESAI SEWA -> hitung durasi & biaya otomatis, update rental
 *                     jadi status 'Selesai' + unit kembali 'Tersedia'
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

// --- MULAI SEWA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'mulai_sewa') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $unit_id      = $_POST['unit_id'] ?? '';
    $nama_penyewa = trim($_POST['nama_penyewa'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $catatan      = trim($_POST['catatan'] ?? '');

    if (empty($unit_id) || !ctype_digit((string) $unit_id)) {
        setFlash('danger', 'Unit PS wajib dipilih.');
        redirect('rental.php');
    }
    if (empty($nama_penyewa)) {
        setFlash('danger', 'Nama penyewa wajib diisi.');
        redirect('rental.php');
    }

    // Cek ulang status unit terkini agar tidak double-booking
    $stmtUnit = $pdo->prepare('SELECT id, status FROM unit_ps WHERE id = :id LIMIT 1');
    $stmtUnit->execute(['id' => $unit_id]);
    $unit = $stmtUnit->fetch();

    if (!$unit) {
        setFlash('danger', 'Data unit tidak ditemukan.');
    } elseif ($unit['status'] !== 'Tersedia') {
        setFlash('danger', 'Unit ini sedang tidak tersedia (statusnya: ' . $unit['status'] . ').');
    } else {
        // ============= MULAI DATABASE TRANSACTION =============
        try {
            $pdo->beginTransaction();

            // 1. Insert baris rental baru dengan waktu_mulai = sekarang
            $stmtInsert = $pdo->prepare('
                INSERT INTO rental (unit_id, nama_penyewa, no_hp, waktu_mulai, status, catatan)
                VALUES (:unit_id, :nama_penyewa, :no_hp, NOW(), "Berlangsung", :catatan)
            ');
            $stmtInsert->execute([
                'unit_id'      => $unit_id,
                'nama_penyewa' => $nama_penyewa,
                'no_hp'        => $no_hp ?: null,
                'catatan'      => $catatan ?: null,
            ]);

            // 2. Update status unit menjadi Disewa
            $stmtUpdate = $pdo->prepare('UPDATE unit_ps SET status = "Disewa" WHERE id = :id');
            $stmtUpdate->execute(['id' => $unit_id]);

            $pdo->commit();
            setFlash('success', 'Sewa untuk "' . $nama_penyewa . '" berhasil dimulai. Timer berjalan otomatis.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Gagal memulai sewa: ' . $e->getMessage());
        }
        // ============= AKHIR DATABASE TRANSACTION =============
    }

    redirect('rental.php');
}

// --- SELESAI SEWA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'selesai_sewa') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $rental_id = $_POST['rental_id'] ?? '';

    if (empty($rental_id) || !ctype_digit((string) $rental_id)) {
        setFlash('danger', 'Data transaksi tidak valid.');
        redirect('rental.php');
    }

    // Ambil data rental beserta harga unit terkait
    $stmtCek = $pdo->prepare('
        SELECT r.id, r.unit_id, r.waktu_mulai, r.status, r.nama_penyewa,
               u.harga_per_jam, u.nama_unit
        FROM rental r
        JOIN unit_ps u ON u.id = r.unit_id
        WHERE r.id = :id LIMIT 1
    ');
    $stmtCek->execute(['id' => $rental_id]);
    $rental = $stmtCek->fetch();

    if (!$rental) {
        setFlash('danger', 'Data transaksi tidak ditemukan.');
        redirect('rental.php');
    }
    if ($rental['status'] !== 'Berlangsung') {
        setFlash('danger', 'Transaksi ini sudah pernah diselesaikan sebelumnya.');
        redirect('rental.php');
    }

    // Hitung durasi & biaya otomatis berdasarkan waktu sekarang
    $waktuSelesai = date('Y-m-d H:i:s');
    $hasil = hitungBiayaSewa($rental['waktu_mulai'], $waktuSelesai, (float) $rental['harga_per_jam']);

    // ============= MULAI DATABASE TRANSACTION =============
    try {
        $pdo->beginTransaction();

        // 1. Update rental: tandai Selesai, catat waktu selesai, jam, dan biaya
        $stmtUpdateRental = $pdo->prepare('
            UPDATE rental
            SET status = "Selesai", waktu_selesai = :waktu_selesai, total_jam = :total_jam, total_biaya = :total_biaya
            WHERE id = :id
        ');
        $stmtUpdateRental->execute([
            'waktu_selesai' => $waktuSelesai,
            'total_jam'     => $hasil['jam'],
            'total_biaya'   => $hasil['biaya'],
            'id'            => $rental_id,
        ]);

        // 2. Update status unit kembali menjadi Tersedia
        $stmtUpdateUnit = $pdo->prepare('UPDATE unit_ps SET status = "Tersedia" WHERE id = :id');
        $stmtUpdateUnit->execute(['id' => $rental['unit_id']]);

        $pdo->commit();
        setFlash('success', 'Sewa "' . $rental['nama_penyewa'] . '" selesai. Total: ' . formatRupiah($hasil['biaya']) . ' (' . $hasil['jam'] . ' jam).');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal menyelesaikan sewa: ' . $e->getMessage());
    }
    // ============= AKHIR DATABASE TRANSACTION =============

    redirect('rental.php');
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
$unitTersedia = $pdo->query("SELECT id, kode_unit, nama_unit, tipe_konsol, harga_per_jam FROM unit_ps WHERE status = 'Tersedia' ORDER BY kode_unit ASC")->fetchAll();

$sewaBerlangsung = $pdo->query("
    SELECT r.id, r.nama_penyewa, r.no_hp, r.waktu_mulai, r.catatan,
           u.kode_unit, u.nama_unit, u.tipe_konsol, u.harga_per_jam
    FROM rental r
    JOIN unit_ps u ON u.id = r.unit_id
    WHERE r.status = 'Berlangsung'
    ORDER BY r.waktu_mulai ASC
")->fetchAll();

$csrfToken  = generateCsrfToken();
$pageTitle  = 'Sewa Berlangsung';
$activeMenu = 'rental';
require_once __DIR__ . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-glass">
            <div class="card-header"><i class="bi bi-play-circle"></i> Mulai Sewa Baru</div>
            <div class="card-body">
                <?php if (empty($unitTersedia)): ?>
                    <div class="alert alert-warning mb-0">
                        Semua unit sedang disewa atau maintenance. Tunggu unit selesai disewa, atau cek menu "Data Unit PS".
                    </div>
                <?php else: ?>
                    <form method="POST" action="rental.php">
                        <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="aksi" value="mulai_sewa">

                        <div class="mb-3">
                            <label class="form-label">Pilih Unit PS (Tersedia)</label>
                            <select class="form-select" name="unit_id" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unitTersedia as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>">
                                        <?= clean($u['kode_unit']) ?> - <?= clean($u['nama_unit']) ?> (<?= clean($u['tipe_konsol']) ?>, <?= formatRupiah($u['harga_per_jam']) ?>/jam)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Penyewa</label>
                            <input type="text" class="form-control" name="nama_penyewa" required maxlength="100" placeholder="Contoh: Rian">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">No. HP (Opsional)</label>
                            <input type="text" class="form-control" name="no_hp" maxlength="20">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <input type="text" class="form-control" name="catatan" maxlength="255" placeholder="Contoh: bawa stik sendiri">
                        </div>

                        <button type="submit" class="btn btn-primary-gaming w-100">
                            <i class="bi bi-joystick"></i> Mulai Sewa & Jalankan Timer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-glass">
            <div class="card-header"><i class="bi bi-controller"></i> Sedang Berlangsung (<?= count($sewaBerlangsung) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Unit</th>
                                <th>Penyewa</th>
                                <th>Mulai</th>
                                <th>Durasi Berjalan</th>
                                <th>Estimasi Biaya</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sewaBerlangsung)): ?>
                                <tr><td colspan="6" class="text-center text-muted-light py-4">Tidak ada sewa yang sedang berlangsung saat ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sewaBerlangsung as $s): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= clean($s['kode_unit']) ?> - <?= clean($s['nama_unit']) ?><br><small class="text-muted-light"><?= clean($s['tipe_konsol']) ?></small></td>
                                        <td><?= clean($s['nama_penyewa']) ?><?= $s['no_hp'] ? '<br><small class="text-muted-light">' . clean($s['no_hp']) . '</small>' : '' ?></td>
                                        <td><?= formatTanggalJamIndo($s['waktu_mulai']) ?></td>
                                        <td>
                                            <span class="timer-live" data-mulai="<?= clean($s['waktu_mulai']) ?>" style="font-size:1rem;">00:00:00</span>
                                        </td>
                                        <td>
                                            <span class="estimasi-biaya fw-semibold" data-mulai="<?= clean($s['waktu_mulai']) ?>" data-harga="<?= (float) $s['harga_per_jam'] ?>">Rp 0</span>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="rental.php" onsubmit="return confirm('Selesaikan sewa <?= clean($s['nama_penyewa']) ?>? Biaya akan dihitung otomatis berdasarkan waktu sekarang.')">
                                                <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                                                <input type="hidden" name="aksi" value="selesai_sewa">
                                                <input type="hidden" name="rental_id" value="<?= (int) $s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-stop-circle"></i> Selesai
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update timer berjalan & estimasi biaya (pembulatan ke atas per jam) setiap detik
    function updateTimersRental() {
        const sekarang = new Date();

        document.querySelectorAll('.timer-live').forEach(function (el) {
            const mulai = new Date(el.dataset.mulai.replace(' ', 'T'));
            let selisihDetik = Math.max(0, Math.floor((sekarang - mulai) / 1000));
            const jam = String(Math.floor(selisihDetik / 3600)).padStart(2, '0');
            const menit = String(Math.floor((selisihDetik % 3600) / 60)).padStart(2, '0');
            const detik = String(selisihDetik % 60).padStart(2, '0');
            el.textContent = jam + ':' + menit + ':' + detik;
        });

        document.querySelectorAll('.estimasi-biaya').forEach(function (el) {
            const mulai = new Date(el.dataset.mulai.replace(' ', 'T'));
            const harga = parseFloat(el.dataset.harga);
            let selisihDetik = Math.max(0, Math.floor((sekarang - mulai) / 1000));
            const jamDibulatkan = Math.max(1, Math.ceil(selisihDetik / 3600));
            const biaya = jamDibulatkan * harga;
            el.textContent = 'Rp ' + biaya.toLocaleString('id-ID');
        });
    }
    updateTimersRental();
    setInterval(updateTimersRental, 1000);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
