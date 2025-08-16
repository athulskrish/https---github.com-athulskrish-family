<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
redirect_if_not_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = "Invalid request.";
    $_SESSION['flash_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

$person_id = (int)$_POST['person_id'];

// Verify user has access to add media for this person
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT p.*, ft.owner_id,
           CASE 
               WHEN ft.owner_id = ? THEN 'owner'
               WHEN ts.permission_level IN ('admin', 'edit') THEN ts.permission_level
               ELSE NULL
           END as access_level
    FROM people p
    JOIN family_trees ft ON p.tree_id = ft.id
    LEFT JOIN tree_sharing ts ON ft.id = ts.tree_id AND ts.user_id = ?
    WHERE p.id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $person_id]);
$person = $stmt->fetch();

if (!$person || !in_array($person['access_level'], ['owner', 'admin', 'edit'])) {
    $_SESSION['flash_message'] = "You don't have permission to add media for this person.";
    $_SESSION['flash_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

try {
    $conn->beginTransaction();

    // Validate media type
    $media_type = sanitize_input($_POST['media_type']);
    if (!in_array($media_type, ['photo', 'document', 'video'])) {
        throw new Exception("Invalid media type.");
    }

    // Handle file upload
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error uploading file.");
    }

    // Define allowed mime types for each media type
    $allowed_types = [
        'photo' => ['image/jpeg', 'image/png', 'image/gif'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'video' => ['video/mp4', 'video/quicktime', 'video/x-msvideo']
    ];

    // Verify file type
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $_FILES['media_file']['tmp_name']);
    finfo_close($file_info);

    if (!in_array($mime_type, $allowed_types[$media_type])) {
        throw new Exception("Invalid file type for selected media type.");
    }

    // Verify file size (limit to 10MB for photos/documents, 50MB for videos)
    $max_size = ($media_type === 'video') ? 52428800 : 10485760;
    if ($_FILES['media_file']['size'] > $max_size) {
        throw new Exception("File size exceeds limit.");
    }

    // Create media directory if it doesn't exist
    $media_dir = 'assets/media/' . $media_type . 's';
    if (!file_exists($media_dir)) {
        mkdir($media_dir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION);
    $filename = uniqid($media_type . '_', true) . '.' . $extension;
    $upload_path = $media_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
        throw new Exception("Failed to save uploaded file.");
    }

    // Create thumbnail for photos
    $thumbnail_url = null;
    if ($media_type === 'photo') {
        $thumb_dir = 'assets/media/thumbnails';
        if (!file_exists($thumb_dir)) {
            mkdir($thumb_dir, 0755, true);
        }
        
        $thumbnail_path = $thumb_dir . '/thumb_' . $filename;
        
        // Create thumbnail using GD library
        $source = null;
        switch ($mime_type) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($upload_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($upload_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($upload_path);
                break;
        }
        
        if ($source) {
            $width = imagesx($source);
            $height = imagesy($source);
            $thumb_width = 200;
            $thumb_height = floor($height * ($thumb_width / $width));
            
            $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
            
            // Preserve transparency for PNG images
            if ($mime_type === 'image/png') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }
            
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, 
                             $thumb_width, $thumb_height, $width, $height);
            
            switch ($mime_type) {
                case 'image/jpeg':
                    imagejpeg($thumbnail, $thumbnail_path, 85);
                    break;
                case 'image/png':
                    imagepng($thumbnail, $thumbnail_path, 8);
                    break;
                case 'image/gif':
                    imagegif($thumbnail, $thumbnail_path);
                    break;
            }
            
            imagedestroy($source);
            imagedestroy($thumbnail);
            
            $thumbnail_url = $thumbnail_path;
        }
    }

    // Insert media record
    $stmt = $conn->prepare("
        INSERT INTO media (
            person_id, media_type, file_url, thumbnail_url,
            title, description
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $person_id,
        $media_type,
        $upload_path,
        $thumbnail_url,
        sanitize_input($_POST['title']),
        sanitize_input($_POST['description'])
    ]);

    $conn->commit();

    $_SESSION['flash_message'] = "Media uploaded successfully!";
    $_SESSION['flash_type'] = "success";

} catch (Exception $e) {
    $conn->rollBack();
    
    // Delete uploaded file if it exists
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    // Delete thumbnail if it exists
    if (isset($thumbnail_url) && file_exists($thumbnail_url)) {
        unlink($thumbnail_url);
    }

    $_SESSION['flash_message'] = "Error uploading media: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
}

// Redirect back to member page
header("Location: member.php?id=" . $person_id);
exit();
