<?php
// Database setup script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Setup</h1>";

try {
    // Load configuration
    require_once 'includes/config.php';
    require_once 'includes/db.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "✓ Database connection established<br>";
    
    // Read and execute the database schema
    $sql_file = 'database.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|\/\*|USE|CREATE DATABASE)/', $statement)) {
                try {
                    $conn->exec($statement);
                    echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
                } catch (PDOException $e) {
                    // Ignore errors for existing tables
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "⚠ Warning: " . $e->getMessage() . "<br>";
                    }
                }
            }
        }
        
        echo "<br>✓ Database setup completed!<br>";
        
        // Create a default user if no users exist
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        
        if ($user_count == 0) {
            echo "<br><h3>Creating Default User</h3>";
            
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, status, email_verified) 
                VALUES (?, ?, ?, ?, 'active', 1)
            ");
            
            $stmt->execute(['admin', 'admin@example.com', $default_password, 'Administrator']);
            echo "✓ Default user created:<br>";
            echo "Username: admin<br>";
            echo "Password: admin123<br>";
            echo "Email: admin@example.com<br>";
        }
        
        // Create a default family tree if none exist
        $stmt = $conn->query("SELECT COUNT(*) as count FROM family_trees");
        $tree_count = $stmt->fetch()['count'];
        
        if ($tree_count == 0) {
            echo "<br><h3>Creating Default Family Tree</h3>";
            
            $stmt = $conn->prepare("
                INSERT INTO family_trees (name, description, owner_id) 
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute(['My Family Tree', 'Default family tree', 1]);
            echo "✓ Default family tree created<br>";
        }
        
    } else {
        echo "✗ Database schema file not found: $sql_file<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Setup error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='login.php'>Go to Login</a>";
?>
