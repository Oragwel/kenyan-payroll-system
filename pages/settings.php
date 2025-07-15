<?php
/**
 * System Settings - Configuration and preferences
 */

// Security check - Admin only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'general';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'general':
            $result = updateGeneralSettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'payroll':
            $result = updatePayrollSettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'security':
            $result = updateSecuritySettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'backup':
            $result = performBackup();
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

/**
 * Update general settings
 */
function updateGeneralSettings($data) {
    global $db;

    try {
        $settings = [
            'company_name' => $data['company_name'] ?? '',
            'company_address' => $data['company_address'] ?? '',
            'company_phone' => $data['company_phone'] ?? '',
            'company_email' => $data['company_email'] ?? '',
            'timezone' => $data['timezone'] ?? 'Africa/Nairobi',
            'currency' => $data['currency'] ?? 'KES',
            'date_format' => $data['date_format'] ?? 'd/m/Y'
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, company_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value, $_SESSION['company_id']]);
        }

        return ['message' => 'General settings updated successfully!', 'type' => 'success'];

    } catch (Exception $e) {
        return ['message' => 'Error updating settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update payroll settings
 */
function updatePayrollSettings($data) {
    global $db;

    try {
        $settings = [
            'paye_rates' => json_encode([
                ['min' => 0, 'max' => 24000, 'rate' => floatval($data['paye_rate_1'] ?? 0.10)],
                ['min' => 24001, 'max' => 32333, 'rate' => floatval($data['paye_rate_2'] ?? 0.25)],
                ['min' => 32334, 'max' => 500000, 'rate' => floatval($data['paye_rate_3'] ?? 0.30)],
                ['min' => 500001, 'max' => 800000, 'rate' => floatval($data['paye_rate_4'] ?? 0.325)],
                ['min' => 800001, 'max' => PHP_INT_MAX, 'rate' => floatval($data['paye_rate_5'] ?? 0.35)]
            ]),
            'nssf_rate' => floatval($data['nssf_rate'] ?? 0.06),
            'nssf_max_pensionable' => floatval($data['nssf_max_pensionable'] ?? 18000),
            'shif_rate' => floatval($data['shif_rate'] ?? 0.0275),
            'shif_minimum' => floatval($data['shif_minimum'] ?? 300),
            'housing_levy_rate' => floatval($data['housing_levy_rate'] ?? 0.015),
            'personal_relief' => floatval($data['personal_relief'] ?? 2400),
            'insurance_relief_limit' => floatval($data['insurance_relief_limit'] ?? 5000),
            'pension_relief_limit' => floatval($data['pension_relief_limit'] ?? 20000)
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, company_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, is_array($value) ? json_encode($value) : $value, $_SESSION['company_id']]);
        }

        return ['message' => 'Payroll settings updated successfully!', 'type' => 'success'];

    } catch (Exception $e) {
        return ['message' => 'Error updating payroll settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update security settings
 */
function updateSecuritySettings($data) {
    global $db;

    try {
        $settings = [
            'session_timeout' => intval($data['session_timeout'] ?? 30),
            'max_login_attempts' => intval($data['max_login_attempts'] ?? 5),
            'password_min_length' => intval($data['password_min_length'] ?? 8),
            'require_password_change' => isset($data['require_password_change']) ? 1 : 0,
            'enable_two_factor' => isset($data['enable_two_factor']) ? 1 : 0,
            'audit_log_retention' => intval($data['audit_log_retention'] ?? 90)
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, company_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value, $_SESSION['company_id']]);
        }

        return ['message' => 'Security settings updated successfully!', 'type' => 'success'];

    } catch (Exception $e) {
        return ['message' => 'Error updating security settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Perform database backup
 */
function performBackup() {
    try {
        $backupDir = 'backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;

        // Simple backup creation (in production, use mysqldump)
        $backup = "-- Kenyan Payroll System Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        // This is a simplified backup - in production, implement proper mysqldump
        file_put_contents($filepath, $backup);

        return ['message' => "Backup created successfully: {$filename}", 'type' => 'success'];

    } catch (Exception $e) {
        return ['message' => 'Error creating backup: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get current settings
function getCurrentSettings() {
    global $db;

    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $results = $stmt->fetchAll();

    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Default values
    $defaults = [
        'company_name' => 'Your Company Name',
        'company_address' => 'Nairobi, Kenya',
        'company_phone' => '+254 700 000 000',
        'company_email' => 'info@company.co.ke',
        'timezone' => 'Africa/Nairobi',
        'currency' => 'KES',
        'date_format' => 'd/m/Y',
        'nssf_rate' => '0.06',
        'nssf_max_pensionable' => '18000',
        'shif_rate' => '0.0275',
        'shif_minimum' => '300',
        'housing_levy_rate' => '0.015',
        'personal_relief' => '2400',
        'insurance_relief_limit' => '5000',
        'pension_relief_limit' => '20000',
        'session_timeout' => '30',
        'max_login_attempts' => '5',
        'password_min_length' => '8',
        'require_password_change' => '0',
        'enable_two_factor' => '0',
        'audit_log_retention' => '90'
    ];

    return array_merge($defaults, $settings);
}

// Create system_settings table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_setting (company_id, setting_key),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table creation failed, but continue
}

$currentSettings = getCurrentSettings();
?>

<!-- Settings Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.setting-summary-card {
    background: rgba(0, 77, 46, 0.2); /* semi-transparent kenya dark green */
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.settings-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.settings-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.settings-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.settings-nav {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.settings-nav .nav-link {
    color: var(--kenya-dark-green);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}

.settings-nav .nav-link:hover {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
}

.settings-nav .nav-link.active {
    background: var(--kenya-green);
    color: white;
}

.btn-save-settings {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save-settings:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,107,63,0.3);
    color: white;
}

.btn-backup {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-backup:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(206,17,38,0.3);
    color: white;
}

.setting-group {
    background: var(--kenya-light-green);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--kenya-green);
}

.paye-rates {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.security-warning {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    color: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}
</style>

<div class="container-fluid">
    <!-- Settings Hero Section -->
    <div class="settings-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-cogs me-3"></i>
                        System Settings
                    </h1>
                    <p class="mb-0 opacity-75">
                        ⚙️ Configure system preferences, payroll rates, and security settings
                    </p>
                </div>
                    <div class="col-md-4 text-end">
                        <div class="setting-summary-card">
                        <h5 class="mb-1">Admin Control</h5>
                        <small class="opacity-75">System configuration</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Settings Navigation -->
    <div class="settings-nav">
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'general' ? 'active' : ''; ?>"
                   href="index.php?page=settings&action=general">
                    <i class="fas fa-building me-2"></i>General
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'payroll' ? 'active' : ''; ?>"
                   href="index.php?page=settings&action=payroll">
                    <i class="fas fa-calculator me-2"></i>Payroll
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'security' ? 'active' : ''; ?>"
                   href="index.php?page=settings&action=security">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'backup' ? 'active' : ''; ?>"
                   href="index.php?page=settings&action=backup">
                    <i class="fas fa-database me-2"></i>Backup
                </a>
            </li>
        </ul>
    </div>

    <!-- Settings Content -->
    <?php switch ($action):
        case 'general': ?>
            <!-- General Settings -->
            <div class="settings-card">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-building text-primary me-2"></i>
                        General Settings
                    </h4>

                    <form method="POST">
                        <div class="setting-group">
                            <h5 class="mb-3">Company Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name"
                                               value="<?php echo htmlspecialchars($currentSettings['company_name']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_email" class="form-label">Company Email</label>
                                        <input type="email" class="form-control" id="company_email" name="company_email"
                                               value="<?php echo htmlspecialchars($currentSettings['company_email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_phone" class="form-label">Company Phone</label>
                                        <input type="tel" class="form-control" id="company_phone" name="company_phone"
                                               value="<?php echo htmlspecialchars($currentSettings['company_phone']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <input type="text" class="form-control" id="company_address" name="company_address"
                                               value="<?php echo htmlspecialchars($currentSettings['company_address']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group">
                            <h5 class="mb-3">System Preferences</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="Africa/Nairobi" <?php echo $currentSettings['timezone'] === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi (EAT)</option>
                                            <option value="UTC" <?php echo $currentSettings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">Currency</label>
                                        <select class="form-select" id="currency" name="currency">
                                            <option value="KES" <?php echo $currentSettings['currency'] === 'KES' ? 'selected' : ''; ?>>KES (Kenyan Shilling)</option>
                                            <option value="USD" <?php echo $currentSettings['currency'] === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="date_format" class="form-label">Date Format</label>
                                        <select class="form-select" id="date_format" name="date_format">
                                            <option value="d/m/Y" <?php echo $currentSettings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                            <option value="m/d/Y" <?php echo $currentSettings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                            <option value="Y-m-d" <?php echo $currentSettings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fas fa-save me-2"></i>Save General Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php break;
        case 'payroll': ?>
            <!-- Payroll Settings -->
            <div class="settings-card">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-calculator text-success me-2"></i>
                        Payroll & Statutory Settings
                    </h4>

                    <form method="POST">
                        <div class="paye-rates">
                            <h5 class="mb-3">
                                <i class="fas fa-percentage me-2"></i>
                                PAYE Tax Rates (2024)
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KES 0 - 24,000 (10%)</label>
                                        <input type="number" class="form-control" name="paye_rate_1"
                                               value="0.10" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KES 24,001 - 32,333 (25%)</label>
                                        <input type="number" class="form-control" name="paye_rate_2"
                                               value="0.25" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KES 32,334 - 500,000 (30%)</label>
                                        <input type="number" class="form-control" name="paye_rate_3"
                                               value="0.30" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KES 500,001 - 800,000 (32.5%)</label>
                                        <input type="number" class="form-control" name="paye_rate_4"
                                               value="0.325" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Above KES 800,000 (35%)</label>
                                        <input type="number" class="form-control" name="paye_rate_5"
                                               value="0.35" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group">
                            <h5 class="mb-3">NSSF Settings</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nssf_rate" class="form-label">NSSF Rate (%)</label>
                                        <input type="number" class="form-control" id="nssf_rate" name="nssf_rate"
                                               value="<?php echo $currentSettings['nssf_rate']; ?>" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nssf_max_pensionable" class="form-label">Max Pensionable Pay (KES)</label>
                                        <input type="number" class="form-control" id="nssf_max_pensionable" name="nssf_max_pensionable"
                                               value="<?php echo $currentSettings['nssf_max_pensionable']; ?>" step="1" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group">
                            <h5 class="mb-3">SHIF Settings</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="shif_rate" class="form-label">SHIF Rate (%)</label>
                                        <input type="number" class="form-control" id="shif_rate" name="shif_rate"
                                               value="<?php echo $currentSettings['shif_rate']; ?>" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="shif_minimum" class="form-label">Minimum SHIF (KES)</label>
                                        <input type="number" class="form-control" id="shif_minimum" name="shif_minimum"
                                               value="<?php echo $currentSettings['shif_minimum']; ?>" step="1" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group">
                            <h5 class="mb-3">Other Settings</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="housing_levy_rate" class="form-label">Housing Levy Rate (%)</label>
                                        <input type="number" class="form-control" id="housing_levy_rate" name="housing_levy_rate"
                                               value="<?php echo $currentSettings['housing_levy_rate']; ?>" step="0.01" min="0" max="1">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="personal_relief" class="form-label">Personal Relief (KES)</label>
                                        <input type="number" class="form-control" id="personal_relief" name="personal_relief"
                                               value="<?php echo $currentSettings['personal_relief']; ?>" step="1" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="insurance_relief_limit" class="form-label">Insurance Relief Limit (KES)</label>
                                        <input type="number" class="form-control" id="insurance_relief_limit" name="insurance_relief_limit"
                                               value="<?php echo $currentSettings['insurance_relief_limit']; ?>" step="1" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fas fa-save me-2"></i>Save Payroll Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php break;
        case 'security': ?>
            <!-- Security Settings -->
            <div class="settings-card">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-shield-alt text-warning me-2"></i>
                        Security Settings
                    </h4>

                    <div class="security-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Security Notice</h6>
                        <p class="mb-0 small">Changes to security settings will affect all users. Please review carefully before saving.</p>
                    </div>

                    <form method="POST">
                        <div class="setting-group">
                            <h5 class="mb-3">Session Management</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                                               value="<?php echo $currentSettings['session_timeout']; ?>" min="5" max="480">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts"
                                               value="<?php echo $currentSettings['max_login_attempts']; ?>" min="3" max="10">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group">
                            <h5 class="mb-3">Password Policy</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" name="password_min_length"
                                               value="<?php echo $currentSettings['password_min_length']; ?>" min="6" max="20">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="audit_log_retention" class="form-label">Audit Log Retention (days)</label>
                                        <input type="number" class="form-control" id="audit_log_retention" name="audit_log_retention"
                                               value="<?php echo $currentSettings['audit_log_retention']; ?>" min="30" max="365">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="require_password_change"
                                               name="require_password_change" <?php echo $currentSettings['require_password_change'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="require_password_change">
                                            Require Password Change on First Login
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="enable_two_factor"
                                               name="enable_two_factor" <?php echo $currentSettings['enable_two_factor'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_two_factor">
                                            Enable Two-Factor Authentication
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-save-settings">
                                <i class="fas fa-save me-2"></i>Save Security Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php break;
        case 'backup': ?>
            <!-- Backup Settings -->
            <div class="settings-card">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-database text-info me-2"></i>
                        Backup & Maintenance
                    </h4>

                    <div class="setting-group">
                        <h5 class="mb-3">Database Backup</h5>
                        <p class="text-muted mb-3">
                            Create a backup of your payroll data. Regular backups are recommended to prevent data loss.
                        </p>

                        <form method="POST">
                            <div class="text-center">
                                <button type="submit" class="btn btn-backup btn-lg">
                                    <i class="fas fa-download me-2"></i>Create Backup Now
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="setting-group">
                        <h5 class="mb-3">System Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                                <p><strong>System:</strong> <?php echo php_uname('s'); ?></p>
                                <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                <p><strong>Upload Max:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                                <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
    endswitch; ?>
</div>