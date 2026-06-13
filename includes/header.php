<?php
// includes/header.php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$role = $_SESSION['role'];
$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();

// Validate active session
$stmtSessionCheck = $conn->prepare("SELECT active_session_id FROM users WHERE id = ?");
$stmtSessionCheck->bind_param("i", $user_id);
$stmtSessionCheck->execute();
$db_session = $stmtSessionCheck->get_result()->fetch_assoc()['active_session_id'] ?? null;
$stmtSessionCheck->close();

if ($db_session !== session_id()) {
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 86400,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax'
        ]);
    }
    $_SESSION['flash_error'] = "Sesi Anda berakhir karena akun ini telah login di perangkat atau browser lain.";
    redirect('login.php');
}

$currentPage = basename($_SERVER['PHP_SELF']);
if ($role !== 'MAJELIS_GEREJA' && $currentPage !== 'setup_security_questions.php' && $currentPage !== 'logout.php') {
    if (!isset($_SESSION['security_setup_done'])) {
        $stmtSec = $conn->prepare("SELECT COUNT(*) as cnt FROM user_security_answers WHERE user_id = ?");
        $stmtSec->bind_param("i", $user_id);
        $stmtSec->execute();
        $secCount = $stmtSec->get_result()->fetch_assoc()['cnt'];
        
        if ($secCount < 3) {
            // Set a flash message if you want, or just redirect
            $_SESSION['flash_error'] = "Anda wajib mengatur pertanyaan keamanan sebelum mengakses sistem.";
            redirect('setup_security_questions.php');
        } else {
            $_SESSION['security_setup_done'] = true;
        }
    }
}

