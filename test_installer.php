<?php
/**
 * Test script for the robust database installer
 */

require_once 'installer/DatabaseInstaller.php';

echo "🇰🇪 Kenyan Payroll System - Installer Test\n";
echo "==========================================\n\n";

// Test 1: Check available drivers
echo "1. Available Database Drivers:\n";
$drivers = DatabaseInstaller::getAvailableDrivers();
foreach ($drivers as $type => $name) {
    echo "   ✅ {$name} ({$type})\n";
}
echo "\n";

// Test 2: Test SQLite configuration
echo "2. Testing SQLite Configuration:\n";
$sqliteConfig = [
    'type' => 'sqlite',
    'path' => __DIR__ . '/database/test_kenyan_payroll.sqlite'
];

$installer = new DatabaseInstaller();
$testResult = $installer->testConnection($sqliteConfig);

if ($testResult['success']) {
    echo "   ✅ SQLite connection successful\n";
    echo "   📊 Version: {$testResult['version']}\n";
} else {
    echo "   ❌ SQLite connection failed: {$testResult['error']}\n";
}
echo "\n";

// Test 3: Test MySQL configuration (if available)
if (isset($drivers['mysql'])) {
    echo "3. Testing MySQL Configuration:\n";
    $mysqlConfig = [
        'type' => 'mysql',
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'test_kenyan_payroll',
        'username' => 'root',
        'password' => ''
    ];
    
    $testResult = $installer->testConnection($mysqlConfig);
    
    if ($testResult['success']) {
        echo "   ✅ MySQL connection successful\n";
        echo "   📊 Version: {$testResult['version']}\n";
    } else {
        echo "   ⚠️ MySQL connection failed: {$testResult['error']}\n";
        echo "   (This is normal if MySQL is not installed or configured)\n";
    }
    echo "\n";
}

// Test 4: Test PostgreSQL configuration (if available)
if (isset($drivers['postgresql'])) {
    echo "4. Testing PostgreSQL Configuration:\n";
    $postgresConfig = [
        'type' => 'postgresql',
        'host' => 'localhost',
        'port' => '5432',
        'database' => 'test_kenyan_payroll',
        'username' => 'postgres',
        'password' => ''
    ];
    
    $testResult = $installer->testConnection($postgresConfig);
    
    if ($testResult['success']) {
        echo "   ✅ PostgreSQL connection successful\n";
        echo "   📊 Version: {$testResult['version']}\n";
    } else {
        echo "   ⚠️ PostgreSQL connection failed: {$testResult['error']}\n";
        echo "   (This is normal if PostgreSQL is not installed or configured)\n";
    }
    echo "\n";
}

// Test 5: Test installation process with SQLite
echo "5. Testing Installation Process (SQLite):\n";
$adminUser = [
    'username' => 'test_admin',
    'email' => 'test@example.com',
    'password' => 'test123'
];

$installResult = $installer->install($sqliteConfig, $adminUser);

if ($installResult['success']) {
    echo "   ✅ Installation completed successfully\n";
    echo "   📋 Steps completed:\n";
    foreach ($installResult['steps'] as $step => $result) {
        if (is_array($result) && isset($result['success'])) {
            $status = $result['success'] ? '✅' : '❌';
            echo "      {$status} {$step}\n";
        }
    }
} else {
    echo "   ❌ Installation failed\n";
    if (!empty($installResult['errors'])) {
        foreach ($installResult['errors'] as $error) {
            echo "      ❌ {$error}\n";
        }
    }
}
echo "\n";

// Test 6: Check if installation marker exists
echo "6. Installation Status:\n";
if (DatabaseInstaller::isInstalled()) {
    echo "   ✅ System is marked as installed\n";
} else {
    echo "   ⚠️ System is not marked as installed\n";
}
echo "\n";

echo "==========================================\n";
echo "🎉 Installer test completed!\n";
echo "\n";
echo "Next steps:\n";
echo "1. Run 'php -S localhost:8000' to start the server\n";
echo "2. Visit 'http://localhost:8000/install_new.php' for the web installer\n";
echo "3. Or visit 'http://localhost:8000' if installation is complete\n";
?>
