<?php
/**
 * Robust Database Manager - Supports Multiple Database Types
 * Supports: MySQL, PostgreSQL, SQLite, SQL Server
 */

class DatabaseManager {
    private $config;
    private $conn;
    private $dbType;
    
    // Supported database types
    const MYSQL = 'mysql';
    const POSTGRESQL = 'postgresql';
    const SQLITE = 'sqlite';
    const SQLSERVER = 'sqlserver';
    
    public function __construct($config = null) {
        if ($config) {
            $this->config = $config;
            $this->dbType = $config['type'] ?? self::MYSQL;
        } else {
            $this->loadConfig();
        }
    }
    
    /**
     * Load database configuration from file or environment
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/database_config.php';
        
        if (file_exists($configFile)) {
            $this->config = include $configFile;
            $this->dbType = $this->config['type'] ?? self::MYSQL;
        } else {
            // Default to SQLite for development
            $this->config = [
                'type' => self::SQLITE,
                'path' => __DIR__ . '/../database/kenyan_payroll.sqlite'
            ];
            $this->dbType = self::SQLITE;
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        if ($this->conn) {
            return $this->conn;
        }
        
        try {
            switch ($this->dbType) {
                case self::MYSQL:
                    $this->conn = $this->createMySQLConnection();
                    break;
                    
                case self::POSTGRESQL:
                    $this->conn = $this->createPostgreSQLConnection();
                    break;
                    
                case self::SQLITE:
                    $this->conn = $this->createSQLiteConnection();
                    break;
                    
                case self::SQLSERVER:
                    $this->conn = $this->createSQLServerConnection();
                    break;
                    
                default:
                    throw new Exception("Unsupported database type: {$this->dbType}");
            }
            
            // Set common PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Database-specific configurations
            $this->configureDatabase();
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        return $this->conn;
    }
    
    /**
     * Create MySQL connection
     */
    private function createMySQLConnection() {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $dbname = $this->config['database'] ?? 'kenyan_payroll';
        $username = $this->config['username'] ?? 'root';
        $password = $this->config['password'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        return new PDO($dsn, $username, $password);
    }
    
    /**
     * Create PostgreSQL connection
     */
    private function createPostgreSQLConnection() {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $dbname = $this->config['database'] ?? 'kenyan_payroll';
        $username = $this->config['username'] ?? 'postgres';
        $password = $this->config['password'] ?? '';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        return new PDO($dsn, $username, $password);
    }
    
    /**
     * Create SQLite connection
     */
    private function createSQLiteConnection() {
        $dbPath = $this->config['path'] ?? __DIR__ . '/../database/kenyan_payroll.sqlite';
        
        // Create directory if it doesn't exist
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        $dsn = "sqlite:{$dbPath}";
        return new PDO($dsn);
    }
    
    /**
     * Create SQL Server connection
     */
    private function createSQLServerConnection() {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 1433;
        $dbname = $this->config['database'] ?? 'kenyan_payroll';
        $username = $this->config['username'] ?? 'sa';
        $password = $this->config['password'] ?? '';
        
        $dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
        return new PDO($dsn, $username, $password);
    }
    
    /**
     * Configure database-specific settings
     */
    private function configureDatabase() {
        switch ($this->dbType) {
            case self::SQLITE:
                // Enable foreign key constraints
                $this->conn->exec("PRAGMA foreign_keys = ON");
                $this->conn->exec("PRAGMA journal_mode = WAL");
                break;
                
            case self::MYSQL:
                // Set timezone and SQL mode
                $this->conn->exec("SET time_zone = '+00:00'");
                $this->conn->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
                break;
                
            case self::POSTGRESQL:
                // Set timezone
                $this->conn->exec("SET timezone = 'UTC'");
                break;
        }
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            
            // Test with a simple query
            switch ($this->dbType) {
                case self::MYSQL:
                    $result = $conn->query("SELECT VERSION() as version")->fetch();
                    break;
                case self::POSTGRESQL:
                    $result = $conn->query("SELECT version() as version")->fetch();
                    break;
                case self::SQLITE:
                    $result = $conn->query("SELECT sqlite_version() as version")->fetch();
                    break;
                case self::SQLSERVER:
                    $result = $conn->query("SELECT @@VERSION as version")->fetch();
                    break;
            }
            
            return [
                'success' => true,
                'type' => $this->dbType,
                'version' => $result['version'] ?? 'Unknown',
                'message' => 'Connection successful'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'type' => $this->dbType,
                'error' => $e->getMessage(),
                'message' => 'Connection failed'
            ];
        }
    }
    
    /**
     * Get database type
     */
    public function getDatabaseType() {
        return $this->dbType;
    }
    
    /**
     * Get supported database types
     */
    public static function getSupportedTypes() {
        return [
            self::MYSQL => 'MySQL',
            self::POSTGRESQL => 'PostgreSQL', 
            self::SQLITE => 'SQLite',
            self::SQLSERVER => 'SQL Server'
        ];
    }
    
    /**
     * Check if database type is supported
     */
    public static function isSupported($type) {
        return in_array($type, [self::MYSQL, self::POSTGRESQL, self::SQLITE, self::SQLSERVER]);
    }
    
    /**
     * Save database configuration
     */
    public function saveConfig($config) {
        $configFile = __DIR__ . '/database_config.php';
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        if (file_put_contents($configFile, $configContent)) {
            $this->config = $config;
            $this->dbType = $config['type'];
            $this->conn = null; // Reset connection
            return true;
        }
        
        return false;
    }
}
?>
