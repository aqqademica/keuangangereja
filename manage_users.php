<?php
// manage_users.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'SEKRETARIS']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

// Fetch jemaat without users
$jemaat_query = "SELECT * FROM jemaat_profiles WHERE user_id IS NULL ORDER BY nama_lengkap ASC";
$jemaat_result = $conn->query($jemaat_query);
$available_jemaat = [];
while ($j = $jemaat_result->fetch_assoc()) {
    $available_jemaat[] = $j;
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $jemaat_id = (int)$_POST['jemaat_id'];
        $name = $_POST['name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $password = $_POST['password'];
        $phone = $_POST['phone'] ?? null;
        
        if ($current_role === 'SEKRETARIS' && $role === 'MAJELIS_GEREJA') {
            $errorMsg = "Anda tidak memiliki izin untuk membuat akun dengan role MAJELIS_GEREJA.";
        } else {
            // Check if jemaat is valid and user_id IS NULL
            $check_jemaat = $conn->query("SELECT id FROM jemaat_profiles WHERE id = $jemaat_id AND user_id IS NULL");
            if ($check_jemaat->num_rows === 0) {
                $errorMsg = "Data Jemaat tidak valid atau sudah memiliki akun.";
            } else {
                // Check valid username regex to be safe
                if (!preg_match('/^[a-z0-9_]+$/', $username)) {
                    $errorMsg = "Format username tidak valid (hanya huruf kecil, angka, dan underscore).";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role, phone) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $name, $username, $email, $hashedPassword, $role, $phone);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $conn->insert_id;
                        // Link user to jemaat
                        $conn->query("UPDATE jemaat_profiles SET user_id = $new_user_id WHERE id = $jemaat_id");
                        
                        $successMsg = "User $name berhasil ditambahkan dan ditautkan ke profil Jemaat.";
                        systemLog('USER_ADD', $user_id, "Menambahkan user baru: $username ($role)");
                        
                        // Refresh available jemaat list
                        $jemaat_result = $conn->query($jemaat_query);
                        $available_jemaat = [];
                        while ($j = $jemaat_result->fetch_assoc()) {
                            $available_jemaat[] = $j;
                        }
                    } else {
                        $errorMsg = "Gagal menambahkan user: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = $_POST['name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $phone = $_POST['phone'] ?? null;
        $password = $_POST['password'] ?? '';
        
        if ($current_role === 'SEKRETARIS' && $role === 'MAJELIS_GEREJA') {
            $errorMsg = "Anda tidak memiliki izin untuk menetapkan role MAJELIS_GEREJA.";
        } else {
            $checkStmt = $conn->prepare("SELECT role FROM users WHERE id=?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $currUserRole = $checkStmt->get_result()->fetch_assoc()['role'];
            $checkStmt->close();
            
            if ($current_role === 'SEKRETARIS' && $currUserRole === 'MAJELIS_GEREJA') {
                $errorMsg = "Anda tidak diizinkan mengubah data MAJELIS_GEREJA.";
            } else {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, username=?, email=?, role=?, phone=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $name, $username, $email, $role, $phone, $hashedPassword, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name=?, username=?, email=?, role=?, phone=? WHERE id=?");
                    $stmt->bind_param("sssssi", $name, $username, $email, $role, $phone, $id);
                }
                
                if ($stmt->execute()) {
                    // Update the jemaat profile too just in case name/email/phone changed
                    $stmtUpdateJemaat = $conn->prepare("UPDATE jemaat_profiles SET nama_lengkap=?, email=?, no_hp=? WHERE user_id=?");
                    $stmtUpdateJemaat->bind_param("sssi", $name, $email, $phone, $id);
                    $stmtUpdateJemaat->execute();

                    $successMsg = "Data user $name berhasil diperbarui.";
                    systemLog('USER_EDIT', $user_id, "Mengubah data user ID: $id");
                } else {
                    $errorMsg = "Gagal memperbarui user: " . $conn->error;
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Cegah ketua menghapus dirinya sendiri
        if ($id === $user_id) {
            $errorMsg = "Anda tidak dapat menghapus akun Anda sendiri.";
        } else {
            $checkStmt = $conn->prepare("SELECT role FROM users WHERE id=?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $currUserRole = $checkStmt->get_result()->fetch_assoc()['role'];
            $checkStmt->close();
            
            if ($current_role === 'SEKRETARIS' && $currUserRole === 'MAJELIS_GEREJA') {
                $errorMsg = "Anda tidak diizinkan menghapus MAJELIS_GEREJA.";
            } else {
                // Delete user (cascade will handle some things, but set user_id to NULL in jemaat_profiles if no cascade)
                $conn->query("UPDATE jemaat_profiles SET user_id = NULL WHERE user_id = $id");
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $successMsg = "User berhasil dihapus.";
                    systemLog('USER_DELETE', $user_id, "Menghapus user ID: $id");
                    
                    // Refresh available jemaat list
                    $jemaat_result = $conn->query($jemaat_query);
                    $available_jemaat = [];
                    while ($j = $jemaat_result->fetch_assoc()) {
                        $available_jemaat[] = $j;
                    }
                } else {
                    $errorMsg = "Gagal menghapus user: " . $conn->error;
                }
            }
        }
    }
}

$query = "SELECT u.*, j.no_anggota FROM users u LEFT JOIN jemaat_profiles j ON u.id = j.user_id ORDER BY u.created_at DESC";
$result = $conn->query($query);
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Manajemen User</h3>
    <button class="btn btn-primary" onclick="openAddUserModal()">
        <i class="fas fa-user-plus me-1"></i> Tambah User Baru
    </button>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableUsers">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>No Anggota</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>No HP</th>
                        <th>Role</th>
                        <th>Tgl Daftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['no_anggota'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['role']) ?></span></td>
                            <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                            <td>
                                <?php if ($current_role !== 'SEKRETARIS' || $row['role'] !== 'MAJELIS_GEREJA'): ?>
                                    <button class="btn btn-sm btn-info text-white mb-1" onclick='openEditModal(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                    <?php if ($row['id'] !== $user_id): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini? Profil jemaatnya akan tetap ada namun tidak tertaut dengan akun login.');">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">Belum ada user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Tambah User Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Cari Data Jemaat <span class="text-danger">*</span></label>
            <select class="form-select" name="jemaat_id" id="jemaat_id_select" style="width: 100%;" required>
                <option value="">-- Cari berdasarkan Nama Jemaat --</option>
                <?php foreach($available_jemaat as $j): ?>
                <option value="<?= $j['id'] ?>" data-name="<?= htmlspecialchars($j['nama_lengkap']) ?>" data-phone="<?= htmlspecialchars($j['no_hp']) ?>" data-email="<?= htmlspecialchars($j['email']) ?>"><?= htmlspecialchars($j['nama_lengkap']) ?> (<?= htmlspecialchars($j['no_anggota']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Hanya jemaat yang belum memiliki akun yang ditampilkan.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" class="form-control" name="name" id="user_name" required readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">No Handphone</label>
            <input type="text" class="form-control" name="phone" id="user_phone" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" id="user_email" required readonly>
            <small class="text-danger" id="email_warning" style="display:none;">Data Jemaat ini belum memiliki Email, mohon isi manual / kembali update Profil Jemaat.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Username (Auto Generated)</label>
            <input type="text" class="form-control bg-light" name="username" id="user_username" required readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Password (Auto Generated)</label>
            <div class="input-group">
                <input type="text" class="form-control bg-light" name="password" id="user_password" required readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">Regenerate</button>
            </div>
            <small class="text-muted">Min 8 karakter, kombinasi huruf besar, angka & simbol.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select class="form-select" name="role" required>
                <option value="JEMAAT">JEMAAT</option>
                <option value="BENDAHARA">BENDAHARA</option>
                <option value="SEKRETARIS">SEKRETARIS</option>
                <?php if ($current_role === 'MAJELIS_GEREJA'): ?>
                <option value="MAJELIS_GEREJA">MAJELIS_GEREJA</option>
                <?php endif; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="btnSubmitAddUser" disabled>Simpan User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id" value="">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="edit_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="username" id="edit_username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" id="edit_email" required>
        </div>
        <div class="mb-3">
            <label class="form-label">No Handphone</label>
            <input type="text" class="form-control" name="phone" id="edit_phone">
        </div>
        <div class="mb-3">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select class="form-select" name="role" id="edit_role" required>
                <option value="JEMAAT">JEMAAT</option>
                <option value="BENDAHARA">BENDAHARA</option>
                <option value="SEKRETARIS">SEKRETARIS</option>
                <?php if ($current_role === 'MAJELIS_GEREJA'): ?>
                <option value="MAJELIS_GEREJA">MAJELIS_GEREJA</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Ganti Password (Kosongkan jika tidak ingin diubah)</label>
            <input type="password" class="form-control" name="password" placeholder="Password baru">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info text-white">Update User</button>
      </div>
    </form>
  </div>
</div>

<!-- Select2 & DataTables Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/umd/simple-datatables.js"></script>
<script>
const availableJemaatCount = <?= count($available_jemaat) ?>;
let addUserModalInstance;

function openAddUserModal() {
    if (availableJemaatCount === 0) {
        alert("Tambah Data Jemaat Terlebih Dahulu");
        window.location.href = "data_jemaat.php";
    } else {
        if (!addUserModalInstance) {
            addUserModalInstance = new bootstrap.Modal(document.getElementById('addUserModal'));
        }
        addUserModalInstance.show();
    }
}

$(document).ready(function() {
    $('#jemaat_id_select').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#addUserModal')
    });
    
    $('#jemaat_id_select').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const val = selectedOption.val();
        
        if (val) {
            const name = selectedOption.data('name') || '';
            const phone = selectedOption.data('phone') || '';
            let email = selectedOption.data('email') || '';
            
            $('#user_name').val(name);
            $('#user_phone').val(phone);
            
            if(!email) {
                $('#user_email').removeAttr('readonly');
                $('#email_warning').show();
            } else {
                $('#user_email').attr('readonly', true);
                $('#email_warning').hide();
                $('#user_email').val(email);
            }
            
            // generate username
            let nameParts = name.trim().toLowerCase().split(/\s+/);
            let username = "";
            if (nameParts.length > 1) {
                username = nameParts[0] + "_" + nameParts[nameParts.length - 1];
            } else if (nameParts.length === 1 && nameParts[0] !== '') {
                username = nameParts[0] + "_" + nameParts[0];
            }
            username = username.replace(/[^a-z0-9_]/g, '');
            $('#user_username').val(username);
            
            generatePassword();
        } else {
            $('#user_name').val('');
            $('#user_phone').val('');
            $('#user_email').val('');
            $('#user_username').val('');
            $('#user_password').val('');
            $('#email_warning').hide();
            validateAddForm();
        }
    });
});

function generatePassword() {
    const chars = "abcdefghijklmnopqrstuvwxyz";
    const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const nums = "0123456789";
    const syms = "!@#$%^&*";
    
    let pass = "";
    pass += upper[Math.floor(Math.random() * upper.length)];
    pass += nums[Math.floor(Math.random() * nums.length)];
    pass += syms[Math.floor(Math.random() * syms.length)];
    
    const all = chars + upper + nums + syms;
    for(let i=0; i<5; i++) {
        pass += all[Math.floor(Math.random() * all.length)];
    }
    pass = pass.split('').sort(() => 0.5 - Math.random()).join('');
    
    document.getElementById('user_password').value = pass;
    validateAddForm();
}

function validateAddForm() {
    const jid = document.getElementById('jemaat_id_select').value;
    const pwd = document.getElementById('user_password').value;
    const username = document.getElementById('user_username').value;
    const btn = document.getElementById('btnSubmitAddUser');
    
    let isUsernameValid = /^[a-z0-9_]+$/.test(username);
    
    if (jid && pwd.length >= 8 && isUsernameValid) {
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

// Validation listener on input change if email is typed manually
$('#user_email').on('input', validateAddForm);

let editModalInstance;
function openEditModal(user) {
    if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editUserModal'));
    }
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_role').value = user.role;
    
    editModalInstance.show();
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("tableUsers")) {
        new simpleDatatables.DataTable("#tableUsers", {
            searchable: true,
            fixedHeight: false,
            perPage: 10,
            labels: {
                placeholder: "Cari user...",
                perPage: "Data per halaman",
                noRows: "Tidak ada data ditemukan",
                info: "Menampilkan {start} sampai {end} dari {rows} data"
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
