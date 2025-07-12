<?php
/**
 * Content Management System (CMS) - Admin Only
 * Manage frontend content, landing page, and system appearance
 */

// SECURITY: Admin only access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update_landing':
            $result = updateLandingPageContent($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'update_company':
            $result = updateCompanyInfo($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'update_theme':
            $result = updateThemeSettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'upload_logo':
            $result = handleLogoUpload($_FILES['logo']);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

// Get current CMS settings
$cmsSettings = getCMSSettings();
$companyInfo = getCompanyInfo();
$themeSettings = getThemeSettings();

/**
 * Get CMS settings from database
 */
function getCMSSettings() {
    global $db;

    try {
        // Check if cms_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'cms_settings'");
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, return default settings
            return [
                'site_title' => 'Kenyan Payroll Management System',
                'site_description' => 'Professional payroll management for Kenyan businesses',
                'hero_title' => 'Streamline Your Payroll Process',
                'hero_subtitle' => 'Comprehensive payroll management system designed for Kenyan employment laws and regulations',
                'company_name' => 'Your Company Name',
                'contact_email' => 'info@yourcompany.com',
                'contact_phone' => '+254 700 000 000'
            ];
        }

        $stmt = $db->prepare("
            SELECT setting_key, setting_value, setting_type
            FROM cms_settings
            WHERE setting_category = 'landing_page'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Default values if not set
        $defaults = [
            'hero_title' => 'Kenyan Payroll Management System',
            'hero_subtitle' => 'Comprehensive payroll solution designed for Kenyan employment structure and statutory compliance requirements.',
            'feature_1_title' => 'Statutory Compliance',
            'feature_1_description' => 'Automated PAYE, NSSF, SHIF & Housing Levy calculations',
            'feature_2_title' => 'Employee Management',
            'feature_2_description' => 'Complete employee lifecycle management',
            'feature_3_title' => 'Advanced Reporting',
            'feature_3_description' => 'Generate comprehensive payroll and statutory reports',
            'feature_4_title' => 'Mobile Responsive',
            'feature_4_description' => 'Access your payroll system from any device',
            'footer_text' => '🇰🇪 Proudly Kenyan • Built for Kenya • Compliant with Kenyan Law 🇰🇪'
        ];

        return array_merge($defaults, $settings);

    } catch (Exception $e) {
        // Handle database errors gracefully
        error_log("CMS Settings error: " . $e->getMessage());

        // Return default settings
        return [
            'hero_title' => 'Kenyan Payroll Management System',
            'hero_subtitle' => 'Comprehensive payroll solution designed for Kenyan employment structure and statutory compliance requirements.',
            'feature_1_title' => 'Statutory Compliance',
            'feature_1_description' => 'Automated PAYE, NSSF, SHIF & Housing Levy calculations',
            'feature_2_title' => 'Employee Management',
            'feature_2_description' => 'Complete employee lifecycle management',
            'feature_3_title' => 'Advanced Reporting',
            'feature_3_description' => 'Generate comprehensive payroll and statutory reports',
            'feature_4_title' => 'Mobile Responsive',
            'feature_4_description' => 'Access your payroll system from any device',
            'footer_text' => '🇰🇪 Proudly Kenyan • Built for Kenya • Compliant with Kenyan Law 🇰🇪'
        ];
    }
}

/**
 * Get company information
 */
function getCompanyInfo() {
    global $db;

    try {
        // Check if cms_settings table exists
        $stmt = $db->query("SHOW TABLES LIKE 'cms_settings'");
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, return default company info
            return [
                'company_name' => 'Your Company Name',
                'company_address' => 'Your Company Address',
                'company_phone' => '+254 700 000 000',
                'company_email' => 'info@yourcompany.com',
                'company_website' => 'www.yourcompany.com'
            ];
        }

        $stmt = $db->prepare("
            SELECT setting_key, setting_value
            FROM cms_settings
            WHERE setting_category = 'company_info'
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();

        $info = [];
        foreach ($results as $row) {
            $info[$row['setting_key']] = $row['setting_value'];
        }

        // Default values
        $defaults = [
            'company_name' => 'Your Company Name',
            'company_address' => 'Nairobi, Kenya',
            'company_phone' => '+254 700 000 000',
            'company_email' => 'info@yourcompany.co.ke',
            'company_website' => 'www.yourcompany.co.ke',
            'company_logo' => '',
            'company_description' => 'Leading payroll management solutions in Kenya'
        ];

        return array_merge($defaults, $info);

    } catch (Exception $e) {
        // Handle database errors gracefully
        error_log("CMS Company Info error: " . $e->getMessage());

        // Return default company info
        return [
            'company_name' => 'Your Company Name',
            'company_address' => 'Nairobi, Kenya',
            'company_phone' => '+254 700 000 000',
            'company_email' => 'info@yourcompany.co.ke',
            'company_website' => 'www.yourcompany.co.ke',
            'company_logo' => '',
            'company_description' => 'Leading payroll management solutions in Kenya'
        ];
    }
}

/**
 * Get theme settings
 */
function getThemeSettings() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT setting_key, setting_value 
        FROM cms_settings 
        WHERE setting_category = 'theme'
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $theme = [];
    foreach ($results as $row) {
        $theme[$row['setting_key']] = $row['setting_value'];
    }
    
    // Default Kenyan flag colors
    $defaults = [
        'primary_color' => '#006b3f',
        'secondary_color' => '#ce1126',
        'accent_color' => '#000000',
        'background_color' => '#ffffff',
        'text_color' => '#1f2937',
        'enable_kenyan_theme' => '1',
        'show_flag_ribbons' => '1'
    ];
    
    return array_merge($defaults, $theme);
}

/**
 * Update landing page content
 */
function updateLandingPageContent($data) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $allowedFields = [
            'hero_title', 'hero_subtitle', 'feature_1_title', 'feature_1_description',
            'feature_2_title', 'feature_2_description', 'feature_3_title', 'feature_3_description',
            'feature_4_title', 'feature_4_description', 'footer_text'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $stmt = $db->prepare("
                    INSERT INTO cms_settings (setting_key, setting_value, setting_category, setting_type) 
                    VALUES (?, ?, 'landing_page', 'text')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$field, sanitizeInput($data[$field])]);
            }
        }
        
        $db->commit();
        return ['message' => 'Landing page content updated successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['message' => 'Error updating landing page: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update company information
 */
function updateCompanyInfo($data) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $allowedFields = [
            'company_name', 'company_address', 'company_phone', 'company_email',
            'company_website', 'company_description'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $stmt = $db->prepare("
                    INSERT INTO cms_settings (setting_key, setting_value, setting_category, setting_type) 
                    VALUES (?, ?, 'company_info', 'text')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$field, sanitizeInput($data[$field])]);
            }
        }
        
        $db->commit();
        return ['message' => 'Company information updated successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['message' => 'Error updating company info: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update theme settings
 */
function updateThemeSettings($data) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $allowedFields = [
            'primary_color', 'secondary_color', 'accent_color', 'background_color',
            'text_color', 'enable_kenyan_theme', 'show_flag_ribbons'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $stmt = $db->prepare("
                    INSERT INTO cms_settings (setting_key, setting_value, setting_category, setting_type) 
                    VALUES (?, ?, 'theme', 'text')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$field, sanitizeInput($data[$field])]);
            }
        }
        
        $db->commit();
        return ['message' => 'Theme settings updated successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['message' => 'Error updating theme: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Handle logo upload
 */
function handleLogoUpload($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['message' => 'No file selected', 'type' => 'warning'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['message' => 'Invalid file type. Please upload JPG, PNG, GIF, or WebP images.', 'type' => 'danger'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['message' => 'File too large. Maximum size is 2MB.', 'type' => 'danger'];
    }
    
    $uploadDir = 'uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'company_logo_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        global $db;
        
        // Update database
        $stmt = $db->prepare("
            INSERT INTO cms_settings (setting_key, setting_value, setting_category, setting_type) 
            VALUES ('company_logo', ?, 'company_info', 'file')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$uploadPath]);
        
        return ['message' => 'Logo uploaded successfully!', 'type' => 'success'];
    } else {
        return ['message' => 'Failed to upload logo.', 'type' => 'danger'];
    }
}

