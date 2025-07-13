-- Prevent Payroll Duplicates - Database Constraint
-- This script adds a unique constraint to prevent duplicate payroll records
-- for the same employee in the same payroll period

-- First, let's check for existing duplicates
SELECT 
    'Checking for existing duplicates...' as status;

SELECT 
    employee_id,
    payroll_period_id,
    COUNT(*) as duplicate_count
FROM payroll_records 
GROUP BY employee_id, payroll_period_id 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Show details of duplicate records if any exist
SELECT 
    'Duplicate record details:' as info;

SELECT 
    pr.id,
    pr.employee_id,
    pr.payroll_period_id,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    pp.period_name,
    pr.created_at,
    pr.net_pay
FROM payroll_records pr
JOIN employees e ON pr.employee_id = e.id
JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
WHERE (pr.employee_id, pr.payroll_period_id) IN (
    SELECT employee_id, payroll_period_id 
    FROM payroll_records 
    GROUP BY employee_id, payroll_period_id 
    HAVING COUNT(*) > 1
)
ORDER BY pr.employee_id, pr.payroll_period_id, pr.created_at;

-- Remove duplicate records (keep the latest one)
-- This is commented out for safety - uncomment if you want to clean existing duplicates
/*
DELETE pr1 FROM payroll_records pr1
INNER JOIN payroll_records pr2 
WHERE pr1.employee_id = pr2.employee_id 
  AND pr1.payroll_period_id = pr2.payroll_period_id 
  AND pr1.id < pr2.id;
*/

-- Add unique constraint to prevent future duplicates
-- This will fail if duplicates exist, so clean them first
ALTER TABLE payroll_records 
ADD CONSTRAINT unique_employee_period 
UNIQUE (employee_id, payroll_period_id);

SELECT 'Unique constraint added successfully!' as result;

-- Verify the constraint was added
SELECT 
    'Constraint verification:' as info;

SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_NAME = 'payroll_records' 
  AND CONSTRAINT_NAME = 'unique_employee_period';
