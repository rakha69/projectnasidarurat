<?php
// includes/header.php
$base_url = $base_url ?? '';
$page_title = $page_title ?? 'DaruratCheck - Sistem Verifikasi Bantuan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/styles.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $base_url; ?>index.php">
            <i class="bi bi-shield-check text-success fs-3 me-2"></i>
            <span class="brand-text">Darurat<span class="text-success">Check</span></span>
        </a>
        <button class="navbar-brand-toggler navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>index.php">Mulai</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>bantuan.php">Ajukan Bantuan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>status.php">Cek Status</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>kontak.php">Cek Kontak Resmi</a>
                </li>
                
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <div class="dropdown">
                            <button class="btn btn-success btn-sm dropdown-toggle px-3 py-2 rounded-pill d-flex align-items-center gap-1" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> Admin Panel
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/dashboard.php"><i class="bi bi-grid-fill me-2 text-success"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/requests.php"><i class="bi bi-file-earmark-medical me-2 text-success"></i>Permintaan</a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/contacts.php"><i class="bi bi-telephone-outbound me-2 text-success"></i>Kontak Resmi</a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/audit-log.php"><i class="bi bi-journal-text me-2 text-success"></i>Audit Log</a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_url; ?>admin/profile.php"><i class="bi bi-gear-fill me-2 text-success"></i>Profil Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo $base_url; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                            </ul>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill" href="<?php echo $base_url; ?>login.php">
                            <i class="bi bi-lock-fill me-1"></i> Admin Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="py-4">
