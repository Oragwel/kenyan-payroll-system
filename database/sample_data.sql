-- Sample data for Kenyan Payroll Management System
-- Run this after creating the schema

USE kenyan_payroll;

-- Insert sample company
INSERT INTO companies (name, registration_number, kra_pin, nssf_number, nhif_number, address, phone, email) VALUES
('ABC Limited', 'C.123456', 'P051234567A', 'NSSF123456', 'NHIF123456', 'P.O. Box 12345, Nairobi', '+254712345678', 'info@abclimited.co.ke');

-- Insert admin user
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@abclimited.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Password is 'password'

-- Insert HR user
INSERT INTO users (username, email, password_hash, role) VALUES
('hr_manager', 'hr@abclimited.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr');

-- Insert departments
INSERT INTO departments (company_id, name, description) VALUES
(1, 'Human Resources', 'Manages employee relations and policies'),
(1, 'Finance & Accounting', 'Handles financial operations and reporting'),
(1, 'Information Technology', 'Manages IT infrastructure and systems'),
(1, 'Sales & Marketing', 'Drives revenue and market presence'),
(1, 'Operations', 'Oversees day-to-day business operations');

-- Insert job positions
INSERT INTO job_positions (company_id, title, description, basic_salary_min, basic_salary_max) VALUES
(1, 'HR Manager', 'Oversees human resource functions', 80000.00, 120000.00),
(1, 'Accountant', 'Manages financial records and reporting', 50000.00, 80000.00),
(1, 'Software Developer', 'Develops and maintains software applications', 60000.00, 100000.00),
(1, 'Sales Executive', 'Drives sales and customer relationships', 40000.00, 70000.00),
(1, 'Operations Assistant', 'Supports daily operational activities', 30000.00, 50000.00),
(1, 'Finance Manager', 'Oversees financial planning and analysis', 90000.00, 150000.00),
(1, 'IT Support Specialist', 'Provides technical support and maintenance', 35000.00, 55000.00);

-- Insert sample employees
INSERT INTO employees (
    company_id, employee_number, first_name, middle_name, last_name, id_number, 
    email, phone, hire_date, contract_type, basic_salary, department_id, position_id
) VALUES
(1, 'EMP010001', 'John', 'Kamau', 'Mwangi', '12345678', 'john.mwangi@abclimited.co.ke', '+254701234567', '2023-01-15', 'permanent', 85000.00, 1, 1),
(1, 'EMP010002', 'Mary', 'Wanjiku', 'Njeri', '23456789', 'mary.njeri@abclimited.co.ke', '+254702345678', '2023-02-01', 'permanent', 65000.00, 2, 2),
(1, 'EMP010003', 'Peter', 'Otieno', 'Ochieng', '34567890', 'peter.ochieng@abclimited.co.ke', '+254703456789', '2023-03-10', 'permanent', 75000.00, 3, 3),
(1, 'EMP010004', 'Grace', 'Akinyi', 'Adhiambo', '45678901', 'grace.adhiambo@abclimited.co.ke', '+254704567890', '2023-04-05', 'permanent', 55000.00, 4, 4),
(1, 'EMP010005', 'David', 'Kiprop', 'Koech', '56789012', 'david.koech@abclimited.co.ke', '+254705678901', '2023-05-20', 'contract', 40000.00, 5, 5),
(1, 'EMP010006', 'Sarah', 'Wambui', 'Karanja', '67890123', 'sarah.karanja@abclimited.co.ke', '+254706789012', '2023-06-15', 'permanent', 110000.00, 2, 6),
(1, 'EMP010007', 'James', 'Maina', 'Githinji', '78901234', 'james.githinji@abclimited.co.ke', '+254707890123', '2023-07-01', 'permanent', 45000.00, 3, 7);

-- Link employees to users (for employees who can log in)
UPDATE employees SET user_id = 2 WHERE employee_number = 'EMP010001'; -- HR Manager can log in

-- Insert allowance types
INSERT INTO allowance_types (company_id, name, description, is_taxable, is_pensionable) VALUES
(1, 'House Allowance', 'Monthly housing allowance', 1, 0),
(1, 'Transport Allowance', 'Monthly transport allowance', 1, 0),
(1, 'Medical Allowance', 'Monthly medical allowance', 1, 0),
(1, 'Lunch Allowance', 'Daily lunch allowance', 1, 0),
(1, 'Performance Bonus', 'Quarterly performance bonus', 1, 0),
(1, 'Overtime Allowance', 'Overtime work compensation', 1, 0);

