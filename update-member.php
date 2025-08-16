<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
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

$member_id = (int)$_POST['member_id'];

// Verify user has access to edit this member
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
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $member_id]);
$member = $stmt->fetch();

if (!$member || !in_array($member['access_level'], ['owner', 'admin', 'edit'])) {
    $_SESSION['flash_message'] = "You don't have permission to edit this member.";
    $_SESSION['flash_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

try {
    $conn->beginTransaction();

    // Handle photo upload if present
    $photo_url = $member['photo_url'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['photo']['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Invalid file type. Only JPEG, PNG, and GIF images are allowed.");
        }

        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('member_', true) . '.' . $extension;
        $upload_path = 'assets/img/members/' . $filename;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
            throw new Exception("Failed to upload photo.");
        }

        // Delete old photo if exists
        if ($member['photo_url'] && file_exists($member['photo_url'])) {
            unlink($member['photo_url']);
        }

        $photo_url = $upload_path;
    }

    // Update member information
    $stmt = $conn->prepare("
        UPDATE people SET
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            gender = ?,
            date_of_birth = ?,
            birth_place = ?,
            date_of_death = ?,
            death_place = ?,
            photo_url = ?,
            notes = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([
        sanitize_input($_POST['first_name']),
        sanitize_input($_POST['middle_name']),
        sanitize_input($_POST['last_name']),
        sanitize_input($_POST['gender']),
        !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
        sanitize_input($_POST['birth_place']),
        !empty($_POST['date_of_death']) ? $_POST['date_of_death'] : null,
        sanitize_input($_POST['death_place']),
        $photo_url,
        sanitize_input($_POST['notes']),
        $member_id
    ]);

    $conn->commit();

    $_SESSION['flash_message'] = "Member information updated successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: member.php?id=" . $member_id);
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    
    // Delete newly uploaded photo if it exists and is different from the original
    if ($photo_url && $photo_url !== $member['photo_url'] && file_exists($photo_url)) {
        unlink($photo_url);
    }

    $_SESSION['flash_message'] = "Error updating member: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    header("Location: member.php?id=" . $member_id);
    exit();
}
