<?php
// uang_keluar.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'BENDAHARA', 'SEKRETARIS']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// CSRF Protection Check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
}

// Handle Bendahara - Process Request or New Input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_keluar']) && $role === 'BENDAHARA') {
    $amount_raw = $_POST['amount'];
    $amount_raw = preg_replace('/,00$/', '', $amount_raw);
    $amount = preg_replace('/[^0-9]/', '', $amount_raw);
    $kategori_utama = $_POST['kategori_utama'];
    $sub_kategori = $_POST['sub_kategori'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $request_id = isset($_POST['request_id']) && $_POST['request_id'] !== '' ? (int)$_POST['request_id'] : null;
    
    if ($request_id) {
        // Bendahara is processing a Ketua's request
        $stmt = $conn->prepare("UPDATE uang_keluar SET amount=?, kategori_utama=?, sub_kategori=?, description=?, date=?, created_by=?, status='PENDING' WHERE id=? AND is_request=1");
        $stmt->bind_param("dssssii", $amount, $kategori_utama, $sub_kategori, $description, $date, $user_id, $request_id);
        if ($stmt->execute()) {
            systemLog('UANG_KELUAR_PROCESS', $user_id, "Bendahara processed request ID $request_id");
            $successMsg = "Request pengeluaran berhasil diproses dan menunggu verifikasi Ketua.";
        }
    } else {
        // Bendahara is inputting a new manual expense
        $stmt = $conn->prepare("INSERT INTO uang_keluar (amount, kategori_utama, sub_kategori, description, date, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->bind_param("dssssi", $amount, $kategori_utama, $sub_kategori, $description, $date, $user_id);
        
        if ($stmt->execute()) {
            systemLog('UANG_KELUAR_INPUT', $user_id, "Bendahara inputted uang keluar $kategori_utama - $sub_kategori sebesar $amount");
            $successMsg = "Data Pengeluaran berhasil disimpan dan menunggu verifikasi Ketua.";
        } else {
            $errorMsg = "Gagal menambah pengeluaran: " . $conn->error;
        }
    }
}

// Handle Bendahara - Reject Request directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request']) && $role === 'BENDAHARA') {
    $req_id = (int)$_POST['req_id'];
    $alasan = $_POST['alasan_penolakan'] ?? '';
    $stmt = $conn->prepare("UPDATE uang_keluar SET status='REJECTED', alasan_penolakan=? WHERE id=? AND is_request=1");
    $stmt->bind_param("si", $alasan, $req_id);
    if ($stmt->execute()) {
        systemLog('UANG_KELUAR_REJECT_REQ', $user_id, "Bendahara rejected request ID $req_id with reason: $alasan");
        $successMsg = "Request dari Ketua berhasil ditolak.";
    }
}

// Handle Ketua - Verify or Reject Processed Pengeluaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $role === 'MAJELIS_GEREJA') {
    $action = $_POST['action'];
    $uk_id = (int)$_POST['uk_id'];
    
    if ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE uang_keluar SET status='VERIFIED', verified_by=? WHERE id=?");
        $stmt->bind_param("ii", $user_id, $uk_id);
        if ($stmt->execute()) $successMsg = "Pengeluaran berhasil diverifikasi.";
    } elseif ($action === 'reject') {
        $alasan = $_POST['alasan_penolakan'] ?? '';
        $stmt = $conn->prepare("UPDATE uang_keluar SET status='REJECTED', verified_by=?, alasan_penolakan=? WHERE id=?");
        $stmt->bind_param("isi", $user_id, $alasan, $uk_id);
        if ($stmt->execute()) {
            systemLog('UANG_KELUAR_REJECT', $user_id, "Ketua rejected uang keluar ID $uk_id with reason: $alasan");
            $successMsg = "Pengeluaran ditolak.";
        }
    }
}

