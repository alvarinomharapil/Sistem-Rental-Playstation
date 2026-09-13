<?php
/**
 * =====================================================================
 * FILE: functions.php
 * FUNGSI: Kumpulan fungsi utilitas: session, sanitasi (anti XSS),
 * flash message, format tampilan, proteksi CSRF, dan kalkulasi biaya sewa.
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function clean(?string $data): string
{
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('danger', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect('login.php');
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function showFlash(): void
{
    if (!empty($_SESSION['flash'])) {
        $type    = clean($_SESSION['flash']['type']);
        $message = clean($_SESSION['flash']['message']);
        echo <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
        unset($_SESSION['flash']);
    }
}

function formatRupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function formatTanggalJamIndo(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $bulanIndo = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    $ts = strtotime($datetime);
    return date('d', $ts) . ' ' . $bulanIndo[(int) date('n', $ts)] . ' ' . date('Y H:i', $ts);
}

function badgeStatusUnit(string $status): string
{
    return match ($status) {
        'Tersedia'    => 'success',
        'Disewa'      => 'danger',
        'Maintenance' => 'warning',
        default       => 'secondary',
    };
}

function badgeStatusRental(string $status): string
{
    return match ($status) {
        'Berlangsung' => 'primary',
        'Selesai'     => 'success',
        default       => 'secondary',
    };
}

/**
 * Menghitung durasi sewa (dalam jam, dibulatkan ke atas per jam - praktik umum
 * rental PS: menit ke-1 sampai ke-60 tetap dihitung 1 jam penuh) dan total biaya.
 *
 * @param string $waktuMulai   Format Y-m-d H:i:s
 * @param string $waktuSelesai Format Y-m-d H:i:s
 * @param float  $hargaPerJam
 * @return array{jam: float, biaya: float}
 */
function hitungBiayaSewa(string $waktuMulai, string $waktuSelesai, float $hargaPerJam): array
{
    $mulai   = new DateTime($waktuMulai);
    $selesai = new DateTime($waktuSelesai);
    $selisihDetik = max(0, $selesai->getTimestamp() - $mulai->getTimestamp());

    // Dibulatkan ke atas per jam, minimum 1 jam
    $jam = max(1, (int) ceil($selisihDetik / 3600));
    $biaya = $jam * $hargaPerJam;

    return ['jam' => $jam, 'biaya' => $biaya];
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): void
{
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('danger', 'Sesi form tidak valid atau kedaluwarsa. Silakan coba lagi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}
