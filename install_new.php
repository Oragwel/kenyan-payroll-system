<?php
/**
 * Modern Multi-Database Installer for Kenyan Payroll System
 */

// Start session first
session_start();

require_once 'installer/DatabaseInstaller.php';

// Check if already installed
if (DatabaseInstaller::isInstalled()) {
    header('Location: index.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Validate step access
if ($step == 3 && (!isset($_SESSION['db_config']) || empty($_SESSION['db_config']))) {
    // Redirect back to step 2 if no database config
    header('Location: install_new.php?step=2');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 2) {
        // Database configuration step
        $config = [
            'type' => $_POST['db_type'],
            'host' => $_POST['host'] ?? '',
            'port' => $_POST['port'] ?? '',
            'database' => $_POST['database'] ?? '',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'path' => $_POST['path'] ?? ''
        ];
        
        $installer = new DatabaseInstaller();
        $testResult = $installer->testConnection($config);
        
        if ($testResult['success']) {
            $_SESSION['db_config'] = $config;
            $_SESSION['installer_step'] = 3;
            header('Location: install_new.php?step=3');
            exit;
        } else {
            $error = $testResult['error'] ?? 'Database connection failed';
        }
    } elseif ($step == 3) {
        // Admin user configuration and installation
        $adminUser = [
            'username' => $_POST['admin_username'],
            'email' => $_POST['admin_email'],
            'password' => $_POST['admin_password']
        ];
        
        if (isset($_SESSION['db_config']) && !empty($_SESSION['db_config'])) {
            $installer = new DatabaseInstaller();
            $installResult = $installer->install($_SESSION['db_config'], $adminUser);

            if ($installResult['success']) {
                $_SESSION['install_success'] = $installResult;
                header('Location: install_new.php?step=4');
                exit;
            } else {
                $error = implode(', ', $installResult['errors']);
            }
        } else {
            $error = 'Database configuration not found. Please start over from Step 2.';
            // Debug info
            if (isset($_SESSION['db_config'])) {
                $error .= ' (Config exists but is empty)';
            } else {
                $error .= ' (No config in session)';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Payroll System - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kenya-black: #000000;
            --kenya-red: #ce1126;
            --kenya-white: #ffffff;
            --kenya-green: #006b3f;
            --kenya-light-green: #e8f5e8;
            --kenya-dark-green: #004d2e;
        }
        
        body {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .install-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .install-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, var(--kenya-red) 0%, #a00e1f 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .install-body {
            padding: 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: bold;
            position: relative;
        }
        
        .step.active {
            background: var(--kenya-green);
            color: white;
        }
        
        .step.completed {
            background: var(--kenya-red);
            color: white;
        }
        
        .step.pending {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 30px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.completed::after {
            background: var(--kenya-green);
        }
        
        .db-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .db-option:hover {
            border-color: var(--kenya-green);
            background: var(--kenya-light-green);
        }
        
        .db-option.selected {
            border-color: var(--kenya-green);
            background: var(--kenya-light-green);
        }
        
        .form-control:focus {
            border-color: var(--kenya-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
        }
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .btn-primary:hover {
            background: var(--kenya-dark-green);
            border-color: var(--kenya-dark-green);
        }
        
        .alert-success {
            background: var(--kenya-light-green);
            border-color: var(--kenya-green);
            color: var(--kenya-dark-green);
        }
        
        .kenya-flag {
            display: inline-block;
            width: 30px;
            height: 20px;
            background: linear-gradient(to bottom, 
                var(--kenya-black) 0%, var(--kenya-black) 25%,
                var(--kenya-red) 25%, var(--kenya-red) 50%,
                var(--kenya-green) 50%, var(--kenya-green) 75%,
                var(--kenya-white) 75%, var(--kenya-white) 100%);
            border-radius: 3px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><span class="kenya-flag"></span>Kenyan Payroll System</h1>
                <p class="mb-0">Professional Payroll Management with Kenyan Tax Compliance</p>
            </div>
            
            <div class="install-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : 'pending'; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : 'pending'; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : 'pending'; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? 'active' : 'pending'; ?>">4</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Step 1: Welcome and Requirements -->
                    <div class="text-center">
                        <h3><i class="fas fa-rocket text-primary"></i> Welcome to Installation</h3>
                        <p class="text-muted">Let's set up your Kenyan Payroll System</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-check-circle text-success"></i> Features</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-users text-primary"></i> Employee Management</li>
                                <li><i class="fas fa-calculator text-primary"></i> Payroll Processing</li>
                                <li><i class="fas fa-file-invoice text-primary"></i> Kenyan Tax Compliance</li>
                                <li><i class="fas fa-chart-bar text-primary"></i> Reports & Analytics</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-server text-info"></i> Requirements</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> PHP 7.4+ (Current: <?php echo PHP_VERSION; ?>)</li>
                                <li><i class="fas fa-check text-success"></i> PDO Extension</li>
                                <li><i class="fas fa-database text-info"></i> Database Server</li>
                                <li><i class="fas fa-folder text-info"></i> Write Permissions</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="install_new.php?step=2" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right"></i> Start Installation
                        </a>
                    </div>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Database Configuration -->
                    <h3><i class="fas fa-database text-primary"></i> Database Configuration</h3>
                    <p class="text-muted">Choose your database type and configure connection settings</p>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Database Type</label>
                            <?php 
                            $availableDrivers = DatabaseInstaller::getAvailableDrivers();
                            foreach ($availableDrivers as $type => $name): 
                            ?>
                                <div class="db-option" onclick="selectDatabase('<?php echo $type; ?>')">
                                    <input type="radio" name="db_type" value="<?php echo $type; ?>" id="db_<?php echo $type; ?>" style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-database text-primary me-3"></i>
                                        <div>
                                            <strong><?php echo $name; ?></strong>
                                            <small class="d-block text-muted">
                                                <?php 
                                                switch($type) {
                                                    case 'mysql': echo 'Most popular, reliable, and well-supported'; break;
                                                    case 'postgresql': echo 'Advanced features, excellent for complex queries'; break;
                                                    case 'sqlite': echo 'File-based, perfect for development and small deployments'; break;
                                                    case 'sqlserver': echo 'Microsoft SQL Server, enterprise-grade'; break;
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Database connection fields (will be shown/hidden based on selection) -->
                        <div id="connection-fields" style="display: none;">
                            <div class="row" id="server-fields">
                                <div class="col-md-6">
                                    <label class="form-label">Host</label>
                                    <input type="text" class="form-control" name="host" value="localhost">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Port</label>
                                    <input type="text" class="form-control" name="port" id="port-field">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" class="form-control" name="database" value="kenyan_payroll">
                                </div>
                                <div class="col-md-6" id="path-field" style="display: none;">
                                    <label class="form-label">Database File Path</label>
                                    <input type="text" class="form-control" name="path" value="database/kenyan_payroll.sqlite">
                                </div>
                            </div>
                            
                            <div class="row mt-3" id="credentials-fields">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Admin User Setup -->
                    <h3><i class="fas fa-user-shield text-primary"></i> Administrator Account</h3>
                    <p class="text-muted">Create your administrator account to manage the system</p>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="admin_username" value="admin" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="admin_email" value="admin@garissa.go.ke" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="admin_password" value="admin123" required>
                            <small class="text-muted">You can change this password after installation</small>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="install_new.php?step=2" class="btn btn-secondary me-3">
                                <i class="fas fa-arrow-left"></i> Back to Database Setup
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-cogs"></i> Install System
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Step 4: Installation Complete -->
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h3 class="text-success mt-3">Installation Complete!</h3>
                        <p class="text-muted">Your Kenyan Payroll System is ready to use</p>
                    </div>
                    
                    <?php if (isset($_SESSION['install_success'])): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-info-circle"></i> Installation Summary</h5>
                            <ul class="mb-0">
                                <li>Database tables created successfully</li>
                                <li>Default company and admin user created</li>
                                <li>System configuration saved</li>
                            </ul>
                        </div>
                        
                        <?php 
                        $adminCreds = $_SESSION['install_success']['steps']['default_data']['details']['admin_user']['credentials'] ?? null;
                        if ($adminCreds): 
                        ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-key"></i> Login Credentials</h5>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($adminCreds['username']); ?></p>
                                <p><strong>Password:</strong> <?php echo htmlspecialchars($adminCreds['password']); ?></p>
                                <small class="text-muted">Please change your password after first login</small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Access Your System
                        </a>
                    </div>
                    
                    <?php unset($_SESSION['install_success'], $_SESSION['db_config']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectDatabase(type) {
            // Remove selected class from all options
            document.querySelectorAll('.db-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById('db_' + type).checked = true;
            
            // Show/hide connection fields based on database type
            const connectionFields = document.getElementById('connection-fields');
            const serverFields = document.getElementById('server-fields');
            const credentialsFields = document.getElementById('credentials-fields');
            const pathField = document.getElementById('path-field');
            const portField = document.getElementById('port-field');
            
            connectionFields.style.display = 'block';
            
            if (type === 'sqlite') {
                serverFields.style.display = 'none';
                credentialsFields.style.display = 'none';
                pathField.style.display = 'block';
            } else {
                serverFields.style.display = 'block';
                credentialsFields.style.display = 'block';
                pathField.style.display = 'none';
                
                // Set default ports
                switch(type) {
                    case 'mysql':
                        portField.value = '3306';
                        break;
                    case 'postgresql':
                        portField.value = '5432';
                        break;
                    case 'sqlserver':
                        portField.value = '1433';
                        break;
                }
            }
        }
    </script>
</body>
</html>
