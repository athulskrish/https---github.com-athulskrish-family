<?php
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/db.php';

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_message'] = 'Please log in to access this page.';
        $_SESSION['flash_type'] = 'warning';
        redirect_to('login.php');
    }
}

function login_user($user_id, $remember = false) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['login_time'] = time();
    
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Store remember me token
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, password_hash($token, PASSWORD_DEFAULT), $expires]);
        
        // Set secure cookie
        setcookie(
            'remember_token',
            $token,
            strtotime('+30 days'),
            '/',
            '',
            true,    // Secure
            true     // HttpOnly
        );
    }
}

function logout_user() {
    // Clear remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Clear session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, username, email, full_name, status, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function check_remember_me() {
    if (!isset($_COOKIE['remember_token']) || is_logged_in()) {
        return;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT user_id, token 
        FROM remember_tokens 
        WHERE expires_at > NOW() 
        AND user_id = ? 
        LIMIT 1
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token && password_verify($_COOKIE['remember_token'], $token['token'])) {
        login_user($token['user_id']);
    }
}

// Check for remember me token on every page load
check_remember_me();
