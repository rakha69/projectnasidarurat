<?php
// admin/requests.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

// Retrieve filters
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$need_type = sanitize($_GET['need_type'] ?? '');
$date_from = sanitize($_GET['date_from'] ?? '');
$date_to = sanitize($_GET['date_to'] ?? '');

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Construct query filters
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(r.request_code LIKE :search OR u.name LIKE :search OR u.whatsapp LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($status)) {
    $where[] = "r.status = :status";
    $params['status'] = $status;
}

if (!empty($need_type)) {
    $where[] = "r.need_type = :need_type";
    $params['need_type'] = $need_type;
}

if (!empty($date_from)) {
    $where[] = "DATE(r.created_at) >= :date_from";
    $params['date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(r.created_at) <= :date_to";
    $params['date_to'] = $date_to;
}

$where_clause = implode(' AND ', $where);

try {
    // 1. Count matching requests
    $sqlCount = "SELECT COUNT(*) FROM requests r JOIN users u ON r.user_id = u.id WHERE $where_clause";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_records = $stmtCount->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // 2. Fetch requests
    $sqlFetch = "SELECT r.*, u.name, u.location, u.whatsapp 
                 FROM requests r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE $where_clause 
                 ORDER BY r.created_at DESC 
                 LIMIT $limit OFFSET $offset";
    
    $stmtFetch = $pdo->prepare($sqlFetch);
    $stmtFetch->execute($params);
    $requests = $stmtFetch->fetchAll();
    
} catch (\PDOException $e) {
    die("Gagal mengambil data permintaan: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Daftar Permintaan - DaruratCheck";
require_once '../includes/header.php';
?>

<div class="container-fluid px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-medical text-success me-2"></i>Kelola Permintaan Bantuan</h2>
            <p class="text-secondary small mb-0">Total ditemukan: <strong><?php echo $total_records; ?></strong> permintaan.</p>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-funnel text-success me-2"></i>Filter Pencarian</h6>
            
            <form action="requests.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Cari Kata Kunci</label>
                        <input type="text" class="form-control rounded-3" name="search" placeholder="ID, Nama, atau No WA..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-secondary">Status</label>
                        <select class="form-select rounded-3" name="status">
                            <option value="">Semua Status</option>
                            <option value="BARU" <?php echo ($status === 'BARU') ? 'selected' : ''; ?>>BARU</option>
                            <option value="SEDANG DIVERIFIKASI" <?php echo ($status === 'SEDANG DIVERIFIKASI') ? 'selected' : ''; ?>>SEDANG DIVERIFIKASI</option>
                            <option value="TERVERIFIKASI" <?php echo ($status === 'TERVERIFIKASI') ? 'selected' : ''; ?>>TERVERIFIKASI</option>
                            <option value="DISETUJUI" <?php echo ($status === 'DISETUJUI') ? 'selected' : ''; ?>>DISETUJUI</option>
                            <option value="BANTUAN DISALURKAN" <?php echo ($status === 'BANTUAN DISALURKAN') ? 'selected' : ''; ?>>BANTUAN DISALURKAN</option>
                            <option value="SELESAI" <?php echo ($status === 'SELESAI') ? 'selected' : ''; ?>>SELESAI</option>
                            <option value="DITOLAK" <?php echo ($status === 'DITOLAK') ? 'selected' : ''; ?>>DITOLAK</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-secondary">Jenis Kebutuhan</label>
                        <select class="form-select rounded-3" name="need_type">
                            <option value="">Semua Kebutuhan</option>
                            <option value="Makanan" <?php echo ($need_type === 'Makanan') ? 'selected' : ''; ?>>Makanan</option>
                            <option value="Sembako" <?php echo ($need_type === 'Sembako') ? 'selected' : ''; ?>>Sembako</option>
                            <option value="Susu atau kebutuhan bayi" <?php echo ($need_type === 'Susu atau kebutuhan bayi') ? 'selected' : ''; ?>>Susu / Bayi</option>
                            <option value="Kebutuhan lansia" <?php echo ($need_type === 'Kebutuhan lansia') ? 'selected' : ''; ?>>Lansia</option>
                            <option value="Kebutuhan kesehatan" <?php echo ($need_type === 'Kebutuhan kesehatan') ? 'selected' : ''; ?>>Kesehatan</option>
                            <option value="Kebutuhan pendidikan" <?php echo ($need_type === 'Kebutuhan pendidikan') ? 'selected' : ''; ?>>Pendidikan</option>
                            <option value="Lainnya" <?php echo ($need_type === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-semibold text-secondary">Dari Tanggal</label>
                        <input type="date" class="form-control rounded-3" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-semibold text-secondary">Sampai Tanggal</label>
                        <input type="date" class="form-control rounded-3" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2">
                            <i class="bi bi-search"></i>
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
                            <th class="ps-4">ID PERMINTAAN</th>
                            <th>NAMA PEMOHON</th>
                            <th>WHATSAPP</th>
                            <th>LOKASI</th>
                            <th>KEBUTUHAN</th>
                            <th>TANGGAL</th>
                            <th>STATUS</th>
                            <th class="text-center">RISK SCORE</th>
                            <th class="text-center pe-4" style="width: 120px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-secondary">Tidak menemukan data permintaan bantuan yang cocok.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): 
                                $risk_info = calculate_risk_score($req);
                            ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-success"><?php echo htmlspecialchars($req['request_code']); ?></td>
                                    <td><?php echo htmlspecialchars($req['name']); ?></td>
                                    <td>
                                        <a href="https://wa.me/<?php echo htmlspecialchars($req['whatsapp']); ?>" target="_blank" class="text-decoration-none text-dark">
                                            <i class="bi bi-whatsapp text-success me-1"></i><?php echo htmlspecialchars($req['whatsapp']); ?>
                                        </a>
                                    </td>
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
                                            <?php echo $risk_info['score']; ?>/100
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
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mb-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-start-pill" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&need_type=<?php echo urlencode($need_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Sebelumnya</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link <?php echo ($page == $i) ? 'bg-success border-success text-white' : 'text-success'; ?>" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&need_type=<?php echo urlencode($need_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-end-pill" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&need_type=<?php echo urlencode($need_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Selanjutnya</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
