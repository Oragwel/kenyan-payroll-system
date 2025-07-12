<?php
/**
 * 🇰🇪 Kenyan Payroll Management System - Installation Wizard
 *
 * Complete setup guide for users after cloning the repository.
 * This installer will guide you through:
 * - System requirements check
 * - Database configuration and setup
 * - Admin account creation
 * - Company information setup
 * - System configuration
 * - Final installation completion
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include installation check functions
require_once 'includes/installation_check.php';

// Check installation status
$installationIncomplete = isset($_GET['incomplete']);
$forceReinstall = isset($_GET['force']);

// Only redirect to index.php if installation is truly complete AND not forced
if (!$installationIncomplete && !$forceReinstall) {
    $installCheck = checkSystemInstallation();
    if ($installCheck['installed']) {
        // Show completion message instead of redirecting
        $showCompletionMessage = true;
    }
}

// Installation steps
$steps = [
    1 => '🏠 Welcome & Requirements Check',
    2 => '🗄️ Database Configuration',
    3 => '⚙️ Database Setup & Tables',
    4 => '👑 Admin Account Creation',
    5 => '🏢 Company Information',
    6 => '🎯 System Configuration',
    7 => '🎉 Installation Complete'
];

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$currentStep = max(1, min(7, $currentStep));
$errors = [];
$success = [];
$warnings = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentStep) {
        case 2:
            $result = handleDatabaseConfig($_POST);
            if ($result['success']) {
                header('Location: install.php?step=3');
                exit;
            } else {
                $errors = $result['errors'];
            }
            break;
        case 3:
            if (isset($_POST['setup_database'])) {
                $result = setupDatabase();
                if ($result['success']) {
                    header('Location: install.php?step=4');
                    exit;
                } else {
                    $errors[] = $result['error'];
                }
            }
            break;
        case 4:
            $result = createAdminAccount($_POST);
            if ($result['success']) {
                $_SESSION['admin_config'] = $_POST;
                header('Location: install.php?step=5');
                exit;
            } else {
                $errors = $result['errors'];
            }
            break;
        case 5:
            $result = saveCompanyConfig($_POST);
            if ($result['success']) {
                $_SESSION['company_config'] = $_POST;
                header('Location: install.php?step=6');
                exit;
            } else {
                $errors = $result['errors'];
            }
            break;
        case 6:
            if (isset($_POST['complete_installation'])) {
                $result = completeInstallation();
                if ($result['success']) {
                    header('Location: install.php?step=7');
                    exit;
                } else {
                    $errors[] = $result['error'];
                }
            }
            break;
    }
}

/**
 * Check system requirements with detailed information
 */
function checkRequirements() {
    $requirements = [];

    // PHP Version Check
    $phpVersion = PHP_VERSION;
    $requirements['PHP Version'] = [
        'required' => '7.4.0+',
        'current' => $phpVersion,
        'status' => version_compare($phpVersion, '7.4.0', '>='),
        'critical' => true,
        'description' => 'PHP 7.4 or higher is required for security and performance'
    ];

    // Required Extensions
    $extensions = [
        'pdo' => 'PDO Extension for database connectivity',
        'pdo_mysql' => 'PDO MySQL Extension for MySQL database support',
        'json' => 'JSON Extension for data processing',
        'session' => 'Session Extension for user authentication',
        'mbstring' => 'Multibyte String Extension for text processing',
        'openssl' => 'OpenSSL Extension for security features'
    ];

    foreach ($extensions as $ext => $description) {
        $loaded = extension_loaded($ext);
        $requirements[$ext] = [
            'required' => 'Enabled',
            'current' => $loaded ? 'Enabled' : 'Disabled',
            'status' => $loaded,
            'critical' => in_array($ext, ['pdo', 'pdo_mysql', 'json']),
            'description' => $description
        ];
    }

    // Directory Permissions
    $directories = [
        'config/' => 'Configuration files storage',
        'uploads/' => 'File uploads and user content',
        'backups/' => 'System backups (will be created)'
    ];

    foreach ($directories as $dir => $description) {
        $writable = is_dir($dir) ? is_writable($dir) : mkdir($dir, 0755, true);
        $requirements["Directory: {$dir}"] = [
            'required' => 'Writable',
            'current' => $writable ? 'Writable' : 'Not Writable',
            'status' => $writable,
            'critical' => $dir !== 'backups/',
            'description' => $description
        ];
    }

    // Memory Limit Check
    $memoryLimit = ini_get('memory_limit');
    $memoryBytes = return_bytes($memoryLimit);
    $requirements['Memory Limit'] = [
        'required' => '128M+',
        'current' => $memoryLimit,
        'status' => $memoryBytes >= return_bytes('128M'),
        'critical' => false,
        'description' => 'Sufficient memory for payroll processing'
    ];

    return $requirements;
}

