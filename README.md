# DaruratCheck - Sistem Verifikasi Bantuan Komunitas

DaruratCheck adalah prototype aplikasi web full-stack yang dibuat untuk membantu komunitas relawan (seperti **Nasi Darurat**) dalam mengelola pengajuan bantuan sosial dari masyarakat secara terorganisir, aman, dan meminimalkan risiko penipuan (fraud).

Sistem ini **tidak menyatakan seseorang sebagai penipu secara otomatis**, melainkan memberikan indikator risiko (Risk Score) berdasarkan kepatuhan data dan memudahkan admin relawan dalam memverifikasi data sebelum mengambil keputusan akhir.

---

## Fitur Utama

1. **Pengajuan Bantuan (Bantuan Publik):** Form untuk masyarakat mengajukan bantuan (sembako, susu bayi, dll) dengan validasi data dan upload berkas pendukung. Menghasilkan ID Permintaan unik: `ND-YYYYMMDD-XXXX`.
2. **Cek Status Permintaan:** Halaman publik untuk melacak status pengajuan menggunakan ID Permintaan dengan tampilan timeline dinamis (data sensitif disamarkan).
3. **Cek Kontak Resmi:** Pencarian publik untuk memverifikasi apakah nomor WhatsApp, nomor rekening, atau media sosial yang menghubungi adalah kontak resmi relawan Nasi Darurat.
4. **Dashboard Admin:** Statistik real-time (jumlah permintaan baru, disetujui, ditolak, selesai) beserta ringkasan pengajuan terbaru.
5. **Penilaian Risiko (Risk Score):** Sistem kalkulasi skor risiko (0-100) otomatis dan manual berbasis indikator keselamatan (trust) dan indikator bahaya (risk).
6. **Sistem Verifikasi Multi-Metode:** Form bagi admin untuk mencatat proses verifikasi (WhatsApp, Telepon, Lapangan, dll).
7. **Pencatatan Penyaluran Bantuan:** Pendataan jenis bantuan, nilai bantuan, nama relawan, dan upload bukti foto serah terima bantuan.
8. **Pengaturan Kontak Resmi:** Kelola kontak resmi komunitas relawan (CRUD).
9. **Audit Log:** Pencatatan otomatis setiap aktivitas penting admin (login, verifikasi, approval, hapus data, dll) disertai pencatatan IP Address.
10. **Aman & Ringan:** Dibuat menggunakan PHP murni tanpa library eksternal berbayar, dilengkapi keamanan standard (Password Hashing, SQL Injection Prevention, CSRF Protection, Upload File Restriction).

---

## Teknologi

* **Frontend:** HTML5, CSS3 (Custom Stylesheet), JavaScript, Bootstrap 5 (UI Framework).
* **Backend:** PHP (PDO Extension).
* **Database:** MySQL.
* **Local Server:** XAMPP (Apache & MySQL).

---

## Cara Instalasi & Menjalankan Aplikasi

### 1. Persiapan Folder Project di XAMPP
Aplikasi ini diletakkan langsung di dalam folder root server lokal XAMPP agar dapat dijalankan secara instan.
1. Salin seluruh folder project `projectnasidarurat` ini ke direktori:
   * **Windows:** `C:\xampp\htdocs\projectnasidarurat`
2. Pastikan folder `uploads` memiliki hak akses tulis (write permission) agar fitur upload foto bantuan berfungsi.

### 2. Jalankan Apache dan MySQL
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL**.

### 3. Membuat & Mengimpor Database
1. Buka browser dan akses **phpMyAdmin** di URL: `http://localhost/phpmyadmin/`.
2. Pilih tab **Import** di bagian atas.
3. Klik tombol **Choose File** (Pilih File) dan pilih berkas `database.sql` yang terletak di dalam folder project Anda (`C:\xampp\htdocs\projectnasidarurat\database.sql`).
4. Klik tombol **Go** atau **Import** di bagian bawah.
5. Database dengan nama `daruratcheck` beserta tabel dan data dummy awal akan otomatis dibuat.

### 4. Menjalankan Aplikasi
1. Buka browser dan akses URL:
   * **Halaman Publik:** `http://localhost/projectnasidarurat/`
   * **Halaman Admin:** `http://localhost/projectnasidarurat/login.php`

---

## Kredensial Akun Testing Admin

Gunakan akun berikut untuk masuk ke dashboard admin:
* **Username:** `admin`
* **Password:** `Admin123!`

---

## Struktur Folder Project

