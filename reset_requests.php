<?php
// reset_requests.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
checkRole(['MAJELIS_GEREJA', 'SEKRETARIS']);
$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $request_id = (int)$_POST['request_id'];
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['user_id'];
    
    // Fetch user details for password generation
    $stmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($user = $res->fetch_assoc()) {
        // Firstname is the first word of the name
        $nameParts = explode(' ', trim($user['name']));
        $firstName = $nameParts[0];
        $phone = $user['phone'] ?? '12345'; // Fallback if no phone
        
        $tempPassword = $firstName . $phone;
        $hashedTemp = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            // Update User password and set force change
            $uStmt = $conn->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
            $uStmt->bind_param("si", $hashedTemp, $user_id);
            $uStmt->execute();
            
            // Update Request Status
            $date = date('Y-m-d H:i:s');
            $rStmt = $conn->prepare("UPDATE password_reset_requests SET status = 'APPROVED', approved_by = ?, approved_at = ? WHERE id = ?");
            $rStmt->bind_param("isi", $admin_id, $date, $request_id);
            $rStmt->execute();
            
            // Invalidate other duplicate pending requests for this user
            $rRejectOthers = $conn->prepare("UPDATE password_reset_requests SET status = 'REJECTED', approved_by = ?, approved_at = ? WHERE user_id = ? AND status = 'PENDING' AND id != ?");
            $rRejectOthers->bind_param("isii", $admin_id, $date, $user_id, $request_id);
            $rRejectOthers->execute();
            $rRejectOthers->close();
            
            $conn->commit();
            systemLog('PASSWORD_RESET_APPROVED', $admin_id, "Approved password reset for user ID: $user_id");
            
            $successMsg = "Permintaan berhasil disetujui. Password sementara untuk user adalah: <strong>" . htmlspecialchars($tempPassword) . "</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Gagal memproses persetujuan: " . $e->getMessage();
        }
    } else {
        $errorMsg = "User tidak ditemukan.";
    }
}

// Fetch Pending Requests
$query = "SELECT pr.id, pr.user_id, pr.requested_at, u.name, u.username, u.email 
          FROM password_reset_requests pr 
          JOIN users u ON pr.user_id = u.id 
          WHERE pr.status = 'PENDING' 
          ORDER BY pr.requested_at ASC";
$result = $conn->query($query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Permintaan Reset Password</h3>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $successMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= $errorMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal Request</th>
                        <th>Nama Jemaat</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['requested_at']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><span class="badge bg-warning text-dark">Pending</span></td>
                            <td>
                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui reset password untuk user ini?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-circle"></i> Setujui & Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">Tidak ada permintaan reset password yang pending.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
