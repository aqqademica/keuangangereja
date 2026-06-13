<?php
// cek_donasi.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$search_result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['receipt_token'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $token = trim($_POST['receipt_token']);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT amount, kategori, date, status FROM uang_masuk WHERE receipt_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $search_result = $row;
    } else {
        $error = 'Donasi dengan Receipt ID tersebut tidak ditemukan.';
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Manajemen Keuangan Gereja | Cek Status Setoran Ke Gereja</title>
    
    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <!-- OverlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <!-- AdminLTE v4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" crossorigin="anonymous">
</head>
<body class="login-page bg-body-secondary">
    <div class="login-box" style="width: 450px;">
        <div class="login-logo">
            <a href="index.php"><b>SI</b>Gereja - Layanan Publik</a>
        </div>
        
        <div class="card shadow-lg">
            <div class="card-body login-card-body">
                <p class="login-box-msg pb-2">Cek Status Donasi / Setoran Anda</p>
                <p class="text-center text-muted small mb-4">Masukkan Receipt ID (Token) yang diberikan oleh Bendahara saat Anda menyerahkan donasi.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center px-2 py-1"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="" method="post" class="mb-4">
                    <?= csrfInput() ?>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control form-control-lg" name="receipt_token" placeholder="Contoh: TRX-2026-A1B2C3D4" required value="<?= htmlspecialchars($_POST['receipt_token'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Cari</button>
                    </div>
                </form>

                <?php if ($search_result): ?>
                    <div class="card bg-light border border-primary">
                        <div class="card-header bg-primary text-white py-2">
                            <h6 class="mb-0 text-center"><i class="bi bi-info-circle"></i> Hasil Pencarian</h6>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" width="40%">Receipt ID</td>
                                    <td class="fw-bold">: <?= htmlspecialchars($_POST['receipt_token']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal</td>
                                    <td class="fw-bold">: <?= htmlspecialchars($search_result['date']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Kategori</td>
                                    <td class="fw-bold">: <?= htmlspecialchars($search_result['kategori']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Nominal</td>
                                    <td class="fw-bold text-success">: <?= formatRupiah($search_result['amount']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted align-middle">Status</td>
                                    <td>: <?= generateBadgeStatus($search_result['status']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke Halaman Login</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>
