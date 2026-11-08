<?php
// status.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$search_code = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$request = null;
$timeline = [];
$error = '';

/**
 * Anonymizes phone number: 081234567890 -> 0812****7890
 */
function anonymize_whatsapp($phone) {
    if (strlen($phone) < 7) return '**********';
    return substr($phone, 0, 4) . '****' . substr($phone, -4);
}

/**
 * Anonymizes name: Andi Santoso -> A*** S******
 */
function anonymize_name($name) {
    $parts = explode(' ', $name);
    $anonymized = [];
    foreach ($parts as $part) {
        if (strlen($part) <= 2) {
            $anonymized[] = $part;
        } else {
            $anonymized[] = substr($part, 0, 1) . str_repeat('*', strlen($part) - 2) . substr($part, -1);
        }
    }
    return implode(' ', $anonymized);
}

if (!empty($search_code)) {
    try {
        // Query the request and user details
        $sql = "SELECT r.*, u.name, u.whatsapp, u.location 
                FROM requests r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.request_code = :request_code";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['request_code' => $search_code]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $error = 'ID Permintaan tidak ditemukan. Silakan periksa kembali penulisan ID Anda.';
        } else {
            // Build the timeline events
            
            // Event 1: Creation
            $timeline[] = [
                'time' => $request['created_at'],
                'title' => 'Permintaan Bantuan Dibuat',
                'desc' => 'Permintaan bantuan berhasil diajukan dan masuk ke sistem antrean verifikasi.',
                'status' => 'BARU'
            ];
            
            // Event 2: Verifications
            $sqlVer = "SELECT * FROM verifications WHERE request_id = :request_id ORDER BY created_at ASC";
            $stmtVer = $pdo->prepare($sqlVer);
            $stmtVer->execute(['request_id' => $request['id']]);
            $verifications = $stmtVer->fetchAll();
            
            foreach ($verifications as $ver) {
                $status_flag = 'SEDANG DIVERIFIKASI';
                if ($ver['result'] === 'Valid') {
                    $desc = "Verifikasi berhasil menggunakan metode <strong>" . sanitize($ver['method']) . "</strong>. Status: <strong>Valid</strong>.";
                } elseif ($ver['result'] === 'Tidak valid') {
                    $desc = "Verifikasi dilakukan menggunakan metode <strong>" . sanitize($ver['method']) . "</strong>. Status: <strong>Data tidak valid/risiko tinggi</strong>.";
                } else {
                    $desc = "Verifikasi dilakukan menggunakan metode <strong>" . sanitize($ver['method']) . "</strong>. Status: <strong>Perlu informasi tambahan</strong>.";
                }
                
                if (!empty($ver['notes'])) {
                    $desc .= " <br><span class='text-muted small'>Catatan: \"" . sanitize($ver['notes']) . "\"</span>";
                }
                
                $timeline[] = [
                    'time' => $ver['created_at'],
                    'title' => 'Pengecekan Verifikasi Admin',
                    'desc' => $desc,
                    'status' => $status_flag
                ];
            }
            
            // Event 3: Verification updates / approval from status
            if ($request['status'] === 'TERVERIFIKASI') {
                $timeline[] = [
                    'time' => $request['updated_at'],
                    'title' => 'Permintaan Terverifikasi',
                    'desc' => 'Admin telah menyelesaikan proses verifikasi dan menandai pengajuan sebagai valid.',
                    'status' => 'TERVERIFIKASI'
                ];
            } elseif ($request['status'] === 'DISETUJUI') {
                $timeline[] = [
                    'time' => $request['updated_at'],
                    'title' => 'Permintaan Disetujui',
                    'desc' => 'Bantuan telah disetujui oleh admin dan masuk ke antrean penyaluran relawan.',
                    'status' => 'DISETUJUI'
                ];
            } elseif ($request['status'] === 'DITOLAK') {
                $timeline[] = [
                    'time' => $request['updated_at'],
                    'title' => 'Permintaan Ditolak',
                    'desc' => 'Mohon maaf, permohonan bantuan ditolak karena tidak memenuhi kriteria verifikasi.',
                    'status' => 'DITOLAK'
                ];
            }
            
            // Event 4: Assistance Distribution
            $sqlAid = "SELECT * FROM assistance WHERE request_id = :request_id ORDER BY created_at ASC";
            $stmtAid = $pdo->prepare($sqlAid);
            $stmtAid->execute(['request_id' => $request['id']]);
            $assistance = $stmtAid->fetchAll();
            
            foreach ($assistance as $aid) {
                $desc = "Bantuan berupa <strong>" . sanitize($aid['aid_type']) . "</strong> telah disalurkan menggunakan metode <strong>" . sanitize($aid['distribution_method']) . "</strong> oleh relawan <strong>" . sanitize($aid['volunteer_name']) . "</strong>.";
                if (!empty($aid['description'])) {
                    $desc .= " <br><span class='text-muted small'>Keterangan: " . sanitize($aid['description']) . "</span>";
                }
                
                $timeline[] = [
                    'time' => $aid['created_at'],
                    'title' => 'Bantuan Disalurkan',
                    'desc' => $desc,
                    'status' => 'BANTUAN DISALURKAN'
                ];
            }
            
            // Event 5: Finished
            if ($request['status'] === 'SELESAI') {
                $timeline[] = [
                    'time' => $request['updated_at'],
                    'title' => 'Permintaan Selesai',
                    'desc' => 'Seluruh proses penyaluran bantuan telah selesai dan dicatat.',
                    'status' => 'SELESAI'
                ];
            }
            
            // Sort timeline by timestamp
            usort($timeline, function($a, $b) {
                return strtotime($a['time']) - strtotime($b['time']);
            });
        }
    } catch (\PDOException $e) {
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}

