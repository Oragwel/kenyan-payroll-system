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

// Initialize database connection (only if system is installed)
$db = null;
$secureAuth = null;

if (file_exists('config/installed.txt')) {
    try {
        // Use the robust database manager
        $db = $database->getConnection();

        if ($db) {
            $secureAuth = new SecureAuth($db, $database->getDatabaseType());
        }
    } catch (Exception $e) {
        // Database not available during installation
        error_log("Database connection failed during initialization: " . $e->getMessage());
    }
}

class SecureAuth {
    private $db;
    private $dbType;
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    private $lockoutDuration = 900; // 15 minutes in seconds

    public function __construct($database, $databaseType = 'mysql') {
        $this->db = $database;
        $this->dbType = $databaseType;

        // Only initialize tables if database connection is available
        if ($this->db) {
            $this->initializeSecurityTables();
        }
    }
    
    /**
     * Get database-specific data types
     */
    private function getDataType($type) {
        switch ($this->dbType) {
            case 'mysql':
                switch ($type) {
                    case 'id': return 'INT PRIMARY KEY AUTO_INCREMENT';
                    case 'varchar': return 'VARCHAR';
                    case 'text': return 'TEXT';
                    case 'boolean': return 'BOOLEAN';
                    case 'timestamp': return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
                    default: return $type;
                }
            case 'postgresql':
                switch ($type) {
                    case 'id': return 'SERIAL PRIMARY KEY';
                    case 'varchar': return 'VARCHAR';
                    case 'text': return 'TEXT';
                    case 'boolean': return 'BOOLEAN';
                    case 'timestamp': return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
                    default: return $type;
                }
            case 'sqlite':
                switch ($type) {
                    case 'id': return 'INTEGER PRIMARY KEY AUTOINCREMENT';
                    case 'varchar': return 'VARCHAR';
                    case 'text': return 'TEXT';
                    case 'boolean': return 'BOOLEAN';
                    case 'timestamp': return 'DATETIME DEFAULT CURRENT_TIMESTAMP';
                    default: return $type;
                }
            case 'sqlserver':
                switch ($type) {
                    case 'id': return 'INT IDENTITY(1,1) PRIMARY KEY';
                    case 'varchar': return 'NVARCHAR';
                    case 'text': return 'NTEXT';
                    case 'boolean': return 'BIT';
                    case 'timestamp': return 'DATETIME2 DEFAULT GETDATE()';
                    default: return $type;
                }
            default:
                return $type;
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
                id " . $this->getDataType('id') . ",
                ip_address " . $this->getDataType('varchar') . "(45) NOT NULL,
                username " . $this->getDataType('varchar') . "(100),
                success " . $this->getDataType('boolean') . " DEFAULT 0,
                user_agent " . $this->getDataType('text') . ",
                attempt_time " . $this->getDataType('timestamp') . "
            )";
            $this->db->exec($sql);

            // Create security logs table if not exists
            $sql = "CREATE TABLE IF NOT EXISTS security_logs (
                id " . $this->getDataType('id') . ",
                user_id INTEGER,
                event_type " . $this->getDataType('varchar') . "(100) NOT NULL,
                description " . $this->getDataType('text') . ",
                ip_address " . $this->getDataType('varchar') . "(45),
                user_agent " . $this->getDataType('text') . ",
                severity " . $this->getDataType('varchar') . "(20) DEFAULT 'medium',
                created_at " . $this->getDataType('timestamp') . "
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
        // Calculate the time threshold (current time minus lockout duration)
        $timeThreshold = date('Y-m-d H:i:s', time() - $this->lockoutDuration);

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as failed_attempts
            FROM login_attempts
            WHERE ip_address = ?
            AND success = 0
            AND attempt_time > ?
        ");
        $stmt->execute([$ipAddress, $timeThreshold]);
        $result = $stmt->fetch();
        
        return $result['failed_attempts'] >= $this->maxAttempts;
    }
    
    /**
     * Get remaining lockout time
     */
    public function getLockoutTimeRemaining($ipAddress) {
        // Calculate the time threshold (current time minus lockout duration)
        $timeThreshold = date('Y-m-d H:i:s', time() - $this->lockoutDuration);

        $stmt = $this->db->prepare("
            SELECT attempt_time
            FROM login_attempts
            WHERE ip_address = ?
            AND success = 0
            AND attempt_time > ?
            ORDER BY attempt_time DESC
            LIMIT 1
        ");
        $stmt->execute([$ipAddress, $timeThreshold]);
        $result = $stmt->fetch();

        if ($result && $result['attempt_time']) {
            $lastAttemptTime = strtotime($result['attempt_time']);
            $lockoutEndTime = $lastAttemptTime + $this->lockoutDuration;
            $remaining = $lockoutEndTime - time();
            return max(0, $remaining);
        }

        return 0;
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
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $this->db->prepare("
                INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, description, severity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $ipAddress, $userAgent, $details, $severity]);
        } catch (Exception $e) {
            // Log error but don't fail the authentication process
            error_log("Failed to log security event: " . $e->getMessage());
        }
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
        
        if (!$user || !verifyPassword($password, $user['password'] ?? '')) {
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
        $cutoffTime24h = date('Y-m-d H:i:s', time() - (24 * 60 * 60));
        $this->db->exec("DELETE FROM login_attempts WHERE attempt_time < '$cutoffTime24h'");

        // Clean security logs older than 90 days
        $cutoffTime90d = date('Y-m-d H:i:s', time() - (90 * 24 * 60 * 60));
        $this->db->exec("DELETE FROM security_logs WHERE created_at < '$cutoffTime90d'");
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
