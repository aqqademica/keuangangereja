<?php
// login.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_id) || empty($password)) {
        $error = 'Username/Email dan Password harus diisi.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, name, username, email, password, role, force_password_change FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $login_id, $login_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Update last_login and active_session_id
                $session_id = session_id();
                $updateLogin = $conn->prepare("UPDATE users SET last_login = NOW(), active_session_id = ? WHERE id = ?");
                $updateLogin->bind_param("si", $session_id, $user['id']);
                $updateLogin->execute();
                
                systemLog('LOGIN_SUCCESS', $user['id'], "User logged in as {$user['role']}");
                
                if ($user['force_password_change']) {
                    redirect('change_password.php');
                } else {
                    redirect('index.php');
                }
            } else {
                systemLog('LOGIN_FAILED', null, "Invalid password attempt for: $login_id");
                $error = 'Password salah.';
            }
        } else {
            systemLog('LOGIN_FAILED', null, "Unknown login attempt: $login_id");
            $error = 'Akun tidak ditemukan.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Manajemen Keuangan Gereja GKKD | Log in</title>
    
    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <!-- OverlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <!-- AdminLTE v4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" crossorigin="anonymous">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/favicon.png">

    <style>
        body.login-page {
            background-image: url('img/bg2.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        /* Add a subtle dark overlay behind the login box for readability */
        .login-box {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            padding-top: 15px;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo mb-2">
            <a href="index.php"><b>SI</b>Gereja</a>
        </div>
        
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Silakan login untuk memulai sesi Anda</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center px-2 py-1"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrfInput() ?>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="login_id" placeholder="Username atau Email" required value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>">
                        <div class="input-group-text">
                            <span class="bi bi-person"></span>
                        </div>
                    </div>
                    
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" id="passwordField" name="password" placeholder="Password" required>
                        <div class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                            <span class="bi bi-eye-slash" id="togglePasswordIcon"></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                        </div>
                    </div>
                </form>

                <p class="mb-1 text-center">
                    <a href="forgot_password.php">Lupa Password?</a>
                </p>
                <p class="mb-0 text-center mt-3 border-top pt-3">
                    <a href="cek_donasi.php" class="text-success fw-bold text-decoration-none"><i class="bi bi-search me-1"></i> Cek Status Donasi (Publik)</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('passwordField');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            }
        }
    </script>
</body>
</html>
