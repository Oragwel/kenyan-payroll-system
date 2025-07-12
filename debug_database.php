<?php
/**
 * Database Debug Tool
 * Check what tables exist and their structure
 */

require_once 'config/database.php';

echo "<h2>🔍 Database Debug Information</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
</style>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo "<p class='error'>❌ Cannot connect to database</p>";
        exit;
    }
    
    echo "<p class='success'>✅ Database connection successful</p>";
    
    // Check current database
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "<p class='info'>📊 Current database: <strong>" . $result['current_db'] . "</strong></p>";
    
    echo "<div class='section'>";
    echo "<h3>📋 All Tables in Database:</h3>";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    
    if (empty($tables)) {
        echo "<p class='error'>❌ No tables found in database!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Table Name</th><th>Status</th></tr>";
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            echo "<tr><td>$tableName</td><td class='success'>✅ Exists</td></tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check specifically for payroll_periods
    echo "<div class='section'>";
    echo "<h3>🎯 Payroll Periods Table Check:</h3>";
    
    $stmt = $db->query("SHOW TABLES LIKE 'payroll_periods'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ payroll_periods table exists</p>";
        
        // Show table structure
        echo "<h4>Table Structure:</h4>";
        $stmt = $db->query("DESCRIBE payroll_periods");
        $columns = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if there's data
        $stmt = $db->query("SELECT COUNT(*) as count FROM payroll_periods");
        $result = $stmt->fetch();
        echo "<p class='info'>📊 Records in table: <strong>" . $result['count'] . "</strong></p>";
        
    } else {
        echo "<p class='error'>❌ payroll_periods table does NOT exist</p>";
    }
    echo "</div>";
    
    // Check for payroll_records table too
    echo "<div class='section'>";
    echo "<h3>💰 Payroll Records Table Check:</h3>";
    
    $stmt = $db->query("SHOW TABLES LIKE 'payroll_records'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ payroll_records table exists</p>";
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM payroll_records");
        $result = $stmt->fetch();
        echo "<p class='info'>📊 Records in table: <strong>" . $result['count'] . "</strong></p>";
    } else {
        echo "<p class='error'>❌ payroll_records table does NOT exist</p>";
    }
    echo "</div>";
    
    // Test the exact query that's failing
    echo "<div class='section'>";
    echo "<h3>🧪 Test Problematic Query:</h3>";
    
    try {
        $stmt = $db->prepare("
            SELECT pr.*, pp.name as period_name, pp.pay_date,
                   'Test Employee' as employee_name,
                   'EMP001' as employee_number, 1 as employee_id
            FROM payroll_records pr
            JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
            LIMIT 1
        ");
        $stmt->execute();
        echo "<p class='success'>✅ Query executed successfully (but may return no results if tables are empty)</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Query failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='index.php'>🏠 Back to Dashboard</a>";
?>
