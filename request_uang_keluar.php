<?php
// request_uang_keluar.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'SEKRETARIS']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    
    if ($action === 'submit_request') {
        $amount_raw = $_POST['amount'];
        $amount_raw = preg_replace('/,00$/', '', $amount_raw);
        $amount = preg_replace('/[^0-9]/', '', $amount_raw);
        $kategori_utama = $_POST['kategori_utama'];
        $sub_kategori = $_POST['sub_kategori'];
        $description = $_POST['description'];
        $date = $_POST['date'];
        
        $stmt = $conn->prepare("INSERT INTO uang_keluar (amount, kategori_utama, sub_kategori, description, date, is_request, requested_by, status) VALUES (?, ?, ?, ?, ?, 1, ?, 'PENDING')");
        $stmt->bind_param("dssssi", $amount, $kategori_utama, $sub_kategori, $description, $date, $user_id);
        
        if ($stmt->execute()) {
            systemLog('UANG_KELUAR_REQUEST', $user_id, "User requested uang keluar $kategori_utama - $sub_kategori sebesar $amount");
            $successMsg = "Request Pengeluaran berhasil diajukan ke Bendahara.";
        } else {
            $errorMsg = "Gagal mengajukan request pengeluaran: " . $conn->error;
        }
    } elseif ($action === 'edit_request') {
        $id = (int)$_POST['id'];
        $amount_raw = $_POST['amount'];
        $amount_raw = preg_replace('/,00$/', '', $amount_raw);
        $amount = preg_replace('/[^0-9]/', '', $amount_raw);
        $kategori_utama = $_POST['kategori_utama'];
        $sub_kategori = $_POST['sub_kategori'];
        $description = $_POST['description'];
        $date = $_POST['date'];
        
        $stmt = $conn->prepare("UPDATE uang_keluar SET amount=?, kategori_utama=?, sub_kategori=?, description=?, date=? WHERE id=? AND requested_by=? AND status='PENDING'");
        $stmt->bind_param("dssssii", $amount, $kategori_utama, $sub_kategori, $description, $date, $id, $user_id);
        if ($stmt->execute()) {
            $successMsg = "Request berhasil diperbarui.";
            systemLog('UANG_KELUAR_EDIT', $user_id, "Updated request id $id");
        } else {
            $errorMsg = "Gagal memperbarui request.";
        }
    } elseif ($action === 'delete_request') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM uang_keluar WHERE id=? AND requested_by=? AND status='PENDING'");
        $stmt->bind_param("ii", $id, $user_id);
        if ($stmt->execute()) {
            $successMsg = "Request berhasil dibatalkan/dihapus.";
            systemLog('UANG_KELUAR_DELETE', $user_id, "Deleted request id $id");
        } else {
            $errorMsg = "Gagal membatalkan request.";
        }
    }
}

// Fetch Previous Requests
$query = "SELECT * FROM uang_keluar WHERE requested_by = ? ORDER BY date DESC, id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Request Pengeluaran Dana</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal">
        <i class="fas fa-plus me-1"></i> Buat Request Baru
    </button>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

<div class="card shadow">
    <div class="card-header bg-white">
        <h5 class="mb-0">Histori Request Pengeluaran Saya</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Pengajuan</th>
                        <th>Tanggal Dibutuhkan</th>
                        <th>Kategori Utama</th>
                        <th>Sub Kategori</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['kategori_utama']) ?></td>
                            <td><?= htmlspecialchars($row['sub_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td class="fw-bold"><?= formatRupiah($row['amount']) ?></td>
                            <td>
                                <?php if($row['status'] === 'PENDING' && $row['is_request']): ?>
                                    <span class="badge bg-warning text-dark">Menunggu Bendahara</span>
                                <?php else: ?>
                                    <?= generateBadgeStatus($row['status']) ?>
                                <?php endif; ?>
                                
                                <?php if($row['status'] === 'REJECTED' && !empty($row['alasan_penolakan'])): ?>
                                    <div class="mt-1 small text-danger fw-bold"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($row['alasan_penolakan']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary mb-1" onclick="alert('Detail: \nKategori: <?= htmlspecialchars($row['kategori_utama']) ?>\nSub: <?= htmlspecialchars($row['sub_kategori']) ?>\nDesc: <?= htmlspecialchars($row['description']) ?>')"><i class="bi bi-eye"></i></button>
                                <?php if($row['status'] === 'PENDING'): ?>
                                <button class="btn btn-sm btn-info text-white mb-1" onclick='openEditRequest(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Batalkan request pengeluaran ini?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_request">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada request pengeluaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Request Pengeluaran -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="submit_request">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Buat Request Pengeluaran</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Tanggal Dibutuhkan</label>
            <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Kategori Utama</label>
            <select class="form-select" name="kategori_utama" required>
                <option value="">-- Pilih Kategori --</option>
                <option value="Biaya_Pembangunan">Biaya Pembangunan</option>
                <option value="Biaya_Sosial">Biaya Sosial</option>
                <option value="Biaya_Khusus">Biaya Khusus</option>
                <option value="Biaya_Umum">Biaya Umum</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Sub Kategori</label>
            <input type="text" class="form-control" name="sub_kategori" required placeholder="Contoh: Beli Sound System Baru">
        </div>
        <div class="mb-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" required placeholder="Contoh: 5000000">
        </div>
        <div class="mb-3">
            <label class="form-label">Alasan / Keterangan</label>
            <textarea class="form-control" name="description" rows="3" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Request ke Bendahara</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Request -->
<div class="modal fade" id="editRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="edit_request">
      <input type="hidden" name="id" id="edit_id" value="">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Edit Request Pengeluaran</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Tanggal Dibutuhkan</label>
            <input type="date" class="form-control" name="date" id="edit_date" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Kategori Utama</label>
            <select class="form-select" name="kategori_utama" id="edit_kategori_utama" required>
                <option value="">-- Pilih Kategori --</option>
                <option value="Biaya_Pembangunan">Biaya Pembangunan</option>
                <option value="Biaya_Sosial">Biaya Sosial</option>
                <option value="Biaya_Khusus">Biaya Khusus</option>
                <option value="Biaya_Umum">Biaya Umum</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Sub Kategori</label>
            <input type="text" class="form-control" name="sub_kategori" id="edit_sub_kategori" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" id="edit_amount" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Alasan / Keterangan</label>
            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info text-white">Update Request</button>
      </div>
    </form>
  </div>
</div>

<script>
let editModalInstance;
function openEditRequest(request) {
    if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editRequestModal'));
    }
    document.getElementById('edit_id').value = request.id;
    document.getElementById('edit_date').value = request.date;
    document.getElementById('edit_kategori_utama').value = request.kategori_utama;
    document.getElementById('edit_sub_kategori').value = request.sub_kategori;
    document.getElementById('edit_amount').value = request.amount;
    document.getElementById('edit_description').value = request.description;
    
    // trigger format rupiah
    let evt = new Event('input');
    document.getElementById('edit_amount').dispatchEvent(evt);
    
    editModalInstance.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
