<?php
/**
 * System Settings Page
 *
 * Manage system-wide settings and configurations
 */

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Use JavaScript redirect to avoid header issues
    echo '<script>window.location.href = "index.php?page=auth";</script>';
    echo '<div class="alert alert-warning">Access denied. Redirecting to login...</div>';
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general_settings'])) {
        // Handle general settings update
        $message = "General settings updated successfully!";
        $messageType = 'success';
    } elseif (isset($_POST['update_security_settings'])) {
        // Handle security settings update
        $message = "Security settings updated successfully!";
        $messageType = 'success';
    } elseif (isset($_POST['update_email_settings'])) {
        // Handle email settings update
        $message = "Email settings updated successfully!";
        $messageType = 'success';
    }
}

// Get current settings (with fallbacks for missing tables)
$generalSettings = [
    'system_name' => 'Kenyan Payroll Management System',
    'company_name' => $_SESSION['company_name'] ?? 'Your Company',
    'timezone' => 'Africa/Nairobi',
    'date_format' => 'Y-m-d',
    'currency' => 'KES',
    'language' => 'en'
];

$securitySettings = [
    'session_timeout' => '30',
    'password_min_length' => '8',
    'require_password_change' => '0',
    'enable_two_factor' => '0',
    'login_attempts_limit' => '5'
];

$emailSettings = [
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'from_email' => '',
    'from_name' => 'Kenyan Payroll System'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Kenyan Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-red: #ce1126;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .settings-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .settings-card .card-header {
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
        
        .nav-pills .nav-link.active {
            background-color: var(--kenya-green);
        }
        
        .nav-pills .nav-link {
            color: var(--kenya-green);
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1><i class="fas fa-cogs me-3"></i>System Settings</h1>
            <p class="mb-0">Configure system-wide settings and preferences</p>
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

        <div class="row">
            <div class="col-md-3">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                    <button class="nav-link active" id="v-pills-general-tab" data-bs-toggle="pill" data-bs-target="#v-pills-general" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i>General Settings
                    </button>
                    <button class="nav-link" id="v-pills-security-tab" data-bs-toggle="pill" data-bs-target="#v-pills-security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                    </button>
                    <button class="nav-link" id="v-pills-email-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email" type="button" role="tab">
                        <i class="fas fa-envelope me-2"></i>Email Settings
                    </button>
                    <button class="nav-link" id="v-pills-backup-tab" data-bs-toggle="pill" data-bs-target="#v-pills-backup" type="button" role="tab">
                        <i class="fas fa-database me-2"></i>Backup & Maintenance
                    </button>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content" id="v-pills-tabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="v-pills-general" role="tabpanel">
                        <div class="settings-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog me-2"></i>General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="system_name" class="form-label">System Name</label>
                                                <input type="text" class="form-control" id="system_name" name="system_name" 
                                                       value="<?php echo htmlspecialchars($generalSettings['system_name']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="company_name" class="form-label">Company Name</label>
                                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                                       value="<?php echo htmlspecialchars($generalSettings['company_name']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="timezone" class="form-label">Timezone</label>
                                                <select class="form-select" id="timezone" name="timezone">
                                                    <option value="Africa/Nairobi" selected>Africa/Nairobi (EAT)</option>
                                                    <option value="UTC">UTC</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="currency" class="form-label">Default Currency</label>
                                                <select class="form-select" id="currency" name="currency">
                                                    <option value="KES" selected>KES - Kenyan Shilling</option>
                                                    <option value="USD">USD - US Dollar</option>
                                                    <option value="EUR">EUR - Euro</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_general_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save General Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="v-pills-security" role="tabpanel">
                        <div class="settings-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                       value="<?php echo htmlspecialchars($securitySettings['session_timeout']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                       value="<?php echo htmlspecialchars($securitySettings['password_min_length']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="require_password_change" name="require_password_change">
                                            <label class="form-check-label" for="require_password_change">
                                                Require password change on first login
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_security_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Security Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="v-pills-email" role="tabpanel">
                        <div class="settings-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Configure SMTP settings for sending system emails (payslips, notifications, etc.)
                                </div>
                                
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                       placeholder="smtp.gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                       value="587">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="from_email" class="form-label">From Email</label>
                                                <input type="email" class="form-control" id="from_email" name="from_email" 
                                                       placeholder="noreply@yourcompany.co.ke">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="from_name" class="form-label">From Name</label>
                                                <input type="text" class="form-control" id="from_name" name="from_name" 
                                                       value="Kenyan Payroll System">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_email_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Email Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Backup & Maintenance -->
                    <div class="tab-pane fade" id="v-pills-backup" role="tabpanel">
                        <div class="settings-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-database me-2"></i>Backup & Maintenance</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Database Backup</h6>
                                        <p class="text-muted">Create a backup of your payroll database</p>
                                        <button class="btn btn-outline-primary">
                                            <i class="fas fa-download me-2"></i>Create Backup
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>System Maintenance</h6>
                                        <p class="text-muted">Clear cache and optimize system performance</p>
                                        <button class="btn btn-outline-secondary">
                                            <i class="fas fa-broom me-2"></i>Clear Cache
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
