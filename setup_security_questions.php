<?php
// setup_security_questions.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Check if user already has 3 questions setup
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_security_answers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$countRes = $stmt->get_result()->fetch_assoc()['cnt'];
$already_setup = ($countRes >= 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_questions'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $q1 = (int)$_POST['q1'];
    $q2 = (int)$_POST['q2'];
    $q3 = (int)$_POST['q3'];
    $a1 = trim($_POST['a1']);
    $a2 = trim($_POST['a2']);
    $a3 = trim($_POST['a3']);
    
    if (empty($a1) || empty($a2) || empty($a3)) {
        $errorMsg = "Semua jawaban harus diisi.";
    } else {
        // Hash answers
        $ha1 = password_hash(strtolower($a1), PASSWORD_DEFAULT);
        $ha2 = password_hash(strtolower($a2), PASSWORD_DEFAULT);
        $ha3 = password_hash(strtolower($a3), PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            // Clear existing
            $conn->query("DELETE FROM user_security_answers WHERE user_id = $user_id");
            
            $ins = $conn->prepare("INSERT INTO user_security_answers (user_id, question_id, answer) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $user_id, $q1, $ha1);
            $ins->execute();
            
            $ins->bind_param("iis", $user_id, $q2, $ha2);
            $ins->execute();
            
            $ins->bind_param("iis", $user_id, $q3, $ha3);
            $ins->execute();
            
            $conn->commit();
            systemLog('SECURITY_QUESTIONS_SETUP', $user_id, "User setup security questions");
            
            $successMsg = "Pertanyaan keamanan berhasil disimpan.";
            $already_setup = true;
            $_SESSION['security_setup_done'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Gagal menyimpan: " . $e->getMessage();
        }
    }
}

// Fetch all available questions
$allQ = $conn->query("SELECT * FROM security_questions ORDER BY id ASC");
$questions = [];
while ($row = $allQ->fetch_assoc()) {
    $questions[] = $row;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Pengaturan Pertanyaan Keamanan</h3>
</div>

<div class="row">
    <div class="col-md-8">
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php elseif (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        
        <?php if ($already_setup && !$successMsg): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> Anda sudah mengatur pertanyaan keamanan. Anda dapat memperbaruinya menggunakan form di bawah ini.
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Pilih 3 Pertanyaan</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrfInput() ?>
                    <input type="hidden" name="setup_questions" value="1">
                    
                    <?php if (count($questions) >= 3): ?>
                        <input type="hidden" name="q1" value="<?= $questions[0]['id'] ?>">
                        <input type="hidden" name="q2" value="<?= $questions[1]['id'] ?>">
                        <input type="hidden" name="q3" value="<?= $questions[2]['id'] ?>">
                        
                        <!-- Question 1 -->
                        <div class="mb-4 p-3 border rounded">
                            <label class="form-label fw-bold">Pertanyaan 1</label>
                            <p class="mb-2"><?= htmlspecialchars($questions[0]['question_text']) ?></p>
                            <input type="text" name="a1" class="form-control" placeholder="Jawaban Anda" required>
                        </div>
                        
                        <!-- Question 2 -->
                        <div class="mb-4 p-3 border rounded">
                            <label class="form-label fw-bold">Pertanyaan 2</label>
                            <p class="mb-2"><?= htmlspecialchars($questions[1]['question_text']) ?></p>
                            <input type="text" name="a2" class="form-control" placeholder="Jawaban Anda" required>
                        </div>
                        
                        <!-- Question 3 -->
                        <div class="mb-4 p-3 border rounded">
                            <label class="form-label fw-bold">Pertanyaan 3</label>
                            <p class="mb-2"><?= htmlspecialchars($questions[2]['question_text']) ?></p>
                            <input type="text" name="a3" class="form-control" placeholder="Jawaban Anda" required>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">Sistem belum memiliki pertanyaan keamanan yang cukup. Hubungi Administrator.</div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