// Fetch unread notifications count
$stmtNotif = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmtNotif->bind_param("i", $user_id);
$stmtNotif->execute();
$notifCount = $stmtNotif->get_result()->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - SI-Manajemen Keuangan Gereja </title>
    
    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <!-- OverlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <!-- AdminLTE v4 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" crossorigin="anonymous">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/favicon.png">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        
        <!-- Header / Navbar -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <!-- Sidebar Toggle -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                </ul>
                
                <!-- Right Navbar Links -->
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#">
                            <i class="bi bi-bell"></i>
                            <?php if ($notifCount > 0): ?>
                            <span class="navbar-badge badge text-bg-warning"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <span class="dropdown-item dropdown-header"><?= $notifCount ?> Pemberitahuan Baru</span>
                            <div class="dropdown-divider"></div>
                            <a href="notifikasi.php" class="dropdown-item text-center">Lihat Semua Notifikasi</a>
                        </div>
                    </li>
                    
                    <!-- User Menu -->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                            <img src="img/usericon.png" class="rounded-circle shadow-sm me-2" alt="User Image" style="width: 25px; height: 25px; object-fit: cover; background: #fff;">
                            <span class="d-none d-md-inline"><?= htmlspecialchars($name) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <li class="user-header text-bg-primary d-flex flex-column align-items-center justify-content-center">
                                <img src="img/usericon.png" class="rounded-circle shadow mb-2 mt-2" alt="User Image" style="width: 90px; height: 90px; object-fit: cover; background: #fff; border: 3px solid rgba(255,255,255,0.3);">
                                <p class="mb-0">
                                    <?= htmlspecialchars($name) ?>
                                    <small><?= htmlspecialchars($role) ?></small>
                                </p>
                            </li>
                            <li class="user-footer">
                                <?php if ($role === 'JEMAAT'): ?>
                                    <a href="profil_saya.php" class="btn btn-default btn-flat">Profil</a>
                                <?php endif; ?>
                                <a href="logout.php" class="btn btn-default btn-flat float-end">Sign out</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Sidebar -->
        <aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="index.php" class="brand-link">
                    <span class="brand-text fw-light">KEUANGAN GEREJA</span>
                </a>
            </div>
            
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                        
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <i class="nav-icon bi bi-speedometer2"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        
                        <?php if ($role === 'SEKRETARIS'): ?>
                        <li class="nav-header">DATA JEMAAT</li>
                        <li class="nav-item">
                            <a href="data_jemaat.php" class="nav-link">
                                <i class="nav-icon bi bi-people"></i>
                                <p>Kelola Data Jemaat</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="laporan_data_jemaat.php" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                                <p>Laporan Data Jemaat</p>
                            </a>
                        </li>

                        <li class="nav-header">KEUANGAN</li>
                        <li class="nav-item">
                            <a href="request_uang_keluar.php" class="nav-link">
                                <i class="nav-icon bi bi-wallet2"></i>
                                <p>Request Dana</p>
                            </a>
                        </li>

                        <li class="nav-header">MANAJEMEN USER</li>
                        <li class="nav-item">
                            <a href="manage_users.php" class="nav-link">
                                <i class="nav-icon bi bi-person-gear"></i>
                                <p>Kelola User</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reset_requests.php" class="nav-link">
                                <i class="nav-icon bi bi-key"></i>
                                <p>Kelola Request Reset</p>
                            </a>
                        </li>
                        
                        <?php else: ?>

                        <?php if (in_array($role, ['MAJELIS_GEREJA', 'BENDAHARA'])): ?>
                        <li class="nav-header">KEUANGAN & DATA</li>
                        <li class="nav-item">
                            <a href="uang_masuk.php" class="nav-link">
                                <i class="nav-icon bi bi-box-arrow-in-right"></i>
                                <p>Data Uang Masuk</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="uang_keluar.php" class="nav-link">
                                <i class="nav-icon bi bi-box-arrow-left"></i>
                                <p>Data Uang Keluar</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="data_jemaat.php" class="nav-link">
                                <i class="nav-icon bi bi-people"></i>
                                <p>Data Jemaat</p>
                            </a>
                        </li>
                        <?php if ($role === 'MAJELIS_GEREJA'): ?>
                        <li class="nav-item">
                            <a href="laporan_data_jemaat.php" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                                <p>Laporan Data Jemaat</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($role === 'JEMAAT'): ?>
                        <li class="nav-header">PRIBADI</li>
                        <li class="nav-item">
                            <a href="setoran_saya.php" class="nav-link">
                                <i class="nav-icon bi bi-wallet2"></i>
                                <p>Setoran Saya</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="profil_saya.php" class="nav-link">
                                <i class="nav-icon bi bi-person"></i>
                                <p>Profil Saya</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (in_array($role, ['MAJELIS_GEREJA', 'BENDAHARA'])): ?>
                        <li class="nav-header">MANAJEMEN</li>
                        <li class="nav-item">
                            <a href="laporan.php" class="nav-link">
                                <i class="nav-icon bi bi-bar-chart"></i>
                                <p>Buat/Lihat Laporan</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="histori_laporan.php" class="nav-link">
                                <i class="nav-icon bi bi-clock-history"></i>
                                <p>Histori Laporan</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($role === 'MAJELIS_GEREJA'): ?>
                        <li class="nav-header">MANAJEMEN KEUANGAN GKKD</li>
                        <li class="nav-item">
                            <a href="request_uang_keluar.php" class="nav-link">
                                <i class="nav-icon bi bi-wallet2"></i>
                                <p>Request Uang Keluar</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reset_requests.php" class="nav-link">
                                <i class="nav-icon bi bi-key"></i>
                                <p>Reset Password <span class="badge bg-warning float-end">Req</span></p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage_users.php" class="nav-link">
                                <i class="nav-icon bi bi-person-gear"></i>
                                <p>Manajemen User</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                        
                        <li class="nav-header">PENGATURAN</li>
                        <li class="nav-item">
                            <a href="setup_security_questions.php" class="nav-link">
                                <i class="nav-icon bi bi-shield-lock"></i>
                                <p>Pertanyaan Keamanan</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="change_password_user.php" class="nav-link">
                                <i class="nav-icon bi bi-key"></i>
                                <p>Ganti Password</p>
                            </a>
                        </li>
                        
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">Manajemen Keuangan Gereja GKKD ABC</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="app-content">
                <div class="container-fluid">
