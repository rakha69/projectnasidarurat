<?php
// bantuan.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$success = false;
$error = '';
$generated_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $name = sanitize($_POST['name'] ?? '');
    $whatsapp_raw = sanitize($_POST['whatsapp'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $need_type = sanitize($_POST['need_type'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $people_count = intval($_POST['people_count'] ?? 1);
    
    // Validate required fields
    if (empty($name) || empty($whatsapp_raw) || empty($location) || empty($need_type) || empty($description) || $people_count <= 0) {
        $error = 'Semua field wajib diisi dengan benar.';
    } else {
        // Validate WA number
        $whatsapp = validate_whatsapp($whatsapp_raw);
        if (!$whatsapp) {
            $error = 'Format nomor WhatsApp tidak valid. Gunakan format angka saja (contoh: 081234567890).';
        } else {
            try {
                $pdo->beginTransaction();
                
                // 1. Check if user already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE whatsapp = :whatsapp");
                $stmt->execute(['whatsapp' => $whatsapp]);
                $user = $stmt->fetch();
                
                $is_new_user = false;
                if ($user) {
                    $user_id = $user['id'];
                    // Update user name/location to latest
                    $stmtUpdate = $pdo->prepare("UPDATE users SET name = :name, location = :location WHERE id = :id");
                    $stmtUpdate->execute(['name' => $name, 'location' => $location, 'id' => $user_id]);
                } else {
                    // Create new user
                    $is_new_user = true;
                    $stmtInsert = $pdo->prepare("INSERT INTO users (whatsapp, name, location) VALUES (:whatsapp, :name, :location)");
                    $stmtInsert->execute(['whatsapp' => $whatsapp, 'name' => $name, 'location' => $location]);
                    $user_id = $pdo->lastInsertId();
                }
                
                // 2. Risk Indicators assessment based on history
                $safe_new_wa = $is_new_user ? 1 : 0;
                
                // Check for prior rejected requests with this user_id
                $safe_no_suspicious_hist = 1;
                if (!$is_new_user) {
                    $stmtSuspicious = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = :user_id AND status = 'DITOLAK'");
                    $stmtSuspicious->execute(['user_id' => $user_id]);
                    if ($stmtSuspicious->fetchColumn() > 0) {
                        $safe_no_suspicious_hist = 0;
                    }
                }
                
                // Check if user has submitted another request in the last 24 hours (risk_multi_request)
                $risk_multi_request = 0;
                if (!$is_new_user) {
                    $stmtRecent = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = :user_id AND created_at > NOW() - INTERVAL 1 DAY");
                    $stmtRecent->execute(['user_id' => $user_id]);
                    if ($stmtRecent->fetchColumn() > 0) {
                        $risk_multi_request = 1;
                    }
                }
                
                // Check if there are differences in location or name between prior request and current (risk_data_changes)
                $risk_data_changes = 0;
                if (!$is_new_user) {
                    $stmtPrior = $pdo->prepare("SELECT name, location FROM users WHERE id = :user_id");
                    $stmtPrior->execute(['user_id' => $user_id]);
                    $priorData = $stmtPrior->fetch();
                    if ($priorData && ($priorData['name'] !== $name || $priorData['location'] !== $location)) {
                        $risk_data_changes = 1;
                    }
                }
                
                // 3. Handle File Upload (Supporting document)
                $supporting_document = null;
                if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_file($_FILES['supporting_document'], 'uploads/');
                    if ($upload_result === false) {
                        throw new Exception('File upload gagal. Pastikan file berupa JPG/PNG/PDF dan ukuran maksimal 2MB.');
                    }
                    $supporting_document = $upload_result;
                }
                
                // 4. Generate unique request code
                $generated_id = generate_request_code($pdo);
                
                // 5. Calculate initial risk score
                $initial_request = [
                    'safe_new_wa' => $safe_new_wa,
                    'safe_consistent_location' => 0,
                    'safe_comm_verified' => 0,
                    'safe_valid_evidence' => 0,
                    'safe_no_suspicious_hist' => $safe_no_suspicious_hist,
                    'risk_multi_request' => $risk_multi_request,
                    'risk_data_changes' => $risk_data_changes,
                    'risk_inconsistent_location' => 0,
                    'risk_refuse_verification' => 0,
                    'risk_repeated_pattern' => 0
                ];
                $risk_info = calculate_risk_score($initial_request);
                $risk_score = $risk_info['score'];
                
                // 6. Insert request
                $sqlInsertReq = "INSERT INTO requests 
                    (request_code, user_id, need_type, description, people_count, supporting_document, status, risk_score, 
                     safe_new_wa, safe_consistent_location, safe_comm_verified, safe_valid_evidence, safe_no_suspicious_hist,
                     risk_multi_request, risk_data_changes, risk_inconsistent_location, risk_refuse_verification, risk_repeated_pattern)
                    VALUES 
                    (:request_code, :user_id, :need_type, :description, :people_count, :supporting_document, 'BARU', :risk_score,
                     :safe_new_wa, 0, 0, 0, :safe_no_suspicious_hist,
                     :risk_multi_request, :risk_data_changes, 0, 0, 0)";
                
                $stmtInsertReq = $pdo->prepare($sqlInsertReq);
                $stmtInsertReq->execute([
                    'request_code' => $generated_id,
                    'user_id' => $user_id,
                    'need_type' => $need_type,
                    'description' => $description,
                    'people_count' => $people_count,
                    'supporting_document' => $supporting_document,
                    'risk_score' => $risk_score,
                    'safe_new_wa' => $safe_new_wa,
                    'safe_no_suspicious_hist' => $safe_no_suspicious_hist,
                    'risk_multi_request' => $risk_multi_request,
                    'risk_data_changes' => $risk_data_changes
                ]);
                
                // 7. Audit log (guest actions can be logged without admin_id)
                log_activity($pdo, null, "Permintaan baru dibuat: $generated_id", $generated_id);
                
                $pdo->commit();
                $success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$page_title = "Ajukan Bantuan - DaruratCheck";
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if ($success): ?>
                <!-- Success Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center">
                    <div class="card-header bg-success text-white py-4">
                        <i class="bi bi-check-circle-fill display-3"></i>
                        <h3 class="fw-bold mt-2">Permintaan Berhasil Dikirim</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <p class="lead text-secondary">Terima kasih, permohonan bantuan Anda telah masuk ke sistem kami.</p>
                        
                        <div class="alert alert-warning border-0 p-4 rounded-3 my-4 glass-container">
                            <h5 class="fw-bold text-dark mb-2">PENTING: Tolong simpan ID permintaan Anda</h5>
                            <p class="small text-secondary mb-3">ID ini digunakan untuk memeriksa status verifikasi secara mandiri.</p>
                            <div class="fs-3 fw-bold text-success border border-success border-2 p-3 rounded bg-white inline-block">
                                <?php echo $generated_id; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4">
                            <a href="status.php?id=<?php echo urlencode($generated_id); ?>" class="btn btn-success px-4 py-3 rounded-pill">
                                <i class="bi bi-search me-2"></i>Cek Status Permintaan
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary px-4 py-3 rounded-pill">
                                Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Form Card -->
                <div class="card border-0 shadow-md rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4">
                        <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-medical me-2 text-success"></i>Form Pengajuan Bantuan</h3>
                        <p class="text-secondary small mb-0">Silakan isi data kebutuhan Anda secara lengkap dan jujur. Data pribadi Anda dijamin keamanannya dan hanya diakses oleh admin relawan resmi.</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                                <div><?php echo $error; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form action="bantuan.php" method="POST" enctype="multipart/form-data">
                            
                            <!-- Section: Data Diri -->
                            <h5 class="fw-bold text-success mb-3 border-bottom pb-2"><i class="bi bi-person me-2"></i>Data Penerima Bantuan</h5>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Nama Lengkap Pemohon</label>
                                <input type="text" class="form-control form-control-lg rounded-3" id="name" name="name" required placeholder="Contoh: Andi Santoso" value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="whatsapp" class="form-label fw-semibold">Nomor WhatsApp Aktif</label>
                                    <input type="tel" class="form-control form-control-lg rounded-3" id="whatsapp" name="whatsapp" required placeholder="Contoh: 081234567890" value="<?php echo isset($_POST['whatsapp']) ? sanitize($_POST['whatsapp']) : ''; ?>">
                                    <div class="form-text small">Pastikan nomor aktif agar relawan dapat menghubungi Anda untuk verifikasi.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label fw-semibold">Lokasi Domisili (Kota/Kecamatan)</label>
                                    <input type="text" class="form-control form-control-lg rounded-3" id="location" name="location" required placeholder="Contoh: Tangerang, Kec. Cipondoh" value="<?php echo isset($_POST['location']) ? sanitize($_POST['location']) : ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Section: Kebutuhan -->
                            <h5 class="fw-bold text-success mt-4 mb-3 border-bottom pb-2"><i class="bi bi-heart me-2"></i>Detail Kebutuhan</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="need_type" class="form-label fw-semibold">Jenis Kebutuhan Utama</label>
                                    <select class="form-select form-select-lg rounded-3" id="need_type" name="need_type" required>
                                        <option value="" disabled selected>Pilih jenis kebutuhan...</option>
                                        <option value="Makanan" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Makanan') ? 'selected' : ''; ?>>Makanan (Siap Saji)</option>
                                        <option value="Sembako" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Sembako') ? 'selected' : ''; ?>>Sembako (Bahan Pokok)</option>
                                        <option value="Susu atau kebutuhan bayi" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Susu atau kebutuhan bayi') ? 'selected' : ''; ?>>Susu atau Kebutuhan Bayi</option>
                                        <option value="Kebutuhan lansia" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Kebutuhan lansia') ? 'selected' : ''; ?>>Kebutuhan Lansia (Popok, susu khusus, dll)</option>
                                        <option value="Kebutuhan kesehatan" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Kebutuhan kesehatan') ? 'selected' : ''; ?>>Kebutuhan Kesehatan (Obat-obatan, masker, dll)</option>
                                        <option value="Kebutuhan pendidikan" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Kebutuhan pendidikan') ? 'selected' : ''; ?>>Kebutuhan Pendidikan</option>
                                        <option value="Lainnya" <?php echo (isset($_POST['need_type']) && $_POST['need_type'] === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="people_count" class="form-label fw-semibold">Jumlah Orang yang Membutuhkan</label>
                                    <input type="number" class="form-control form-control-lg rounded-3" id="people_count" name="people_count" min="1" required placeholder="Contoh: 4" value="<?php echo isset($_POST['people_count']) ? intval($_POST['people_count']) : '1'; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label fw-semibold">Deskripsi Detail Kebutuhan</label>
                                <textarea class="form-control rounded-3" id="description" name="description" rows="4" required placeholder="Ceritakan kondisi Anda dan apa yang benar-benar dibutuhkan secara mendetail agar admin dapat memahaminya..."><?php echo isset($_POST['description']) ? sanitize($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="supporting_document" class="form-label fw-semibold">Foto atau Dokumen Pendukung (Opsional)</label>
                                <input class="form-control rounded-3" type="file" id="supporting_document" name="supporting_document" accept="image/jpeg,image/png,application/pdf">
                                <div class="form-text small">Foto kondisi rumah, berkas KTP/KK, atau bukti resep dokter. Format: JPG/PNG/PDF. Ukuran maks: 2MB.</div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-success btn-lg py-3 rounded-pill shadow-sm">
                                    <i class="bi bi-send me-2"></i>Kirim Pengajuan Bantuan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
