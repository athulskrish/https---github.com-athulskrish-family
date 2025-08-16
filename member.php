<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

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

            <!-- Relationships Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">Family Relationships</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($relationships['parents'])): ?>
                        <h5>Parents</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['parents'] as $parent): ?>
                                <a href="member.php?id=<?php echo $parent['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['siblings'])): ?>
                        <h5>Siblings</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['siblings'] as $sibling): ?>
                                <a href="member.php?id=<?php echo $sibling['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <?php echo htmlspecialchars($sibling['first_name'] . ' ' . $sibling['last_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['spouses'])): ?>
                        <h5>Spouses</h5>
                        <div class="list-group mb-3">
                            <?php foreach ($relationships['spouses'] as $spouse): ?>
                                <a href="member.php?id=<?php echo $spouse['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <?php echo htmlspecialchars($spouse['first_name'] . ' ' . $spouse['last_name']); ?>
                                    <?php if ($spouse['marriage_date']): ?>
                                        <small class="text-muted">
                                            (Married: <?php echo date('Y', strtotime($spouse['marriage_date'])); ?>
                                            <?php if ($spouse['divorce_date']): ?>
                                                - Divorced: <?php echo date('Y', strtotime($spouse['divorce_date'])); ?>
                                            <?php endif; ?>)
                                        </small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($relationships['children'])): ?>
                        <h5>Children</h5>
                        <div class="list-group">
                            <?php foreach ($relationships['children'] as $child): ?>
                                <a href="member.php?id=<?php echo $child['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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

<!-- Add Media Modal -->
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

<?php include 'templates/footer.php'; ?>
