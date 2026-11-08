<?php
// includes/functions.php

/**
 * Sanitizes input text to prevent XSS attacks
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validates a WhatsApp number format (Indonesian standard)
 */
function validate_whatsapp($number) {
    // Remove non-numeric characters
    $number = preg_replace('/[^0-9]/', '', $number);
    // Replace leading '0' with '62'
    if (strpos($number, '0') === 0) {
        $number = '62' . substr($number, 1);
    }
    // Check length (typically 10-15 digits)
    if (strlen($number) >= 10 && strlen($number) <= 15) {
        return $number;
    }
    return false;
}

/**
 * Log admin activities to the audit_logs table
 */
function log_activity($pdo, $admin_id, $activity, $data_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $sql = "INSERT INTO audit_logs (admin_id, activity, data_id, ip_address) VALUES (:admin_id, :activity, :data_id, :ip_address)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'admin_id' => $admin_id,
            'activity' => $activity,
            'data_id' => $data_id,
            'ip_address' => $ip_address
        ]);
    } catch (\PDOException $e) {
        // Silently fail or log to error log
        error_log("Failed to insert audit log: " . $e->getMessage());
    }
}

/**
 * Generate a unique sequential request code: ND-YYYYMMDD-XXXX
 */
function generate_request_code($pdo) {
    $dateStr = date('Ymd');
    $prefix = "ND-" . $dateStr . "-";
    
    // Find the latest request code generated today
    $sql = "SELECT request_code FROM requests WHERE request_code LIKE :pattern ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['pattern' => $prefix . '%']);
    $lastRequest = $stmt->fetch();
    
    if ($lastRequest) {
        $parts = explode('-', $lastRequest['request_code']);
        $lastSeq = intval(end($parts));
        $newSeq = $lastSeq + 1;
    } else {
        $newSeq = 1;
    }
    
    return $prefix . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate Risk Score (0-100) and return score, category, and reasons
 */
function calculate_risk_score($request) {
    $base_risk = 100;
    
    // Define indicator values (1 or 0)
    $safe_new_wa = intval($request['safe_new_wa'] ?? 0);
    $safe_consistent_location = intval($request['safe_consistent_location'] ?? 0);
    $safe_comm_verified = intval($request['safe_comm_verified'] ?? 0);
    $safe_valid_evidence = intval($request['safe_valid_evidence'] ?? 0);
    $safe_no_suspicious_hist = intval($request['safe_no_suspicious_hist'] ?? 0);
    
    $risk_multi_request = intval($request['risk_multi_request'] ?? 0);
    $risk_data_changes = intval($request['risk_data_changes'] ?? 0);
    $risk_inconsistent_location = intval($request['risk_inconsistent_location'] ?? 0);
    $risk_refuse_verification = intval($request['risk_refuse_verification'] ?? 0);
    $risk_repeated_pattern = intval($request['risk_repeated_pattern'] ?? 0);
    
    // Compute totals
    $safety_score = ($safe_new_wa + $safe_consistent_location + $safe_comm_verified + $safe_valid_evidence + $safe_no_suspicious_hist) * 20;
    $risk_score_add = ($risk_multi_request + $risk_data_changes + $risk_inconsistent_location + $risk_refuse_verification + $risk_repeated_pattern) * 20;
    
    $final_score = $base_risk - $safety_score + $risk_score_add;
    
    // Clamp between 0 and 100
    $final_score = max(0, min(100, $final_score));
    
    // Categorize
    if ($final_score <= 30) {
        $category = 'Rendah';
    } elseif ($final_score <= 70) {
        $category = 'Sedang';
    } else {
        $category = 'Tinggi';
    }
    
    // Collect explanations
    $reasons = [];
    
    // Trust factors
    if ($safe_new_wa) $reasons[] = ["type" => "safe", "text" => "WhatsApp tidak memiliki catatan bermasalah (-20%)"];
    if ($safe_consistent_location) $reasons[] = ["type" => "safe", "text" => "Lokasi pemohon terverifikasi konsisten (-20%)"];
    if ($safe_comm_verified) $reasons[] = ["type" => "safe", "text" => "Komunikasi dua arah berhasil dan kooperatif (-20%)"];
    if ($safe_valid_evidence) $reasons[] = ["type" => "safe", "text" => "Bukti foto/dokumen kebutuhan valid (-20%)"];
    if ($safe_no_suspicious_hist) $reasons[] = ["type" => "safe", "text" => "Tidak ditemukan riwayat pengajuan mencurigakan (-20%)"];
    
    // Risk factors
    if ($risk_multi_request) $reasons[] = ["type" => "risk", "text" => "Nomor WhatsApp mengajukan beberapa bantuan dalam waktu singkat (+20%)"];
    if ($risk_data_changes) $reasons[] = ["type" => "risk", "text" => "Terdapat perubahan data pemohon yang sering (+20%)"];
    if ($risk_inconsistent_location) $reasons[] = ["type" => "risk", "text" => "Informasi lokasi pengajuan tidak konsisten (+20%)"];
    if ($risk_refuse_verification) $reasons[] = ["type" => "risk", "text" => "Pemohon menolak/menghindari proses verifikasi (+20%)"];
    if ($risk_repeated_pattern) $reasons[] = ["type" => "risk", "text" => "Ditemukan pola pengajuan berulang atau terindikasi spam (+20%)"];
    
    // If no indicators checked at all
    if (empty($reasons)) {
        $reasons[] = ["type" => "neutral", "text" => "Belum ada indikator yang diperiksa. Risiko default awal."];
    }
    
    return [
        'score' => $final_score,
        'category' => $category,
        'reasons' => $reasons
    ];
}

/**
 * Handle secure file upload
 * Returns file path or false on failure
 */
function upload_file($file, $target_dir = "../uploads/") {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate extension
    if (!in_array($extension, $allowed_extensions)) {
        return false;
    }
    
    // Validate size
    if ($file_size > $max_size) {
        return false;
    }
    
    // Validate actual file type using MIME check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime_type, $allowed_mimes)) {
        return false;
    }
    
    // Create target dir if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Generate randomized unique filename
    $new_filename = uniqid('doc_', true) . '.' . $extension;
    $target_path = rtrim($target_dir, '/') . '/' . $new_filename;
    
    if (move_uploaded_file($file_tmp, $target_path)) {
        // Return relative path from webroot (always save relative to base)
        // If target_dir has '../uploads/', the path saved in DB will be 'uploads/filename'
        return 'uploads/' . $new_filename;
    }
    
    return false;
}

/**
 * Helper to display Badge color based on status
 */
function get_status_badge($status) {
    switch ($status) {
        case 'BARU':
            return 'bg-info text-dark';
        case 'SEDANG DIVERIFIKASI':
            return 'bg-warning text-dark';
        case 'TERVERIFIKASI':
            return 'bg-primary';
        case 'DISETUJUI':
            return 'bg-secondary';
        case 'BANTUAN DISALURKAN':
            return 'bg-indigo text-light';
        case 'SELESAI':
            return 'bg-success';
        case 'DITOLAK':
            return 'bg-danger';
        default:
            return 'bg-light text-dark';
    }
}

/**
 * Helper to display Badge color for Risk Category
 */
function get_risk_badge($category) {
    switch ($category) {
        case 'Rendah':
            return 'bg-success';
        case 'Sedang':
            return 'bg-warning text-dark';
        case 'Tinggi':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

/**
 * Helper to format Rupiah currency
 */
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

/**
 * Helper to format date indonesian style
 */
function format_date_id($date_str) {
    if (!$date_str) return '-';
    $timestamp = strtotime($date_str);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $day = date('d', $timestamp);
    $month = $months[intval(date('m', $timestamp))];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    return "$day $month $year $time";
}
?>
