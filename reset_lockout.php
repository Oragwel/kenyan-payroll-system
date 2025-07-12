<?php
/**
 * Reset Login Lockout
 * 
 * This script allows administrators to reset login lockouts
 * and manage security settings for the authentication system.
 */

require_once 'config/database.php';
require_once 'secure_auth.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed. Please check your database configuration.');
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_ip') {
        $ipAddress = trim($_POST['ip_address'] ?? '');
        
        if (empty($ipAddress)) {
            $message = 'Please enter an IP address to reset.';
            $messageType = 'danger';
        } else {
            try {
                // Clear failed login attempts for the IP
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = FALSE");
                $stmt->execute([$ipAddress]);
                $deletedRows = $stmt->rowCount();
                
                $message = "Successfully reset lockout for IP: $ipAddress ($deletedRows failed attempts cleared)";
                $messageType = 'success';
                
                // Log the reset action
                $stmt = $db->prepare("INSERT INTO security_logs (user_id, event_type, description, ip_address, severity) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    null,
                    'lockout_reset',
                    "Lockout reset for IP: $ipAddress by admin",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    'medium'
                ]);
                
            } catch (Exception $e) {
                $message = 'Error resetting lockout: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'reset_all') {
        try {
            // Clear all failed login attempts
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE success = FALSE");
            $stmt->execute();
            $deletedRows = $stmt->rowCount();
            
            $message = "Successfully reset all lockouts ($deletedRows failed attempts cleared)";
            $messageType = 'success';
            
            // Log the reset action
            $stmt = $db->prepare("INSERT INTO security_logs (user_id, event_type, description, ip_address, severity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                null,
                'all_lockouts_reset',
                "All lockouts reset by admin",
                $_SERVER['REMOTE_ADDR'] ?? '',
                'high'
            ]);
            
        } catch (Exception $e) {
            $message = 'Error resetting all lockouts: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get current user's IP
$currentIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Get locked out IPs
$lockedIPs = [];
try {
    $stmt = $db->prepare("
        SELECT ip_address, 
               COUNT(*) as failed_attempts,
               MAX(attempt_time) as last_attempt,
               TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(attempt_time), INTERVAL 900 SECOND)) as remaining_seconds
        FROM login_attempts 
        WHERE success = FALSE 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 900 SECOND)
        GROUP BY ip_address 
        HAVING failed_attempts >= 5
        ORDER BY last_attempt DESC
    ");
    $stmt->execute();
    $lockedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Get recent login attempts
$recentAttempts = [];
try {
    $stmt = $db->prepare("
        SELECT ip_address, username, success, attempt_time, user_agent
        FROM login_attempts 
        ORDER BY attempt_time DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Login Lockout - Kenyan Payroll System</title>
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
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .btn-primary:hover {
            background: #004d2e;
            border-color: #004d2e;
        }
        
        .btn-danger {
            background: var(--kenya-red);
            border-color: var(--kenya-red);
        }
        
        .current-ip {
            background: linear-gradient(90deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .locked-ip {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .table th {
            background: var(--kenya-green);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-shield-alt me-3"></i>Security Management</h1>
            <p class="mb-0">Reset login lockouts and manage authentication security</p>
        </div>
    </div>

    <div class="container">
        <!-- Current IP Display -->
        <div class="current-ip">
            <h5><i class="fas fa-globe me-2"></i>Your Current IP Address: <strong><?php echo htmlspecialchars($currentIP); ?></strong></h5>
            <small>This is the IP address that may be locked out</small>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Reset Forms -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-unlock me-2"></i>Reset Lockout</h5>
                    </div>
                    <div class="card-body">
                        <!-- Reset Specific IP -->
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="reset_ip">
                            <div class="mb-3">
                                <label for="ip_address" class="form-label">IP Address to Reset</label>
                                <input type="text" class="form-control" id="ip_address" name="ip_address" 
                                       value="<?php echo htmlspecialchars($currentIP); ?>" 
                                       placeholder="Enter IP address">
                                <div class="form-text">Enter the IP address to unlock (your current IP is pre-filled)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-unlock me-2"></i>Reset This IP
                            </button>
                        </form>

                        <hr>

                        <!-- Reset All -->
                        <form method="POST">
                            <input type="hidden" name="action" value="reset_all">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This will reset ALL login lockouts system-wide.
                            </div>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset ALL lockouts? This action cannot be undone.')">
                                <i class="fas fa-unlock-alt me-2"></i>Reset All Lockouts
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Currently Locked IPs -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lock me-2"></i>Currently Locked IPs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lockedIPs)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <p>No IP addresses are currently locked out</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lockedIPs as $ip): ?>
                                <div class="locked-ip">
                                    <strong><?php echo htmlspecialchars($ip['ip_address']); ?></strong>
                                    <br>
                                    <small>
                                        Failed attempts: <?php echo $ip['failed_attempts']; ?><br>
                                        Time remaining: <?php echo max(0, ceil($ip['remaining_seconds'] / 60)); ?> minutes<br>
                                        Last attempt: <?php echo $ip['last_attempt']; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Login Attempts -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>Recent Login Attempts</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentAttempts)): ?>
                    <p class="text-muted">No recent login attempts found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['username'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($attempt['success']): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $attempt['attempt_time']; ?></td>
                                        <td><small><?php echo htmlspecialchars(substr($attempt['user_agent'] ?? '', 0, 50)); ?>...</small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-4 mb-4">
            <a href="landing.html" class="btn btn-success me-2">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home me-2"></i>Go to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
