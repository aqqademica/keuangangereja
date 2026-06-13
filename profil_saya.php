<?php
// profil_saya.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['JEMAAT']);

$conn = getDBConnection();
$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Function to handle image upload
if (!function_exists('handleProfileUpload')) {
    function handleProfileUpload($file) {
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . uniqid() . '.jpg';
            $target_file = $upload_dir . $filename;
            
            $source_file = $file['tmp_name'];
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
            if (move_uploaded_file($source_file, $target_file)) {
                return $filename;
            }
        }
        return null;
    }
}

$stmt = $conn->prepare("SELECT * FROM jemaat_profiles WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $email = $_POST['email'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir = empty($_POST['tanggal_lahir']) ? null : $_POST['tanggal_lahir'];
    $alamat_lengkap = $_POST['alamat_lengkap'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $golongan_darah = $_POST['golongan_darah'] ?? '';
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $pendidikan = $_POST['pendidikan'] ?? '';
    
    $status_baptis = isset($_POST['status_baptis']) ? (int)$_POST['status_baptis'] : 0;
    $status_sidi = isset($_POST['status_sidi']) ? (int)$_POST['status_sidi'] : 0;
    
    $status_perkawinan = $_POST['status_perkawinan'] ?? 'TIDAK_MENIKAH';
    $tanggal_nikah = ($status_perkawinan === 'MENIKAH' && !empty($_POST['tanggal_nikah'])) ? $_POST['tanggal_nikah'] : null;
    $tempat_nikah = ($status_perkawinan === 'MENIKAH') ? $_POST['tempat_nikah'] : null;
    
    $foto_profil = handleProfileUpload($_FILES['foto_profil']);
    
    if ($profile) {
        if ($foto_profil) {
            $stmt = $conn->prepare("UPDATE jemaat_profiles SET nama_lengkap=?, email=?, foto_profil=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, alamat_lengkap=?, no_hp=?, golongan_darah=?, pekerjaan=?, pendidikan=?, status_baptis=?, status_sidi=?, status_perkawinan=?, tanggal_nikah=?, tempat_nikah=? WHERE user_id=?");
            $stmt->bind_param("sssssssssssiisssi", $nama_lengkap, $email, $foto_profil, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_baptis, $status_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE jemaat_profiles SET nama_lengkap=?, email=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, alamat_lengkap=?, no_hp=?, golongan_darah=?, pekerjaan=?, pendidikan=?, status_baptis=?, status_sidi=?, status_perkawinan=?, tanggal_nikah=?, tempat_nikah=? WHERE user_id=?");
            $stmt->bind_param("ssssssssssiisssi", $nama_lengkap, $email, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_baptis, $status_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah, $userId);
        }
    } else {
        $no_anggota = 'JMT-' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
        $tahun_masuk = date('Y');
        $stmt = $conn->prepare("INSERT INTO jemaat_profiles (user_id, no_anggota, nama_lengkap, email, foto_profil, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat_lengkap, no_hp, golongan_darah, pekerjaan, pendidikan, status_baptis, status_sidi, status_perkawinan, tanggal_nikah, tempat_nikah, tahun_masuk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssssssssiissss", $userId, $no_anggota, $nama_lengkap, $email, $foto_profil, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $alamat_lengkap, $no_hp, $golongan_darah, $pekerjaan, $pendidikan, $status_baptis, $status_sidi, $status_perkawinan, $tanggal_nikah, $tempat_nikah, $tahun_masuk);
    }
    
    if ($stmt->execute()) {
        systemLog('UPDATE_PROFILE', $userId, "User updated their profile data");
        $successMsg = 'Profil berhasil disimpan.';
        $stmt = $conn->prepare("SELECT * FROM jemaat_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
    } else {
        $errorMsg = 'Gagal menyimpan profil: ' . $conn->error;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h4 class="mb-0">Profil Saya</h4>
            </div>
            <div class="card-body">
                <?php if ($successMsg): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
                <?php if ($errorMsg): ?><div class="alert alert-danger"><?= $errorMsg ?></div><?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfInput() ?>
                    <div class="text-center mb-4">
                        <?php if(!empty($profile['foto_profil'])): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($profile['foto_profil']) ?>" class="rounded-circle shadow-sm" width="150" height="150" style="object-fit: cover; border: 3px solid #dee2e6;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width:150px;height:150px; font-size: 48px; border: 3px solid #dee2e6;">
                                <?= strtoupper(substr($profile['nama_lengkap'] ?? $_SESSION['name'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <label class="form-label d-block text-muted">Ubah Foto Profil (Opsional, Max 2MB, dipotong ke 250x250)</label>
                            <input type="file" class="form-control w-50 mx-auto" name="foto_profil" accept="image/jpeg, image/png, image/gif">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">No Anggota</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($profile['no_anggota'] ?? 'Belum Digenerate') ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($profile['nama_lengkap'] ?? $_SESSION['name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select class="form-select" name="jenis_kelamin">
                                <option value="L" <?= ($profile['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($profile['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" name="tempat_lahir" value="<?= htmlspecialchars($profile['tempat_lahir'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" name="tanggal_lahir" value="<?= htmlspecialchars($profile['tanggal_lahir'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat_lengkap" rows="2"><?= htmlspecialchars($profile['alamat_lengkap'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" name="no_hp" value="<?= htmlspecialchars($profile['no_hp'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Golongan Darah</label>
                            <input type="text" class="form-control" name="golongan_darah" value="<?= htmlspecialchars($profile['golongan_darah'] ?? '') ?>" placeholder="A/B/AB/O">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tahun Masuk</label>
                            <input type="number" class="form-control" name="tahun_masuk" value="<?= htmlspecialchars($profile['tahun_masuk'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pekerjaan</label>
                            <input type="text" class="form-control" name="pekerjaan" value="<?= htmlspecialchars($profile['pekerjaan'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pendidikan Terakhir</label>
                            <input type="text" class="form-control" name="pendidikan" value="<?= htmlspecialchars($profile['pendidikan'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">Informasi Gerejawi</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sudah Baptis?</label>
                            <select class="form-select" name="status_baptis">
                                <option value="1" <?= ($profile['status_baptis'] ?? 0) == 1 ? 'selected' : '' ?>>SUDAH BAPTIS</option>
                                <option value="0" <?= ($profile['status_baptis'] ?? 0) == 0 ? 'selected' : '' ?>>BELUM BAPTIS</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sudah Sidi?</label>
                            <select class="form-select" name="status_sidi">
                                <option value="1" <?= ($profile['status_sidi'] ?? 0) == 1 ? 'selected' : '' ?>>SUDAH SIDI</option>
                                <option value="0" <?= ($profile['status_sidi'] ?? 0) == 0 ? 'selected' : '' ?>>BELUM SIDI</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Perkawinan</label>
                            <select class="form-select" name="status_perkawinan" id="profil_status_perkawinan" onchange="togglePernikahan(this)">
                                <option value="TIDAK_MENIKAH" <?= ($profile['status_perkawinan'] ?? '') == 'TIDAK_MENIKAH' ? 'selected' : '' ?>>Tidak Menikah</option>
                                <option value="MENIKAH" <?= ($profile['status_perkawinan'] ?? '') == 'MENIKAH' ? 'selected' : '' ?>>Menikah</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Nikah</label>
                            <input type="date" class="form-control" name="tanggal_nikah" id="profil_tanggal_nikah" value="<?= htmlspecialchars($profile['tanggal_nikah'] ?? '') ?>" <?= ($profile['status_perkawinan'] ?? '') !== 'MENIKAH' ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tempat Nikah</label>
                            <input type="text" class="form-control" name="tempat_nikah" id="profil_tempat_nikah" value="<?= htmlspecialchars($profile['tempat_nikah'] ?? '') ?>" <?= ($profile['status_perkawinan'] ?? '') !== 'MENIKAH' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Simpan Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePernikahan(selectElement) {
    const isMenikah = selectElement.value === 'MENIKAH';
    const tgl = document.getElementById('profil_tanggal_nikah');
    const tempat = document.getElementById('profil_tempat_nikah');
    if (tgl) tgl.disabled = !isMenikah;
    if (tempat) tempat.disabled = !isMenikah;
    if (!isMenikah) {
        if(tgl) tgl.value = '';
        if(tempat) tempat.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
