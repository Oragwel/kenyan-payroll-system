<?php
/**
 * Kenyan Payroll Management System
 * Main entry point for the application
 */

session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'secure_auth.php';

// Simple routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Initialize secure authentication
global $secureAuth;

// Check if user is logged in with enhanced security
if (!isset($_SESSION['user_id']) && $page !== 'auth') {
    header('Location: landing.html');
    exit;
}

// Validate session security for authenticated users
if (isset($_SESSION['user_id']) && !$secureAuth->validateSession()) {
    header('Location: landing.html');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Payroll Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php include 'includes/header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <div class="<?php echo isset($_SESSION['user_id']) ? 'main-content' : 'auth-container'; ?>">
        <?php
        // Include the appropriate page
        $page_file = "pages/{$page}.php";
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            include 'pages/404.php';
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
