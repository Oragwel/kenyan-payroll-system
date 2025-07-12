-- =====================================================
-- SMART PAYROLL RECORDS TABLE FIX
-- Only adds columns that don't already exist
-- =====================================================

USE kenyan_payroll;

-- Show current structure
SELECT 'Current payroll_records structure:' as info;
DESCRIBE payroll_records;

-- Add columns only if they don't exist using dynamic SQL
-- This prevents "Duplicate column name" errors

-- Check and add taxable_income
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'taxable_income') = 0,
    'ALTER TABLE payroll_records ADD COLUMN taxable_income DECIMAL(12,2) DEFAULT 0 AFTER gross_pay',
    'SELECT "taxable_income already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add paye_tax
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'paye_tax') = 0,
    'ALTER TABLE payroll_records ADD COLUMN paye_tax DECIMAL(12,2) DEFAULT 0',
    'SELECT "paye_tax already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add nssf_deduction
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'nssf_deduction') = 0,
    'ALTER TABLE payroll_records ADD COLUMN nssf_deduction DECIMAL(12,2) DEFAULT 0',
    'SELECT "nssf_deduction already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add nhif_deduction
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'nhif_deduction') = 0,
    'ALTER TABLE payroll_records ADD COLUMN nhif_deduction DECIMAL(12,2) DEFAULT 0',
    'SELECT "nhif_deduction already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add housing_levy
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'housing_levy') = 0,
    'ALTER TABLE payroll_records ADD COLUMN housing_levy DECIMAL(12,2) DEFAULT 0',
    'SELECT "housing_levy already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add total_allowances
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'total_allowances') = 0,
    'ALTER TABLE payroll_records ADD COLUMN total_allowances DECIMAL(12,2) DEFAULT 0',
    'SELECT "total_allowances already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add total_deductions
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'total_deductions') = 0,
    'ALTER TABLE payroll_records ADD COLUMN total_deductions DECIMAL(12,2) DEFAULT 0',
    'SELECT "total_deductions already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add overtime_hours
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'overtime_hours') = 0,
    'ALTER TABLE payroll_records ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0',
    'SELECT "overtime_hours already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add overtime_amount
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'overtime_amount') = 0,
    'ALTER TABLE payroll_records ADD COLUMN overtime_amount DECIMAL(12,2) DEFAULT 0',
    'SELECT "overtime_amount already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add days_worked
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_records' AND COLUMN_NAME = 'days_worked') = 0,
    'ALTER TABLE payroll_records ADD COLUMN days_worked INT DEFAULT 30',
    'SELECT "days_worked already exists" as result'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Update existing records with calculated values
UPDATE payroll_records SET 
    taxable_income = CASE WHEN taxable_income = 0 OR taxable_income IS NULL THEN gross_pay ELSE taxable_income END,
    total_deductions = COALESCE(paye_tax, 0) + COALESCE(nssf_deduction, 0) + COALESCE(nhif_deduction, 0) + COALESCE(housing_levy, 0)
WHERE taxable_income = 0 OR taxable_income IS NULL OR total_deductions = 0 OR total_deductions IS NULL;

-- Show final structure
SELECT 'Updated payroll_records structure:' as info;
DESCRIBE payroll_records;

-- Test the complete statutory query
SELECT 'Testing complete statutory query:' as info;
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

SELECT '✅ SMART FIX COMPLETE - All columns checked and added as needed!' as result;
