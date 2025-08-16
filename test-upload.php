<?php
// Simple test file to check upload functionality
echo "<h2>Upload Directory Test</h2>";

$upload_dir = 'assets/img/members/';

echo "<p><strong>Upload directory:</strong> $upload_dir</p>";
echo "<p><strong>Directory exists:</strong> " . (is_dir($upload_dir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Directory writable:</strong> " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";

if (is_dir($upload_dir)) {
    echo "<p><strong>Directory permissions:</strong> " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "</p>";
}

// Test file creation
$test_file = $upload_dir . 'test.txt';
if (file_put_contents($test_file, 'test') !== false) {
    echo "<p><strong>Test file creation:</strong> Success</p>";
    unlink($test_file); // Clean up
} else {
    echo "<p><strong>Test file creation:</strong> Failed</p>";
}

// PHP upload settings
echo "<h3>PHP Upload Settings</h3>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</p>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";

// Test form
echo "<h3>Test Upload Form</h3>";
echo '<form action="test-upload.php" method="POST" enctype="multipart/form-data">';
echo '<input type="file" name="test_file" accept="image/*">';
echo '<input type="submit" value="Test Upload">';
echo '</form>';

// Handle test upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Results</h3>";
    $file = $_FILES['test_file'];
    
    echo "<p><strong>File name:</strong> " . htmlspecialchars($file['name']) . "</p>";
    echo "<p><strong>File size:</strong> " . $file['size'] . " bytes</p>";
    echo "<p><strong>Upload error:</strong> " . $file['error'] . "</p>";
    echo "<p><strong>Temporary file:</strong> " . $file['tmp_name'] . "</p>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = 'test_' . uniqid() . '_' . basename($file['name']);
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo "<p><strong>Upload result:</strong> Success - File saved as $upload_path</p>";
            // Clean up
            unlink($upload_path);
        } else {
            echo "<p><strong>Upload result:</strong> Failed to move uploaded file</p>";
            $error = error_get_last();
            echo "<p><strong>Error:</strong> " . ($error['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        echo "<p><strong>Upload error:</strong> " . ($upload_errors[$file['error']] ?? 'Unknown error') . "</p>";
    }
}
?> 