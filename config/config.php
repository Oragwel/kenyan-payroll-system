<?php
/**
 * Application configuration
 */

// Application settings
define('APP_NAME', 'Kenyan Payroll Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/kenyan-payroll');

// Kenyan statutory rates (2024)
define('PAYE_RATES', [
    ['min' => 0, 'max' => 24000, 'rate' => 0.10],
    ['min' => 24001, 'max' => 32333, 'rate' => 0.25],
    ['min' => 32334, 'max' => 500000, 'rate' => 0.30],
    ['min' => 500001, 'max' => 800000, 'rate' => 0.325],
    ['min' => 800001, 'max' => PHP_INT_MAX, 'rate' => 0.35]
]);

// NSSF rates
define('NSSF_RATE', 0.06); // 6% of pensionable pay
define('NSSF_MAX_PENSIONABLE', 18000); // Maximum pensionable pay

// NHIF/SHIF rates (Social Health Insurance Fund) - Updated 2024
define('SHIF_RATES', [
    ['min' => 0, 'max' => 5999, 'amount' => 300],
    ['min' => 6000, 'max' => 7999, 'amount' => 300],
    ['min' => 8000, 'max' => 11999, 'amount' => 400],
    ['min' => 12000, 'max' => 14999, 'amount' => 500],
    ['min' => 15000, 'max' => 19999, 'amount' => 600],
    ['min' => 20000, 'max' => 24999, 'amount' => 750],
    ['min' => 25000, 'max' => 29999, 'amount' => 850],
    ['min' => 30000, 'max' => 34999, 'amount' => 900],
    ['min' => 35000, 'max' => 39999, 'amount' => 950],
    ['min' => 40000, 'max' => 44999, 'amount' => 1000],
    ['min' => 45000, 'max' => 49999, 'amount' => 1100],
    ['min' => 50000, 'max' => 59999, 'amount' => 1200],
    ['min' => 60000, 'max' => 69999, 'amount' => 1300],
    ['min' => 70000, 'max' => 79999, 'amount' => 1400],
    ['min' => 80000, 'max' => 89999, 'amount' => 1500],
    ['min' => 90000, 'max' => 99999, 'amount' => 1600],
    ['min' => 100000, 'max' => PHP_INT_MAX, 'amount' => 1700]
]);

// Housing Levy
define('HOUSING_LEVY_RATE', 0.015); // 1.5% of gross pay

// Personal relief
define('PERSONAL_RELIEF', 2400); // Monthly personal relief

// Insurance relief limit
define('INSURANCE_RELIEF_LIMIT', 5000); // Monthly limit

// Pension contribution relief limit
define('PENSION_RELIEF_LIMIT', 20000); // Monthly limit

// Date formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');

// File upload settings
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Pagination
define('RECORDS_PER_PAGE', 20);

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_HR', 'hr');
define('ROLE_EMPLOYEE', 'employee');
define('ROLE_ACCOUNTANT', 'accountant');
?>
