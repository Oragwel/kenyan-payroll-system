<?php
/**
 * Quick Reset Lockout Script
 * 
 * This script quickly resets login lockouts from command line
 * Usage: php quick_reset_lockout.php [ip_address]
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get IP address from command line argument or reset all
    $targetIP = $argv[1] ?? null;
    
    if ($targetIP) {
        // Reset specific IP
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = FALSE");
        $stmt->execute([$targetIP]);
        $deletedRows = $stmt->rowCount();
        
        echo "✅ Successfully reset lockout for IP: $targetIP ($deletedRows failed attempts cleared)\n";
        
        // Log the reset
        try {
            $stmt = $db->prepare("INSERT INTO security_logs (user_id, event_type, description, ip_address, severity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                null,
                'lockout_reset',
                "Lockout reset for IP: $targetIP via command line",
                'localhost',
                'medium'
            ]);
        } catch (Exception $e) {
            // Ignore logging errors
        }
        
    } else {
        // Reset all lockouts
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE success = FALSE");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
        
        echo "✅ Successfully reset ALL lockouts ($deletedRows failed attempts cleared)\n";
        
        // Log the reset
        try {
            $stmt = $db->prepare("INSERT INTO security_logs (user_id, event_type, description, ip_address, severity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                null,
                'all_lockouts_reset',
                "All lockouts reset via command line",
                'localhost',
                'high'
            ]);
        } catch (Exception $e) {
            // Ignore logging errors
        }
    }
    
    echo "\n🔓 You can now try logging in again!\n";
    echo "📍 Login page: http://localhost:8888/kenyan-payroll-system/landing.html\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
