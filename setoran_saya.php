<?php
// setoran_saya.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['JEMAAT']);

$conn = getDBConnection();
$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $amount_raw = $_POST['amount'];
        $amount_raw = preg_replace('/,00$/', '', $amount_raw);
        $amount = preg_replace('/[^0-9]/', '', $amount_raw);
        $kategori = $_POST['kategori'] ?? '';
        $description = $_POST['description'] ?? '';
        $date = date('Y-m-d');
        
        $receipt_token = 'TRX-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        // Handle File Upload
        $proof_path = null;
        if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['proof']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            
            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'pdf'])) {
                if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) {
                    $proof_path = 'uploads/' . $fileName;
                } else {
                    $errorMsg = "Gagal mengunggah file bukti.";
                }
            } else {
                $errorMsg = "Format file tidak didukung. Harap upload JPG, PNG, atau PDF.";
            }
        } else {
            $errorMsg = "Bukti transfer wajib diunggah.";
        }
        
        if (empty($errorMsg)) {
            $stmt = $conn->prepare("INSERT INTO uang_masuk (user_id, amount, kategori, description, proof_of_transfer, date, status, input_by, receipt_token) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)");
            $stmt->bind_param("idssssis", $userId, $amount, $kategori, $description, $proof_path, $date, $userId, $receipt_token);
            
            if ($stmt->execute()) {
                systemLog('SETORAN_SUBMITTED', $userId, "Jemaat submitted setoran $kategori sebesar $amount");
                $successMsg = "Setoran berhasil dikirim. ID Transaksi: $receipt_token. Menunggu verifikasi Bendahara.";
            } else {
                $errorMsg = "Gagal menyimpan data setoran.";
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $amount_raw = $_POST['amount'];
        $amount_raw = preg_replace('/,00$/', '', $amount_raw);
        $amount = preg_replace('/[^0-9]/', '', $amount_raw);
        $kategori = $_POST['kategori'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $proof_path = null;
        if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['proof']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            
            if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'pdf'])) {
                if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) {
                    $proof_path = 'uploads/' . $fileName;
                } else {
                    $errorMsg = "Gagal mengunggah file bukti.";
                }
            } else {
                $errorMsg = "Format file tidak didukung.";
            }
        }
        
        if (empty($errorMsg)) {
            if ($proof_path) {
                $stmt = $conn->prepare("UPDATE uang_masuk SET amount=?, kategori=?, description=?, proof_of_transfer=? WHERE id=? AND user_id=? AND status='PENDING'");
                $stmt->bind_param("dsssii", $amount, $kategori, $description, $proof_path, $id, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE uang_masuk SET amount=?, kategori=?, description=? WHERE id=? AND user_id=? AND status='PENDING'");
                $stmt->bind_param("dssii", $amount, $kategori, $description, $id, $userId);
            }
            
            if ($stmt->execute()) {
                systemLog('SETORAN_EDITED', $userId, "Jemaat edited setoran id $id");
                $successMsg = "Setoran berhasil diperbarui.";
            } else {
                $errorMsg = "Gagal memperbarui data setoran.";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM uang_masuk WHERE id=? AND user_id=? AND status='PENDING'");
        $stmt->bind_param("ii", $id, $userId);
        
        if ($stmt->execute()) {
            systemLog('SETORAN_DELETED', $userId, "Jemaat deleted setoran id $id");
            $successMsg = "Setoran berhasil dibatalkan/dihapus.";
        } else {
            $errorMsg = "Gagal menghapus setoran.";
        }
    }
}

// Fetch history
$stmt = $conn->prepare("SELECT * FROM uang_masuk WHERE user_id = ? ORDER BY date DESC, id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$riwayat = $stmt->get_result();
?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Form Setoran Uang Masuk</h5>
            </div>
            <div class="card-body">
                <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
                <?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" name="kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Perpuluhan">Perpuluhan</option>
                            <option value="Persembahan">Persembahan</option>
                            <option value="Donasi">Donasi</option>
                            <option value="Kolekte">Kolekte</option>
                            <option value="Pembangunan">Pembangunan</option>
                            <option value="DiakoniaSosial">Diakonia Sosial</option>
                            <option value="PemasukanLain">Pemasukan Lain</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" required placeholder="Contoh: 100000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan / Doa (Opsional)</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Bukti Transfer (Wajib)</label>
                        <input class="form-control" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small class="text-muted">Maksimal 2MB. Format: JPG, PNG, PDF</small>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-paper-plane me-1"></i> Kirim Setoran</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h5 class="mb-0">Riwayat Setoran Saya</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Kategori</th>
                                <th>Nominal</th>
                                <th>Bukti</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($riwayat->num_rows > 0): ?>
                                <?php while($row = $riwayat->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($row['date']) ?><br>
                                        <small class="text-muted"><i class="bi bi-ticket-detailed"></i> <?= htmlspecialchars($row['receipt_token']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['kategori']) ?></td>
                                    <td><?= formatRupiah($row['amount']) ?></td>
                                    <td>
                                        <?php if ($row['proof_of_transfer']): ?>
                                            <a href="<?= htmlspecialchars($row['proof_of_transfer']) ?>" target="_blank" class="btn btn-sm btn-outline-info">Lihat</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= generateBadgeStatus($row['status']) ?>
                                        
                                        <?php if ($row['status'] === 'PENDING'): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-info text-white mb-1" onclick='openEditSetoran(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan setoran ini?');">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">Belum ada riwayat setoran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Setoran -->
<div class="modal fade" id="editSetoranModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="" enctype="multipart/form-data">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id" value="">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Edit Setoran</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Kategori</label>
            <select class="form-select" name="kategori" id="edit_kategori" required>
                <option value="">-- Pilih Kategori --</option>
                <option value="Perpuluhan">Perpuluhan</option>
                <option value="Persembahan">Persembahan</option>
                <option value="Donasi">Donasi</option>
                <option value="Kolekte">Kolekte</option>
                <option value="Pembangunan">Pembangunan</option>
                <option value="DiakoniaSosial">Diakonia Sosial</option>
                <option value="PemasukanLain">Pemasukan Lain</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" id="edit_amount" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Keterangan / Doa (Opsional)</label>
            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
        </div>
        <div class="mb-4">
            <label class="form-label">Update Bukti Transfer (Opsional)</label>
            <input class="form-control" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
            <small class="text-muted">Biarkan kosong jika tidak ingin mengubah file bukti. Maks 2MB.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info text-white">Update Setoran</button>
      </div>
    </form>
  </div>
</div>

<script>
let editModalInstance;
function openEditSetoran(data) {
    if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editSetoranModal'));
    }
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_kategori').value = data.kategori;
    document.getElementById('edit_amount').value = data.amount;
    document.getElementById('edit_description').value = data.description || '';
    
    // trigger format rupiah
    let evt = new Event('input');
    document.getElementById('edit_amount').dispatchEvent(evt);
    
    editModalInstance.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
