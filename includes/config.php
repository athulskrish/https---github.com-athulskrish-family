<?php
// Force HTTPS (disabled for local development)
// Uncomment the following lines for production
/*
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
*/

// Define paths
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('TEMPLATES_PATH', ROOT_PATH . 'templates' . DIRECTORY_SEPARATOR);
define('VIEWS_PATH', ROOT_PATH . 'views' . DIRECTORY_SEPARATOR);
define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'indianjo_familytree');
define('DB_PASS', 'H92d7F4PLs7LW3jzfL6s');
define('DB_NAME', 'indianjo_familytree');

// Database SSL/TLS Configuration (uncomment and set paths for production)
// define('DB_SSL_CA', '/path/to/ca.pem');
// define('DB_SSL_KEY', '/path/to/client-key.pem');
// define('DB_SSL_CERT', '/path/to/client-cert.pem');

// Encryption key for sensitive data (change this in production!)
define('DB_ENCRYPT_KEY', bin2hex(random_bytes(32)));

// Error reporting (disable in production)
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters before starting the session
    $sessionParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $sessionParams["lifetime"],
        'path' => $sessionParams["path"],
        'domain' => $sessionParams["domain"],
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

// Base URL - Update this according to your setup
// For local development, use relative URL
// For production, use: 'https://familytree.indianjobber.com/'
define('BASE_URL', '');

// Security settings
define('HASH_COST', 12); // Increased cost for password hashing
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 12);
define('REQUIRE_PASSWORD_COMPLEXITY', true);

// Upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'video/mp4'
]);
