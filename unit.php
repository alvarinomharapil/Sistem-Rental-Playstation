<?php
/**
 * =====================================================================
 * FILE: unit.php
 * FUNGSI: CRUD penuh untuk data Unit PS (Tampil, Tambah, Edit, Hapus).
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();
$errors = [];

// --- TAMBAH / EDIT UNIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_unit') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $id            = $_POST['id'] ?? '';
    $kode_unit     = trim($_POST['kode_unit'] ?? '');
    $nama_unit     = trim($_POST['nama_unit'] ?? '');
    $tipe_konsol   = $_POST['tipe_konsol'] ?? 'PS4';
    $harga_per_jam = trim($_POST['harga_per_jam'] ?? '');
    $status        = $_POST['status'] ?? 'Tersedia';

    if (empty($kode_unit)) {
        $errors[] = 'Kode unit wajib diisi.';
    }
    if (empty($nama_unit)) {
        $errors[] = 'Nama unit wajib diisi.';
    }
    if (!in_array($tipe_konsol, ['PS3', 'PS4', 'PS5'], true)) {
        $errors[] = 'Tipe konsol tidak valid.';
    }
    if (!is_numeric($harga_per_jam) || $harga_per_jam <= 0) {
        $errors[] = 'Harga per jam harus berupa angka positif.';
    }
    if (!in_array($status, ['Tersedia', 'Disewa', 'Maintenance'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    // Cegah admin mengubah status jadi Tersedia/Maintenance secara manual
    // padahal unit sedang punya transaksi Berlangsung (harus lewat menu Selesai Sewa)
    if (empty($errors) && !empty($id) && $status !== 'Disewa') {
        $cekAktif = $pdo->prepare("SELECT COUNT(*) AS jumlah FROM rental WHERE unit_id = :id AND status = 'Berlangsung'");
        $cekAktif->execute(['id' => $id]);
        if ($cekAktif->fetch()['jumlah'] > 0) {
            $errors[] = 'Unit ini masih memiliki transaksi sewa yang berlangsung. Selesaikan dulu lewat menu "Sewa Berlangsung".';
        }
    }

    if (empty($errors)) {
        if (empty($id)) {
            $cek = $pdo->prepare('SELECT id FROM unit_ps WHERE kode_unit = :kode_unit');
            $cek->execute(['kode_unit' => $kode_unit]);

            if ($cek->fetch()) {
                setFlash('danger', 'Kode unit "' . $kode_unit . '" sudah terdaftar.');
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO unit_ps (kode_unit, nama_unit, tipe_konsol, harga_per_jam, status)
                    VALUES (:kode_unit, :nama_unit, :tipe_konsol, :harga_per_jam, :status)
                ');
                $stmt->execute([
                    'kode_unit'     => $kode_unit,
                    'nama_unit'     => $nama_unit,
                    'tipe_konsol'   => $tipe_konsol,
                    'harga_per_jam' => $harga_per_jam,
                    'status'        => $status,
                ]);
                setFlash('success', 'Unit "' . $nama_unit . '" berhasil ditambahkan.');
            }
        } else {
            $cek = $pdo->prepare('SELECT id FROM unit_ps WHERE kode_unit = :kode_unit AND id != :id');
            $cek->execute(['kode_unit' => $kode_unit, 'id' => $id]);

            if ($cek->fetch()) {
                setFlash('danger', 'Kode unit "' . $kode_unit . '" sudah dipakai unit lain.');
            } else {
                $stmt = $pdo->prepare('
                    UPDATE unit_ps
                    SET kode_unit = :kode_unit, nama_unit = :nama_unit, tipe_konsol = :tipe_konsol,
                        harga_per_jam = :harga_per_jam, status = :status
                    WHERE id = :id
                ');
                $stmt->execute([
                    'kode_unit'     => $kode_unit,
                    'nama_unit'     => $nama_unit,
                    'tipe_konsol'   => $tipe_konsol,
                    'harga_per_jam' => $harga_per_jam,
                    'status'        => $status,
                    'id'            => $id,
                ]);
                setFlash('success', 'Data unit "' . $nama_unit . '" berhasil diperbarui.');
            }
        }
    } else {
        setFlash('danger', implode(' ', $errors));
    }

    redirect('unit.php');
}

// --- HAPUS UNIT ---
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    verifyCsrfToken($_GET['csrf_token'] ?? null);
    $id = (int) $_GET['id'];

    $cekRiwayat = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM rental WHERE unit_id = :id');
    $cekRiwayat->execute(['id' => $id]);
    $adaRiwayat = $cekRiwayat->fetch()['jumlah'] > 0;

    if ($adaRiwayat) {
        setFlash('danger', 'Unit tidak dapat dihapus karena memiliki riwayat transaksi sewa. Ubah status menjadi "Maintenance" jika unit ingin dinonaktifkan.');
    } else {
        $stmt = $pdo->prepare('DELETE FROM unit_ps WHERE id = :id');
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Data unit berhasil dihapus.');
    }

    redirect('unit.php');
}

// --- AMBIL DATA ---
$daftarUnit = $pdo->query('SELECT * FROM unit_ps ORDER BY kode_unit ASC')->fetchAll();

$csrfToken  = generateCsrfToken();
$pageTitle  = 'Data Unit PS';
$activeMenu = 'unit';
require_once __DIR__ . '/header.php';
?>

<div class="card card-glass">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tv"></i> Daftar Unit PS</span>
        <button class="btn btn-primary-gaming btn-sm" data-bs-toggle="modal" data-bs-target="#modalUnit" onclick="bukaModalTambah()">
            <i class="bi bi-plus-circle"></i> Tambah Unit
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Kode</th>
                        <th>Nama Unit</th>
                        <th>Tipe</th>
                        <th>Harga/Jam</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarUnit)): ?>
                        <tr><td colspan="7" class="text-center text-muted-light py-4">Belum ada data unit PS.</td></tr>
                    <?php else: ?>
                        <?php foreach ($daftarUnit as $i => $u): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= clean($u['kode_unit']) ?></td>
                                <td><?= clean($u['nama_unit']) ?></td>
                                <td><?= clean($u['tipe_konsol']) ?></td>
                                <td><?= formatRupiah($u['harga_per_jam']) ?></td>
                                <td><span class="badge bg-<?= badgeStatusUnit($u['status']) ?>"><?= clean($u['status']) ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-light"
                                        data-bs-toggle="modal" data-bs-target="#modalUnit"
                                        onclick='bukaModalEdit(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a class="btn btn-sm btn-outline-danger"
                                       href="unit.php?aksi=hapus&id=<?= (int) $u['id'] ?>&csrf_token=<?= clean($csrfToken) ?>"
                                       onclick="return confirm('Yakin ingin menghapus unit <?= clean($u['nama_unit']) ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUnit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="unit.php">
                <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="aksi" value="simpan_unit">
                <input type="hidden" name="id" id="form_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalUnitLabel">Tambah Unit PS</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Unit</label>
                        <input type="text" class="form-control" name="kode_unit" id="form_kode_unit" required maxlength="10" placeholder="Contoh: PS-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Unit</label>
                        <input type="text" class="form-control" name="nama_unit" id="form_nama_unit" required maxlength="50" placeholder="Contoh: Bilik 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipe Konsol</label>
                        <select class="form-select" name="tipe_konsol" id="form_tipe_konsol">
                            <option value="PS3">PS3</option>
                            <option value="PS4">PS4</option>
                            <option value="PS5">PS5</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga per Jam (Rp)</label>
                        <input type="number" class="form-control" name="harga_per_jam" id="form_harga_per_jam" required min="0" step="500">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="form_status">
                            <option value="Tersedia">Tersedia</option>
                            <option value="Disewa">Disewa</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                        <div class="form-text">Status "Disewa" idealnya diatur otomatis lewat menu "Sewa Berlangsung", bukan diubah manual di sini.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-gaming">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function bukaModalTambah() {
        document.getElementById('modalUnitLabel').innerText = 'Tambah Unit PS';
        document.getElementById('form_id').value = '';
        document.getElementById('form_kode_unit').value = '';
        document.getElementById('form_nama_unit').value = '';
        document.getElementById('form_tipe_konsol').value = 'PS4';
        document.getElementById('form_harga_per_jam').value = '';
        document.getElementById('form_status').value = 'Tersedia';
    }

    function bukaModalEdit(data) {
        document.getElementById('modalUnitLabel').innerText = 'Edit Unit - ' + data.nama_unit;
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_kode_unit').value = data.kode_unit;
        document.getElementById('form_nama_unit').value = data.nama_unit;
        document.getElementById('form_tipe_konsol').value = data.tipe_konsol;
        document.getElementById('form_harga_per_jam').value = data.harga_per_jam;
        document.getElementById('form_status').value = data.status;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
