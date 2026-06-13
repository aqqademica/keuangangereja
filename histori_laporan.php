<?php
// histori_laporan.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'BENDAHARA']);

$conn = getDBConnection();
$role = $_SESSION['role'];
$successMsg = '';
$errorMsg = '';

// Handle Bendahara Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $role === 'BENDAHARA') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    $laporan_id = (int)$_POST['laporan_id'];
    
    if ($action === 'cancel') {
        // Cancel a SUBMITTED report back to DRAFT
        $stmt = $conn->prepare("UPDATE laporan_keuangan SET status = 'DRAFT' WHERE id = ? AND status = 'SUBMITTED'");
        $stmt->bind_param("i", $laporan_id);
        if ($stmt->execute()) {
            systemLog('LAPORAN_CANCEL', $_SESSION['user_id'], "Bendahara canceled submission for report ID $laporan_id");
            $successMsg = "Pengajuan laporan berhasil dibatalkan (kembali ke Draft).";
        }
    } else if ($action === 'resubmit') {
        // Resubmit a REJECTED report
        $stmt = $conn->prepare("UPDATE laporan_keuangan SET status = 'SUBMITTED', rejection_reason = NULL WHERE id = ? AND status = 'REJECTED'");
        $stmt->bind_param("i", $laporan_id);
        if ($stmt->execute()) {
            systemLog('LAPORAN_RESUBMIT', $_SESSION['user_id'], "Bendahara resubmitted report ID $laporan_id");
            $successMsg = "Laporan berhasil diajukan ulang ke Ketua.";
        }
    }
}

// Fetch all Laporan
$query = "SELECT l.*, 
                 u1.name as creator_name, 
                 u2.name as verifier_name 
          FROM laporan_keuangan l
          LEFT JOIN users u1 ON l.created_by = u1.id
          LEFT JOIN users u2 ON l.verified_by = u2.id
          ORDER BY l.created_at DESC";
$result = $conn->query($query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Histori & Status Laporan Keuangan</h3>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Periode</th>
                        <th>Waktu Submit</th>
                        <th>Waktu Verifikasi / Ditolak</th>
                        <th>Total Masuk</th>
                        <th>Total Keluar</th>
                        <th>Saldo Akhir</th>
                        <th>Status</th>
                        <th>Catatan Penolakan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $periodeStr = ($row['periode_bulan'] == 0 ? 'Semua Bulan' : date('F', mktime(0,0,0,$row['periode_bulan'],10))) . ' ' . ($row['periode_tahun'] == 0 ? 'Semua Tahun' : $row['periode_tahun']);
                            $submitTime = $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-';
                            $verifiedTime = ($row['status'] === 'VERIFIED' || $row['status'] === 'REJECTED') && isset($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-';
                        ?>
                        <tr>
                            <td class="fw-bold"><?= $periodeStr ?></td>
                            <td><?= $submitTime ?></td>
                            <td><?= $verifiedTime ?></td>
                            <td class="text-success"><?= formatRupiah($row['total_masuk']) ?></td>
                            <td class="text-danger"><?= formatRupiah($row['total_keluar']) ?></td>
                            <td class="fw-bold"><?= formatRupiah($row['saldo_akhir']) ?></td>
                            <td><?= generateBadgeStatus($row['status']) ?></td>
                            <td class="text-danger small" style="max-width: 200px; white-space: normal;">
                                <?= $row['status'] === 'REJECTED' ? htmlspecialchars($row['rejection_reason']) : '-' ?>
                            </td>
                            <td>
                                <a href="laporan.php?bulan=<?= $row['periode_bulan'] ?>&tahun=<?= $row['periode_tahun'] ?>" class="btn btn-sm btn-info text-white mb-1">
                                    <i class="bi bi-eye"></i> Lihat
                                </a>
                                
                                <?php if ($role === 'BENDAHARA'): ?>
                                    <?php if ($row['status'] === 'SUBMITTED'): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin ingin membatalkan pengajuan ini?');">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="laporan_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning mb-1"><i class="bi bi-x-circle"></i> Batalkan</button>
                                        </form>
                                    <?php elseif ($row['status'] === 'REJECTED'): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Pastikan Anda sudah merevisi laporan sebelum mengajukan ulang. Lanjutkan?');">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="action" value="resubmit">
                                            <input type="hidden" name="laporan_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success mb-1"><i class="bi bi-arrow-repeat"></i> Laporkan Ulang</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">Belum ada histori laporan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
