-- Migration to add termination_date column to employees table
-- This script adds the missing termination_date column that exists in schema.sql but not in install.php

-- Check if the column already exists before adding it
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'employees'
    AND COLUMN_NAME = 'termination_date'
);

-- Add the column only if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE employees ADD COLUMN termination_date DATE AFTER employment_status',
    'SELECT "Column termination_date already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the result
SELECT 
    CASE 
        WHEN @column_exists = 0 THEN 'termination_date column added successfully'
        ELSE 'termination_date column already exists'
    END as result;

-- Show updated table structure
DESCRIBE employees;
