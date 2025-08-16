<?php
// Test photo upload functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Photo Upload Test</h1>";

// Test the handlePhotoUpload function
function handlePhotoUpload($file) {
    $upload_dir = 'assets/img/members/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size too large. Maximum size is 10MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('member_', true) . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => $file_path];
    } else {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }
}

if ($_POST && isset($_FILES['test_photo'])) {
    echo "<h2>Testing Photo Upload</h2>";
    
    $result = handlePhotoUpload($_FILES['test_photo']);
    
    if ($result['success']) {
        echo "<div style='color: green;'>✓ Photo uploaded successfully!</div>";
        echo "<p>File saved to: " . htmlspecialchars($result['file_path']) . "</p>";
        echo "<img src='" . htmlspecialchars($result['file_path']) . "' style='max-width: 200px; border: 1px solid #ccc;'>";
    } else {
        echo "<div style='color: red;'>✗ Upload failed: " . htmlspecialchars($result['error']) . "</div>";
    }
} else {
    echo "<h2>Test Photo Upload</h2>";
    echo "<form method='POST' enctype='multipart/form-data'>";
    echo "<div style='margin: 20px 0;'>";
    echo "<label for='test_photo'>Select a photo to test upload:</label><br>";
    echo "<input type='file' name='test_photo' id='test_photo' accept='image/*' required>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'>Test Upload</button>";
    echo "</form>";
}

echo "<br><a href='family-tree.php'>Back to Family Tree</a>";
?>
