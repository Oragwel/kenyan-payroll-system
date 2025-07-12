<?php
/**
 * Company Management System
 * Manage company settings, information, and configuration
 */

// Security check - Admin only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'settings';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update_company':
            $result = updateCompanySettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'update_tax_settings':
            $result = updateTaxSettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'update_statutory_settings':
            $result = updateStatutorySettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

/**
 * Update company basic settings
 */
function updateCompanySettings($data) {
    global $db;
    
    try {
        $companyId = $_SESSION['company_id'];
        
        $stmt = $db->prepare("
            UPDATE companies SET 
                name = ?, 
                address = ?, 
                phone = ?, 
                email = ?, 
                website = ?,
                kra_pin = ?,
                business_registration = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            trim($data['company_name']),
            trim($data['company_address']),
            trim($data['company_phone']),
            trim($data['company_email']),
            trim($data['company_website']),
            trim($data['kra_pin']),
            trim($data['business_registration']),
            $companyId
        ]);
        
        logActivity('company_update', "Updated company settings");
        return ['message' => 'Company settings updated successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating company settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update tax settings
 */
function updateTaxSettings($data) {
    global $db;
    
    try {
        $companyId = $_SESSION['company_id'];
        
        // Update or insert tax settings
        $taxSettings = [
            'paye_rate_band_1' => floatval($data['paye_rate_band_1'] ?? 10),
            'paye_rate_band_2' => floatval($data['paye_rate_band_2'] ?? 25),
            'paye_rate_band_3' => floatval($data['paye_rate_band_3'] ?? 30),
            'paye_rate_band_4' => floatval($data['paye_rate_band_4'] ?? 32.5),
            'paye_rate_band_5' => floatval($data['paye_rate_band_5'] ?? 35),
            'nssf_rate' => floatval($data['nssf_rate'] ?? 6),
            'nssf_max_amount' => floatval($data['nssf_max_amount'] ?? 2160),
            'nhif_rate' => floatval($data['nhif_rate'] ?? 2.75),
            'housing_levy_rate' => floatval($data['housing_levy_rate'] ?? 1.5)
        ];
        
        foreach ($taxSettings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO company_settings (company_id, setting_key, setting_value, setting_category) 
                VALUES (?, ?, ?, 'tax_settings')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$companyId, $key, $value]);
        }
        
        logActivity('tax_settings_update', "Updated tax settings");
        return ['message' => 'Tax settings updated successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating tax settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update statutory settings
 */
function updateStatutorySettings($data) {
    global $db;
    
    try {
        $companyId = $_SESSION['company_id'];
        
        $statutorySettings = [
            'annual_leave_days' => intval($data['annual_leave_days'] ?? 21),
            'sick_leave_days' => intval($data['sick_leave_days'] ?? 7),
            'maternity_leave_days' => intval($data['maternity_leave_days'] ?? 90),
            'paternity_leave_days' => intval($data['paternity_leave_days'] ?? 14),
            'working_days_per_week' => intval($data['working_days_per_week'] ?? 5),
            'working_hours_per_day' => floatval($data['working_hours_per_day'] ?? 8),
            'overtime_rate_multiplier' => floatval($data['overtime_rate_multiplier'] ?? 1.5)
        ];
        
        foreach ($statutorySettings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO company_settings (company_id, setting_key, setting_value, setting_category) 
                VALUES (?, ?, ?, 'statutory_settings')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$companyId, $key, $value]);
        }
        
        logActivity('statutory_settings_update', "Updated statutory settings");
        return ['message' => 'Statutory settings updated successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating statutory settings: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Create company_settings table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS company_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            setting_category VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            UNIQUE KEY unique_company_setting (company_id, setting_key)
        )
    ");
} catch (Exception $e) {
    // Table creation failed, but continue
}

// Get current company data
$stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch();

// Get company settings
$stmt = $db->prepare("SELECT setting_key, setting_value, setting_category FROM company_settings WHERE company_id = ?");
$stmt->execute([$_SESSION['company_id']]);
$settingsData = $stmt->fetchAll();

$settings = [];
foreach ($settingsData as $setting) {
    $settings[$setting['setting_category']][$setting['setting_key']] = $setting['setting_value'];
}

// Default values
$taxDefaults = [
    'paye_rate_band_1' => 10,
    'paye_rate_band_2' => 25,
    'paye_rate_band_3' => 30,
    'paye_rate_band_4' => 32.5,
    'paye_rate_band_5' => 35,
    'nssf_rate' => 6,
    'nssf_max_amount' => 2160,
    'nhif_rate' => 2.75,
    'housing_levy_rate' => 1.5
];

$statutoryDefaults = [
    'annual_leave_days' => 21,
    'sick_leave_days' => 7,
    'maternity_leave_days' => 90,
    'paternity_leave_days' => 14,
    'working_days_per_week' => 5,
    'working_hours_per_day' => 8,
    'overtime_rate_multiplier' => 1.5
];

$taxSettings = array_merge($taxDefaults, $settings['tax_settings'] ?? []);
$statutorySettings = array_merge($statutoryDefaults, $settings['statutory_settings'] ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Settings - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
            --kenya-black: #000000;
        }

        .company-header {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .company-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .company-card:hover {
            transform: translateY(-2px);
        }

        .btn-company {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-company:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,107,63,0.3);
            color: white;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--kenya-green);
            font-weight: 600;
        }

        .nav-tabs .nav-link.active {
            background: var(--kenya-green);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .kenyan-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left: 4px solid var(--kenya-gold);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
        }

        .setting-group {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--kenya-green);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="company-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-building me-3"></i>
                        Company Settings
                    </h1>
                    <p class="mb-0 opacity-75">
                        🏢 Configure company information, tax rates, and statutory compliance settings
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h5 class="mb-1"><?php echo htmlspecialchars($company['name'] ?? 'Company Name'); ?></h5>
                        <small class="opacity-75">KRA PIN: <?php echo htmlspecialchars($company['kra_pin'] ?? 'Not Set'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'settings' ? 'active' : ''; ?>"
                   href="index.php?page=companies&action=settings">
                    <i class="fas fa-building me-2"></i>Company Information
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'tax_settings' ? 'active' : ''; ?>"
                   href="index.php?page=companies&action=tax_settings">
                    <i class="fas fa-calculator me-2"></i>Tax Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'statutory_settings' ? 'active' : ''; ?>"
                   href="index.php?page=companies&action=statutory_settings">
                    <i class="fas fa-gavel me-2"></i>Statutory Settings
                </a>
            </li>
        </ul>

        <?php if ($action === 'settings'): ?>
            <!-- Company Information -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="company-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-building me-2"></i>
                                Company Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name"
                                                   value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="kra_pin" class="form-label">KRA PIN *</label>
                                            <input type="text" class="form-control" id="kra_pin" name="kra_pin"
                                                   value="<?php echo htmlspecialchars($company['kra_pin'] ?? ''); ?>"
                                                   placeholder="A123456789P" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="company_address" class="form-label">Address</label>
                                            <textarea class="form-control" id="company_address" name="company_address" rows="3"
                                                      placeholder="Company physical address"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_phone" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" id="company_phone" name="company_phone"
                                                   value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>"
                                                   placeholder="+254 700 000 000">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="company_email" name="company_email"
                                                   value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>"
                                                   placeholder="info@company.co.ke">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="company_website" name="company_website"
                                                   value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>"
                                                   placeholder="https://www.company.co.ke">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="business_registration" class="form-label">Business Registration Number</label>
                                            <input type="text" class="form-control" id="business_registration" name="business_registration"
                                                   value="<?php echo htmlspecialchars($company['business_registration'] ?? ''); ?>"
                                                   placeholder="Business registration/certificate number">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="action" value="update_company" class="btn btn-company">
                                        <i class="fas fa-save me-2"></i>Update Company Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Company Guidelines -->
                    <div class="company-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                🇰🇪 Kenyan Business Requirements
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="kenyan-info">
                                <h6>Required Information:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-id-card text-primary me-2"></i>
                                        <strong>KRA PIN:</strong> Required for tax compliance
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-certificate text-success me-2"></i>
                                        <strong>Business Registration:</strong> Certificate of incorporation
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-map-marker-alt text-warning me-2"></i>
                                        <strong>Physical Address:</strong> Required for statutory filings
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-envelope text-info me-2"></i>
                                        <strong>Contact Details:</strong> For official correspondence
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'tax_settings'): ?>
            <!-- Tax Settings -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="company-card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>
                                Kenyan Tax Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- PAYE Tax Bands -->
                                <div class="setting-group">
                                    <h6 class="text-danger mb-3">
                                        <i class="fas fa-percentage me-2"></i>
                                        PAYE Tax Bands (2024 Rates)
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Band 1 Rate (0 - 24,000) %</label>
                                                <input type="number" class="form-control" name="paye_rate_band_1"
                                                       value="<?php echo $taxSettings['paye_rate_band_1']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Band 2 Rate (24,001 - 32,333) %</label>
                                                <input type="number" class="form-control" name="paye_rate_band_2"
                                                       value="<?php echo $taxSettings['paye_rate_band_2']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Band 3 Rate (32,334 - 500,000) %</label>
                                                <input type="number" class="form-control" name="paye_rate_band_3"
                                                       value="<?php echo $taxSettings['paye_rate_band_3']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Band 4 Rate (500,001 - 800,000) %</label>
                                                <input type="number" class="form-control" name="paye_rate_band_4"
                                                       value="<?php echo $taxSettings['paye_rate_band_4']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Band 5 Rate (Above 800,000) %</label>
                                                <input type="number" class="form-control" name="paye_rate_band_5"
                                                       value="<?php echo $taxSettings['paye_rate_band_5']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statutory Deductions -->
                                <div class="setting-group">
                                    <h6 class="text-success mb-3">
                                        <i class="fas fa-shield-alt me-2"></i>
                                        Statutory Deductions
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">NSSF Rate %</label>
                                                <input type="number" class="form-control" name="nssf_rate"
                                                       value="<?php echo $taxSettings['nssf_rate']; ?>"
                                                       step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">NSSF Maximum Amount (KES)</label>
                                                <input type="number" class="form-control" name="nssf_max_amount"
                                                       value="<?php echo $taxSettings['nssf_max_amount']; ?>"
                                                       step="1" min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SHIF/NHIF Rate %</label>
                                                <input type="number" class="form-control" name="nhif_rate"
                                                       value="<?php echo $taxSettings['nhif_rate']; ?>"
                                                       step="0.01" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Housing Levy Rate %</label>
                                                <input type="number" class="form-control" name="housing_levy_rate"
                                                       value="<?php echo $taxSettings['housing_levy_rate']; ?>"
                                                       step="0.01" min="0" max="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="action" value="update_tax_settings" class="btn btn-company">
                                        <i class="fas fa-save me-2"></i>Update Tax Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Tax Guidelines -->
                    <div class="company-card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                🇰🇪 Current Kenyan Tax Rates
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="kenyan-info">
                                <h6>PAYE Tax Bands (2024):</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-1">
                                        <strong>0 - 24,000:</strong> 10%
                                    </li>
                                    <li class="mb-1">
                                        <strong>24,001 - 32,333:</strong> 25%
                                    </li>
                                    <li class="mb-1">
                                        <strong>32,334 - 500,000:</strong> 30%
                                    </li>
                                    <li class="mb-1">
                                        <strong>500,001 - 800,000:</strong> 32.5%
                                    </li>
                                    <li class="mb-1">
                                        <strong>Above 800,000:</strong> 35%
                                    </li>
                                </ul>

                                <hr>

                                <h6>Statutory Deductions:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-1">
                                        <strong>NSSF:</strong> 6% (max KES 2,160)
                                    </li>
                                    <li class="mb-1">
                                        <strong>SHIF:</strong> 2.75% of gross salary
                                    </li>
                                    <li class="mb-1">
                                        <strong>Housing Levy:</strong> 1.5% of gross salary
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'statutory_settings'): ?>
            <!-- Statutory Settings -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="company-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-gavel me-2"></i>
                                Statutory Employment Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Leave Entitlements -->
                                <div class="setting-group">
                                    <h6 class="text-success mb-3">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        Leave Entitlements (Days per Year)
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Annual Leave Days</label>
                                                <input type="number" class="form-control" name="annual_leave_days"
                                                       value="<?php echo $statutorySettings['annual_leave_days']; ?>"
                                                       min="21" max="365">
                                                <div class="form-text">Minimum 21 days per Kenyan law</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Sick Leave Days</label>
                                                <input type="number" class="form-control" name="sick_leave_days"
                                                       value="<?php echo $statutorySettings['sick_leave_days']; ?>"
                                                       min="7" max="365">
                                                <div class="form-text">Minimum 7 days per Kenyan law</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Maternity Leave Days</label>
                                                <input type="number" class="form-control" name="maternity_leave_days"
                                                       value="<?php echo $statutorySettings['maternity_leave_days']; ?>"
                                                       min="90" max="365">
                                                <div class="form-text">90 days (3 months) per Kenyan law</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Paternity Leave Days</label>
                                                <input type="number" class="form-control" name="paternity_leave_days"
                                                       value="<?php echo $statutorySettings['paternity_leave_days']; ?>"
                                                       min="14" max="365">
                                                <div class="form-text">14 days (2 weeks) per Kenyan law</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Working Hours -->
                                <div class="setting-group">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-clock me-2"></i>
                                        Working Hours & Overtime
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Working Days per Week</label>
                                                <input type="number" class="form-control" name="working_days_per_week"
                                                       value="<?php echo $statutorySettings['working_days_per_week']; ?>"
                                                       min="1" max="7">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Working Hours per Day</label>
                                                <input type="number" class="form-control" name="working_hours_per_day"
                                                       value="<?php echo $statutorySettings['working_hours_per_day']; ?>"
                                                       step="0.5" min="1" max="24">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Overtime Rate Multiplier</label>
                                                <input type="number" class="form-control" name="overtime_rate_multiplier"
                                                       value="<?php echo $statutorySettings['overtime_rate_multiplier']; ?>"
                                                       step="0.1" min="1" max="5">
                                                <div class="form-text">1.5x for overtime (Kenyan standard)</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="action" value="update_statutory_settings" class="btn btn-company">
                                        <i class="fas fa-save me-2"></i>Update Statutory Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Statutory Guidelines -->
                    <div class="company-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-book me-2"></i>
                                🇰🇪 Kenyan Employment Act
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="kenyan-info">
                                <h6>Minimum Requirements:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-calendar text-success me-2"></i>
                                        <strong>Annual Leave:</strong> Minimum 21 days per year
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-thermometer-half text-warning me-2"></i>
                                        <strong>Sick Leave:</strong> 7 days with medical certificate
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-baby text-pink me-2"></i>
                                        <strong>Maternity:</strong> 3 months (90 days)
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-male text-primary me-2"></i>
                                        <strong>Paternity:</strong> 2 weeks (14 days)
                                    </li>
                                </ul>

                                <hr>

                                <h6>Working Hours:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-1">
                                        <strong>Standard:</strong> 8 hours/day, 5 days/week
                                    </li>
                                    <li class="mb-1">
                                        <strong>Maximum:</strong> 52 hours per week
                                    </li>
                                    <li class="mb-1">
                                        <strong>Overtime:</strong> 1.5x normal rate
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Form validation and enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // KRA PIN validation
            const kraPinInput = document.getElementById('kra_pin');
            if (kraPinInput) {
                kraPinInput.addEventListener('input', function() {
                    const value = this.value.toUpperCase();
                    const pattern = /^[A-Z]\d{9}[A-Z]$/;

                    if (value && !pattern.test(value)) {
                        this.setCustomValidity('KRA PIN format: A123456789P');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Phone number validation
            const phoneInput = document.getElementById('company_phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    const value = this.value;
                    const pattern = /^\+254\d{9}$/;

                    if (value && !pattern.test(value)) {
                        this.setCustomValidity('Phone format: +254700000000');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Tax rate validation
            const taxInputs = document.querySelectorAll('input[name*="rate"]');
            taxInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    if (value < 0 || value > 100) {
                        this.setCustomValidity('Rate must be between 0 and 100');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });
        });
    </script>
</body>
</html>
