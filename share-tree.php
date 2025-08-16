<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
// Check if user is logged in
redirect_if_not_logged_in();

$tree_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tree_id) {
    $_SESSION['flash_message'] = "Invalid family tree ID.";
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

$db = new Database();
$conn = $db->getConnection();

// Verify user owns this tree
$stmt = $conn->prepare("
    SELECT * FROM family_trees WHERE id = ? AND owner_id = ?
");
$stmt->execute([$tree_id, $_SESSION['user_id']]);
$tree = $stmt->fetch();

if (!$tree) {
    $_SESSION['flash_message'] = "You don't have permission to share this tree.";
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

// Handle share submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_message'] = "Invalid request.";
        $_SESSION['flash_type'] = "danger";
        redirect_to('share-tree.php?id=' . $tree_id);
    }

    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                // Add new share
                $email = sanitize_input($_POST['email']);
                $permission = sanitize_input($_POST['permission']);

                // Verify valid permission level
                if (!in_array($permission, ['view', 'edit', 'admin'])) {
                    throw new Exception("Invalid permission level.");
                }

                // Get user by email
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    throw new Exception("User not found with this email.");
                }

                if ($user['id'] === $_SESSION['user_id']) {
                    throw new Exception("You can't share the tree with yourself.");
                }

                // Check if already shared
                $stmt = $conn->prepare("
                    SELECT id FROM tree_sharing 
                    WHERE tree_id = ? AND user_id = ?
                ");
                $stmt->execute([$tree_id, $user['id']]);
                if ($stmt->fetch()) {
                    throw new Exception("Tree is already shared with this user.");
                }

                // Add share
                $stmt = $conn->prepare("
                    INSERT INTO tree_sharing (tree_id, user_id, permission_level)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$tree_id, $user['id'], $permission]);

                $_SESSION['flash_message'] = "Tree shared successfully!";
                $_SESSION['flash_type'] = "success";

            } elseif ($_POST['action'] === 'update') {
                // Update existing share
                $share_id = (int)$_POST['share_id'];
                $permission = sanitize_input($_POST['permission']);

                if (!in_array($permission, ['view', 'edit', 'admin'])) {
                    throw new Exception("Invalid permission level.");
                }

                $stmt = $conn->prepare("
                    UPDATE tree_sharing 
                    SET permission_level = ?
                    WHERE id = ? AND tree_id = ?
                ");
                $stmt->execute([$permission, $share_id, $tree_id]);

                $_SESSION['flash_message'] = "Sharing permissions updated!";
                $_SESSION['flash_type'] = "success";

            } elseif ($_POST['action'] === 'remove') {
                // Remove share
                $share_id = (int)$_POST['share_id'];

                $stmt = $conn->prepare("
                    DELETE FROM tree_sharing 
                    WHERE id = ? AND tree_id = ?
                ");
                $stmt->execute([$share_id, $tree_id]);

                $_SESSION['flash_message'] = "Sharing removed successfully!";
                $_SESSION['flash_type'] = "success";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }

    redirect_to('share-tree.php?id=' . $tree_id);
}

// Get current shares
$stmt = $conn->prepare("
    SELECT ts.*, u.email, u.full_name
    FROM tree_sharing ts
    JOIN users u ON ts.user_id = u.id
    WHERE ts.tree_id = ?
    ORDER BY ts.created_at DESC
");
$stmt->execute([$tree_id]);
$shares = $stmt->fetchAll();

$page_title = "Share Tree - " . htmlspecialchars($tree['name']);
include 'templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Share "<?php echo htmlspecialchars($tree['name']); ?>"</h2>
                </div>
                <div class="card-body">
                    <!-- Add new share form -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <input type="email" class="form-control" name="email" 
                                       placeholder="Enter email address" required>
                            </div>
                            <div class="col-sm-4">
                                <select class="form-select" name="permission" required>
                                    <option value="view">View only</option>
                                    <option value="edit">Can edit</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <button type="submit" class="btn btn-primary w-100">Share</button>
                            </div>
                        </div>
                    </form>

                    <!-- Current shares list -->
                    <?php if (empty($shares)): ?>
                        <p class="text-muted">This tree hasn't been shared with anyone yet.</p>
                    <?php else: ?>
                        <h5 class="mb-3">Current Shares</h5>
                        <div class="list-group">
                            <?php foreach ($shares as $share): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($share['full_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($share['email']); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <form method="POST" class="me-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                                <select class="form-select form-select-sm" name="permission" 
                                                        onchange="this.form.submit()">
                                                    <option value="view" <?php echo $share['permission_level'] === 'view' ? 'selected' : ''; ?>>
                                                        View only
                                                    </option>
                                                    <option value="edit" <?php echo $share['permission_level'] === 'edit' ? 'selected' : ''; ?>>
                                                        Can edit
                                                    </option>
                                                    <option value="admin" <?php echo $share['permission_level'] === 'admin' ? 'selected' : ''; ?>>
                                                        Administrator
                                                    </option>
                                                </select>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this share?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="family-tree.php?id=<?php echo $tree_id; ?>" class="btn btn-secondary">
                    Back to Tree
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
