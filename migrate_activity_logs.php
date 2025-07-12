<?php
/**
 * Database Migration: Add Activity Logs Table
 * 
 * This script adds the missing activity_logs table to existing installations
 * to fix the login error and enable activity logging functionality.
 */

session_start();
require_once 'config/database.php';

// Check if database config exists
if (!file_exists('config/database.php')) {
    die('Database configuration not found. Please run the installer first.');
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed. Please check your database configuration.');
}

echo "<h2>🔧 Activity Logs Table Migration</h2>";
echo "<p>Adding missing activity_logs table to fix login functionality...</p>";

try {
    // Check if activity_logs table already exists
    $stmt = $db->query("SHOW TABLES LIKE 'activity_logs'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<div style='color: green;'>✅ Activity logs table already exists. No migration needed.</div>";
    } else {
        echo "<p>Creating activity_logs table...</p>";
        
        // Create activity_logs table
        $createTableSQL = "
            CREATE TABLE activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        $db->exec($createTableSQL);
        
        echo "<div style='color: green;'>✅ Activity logs table created successfully!</div>";
        
        // Test the table by inserting a migration log entry
        try {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                null, 
                'migration', 
                'Activity logs table created via migration script', 
                $_SERVER['REMOTE_ADDR'] ?? 'localhost'
            ]);
            
            echo "<div style='color: green;'>✅ Activity logging functionality verified!</div>";
        } catch (Exception $e) {
            echo "<div style='color: orange;'>⚠️ Table created but test insert failed: " . $e->getMessage() . "</div>";
        }
        
        echo "<div style='color: green; font-weight: bold; margin-top: 20px;'>🎉 Migration completed successfully!</div>";
        echo "<p>The login functionality should now work properly without errors.</p>";
    }
    
    // Show table structure
    echo "<h3>📋 Activity Logs Table Structure</h3>";
    $stmt = $db->query("DESCRIBE activity_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #006b3f; color: white;'>";
    echo "<th style='padding: 10px;'>Field</th>";
    echo "<th style='padding: 10px;'>Type</th>";
    echo "<th style='padding: 10px;'>Null</th>";
    echo "<th style='padding: 10px;'>Key</th>";
    echo "<th style='padding: 10px;'>Default</th>";
    echo "<th style='padding: 10px;'>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$column['Field']}</td>";
        echo "<td style='padding: 8px;'>{$column['Type']}</td>";
        echo "<td style='padding: 8px;'>{$column['Null']}</td>";
        echo "<td style='padding: 8px;'>{$column['Key']}</td>";
        echo "<td style='padding: 8px;'>{$column['Default']}</td>";
        echo "<td style='padding: 8px;'>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show recent activity logs if any exist
    echo "<h3>📊 Recent Activity Logs</h3>";
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($logs)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #006b3f; color: white;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>User ID</th>";
        echo "<th style='padding: 10px;'>Action</th>";
        echo "<th style='padding: 10px;'>Description</th>";
        echo "<th style='padding: 10px;'>IP Address</th>";
        echo "<th style='padding: 10px;'>Created At</th>";
        echo "</tr>";
        
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$log['id']}</td>";
            echo "<td style='padding: 8px;'>" . ($log['user_id'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>{$log['action']}</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($log['description']) . "</td>";
            echo "<td style='padding: 8px;'>{$log['ip_address']}</td>";
            echo "<td style='padding: 8px;'>{$log['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No activity logs found yet. Logs will appear here after user activities.</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Migration failed: " . $e->getMessage() . "</div>";
    echo "<p>Please check your database permissions and try again.</p>";
    
    // Show detailed error information
    echo "<h3>🔍 Error Details</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<br><a href='landing.html' style='background: #006b3f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Login</a>";
echo "<a href='index.php' style='background: #ce1126; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
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
        
        p {
            line-height: 1.6;
        }
        
        div {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        
        table {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            font-weight: bold;
            text-align: center;
        }
        
        td {
            text-align: center;
        }
    </style>
</head>
<body>
</body>
</html>
