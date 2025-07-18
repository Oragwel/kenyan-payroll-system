<?php
/**
 * Robust Database Installer - Works with Multiple Database Types
 */

require_once __DIR__ . '/../config/DatabaseManager.php';
require_once __DIR__ . '/../config/SchemaManager.php';

class DatabaseInstaller {
    private $dbManager;
    private $schemaManager;
    private $config;
    
    public function __construct($config = null) {
        $this->config = $config;
        if ($config) {
            $this->dbManager = new DatabaseManager($config);
            $this->schemaManager = new SchemaManager($this->dbManager);
        }
    }
    
    /**
     * Test database connection with given configuration
     */
    public function testConnection($config) {
        try {
            $testManager = new DatabaseManager($config);
            return $testManager->testConnection();
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Connection test failed'
            ];
        }
    }
    
    /**
     * Install database schema and initial data
     */
    public function install($config, $adminUser = null) {
        $results = [
            'success' => false,
            'steps' => [],
            'errors' => []
        ];
        
        try {
            // Step 1: Test connection
            $results['steps']['connection'] = $this->testConnection($config);
            if (!$results['steps']['connection']['success']) {
                throw new Exception('Database connection failed');
            }
            
            // Step 2: Initialize managers
            $this->dbManager = new DatabaseManager($config);
            $this->schemaManager = new SchemaManager($this->dbManager);
            
            // Step 3: Create database if needed (for MySQL/PostgreSQL)
            if (in_array($config['type'], [DatabaseManager::MYSQL, DatabaseManager::POSTGRESQL])) {
                $results['steps']['database_creation'] = $this->createDatabase($config);
            } else {
                $results['steps']['database_creation'] = ['success' => true, 'message' => 'Database file will be created automatically'];
            }
            
            // Step 4: Create tables
            $results['steps']['tables'] = $this->schemaManager->createTables();
            
            // Step 5: Insert default data
            $results['steps']['default_data'] = $this->insertDefaultData($adminUser);
            
            // Step 6: Save configuration
            $results['steps']['config_save'] = $this->saveConfiguration($config);
            
            // Step 7: Create installation marker
            $results['steps']['installation_marker'] = $this->createInstallationMarker();
            
            $results['success'] = true;
            $results['message'] = 'Installation completed successfully';
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['message'] = 'Installation failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Create database (for MySQL/PostgreSQL)
     */
    private function createDatabase($config) {
        try {
            if ($config['type'] === DatabaseManager::MYSQL) {
                return $this->createMySQLDatabase($config);
            } elseif ($config['type'] === DatabaseManager::POSTGRESQL) {
                return $this->createPostgreSQLDatabase($config);
            }
            
            return ['success' => true, 'message' => 'Database creation not required'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create MySQL database
     */
    private function createMySQLDatabase($config) {
        $tempConfig = $config;
        unset($tempConfig['database']); // Connect without database first
        
        $tempManager = new DatabaseManager($tempConfig);
        $conn = $tempManager->getConnection();
        
        $dbName = $config['database'];
        $stmt = $conn->prepare("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        
        return ['success' => true, 'message' => "Database '{$dbName}' created successfully"];
    }
    
    /**
     * Create PostgreSQL database
     */
    private function createPostgreSQLDatabase($config) {
        $tempConfig = $config;
        $tempConfig['database'] = 'postgres'; // Connect to default database first
        
        $tempManager = new DatabaseManager($tempConfig);
        $conn = $tempManager->getConnection();
        
        $dbName = $config['database'];
        
        // Check if database exists
        $stmt = $conn->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
        $stmt->execute([$dbName]);
        
        if (!$stmt->fetch()) {
            $conn->exec("CREATE DATABASE \"{$dbName}\"");
        }
        
        return ['success' => true, 'message' => "Database '{$dbName}' created successfully"];
    }
    
    /**
     * Insert default data
     */
    private function insertDefaultData($adminUser = null) {
        try {
            $conn = $this->dbManager->getConnection();
            $results = [];
            
            // Insert default company
            $results['company'] = $this->insertDefaultCompany($conn);

            // Insert default company settings
            $results['company_settings'] = $this->insertDefaultCompanySettings($conn);

            // Insert default admin user
            $results['admin_user'] = $this->insertDefaultAdminUser($conn, $adminUser);
            
            // Insert default department
            $results['department'] = $this->insertDefaultDepartment($conn);
            
            // Insert default job position
            $results['job_position'] = $this->insertDefaultJobPosition($conn);
            
            // Insert default leave types
            $results['leave_types'] = $this->insertDefaultLeaveTypes($conn);
            
            return ['success' => true, 'details' => $results];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Insert default company
     */
    private function insertDefaultCompany($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("
                INSERT INTO companies (name, address, phone, email, kra_pin, business_registration) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Garissa County Government',
                'P.O. Box 1-70100, Garissa, Kenya',
                '+254-700-000-000',
                'info@garissa.go.ke',
                'P051234567A',
                'REG/2024/001'
            ]);
            return ['created' => true, 'message' => 'Default company created'];
        }
        
        return ['created' => false, 'message' => 'Company already exists'];
    }
    
    /**
     * Insert default admin user
     */
    private function insertDefaultAdminUser($conn, $adminUser = null) {
        $username = $adminUser['username'] ?? 'admin';
        $email = $adminUser['email'] ?? 'admin@garissa.go.ke';
        $password = $adminUser['password'] ?? 'admin123';
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, role, company_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'admin',
                1
            ]);
            return [
                'created' => true, 
                'message' => 'Default admin user created',
                'credentials' => ['username' => $username, 'password' => $password]
            ];
        }
        
        return ['created' => false, 'message' => 'Admin user already exists'];
    }
    
    /**
     * Insert default department
     */
    private function insertDefaultDepartment($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("
                INSERT INTO departments (company_id, name, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                1,
                'Administration',
                'General administration and management'
            ]);
            return ['created' => true, 'message' => 'Default department created'];
        }
        
        return ['created' => false, 'message' => 'Department already exists'];
    }
    
    /**
     * Insert default job position
     */
    private function insertDefaultJobPosition($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_positions");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("
                INSERT INTO job_positions (company_id, title, description, department_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                1,
                'Administrator',
                'System administrator and general management',
                1
            ]);
            return ['created' => true, 'message' => 'Default job position created'];
        }
        
        return ['created' => false, 'message' => 'Job position already exists'];
    }
    
    /**
     * Insert default leave types
     */
    private function insertDefaultLeaveTypes($conn) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_types");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $leaveTypes = [
                ['Annual Leave', 'Annual vacation leave', 21, 1, 1, 5],
                ['Sick Leave', 'Medical leave', 14, 1, 0, 0],
                ['Maternity Leave', 'Maternity leave for female employees', 90, 1, 0, 0],
                ['Paternity Leave', 'Paternity leave for male employees', 14, 1, 0, 0],
                ['Compassionate Leave', 'Leave for family emergencies', 7, 1, 0, 0]
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO leave_types (company_id, name, description, days_allowed, is_paid, carry_forward, max_carry_forward) 
                VALUES (1, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($leaveTypes as $leaveType) {
                $stmt->execute($leaveType);
            }
            
            return ['created' => true, 'message' => count($leaveTypes) . ' default leave types created'];
        }
        
        return ['created' => false, 'message' => 'Leave types already exist'];
    }
    
    /**
     * Save configuration
     */
    private function saveConfiguration($config) {
        try {
            if ($this->dbManager->saveConfig($config)) {
                return ['success' => true, 'message' => 'Configuration saved successfully'];
            } else {
                return ['success' => false, 'error' => 'Failed to save configuration'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create installation marker
     */
    private function createInstallationMarker() {
        try {
            $markerFile = __DIR__ . '/../config/installed.txt';
            $markerDir = dirname($markerFile);
            
            if (!is_dir($markerDir)) {
                mkdir($markerDir, 0755, true);
            }
            
            $content = "Installation completed on: " . date('Y-m-d H:i:s') . "\n";
            $content .= "Database type: " . $this->dbManager->getDatabaseType() . "\n";
            
            if (file_put_contents($markerFile, $content)) {
                return ['success' => true, 'message' => 'Installation marker created'];
            } else {
                return ['success' => false, 'error' => 'Failed to create installation marker'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check if system is already installed
     */
    public static function isInstalled() {
        return file_exists(__DIR__ . '/../config/installed.txt');
    }
    
    /**
     * Get available database drivers
     */
    public static function getAvailableDrivers() {
        $drivers = [];
        $pdoDrivers = PDO::getAvailableDrivers();
        
        if (in_array('mysql', $pdoDrivers)) {
            $drivers[DatabaseManager::MYSQL] = 'MySQL';
        }
        if (in_array('pgsql', $pdoDrivers)) {
            $drivers[DatabaseManager::POSTGRESQL] = 'PostgreSQL';
        }
        if (in_array('sqlite', $pdoDrivers)) {
            $drivers[DatabaseManager::SQLITE] = 'SQLite';
        }
        if (in_array('sqlsrv', $pdoDrivers)) {
            $drivers[DatabaseManager::SQLSERVER] = 'SQL Server';
        }
        
        return $drivers;
    }

    /**
     * Insert default company settings
     */
    private function insertDefaultCompanySettings($conn) {
        // Check if company_settings table exists
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM company_settings WHERE company_id = 1");
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result['count'] == 0) {
                // Default settings for Kenyan payroll system
                $defaultSettings = [
                    // Payroll settings
                    ['payroll', 'pay_frequency', 'monthly', 'How often employees are paid'],
                    ['payroll', 'pay_day', '30', 'Day of month when salaries are paid'],
                    ['payroll', 'overtime_rate', '1.5', 'Overtime multiplier rate'],
                    ['payroll', 'working_hours_per_day', '8', 'Standard working hours per day'],
                    ['payroll', 'working_days_per_week', '5', 'Standard working days per week'],

                    // Tax settings
                    ['tax', 'paye_enabled', '1', 'Enable PAYE tax calculations'],
                    ['tax', 'nssf_enabled', '1', 'Enable NSSF deductions'],
                    ['tax', 'nhif_enabled', '1', 'Enable SHIF deductions'],
                    ['tax', 'housing_levy_enabled', '1', 'Enable Housing Levy deductions'],
                    ['tax', 'personal_relief', '2400', 'Monthly personal relief amount'],

                    // General settings
                    ['general', 'currency', 'KES', 'Default currency'],
                    ['general', 'currency_symbol', 'KSh', 'Currency symbol'],
                    ['general', 'timezone', 'Africa/Nairobi', 'System timezone'],
                    ['general', 'date_format', 'Y-m-d', 'Date display format'],
                    ['general', 'decimal_places', '2', 'Decimal places for currency'],

                    // Leave settings
                    ['leave', 'annual_leave_days', '21', 'Annual leave entitlement'],
                    ['leave', 'sick_leave_days', '14', 'Sick leave entitlement'],
                    ['leave', 'maternity_leave_days', '90', 'Maternity leave entitlement'],
                    ['leave', 'paternity_leave_days', '14', 'Paternity leave entitlement']
                ];

                $stmt = $conn->prepare("
                    INSERT INTO company_settings (company_id, setting_category, setting_key, setting_value, description)
                    VALUES (1, ?, ?, ?, ?)
                ");

                foreach ($defaultSettings as $setting) {
                    $stmt->execute($setting);
                }

                return ['created' => true, 'message' => count($defaultSettings) . ' default company settings created'];
            }

            return ['created' => false, 'message' => 'Company settings already exist'];

        } catch (Exception $e) {
            // Table might not exist yet, that's okay
            return ['created' => false, 'message' => 'Company settings table not available: ' . $e->getMessage()];
        }
    }
}
?>
