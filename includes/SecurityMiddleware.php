<?php
require_once 'config.php';
require_once 'security.php';

class SecurityMiddleware {
    public static function apply() {
        // Apply security headers
        set_security_headers();
        
        // Ensure HTTPS in production
        self::enforceHttps();
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Enable strict transport security
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        
        // Check for suspicious input
        self::validateInput();
    }
    
    private static function enforceHttps() {
        if (getenv('APP_ENV') === 'production' && 
            (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit();
        }
    }
    
    private static function validateInput() {
        $input = array_merge($_GET, $_POST);
        foreach ($input as $key => $value) {
            if (self::containsSuspiciousPatterns($value)) {
                error_log("Suspicious input detected: " . $key);
                header("HTTP/1.1 400 Bad Request");
                exit("Invalid input detected");
            }
        }
    }
    
    private static function containsSuspiciousPatterns($value) {
        if (!is_string($value)) return false;
        
        $patterns = [
            '/(<|%3C)script/i',              // XSS attempts
            '/javascript:/i',                // JavaScript injection
            '/data:\s*\w+\/[\w+-]+;/i',     // Data URI schemes
            '/vbscript:/i',                 // VBScript injection
            '/onload\s*=/i',                // Event handler injection
            '/document\.cookie/i',           // Cookie stealing attempts
            '/eval\s*\(/i',                 // Eval injection
            '/union\s+select/i',            // SQL injection attempts
            '/exec\s*\(/i',                 // Command injection
            '/base64_/i',                   // Base64 injection attempts
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function validatePassword($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        if (REQUIRE_PASSWORD_COMPLEXITY) {
            // Check for at least one uppercase letter
            if (!preg_match('/[A-Z]/', $password)) return false;
            
            // Check for at least one lowercase letter
            if (!preg_match('/[a-z]/', $password)) return false;
            
            // Check for at least one number
            if (!preg_match('/[0-9]/', $password)) return false;
            
            // Check for at least one special character
            if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
        }
        
        return true;
    }
    
    public static function checkRateLimit($type = 'default', $identifier = null) {
        require_once 'RateLimit.php';
        $limiter = new RateLimit();
        
        $identifier = $identifier ?? $_SERVER['REMOTE_ADDR'];
        
        switch ($type) {
            case 'login':
                return $limiter->check($identifier, 'login', MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);
            case 'upload':
                return $limiter->check($identifier, 'upload', 10, 3600); // 10 uploads per hour
            case 'api':
                return $limiter->check($identifier, 'api', 100, 3600); // 100 requests per hour
            default:
                return $limiter->check($identifier, 'default', 1000, 3600); // 1000 requests per hour
        }
    }
}
