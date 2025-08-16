<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

// Check if user is logged in
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = "Invalid request.";
    $_SESSION['flash_type'] = "error";
    redirect_to('dashboard.php');
}

$action = $_POST['action'] ?? '';
$member_id = (int)($_POST['member_id'] ?? 0);

if (!$member_id) {
    $_SESSION['flash_message'] = "Invalid member ID.";
    $_SESSION['flash_type'] = "error";
    redirect_to('dashboard.php');
}

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Verify user has access to edit this member
$stmt = $conn->prepare("
    SELECT p.*, ft.owner_id
    FROM people p
    JOIN family_trees ft ON p.tree_id = ft.id
    WHERE p.id = ?
");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member || !can_access_tree($member['tree_id'])) {
    $_SESSION['flash_message'] = "You don't have permission to edit this member's relationships.";
    $_SESSION['flash_type'] = "error";
    redirect_to('dashboard.php');
}

try {
    $conn->beginTransaction();
    
    switch ($action) {
        case 'add_relationship':
            addNewRelationship($conn, $_POST);
            break;
            
        case 'update_relationship':
            updateExistingRelationship($conn, $_POST);
            break;
            
        case 'delete_relationship':
            deleteRelationship($conn, $_POST);
            break;
            
        default:
            throw new Exception("Invalid action.");
    }
    
    $conn->commit();
    $_SESSION['flash_message'] = "Relationship updated successfully!";
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = "Error updating relationship: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

redirect_to('member.php?id=' . $member_id);

function addNewRelationship($conn, $data) {
    $member_id = (int)$data['member_id'];
    $related_member_id = (int)$data['related_member_id'];
    $relationship_subtype = $data['relationship_subtype'];
    $marriage_date = !empty($data['marriage_date']) ? $data['marriage_date'] : null;
    $marriage_place = !empty($data['marriage_place']) ? $data['marriage_place'] : null;
    
    if (!$related_member_id || !$relationship_subtype) {
        throw new Exception("Related member and relationship type are required.");
    }
    
    // Check if relationship already exists
    $stmt = $conn->prepare("
        SELECT id FROM relationships 
        WHERE ((person1_id = ? AND person2_id = ?) OR (person1_id = ? AND person2_id = ?))
        AND relationship_subtype = ?
    ");
    $stmt->execute([$member_id, $related_member_id, $related_member_id, $member_id, $relationship_subtype]);
    
    if ($stmt->fetch()) {
        throw new Exception("This relationship already exists.");
    }
    
    // Get relationship category from FamilyRelationships class
    require_once 'family-tree.php'; // Load the FamilyRelationships class
    $relationship_category = FamilyRelationships::getRelationshipCategory($relationship_subtype);
    
    // Add the main relationship
    $stmt = $conn->prepare("
        INSERT INTO relationships (person1_id, person2_id, relationship_type, relationship_subtype, marriage_date, marriage_place)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$member_id, $related_member_id, $relationship_category, $relationship_subtype, $marriage_date, $marriage_place]);
    
    // Add reciprocal relationship if needed
    $reciprocal = FamilyRelationships::getReciprocalRelationship($relationship_subtype);
    if ($reciprocal) {
        $reciprocal_category = FamilyRelationships::getRelationshipCategory($reciprocal);
        $stmt->execute([$related_member_id, $member_id, $reciprocal_category, $reciprocal, $marriage_date, $marriage_place]);
    }
}

function updateExistingRelationship($conn, $data) {
    $relationship_id = (int)$data['relationship_id'];
    $marriage_date = !empty($data['marriage_date']) ? $data['marriage_date'] : null;
    $marriage_place = !empty($data['marriage_place']) ? $data['marriage_place'] : null;
    $divorce_date = !empty($data['divorce_date']) ? $data['divorce_date'] : null;
    
    if (!$relationship_id) {
        throw new Exception("Relationship ID is required.");
    }
    
    $stmt = $conn->prepare("
        UPDATE relationships 
        SET marriage_date = ?, marriage_place = ?, divorce_date = ?
        WHERE id = ?
    ");
    $stmt->execute([$marriage_date, $marriage_place, $divorce_date, $relationship_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Relationship not found or no changes made.");
    }
}

function deleteRelationship($conn, $data) {
    $relationship_id = (int)$data['relationship_id'];
    
    if (!$relationship_id) {
        throw new Exception("Relationship ID is required.");
    }
    
    // Get the relationship details first
    $stmt = $conn->prepare("
        SELECT person1_id, person2_id, relationship_subtype
        FROM relationships 
        WHERE id = ?
    ");
    $stmt->execute([$relationship_id]);
    $relationship = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$relationship) {
        throw new Exception("Relationship not found.");
    }
    
    // Delete the main relationship
    $stmt = $conn->prepare("DELETE FROM relationships WHERE id = ?");
    $stmt->execute([$relationship_id]);
    
    // Delete reciprocal relationship if it exists
    require_once 'family-tree.php';
    $reciprocal = FamilyRelationships::getReciprocalRelationship($relationship['relationship_subtype']);
    if ($reciprocal) {
        $stmt = $conn->prepare("
            DELETE FROM relationships 
            WHERE person1_id = ? AND person2_id = ? AND relationship_subtype = ?
        ");
        $stmt->execute([$relationship['person2_id'], $relationship['person1_id'], $reciprocal]);
    }
}
?>
