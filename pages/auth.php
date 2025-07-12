<?php
/**
 * Authentication page - Login and Registration
 */

$action = $_GET['action'] ?? 'login';
$message = '';
$messageType = '';

// Handle logout
if ($action === 'logout') {
    logActivity('logout', 'User logged out');

    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    session_destroy();
    header('Location: landing.html');
    exit;
}

// Include secure authentication class and functions
require_once __DIR__ . '/../secure_auth.php'; // Adjust path if needed
require_once __DIR__ . '/../includes/functions.php'; // To ensure sanitizeInput exists

$secureAuth = new SecureAuth($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $message = 'Please fill in all fields';
            $messageType = 'danger';
        } else {
            $result = $secureAuth->authenticate($username, $password);

            if ($result['success']) {
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                $message = $result['message'];
                $messageType = $result['lockout'] ?? false ? 'warning' : 'danger';
            }
        }
    } elseif ($action === 'register' && hasPermission('admin')) {
        // ✅ Registration logic stays as-is
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $role = sanitizeInput($_POST['role']);

        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $message = 'Please fill in all fields';
            $messageType = 'danger';
        } elseif (!validateEmail($email)) {
            $message = 'Please enter a valid email address';
            $messageType = 'danger';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match';
            $messageType = 'danger';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long';
            $messageType = 'danger';
        } else {
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                $message = 'Username or email already exists';
                $messageType = 'danger';
            } else {
                $passwordHash = hashPassword($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");

                if ($stmt->execute([$username, $email, $passwordHash, $role])) {
                    $message = 'User registered successfully';
                    $messageType = 'success';
                    logActivity('user_registration', "New user registered: $username");
                } else {
                    $message = 'Registration failed. Please try again.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

?>

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-header text-center bg-primary text-white">
                    <h4><i class="fas fa-calculator"></i> <?php echo APP_NAME; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'login'): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>
                        
                        <?php if (hasPermission('admin')): ?>
                            <div class="text-center mt-3">
                                <a href="index.php?page=auth&action=register" class="text-decoration-none">
                                    Register New User
                                </a>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($action === 'register' && hasPermission('admin')): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="employee">Employee</option>
                                    <option value="hr">HR Manager</option>
                                    <option value="accountant">Accountant</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Register User
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="index.php?page=auth&action=login" class="text-decoration-none">
                                Back to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Kenyan Payroll Management System v<?php echo APP_VERSION; ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.card {
    border: none;
    border-radius: 15px;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
}
</style>
