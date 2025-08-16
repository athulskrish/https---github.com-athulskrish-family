<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
// Relationship mapping helpers
require_once 'includes/FamilyRelationships.php';

// Local helper to update an existing relationship (mirrors manage-relationships.php)
function updateExistingRelationship($conn, $data) {
    $relationship_id = isset($data['relationship_id']) ? (int)$data['relationship_id'] : 0;
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
        // No changes or not found, still treat as success silently
        return;
    }
}

// Add a relationship between two family members (mirrors logic in family-tree.php)
function addRelationship($data, $conn) {
    // Determine relationship category from subtype
    $relationship_category = FamilyRelationships::getRelationshipCategory($data['relationship_subtype']);

    $stmt = $conn->prepare("INSERT INTO relationships (
        person1_id, person2_id, relationship_type, relationship_subtype,
        marriage_date, marriage_place
    ) VALUES (?, ?, ?, ?, ?, ?)");

    // Normalize optional fields
    $marriage_date = !empty($data['marriage_date']) ? $data['marriage_date'] : null;
    $marriage_place = !empty($data['marriage_place']) ? $data['marriage_place'] : null;

    $stmt->execute([
        $data['person1_id'],
        $data['person2_id'],
        $relationship_category,
        $data['relationship_subtype'],
        $marriage_date,
        $marriage_place
    ]);

    // Add reciprocal relationship if defined
    $reciprocal = FamilyRelationships::getReciprocalRelationship($data['relationship_subtype']);
    if ($reciprocal) {
        $reciprocal_category = FamilyRelationships::getRelationshipCategory($reciprocal);
        $stmt->execute([
            $data['person2_id'],
            $data['person1_id'],
            $reciprocal_category,
            $reciprocal,
            $marriage_date,
            $marriage_place
        ]);
    }
}

// Check if user is logged in
redirect_if_not_logged_in();

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$member_id) {
    $_SESSION['flash_message'] = "Invalid member ID.";
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

$db = new Database();
$conn = $db->getConnection();

// Handle relationship operations (inline processing for add/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token']) ) {
            throw new Exception('Invalid CSRF token');
        }

        if ($_POST['action'] === 'add_relationship') {
            // Expect: person1_id (current), person2_id, relationship_subtype, optional marriage_* fields
            addRelationship($_POST, $conn);

            $_SESSION['flash_message'] = 'Relationship added successfully!';
            $_SESSION['flash_type'] = 'success';
            redirect_to('member.php?id=' . $member_id);
        } elseif ($_POST['action'] === 'update_relationship') {
            updateExistingRelationship($conn, $_POST);

            $_SESSION['flash_message'] = 'Relationship updated successfully!';
            $_SESSION['flash_type'] = 'success';
            redirect_to('member.php?id=' . $member_id);
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        // fall through to render page with message
    }
}

// Get member details with tree access check
$stmt = $conn->prepare("
    SELECT p.*, ft.id as tree_id, ft.name as tree_name,
           CASE 
               WHEN ft.owner_id = ? THEN 'owner'
               WHEN ts.permission_level IS NOT NULL THEN ts.permission_level
               WHEN ft.privacy_level = 'public' THEN 'view'
               ELSE NULL
           END as access_level
    FROM people p
    JOIN family_trees ft ON p.tree_id = ft.id
    LEFT JOIN tree_sharing ts ON ft.id = ts.tree_id AND ts.user_id = ?
    WHERE p.id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $member_id]);
$member = $stmt->fetch();

