<?php
// kontak.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$search_query = sanitize($_GET['q'] ?? $_POST['q'] ?? '');
$contact = null;
$searched = false;
$error = '';

if (!empty($search_query)) {
    $searched = true;
    
    // Normalize phone number for search
    $clean_phone = preg_replace('/[^0-9]/', '', $search_query);
    // Handle leading 0 -> 62 conversion
    if (strpos($clean_phone, '0') === 0) {
        $clean_phone_62 = '62' . substr($clean_phone, 1);
    } else {
        $clean_phone_62 = $clean_phone;
    }
    
    // Normalize social media username (remove @)
    $clean_social = ltrim($search_query, '@');
    
    try {
        $sql = "SELECT * FROM official_contacts 
                WHERE status = 'Aktif' 
                  AND (
                    whatsapp = :clean_phone 
                    OR whatsapp = :clean_phone_62 
                    OR whatsapp LIKE :phone_like
                    OR REPLACE(bank_account, ' ', '') LIKE :bank_like
                    OR LOWER(instagram) = LOWER(:social)
                    OR LOWER(instagram) = LOWER(:social_with_at)
                    OR LOWER(tiktok) = LOWER(:social)
                    OR LOWER(tiktok) = LOWER(:social_with_at)
                    OR LOWER(website) LIKE LOWER(:web_like)
                  ) 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'clean_phone' => $search_query,
            'clean_phone_62' => $clean_phone_62,
            'phone_like' => '%' . $search_query . '%',
            'bank_like' => '%' . str_replace(' ', '', $search_query) . '%',
            'social' => $clean_social,
            'social_with_at' => '@' . $clean_social,
            'web_like' => '%' . $search_query . '%'
        ]);
        
        $contact = $stmt->fetch();
        
    } catch (\PDOException $e) {
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}

$page_title = "Cek Kontak Resmi - DaruratCheck";
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Search Box -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-shield-fill-check text-success me-2"></i>Cek Kontak Resmi</h4>
                    <p class="text-secondary small">Masukkan nomor WhatsApp, nomor rekening, atau username media sosial (Instagram/TikTok) yang menghubungi Anda untuk mengecek apakah terdaftar sebagai kontak resmi relawan Nasi Darurat.</p>
                    
                    <form action="kontak.php" method="GET">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control rounded-start-pill border-success" name="q" placeholder="Masukkan nomor WA, rekening, atau username..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                            <button class="btn btn-success rounded-end-pill px-4" type="submit">
                                <i class="bi bi-search me-1"></i> Cek Kontak
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
            
            <?php if ($searched): ?>
                <?php if ($contact): ?>
                    <!-- Verified Contact Card -->
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 border border-success border-2">
                        <div class="card-header bg-success text-white py-4 text-center">
                            <i class="bi bi-patch-check-fill display-4"></i>
                            <h4 class="fw-bold mt-2">Kontak Resmi Terverifikasi</h4>
                            <p class="mb-0 small opacity-75">Kontak ini terdaftar sebagai kontak resmi dalam database kami.</p>
                        </div>
                        
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                <div class="col-md-6 border-end border-light">
                                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-success me-2"></i>Detail Komunitas</h5>
                                    <table class="table table-borderless table-sm small mb-0">
                                        <tr>
                                            <td class="text-secondary ps-0" style="width: 40%;">Komunitas:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['community_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0">Admin/Relawan:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['admin_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0">Kota Wilayah:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['city']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0">Tgl Verifikasi:</td>
                                            <td class="fw-semibold text-dark"><?php echo date('d-m-Y', strtotime($contact['verification_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-telephone text-success me-2"></i>Saluran Kontak</h5>
                                    <table class="table table-borderless table-sm small mb-0">
                                        <tr>
                                            <td class="text-secondary ps-0" style="width: 40%;">WhatsApp:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['whatsapp']); ?></td>
                                        </tr>
                                        <?php if ($contact['bank_account']): ?>
                                        <tr>
                                            <td class="text-secondary ps-0">No. Rekening:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['bank_account']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($contact['instagram']): ?>
                                        <tr>
                                            <td class="text-secondary ps-0">Instagram:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['instagram']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($contact['tiktok']): ?>
                                        <tr>
                                            <td class="text-secondary ps-0">TikTok:</td>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($contact['tiktok']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($contact['website']): ?>
                                        <tr>
                                            <td class="text-secondary ps-0">Website:</td>
                                            <td class="fw-semibold text-dark"><a href="<?php echo htmlspecialchars($contact['website']); ?>" target="_blank" class="text-success text-decoration-none"><?php echo htmlspecialchars($contact['website']); ?></a></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Unverified Contact Card -->
                    <div class="card border-0 shadow-md rounded-4 overflow-hidden mb-4 border border-warning border-2">
                        <div class="card-header bg-warning text-dark py-4 text-center">
                            <i class="bi bi-exclamation-octagon display-4"></i>
                            <h4 class="fw-bold mt-2">Kontak Belum Terdaftar</h4>
                            <p class="mb-0 small opacity-75">Kontak ini tidak ditemukan dalam database kontak resmi.</p>
                        </div>
                        
                        <div class="card-body p-4 p-md-5">
                            <p class="text-secondary mb-4 text-center">
                                Kami tidak menemukan data resmi untuk kata kunci <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>.
                            </p>
                            
                            <div class="alert alert-danger border-0 p-4 rounded-3 glass-container mb-0 text-center">
                                <h5 class="fw-bold text-danger mb-2"><i class="bi bi-shield-fill-exclamation me-2"></i>Lakukan Verifikasi Tambahan</h5>
                                <p class="small text-secondary mb-0">
                                    Jangan terburu-buru memberikan uang, nomor OTP, kata sandi, atau data pribadi sensitif lainnya. Selalu konfirmasi ulang melalui saluran komunikasi resmi komunitas yang Anda kenal sebelum melanjutkan transaksi.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
