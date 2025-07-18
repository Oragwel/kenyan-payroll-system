<?php
/**
 * Database Schema Manager - Handles different SQL dialects
 */

require_once 'DatabaseManager.php';

class SchemaManager {
    private $dbManager;
    private $dbType;
    
    public function __construct(DatabaseManager $dbManager) {
        $this->dbManager = $dbManager;
        $this->dbType = $dbManager->getDatabaseType();
    }
    
    /**
     * Create all database tables
     */
    public function createTables() {
        $tables = [
            'companies' => $this->getCompaniesTableSQL(),
            'users' => $this->getUsersTableSQL(),
            'departments' => $this->getDepartmentsTableSQL(),
            'job_positions' => $this->getJobPositionsTableSQL(),
            'employees' => $this->getEmployeesTableSQL(),
            'allowances' => $this->getAllowancesTableSQL(),
            'deductions' => $this->getDeductionsTableSQL(),
            'leave_types' => $this->getLeaveTypesTableSQL(),
            'leave_applications' => $this->getLeaveApplicationsTableSQL(),
            'attendance' => $this->getAttendanceTableSQL(),
            'payroll_periods' => $this->getPayrollPeriodsTableSQL(),
            'payroll_records' => $this->getPayrollRecordsTableSQL(),
            'employee_allowances' => $this->getEmployeeAllowancesTableSQL(),
            'employee_deductions' => $this->getEmployeeDeductionsTableSQL()
        ];
        
        $conn = $this->dbManager->getConnection();
        $results = [];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $conn->exec($sql);
                $results[$tableName] = ['success' => true, 'message' => 'Created successfully'];
            } catch (Exception $e) {
                $results[$tableName] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * Get data type based on database type
     */
    private function getDataType($type, $length = null) {
        switch ($this->dbType) {
            case DatabaseManager::MYSQL:
                return $this->getMySQLDataType($type, $length);
            case DatabaseManager::POSTGRESQL:
                return $this->getPostgreSQLDataType($type, $length);
            case DatabaseManager::SQLITE:
                return $this->getSQLiteDataType($type, $length);
            case DatabaseManager::SQLSERVER:
                return $this->getSQLServerDataType($type, $length);
            default:
                return $type;
        }
    }
    
    private function getMySQLDataType($type, $length) {
        switch ($type) {
            case 'id': return 'INT AUTO_INCREMENT PRIMARY KEY';
            case 'string': return 'VARCHAR(' . ($length ?? 255) . ')';
            case 'text': return 'TEXT';
            case 'integer': return 'INT';
            case 'decimal': return 'DECIMAL(12,2)';
            case 'boolean': return 'BOOLEAN';
            case 'date': return 'DATE';
            case 'datetime': return 'DATETIME';
            case 'timestamp': return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
            default: return $type;
        }
    }
    
    private function getPostgreSQLDataType($type, $length) {
        switch ($type) {
            case 'id': return 'SERIAL PRIMARY KEY';
            case 'string': return 'VARCHAR(' . ($length ?? 255) . ')';
            case 'text': return 'TEXT';
            case 'integer': return 'INTEGER';
            case 'decimal': return 'DECIMAL(12,2)';
            case 'boolean': return 'BOOLEAN';
            case 'date': return 'DATE';
            case 'datetime': return 'TIMESTAMP';
            case 'timestamp': return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
            default: return $type;
        }
    }
    
    private function getSQLiteDataType($type, $length) {
        switch ($type) {
            case 'id': return 'INTEGER PRIMARY KEY AUTOINCREMENT';
            case 'string': return 'VARCHAR(' . ($length ?? 255) . ')';
            case 'text': return 'TEXT';
            case 'integer': return 'INTEGER';
            case 'decimal': return 'DECIMAL(12,2)';
            case 'boolean': return 'BOOLEAN';
            case 'date': return 'DATE';
            case 'datetime': return 'DATETIME';
            case 'timestamp': return 'DATETIME DEFAULT CURRENT_TIMESTAMP';
            default: return $type;
        }
    }
    
    private function getSQLServerDataType($type, $length) {
        switch ($type) {
            case 'id': return 'INT IDENTITY(1,1) PRIMARY KEY';
            case 'string': return 'NVARCHAR(' . ($length ?? 255) . ')';
            case 'text': return 'NTEXT';
            case 'integer': return 'INT';
            case 'decimal': return 'DECIMAL(12,2)';
            case 'boolean': return 'BIT';
            case 'date': return 'DATE';
            case 'datetime': return 'DATETIME2';
            case 'timestamp': return 'DATETIME2 DEFAULT GETDATE()';
            default: return $type;
        }
    }
    
    /**
     * Get foreign key constraint syntax
     */
    private function getForeignKey($column, $refTable, $refColumn = 'id') {
        switch ($this->dbType) {
            case DatabaseManager::SQLITE:
                return "FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn})";
            default:
                return "FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) ON DELETE CASCADE";
        }
    }
    
    /**
     * Companies table SQL
     */
    private function getCompaniesTableSQL() {
        return "CREATE TABLE IF NOT EXISTS companies (
            id " . $this->getDataType('id') . ",
            name " . $this->getDataType('string') . " NOT NULL,
            address " . $this->getDataType('text') . ",
            phone " . $this->getDataType('string', 20) . ",
            email " . $this->getDataType('string', 100) . ",
            website " . $this->getDataType('string', 100) . ",
            kra_pin " . $this->getDataType('string', 20) . ",
            business_registration " . $this->getDataType('string', 50) . ",
            created_at " . $this->getDataType('timestamp') . ",
            updated_at " . $this->getDataType('timestamp') . "
        )";
    }
    
