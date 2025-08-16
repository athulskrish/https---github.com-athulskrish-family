<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
redirect_if_not_logged_in();

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Media ID not provided']));
}

$media_id = (int)$_GET['id'];

$db = new Database();
$conn = $db->getConnection();

// Get media with access check
$stmt = $conn->prepare("
    SELECT m.*, p.tree_id,
           CASE 
               WHEN ft.owner_id = ? THEN 'owner'
               WHEN ts.permission_level IS NOT NULL THEN ts.permission_level
               WHEN ft.privacy_level = 'public' THEN 'view'
               ELSE NULL
           END as access_level
    FROM media m
    JOIN people p ON m.person_id = p.id
    JOIN family_trees ft ON p.tree_id = ft.id
    LEFT JOIN tree_sharing ts ON ft.id = ts.tree_id AND ts.user_id = ?
    WHERE m.id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $media_id]);
$media = $stmt->fetch();

if (!$media || !$media['access_level']) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

// Return media details
header('Content-Type: application/json');
echo json_encode([
    'id' => $media['id'],
    'media_type' => $media['media_type'],
    'file_url' => $media['file_url'],
    'thumbnail_url' => $media['thumbnail_url'],
    'title' => $media['title'],
    'description' => $media['description'],
    'upload_date' => $media['upload_date']
]);
