<?php
/**
 * Clean Installation Script
 * 
 * This script removes installation files and resets the system
 * for a fresh installation.
 */

echo "<h2>🧹 Clean Installation</h2>";
echo "<p>Removing installation files for fresh setup...</p>";

$filesToRemove = [
    '.installed',
    'config/database.php'
];

$cleaned = [];
$errors = [];

foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            $cleaned[] = $file;
            echo "<span style='color: green;'>✅ Removed: $file</span><br>";
        } else {
            $errors[] = $file;
            echo "<span style='color: red;'>❌ Failed to remove: $file</span><br>";
        }
    } else {
        echo "<span style='color: gray;'>ℹ️ Not found: $file</span><br>";
    }
}

// Clear session data
session_start();
session_destroy();
echo "<span style='color: green;'>✅ Cleared session data</span><br>";

echo "<br>";

if (empty($errors)) {
    echo "<div style='color: green; font-weight: bold;'>🎉 System cleaned successfully!</div>";
    echo "<p>You can now run a fresh installation.</p>";
    echo "<br><a href='install.php' style='background: #006b3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Start Fresh Installation</a>";
} else {
    echo "<div style='color: red; font-weight: bold;'>⚠️ Some files could not be removed:</div>";
    foreach ($errors as $error) {
        echo "<p style='color: red;'>- $error</p>";
    }
    echo "<p>Please remove these files manually and try again.</p>";
}

echo "<br><br><a href='index.php' style='background: #ce1126; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to System</a>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Clean Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        h2 {
            color: #006b3f;
            border-bottom: 3px solid #ce1126;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
</body>
</html>
