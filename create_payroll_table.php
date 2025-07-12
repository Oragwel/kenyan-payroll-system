<?php
/**
 * Create Payroll Records Table
 * 
 * Quick fix for missing payroll_records table error
 */

echo "🔧 CREATING PAYROLL_RECORDS TABLE...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database.\n");
    }
    
    echo "✅ Database connected\n\n";
    
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'payroll_records'");
    if ($stmt->rowCount() > 0) {
        echo "✅ payroll_records table already exists\n";
        echo "🎯 Try accessing the dashboard now\n";
        exit;
    }
    
    echo "📋 Creating payroll_records table...\n";
    
    // Create payroll_records table
    $sql = "
        CREATE TABLE payroll_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            payroll_period_id INT NOT NULL,
            basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
            allowances DECIMAL(15,2) DEFAULT 0,
            overtime_amount DECIMAL(15,2) DEFAULT 0,
            gross_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
            paye_tax DECIMAL(15,2) DEFAULT 0,
            nssf_deduction DECIMAL(15,2) DEFAULT 0,
            nhif_deduction DECIMAL(15,2) DEFAULT 0,
            housing_levy DECIMAL(15,2) DEFAULT 0,
            other_deductions DECIMAL(15,2) DEFAULT 0,
            total_deductions DECIMAL(15,2) DEFAULT 0,
            net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
            company_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_employee_period (employee_id, payroll_period_id),
            INDEX idx_company_period (company_id, payroll_period_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "✅ payroll_records table created successfully\n\n";
    
    // Also create payroll_periods table if it doesn't exist
    $stmt = $db->query("SHOW TABLES LIKE 'payroll_periods'");
    if ($stmt->rowCount() == 0) {
        echo "📋 Creating payroll_periods table...\n";
        
        $sql = "
            CREATE TABLE payroll_periods (
                id INT PRIMARY KEY AUTO_INCREMENT,
                company_id INT NOT NULL,
                period_name VARCHAR(100) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                pay_date DATE NOT NULL,
                status ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
                total_gross DECIMAL(15,2) DEFAULT 0,
                total_deductions DECIMAL(15,2) DEFAULT 0,
                total_net DECIMAL(15,2) DEFAULT 0,
                employee_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                UNIQUE KEY unique_company_period (company_id, period_name),
                INDEX idx_company_dates (company_id, start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($sql);
        echo "✅ payroll_periods table created successfully\n\n";
    }
    
    echo "🎉 SUCCESS!\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "✅ All required payroll tables created\n";
    echo "✅ Dashboard should now work without errors\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "1. Visit: http://localhost:8888/kenyan-payroll-system/index.php?page=dashboard\n";
    echo "2. Dashboard should load without fatal errors\n";
    echo "3. You can now add employees and create payroll periods\n\n";
    
} catch (Exception $e) {
    echo "❌ TABLE CREATION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "1. Open phpMyAdmin\n";
    echo "2. Select kenyan_payroll database\n";
    echo "3. Run the CREATE TABLE commands manually\n";
    echo "4. Or use the complete table creation script\n\n";
}

echo "🇰🇪 Payroll Table Creation Complete\n";
?>
