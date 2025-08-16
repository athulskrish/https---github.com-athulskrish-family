<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
// Check if user is logged in
redirect_if_not_logged_in();

$page_title = "Import Family Tree";
include 'templates/header.php';
?>

<div class="container mt-4">
    <h1>Import Family Tree</h1>
    
    <div class="card">
        <div class="card-body">
            <form action="import-tree.php" method="post" enctype="multipart/form-data">
                <?php insert_csrf_token(); ?>
                
                <div class="mb-3">
                    <label for="tree_name" class="form-label">Tree Name</label>
                    <input type="text" class="form-control" id="tree_name" name="tree_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="tree_description" class="form-label">Description (Optional)</label>
                    <textarea class="form-control" id="tree_description" name="tree_description" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="gedcom_file" class="form-label">GEDCOM File</label>
                    <input type="file" class="form-control" id="gedcom_file" name="gedcom_file" accept=".ged,.gedcom" required>
                    <div class="form-text">
                        Upload a GEDCOM file (.ged) containing your family tree data.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Import Tree</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
