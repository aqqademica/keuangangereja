<?php
// uang_masuk.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'BENDAHARA', 'SEKRETARIS']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$role = $_SESSION['role'];

// Handle POST Requests CSRF Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
}

// Handle Verification / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $role === 'BENDAHARA') {
    $id = $_POST['id'];
    $action = $_POST['action']; // 'verify' or 'reject'
    $status = $action === 'verify' ? 'VERIFIED' : 'REJECTED';
    $verifier = $_SESSION['user_id'];
    
    // Get user_id to send notification
    $stmt = $conn->prepare("SELECT user_id, amount, kategori FROM uang_masuk WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    
    if ($tx) {
        $stmt = $conn->prepare("UPDATE uang_masuk SET status = ?, verified_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $status, $verifier, $id);
        
        if ($stmt->execute()) {
            systemLog('TX_VERIFICATION', $verifier, "Transaction $id status changed to $status");
            $successMsg = "Transaksi berhasil diperbarui.";
            
            // Send Notification to Jemaat
            if ($tx['user_id']) {
                $msg = "Setoran Anda sebesar " . formatRupiah($tx['amount']) . " untuk kategori " . $tx['kategori'] . " telah di-" . $status . " oleh Bendahara.";
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notifStmt->bind_param("is", $tx['user_id'], $msg);
                $notifStmt->execute();
            }
        } else {
            $errorMsg = "Gagal memperbarui transaksi.";
        }
    }
}

// Handle Manual Input by Bendahara
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_input']) && $role === 'BENDAHARA') {
    $amount_raw = $_POST['amount'];
    $amount_raw = preg_replace('/,00$/', '', $amount_raw);
    $amount = preg_replace('/[^0-9]/', '', $amount_raw);
    $kategori = $_POST['kategori'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $user_id = empty($_POST['user_id']) ? null : $_POST['user_id'];
    $verifier = $_SESSION['user_id']; // For manual input, verified_by is the inputter
    $input_by = $_SESSION['user_id']; 
    
    $receipt_token = 'TRX-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $proof = 'Disetor Bendahara';
    
    $stmt = $conn->prepare("INSERT INTO uang_masuk (user_id, amount, kategori, description, proof_of_transfer, date, status, verified_by, input_by, receipt_token) VALUES (?, ?, ?, ?, ?, ?, 'VERIFIED', ?, ?, ?)");
    $stmt->bind_param("idssssiis", $user_id, $amount, $kategori, $description, $proof, $date, $verifier, $input_by, $receipt_token);
    
    if ($stmt->execute()) {
        systemLog('MANUAL_TX_INPUT', $verifier, "Bendahara manually inputted setoran $kategori sebesar $amount");
        if ($receipt_token) {
            $successMsg = "Setoran Anonim berhasil disimpan. <strong>Receipt ID (Token): " . htmlspecialchars($receipt_token) . "</strong><br>Berikan token ini kepada donatur untuk cek status di halaman Cek Donasi.";
        } else {
            $successMsg = "Setoran manual berhasil ditambahkan dan langsung berstatus VERIFIED.";
            // Send Notif
            $msg = "Terima kasih, setoran manual sebesar " . formatRupiah($amount) . " untuk kategori " . $kategori . " telah di-input oleh Bendahara dan diverifikasi.";
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notifStmt->bind_param("is", $user_id, $msg);
            $notifStmt->execute();
        }
    } else {
        $errorMsg = "Gagal menambah setoran manual: " . $conn->error;
    }
}

// Fetch Data
$query = "SELECT u.id, u.amount, u.kategori, u.description, u.proof_of_transfer, u.date, u.status, u.receipt_token, 
                 usr.name as jemaat_name, input_user.role as input_role
          FROM uang_masuk u 
          LEFT JOIN users usr ON u.user_id = usr.id 
          LEFT JOIN users input_user ON u.input_by = input_user.id
          ORDER BY u.date DESC, u.id DESC";
$result = $conn->query($query);

// Fetch Jemaat list for manual input
$jemaatList = $conn->query("SELECT id, name FROM users WHERE role = 'JEMAAT' ORDER BY name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Data Uang Masuk</h3>
    <?php if ($role === 'BENDAHARA'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualInputModal">
            <i class="bi bi-plus-circle me-1"></i> Input Setoran Manual
        </button>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?><div class="alert alert-success alert-dismissible fade show"><?= $successMsg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-danger alert-dismissible fade show"><?= $errorMsg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableUangMasuk">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Jemaat / Receipt ID</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                        <th>Bukti</th>
                        <th>Di-input Oleh</th>
                        <th>Status</th>
                        <?php if ($role === 'BENDAHARA'): ?>
                        <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td>
                                <?php if ($row['jemaat_name']): ?>
                                    <span class="fw-bold"><?= htmlspecialchars($row['jemaat_name']) ?></span><br>
                                    <small class="text-muted"><i class="bi bi-ticket-detailed"></i> <?= htmlspecialchars($row['receipt_token'] ?? '-') ?></small>
                                <?php else: ?>
                                    <span class="fst-italic text-secondary">Anonim / Publik</span><br>
                                    <small class="text-muted"><i class="bi bi-ticket-detailed"></i> <?= htmlspecialchars($row['receipt_token']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['kategori']) ?></td>
                            <td class="fw-bold"><?= formatRupiah($row['amount']) ?></td>
                            <td>
                                <?php if ($row['proof_of_transfer'] === 'Disetor Bendahara'): ?>
                                    <span class="badge bg-secondary">Disetor Bendahara</span>
                                <?php elseif ($row['proof_of_transfer']): ?>
                                    <a href="<?= htmlspecialchars($row['proof_of_transfer']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-image"></i> Lihat</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    if ($row['input_role'] === 'BENDAHARA') {
                                        echo "<span class='badge bg-info text-dark'><i class='bi bi-person-badge'></i> Bendahara</span>";
                                    } elseif ($row['input_role'] === 'JEMAAT') {
                                        echo "<span class='badge bg-secondary'><i class='bi bi-phone'></i> Jemaat via App</span>";
                                    } else {
                                        echo "-";
                                    }
                                ?>
                            </td>
                            <td><?= generateBadgeStatus($row['status']) ?></td>
                            <?php if ($role === 'BENDAHARA'): ?>
                            <td>
                                <?php if ($row['status'] === 'PENDING'): ?>
                                <form method="POST" action="" class="d-inline">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="action" value="verify" class="btn btn-sm btn-success" title="Verifikasi"><i class="bi bi-check-lg"></i></button>
                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $role === 'BENDAHARA' ? '8' : '7' ?>" class="text-center">Belum ada data uang masuk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($role === 'BENDAHARA'): ?>
<!-- Modal Input Manual (Two-Step) -->
<div class="modal fade" id="manualInputModal" tabindex="-1" aria-labelledby="manualInputModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="manualInputModalLabel">Input Setoran Manual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <!-- Step 1: Search Jemaat -->
      <div id="step1-search" class="modal-body">
          <p class="text-muted small">Langkah 1: Cari jemaat yang memberikan setoran, atau pilih Anonim.</p>
          <div class="input-group mb-3">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="searchJemaatInput" class="form-control" placeholder="Ketik nama jemaat..." onkeyup="filterJemaat()">
          </div>
          
          <div class="list-group" id="jemaatList" style="max-height: 250px; overflow-y: auto;">
              <!-- Option Anonim -->
              <button type="button" class="list-group-item list-group-item-action text-primary fw-bold" onclick="selectJemaat('', 'Anonim / Publik')">
                  <i class="bi bi-person-fill-slash me-2"></i> Lanjut sebagai Anonim
              </button>
              
              <!-- Loop Jemaat -->
              <?php 
              if (isset($jemaatList)) {
                  $jemaatList->data_seek(0);
                  while($j = $jemaatList->fetch_assoc()): 
              ?>
                  <button type="button" class="list-group-item list-group-item-action jemaat-item" onclick="selectJemaat('<?= $j['id'] ?>', '<?= htmlspecialchars(addslashes($j['name'])) ?>')">
                      <i class="bi bi-person me-2 text-muted"></i> <?= htmlspecialchars($j['name']) ?>
                  </button>
              <?php 
                  endwhile; 
              }
              ?>
          </div>
      </div>
      
      <!-- Step 2: Input Form -->
      <form id="step2-form" method="POST" action="" style="display: none;">
        <?= csrfInput() ?>
        <input type="hidden" name="manual_input" value="1">
        <input type="hidden" name="user_id" id="selectedUserId" value="">
        
        <div class="modal-body">
            <p class="text-muted small mb-2">Langkah 2: Lengkapi detail setoran.</p>
            
            <div class="alert alert-info py-2 d-flex justify-content-between align-items-center">
                <span>Jemaat: <strong id="selectedJemaatName"></strong></span>
                <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="goBackToSearch()">Ubah</button>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Tanggal Transaksi</label>
                <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Kategori</label>
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
                <label class="form-label fw-bold">Nominal (Rp)</label>
                <input type="text" inputmode="numeric" class="form-control format-rupiah" name="amount" min="1000" placeholder="Contoh: 50000" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Keterangan</label>
                <textarea class="form-control" name="description" rows="2" placeholder="Opsional..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Setoran</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function filterJemaat() {
    let input = document.getElementById('searchJemaatInput').value.toLowerCase();
    let items = document.getElementsByClassName('jemaat-item');
    for (let i = 0; i < items.length; i++) {
        let txt = items[i].textContent || items[i].innerText;
        if (txt.toLowerCase().indexOf(input) > -1) {
            items[i].style.display = "";
        } else {
            items[i].style.display = "none";
        }
    }
}

function selectJemaat(id, name) {
    document.getElementById('selectedUserId').value = id;
    document.getElementById('selectedJemaatName').textContent = name;
    
    // Smooth transition
    document.getElementById('step1-search').style.display = 'none';
    document.getElementById('step2-form').style.display = 'block';
}

function goBackToSearch() {
    document.getElementById('step2-form').style.display = 'none';
    document.getElementById('step1-search').style.display = 'block';
    // Clear search filter
    document.getElementById('searchJemaatInput').value = '';
    filterJemaat();
}
</script>
<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/umd/simple-datatables.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("tableUangMasuk")) {
        new simpleDatatables.DataTable("#tableUangMasuk", {
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
