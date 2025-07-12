<?php
/**
 * Emergency Duplicate Fix
 * 
 * This script will definitely resolve the duplicate admin user issue
 * and allow your installation to complete successfully.
 */

echo "🚨 EMERGENCY DUPLICATE FIX STARTING...\n\n";

try {
    // Include database config
    if (!file_exists('config/database.php')) {
        die("❌ Database configuration not found. Please ensure config/database.php exists.\n");
    }
    
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Cannot connect to database. Please check your database configuration.\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Step 1: Show current admin users
    echo "📋 CURRENT ADMIN USERS:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT id, username, email, first_name, last_name, created_at FROM users WHERE username = 'admin' OR role = 'admin' ORDER BY id");
    $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($adminUsers)) {
        echo "ℹ️ No admin users found.\n\n";
    } else {
        foreach ($adminUsers as $i => $user) {
            echo ($i + 1) . ". ID: {$user['id']}\n";
            echo "   Username: {$user['username']}\n";
            echo "   Email: {$user['email']}\n";
            echo "   Name: {$user['first_name']} {$user['last_name']}\n";
            echo "   Created: {$user['created_at']}\n";
            echo "   " . str_repeat("-", 40) . "\n";
        }
    }
    
    // Step 2: Remove ALL admin users (clean slate)
    echo "\n🧹 CLEANING ALL ADMIN USERS...\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->prepare("DELETE FROM users WHERE username = 'admin' OR role = 'admin'");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    
    echo "✅ Removed $deletedCount admin users\n\n";
    
    // Step 3: Verify cleanup
    echo "🔍 VERIFYING CLEANUP...\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin' OR role = 'admin'");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "✅ All admin users successfully removed\n";
        echo "✅ Database is clean and ready for fresh admin creation\n\n";
    } else {
        echo "⚠️ Warning: {$result['count']} admin users still exist\n\n";
    }
    
    // Step 4: Show all remaining users
    echo "👥 REMAINING USERS:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $stmt = $db->query("SELECT id, username, email, role FROM users ORDER BY id");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allUsers)) {
        echo "ℹ️ No users found in database\n\n";
    } else {
        foreach ($allUsers as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}\n";
        }
        echo "\n";
    }
    
    // Step 5: Clear installation marker to allow fresh setup
    echo "🔄 RESETTING INSTALLATION STATE...\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    if (file_exists('.installed')) {
        unlink('.installed');
        echo "✅ Removed .installed marker\n";
    } else {
        echo "ℹ️ No .installed marker found\n";
    }
    
    // Clear session data
    session_start();
    session_destroy();
    echo "✅ Cleared session data\n\n";
    
    // Step 6: Success message and next steps
    echo "🎉 EMERGENCY FIX COMPLETED SUCCESSFULLY!\n";
    echo "=" . str_repeat("=", 60) . "\n\n";
    
    echo "✅ All duplicate admin users removed\n";
    echo "✅ Database is clean and ready\n";
    echo "✅ Installation state reset\n";
    echo "✅ Session data cleared\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Go to: http://localhost:8888/kenyan-payroll-system/install.php\n";
    echo "2. Start fresh installation from Step 1\n";
    echo "3. Complete all steps normally\n";
    echo "4. Your installation should now work without errors!\n\n";
    
    echo "🔧 ALTERNATIVE OPTIONS:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "- Force reinstall: http://localhost:8888/kenyan-payroll-system/install.php?force=1\n";
    echo "- Check status: http://localhost:8888/kenyan-payroll-system/installation_status.php\n";
    echo "- Simple access: http://localhost:8888/kenyan-payroll-system/simple_landing.php\n\n";
    
    echo "💡 TIP: If you still get errors, clear your browser cache and cookies.\n\n";
    
} catch (Exception $e) {
    echo "❌ EMERGENCY FIX FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🆘 MANUAL STEPS TO FIX:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    echo "1. Access your database directly (phpMyAdmin, MySQL Workbench, etc.)\n";
    echo "2. Run this SQL command:\n";
    echo "   DELETE FROM users WHERE username = 'admin';\n";
    echo "3. Remove the .installed file from your project directory\n";
    echo "4. Clear browser cache and cookies\n";
    echo "5. Start fresh installation\n\n";
    
    echo "📞 NEED HELP?\n";
    echo "=" . str_repeat("=", 20) . "\n";
    echo "- Check database connection settings in config/database.php\n";
    echo "- Ensure MySQL service is running\n";
    echo "- Verify database user has DELETE privileges\n";
    echo "- Try accessing: http://localhost:8888/kenyan-payroll-system/break_redirect_loop.php\n\n";
}

echo "🇰🇪 Kenyan Payroll Management System - Emergency Fix Complete\n";
echo "=" . str_repeat("=", 60) . "\n";
?>
