<?php
// Test form submission
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Form Test</h1>";

if ($_POST) {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Test the addFamilyMember function
    try {
        require_once 'includes/config.php';
        require_once 'includes/db.php';
        require_once 'includes/functions.php';
        require_once 'includes/auth.php';
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Mock user session for testing
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = 1;
            $_SESSION['current_tree_id'] = 1;
        }
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Include the family tree functions
        require_once 'family-tree.php';
        
        echo "<h2>Testing addFamilyMember function:</h2>";
        $result = addFamilyMember($_POST, $conn);
        
        if ($result['success']) {
            echo "<div style='color: green;'>✓ Success! Member ID: " . $result['member_id'] . "</div>";
        } else {
            echo "<div style='color: red;'>✗ Error: " . $result['error'] . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ Exception: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<h2>No POST data received</h2>";
    echo "<p>This script tests form submission. Submit the form from family-tree.php to see the results.</p>";
}

echo "<br><a href='family-tree.php'>Back to Family Tree</a>";
?>