    /**
     * Users table SQL
     */
    private function getUsersTableSQL() {
        return "CREATE TABLE IF NOT EXISTS users (
            id " . $this->getDataType('id') . ",
            username " . $this->getDataType('string', 50) . " UNIQUE NOT NULL,
            email " . $this->getDataType('string', 100) . " UNIQUE NOT NULL,
            password " . $this->getDataType('string') . " NOT NULL,
            role " . $this->getDataType('string', 20) . " DEFAULT 'employee',
            company_id " . $this->getDataType('integer') . ",
            employee_id " . $this->getDataType('integer') . ",
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            last_login " . $this->getDataType('datetime') . ",
            failed_login_attempts " . $this->getDataType('integer') . " DEFAULT 0,
            locked_until " . $this->getDataType('datetime') . ",
            created_at " . $this->getDataType('timestamp') . ",
            updated_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }
    
    /**
     * Departments table SQL
     */
    private function getDepartmentsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS departments (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            name " . $this->getDataType('string', 100) . " NOT NULL,
            description " . $this->getDataType('text') . ",
            manager_id " . $this->getDataType('integer') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }
    
    /**
     * Job positions table SQL
     */
    private function getJobPositionsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS job_positions (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            title " . $this->getDataType('string', 100) . " NOT NULL,
            description " . $this->getDataType('text') . ",
            department_id " . $this->getDataType('integer') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . ",
            " . $this->getForeignKey('department_id', 'departments') . "
        )";
    }
    