/**
 * Convert memory limit to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

/**
 * Handle database configuration
 */
function handleDatabaseConfig($data) {
    $host = trim($data['host'] ?? '');
    $port = trim($data['port'] ?? '3306');
    $database = trim($data['database'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    $errors = [];

    if (empty($host)) $errors[] = 'Database host is required';
    if (empty($database)) $errors[] = 'Database name is required';
    if (empty($username)) $errors[] = 'Database username is required';
    if (empty($port) || !is_numeric($port)) $errors[] = 'Valid database port is required';

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Test database connection - first try without database name
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Store database config in session
        $_SESSION['db_config'] = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password
        ];

        return ['success' => true];

    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Database connection failed: ' . $e->getMessage()]];
    }
}

/**
 * Setup database tables
 */
function setupDatabase() {
    if (!isset($_SESSION['db_config'])) {
        return ['success' => false, 'error' => 'Database configuration not found'];
    }

    $config = $_SESSION['db_config'];

    try {
        // First, connect without specifying database to create it if needed
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create database if it doesn't exist
        $dbName = $config['database'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Now connect to the specific database
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create database configuration file
        $configContent = "<?php
/**
 * Database Configuration - Generated by Installer
 */

class Database {
    private \$host = '{$config['host']}';
    private \$db_name = '{$config['database']}';
    private \$username = '{$config['username']}';
    private \$password = '{$config['password']}';
    private \$port = '{$config['port']}';
    private \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO(
                \"mysql:host=\" . \$this->host . \";port=\" . \$this->port . \";dbname=\" . \$this->db_name . \";charset=utf8mb4\",
                \$this->username,
                \$this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }
        return \$this->conn;
    }
}
?>";
        
        file_put_contents('config/database.php', $configContent);
        
        // Create essential tables
        $tables = [
            "CREATE TABLE IF NOT EXISTS companies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                address TEXT,
                phone VARCHAR(20),
                email VARCHAR(100),
                website VARCHAR(255),
                kra_pin VARCHAR(11),
                nssf_number VARCHAR(20),
                nhif_number VARCHAR(20),
                logo VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                role ENUM('admin', 'hr', 'employee') NOT NULL,
                employee_id INT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS departments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                manager_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS job_positions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                min_salary DECIMAL(12,2),
                max_salary DECIMAL(12,2),
                department_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
            )",
            
            "CREATE TABLE IF NOT EXISTS employees (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                employee_number VARCHAR(50) UNIQUE NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE,
                phone VARCHAR(20),
                id_number VARCHAR(20) UNIQUE,
                kra_pin VARCHAR(20),
                nssf_number VARCHAR(20),
                nhif_number VARCHAR(20),
                hire_date DATE NOT NULL,
                basic_salary DECIMAL(12,2) NOT NULL,
                employment_status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
                contract_type ENUM('permanent', 'contract', 'casual', 'intern') DEFAULT 'permanent',
                department_id INT,
                position_id INT,
                bank_code VARCHAR(10),
                bank_name VARCHAR(100),
                bank_branch VARCHAR(100),
                account_number VARCHAR(30),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
                FOREIGN KEY (position_id) REFERENCES job_positions(id) ON DELETE SET NULL
            )",

            "CREATE TABLE IF NOT EXISTS leave_types (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                days_per_year INT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )"
        ];
        
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database setup failed: ' . $e->getMessage()];
    }
}

