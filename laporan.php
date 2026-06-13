<?php
// laporan.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'BENDAHARA']);

$conn = getDBConnection();
$successMsg = '';
$errorMsg = '';
$role = $_SESSION['role'];

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Handle Actions (Submit by Bendahara, Verify by Ketua)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token validation failed.");
    }
    $action = $_POST['action'];
    $laporan_id = $_POST['laporan_id'] ?? null;
    
    if ($action === 'submit' && $role === 'BENDAHARA') {
        // Calculate totals for the selected month/year
        $stmtM = $conn->prepare("SELECT SUM(amount) as total FROM uang_masuk WHERE status = 'VERIFIED' AND (? = 0 OR MONTH(date) = ?) AND (? = 0 OR YEAR(date) = ?)");
        $stmtM->bind_param("iiii", $bulan, $bulan, $tahun, $tahun);
        $stmtM->execute();
        $total_masuk = $stmtM->get_result()->fetch_assoc()['total'] ?? 0;
        
        $stmtK = $conn->prepare("SELECT SUM(amount) as total FROM uang_keluar WHERE (? = 0 OR MONTH(date) = ?) AND (? = 0 OR YEAR(date) = ?)");
        $stmtK->bind_param("iiii", $bulan, $bulan, $tahun, $tahun);
        $stmtK->execute();
        $total_keluar = $stmtK->get_result()->fetch_assoc()['total'] ?? 0;
        
        $saldo_akhir = $total_masuk - $total_keluar;
        $creator = $_SESSION['user_id'];
        
        // Insert or Update Laporan
        $stmt = $conn->prepare("INSERT INTO laporan_keuangan (periode_bulan, periode_tahun, total_masuk, total_keluar, saldo_akhir, status, created_by) VALUES (?, ?, ?, ?, ?, 'SUBMITTED', ?) ON DUPLICATE KEY UPDATE total_masuk=VALUES(total_masuk), total_keluar=VALUES(total_keluar), saldo_akhir=VALUES(saldo_akhir), status='SUBMITTED'");
        $stmt->bind_param("iidddi", $bulan, $tahun, $total_masuk, $total_keluar, $saldo_akhir, $creator);
        
        if ($stmt->execute()) {
            systemLog('LAPORAN_SUBMIT', $creator, "Bendahara submitted report for $bulan/$tahun");
            $successMsg = "Laporan berhasil di-submit ke Majelis Gereja.";
        }
    } else if ($action === 'verify' && $role === 'MAJELIS_GEREJA' && $laporan_id) {
        $verifier = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE laporan_keuangan SET status = 'VERIFIED', verified_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $verifier, $laporan_id);
        
        if ($stmt->execute()) {
            systemLog('LAPORAN_VERIFY', $verifier, "Ketua verified report ID $laporan_id");
            $successMsg = "Laporan berhasil diverifikasi.";
        }
    } else if ($action === 'reject' && $role === 'MAJELIS_GEREJA' && $laporan_id) {
        $verifier = $_SESSION['user_id'];
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = $conn->prepare("UPDATE laporan_keuangan SET status = 'REJECTED', verified_by = ?, rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("isi", $verifier, $reason, $laporan_id);
        
        if ($stmt->execute()) {
            systemLog('LAPORAN_REJECT', $verifier, "Ketua rejected report ID $laporan_id");
            $successMsg = "Laporan berhasil ditolak.";
        }
    }
}

// Fetch Current Laporan Status
$stmtL = $conn->prepare("SELECT * FROM laporan_keuangan WHERE periode_bulan = ? AND periode_tahun = ? LIMIT 1");
$stmtL->bind_param("ii", $bulan, $tahun);
$stmtL->execute();
$laporan = $stmtL->get_result()->fetch_assoc();

// Fetch Data Details for the selected period
$qMasuk = $conn->prepare("SELECT * FROM uang_masuk WHERE status = 'VERIFIED' AND (? = 0 OR MONTH(date) = ?) AND (? = 0 OR YEAR(date) = ?) ORDER BY date ASC");
$qMasuk->bind_param("iiii", $bulan, $bulan, $tahun, $tahun);
$qMasuk->execute();
$dataMasuk = $qMasuk->get_result();

$qKeluar = $conn->prepare("SELECT * FROM uang_keluar WHERE (? = 0 OR MONTH(date) = ?) AND (? = 0 OR YEAR(date) = ?) ORDER BY date ASC");
$qKeluar->bind_param("iiii", $bulan, $bulan, $tahun, $tahun);
$qKeluar->execute();
$dataKeluar = $qKeluar->get_result();

$tMasuk = 0; $tKeluar = 0;

