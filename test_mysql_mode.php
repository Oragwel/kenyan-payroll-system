<?php
/**
 * Test MySQL Mode and GROUP BY Query
 * 
 * This script tests the exact query that's causing the error
 * and shows the current MySQL mode.
 */

echo "🔍 TESTING MYSQL MODE AND GROUP BY QUERY...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database.\n");
    }
    
    echo "✅ Database connected\n\n";
    
    // Check current SQL mode
    echo "📋 CURRENT SQL MODE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT @@sql_mode as sql_mode");
    $result = $stmt->fetch();
    $currentMode = $result['sql_mode'];
    
    echo "SQL Mode: $currentMode\n\n";
    
    $hasGroupByIssue = strpos($currentMode, 'ONLY_FULL_GROUP_BY') !== false;
    
    if ($hasGroupByIssue) {
        echo "⚠️ ONLY_FULL_GROUP_BY is ENABLED - This will cause GROUP BY errors\n\n";
    } else {
        echo "✅ ONLY_FULL_GROUP_BY is DISABLED - Should work fine\n\n";
    }
    
    // Test the exact problematic query
    echo "🧪 TESTING THE PROBLEMATIC QUERY:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $testQuery = "
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
    ";
    
    echo "Query:\n";
    echo $testQuery . "\n\n";
    
    try {
        $stmt = $db->prepare($testQuery);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        echo "✅ QUERY EXECUTED SUCCESSFULLY!\n";
        echo "Result count: " . count($result) . " rows\n\n";
        
        if (count($result) > 0) {
            echo "Sample result:\n";
            print_r($result[0]);
        }
        
    } catch (Exception $e) {
        echo "❌ QUERY FAILED!\n";
        echo "Error: " . $e->getMessage() . "\n\n";
        
        if (strpos($e->getMessage(), 'only_full_group_by') !== false) {
            echo "🔧 This is the ONLY_FULL_GROUP_BY error!\n";
            echo "Run the MySQL fix tool to resolve this.\n\n";
        }
    }
    
    // Test if we can fix the SQL mode
    if ($hasGroupByIssue) {
        echo "🔧 ATTEMPTING TO FIX SQL MODE:\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        $newMode = str_replace(['ONLY_FULL_GROUP_BY,', ',ONLY_FULL_GROUP_BY', 'ONLY_FULL_GROUP_BY'], '', $currentMode);
        $newMode = str_replace(',,', ',', $newMode);
        $newMode = trim($newMode, ',');
        
        try {
            $stmt = $db->prepare("SET sql_mode = ?");
            $stmt->execute([$newMode]);
            
            echo "✅ SQL mode updated successfully!\n";
            echo "New mode: $newMode\n\n";
            
            // Test the query again
            echo "🧪 TESTING QUERY AGAIN AFTER FIX:\n";
            echo "=" . str_repeat("=", 30) . "\n";
            
            $stmt = $db->prepare($testQuery);
            $stmt->execute();
            $result = $stmt->fetchAll();
            
            echo "✅ QUERY NOW WORKS!\n";
            echo "Result count: " . count($result) . " rows\n\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to fix SQL mode: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    
    if ($hasGroupByIssue) {
        echo "1. The MySQL fix tool should work - try mysql_fix_web.php\n";
        echo "2. Or run: php quick_mysql_fix.php\n";
        echo "3. For permanent fix: Edit MySQL configuration\n";
        echo "4. MAMP users: MAMP > Preferences > MySQL > Set to defaults\n";
    } else {
        echo "1. SQL mode looks correct\n";
        echo "2. Try accessing the dashboard now\n";
        echo "3. If still having issues, check for other errors\n";
    }
    
} catch (Exception $e) {
    echo "❌ TEST FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "1. Check database connection\n";
    echo "2. Verify config/database.php exists\n";
    echo "3. Try accessing phpMyAdmin\n";
    echo "4. Run SQL manually: SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';\n";
}

echo "\n🇰🇪 MySQL Mode Test Complete\n";
?>
