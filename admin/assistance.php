<?php
// admin/assistance.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

$search = sanitize($_GET['search'] ?? '');
$method = sanitize($_GET['method'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(r.request_code LIKE :search OR ast.volunteer_name LIKE :search OR ast.aid_type LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($method)) {
    $where[] = "ast.distribution_method = :method";
    $params['method'] = $method;
}

$where_clause = implode(' AND ', $where);

try {
    $sql = "SELECT ast.*, r.request_code, r.status as req_status, u.name as user_name, a.name as admin_name 
            FROM assistance ast
            JOIN requests r ON ast.request_id = r.id
            JOIN users u ON r.user_id = u.id
            JOIN admins a ON ast.admin_id = a.id
            WHERE $where_clause
            ORDER BY ast.distribution_date DESC, ast.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assistance_records = $stmt->fetchAll();
    
    // Calculate total value distributed in this query view
    $total_value = 0;
    foreach ($assistance_records as $rec) {
        $total_value += floatval($rec['value']);
    }
    
} catch (\PDOException $e) {
    die("Gagal memuat log bantuan: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Riwayat Bantuan - DaruratCheck";
require_once '../includes/header.php';
?>

<div class="container-fluid px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-gift-fill text-success me-2"></i>Riwayat Penyaluran Bantuan</h2>
            <p class="text-secondary small mb-0">Total Penyaluran: <strong><?php echo count($assistance_records); ?></strong> kali &bull; Total Nilai: <strong><?php echo format_rupiah($total_value); ?></strong></p>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-funnel text-success me-2"></i>Filter Riwayat</h6>
            <form action="assistance.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Cari Bantuan</label>
                        <input type="text" class="form-control rounded-3" name="search" placeholder="ID Permintaan, nama relawan, jenis bantuan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Metode Penyaluran</label>
                        <select class="form-select rounded-3" name="method">
                            <option value="">Semua Metode</option>
                            <option value="Barang" <?php echo ($method === 'Barang') ? 'selected' : ''; ?>>Barang</option>
                            <option value="Pengantaran" <?php echo ($method === 'Pengantaran') ? 'selected' : ''; ?>>Pengantaran</option>
                            <option value="Pembayaran langsung ke toko" <?php echo ($method === 'Pembayaran langsung ke toko') ? 'selected' : ''; ?>>Pembayaran langsung ke toko</option>
                            <option value="Lainnya" <?php echo ($method === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
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
    
    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-4">TANGGAL SALUR</th>
                            <th>ID PERMINTAAN</th>
                            <th>NAMA PEMOHON</th>
                            <th>JENIS BANTUAN</th>
                            <th>METODE SALUR</th>
                            <th>NILAI BANTUAN</th>
                            <th>RELAWAN PENYALUR</th>
                            <th>BUKTI</th>
                            <th class="pe-4" style="width: 100px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($assistance_records)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-secondary">Belum ada catatan penyaluran bantuan yang cocok.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assistance_records as $ar): ?>
                                <tr>
                                    <td class="ps-4 text-dark fw-medium"><?php echo date('d-m-Y', strtotime($ar['distribution_date'])); ?></td>
                                    <td>
                                        <a href="request-detail.php?id=<?php echo urlencode($ar['request_code']); ?>" class="fw-bold text-success text-decoration-none">
                                            <?php echo htmlspecialchars($ar['request_code']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($ar['user_name']); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($ar['aid_type']); ?></td>
                                    <td><?php echo htmlspecialchars($ar['distribution_method']); ?></td>
                                    <td class="fw-bold text-success"><?php echo format_rupiah($ar['value']); ?></td>
                                    <td><?php echo htmlspecialchars($ar['volunteer_name']); ?></td>
                                    <td>
                                        <?php if ($ar['distribution_proof']): ?>
                                            <a href="<?php echo $base_url . htmlspecialchars($ar['distribution_proof']); ?>" target="_blank" class="btn btn-outline-success btn-xs px-2 py-0.5 rounded-pill" style="font-size: 0.75rem;">
                                                <i class="bi bi-image"></i> Lihat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-secondary small italic">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4">
                                        <a href="request-detail.php?id=<?php echo urlencode($ar['request_code']); ?>" class="btn btn-outline-success btn-sm rounded-pill px-2.5 py-1">
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
