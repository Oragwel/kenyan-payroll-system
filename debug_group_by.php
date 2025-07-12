<?php
/**
 * Debug GROUP BY Error - Web Interface
 * 
 * This script helps debug and fix the GROUP BY error directly in the browser
 */

$message = '';
$messageType = '';
$currentMode = '';
$queryResult = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fix_sql_mode'])) {
        try {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception('Database connection failed');
            }
            
            // Get current mode
            $stmt = $db->query("SELECT @@sql_mode as sql_mode");
            $result = $stmt->fetch();
            $currentMode = $result['sql_mode'];
            
            // Fix the mode
            $newMode = str_replace(['ONLY_FULL_GROUP_BY,', ',ONLY_FULL_GROUP_BY', 'ONLY_FULL_GROUP_BY'], '', $currentMode);
            $newMode = str_replace(',,', ',', $newMode);
            $newMode = trim($newMode, ',');
            
            $stmt = $db->prepare("SET sql_mode = ?");
            $stmt->execute([$newMode]);
            
            $message = "SQL mode fixed! ONLY_FULL_GROUP_BY disabled. Try the dashboard now.";
            $messageType = 'success';
            $currentMode = $newMode;
            
        } catch (Exception $e) {
            $message = 'Fix failed: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if (isset($_POST['test_query'])) {
        try {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception('Database connection failed');
            }
            
            // Test the problematic query
            $testQuery = "
                SELECT
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    DATE_FORMAT(created_at, '%M %Y') as month_name,
                    COUNT(*) as new_employees
                FROM employees
                WHERE company_id = 1
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
                LIMIT 3
            ";
            
            $stmt = $db->prepare($testQuery);
            $stmt->execute();
            $result = $stmt->fetchAll();
            
            $queryResult = "Query executed successfully! Found " . count($result) . " rows.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $queryResult = 'Query failed: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get current SQL mode
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $stmt = $db->query("SELECT @@sql_mode as sql_mode");
            $result = $stmt->fetch();
            $currentMode = $result['sql_mode'];
        }
    }
} catch (Exception $e) {
    $currentMode = 'Error: ' . $e->getMessage();
}

$hasGroupByIssue = strpos($currentMode, 'ONLY_FULL_GROUP_BY') !== false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug GROUP BY Error - Kenyan Payroll System</title>
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
            font-size: 0.9em;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-good { background-color: #28a745; }
        .status-bad { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-bug me-3"></i>Debug GROUP BY Error</h1>
            <p class="mb-0">Diagnose and fix MySQL ONLY_FULL_GROUP_BY issues</p>
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

        <?php if ($queryResult): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-database me-2"></i>
                <?php echo htmlspecialchars($queryResult); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Current MySQL Status</h5>
            </div>
            <div class="card-body">
                <h6>Current SQL Mode:</h6>
                <div class="sql-mode mb-3"><?php echo htmlspecialchars($currentMode); ?></div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>
                            <span class="status-indicator <?php echo $hasGroupByIssue ? 'status-bad' : 'status-good'; ?>"></span>
                            ONLY_FULL_GROUP_BY Status
                        </h6>
                        <p class="mb-0">
                            <?php if ($hasGroupByIssue): ?>
                                <span class="text-danger">ENABLED - This is causing the error</span>
                            <?php else: ?>
                                <span class="text-success">DISABLED - Should work fine</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Error Location:</h6>
                        <p class="mb-0">
                            <code>pages/dashboard.php:224</code><br>
                            Employee Growth Analytics query
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-tools me-2"></i>Fix Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="POST" class="mb-3">
                            <button type="submit" name="fix_sql_mode" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-wrench me-2"></i>Fix SQL Mode
                            </button>
                        </form>
                        <p class="text-muted small">Disables ONLY_FULL_GROUP_BY for current session</p>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" class="mb-3">
                            <button type="submit" name="test_query" class="btn btn-secondary btn-lg w-100">
                                <i class="fas fa-flask me-2"></i>Test Query
                            </button>
                        </form>
                        <p class="text-muted small">Test the problematic GROUP BY query</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Instructions -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-book me-2"></i>Manual Fix Instructions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Option 1: phpMyAdmin</h6>
                        <ol>
                            <li>Open phpMyAdmin</li>
                            <li>Click "SQL" tab</li>
                            <li>Run this command:</li>
                        </ol>
                        <div class="sql-mode">
                            SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Option 2: MAMP Configuration</h6>
                        <ol>
                            <li>Open MAMP</li>
                            <li>Go to Preferences > MySQL</li>
                            <li>Click "Set to MySQL defaults"</li>
                            <li>Restart MAMP</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Dashboard -->
        <div class="text-center">
            <a href="index.php?page=dashboard" class="btn btn-success btn-lg me-2">
                <i class="fas fa-chart-line me-2"></i>Test Dashboard
            </a>
            <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                <i class="fas fa-sync me-2"></i>Refresh Status
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
