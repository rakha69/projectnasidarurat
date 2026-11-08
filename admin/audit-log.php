<?php
// admin/audit-log.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Get total logs count
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    $total_records = $stmtCount->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // 2. Fetch logs with pagination
    $sql = "SELECT al.*, a.name as admin_name, a.username 
            FROM audit_logs al 
            LEFT JOIN admins a ON al.admin_id = a.id 
            ORDER BY al.created_at DESC 
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll();
    
} catch (\PDOException $e) {
    die("Gagal mengambil audit log: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Audit Log Aktivitas - DaruratCheck";
require_once '../includes/header.php';
?>

<div class="container-fluid px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-journal-text text-success me-2"></i>Audit Log Aktivitas</h2>
            <p class="text-secondary small mb-0">Catatan riwayat seluruh aktivitas penting yang terjadi di sistem.</p>
        </div>
    </div>
    
    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-4" style="width: 180px;">WAKTU</th>
                            <th>PELAKU</th>
                            <th>AKTIVITAS</th>
                            <th>ID REFERENSI</th>
                            <th class="pe-4" style="width: 140px;">IP ADDRESS</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-secondary">Belum ada catatan aktivitas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4 text-secondary"><?php echo date('d-m-Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['admin_id']): ?>
                                            <span class="fw-bold text-dark"><i class="bi bi-person-fill text-success me-1"></i><?php echo htmlspecialchars($log['admin_name']); ?></span>
                                            <span class="text-secondary small d-block">@<?php echo htmlspecialchars($log['username']); ?></span>
                                        <?php else: ?>
                                            <span class="text-secondary italic"><i class="bi bi-laptop me-1"></i>Pengunjung / Sistem</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-medium text-dark"><?php echo htmlspecialchars($log['activity']); ?></td>
                                    <td>
                                        <?php if ($log['data_id']): ?>
                                            <span class="badge bg-light text-success border border-success font-monospace"><?php echo htmlspecialchars($log['data_id']); ?></span>
                                        <?php else: ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-secondary font-monospace"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mb-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-start-pill" href="?page=<?php echo $page - 1; ?>">Sebelumnya</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link <?php echo ($page == $i) ? 'bg-success border-success text-white' : 'text-success'; ?>" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-end-pill" href="?page=<?php echo $page + 1; ?>">Selanjutnya</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
