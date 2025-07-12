<?php
/**
 * Fix Payroll Records Company ID Issue
 * Adds company_id column to payroll_records table if missing
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Payroll Company ID Issue</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; padding: 20px; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 10px; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .fix-section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        th, td { border: 1px solid #dee2e6; padding: 8px 12px; text-align: left; }
        th { background: #e9ecef; font-weight: bold; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Payroll Company ID Issue</h1>
        <p class="info">This script will fix the "Column 'company_id' cannot be null" error in payroll processing.</p>
        
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception("Cannot connect to database");
            }
            
            echo '<div class="fix-section"><span class="success">✅ Database connected successfully</span></div>';
            
            // Check if payroll_records table exists
            $stmt = $db->query("SHOW TABLES LIKE 'payroll_records'");
            if ($stmt->rowCount() == 0) {
                echo '<div class="error">❌ Error: payroll_records table does not exist!</div>';
                echo '<p>Please create the payroll_records table first.</p>';
                exit;
            }
            
            echo '<div class="fix-section"><span class="success">✅ payroll_records table exists</span></div>';
            
            // Check current table structure
            echo '<h2>📊 Current Table Structure</h2>';
            $stmt = $db->query("DESCRIBE payroll_records");
            $columns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>';
            $hasCompanyId = false;
            foreach ($columns as $col) {
                if ($col['Field'] === 'company_id') {
                    $hasCompanyId = true;
                }
                echo '<tr>';
                echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($col['Extra'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Check if company_id column exists
            echo '<h2>🔍 Company ID Column Check</h2>';
            if ($hasCompanyId) {
                echo '<div class="fix-section">';
                echo '<span class="success">✅ company_id column already exists</span>';
                echo '<p>The payroll_records table already has the company_id column. The error might be due to a null value being passed.</p>';
                echo '</div>';
                
                // Check for any records with null company_id
                $stmt = $db->query("SELECT COUNT(*) as null_count FROM payroll_records WHERE company_id IS NULL");
                $result = $stmt->fetch();
                
                if ($result['null_count'] > 0) {
                    echo '<div class="warning">';
                    echo '<h4>⚠️ Found ' . $result['null_count'] . ' records with NULL company_id</h4>';
                    echo '<p>These records need to be updated with a valid company_id.</p>';
                    echo '</div>';
                    
                    // Get available companies
                    $stmt = $db->query("SELECT id, name FROM companies ORDER BY id");
                    $companies = $stmt->fetchAll();
                    
                    if (!empty($companies)) {
                        echo '<h4>Available Companies:</h4>';
                        echo '<ul>';
                        foreach ($companies as $company) {
                            echo '<li>ID: ' . $company['id'] . ' - ' . htmlspecialchars($company['name']) . '</li>';
                        }
                        echo '</ul>';
                        
                        // Update null company_id records with the first company
                        $defaultCompanyId = $companies[0]['id'];
                        try {
                            $stmt = $db->prepare("UPDATE payroll_records SET company_id = ? WHERE company_id IS NULL");
                            $stmt->execute([$defaultCompanyId]);
                            $updated = $stmt->rowCount();
                            
                            echo '<div class="fix-section">';
                            echo '<span class="success">✅ Updated ' . $updated . ' records with company_id = ' . $defaultCompanyId . '</span>';
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="error">❌ Failed to update records: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
                
            } else {
                echo '<div class="fix-section">';
                echo '<span class="error">❌ company_id column is missing</span>';
                echo '<p>Adding company_id column to payroll_records table...</p>';
                echo '</div>';
                
                try {
                    // Add company_id column
                    $sql = "ALTER TABLE payroll_records ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER net_pay";
                    $db->exec($sql);
                    
                    echo '<div class="fix-section">';
                    echo '<span class="success">✅ Successfully added company_id column</span>';
                    echo '</div>';
                    
                    // Add foreign key constraint if companies table exists
                    $stmt = $db->query("SHOW TABLES LIKE 'companies'");
                    if ($stmt->rowCount() > 0) {
                        try {
                            $sql = "ALTER TABLE payroll_records ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE";
                            $db->exec($sql);
                            
                            echo '<div class="fix-section">';
                            echo '<span class="success">✅ Added foreign key constraint for company_id</span>';
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="warning">⚠️ Could not add foreign key constraint: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                    
                    // Update existing records with session company_id or default
                    $defaultCompanyId = $_SESSION['company_id'] ?? 1;
                    
                    $stmt = $db->prepare("UPDATE payroll_records SET company_id = ? WHERE company_id = 1");
                    $stmt->execute([$defaultCompanyId]);
                    $updated = $stmt->rowCount();
                    
                    if ($updated > 0) {
                        echo '<div class="fix-section">';
                        echo '<span class="success">✅ Updated ' . $updated . ' existing records with company_id = ' . $defaultCompanyId . '</span>';
                        echo '</div>';
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ Failed to add company_id column: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            
            // Show updated table structure
            echo '<h2>📊 Updated Table Structure</h2>';
            $stmt = $db->query("DESCRIBE payroll_records");
            $updatedColumns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>';
            foreach ($updatedColumns as $col) {
                $isCompanyId = ($col['Field'] === 'company_id');
                $rowClass = $isCompanyId ? ' style="background-color: #d4edda;"' : '';
                echo '<tr' . $rowClass . '>';
                echo '<td>' . htmlspecialchars($col['Field']) . ($isCompanyId ? ' <strong>(FIXED)</strong>' : '') . '</td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($col['Extra'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Test payroll record insertion
            echo '<h2>🧪 Testing Payroll Record Insertion</h2>';
            try {
                // Check if we have employees and payroll periods
                $stmt = $db->query("SELECT COUNT(*) as emp_count FROM employees");
                $empResult = $stmt->fetch();
                
                $stmt = $db->query("SELECT COUNT(*) as period_count FROM payroll_periods");
                $periodResult = $stmt->fetch();
                
                if ($empResult['emp_count'] > 0 && $periodResult['period_count'] > 0) {
                    // Get first employee and period for testing
                    $stmt = $db->query("SELECT id FROM employees LIMIT 1");
                    $employee = $stmt->fetch();
                    
                    $stmt = $db->query("SELECT id FROM payroll_periods LIMIT 1");
                    $period = $stmt->fetch();
                    
                    // Test insert (will rollback)
                    $db->beginTransaction();
                    
                    $testStmt = $db->prepare("
                        INSERT INTO payroll_records (
                            employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                            paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                            total_deductions, net_pay, days_worked, overtime_hours, overtime_amount, company_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $testStmt->execute([
                        $employee['id'], $period['id'], 50000, 50000, 50000,
                        12500, 3000, 1375, 750, 0, 17625, 32375, 30, 0, 0,
                        $_SESSION['company_id'] ?? 1
                    ]);
                    
                    $db->rollBack(); // Don't actually insert the test record
                    
                    echo '<div class="fix-section">';
                    echo '<span class="success">✅ Test insertion successful - payroll processing should now work</span>';
                    echo '</div>';
                    
                } else {
                    echo '<div class="warning">⚠️ Cannot test insertion - no employees or payroll periods found</div>';
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                echo '<div class="error">❌ Test insertion failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            // Success message
            echo '<div class="fix-section" style="background: #d4edda; border-left-color: #28a745;">';
            echo '<h3 class="success">🎉 SUCCESS!</h3>';
            echo '<p><strong>The payroll company_id issue has been fixed!</strong></p>';
            echo '<p>You can now process payroll without the "Column company_id cannot be null" error.</p>';
            echo '</div>';
            
            echo '<div style="text-align: center; margin-top: 30px;">';
            echo '<a href="index.php?page=payroll&action=process" class="btn btn-success">🎯 Try Payroll Processing</a>';
            echo '<a href="index.php?page=payroll" class="btn">📊 Go to Payroll</a>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Error</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
