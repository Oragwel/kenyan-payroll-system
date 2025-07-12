<?php
/**
 * Dedicated Login Handler
 * Processes login requests from the landing page without showing another login form
 */

session_start();

// Check if system is installed
if (!file_exists('.installed')) {
    header('Location: install.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'secure_auth.php';

// Initialize database and auth
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    header('Location: landing.html?error=' . urlencode('Database connection failed. Please check system configuration.'));
    exit;
}

$secureAuth = new SecureAuth($db);

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: landing.html');
    exit;
}

// Get form data
$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember_me']);

// Validate input
if (empty($username) || empty($password)) {
    // Redirect back to landing with error
    header('Location: landing.html?error=' . urlencode('Please fill in all fields'));
    exit;
}

// Attempt authentication
$result = $secureAuth->authenticate($username, $password);

if ($result['success']) {
    // Set remember me cookie if requested
    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database
        try {
            $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
            $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $_SESSION['user_id']]);
            
            // Set cookie
            setcookie('remember_token', $token, $expiry, '/', '', true, true);
        } catch (Exception $e) {
            // Continue without remember me if there's an error
            error_log("Remember me token error: " . $e->getMessage());
        }
    }
    
    // Log successful login
    logActivity('login', 'User logged in successfully from landing page');
    
    // Redirect to dashboard
    header('Location: index.php?page=dashboard');
    exit;
    
} else {
    // Authentication failed
    $errorMessage = $result['message'];
    $isLockout = $result['lockout'] ?? false;
    
    // Log failed login attempt
    logActivity('login_failed', "Failed login attempt for username: $username");
    
    // Redirect back to landing with error
    $errorParam = $isLockout ? 'lockout' : 'invalid';
    header('Location: landing.html?error=' . urlencode($errorMessage) . '&type=' . $errorParam);
    exit;
}
?>
