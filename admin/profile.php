<?php
// admin/profile.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify admin login
require_admin_login();

$success_msg = '';
$error_msg = '';

try {
    // Retrieve latest admin data
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        die("Admin tidak ditemukan.");
    }
} catch (\PDOException $e) {
    die("Kesalahan database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Validate CSRF
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "CSRF Token tidak valid.";
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($name)) {
            $error_msg = "Nama lengkap wajib diisi.";
        } else {
            try {
                $pdo->beginTransaction();
                
                $password_changed = false;
                
                // If trying to change password
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        throw new Exception("Password lama wajib diisi untuk mengubah password.");
                    }
                    
                    // Verify current password
                    if (!password_verify($current_password, $admin['password'])) {
                        throw new Exception("Password lama yang Anda masukkan salah.");
                    }
                    
                    if ($new_password !== $confirm_password) {
                        throw new Exception("Konfirmasi password baru tidak cocok.");
                    }
                    
                    if (strlen($new_password) < 6) {
                        throw new Exception("Password baru minimal 6 karakter.");
                    }
                    
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    
                    // Update name & password
                    $stmtUpdate = $pdo->prepare("UPDATE admins SET name = :name, password = :password WHERE id = :id");
                    $stmtUpdate->execute([
                        'name' => $name,
                        'password' => $hashed_password,
                        'id' => $_SESSION['admin_id']
                    ]);
                    
                    $password_changed = true;
                } else {
                    // Update only name
                    $stmtUpdate = $pdo->prepare("UPDATE admins SET name = :name WHERE id = :id");
                    $stmtUpdate->execute([
                        'name' => $name,
                        'id' => $_SESSION['admin_id']
                    ]);
                }
                
                // Update session variable
                $_SESSION['admin_name'] = $name;
                
                // Log activity
                $act_text = $password_changed ? "Memperbarui profil dan password admin" : "Memperbarui profil admin (nama)";
                log_activity($pdo, $_SESSION['admin_id'], $act_text, $_SESSION['admin_username']);
                
                $pdo->commit();
                $success_msg = "Profil berhasil diperbarui.";
                
                // Reload admin details
                $stmt->execute(['id' => $_SESSION['admin_id']]);
                $admin = $stmt->fetch();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = $e->getMessage();
            }
        }
    }
}

$base_url = '../';
$page_title = "Profil Saya - DaruratCheck";
require_once '../includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
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
            
            <div class="card border-0 shadow-md rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4">
                    <h4 class="fw-bold mb-0"><i class="bi bi-person-gear text-success me-2"></i>Pengaturan Profil Saya</h4>
                    <p class="text-secondary small mb-0 mt-1">Ubah nama relawan Anda atau perbarui password akun.</p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <!-- Readonly username -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Username (tidak dapat diubah)</label>
                            <input type="text" class="form-control bg-light" readonly value="<?php echo htmlspecialchars($admin['username']); ?>">
                        </div>
                        
                        <!-- Name edit -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Nama Lengkap Relawan *</label>
                            <input type="text" class="form-control" name="name" required placeholder="Contoh: Rian Relawan" value="<?php echo htmlspecialchars($admin['name']); ?>">
                        </div>
                        
                        <!-- Password edits section -->
                        <h5 class="fw-bold text-success border-bottom pb-2 mb-3"><i class="bi bi-key me-2"></i>Ubah Password (Isi jika ingin diubah)</h5>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password Saat Ini</label>
                            <input type="password" class="form-control" name="current_password" placeholder="Masukkan password lama...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password Baru</label>
                            <input type="password" class="form-control" name="new_password" placeholder="Masukkan password baru (min 6 karakter)...">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-secondary">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Ulangi password baru...">
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success py-2.5 rounded-pill shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="dashboard.php" class="text-secondary text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
