-- =====================================================
-- COMPLETE PAYROLL RECORDS TABLE FIX
-- Adds ALL missing columns for statutory reporting
-- =====================================================

USE kenyan_payroll;

-- Show current structure
SELECT 'BEFORE: Current payroll_records structure' as info;
DESCRIBE payroll_records;

-- Add ALL missing columns for statutory reporting
-- (Ignore errors if columns already exist)

-- Core tax and deduction columns
ALTER TABLE payroll_records ADD COLUMN taxable_income DECIMAL(12,2) DEFAULT 0 AFTER gross_pay;
ALTER TABLE payroll_records ADD COLUMN paye_tax DECIMAL(12,2) DEFAULT 0 AFTER taxable_income;
ALTER TABLE payroll_records ADD COLUMN nssf_deduction DECIMAL(12,2) DEFAULT 0 AFTER paye_tax;
ALTER TABLE payroll_records ADD COLUMN nhif_deduction DECIMAL(12,2) DEFAULT 0 AFTER nssf_deduction;
ALTER TABLE payroll_records ADD COLUMN housing_levy DECIMAL(12,2) DEFAULT 0 AFTER nhif_deduction;

-- Allowances and deductions totals
ALTER TABLE payroll_records ADD COLUMN total_allowances DECIMAL(12,2) DEFAULT 0 AFTER housing_levy;
ALTER TABLE payroll_records ADD COLUMN total_deductions DECIMAL(12,2) DEFAULT 0 AFTER total_allowances;

-- Overtime and attendance
ALTER TABLE payroll_records ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0 AFTER total_deductions;
ALTER TABLE payroll_records ADD COLUMN overtime_amount DECIMAL(12,2) DEFAULT 0 AFTER overtime_hours;
ALTER TABLE payroll_records ADD COLUMN days_worked INT DEFAULT 30 AFTER overtime_amount;

-- Timestamp for updates
ALTER TABLE payroll_records ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing records with calculated values
UPDATE payroll_records SET 
    taxable_income = gross_pay,
    total_deductions = COALESCE(paye_tax, 0) + COALESCE(nssf_deduction, 0) + COALESCE(nhif_deduction, 0) + COALESCE(housing_levy, 0)
WHERE taxable_income IS NULL OR taxable_income = 0;

-- Show updated structure
SELECT 'AFTER: Updated payroll_records structure' as info;
DESCRIBE payroll_records;

-- Test the statutory query
SELECT 'Testing statutory reporting query...' as info;
SELECT 
    COUNT(*) as total_records,
    SUM(COALESCE(basic_salary, 0)) as total_basic_salary,
    SUM(COALESCE(gross_pay, 0)) as total_gross_pay,
    SUM(COALESCE(taxable_income, 0)) as total_taxable_income,
    SUM(COALESCE(paye_tax, 0)) as total_paye_tax,
    SUM(COALESCE(nssf_deduction, 0)) as total_nssf,
    SUM(COALESCE(nhif_deduction, 0)) as total_nhif,
    SUM(COALESCE(housing_levy, 0)) as total_housing_levy,
    SUM(COALESCE(total_allowances, 0)) as total_allowances,
    SUM(COALESCE(total_deductions, 0)) as total_deductions,
    SUM(COALESCE(net_pay, 0)) as total_net_pay
FROM payroll_records;

SELECT '✅ COMPLETE FIX APPLIED - All statutory columns added!' as result;
