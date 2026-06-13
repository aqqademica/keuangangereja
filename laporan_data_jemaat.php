<?php
// laporan_data_jemaat.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

checkRole(['MAJELIS_GEREJA', 'SEKRETARIS']);

$conn = getDBConnection();
$query = "SELECT * FROM jemaat_profiles ORDER BY nama_lengkap ASC";
$result = $conn->query($query);

$sekretaris_name = $_SESSION['name'];

// Get Majelis Gereja name
$qKetua = $conn->query("SELECT name FROM users WHERE role='MAJELIS_GEREJA' LIMIT 1");
$ketua = $qKetua->fetch_assoc();
$ketua_name = $ketua ? $ketua['name'] : 'MAJELIS GEREJA';

?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Laporan Detail Data Jemaat</h3>
</div>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped align-middle" id="laporanJemaat" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>No Anggota</th>
                        <th>Foto</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>L/P</th>
                        <th>Tempat Lahir</th>
                        <th>Tanggal Lahir</th>
                        <th>Alamat Lengkap</th>
                        <th>No HP</th>
                        <th>Gol. Darah</th>
                        <th>Pekerjaan</th>
                        <th>Pendidikan</th>
                        <th>Keanggotaan</th>
                        <th>Thn Masuk</th>
                        <th>Perkawinan</th>
                        <th>Tgl Nikah</th>
                        <th>Tempat Nikah</th>
                        <th>Baptis</th>
                        <th>Sidi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                            <td>
                                <?php if($row['foto_profil']): ?>
                                    <img src="uploads/profiles/<?= htmlspecialchars($row['foto_profil']) ?>" alt="Foto" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px; font-size: 14px;">
                                        <?= strtoupper(substr($row['nama_lengkap'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                            <td><?= htmlspecialchars($row['tempat_lahir'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['tanggal_lahir'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['alamat_lengkap'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['no_hp'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['golongan_darah'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['pekerjaan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['pendidikan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['status_keanggotaan']) ?></td>
                            <td><?= htmlspecialchars($row['tahun_masuk'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['status_perkawinan']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal_nikah'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['tempat_nikah'] ?? '-') ?></td>
                            <td><?= $row['status_baptis'] == 1 ? 'SUDAH' : 'BELUM' ?></td>
                            <td><?= $row['status_sidi'] == 1 ? 'SUDAH' : 'BELUM' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    
    var headerTitle = 'LAPORAN DATA JEMAAT GEREJA GKKD';
    var headerMessage = 'OLEH <?= strtoupper(htmlspecialchars($sekretaris_name)) ?>\nDIKETAHUI OLEH <?= strtoupper(htmlspecialchars($ketua_name)) ?>';
    
    // Custom print message formatted for HTML
    var printMessage = '<div style="margin-bottom:20px;"><strong>OLEH <?= strtoupper(htmlspecialchars($sekretaris_name)) ?></strong><br><strong>DIKETAHUI OLEH <?= strtoupper(htmlspecialchars($ketua_name)) ?></strong></div>';

    var table = $('#laporanJemaat').DataTable({
        dom: "<'row mb-3'<'col-sm-12 col-md-8'B><'col-sm-12 col-md-4'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'colvis',
                text: '<i class="bi bi-layout-three-columns"></i> Pilih Kolom',
                className: 'btn btn-secondary btn-sm'
            },
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel"></i> Export Excel',
                className: 'btn btn-success btn-sm',
                title: headerTitle,
                messageTop: headerMessage,
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf"></i> Export PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                title: headerTitle,
                messageTop: headerMessage,
                exportOptions: {
                    columns: ':visible' // Only export visible columns
                }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer"></i> Print',
                className: 'btn btn-info btn-sm text-white',
                orientation: 'landscape',
                title: '<h3>' + headerTitle + '</h3>',
                messageTop: printMessage,
                exportOptions: {
                    columns: ':visible'
                }
            }
        ],
        orderCellsTop: true,
        fixedHeader: true,
        scrollX: true,
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