// Fetch Pending Requests for Bendahara
$pendingRequests = null;
if ($role === 'BENDAHARA') {
    $qReq = "SELECT u.*, usr.name as requester_name 
             FROM uang_keluar u 
             LEFT JOIN users usr ON u.requested_by = usr.id 
             WHERE u.is_request = 1 AND u.status = 'PENDING' AND u.created_by IS NULL 
             ORDER BY u.date ASC";
    $pendingRequests = $conn->query($qReq);
}

// Fetch Main Data (Only processed or manual inputs, meaning created_by IS NOT NULL)
$query = "SELECT u.*, usr.name as creator_name, req.name as requester_name 
          FROM uang_keluar u 
          LEFT JOIN users usr ON u.created_by = usr.id 
          LEFT JOIN users req ON u.requested_by = req.id
          WHERE u.created_by IS NOT NULL
          ORDER BY u.date DESC, u.id DESC";
$result = $conn->query($query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Data Uang Keluar</h3>
    <?php if ($role === 'BENDAHARA'): ?>
        <button class="btn btn-danger" onclick="openInputModal()">
            <i class="fas fa-minus me-1"></i> Input Pengeluaran
        </button>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

<!-- Pending Requests Section (For Bendahara) -->
<?php if ($role === 'BENDAHARA' && $pendingRequests && $pendingRequests->num_rows > 0): ?>
<div class="card shadow border-warning mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0 text-dark"><i class="bi bi-exclamation-triangle-fill me-2"></i> Request Pengeluaran dari Ketua</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal Diminta</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($req = $pendingRequests->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($req['date']) ?></td>
                        <td><?= htmlspecialchars($req['kategori_utama']) ?> - <?= htmlspecialchars($req['sub_kategori']) ?></td>
                        <td><?= htmlspecialchars($req['description']) ?></td>
                        <td class="fw-bold text-danger"><?= formatRupiah($req['amount']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary mb-1" onclick='processRequest(<?= json_encode($req) ?>)'>Lanjutkan</button>
                            <button class="btn btn-sm btn-danger mb-1" onclick="openRejectModal(<?= $req['id'] ?>, 'request')">Tolak</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Data Table -->
<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableUangKeluar">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori Utama</th>
                        <th>Sub Kategori</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Di Request Oleh</th>
                        <th>Di-input Oleh</th>
                        <th>Status</th>
                        <?php if($role === 'MAJELIS_GEREJA') echo '<th>Aksi</th>'; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['kategori_utama']) ?></td>
                            <td><?= htmlspecialchars($row['sub_kategori']) ?></td>
                            <td>
                                <?= htmlspecialchars($row['description']) ?>
                                <?php if($row['is_request']): ?>
                                    <br><span class="badge bg-info mt-1"><i class="bi bi-person-badge"></i> Request KETUA</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-danger"><?= formatRupiah($row['amount']) ?></td>
                            <td><?= $row['is_request'] && !empty($row['requester_name']) ? htmlspecialchars($row['requester_name']) : 'Bendahara' ?></td>
                            <td><?= htmlspecialchars($row['creator_name']) ?></td>
                            <td>
                                <?= generateBadgeStatus($row['status']) ?>
                                <?php if($row['status'] === 'REJECTED' && !empty($row['alasan_penolakan'])): ?>
                                    <div class="mt-1 small text-danger fw-bold"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($row['alasan_penolakan']) ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <?php if($role === 'MAJELIS_GEREJA'): ?>
                            <td>
                                <?php if($row['status'] === 'PENDING'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="uk_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success mb-1" title="Setujui"><i class="bi bi-check-circle"></i></button>
                                    </form>
                                    <button class="btn btn-sm btn-danger mb-1" title="Tolak" onclick="openRejectModal(<?= $row['id'] ?>, 'pengeluaran')"><i class="bi bi-x-circle"></i></button>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada data uang keluar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($role === 'BENDAHARA'): ?>
<!-- Modal Input Pengeluaran -->
<div class="modal fade" id="inputKeluarModal" tabindex="-1" aria-labelledby="inputKeluarModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="submit_keluar" value="1">
      <input type="hidden" name="request_id" id="form_request_id" value="">
      
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="inputKeluarModalLabel">Input Uang Keluar</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <div id="requestAlert" class="alert alert-info d-none">
            <i class="bi bi-info-circle"></i> Memproses Request Pengeluaran dari Ketua.
        </div>
        
        <div class="mb-3">
            <label class="form-label">Tanggal</label>
            <input type="date" class="form-control" name="date" id="form_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Kategori Utama</label>
            <select class="form-select" name="kategori_utama" id="form_kategori_utama" required>
                <option value="">-- Pilih Kategori --</option>
                <option value="Biaya_Pembangunan">Biaya Pembangunan</option>
                <option value="Biaya_Sosial">Biaya Sosial</option>
                <option value="Biaya_Khusus">Biaya Khusus</option>
                <option value="Biaya_Umum">Biaya Umum</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Sub Kategori</label>
            <input type="text" class="form-control" name="sub_kategori" id="form_sub_kategori" required placeholder="Contoh: Beli Lampu, Bayar Listrik">
        </div>
        <div class="mb-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" id="form_amount" required placeholder="Contoh: 150000">
        </div>
        <div class="mb-3">
            <label class="form-label">Keterangan / Detail</label>
            <textarea class="form-control" name="description" id="form_description" rows="2"></textarea>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Ajukan Pengeluaran</button>
      </div>
    </form>
  </div>
</div>

<script>
let keluarModal;
document.addEventListener("DOMContentLoaded", function() {
    keluarModal = new bootstrap.Modal(document.getElementById('inputKeluarModal'));
});

function openInputModal() {
    document.getElementById('form_request_id').value = '';
    document.getElementById('form_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('form_kategori_utama').value = '';
    document.getElementById('form_sub_kategori').value = '';
    document.getElementById('form_amount').value = '';
    document.getElementById('form_description').value = '';
    document.getElementById('requestAlert').classList.add('d-none');
    
    keluarModal.show();
}

function processRequest(req) {
    document.getElementById('form_request_id').value = req.id;
    document.getElementById('form_date').value = req.date;
    document.getElementById('form_kategori_utama').value = req.kategori_utama;
    document.getElementById('form_sub_kategori').value = req.sub_kategori;
    document.getElementById('form_amount').value = req.amount;
    document.getElementById('form_description').value = req.description;
    
    document.getElementById('requestAlert').classList.remove('d-none');
    
    keluarModal.show();
}
</script>
<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/umd/simple-datatables.js"></script>
<!-- Modal Tolak -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" id="reject_action" value="">
      <input type="hidden" name="reject_request" id="reject_request_flag" value="">
      <input type="hidden" name="uk_id" id="reject_uk_id" value="">
      <input type="hidden" name="req_id" id="reject_req_id" value="">
      
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Konfirmasi Penolakan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea class="form-control" name="alasan_penolakan" rows="3" required placeholder="Jelaskan mengapa pengajuan ini ditolak..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
      </div>
    </form>
  </div>
</div>

<script>
let rejectModalInstance;
function openRejectModal(id, type) {
    if (!rejectModalInstance) {
        rejectModalInstance = new bootstrap.Modal(document.getElementById('rejectModal'));
    }
    document.getElementById('reject_uk_id').value = '';
    document.getElementById('reject_req_id').value = '';
    document.getElementById('reject_request_flag').value = '';
    document.getElementById('reject_action').value = '';
    
    if (type === 'request') {
        document.getElementById('reject_req_id').value = id;
        document.getElementById('reject_request_flag').value = '1';
    } else {
        document.getElementById('reject_uk_id').value = id;
        document.getElementById('reject_action').value = 'reject';
    }
    rejectModalInstance.show();
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("tableUangKeluar")) {
        new simpleDatatables.DataTable("#tableUangKeluar", {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            labels: {
                placeholder: "Cari transaksi (nama, kategori, dll)...",
                perPage: "Data per halaman",
                noRows: "Tidak ada data ditemukan",
                info: "Menampilkan {start} sampai {end} dari {rows} data"
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
