<?php
/**
 * Check Remember Me Token
 * Automatically logs in users with valid remember me tokens
 */

session_start();

// Check if system is installed
if (!file_exists('.installed')) {
    header('Location: install.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Check for remember me token
if (isset($_COOKIE['remember_token'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        try {
            $token = $_COOKIE['remember_token'];
            
            // Find user with valid token
            $stmt = $db->prepare("
                SELECT u.*, c.name as company_name 
                FROM users u 
                LEFT JOIN companies c ON u.company_id = c.id 
                WHERE u.remember_token = ? 
                AND u.remember_token_expires > NOW() 
                AND u.is_active = 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['company_name'] = $user['company_name'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Generate new session ID for security
                session_regenerate_id(true);
                
                // Log the automatic login
                logActivity('auto_login', 'User automatically logged in via remember me token');
                
                // Redirect to dashboard
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                // Invalid or expired token, clear cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        } catch (Exception $e) {
            error_log("Remember me check error: " . $e->getMessage());
            // Clear invalid cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
}

// No valid remember me token, continue to landing page
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=landing.html">
    <title>Redirecting...</title>
</head>
<body>
    <p>Redirecting to login page...</p>
    <script>
        window.location.href = 'landing.html';
    </script>
</body>
</html>
