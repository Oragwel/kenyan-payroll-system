<?php
/**
 * Core functions for Kenyan Payroll Management System
 */

/**
 * Calculate PAYE tax based on Kenyan tax brackets
 */
function calculatePAYE($taxableIncome) {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $taxableIncome = $taxableIncome ?? 0;
    $tax = 0;
    $rates = PAYE_RATES;
    
    foreach ($rates as $bracket) {
        if ($taxableIncome > $bracket['min']) {
            $taxableAmount = min($taxableIncome, $bracket['max']) - $bracket['min'] + 1;
            if ($taxableAmount > 0) {
                $tax += $taxableAmount * $bracket['rate'];
            }
        }
    }
    
    // Apply personal relief
    $tax = max(0, $tax - PERSONAL_RELIEF);
    
    return round($tax, 2);
}

/**
 * Calculate NSSF contribution (exempted for contract employees)
 */
function calculateNSSF($grossPay, $contractType = 'permanent') {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $grossPay = $grossPay ?? 0;

    // Contract employees are exempted from NSSF
    if ($contractType === 'contract') {
        return 0;
    }

    $pensionablePay = min($grossPay, NSSF_MAX_PENSIONABLE);
    return round($pensionablePay * NSSF_RATE, 2);
}

/**
 * Calculate SHIF contribution (2.75% of gross pay with minimum KES 300)
 */
function calculateSHIF($grossPay) {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $grossPay = $grossPay ?? 0;
    $calculated = $grossPay * SHIF_RATE;
    return ceil(max($calculated, SHIF_MINIMUM));
}

/**
 * Calculate Housing Levy (exempted for contract employees)
 */
function calculateHousingLevy($grossPay, $contractType = 'permanent') {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $grossPay = $grossPay ?? 0;

    // Contract employees are exempted from Housing Levy
    if ($contractType === 'contract') {
        return 0;
    }

    return round($grossPay * HOUSING_LEVY_RATE, 2);
}

/**
 * Calculate total taxable income
 */
function calculateTaxableIncome($grossPay, $nssfContribution, $pensionContribution = 0, $insurancePremium = 0) {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $grossPay = $grossPay ?? 0;
    $nssfContribution = $nssfContribution ?? 0;
    $pensionContribution = $pensionContribution ?? 0;
    $insurancePremium = $insurancePremium ?? 0;

    // Deduct NSSF, pension contributions, and insurance premiums from gross pay
    $pensionRelief = min($pensionContribution, PENSION_RELIEF_LIMIT);
    $insuranceRelief = min($insurancePremium, INSURANCE_RELIEF_LIMIT);
    
    $taxableIncome = $grossPay - $nssfContribution - $pensionRelief - $insuranceRelief;
    
    return max(0, $taxableIncome);
}

/**
 * Process complete payroll for an employee
 */
function processEmployeePayroll($employeeId, $payrollPeriodId, $basicSalary, $allowances = [], $deductions = [], $daysWorked = 30, $overtimeHours = 0, $overtimeRate = 0, $contractType = 'permanent') {
    // Calculate gross pay
    $totalAllowances = array_sum($allowances);
    $overtimeAmount = $overtimeHours * $overtimeRate;
    $grossPay = $basicSalary + $totalAllowances + $overtimeAmount;

    // Calculate statutory deductions based on contract type
    $nssfDeduction = calculateNSSF($grossPay, $contractType);
    $shifDeduction = calculateSHIF($grossPay); // SHIF applies to all employment types
    $housingLevy = calculateHousingLevy($grossPay, $contractType);

    // Calculate taxable income
    $pensionContribution = $deductions['pension'] ?? 0;
    $insurancePremium = $deductions['insurance'] ?? 0;
    $taxableIncome = calculateTaxableIncome($grossPay, $nssfDeduction, $pensionContribution, $insurancePremium);

    // Calculate PAYE
    $payeTax = calculatePAYE($taxableIncome);

    // Calculate total deductions
    $totalOtherDeductions = array_sum($deductions);
    $totalDeductions = $payeTax + $nssfDeduction + $shifDeduction + $housingLevy + $totalOtherDeductions;

    // Calculate net pay
    $netPay = $grossPay - $totalDeductions;

    return [
        'basic_salary' => $basicSalary,
        'total_allowances' => $totalAllowances,
        'overtime_amount' => $overtimeAmount,
        'gross_pay' => $grossPay,
        'taxable_income' => $taxableIncome,
        'paye_tax' => $payeTax,
        'nssf_deduction' => $nssfDeduction,
        'nhif_deduction' => $shifDeduction,
        'housing_levy' => $housingLevy,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
        'days_worked' => $daysWorked,
        'overtime_hours' => $overtimeHours,
        'contract_type' => $contractType
    ];
}

/**
 * Format currency for display
 */
function formatCurrency($amount) {
    // Handle null values to prevent PHP 8.1+ deprecation warnings
    $amount = $amount ?? 0;
    return 'KES ' . number_format($amount, 2);
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date(DISPLAY_DATE_FORMAT, strtotime($date));
}

/**
 * Generate employee number
 */
function generateEmployeeNumber($companyId) {
    global $db;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $result = $stmt->fetch();
    
    $nextNumber = $result['count'] + 1;
    return 'EMP' . str_pad($companyId, 2, '0', STR_PAD_LEFT) . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Check user permissions
 */
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $roleHierarchy = [
        'employee' => 1,
        'hr' => 2,
        'accountant' => 3,
        'admin' => 4
    ];
    
    $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 5;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate secure password hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    // Handle null or empty hash values
    if (empty($hash) || $hash === null) {
        return false;
    }

    // Handle null or empty password
    if (empty($password) || $password === null) {
        return false;
    }

    return password_verify($password, $hash);
}

/**
 * Calculate working days between two dates
 */
function calculateWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->add($interval));
    
    $workingDays = 0;
    foreach ($period as $date) {
        if ($date->format('N') < 6) { // Monday = 1, Sunday = 7
            $workingDays++;
        }
    }
    
    return $workingDays;
}

/**
 * Get financial year for a given date
 */
function getFinancialYear($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    
    // Kenyan financial year runs from July to June
    if ($month >= 7) {
        return $year . '/' . ($year + 1);
    } else {
        return ($year - 1) . '/' . $year;
    }
}

/**
 * Log system activity
 */
function logActivity($action, $description, $userId = null) {
    global $db;

    // Skip logging if database is not available
    if (!$db) {
        return;
    }

    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {
        // Silently fail if activity_logs table doesn't exist
        // This prevents login failures due to missing table
        error_log("Activity logging failed: " . $e->getMessage());
    }
}
?>
