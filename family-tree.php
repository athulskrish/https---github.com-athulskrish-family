<?php
// Include core files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/FamilyRelationships.php';

// Check if user is logged in
require_login();

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Enhanced FamilyRelationships class with category mapping
if (!class_exists('FamilyRelationships')) {
class FamilyRelationships {
    public static $relationships = [
        // Direct Line (Vertical)
        'parent' => [
            'father' => 'Father',
            'mother' => 'Mother',
            'step-father' => 'Step-Father',
            'step-mother' => 'Step-Mother',
            'adoptive-father' => 'Adoptive Father',
            'adoptive-mother' => 'Adoptive Mother'
        ],
        'child' => [
            'son' => 'Son',
            'daughter' => 'Daughter',
            'step-son' => 'Step-Son',
            'step-daughter' => 'Step-Daughter',
            'adoptive-son' => 'Adoptive Son',
            'adoptive-daughter' => 'Adoptive Daughter'
        ],
        'grandparent' => [
            'grandfather' => 'Grandfather',
            'grandmother' => 'Grandmother',
            'step-grandfather' => 'Step-Grandfather',
            'step-grandmother' => 'Step-Grandmother'
        ],
        'grandchild' => [
            'grandson' => 'Grandson',
            'granddaughter' => 'Granddaughter'
        ],
        'great-grandparent' => [
            'great-grandfather' => 'Great-Grandfather',
            'great-grandmother' => 'Great-Grandmother'
        ],
        'great-grandchild' => [
            'great-grandson' => 'Great-Grandson',
            'great-granddaughter' => 'Great-Granddaughter'
        ],
        
        // Siblings (Same Generation)
        'sibling' => [
            'brother' => 'Brother',
            'sister' => 'Sister',
            'half-brother' => 'Half-Brother',
            'half-sister' => 'Half-Sister',
            'step-brother' => 'Step-Brother',
            'step-sister' => 'Step-Sister'
        ],
        
        // Aunts and Uncles
        'aunt-uncle' => [
            'aunt' => 'Aunt',
            'uncle' => 'Uncle',
            'great-aunt' => 'Great-Aunt',
            'great-uncle' => 'Great-Uncle'
        ],
        
        // Cousins
        'cousin' => [
            'first-cousin' => 'First Cousin',
            'second-cousin' => 'Second Cousin',
            'third-cousin' => 'Third Cousin',
            'first-cousin-once-removed' => 'First Cousin Once Removed',
            'first-cousin-twice-removed' => 'First Cousin Twice Removed'
        ],
        
        // In-Laws
        'spouse' => [
            'husband' => 'Husband',
            'wife' => 'Wife',
            'ex-husband' => 'Ex-Husband',
            'ex-wife' => 'Ex-Wife'
        ],
        'in-law' => [
            'father-in-law' => 'Father-in-Law',
            'mother-in-law' => 'Mother-in-Law',
            'son-in-law' => 'Son-in-Law',
            'daughter-in-law' => 'Daughter-in-Law',
            'brother-in-law' => 'Brother-in-Law',
            'sister-in-law' => 'Sister-in-Law'
        ],
        
        // Nieces and Nephews
        'niece-nephew' => [
            'niece' => 'Niece',
            'nephew' => 'Nephew',
            'grand-niece' => 'Grand-Niece',
            'grand-nephew' => 'Grand-Nephew'
        ]
    ];
    
    public static function getAllRelationships() {
        $all = [];
        foreach(self::$relationships as $category => $relations) {
            $all = array_merge($all, $relations);
        }
        return $all;
    }
    
    // Get the category for a specific relationship subtype
    public static function getRelationshipCategory($subtype) {
        foreach(self::$relationships as $category => $relations) {
            if(array_key_exists($subtype, $relations)) {
                return $category;
            }
        }
        return 'other'; // fallback
    }
    
    public static function getReciprocalRelationship($relationship) {
        $reciprocals = [
            'father' => 'son', 'mother' => 'daughter',
            'son' => 'father', 'daughter' => 'mother',
            'grandfather' => 'grandson', 'grandmother' => 'granddaughter',
            'grandson' => 'grandfather', 'granddaughter' => 'grandmother',
            'brother' => 'brother', 'sister' => 'sister',
            'husband' => 'wife', 'wife' => 'husband',
            'uncle' => 'nephew', 'aunt' => 'niece',
            'nephew' => 'uncle', 'niece' => 'aunt',
            'first-cousin' => 'first-cousin',
            'step-father' => 'step-son', 'step-mother' => 'step-daughter',
            'step-son' => 'step-father', 'step-daughter' => 'step-mother',
            'half-brother' => 'half-brother', 'half-sister' => 'half-sister',
            'step-brother' => 'step-brother', 'step-sister' => 'step-sister'
        ];
        
        return $reciprocals[$relationship] ?? null;
    }
}
}
// Handle photo upload
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