if (!$member || !$member['access_level']) {
    $_SESSION['flash_message'] = "You don't have access to view this member.";
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

// Get relationships
$relationships = [];

// Get parents
$stmt = $conn->prepare("
    SELECT p.*, 'parent' as relationship_type
    FROM relationships r
    JOIN people p ON r.person1_id = p.id
    WHERE r.person2_id = ? AND r.relationship_type = 'parent-child'
");
$stmt->execute([$member_id]);
$relationships['parents'] = $stmt->fetchAll();

// Get children
$stmt = $conn->prepare("
    SELECT p.*, 'child' as relationship_type
    FROM relationships r
    JOIN people p ON r.person2_id = p.id
    WHERE r.person1_id = ? AND r.relationship_type = 'parent-child'
");
$stmt->execute([$member_id]);
$relationships['children'] = $stmt->fetchAll();

// Get siblings
$stmt = $conn->prepare("
    SELECT p.*, 'sibling' as relationship_type
    FROM relationships r1
    JOIN relationships r2 ON r1.person1_id = r2.person1_id
    JOIN people p ON r2.person2_id = p.id
    WHERE r1.person2_id = ? 
    AND r2.person2_id != ?
    AND r1.relationship_type = 'parent-child'
    AND r2.relationship_type = 'parent-child'
");
$stmt->execute([$member_id, $member_id]);
$relationships['siblings'] = $stmt->fetchAll();

// Get spouses
$stmt = $conn->prepare("
    SELECT p.*, r.marriage_date, r.divorce_date,
           CASE 
               WHEN r.person1_id = ? THEN r.person2_id
               ELSE r.person1_id
           END as spouse_id
    FROM relationships r
    JOIN people p ON (
        CASE 
            WHEN r.person1_id = ? THEN r.person2_id
            ELSE r.person1_id
        END = p.id
    )
    WHERE (r.person1_id = ? OR r.person2_id = ?)
    AND r.relationship_type = 'spouse'
");
$stmt->execute([$member_id, $member_id, $member_id, $member_id]);
$relationships['spouses'] = $stmt->fetchAll();

// Get media
$stmt = $conn->prepare("
    SELECT * FROM media 
    WHERE person_id = ?
    ORDER BY upload_date DESC
");
$stmt->execute([$member_id]);
$media = $stmt->fetchAll();

// Check if this is an AJAX request for the modal
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'member' => $member,
        'relationships' => $relationships,
        'media' => $media,
        'canEdit' => in_array($member['access_level'], ['owner', 'admin', 'edit'])
    ]);
    exit();
}

$page_title = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
include 'templates/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Member Information Column -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h2>
                        <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                            <button class="btn btn-primary" onclick="editMember()">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <?php if ($member['photo_url']): ?>
                            <div class="col-md-4 mb-3">
                                <img src="<?php echo htmlspecialchars($member['photo_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($member['first_name']); ?>"
                                     class="img-fluid rounded">
                            </div>
                        <?php endif; ?>

                        <div class="col">
                            <table class="table">
                                <tr>
                                    <th>Full Name</th>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($member['first_name']);
                                        if ($member['middle_name']) echo ' ' . htmlspecialchars($member['middle_name']);
                                        echo ' ' . htmlspecialchars($member['last_name']);
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Gender</th>
                                    <td><?php echo $member['gender'] === 'M' ? 'Male' : ($member['gender'] === 'F' ? 'Female' : 'Other'); ?></td>
                                </tr>
                                <?php if ($member['date_of_birth']): ?>
                                    <tr>
                                        <th>Birth Date</th>
                                        <td><?php echo date('F j, Y', strtotime($member['date_of_birth'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($member['birth_place']): ?>
                                    <tr>
                                        <th>Birth Place</th>
                                        <td><?php echo htmlspecialchars($member['birth_place']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($member['date_of_death']): ?>
                                    <tr>
                                        <th>Death Date</th>
                                        <td><?php echo date('F j, Y', strtotime($member['date_of_death'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($member['death_place']): ?>
                                    <tr>
                                        <th>Death Place</th>
                                        <td><?php echo htmlspecialchars($member['death_place']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                            <?php if ($member['notes']): ?>
                                <div class="mt-3">
                                    <h5>Notes</h5>
                                    <p><?php echo nl2br(htmlspecialchars($member['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Family Relationships -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Family Relationships</h3>
                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                        <button class="btn btn-primary btn-sm" onclick="addRelationship()">
                            <i class="fas fa-plus"></i> Add Relationship
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($relationships['parents'])): ?>
                        <h5>Parents</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['parents'] as $parent): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <a href="member.php?id=<?php echo $parent['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                    </a>
                                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                                        <div class="btn-group btn-group-sm relationship-actions">
                                            <button class="btn btn-outline-primary" onclick="editRelationship(<?php echo $parent['id']; ?>, 'parent')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRelationship(<?php echo $parent['id']; ?>, 'parent')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['siblings'])): ?>
                        <h5>Siblings</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['siblings'] as $sibling): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <a href="member.php?id=<?php echo $sibling['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($sibling['first_name'] . ' ' . $sibling['last_name']); ?>
                                    </a>
                                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                                        <div class="btn-group btn-group-sm relationship-actions">
                                            <button class="btn btn-outline-primary" onclick="editRelationship(<?php echo $sibling['id']; ?>, 'sibling')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRelationship(<?php echo $sibling['id']; ?>, 'sibling')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['spouses'])): ?>
                        <h5>Spouses</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['spouses'] as $spouse): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="member.php?id=<?php echo $spouse['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($spouse['first_name'] . ' ' . $spouse['last_name']); ?>
                                        </a>
                                        <?php if ($spouse['marriage_date']): ?>
                                            <small class="text-muted d-block">
                                                Married: <?php echo date('M j, Y', strtotime($spouse['marriage_date'])); ?>
                                                <?php if ($spouse['divorce_date']): ?>
                                                    - Divorced: <?php echo date('M j, Y', strtotime($spouse['divorce_date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                                        <div class="btn-group btn-group-sm relationship-actions">
                                            <button class="btn btn-outline-primary" onclick="editRelationship(<?php echo $spouse['id']; ?>, 'spouse', '<?php echo $spouse['marriage_date']; ?>', '<?php echo $spouse['divorce_date']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRelationship(<?php echo $spouse['id']; ?>, 'spouse')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['children'])): ?>
                        <h5>Children</h5>
                        <div class="list-group">
                            <?php foreach ($relationships['children'] as $child): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <a href="member.php?id=<?php echo $child['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    </a>
                                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                                        <div class="btn-group btn-group-sm relationship-actions">
                                            <button class="btn btn-outline-primary" onclick="editRelationship(<?php echo $child['id']; ?>, 'child')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRelationship(<?php echo $child['id']; ?>, 'child')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
        <!-- Inline Relationship Management -->
        <div class="col-12">
            <!-- Add Relationship Form -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="mb-0">Add Relationship</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="member.php?id=<?php echo $member_id; ?>">
                        <input type="hidden" name="action" value="add_relationship">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="person1_id" value="<?php echo $member_id; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inline_person2_id" class="form-label">Related Person</label>
                                    <select class="form-select" id="inline_person2_id" name="person2_id" required>
                                        <option value="">Select Person</option>
                                        <?php
                                        // Get all people in the same tree except current one
                                        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM people WHERE tree_id = ? AND id != ? ORDER BY first_name, last_name");
                                        $stmt->execute([$member['tree_id'], $member_id]);
                                        while ($person = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . (int)$person['id'] . '">' .
                                                 htmlspecialchars($person['first_name'] . ' ' . $person['last_name']) .
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inline_relationship_subtype" class="form-label">Relationship Type</label>
                                    <select class="form-select" id="inline_relationship_subtype" name="relationship_subtype" required>
                                        <option value="">Select Relationship</option>
                                        <optgroup label="Family">
                                            <option value="father">Father</option>
                                            <option value="mother">Mother</option>
                                            <option value="son">Son</option>
                                            <option value="daughter">Daughter</option>
                                            <option value="brother">Brother</option>
                                            <option value="sister">Sister</option>
                                        </optgroup>
                                        <optgroup label="Spouse">
                                            <option value="husband">Husband</option>
                                            <option value="wife">Wife</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1" id="inline-marriage-fields" style="display:none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inline_marriage_date" class="form-label">Marriage Date</label>
                                    <input type="date" class="form-control" id="inline_marriage_date" name="marriage_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="inline_marriage_place" class="form-label">Marriage Place</label>
                                    <input type="text" class="form-control" id="inline_marriage_place" name="marriage_place">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Add Relationship</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing Relationships List with Edit capability -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="mb-0">Existing Relationships</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Display existing relationships with edit forms
                    $stmt = $conn->prepare("
                        SELECT r.*, 
                               fm.first_name, fm.last_name
                        FROM relationships r
                        JOIN people fm ON 
                            (r.person1_id = ? AND r.person2_id = fm.id) OR 
                            (r.person2_id = ? AND r.person1_id = fm.id)
                        WHERE r.person1_id = ? OR r.person2_id = ?
                    ");
                    $stmt->execute([$member_id, $member_id, $member_id, $member_id]);
                    $relationships_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($relationships_list)) {
                        echo '<p class="text-muted mb-0">No relationships found.</p>';
                    } else {
                        foreach ($relationships_list as $rel_item) {
                            ?>
                            <div class="relationship-item mb-3 p-3 border rounded">
                                <form method="POST" action="member.php?id=<?php echo $member_id; ?>" class="row g-2 align-items-end">
                                    <input type="hidden" name="action" value="update_relationship">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="relationship_id" value="<?php echo (int)$rel_item['id']; ?>">
                                    
                                    <div class="col-md-4">
                                        <div>
                                            <strong><?php echo htmlspecialchars($rel_item['first_name'] . ' ' . $rel_item['last_name']); ?></strong>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($rel_item['relationship_subtype']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm">Marriage Date</label>
                                        <input type="date" class="form-control form-control-sm" 
                                               name="marriage_date" value="<?php echo htmlspecialchars($rel_item['marriage_date'] ?? ''); ?>"
                                               placeholder="Marriage Date">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm">Marriage Place</label>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="marriage_place" value="<?php echo htmlspecialchars($rel_item['marriage_place'] ?? ''); ?>"
                                               placeholder="Marriage Place">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">Update</button>
                                    </div>
                                </form>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Media Column -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Media</h3>
                    <?php if (in_array($member['access_level'], ['owner', 'admin', 'edit'])): ?>
                        <button class="btn btn-primary btn-sm" onclick="addMedia()">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($media)): ?>
                        <p class="text-muted">No media items found.</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($media as $item): ?>
                                <div class="col-6">
                                    <div class="card">
                                        <?php if ($item['media_type'] === 'photo'): ?>
                                            <img src="<?php echo htmlspecialchars($item['file_url']); ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                 onclick="viewMedia(<?php echo $item['id']; ?>)">
                                        <?php else: ?>
                                            <div class="card-body">
                                                <a href="<?php echo htmlspecialchars($item['file_url']); ?>" 
                                                   target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-file"></i> View <?php echo ucfirst($item['media_type']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="update-member.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Member edit form fields will be loaded dynamically -->
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Relationship Modal -->
<div class="modal fade" id="addRelationshipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="manage-relationships.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_relationship">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Relationship</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="related_member_id" class="form-label">Related Person</label>
                        <select class="form-select" id="related_member_id" name="related_member_id" required>
                            <option value="">Select a person...</option>
                            <?php
                            // Get all other members in the same tree
                            $stmt = $conn->prepare("SELECT id, first_name, last_name FROM people WHERE tree_id = ? AND id != ? ORDER BY first_name, last_name");
                            $stmt->execute([$member['tree_id'], $member_id]);
                            $other_members = $stmt->fetchAll();
                            foreach ($other_members as $other_member):
                            ?>
                                <option value="<?php echo $other_member['id']; ?>">
                                    <?php echo htmlspecialchars($other_member['first_name'] . ' ' . $other_member['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="relationship_type" class="form-label">Relationship Type</label>
                        <select class="form-select" id="relationship_type" name="relationship_type" required>
                            <option value="">Select relationship type...</option>
                            <option value="parent">Parent</option>
                            <option value="child">Child</option>
                            <option value="sibling">Sibling</option>
                            <option value="spouse">Spouse</option>
                            <option value="grandparent">Grandparent</option>
                            <option value="grandchild">Grandchild</option>
                            <option value="aunt-uncle">Aunt/Uncle</option>
                            <option value="niece-nephew">Niece/Nephew</option>
                            <option value="cousin">Cousin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="relationship_subtype" class="form-label">Specific Relationship</label>
                        <select class="form-select" id="relationship_subtype" name="relationship_subtype" required>
                            <option value="">Select specific relationship...</option>
                        </select>
                    </div>
                    
                    <div id="marriage_details" style="display: none;">
                        <div class="mb-3">
                            <label for="marriage_date" class="form-label">Marriage Date</label>
                            <input type="date" class="form-control" id="marriage_date" name="marriage_date">
                        </div>
                        
                        <div class="mb-3">
                            <label for="marriage_place" class="form-label">Marriage Place</label>
                            <input type="text" class="form-control" id="marriage_place" name="marriage_place">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Relationship</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Relationship Modal -->
<div class="modal fade" id="editRelationshipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="manage-relationships.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_relationship">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="relationship_id" id="edit_relationship_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Relationship</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Related Person</label>
                        <input type="text" class="form-control" id="edit_related_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_marriage_date" class="form-label">Marriage Date</label>
                        <input type="date" class="form-control" id="edit_marriage_date" name="marriage_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_marriage_place" class="form-label">Marriage Place</label>
                        <input type="text" class="form-control" id="edit_marriage_place" name="marriage_place">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_divorce_date" class="form-label">Divorce Date (if applicable)</label>
                        <input type="date" class="form-control" id="edit_divorce_date" name="divorce_date">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Relationship</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addMediaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="add-media.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="person_id" value="<?php echo $member_id; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="media_type" class="form-label">Type</label>
                        <select class="form-select" id="media_type" name="media_type" required>
                            <option value="photo">Photo</option>
                            <option value="document">Document</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="media_file" class="form-label">File</label>
                        <input type="file" class="form-control" id="media_file" name="media_file" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Media Modal -->
<div class="modal fade" id="viewMediaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Media content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Relationship management functions
function addRelationship() {
    const modal = new bootstrap.Modal(document.getElementById('addRelationshipModal'));
    modal.show();
}

function editRelationship(relatedId, relationType, marriageDate = '', divorceDate = '') {
    // Find the relationship ID from the database
    findRelationshipId(<?php echo $member_id; ?>, relatedId, relationType)
        .then(relationshipId => {
            document.getElementById('edit_relationship_id').value = relationshipId;
            
            // Get the related person's name
            fetch(`member.php?id=${relatedId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_related_name').value = `${data.member.first_name} ${data.member.last_name}`;
                document.getElementById('edit_marriage_date').value = marriageDate || '';
                document.getElementById('edit_marriage_place').value = '';
                document.getElementById('edit_divorce_date').value = divorceDate || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editRelationshipModal'));
                modal.show();
            });
        });
}

function deleteRelationship(relatedId, relationType) {
    if (!confirm('Are you sure you want to delete this relationship?')) {
        return;
    }
    
    findRelationshipId(<?php echo $member_id; ?>, relatedId, relationType)
        .then(relationshipId => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage-relationships.php';
            
            const fields = {
                'csrf_token': '<?php echo generate_csrf_token(); ?>',
                'action': 'delete_relationship',
                'member_id': '<?php echo $member_id; ?>',
                'relationship_id': relationshipId
            };
            
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
}

async function findRelationshipId(memberId, relatedId, relationType) {
    // This is a simplified approach - in a real application, you'd want to
    // store the relationship ID in the HTML or make an API call
    const response = await fetch(`get-relationship-id.php?member_id=${memberId}&related_id=${relatedId}&type=${relationType}`);
    const data = await response.json();
    return data.relationship_id;
}

// Handle relationship type changes for add relationship modal
document.addEventListener('DOMContentLoaded', function() {
    const relationshipTypeSelect = document.getElementById('relationship_type');
    const relationshipSubtypeSelect = document.getElementById('relationship_subtype');
    const marriageDetails = document.getElementById('marriage_details');
    
    if (relationshipTypeSelect) {
        relationshipTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Clear previous options
            relationshipSubtypeSelect.innerHTML = '<option value="">Select specific relationship...</option>';
            
            // Define relationship subtypes
            const relationships = {
                'parent': {
                    'father': 'Father',
                    'mother': 'Mother',
                    'step-father': 'Step-Father',
                    'step-mother': 'Step-Mother'
                },
                'child': {
                    'son': 'Son',
                    'daughter': 'Daughter',
                    'step-son': 'Step-Son',
                    'step-daughter': 'Step-Daughter'
                },
                'sibling': {
                    'brother': 'Brother',
                    'sister': 'Sister',
                    'half-brother': 'Half-Brother',
                    'half-sister': 'Half-Sister'
                },
                'spouse': {
                    'husband': 'Husband',
                    'wife': 'Wife',
                    'ex-husband': 'Ex-Husband',
                    'ex-wife': 'Ex-Wife'
                },
                'grandparent': {
                    'grandfather': 'Grandfather',
                    'grandmother': 'Grandmother'
                },
                'grandchild': {
                    'grandson': 'Grandson',
                    'granddaughter': 'Granddaughter'
                },
                'aunt-uncle': {
                    'aunt': 'Aunt',
                    'uncle': 'Uncle'
                },
                'niece-nephew': {
                    'niece': 'Niece',
                    'nephew': 'Nephew'
                },
                'cousin': {
                    'first-cousin': 'First Cousin',
                    'second-cousin': 'Second Cousin'
                }
            };
            
            if (relationships[selectedType]) {
                for (const [value, label] of Object.entries(relationships[selectedType])) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    relationshipSubtypeSelect.appendChild(option);
                }
            }
            
            // Show/hide marriage details for spouse relationships
            if (selectedType === 'spouse') {
                marriageDetails.style.display = 'block';
            } else {
                marriageDetails.style.display = 'none';
            }
        });
    }
});

function editMember() {
    const modal = new bootstrap.Modal(document.getElementById('editMemberModal'));
    const modalBody = document.querySelector('#editMemberModal .modal-body');
    
    // Load member data
    fetch(`member.php?id=<?php echo $member_id; ?>`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        modalBody.innerHTML = generateEditForm(data.member);
        modal.show();
    });
}

function generateEditForm(member) {
    return `
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="${member.first_name}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="${member.last_name}" required>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="middle_name" class="form-label">Middle Name</label>
            <input type="text" class="form-control" id="middle_name" name="middle_name" 
                   value="${member.middle_name || ''}">
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="M" ${member.gender === 'M' ? 'selected' : ''}>Male</option>
                        <option value="F" ${member.gender === 'F' ? 'selected' : ''}>Female</option>
                        <option value="O" ${member.gender === 'O' ? 'selected' : ''}>Other</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                           value="${member.date_of_birth || ''}">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="birth_place" class="form-label">Place of Birth</label>
            <input type="text" class="form-control" id="birth_place" name="birth_place" 
                   value="${member.birth_place || ''}">
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_of_death" class="form-label">Date of Death</label>
                    <input type="date" class="form-control" id="date_of_death" name="date_of_death" 
                           value="${member.date_of_death || ''}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="death_place" class="form-label">Place of Death</label>
                    <input type="text" class="form-control" id="death_place" name="death_place" 
                           value="${member.death_place || ''}">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="photo" class="form-label">Photo</label>
            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
            ${member.photo_url ? `<small class="form-text text-muted">Leave blank to keep existing photo</small>` : ''}
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3">${member.notes || ''}</textarea>
        </div>
    `;
}

function addMedia() {
    const modal = new bootstrap.Modal(document.getElementById('addMediaModal'));
    modal.show();
}

function viewMedia(mediaId) {
    const modal = new bootstrap.Modal(document.getElementById('viewMediaModal'));
    // Load media details and show in modal
    modal.show();
}
</script>

<script>
// Show/hide marriage fields in inline form based on relationship type
document.addEventListener('DOMContentLoaded', function() {
    const subtypeSelect = document.getElementById('inline_relationship_subtype');
    const marriageFields = document.getElementById('inline-marriage-fields');
    if (subtypeSelect && marriageFields) {
        const toggleMarriage = () => {
            const spouseTypes = ['husband', 'wife', 'partner'];
            if (spouseTypes.includes(subtypeSelect.value)) {
                marriageFields.style.display = 'flex';
            } else {
                marriageFields.style.display = 'none';
            }
        };
        subtypeSelect.addEventListener('change', toggleMarriage);
        toggleMarriage();
    }
});
</script>

<?php include 'templates/footer.php'; ?>
