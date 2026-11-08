<?php
// login.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            // Retrieve admin details
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Login success, set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                
                // Initialize CSRF token
                generate_csrf_token();
                
                // Log activity
                log_activity($pdo, $admin['id'], "Admin Login", "admin");
                
                header("Location: admin/dashboard.php");
                exit;
            } else {
                $error = 'Username atau password salah.';
                // Log failed attempt if username exists
                if ($admin) {
                    log_activity($pdo, null, "Gagal Login (Password salah) untuk username: $username", $username);
                } else {
                    log_activity($pdo, null, "Gagal Login (Username tidak ditemukan): $username", $username);
                }
            }
        } catch (\PDOException $e) {
            $error = 'Kesalahan database: ' . $e->getMessage();
        }
    }
}

$page_title = "Admin Login - DaruratCheck";
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4 text-center">
                    <i class="bi bi-shield-lock-fill display-4 text-success mb-2 d-block"></i>
                    <h4 class="fw-bold mb-0">Admin Login</h4>
                    <p class="text-secondary small mb-0 mt-1">Hanya untuk relawan Nasi Darurat terdaftar</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-fill text-secondary"></i></span>
                                <input type="text" class="form-control rounded-3 border-start-0" id="username" name="username" placeholder="Masukkan username..." required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key-fill text-secondary"></i></span>
                                <input type="password" class="form-control rounded-3 border-start-0" id="password" name="password" placeholder="Masukkan password..." required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg py-3 rounded-pill shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="index.php" class="text-secondary text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
