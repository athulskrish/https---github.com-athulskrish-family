<?php
// Simple test script to debug family tree rendering
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Start session for testing
session_start();

// Set a test tree ID (modify as needed)
$test_tree_id = 1;
$_SESSION['current_tree_id'] = $test_tree_id;

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get family data
$family_data = getFamilyTreeData($test_tree_id, $conn);

echo "<h2>Debug Information</h2>";
echo "<h3>Members Found: " . count($family_data['members']) . "</h3>";
echo "<pre>";
foreach($family_data['members'] as $member) {
    echo "ID: {$member['id']}, Name: {$member['first_name']} {$member['last_name']}, Gender: {$member['gender']}\n";
}
echo "</pre>";

echo "<h3>Relationships Found: " . count($family_data['relationships']) . "</h3>";
echo "<pre>";
foreach($family_data['relationships'] as $rel) {
    echo "Person1: {$rel['person1_id']}, Person2: {$rel['person2_id']}, Type: {$rel['relationship_subtype']}\n";
}
echo "</pre>";

// Include minimal HTML for the tree
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Tree Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/family-tree.css">
</head>
<body style="background: #f8f9fa; padding: 20px;">
    <div class="container">
        <h1>Family Tree Test View</h1>
        
        <div class="tree-container">
            <?php 
            if(function_exists('renderFamilyTree')) {
                echo renderFamilyTree($family_data);
            } else {
                echo '<div class="alert alert-danger">Tree rendering function not found.</div>';
            }
            ?>
        </div>
    </div>
    
    <script src="assets/js/family-tree.js"></script>
</body>
</html>
