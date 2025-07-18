<?php
/**
 * Database Initialization Script for SQLite
 * Run this script to set up the database and create initial data
 */

require_once 'config/database.php';

echo "🇰🇪 Kenyan Payroll System - Database Initialization\n";
echo "==================================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    echo "✅ Database connection successful\n";
    
    // Create companies table
    echo "📋 Creating companies table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS companies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(100),
            website VARCHAR(100),
            kra_pin VARCHAR(20),
            business_registration VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create users table
    echo "👥 Creating users table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'employee',
            company_id INTEGER,
            employee_id INTEGER,
            is_active BOOLEAN DEFAULT 1,
            last_login DATETIME,
            failed_login_attempts INTEGER DEFAULT 0,
            locked_until DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id)
        )
    ");
    
    // Create departments table
    echo "🏢 Creating departments table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            manager_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id)
        )
    ");
    
    // Create job_positions table
    echo "💼 Creating job_positions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS job_positions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT,
            department_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (department_id) REFERENCES departments(id)
        )
    ");
    
    // Create employees table
    echo "👤 Creating employees table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            employee_number VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            id_number VARCHAR(20),
            date_of_birth DATE,
            gender VARCHAR(10),
            address TEXT,
            department_id INTEGER,
            position_id INTEGER,
            hire_date DATE,
            contract_type VARCHAR(20) DEFAULT 'permanent',
            employment_status VARCHAR(20) DEFAULT 'active',
            basic_salary DECIMAL(12,2) DEFAULT 0,
            bank_name VARCHAR(100),
            bank_account VARCHAR(50),
            bank_code VARCHAR(20),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (department_id) REFERENCES departments(id),
            FOREIGN KEY (position_id) REFERENCES job_positions(id)
        )
    ");
    
    // Create payroll_periods table
    echo "📅 Creating payroll_periods table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS payroll_periods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            period_name VARCHAR(100) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            pay_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'draft',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
    
    // Create payroll_records table
    echo "💰 Creating payroll_records table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS payroll_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            payroll_period_id INTEGER NOT NULL,
            basic_salary DECIMAL(12,2) DEFAULT 0,
            gross_pay DECIMAL(12,2) DEFAULT 0,
            taxable_income DECIMAL(12,2) DEFAULT 0,
            paye_tax DECIMAL(12,2) DEFAULT 0,
            nssf_deduction DECIMAL(12,2) DEFAULT 0,
            nhif_deduction DECIMAL(12,2) DEFAULT 0,
            housing_levy DECIMAL(12,2) DEFAULT 0,
            total_allowances DECIMAL(12,2) DEFAULT 0,
            total_deductions DECIMAL(12,2) DEFAULT 0,
            net_pay DECIMAL(12,2) DEFAULT 0,
            days_worked INTEGER DEFAULT 30,
            overtime_hours DECIMAL(5,2) DEFAULT 0,
            overtime_amount DECIMAL(12,2) DEFAULT 0,
            company_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
            FOREIGN KEY (company_id) REFERENCES companies(id)
        )
    ");
    
    // Insert default company
    echo "🏢 Creating default company...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM companies");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = $db->prepare("
            INSERT INTO companies (name, address, phone, email, kra_pin) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Garissa County Government',
            'P.O. Box 1-70100, Garissa, Kenya',
            '+254-700-000-000',
            'info@garissa.go.ke',
            'P051234567A'
        ]);
        echo "✅ Default company created\n";
    }
    
    // Insert default admin user
    echo "👑 Creating default admin user...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, role, company_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'admin',
            'admin@garissa.go.ke',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            1
        ]);
        echo "✅ Default admin user created\n";
        echo "   Username: admin\n";
        echo "   Password: admin123\n";
    }
    
    // Insert default department
    echo "🏢 Creating default department...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM departments");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = $db->prepare("
            INSERT INTO departments (company_id, name, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            1,
            'Administration',
            'General administration and management'
        ]);
        echo "✅ Default department created\n";
    }
    
    echo "\n🎉 Database initialization completed successfully!\n";
    echo "==================================================\n";
    echo "🌐 You can now access the application at: http://localhost:8000\n";
    echo "👤 Login with:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n";
    echo "==================================================\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
