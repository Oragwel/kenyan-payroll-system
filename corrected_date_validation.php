<?php
/**
 * Corrected Date Validation System for Payroll
 * Based on the documented rules in fatal_error.md
 */

/**
 * Comprehensive payroll period date validation
 * 
 * Rules:
 * 1. Period Dates: Start and end dates cannot be in the future
 * 2. Pay Date: Must be today or up to 30 days in the future
 * 3. Date Logic: End date must be after start date, pay date must be after end date
 * 4. Overlapping Periods: New periods cannot overlap with existing ones
 * 5. Payroll can be generated for the past months within same year
 */
function validatePayrollDates($startDate, $endDate, $payDate, $companyId = null) {
    $errors = [];
    $today = new DateTime();
    $currentYear = $today->format('Y');
    
    try {
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $payDateObj = new DateTime($payDate);
    } catch (Exception $e) {
        return ['valid' => false, 'errors' => ['Invalid date format provided']];
    }
    
    // Rule 1: Period dates cannot be in the future
    if ($startDateObj > $today) {
        $errors[] = 'Start date cannot be in the future';
    }
    
    if ($endDateObj > $today) {
        $errors[] = 'End date cannot be in the future';
    }
    
    // Rule 5: Payroll can only be generated for past months within same year
    $startYear = $startDateObj->format('Y');
    $endYear = $endDateObj->format('Y');
    
    if ($startYear != $currentYear || $endYear != $currentYear) {
        $errors[] = 'Payroll periods must be within the current year (' . $currentYear . ')';
    }
    
    // Rule 3a: End date must be after start date
    if ($startDateObj >= $endDateObj) {
        $errors[] = 'End date must be after start date';
    }
    
    // Rule 2: Pay date must be today or up to 30 days in the future
    $todayStart = clone $today;
    $todayStart->setTime(0, 0, 0); // Start of today
    
    $maxPayDate = clone $today;
    $maxPayDate->modify('+30 days');
    
    if ($payDateObj < $todayStart) {
        $errors[] = 'Pay date cannot be in the past';
    }
    
    if ($payDateObj > $maxPayDate) {
        $errors[] = 'Pay date cannot be more than 30 days in the future';
    }
    
    // Rule 3b: Pay date must be after end date (for logical payroll processing)
    if ($payDateObj <= $endDateObj) {
        $errors[] = 'Pay date must be after the period end date';
    }
    
    // Rule 4: Check for overlapping periods (if company ID provided)
    if ($companyId && empty($errors)) {
        global $db;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, period_name
            FROM payroll_periods
            WHERE company_id = ?
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (start_date <= ? AND end_date >= ?) OR
                (start_date >= ? AND end_date <= ?)
            )
        ");
        
        $stmt->execute([
            $companyId,
            $startDate, $startDate,  // Check if new start date falls within existing period
            $endDate, $endDate,      // Check if new end date falls within existing period
            $startDate, $endDate     // Check if new period encompasses existing period
        ]);
        
        $overlap = $stmt->fetch();
        
        if ($overlap['count'] > 0) {
            $errors[] = 'This payroll period overlaps with an existing period: ' . $overlap['period_name'];
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => generateDateWarnings($startDateObj, $endDateObj, $payDateObj)
    ];
}

/**
 * Generate helpful warnings for date selections
 */
function generateDateWarnings($startDate, $endDate, $payDate) {
    $warnings = [];
    $today = new DateTime();
    
    // Warn if period is very recent
    $daysSinceEnd = $today->diff($endDate)->days;
    if ($daysSinceEnd < 3) {
        $warnings[] = 'Period ended recently. Ensure all attendance and overtime data is complete.';
    }
    
    // Warn if pay date is very soon
    $daysToPayDate = $today->diff($payDate)->days;
    if ($daysToPayDate < 2) {
        $warnings[] = 'Pay date is very soon. Ensure payroll processing is completed in time.';
    }
    
    // Warn about weekend pay dates
    $payDayOfWeek = $payDate->format('N'); // 1 = Monday, 7 = Sunday
    if ($payDayOfWeek >= 6) {
        $warnings[] = 'Pay date falls on a weekend. Consider banking processing delays.';
    }
    
    return $warnings;
}

/**
 * Get suggested dates for payroll period
 */
