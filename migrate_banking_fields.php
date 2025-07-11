<?php
/**
 * Database Migration: Add Banking Fields to Employees Table
 * 
 * This script adds banking information fields to existing employee records
 * for installations that were created before the banking feature was added.
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed.');
}

echo "<h2>🏦 Banking Fields Migration</h2>";
echo "<p>Adding banking information fields to employees table...</p>";

try {
    // Check if banking fields already exist
    $stmt = $db->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $bankingFields = ['bank_code', 'bank_name', 'bank_branch', 'account_number'];
    $missingFields = [];
    
    foreach ($bankingFields as $field) {
        if (!in_array($field, $columns)) {
            $missingFields[] = $field;
        }
    }
    
    if (empty($missingFields)) {
        echo "<div style='color: green;'>✅ All banking fields already exist. No migration needed.</div>";
    } else {
        echo "<p>Missing fields: " . implode(', ', $missingFields) . "</p>";
        
        // Add missing banking fields
        $alterStatements = [
            'bank_code' => "ALTER TABLE employees ADD COLUMN bank_code VARCHAR(10) AFTER position_id",
            'bank_name' => "ALTER TABLE employees ADD COLUMN bank_name VARCHAR(100) AFTER bank_code",
            'bank_branch' => "ALTER TABLE employees ADD COLUMN bank_branch VARCHAR(100) AFTER bank_name",
            'account_number' => "ALTER TABLE employees ADD COLUMN account_number VARCHAR(30) AFTER bank_branch"
        ];
        
        foreach ($missingFields as $field) {
            if (isset($alterStatements[$field])) {
                echo "<p>Adding field: $field...</p>";
                $db->exec($alterStatements[$field]);
                echo "<div style='color: green;'>✅ Added $field field successfully</div>";
            }
        }
        
        echo "<div style='color: green; font-weight: bold; margin-top: 20px;'>🎉 Banking fields migration completed successfully!</div>";
        echo "<p>You can now use the banking information features in employee management.</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Migration failed: " . $e->getMessage() . "</div>";
    echo "<p>Please check your database permissions and try again.</p>";
}

echo "<br><a href='index.php?page=employees' style='background: #006b3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Employee Management</a>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Banking Fields Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        h2 {
            color: #006b3f;
            border-bottom: 3px solid #ce1126;
            padding-bottom: 10px;
        }
        
        p {
            line-height: 1.6;
        }
        
        div {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
</body>
</html>