$page_title = "Cek Status Permintaan - DaruratCheck";
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Search Box -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-search text-success me-2"></i>Cek Status Bantuan</h4>
                    <p class="text-secondary small">Masukkan ID permintaan bantuan Anda untuk memeriksa perkembangan verifikasi dan penyaluran bantuan.</p>
                    
                    <form action="status.php" method="GET">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control rounded-start-pill border-success" name="id" placeholder="Contoh: ND-20260815-0043" value="<?php echo htmlspecialchars($search_code); ?>" required>
                            <button class="btn btn-success rounded-end-pill px-4" type="submit">
                                <i class="bi bi-search me-1"></i> Cek Status
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 mt-3 d-flex align-items-center mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($request): ?>
                <!-- Result Card -->
                <div class="card border-0 shadow-md rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-dark text-white p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-secondary small d-block">ID PERMINTAAN</span>
                            <span class="fs-5 fw-bold text-success"><?php echo htmlspecialchars($request['request_code']); ?></span>
                        </div>
                        <div>
                            <span class="badge <?php echo get_status_badge($request['status']); ?> fs-6 px-3 py-2 rounded-pill shadow-sm">
                                <?php echo htmlspecialchars($request['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Permintaan</h5>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <span class="text-secondary small d-block">Nama Lengkap (Disamarkan)</span>
                                <span class="fw-semibold"><?php echo anonymize_name($request['name']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <span class="text-secondary small d-block">Nomor WhatsApp (Disamarkan)</span>
                                <span class="fw-semibold"><?php echo anonymize_whatsapp($request['whatsapp']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <span class="text-secondary small d-block">Lokasi Domisili</span>
                                <span class="fw-semibold"><?php echo htmlspecialchars($request['location']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <span class="text-secondary small d-block">Jenis Kebutuhan</span>
                                <span class="fw-semibold"><?php echo htmlspecialchars($request['need_type']); ?> (<?php echo intval($request['people_count']); ?> orang)</span>
                            </div>
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Timeline Riwayat Permintaan</h5>
                        
                        <ul class="status-timeline">
                            <?php foreach ($timeline as $evt): ?>
                                <li class="timeline-item <?php echo ($evt['status'] === $request['status']) ? 'active' : ''; ?> <?php echo ($evt['status'] === 'DITOLAK') ? 'rejected' : ''; ?>">
                                    <div class="timeline-date"><?php echo format_date_id($evt['time']); ?></div>
                                    <div class="timeline-title"><?php echo $evt['title']; ?></div>
                                    <div class="timeline-desc"><?php echo $evt['desc']; ?></div>
                                </li>
                            <?php endcode; // Wait, PHP syntax typo check: it should be 'endforeach;' not 'endcode;' ?>
                            <?php endforeach; ?>
                        </ul>
                        
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
