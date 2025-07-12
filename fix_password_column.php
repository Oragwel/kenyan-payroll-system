<?php
/**
 * Fix Password Column Name
 * 
 * This script fixes the password column name inconsistency
 * between password_hash and password in the users table.
 */

echo "🔧 FIXING PASSWORD COLUMN INCONSISTENCY...\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database. Please check your database configuration.\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Check current table structure
    echo "📋 CHECKING CURRENT TABLE STRUCTURE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasPassword = false;
    $hasPasswordHash = false;
    
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
        if ($column['Field'] === 'password') {
            $hasPassword = true;
        }
        if ($column['Field'] === 'password_hash') {
            $hasPasswordHash = true;
        }
    }
    
    echo "\n";
    
    // Determine what needs to be fixed
    if ($hasPassword && !$hasPasswordHash) {
        echo "✅ Table structure is correct (has 'password' column)\n";
        echo "ℹ️ No changes needed\n\n";
    } elseif (!$hasPassword && $hasPasswordHash) {
        echo "🔧 FIXING: Table has 'password_hash' but needs 'password'\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Rename column
        $stmt = $db->exec("ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL");
        echo "✅ Renamed 'password_hash' column to 'password'\n\n";
        
    } elseif ($hasPassword && $hasPasswordHash) {
        echo "⚠️ WARNING: Table has BOTH 'password' and 'password_hash' columns\n";
        echo "🔧 FIXING: Removing duplicate 'password_hash' column\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Check if password_hash has data that password doesn't
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE password IS NULL AND password_hash IS NOT NULL");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo "📋 Copying data from 'password_hash' to 'password' for {$result['count']} users\n";
            $stmt = $db->exec("UPDATE users SET password = password_hash WHERE password IS NULL AND password_hash IS NOT NULL");
        }
        
        // Drop the duplicate column
        $stmt = $db->exec("ALTER TABLE users DROP COLUMN password_hash");
        echo "✅ Removed duplicate 'password_hash' column\n\n";
        
    } else {
        echo "❌ ERROR: Table has neither 'password' nor 'password_hash' column\n";
        echo "🔧 FIXING: Adding 'password' column\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        $stmt = $db->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT ''");
        echo "✅ Added 'password' column\n\n";
    }
    
    // Verify final structure
    echo "🔍 VERIFYING FINAL TABLE STRUCTURE:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $finalHasPassword = false;
    $finalHasPasswordHash = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'password') {
            $finalHasPassword = true;
            echo "✅ password: {$column['Type']}\n";
        }
        if ($column['Field'] === 'password_hash') {
            $finalHasPasswordHash = true;
            echo "⚠️ password_hash: {$column['Type']} (should not exist)\n";
        }
    }
    
    echo "\n";
    
    if ($finalHasPassword && !$finalHasPasswordHash) {
        echo "🎉 SUCCESS: Table structure is now correct!\n";
        echo "✅ Has 'password' column\n";
        echo "✅ No 'password_hash' column\n";
        echo "✅ Login functionality should work properly\n\n";
    } else {
        echo "❌ ERROR: Table structure still has issues\n";
        if (!$finalHasPassword) {
            echo "- Missing 'password' column\n";
        }
        if ($finalHasPasswordHash) {
            echo "- Still has 'password_hash' column\n";
        }
        echo "\n";
    }
    
    // Check for users with empty passwords
    echo "👥 CHECKING USER PASSWORD DATA:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as empty FROM users WHERE password IS NULL OR password = ''");
    $emptyPasswords = $stmt->fetch()['empty'];
    
    echo "Total users: $totalUsers\n";
    echo "Users with empty passwords: $emptyPasswords\n";
    
    if ($emptyPasswords > 0) {
        echo "⚠️ WARNING: Some users have empty passwords\n";
        echo "💡 These users will not be able to log in\n";
        echo "🔧 Consider running the installation again to create proper admin user\n";
    } else {
        echo "✅ All users have password data\n";
    }
    
    echo "\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Test login functionality\n";
    echo "2. If login still fails, run emergency fix tools\n";
    echo "3. Check installation status: installation_status.php\n";
    echo "4. Complete installation if needed: install.php\n\n";
    
    echo "🔧 TROUBLESHOOTING:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "- Clear browser cache and cookies\n";
    echo "- Try different browser or incognito mode\n";
    echo "- Run emergency_fix_web.php if still having issues\n";
    echo "- Check error logs for additional information\n\n";
    
} catch (Exception $e) {
    echo "❌ FIX FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Access your database directly (phpMyAdmin, etc.)\n";
    echo "2. Check the users table structure\n";
    echo "3. If you have 'password_hash' column, rename it to 'password':\n";
    echo "   ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL;\n";
    echo "4. If you have both columns, drop 'password_hash':\n";
    echo "   ALTER TABLE users DROP COLUMN password_hash;\n";
    echo "5. Restart your web server\n\n";
}

echo "🇰🇪 Kenyan Payroll Management System - Password Column Fix Complete\n";
echo "=" . str_repeat("=", 60) . "\n";
?>
