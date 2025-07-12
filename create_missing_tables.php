<?php
/**
 * Create Missing Tables
 * 
 * This script creates all the tables that the dashboard and other parts
 * of the system expect but might not be created during basic installation.
 */

echo "🔧 CREATING MISSING PAYROLL SYSTEM TABLES...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database. Please check your database configuration.\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // List of tables to create
    $tables = [
        'departments' => "CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            manager_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        'payroll_periods' => "CREATE TABLE IF NOT EXISTS payroll_periods (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            pay_date DATE NOT NULL,
            status ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )",
        
        'payroll_records' => "CREATE TABLE IF NOT EXISTS payroll_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payroll_period_id INT NOT NULL,
            employee_id INT NOT NULL,
            basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
            allowances DECIMAL(15,2) DEFAULT 0,
            overtime_pay DECIMAL(15,2) DEFAULT 0,
            gross_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
            paye_tax DECIMAL(15,2) DEFAULT 0,
            nssf_deduction DECIMAL(15,2) DEFAULT 0,
            nhif_deduction DECIMAL(15,2) DEFAULT 0,
            housing_levy DECIMAL(15,2) DEFAULT 0,
            other_deductions DECIMAL(15,2) DEFAULT 0,
            total_deductions DECIMAL(15,2) DEFAULT 0,
            net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )",
        
        'leave_types' => "CREATE TABLE IF NOT EXISTS leave_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            days_per_year INT NOT NULL DEFAULT 0,
            carry_forward BOOLEAN DEFAULT FALSE,
            max_carry_forward INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )",
        
        'leave_applications' => "CREATE TABLE IF NOT EXISTS leave_applications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_requested INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        'attendance' => "CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            time_in TIME NULL,
            time_out TIME NULL,
            break_time_minutes INT DEFAULT 0,
            total_hours DECIMAL(4,2) DEFAULT 0,
            overtime_hours DECIMAL(4,2) DEFAULT 0,
            status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_employee_date (employee_id, date)
        )"
    ];
    
    echo "📋 CREATING TABLES:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $created = 0;
    $existed = 0;
    
    foreach ($tables as $tableName => $sql) {
        try {
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
            if ($stmt->rowCount() > 0) {
                echo "✅ $tableName - Already exists\n";
                $existed++;
            } else {
                // Create table
                $db->exec($sql);
                echo "🆕 $tableName - Created successfully\n";
                $created++;
            }
        } catch (Exception $e) {
            echo "❌ $tableName - Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    echo "📊 SUMMARY:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "Tables created: $created\n";
    echo "Tables already existed: $existed\n";
    echo "Total tables processed: " . count($tables) . "\n\n";
    
    // Insert default data if tables were created
    if ($created > 0) {
        echo "📝 INSERTING DEFAULT DATA:\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Get company ID
        $stmt = $db->query("SELECT id FROM companies LIMIT 1");
        $company = $stmt->fetch();
        $companyId = $company['id'] ?? 1;
        
        // Insert default leave types if leave_types table was created
        $stmt = $db->query("SHOW TABLES LIKE 'leave_types'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_types WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
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
                echo "✅ Inserted default leave types\n";
            }
        }
        
        // Insert default departments if departments table was created
        $stmt = $db->query("SHOW TABLES LIKE 'departments'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM departments WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $departments = [
                    ['Human Resources', 'Manages employee relations and policies'],
                    ['Finance & Accounting', 'Handles financial operations and reporting'],
                    ['Information Technology', 'Manages IT infrastructure and systems'],
                    ['Sales & Marketing', 'Drives revenue and market presence'],
                    ['Operations', 'Oversees day-to-day business operations']
                ];
                
                $stmt = $db->prepare("INSERT INTO departments (company_id, name, description) VALUES (?, ?, ?)");
                foreach ($departments as $dept) {
                    $stmt->execute([$companyId, $dept[0], $dept[1]]);
                }
                echo "✅ Inserted default departments\n";
            }
        }
        
        echo "\n";
    }
    
    echo "🎉 SUCCESS: All required tables are now available!\n";
    echo "✅ Dashboard should now load without errors\n";
    echo "✅ Payroll system is ready for use\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Test dashboard access: index.php?page=dashboard\n";
    echo "2. Add employees through the employee management page\n";
    echo "3. Set up payroll periods for processing\n";
    echo "4. Configure leave policies and types\n\n";
    
} catch (Exception $e) {
    echo "❌ TABLE CREATION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Access your database directly (phpMyAdmin, etc.)\n";
    echo "2. Run the SQL commands manually for each missing table\n";
    echo "3. Check database user permissions\n";
    echo "4. Ensure foreign key constraints are satisfied\n\n";
}

echo "🇰🇪 Kenyan Payroll Management System - Table Creation Complete\n";
echo "=" . str_repeat("=", 60) . "\n";
?>