function getSuggestedPayrollDates() {
    $today = new DateTime();
    $currentMonth = $today->format('n');
    $currentYear = $today->format('Y');
    
    // Suggest previous month if we're in the first week of current month
    if ($today->format('j') <= 7) {
        $suggestedMonth = $currentMonth - 1;
        $suggestedYear = $currentYear;
        
        if ($suggestedMonth <= 0) {
            $suggestedMonth = 12;
            $suggestedYear = $currentYear - 1;
        }
    } else {
        $suggestedMonth = $currentMonth;
        $suggestedYear = $currentYear;
    }
    
    // Only suggest if it's the same year
    if ($suggestedYear != $currentYear) {
        return null;
    }
    
    $startDate = new DateTime("$suggestedYear-$suggestedMonth-01");
    $endDate = clone $startDate;
    $endDate->modify('last day of this month');
    
    // Suggest pay date 3-5 days after month end
    $payDate = clone $endDate;
    $payDate->modify('+5 days');
    
    // If pay date is too far in future, adjust
    $maxPayDate = clone $today;
    $maxPayDate->modify('+30 days');
    
    if ($payDate > $maxPayDate) {
        $payDate = clone $maxPayDate;
    }
    
    return [
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),
        'pay_date' => $payDate->format('Y-m-d'),
        'period_name' => $startDate->format('F Y')
    ];
}

/**
 * Validate if payroll can be processed for a specific period
 */
function canProcessPayrollForPeriod($startDate, $endDate) {
    $today = new DateTime();
    $endDateObj = new DateTime($endDate);
    
    // Must be past period
    if ($endDateObj >= $today) {
        return [
            'can_process' => false,
            'reason' => 'Cannot process payroll for current or future periods'
        ];
    }
    
    // Must be within current year
    $currentYear = $today->format('Y');
    $periodYear = $endDateObj->format('Y');
    
    if ($periodYear != $currentYear) {
        return [
            'can_process' => false,
            'reason' => 'Can only process payroll for periods within the current year'
        ];
    }
    
    return [
        'can_process' => true,
        'reason' => 'Period is valid for payroll processing'
    ];
}

/**
 * Format validation errors for display
 */
function formatValidationErrors($validation) {
    $html = '';
    
    if (!$validation['valid']) {
        $html .= '<div class="alert alert-danger">';
        $html .= '<h6><i class="fas fa-exclamation-triangle"></i> Validation Errors:</h6>';
        $html .= '<ul class="mb-0">';
        foreach ($validation['errors'] as $error) {
            $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    if (!empty($validation['warnings'])) {
        $html .= '<div class="alert alert-warning">';
        $html .= '<h6><i class="fas fa-exclamation-circle"></i> Warnings:</h6>';
        $html .= '<ul class="mb-0">';
        foreach ($validation['warnings'] as $warning) {
            $html .= '<li>' . htmlspecialchars($warning) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    return $html;
}

// Example usage and testing
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "<h2>Date Validation Testing</h2>";
    
    // Test cases
    $testCases = [
        ['2024-11-01', '2024-11-30', '2024-12-05', 'Valid past month'],
        ['2024-12-01', '2024-12-31', '2025-01-05', 'Future period (should fail)'],
        ['2024-10-01', '2024-10-30', '2024-10-25', 'Pay date before end date (should fail)'],
        ['2023-12-01', '2023-12-31', '2024-01-05', 'Previous year (should fail)'],
        ['2024-11-15', '2024-11-10', '2024-11-20', 'Start after end (should fail)']
    ];
    
    foreach ($testCases as $test) {
        echo "<h4>Test: {$test[3]}</h4>";
        $validation = validatePayrollDates($test[0], $test[1], $test[2]);
        echo formatValidationErrors($validation);
        echo "<hr>";
    }
    
    // Show suggested dates
    $suggested = getSuggestedPayrollDates();
    if ($suggested) {
        echo "<h4>Suggested Payroll Period:</h4>";
        echo "<p><strong>Period:</strong> {$suggested['period_name']}</p>";
        echo "<p><strong>Start Date:</strong> {$suggested['start_date']}</p>";
        echo "<p><strong>End Date:</strong> {$suggested['end_date']}</p>";
        echo "<p><strong>Pay Date:</strong> {$suggested['pay_date']}</p>";
    }
}
?>
