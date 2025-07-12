-- =====================================================
-- Fix Payroll Records Table Structure
-- Adds missing columns for statutory reporting compatibility
-- =====================================================

-- Use the kenyan_payroll database
USE kenyan_payroll;

-- Display current table structure (for reference)
SELECT 'Current payroll_records table structure:' as info;
DESCRIBE payroll_records;

-- =====================================================
-- Add missing columns if they don't exist
-- =====================================================

-- Add taxable_income column
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN taxable_income DECIMAL(12,2) DEFAULT 0 AFTER gross_pay;',
        'SELECT "taxable_income column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'taxable_income'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add total_allowances column
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN total_allowances DECIMAL(12,2) DEFAULT 0 AFTER housing_levy;',
        'SELECT "total_allowances column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'total_allowances'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add overtime_hours column
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0 AFTER total_deductions;',
        'SELECT "overtime_hours column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'overtime_hours'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add overtime_amount column
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN overtime_amount DECIMAL(12,2) DEFAULT 0 AFTER overtime_hours;',
        'SELECT "overtime_amount column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'overtime_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add days_worked column
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN days_worked INT DEFAULT 30 AFTER overtime_amount;',
        'SELECT "days_worked column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'days_worked'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add updated_at column if it doesn't exist
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE payroll_records ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;',
        'SELECT "updated_at column already exists" as result;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Update existing records with calculated values
-- =====================================================

-- Update taxable_income for existing records where it's 0 or NULL
-- Use gross_pay as taxable_income if no specific calculation is needed
UPDATE payroll_records 
SET taxable_income = gross_pay 
WHERE taxable_income IS NULL OR taxable_income = 0;

-- Update total_allowances for existing records where it's 0 or NULL
-- Check if 'allowances' column exists and use it, otherwise set to 0
SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'UPDATE payroll_records SET total_allowances = allowances WHERE total_allowances IS NULL OR total_allowances = 0;',
        'UPDATE payroll_records SET total_allowances = 0 WHERE total_allowances IS NULL;'
    )
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'allowances'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set default values for other new columns
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
-- Display updated table structure
-- =====================================================

SELECT 'Updated payroll_records table structure:' as info;
DESCRIBE payroll_records;

-- =====================================================
-- Test the statutory reporting query
-- =====================================================

SELECT 'Testing statutory reporting query...' as info;

-- Test query that should now work without errors
SELECT 
    'Test Query' as test_type,
    COUNT(*) as record_count,
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

-- =====================================================
-- Verification queries
-- =====================================================

-- Check for any NULL values in critical columns
SELECT 'Checking for NULL values in critical columns...' as info;

SELECT 
    'NULL Value Check' as check_type,
    SUM(CASE WHEN taxable_income IS NULL THEN 1 ELSE 0 END) as null_taxable_income,
    SUM(CASE WHEN total_allowances IS NULL THEN 1 ELSE 0 END) as null_total_allowances,
    SUM(CASE WHEN overtime_hours IS NULL THEN 1 ELSE 0 END) as null_overtime_hours,
    SUM(CASE WHEN overtime_amount IS NULL THEN 1 ELSE 0 END) as null_overtime_amount,
    SUM(CASE WHEN days_worked IS NULL THEN 1 ELSE 0 END) as null_days_worked
FROM payroll_records;

-- Show sample records to verify data integrity
SELECT 'Sample records after update:' as info;

SELECT 
    id,
    basic_salary,
    gross_pay,
    taxable_income,
    paye_tax,
    nssf_deduction,
    nhif_deduction,
    housing_levy,
    total_allowances,
    total_deductions,
    net_pay,
    days_worked,
    overtime_hours,
    overtime_amount
FROM payroll_records 
LIMIT 5;

-- =====================================================
-- Success message
-- =====================================================

SELECT 
    '✅ PAYROLL_RECORDS TABLE STRUCTURE FIXED SUCCESSFULLY!' as status,
    'All required columns have been added and existing data has been updated.' as message,
    'You can now use the statutory reporting system without column errors.' as next_step;