$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function getIndonesianDate($dateStr) {
    global $hariIndo, $bulanIndo;
    $time = strtotime($dateStr);
    return $hariIndo[date('w', $time)] . ', ' . date('d', $time) . ' ' . $bulanIndo[date('n', $time)] . ' ' . date('Y', $time);
}

$bendaharaName = '(Nama Lengkap bendahara)';
if ($laporan && $laporan['created_by']) {
    $q = $conn->query("SELECT name FROM users WHERE id = " . $laporan['created_by']);
    if ($q && $r = $q->fetch_assoc()) $bendaharaName = $r['name'];
} else {
    $q = $conn->query("SELECT name FROM users WHERE role = 'BENDAHARA' LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) $bendaharaName = $r['name'];
}

$ketuaName = '(Nama Lengkap ketua)';
if ($laporan && $laporan['verified_by']) {
    $q = $conn->query("SELECT name FROM users WHERE id = " . $laporan['verified_by']);
    if ($q && $r = $q->fetch_assoc()) $ketuaName = $r['name'];
} else {
    $q = $conn->query("SELECT name FROM users WHERE role = 'MAJELIS_GEREJA' LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) $ketuaName = $r['name'];
}

$tanggalDibuat = $laporan ? getIndonesianDate($laporan['created_at']) : getIndonesianDate(date('Y-m-d'));
$tanggalDisetujui = ($laporan && $laporan['status'] == 'VERIFIED' && isset($laporan['updated_at'])) ? getIndonesianDate($laporan['updated_at']) : getIndonesianDate(date('Y-m-d'));
?>

<div class="d-print-none d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Laporan Keuangan</h3>
    <div>
        <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#exportOptionsModal" onclick="setExportAction('print')"><i class="fas fa-print me-1"></i> Print Laporan</button>
        <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#exportOptionsModal" onclick="setExportAction('pdf')"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportOptionsModal" onclick="setExportAction('excel')"><i class="bi bi-file-earmark-excel"></i> Unduh Excel</button>
    </div>
</div>

<?php if ($successMsg): ?><div class="alert alert-success d-print-none"><?= $successMsg ?></div><?php endif; ?>

<div class="card shadow mb-4 d-print-none">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label class="col-form-label">Bulan:</label>
            </div>
            <div class="col-auto">
                <select name="bulan" class="form-select">
                    <option value="0" <?= $bulan === 0 ? 'selected' : '' ?>>Semua Bulan</option>
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?= $i ?>" <?= $bulan === $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 10)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="col-form-label">Tahun:</label>
            </div>
            <div class="col-auto">
                <select name="tahun" class="form-select">
                    <option value="0" <?= $tahun === 0 ? 'selected' : '' ?>>Semua Tahun</option>
                    <?php 
                    $currentYear = date('Y');
                    for($i = $currentYear; $i >= 2020; $i--): ?>
                        <option value="<?= $i ?>" <?= $tahun === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="col-form-label">Tampilan:</label>
            </div>
            <div class="col-auto">
                <select name="jenis_rincian" class="form-select" id="filter_rincian" onchange="toggleRincianView()">
                    <option value="semua">Pemasukan & Pengeluaran</option>
                    <option value="pemasukan">Rincian Pemasukan Saja</option>
                    <option value="pengeluaran">Rincian Pengeluaran Saja</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div id="printArea">
    <div class="text-center mb-4">
        <h2>LAPORAN KEUANGAN GEREJA</h2>
        <h4>Periode: <?= $bulan === 0 ? 'Semua Bulan' : date('F', mktime(0, 0, 0, $bulan, 10)) ?> <?= $tahun === 0 ? 'Semua Tahun' : $tahun ?></h4>
        <hr>
    </div>
    
    <div class="mb-3">
        <h5>Status Laporan: 
            <?php 
            if (!$laporan) echo '<span class="badge bg-secondary">Belum Dibuat / Draft</span>';
            else echo generateBadgeStatus($laporan['status']);
            ?>
        </h5>
        <?php if ($laporan && $laporan['status'] === 'REJECTED' && $laporan['rejection_reason']): ?>
            <div class="alert alert-danger mt-2">
                <strong>Alasan Penolakan Ketua:</strong><br>
                <?= nl2br(htmlspecialchars($laporan['rejection_reason'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4" id="cardPemasukan">
            <div class="card">
                <div class="card-header bg-success text-white">Rincian Pemasukan</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr><th>Tanggal</th><th>Kategori</th><th class="text-end">Nominal</th></tr>
                        </thead>
                        <tbody>
                            <?php while($m = $dataMasuk->fetch_assoc()): $tMasuk += $m['amount']; ?>
                            <tr>
                                <td><?= $m['date'] ?></td>
                                <td><?= $m['kategori'] ?></td>
                                <td class="text-end"><?= formatRupiah($m['amount']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-success fw-bold">
                                <td colspan="2">TOTAL PEMASUKAN</td>
                                <td class="text-end"><?= formatRupiah($tMasuk) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4" id="cardPengeluaran">
            <div class="card">
                <div class="card-header bg-danger text-white">Rincian Pengeluaran</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr><th>Tanggal</th><th>Kategori</th><th class="text-end">Nominal</th></tr>
                        </thead>
                        <tbody>
                            <?php while($k = $dataKeluar->fetch_assoc()): $tKeluar += $k['amount']; ?>
                            <tr>
                                <td><?= $k['date'] ?></td>
                                <td><?= $k['kategori_utama'] ?> - <?= $k['sub_kategori'] ?></td>
                                <td class="text-end"><?= formatRupiah($k['amount']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-danger fw-bold">
                                <td colspan="2">TOTAL PENGELUARAN</td>
                                <td class="text-end"><?= formatRupiah($tKeluar) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4 shadow border-0 rounded-4" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-center p-4">
            <div class="d-flex align-items-center mb-3 mb-md-0">
                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 55px; height: 55px; background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                    <i class="bi bi-wallet2 fs-3 text-white"></i>
                </div>
                <div>
                    <h6 class="mb-1 text-white-50 text-uppercase fw-bold" style="letter-spacing: 1px;">Total Keseluruhan</h6>
                    <h4 class="mb-0 fw-semibold">Saldo Akhir Periode Ini</h4>
                </div>
            </div>
            <h2 class="mb-0 fw-bold text-end" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3); font-size: 2.2rem;"><?= formatRupiah($tMasuk - $tKeluar) ?></h2>
        </div>
    </div>
    
    <div class="row mt-5 text-center">
        <div class="col-6">
            <p><?= $tanggalDibuat ?></p>
            <br>
            <p>Dibuat Oleh,</p>
            <br><br><br>
            <p><strong>Bendahara</strong><br>(<?= htmlspecialchars($bendaharaName) ?>)</p>
        </div>
        <div class="col-6">
            <p><?= $tanggalDisetujui ?></p>
            <br>
            <p>Majelis Gereja</p>
            <br><br><br>
            <p><strong>Majelis Gereja</strong><br>(<?= htmlspecialchars($ketuaName) ?>)</p>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="d-print-none mt-4 text-center">
    <?php if ($role === 'BENDAHARA' && (!$laporan || $laporan['status'] === 'DRAFT' || $laporan['status'] === 'SUBMITTED')): ?>
        <form method="POST" action="">
            <?= csrfInput() ?>
            <button type="submit" name="action" value="submit" class="btn btn-lg btn-success">Submit Rekap ke Ketua</button>
        </form>
    <?php endif; ?>
    
    <?php if ($role === 'MAJELIS_GEREJA' && $laporan && $laporan['status'] === 'SUBMITTED'): ?>
        <form method="POST" action="" class="d-inline">
            <?= csrfInput() ?>
            <input type="hidden" name="laporan_id" value="<?= $laporan['id'] ?>">
            <button type="submit" name="action" value="verify" class="btn btn-lg btn-primary">Verifikasi Laporan Ini</button>
        </form>
        <button type="button" class="btn btn-lg btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#rejectModal">Tolak Laporan</button>
    <?php endif; ?>
</div>

<!-- Modal Reject -->
<?php if ($role === 'MAJELIS_GEREJA' && $laporan && $laporan['status'] === 'SUBMITTED'): ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <?= csrfInput() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="laporan_id" value="<?= $laporan['id'] ?>">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Tolak Laporan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Keterangan Penolakan (Wajib)</label>
            <textarea class="form-control" name="rejection_reason" rows="3" required placeholder="Berikan alasan mengapa laporan ini ditolak agar Bendahara bisa merevisi..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Tolak Laporan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal Export Options -->
<div class="modal fade d-print-none" id="exportOptionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">Pilih Rincian Laporan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Silahkan pilih rincian apa saja yang ingin disertakan dalam dokumen (Print/PDF/Excel):</p>
        <select class="form-select" id="exportDetailType">
            <option value="semua">Semua Rincian (Pemasukan & Pengeluaran)</option>
            <option value="pemasukan">View Rincian Pemasukan Saja</option>
            <option value="pengeluaran">View Rincian Pengeluaran Saja</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" onclick="executeExport()">Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
    .d-print-none { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-success, .table-danger { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
    body { background-color: white; }
}
</style>

<!-- Scripts for PDF & Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let currentExportAction = 'print';

function setExportAction(action) {
    currentExportAction = action;
}

function executeExport() {
    var type = document.getElementById('exportDetailType').value;
    var cardMasuk = document.getElementById('cardPemasukan');
    var cardKeluar = document.getElementById('cardPengeluaran');
    
    // Save original display styles
    var originalMasukDisplay = cardMasuk.style.display;
    var originalKeluarDisplay = cardKeluar.style.display;
    
    // Apply visibility
    if (type === 'pemasukan') {
        cardMasuk.style.display = 'block';
        cardKeluar.style.display = 'none';
        cardMasuk.classList.remove('col-md-6');
        cardMasuk.classList.add('col-md-12');
    } else if (type === 'pengeluaran') {
        cardMasuk.style.display = 'none';
        cardKeluar.style.display = 'block';
        cardKeluar.classList.remove('col-md-6');
        cardKeluar.classList.add('col-md-12');
    } else {
        cardMasuk.style.display = 'block';
        cardKeluar.style.display = 'block';
        cardMasuk.classList.remove('col-md-12');
        cardMasuk.classList.add('col-md-6');
        cardKeluar.classList.remove('col-md-12');
        cardKeluar.classList.add('col-md-6');
    }
    
    // Close Modal
    var modalEl = document.getElementById('exportOptionsModal');
    var modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
    
    // Slight delay to allow DOM updates and modal fade out
    setTimeout(() => {
        if (currentExportAction === 'print') {
            window.print();
            restoreLayout(type, cardMasuk, cardKeluar, originalMasukDisplay, originalKeluarDisplay);
        } else if (currentExportAction === 'pdf') {
            exportToPDF(type, cardMasuk, cardKeluar, originalMasukDisplay, originalKeluarDisplay);
        } else if (currentExportAction === 'excel') {
            exportToExcel(type);
            restoreLayout(type, cardMasuk, cardKeluar, originalMasukDisplay, originalKeluarDisplay);
        }
    }, 500);
}

function restoreLayout(type, cardMasuk, cardKeluar, origM, origK) {
    if (type === 'pemasukan') {
        cardMasuk.classList.remove('col-md-12');
        cardMasuk.classList.add('col-md-6');
    } else if (type === 'pengeluaran') {
        cardKeluar.classList.remove('col-md-12');
        cardKeluar.classList.add('col-md-6');
    }
    toggleRincianView(); // Restore based on main page filter
}

function exportToPDF(type, cardMasuk, cardKeluar, origM, origK) {
    var element = document.getElementById('printArea');
    var opt = {
      margin:       0.5,
      filename:     'Laporan_Keuangan_<?= $bulan ?>_<?= $tahun ?>.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save().then(() => {
        restoreLayout(type, cardMasuk, cardKeluar, origM, origK);
    });
}

function exportToExcel(type) {
    var wb = XLSX.utils.book_new();
    wb.Props = {
        Title: "Laporan Keuangan Gereja",
        Author: "Manajemen Keuangan GKKD"
    };
    
    var tables = document.querySelectorAll('#printArea table');
    if (tables.length > 0 && (type === 'semua' || type === 'pemasukan')) {
        var wsMasuk = XLSX.utils.table_to_sheet(tables[0]);
        XLSX.utils.book_append_sheet(wb, wsMasuk, "Pemasukan");
    }
    if (tables.length > 1 && (type === 'semua' || type === 'pengeluaran')) {
        var wsKeluar = XLSX.utils.table_to_sheet(tables[1]);
        XLSX.utils.book_append_sheet(wb, wsKeluar, "Pengeluaran");
    }
    
    XLSX.writeFile(wb, 'Laporan_Keuangan_<?= $bulan ?>_<?= $tahun ?>.xlsx');
}

// Handle initial view toggle
function toggleRincianView() {
    var type = document.getElementById('filter_rincian').value;
    var cardMasuk = document.getElementById('cardPemasukan');
    var cardKeluar = document.getElementById('cardPengeluaran');
    
    if (type === 'pemasukan') {
        cardMasuk.style.display = 'block';
        cardKeluar.style.display = 'none';
        cardMasuk.classList.remove('col-md-6');
        cardMasuk.classList.add('col-md-12');
    } else if (type === 'pengeluaran') {
        cardMasuk.style.display = 'none';
        cardKeluar.style.display = 'block';
        cardKeluar.classList.remove('col-md-6');
        cardKeluar.classList.add('col-md-12');
    } else {
        cardMasuk.style.display = 'block';
        cardKeluar.style.display = 'block';
        cardMasuk.classList.remove('col-md-12');
        cardMasuk.classList.add('col-md-6');
        cardKeluar.classList.remove('col-md-12');
        cardKeluar.classList.add('col-md-6');
    }
}

document.addEventListener('DOMContentLoaded', toggleRincianView);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
