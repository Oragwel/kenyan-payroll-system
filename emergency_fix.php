<?php
/**
 * Emergency Fix for Payroll Records Table
 * Run this once to add missing columns
 */

require_once 'config/database.php';

echo "🚨 EMERGENCY FIX: Adding missing columns to payroll_records table\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database.\n");
    }
    
    echo "✅ Database connected\n";
    
    // Check current columns
    echo "📋 Checking current table structure...\n";
    $stmt = $db->query("DESCRIBE payroll_records");
    $currentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current columns: " . implode(', ', $currentColumns) . "\n\n";
    
    // Add missing columns one by one
    $columnsToAdd = [
        'taxable_income' => 'DECIMAL(12,2) DEFAULT 0',
        'total_allowances' => 'DECIMAL(12,2) DEFAULT 0',
        'overtime_hours' => 'DECIMAL(5,2) DEFAULT 0',
        'overtime_amount' => 'DECIMAL(12,2) DEFAULT 0',
        'days_worked' => 'INT DEFAULT 30'
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        if (!in_array($column, $currentColumns)) {
            try {
                $sql = "ALTER TABLE payroll_records ADD COLUMN $column $definition";
                echo "Adding $column... ";
                $db->exec($sql);
                echo "✅ SUCCESS\n";
            } catch (Exception $e) {
                echo "❌ FAILED: " . $e->getMessage() . "\n";
            }
        } else {
            echo "$column already exists ✅\n";
        }
    }
    
    // Update existing records
    echo "\n📝 Updating existing records...\n";
    try {
        $sql = "UPDATE payroll_records SET taxable_income = gross_pay WHERE taxable_income IS NULL OR taxable_income = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        echo "Updated " . $stmt->rowCount() . " records with taxable_income ✅\n";
    } catch (Exception $e) {
        echo "Update failed: " . $e->getMessage() . "\n";
    }
    
    // Test the query
    echo "\n🧪 Testing statutory query...\n";
    try {
        $sql = "SELECT taxable_income, total_allowances, paye_tax FROM payroll_records LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        echo "✅ Query test PASSED - all columns accessible\n";
    } catch (Exception $e) {
        echo "❌ Query test FAILED: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 EMERGENCY FIX COMPLETE!\n";
    echo "Try the statutory reporting again.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
