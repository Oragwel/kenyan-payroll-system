<?php
/**
 * Fix Security Logs Table Structure
 * 
 * This script ensures the security_logs table has the correct column structure
 * to prevent "Unknown column 'action'" errors.
 */

echo "🔧 FIXING SECURITY LOGS TABLE STRUCTURE...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database. Please check your database configuration.\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Check if security_logs table exists
    echo "📋 CHECKING SECURITY LOGS TABLE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'security_logs'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "ℹ️ security_logs table does not exist\n";
        echo "🔧 Creating security_logs table...\n";
        
        $sql = "CREATE TABLE security_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            event_type VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        $db->exec($sql);
        echo "✅ Created security_logs table with correct structure\n\n";
    } else {
        echo "✅ security_logs table exists\n";
        
        // Check current table structure
        echo "\n📋 CURRENT TABLE STRUCTURE:\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        $stmt = $db->query("DESCRIBE security_logs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasEventType = false;
        $hasAction = false;
        $hasDescription = false;
        $hasDetails = false;
        
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']}\n";
            if ($column['Field'] === 'event_type') {
                $hasEventType = true;
            }
            if ($column['Field'] === 'action') {
                $hasAction = true;
            }
            if ($column['Field'] === 'description') {
                $hasDescription = true;
            }
            if ($column['Field'] === 'details') {
                $hasDetails = true;
            }
        }
        
        echo "\n";
        
        // Fix column issues
        $needsFix = false;
        
        if ($hasAction && !$hasEventType) {
            echo "🔧 FIXING: Renaming 'action' column to 'event_type'\n";
            $db->exec("ALTER TABLE security_logs CHANGE action event_type VARCHAR(100) NOT NULL");
            $needsFix = true;
        }
        
        if ($hasDetails && !$hasDescription) {
            echo "🔧 FIXING: Renaming 'details' column to 'description'\n";
            $db->exec("ALTER TABLE security_logs CHANGE details description TEXT");
            $needsFix = true;
        }
        
        if (!$hasEventType && !$hasAction) {
            echo "🔧 FIXING: Adding missing 'event_type' column\n";
            $db->exec("ALTER TABLE security_logs ADD COLUMN event_type VARCHAR(100) NOT NULL DEFAULT 'unknown'");
            $needsFix = true;
        }
        
        if (!$hasDescription && !$hasDetails) {
            echo "🔧 FIXING: Adding missing 'description' column\n";
            $db->exec("ALTER TABLE security_logs ADD COLUMN description TEXT");
            $needsFix = true;
        }
        
        if ($needsFix) {
            echo "✅ Table structure fixed\n\n";
        } else {
            echo "✅ Table structure is correct\n\n";
        }
    }
    
    // Verify final structure
    echo "🔍 VERIFYING FINAL TABLE STRUCTURE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("DESCRIBE security_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'user_id', 'event_type', 'description', 'ip_address', 'user_agent', 'severity', 'created_at'];
    $foundColumns = [];
    
    foreach ($columns as $column) {
        $foundColumns[] = $column['Field'];
        echo "✅ {$column['Field']}: {$column['Type']}\n";
    }
    
    echo "\n";
    
    // Check for missing required columns
    $missingColumns = array_diff($requiredColumns, $foundColumns);
    if (empty($missingColumns)) {
        echo "🎉 SUCCESS: All required columns are present!\n";
        echo "✅ event_type column exists (not 'action')\n";
        echo "✅ description column exists (not 'details')\n";
        echo "✅ Security logging should work properly\n\n";
    } else {
        echo "❌ ERROR: Missing required columns:\n";
        foreach ($missingColumns as $column) {
            echo "- $column\n";
        }
        echo "\n";
    }
    
    // Test insert to verify structure
    echo "🧪 TESTING TABLE FUNCTIONALITY:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    try {
        $stmt = $db->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, description, severity) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([null, 'test_event', '127.0.0.1', 'Test Agent', 'Table structure test', 'low']);
        
        // Clean up test record
        $db->exec("DELETE FROM security_logs WHERE event_type = 'test_event' AND description = 'Table structure test'");
        
        echo "✅ Table insert/delete test successful\n";
        echo "✅ Security logging functionality verified\n\n";
        
    } catch (Exception $e) {
        echo "❌ Table test failed: " . $e->getMessage() . "\n";
        echo "⚠️ Security logging may still have issues\n\n";
    }
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Test login functionality again\n";
    echo "2. Check for any remaining errors\n";
    echo "3. Monitor error logs for security logging issues\n";
    echo "4. Complete installation if needed\n\n";
    
    echo "🔧 IF STILL HAVING ISSUES:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "- Run emergency_fix_web.php for complete reset\n";
    echo "- Check installation status: installation_status.php\n";
    echo "- Try force reinstall: install.php?force=1\n";
    echo "- Clear browser cache and cookies\n\n";
    
} catch (Exception $e) {
    echo "❌ FIX FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Access your database directly (phpMyAdmin, etc.)\n";
    echo "2. Check if security_logs table exists\n";
    echo "3. If it has 'action' column, rename it:\n";
    echo "   ALTER TABLE security_logs CHANGE action event_type VARCHAR(100) NOT NULL;\n";
    echo "4. If it has 'details' column, rename it:\n";
    echo "   ALTER TABLE security_logs CHANGE details description TEXT;\n";
    echo "5. Or drop and recreate the table:\n";
    echo "   DROP TABLE security_logs;\n";
    echo "   -- Then let the system recreate it automatically\n\n";
}

echo "🇰🇪 Kenyan Payroll Management System - Security Logs Fix Complete\n";
echo "=" . str_repeat("=", 60) . "\n";
?>
