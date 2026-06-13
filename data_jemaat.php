<?php
// data_jemaat.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'BENDAHARA', 'SEKRETARIS']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$user_id_session = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Function to handle image upload
function handleProfileUpload($file) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = time() . '_' . uniqid() . '.jpg';
        $target_file = $upload_dir . $filename;
        
        $source_file = $file['tmp_name'];
        
        // If GD library is installed and imagecreatefromjpeg exists
        if (function_exists('imagecreatefromjpeg')) {
            $image_info = getimagesize($source_file);
            if (!$image_info) return null;
            $mime_type = $image_info['mime'];
            
            switch($mime_type){
                case 'image/jpeg': $image = imagecreatefromjpeg($source_file); break;
                case 'image/png': $image = imagecreatefrompng($source_file); break;
                case 'image/gif': $image = imagecreatefromgif($source_file); break;
                default: $image = false;
            }
            
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $original_aspect = $width / $height;
                $thumb_aspect = 1;
                
                if ( $original_aspect >= $thumb_aspect ) {
                   $new_height = 250;
                   $new_width = $width / ($height / 250);
                } else {
                   $new_width = 250;
                   $new_height = $height / ($width / 250);
                }
                
                $thumb = imagecreatetruecolor(250, 250);
                imagecopyresampled($thumb, $image, 0 - ($new_width - 250) / 2, 0 - ($new_height - 250) / 2, 0, 0, $new_width, $new_height, $width, $height);
                
                imagejpeg($thumb, $target_file, 90);
                imagedestroy($image);
                imagedestroy($thumb);
                return $filename;
            }
        }
        
        // Fallback if GD is not installed or image creation failed
        if (move_uploaded_file($source_file, $target_file)) {
            return $filename;
        }
    }
    return null;
}

