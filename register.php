<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Register';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request";
    } else {
        // Sanitize input
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitize_input($_POST['full_name']);

        // Validate input
        if (empty($username)) {
            $errors[] = "Username is required";
        }
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }

        // If no errors, proceed with registration
        if (empty($errors)) {
            $db = new Database();
            $conn = $db->getConnection();

            try {
                // Check if username or email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Username or email already exists";
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $full_name]);

                    // Set success message and redirect to login
                    $_SESSION['flash_message'] = "Registration successful! Please login.";
                    $_SESSION['flash_type'] = "success";
                    redirect_to('login.php');
                    exit();
                }
            } catch (PDOException $e) {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}

include 'templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="form-container">
            <h2 class="text-center mb-4">Create an Account</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <div class="invalid-feedback">Please choose a username.</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div class="invalid-feedback">Please provide a valid email.</div>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    <div class="invalid-feedback">Please provide your full name.</div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">Please confirm your password.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>

            <div class="text-center mt-3">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
