<?php
// notifikasi.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$successMsg = '';

// Mark all as read automatically on page visit
$stmtMark = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
$stmtMark->bind_param("i", $user_id);
$stmtMark->execute();
$stmtMark->close();

// Handle deletion of all notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $stmtDelete = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmtDelete->bind_param("i", $user_id);
    if ($stmtDelete->execute()) {
        $successMsg = "Semua pemberitahuan berhasil dihapus.";
    }
    $stmtDelete->close();
}

// Fetch all notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-bell me-2 text-primary"></i> Pemberitahuan Anda</h3>
    <?php if ($result->num_rows > 0): ?>
        <form method="POST" action="" onsubmit="return confirm('Hapus semua histori pemberitahuan?');">
            <?= csrfInput() ?>
            <button type="submit" name="clear_all" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Bersihkan Semua
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <?php if ($result->num_rows > 0): ?>
            <div class="list-group list-group-flush">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="list-group-item p-3 border-bottom">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1 text-dark fw-semibold"><?= htmlspecialchars($row['message']) ?></h6>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= date('d M Y H:i', strtotime($row['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                Tidak ada pemberitahuan baru.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
require_once __DIR__ . '/includes/footer.php';
?>
