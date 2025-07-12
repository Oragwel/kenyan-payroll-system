<?php
/**
 * Installation Status Checker
 * 
 * This page provides a comprehensive overview of the installation status
 * and helps diagnose any issues with the system setup.
 */

require_once 'includes/installation_check.php';

$installCheck = checkSystemInstallation();
$progress = getInstallationProgress();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Status - Kenyan Payroll System</title>
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
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .progress-bar-custom {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-fill-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--kenya-green), var(--kenya-red));
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .status-pass {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .status-fail {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .btn-primary:hover {
            background: #004d2e;
            border-color: #004d2e;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-cogs me-3"></i>Installation Status</h1>
            <p class="mb-0">Comprehensive system installation diagnostics</p>
        </div>
    </div>

    <div class="container">
        <!-- Overall Status -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Overall Installation Status</h5>
            </div>
            <div class="card-body">
                <div class="progress-bar-custom">
                    <div class="progress-fill-custom" style="width: <?php echo $progress; ?>%">
                        <?php echo round($progress); ?>%
                    </div>
                </div>
                
                <?php if ($installCheck['installed']): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>✅ Installation Complete!</strong> Your system is properly installed and ready to use.
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home me-2"></i>Go to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>⚠️ Installation Incomplete</strong> Some components are missing or not properly configured.
                    </div>
                    <div class="text-center">
                        <a href="install.php?incomplete=1" class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-play me-2"></i>Complete Installation
                        </a>
                        <a href="install.php?force=1" class="btn btn-warning btn-lg">
                            <i class="fas fa-redo me-2"></i>Force Reinstall
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detailed Status -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list-check me-2"></i>Detailed Component Status</h5>
            </div>
            <div class="card-body">
                <?php
                $components = [
                    'Installation Marker' => file_exists('.installed'),
                    'Database Configuration' => file_exists('config/database.php'),
                    'Database Connection' => false,
                    'Companies Table' => false,
                    'Users Table' => false,
                    'Employees Table' => false,
                    'Admin User' => false,
                    'Company Record' => false
                ];
                
                // Test database components
                try {
                    if (file_exists('config/database.php')) {
                        require_once 'config/database.php';
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        if ($db) {
                            $components['Database Connection'] = true;
                            
                            // Check tables
                            $tables = ['companies', 'users', 'employees'];
                            foreach ($tables as $table) {
                                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                                $components[ucfirst($table) . ' Table'] = $stmt->rowCount() > 0;
                            }
                            
                            // Check admin user
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1");
                                $result = $stmt->fetch();
                                $components['Admin User'] = $result['count'] > 0;
                            } catch (Exception $e) {
                                // Table might not exist
                            }
                            
                            // Check company record
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM companies");
                                $result = $stmt->fetch();
                                $components['Company Record'] = $result['count'] > 0;
                            } catch (Exception $e) {
                                // Table might not exist
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Database issues
                }
                
                foreach ($components as $component => $status):
                ?>
                    <div class="status-item <?php echo $status ? 'status-pass' : 'status-fail'; ?>">
                        <span><strong><?php echo $component; ?></strong></span>
                        <span>
                            <?php if ($status): ?>
                                <i class="fas fa-check-circle text-success"></i> Ready
                            <?php else: ?>
                                <i class="fas fa-times-circle text-danger"></i> Missing
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Missing Components -->
        <?php if (!empty($installCheck['missing']) || !empty($installCheck['errors'])): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Issues Found</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($installCheck['missing'])): ?>
                        <h6>Missing Components:</h6>
                        <ul>
                            <?php foreach ($installCheck['missing'] as $missing): ?>
                                <li><?php echo htmlspecialchars($missing); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($installCheck['errors'])): ?>
                        <h6>Errors:</h6>
                        <ul>
                            <?php foreach ($installCheck['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-tools me-2"></i>Available Actions</h5>
            </div>
            <div class="card-body text-center">
                <a href="install.php?incomplete=1" class="btn btn-primary me-2">
                    <i class="fas fa-play me-2"></i>Complete Installation
                </a>
                <a href="install.php?force=1" class="btn btn-warning me-2">
                    <i class="fas fa-redo me-2"></i>Force Reinstall
                </a>
                <a href="clean_install.php" class="btn btn-danger me-2">
                    <i class="fas fa-trash me-2"></i>Clean Install
                </a>
                <a href="javascript:location.reload()" class="btn btn-secondary">
                    <i class="fas fa-sync me-2"></i>Refresh Status
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
