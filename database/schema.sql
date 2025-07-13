-- Kenyan Payroll Management System Database Schema
-- Created for compliance with Kenyan employment laws and statutory requirements

CREATE DATABASE IF NOT EXISTS kenyan_payroll;
USE kenyan_payroll;

-- Users table for system authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'employee', 'accountant') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Companies table for multi-company support
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    registration_number VARCHAR(50),
    kra_pin VARCHAR(20),
    nssf_number VARCHAR(20),
    nhif_number VARCHAR(20),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Job positions table
CREATE TABLE job_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    basic_salary_min DECIMAL(12,2),
    basic_salary_max DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Employees table
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    company_id INT NOT NULL,
    department_id INT,
    position_id INT,
    employee_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    id_number VARCHAR(20) UNIQUE,
    kra_pin VARCHAR(20),
    nssf_number VARCHAR(20),
    nhif_number VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(100),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    marital_status ENUM('single', 'married', 'divorced', 'widowed'),
    address TEXT,
    bank_name VARCHAR(100),
    bank_branch VARCHAR(100),
    account_number VARCHAR(30),
    hire_date DATE,
    contract_type ENUM('permanent', 'contract', 'casual', 'intern') DEFAULT 'permanent',
    employment_status ENUM('active', 'suspended', 'terminated', 'resigned') DEFAULT 'active',
    termination_date DATE,
    basic_salary DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES job_positions(id) ON DELETE SET NULL
);

-- Allowances types table
CREATE TABLE allowance_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_taxable BOOLEAN DEFAULT TRUE,
    is_pensionable BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Employee allowances table
CREATE TABLE employee_allowances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    allowance_type_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE NOT NULL,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (allowance_type_id) REFERENCES allowance_types(id) ON DELETE CASCADE
);

-- Deduction types table
CREATE TABLE deduction_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_statutory BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Employee deductions table
CREATE TABLE employee_deductions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    deduction_type_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE NOT NULL,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(id) ON DELETE CASCADE
);

-- Payroll periods table
CREATE TABLE payroll_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    period_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pay_date DATE NOT NULL,
    status ENUM('draft', 'processing', 'completed', 'approved') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Payroll records table
CREATE TABLE payroll_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    payroll_period_id INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    gross_pay DECIMAL(12,2) NOT NULL,
    taxable_income DECIMAL(12,2) NOT NULL,
    paye_tax DECIMAL(12,2) NOT NULL,
    nssf_deduction DECIMAL(12,2) NOT NULL,
    nhif_deduction DECIMAL(12,2) NOT NULL,
    housing_levy DECIMAL(12,2) NOT NULL,
    total_allowances DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL,
    net_pay DECIMAL(12,2) NOT NULL,
    days_worked INT DEFAULT 30,
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    overtime_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_period (employee_id, payroll_period_id)
);

-- Payroll allowances breakdown table
CREATE TABLE payroll_allowances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payroll_record_id INT NOT NULL,
    allowance_type_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE,
    FOREIGN KEY (allowance_type_id) REFERENCES allowance_types(id) ON DELETE CASCADE
);

-- Payroll deductions breakdown table
CREATE TABLE payroll_deductions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payroll_record_id INT NOT NULL,
    deduction_type_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (payroll_record_id) REFERENCES payroll_records(id) ON DELETE CASCADE,
    FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(id) ON DELETE CASCADE
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    hours_worked DECIMAL(4,2) DEFAULT 0,
    overtime_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_date (employee_id, date)
);

-- Leave types table
CREATE TABLE leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    days_per_year INT NOT NULL,
    is_paid BOOLEAN DEFAULT TRUE,
    carry_forward BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Leave applications table
CREATE TABLE leave_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Statutory reports table
CREATE TABLE statutory_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    report_type ENUM('paye', 'nssf', 'nhif', 'housing_levy') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    file_path VARCHAR(255),
    status ENUM('generated', 'submitted') DEFAULT 'generated',
    generated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id)
);