/**
 * Create admin account
 */
function createAdminAccount($data) {
    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    $companyName = trim($data['company_name'] ?? '');
    
    $errors = [];
    
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
   // if (empty($companyName)) $errors[] = 'Company name is required';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        require_once 'config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'errors' => ["Username '$username' already exists. Please choose a different username."]];
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'errors' => ["Email '$email' is already registered. Please use a different email address."]];
        }

        // Check if company already exists or create new one
        $companyId = null;
        if (!empty($companyName)) {
            $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
            $stmt->execute([$companyName]);
            $existingCompany = $stmt->fetch();

            if ($existingCompany) {
                $companyId = $existingCompany['id'];
            } else {
                // Create new company
                $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
                $stmt->execute([$companyName]);
                $companyId = $pdo->lastInsertId();
            }
        } else {
            // Use default company if no name provided
            $stmt = $pdo->prepare("SELECT id FROM companies LIMIT 1");
            $stmt->execute();
            $existingCompany = $stmt->fetch();

            if ($existingCompany) {
                $companyId = $existingCompany['id'];
            } else {
                // Create default company
                $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
                $stmt->execute(['Default Company']);
                $companyId = $pdo->lastInsertId();
            }
        }

        // Create admin user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (company_id, username, email, password, first_name, last_name, role, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 'admin', 1)
        ");
        $stmt->execute([$companyId, $username, $email, $hashedPassword, $firstName, $lastName]);

        $_SESSION['admin_created'] = true;
        $_SESSION['company_id'] = $companyId;

        return ['success' => true, 'message' => 'Admin account created successfully!'];

    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Failed to create admin account: ' . $e->getMessage()]];
    }
}

/**
 * Finalize installation
 */
function finalizeInstallation($data) {
    try {
        // Create uploads directory if it doesn't exist
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        // Create backups directory
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        // Create installation complete marker
        file_put_contents('.installed', date('Y-m-d H:i:s'));
        
        // Clear installation session data
        unset($_SESSION['db_config']);
        unset($_SESSION['admin_created']);
        unset($_SESSION['company_id']);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Installation finalization failed: ' . $e->getMessage()]];
    }
}

/**
 * Save company configuration
 */
