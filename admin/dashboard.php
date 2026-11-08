<?php
// admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin is logged in
require_admin_login();

// Fetch status statistics
$stats = [
    'TOTAL' => 0,
    'BARU' => 0,
    'SEDANG DIVERIFIKASI' => 0,
    'TERVERIFIKASI' => 0,
    'DISETUJUI' => 0,
    'BANTUAN DISALURKAN' => 0,
    'SELESAI' => 0,
    'DITOLAK' => 0
];

try {
    // Get total requests count
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM requests");
    $stats['TOTAL'] = $stmtTotal->fetchColumn();
    
    // Get counts grouped by status
    $stmtGroup = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
    while ($row = $stmtGroup->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
    // Fetch 10 most recent requests
    $sqlRecent = "SELECT r.*, u.name, u.location, u.whatsapp 
                  FROM requests r 
                  JOIN users u ON r.user_id = u.id 
                  ORDER BY r.created_at DESC 
                  LIMIT 10";
    $stmtRecent = $pdo->query($sqlRecent);
    $recentRequests = $stmtRecent->fetchAll();
    
} catch (\PDOException $e) {
    die("Gagal memuat data dashboard: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Dashboard Admin - DaruratCheck";
require_once '../includes/header.php';
?>

<div class="container-fluid px-md-4">
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Selamat Datang, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h2>
            <p class="text-secondary small mb-0">Berikut adalah ikhtisar terkini pengelolaan bantuan Nasi Darurat.</p>
        </div>
        <div class="text-muted small">
            <i class="bi bi-clock-history me-1"></i> Terakhir login: <?php echo date('d-m-Y H:i'); ?>
        </div>
    </div>
    
    <!-- Stats Cards Grid -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-blue">
                <span class="small d-block opacity-75">TOTAL PERMINTAAN</span>
                <span class="display-5 fw-bold"><?php echo $stats['TOTAL']; ?></span>
                <i class="bi bi-folder-fill fs-2 float-end opacity-25 mt-2"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-yellow">
                <span class="small d-block opacity-75">PERMINTAAN BARU</span>
                <span class="display-5 fw-bold"><?php echo $stats['BARU']; ?></span>
                <i class="bi bi-bell-fill fs-2 float-end opacity-25 mt-2"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-dark">
                <span class="small d-block opacity-75">MENUNGGU VERIFIKASI</span>
                <span class="display-5 fw-bold"><?php echo $stats['SEDANG DIVERIFIKASI']; ?></span>
                <i class="bi bi-hourglass-split fs-2 float-end opacity-25 mt-2"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-green">
                <span class="small d-block opacity-75">BANTUAN SELESAI</span>
                <span class="display-5 fw-bold"><?php echo $stats['SELESAI']; ?></span>
                <i class="bi bi-check-circle-fill fs-2 float-end opacity-25 mt-2"></i>
            </div>
        </div>
    </div>
    
    <!-- Secondary Status Counts Row -->
    <div class="bg-white rounded-4 p-4 shadow-sm border border-light mb-5">
        <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-success me-2"></i>Detail Status Permintaan</h5>
        <div class="row text-center g-3">
            <div class="col-4 col-md-2 border-end border-light">
                <span class="text-secondary small d-block">Terverifikasi</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $stats['TERVERIFIKASI']; ?></span>
            </div>
            <div class="col-4 col-md-2 border-end border-light">
                <span class="text-secondary small d-block">Disetujui</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $stats['DISETUJUI']; ?></span>
            </div>
            <div class="col-4 col-md-2 border-end border-light">
                <span class="text-secondary small d-block">Disalurkan</span>
                <span class="fs-4 fw-bold text-indigo"><?php echo $stats['BANTUAN DISALURKAN']; ?></span>
            </div>
            <div class="col-4 col-md-2 border-end border-light">
                <span class="text-secondary small d-block">Ditolak</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $stats['DITOLAK']; ?></span>
            </div>
            <div class="col-4 col-md-2">
                <span class="text-secondary small d-block">Lainnya</span>
                <span class="fs-4 fw-bold text-dark">0</span>
            </div>
        </div>
    </div>

    <!-- Recent Requests Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-list-stars me-2 text-success"></i>10 Permintaan Bantuan Terkini</h5>
            <a href="requests.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Lihat Semua</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-4">ID PERMINTAAN</th>
                            <th>NAMA PEMOHON</th>
                            <th>LOKASI</th>
                            <th>KEBUTUHAN</th>
                            <th>TANGGAL</th>
                            <th>STATUS</th>
                            <th class="text-center">RISK SCORE</th>
                            <th class="text-center pe-4" style="width: 120px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($recentRequests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-secondary">Belum ada pengajuan bantuan yang masuk.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRequests as $req): 
                                $risk_info = calculate_risk_score($req);
                            ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-success"><?php echo htmlspecialchars($req['request_code']); ?></td>
                                    <td><?php echo htmlspecialchars($req['name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['location']); ?></td>
                                    <td><?php echo htmlspecialchars($req['need_type']); ?> <span class="text-muted">(<?php echo intval($req['people_count']); ?> orang)</span></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_status_badge($req['status']); ?> px-2.5 py-1.5 rounded-pill text-uppercase">
                                            <?php echo htmlspecialchars($req['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo get_risk_badge($risk_info['category']); ?> px-2.5 py-1.5 rounded-pill">
                                            <?php echo $risk_info['score']; ?>/100 (<?php echo $risk_info['category']; ?>)
                                        </span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <a href="request-detail.php?id=<?php echo urlencode($req['request_code']); ?>" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
