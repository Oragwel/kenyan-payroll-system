<?php
/**
 * Enhanced Secure Authentication System
 * Includes rate limiting, session security, and audit logging
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/functions.php';

// Initialize database connection (only if not in installation mode)
$db = null;
$secureAuth = null;

if (file_exists('.installed') || (isset($_GET['page']) && $_GET['page'] === 'auth')) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            $secureAuth = new SecureAuth($db);
        }
    } catch (Exception $e) {
        // Database not available during installation
        error_log("Database connection failed during initialization: " . $e->getMessage());
    }
}

class SecureAuth {
    private $db;
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    
    public function __construct($database) {
        $this->db = $database;

        // Only initialize tables if database connection is available
        if ($this->db) {
            $this->initializeSecurityTables();
        }
    }
    
    /**
     * Initialize security-related database tables
     */
    private function initializeSecurityTables() {
        // Skip if no database connection
        if (!$this->db) {
            return;
        }

        try {
            // Create login attempts table if not exists
            $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(100),
                success BOOLEAN DEFAULT FALSE,
                user_agent TEXT,
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_username_time (username, attempt_time)
            )";
            $this->db->exec($sql);

            // Create security logs table if not exists
            $sql = "CREATE TABLE IF NOT EXISTS security_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                event_type VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )";
            $this->db->exec($sql);

        } catch (Exception $e) {
            // Log error but don't fail completely
            error_log("Failed to initialize security tables: " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP is currently locked out
     */
    public function isLockedOut($ipAddress) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as failed_attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ipAddress, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['failed_attempts'] >= $this->maxAttempts;
    }
    
    /**
     * Get remaining lockout time
     */
    public function getLockoutTimeRemaining($ipAddress) {
        $stmt = $this->db->prepare("
            SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(attempt_time), INTERVAL ? SECOND)) as remaining
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$this->lockoutTime, $ipAddress, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return max(0, $result['remaining'] ?? 0);
    }
    
    /**
     * Record login attempt
     */
    public function recordAttempt($ipAddress, $username, $success, $userAgent = '') {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, username, success, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $ipAddress,
            $username,
            (int) $success, // 👈 always cast before SQL insert
            $userAgent
        ]);
    }
    
    
    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $action, $details = '', $severity = 'medium') {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->db->prepare("
            INSERT INTO security_logs (user_id, action, ip_address, user_agent, details, severity) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $ipAddress, $userAgent, $details, $severity]);
    }
    
    /**
     * Authenticate user with enhanced security
     */
    public function authenticate($username, $password) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check if IP is locked out
        if ($this->isLockedOut($ipAddress)) {
            $remaining = $this->getLockoutTimeRemaining($ipAddress);
            $this->logSecurityEvent(null, 'blocked_login_attempt', 
                "IP $ipAddress blocked, $remaining seconds remaining", 'high');
            return [
                'success' => false,
                'message' => "Too many failed attempts. Please try again in " . ceil($remaining / 60) . " minutes.",
                'lockout' => true
            ];
        }
        
        // Validate input
        if (empty($username) || empty($password)) {
            $this->recordAttempt($ipAddress, $username, false, $userAgent);
            return [
                'success' => false,
                'message' => 'Username and password are required'
            ];
        }
        
        // Get user from database
        $stmt = $this->db->prepare("
            SELECT u.*, e.id as employee_id, e.company_id 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            $this->recordAttempt($ipAddress, $username, false, $userAgent);
            $this->logSecurityEvent($user['id'] ?? null, 'failed_login', 
                "Failed login for username: $username from IP: $ipAddress", 'medium');
            
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
        
        // Successful login
        $this->recordAttempt($ipAddress, $username, true, $userAgent);
        $this->logSecurityEvent($user['id'], 'successful_login', 
            "Successful login from IP: $ipAddress", 'low');
        
        // Set secure session
        $this->setSecureSession($user);
        
        return [
            'success' => true,
            'user' => $user,
            'message' => 'Login successful'
        ];
    }
    
    /**
     * Set secure session with additional security measures
     */
    private function setSecureSession($user) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Set session security token
        $_SESSION['security_token'] = bin2hex(random_bytes(32));
        
        // Set session timeout (8 hours)
        $_SESSION['expires'] = time() + (8 * 60 * 60);
    }
    
    /**
     * Validate session security
     */
    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['expires']) && time() > $_SESSION['expires']) {
            $this->logSecurityEvent($_SESSION['user_id'], 'session_timeout', 
                'Session expired due to timeout', 'low');
            $this->destroySession();
            return false;
        }
        
        // Check IP address consistency (optional - can be disabled for mobile users)
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIp) {
            $this->logSecurityEvent($_SESSION['user_id'], 'ip_change_detected', 
                "IP changed from {$_SESSION['ip_address']} to $currentIp", 'medium');
            // Uncomment to enforce IP consistency
            // $this->destroySession();
            // return false;
        }
        
        // Extend session if user is active
        $_SESSION['expires'] = time() + (8 * 60 * 60);
        
        return true;
    }
    
    /**
     * Secure logout
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logSecurityEvent($_SESSION['user_id'], 'logout', 
                'User logged out', 'low');
        }
        
        $this->destroySession();
    }
    
    /**
     * Destroy session securely
     */
    private function destroySession() {
        // Clear all session variables
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Clean old login attempts and logs
     */
    public function cleanOldRecords() {
        // Clean login attempts older than 24 hours
        $this->db->exec("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Clean security logs older than 90 days
        $this->db->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
}

// Initialize secure authentication
$secureAuth = new SecureAuth($db);

// Handle authentication requests
$action = $_GET['action'] ?? 'show_login';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    $result = $secureAuth->authenticate($username, $password);
    
    if ($result['success']) {
        header('Location: index.php?page=dashboard');
        exit;
    } else {
        $message = $result['message'];
        $messageType = $result['lockout'] ?? false ? 'warning' : 'danger';
    }
}

// Handle logout
if ($action === 'logout') {
    $secureAuth->logout();
    header('Location: landing.html');
    exit;
}

// Clean old records periodically
if (rand(1, 100) === 1) {
    $secureAuth->cleanOldRecords();
}
?>
