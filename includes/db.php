<?php
require_once 'config.php';
require_once 'security.php';

class Database {
    private $conn = null;
    private static $instance = null;
    private $encrypt_key;
    
    public function __construct() {
        $this->encrypt_key = getenv('DB_ENCRYPT_KEY') ?: DB_ENCRYPT_KEY;
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_PERSISTENT => true
            ];
            
            // Add SSL options if certificates are configured
            if (defined('DB_SSL_CA') && DB_SSL_CA) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
            
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                $options
            );
            
            // Set additional security measures
            $this->conn->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
            $this->conn->exec("SET SESSION time_zone = '+00:00'");
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function encryptSensitiveData($data) {
        return encrypt_sensitive_data($data, $this->encrypt_key);
    }
    
    public function decryptSensitiveData($encrypted_data) {
        return decrypt_sensitive_data($encrypted_data, $this->encrypt_key);
    }
}
