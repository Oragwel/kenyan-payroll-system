<?php
/**
 * Flexible Date Validation System for Payroll
 * Removes unnecessary restrictions while maintaining essential compliance
 */

/**
 * Simplified payroll period date validation
 * 
 * Essential Rules Only:
 * 1. Date Logic: End date must be after start date
 * 2. Pay Date: Must be a valid date (can be past, present, or future)
 * 3. Basic Format: All dates must be valid date formats
 * 4. Overlapping Periods: Optional check (can be disabled)
 */
function validatePayrollDatesFlexible($startDate, $endDate, $payDate, $companyId = null, $checkOverlaps = false) {
    $errors = [];
    
    try {
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $payDateObj = new DateTime($payDate);
    } catch (Exception $e) {
        return ['valid' => false, 'errors' => ['Invalid date format provided. Please use YYYY-MM-DD format.']];
    }
    
    // Essential Rule 1: End date must be after start date
    if ($startDateObj >= $endDateObj) {
        $errors[] = 'End date must be after start date';
    }
    
    // Essential Rule 2: Reasonable date range (not more than 1 year apart)
    $daysDiff = $startDateObj->diff($endDateObj)->days;
    if ($daysDiff > 365) {
        $errors[] = 'Payroll period cannot exceed 1 year';
    }
    
    // Optional Rule 3: Check for overlapping periods (only if requested)
    if ($checkOverlaps && $companyId) {
        global $db;
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM payroll_periods 
                WHERE company_id = ? 
                AND (
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date >= ? AND end_date <= ?)
                )
            ");
            $stmt->execute([
                $companyId, 
                $startDate, $startDate,
                $endDate, $endDate,
                $startDate, $endDate
            ]);
            
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                $errors[] = 'This payroll period overlaps with an existing period';
            }
        } catch (Exception $e) {
            // Ignore database errors for overlap check
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Quick validation for simple payroll processing
 * Only checks essential date logic
 */
function validateQuickPayroll($payPeriod, $payDate) {
    $errors = [];
    
    try {
        // Convert pay period (YYYY-MM) to date range
        $startDate = date('Y-m-01', strtotime($payPeriod . '-01'));
        $endDate = date('Y-m-t', strtotime($payPeriod . '-01'));
        $payDateObj = new DateTime($payDate);
    } catch (Exception $e) {
        return ['valid' => false, 'errors' => ['Invalid date format provided']];
    }
    
    // Basic validation - just ensure pay date is valid
    if (!$payDateObj) {
        $errors[] = 'Invalid pay date';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
}

/**
 * Minimal validation for emergency payroll processing
 * Use when you need to process payroll without restrictions
 */
function validateEmergencyPayroll($startDate, $endDate, $payDate) {
    try {
        $startDateObj = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $payDateObj = new DateTime($payDate);
        
        // Only check that end date is after start date
        if ($startDateObj >= $endDateObj) {
            return ['valid' => false, 'errors' => ['End date must be after start date']];
        }
        
        return ['valid' => true, 'errors' => []];
        
    } catch (Exception $e) {
        return ['valid' => false, 'errors' => ['Invalid date format']];
    }
}

/**
 * Get suggested payroll periods for quick setup
 */
function getSuggestedPayrollPeriods() {
    $suggestions = [];
    $currentDate = new DateTime();
    
    // Current month
    $suggestions[] = [
        'name' => $currentDate->format('F Y'),
        'period' => $currentDate->format('Y-m'),
        'start_date' => $currentDate->format('Y-m-01'),
        'end_date' => $currentDate->format('Y-m-t'),
        'pay_date' => $currentDate->format('Y-m-d')
    ];
    
    // Previous month
    $prevMonth = clone $currentDate;
    $prevMonth->modify('-1 month');
    $suggestions[] = [
        'name' => $prevMonth->format('F Y'),
        'period' => $prevMonth->format('Y-m'),
        'start_date' => $prevMonth->format('Y-m-01'),
        'end_date' => $prevMonth->format('Y-m-t'),
        'pay_date' => $currentDate->format('Y-m-d')
    ];
    
    // Next month (for planning)
    $nextMonth = clone $currentDate;
    $nextMonth->modify('+1 month');
    $suggestions[] = [
        'name' => $nextMonth->format('F Y'),
        'period' => $nextMonth->format('Y-m'),
        'start_date' => $nextMonth->format('Y-m-01'),
        'end_date' => $nextMonth->format('Y-m-t'),
        'pay_date' => $nextMonth->format('Y-m-d')
    ];
    
    return $suggestions;
}

/**
 * Check if a payroll period already exists (non-blocking check)
 */
function checkPayrollPeriodExists($companyId, $startDate, $endDate) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT period_name FROM payroll_periods 
            WHERE company_id = ? 
            AND start_date = ? 
            AND end_date = ?
            LIMIT 1
        ");
        $stmt->execute([$companyId, $startDate, $endDate]);
        $existing = $stmt->fetch();
        
        return $existing ? $existing['period_name'] : false;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Auto-generate period name from dates
 */
function generatePeriodName($startDate, $endDate) {
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        // If same month, use "Month Year"
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('F Y');
        }
        
        // If different months, use "Month Year - Month Year"
        return $start->format('M Y') . ' - ' . $end->format('M Y');
        
    } catch (Exception $e) {
        return 'Payroll Period ' . date('Y-m-d');
    }
}
?>
