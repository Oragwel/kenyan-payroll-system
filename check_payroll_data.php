<?php
/**
 * Check Payroll Data and Create Test Data
 * Verify what data exists and optionally create sample data
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Data Check</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; padding: 20px; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 10px; max-width: 1000px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        th, td { border: 1px solid #dee2e6; padding: 8px 12px; text-align: left; }
        th { background: #e9ecef; font-weight: bold; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Payroll Data Analysis</h1>
        
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception("Cannot connect to database");
            }
            
            echo '<div class="section"><span class="success">✅ Database connected successfully</span></div>';
            
            // Check payroll_records table
            echo '<h2>1. Payroll Records Table Status</h2>';
            $stmt = $db->query("SELECT COUNT(*) as total_records FROM payroll_records");
            $result = $stmt->fetch();
            $totalRecords = $result['total_records'];
            
            echo '<div class="section">';
            echo '<strong>Total payroll records:</strong> ' . $totalRecords;
            if ($totalRecords == 0) {
                echo ' <span class="warning">⚠️ No payroll records found</span>';
            } else {
                echo ' <span class="success">✅ Records exist</span>';
            }
            echo '</div>';
            
            // Check employees table
            echo '<h2>2. Employees Table Status</h2>';
            $stmt = $db->prepare("SELECT COUNT(*) as total_employees FROM employees WHERE company_id = ?");
            $stmt->execute([$_SESSION['company_id'] ?? 1]);
            $result = $stmt->fetch();
            $totalEmployees = $result['total_employees'];
            
            echo '<div class="section">';
            echo '<strong>Total employees:</strong> ' . $totalEmployees;
            if ($totalEmployees == 0) {
                echo ' <span class="warning">⚠️ No employees found</span>';
            } else {
                echo ' <span class="success">✅ Employees exist</span>';
            }
            echo '</div>';
            
            // Check payroll periods
            echo '<h2>3. Payroll Periods Table Status</h2>';
            $stmt = $db->prepare("SELECT COUNT(*) as total_periods FROM payroll_periods WHERE company_id = ?");
            $stmt->execute([$_SESSION['company_id'] ?? 1]);
            $result = $stmt->fetch();
            $totalPeriods = $result['total_periods'];
            
            echo '<div class="section">';
            echo '<strong>Total payroll periods:</strong> ' . $totalPeriods;
            if ($totalPeriods == 0) {
                echo ' <span class="warning">⚠️ No payroll periods found</span>';
            } else {
                echo ' <span class="success">✅ Payroll periods exist</span>';
            }
            echo '</div>';
            
            // Show recent payroll data if exists
            if ($totalRecords > 0) {
                echo '<h2>4. Recent Payroll Records</h2>';
                $stmt = $db->prepare("
                    SELECT 
                        pr.*,
                        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                        pp.period_name,
                        pp.pay_date
                    FROM payroll_records pr
                    LEFT JOIN employees e ON pr.employee_id = e.id
                    LEFT JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
                    ORDER BY pr.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute();
                $records = $stmt->fetchAll();
                
                if (!empty($records)) {
                    echo '<table>';
                    echo '<tr><th>Employee</th><th>Period</th><th>Basic Salary</th><th>Gross Pay</th><th>Net Pay</th><th>Pay Date</th></tr>';
                    foreach ($records as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['employee_name'] ?? 'Unknown') . '</td>';
                        echo '<td>' . htmlspecialchars($record['period_name'] ?? 'Unknown') . '</td>';
                        echo '<td>KES ' . number_format($record['basic_salary'], 2) . '</td>';
                        echo '<td>KES ' . number_format($record['gross_pay'], 2) . '</td>';
                        echo '<td>KES ' . number_format($record['net_pay'], 2) . '</td>';
                        echo '<td>' . htmlspecialchars($record['pay_date'] ?? 'Unknown') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
            
            // Show date ranges with data
            if ($totalRecords > 0) {
                echo '<h2>5. Available Date Ranges</h2>';
                $stmt = $db->query("
                    SELECT 
                        MIN(pp.pay_date) as earliest_date,
                        MAX(pp.pay_date) as latest_date,
                        COUNT(DISTINCT pp.pay_date) as unique_pay_dates
                    FROM payroll_records pr
                    JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
                ");
                $result = $stmt->fetch();
                
                echo '<div class="section">';
                echo '<strong>Date range with data:</strong><br>';
                echo 'Earliest: ' . ($result['earliest_date'] ?? 'None') . '<br>';
                echo 'Latest: ' . ($result['latest_date'] ?? 'None') . '<br>';
                echo 'Unique pay dates: ' . ($result['unique_pay_dates'] ?? 0);
                echo '</div>';
            }
            
            // Recommendations
            echo '<h2>6. Recommendations</h2>';
            echo '<div class="section">';
            
            if ($totalRecords == 0) {
                echo '<h3 class="warning">⚠️ No Payroll Data Found</h3>';
                echo '<p>To test statutory reporting, you need to:</p>';
                echo '<ol>';
                echo '<li><strong>Add Employees:</strong> Go to <a href="index.php?page=employees">Employee Management</a></li>';
                echo '<li><strong>Create Payroll Period:</strong> Go to <a href="index.php?page=payroll">Payroll Management</a></li>';
                echo '<li><strong>Process Payroll:</strong> Calculate salaries for employees</li>';
                echo '<li><strong>Generate Reports:</strong> Then use statutory reporting</li>';
                echo '</ol>';
                
                echo '<h4>Quick Test Data Option:</h4>';
                echo '<p>I can create sample test data for you to test the statutory reporting:</p>';
                echo '<form method="POST">';
                echo '<button type="submit" name="create_test_data" class="btn btn-success">Create Test Payroll Data</button>';
                echo '</form>';
                
            } else {
                echo '<h3 class="success">✅ Payroll Data Exists</h3>';
                echo '<p>You have payroll data! For statutory reporting:</p>';
                echo '<ol>';
                echo '<li>Use the date range: <strong>' . ($result['earliest_date'] ?? 'Unknown') . '</strong> to <strong>' . ($result['latest_date'] ?? 'Unknown') . '</strong></li>';
                echo '<li>Go to <a href="index.php?page=statutory&action=generate" class="btn">Test Statutory Reporting</a></li>';
                echo '</ol>';
            }
            echo '</div>';
            
            // Handle test data creation
            if (isset($_POST['create_test_data'])) {
                echo '<h2>7. Creating Test Data</h2>';
                
                // Create test company if needed
                $companyId = $_SESSION['company_id'] ?? 1;
                
                // Create test employees
                $testEmployees = [
                    ['John', 'Doe', 'EMP001', '12345678', 'A123456789P', 'NSS001', 'NHIF001', 50000],
                    ['Jane', 'Smith', 'EMP002', '87654321', 'A987654321P', 'NSS002', 'NHIF002', 60000],
                    ['Peter', 'Mwangi', 'EMP003', '11223344', 'A111222333P', 'NSS003', 'NHIF003', 45000]
                ];
                
                foreach ($testEmployees as $emp) {
                    try {
                        $stmt = $db->prepare("
                            INSERT IGNORE INTO employees 
                            (company_id, first_name, last_name, employee_number, id_number, kra_pin, nssf_number, nhif_number, basic_salary, employment_status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([$companyId, $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7]]);
                        echo '<p class="success">✅ Created employee: ' . $emp[0] . ' ' . $emp[1] . '</p>';
                    } catch (Exception $e) {
                        echo '<p class="info">ℹ️ Employee ' . $emp[0] . ' ' . $emp[1] . ' already exists</p>';
                    }
                }
                
                // Create test payroll period
                try {
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO payroll_periods 
                        (company_id, period_name, start_date, end_date, pay_date, status) 
                        VALUES (?, 'December 2024', '2024-12-01', '2024-12-31', '2024-12-31', 'processed')
                    ");
                    $stmt->execute([$companyId]);
                    echo '<p class="success">✅ Created payroll period: December 2024</p>';
                } catch (Exception $e) {
                    echo '<p class="info">ℹ️ Payroll period already exists</p>';
                }
                
                // Get period ID
                $stmt = $db->prepare("SELECT id FROM payroll_periods WHERE company_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$companyId]);
                $period = $stmt->fetch();
                $periodId = $period['id'];
                
                // Get employees
                $stmt = $db->prepare("SELECT id, basic_salary FROM employees WHERE company_id = ? LIMIT 3");
                $stmt->execute([$companyId]);
                $employees = $stmt->fetchAll();
                
                // Create payroll records
                foreach ($employees as $employee) {
                    $basicSalary = $employee['basic_salary'];
                    $grossPay = $basicSalary;
                    $taxableIncome = $grossPay;
                    $payeTax = $taxableIncome * 0.25; // 25% tax rate
                    $nssfDeduction = min($grossPay * 0.06, 2160); // 6% max 2160
                    $nhifDeduction = $grossPay * 0.0275; // 2.75%
                    $housingLevy = $grossPay * 0.015; // 1.5%
                    $totalDeductions = $payeTax + $nssfDeduction + $nhifDeduction + $housingLevy;
                    $netPay = $grossPay - $totalDeductions;
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT IGNORE INTO payroll_records 
                            (employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income, paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances, total_deductions, net_pay, days_worked, company_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 30, ?)
                        ");
                        $stmt->execute([$employee['id'], $periodId, $basicSalary, $grossPay, $taxableIncome, $payeTax, $nssfDeduction, $nhifDeduction, $housingLevy, $totalDeductions, $netPay, $companyId]);
                        echo '<p class="success">✅ Created payroll record for employee ID: ' . $employee['id'] . '</p>';
                    } catch (Exception $e) {
                        echo '<p class="info">ℹ️ Payroll record already exists for employee ID: ' . $employee['id'] . '</p>';
                    }
                }
                
                echo '<div class="section">';
                echo '<h3 class="success">🎉 Test Data Created Successfully!</h3>';
                echo '<p>You can now test statutory reporting with:</p>';
                echo '<ul>';
                echo '<li><strong>Date Range:</strong> 2024-12-01 to 2024-12-31</li>';
                echo '<li><strong>Employees:</strong> 3 test employees with calculated deductions</li>';
                echo '<li><strong>All Reports:</strong> PAYE, NSSF, SHIF, Housing Levy</li>';
                echo '</ul>';
                echo '<a href="index.php?page=statutory&action=generate" class="btn btn-success">Test Statutory Reporting Now</a>';
                echo '</div>';
            }
            
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
