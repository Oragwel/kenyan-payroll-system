<?php
/**
 * Debug Payroll Table Structure
 * Check and fix payroll_records table columns
 */

require_once 'config/database.php';

// Set content type for web display
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Table Diagnostic</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PAYROLL_RECORDS TABLE DIAGNOSTIC</h1>
        <hr>

<?php

echo "<h2>Database Connection</h2>\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die('<p class="error">❌ Cannot connect to database.</p>');
    }

    echo '<p class="success">✅ Database connected successfully</p>';
    
    // Check if payroll_records table exists
    echo "<h2>Table Existence Check</h2>\n";
    echo "<p>📋 Checking if payroll_records table exists...</p>\n";
    $stmt = $db->query("SHOW TABLES LIKE 'payroll_records'");
    if ($stmt->rowCount() == 0) {
        echo '<p class="error">❌ payroll_records table does not exist!</p>';
        echo '<p class="info">🔧 Creating payroll_records table...</p>';
        
        $sql = "
            CREATE TABLE payroll_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NOT NULL,
                payroll_period_id INT NOT NULL,
                basic_salary DECIMAL(12,2) NOT NULL,
                gross_pay DECIMAL(12,2) NOT NULL,
                taxable_income DECIMAL(12,2) NOT NULL,
                paye_tax DECIMAL(12,2) NOT NULL,
                nssf_deduction DECIMAL(12,2) NOT NULL,
                nhif_deduction DECIMAL(12,2) NOT NULL,
                housing_levy DECIMAL(12,2) NOT NULL,
                total_allowances DECIMAL(12,2) DEFAULT 0,
                total_deductions DECIMAL(12,2) NOT NULL,
                net_pay DECIMAL(12,2) NOT NULL,
                days_worked INT DEFAULT 30,
                overtime_hours DECIMAL(5,2) DEFAULT 0,
                overtime_amount DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
                UNIQUE KEY unique_employee_period (employee_id, payroll_period_id)
            )
        ";
        
        $db->exec($sql);
        echo '<p class="success">✅ payroll_records table created successfully</p>';
    } else {
        echo '<p class="success">✅ payroll_records table exists</p>';
    }

    // Show current table structure
    echo "<h2>Current Table Structure</h2>\n";
    $stmt = $db->query("DESCRIBE payroll_records");
    $columns = $stmt->fetchAll();

    echo "<table>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>\n";

    $columnNames = [];
    foreach ($columns as $column) {
        $columnNames[] = $column['Field'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check for required columns
    echo "<h2>Required Columns Check</h2>\n";

    $requiredColumns = [
        'taxable_income' => 'DECIMAL(12,2) DEFAULT 0',
        'total_allowances' => 'DECIMAL(12,2) DEFAULT 0',
        'overtime_hours' => 'DECIMAL(5,2) DEFAULT 0',
        'overtime_amount' => 'DECIMAL(12,2) DEFAULT 0',
        'days_worked' => 'INT DEFAULT 30'
    ];

    $missingColumns = [];
    echo "<ul>\n";
    foreach ($requiredColumns as $column => $definition) {
        if (in_array($column, $columnNames)) {
            echo '<li class="success">✅ ' . htmlspecialchars($column) . ' - EXISTS</li>';
        } else {
            echo '<li class="error">❌ ' . htmlspecialchars($column) . ' - MISSING</li>';
            $missingColumns[$column] = $definition;
        }
    }
    echo "</ul>\n";
    
    // Add missing columns
    if (!empty($missingColumns)) {
        echo "<h2>Adding Missing Columns</h2>\n";

        foreach ($missingColumns as $column => $definition) {
            try {
                $sql = "ALTER TABLE payroll_records ADD COLUMN $column $definition";
                echo '<p class="info">Adding ' . htmlspecialchars($column) . '... ';
                $db->exec($sql);
                echo '<span class="success">✅ SUCCESS</span></p>';
            } catch (Exception $e) {
                echo '<span class="error">❌ FAILED: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
            }
        }

        echo "<h3>Updated Table Structure</h3>\n";
        $stmt = $db->query("DESCRIBE payroll_records");
        $columns = $stmt->fetchAll();

        echo "<table>\n";
        echo "<tr><th>Field</th><th>Type</th></tr>\n";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Test query
    echo "<h2>Testing Statutory Report Query</h2>\n";

    try {
        $sql = "
            SELECT
                pr.basic_salary,
                pr.gross_pay,
                pr.taxable_income,
                pr.paye_tax,
                pr.nssf_deduction,
                pr.nhif_deduction,
                pr.housing_levy,
                pr.total_allowances,
                pr.total_deductions,
                pr.net_pay
            FROM payroll_records pr
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        echo '<p class="success">✅ Query executed successfully - all columns accessible</p>';

        $result = $stmt->fetch();
        if ($result) {
            echo '<p class="success">✅ Sample data found</p>';
            echo "<h3>Sample Record</h3>\n";
            echo "<table>\n";
            foreach ($result as $key => $value) {
                if (!is_numeric($key)) {
                    echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
                }
            }
            echo "</table>\n";
        } else {
            echo '<p class="warning">⚠️ No data in table (this is normal if no payroll has been processed)</p>';
        }

    } catch (Exception $e) {
        echo '<p class="error">❌ Query failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }

    echo "<h2>🎉 DIAGNOSIS COMPLETE!</h2>\n";
    echo '<p class="success">✅ Table structure should now be compatible with statutory reporting</p>';
    echo '<p class="info">🎯 Try accessing the statutory page again</p>';

} catch (Exception $e) {
    echo '<p class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>

    </div>
</body>
</html>
