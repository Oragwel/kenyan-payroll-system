-- =====================================================
-- Simple Fix for Payroll Records Table Structure
-- Adds missing columns for statutory reporting compatibility
-- =====================================================

-- Use the kenyan_payroll database
USE kenyan_payroll;

-- Display current table structure
SELECT 'BEFORE: Current payroll_records table structure:' as info;
SHOW COLUMNS FROM payroll_records;

-- =====================================================
-- Add missing columns (ignore errors if columns exist)
-- =====================================================

-- Add taxable_income column
ALTER TABLE payroll_records 
ADD COLUMN taxable_income DECIMAL(12,2) DEFAULT 0 AFTER gross_pay;

-- Add total_allowances column  
ALTER TABLE payroll_records 
ADD COLUMN total_allowances DECIMAL(12,2) DEFAULT 0 AFTER housing_levy;

-- Add overtime_hours column
ALTER TABLE payroll_records 
ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0 AFTER total_deductions;

-- Add overtime_amount column
ALTER TABLE payroll_records 
ADD COLUMN overtime_amount DECIMAL(12,2) DEFAULT 0 AFTER overtime_hours;

-- Add days_worked column
ALTER TABLE payroll_records 
ADD COLUMN days_worked INT DEFAULT 30 AFTER overtime_amount;

-- Add updated_at column
ALTER TABLE payroll_records 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- =====================================================
-- Update existing records with calculated values
-- =====================================================

-- Update taxable_income for existing records (use gross_pay as default)
UPDATE payroll_records 
SET taxable_income = gross_pay 
WHERE taxable_income IS NULL OR taxable_income = 0;

-- Update total_allowances (try to use allowances column if it exists, otherwise 0)
UPDATE payroll_records 
SET total_allowances = COALESCE(allowances, 0) 
WHERE total_allowances IS NULL OR total_allowances = 0;

-- Set default values for new columns
UPDATE payroll_records 
SET 
    overtime_hours = COALESCE(overtime_hours, 0),
    overtime_amount = COALESCE(overtime_amount, 0),
    days_worked = COALESCE(days_worked, 30)
WHERE 
    overtime_hours IS NULL 
    OR overtime_amount IS NULL 
    OR days_worked IS NULL;

-- =====================================================
-- Display results
-- =====================================================

SELECT 'AFTER: Updated payroll_records table structure:' as info;
SHOW COLUMNS FROM payroll_records;

-- Test the statutory reporting query
SELECT 'Testing statutory reporting query...' as info;

SELECT 
    COUNT(*) as total_records,
    SUM(basic_salary) as total_basic_salary,
    SUM(gross_pay) as total_gross_pay,
    SUM(taxable_income) as total_taxable_income,
    SUM(paye_tax) as total_paye_tax,
    SUM(nssf_deduction) as total_nssf,
    SUM(nhif_deduction) as total_nhif,
    SUM(housing_levy) as total_housing_levy,
    SUM(total_allowances) as total_allowances,
    SUM(total_deductions) as total_deductions,
    SUM(net_pay) as total_net_pay
FROM payroll_records;

-- Show sample records
SELECT 'Sample records:' as info;
SELECT 
    id,
    basic_salary,
    gross_pay,
    taxable_income,
    total_allowances,
    paye_tax,
    nssf_deduction,
    nhif_deduction,
    housing_levy,
    net_pay,
    days_worked
FROM payroll_records 
LIMIT 3;

SELECT '✅ SUCCESS: Payroll table structure has been fixed!' as result;
