<?php
// Security headers and functions

function set_security_headers() {
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net");
    
    // XSS Protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent MIME-type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Clickjacking protection
    header("X-Frame-Options: SAMEORIGIN");
    
    // HSTS (uncomment if using HTTPS)
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Feature Policy
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

function encode_html($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validate_upload($file) {
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'application/pdf' => 'pdf'
    ];
    
    $max_size = 10 * 1024 * 1024; // 10MB
    $errors = [];
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds limit of 10MB';
    }
    
    // Check file type
    if (!isset($allowed_types[$file['type']])) {
        $errors[] = 'Invalid file type. Allowed types: JPG, PNG, GIF, MP4, PDF';
    }
    
    // Verify file extension matches mime type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        $errors[] = 'Invalid file extension';
    }
    
    // Additional checks for image files
    if (strpos($file['type'], 'image/') === 0) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = 'Invalid image file';
        }
    }
    
    return $errors;
}

function generate_secure_filename($original_name, $type) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    return uniqid('file_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
}

function encrypt_sensitive_data($data, $key) {
    $cipher = "aes-256-gcm";
    $iv_len = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($iv_len);
    $tag = "";
    
    $encrypted = openssl_encrypt(
        $data,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    
    return base64_encode($iv . $tag . $encrypted);
}

function decrypt_sensitive_data($encrypted_data, $key) {
    $cipher = "aes-256-gcm";
    $data = base64_decode($encrypted_data);
    $iv_len = openssl_cipher_iv_length($cipher);
    $tag_len = 16;
    
    $iv = substr($data, 0, $iv_len);
    $tag = substr($data, $iv_len, $tag_len);
    $ciphertext = substr($data, $iv_len + $tag_len);
    
    return openssl_decrypt(
        $ciphertext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
}
