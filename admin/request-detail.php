<?php
// admin/request-detail.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

$request_code = sanitize($_GET['id'] ?? '');
$success_msg = '';
$error_msg = '';

if (empty($request_code)) {
    header("Location: requests.php");
    exit;
}

try {
    // 1. Fetch Request Details
    $sql = "SELECT r.*, u.name as user_name, u.whatsapp, u.location 
            FROM requests r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.request_code = :request_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['request_code' => $request_code]);
    $request = $stmt->fetch();
    
    if (!$request) {
        die("Permintaan tidak ditemukan.");
    }
    
    $request_id = $request['id'];
    
    // --------------------------------------------------------
    // POST Handle: Update Status directly
    // --------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
        // Validate CSRF
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception("CSRF Token tidak valid.");
        }
        
        $new_status = sanitize($_POST['new_status']);
        $allowed_statuses = ['BARU', 'SEDANG DIVERIFIKASI', 'TERVERIFIKASI', 'DISETUJUI', 'BANTUAN DISALURKAN', 'SELESAI', 'DITOLAK'];
        
        if (in_array($new_status, $allowed_statuses)) {
            $stmtUpdate = $pdo->prepare("UPDATE requests SET status = :status WHERE id = :id");
            $stmtUpdate->execute(['status' => $new_status, 'id' => $request_id]);
            
            log_activity($pdo, $_SESSION['admin_id'], "Mengubah status permintaan $request_code menjadi $new_status", $request_code);
            $success_msg = "Status permintaan berhasil diperbarui menjadi $new_status.";
            
            // Reload request details
            $stmt->execute(['request_code' => $request_code]);
            $request = $stmt->fetch();
        } else {
            $error_msg = "Status tidak valid.";
        }
    }
    
    // --------------------------------------------------------
    // POST Handle: Update Risk Indicators
    // --------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_risk_indicators') {
        // Validate CSRF
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception("CSRF Token tidak valid.");
        }
        
        $indicators = [
            'safe_new_wa' => isset($_POST['safe_new_wa']) ? 1 : 0,
            'safe_consistent_location' => isset($_POST['safe_consistent_location']) ? 1 : 0,
            'safe_comm_verified' => isset($_POST['safe_comm_verified']) ? 1 : 0,
            'safe_valid_evidence' => isset($_POST['safe_valid_evidence']) ? 1 : 0,
            'safe_no_suspicious_hist' => isset($_POST['safe_no_suspicious_hist']) ? 1 : 0,
            'risk_multi_request' => isset($_POST['risk_multi_request']) ? 1 : 0,
            'risk_data_changes' => isset($_POST['risk_data_changes']) ? 1 : 0,
            'risk_inconsistent_location' => isset($_POST['risk_inconsistent_location']) ? 1 : 0,
            'risk_refuse_verification' => isset($_POST['risk_refuse_verification']) ? 1 : 0,
            'risk_repeated_pattern' => isset($_POST['risk_repeated_pattern']) ? 1 : 0
        ];
        
        // Recalculate score
        $risk_calc = calculate_risk_score($indicators);
        $new_score = $risk_calc['score'];
        
        // Save to DB
        $sqlUpdateIndicators = "UPDATE requests SET 
            safe_new_wa = :safe_new_wa,
            safe_consistent_location = :safe_consistent_location,
            safe_comm_verified = :safe_comm_verified,
            safe_valid_evidence = :safe_valid_evidence,
            safe_no_suspicious_hist = :safe_no_suspicious_hist,
            risk_multi_request = :risk_multi_request,
            risk_data_changes = :risk_data_changes,
            risk_inconsistent_location = :risk_inconsistent_location,
            risk_refuse_verification = :risk_refuse_verification,
            risk_repeated_pattern = :risk_repeated_pattern,
            risk_score = :risk_score
            WHERE id = :id";
            
        $stmtUpdateInd = $pdo->prepare($sqlUpdateIndicators);
        $indicators['risk_score'] = $new_score;
        $indicators['id'] = $request_id;
        $stmtUpdateInd->execute($indicators);
        
        log_activity($pdo, $_SESSION['admin_id'], "Memperbarui indikator risiko permintaan $request_code (Skor: $new_score)", $request_code);
        $success_msg = "Indikator risiko dan skor berhasil diperbarui.";
        
        // Reload details
        $stmt->execute(['request_code' => $request_code]);
        $request = $stmt->fetch();
    }
    
    // --------------------------------------------------------
    // POST Handle: Add Verification Record
    // --------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_verification') {
        // Validate CSRF
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception("CSRF Token tidak valid.");
        }
        
        $method = sanitize($_POST['method'] ?? '');
        $result = sanitize($_POST['result'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($method) || empty($result) || empty($notes)) {
            $error_msg = "Semua field verifikasi harus diisi.";
        } else {
            // Insert verification record
            $sqlInsertVer = "INSERT INTO verifications (request_id, admin_id, method, result, notes) VALUES (:request_id, :admin_id, :method, :result, :notes)";
            $stmtInsertVer = $pdo->prepare($sqlInsertVer);
            $stmtInsertVer->execute([
                'request_id' => $request_id,
                'admin_id' => $_SESSION['admin_id'],
                'method' => $method,
                'result' => $result,
                'notes' => $notes
            ]);
            
            // Auto update status based on verification results
            $new_status = $request['status'];
            if ($result === 'Valid') {
                $new_status = 'TERVERIFIKASI';
            } elseif ($result === 'Tidak valid') {
                $new_status = 'DITOLAK';
            } else {
                $new_status = 'SEDANG DIVERIFIKASI';
            }
            
            $stmtUpdateStatus = $pdo->prepare("UPDATE requests SET status = :status WHERE id = :id");
            $stmtUpdateStatus->execute(['status' => $new_status, 'id' => $request_id]);
            
            log_activity($pdo, $_SESSION['admin_id'], "Membuat verifikasi permintaan $request_code (Hasil: $result, Status: $new_status)", $request_code);
            $success_msg = "Catatan verifikasi berhasil disimpan. Status diubah menjadi $new_status.";
            
            // Reload details
            $stmt->execute(['request_code' => $request_code]);
            $request = $stmt->fetch();
        }
    }
    
    // --------------------------------------------------------
    // POST Handle: Add Assistance Record
    // --------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_assistance') {
        // Validate CSRF
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception("CSRF Token tidak valid.");
        }
        
        $aid_type = sanitize($_POST['aid_type'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $value = floatval($_POST['value'] ?? 0);
        $distribution_date = sanitize($_POST['distribution_date'] ?? '');
        $distribution_method = sanitize($_POST['distribution_method'] ?? '');
        $volunteer_name = sanitize($_POST['volunteer_name'] ?? '');
        
        if (empty($aid_type) || empty($distribution_date) || empty($distribution_method) || empty($volunteer_name)) {
            $error_msg = "Field bertanda bintang pada form bantuan wajib diisi.";
        } else {
            // Handle file upload for proof
            $distribution_proof = null;
            if (isset($_FILES['distribution_proof']) && $_FILES['distribution_proof']['error'] === UPLOAD_ERR_OK) {
                // target path relative to base is uploads/
                // since we are in admin/ folder, uploads is in ../uploads/
                $upload_result = upload_file($_FILES['distribution_proof'], '../uploads/');
                if ($upload_result === false) {
                    throw new Exception("Upload bukti penyaluran gagal.");
                }
                $distribution_proof = $upload_result;
            }
            
            $sqlInsertAid = "INSERT INTO assistance 
                (request_id, admin_id, aid_type, description, value, distribution_date, distribution_method, volunteer_name, distribution_proof) 
                VALUES 
                (:request_id, :admin_id, :aid_type, :description, :value, :distribution_date, :distribution_method, :volunteer_name, :distribution_proof)";
            
            $stmtInsertAid = $pdo->prepare($sqlInsertAid);
            $stmtInsertAid->execute([
                'request_id' => $request_id,
                'admin_id' => $_SESSION['admin_id'],
                'aid_type' => $aid_type,
                'description' => $description,
                'value' => $value,
                'distribution_date' => $distribution_date,
                'distribution_method' => $distribution_method,
                'volunteer_name' => $volunteer_name,
                'distribution_proof' => $distribution_proof
            ]);
            
            // Set status to SELESAI
            $stmtUpdateStatus = $pdo->prepare("UPDATE requests SET status = 'SELESAI' WHERE id = :id");
            $stmtUpdateStatus->execute(['id' => $request_id]);
            
            log_activity($pdo, $_SESSION['admin_id'], "Mencatat penyaluran bantuan $aid_type untuk $request_code", $request_code);
            $success_msg = "Penyaluran bantuan berhasil dicatat. Status permintaan diubah menjadi SELESAI.";
            
            // Reload details
            $stmt->execute(['request_code' => $request_code]);
            $request = $stmt->fetch();
        }
    }
    
    // --------------------------------------------------------
    // Fetch Histories
    // --------------------------------------------------------
    // Fetch Verifications
    $stmtVerList = $pdo->prepare("SELECT v.*, a.name as admin_name 
                                  FROM verifications v 
                                  JOIN admins a ON v.admin_id = a.id 
                                  WHERE v.request_id = :request_id 
                                  ORDER BY v.created_at DESC");
    $stmtVerList->execute(['request_id' => $request_id]);
    $verifications = $stmtVerList->fetchAll();
    
    // Fetch Assistance
    $stmtAidList = $pdo->prepare("SELECT ast.*, a.name as admin_name 
                                  FROM assistance ast 
                                  JOIN admins a ON ast.admin_id = a.id 
                                  WHERE ast.request_id = :request_id 
                                  ORDER BY ast.created_at DESC");
    $stmtAidList->execute(['request_id' => $request_id]);
    $assistance_records = $stmtAidList->fetchAll();
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

$base_url = '../';
$page_title = "Detail Permintaan " . htmlspecialchars($request_code) . " - DaruratCheck";
require_once '../includes/header.php';
$csrf_token = generate_csrf_token();
$risk_info = calculate_risk_score($request);
?>

<div class="container-fluid px-md-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" class="text-success text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="requests.php" class="text-success text-decoration-none">Permintaan</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($request_code); ?></li>
        </ol>
    </nav>
    
    <!-- Title -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">ID: <?php echo htmlspecialchars($request_code); ?></h2>
            <span class="text-secondary small">Daftar Pengajuan Masuk &bull; <?php echo format_date_id($request['created_at']); ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge <?php echo get_status_badge($request['status']); ?> fs-6 px-3 py-2 rounded-pill text-uppercase">
                STATUS: <?php echo htmlspecialchars($request['status']); ?>
            </span>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i>
            <div><?php echo $success_msg; ?></div>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
            <div><?php echo $error_msg; ?></div>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        
        <!-- Left Column: Request Details & Action Forms -->
        <div class="col-lg-7">
            <!-- Basic info card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="bi bi-file-earmark-person text-success me-2"></i>Informasi Pemohon & Kebutuhan</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">Nama Lengkap</label>
                            <span class="fw-semibold text-dark fs-5"><?php echo htmlspecialchars($request['user_name']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">WhatsApp</label>
                            <span class="fw-semibold text-dark fs-5">
                                <a href="https://wa.me/<?php echo htmlspecialchars($request['whatsapp']); ?>" target="_blank" class="text-success text-decoration-none">
                                    <i class="bi bi-whatsapp me-1"></i><?php echo htmlspecialchars($request['whatsapp']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">Lokasi Domisili</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($request['location']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">Jenis Kebutuhan</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($request['need_type']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">Jumlah Orang</label>
                            <span class="fw-semibold text-dark"><?php echo intval($request['people_count']); ?> orang</span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-secondary small d-block">Berkas Lampiran</label>
                            <?php if ($request['supporting_document']): ?>
                                <a href="<?php echo $base_url . htmlspecialchars($request['supporting_document']); ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill py-1 mt-1">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Lihat Berkas
                                </a>
                            <?php else: ?>
                                <span class="text-secondary small italic">Tidak melampirkan berkas.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="text-secondary small d-block mb-1">Deskripsi Masalah/Kebutuhan</label>
                        <div class="p-3 bg-light rounded-3 text-dark small border border-light">
                            <?php echo nl2br(htmlspecialchars($request['description'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick status action buttons -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-lightning-fill text-success me-2"></i>Ubah Status Cepat</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="SEDANG DIVERIFIKASI">
                            <button type="submit" class="btn btn-warning btn-sm rounded-pill px-3 py-2 text-dark <?php echo ($request['status'] === 'SEDANG DIVERIFIKASI') ? 'disabled' : ''; ?>">
                                <i class="bi bi-hourglass-split me-1"></i> Mulai Verifikasi
                            </button>
                        </form>
                        
                        <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="DISETUJUI">
                            <button type="submit" class="btn btn-secondary btn-sm rounded-pill px-3 py-2 text-white <?php echo ($request['status'] === 'DISETUJUI') ? 'disabled' : ''; ?>">
                                <i class="bi bi-check-circle me-1"></i> Setujui Bantuan
                            </button>
                        </form>
                        
                        <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="new_status" value="DITOLAK">
                            <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3 py-2 <?php echo ($request['status'] === 'DITOLAK') ? 'disabled' : ''; ?>">
                                <i class="bi bi-x-circle me-1"></i> Tolak Bantuan
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 py-2" data-bs-toggle="collapse" data-bs-target="#verificationFormCollapse">
                            <i class="bi bi-pencil-square me-1"></i> Tambah Catatan Verifikasi
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Collapse Verification Form -->
            <div class="collapse mb-4" id="verificationFormCollapse">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-text text-success me-2"></i>Catat Hasil Verifikasi Baru</h5>
                        <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add_verification">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Metode Verifikasi</label>
                                    <select class="form-select" name="method" required>
                                        <option value="WhatsApp" selected>WhatsApp</option>
                                        <option value="Telepon">Telepon</option>
                                        <option value="Video call">Video call</option>
                                        <option value="Pengecekan dokumen">Pengecekan dokumen</option>
                                        <option value="Pengecekan lapangan">Pengecekan lapangan</option>
                                        <option value="Informasi dari relawan">Informasi dari relawan</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Hasil Verifikasi</label>
                                    <select class="form-select" name="result" required>
                                        <option value="Valid">Valid (Terverifikasi)</option>
                                        <option value="Perlu pemeriksaan lanjutan">Perlu pemeriksaan lanjutan</option>
                                        <option value="Tidak valid">Tidak valid / Palsu</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Catatan Verifikasi</label>
                                <textarea class="form-control" name="notes" rows="3" required placeholder="Contoh: Pemohon dapat menunjukkan foto KK asli dan kondisi dapur lewat video call."></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#verificationFormCollapse">Batal</button>
                                <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Simpan Verifikasi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Assistance form: only active if status allows (DISETUJUI, BANTUAN DISALURKAN, SELESAI) -->
            <?php if (in_array($request['status'], ['DISETUJUI', 'BANTUAN DISALURKAN', 'SELESAI'])): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 border border-success border-1">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-gift-fill text-success me-2"></i>Catat Penyaluran Bantuan</h5>
                        <p class="text-secondary small">Gunakan form ini untuk mencatat jenis bantuan yang dikirimkan, nama relawan penyalur, dan berkas foto dokumentasi penyerahan bantuan.</p>
                        
                        <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add_assistance">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Jenis Bantuan *</label>
                                    <input type="text" class="form-control" name="aid_type" required placeholder="Contoh: Paket Sembako Premium" value="Sembako">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nilai Bantuan (Rupiah)</label>
                                    <input type="number" class="form-control" name="value" placeholder="Contoh: 150000" min="0" value="150000">
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Tanggal Penyaluran *</label>
                                    <input type="date" class="form-control" name="distribution_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Metode Penyaluran *</label>
                                    <select class="form-select" name="distribution_method" required>
                                        <option value="Barang" selected>Barang (Diserahkan fisik)</option>
                                        <option value="Pengantaran">Pengantaran (Kurir/Relawan)</option>
                                        <option value="Pembayaran langsung ke toko">Pembayaran langsung ke toko</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nama Relawan Penyalur *</label>
                                    <input type="text" class="form-control" name="volunteer_name" required placeholder="Contoh: Budi (Relawan Tangsel)" value="<?php echo htmlspecialchars($_SESSION['admin_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Foto/Bukti Penyaluran (Maks 2MB)</label>
                                    <input type="file" class="form-control" name="distribution_proof" accept="image/jpeg,image/png,application/pdf">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Keterangan Tambahan</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Detail deskripsi barang bantuan atau catatan tambahan..."></textarea>
                            </div>
                            
                            <div class="alert alert-info py-2 px-3 small border-0 bg-light text-secondary mb-3">
                                <i class="bi bi-info-circle-fill text-success me-1"></i>
                                **Hindari transfer uang tunai langsung ke rekening penerima** jika bantuan dapat diberikan dalam bentuk barang kebutuhan atau pembayaran langsung ke penyedia kebutuhan.
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success rounded-pill py-2.5">
                                    <i class="bi bi-check-circle-fill me-1"></i> Catat Bantuan & Selesaikan Permintaan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column: Risk Assessment & Logs -->
        <div class="col-lg-5">
            <!-- Risk score assessment box -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-shield-exclamation text-success me-2"></i>Penilaian Indikator Risiko</h5>
                    
                    <!-- Score Meter -->
                    <div class="risk-box <?php echo strtolower($risk_info['category'] === 'Rendah' ? 'low' : ($risk_info['category'] === 'Tinggi' ? 'high' : 'medium')); ?> mb-4">
                        <span class="small d-block fw-semibold opacity-75">RISK SCORE INDICATOR</span>
                        <span class="risk-number"><?php echo $risk_info['score']; ?>/100</span>
                        <span class="d-block fw-bold fs-5 mt-1">Kategori: <?php echo $risk_info['category']; ?></span>
                    </div>
                    
                    <!-- Checklist Form -->
                    <form action="request-detail.php?id=<?php echo urlencode($request_code); ?>" method="POST" class="small">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_risk_indicators">
                        
                        <h6 class="fw-bold text-secondary mb-2">Indikator Konsistensi / Kesesuaian (Trust Factors -20% tiap item)</h6>
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="safe_new_wa" id="safe_new_wa" <?php echo $request['safe_new_wa'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="safe_new_wa">WhatsApp tidak dilaporkan bermasalah / bersih</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="safe_consistent_location" id="safe_consistent_location" <?php echo $request['safe_consistent_location'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="safe_consistent_location">Lokasi konsisten dengan data fisik/KK/KTP</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="safe_comm_verified" id="safe_comm_verified" <?php echo $request['safe_comm_verified'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="safe_comm_verified">Verifikasi komunikasi sukses (respon kooperatif)</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="safe_valid_evidence" id="safe_valid_evidence" <?php echo $request['safe_valid_evidence'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="safe_valid_evidence">Bukti foto / dokumen kebutuhan valid</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="safe_no_suspicious_hist" id="safe_no_suspicious_hist" <?php echo $request['safe_no_suspicious_hist'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="safe_no_suspicious_hist">Tidak ada riwayat pengajuan mencurigakan</label>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold text-secondary mb-2">Indikator Risiko Pengajuan (Risk Factors +20% tiap item)</h6>
                        <div class="mb-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="risk_multi_request" id="risk_multi_request" <?php echo $request['risk_multi_request'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="risk_multi_request">WhatsApp dipakai berkali-kali dalam waktu dekat</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="risk_data_changes" id="risk_data_changes" <?php echo $request['risk_data_changes'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="risk_data_changes">Terdapat banyak perubahan data pemohon</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="risk_inconsistent_location" id="risk_inconsistent_location" <?php echo $request['risk_inconsistent_location'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="risk_inconsistent_location">Informasi lokasi tidak konsisten</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="risk_refuse_verification" id="risk_refuse_verification" <?php echo $request['risk_refuse_verification'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="risk_refuse_verification">Pemohon menolak seluruh proses verifikasi</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="risk_repeated_pattern" id="risk_repeated_pattern" <?php echo $request['risk_repeated_pattern'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="risk_repeated_pattern">Terdapat pola pengajuan berulang (indikasi spam)</label>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-outline-success btn-sm rounded-pill py-2">
                                <i class="bi bi-arrow-repeat me-1"></i> Update Indikator & Hitung Ulang
                            </button>
                        </div>
                        
                        <div class="alert alert-secondary py-2 border-0 bg-light small mb-0">
                            <i class="bi bi-info-circle me-1"></i> **Pernyataan:** Risk Score hanya rekomendasi sistem berdasarkan kepatuhan data. Relawan tetap mengambil keputusan verifikasi akhir secara manual.
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- History Lists -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-journal-check text-success me-2"></i>Riwayat Verifikasi</h5>
                    <?php if (empty($verifications)): ?>
                        <p class="text-secondary small italic mb-0">Belum ada catatan verifikasi untuk permintaan ini.</p>
                    <?php else: ?>
                        <div class="small">
                            <?php foreach ($verifications as $v): ?>
                                <div class="border-bottom border-light pb-2 mb-2">
                                    <div class="d-flex justify-content-between text-secondary mb-1" style="font-size: 0.75rem;">
                                        <span><?php echo date('d-m-Y H:i', strtotime($v['created_at'])); ?> &bull; Oleh: <?php echo htmlspecialchars($v['admin_name']); ?></span>
                                        <span class="badge <?php echo ($v['result'] === 'Valid') ? 'bg-success' : (($v['result'] === 'Tidak valid') ? 'bg-danger' : 'bg-warning text-dark'); ?>"><?php echo htmlspecialchars($v['result']); ?></span>
                                    </div>
                                    <div class="fw-semibold mb-1">Metode: <?php echo htmlspecialchars($v['method']); ?></div>
                                    <div class="text-muted italic">"<?php echo htmlspecialchars($v['notes']); ?>"</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-gift text-success me-2"></i>Catatan Penyaluran Bantuan</h5>
                    <?php if (empty($assistance_records)): ?>
                        <p class="text-secondary small italic mb-0">Belum ada penyaluran bantuan yang tercatat.</p>
                    <?php else: ?>
                        <div class="small">
                            <?php foreach ($assistance_records as $ar): ?>
                                <div class="border-bottom border-light pb-2 mb-2">
                                    <div class="d-flex justify-content-between text-secondary mb-1" style="font-size: 0.75rem;">
                                        <span><?php echo date('d-m-Y', strtotime($ar['distribution_date'])); ?> &bull; Oleh: <?php echo htmlspecialchars($ar['admin_name']); ?></span>
                                        <span class="fw-bold text-success"><?php echo format_rupiah($ar['value']); ?></span>
                                    </div>
                                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars($ar['aid_type']); ?> (Metode: <?php echo htmlspecialchars($ar['distribution_method']); ?>)</div>
                                    <div class="text-muted mb-1">Penyalur: <?php echo htmlspecialchars($ar['volunteer_name']); ?></div>
                                    <?php if ($ar['description']): ?>
                                        <div class="text-muted italic mb-1">"<?php echo htmlspecialchars($ar['description']); ?>"</div>
                                    <?php endif; ?>
                                    <?php if ($ar['distribution_proof']): ?>
                                        <a href="<?php echo $base_url . htmlspecialchars($ar['distribution_proof']); ?>" target="_blank" class="btn btn-outline-success btn-xs px-2 py-0.5 mt-1" style="font-size: 0.7rem;">
                                            <i class="bi bi-image me-1"></i>Lihat Bukti Foto
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
