<?php
/**
 * Temporarily Disable Login Lockout
 * 
 * This script temporarily disables the login lockout feature
 * by modifying the SecureAuth class settings.
 */

$secureAuthFile = 'secure_auth.php';

if (!file_exists($secureAuthFile)) {
    die("Error: secure_auth.php file not found.\n");
}

// Read the current file
$content = file_get_contents($secureAuthFile);

// Check if already disabled
if (strpos($content, 'private $maxAttempts = 999999;') !== false) {
    echo "✅ Login lockout is already disabled.\n";
    echo "📍 You can login without lockout restrictions.\n";
    exit(0);
}

// Create backup
$backupFile = 'secure_auth.php.backup.' . date('Y-m-d-H-i-s');
file_put_contents($backupFile, $content);
echo "📁 Backup created: $backupFile\n";

// Disable lockout by setting very high max attempts
$newContent = str_replace(
    'private $maxAttempts = 5;',
    'private $maxAttempts = 999999; // TEMPORARILY DISABLED',
    $content
);

$newContent = str_replace(
    'private $lockoutTime = 900;',
    'private $lockoutTime = 1; // TEMPORARILY DISABLED',
    $newContent
);

// Write the modified file
file_put_contents($secureAuthFile, $newContent);

echo "🔓 Login lockout temporarily disabled!\n";
echo "⚠️  WARNING: This is for testing only. Re-enable security when done.\n";
echo "📍 Login page: http://localhost:8888/kenyan-payroll-system/landing.html\n";
echo "\n🔄 To re-enable lockout, run: php enable_lockout.php\n";
?>
