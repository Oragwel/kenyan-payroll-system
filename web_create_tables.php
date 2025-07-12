<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Missing Tables - Kenyan Payroll System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            color: #006b3f;
            margin-bottom: 30px;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .btn {
            background: #006b3f;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover { background: #004d2e; }
        .summary {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #006b3f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🇰🇪 Create Missing Database Tables</h1>
            <p>Kenyan Payroll Management System</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables'])) {
            echo '<div class="status info">🔧 CREATING MISSING PAYROLL SYSTEM TABLES...</div>';
            
            try {
                require_once 'config/database.php';
                $database = new Database();
                $db = $database->getConnection();
                
                if (!$db) {
                    throw new Exception("Cannot connect to database. Please check your database configuration.");
                }
                
                echo '<div class="status success">✅ Database connection successful</div>';
                
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
                        period_name VARCHAR(100) NOT NULL,
                        start_date DATE NOT NULL,
                        end_date DATE NOT NULL,
                        pay_date DATE NOT NULL,
                        status ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )",
                    
                    'payroll_records' => "CREATE TABLE IF NOT EXISTS payroll_records (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        payroll_period_id INT NOT NULL,
                        employee_id INT NOT NULL,
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
                    )",
                    
                    'system_settings' => "CREATE TABLE IF NOT EXISTS system_settings (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        company_id INT NOT NULL,
                        setting_key VARCHAR(100) NOT NULL,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_setting (company_id, setting_key),
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
                    )"
                ];
                
                echo '<div class="status info">📋 CREATING TABLES:</div>';
                
                $created = 0;
                $existed = 0;
                
                foreach ($tables as $tableName => $sql) {
                    try {
                        // Check if table exists
                        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
                        if ($stmt->rowCount() > 0) {
                            echo '<div class="status success">✅ ' . $tableName . ' - Already exists</div>';
                            $existed++;
                        } else {
                            // Create table
                            $db->exec($sql);
                            echo '<div class="status success">🆕 ' . $tableName . ' - Created successfully</div>';
                            $created++;
                        }
                    } catch (Exception $e) {
                        echo '<div class="status error">❌ ' . $tableName . ' - Error: ' . $e->getMessage() . '</div>';
                    }
                }
                
                echo '<div class="summary">';
                echo '<h3>📊 SUMMARY:</h3>';
                echo '<p><strong>Tables created:</strong> ' . $created . '</p>';
                echo '<p><strong>Tables already existed:</strong> ' . $existed . '</p>';
                echo '<p><strong>Total tables processed:</strong> ' . count($tables) . '</p>';
                echo '</div>';
                
                if ($created > 0) {
                    echo '<div class="status success">🎉 Table creation completed successfully!</div>';
                    echo '<div class="status info">💡 You can now try accessing the payslips page again.</div>';
                } else {
                    echo '<div class="status warning">ℹ️ All tables already existed. No new tables were created.</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="status error">❌ TABLE CREATION FAILED!<br>Error: ' . $e->getMessage() . '</div>';
                echo '<div class="status warning">🆘 MANUAL STEPS:<br>';
                echo '1. Access your database directly (phpMyAdmin, etc.)<br>';
                echo '2. Run the SQL commands manually for each missing table<br>';
                echo '3. Check database user permissions<br>';
                echo '4. Ensure foreign key constraints are satisfied</div>';
            }
        } else {
            ?>
            <div class="status warning">
                <h3>⚠️ Missing Database Tables Detected</h3>
                <p>The system has detected that some required database tables are missing, which is causing fatal errors.</p>
                <p>This tool will create the following tables if they don't exist:</p>
                <ul>
                    <li><strong>payroll_periods</strong> - Pay period management</li>
                    <li><strong>payroll_records</strong> - Payroll calculations and history</li>
                    <li><strong>departments</strong> - Organizational structure</li>
                    <li><strong>leave_types</strong> - Leave category definitions</li>
                    <li><strong>leave_applications</strong> - Leave request workflow</li>
                    <li><strong>attendance</strong> - Time tracking and hours worked</li>
                    <li><strong>system_settings</strong> - Application preferences</li>
                </ul>
            </div>
            
            <form method="POST" style="text-align: center;">
                <button type="submit" name="create_tables" class="btn">
                    🗄️ Create Missing Tables
                </button>
            </form>
            
            <div class="status info">
                <h4>🔒 Safety Notes:</h4>
                <ul>
                    <li>This operation is safe - it only creates tables that don't exist</li>
                    <li>Existing tables and data will not be affected</li>
                    <li>Foreign key constraints ensure data integrity</li>
                    <li>All tables follow Kenyan employment law requirements</li>
                </ul>
            </div>
            <?php
        }
        ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">🏠 Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
