<?php
// change_password.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Must be logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Check if they actually need to change it
$stmt = $conn->prepare("SELECT force_password_change FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['force_password_change']) {
    redirect('index.php');
}
$stmt->close();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($new_password) < 6) {
        $error = 'Password baru harus minimal 6 karakter.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        $upd = $conn->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
        $upd->bind_param("si", $hashed, $user_id);
        if ($upd->execute()) {
            systemLog('PASSWORD_CHANGED_FORCED', $user_id, "User changed temporary password successfully.");
            $success = 'Password berhasil diubah. Mengalihkan ke dashboard...';
            // Output small JS to redirect after 2s
            echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";
        } else {
            $error = 'Gagal menyimpan password baru.';
        }
        $upd->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Manajemen Keuangan Gereja GKKD | Ubah Password</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" crossorigin="anonymous">
</head>
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="index.php"><b>SI</b>Gereja</a>
        </div>
        
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg text-danger fw-bold">Pembaruan Password Diperlukan!</p>
                <p class="text-sm text-center mb-4">Demi keamanan, Anda wajib mengubah password sementara Anda dengan yang baru.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center px-2 py-1"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success text-center px-2 py-1"><?= htmlspecialchars($success) ?></div>
                <?php else: ?>

                <form action="" method="post">
                    <?= csrfInput() ?>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="new_password" placeholder="Password Baru (min. 6 karakter)" required>
                        <div class="input-group-text"><span class="bi bi-lock"></span></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="confirm_password" placeholder="Konfirmasi Password Baru" required>
                        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Simpan Password</button>
                        </div>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