    /**
     * Employees table SQL
     */
    private function getEmployeesTableSQL() {
        return "CREATE TABLE IF NOT EXISTS employees (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            employee_number " . $this->getDataType('string', 20) . " UNIQUE NOT NULL,
            first_name " . $this->getDataType('string', 50) . " NOT NULL,
            last_name " . $this->getDataType('string', 50) . " NOT NULL,
            email " . $this->getDataType('string', 100) . ",
            phone " . $this->getDataType('string', 20) . ",
            id_number " . $this->getDataType('string', 20) . ",
            date_of_birth " . $this->getDataType('date') . ",
            gender " . $this->getDataType('string', 10) . ",
            address " . $this->getDataType('text') . ",
            department_id " . $this->getDataType('integer') . ",
            position_id " . $this->getDataType('integer') . ",
            hire_date " . $this->getDataType('date') . ",
            contract_type " . $this->getDataType('string', 20) . " DEFAULT 'permanent',
            employment_status " . $this->getDataType('string', 20) . " DEFAULT 'active',
            basic_salary " . $this->getDataType('decimal') . " DEFAULT 0,
            bank_name " . $this->getDataType('string', 100) . ",
            bank_account " . $this->getDataType('string', 50) . ",
            bank_code " . $this->getDataType('string', 20) . ",
            created_at " . $this->getDataType('timestamp') . ",
            updated_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . ",
            " . $this->getForeignKey('department_id', 'departments') . ",
            " . $this->getForeignKey('position_id', 'job_positions') . "
        )";
    }

    /**
     * Allowances table SQL
     */
    private function getAllowancesTableSQL() {
        return "CREATE TABLE IF NOT EXISTS allowances (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            name " . $this->getDataType('string', 100) . " NOT NULL,
            description " . $this->getDataType('text') . ",
            amount " . $this->getDataType('decimal') . " DEFAULT 0,
            is_percentage " . $this->getDataType('boolean') . " DEFAULT 0,
            is_taxable " . $this->getDataType('boolean') . " DEFAULT 1,
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }

    /**
     * Deductions table SQL
     */
    private function getDeductionsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS deductions (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            name " . $this->getDataType('string', 100) . " NOT NULL,
            description " . $this->getDataType('text') . ",
            amount " . $this->getDataType('decimal') . " DEFAULT 0,
            is_percentage " . $this->getDataType('boolean') . " DEFAULT 0,
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }

    /**
     * Leave types table SQL
     */
    private function getLeaveTypesTableSQL() {
        return "CREATE TABLE IF NOT EXISTS leave_types (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            name " . $this->getDataType('string', 100) . " NOT NULL,
            description " . $this->getDataType('text') . ",
            days_allowed " . $this->getDataType('integer') . " DEFAULT 0,
            is_paid " . $this->getDataType('boolean') . " DEFAULT 1,
            carry_forward " . $this->getDataType('boolean') . " DEFAULT 0,
            max_carry_forward " . $this->getDataType('integer') . " DEFAULT 0,
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            created_at " . $this->getDataType('timestamp') . ",
            updated_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }

    /**
     * Leave applications table SQL
     */
    private function getLeaveApplicationsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS leave_applications (
            id " . $this->getDataType('id') . ",
            employee_id " . $this->getDataType('integer') . " NOT NULL,
            leave_type_id " . $this->getDataType('integer') . " NOT NULL,
            start_date " . $this->getDataType('date') . " NOT NULL,
            end_date " . $this->getDataType('date') . " NOT NULL,
            days_requested " . $this->getDataType('integer') . " NOT NULL,
            reason " . $this->getDataType('text') . ",
            status " . $this->getDataType('string', 20) . " DEFAULT 'pending',
            approved_by " . $this->getDataType('integer') . ",
            approved_at " . $this->getDataType('datetime') . ",
            comments " . $this->getDataType('text') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('employee_id', 'employees') . ",
            " . $this->getForeignKey('leave_type_id', 'leave_types') . ",
            " . $this->getForeignKey('approved_by', 'users') . "
        )";
    }

    /**
     * Attendance table SQL
     */
    private function getAttendanceTableSQL() {
        return "CREATE TABLE IF NOT EXISTS attendance (
            id " . $this->getDataType('id') . ",
            employee_id " . $this->getDataType('integer') . " NOT NULL,
            date " . $this->getDataType('date') . " NOT NULL,
            time_in " . $this->getDataType('datetime') . ",
            time_out " . $this->getDataType('datetime') . ",
            break_time " . $this->getDataType('integer') . " DEFAULT 0,
            overtime_hours " . $this->getDataType('decimal') . " DEFAULT 0,
            status " . $this->getDataType('string', 20) . " DEFAULT 'present',
            notes " . $this->getDataType('text') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('employee_id', 'employees') . "
        )";
    }

    /**
     * Payroll periods table SQL
     */
    private function getPayrollPeriodsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS payroll_periods (
            id " . $this->getDataType('id') . ",
            company_id " . $this->getDataType('integer') . " NOT NULL,
            period_name " . $this->getDataType('string', 100) . " NOT NULL,
            start_date " . $this->getDataType('date') . " NOT NULL,
            end_date " . $this->getDataType('date') . " NOT NULL,
            pay_date " . $this->getDataType('date') . " NOT NULL,
            status " . $this->getDataType('string', 20) . " DEFAULT 'draft',
            created_by " . $this->getDataType('integer') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('company_id', 'companies') . ",
            " . $this->getForeignKey('created_by', 'users') . "
        )";
    }

    /**
     * Payroll records table SQL
     */
    private function getPayrollRecordsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS payroll_records (
            id " . $this->getDataType('id') . ",
            employee_id " . $this->getDataType('integer') . " NOT NULL,
            payroll_period_id " . $this->getDataType('integer') . " NOT NULL,
            basic_salary " . $this->getDataType('decimal') . " DEFAULT 0,
            gross_pay " . $this->getDataType('decimal') . " DEFAULT 0,
            taxable_income " . $this->getDataType('decimal') . " DEFAULT 0,
            paye_tax " . $this->getDataType('decimal') . " DEFAULT 0,
            nssf_deduction " . $this->getDataType('decimal') . " DEFAULT 0,
            nhif_deduction " . $this->getDataType('decimal') . " DEFAULT 0,
            housing_levy " . $this->getDataType('decimal') . " DEFAULT 0,
            total_allowances " . $this->getDataType('decimal') . " DEFAULT 0,
            total_deductions " . $this->getDataType('decimal') . " DEFAULT 0,
            net_pay " . $this->getDataType('decimal') . " DEFAULT 0,
            days_worked " . $this->getDataType('integer') . " DEFAULT 30,
            overtime_hours " . $this->getDataType('decimal') . " DEFAULT 0,
            overtime_amount " . $this->getDataType('decimal') . " DEFAULT 0,
            company_id " . $this->getDataType('integer') . ",
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('employee_id', 'employees') . ",
            " . $this->getForeignKey('payroll_period_id', 'payroll_periods') . ",
            " . $this->getForeignKey('company_id', 'companies') . "
        )";
    }

    /**
     * Employee allowances table SQL
     */
    private function getEmployeeAllowancesTableSQL() {
        return "CREATE TABLE IF NOT EXISTS employee_allowances (
            id " . $this->getDataType('id') . ",
            employee_id " . $this->getDataType('integer') . " NOT NULL,
            allowance_id " . $this->getDataType('integer') . " NOT NULL,
            amount " . $this->getDataType('decimal') . " DEFAULT 0,
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('employee_id', 'employees') . ",
            " . $this->getForeignKey('allowance_id', 'allowances') . "
        )";
    }

    /**
     * Employee deductions table SQL
     */
    private function getEmployeeDeductionsTableSQL() {
        return "CREATE TABLE IF NOT EXISTS employee_deductions (
            id " . $this->getDataType('id') . ",
            employee_id " . $this->getDataType('integer') . " NOT NULL,
            deduction_id " . $this->getDataType('integer') . " NOT NULL,
            amount " . $this->getDataType('decimal') . " DEFAULT 0,
            is_active " . $this->getDataType('boolean') . " DEFAULT 1,
            created_at " . $this->getDataType('timestamp') . ",
            " . $this->getForeignKey('employee_id', 'employees') . ",
            " . $this->getForeignKey('deduction_id', 'deductions') . "
        )";
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $conn = $this->dbManager->getConnection();
        
        try {
            switch ($this->dbType) {
                case DatabaseManager::MYSQL:
                    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$tableName]);
                    return $stmt->rowCount() > 0;
                    
                case DatabaseManager::POSTGRESQL:
                    $stmt = $conn->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
                    $stmt->execute([$tableName]);
                    return $stmt->fetchColumn();
                    
                case DatabaseManager::SQLITE:
                    $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                    $stmt->execute([$tableName]);
                    return $stmt->rowCount() > 0;
                    
                case DatabaseManager::SQLSERVER:
                    $stmt = $conn->prepare("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?");
                    $stmt->execute([$tableName]);
                    return $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Get database version info
     */
    public function getDatabaseInfo() {
        $conn = $this->dbManager->getConnection();
        
        try {
            switch ($this->dbType) {
                case DatabaseManager::MYSQL:
                    $version = $conn->query("SELECT VERSION()")->fetchColumn();
                    break;
                case DatabaseManager::POSTGRESQL:
                    $version = $conn->query("SELECT version()")->fetchColumn();
                    break;
                case DatabaseManager::SQLITE:
                    $version = $conn->query("SELECT sqlite_version()")->fetchColumn();
                    break;
                case DatabaseManager::SQLSERVER:
                    $version = $conn->query("SELECT @@VERSION")->fetchColumn();
                    break;
                default:
                    $version = 'Unknown';
            }
            
            return [
                'type' => $this->dbType,
                'version' => $version,
                'driver' => $conn->getAttribute(PDO::ATTR_DRIVER_NAME)
            ];
        } catch (Exception $e) {
            return [
                'type' => $this->dbType,
                'version' => 'Unknown',
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