```
projectnasidarurat/
│
├── index.php                 # Halaman beranda utama publik
├── bantuan.php               # Form pengajuan bantuan publik
├── status.php                # Cek status dan timeline publik
├── kontak.php                # Pencarian kontak resmi publik
├── login.php                 # Halaman masuk admin
├── logout.php                # Halaman keluar admin
│
├── admin/
│   ├── dashboard.php         # Ringkasan data & statistik admin
│   ├── requests.php          # Manajemen & filter daftar permintaan
│   ├── request-detail.php    # Detail permintaan, verifikasi & bantuan
│   ├── verification.php      # Halaman log seluruh verifikasi admin
│   ├── assistance.php        # Halaman log seluruh riwayat bantuan
│   ├── contacts.php          # CRUD kelola data kontak resmi
│   └── profile.php           # Pengaturan nama & sandi admin
│
├── config/
│   └── database.php          # File konfigurasi koneksi database PDO
│
├── includes/
│   ├── auth.php              # Proteksi session & penanganan CSRF
│   ├── functions.php         # Utility (sanitasi, skor risiko, upload)
│   ├── header.php            # Kerangka navigasi atas (Bootstrap 5)
│   └── footer.php            # Kerangka kaki halaman & disclaimer
│
├── assets/
│   ├── css/
│   │   └── styles.css        # Desain custom modern & responsif
│   └── js/
│
├── uploads/                  # Folder penyimpanan berkas terunggah
│
├── database.sql              # Skema database MySQL & data dummy
└── README.md                 # Dokumentasi panduan sistem
```

---

## Konfigurasi Database (Jika Diperlukan)

Koneksi database dikonfigurasi melalui file [database.php](file:///C:/xampp/htdocs/projectnasidarurat/config/database.php).
Jika Anda menggunakan username/password MySQL yang berbeda di luar default XAMPP, silakan sesuaikan variabel berikut di dalam file tersebut:

```php
$host = 'localhost';
$db   = 'daruratcheck';
$user = 'root';    // Ganti dengan username MySQL Anda
$pass = '';        // Ganti dengan password MySQL Anda
```

---

## Alur Skenario Testing (Uji Coba Sistem)

Untuk menguji seluruh fungsionalitas sistem dari awal hingga akhir, silakan ikuti langkah berikut:

1. **Tahap Pengajuan Bantuan:**
   * Buka `http://localhost/projectnasidarurat/bantuan.php`.
   * Masukkan data: Nama `Andi`, Nomor `081234567890`, Lokasi `Tangerang`, Kebutuhan `Sembako`, deskripsi penjelasan, lalu kirim.
   * Pastikan sistem menghasilkan ID Permintaan baru (misal: `ND-20260816-0004`) dan menampilkan pesan sukses.
2. **Tahap Login Admin:**
   * Buka `http://localhost/projectnasidarurat/login.php` lalu masuk menggunakan akun `admin` / `Admin123!`.
3. **Pemeriksaan Permintaan Baru:**
   * Buka Dashboard Admin. Anda akan melihat pertambahan statistik Permintaan Baru.
   * Buka tabel di menu **Permintaan** lalu cari nama `Andi` atau klik tombol **Detail** pada pengajuan baru tersebut.
4. **Proses Verifikasi:**
   * Pada detail permintaan, klik tombol **Mulai Verifikasi** (Status berubah menjadi *SEDANG DIVERIFIKASI*).
   * Gunakan tombol **Tambah Catatan Verifikasi**. Pilih metode `Video call`, hasil `Valid`, tulis catatan pendukung, lalu simpan.
   * Status permintaan secara otomatis akan ter-update menjadi *TERVERIFIKASI*.
5. **Penilaian Risiko (Risk Score):**
   * Di panel kanan detail permintaan, ubah checkbox indikator keselamatan/risiko (misal: centang "Lokasi konsisten...", "Verifikasi komunikasi sukses...").
   * Klik tombol **Update Indikator**. Nilai Risk Score akan turun drastis (semakin kecil, semakin aman).
6. **Persetujuan & Penyaluran Bantuan:**
   * Klik tombol status **Setujui Bantuan** (Status berubah menjadi *DISETUJUI*).
   * Isi form **Catat Penyaluran Bantuan** (masukkan nama relawan, nilai bantuan, dan unggah foto dokumentasi opsional). Kirim form.
   * Status pengajuan akan otomatis berakhir di status *SELESAI*.
7. **Pengecekan Status Publik & Audit Log:**
   * Buka kembali halaman publik Cek Status di `http://localhost/projectnasidarurat/status.php` dan masukkan ID pengajuan tadi. Pastikan timeline riwayat dan perubahan status tercatat lengkap dengan data pribadi yang disamarkan.
   * Masuk ke dashboard admin pada menu **Audit Log** untuk memastikan seluruh aktivitas perubahan data yang dilakukan admin tercatat secara mendetail di database.