// Add a new family member and their relationships
function addFamilyMember($data, $conn) {
    $conn->beginTransaction();
    
    try {
        // Handle file upload
        $photo_url = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handlePhotoUpload($_FILES['photo']);
            if ($upload_result['success']) {
                $photo_url = $upload_result['file_path'];
            } else {
                throw new Exception("Photo upload failed: " . $upload_result['error']);
            }
        }
        
        // Insert new member
        $stmt = $conn->prepare("INSERT INTO people (
            tree_id, first_name, middle_name, last_name, maiden_name, 
            gender, date_of_birth, birth_place, date_of_death, death_place, 
            photo_url, occupation, notes, is_living
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $tree_id = $_SESSION['current_tree_id'] ?? 1; // Default to 1 if not set
        // Handle checkbox values and default values
        $is_living = isset($data['is_living']) ? 1 : 0;
        
        // Handle maiden name - only save for females
        $maiden_name = ($data['gender'] === 'F' && !empty($data['maiden_name'])) ? $data['maiden_name'] : null;
        
        // Handle date fields - convert empty strings to null
        $birth_date = !empty($data['birth_date']) ? $data['birth_date'] : null;
        $death_date = !empty($data['death_date']) ? $data['death_date'] : null;
        
        // Handle text fields - convert empty strings to null
        $middle_name = !empty($data['middle_name']) ? $data['middle_name'] : null;
        $birth_place = !empty($data['birth_place']) ? $data['birth_place'] : null;
        $death_place = !empty($data['death_place']) ? $data['death_place'] : null;
        $occupation = !empty($data['occupation']) ? $data['occupation'] : null;
        $notes = !empty($data['notes']) ? $data['notes'] : null;
        
        $stmt->execute([
            $tree_id,
            $data['first_name'],
            $middle_name,
            $data['last_name'],
            $maiden_name,
            $data['gender'],
            $birth_date,
            $birth_place,
            $death_date,
            $death_place,
            $photo_url,
            $occupation,
            $notes,
            $is_living
        ]);
        $new_member_id = $conn->lastInsertId();
        
        // Add relationship if specified
        if(!empty($data['related_to']) && !empty($data['relationship_type'])) {
            addRelationship([
                'person1_id' => $new_member_id,
                'person2_id' => $data['related_to'],
                'relationship_type' => $data['relationship_type'],
                'relationship_subtype' => $data['relationship_subtype'],
                'marriage_date' => !empty($data['marriage_date']) ? $data['marriage_date'] : null,
                'marriage_place' => !empty($data['marriage_place']) ? $data['marriage_place'] : null
            ], $conn);
        }
        
        $conn->commit();
        return ['success' => true, 'member_id' => $new_member_id];
        
    } catch(Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Add a relationship between two family members
function addRelationship($data, $conn) {
    // Get the proper relationship category
    $relationship_category = FamilyRelationships::getRelationshipCategory($data['relationship_subtype']);
    
    $stmt = $conn->prepare("INSERT INTO relationships (
        person1_id, person2_id, relationship_type, relationship_subtype,
        marriage_date, marriage_place
    ) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Handle empty date and place fields
    $marriage_date = !empty($data['marriage_date']) ? $data['marriage_date'] : null;
    $marriage_place = !empty($data['marriage_place']) ? $data['marriage_place'] : null;
    
    $stmt->execute([
        $data['person1_id'],
        $data['person2_id'],
        $relationship_category, // Use the category, not the full relationship type
        $data['relationship_subtype'],
        $marriage_date,
        $marriage_place
    ]);
    
    // Add reciprocal relationship if needed (gender-aware so parent is father/mother correctly)
    $reciprocal = FamilyRelationships::getReciprocalRelationshipSmart($data['relationship_subtype'], $data['person1_id'], $data['person2_id'], $conn);
    if($reciprocal) {
        $reciprocal_category = FamilyRelationships::getRelationshipCategory($reciprocal);
        $stmt->execute([
            $data['person2_id'],
            $data['person1_id'],
            $reciprocal_category, // Use the reciprocal category
            $reciprocal,
            $marriage_date,
            $marriage_place
        ]);
    }

    // If we added a child -> parent relation (e.g., daughter/son), also auto-link to the other parent (spouse of the given parent)
    $childSubtypes = ['son','daughter','step-son','step-daughter','adoptive-son','adoptive-daughter'];
    if (in_array(strtolower($data['relationship_subtype']), $childSubtypes)) {
        $childId = (int)$data['person1_id'];
        $parentId = (int)$data['person2_id'];

        // Find spouse(s) of the parent
        $spouseStmt = $conn->prepare("SELECT person2_id FROM relationships WHERE person1_id = ? AND relationship_subtype IN ('husband','wife')");
        $spouseStmt->execute([$parentId]);
        $spouses = $spouseStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($spouses) {
            // Helper to check existence
            $existsStmt = $conn->prepare("SELECT 1 FROM relationships WHERE person1_id = ? AND person2_id = ? AND relationship_subtype = ? LIMIT 1");
            foreach ($spouses as $spouseId) {
                $spouseId = (int)$spouseId;
                if (!$spouseId || $spouseId === $parentId || $spouseId === $childId) continue;

                // Add child -> other parent (same child subtype)
                $existsStmt->execute([$childId, $spouseId, strtolower($data['relationship_subtype'])]);
                if (!$existsStmt->fetchColumn()) {
                    $cat = FamilyRelationships::getRelationshipCategory(strtolower($data['relationship_subtype']));
                    $stmt->execute([$childId, $spouseId, $cat, strtolower($data['relationship_subtype']), null, null]);
                }

                // Add other parent -> child (gender-aware reciprocal)
                $rec = FamilyRelationships::getReciprocalRelationshipSmart(strtolower($data['relationship_subtype']), $childId, $spouseId, $conn);
                if ($rec) {
                    $existsStmt->execute([$spouseId, $childId, $rec]);
                    if (!$existsStmt->fetchColumn()) {
                        $rcat = FamilyRelationships::getRelationshipCategory($rec);
                        $stmt->execute([$spouseId, $childId, $rcat, $rec, null, null]);
                    }
                }
            }
        }
    }
}

// Get all family members and relationships for a tree
function getFamilyTreeData($tree_id, $conn) {
    $members = [];
    $relationships = [];
    
    // Get all members
    $query = "SELECT * FROM people WHERE tree_id = ? ORDER BY date_of_birth ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tree_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($result as $member) {
        $members[$member['id']] = $member;
    }
    
    // Get all relationships with both directions
    $query = "SELECT r.*, 
        p1.first_name as person1_first_name, p1.last_name as person1_last_name,
        p2.first_name as person2_first_name, p2.last_name as person2_last_name
        FROM relationships r
        JOIN people p1 ON r.person1_id = p1.id
        JOIN people p2 ON r.person2_id = p2.id
        WHERE p1.tree_id = ?
        ORDER BY r.person1_id, r.person2_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$tree_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($result as $rel) {
        $rel['person1_name'] = trim($rel['person1_first_name'] . ' ' . $rel['person1_last_name']);
        $rel['person2_name'] = trim($rel['person2_first_name'] . ' ' . $rel['person2_last_name']);
        
        // Convert relationship_subtype to the format expected by our tree builder
        $relationships[] = [
            'person1_id' => $rel['person1_id'],
            'person2_id' => $rel['person2_id'], 
            'relationship_type' => $rel['relationship_type'],
            'relationship_subtype' => $rel['relationship_subtype'],
            'marriage_date' => $rel['marriage_date'],
            'marriage_place' => $rel['marriage_place'],
            'person1_name' => $rel['person1_name'],
            'person2_name' => $rel['person2_name']
        ];
    }
    
    return ['members' => $members, 'relationships' => $relationships];
}

// Process form submission
if(isset($_POST['add_member'])) {
    $result = addFamilyMember($_POST, $conn);
    if($result['success']) {
        $success_message = "Family member added successfully!";
    } else {
        $error_message = "Error adding family member: " . $result['error'];
    }
}

// Define BASEPATH for view security
define('BASEPATH', true);

// Get current tree data
$current_tree_id = $_SESSION['current_tree_id'] ?? 1;

// Check if user has access to this tree
if (!can_access_tree($current_tree_id)) {
    $_SESSION['flash_message'] = "You don't have access to this family tree.";
    $_SESSION['flash_type'] = 'error';
    redirect_to('dashboard.php');
    exit();
}

$family_data = getFamilyTreeData($current_tree_id, $conn);

// Include the view template
require_once 'templates/header.php';
include 'views/family-tree-view.php';
require_once 'templates/footer.php';
?>
