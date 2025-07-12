<?php
/**
 * Emergency Duplicate Fix - Web Interface
 * 
 * Web-based tool to fix duplicate admin users and reset installation
 */

session_start();

$message = '';
$messageType = '';
$step = $_GET['step'] ?? 'check';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_fix'])) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // Remove all admin users
        $stmt = $db->prepare("DELETE FROM users WHERE username = 'admin' OR role = 'admin'");
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        
        // Remove installation marker
        if (file_exists('.installed')) {
            unlink('.installed');
        }
        
        // Clear session
        session_destroy();
        session_start();
        
        $message = "Successfully removed $deletedCount admin users and reset installation state.";
        $messageType = 'success';
        $step = 'fixed';
        
    } catch (Exception $e) {
        $message = 'Fix failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check current state
$adminUsers = [];
$canConnect = false;
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $canConnect = true;
            $stmt = $db->query("SELECT id, username, email, first_name, last_name, role, created_at FROM users WHERE username = 'admin' OR role = 'admin' ORDER BY id");
            $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    $messageType = 'warning';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Fix - Kenyan Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-red: #ce1126;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .btn-danger {
            background: var(--kenya-red);
            border-color: var(--kenya-red);
        }
        
        .btn-success {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .emergency-warning {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-exclamation-triangle me-3"></i>🚨 Emergency Duplicate Fix</h1>
            <p class="mb-0">Resolve duplicate admin user errors and reset installation</p>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($step === 'fixed'): ?>
            <!-- Success State -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-check-circle me-2"></i>Fix Applied Successfully!</h5>
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-success">
                        <h4>🎉 Emergency Fix Completed!</h4>
                        <p>All duplicate admin users have been removed and the installation state has been reset.</p>
                    </div>
                    
                    <h5>🎯 Next Steps:</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <a href="install.php" class="btn btn-success btn-lg w-100 mb-2">
                                <i class="fas fa-play me-2"></i>Start Fresh Installation
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="installation_status.php" class="btn btn-outline-secondary btn-lg w-100 mb-2">
                                <i class="fas fa-chart-line me-2"></i>Check Installation Status
                            </a>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            💡 Tip: Clear your browser cache and cookies before starting the installation
                        </small>
                    </div>
                </div>
            </div>

        <?php elseif (!$canConnect): ?>
            <!-- Database Connection Error -->
            <div class="emergency-warning">
                <h4><i class="fas fa-database me-2"></i>Database Connection Error</h4>
                <p>Cannot connect to the database. Please check your configuration.</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tools me-2"></i>Manual Fix Required</h5>
                </div>
                <div class="card-body">
                    <h6>🔧 Manual Steps to Fix:</h6>
                    <ol>
                        <li><strong>Access your database directly</strong> (phpMyAdmin, MySQL Workbench, etc.)</li>
                        <li><strong>Run this SQL command:</strong>
                            <div class="bg-dark text-light p-2 rounded mt-2 mb-2">
                                <code>DELETE FROM users WHERE username = 'admin';</code>
                            </div>
                        </li>
                        <li><strong>Remove the .installed file</strong> from your project directory</li>
                        <li><strong>Clear browser cache and cookies</strong></li>
                        <li><strong>Start fresh installation</strong></li>
                    </ol>
                    
                    <div class="text-center mt-3">
                        <a href="install.php" class="btn btn-primary">
                            <i class="fas fa-redo me-2"></i>Try Installation Again
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Main Fix Interface -->
            <div class="emergency-warning">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Duplicate Admin User Detected</h4>
                <p>This tool will remove ALL admin users and reset the installation to fix the duplicate error.</p>
            </div>

            <!-- Current Admin Users -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Current Admin Users (<?php echo count($adminUsers); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($adminUsers)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No admin users found. You may proceed with installation.
                        </div>
                        <div class="text-center">
                            <a href="install.php" class="btn btn-success">
                                <i class="fas fa-play me-2"></i>Start Installation
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminUsers as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><span class="badge bg-danger"><?php echo ucfirst($user['role']); ?></span></td>
                                            <td><?php echo $user['created_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning:</h6>
                            <p>This action will <strong>permanently delete ALL admin users</strong> shown above and reset the installation state. You will need to create a new admin account during installation.</p>
                        </div>

                        <form method="POST" class="text-center">
                            <button type="submit" name="confirm_fix" class="btn btn-danger btn-lg" onclick="return confirm('Are you sure you want to remove ALL admin users and reset the installation? This action cannot be undone.')">
                                <i class="fas fa-trash me-2"></i>Remove All Admin Users & Reset
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Additional Tools -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-tools me-2"></i>Additional Tools</h5>
            </div>
            <div class="card-body text-center">
                <a href="break_redirect_loop.php" class="btn btn-outline-warning me-2">
                    <i class="fas fa-sync me-2"></i>Break Redirect Loops
                </a>
                <a href="simple_landing.php" class="btn btn-outline-info me-2">
                    <i class="fas fa-home me-2"></i>Simple Landing
                </a>
                <a href="clean_install.php" class="btn btn-outline-danger">
                    <i class="fas fa-broom me-2"></i>Clean Install
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
