<?php
// admin/contacts.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

$success_msg = '';
$error_msg = '';
$edit_mode = false;
$edit_contact = null;

// Determine if we are editing a contact
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM official_contacts WHERE id = :id");
        $stmt->execute(['id' => $edit_id]);
        $edit_contact = $stmt->fetch();
        if ($edit_contact) {
            $edit_mode = true;
        }
    } catch (\PDOException $e) {
        $error_msg = "Gagal memuat data edit: " . $e->getMessage();
    }
}

// Handle Form Submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF Token tidak valid.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            $community_name = sanitize($_POST['community_name'] ?? '');
            $admin_name = sanitize($_POST['admin_name'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $whatsapp_raw = sanitize($_POST['whatsapp'] ?? '');
            $bank_account = sanitize($_POST['bank_account'] ?? '');
            $instagram = sanitize($_POST['instagram'] ?? '');
            $tiktok = sanitize($_POST['tiktok'] ?? '');
            $website = sanitize($_POST['website'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Aktif');
            $verification_date = sanitize($_POST['verification_date'] ?? date('Y-m-d'));
            
            // Validate required fields
            if (empty($community_name) || empty($admin_name) || empty($city) || empty($whatsapp_raw) || empty($verification_date)) {
                $error_msg = "Semua field bertanda bintang wajib diisi.";
            } else {
                $whatsapp = validate_whatsapp($whatsapp_raw);
                if (!$whatsapp) {
                    $error_msg = "Format nomor WhatsApp tidak valid.";
                } else {
                    try {
                        if ($action === 'add') {
                            // Insert
                            $sql = "INSERT INTO official_contacts 
                                    (admin_id, community_name, admin_name, city, whatsapp, bank_account, instagram, tiktok, website, status, verification_date) 
                                    VALUES 
                                    (:admin_id, :community_name, :admin_name, :city, :whatsapp, :bank_account, :instagram, :tiktok, :website, :status, :verification_date)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                'admin_id' => $_SESSION['admin_id'],
                                'community_name' => $community_name,
                                'admin_name' => $admin_name,
                                'city' => $city,
                                'whatsapp' => $whatsapp,
                                'bank_account' => $bank_account,
                                'instagram' => $instagram,
                                'tiktok' => $tiktok,
                                'website' => $website,
                                'status' => $status,
                                'verification_date' => $verification_date
                            ]);
                            
                            log_activity($pdo, $_SESSION['admin_id'], "Menambahkan kontak resmi baru: $whatsapp ($community_name)", $whatsapp);
                            $success_msg = "Kontak resmi berhasil ditambahkan.";
                        } else {
                            // Update
                            $id = intval($_POST['id']);
                            $sql = "UPDATE official_contacts SET 
                                    community_name = :community_name, 
                                    admin_name = :admin_name, 
                                    city = :city, 
                                    whatsapp = :whatsapp, 
                                    bank_account = :bank_account, 
                                    instagram = :instagram, 
                                    tiktok = :tiktok, 
                                    website = :website, 
                                    status = :status, 
                                    verification_date = :verification_date 
                                    WHERE id = :id";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                'community_name' => $community_name,
                                'admin_name' => $admin_name,
                                'city' => $city,
                                'whatsapp' => $whatsapp,
                                'bank_account' => $bank_account,
                                'instagram' => $instagram,
                                'tiktok' => $tiktok,
                                'website' => $website,
                                'status' => $status,
                                'verification_date' => $verification_date,
                                'id' => $id
                            ]);
                            
                            log_activity($pdo, $_SESSION['admin_id'], "Memperbarui kontak resmi: $whatsapp ($community_name)", $whatsapp);
                            $success_msg = "Data kontak resmi berhasil diperbarui.";
                            $edit_mode = false;
                        }
                    } catch (\PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error_msg = "Nomor WhatsApp tersebut sudah terdaftar dalam database.";
                        } else {
                            $error_msg = "Gagal memproses data: " . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            $whatsapp_del = sanitize($_POST['whatsapp_del'] ?? '');
            try {
                $stmt = $pdo->prepare("DELETE FROM official_contacts WHERE id = :id");
                $stmt->execute(['id' => $id]);
                
                log_activity($pdo, $_SESSION['admin_id'], "Menghapus kontak resmi: $whatsapp_del", $whatsapp_del);
                $success_msg = "Kontak resmi berhasil dihapus.";
                $edit_mode = false;
            } catch (\PDOException $e) {
                $error_msg = "Gagal menghapus kontak: " . $e->getMessage();
            }
        }
    }
}

// Fetch all contacts
try {
    $stmtContacts = $pdo->query("SELECT * FROM official_contacts ORDER BY created_at DESC");
    $contacts = $stmtContacts->fetchAll();
} catch (\PDOException $e) {
    die("Gagal mengambil data kontak: " . $e->getMessage());
}

