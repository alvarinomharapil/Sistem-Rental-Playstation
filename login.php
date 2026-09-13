<?php
/**
 * =====================================================================
 * FILE: login.php
 * FUNGSI: Autentikasi admin/operator menggunakan session + password_verify().
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Username dan password wajib diisi.';

    /**
     * ---------------------------------------------------------------
     * MODE TESTING: login pintas tanpa cek database.
     * Kredensial admin/admin123 di-hardcode di sini supaya bisa langsung
     * masuk tanpa perlu import database_schema.sql atau jalankan
     * reset_password.php dulu.
     *
     * PERINGATAN: JANGAN dipakai untuk production / server publik, karena
     * siapa pun yang baca source code ini otomatis tahu passwordnya.
     * Untuk kembali ke mode aman (cek ke tabel users di database),
     * hapus blok "if ($username === 'admin' ...)" di bawah ini dan
     * aktifkan lagi blok "MODE DATABASE" yang ada di bawahnya (tinggal
     * uncomment).
     * ---------------------------------------------------------------
     */
    } elseif ($username === 'admin' && $password === 'admin123') {
        session_regenerate_id(true);
        $_SESSION['user_id']      = 1; // id dummy (bukan 0, karena isLoggedIn() memakai empty() dan 0 dianggap kosong oleh PHP)
        $_SESSION['username']     = 'admin';
        $_SESSION['nama_lengkap'] = 'Administrator Rental';

        setFlash('success', 'Selamat datang kembali, Administrator Rental! (mode testing, tanpa cek database)');
        redirect('dashboard.php');
    } else {
        $errors[] = 'Username atau password yang Anda masukkan salah.';

        /**
         * ---------------------------------------------------------------
         * MODE DATABASE (aman, untuk production): hapus komentar di bawah
         * ini dan hapus blok "elseif" di atas jika ingin login kembali
         * memverifikasi ke tabel users pakai password_verify().
         * ---------------------------------------------------------------
         *
         * $pdo = getConnection();
         * $stmt = $pdo->prepare('SELECT id, username, password, nama_lengkap FROM users WHERE username = :username LIMIT 1');
         * $stmt->execute(['username' => $username]);
         * $user = $stmt->fetch();
         *
         * if ($user && password_verify($password, $user['password'])) {
         *     session_regenerate_id(true);
         *     $_SESSION['user_id']      = $user['id'];
         *     $_SESSION['username']     = $user['username'];
         *     $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
         *
         *     setFlash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!');
         *     redirect('dashboard.php');
         * } else {
         *     $errors[] = 'Username atau password yang Anda masukkan salah.';
         * }
         */
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rental PS Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top left, #2b2f6b, #0f1220 60%);
        }
        .login-card {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            background: #171b34;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            color: #e8eaf1;
        }
        .brand-title { background: linear-gradient(90deg, #a29bfe, #74b9ff); -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: 700; }
        .form-control { background-color: #12152b; border: 1px solid rgba(255,255,255,0.12); color: #e8eaf1; }
        .form-control:focus { background-color: #12152b; color: #fff; border-color: #6C5CE7; box-shadow: 0 0 0 .2rem rgba(108,92,231,.25); }
        .input-group-text { background-color: #12152b; border: 1px solid rgba(255,255,255,0.12); color: #9aa1c2; }
        .btn-gaming { background: linear-gradient(90deg, #6C5CE7, #a29bfe); border: none; color: #fff; font-weight: 600; }
        .btn-gaming:hover { opacity: .9; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-controller" style="font-size: 2.5rem; color: #a29bfe;"></i>
                        <h4 class="mt-2 mb-0 brand-title">Rental PS Game</h4>
                        <small class="text-muted-light" style="color:#9aa1c2;">Silakan login untuk melanjutkan</small>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= clean($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="username" required autofocus
                                       value="<?= clean($_POST['username'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gaming w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 small" style="color:#6b7099;">&copy; <?= date('Y') ?> Sistem Rental PS Game</p>
        </div>
    </div>
</div>
</body>
</html>
