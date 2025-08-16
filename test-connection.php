<?php
// Simple test script to debug the application
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Family Tree Application Test</h1>";

// Test 1: Check if session is working
echo "<h2>Test 1: Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Test 2: Check if config is loading
echo "<h2>Test 2: Config Test</h2>";
try {
    require_once 'includes/config.php';
    echo "✓ Config loaded successfully<br>";
    echo "BASE_URL: " . BASE_URL . "<br>";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
}

// Test 3: Check database connection
echo "<h2>Test 3: Database Test</h2>";
try {
    require_once 'includes/db.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✓ Database connected successfully<br>";
    
    // Test a simple query
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✓ Database query test: " . $result['test'] . "<br>";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Test 4: Check authentication
echo "<h2>Test 4: Authentication Test</h2>";
try {
    require_once 'includes/auth.php';
    echo "✓ Auth loaded successfully<br>";
    echo "Is logged in: " . (is_logged_in() ? 'Yes' : 'No') . "<br>";
} catch (Exception $e) {
    echo "✗ Auth error: " . $e->getMessage() . "<br>";
}

// Test 5: Check functions
echo "<h2>Test 5: Functions Test</h2>";
try {
    require_once 'includes/functions.php';
    echo "✓ Functions loaded successfully<br>";
} catch (Exception $e) {
    echo "✗ Functions error: " . $e->getMessage() . "<br>";
}

// Test 6: Check if user is logged in and show user info
echo "<h2>Test 6: User Info</h2>";
if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user) {
        echo "✓ User logged in: " . htmlspecialchars($user['username']) . "<br>";
        echo "User ID: " . $user['id'] . "<br>";
        echo "Full Name: " . htmlspecialchars($user['full_name']) . "<br>";
    } else {
        echo "✗ User logged in but couldn't get user data<br>";
    }
} else {
    echo "User not logged in<br>";
    echo "<a href='login.php'>Go to Login</a><br>";
}

// Test 7: Check if family tree tables exist
echo "<h2>Test 7: Database Tables Test</h2>";
try {
    $tables = ['users', 'family_trees', 'people', 'relationships', 'tree_access'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Table '$table' exists<br>";
        } else {
            echo "✗ Table '$table' missing<br>";
        }
    }
} catch (Exception $e) {
    echo "✗ Table check error: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";
echo "<p>If you see any ✗ marks above, those need to be fixed before the application will work properly.</p>";
?>
