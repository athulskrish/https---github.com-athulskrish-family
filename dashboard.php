<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
redirect_if_not_logged_in();

$page_title = 'Dashboard';
$db = new Database();
$conn = $db->getConnection();

// Fetch user's family trees
$stmt = $conn->prepare("
    SELECT ft.*, 
           (SELECT COUNT(*) FROM people WHERE tree_id = ft.id) as member_count
    FROM family_trees ft
    WHERE ft.owner_id = ?
    ORDER BY ft.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$my_trees = $stmt->fetchAll();

// Fetch shared trees
$stmt = $conn->prepare("
    SELECT ft.*, u.full_name as owner_name, ts.permission_level,
           (SELECT COUNT(*) FROM people WHERE tree_id = ft.id) as member_count
    FROM tree_sharing ts
    JOIN family_trees ft ON ts.tree_id = ft.id
    JOIN users u ON ft.owner_id = u.id
    WHERE ts.user_id = ?
    ORDER BY ts.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$shared_trees = $stmt->fetchAll();

include 'templates/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Family Trees</h2>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTreeModal">
                <i class="fas fa-plus"></i> Create New Tree
            </button>
            <a href="import.php" class="btn btn-secondary">
                <i class="fas fa-file-import"></i> Import GEDCOM
            </a>
        </div>
    </div>

    <?php if (empty($my_trees) && empty($shared_trees)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">Welcome to Family Tree Maker!</h4>
            <p>You haven't created any family trees yet. Click the "Create New Tree" button to get started!</p>
        </div>
    <?php else: ?>
        <!-- My Trees Section -->
        <?php if (!empty($my_trees)): ?>
            <div class="row">
                <?php foreach ($my_trees as $tree): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($tree['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($tree['description']); ?></p>
                                <div class="mb-2">
                                    <span class="badge bg-primary"><?php echo $tree['member_count']; ?> members</span>
                                    <span class="badge bg-info"><?php echo ucfirst($tree['privacy_level']); ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100">
                                    <a href="family-tree.php?id=<?php echo $tree['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit-tree.php?id=<?php echo $tree['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-info" onclick="shareTree(<?php echo $tree['id']; ?>)">
                                        <i class="fas fa-share-alt"></i> Share
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Shared Trees Section -->
        <?php if (!empty($shared_trees)): ?>
            <h3 class="mt-4 mb-3">Shared with Me</h3>
            <div class="row">
                <?php foreach ($shared_trees as $tree): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($tree['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($tree['description']); ?></p>
                                <p class="card-text"><small class="text-muted">Owned by: <?php echo htmlspecialchars($tree['owner_name']); ?></small></p>
                                <div class="mb-2">
                                    <span class="badge bg-primary"><?php echo $tree['member_count']; ?> members</span>
                                    <span class="badge bg-success"><?php echo ucfirst($tree['permission_level']); ?> access</span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="family-tree.php?id=<?php echo $tree['id']; ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-eye"></i> View Tree
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- New Tree Modal -->
<div class="modal fade" id="newTreeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="create-tree.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Create New Family Tree</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tree_name" class="form-label">Tree Name</label>
                        <input type="text" class="form-control" id="tree_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tree_description" class="form-label">Description</label>
                        <textarea class="form-control" id="tree_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="privacy_level" class="form-label">Privacy Level</label>
                        <select class="form-select" id="privacy_level" name="privacy_level">
                            <option value="private">Private</option>
                            <option value="shared">Shared</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Tree</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function shareTree(treeId) {
    // Implement sharing functionality
    window.location.href = `share-tree.php?id=${treeId}`;
}
</script>

<?php include 'templates/footer.php'; ?>
