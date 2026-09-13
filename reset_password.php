<?php
/**
 * =====================================================================
 * FILE: reset_password.php
 * FUNGSI: Alat bantu SEKALI PAKAI untuk men-generate hash password yang
 * valid di server Anda sendiri dan langsung menyimpannya ke database.
 * Ini dibuat karena hash password_hash() bersifat unik per proses PHP,
 * jadi hash contoh di database_schema.sql tidak bisa dipakai langsung.
 *
 * CARA PAKAI:
 * 1. Pastikan database_schema.sql sudah di-import.
 * 2. Jalankan `php -S localhost:8000` lalu buka:
 *    http://localhost:8000/reset_password.php
 * 3. Masukkan username & password baru, klik "Set Password".
 * 4. SETELAH SELESAI, HAPUS FILE INI dari server (demi keamanan),
 *    karena siapa pun yang mengakses file ini bisa mengubah password admin.
 * =====================================================================
 */
require_once __DIR__ . '/config.php';

$pesan = '';
$tipePesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $pesan = 'Username dan password wajib diisi.';
        $tipePesan = 'danger';
    } elseif (strlen($password) < 6) {
        $pesan = 'Password minimal 6 karakter.';
        $tipePesan = 'danger';
    } else {
        $pdo = getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Cek apakah username sudah ada; jika ada, update, jika belum, buat baru.
        $cek = $pdo->prepare('SELECT id FROM users WHERE username = :username');
        $cek->execute(['username' => $username]);

        if ($cek->fetch()) {
            $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE username = :username');
            $stmt->execute(['password' => $hash, 'username' => $username]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, nama_lengkap) VALUES (:username, :password, :nama)');
            $stmt->execute(['username' => $username, 'password' => $hash, 'nama' => 'Administrator Rental']);
        }

        $pesan = 'Password untuk username "' . htmlspecialchars($username) . '" berhasil di-set. Silakan login, lalu HAPUS file reset_password.php ini dari server.';
        $tipePesan = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Admin - Rental PS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="alert alert-warning">
                <strong>Perhatian:</strong> Halaman ini adalah alat bantu sementara. Hapus file <code>reset_password.php</code> setelah selesai digunakan agar tidak disalahgunakan orang lain.
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3">Set / Reset Password Admin</h5>

                    <?php if ($pesan): ?>
                        <div class="alert alert-<?= $tipePesan ?>"><?= $pesan ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="admin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="text" name="password" class="form-control" value="admin123" required minlength="6">
                            <div class="form-text">Ditampilkan sebagai teks biasa agar mudah dicek, jangan pakai password penting di sini.</div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100">Set Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
