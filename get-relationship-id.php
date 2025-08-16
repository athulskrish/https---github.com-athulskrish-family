<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/auth.php';
// Check if user is logged in
require_login();

header('Content-Type: application/json');

$member_id = (int)($_GET['member_id'] ?? 0);
$related_id = (int)($_GET['related_id'] ?? 0);
$type = $_GET['type'] ?? '';

if (!$member_id || !$related_id || !$type) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify user has access to this member
    $stmt = $conn->prepare("
        SELECT p.tree_id 
        FROM people p 
        WHERE p.id = ?
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member || !can_access_tree($member['tree_id'])) {
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Map relationship types to subtypes for searching
    $type_mapping = [
        'parent' => ['father', 'mother', 'step-father', 'step-mother'],
        'child' => ['son', 'daughter', 'step-son', 'step-daughter'],
        'sibling' => ['brother', 'sister', 'half-brother', 'half-sister'],
        'spouse' => ['husband', 'wife', 'ex-husband', 'ex-wife'],
        'grandparent' => ['grandfather', 'grandmother'],
        'grandchild' => ['grandson', 'granddaughter']
    ];
    
    $subtypes = $type_mapping[$type] ?? [$type];
    
    // Find the relationship
    $placeholders = str_repeat('?,', count($subtypes) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT id 
        FROM relationships 
        WHERE person1_id = ? AND person2_id = ? AND relationship_subtype IN ($placeholders)
        LIMIT 1
    ");
    
    $params = array_merge([$member_id, $related_id], $subtypes);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode(['relationship_id' => $result['id']]);
    } else {
        echo json_encode(['error' => 'Relationship not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
