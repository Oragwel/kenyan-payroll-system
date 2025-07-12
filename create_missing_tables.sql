-- =====================================================
-- Kenyan Payroll System - Missing Tables Creation Script
-- =====================================================
-- This script creates all the missing tables that are causing fatal errors
-- Run this in your MySQL/phpMyAdmin to fix the database structure

USE kenyan_payroll;

-- 1. PAYROLL_PERIODS TABLE (Main table causing the current error)
-- ================================================================
CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    period_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pay_date DATE NOT NULL,
    status ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. PAYROLL_RECORDS TABLE
-- ========================
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payroll_period_id INT NOT NULL,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(15,2) DEFAULT 0,
    overtime_pay DECIMAL(15,2) DEFAULT 0,
    gross_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
    paye_tax DECIMAL(15,2) DEFAULT 0,
    nssf_deduction DECIMAL(15,2) DEFAULT 0,
    nhif_deduction DECIMAL(15,2) DEFAULT 0,
    housing_levy DECIMAL(15,2) DEFAULT 0,
    other_deductions DECIMAL(15,2) DEFAULT 0,
    total_deductions DECIMAL(15,2) DEFAULT 0,
    net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 3. DEPARTMENTS TABLE
-- ===================
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. LEAVE_TYPES TABLE
-- ====================
CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    days_per_year INT NOT NULL DEFAULT 0,
    carry_forward BOOLEAN DEFAULT FALSE,
    max_carry_forward INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 5. LEAVE_APPLICATIONS TABLE
-- ===========================
CREATE TABLE IF NOT EXISTS leave_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. ATTENDANCE TABLE
-- ===================
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    break_time_minutes INT DEFAULT 0,
    total_hours DECIMAL(4,2) DEFAULT 0,
    overtime_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_date (employee_id, date)
);

-- 7. SYSTEM_SETTINGS TABLE
-- =========================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting (company_id, setting_key),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- =====================================================
-- INSERT DEFAULT DATA (Optional)
-- =====================================================

-- Insert default leave types for the first company
INSERT IGNORE INTO leave_types (company_id, name, days_per_year, carry_forward) 
SELECT 1, 'Annual Leave', 21, TRUE WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO leave_types (company_id, name, days_per_year, carry_forward) 
SELECT 1, 'Sick Leave', 7, FALSE WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO leave_types (company_id, name, days_per_year, carry_forward) 
SELECT 1, 'Maternity Leave', 90, FALSE WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO leave_types (company_id, name, days_per_year, carry_forward) 
SELECT 1, 'Paternity Leave', 14, FALSE WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO leave_types (company_id, name, days_per_year, carry_forward) 
SELECT 1, 'Compassionate Leave', 3, FALSE WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

-- Insert default departments for the first company
INSERT IGNORE INTO departments (company_id, name, description) 
SELECT 1, 'Human Resources', 'Manages employee relations and policies' WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO departments (company_id, name, description) 
SELECT 1, 'Finance & Accounting', 'Handles financial operations and payroll' WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO departments (company_id, name, description) 
SELECT 1, 'Operations', 'Core business operations and management' WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

INSERT IGNORE INTO departments (company_id, name, description) 
SELECT 1, 'Information Technology', 'IT support and system administration' WHERE EXISTS (SELECT 1 FROM companies WHERE id = 1);

-- =====================================================
-- VERIFICATION QUERIES (Run these to check if tables were created)
-- =====================================================

-- Check if all tables exist
-- SHOW TABLES LIKE 'payroll_periods';
-- SHOW TABLES LIKE 'payroll_records';
-- SHOW TABLES LIKE 'departments';
-- SHOW TABLES LIKE 'leave_types';
-- SHOW TABLES LIKE 'leave_applications';
-- SHOW TABLES LIKE 'attendance';
-- SHOW TABLES LIKE 'system_settings';

-- Check table structures
-- DESCRIBE payroll_periods;
-- DESCRIBE payroll_records;

-- =====================================================
-- END OF SCRIPT
-- =====================================================
