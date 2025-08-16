<?php
// Include core files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

// Check if user is logged in
require_login();

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get member ID from URL
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$member_id) {
    $_SESSION['flash_message'] = "Member ID is required.";
    $_SESSION['flash_type'] = 'error';
    redirect_to('family-tree.php');
    exit();
}

// Get member details
$stmt = $conn->prepare("
    SELECT p.*, ft.name as tree_name 
    FROM people p 
    JOIN family_trees ft ON p.tree_id = ft.id 
    WHERE p.id = ?
");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['flash_message'] = "Member not found.";
    $_SESSION['flash_type'] = 'error';
    redirect_to('family-tree.php');
    exit();
}

// Check if user has access to this tree
if (!can_access_tree($member['tree_id'])) {
    $_SESSION['flash_message'] = "You don't have access to this family tree.";
    $_SESSION['flash_type'] = 'error';
    redirect_to('dashboard.php');
    exit();
}

// Get member's relationships
$stmt = $conn->prepare("
    SELECT r.*, p.id as related_id, p.first_name, p.middle_name, p.last_name, p.gender, p.photo_url,
           p.date_of_birth, p.date_of_death, p.is_living
    FROM relationships r
    JOIN people p ON (r.person2_id = p.id)
    WHERE r.person1_id = ?
    ORDER BY 
        CASE r.relationship_subtype
            WHEN 'husband' THEN 1
            WHEN 'wife' THEN 1
            WHEN 'father' THEN 2
            WHEN 'mother' THEN 2
            WHEN 'son' THEN 3
            WHEN 'daughter' THEN 3
            WHEN 'brother' THEN 4
            WHEN 'sister' THEN 4
            ELSE 5
        END,
        p.date_of_birth ASC
");
$stmt->execute([$member_id]);
$relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get member's media
$stmt = $conn->prepare("SELECT * FROM media WHERE person_id = ? ORDER BY upload_date DESC");
$stmt->execute([$member_id]);
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate age
function calculateAge($birth_date, $death_date = null) {
    if (!$birth_date) return null;
    
    $birth = new DateTime($birth_date);
    $end_date = $death_date ? new DateTime($death_date) : new DateTime();
    
    return $birth->diff($end_date)->y;
}

$age = calculateAge($member['date_of_birth'], $member['date_of_death']);

// Include header
require_once 'templates/header.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="breadcrumb-item"><a href="family-tree.php"><i class="fas fa-tree"></i> <?php echo htmlspecialchars($member['tree_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
            </li>
        </ol>
    </nav>

    <!-- Back Button -->
    <div class="mb-4">
        <a href="family-tree.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Back to Family Tree
        </a>
        <a href="update-member.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-edit"></i> Edit Member
        </a>
    </div>

    <!-- Member Profile Card -->
    <div class="row">
        <div class="col-lg-4">
            <div class="member-profile-card">
                <div class="profile-header">
                    <div class="profile-photo">
                        <?php if (!empty($member['photo_url']) && file_exists($member['photo_url'])): ?>
                            <img src="<?php echo htmlspecialchars($member['photo_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['first_name']); ?>" 
                                 class="profile-img">
                        <?php else: ?>
                            <div class="default-profile-photo">
                                <i class="fas <?php echo $member['gender'] === 'F' ? 'fa-female' : ($member['gender'] === 'M' ? 'fa-male' : 'fa-user'); ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="profile-name">
                        <?php 
                        echo htmlspecialchars($member['first_name']);
                        if (!empty($member['middle_name'])) {
                            echo ' ' . htmlspecialchars($member['middle_name']);
                        }
                        echo ' ' . htmlspecialchars($member['last_name']);
                        ?>
                        <?php if (!$member['is_living'] || !empty($member['date_of_death'])): ?>
                            <span class="deceased-indicator">†</span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if ($member['gender'] === 'F' && !empty($member['maiden_name'])): ?>
                        <p class="maiden-name">née <?php echo htmlspecialchars($member['maiden_name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-details">
                    <?php if (!empty($member['date_of_birth'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-birthday-cake"></i>
                            <span class="detail-label">Born:</span>
                            <span class="detail-value">
                                <?php echo date('F j, Y', strtotime($member['date_of_birth'])); ?>
                                <?php if ($age && ($member['is_living'] || empty($member['date_of_death']))): ?>
                                    (Age <?php echo $age; ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($member['birth_place'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="detail-label">Birth Place:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($member['birth_place']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($member['date_of_death'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-cross"></i>
                            <span class="detail-label">Died:</span>
                            <span class="detail-value">
                                <?php echo date('F j, Y', strtotime($member['date_of_death'])); ?>
                                <?php if ($age): ?>
                                    (Age <?php echo $age; ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($member['death_place'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="detail-label">Death Place:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($member['death_place']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($member['occupation'])): ?>
                        <div class="detail-item">
                            <i class="fas fa-briefcase"></i>
                            <span class="detail-label">Occupation:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($member['occupation']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="detail-item">
                        <i class="fas fa-venus-mars"></i>
                        <span class="detail-label">Gender:</span>
                        <span class="detail-value">
                            <?php echo $member['gender'] === 'M' ? 'Male' : ($member['gender'] === 'F' ? 'Female' : 'Other'); ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($member['notes'])): ?>
                    <div class="profile-notes">
                        <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($member['notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Family Relationships -->
            <div class="relationships-section">
                <h3><i class="fas fa-users"></i> Family Relationships</h3>
                
                <?php if (empty($relationships)): ?>
                    <div class="alert alert-info">No family relationships recorded yet.</div>
                <?php else: ?>
                    <div class="relationships-grid">
                        <?php 
                        $grouped_relationships = [];
                        foreach ($relationships as $rel) {
                            $category = FamilyRelationships::getRelationshipCategory($rel['relationship_subtype']);
                            if (!isset($grouped_relationships[$category])) {
                                $grouped_relationships[$category] = [];
                            }
                            $grouped_relationships[$category][] = $rel;
                        }
                        
                        $category_order = ['spouse', 'parent', 'child', 'sibling', 'grandparent', 'grandchild', 'aunt-uncle', 'niece-nephew', 'cousin', 'in-law'];
                        
                        foreach ($category_order as $category):
                            if (!isset($grouped_relationships[$category])) continue;
                            
                            $category_name = ucwords(str_replace('-', '/', $category));
                            if ($category === 'spouse') $category_name = 'Spouse';
                            elseif ($category === 'parent') $category_name = 'Parents';
                            elseif ($category === 'child') $category_name = 'Children';
                            elseif ($category === 'sibling') $category_name = 'Siblings';
                        ?>
                            <div class="relationship-category">
                                <h5 class="category-title"><?php echo $category_name; ?></h5>
                                <div class="relationship-cards">
                                    <?php foreach ($grouped_relationships[$category] as $rel): ?>
                                        <div class="relationship-card" onclick="viewMember(<?php echo $rel['related_id']; ?>)">
                                            <div class="rel-photo">
                                                <?php if (!empty($rel['photo_url']) && file_exists($rel['photo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($rel['photo_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($rel['first_name']); ?>">
                                                <?php else: ?>
                                                    <div class="default-rel-photo">
                                                        <i class="fas <?php echo $rel['gender'] === 'F' ? 'fa-female' : ($rel['gender'] === 'M' ? 'fa-male' : 'fa-user'); ?>"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="rel-info">
                                                <h6><?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?></h6>
                                                <small class="relationship-type"><?php echo ucwords($rel['relationship_subtype']); ?></small>
                                                <?php if (!empty($rel['date_of_birth'])): ?>
                                                    <small class="rel-dates">
                                                        <?php 
                                                        echo date('Y', strtotime($rel['date_of_birth']));
                                                        if (!$rel['is_living'] && !empty($rel['date_of_death'])) {
                                                            echo ' - ' . date('Y', strtotime($rel['date_of_death']));
                                                        }
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Media Gallery -->
            <?php if (!empty($media)): ?>
                <div class="media-section">
                    <h3><i class="fas fa-images"></i> Media Gallery</h3>
                    <div class="media-grid">
                        <?php foreach ($media as $item): ?>
                            <div class="media-item">
                                <?php if ($item['media_type'] === 'photo'): ?>
                                    <img src="<?php echo htmlspecialchars($item['file_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['title'] ?? 'Photo'); ?>"
                                         class="media-thumbnail"
                                         onclick="openMediaModal('<?php echo htmlspecialchars($item['file_url']); ?>', '<?php echo htmlspecialchars($item['title'] ?? 'Photo'); ?>')">
                                <?php else: ?>
                                    <div class="document-item">
                                        <i class="fas fa-file"></i>
                                        <span><?php echo htmlspecialchars($item['title'] ?? 'Document'); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['title'])): ?>
                                    <p class="media-title"><?php echo htmlspecialchars($item['title']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Media Modal -->
<div class="modal fade" id="mediaModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaModalTitle">Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
function viewMember(memberId) {
    window.location.href = 'member.php?id=' + memberId;
}

function openMediaModal(imageUrl, title) {
    document.getElementById('modalImage').src = imageUrl;
    document.getElementById('mediaModalTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('mediaModal')).show();
}
</script>

<?php require_once 'templates/footer.php'; ?>
