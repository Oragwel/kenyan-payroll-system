-- Migration to make employee fields optional
-- This script updates the employees table to remove NOT NULL constraints from optional fields
-- Run this script on existing databases to apply the new schema changes

-- Make id_number optional (remove NOT NULL constraint)
ALTER TABLE employees MODIFY COLUMN id_number VARCHAR(20) UNIQUE;

-- Make hire_date optional (remove NOT NULL constraint)
ALTER TABLE employees MODIFY COLUMN hire_date DATE;

-- Make contract_type optional with default value (remove NOT NULL constraint)
ALTER TABLE employees MODIFY COLUMN contract_type ENUM('permanent', 'contract', 'casual', 'intern') DEFAULT 'permanent';

-- Update any existing records with NULL contract_type to have default value
UPDATE employees SET contract_type = 'permanent' WHERE contract_type IS NULL;

-- Note: The following fields were already optional in the original schema:
-- - middle_name
-- - email
-- - phone
-- - department_id
-- - position_id
-- - bank_code
-- - bank_name
-- - bank_branch
-- - account_number
-- - kra_pin
-- - nssf_number
-- - nhif_number
-- - date_of_birth
-- - gender
-- - marital_status
-- - address
-- - termination_date

-- Required fields that remain mandatory:
-- - company_id (NOT NULL)
-- - employee_number (UNIQUE NOT NULL)
-- - first_name (NOT NULL)
-- - last_name (NOT NULL)
-- - basic_salary (NOT NULL)
-- - employment_status (DEFAULT 'active')
-- - created_at (DEFAULT CURRENT_TIMESTAMP)
