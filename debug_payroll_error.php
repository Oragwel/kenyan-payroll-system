<?php
/**
 * Debug Payroll Processing Error
 * Comprehensive diagnostic for company_id constraint violation
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Payroll Error</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .debug-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Payroll Error Diagnostic</h1>
        <p>Comprehensive analysis of the company_id constraint violation error.</p>
        
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception("Cannot connect to database");
            }
            
            echo '<div class="debug-section"><span class="success">✅ Database connected</span></div>';
            
            // 1. Check session data
            echo '<h2>1. 📋 Session Analysis</h2>';
            echo '<div class="debug-section">';
            echo '<h4>Session Data:</h4>';
            echo '<pre>';
            echo 'Session ID: ' . session_id() . "\n";
            echo 'Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
            echo 'Company ID in Session: ' . (isset($_SESSION['company_id']) ? $_SESSION['company_id'] : 'NOT SET') . "\n";
            echo 'User ID in Session: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
            echo 'User Role in Session: ' . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'NOT SET') . "\n";
            echo '</pre>';
            
            if (!isset($_SESSION['company_id']) || $_SESSION['company_id'] === null) {
                echo '<span class="error">❌ CRITICAL: company_id is not set in session!</span>';
            } else {
                echo '<span class="success">✅ company_id is set in session</span>';
            }
            echo '</div>';
            
            // 2. Check payroll_records table structure
            echo '<h2>2. 🗃️ Table Structure Analysis</h2>';
            echo '<div class="debug-section">';
            
            $stmt = $db->query("DESCRIBE payroll_records");
            $columns = $stmt->fetchAll();
            
            echo '<h4>payroll_records Table Columns:</h4>';
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>';
            
            $hasCompanyId = false;
            $companyIdDetails = null;
            
            foreach ($columns as $col) {
                if ($col['Field'] === 'company_id') {
                    $hasCompanyId = true;
                    $companyIdDetails = $col;
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
            
            if ($hasCompanyId) {
                echo '<span class="success">✅ company_id column exists</span><br>';
                echo '<strong>Column Details:</strong><br>';
                echo 'Type: ' . $companyIdDetails['Type'] . '<br>';
                echo 'Null Allowed: ' . $companyIdDetails['Null'] . '<br>';
                echo 'Default: ' . ($companyIdDetails['Default'] ?? 'NULL') . '<br>';
                
                if ($companyIdDetails['Null'] === 'NO' && ($companyIdDetails['Default'] === null || $companyIdDetails['Default'] === 'NULL')) {
                    echo '<span class="error">❌ ISSUE: Column is NOT NULL but has no default value!</span>';
                }
            } else {
                echo '<span class="error">❌ company_id column does not exist</span>';
            }
            echo '</div>';
            
            // 3. Check companies table
            echo '<h2>3. 🏢 Companies Table Analysis</h2>';
            echo '<div class="debug-section">';
            
            $stmt = $db->query("SHOW TABLES LIKE 'companies'");
            if ($stmt->rowCount() > 0) {
                echo '<span class="success">✅ companies table exists</span><br>';
                
                $stmt = $db->query("SELECT id, name FROM companies ORDER BY id");
                $companies = $stmt->fetchAll();
                
                echo '<h4>Available Companies:</h4>';
                if (!empty($companies)) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Name</th></tr>';
                    foreach ($companies as $company) {
                        echo '<tr>';
                        echo '<td>' . $company['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($company['name']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<span class="warning">⚠️ No companies found in database</span>';
                }
            } else {
                echo '<span class="error">❌ companies table does not exist</span>';
            }
            echo '</div>';
            
            // 4. Check existing payroll records
            echo '<h2>4. 📊 Existing Payroll Records Analysis</h2>';
            echo '<div class="debug-section">';
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM payroll_records");
            $result = $stmt->fetch();
            echo '<strong>Total payroll records:</strong> ' . $result['total'] . '<br>';
            
            if ($hasCompanyId) {
                $stmt = $db->query("SELECT COUNT(*) as null_count FROM payroll_records WHERE company_id IS NULL");
                $nullResult = $stmt->fetch();
                echo '<strong>Records with NULL company_id:</strong> ' . $nullResult['null_count'] . '<br>';
                
                if ($nullResult['null_count'] > 0) {
                    echo '<span class="error">❌ Found records with NULL company_id</span>';
                } else {
                    echo '<span class="success">✅ No NULL company_id values found</span>';
                }
            }
            echo '</div>';
            
            // 5. Test the exact INSERT that's failing
            echo '<h2>5. 🧪 Simulate Payroll Insert</h2>';
            echo '<div class="debug-section">';
            
            // Get test data
            $stmt = $db->query("SELECT id FROM employees LIMIT 1");
            $employee = $stmt->fetch();
            
            $stmt = $db->query("SELECT id FROM payroll_periods LIMIT 1");
            $period = $stmt->fetch();
            
            if ($employee && $period) {
                echo '<h4>Test Data Available:</h4>';
                echo 'Employee ID: ' . $employee['id'] . '<br>';
                echo 'Period ID: ' . $period['id'] . '<br>';
                echo 'Session Company ID: ' . ($_SESSION['company_id'] ?? 'NULL') . '<br>';
                
                // Test the exact INSERT logic from payroll.php
                echo '<h4>Testing INSERT Logic:</h4>';
                
                // Check if company_id column exists (same logic as payroll.php)
                $hasCompanyIdCheck = false;
                try {
                    $checkStmt = $db->prepare("SHOW COLUMNS FROM payroll_records LIKE 'company_id'");
                    $checkStmt->execute();
                    $hasCompanyIdCheck = $checkStmt->rowCount() > 0;
                } catch (Exception $e) {
                    echo '<span class="error">Error checking column: ' . $e->getMessage() . '</span><br>';
                }
                
                echo 'Column check result: ' . ($hasCompanyIdCheck ? 'TRUE' : 'FALSE') . '<br>';
                
                // Test INSERT (with rollback)
                try {
                    $db->beginTransaction();
                    
                    if ($hasCompanyIdCheck) {
                        echo '<h4>Testing INSERT with company_id:</h4>';
                        $testStmt = $db->prepare("
                            INSERT INTO payroll_records (
                                employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                                paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                                total_deductions, net_pay, days_worked, overtime_hours, overtime_amount, company_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $companyIdValue = $_SESSION['company_id'] ?? 1;
                        echo 'Using company_id value: ' . $companyIdValue . '<br>';
                        
                        $testStmt->execute([
                            $employee['id'], $period['id'], 50000, 50000, 50000,
                            12500, 3000, 1375, 750, 0, 17625, 32375, 30, 0, 0,
                            $companyIdValue
                        ]);
                        
                        echo '<span class="success">✅ INSERT with company_id successful</span>';
                        
                    } else {
                        echo '<h4>Testing INSERT without company_id:</h4>';
                        $testStmt = $db->prepare("
                            INSERT INTO payroll_records (
                                employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                                paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                                total_deductions, net_pay, days_worked, overtime_hours, overtime_amount
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $testStmt->execute([
                            $employee['id'], $period['id'], 50000, 50000, 50000,
                            12500, 3000, 1375, 750, 0, 17625, 32375, 30, 0, 0
                        ]);
                        
                        echo '<span class="success">✅ INSERT without company_id successful</span>';
                    }
                    
                    $db->rollback(); // Don't actually insert
                    
                } catch (Exception $e) {
                    $db->rollback();
                    echo '<span class="error">❌ INSERT failed: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    
                    // Show the exact error details
                    echo '<h4>Error Details:</h4>';
                    echo '<pre>';
                    echo 'Error Code: ' . $e->getCode() . "\n";
                    echo 'Error Message: ' . $e->getMessage() . "\n";
                    echo 'Error File: ' . $e->getFile() . ':' . $e->getLine() . "\n";
                    echo '</pre>';
                }
                
            } else {
                echo '<span class="warning">⚠️ No test data available (need employees and payroll periods)</span>';
            }
            echo '</div>';
            
            // 6. Recommendations
            echo '<h2>6. 💡 Recommendations</h2>';
            echo '<div class="debug-section">';
            
            if (!isset($_SESSION['company_id'])) {
                echo '<h4 class="error">CRITICAL ISSUE: Session company_id not set</h4>';
                echo '<p>The session does not contain a company_id. This is likely the root cause.</p>';
                echo '<p><strong>Solutions:</strong></p>';
                echo '<ul>';
                echo '<li>Ensure user login sets $_SESSION[\'company_id\']</li>';
                echo '<li>Check authentication system</li>';
                echo '<li>Verify session management</li>';
                echo '</ul>';
                
                // Try to set a default company_id
                if (!empty($companies)) {
                    $defaultCompany = $companies[0];
                    $_SESSION['company_id'] = $defaultCompany['id'];
                    echo '<p class="success">✅ Temporarily set company_id to ' . $defaultCompany['id'] . ' (' . htmlspecialchars($defaultCompany['name']) . ')</p>';
                }
            }
            
            if ($hasCompanyId && $companyIdDetails['Null'] === 'NO' && ($companyIdDetails['Default'] === null || $companyIdDetails['Default'] === 'NULL')) {
                echo '<h4 class="error">TABLE ISSUE: company_id column has no default</h4>';
                echo '<p>The company_id column is NOT NULL but has no default value.</p>';
                echo '<p><strong>Solution:</strong> Add a default value to the column</p>';
                echo '<form method="POST">';
                echo '<button type="submit" name="fix_column" class="btn btn-danger">Fix Column Default</button>';
                echo '</form>';
            }
            
            echo '</div>';
            
            // Handle fix column request
            if (isset($_POST['fix_column'])) {
                echo '<h2>7. 🔧 Fixing Column Default</h2>';
                echo '<div class="debug-section">';
                
                try {
                    $db->exec("ALTER TABLE payroll_records MODIFY COLUMN company_id INT NOT NULL DEFAULT 1");
                    echo '<span class="success">✅ Successfully added default value to company_id column</span>';
                } catch (Exception $e) {
                    echo '<span class="error">❌ Failed to fix column: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
                
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Diagnostic Error</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php?page=payroll&action=process" class="btn">🔄 Try Payroll Processing Again</a>
            <a href="index.php?page=payroll" class="btn">📊 Back to Payroll</a>
        </div>
    </div>
</body>
</html>
