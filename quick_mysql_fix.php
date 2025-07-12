<?php
/**
 * Quick MySQL Strict Mode Fix
 * 
 * This script quickly disables ONLY_FULL_GROUP_BY for the current session
 * to resolve GROUP BY errors immediately.
 */

echo "🔧 QUICK MYSQL STRICT MODE FIX...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database.\n");
    }
    
    echo "✅ Database connected\n";
    
    // Get current SQL mode
    $stmt = $db->query("SELECT @@sql_mode as sql_mode");
    $result = $stmt->fetch();
    $currentMode = $result['sql_mode'];
    
    echo "Current SQL Mode: $currentMode\n\n";
    
    // Remove ONLY_FULL_GROUP_BY
    $newMode = str_replace(['ONLY_FULL_GROUP_BY,', ',ONLY_FULL_GROUP_BY', 'ONLY_FULL_GROUP_BY'], '', $currentMode);
    $newMode = str_replace(',,', ',', $newMode);
    $newMode = trim($newMode, ',');
    
    // Set new mode
    $stmt = $db->prepare("SET sql_mode = ?");
    $stmt->execute([$newMode]);
    
    echo "✅ ONLY_FULL_GROUP_BY disabled for this session\n";
    echo "New SQL Mode: $newMode\n\n";
    
    echo "🎯 Dashboard should now work!\n";
    echo "Visit: http://localhost:8888/kenyan-payroll-system/index.php?page=dashboard\n\n";
    
    echo "💡 For permanent fix:\n";
    echo "1. MAMP users: MAMP > Preferences > MySQL > Set to MySQL defaults\n";
    echo "2. Or edit my.cnf: sql_mode = \"STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO\"\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Manual fix:\n";
    echo "Run this SQL command in phpMyAdmin or MySQL:\n";
    echo "SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';\n";
}
?>
