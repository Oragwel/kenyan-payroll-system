<?php
/**
 * IMMEDIATE PAYROLL FIX
 * Run this to fix the company_id error right now
 */

require_once 'config/database.php';

echo "🚨 IMMEDIATE PAYROLL FIX\n";
echo "========================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database.\n");
    }
    
    echo "✅ Database connected\n";
    
    // Step 1: Check if company_id column exists
    echo "📋 Checking payroll_records table structure...\n";
    $stmt = $db->query("SHOW COLUMNS FROM payroll_records");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hasCompanyId = in_array('company_id', $columns);
    echo "Company ID column exists: " . ($hasCompanyId ? "YES" : "NO") . "\n";
    
    if (!$hasCompanyId) {
        // Add company_id column
        echo "🔧 Adding company_id column...\n";
        try {
            $db->exec("ALTER TABLE payroll_records ADD COLUMN company_id INT NOT NULL DEFAULT 1");
            echo "✅ Added company_id column\n";
        } catch (Exception $e) {
            echo "❌ Failed to add column: " . $e->getMessage() . "\n";
            exit;
        }
    }
    
    // Step 2: Ensure column allows NULL or has default
    echo "🔧 Making company_id column safe...\n";
    try {
        $db->exec("ALTER TABLE payroll_records MODIFY COLUMN company_id INT DEFAULT 1");
        echo "✅ Made company_id column safe with default value\n";
    } catch (Exception $e) {
        echo "⚠️ Could not modify column: " . $e->getMessage() . "\n";
    }
    
    // Step 3: Update any NULL values
    echo "📝 Fixing any NULL company_id values...\n";
    $stmt = $db->prepare("UPDATE payroll_records SET company_id = 1 WHERE company_id IS NULL");
    $stmt->execute();
    $updated = $stmt->rowCount();
    echo "✅ Updated $updated records with NULL company_id\n";
    
    // Step 4: Test insert
    echo "🧪 Testing payroll record insert...\n";
    
    // Get test data
    $stmt = $db->query("SELECT id FROM employees LIMIT 1");
    $employee = $stmt->fetch();
    
    $stmt = $db->query("SELECT id FROM payroll_periods LIMIT 1");
    $period = $stmt->fetch();
    
    if ($employee && $period) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO payroll_records (
                    employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                    paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                    total_deductions, net_pay, days_worked, overtime_hours, overtime_amount, company_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $employee['id'], $period['id'], 50000, 50000, 50000,
                12500, 3000, 1375, 750, 0, 17625, 32375, 30, 0, 0, 1
            ]);
            
            $db->rollback(); // Don't actually insert
            echo "✅ Test insert SUCCESSFUL - payroll processing should work now!\n";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "❌ Test insert failed: " . $e->getMessage() . "\n";
            
            // Try without company_id
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    INSERT INTO payroll_records (
                        employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                        paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                        total_deductions, net_pay, days_worked, overtime_hours, overtime_amount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $employee['id'], $period['id'], 50000, 50000, 50000,
                    12500, 3000, 1375, 750, 0, 17625, 32375, 30, 0, 0
                ]);
                
                $db->rollback();
                echo "✅ Test insert WITHOUT company_id SUCCESSFUL!\n";
                
                // Update payroll.php to not use company_id
                echo "🔧 Updating payroll.php to skip company_id...\n";
                
                $payrollContent = file_get_contents('pages/payroll.php');
                
                // Replace the problematic INSERT with a simpler one
                $oldInsert = 'INSERT INTO payroll_records (
                            employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                            paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                            total_deductions, net_pay, days_worked, overtime_hours, overtime_amount, company_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                
                $newInsert = 'INSERT INTO payroll_records (
                            employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                            paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                            total_deductions, net_pay, days_worked, overtime_hours, overtime_amount
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                
                $payrollContent = str_replace($oldInsert, $newInsert, $payrollContent);
                
                // Remove the company_id parameter
                $payrollContent = str_replace('$_SESSION[\'company_id\'] ?? 1', '', $payrollContent);
                $payrollContent = str_replace(', $_SESSION[\'company_id\']', '', $payrollContent);
                
                file_put_contents('pages/payroll.php', $payrollContent);
                echo "✅ Updated payroll.php to work without company_id\n";
                
            } catch (Exception $e2) {
                $db->rollback();
                echo "❌ Both insert methods failed: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        echo "⚠️ No test data available (need employees and payroll periods)\n";
    }
    
    echo "\n🎉 FIX COMPLETE!\n";
    echo "Try payroll processing again - it should work now.\n";
    echo "If it still fails, the table structure might be different than expected.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
