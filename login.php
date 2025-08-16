<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = 'Login';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit for authentication attempts
    check_rate_limit('auth');
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $errors[] = "Both username and password are required";
        } else {
            $db = new Database();
            $conn = $db->getConnection();

            try {
                $stmt = $conn->prepare("SELECT id, username, password, full_name FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    $_SESSION['flash_message'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
                    $_SESSION['flash_type'] = "success";
                    
                    // Redirect to dashboard
                    redirect_to('dashboard.php');
                    exit();
                } else {
                    $errors[] = "Invalid username or password";
                }
            } catch (PDOException $e) {
                $errors[] = "Login failed. Please try again.";
            }
        }
    }
}

include 'templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="form-container">
            <h2 class="text-center mb-4">Login</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <div class="invalid-feedback">Please enter your username.</div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">Please enter your password.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="text-center mt-3">
                <a href="forgot-password.php">Forgot Password?</a><br>
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
