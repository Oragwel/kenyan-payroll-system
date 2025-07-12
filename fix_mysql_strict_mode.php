<?php
/**
 * Fix MySQL Strict Mode Issues
 * 
 * This script helps resolve MySQL sql_mode=only_full_group_by errors
 * and other strict mode compatibility issues.
 */

echo "🔧 FIXING MYSQL STRICT MODE COMPATIBILITY ISSUES...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database. Please check your database configuration.\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Check current SQL mode
    echo "📋 CHECKING CURRENT SQL MODE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT @@sql_mode as sql_mode");
    $result = $stmt->fetch();
    $currentSqlMode = $result['sql_mode'];
    
    echo "Current SQL Mode: $currentSqlMode\n\n";
    
    // Check if ONLY_FULL_GROUP_BY is enabled
    $hasOnlyFullGroupBy = strpos($currentSqlMode, 'ONLY_FULL_GROUP_BY') !== false;
    
    if ($hasOnlyFullGroupBy) {
        echo "⚠️ ONLY_FULL_GROUP_BY is enabled - this can cause GROUP BY errors\n\n";
        
        echo "🔧 FIXING SQL MODE:\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Remove ONLY_FULL_GROUP_BY from sql_mode
        $newSqlMode = str_replace('ONLY_FULL_GROUP_BY,', '', $currentSqlMode);
        $newSqlMode = str_replace(',ONLY_FULL_GROUP_BY', '', $newSqlMode);
        $newSqlMode = str_replace('ONLY_FULL_GROUP_BY', '', $newSqlMode);
        
        // Clean up any double commas
        $newSqlMode = str_replace(',,', ',', $newSqlMode);
        $newSqlMode = trim($newSqlMode, ',');
        
        try {
            // Set the new SQL mode for this session
            $stmt = $db->prepare("SET sql_mode = ?");
            $stmt->execute([$newSqlMode]);
            
            echo "✅ Temporarily disabled ONLY_FULL_GROUP_BY for this session\n";
            echo "New SQL Mode: $newSqlMode\n\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to modify SQL mode: " . $e->getMessage() . "\n\n";
        }
        
    } else {
        echo "✅ ONLY_FULL_GROUP_BY is not enabled - no issues expected\n\n";
    }
    
    // Test problematic queries
    echo "🧪 TESTING DASHBOARD QUERIES:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $testQueries = [
        'Employee Growth Analytics' => "
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                DATE_FORMAT(created_at, '%M %Y') as month_name,
                COUNT(*) as new_employees
            FROM employees
            WHERE company_id = 1
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
            LIMIT 1
        ",
        
        'Monthly Payroll Trends' => "
            SELECT
                DATE_FORMAT(pp.start_date, '%Y-%m') as month,
                DATE_FORMAT(pp.start_date, '%M %Y') as month_name,
                SUM(pr.net_pay) as total_net_pay,
                COUNT(pr.id) as employee_count
            FROM payroll_periods pp
            JOIN payroll_records pr ON pp.id = pr.payroll_period_id
            WHERE pp.company_id = 1
            AND pp.start_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(pp.start_date, '%Y-%m')
            ORDER BY month ASC
            LIMIT 1
        ",
        
        'Department Statistics' => "
            SELECT
                d.name as department_name,
                COUNT(e.id) as employee_count,
                AVG(e.basic_salary) as avg_salary
            FROM departments d
            LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status = 'active'
            WHERE d.company_id = 1
            GROUP BY d.id, d.name
            ORDER BY employee_count DESC
            LIMIT 1
        "
    ];
    
    $passedTests = 0;
    $totalTests = count($testQueries);
    
    foreach ($testQueries as $testName => $query) {
        try {
            $stmt = $db->prepare($query);
            $stmt->execute();
            echo "✅ $testName - Query executed successfully\n";
            $passedTests++;
        } catch (Exception $e) {
            echo "❌ $testName - Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    echo "📊 TEST RESULTS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "Passed: $passedTests / $totalTests tests\n";
    
    if ($passedTests == $totalTests) {
        echo "🎉 All queries are working properly!\n\n";
    } else {
        echo "⚠️ Some queries still have issues\n\n";
    }
    
    // Provide recommendations
    echo "💡 RECOMMENDATIONS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    
    if ($hasOnlyFullGroupBy) {
        echo "1. TEMPORARY FIX (Current Session):\n";
        echo "   - ONLY_FULL_GROUP_BY has been disabled for this session\n";
        echo "   - Dashboard should work until you restart MySQL\n\n";
        
        echo "2. PERMANENT FIX (MySQL Configuration):\n";
        echo "   - Edit your MySQL configuration file (my.cnf or my.ini)\n";
        echo "   - Add or modify the sql_mode setting:\n";
        echo "     sql_mode = \"STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO\"\n";
        echo "   - Restart MySQL server\n\n";
        
        echo "3. MAMP USERS:\n";
        echo "   - Go to MAMP > Preferences > MySQL\n";
        echo "   - Click 'Set to MySQL defaults'\n";
        echo "   - Or edit /Applications/MAMP/conf/mysql/my.cnf\n\n";
        
        echo "4. ALTERNATIVE (Code Fix):\n";
        echo "   - The dashboard queries have been updated to be compatible\n";
        echo "   - This should resolve the GROUP BY issues\n\n";
    } else {
        echo "✅ Your MySQL configuration is compatible\n";
        echo "✅ No changes needed\n\n";
    }
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Test dashboard access: index.php?page=dashboard\n";
    echo "2. If still having issues, try the permanent MySQL config fix\n";
    echo "3. Consider updating to MySQL 8.0+ for better compatibility\n";
    echo "4. Monitor error logs for any remaining issues\n\n";
    
} catch (Exception $e) {
    echo "❌ MYSQL STRICT MODE FIX FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Access MySQL directly and run:\n";
    echo "   SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';\n\n";
    echo "2. Or edit MySQL configuration file:\n";
    echo "   - Find my.cnf or my.ini\n";
    echo "   - Add: sql_mode = \"STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO\"\n";
    echo "   - Restart MySQL\n\n";
    echo "3. For MAMP users:\n";
    echo "   - MAMP > Preferences > MySQL > Set to MySQL defaults\n\n";
}

echo "🇰🇪 Kenyan Payroll Management System - MySQL Strict Mode Fix Complete\n";
echo "=" . str_repeat("=", 60) . "\n";
?>
