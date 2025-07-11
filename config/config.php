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

// SHIF (Social Health Insurance Fund) - 2024 Rates
// SHIF is calculated as 2.75% of gross salary with a minimum of KES 300
define('SHIF_RATE', 0.0275); // 2.75% of gross salary
define('SHIF_MINIMUM', 300);  // Minimum SHIF contribution

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
