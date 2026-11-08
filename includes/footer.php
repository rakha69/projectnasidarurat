</main> <!-- End Main Container -->

<footer class="footer bg-dark text-light mt-auto py-4 border-top border-secondary">
    <div class="container">
        <div class="row gy-4">
            <div class="col-md-6">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-shield-check me-2"></i>DaruratCheck</h5>
                <p class="text-secondary small">
                    DaruratCheck bukan lembaga pemberi bantuan. Sistem ini merupakan alat bantu untuk komunitas dan relawan dalam melakukan verifikasi serta pencatatan bantuan untuk meminimalkan risiko penipuan.
                </p>
                <p class="text-secondary small mb-0">
                    &copy; <?php echo date('Y'); ?> DaruratCheck. Prototype Edukasi Komunitas.
                </p>
            </div>
            <div class="col-md-3">
                <h6 class="fw-bold text-light mb-3">Tautan Publik</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><a href="<?php echo $base_url; ?>index.php" class="text-secondary text-decoration-none hover-light">Halaman Utama</a></li>
                    <li class="mb-2"><a href="<?php echo $base_url; ?>bantuan.php" class="text-secondary text-decoration-none hover-light">Ajukan Bantuan</a></li>
                    <li class="mb-2"><a href="<?php echo $base_url; ?>status.php" class="text-secondary text-decoration-none hover-light">Cek Status Bantuan</a></li>
                    <li class="mb-0"><a href="<?php echo $base_url; ?>kontak.php" class="text-secondary text-decoration-none hover-light">Cek Kontak Resmi</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h6 class="fw-bold text-light mb-3">Untuk Relawan</h6>
                <ul class="list-unstyled mb-0 small">
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <li class="mb-2"><a href="<?php echo $base_url; ?>admin/dashboard.php" class="text-secondary text-decoration-none hover-light">Dashboard Admin</a></li>
                        <li class="mb-2"><a href="<?php echo $base_url; ?>admin/requests.php" class="text-secondary text-decoration-none hover-light">Daftar Permintaan</a></li>
                        <li class="mb-0"><a href="<?php echo $base_url; ?>logout.php" class="text-secondary text-decoration-none hover-light text-danger">Keluar (Logout)</a></li>
                    <?php else: ?>
                        <li class="mb-0"><a href="<?php echo $base_url; ?>login.php" class="text-secondary text-decoration-none hover-light"><i class="bi bi-lock-fill text-success me-1"></i>Login Admin</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
