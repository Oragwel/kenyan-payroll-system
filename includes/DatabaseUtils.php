<?php
/**
 * Database Utilities for Cross-Database Compatibility
 * Provides helper functions for database-agnostic queries
 */

class DatabaseUtils {
    private static $dbType = 'mysql';
    
    /**
     * Set the database type
     */
    public static function setDatabaseType($type) {
        self::$dbType = $type;
    }
    
    /**
     * Get the current database type
     */
    public static function getDatabaseType() {
        return self::$dbType;
    }
    
    /**
     * Convert boolean values for database compatibility
     */
    public static function boolValue($value) {
        switch (self::$dbType) {
            case 'sqlite':
            case 'mysql':
            case 'postgresql':
                return $value ? 1 : 0;
            case 'sqlserver':
                return $value ? 1 : 0;
            default:
                return $value ? 1 : 0;
        }
    }
    
    /**
     * Get boolean TRUE value for the current database
     */
    public static function trueValue() {
        return self::boolValue(true);
    }
    
    /**
     * Get boolean FALSE value for the current database
     */
    public static function falseValue() {
        return self::boolValue(false);
    }
    
    /**
     * Get date/time that is X hours ago
     */
    public static function hoursAgo($hours) {
        return date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
    }
    
    /**
     * Get date/time that is X days ago
     */
    public static function daysAgo($days) {
        return date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }
    
    /**
     * Get date/time that is X months ago
     */
    public static function monthsAgo($months) {
        return date('Y-m-d H:i:s', strtotime("-{$months} months"));
    }
    
    /**
     * Get current date/time in database format
     */
    public static function now() {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'Y-m-d') {
        return date($format, strtotime($date));
    }
    
    /**
     * Get month/year from date for grouping
     */
    public static function getMonthYear($column) {
        switch (self::$dbType) {
            case 'mysql':
                return "DATE_FORMAT($column, '%Y-%m')";
            case 'postgresql':
                return "TO_CHAR($column, 'YYYY-MM')";
            case 'sqlite':
                return "strftime('%Y-%m', $column)";
            case 'sqlserver':
                return "FORMAT($column, 'yyyy-MM')";
            default:
                return "strftime('%Y-%m', $column)";
        }
    }
    
    /**
     * Get month name from date
     */
    public static function getMonthName($column) {
        switch (self::$dbType) {
            case 'mysql':
                return "DATE_FORMAT($column, '%M %Y')";
            case 'postgresql':
                return "TO_CHAR($column, 'Month YYYY')";
            case 'sqlite':
                return "strftime('%Y-%m', $column)"; // SQLite doesn't have month names
            case 'sqlserver':
                return "FORMAT($column, 'MMMM yyyy')";
            default:
                return "strftime('%Y-%m', $column)";
        }
    }
    
    /**
     * Check if table exists
     */
    public static function tableExists($db, $tableName) {
        try {
            switch (self::$dbType) {
                case 'mysql':
                    $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
                    return $stmt->rowCount() > 0;
                case 'postgresql':
                    $stmt = $db->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
                    $stmt->execute([$tableName]);
                    return $stmt->fetchColumn();
                case 'sqlite':
                    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'");
                    return $stmt->rowCount() > 0;
                case 'sqlserver':
                    $stmt = $db->query("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'");
                    return $stmt->rowCount() > 0;
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get LIMIT clause for pagination
     */
    public static function limitClause($limit, $offset = 0) {
        switch (self::$dbType) {
            case 'mysql':
            case 'postgresql':
            case 'sqlite':
                return "LIMIT $limit OFFSET $offset";
            case 'sqlserver':
                return "OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
            default:
                return "LIMIT $limit OFFSET $offset";
        }
    }
    
    /**
     * Get auto-increment column definition
     */
    public static function autoIncrementColumn() {
        switch (self::$dbType) {
            case 'mysql':
                return 'INT AUTO_INCREMENT PRIMARY KEY';
            case 'postgresql':
                return 'SERIAL PRIMARY KEY';
            case 'sqlite':
                return 'INTEGER PRIMARY KEY AUTOINCREMENT';
            case 'sqlserver':
                return 'INT IDENTITY(1,1) PRIMARY KEY';
            default:
                return 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }
    }
    
    /**
     * Get timestamp column definition
     */
    public static function timestampColumn() {
        switch (self::$dbType) {
            case 'mysql':
                return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
            case 'postgresql':
                return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
            case 'sqlite':
                return 'DATETIME DEFAULT CURRENT_TIMESTAMP';
            case 'sqlserver':
                return 'DATETIME2 DEFAULT GETDATE()';
            default:
                return 'DATETIME DEFAULT CURRENT_TIMESTAMP';
        }
    }
    
    /**
     * Escape string for LIKE queries
     */
    public static function escapeLike($string) {
        return str_replace(['%', '_'], ['\\%', '\\_'], $string);
    }
    
    /**
     * Get CONCAT function for the database
     */
    public static function concat($columns) {
        if (!is_array($columns)) {
            return $columns;
        }
        
        switch (self::$dbType) {
            case 'mysql':
                return 'CONCAT(' . implode(', ', $columns) . ')';
            case 'postgresql':
            case 'sqlite':
                return implode(' || ', $columns);
            case 'sqlserver':
                return implode(' + ', $columns);
            default:
                return implode(' || ', $columns);
        }
    }
    
    /**
     * Replace MySQL-specific functions in SQL queries
     */
    public static function replaceMySQLFunctions($sql) {
        if (self::$dbType === 'sqlite') {
            // Replace CONCAT with SQLite concatenation
            $sql = preg_replace_callback(
                '/CONCAT\s*\(\s*([^)]+)\s*\)/i',
                function($matches) {
                    $args = explode(',', $matches[1]);
                    $args = array_map('trim', $args);
                    return implode(' || ', $args);
                },
                $sql
            );

            // Replace other MySQL functions as needed
            $sql = str_ireplace('NOW()', "datetime('now')", $sql);
            $sql = str_ireplace('CURDATE()', "date('now')", $sql);
        }

        return $sql;
    }

    /**
     * Prepare a statement with automatic MySQL function replacement
     */
    public static function prepare($db, $sql) {
        $sql = self::replaceMySQLFunctions($sql);
        return $db->prepare($sql);
    }

    /**
     * Initialize database utilities with the current database type
     */
    public static function initialize($databaseInstance = null) {
        global $database;

        $dbInstance = $databaseInstance ?: $database;
        
        if ($dbInstance && method_exists($dbInstance, 'getDatabaseType')) {
            self::setDatabaseType($dbInstance->getDatabaseType());
        }
    }
}

// Auto-initialize if global database is available
if (isset($database) && method_exists($database, 'getDatabaseType')) {
    DatabaseUtils::initialize($database);
}
?>
