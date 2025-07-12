<?php
/**
 * Redirect Loop Breaker
 * 
 * This script helps users who are stuck in redirect loops
 * by clearing session data and providing direct access options.
 */

session_start();

// Clear any session data that might be causing loops
unset($_SESSION['installation_redirect_count']);
session_destroy();

// Start a fresh session
session_start();

echo "<h2>🔄 Redirect Loop Breaker</h2>";
echo "<p>Session data cleared. You can now access the system safely.</p>";

// Check current installation status
$statusMessage = '';
$canAccessSystem = false;

try {
    // Basic file checks without including problematic files
    $hasInstallMarker = file_exists('.installed');
    $hasDbConfig = file_exists('config/database.php');
    
    if (!$hasInstallMarker) {
        $statusMessage = "❌ Installation marker missing - System not installed";
    } elseif (!$hasDbConfig) {
        $statusMessage = "❌ Database configuration missing";
    } else {
        // Try to check database without causing loops
        try {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                // Quick table check
                $stmt = $db->query("SHOW TABLES LIKE 'users'");
                if ($stmt->rowCount() > 0) {
                    $canAccessSystem = true;
                    $statusMessage = "✅ System appears to be installed";
                } else {
                    $statusMessage = "❌ Database tables missing";
                }
            } else {
                $statusMessage = "❌ Cannot connect to database";
            }
        } catch (Exception $e) {
            $statusMessage = "❌ Database error: " . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $statusMessage = "❌ System error: " . $e->getMessage();
}

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Current Status:</h3>";
echo "<p>$statusMessage</p>";
echo "</div>";

echo "<h3>🎯 Available Options:</h3>";
echo "<div style='margin: 20px 0;'>";

if ($canAccessSystem) {
    echo "<a href='index.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px; display: inline-block; margin-bottom: 10px;'>
        🏠 Access System Dashboard
    </a>";
    
    echo "<a href='landing.html' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px; display: inline-block; margin-bottom: 10px;'>
        🔐 Go to Login Page
    </a>";
}

echo "<a href='install.php' style='background: #ffc107; color: black; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px; display: inline-block; margin-bottom: 10px;'>
    🚀 Run Installer
</a>";

echo "<a href='installation_status.php' style='background: #17a2b8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px; display: inline-block; margin-bottom: 10px;'>
    📊 Check Installation Status
</a>";

echo "<a href='clean_install.php' style='background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px; display: inline-block; margin-bottom: 10px;'>
    🧹 Clean Install
</a>";

echo "</div>";

echo "<h3>🔧 Troubleshooting:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>If you continue to experience redirect loops:</h4>";
echo "<ol>";
echo "<li><strong>Clear browser cache and cookies</strong> for this site</li>";
echo "<li><strong>Try a different browser</strong> or incognito/private mode</li>";
echo "<li><strong>Access the installer directly:</strong> <code>install.php</code></li>";
echo "<li><strong>Check installation status:</strong> <code>installation_status.php</code></li>";
echo "<li><strong>Clean install if needed:</strong> <code>clean_install.php</code></li>";
echo "</ol>";
echo "</div>";

echo "<h3>📋 System Information:</h3>";
echo "<div style='background: #e9ecef; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<p><strong>Current Script:</strong> " . basename($_SERVER['SCRIPT_NAME']) . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Installation Marker:</strong> " . (file_exists('.installed') ? 'Present' : 'Missing') . "</p>";
echo "<p><strong>Database Config:</strong> " . (file_exists('config/database.php') ? 'Present' : 'Missing') . "</p>";
echo "</div>";

?>

<!DOCTYPE html>
<html>
<head>
    <title>Redirect Loop Breaker - Kenyan Payroll System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        h2 {
            color: #006b3f;
            border-bottom: 3px solid #ce1126;
            padding-bottom: 10px;
        }
        
        h3 {
            color: #004d2e;
            margin-top: 30px;
        }
        
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        a {
            transition: all 0.3s ease;
        }
        
        a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
</body>
</html>
