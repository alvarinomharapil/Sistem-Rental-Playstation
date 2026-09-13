<?php
/**
 * =====================================================================
 * FILE: header.php
 * FUNGSI: Template bagian atas (head, navbar, sidebar) bertema gaming.
 * =====================================================================
 */
if (!isLoggedIn()) {
    requireLogin();
}
$pageTitle = $pageTitle ?? 'Rental PS Game';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle) ?> - Rental PS Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #0f1220;
            font-family: 'Poppins', sans-serif;
            color: #e8eaf1;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a1f3a 0%, #12152b 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar a { color: #9aa1c2; text-decoration: none; }
        .sidebar a.active, .sidebar a:hover { background: linear-gradient(90deg, #6C5CE7, #a29bfe); color: #fff; }
        .sidebar .nav-link { padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 6px; font-weight: 500; transition: all .15s; }
        .brand-title { background: linear-gradient(90deg, #a29bfe, #74b9ff); -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: 700; }
        .card-stat, .card-glass {
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            background: #171b34;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            color: #e8eaf1;
        }
        .card-glass .card-header { background: transparent; border-bottom: 1px solid rgba(255,255,255,0.07); color: #cdd1ec; font-weight: 600; }
        .table { color: #e8eaf1; }
        .table > :not(caption) > * > * { background-color: transparent; color: #e8eaf1; }
        .table-hover > tbody > tr:hover > * { background-color: rgba(255,255,255,0.04); }
        .table-light, thead.table-light th { background-color: #1f2444 !important; color: #cdd1ec !important; border-color: rgba(255,255,255,0.07); }
        .form-control, .form-select {
            background-color: #12152b; border: 1px solid rgba(255,255,255,0.12); color: #e8eaf1;
        }
        .form-control:focus, .form-select:focus {
            background-color: #12152b; color: #fff; border-color: #6C5CE7; box-shadow: 0 0 0 .2rem rgba(108,92,231,.25);
        }
        .modal-content { background-color: #171b34; color: #e8eaf1; }
        .btn-primary-gaming {
            background: linear-gradient(90deg, #6C5CE7, #a29bfe);
            border: none; color: #fff; font-weight: 600;
        }
        .btn-primary-gaming:hover { opacity: .9; color: #fff; }
        .unit-card {
            border-radius: 18px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            transition: transform .15s;
        }
        .unit-card:hover { transform: translateY(-3px); }
        .unit-tersedia { background: linear-gradient(135deg, #0f5132, #14532d); }
        .unit-disewa   { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
        .unit-maintenance { background: linear-gradient(135deg, #78350f, #92400e); }
        .timer-live { font-variant-numeric: tabular-nums; font-size: 1.4rem; font-weight: 700; }
        .text-muted-light { color: #9aa1c2; }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="sidebar p-3" style="width: 250px;">
        <h5 class="brand-title mb-4"><i class="bi bi-controller"></i> Rental PS Game</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'rental' ? 'active' : '' ?>" href="rental.php">
                    <i class="bi bi-joystick me-2"></i> Sewa Berlangsung
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'riwayat' ? 'active' : '' ?>" href="riwayat.php">
                    <i class="bi bi-clock-history me-2"></i> Riwayat Transaksi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'unit' ? 'active' : '' ?>" href="unit.php">
                    <i class="bi bi-tv me-2"></i> Data Unit PS
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-power me-2"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <main class="flex-fill p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><?= clean($pageTitle) ?></h4>
            <span class="text-muted-light">
                <i class="bi bi-person-circle"></i> <?= clean($_SESSION['nama_lengkap'] ?? 'Admin') ?>
            </span>
        </div>
        <?php showFlash(); ?>
