<?php
/**
 * Installation Validation Functions
 * 
 * Comprehensive checks to ensure the system is properly installed
 * before allowing access to the main application.
 */

/**
 * Check if the system is properly installed
 * 
 * @return array ['installed' => bool, 'missing' => array, 'errors' => array]
 */
function checkSystemInstallation() {
    $result = [
        'installed' => false,
        'missing' => [],
        'errors' => []
    ];
    
    // Check 1: Installation marker file
    if (!file_exists('.installed')) {
        $result['missing'][] = 'Installation marker file (.installed)';
        return $result; // If no marker, definitely not installed
    }
    
    // Check 2: Database configuration file
    if (!file_exists('config/database.php')) {
        $result['missing'][] = 'Database configuration file (config/database.php)';
        return $result;
    }
    
    // Check 3: Database connection
    try {
        // Only include database.php if it exists and we haven't already included it
        if (!class_exists('Database')) {
            require_once 'config/database.php';
        }

        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            $result['errors'][] = 'Cannot connect to database';
            return $result;
        }
        
        // Check 4: Essential tables exist
        $requiredTables = [
            'companies',
            'users', 
            'employees',
            'departments',
            'job_positions',
            'leave_types',
            'activity_logs'
        ];
        
        $missingTables = [];
        foreach ($requiredTables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            $result['missing'] = array_merge($result['missing'], $missingTables);
            return $result;
        }
        
        // Check 5: Admin user exists
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1");
        $adminCount = $stmt->fetch();
        
        if ($adminCount['count'] == 0) {
            $result['missing'][] = 'Admin user account';
            return $result;
        }
        
        // Check 6: Company record exists
        $stmt = $db->query("SELECT COUNT(*) as count FROM companies");
        $companyCount = $stmt->fetch();
        
        if ($companyCount['count'] == 0) {
            $result['missing'][] = 'Company information';
            return $result;
        }
        
        // All checks passed
        $result['installed'] = true;
        
    } catch (Exception $e) {
        $result['errors'][] = 'Database validation error: ' . $e->getMessage();
        return $result;
    }
    
    return $result;
}

/**
 * Get installation status message
 * 
 * @param array $checkResult Result from checkSystemInstallation()
 * @return string HTML formatted status message
 */
function getInstallationStatusMessage($checkResult) {
    if ($checkResult['installed']) {
        return '<div class="alert alert-success">✅ System is properly installed and ready to use.</div>';
    }
    
    $message = '<div class="alert alert-danger">';
    $message .= '<h5>❌ System Installation Incomplete</h5>';
    
    if (!empty($checkResult['missing'])) {
        $message .= '<p><strong>Missing Components:</strong></p><ul>';
        foreach ($checkResult['missing'] as $missing) {
            $message .= "<li>$missing</li>";
        }
        $message .= '</ul>';
    }
    
    if (!empty($checkResult['errors'])) {
        $message .= '<p><strong>Errors:</strong></p><ul>';
        foreach ($checkResult['errors'] as $error) {
            $message .= "<li>$error</li>";
        }
        $message .= '</ul>';
    }
    
    $message .= '<p><strong>Action Required:</strong> Please complete the installation process.</p>';
    $message .= '</div>';
    
    return $message;
}

/**
 * Force redirect to installer if system is not properly installed
 */
function enforceInstallationCheck() {
    // Prevent redirect loops by checking current script
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $allowedScripts = [
        'install.php',
        'installation_status.php',
        'clean_install.php',
        'migrate_activity_logs.php',
        'migrate_banking_fields.php',
        'clear_session.php',
        'break_redirect_loop.php'
    ];

    // Don't redirect if we're already on an installation-related page
    if (in_array($currentScript, $allowedScripts)) {
        return;
    }

    // First, check installation status
    $installCheck = checkSystemInstallation();

    if ($installCheck['installed']) {
        // Installation is complete, clear any redirect counter and continue
        unset($_SESSION['installation_redirect_count']);
        return;
    }

    // Installation is incomplete, check for redirect loops
    if (isset($_SESSION['installation_redirect_count'])) {
        $_SESSION['installation_redirect_count']++;
        if ($_SESSION['installation_redirect_count'] > 3) {
            // Too many redirects, show error instead
            die('
                <h2>Installation Error</h2>
                <p>The system appears to be in a redirect loop. Please manually access the installer:</p>
                <p><a href="install.php">Click here to access the installer</a></p>
                <p><a href="installation_status.php">Check installation status</a></p>
                <p><a href="clear_session.php">Debug installation status</a></p>
            ');
        }
    } else {
        $_SESSION['installation_redirect_count'] = 1;
    }

    // Log the incomplete installation attempt
    error_log("Incomplete installation detected: " . json_encode($installCheck));

    // Redirect to installer
    header('Location: install.php?incomplete=1');
    exit;
}

/**
 * Quick installation check (lightweight version)
 * 
 * @return bool True if basic installation appears complete
 */
function isBasicallyInstalled() {
    // Quick checks without database connection
    if (!file_exists('.installed')) {
        return false;
    }
    
    if (!file_exists('config/database.php')) {
        return false;
    }
    
    return true;
}

/**
 * Get installation progress percentage
 * 
 * @return int Percentage of installation completion (0-100)
 */
function getInstallationProgress() {
    $progress = 0;
    $totalSteps = 7;
    
    // Step 1: Installation marker
    if (file_exists('.installed')) $progress++;
    
    // Step 2: Database config
    if (file_exists('config/database.php')) $progress++;
    
    // Step 3-7: Database components (check if we can connect)
    try {
        if (file_exists('config/database.php')) {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                $progress++; // Database connection works
                
                // Check for tables
                $tables = ['companies', 'users', 'employees', 'departments'];
                foreach ($tables as $table) {
                    $stmt = $db->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        $progress++;
                        break; // At least some tables exist
                    }
                }
                
                // Check for admin user
                $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                if ($stmt && $stmt->fetch()['count'] > 0) {
                    $progress++;
                }
                
                // Check for company
                $stmt = $db->query("SELECT COUNT(*) as count FROM companies");
                if ($stmt && $stmt->fetch()['count'] > 0) {
                    $progress++;
                }
            }
        }
    } catch (Exception $e) {
        // Database issues, progress stays as is
    }
    
    return min(100, ($progress / $totalSteps) * 100);
}
?>
