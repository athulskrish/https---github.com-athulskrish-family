<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
$page_title = 'Welcome';

include 'templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 text-center">
        <h1 class="display-4 mb-4">Welcome to Family Tree Maker</h1>
        <p class="lead">Create and manage your family tree with ease. Track your ancestry, add family members, and visualize your family connections.</p>
        
        <?php if (!is_logged_in()): ?>
            <div class="mt-5">
                <a href="register.php" class="btn btn-primary btn-lg me-3">Get Started</a>
                <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
            </div>
        <?php else: ?>
            <div class="mt-5">
                <a href="family-tree.php" class="btn btn-primary btn-lg">View My Family Tree</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x mb-3 text-primary"></i>
                <h3>Create Family Tree</h3>
                <p>Add family members and define relationships easily.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-project-diagram fa-3x mb-3 text-primary"></i>
                <h3>Visualize Connections</h3>
                <p>Interactive tree view to explore family relationships.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-share-alt fa-3x mb-3 text-primary"></i>
                <h3>Share History</h3>
                <p>Share your family tree with relatives securely.</p>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