function saveCompanyConfig($data) {
    $errors = [];

    // Validate required fields
    if (empty($data['company_name'])) $errors[] = 'Company name is required';
    if (empty($data['company_email'])) $errors[] = 'Company email is required';
    if (empty($data['company_address'])) $errors[] = 'Company address is required';
    if (empty($data['kra_pin'])) $errors[] = 'KRA PIN is required';

    // Validate KRA PIN format
    if (!empty($data['kra_pin']) && !preg_match('/^P\d{9}[A-Z]$/', $data['kra_pin'])) {
        $errors[] = 'Invalid KRA PIN format (should be like P051234567A)';
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    return ['success' => true];
}

/**
 * Complete installation by creating all data
 */
function completeInstallation() {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        $adminConfig = $_SESSION['admin_config'];
        $companyConfig = $_SESSION['company_config'];

        // Check if company already exists or create new one
        $companyId = null;
        $stmt = $db->prepare("SELECT id FROM companies WHERE name = ?");
        $stmt->execute([$companyConfig['company_name']]);
        $existingCompany = $stmt->fetch();

        if ($existingCompany) {
            $companyId = $existingCompany['id'];

            // Update existing company with new information
            $stmt = $db->prepare("UPDATE companies SET address = ?, phone = ?, email = ?, website = ?, kra_pin = ?, nssf_number = ?, nhif_number = ? WHERE id = ?");
            $stmt->execute([
                $companyConfig['company_address'],
                $companyConfig['company_phone'] ?? '',
                $companyConfig['company_email'],
                $companyConfig['company_website'] ?? '',
                $companyConfig['kra_pin'],
                $companyConfig['nssf_number'] ?? '',
                $companyConfig['nhif_number'] ?? '',
                $companyId
            ]);
        } else {
            // Create new company
            $stmt = $db->prepare("INSERT INTO companies (name, address, phone, email, website, kra_pin, nssf_number, nhif_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $companyConfig['company_name'],
                $companyConfig['company_address'],
                $companyConfig['company_phone'] ?? '',
                $companyConfig['company_email'],
                $companyConfig['company_website'] ?? '',
                $companyConfig['kra_pin'],
                $companyConfig['nssf_number'] ?? '',
                $companyConfig['nhif_number'] ?? ''
            ]);
            $companyId = $db->lastInsertId();
        }

        // Check if admin user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$adminConfig['username']]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Update existing admin user with company association and latest info
            $stmt = $db->prepare("UPDATE users SET company_id = ?, email = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $companyId,
                $adminConfig['email'],
                $adminConfig['first_name'],
                $adminConfig['last_name'],
                $adminConfig['phone'] ?? '',
                $existingUser['id']
            ]);
        } else {
            // Create new admin user (this should rarely happen if Step 4 worked correctly)
            $hashedPassword = password_hash($adminConfig['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (company_id, username, email, password, first_name, last_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 'admin', 1)");
            $stmt->execute([
                $companyId,
                $adminConfig['username'],
                $adminConfig['email'],
                $hashedPassword,
                $adminConfig['first_name'],
                $adminConfig['last_name'],
                $adminConfig['phone'] ?? ''
            ]);
        }

        // Create default leave types
        $leaveTypes = [
            ['Annual Leave', 21],
            ['Sick Leave', 7],
            ['Maternity Leave', 90],
            ['Paternity Leave', 14],
            ['Compassionate Leave', 3]
        ];

        $stmt = $db->prepare("INSERT INTO leave_types (company_id, name, days_per_year) VALUES (?, ?, ?)");
        foreach ($leaveTypes as $leave) {
            $stmt->execute([$companyId, $leave[0], $leave[1]]);
        }

        // Create installation complete flag
        file_put_contents('.installed', date('Y-m-d H:i:s'));

        // Clear session data
        unset($_SESSION['db_config'], $_SESSION['admin_config'], $_SESSION['company_config']);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Check if already installed
if (file_exists('.installed') && $currentStep != 7) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Payroll System - Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            font-family: 'Inter', sans-serif;
        }
        
        .installer-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .installer-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .installer-header {
            background: linear-gradient(90deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
            padding: 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .installer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
        }
        
        .installer-header > * {
            position: relative;
            z-index: 2;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
            padding: 0 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number {
            background: var(--kenya-green);
            color: white;
        }
        
        .step.completed .step-number {
            background: var(--kenya-red);
            color: white;
        }
        
        .step-line {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e9ecef;
            z-index: -1;
        }
        
        .step:last-child .step-line {
            display: none;
        }
        
        .installer-content {
            padding: 2rem;
        }
        
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .requirement-pass {
            background: var(--kenya-light-green);
            color: var(--kenya-dark-green);
        }
        
        .requirement-fail {
            background: #ffeaa7;
            color: #d63031;
        }
        
        .btn-installer {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-installer:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,107,63,0.3);
            color: white;
        }
        
        .kenyan-flag {
            height: 4px;
            background: linear-gradient(90deg, var(--kenya-black) 25%, var(--kenya-red) 25% 50%, var(--kenya-white) 50% 75%, var(--kenya-green) 75%);
            margin: 1rem 0;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: var(--kenya-light-green);
            color: var(--kenya-dark-green);
            border: 1px solid var(--kenya-green);
        }

        .alert-danger {
            background: #ffeaa7;
            color: #d63031;
            border: 1px solid #d63031;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--kenya-dark-green);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--kenya-green);
            box-shadow: 0 0 0 3px rgba(0,107,63,0.1);
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--kenya-green), var(--kenya-red));
            transition: width 0.3s ease;
        }

        .installation-summary {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin: 2rem 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .feature-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--kenya-green);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--kenya-green);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <!-- Kenyan Flag Header -->
        <div class="kenyan-flag"></div>

        <!-- Header -->
        <div class="installer-header">
            <h1>🇰🇪 Kenyan Payroll Management System</h1>
            <p>Installation Wizard - Setting up your enterprise payroll solution</p>

            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($currentStep / 7) * 100; ?>%"></div>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <?php foreach ($steps as $stepNum => $stepName): ?>
                <div class="step <?php echo $stepNum == $currentStep ? 'active' : ($stepNum < $currentStep ? 'completed' : ''); ?>">
                    <div class="step-number"><?php echo $stepNum; ?></div>
                    <div class="step-title"><?php echo $stepName; ?></div>
                    <?php if ($stepNum < count($steps)): ?>
                        <div class="step-line"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Content -->
        <div class="installer-content">
            <?php
            // Display messages
            if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5>❌ Errors Found:</h5>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;

            if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h5>✅ Success:</h5>
                    <ul>
                        <?php foreach ($success as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;

            // Show installation status if accessed due to incomplete installation
            if ($installationIncomplete):
                $installCheck = checkSystemInstallation();
                $progress = getInstallationProgress();
                ?>
                <div class="alert alert-warning">
                    <h5>⚠️ Incomplete Installation Detected</h5>
                    <p>The system detected that your installation is not complete. Please finish the setup process.</p>

                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <small>Installation Progress: <?php echo round($progress); ?>%</small>

                    <?php echo getInstallationStatusMessage($installCheck); ?>
                </div>
            <?php endif;

            if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <h5>⚠️ Warnings:</h5>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;

            // Show completion message if system is already installed
            if (isset($showCompletionMessage) && $showCompletionMessage): ?>
                <div class="installation-summary">
                    <h3>🎉 Installation Already Complete!</h3>
                    <p>Your Kenyan Payroll Management System is already installed and ready to use.</p>

                    <div class="feature-grid">
                        <div class="feature-card">
                            <h5>✅ System Status</h5>
                            <p>All components are properly installed and configured.</p>
                        </div>
                        <div class="feature-card">
                            <h5>🏦 Database Ready</h5>
                            <p>Database connection and tables are working correctly.</p>
                        </div>
                        <div class="feature-card">
                            <h5>👑 Admin Account</h5>
                            <p>Administrator account is set up and active.</p>
                        </div>
                        <div class="feature-card">
                            <h5>🏢 Company Info</h5>
                            <p>Company information is configured and ready.</p>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="index.php?page=dashboard" class="btn-installer btn-lg me-3">
                            <i class="fas fa-home me-2"></i>Go to Dashboard
                        </a>
                        <a href="landing.html" class="btn btn-outline-light btn-lg me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Page
                        </a>
                        <a href="install.php?force=1" class="btn btn-outline-warning btn-lg">
                            <i class="fas fa-redo me-2"></i>Force Reinstall
                        </a>
                    </div>
                </div>
            <?php else:

            // Display current step content
            switch ($currentStep):
                case 1: include 'install_steps/step1_welcome.php'; break;
                case 2: include 'install_steps/step2_database.php'; break;
                case 3: include 'install_steps/step3_setup.php'; break;
                case 4: include 'install_steps/step4_admin.php'; break;
                case 5: include 'install_steps/step5_company.php'; break;
                case 6: include 'install_steps/step6_config.php'; break;
                case 7: include 'install_steps/step7_complete.php'; break;
            endswitch;

            endif; // End of completion message check
            ?>
        </div>

        <!-- Footer -->
        <div class="kenyan-flag"></div>
    </div>
</body>
</html>
