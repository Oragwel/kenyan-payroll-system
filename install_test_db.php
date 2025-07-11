<?php
/**
 * Database Connection Test for Installer
 */

header('Content-Type: application/json');

try {
    $host = $_POST['host'] ?? 'localhost';
    $port = $_POST['port'] ?? '3306';
    $database = $_POST['database'] ?? 'kenyan_payroll';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    
    // Test basic connection first
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->execute([$database]);
    $dbExists = $stmt->fetch();
    
    // Get MySQL version
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    
    // Check privileges
    $privileges = [];
    try {
        $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $privileges[] = $row[0];
        }
    } catch (Exception $e) {
        $privileges = ['Could not check privileges'];
    }
    
    $canCreateDB = false;
    foreach ($privileges as $privilege) {
        if (strpos($privilege, 'ALL PRIVILEGES') !== false || 
            strpos($privilege, 'CREATE') !== false) {
            $canCreateDB = true;
            break;
        }
    }
    
    $message = "✅ Successfully connected to MySQL server!\n";
    $message .= "📊 MySQL Version: {$version}\n";
    $message .= "🗄️ Database '{$database}': " . ($dbExists ? "Already exists" : "Will be created") . "\n";
    $message .= "🔑 Create Permission: " . ($canCreateDB ? "Available" : "Limited - may need manual database creation");
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => [
            'mysql_version' => $version,
            'database_exists' => (bool)$dbExists,
            'can_create_db' => $canCreateDB,
            'privileges' => $privileges
        ]
    ]);
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    
    // Provide helpful error messages
    if (strpos($errorMessage, 'Connection refused') !== false) {
        $helpMessage = "❌ Cannot connect to MySQL server. Please check:\n";
        $helpMessage .= "• Is MySQL server running?\n";
        $helpMessage .= "• Is the host and port correct?\n";
        $helpMessage .= "• For XAMPP: Start MySQL in Control Panel\n";
        $helpMessage .= "• For MAMP: Check if port is 8889";
    } elseif (strpos($errorMessage, 'Access denied') !== false) {
        $helpMessage = "❌ Access denied. Please check:\n";
        $helpMessage .= "• Username and password are correct\n";
        $helpMessage .= "• User has sufficient privileges\n";
        $helpMessage .= "• For XAMPP: Usually 'root' with no password\n";
        $helpMessage .= "• For MAMP: Usually 'root' / 'root'";
    } elseif (strpos($errorMessage, 'Unknown database') !== false) {
        $helpMessage = "ℹ️ Database doesn't exist yet - this is normal!\n";
        $helpMessage .= "The installer will create it automatically.";
    } else {
        $helpMessage = "❌ Database connection error:\n" . $errorMessage;
    }
    
    echo json_encode([
        'success' => false,
        'message' => $helpMessage,
        'error' => $errorMessage
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "❌ Unexpected error: " . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
