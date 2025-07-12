-- =====================================================
-- Fix Payroll Records Company ID Issue
-- Adds company_id column if missing and fixes null values
-- =====================================================

USE kenyan_payroll;

-- Show current table structure
SELECT 'BEFORE: Current payroll_records structure' as info;
DESCRIBE payroll_records;

-- Check if company_id column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'company_id'
);

-- Add company_id column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE payroll_records ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER net_pay',
    'SELECT "company_id column already exists" as result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if companies table exists and constraint doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'payroll_records' 
    AND COLUMN_NAME = 'company_id' 
    AND REFERENCED_TABLE_NAME = 'companies'
);

SET @companies_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'kenyan_payroll' 
    AND TABLE_NAME = 'companies'
);

SET @sql = IF(@fk_exists = 0 AND @companies_exists > 0, 
    'ALTER TABLE payroll_records ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
    'SELECT "Foreign key constraint not needed or already exists" as result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update any NULL company_id values with default company (ID = 1)
UPDATE payroll_records 
SET company_id = 1 
WHERE company_id IS NULL;

-- Show updated table structure
SELECT 'AFTER: Updated payroll_records structure' as info;
DESCRIBE payroll_records;

-- Show sample records to verify company_id is populated
SELECT 'Sample records with company_id:' as info;
SELECT 
    id,
    employee_id,
    payroll_period_id,
    basic_salary,
    net_pay,
    company_id,
    created_at
FROM payroll_records 
LIMIT 5;

-- Test query to ensure no NULL company_id values remain
SELECT 'Checking for NULL company_id values:' as info;
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN company_id IS NULL THEN 1 ELSE 0 END) as null_company_id_count
FROM payroll_records;

SELECT '✅ COMPANY_ID ISSUE FIXED SUCCESSFULLY!' as result;
