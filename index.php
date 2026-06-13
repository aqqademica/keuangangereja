<?php
// index.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

// Fetch summary data based on role
$role = $_SESSION['role'];

if (in_array($role, ['MAJELIS_GEREJA', 'BENDAHARA'])) {
    // Global summary
    $qMasuk = $conn->query("SELECT SUM(amount) as total FROM uang_masuk WHERE status = 'VERIFIED'");
    $masuk = $qMasuk->fetch_assoc()['total'] ?? 0;
    
    $qKeluar = $conn->query("SELECT SUM(amount) as total FROM uang_keluar");
    $keluar = $qKeluar->fetch_assoc()['total'] ?? 0;
    
    $saldo = $masuk - $keluar;
    ?>
    <div class="row">
        <div class="col-lg-4 col-6">
            <div class="small-box text-bg-primary">
                <div class="inner">
                    <h3><?= formatRupiah($saldo) ?></h3>
                    <p>Saldo Kas Saat Ini</p>
                </div>
                <i class="small-box-icon bi bi-wallet2"></i>
                <a href="uang_masuk.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Lihat Detail <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-4 col-6">
            <div class="small-box text-bg-success">
                <div class="inner">
                    <h3><?= formatRupiah($masuk) ?></h3>
                    <p>Total Uang Masuk</p>
                </div>
                <i class="small-box-icon bi bi-box-arrow-in-right"></i>
                <a href="uang_masuk.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Lihat Detail <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-4 col-6">
            <div class="small-box text-bg-danger">
                <div class="inner">
                    <h3><?= formatRupiah($keluar) ?></h3>
                    <p>Total Uang Keluar</p>
                </div>
                <i class="small-box-icon bi bi-box-arrow-left"></i>
                <a href="uang_keluar.php" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Lihat Detail <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>
    </div>
    
    <?php
    // --- CHART DATA FETCHING ---
    // Chart 1: Penerimaan per Bulan
    $qChart1 = $conn->query("
        SELECT MONTH(date) as month, SUM(amount) as total 
        FROM uang_masuk 
        WHERE status = 'VERIFIED' AND YEAR(date) = YEAR(CURDATE())
        GROUP BY MONTH(date)
        ORDER BY month
    ");
    $chart1_data = array_fill(1, 12, 0);
    while($r = $qChart1->fetch_assoc()) {
        $chart1_data[$r['month']] = (float)$r['total'];
    }

    // Chart 2: Pemasukan per Kategori
    $qChart2 = $conn->query("
        SELECT kategori, SUM(amount) as total 
        FROM uang_masuk 
        WHERE status = 'VERIFIED'
        GROUP BY kategori
    ");
    $chart2_labels = [];
    $chart2_data = [];
    while($r = $qChart2->fetch_assoc()) {
        $chart2_labels[] = $r['kategori'];
        $chart2_data[] = (float)$r['total'];
    }

    // Chart 3: Keaktifan Jemaat
    $qTotalJemaat = $conn->query("SELECT COUNT(id) as total FROM users WHERE role = 'JEMAAT'");
    $totalJemaat = $qTotalJemaat->fetch_assoc()['total'] ?? 0;

    $qActiveJemaat = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM uang_masuk WHERE user_id IS NOT NULL AND status = 'VERIFIED'");
    $activeJemaat = $qActiveJemaat->fetch_assoc()['total'] ?? 0;
    
    $inactiveJemaat = max(0, $totalJemaat - $activeJemaat);
    ?>
    
    <!-- Analytics Section -->
    <h4 class="mt-4 mb-3"><i class="bi bi-graph-up text-primary"></i> Analisis Data Keuangan</h4>
    
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Statistik Penerimaan per Bulan (Tahun Ini)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartPenerimaanBulan" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Penerimaan Berdasarkan Kategori</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartKategori" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Tingkat Partisipasi Jemaat</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartPartisipasi" style="max-height: 250px;"></canvas>
                    <div class="mt-3 text-center small text-muted">
                        Total Jemaat Terdaftar: <strong><?= $totalJemaat ?></strong> orang
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Chart 1: Penerimaan Per Bulan
            const ctx1 = document.getElementById('chartPenerimaanBulan').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: 'Total Penerimaan (Rp)',
                        data: <?= json_encode(array_values($chart1_data)) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });

            // Chart 2: Penerimaan Kategori
            const ctx2 = document.getElementById('chartKategori').getContext('2d');
            new Chart(ctx2, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($chart2_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart2_data) ?>,
                        backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14']
                    }]
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // Chart 3: Partisipasi
            const ctx3 = document.getElementById('chartPartisipasi').getContext('2d');
            new Chart(ctx3, {
                type: 'doughnut',
                data: {
                    labels: ['Aktif Memberi', 'Belum Partisipasi'],
                    datasets: [{
                        data: [<?= $activeJemaat ?>, <?= $inactiveJemaat ?>],
                        backgroundColor: ['#20c997', '#dee2e6'],
                        borderWidth: 0
                    }]
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        });
    </script>
    <?php
} else if ($role === 'SEKRETARIS') {
    // CHART 1: Stats Jemaat y/y (tahun_masuk)
    $qChart1 = $conn->query("SELECT tahun_masuk as year, COUNT(*) as total FROM jemaat_profiles WHERE tahun_masuk IS NOT NULL GROUP BY tahun_masuk ORDER BY tahun_masuk ASC");
    $c1_labels = []; $c1_data = [];
    while($r = $qChart1->fetch_assoc()) {
        $c1_labels[] = $r['year']; $c1_data[] = $r['total'];
    }

    // CHART 2: Stats Jemaat Per Month y/y
    $qChart2 = $conn->query("SELECT MONTH(created_at) as month, COUNT(*) as total FROM users WHERE role='JEMAAT' AND YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY month ASC");
    $c2_data = array_fill(1, 12, 0);
    while($r = $qChart2->fetch_assoc()) {
        $c2_data[$r['month']] = (int)$r['total'];
    }

    // CHART 3: Stats Jemaat Active (30 Days)
    $qChart3 = $conn->query("
        SELECT 
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN last_login < DATE_SUB(NOW(), INTERVAL 30 DAY) OR last_login IS NULL THEN 1 ELSE 0 END) as inactive_count
        FROM users WHERE role='JEMAAT'
    ");
    $r3 = $qChart3->fetch_assoc();
    $active_users = (int)($r3['active_count'] ?? 0);
    $inactive_users = (int)($r3['inactive_count'] ?? 0);

    // CHART 4: Gender
    $qChart4 = $conn->query("SELECT jenis_kelamin, COUNT(*) as total FROM jemaat_profiles WHERE jenis_kelamin IS NOT NULL GROUP BY jenis_kelamin");
    $gender_data = ['L' => 0, 'P' => 0];
    while($r = $qChart4->fetch_assoc()) {
        if (isset($gender_data[$r['jenis_kelamin']])) $gender_data[$r['jenis_kelamin']] = (int)$r['total'];
    }

    // CHART 5: Status Perkawinan
    $qChart5 = $conn->query("SELECT status_perkawinan, COUNT(*) as total FROM jemaat_profiles WHERE status_perkawinan IS NOT NULL GROUP BY status_perkawinan");
    $status_labels = []; $status_data = [];
    while($r = $qChart5->fetch_assoc()) {
        $status_labels[] = $r['status_perkawinan'];
        $status_data[] = (int)$r['total'];
    }
    ?>
    <h4 class="mt-4 mb-3"><i class="bi bi-people-fill text-primary"></i> Dashboard Analisis Jemaat</h4>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Statistik Pertumbuhan Jemaat (Y/Y)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatYY" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Pendaftaran Jemaat Per Bulan (Tahun Ini)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatBulan" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Keaktifan Jemaat (30 Hari)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatAktif" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Berdasarkan Jenis Kelamin</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartGender" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Berdasarkan Status Perkawinan</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartStatusPerkawinan" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Chart 1
            new Chart(document.getElementById('chartJemaatYY').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($c1_labels) ?>,
                    datasets: [{
                        label: 'Total Jemaat Baru',
                        data: <?= json_encode($c1_data) ?>,
                        backgroundColor: '#0d6efd'
                    }]
                }
            });

            // Chart 2
            new Chart(document.getElementById('chartJemaatBulan').getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: 'Pendaftaran Jemaat',
                        data: <?= json_encode(array_values($c2_data)) ?>,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                }
            });

            // Chart 3
            new Chart(document.getElementById('chartJemaatAktif').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Aktif Login (30h)', 'Tidak Aktif'],
                    datasets: [{
                        data: [<?= $active_users ?>, <?= $inactive_users ?>],
                        backgroundColor: ['#0dcaf0', '#dee2e6']
                    }]
                }
            });

            // Chart 4
            new Chart(document.getElementById('chartGender').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Laki-laki', 'Perempuan'],
                    datasets: [{
                        data: [<?= $gender_data['L'] ?>, <?= $gender_data['P'] ?>],
                        backgroundColor: ['#0d6efd', '#d63384']
                    }]
                }
            });

            // Chart 5
            new Chart(document.getElementById('chartStatusPerkawinan').getContext('2d'), {
                type: 'polarArea',
                data: {
                    labels: <?= json_encode($status_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($status_data) ?>,
                        backgroundColor: ['#198754', '#ffc107', '#dc3545', '#6f42c1']
                    }]
                }
            });
        });
    </script>
    <?php
} else if ($role === 'JEMAAT') {
    $userId = $_SESSION['user_id'];
    
    // CHART 1: Penerimaan Gereja m/m
    $qPenerimaanMM = $conn->query("
        SELECT MONTH(date) as m, SUM(amount) as t 
        FROM uang_masuk 
        WHERE status='VERIFIED' AND YEAR(date) = YEAR(CURDATE()) 
        GROUP BY m ORDER BY m
    ");
    $c1_data = array_fill(1, 12, 0);
    while($r = $qPenerimaanMM->fetch_assoc()) $c1_data[$r['m']] = (float)$r['t'];
    
    // CHART 2: Penerimaan Gereja y/y
    $qPenerimaanYY = $conn->query("
        SELECT YEAR(date) as y, SUM(amount) as t 
        FROM uang_masuk 
        WHERE status='VERIFIED' 
        GROUP BY y ORDER BY y DESC LIMIT 5
    ");
    $c2_labels = []; $c2_data = [];
    $c2_rows = [];
    while($r = $qPenerimaanYY->fetch_assoc()) {
        $c2_rows[] = $r;
    }
    $c2_rows = array_reverse($c2_rows);
    foreach($c2_rows as $r) {
        $c2_labels[] = $r['y'];
        $c2_data[] = (float)$r['t'];
    }
    if (empty($c2_labels)) {
        $c2_labels = [date('Y')];
        $c2_data = [0];
    }
    
    // CHART 3: Partisipasi Jemaat
    $qTotalJem = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='JEMAAT'");
    $totJem = $qTotalJem->fetch_assoc()['t'] ?? 0;
    $qActiveJem = $conn->query("SELECT COUNT(DISTINCT user_id) as t FROM uang_masuk WHERE status='VERIFIED' AND user_id IS NOT NULL");
    $actJem = $qActiveJem->fetch_assoc()['t'] ?? 0;
    $inactJem = max(0, $totJem - $actJem);
    
    // CHART 4: Kas m/m
    $c4_data = array_fill(1, 12, 0);
    $qKeluarMM = $conn->query("
        SELECT MONTH(date) as m, SUM(amount) as t 
        FROM uang_keluar 
        WHERE status='VERIFIED' AND YEAR(date) = YEAR(CURDATE()) 
        GROUP BY m
    ");
    while($r = $qKeluarMM->fetch_assoc()) {
        $c4_data[$r['m']] = -((float)$r['t']);
    }
    for($i=1; $i<=12; $i++) {
        $c4_data[$i] += $c1_data[$i];
    }
    
    // CHART 5: Kas y/y
    $kas_yy = [];
    foreach($c2_labels as $y) {
        $kas_yy[$y] = $c2_data[array_search($y, $c2_labels)];
    }
    $qKeluarYY = $conn->query("
        SELECT YEAR(date) as y, SUM(amount) as t 
        FROM uang_keluar 
        WHERE status='VERIFIED' AND YEAR(date) IN (" . implode(',', $c2_labels) . ")
        GROUP BY y
    ");
    while($r = $qKeluarYY->fetch_assoc()) {
        if(isset($kas_yy[$r['y']])) {
            $kas_yy[$r['y']] -= (float)$r['t'];
        }
    }
    $c5_data = array_values($kas_yy);
    
    // TABEL 1: Setoran Saya
    $qSetoranPribadi = $conn->query("SELECT * FROM uang_masuk WHERE user_id = $userId ORDER BY date DESC LIMIT 5");
    
    // TABEL 2: Papan Pengumuman Setoran Terverifikasi
    $qSetoranSemua = $conn->query("
        SELECT u.date, u.amount, u.kategori, usr.name as jemaat_name, u.receipt_token
        FROM uang_masuk u
        LEFT JOIN users usr ON u.user_id = usr.id
        WHERE u.status = 'VERIFIED'
        ORDER BY u.date DESC LIMIT 5
    ");
    ?>
    
    <h4 class="mt-4 mb-3"><i class="bi bi-bar-chart-fill text-primary"></i> Dashboard Transparansi Jemaat</h4>
    
    <div class="row">
        <!-- Tabel Setoran Pribadi -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">1. Setoran Saya Terakhir</h6>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tgl & ID</th>
                                    <th>Kategori</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($qSetoranPribadi->num_rows > 0): ?>
                                    <?php while($row = $qSetoranPribadi->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($row['date']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['receipt_token']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['kategori']) ?></td>
                                        <td class="fw-bold"><?= formatRupiah($row['amount']) ?></td>
                                        <td><?= generateBadgeStatus($row['status']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada data setoran.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabel Papan Pengumuman -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0 border-start border-success border-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-success text-uppercase">2. Setoran Terverifikasi (Papan Pengumuman)</h6>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tgl & ID</th>
                                    <th>Sumbangsih Dari</th>
                                    <th>Kategori</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($qSetoranSemua->num_rows > 0): ?>
                                    <?php while($row = $qSetoranSemua->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($row['date']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['receipt_token']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($row['jemaat_name']): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($row['jemaat_name']) ?></span>
                                            <?php else: ?>
                                                <span class="fst-italic text-secondary">Anonim / Publik</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['kategori']) ?></td>
                                        <td class="fw-bold text-success">+<?= formatRupiah($row['amount']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada pengumuman setoran.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Penerimaan Gereja (M/M)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatPenerimaanMM" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Penerimaan Gereja (Y/Y)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatPenerimaanYY" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Partisipasi Setoran Jemaat</h6>
                </div>
                <div class="card-body text-center">
                    <canvas id="chartJemaatPartisipasi" style="max-height: 220px;"></canvas>
                    <div class="mt-3 small text-muted">Total Jemaat Database: <?= $totJem ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Kas Gereja (M/M)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatKasMM" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 border-0">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="mb-0 fw-bold text-muted text-uppercase">Kas Gereja (Y/Y)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartJemaatKasYY" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Chart 1: Penerimaan M/M
            new Chart(document.getElementById('chartJemaatPenerimaanMM').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: 'Total Penerimaan (Rp)',
                        data: <?= json_encode(array_values($c1_data)) ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)'
                    }]
                }
            });

            // Chart 2: Penerimaan Y/Y
            new Chart(document.getElementById('chartJemaatPenerimaanYY').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($c2_labels) ?>,
                    datasets: [{
                        label: 'Total Penerimaan (Rp)',
                        data: <?= json_encode($c2_data) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)'
                    }]
                }
            });

            // Chart 3: Partisipasi Jemaat
            new Chart(document.getElementById('chartJemaatPartisipasi').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Sudah Setor', 'Belum Setor'],
                    datasets: [{
                        data: [<?= $actJem ?>, <?= $inactJem ?>],
                        backgroundColor: ['#20c997', '#dee2e6']
                    }]
                }
            });

            // Chart 4: Kas M/M
            new Chart(document.getElementById('chartJemaatKasMM').getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: 'Saldo Kas (Rp)',
                        data: <?= json_encode(array_values($c4_data)) ?>,
                        borderColor: '#0dcaf0',
                        backgroundColor: 'rgba(13, 202, 240, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                }
            });

            // Chart 5: Kas Y/Y
            new Chart(document.getElementById('chartJemaatKasYY').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($c2_labels) ?>,
                    datasets: [{
                        label: 'Saldo Kas (Rp)',
                        data: <?= json_encode($c5_data) ?>,
                        borderColor: '#6f42c1',
                        backgroundColor: 'rgba(111, 66, 193, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                }
            });
        });
    </script>
    <?php
}

require_once __DIR__ . '/includes/footer.php';
?>
