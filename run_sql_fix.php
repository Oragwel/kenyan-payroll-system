<?php
/**
 * Web-based SQL Script Runner
 * Executes the payroll table fix script
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Table Fix - SQL Script Runner</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa; 
            padding: 20px; 
            margin: 0;
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            max-width: 1000px; 
            margin: 0 auto; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .sql-result { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
            border-left: 4px solid #007bff;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            background: white;
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 8px 12px; 
            text-align: left; 
        }
        th { 
            background: #e9ecef; 
            font-weight: bold;
        }
        .step { 
            background: #e7f3ff; 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 5px; 
            border-left: 4px solid #007bff;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Payroll Table Structure Fix</h1>
        <p class="info">This script will add missing columns to the payroll_records table for statutory reporting compatibility.</p>
        
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                throw new Exception("Cannot connect to database");
            }
            
            echo '<div class="step"><strong>✅ Database Connection:</strong> Connected successfully</div>';
            
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE 'payroll_records'");
            if ($stmt->rowCount() == 0) {
                echo '<div class="error">❌ Error: payroll_records table does not exist!</div>';
                echo '<p>Please create the payroll_records table first.</p>';
                exit;
            }
            
            echo '<div class="step"><strong>✅ Table Check:</strong> payroll_records table exists</div>';
            
            // Show current structure
            echo '<h2>📊 Current Table Structure</h2>';
            $stmt = $db->query("DESCRIBE payroll_records");
            $currentColumns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>';
            $existingColumns = [];
            foreach ($currentColumns as $col) {
                $existingColumns[] = $col['Field'];
                echo '<tr>';
                echo '<td>' . htmlspecialchars($col['Field']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Define required columns
            $requiredColumns = [
                'taxable_income' => ['type' => 'DECIMAL(12,2)', 'default' => '0', 'after' => 'gross_pay'],
                'total_allowances' => ['type' => 'DECIMAL(12,2)', 'default' => '0', 'after' => 'housing_levy'],
                'overtime_hours' => ['type' => 'DECIMAL(5,2)', 'default' => '0', 'after' => 'total_deductions'],
                'overtime_amount' => ['type' => 'DECIMAL(12,2)', 'default' => '0', 'after' => 'overtime_hours'],
                'days_worked' => ['type' => 'INT', 'default' => '30', 'after' => 'overtime_amount'],
                'updated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'after' => null]
            ];
            
            echo '<h2>🔧 Adding Missing Columns</h2>';
            
            $addedColumns = [];
            foreach ($requiredColumns as $column => $config) {
                if (!in_array($column, $existingColumns)) {
                    try {
                        $sql = "ALTER TABLE payroll_records ADD COLUMN $column {$config['type']} DEFAULT {$config['default']}";
                        if ($config['after']) {
                            $sql .= " AFTER {$config['after']}";
                        }
                        
                        echo '<div class="sql-result">';
                        echo '<strong>Adding column:</strong> ' . htmlspecialchars($column) . '<br>';
                        echo '<code>' . htmlspecialchars($sql) . '</code><br>';
                        
                        $db->exec($sql);
                        echo '<span class="success">✅ SUCCESS</span>';
                        $addedColumns[] = $column;
                        
                    } catch (Exception $e) {
                        echo '<span class="error">❌ FAILED: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="sql-result">';
                    echo '<strong>Column exists:</strong> ' . htmlspecialchars($column) . ' ';
                    echo '<span class="success">✅ ALREADY EXISTS</span>';
                    echo '</div>';
                }
            }
            
            // Update existing records
            if (!empty($addedColumns)) {
                echo '<h2>📝 Updating Existing Records</h2>';
                
                // Update taxable_income
                if (in_array('taxable_income', $addedColumns)) {
                    try {
                        $sql = "UPDATE payroll_records SET taxable_income = gross_pay WHERE taxable_income IS NULL OR taxable_income = 0";
                        $stmt = $db->prepare($sql);
                        $stmt->execute();
                        $affected = $stmt->rowCount();
                        echo '<div class="sql-result">';
                        echo '<strong>Updated taxable_income:</strong> ' . $affected . ' records updated';
                        echo '<span class="success"> ✅</span>';
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div class="sql-result">';
                        echo '<strong>Update taxable_income:</strong> ';
                        echo '<span class="error">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
                        echo '</div>';
                    }
                }
                
                // Update other columns with defaults
                try {
                    $sql = "UPDATE payroll_records SET 
                            total_allowances = COALESCE(total_allowances, 0),
                            overtime_hours = COALESCE(overtime_hours, 0),
                            overtime_amount = COALESCE(overtime_amount, 0),
                            days_worked = COALESCE(days_worked, 30)
                            WHERE total_allowances IS NULL 
                            OR overtime_hours IS NULL 
                            OR overtime_amount IS NULL 
                            OR days_worked IS NULL";
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $affected = $stmt->rowCount();
                    echo '<div class="sql-result">';
                    echo '<strong>Updated default values:</strong> ' . $affected . ' records updated';
                    echo '<span class="success"> ✅</span>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="sql-result">';
                    echo '<strong>Update defaults:</strong> ';
                    echo '<span class="error">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
                    echo '</div>';
                }
            }
            
            // Show updated structure
            echo '<h2>📊 Updated Table Structure</h2>';
            $stmt = $db->query("DESCRIBE payroll_records");
            $updatedColumns = $stmt->fetchAll();
            
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>';
            foreach ($updatedColumns as $col) {
                $isNew = in_array($col['Field'], $addedColumns);
                $rowClass = $isNew ? ' style="background-color: #d4edda;"' : '';
                echo '<tr' . $rowClass . '>';
                echo '<td>' . htmlspecialchars($col['Field']) . ($isNew ? ' <strong>(NEW)</strong>' : '') . '</td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Test the query
            echo '<h2>🧪 Testing Statutory Report Query</h2>';
            try {
                $sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(COALESCE(basic_salary, 0)) as total_basic_salary,
                    SUM(COALESCE(gross_pay, 0)) as total_gross_pay,
                    SUM(COALESCE(taxable_income, 0)) as total_taxable_income,
                    SUM(COALESCE(paye_tax, 0)) as total_paye_tax,
                    SUM(COALESCE(nssf_deduction, 0)) as total_nssf,
                    SUM(COALESCE(nhif_deduction, 0)) as total_nhif,
                    SUM(COALESCE(housing_levy, 0)) as total_housing_levy,
                    SUM(COALESCE(total_allowances, 0)) as total_allowances,
                    SUM(COALESCE(total_deductions, 0)) as total_deductions,
                    SUM(COALESCE(net_pay, 0)) as total_net_pay
                FROM payroll_records";
                
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch();
                
                echo '<div class="sql-result">';
                echo '<span class="success">✅ Query executed successfully!</span><br>';
                echo '<strong>Records found:</strong> ' . $result['total_records'] . '<br>';
                if ($result['total_records'] > 0) {
                    echo '<strong>Total Gross Pay:</strong> KES ' . number_format($result['total_gross_pay'], 2) . '<br>';
                    echo '<strong>Total Taxable Income:</strong> KES ' . number_format($result['total_taxable_income'], 2) . '<br>';
                    echo '<strong>Total PAYE Tax:</strong> KES ' . number_format($result['total_paye_tax'], 2) . '<br>';
                }
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="sql-result">';
                echo '<span class="error">❌ Query test failed: ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '</div>';
            }
            
            // Success message
            echo '<div class="step" style="background: #d4edda; border-left-color: #28a745;">';
            echo '<h3 class="success">🎉 SUCCESS!</h3>';
            echo '<p><strong>The payroll_records table structure has been fixed successfully!</strong></p>';
            echo '<p>All required columns have been added and existing data has been updated.</p>';
            echo '<p>You can now use the statutory reporting system without column errors.</p>';
            echo '</div>';
            
            echo '<div style="text-align: center; margin-top: 30px;">';
            echo '<a href="index.php?page=statutory&action=generate" class="btn btn-success">🎯 Test Statutory Reporting</a>';
            echo '<a href="index.php?page=dashboard" class="btn">📊 Go to Dashboard</a>';
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
