<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=auth');
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    header('Location: index.php?page=payslips&error=database');
    exit;
}

$payslipId = $_GET['payslip_id'] ?? null;

if (!$payslipId) {
    header('Location: index.php?page=payslips');
    exit;
}

// Get payslip data
$stmt = $db->prepare("
    SELECT pr.*, e.first_name, e.last_name, e.employee_number as emp_id, 
           d.name as department, p.title as position,
           pp.start_date as pay_period_start, pp.end_date as pay_period_end, pp.pay_date,
           c.name as company_name, c.address as company_address, c.phone as company_phone
    FROM payroll_records pr
    JOIN employees e ON pr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN job_positions p ON e.position_id = p.id
    JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
    JOIN companies c ON e.company_id = c.id
    WHERE pr.id = ? AND e.company_id = ?
");
$stmt->execute([$payslipId, $_SESSION['company_id']]);
$payslip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payslip) {
    die("No payslip data found for ID: " . $payslipId);
}

// Generate filename
$filename = 'Payslip_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip['first_name'] . '_' . $payslip['last_name']) . '_' . date('Y-m-d', strtotime($payslip['pay_date'])) . '.txt';

// Set headers for text download (readable format)
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

// Generate text-based payslip content
echo "=====================================\n";
echo "           PAYSLIP                   \n";
echo "=====================================\n\n";

echo strtoupper($payslip['company_name']) . "\n";
echo $payslip['company_address'] . "\n";
echo "Tel: " . $payslip['company_phone'] . "\n\n";

echo "-------------------------------------\n";
echo "EMPLOYEE INFORMATION\n";
echo "-------------------------------------\n";
echo "Name: " . $payslip['first_name'] . " " . $payslip['last_name'] . "\n";
echo "Employee ID: " . $payslip['emp_id'] . "\n";
echo "Department: " . ($payslip['department'] ?? 'N/A') . "\n";
echo "Position: " . ($payslip['position'] ?? 'N/A') . "\n\n";

echo "-------------------------------------\n";
echo "PAY PERIOD INFORMATION\n";
echo "-------------------------------------\n";
$startDate = $payslip['pay_period_start'] ?? null;
$endDate = $payslip['pay_period_end'] ?? null;
if ($startDate && $endDate) {
    echo "Pay Period: " . date('d/m/Y', strtotime($startDate)) . " - " . date('d/m/Y', strtotime($endDate)) . "\n";
} else {
    echo "Pay Period: N/A\n";
}
$payDate = $payslip['pay_date'] ?? null;
echo "Pay Date: " . ($payDate ? date('d/m/Y', strtotime($payDate)) : 'N/A') . "\n\n";

echo "=====================================\n";
echo "EARNINGS\n";
echo "=====================================\n";
echo sprintf("%-20s %15s\n", "Basic Salary:", "KES " . number_format($payslip['basic_salary'] ?? 0, 2));
if (($payslip['total_allowances'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "Allowances:", "KES " . number_format($payslip['total_allowances'], 2));
}
if (($payslip['overtime_amount'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "Overtime Pay:", "KES " . number_format($payslip['overtime_amount'], 2));
}
echo "-------------------------------------\n";
echo sprintf("%-20s %15s\n", "GROSS PAY:", "KES " . number_format($payslip['gross_pay'] ?? 0, 2));
echo "\n";

echo "=====================================\n";
echo "DEDUCTIONS\n";
echo "=====================================\n";
if (($payslip['paye_tax'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "PAYE Tax:", "KES " . number_format($payslip['paye_tax'], 2));
}
if (($payslip['nssf_deduction'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "NSSF:", "KES " . number_format($payslip['nssf_deduction'], 2));
}
if (($payslip['nhif_deduction'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "NHIF:", "KES " . number_format($payslip['nhif_deduction'], 2));
}
if (($payslip['housing_levy'] ?? 0) > 0) {
    echo sprintf("%-20s %15s\n", "Housing Levy:", "KES " . number_format($payslip['housing_levy'], 2));
}
echo "-------------------------------------\n";
echo sprintf("%-20s %15s\n", "TOTAL DEDUCTIONS:", "KES " . number_format($payslip['total_deductions'] ?? 0, 2));
echo "\n";

echo "=====================================\n";
echo "           NET PAY                   \n";
echo "=====================================\n";
echo sprintf("%37s\n", "KES " . number_format($payslip['net_pay'] ?? 0, 2));
echo "=====================================\n\n";

echo "Generated on: " . date('d/m/Y H:i:s') . "\n";
echo "This is a computer generated payslip\n";
echo "=====================================\n";
?>
