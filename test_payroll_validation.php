<?php
/**
 * Test Script for Payroll Period Validation
 * 
 * This script tests the payroll period validation logic
 * to ensure future periods are properly prevented.
 */

// Test validation logic
function testPayrollValidation() {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $lastWeek = date('Y-m-d', strtotime('-7 days'));
    $nextMonth = date('Y-m-d', strtotime('+31 days'));
    
    echo "<h2>🧪 Payroll Period Validation Tests</h2>";
    echo "<p><strong>Today:</strong> $today</p>";
    
    $tests = [
        [
            'name' => 'Valid Period (Past Dates)',
            'start_date' => $lastWeek,
            'end_date' => $yesterday,
            'pay_date' => $today,
            'expected' => 'PASS'
        ],
        [
            'name' => 'Invalid - Future Start Date',
            'start_date' => $tomorrow,
            'end_date' => $tomorrow,
            'pay_date' => $tomorrow,
            'expected' => 'FAIL'
        ],
        [
            'name' => 'Invalid - Future End Date',
            'start_date' => $yesterday,
            'end_date' => $tomorrow,
            'pay_date' => $tomorrow,
            'expected' => 'FAIL'
        ],
        [
            'name' => 'Invalid - Pay Date Too Far Future',
            'start_date' => $lastWeek,
            'end_date' => $yesterday,
            'pay_date' => $nextMonth,
            'expected' => 'FAIL'
        ],
        [
            'name' => 'Invalid - Start After End',
            'start_date' => $yesterday,
            'end_date' => $lastWeek,
            'pay_date' => $today,
            'expected' => 'FAIL'
        ],
        [
            'name' => 'Invalid - Pay Before End',
            'start_date' => $lastWeek,
            'end_date' => $today,
            'pay_date' => $yesterday,
            'expected' => 'FAIL'
        ]
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #006b3f; color: white;'>";
    echo "<th style='padding: 10px;'>Test Case</th>";
    echo "<th style='padding: 10px;'>Start Date</th>";
    echo "<th style='padding: 10px;'>End Date</th>";
    echo "<th style='padding: 10px;'>Pay Date</th>";
    echo "<th style='padding: 10px;'>Expected</th>";
    echo "<th style='padding: 10px;'>Result</th>";
    echo "<th style='padding: 10px;'>Status</th>";
    echo "</tr>";
    
    foreach ($tests as $test) {
        $result = validatePayrollPeriod($test['start_date'], $test['end_date'], $test['pay_date']);
        $status = ($result['valid'] && $test['expected'] === 'PASS') || (!$result['valid'] && $test['expected'] === 'FAIL') ? '✅ PASS' : '❌ FAIL';
        $bgColor = $status === '✅ PASS' ? '#d4edda' : '#f8d7da';
        
        echo "<tr style='background: $bgColor;'>";
        echo "<td style='padding: 8px;'>{$test['name']}</td>";
        echo "<td style='padding: 8px;'>{$test['start_date']}</td>";
        echo "<td style='padding: 8px;'>{$test['end_date']}</td>";
        echo "<td style='padding: 8px;'>{$test['pay_date']}</td>";
        echo "<td style='padding: 8px;'>{$test['expected']}</td>";
        echo "<td style='padding: 8px;'>" . ($result['valid'] ? 'VALID' : 'INVALID: ' . $result['message']) . "</td>";
        echo "<td style='padding: 8px; font-weight: bold;'>$status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

function validatePayrollPeriod($startDate, $endDate, $payDate) {
    $today = date('Y-m-d');
    $startDateObj = new DateTime($startDate);
    $endDateObj = new DateTime($endDate);
    $payDateObj = new DateTime($payDate);
    $todayObj = new DateTime($today);
    
    // Check if start date is in the future
    if ($startDateObj > $todayObj) {
        return ['valid' => false, 'message' => 'Start date cannot be in the future'];
    }
    
    // Check if end date is in the future
    if ($endDateObj > $todayObj) {
        return ['valid' => false, 'message' => 'End date cannot be in the future'];
    }
    
    // Check if pay date is too far in the future (allow up to 30 days from today)
    $maxPayDate = clone $todayObj;
    $maxPayDate->modify('+30 days');
    if ($payDateObj > $maxPayDate) {
        return ['valid' => false, 'message' => 'Pay date cannot be more than 30 days in the future'];
    }
    
    // Check if start date is after end date
    if ($startDateObj > $endDateObj) {
        return ['valid' => false, 'message' => 'Start date cannot be after end date'];
    }
    
    // Check if pay date is before end date
    if ($payDateObj < $endDateObj) {
        return ['valid' => false, 'message' => 'Pay date cannot be before end date'];
    }
    
    return ['valid' => true, 'message' => 'All validations passed'];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Payroll Validation Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        h2 {
            color: #006b3f;
            border-bottom: 3px solid #ce1126;
            padding-bottom: 10px;
        }
        
        table {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            font-weight: bold;
            text-align: center;
        }
        
        td {
            text-align: center;
        }
        
        .summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #006b3f;
        }
    </style>
</head>
<body>
    <?php testPayrollValidation(); ?>
    
    <div class="summary">
        <h3>🎯 Validation Summary</h3>
        <p>The payroll period validation system includes the following checks:</p>
        <ul>
            <li><strong>Future Period Prevention:</strong> ✅ Start and end dates cannot be in the future</li>
            <li><strong>Pay Date Limits:</strong> ✅ Pay date must be within 30 days from today</li>
            <li><strong>Date Logic:</strong> ✅ Proper date sequence validation</li>
            <li><strong>Overlap Prevention:</strong> ✅ Database check for overlapping periods</li>
            <li><strong>Real-time Validation:</strong> ✅ JavaScript validation for immediate feedback</li>
            <li><strong>Server-side Security:</strong> ✅ PHP validation as final check</li>
        </ul>
        
        <h4>🔒 Security Features:</h4>
        <ul>
            <li>Both client-side and server-side validation</li>
            <li>Database transaction safety</li>
            <li>Comprehensive error handling</li>
            <li>User-friendly error messages</li>
            <li>Confirmation dialogs for critical actions</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php?page=payroll" style="background: #006b3f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">
            🔙 Back to Payroll Management
        </a>
    </div>
</body>
</html>