// Handle Actions (Only for MAJELIS_GEREJA and SEKRETARIS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($role, ['MAJELIS_GEREJA', 'SEKRETARIS'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $no_anggota = $_POST['no_anggota'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $email = $_POST['email'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tempat_lahir = $_POST['tempat_lahir'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $alamat_lengkap = $_POST['alamat_lengkap'];
        $no_hp = $_POST['no_hp'];
        $golongan_darah = $_POST['golongan_darah'];
        $pekerjaan = $_POST['pekerjaan'];
        $pendidikan = $_POST['pendidikan'];
        $status_keanggotaan = $_POST['status_keanggotaan'];
        $tahun_masuk = $_POST['tahun_masuk'];
        
        $status_baptis = isset($_POST['status_baptis']) ? (int)$_POST['status_baptis'] : 0;
        $status_sidi = isset($_POST['status_sidi']) ? (int)$_POST['status_sidi'] : 0;
        
        $status_perkawinan = $_POST['status_perkawinan'];
        $tanggal_nikah = ($status_perkawinan === 'MENIKAH' && !empty($_POST['tanggal_nikah'])) ? $_POST['tanggal_nikah'] : null;
        $tempat_nikah = ($status_perkawinan === 'MENIKAH') ? $_POST['tempat_nikah'] : null;
        
        $foto_profil = handleProfileUpload($_FILES['foto_profil']);
        
        // Pass NULL for tanggal_baptis and tanggal_sidi since they are removed from form
        $tanggal_baptis = null;
        $tanggal_sidi = null;
        
        $stmt = $conn->prepare("INSERT INTO jemaat_profiles (no_anggota, nama_lengkap, email, foto_profil, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat_lengkap, no_hp, golongan_darah, pekerjaan, pendidikan, status_keanggotaan, tahun_masuk, status_baptis, tanggal_baptis, status_sidi, tanggal_sidi, status_perkawinan, tanggal_nikah, tempat_nikah) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssssisississ", $no_anggota, $nama_lengkap, $email, $foto_profil, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_keanggotaan, $tahun_masuk, $status_baptis, $tanggal_baptis, $status_sidi, $tanggal_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah);
        
        if ($stmt->execute()) {
            $successMsg = "Data Jemaat berhasil ditambahkan.";
            systemLog('JEMAAT_ADD', $user_id_session, "Added profile: $nama_lengkap");
        } else {
            $errorMsg = "Gagal menambahkan data: " . $conn->error;
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $no_anggota = $_POST['no_anggota'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $email = $_POST['email'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tempat_lahir = $_POST['tempat_lahir'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $alamat_lengkap = $_POST['alamat_lengkap'];
        $no_hp = $_POST['no_hp'];
        $golongan_darah = $_POST['golongan_darah'];
        $pekerjaan = $_POST['pekerjaan'];
        $pendidikan = $_POST['pendidikan'];
        $status_keanggotaan = $_POST['status_keanggotaan'];
        $tahun_masuk = $_POST['tahun_masuk'];
        
        $status_baptis = isset($_POST['status_baptis']) ? (int)$_POST['status_baptis'] : 0;
        $status_sidi = isset($_POST['status_sidi']) ? (int)$_POST['status_sidi'] : 0;
        
        $status_perkawinan = $_POST['status_perkawinan'];
        $tanggal_nikah = ($status_perkawinan === 'MENIKAH' && !empty($_POST['tanggal_nikah'])) ? $_POST['tanggal_nikah'] : null;
        $tempat_nikah = ($status_perkawinan === 'MENIKAH') ? $_POST['tempat_nikah'] : null;
        
        // Pass NULL for tanggal_baptis and tanggal_sidi since they are removed from form
        $tanggal_baptis = null;
        $tanggal_sidi = null;
        
        $foto_profil = handleProfileUpload($_FILES['foto_profil']);
        
        if ($foto_profil) {
            $stmt = $conn->prepare("UPDATE jemaat_profiles SET no_anggota=?, nama_lengkap=?, email=?, foto_profil=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, alamat_lengkap=?, no_hp=?, golongan_darah=?, pekerjaan=?, pendidikan=?, status_keanggotaan=?, tahun_masuk=?, status_baptis=?, tanggal_baptis=?, status_sidi=?, tanggal_sidi=?, status_perkawinan=?, tanggal_nikah=?, tempat_nikah=? WHERE id=?");
            $stmt->bind_param("sssssssssssssisississi", $no_anggota, $nama_lengkap, $email, $foto_profil, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_keanggotaan, $tahun_masuk, $status_baptis, $tanggal_baptis, $status_sidi, $tanggal_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah, $id);
        } else {
            $stmt = $conn->prepare("UPDATE jemaat_profiles SET no_anggota=?, nama_lengkap=?, email=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, alamat_lengkap=?, no_hp=?, golongan_darah=?, pekerjaan=?, pendidikan=?, status_keanggotaan=?, tahun_masuk=?, status_baptis=?, tanggal_baptis=?, status_sidi=?, tanggal_sidi=?, status_perkawinan=?, tanggal_nikah=?, tempat_nikah=? WHERE id=?");
            $stmt->bind_param("sssssssssssisississi", $no_anggota, $nama_lengkap, $email, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_keanggotaan, $tahun_masuk, $status_baptis, $tanggal_baptis, $status_sidi, $tanggal_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah, $id);
        }
        
        if ($stmt->execute()) {
            $successMsg = "Data Jemaat berhasil diperbarui.";
            systemLog('JEMAAT_EDIT', $user_id_session, "Edited profile ID $id");
        } else {
            $errorMsg = "Gagal memperbarui data: " . $conn->error;
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt_get = $conn->prepare("SELECT foto_profil FROM jemaat_profiles WHERE id=?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['foto_profil'] && file_exists(__DIR__ . '/uploads/profiles/' . $row['foto_profil'])) {
                unlink(__DIR__ . '/uploads/profiles/' . $row['foto_profil']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM jemaat_profiles WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $successMsg = "Data Jemaat berhasil dihapus.";
            systemLog('JEMAAT_DELETE', $user_id_session, "Deleted profile ID $id");
        } else {
            $errorMsg = "Gagal menghapus data: " . $conn->error;
        }
    }
}

// Generate next No Anggota AutoIncrement Format: JMT-YYYYMM-XXXX
$current_month_prefix = "JMT-" . date('Ym') . "-";
$query_max = $conn->query("SELECT MAX(CAST(SUBSTRING(no_anggota, 13) AS UNSIGNED)) as max_no FROM jemaat_profiles WHERE no_anggota LIKE '$current_month_prefix%'");
$row_max = $query_max->fetch_assoc();
$next_no = $row_max['max_no'] ? (int)$row_max['max_no'] + 1 : 1;
$next_no_anggota = $current_month_prefix . str_pad($next_no, 4, '0', STR_PAD_LEFT);

// Fetch all profiles
$query = "SELECT p.* FROM jemaat_profiles p ORDER BY p.nama_lengkap ASC";
$result = $conn->query($query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Data Jemaat</h3>
    <?php if (in_array($role, ['MAJELIS_GEREJA', 'SEKRETARIS'])): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJemaatModal">
        <i class="fas fa-plus me-1"></i> Tambah Data Jemaat
    </button>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>

<div class="card shadow">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableJemaat">
                <thead class="table-light">
                    <tr>
                        <th data-sortable="true">No Anggota</th>
                        <th data-sortable="true">Nama Lengkap</th>
                        <th data-sortable="true">Email</th>
                        <th data-sortable="true">No HP</th>
                        <th data-sortable="true">L/P</th>
                        <th data-sortable="true">Status</th>
                        <th data-sortable="true">Tahun Masuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['no_hp']) ?></td>
                            <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                            <td><?= htmlspecialchars($row['status_keanggotaan']) ?></td>
                            <td><?= htmlspecialchars($row['tahun_masuk']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary mb-1" onclick='openViewModal(<?= json_encode($row) ?>)'><i class="bi bi-eye"></i></button>
                                <?php if (in_array($role, ['MAJELIS_GEREJA', 'SEKRETARIS'])): ?>
                                <button class="btn btn-sm btn-info text-white mb-1" onclick='openEditModal(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data profil ini?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada data jemaat.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal View -->
<div class="modal fade" id="viewJemaatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">Detail Profil Jemaat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4" id="viewFotoProfil"></div>
        <div id="viewModalBody"></div>
      </div>
    </div>
  </div>
</div>

<?php if (in_array($role, ['MAJELIS_GEREJA', 'SEKRETARIS'])): ?>
<!-- Modal Add -->
<div class="modal fade" id="addJemaatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Tambah Data Jemaat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">No Anggota <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="no_anggota" value="<?= $next_no_anggota ?>" required readonly>
                  <small class="text-muted">Auto-generated format.</small>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nama_lengkap" required>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Foto Profil (Max 2MB, akan di-crop ke 250x250)</label>
                  <input type="file" class="form-control" name="foto_profil" accept="image/jpeg, image/png, image/gif">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Jenis Kelamin</label>
                  <select class="form-select" name="jenis_kelamin">
                      <option value="L">Laki-laki (L)</option>
                      <option value="P">Perempuan (P)</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tempat Lahir</label>
                  <input type="text" class="form-control" name="tempat_lahir">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tanggal Lahir</label>
                  <input type="date" class="form-control" name="tanggal_lahir">
              </div>
              <div class="col-md-12 mb-3">
                  <label class="form-label">Alamat Lengkap</label>
                  <textarea class="form-control" name="alamat_lengkap" rows="2"></textarea>
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">No HP</label>
                  <input type="text" class="form-control" name="no_hp">
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">Golongan Darah</label>
                  <input type="text" class="form-control" name="golongan_darah">
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">Tahun Masuk</label>
                  <input type="number" class="form-control" name="tahun_masuk" value="<?= date('Y') ?>">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Pekerjaan</label>
                  <input type="text" class="form-control" name="pekerjaan">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Pendidikan</label>
                  <input type="text" class="form-control" name="pendidikan">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Status Keanggotaan</label>
                  <select class="form-select" name="status_keanggotaan">
                      <option value="TETAP">Tetap</option>
                      <option value="TIDAK_TETAP">Tidak Tetap</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Status Perkawinan</label>
                  <select class="form-select" name="status_perkawinan" id="add_status_perkawinan" onchange="togglePernikahan(this, 'add_')">
                      <option value="TIDAK_MENIKAH">Tidak Menikah</option>
                      <option value="MENIKAH">Menikah</option>
                  </select>
              </div>
              
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tanggal Nikah (Jika Menikah)</label>
                  <input type="date" class="form-control" name="tanggal_nikah" id="add_tanggal_nikah" disabled>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tempat Nikah (Jika Menikah)</label>
                  <input type="text" class="form-control" name="tempat_nikah" id="add_tempat_nikah" disabled>
              </div>

              <div class="col-md-6 mb-3">
                  <label class="form-label">Sudah Baptis?</label>
                  <select class="form-select" name="status_baptis">
                      <option value="1">SUDAH BAPTIS</option>
                      <option value="0" selected>BELUM BAPTIS</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Sudah Sidi?</label>
                  <select class="form-select" name="status_sidi">
                      <option value="1">SUDAH SIDI</option>
                      <option value="0" selected>BELUM SIDI</option>
                  </select>
              </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Profil</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editJemaatModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Edit Data Jemaat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">No Anggota <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="no_anggota" id="edit_no_anggota" required readonly>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nama_lengkap" id="edit_nama_lengkap" required>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" id="edit_email">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Foto Profil (Kosongkan jika tidak ingin mengubah)</label>
                  <input type="file" class="form-control" name="foto_profil" accept="image/jpeg, image/png, image/gif">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Jenis Kelamin</label>
                  <select class="form-select" name="jenis_kelamin" id="edit_jenis_kelamin">
                      <option value="L">Laki-laki (L)</option>
                      <option value="P">Perempuan (P)</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tempat Lahir</label>
                  <input type="text" class="form-control" name="tempat_lahir" id="edit_tempat_lahir">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tanggal Lahir</label>
                  <input type="date" class="form-control" name="tanggal_lahir" id="edit_tanggal_lahir">
              </div>
              <div class="col-md-12 mb-3">
                  <label class="form-label">Alamat Lengkap</label>
                  <textarea class="form-control" name="alamat_lengkap" id="edit_alamat_lengkap" rows="2"></textarea>
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">No HP</label>
                  <input type="text" class="form-control" name="no_hp" id="edit_no_hp">
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">Golongan Darah</label>
                  <input type="text" class="form-control" name="golongan_darah" id="edit_golongan_darah">
              </div>
              <div class="col-md-4 mb-3">
                  <label class="form-label">Tahun Masuk</label>
                  <input type="number" class="form-control" name="tahun_masuk" id="edit_tahun_masuk">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Pekerjaan</label>
                  <input type="text" class="form-control" name="pekerjaan" id="edit_pekerjaan">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Pendidikan</label>
                  <input type="text" class="form-control" name="pendidikan" id="edit_pendidikan">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Status Keanggotaan</label>
                  <select class="form-select" name="status_keanggotaan" id="edit_status_keanggotaan">
                      <option value="TETAP">Tetap</option>
                      <option value="TIDAK_TETAP">Tidak Tetap</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Status Perkawinan</label>
                  <select class="form-select" name="status_perkawinan" id="edit_status_perkawinan" onchange="togglePernikahan(this, 'edit_')">
                      <option value="TIDAK_MENIKAH">Tidak Menikah</option>
                      <option value="MENIKAH">Menikah</option>
                  </select>
              </div>
              
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tanggal Nikah</label>
                  <input type="date" class="form-control" name="tanggal_nikah" id="edit_tanggal_nikah" disabled>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Tempat Nikah</label>
                  <input type="text" class="form-control" name="tempat_nikah" id="edit_tempat_nikah" disabled>
              </div>

              <div class="col-md-6 mb-3">
                  <label class="form-label">Sudah Baptis?</label>
                  <select class="form-select" name="status_baptis" id="edit_status_baptis">
                      <option value="1">SUDAH BAPTIS</option>
                      <option value="0">BELUM BAPTIS</option>
                  </select>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Sudah Sidi?</label>
                  <select class="form-select" name="status_sidi" id="edit_status_sidi">
                      <option value="1">SUDAH SIDI</option>
                      <option value="0">BELUM SIDI</option>
                  </select>
              </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info text-white">Update Profil</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/style.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.0/dist/umd/simple-datatables.js"></script>
<script>
function togglePernikahan(selectElement, prefix) {
    const isMenikah = selectElement.value === 'MENIKAH';
    const tgl = document.getElementById(prefix + 'tanggal_nikah');
    const tempat = document.getElementById(prefix + 'tempat_nikah');
    if (tgl) tgl.disabled = !isMenikah;
    if (tempat) tempat.disabled = !isMenikah;
    if (!isMenikah) {
        if(tgl) tgl.value = '';
        if(tempat) tempat.value = '';
    }
}

let editModalInstance;
let viewModalInstance;

function openEditModal(profile) {
    if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editJemaatModal'));
    }
    document.getElementById('edit_id').value = profile.id;
    document.getElementById('edit_no_anggota').value = profile.no_anggota;
    document.getElementById('edit_nama_lengkap').value = profile.nama_lengkap;
    document.getElementById('edit_email').value = profile.email || '';
    document.getElementById('edit_jenis_kelamin').value = profile.jenis_kelamin;
    document.getElementById('edit_tempat_lahir').value = profile.tempat_lahir || '';
    document.getElementById('edit_tanggal_lahir').value = profile.tanggal_lahir || '';
    document.getElementById('edit_alamat_lengkap').value = profile.alamat_lengkap || '';
    document.getElementById('edit_no_hp').value = profile.no_hp || '';
    document.getElementById('edit_golongan_darah').value = profile.golongan_darah || '';
    document.getElementById('edit_tahun_masuk').value = profile.tahun_masuk || '';
    document.getElementById('edit_pekerjaan').value = profile.pekerjaan || '';
    document.getElementById('edit_pendidikan').value = profile.pendidikan || '';
    document.getElementById('edit_status_keanggotaan').value = profile.status_keanggotaan;
    
    document.getElementById('edit_status_perkawinan').value = profile.status_perkawinan;
    togglePernikahan(document.getElementById('edit_status_perkawinan'), 'edit_');
    if (profile.status_perkawinan === 'MENIKAH') {
        document.getElementById('edit_tanggal_nikah').value = profile.tanggal_nikah || '';
        document.getElementById('edit_tempat_nikah').value = profile.tempat_nikah || '';
    }
    
    document.getElementById('edit_status_baptis').value = profile.status_baptis;
    document.getElementById('edit_status_sidi').value = profile.status_sidi;
    
    editModalInstance.show();
}

function openViewModal(profile) {
    if (!viewModalInstance) {
        viewModalInstance = new bootstrap.Modal(document.getElementById('viewJemaatModal'));
    }
    
    let photoHtml = '';
    if (profile.foto_profil) {
        photoHtml = `<img src="uploads/profiles/${profile.foto_profil}" class="rounded-circle shadow-sm" width="150" height="150" style="object-fit: cover; border: 3px solid #dee2e6;">`;
    } else {
        photoHtml = `<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width:150px;height:150px; font-size: 48px; border: 3px solid #dee2e6;">
                        ${profile.nama_lengkap.substring(0, 2).toUpperCase()}
                     </div>`;
    }
    document.getElementById('viewFotoProfil').innerHTML = photoHtml;
    
    let html = `<table class="table table-bordered">
        <tr><th width="35%">No Anggota</th><td>${profile.no_anggota}</td></tr>
        <tr><th>Nama Lengkap</th><td>${profile.nama_lengkap}</td></tr>
        <tr><th>Email</th><td>${profile.email || '-'}</td></tr>
        <tr><th>Jenis Kelamin</th><td>${profile.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'}</td></tr>
        <tr><th>Tempat, Tanggal Lahir</th><td>${profile.tempat_lahir || '-'}, ${profile.tanggal_lahir || '-'}</td></tr>
        <tr><th>Alamat</th><td>${profile.alamat_lengkap || '-'}</td></tr>
        <tr><th>No HP</th><td>${profile.no_hp || '-'}</td></tr>
        <tr><th>Gol. Darah</th><td>${profile.golongan_darah || '-'}</td></tr>
        <tr><th>Pekerjaan</th><td>${profile.pekerjaan || '-'}</td></tr>
        <tr><th>Pendidikan</th><td>${profile.pendidikan || '-'}</td></tr>
        <tr><th>Tahun Masuk</th><td>${profile.tahun_masuk || '-'}</td></tr>
        <tr><th>Keanggotaan</th><td>${profile.status_keanggotaan}</td></tr>
        <tr><th>Status Perkawinan</th><td>${profile.status_perkawinan === 'MENIKAH' ? 'Menikah (' + (profile.tanggal_nikah || '-') + ')' : 'Tidak Menikah'}</td></tr>
        <tr><th>Baptis</th><td>${profile.status_baptis == 1 ? 'SUDAH BAPTIS' : 'BELUM BAPTIS'}</td></tr>
        <tr><th>Sidi</th><td>${profile.status_sidi == 1 ? 'SUDAH SIDI' : 'BELUM SIDI'}</td></tr>
    </table>`;
    document.getElementById('viewModalBody').innerHTML = html;
    viewModalInstance.show();
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("tableJemaat")) {
        new simpleDatatables.DataTable("#tableJemaat", {
            searchable: true,
            sortable: true,
            fixedHeight: false,
            perPage: 10,
            labels: {
                placeholder: "Cari data...",
                perPage: "Data per halaman",
                noRows: "Tidak ada data ditemukan",
                info: "Menampilkan {start} sampai {end} dari {rows} data"
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
