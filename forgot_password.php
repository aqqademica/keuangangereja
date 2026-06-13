<?php
// forgot_password.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';
$step = 1;
$user_id = null;
$questions = [];

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    if (isset($_POST['check_user'])) {
        $login_id = trim($_POST['login_id']);
        if (empty($login_id)) {
            $error = 'Masukkan Username atau Email Anda.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $login_id, $login_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $user_id = $user['id'];
                
                // Fetch user's security questions
                $qStmt = $conn->prepare("SELECT sq.id, sq.question_text FROM user_security_answers usa JOIN security_questions sq ON usa.question_id = sq.id WHERE usa.user_id = ?");
                $qStmt->bind_param("i", $user_id);
                $qStmt->execute();
                $qResult = $qStmt->get_result();
                
                while ($row = $qResult->fetch_assoc()) {
                    $questions[] = $row;
                }
                
                if (count($questions) < 3) {
                    $error = 'Anda belum mengatur 3 pertanyaan keamanan. Silakan hubungi Administrator.';
                } else {
                    $step = 2; // Move to step 2 (Answer questions)
                }
            } else {
                $error = 'Akun tidak ditemukan.';
            }
        }
    } elseif (isset($_POST['submit_answers'])) {
        $user_id = (int)$_POST['user_id'];
        $answers = $_POST['answers'] ?? [];
        
        $allCorrect = true;
        
        foreach ($answers as $q_id => $answer_text) {
            $stmt = $conn->prepare("SELECT answer FROM user_security_answers WHERE user_id = ? AND question_id = ?");
            $stmt->bind_param("ii", $user_id, $q_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Verify hash (Assuming answers are hashed, or strtolower comparison if plaintext)
                // For simplicity, we use case-insensitive plaintext matching if it's not a password hash, but the implementation plan said hashed. 
                // Let's assume password_verify is used
                if (!password_verify(trim(strtolower($answer_text)), $row['answer'])) {
                    $allCorrect = false;
                    break;
                }
            } else {
                $allCorrect = false;
                break;
            }
        }
        
        if ($allCorrect) {
            // Insert request
            $stmt = $conn->prepare("INSERT INTO password_reset_requests (user_id, status) VALUES (?, 'PENDING')");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = 'Permintaan reset password berhasil dikirim. Silakan tunggu verifikasi dari Admin.';
                $step = 3;
            } else {
                $error = 'Gagal mengirim permintaan: ' . $conn->error;
            }
        } else {
            $error = 'Jawaban keamanan Anda salah. Silakan coba lagi.';
            // Need to reload questions to stay on step 2
            $qStmt = $conn->prepare("SELECT sq.id, sq.question_text FROM user_security_answers usa JOIN security_questions sq ON usa.question_id = sq.id WHERE usa.user_id = ?");
            $qStmt->bind_param("i", $user_id);
            $qStmt->execute();
            $qResult = $qStmt->get_result();
            while ($row = $qResult->fetch_assoc()) {
                $questions[] = $row;
            }
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Manajemen Keuangan  GKKD | Lupa Password</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" crossorigin="anonymous">
</head>
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="index.php"><b>SI</b>Keuangan Gereja</a>
        </div>
        
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Reset Password</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center px-2 py-1"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success text-center px-2 py-1"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <form action="" method="post">
                        <?= csrfInput() ?>
                        <p class="text-muted text-sm">Masukkan Username atau Email Anda untuk mencari akun.</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="login_id" placeholder="Username atau Email" required>
                            <div class="input-group-text"><span class="bi bi-person"></span></div>
                        </div>
                        <button type="submit" name="check_user" class="btn btn-primary w-100">Cari Akun</button>
                    </form>
                    <p class="mt-3 mb-1 text-center"><a href="login.php">Kembali ke Login</a></p>
                
                <?php elseif ($step === 2): ?>
                    <form action="" method="post">
                        <?= csrfInput() ?>
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        <p class="text-muted text-sm">Jawab 3 pertanyaan keamanan berikut untuk memverifikasi identitas Anda.</p>
                        
                        <?php foreach ($questions as $q): ?>
                            <div class="mb-3">
                                <label class="form-label text-sm fw-normal"><?= htmlspecialchars($q['question_text']) ?></label>
                                <input type="text" class="form-control" name="answers[<?= $q['id'] ?>]" required>
                            </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="submit_answers" class="btn btn-primary w-100 mb-2">Kirim Permintaan Reset</button>
                        <a href="forgot_password.php" class="btn btn-secondary w-100">Batal</a>
                    </form>
                    
                <?php elseif ($step === 3): ?>
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-primary w-100">Kembali ke Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
