<?php
// index.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$page_title = "DaruratCheck - Bantu Dengan Aman, Terima Dengan Tepat";
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<div class="container mb-5">
    <div class="hero-section">
        <h1 class="display-5 mb-2">DaruratCheck</h1>
        <p class="lead mb-4">“Bantu dengan aman. Terima bantuan dengan tepat.”</p>
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
            <a href="bantuan.php" class="btn btn-success btn-lg px-4 py-3 rounded-pill shadow-sm">
                <i class="bi bi-file-earmark-plus me-2"></i>Saya Membutuhkan Bantuan
            </a>
            <a href="status.php" class="btn btn-outline-light btn-lg px-4 py-3 rounded-pill shadow-sm">
                <i class="bi bi-search me-2"></i>Cek Status Permintaan
            </a>
        </div>
    </div>
</div>

<!-- Main Options Section -->
<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        <!-- Card 1 -->
        <div class="col-md-4">
            <div class="action-card text-center d-flex flex-column justify-content-between">
                <div>
                    <div class="fs-1 text-success mb-3"><i class="bi bi-person-fill-exclamation"></i></div>
                    <h4 class="mb-2">Ajukan Bantuan</h4>
                    <p class="text-secondary small">Jika Anda atau keluarga terdekat membutuhkan bantuan sembako, makanan, susu bayi, atau kebutuhan pokok mendesak lainnya.</p>
                </div>
                <a href="bantuan.php" class="btn btn-success w-100 mt-3 rounded-pill">Ajukan Sekarang</a>
            </div>
        </div>
        
        <!-- Card 2 -->
        <div class="col-md-4">
            <div class="action-card text-center d-flex flex-column justify-content-between">
                <div>
                    <div class="fs-1 text-primary mb-3"><i class="bi bi-hourglass-split"></i></div>
                    <h4 class="mb-2">Cek Status</h4>
                    <p class="text-secondary small">Masukkan ID permintaan bantuan Anda (format: ND-YYYYMMDD-XXXX) untuk memantau proses verifikasi dan penyaluran bantuan.</p>
                </div>
                <a href="status.php" class="btn btn-outline-primary w-100 mt-3 rounded-pill">Cek Status Bantuan</a>
            </div>
        </div>
        
        <!-- Card 3 -->
        <div class="col-md-4">
            <div class="action-card text-center d-flex flex-column justify-content-between">
                <div>
                    <div class="fs-1 text-warning mb-3"><i class="bi bi-shield-fill-check"></i></div>
                    <h4 class="mb-2">Cek Kontak Resmi</h4>
                    <p class="text-secondary small">Cek apakah nomor WhatsApp, nomor rekening, atau akun sosial media yang menghubungi Anda benar-benar relawan Nasi Darurat resmi.</p>
                </div>
                <a href="kontak.php" class="btn btn-outline-warning w-100 mt-3 rounded-pill">Verifikasi Kontak</a>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="container mb-5">
    <div class="bg-white rounded-4 p-4 p-md-5 shadow-sm border border-light">
        <h3 class="text-center mb-5 fw-bold">Bagaimana Cara Kerja Sistem?</h3>
        <div class="row g-4">
            <div class="col-md-3 col-sm-6">
                <div class="workflow-step">
                    <div class="workflow-icon">1</div>
                    <h5 class="fw-bold mb-2">Ajukan Kebutuhan</h5>
                    <p class="text-secondary small mb-0">Isi formulir pengajuan dengan data lengkap dan lampirkan bukti kebutuhan Anda.</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="workflow-step">
                    <div class="workflow-icon">2</div>
                    <h5 class="fw-bold mb-2">Verifikasi Admin</h5>
                    <p class="text-secondary small mb-0">Admin Nasi Darurat melakukan verifikasi data melalui telepon, WhatsApp, atau pengecekan lapangan.</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="workflow-step">
                    <div class="workflow-icon">3</div>
                    <h5 class="fw-bold mb-2">Penyaluran Bantuan</h5>
                    <p class="text-secondary small mb-0">Jika terverifikasi dan disetujui, relawan kami akan menyalurkan bantuan sesuai kebutuhan.</p>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6">
                <div class="workflow-step">
                    <div class="workflow-icon">4</div>
                    <h5 class="fw-bold mb-2">Bantuan Tercatat</h5>
                    <p class="text-secondary small mb-0">Setiap bantuan yang disalurkan dicatat secara transparan sebagai bentuk pertanggungjawaban.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info Disclaimer Box -->
<div class="container mb-4">
    <div class="alert alert-secondary border-0 p-4 rounded-4 text-center glass-container shadow-sm">
        <i class="bi bi-info-circle-fill text-success fs-3 mb-2 d-block"></i>
        <h5 class="fw-bold text-dark">Informasi Penting</h5>
        <p class="text-secondary mb-0 max-width-600 mx-auto small">
            DaruratCheck bukan lembaga pemberi bantuan. Sistem ini merupakan alat bantu untuk komunitas dan relawan dalam melakukan verifikasi serta pencatatan bantuan.
        </p>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
