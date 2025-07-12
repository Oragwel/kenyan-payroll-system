<?php
/**
 * MySQL Strict Mode Fix - Web Interface
 * 
 * Web-based tool to fix MySQL ONLY_FULL_GROUP_BY issues
 */

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_mysql'])) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // Get current SQL mode
        $stmt = $db->query("SELECT @@sql_mode as sql_mode");
        $result = $stmt->fetch();
        $currentMode = $result['sql_mode'];
        
        // Remove ONLY_FULL_GROUP_BY
        $newMode = str_replace(['ONLY_FULL_GROUP_BY,', ',ONLY_FULL_GROUP_BY', 'ONLY_FULL_GROUP_BY'], '', $currentMode);
        $newMode = str_replace(',,', ',', $newMode);
        $newMode = trim($newMode, ',');
        
        // Set new mode
        $stmt = $db->prepare("SET sql_mode = ?");
        $stmt->execute([$newMode]);
        
        $message = "Successfully disabled ONLY_FULL_GROUP_BY for this session. Dashboard should now work!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Fix failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check current state
$currentMode = '';
$hasGroupByIssue = false;
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $stmt = $db->query("SELECT @@sql_mode as sql_mode");
            $result = $stmt->fetch();
            $currentMode = $result['sql_mode'];
            $hasGroupByIssue = strpos($currentMode, 'ONLY_FULL_GROUP_BY') !== false;
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
    <title>MySQL Fix - Kenyan Payroll System</title>
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
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
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
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .btn-primary:hover {
            background: #004d2e;
            border-color: #004d2e;
        }
        
        .sql-mode {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-database me-3"></i>MySQL Strict Mode Fix</h1>
            <p class="mb-0">Resolve GROUP BY compatibility issues</p>
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

        <!-- Current Status -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Current MySQL Configuration</h5>
            </div>
            <div class="card-body">
                <?php if ($currentMode): ?>
                    <h6>Current SQL Mode:</h6>
                    <div class="sql-mode mb-3"><?php echo htmlspecialchars($currentMode); ?></div>
                    
                    <?php if ($hasGroupByIssue): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>ONLY_FULL_GROUP_BY is enabled</strong> - This is causing the GROUP BY errors in the dashboard.
                        </div>
                        
                        <form method="POST" class="text-center">
                            <button type="submit" name="fix_mysql" class="btn btn-primary btn-lg">
                                <i class="fas fa-wrench me-2"></i>Fix MySQL Mode (Temporary)
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>MySQL configuration is compatible</strong> - ONLY_FULL_GROUP_BY is not enabled.
                        </div>
                        
                        <div class="text-center">
                            <a href="index.php?page=dashboard" class="btn btn-success btn-lg">
                                <i class="fas fa-chart-line me-2"></i>Go to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cannot connect to database to check MySQL configuration.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-book me-2"></i>Fix Instructions</h5>
            </div>
            <div class="card-body">
                <h6>🔧 Temporary Fix (Current Session):</h6>
                <p>Click the "Fix MySQL Mode" button above to disable ONLY_FULL_GROUP_BY for this session. This will allow the dashboard to work until you restart MySQL.</p>
                
                <h6>🔧 Permanent Fix Options:</h6>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>For MAMP Users:</h6>
                        <ol>
                            <li>Open MAMP</li>
                            <li>Go to Preferences > MySQL</li>
                            <li>Click "Set to MySQL defaults"</li>
                            <li>Restart MAMP</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>Manual Configuration:</h6>
                        <ol>
                            <li>Edit your MySQL configuration file (my.cnf or my.ini)</li>
                            <li>Add or modify the sql_mode setting:</li>
                            <li><code>sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"</code></li>
                            <li>Restart MySQL server</li>
                        </ol>
                    </div>
                </div>
                
                <h6>🔧 Alternative (phpMyAdmin/MySQL Command):</h6>
                <div class="sql-mode">
                    SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="text-center">
            <a href="index.php?page=dashboard" class="btn btn-primary me-2">
                <i class="fas fa-chart-line me-2"></i>Test Dashboard
            </a>
            <a href="installation_status.php" class="btn btn-secondary me-2">
                <i class="fas fa-info me-2"></i>Installation Status
            </a>
            <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                <i class="fas fa-sync me-2"></i>Refresh
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
