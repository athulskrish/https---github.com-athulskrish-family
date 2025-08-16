<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Ensure user is logged in
redirect_if_not_logged_in();

$response = ['success' => false, 'message' => ''];

// Check if it's a file upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media'])) {
    // Check rate limit for uploads
    check_rate_limit('upload');
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $response['message'] = 'Invalid request';
        echo json_encode($response);
        exit;
    }

    $file = $_FILES['media'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'application/pdf'];
    $maxSize = 10 * 1024 * 1024; // 10MB limit

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
        $response['message'] = 'Invalid file type. Allowed types: JPG, PNG, GIF, MP4, PDF';
    } elseif ($file['size'] > $maxSize) {
        $response['message'] = 'File size too large. Maximum size: 10MB';
    } else {
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = 'uploads/' . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Save to database
            $stmt = $db->prepare("INSERT INTO media (user_id, file_path, file_type, title) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([
                $_SESSION['user_id'],
                $uploadPath,
                $file['type'],
                sanitize_input($_POST['title'] ?? $file['name'])
            ])) {
                $response['success'] = true;
                $response['message'] = 'File uploaded successfully';
                $response['file_id'] = $db->lastInsertId();
            } else {
                $response['message'] = 'Failed to save file information';
                // Clean up uploaded file
                unlink($uploadPath);
            }
        } else {
            $response['message'] = 'Failed to upload file';
        }
    }
} else {
    $response['message'] = 'No file uploaded';
}

header('Content-Type: application/json');
echo json_encode($response);
