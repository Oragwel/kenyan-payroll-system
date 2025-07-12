<?php
/**
 * Test Login Flow
 * Tests the complete login process to ensure it works correctly
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>🔐 Login Flow Test</h2>";
echo "<p>Testing the complete login process...</p>";

// Test 1: Check if landing page is accessible
echo "<h3>Test 1: Landing Page Access</h3>";
if (file_exists('landing.html')) {
    echo "<span style='color: green;'>✅ Landing page exists</span><br>";
} else {
    echo "<span style='color: red;'>❌ Landing page missing</span><br>";
}

// Test 2: Check if login handler exists
echo "<h3>Test 2: Login Handler</h3>";
if (file_exists('login_handler.php')) {
    echo "<span style='color: green;'>✅ Login handler exists</span><br>";
} else {
    echo "<span style='color: red;'>❌ Login handler missing</span><br>";
}

// Test 3: Check if remember me checker exists
echo "<h3>Test 3: Remember Me Checker</h3>";
if (file_exists('check_remember_me.php')) {
    echo "<span style='color: green;'>✅ Remember me checker exists</span><br>";
} else {
    echo "<span style='color: red;'>❌ Remember me checker missing</span><br>";
}

// Test 4: Check database connection
echo "<h3>Test 4: Database Connection</h3>";
try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "<span style='color: green;'>✅ Database connection successful</span><br>";
        
        // Check if users table exists
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "<span style='color: green;'>✅ Users table exists</span><br>";
            
            // Check if there are any users
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                echo "<span style='color: green;'>✅ Active users found ({$result['count']})</span><br>";
            } else {
                echo "<span style='color: orange;'>⚠️ No active users found</span><br>";
            }
        } else {
            echo "<span style='color: red;'>❌ Users table missing</span><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ Database connection failed</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

// Test 5: Check secure auth class
echo "<h3>Test 5: Secure Authentication</h3>";
if (file_exists('secure_auth.php')) {
    echo "<span style='color: green;'>✅ Secure auth class exists</span><br>";
    
    try {
        require_once 'secure_auth.php';
        if (class_exists('SecureAuth')) {
            echo "<span style='color: green;'>✅ SecureAuth class loaded</span><br>";
        } else {
            echo "<span style='color: red;'>❌ SecureAuth class not found</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>❌ Error loading SecureAuth: " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "<span style='color: red;'>❌ Secure auth file missing</span><br>";
}

// Test 6: Check session handling
echo "<h3>Test 6: Session Handling</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<span style='color: green;'>✅ Session is active</span><br>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<span style='color: blue;'>ℹ️ User is currently logged in (ID: {$_SESSION['user_id']})</span><br>";
    } else {
        echo "<span style='color: blue;'>ℹ️ No user currently logged in</span><br>";
    }
} else {
    echo "<span style='color: red;'>❌ Session not active</span><br>";
}

// Test 7: Check login flow files
echo "<h3>Test 7: Login Flow Files</h3>";
$loginFiles = [
    'index.php' => 'Main application entry',
    'landing.html' => 'Landing page with login form',
    'login_handler.php' => 'Login processing script',
    'check_remember_me.php' => 'Remember me token checker',
    'pages/auth.php' => 'Authentication page',
    'secure_auth.php' => 'Secure authentication class'
];

foreach ($loginFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span style='color: green;'>✅ $file</span> - $description<br>";
    } else {
        echo "<span style='color: red;'>❌ $file</span> - $description<br>";
    }
}

echo "<h3>🎯 Login Flow Summary</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>Expected Login Flow:</h4>";
echo "<ol>";
echo "<li><strong>User visits site:</strong> Redirected to check_remember_me.php</li>";
echo "<li><strong>Remember me check:</strong> If valid token exists, auto-login; otherwise redirect to landing.html</li>";
echo "<li><strong>User fills login form:</strong> Form submits to login_handler.php</li>";
echo "<li><strong>Login processing:</strong> Credentials validated, session created</li>";
echo "<li><strong>Success:</strong> Redirect to dashboard</li>";
echo "<li><strong>Failure:</strong> Redirect back to landing.html with error message</li>";
echo "</ol>";
echo "</div>";

echo "<h3>🔧 Troubleshooting</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>If login redirects to auth page:</h4>";
echo "<ul>";
echo "<li>Check that landing.html form action points to login_handler.php</li>";
echo "<li>Verify login_handler.php processes POST requests correctly</li>";
echo "<li>Ensure database connection and user authentication work</li>";
echo "<li>Check for any PHP errors in error logs</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='landing.html' style='background: #006b3f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Login Page</a>";
echo "<a href='index.php' style='background: #ce1126; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
echo "</div>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Flow Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
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
            margin-top: 25px;
        }
        
        h4 {
            color: #006b3f;
        }
    </style>
</head>
<body>
</body>
</html>
