<?php
/**
 * Re-enable Login Lockout
 * 
 * This script re-enables the login lockout security feature
 * by restoring the original SecureAuth class settings.
 */

$secureAuthFile = 'secure_auth.php';

if (!file_exists($secureAuthFile)) {
    die("Error: secure_auth.php file not found.\n");
}

// Read the current file
$content = file_get_contents($secureAuthFile);

// Check if already enabled
if (strpos($content, 'private $maxAttempts = 5;') !== false) {
    echo "✅ Login lockout is already enabled.\n";
    echo "🔒 Security features are active.\n";
    exit(0);
}

// Re-enable lockout by restoring original settings
$newContent = str_replace(
    'private $maxAttempts = 999999; // TEMPORARILY DISABLED',
    'private $maxAttempts = 5;',
    $content
);

$newContent = str_replace(
    'private $lockoutTime = 1; // TEMPORARILY DISABLED',
    'private $lockoutTime = 900; // 15 minutes',
    $newContent
);

// Write the modified file
file_put_contents($secureAuthFile, $newContent);

echo "🔒 Login lockout security re-enabled!\n";
echo "✅ System is now secure with 5 max attempts and 15-minute lockout.\n";
echo "📍 Login page: http://localhost:8888/kenyan-payroll-system/landing.html\n";
?>