// Create CMS tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS cms_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            setting_category VARCHAR(50) NOT NULL,
            setting_type VARCHAR(20) DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_setting (setting_key, setting_category)
        )
    ");
} catch (Exception $e) {
    // Table creation failed, but continue
}
?>

<!-- CMS Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
    --kenya-gold: #ffd700;
}

.cms-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
    position: relative;
    overflow: hidden;
}

.cms-hero::before {
    content: '';
    position: absolute;
    top: -20px;
    left: -50px;
    width: 110%;
    height: 80px;
    background: linear-gradient(45deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
    transform: rotate(-3deg);
    opacity: 0.2;
}

.cms-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.cms-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
}

.cms-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    transition: all 0.3s ease;
}

.cms-nav {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.cms-nav .nav-link {
    color: var(--kenya-dark-green);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}

.cms-nav .nav-link:hover {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
}

.cms-nav .nav-link.active {
    background: var(--kenya-green);
    color: white;
}

.color-picker-wrapper {
    position: relative;
    display: inline-block;
}

.color-preview {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 2px solid #ddd;
    cursor: pointer;
    display: inline-block;
    margin-left: 10px;
    vertical-align: middle;
}

.preview-section {
    background: var(--kenya-light-green);
    border-radius: 15px;
    padding: 2rem;
    margin-top: 1rem;
    border: 2px dashed var(--kenya-green);
}

.logo-preview {
    max-width: 200px;
    max-height: 100px;
    border-radius: 8px;
    border: 2px solid #ddd;
    padding: 10px;
    background: white;
}

.feature-preview {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin: 1rem 0;
    border-left: 4px solid var(--kenya-green);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
</style>

<div class="container-fluid">
    <!-- CMS Hero Section -->
    <div class="cms-hero">
        <div class="container-fluid position-relative" style="z-index: 2;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-edit me-3"></i>
                        Content Management System
                    </h1>
                    <p class="mb-0 opacity-75">
                        🎨 Customize your frontend, manage content, and control system appearance
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h5 class="mb-1">Admin Control Panel</h5>
                        <small class="opacity-75">Full customization access</small>
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

    <!-- CMS Navigation -->
    <div class="cms-nav">
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'dashboard' ? 'active' : ''; ?>"
                   href="index.php?page=cms&action=dashboard">
                    <i class="fas fa-tachometer-alt me-2"></i>Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'landing' ? 'active' : ''; ?>"
                   href="index.php?page=cms&action=landing">
                    <i class="fas fa-home me-2"></i>Landing Page
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'company' ? 'active' : ''; ?>"
                   href="index.php?page=cms&action=company">
                    <i class="fas fa-building me-2"></i>Company Info
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'theme' ? 'active' : ''; ?>"
                   href="index.php?page=cms&action=theme">
                    <i class="fas fa-palette me-2"></i>Theme Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'preview' ? 'active' : ''; ?>"
                   href="index.php?page=cms&action=preview">
                    <i class="fas fa-eye me-2"></i>Live Preview
                </a>
            </li>
        </ul>
    </div>

    <!-- CMS Content Sections -->
    <?php switch ($action):
        case 'dashboard': ?>
            <!-- CMS Overview Dashboard -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="cms-card position-relative">
                        <div class="p-4">
                            <h4 class="mb-3">
                                <i class="fas fa-chart-pie text-success me-2"></i>
                                Content Management Overview
                            </h4>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="feature-preview">
                                        <h6><i class="fas fa-home text-primary me-2"></i>Landing Page</h6>
                                        <p class="mb-2 text-muted">Customize hero section, features, and footer content</p>
                                        <a href="index.php?page=cms&action=landing" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit Content
                                        </a>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="feature-preview">
                                        <h6><i class="fas fa-building text-info me-2"></i>Company Information</h6>
                                        <p class="mb-2 text-muted">Update company details, contact info, and logo</p>
                                        <a href="index.php?page=cms&action=company" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-edit me-1"></i>Edit Info
                                        </a>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="feature-preview">
                                        <h6><i class="fas fa-palette text-warning me-2"></i>Theme Customization</h6>
                                        <p class="mb-2 text-muted">Customize colors and Kenyan flag theme settings</p>
                                        <a href="index.php?page=cms&action=theme" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-palette me-1"></i>Customize Theme
                                        </a>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="feature-preview">
                                        <h6><i class="fas fa-eye text-success me-2"></i>Live Preview</h6>
                                        <p class="mb-2 text-muted">Preview your changes before publishing</p>
                                        <a href="index.php?page=cms&action=preview" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-eye me-1"></i>View Preview
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="cms-card position-relative">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                Quick Stats
                            </h5>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Landing Page Content</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <small class="text-muted">Last updated: <?php echo date('M j, Y'); ?></small>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Company Logo</span>
                                    <span class="badge bg-<?php echo !empty($companyInfo['company_logo']) ? 'success' : 'warning'; ?>">
                                        <?php echo !empty($companyInfo['company_logo']) ? 'Uploaded' : 'Default'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Kenyan Theme</span>
                                    <span class="badge bg-<?php echo $themeSettings['enable_kenyan_theme'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $themeSettings['enable_kenyan_theme'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                <a href="landing.html" target="_blank" class="btn btn-success btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    View Live Site
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Kenyan Pride Section -->
                    <div class="cms-card position-relative">
                        <div class="p-4 text-center" style="background: linear-gradient(45deg, var(--kenya-green), var(--kenya-dark-green)); color: white; border-radius: 15px;">
                            <h6 class="mb-2">🇰🇪 Kenyan Heritage</h6>
                            <p class="mb-0 small opacity-75">
                                This CMS maintains the beautiful Kenyan flag theme throughout your system while providing full customization control.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
        case 'landing': ?>
            <!-- Landing Page Content Management -->
            <div class="cms-card position-relative">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-home text-primary me-2"></i>
                        Landing Page Content Management
                    </h4>

                    <form method="POST" action="index.php?page=cms&action=update_landing">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Hero Section -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-star text-warning me-2"></i>Hero Section
                                    </h5>

                                    <div class="mb-3">
                                        <label for="hero_title" class="form-label">Hero Title</label>
                                        <input type="text" class="form-control" id="hero_title" name="hero_title"
                                               value="<?php echo htmlspecialchars($cmsSettings['hero_title']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="hero_subtitle" class="form-label">Hero Subtitle</label>
                                        <textarea class="form-control" id="hero_subtitle" name="hero_subtitle" rows="3" required><?php echo htmlspecialchars($cmsSettings['hero_subtitle']); ?></textarea>
                                    </div>
                                </div>

                                <!-- Features Section -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-list text-info me-2"></i>Features Section
                                    </h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="feature_1_title" class="form-label">Feature 1 Title</label>
                                                <input type="text" class="form-control" id="feature_1_title" name="feature_1_title"
                                                       value="<?php echo htmlspecialchars($cmsSettings['feature_1_title']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="feature_1_description" class="form-label">Feature 1 Description</label>
                                                <textarea class="form-control" id="feature_1_description" name="feature_1_description" rows="2"><?php echo htmlspecialchars($cmsSettings['feature_1_description']); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="feature_2_title" class="form-label">Feature 2 Title</label>
                                                <input type="text" class="form-control" id="feature_2_title" name="feature_2_title"
                                                       value="<?php echo htmlspecialchars($cmsSettings['feature_2_title']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="feature_2_description" class="form-label">Feature 2 Description</label>
                                                <textarea class="form-control" id="feature_2_description" name="feature_2_description" rows="2"><?php echo htmlspecialchars($cmsSettings['feature_2_description']); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="feature_3_title" class="form-label">Feature 3 Title</label>
                                                <input type="text" class="form-control" id="feature_3_title" name="feature_3_title"
                                                       value="<?php echo htmlspecialchars($cmsSettings['feature_3_title']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="feature_3_description" class="form-label">Feature 3 Description</label>
                                                <textarea class="form-control" id="feature_3_description" name="feature_3_description" rows="2"><?php echo htmlspecialchars($cmsSettings['feature_3_description']); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="feature_4_title" class="form-label">Feature 4 Title</label>
                                                <input type="text" class="form-control" id="feature_4_title" name="feature_4_title"
                                                       value="<?php echo htmlspecialchars($cmsSettings['feature_4_title']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="feature_4_description" class="form-label">Feature 4 Description</label>
                                                <textarea class="form-control" id="feature_4_description" name="feature_4_description" rows="2"><?php echo htmlspecialchars($cmsSettings['feature_4_description']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Section -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-flag text-success me-2"></i>Footer Section
                                    </h5>

                                    <div class="mb-3">
                                        <label for="footer_text" class="form-label">Footer Text</label>
                                        <input type="text" class="form-control" id="footer_text" name="footer_text"
                                               value="<?php echo htmlspecialchars($cmsSettings['footer_text']); ?>">
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Landing Page Content
                                    </button>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Live Preview -->
                                <div class="preview-section">
                                    <h6 class="mb-3">
                                        <i class="fas fa-eye text-primary me-2"></i>Preview
                                    </h6>
                                    <div class="text-center">
                                        <div class="mb-3 p-3 bg-white rounded">
                                            <h6 class="text-success"><?php echo htmlspecialchars($cmsSettings['hero_title']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($cmsSettings['hero_subtitle'], 0, 100)); ?>...</small>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 mb-2">
                                                <div class="p-2 bg-white rounded">
                                                    <small class="fw-bold"><?php echo htmlspecialchars($cmsSettings['feature_1_title']); ?></small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="p-2 bg-white rounded">
                                                    <small class="fw-bold"><?php echo htmlspecialchars($cmsSettings['feature_2_title']); ?></small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="p-2 bg-white rounded">
                                                    <small class="fw-bold"><?php echo htmlspecialchars($cmsSettings['feature_3_title']); ?></small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <div class="p-2 bg-white rounded">
                                                    <small class="fw-bold"><?php echo htmlspecialchars($cmsSettings['feature_4_title']); ?></small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3 p-2 bg-white rounded">
                                            <small class="text-muted"><?php echo htmlspecialchars($cmsSettings['footer_text']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <?php break;
        case 'company': ?>
            <!-- Company Information Management -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="cms-card position-relative">
                        <div class="p-4">
                            <h4 class="mb-4">
                                <i class="fas fa-building text-info me-2"></i>
                                Company Information Management
                            </h4>

                            <form method="POST" action="index.php?page=cms&action=update_company">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name"
                                                   value="<?php echo htmlspecialchars($companyInfo['company_name']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="company_email" name="company_email"
                                                   value="<?php echo htmlspecialchars($companyInfo['company_email']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="company_phone" name="company_phone"
                                                   value="<?php echo htmlspecialchars($companyInfo['company_phone']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="company_website" name="company_website"
                                                   value="<?php echo htmlspecialchars($companyInfo['company_website']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="company_address" class="form-label">Address</label>
                                            <textarea class="form-control" id="company_address" name="company_address" rows="2"><?php echo htmlspecialchars($companyInfo['company_address']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="company_description" class="form-label">Company Description</label>
                                            <textarea class="form-control" id="company_description" name="company_description" rows="3"><?php echo htmlspecialchars($companyInfo['company_description']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-info btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Company Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Logo Upload -->
                    <div class="cms-card position-relative">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-image text-primary me-2"></i>
                                Company Logo
                            </h5>

                            <?php if (!empty($companyInfo['company_logo']) && file_exists($companyInfo['company_logo'])): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo htmlspecialchars($companyInfo['company_logo']); ?>"
                                         alt="Company Logo" class="logo-preview">
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="index.php?page=cms&action=upload_logo" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Upload New Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo"
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="form-text">
                                        Supported formats: JPG, PNG, GIF, WebP<br>
                                        Maximum size: 2MB
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-2"></i>Upload Logo
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Company Preview -->
                    <div class="cms-card position-relative">
                        <div class="p-4">
                            <h6 class="mb-3">
                                <i class="fas fa-eye text-success me-2"></i>Company Preview
                            </h6>

                            <div class="preview-section">
                                <div class="text-center">
                                    <?php if (!empty($companyInfo['company_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($companyInfo['company_logo']); ?>"
                                             alt="Logo" style="max-width: 100px; max-height: 50px;" class="mb-2">
                                    <?php endif; ?>
                                    <h6 class="text-success"><?php echo htmlspecialchars($companyInfo['company_name']); ?></h6>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($companyInfo['company_description']); ?></small>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($companyInfo['company_email']); ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($companyInfo['company_phone']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
        case 'theme': ?>
            <!-- Theme Settings Management -->
            <div class="cms-card position-relative">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-palette text-warning me-2"></i>
                        Theme Customization Settings
                    </h4>

                    <form method="POST" action="index.php?page=cms&action=update_theme">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Kenyan Theme Settings -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-flag text-success me-2"></i>Kenyan Flag Theme
                                    </h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="enable_kenyan_theme"
                                                       name="enable_kenyan_theme" value="1"
                                                       <?php echo $themeSettings['enable_kenyan_theme'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_kenyan_theme">
                                                    Enable Kenyan Flag Theme
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="show_flag_ribbons"
                                                       name="show_flag_ribbons" value="1"
                                                       <?php echo $themeSettings['show_flag_ribbons'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="show_flag_ribbons">
                                                    Show Flag Ribbon Patterns
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Color Customization -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-paint-brush text-info me-2"></i>Color Customization
                                    </h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="primary_color" class="form-label">Primary Color (Green)</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="color" class="form-control form-control-color" id="primary_color"
                                                           name="primary_color" value="<?php echo htmlspecialchars($themeSettings['primary_color']); ?>">
                                                    <input type="text" class="form-control ms-2"
                                                           value="<?php echo htmlspecialchars($themeSettings['primary_color']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="secondary_color" class="form-label">Secondary Color (Red)</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="color" class="form-control form-control-color" id="secondary_color"
                                                           name="secondary_color" value="<?php echo htmlspecialchars($themeSettings['secondary_color']); ?>">
                                                    <input type="text" class="form-control ms-2"
                                                           value="<?php echo htmlspecialchars($themeSettings['secondary_color']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="accent_color" class="form-label">Accent Color (Black)</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="color" class="form-control form-control-color" id="accent_color"
                                                           name="accent_color" value="<?php echo htmlspecialchars($themeSettings['accent_color']); ?>">
                                                    <input type="text" class="form-control ms-2"
                                                           value="<?php echo htmlspecialchars($themeSettings['accent_color']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="background_color" class="form-label">Background Color</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="color" class="form-control form-control-color" id="background_color"
                                                           name="background_color" value="<?php echo htmlspecialchars($themeSettings['background_color']); ?>">
                                                    <input type="text" class="form-control ms-2"
                                                           value="<?php echo htmlspecialchars($themeSettings['background_color']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="text_color" class="form-label">Text Color</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="color" class="form-control form-control-color" id="text_color"
                                                           name="text_color" value="<?php echo htmlspecialchars($themeSettings['text_color']); ?>">
                                                    <input type="text" class="form-control ms-2"
                                                           value="<?php echo htmlspecialchars($themeSettings['text_color']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="button" class="btn btn-outline-secondary me-2" onclick="resetToKenyanColors()">
                                        <i class="fas fa-undo me-2"></i>Reset to Kenyan Flag Colors
                                    </button>
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Theme Settings
                                    </button>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Theme Preview -->
                                <div class="preview-section">
                                    <h6 class="mb-3">
                                        <i class="fas fa-eye text-primary me-2"></i>Theme Preview
                                    </h6>

                                    <div class="theme-preview" id="themePreview">
                                        <div class="p-3 rounded mb-2" style="background: <?php echo $themeSettings['primary_color']; ?>; color: white;">
                                            <strong>Primary Color</strong><br>
                                            <small>Headers and main elements</small>
                                        </div>

                                        <div class="p-3 rounded mb-2" style="background: <?php echo $themeSettings['secondary_color']; ?>; color: white;">
                                            <strong>Secondary Color</strong><br>
                                            <small>Accent elements</small>
                                        </div>

                                        <div class="p-3 rounded mb-2" style="background: <?php echo $themeSettings['accent_color']; ?>; color: white;">
                                            <strong>Accent Color</strong><br>
                                            <small>Special highlights</small>
                                        </div>

                                        <div class="p-3 rounded border" style="background: <?php echo $themeSettings['background_color']; ?>; color: <?php echo $themeSettings['text_color']; ?>;">
                                            <strong>Background & Text</strong><br>
                                            <small>Main content area</small>
                                        </div>
                                    </div>

                                    <?php if ($themeSettings['enable_kenyan_theme']): ?>
                                        <div class="mt-3 p-2 text-center" style="background: linear-gradient(90deg, #000000, #ce1126, #ffffff, #006b3f); border-radius: 8px;">
                                            <small style="color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">
                                                🇰🇪 Kenyan Flag Theme Active
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <?php break;
        case 'preview': ?>
            <!-- Live Preview Section -->
            <div class="cms-card position-relative">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-eye text-success me-2"></i>
                        Live Preview & Testing
                    </h4>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="preview-section">
                                <h6 class="mb-3">Landing Page Preview</h6>

                                <!-- Hero Section Preview -->
                                <div class="p-4 mb-3 rounded" style="background: linear-gradient(135deg, <?php echo $themeSettings['primary_color']; ?>, <?php echo $themeSettings['accent_color']; ?>); color: white;">
                                    <h3><?php echo htmlspecialchars($cmsSettings['hero_title']); ?></h3>
                                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($cmsSettings['hero_subtitle']); ?></p>
                                </div>

                                <!-- Features Preview -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 bg-white rounded border-start border-4" style="border-color: <?php echo $themeSettings['primary_color']; ?> !important;">
                                            <h6 style="color: <?php echo $themeSettings['primary_color']; ?>;">
                                                <i class="fas fa-shield-alt me-2"></i>
                                                <?php echo htmlspecialchars($cmsSettings['feature_1_title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($cmsSettings['feature_1_description']); ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 bg-white rounded border-start border-4" style="border-color: <?php echo $themeSettings['secondary_color']; ?> !important;">
                                            <h6 style="color: <?php echo $themeSettings['secondary_color']; ?>;">
                                                <i class="fas fa-users me-2"></i>
                                                <?php echo htmlspecialchars($cmsSettings['feature_2_title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($cmsSettings['feature_2_description']); ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 bg-white rounded border-start border-4" style="border-color: <?php echo $themeSettings['accent_color']; ?> !important;">
                                            <h6 style="color: <?php echo $themeSettings['accent_color']; ?>;">
                                                <i class="fas fa-chart-line me-2"></i>
                                                <?php echo htmlspecialchars($cmsSettings['feature_3_title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($cmsSettings['feature_3_description']); ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 bg-white rounded border-start border-4" style="border-color: <?php echo $themeSettings['primary_color']; ?> !important;">
                                            <h6 style="color: <?php echo $themeSettings['primary_color']; ?>;">
                                                <i class="fas fa-mobile-alt me-2"></i>
                                                <?php echo htmlspecialchars($cmsSettings['feature_4_title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($cmsSettings['feature_4_description']); ?></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Preview -->
                                <div class="p-3 text-center rounded" style="background: linear-gradient(90deg, <?php echo $themeSettings['accent_color']; ?>, <?php echo $themeSettings['secondary_color']; ?>, white, <?php echo $themeSettings['primary_color']; ?>); color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">
                                    <small><?php echo htmlspecialchars($cmsSettings['footer_text']); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="mb-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-tools text-primary me-2"></i>Quick Actions
                                </h6>

                                <div class="d-grid gap-2">
                                    <a href="landing.html" target="_blank" class="btn btn-success">
                                        <i class="fas fa-external-link-alt me-2"></i>View Live Landing Page
                                    </a>

                                    <a href="index.php?page=cms&action=landing" class="btn btn-outline-primary">
                                        <i class="fas fa-edit me-2"></i>Edit Content
                                    </a>

                                    <a href="index.php?page=cms&action=theme" class="btn btn-outline-warning">
                                        <i class="fas fa-palette me-2"></i>Customize Theme
                                    </a>

                                    <a href="index.php?page=cms&action=company" class="btn btn-outline-info">
                                        <i class="fas fa-building me-2"></i>Company Settings
                                    </a>
                                </div>
                            </div>

                            <!-- Company Info Preview -->
                            <div class="preview-section">
                                <h6 class="mb-3">
                                    <i class="fas fa-building text-info me-2"></i>Company Information
                                </h6>

                                <div class="text-center">
                                    <?php if (!empty($companyInfo['company_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($companyInfo['company_logo']); ?>"
                                             alt="Company Logo" style="max-width: 120px; max-height: 60px;" class="mb-3">
                                    <?php endif; ?>

                                    <h6 style="color: <?php echo $themeSettings['primary_color']; ?>;">
                                        <?php echo htmlspecialchars($companyInfo['company_name']); ?>
                                    </h6>

                                    <small class="text-muted d-block mb-2">
                                        <?php echo htmlspecialchars($companyInfo['company_description']); ?>
                                    </small>

                                    <div class="text-start">
                                        <small class="d-block">
                                            <i class="fas fa-envelope me-2" style="color: <?php echo $themeSettings['secondary_color']; ?>;"></i>
                                            <?php echo htmlspecialchars($companyInfo['company_email']); ?>
                                        </small>
                                        <small class="d-block">
                                            <i class="fas fa-phone me-2" style="color: <?php echo $themeSettings['secondary_color']; ?>;"></i>
                                            <?php echo htmlspecialchars($companyInfo['company_phone']); ?>
                                        </small>
                                        <small class="d-block">
                                            <i class="fas fa-map-marker-alt me-2" style="color: <?php echo $themeSettings['secondary_color']; ?>;"></i>
                                            <?php echo htmlspecialchars($companyInfo['company_address']); ?>
                                        </small>
                                        <?php if (!empty($companyInfo['company_website'])): ?>
                                            <small class="d-block">
                                                <i class="fas fa-globe me-2" style="color: <?php echo $themeSettings['secondary_color']; ?>;"></i>
                                                <a href="<?php echo htmlspecialchars($companyInfo['company_website']); ?>" target="_blank"
                                                   style="color: <?php echo $themeSettings['primary_color']; ?>;">
                                                    <?php echo htmlspecialchars($companyInfo['company_website']); ?>
                                                </a>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
    endswitch; ?>
</div>

<!-- CMS JavaScript -->
<script>
// Color picker synchronization
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('input[type="color"]');

    colorInputs.forEach(input => {
        const textInput = input.parentNode.querySelector('input[type="text"]');

        input.addEventListener('change', function() {
            textInput.value = this.value;
            updateThemePreview();
        });

        textInput.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                input.value = this.value;
                updateThemePreview();
            }
        });
    });
});

// Update theme preview in real-time
function updateThemePreview() {
    const preview = document.getElementById('themePreview');
    if (!preview) return;

    const primaryColor = document.getElementById('primary_color')?.value || '#006b3f';
    const secondaryColor = document.getElementById('secondary_color')?.value || '#ce1126';
    const accentColor = document.getElementById('accent_color')?.value || '#000000';
    const backgroundColor = document.getElementById('background_color')?.value || '#ffffff';
    const textColor = document.getElementById('text_color')?.value || '#1f2937';

    const previews = preview.querySelectorAll('div');
    if (previews.length >= 4) {
        previews[0].style.background = primaryColor;
        previews[1].style.background = secondaryColor;
        previews[2].style.background = accentColor;
        previews[3].style.background = backgroundColor;
        previews[3].style.color = textColor;
    }
}

// Reset to Kenyan flag colors
function resetToKenyanColors() {
    const colors = {
        'primary_color': '#006b3f',
        'secondary_color': '#ce1126',
        'accent_color': '#000000',
        'background_color': '#ffffff',
        'text_color': '#1f2937'
    };

    Object.keys(colors).forEach(id => {
        const colorInput = document.getElementById(id);
        const textInput = colorInput?.parentNode.querySelector('input[type="text"]');

        if (colorInput) {
            colorInput.value = colors[id];
            if (textInput) textInput.value = colors[id];
        }
    });

    updateThemePreview();
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
});

// Auto-save functionality (optional)
let autoSaveTimeout;
function autoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        // Auto-save logic can be implemented here
        console.log('Auto-save triggered');
    }, 5000);
}

// Add auto-save to form inputs
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('input', autoSave);
    });
});
</script>
