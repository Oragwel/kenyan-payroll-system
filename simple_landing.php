<?php
/**
 * Simple Landing Page
 * 
 * A basic landing page that doesn't include installation checks
 * to prevent redirect loops while still allowing access to the system.
 */

session_start();

// Basic status check without complex validation
$systemStatus = 'unknown';
$statusMessage = '';
$canLogin = false;

try {
    if (file_exists('.installed') && file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $stmt = $db->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                $systemStatus = 'ready';
                $statusMessage = 'System is ready for use';
                $canLogin = true;
            } else {
                $systemStatus = 'incomplete';
                $statusMessage = 'Database tables missing';
            }
        } else {
            $systemStatus = 'db_error';
            $statusMessage = 'Cannot connect to database';
        }
    } else {
        $systemStatus = 'not_installed';
        $statusMessage = 'System not installed';
    }
} catch (Exception $e) {
    $systemStatus = 'error';
    $statusMessage = 'System error: ' . $e->getMessage();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canLogin) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // Simple login without complex security checks
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['company_id'] = $user['company_id'];
                
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                $loginError = 'Invalid username or password';
            }
        } catch (Exception $e) {
            $loginError = 'Login error: ' . $e->getMessage();
        }
    } else {
        $loginError = 'Please fill in all fields';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Payroll Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-red: #ce1126;
            --kenya-black: #000000;
            --kenya-white: #ffffff;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 400px;
            margin: 2rem auto;
        }
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .btn-primary:hover {
            background: #004d2e;
            border-color: #004d2e;
        }
        
        .status-ready { color: #28a745; }
        .status-incomplete { color: #ffc107; }
        .status-error { color: #dc3545; }
        .status-unknown { color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-flag me-3"></i>🇰🇪 Kenyan Payroll Management System</h1>
            <p class="mb-0">Enterprise Payroll Solution for Kenyan Businesses</p>
        </div>
    </div>

    <div class="container">
        <!-- System Status -->
        <div class="status-card">
            <h3><i class="fas fa-info-circle me-2"></i>System Status</h3>
            <p class="status-<?php echo $systemStatus; ?>">
                <i class="fas fa-circle me-2"></i>
                <?php echo htmlspecialchars($statusMessage); ?>
            </p>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <a href="installation_status.php" class="btn btn-info w-100 mb-2">
                        <i class="fas fa-chart-line me-2"></i>Check Installation Status
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="install.php" class="btn btn-warning w-100 mb-2">
                        <i class="fas fa-cog me-2"></i>Run Installer
                    </a>
                </div>
            </div>
        </div>

        <?php if ($canLogin): ?>
            <!-- Login Form -->
            <div class="login-card">
                <h4 class="text-center mb-4">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to System
                </h4>
                
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($loginError); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Installation Required -->
            <div class="login-card">
                <h4 class="text-center mb-4 text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>Installation Required
                </h4>
                <p class="text-center">The system needs to be installed before you can login.</p>
                <div class="d-grid gap-2">
                    <a href="install.php" class="btn btn-primary">
                        <i class="fas fa-play me-2"></i>Start Installation
                    </a>
                    <a href="installation_status.php" class="btn btn-outline-secondary">
                        <i class="fas fa-info me-2"></i>Check Status
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Troubleshooting -->
        <div class="status-card">
            <h5><i class="fas fa-tools me-2"></i>Troubleshooting</h5>
            <p>If you're experiencing issues:</p>
            <div class="row">
                <div class="col-md-4">
                    <a href="break_redirect_loop.php" class="btn btn-outline-danger w-100 mb-2">
                        <i class="fas fa-sync me-2"></i>Fix Redirect Loops
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="clean_install.php" class="btn btn-outline-warning w-100 mb-2">
                        <i class="fas fa-broom me-2"></i>Clean Install
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="landing.html" class="btn btn-outline-info w-100 mb-2">
                        <i class="fas fa-home me-2"></i>Original Landing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
