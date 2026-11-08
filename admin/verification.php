<?php
// admin/verification.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

$search = sanitize($_GET['search'] ?? '');
$method = sanitize($_GET['method'] ?? '');
$result = sanitize($_GET['result'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(r.request_code LIKE :search OR u.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($method)) {
    $where[] = "v.method = :method";
    $params['method'] = $method;
}

if (!empty($result)) {
    $where[] = "v.result = :result";
    $params['result'] = $result;
}

$where_clause = implode(' AND ', $where);

try {
    $sql = "SELECT v.*, r.request_code, u.name as user_name, a.name as admin_name 
            FROM verifications v
            JOIN requests r ON v.request_id = r.id
            JOIN users u ON r.user_id = u.id
            JOIN admins a ON v.admin_id = a.id
            WHERE $where_clause
            ORDER BY v.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $verifications = $stmt->fetchAll();
    
} catch (\PDOException $e) {
    die("Gagal memuat log verifikasi: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Log Verifikasi - DaruratCheck";
require_once '../includes/header.php';
?>

<div class="container-fluid px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-journal-check text-success me-2"></i>Log Verifikasi Sistem</h2>
            <p class="text-secondary small mb-0">Total tercatat: <strong><?php echo count($verifications); ?></strong> proses verifikasi.</p>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-funnel text-success me-2"></i>Filter Log</h6>
            <form action="verification.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Cari Permintaan</label>
                        <input type="text" class="form-control rounded-3" name="search" placeholder="ID Permintaan atau nama pemohon..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Metode</label>
                        <select class="form-select rounded-3" name="method">
                            <option value="">Semua Metode</option>
                            <option value="WhatsApp" <?php echo ($method === 'WhatsApp') ? 'selected' : ''; ?>>WhatsApp</option>
                            <option value="Telepon" <?php echo ($method === 'Telepon') ? 'selected' : ''; ?>>Telepon</option>
                            <option value="Video call" <?php echo ($method === 'Video call') ? 'selected' : ''; ?>>Video call</option>
                            <option value="Pengecekan dokumen" <?php echo ($method === 'Pengecekan dokumen') ? 'selected' : ''; ?>>Pengecekan dokumen</option>
                            <option value="Pengecekan lapangan" <?php echo ($method === 'Pengecekan lapangan') ? 'selected' : ''; ?>>Pengecekan lapangan</option>
                            <option value="Informasi dari relawan" <?php echo ($method === 'Informasi dari relawan') ? 'selected' : ''; ?>>Informasi dari relawan</option>
                            <option value="Lainnya" <?php echo ($method === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Hasil</label>
                        <select class="form-select rounded-3" name="result">
                            <option value="">Semua Hasil</option>
                            <option value="Valid" <?php echo ($result === 'Valid') ? 'selected' : ''; ?>>Valid</option>
                            <option value="Perlu pemeriksaan lanjutan" <?php echo ($result === 'Perlu pemeriksaan lanjutan') ? 'selected' : ''; ?>>Perlu pemeriksaan lanjutan</option>
                            <option value="Tidak valid" <?php echo ($result === 'Tidak valid') ? 'selected' : ''; ?>>Tidak valid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-4">TANGGAL/WAKTU</th>
                            <th>ID PERMINTAAN</th>
                            <th>NAMA PEMOHON</th>
                            <th>METODE VERIFIKASI</th>
                            <th>HASIL</th>
                            <th>CATATAN ADMIN</th>
                            <th class="pe-4">VERIFIKATOR</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($verifications)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-secondary">Belum ada log verifikasi yang tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($verifications as $v): ?>
                                <tr>
                                    <td class="ps-4 text-secondary"><?php echo date('d-m-Y H:i', strtotime($v['created_at'])); ?></td>
                                    <td>
                                        <a href="request-detail.php?id=<?php echo urlencode($v['request_code']); ?>" class="fw-bold text-success text-decoration-none">
                                            <?php echo htmlspecialchars($v['request_code']); ?>
                                        </a>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($v['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['method']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($v['result'] === 'Valid') ? 'bg-success' : (($v['result'] === 'Tidak valid') ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                            <?php echo htmlspecialchars($v['result']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($v['notes']); ?>
                                    </td>
                                    <td class="pe-4 fw-semibold"><?php echo htmlspecialchars($v['admin_name']); ?></td>
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