-- Insert deduction types
INSERT INTO deduction_types (company_id, name, description, is_statutory) VALUES
(1, 'PAYE Tax', 'Pay As You Earn tax', 1),
(1, 'NSSF Contribution', 'National Social Security Fund contribution', 1),
(1, 'NHIF Contribution', 'National Hospital Insurance Fund contribution', 1),
(1, 'Housing Levy', 'Affordable Housing Levy', 1),
(1, 'Loan Repayment', 'Staff loan repayment', 0),
(1, 'Insurance Premium', 'Life insurance premium', 0),
(1, 'Pension Contribution', 'Voluntary pension contribution', 0),
(1, 'Union Dues', 'Trade union membership fees', 0);

-- Insert sample employee allowances
INSERT INTO employee_allowances (employee_id, allowance_type_id, amount, effective_date) VALUES
(1, 1, 25000.00, '2023-01-15'), -- John - House Allowance
(1, 2, 10000.00, '2023-01-15'), -- John - Transport Allowance
(2, 1, 20000.00, '2023-02-01'), -- Mary - House Allowance
(2, 2, 8000.00, '2023-02-01'),  -- Mary - Transport Allowance
(3, 1, 22000.00, '2023-03-10'), -- Peter - House Allowance
(3, 2, 9000.00, '2023-03-10'),  -- Peter - Transport Allowance
(4, 2, 7000.00, '2023-04-05'),  -- Grace - Transport Allowance
(5, 2, 5000.00, '2023-05-20'),  -- David - Transport Allowance
(6, 1, 30000.00, '2023-06-15'), -- Sarah - House Allowance
(6, 2, 12000.00, '2023-06-15'), -- Sarah - Transport Allowance
(7, 2, 6000.00, '2023-07-01');  -- James - Transport Allowance

-- Insert sample employee deductions
INSERT INTO employee_deductions (employee_id, deduction_type_id, amount, effective_date) VALUES
(1, 5, 5000.00, '2023-01-15'),  -- John - Loan Repayment
(2, 6, 2000.00, '2023-02-01'),  -- Mary - Insurance Premium
(3, 7, 3000.00, '2023-03-10'),  -- Peter - Pension Contribution
(6, 5, 8000.00, '2023-06-15');  -- Sarah - Loan Repayment

-- Insert leave types
INSERT INTO leave_types (company_id, name, days_per_year, is_paid, carry_forward) VALUES
(1, 'Annual Leave', 21, 1, 1),
(1, 'Sick Leave', 14, 1, 0),
(1, 'Maternity Leave', 90, 1, 0),
(1, 'Paternity Leave', 14, 1, 0),
(1, 'Compassionate Leave', 7, 1, 0),
(1, 'Study Leave', 10, 0, 0);

-- Insert sample leave applications
INSERT INTO leave_applications (employee_id, leave_type_id, start_date, end_date, days_requested, reason, status) VALUES
(1, 1, '2024-03-15', '2024-03-22', 6, 'Family vacation', 'approved'),
(2, 2, '2024-02-10', '2024-02-12', 3, 'Medical appointment', 'approved'),
(3, 1, '2024-04-01', '2024-04-05', 5, 'Personal matters', 'pending'),
(4, 5, '2024-01-20', '2024-01-22', 3, 'Family bereavement', 'approved');

-- Create activity logs table (missing from schema)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert sample activity logs
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'login', 'Admin user logged in', '127.0.0.1'),
(2, 'login', 'HR Manager logged in', '127.0.0.1'),
(1, 'employee_add', 'Added new employee: John Kamau Mwangi', '127.0.0.1'),
(2, 'payroll_process', 'Processed payroll for January 2024', '127.0.0.1');

-- Sample payroll period and records
INSERT INTO payroll_periods (company_id, period_name, start_date, end_date, pay_date, status, created_by) VALUES
(1, 'January 2024', '2024-01-01', '2024-01-31', '2024-02-01', 'completed', 1);

-- Sample payroll records (simplified - in real system these would be calculated)
INSERT INTO payroll_records (
    employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
    paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
    total_deductions, net_pay, days_worked
) VALUES
(1, 1, 85000.00, 120000.00, 114900.00, 25470.00, 5100.00, 1600.00, 1800.00, 35000.00, 33970.00, 86030.00, 30),
(2, 1, 65000.00, 93000.00, 89080.00, 16270.00, 5400.00, 1500.00, 1395.00, 28000.00, 24565.00, 68435.00, 30),
(3, 1, 75000.00, 106000.00, 100920.00, 20276.00, 5400.00, 1600.00, 1590.00, 31000.00, 28866.00, 77134.00, 30);

-- Insert statutory reports table data
INSERT INTO statutory_reports (company_id, report_type, period_start, period_end, total_amount, status, generated_by) VALUES
(1, 'paye', '2024-01-01', '2024-01-31', 62016.00, 'generated', 1),
(1, 'nssf', '2024-01-01', '2024-01-31', 15900.00, 'generated', 1),
(1, 'nhif', '2024-01-01', '2024-01-31', 4700.00, 'generated', 1),
(1, 'housing_levy', '2024-01-01', '2024-01-31', 4785.00, 'generated', 1);
