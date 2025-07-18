<?php
/**
 * Complete Installation - Mark installation as complete
 */

// Create installation marker file
$installFile = __DIR__ . '/config/installed.txt';
$installDir = dirname($installFile);

if (!is_dir($installDir)) {
    mkdir($installDir, 0755, true);
}

file_put_contents($installFile, date('Y-m-d H:i:s') . " - Installation completed with SQLite database\n");

echo "✅ Installation marked as complete!\n";
echo "🌐 You can now access the application at: http://localhost:8000\n";
echo "👤 Login with:\n";
echo "   Username: admin\n";
echo "   Password: admin123\n";
?>
