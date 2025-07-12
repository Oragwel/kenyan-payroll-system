-- =====================================================
-- SIMPLE COLUMN FIX - Run each statement individually
-- Copy and paste one statement at a time to avoid duplicate errors
-- =====================================================

USE kenyan_payroll;

-- Show current structure first
DESCRIBE payroll_records;

-- Add columns one by one (ignore errors if column exists)
-- Copy and paste each ALTER statement individually:

-- 1. Add paye_tax (if not exists)
ALTER TABLE payroll_records ADD COLUMN paye_tax DECIMAL(12,2) DEFAULT 0;

-- 2. Add nssf_deduction (if not exists)  
ALTER TABLE payroll_records ADD COLUMN nssf_deduction DECIMAL(12,2) DEFAULT 0;

-- 3. Add nhif_deduction (if not exists)
ALTER TABLE payroll_records ADD COLUMN nhif_deduction DECIMAL(12,2) DEFAULT 0;

-- 4. Add housing_levy (if not exists)
ALTER TABLE payroll_records ADD COLUMN housing_levy DECIMAL(12,2) DEFAULT 0;

-- 5. Add total_deductions (if not exists)
ALTER TABLE payroll_records ADD COLUMN total_deductions DECIMAL(12,2) DEFAULT 0;

-- 6. Update existing records with calculated values
UPDATE payroll_records SET 
    taxable_income = CASE WHEN taxable_income = 0 OR taxable_income IS NULL THEN gross_pay ELSE taxable_income END,
    total_deductions = COALESCE(paye_tax, 0) + COALESCE(nssf_deduction, 0) + COALESCE(nhif_deduction, 0) + COALESCE(housing_levy, 0);

-- 7. Show final structure
DESCRIBE payroll_records;

-- 8. Test the statutory query
SELECT 
    'Testing statutory query' as test,
    COUNT(*) as records,
    SUM(COALESCE(taxable_income, 0)) as total_taxable,
    SUM(COALESCE(paye_tax, 0)) as total_paye,
    SUM(COALESCE(nssf_deduction, 0)) as total_nssf,
    SUM(COALESCE(nhif_deduction, 0)) as total_nhif,
    SUM(COALESCE(housing_levy, 0)) as total_housing
FROM payroll_records;