$base_url = '../';
$page_title = "Kelola Kontak Resmi - DaruratCheck";
require_once '../includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-md-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap g-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-shield-fill-check text-success me-2"></i>Kelola Database Kontak Resmi</h2>
            <p class="text-secondary small mb-0">Kelola informasi kontak relawan atau media sosial komunitas resmi untuk publik.</p>
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
        <!-- Left: Form Input (Add / Edit) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-lg-top" style="top: 80px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                        <?php echo $edit_mode ? '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Kontak Resmi' : '<i class="bi bi-plus-circle text-success me-2"></i>Tambah Kontak Baru'; ?>
                    </h5>
                    
                    <form action="contacts.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_contact['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Komunitas *</label>
                            <input type="text" class="form-control" name="community_name" required placeholder="Contoh: Nasi Darurat Surabaya" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['community_name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Admin / Relawan *</label>
                            <input type="text" class="form-control" name="admin_name" required placeholder="Contoh: Rian Relawan" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['admin_name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Kota Wilayah *</label>
                            <input type="text" class="form-control" name="city" required placeholder="Contoh: Surabaya" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['city']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nomor WhatsApp *</label>
                            <input type="tel" class="form-control" name="whatsapp" required placeholder="Contoh: 08123456789" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['whatsapp']) : ''; ?>">
                            <div class="form-text small">Angka saja, tanpa spasi atau tanda +.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nomor Rekening (Opsional)</label>
                            <input type="text" class="form-control" name="bank_account" placeholder="Contoh: BCA 123456 a/n Nasi Darurat" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['bank_account']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Instagram Username (Opsional)</label>
                            <input type="text" class="form-control" name="instagram" placeholder="Contoh: @nasidarurat_sby" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['instagram']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">TikTok Username (Opsional)</label>
                            <input type="text" class="form-control" name="tiktok" placeholder="Contoh: @nasidarurat_sby" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['tiktok']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Website (Opsional)</label>
                            <input type="url" class="form-control" name="website" placeholder="Contoh: https://nasidarurat.org" value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['website']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tanggal Verifikasi *</label>
                            <input type="date" class="form-control" name="verification_date" required value="<?php echo $edit_mode ? htmlspecialchars($edit_contact['verification_date']) : date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Aktif" <?php echo ($edit_mode && $edit_contact['status'] === 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Tidak aktif" <?php echo ($edit_mode && $edit_contact['status'] === 'Tidak aktif') ? 'selected' : ''; ?>>Tidak aktif</option>
                                <option value="Perlu diperbarui" <?php echo ($edit_mode && $edit_contact['status'] === 'Perlu diperbarui') ? 'selected' : ''; ?>>Perlu diperbarui</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success py-2.5">
                                <i class="bi bi-check-circle-fill me-1"></i> <?php echo $edit_mode ? 'Simpan Perubahan' : 'Tambah Kontak'; ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="contacts.php" class="btn btn-outline-secondary py-2">Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right: Table List -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-check me-2 text-success"></i>Daftar Kontak Resmi</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-4">KOMUNITAS</th>
                                    <th>KOTA</th>
                                    <th>ADMIN</th>
                                    <th>WHATSAPP</th>
                                    <th>STATUS</th>
                                    <th class="text-center pe-4" style="width: 150px;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($contacts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-secondary">Belum ada kontak resmi yang dimasukkan.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $c): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <?php echo htmlspecialchars($c['community_name']); ?>
                                                <?php if ($c['bank_account']): ?>
                                                    <span class="d-block text-secondary font-weight-normal small fs-7" style="font-size: 0.75rem;"><i class="bi bi-wallet2 me-1"></i><?php echo htmlspecialchars($c['bank_account']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($c['city']); ?></td>
                                            <td><?php echo htmlspecialchars($c['admin_name']); ?></td>
                                            <td>
                                                <a href="https://wa.me/<?php echo htmlspecialchars($c['whatsapp']); ?>" target="_blank" class="text-success text-decoration-none">
                                                    <i class="bi bi-whatsapp me-1"></i><?php echo htmlspecialchars($c['whatsapp']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($c['status'] === 'Aktif'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php elseif ($c['status'] === 'Tidak aktif'): ?>
                                                    <span class="badge bg-danger">Tidak aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Perlu diperbarui</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="contacts.php?edit_id=<?php echo $c['id']; ?>" class="btn btn-outline-warning btn-xs px-2 py-1 rounded-pill" style="font-size: 0.75rem;">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <form action="contacts.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kontak ini?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                        <input type="hidden" name="whatsapp_del" value="<?php echo htmlspecialchars($c['whatsapp']); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-xs px-2 py-1 rounded-pill" style="font-size: 0.75rem;">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
