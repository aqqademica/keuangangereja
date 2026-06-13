<?php
// change_password_user.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Database connection setup

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $errorMsg = 'Semua field wajib diisi.';
    } elseif (strlen($new_password) < 6) {
        $errorMsg = 'Password baru harus minimal 6 karakter.';
    } elseif ($new_password !== $confirm_password) {
        $errorMsg = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($old_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashed, $user_id);
            if ($upd->execute()) {
                systemLog('PASSWORD_CHANGED_MANUAL', $user_id, "User changed their password manually.");
                $successMsg = 'Password berhasil diubah.';
            } else {
                $errorMsg = 'Terjadi kesalahan sistem saat mengubah password.';
            }
            $upd->close();
        } else {
            $errorMsg = 'Password lama salah.';
        }
        $stmt->close();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Ganti Password</h3>
</div>

<div class="row">
    <div class="col-md-6">
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <form action="" method="post">
                    <?= csrfInput() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Lama</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Baru</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6" placeholder="Minimal 6 karakter">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
