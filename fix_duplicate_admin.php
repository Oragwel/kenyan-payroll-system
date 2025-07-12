<?php
/**
 * Quick Fix for Duplicate Admin Issue
 * 
 * This script removes duplicate admin users to allow installation to continue.
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    echo "🔧 Fixing duplicate admin user issue...\n\n";
    
    // Check current admin users
    $stmt = $db->query("SELECT id, username, email, first_name, last_name, created_at FROM users WHERE username = 'admin' ORDER BY id");
    $adminUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($adminUsers) . " admin users:\n";
    foreach ($adminUsers as $user) {
        echo "- ID: {$user['id']}, Email: {$user['email']}, Name: {$user['first_name']} {$user['last_name']}, Created: {$user['created_at']}\n";
    }
    echo "\n";
    
    if (count($adminUsers) > 1) {
        // Keep the first admin user, remove duplicates
        $keepUser = $adminUsers[0];
        echo "Keeping admin user ID: {$keepUser['id']} ({$keepUser['email']})\n";
        
        for ($i = 1; $i < count($adminUsers); $i++) {
            $removeUser = $adminUsers[$i];
            echo "Removing duplicate admin user ID: {$removeUser['id']} ({$removeUser['email']})\n";
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$removeUser['id']]);
        }
        
        echo "\n✅ Removed " . (count($adminUsers) - 1) . " duplicate admin users.\n";
        echo "✅ Installation can now continue.\n\n";
        
    } elseif (count($adminUsers) == 1) {
        echo "✅ Only one admin user found. No duplicates to remove.\n\n";
    } else {
        echo "⚠️ No admin users found. You may need to create one during installation.\n\n";
    }
    
    echo "🎯 Next steps:\n";
    echo "1. Go back to the installer: http://localhost:8888/kenyan-payroll-system/install.php?step=6\n";
    echo "2. Click 'Complete Installation' to finish the setup\n";
    echo "3. If you still get errors, try: http://localhost:8888/kenyan-payroll-system/install.php?force=1\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nTry accessing the cleanup tool: http://localhost:8888/kenyan-payroll-system/cleanup_duplicate_users.php\n";
    exit(1);
}
?>
