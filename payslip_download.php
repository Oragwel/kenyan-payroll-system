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
    header('Location: index.php?page=payslips');
    exit;
}

// Generate filename
$filename = 'Payslip_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip['first_name'] . '_' . $payslip['last_name']) . '_' . date('Y-m-d', strtotime($payslip['pay_date'])) . '.pdf';

// Check if we have data
if (!$payslip) {
    die("No payslip data found for ID: " . $payslipId);
}

// Create a simple text-based PDF-like content
$content = generatePayslipText($payslip);

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

// Output the content
echo $content;
exit;

function generatePayslipText($payslip) {
    $text = "";

    // PDF Header (basic PDF structure)
    $text .= "%PDF-1.4\n";
    $text .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
    $text .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    $text .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 595 842]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n>>\n>>\n>>\nendobj\n";

    // Content stream
    $content = "BT\n/F1 12 Tf\n";
    $y = 800;

    // Company header
    $content .= "50 $y Td\n";
    $content .= "(" . ($payslip['company_name'] ?? 'Company') . ") Tj\n";
    $y -= 20;
    $content .= "0 -20 Td\n";
    $content .= "(PAYSLIP) Tj\n";
    $y -= 30;

    // Employee info
    $content .= "0 -30 Td\n";
    $content .= "(Employee: " . ($payslip['first_name'] ?? '') . " " . ($payslip['last_name'] ?? '') . ") Tj\n";
    $content .= "0 -15 Td\n";
    $content .= "(ID: " . ($payslip['emp_id'] ?? 'N/A') . ") Tj\n";
    $content .= "0 -15 Td\n";
    $content .= "(Department: " . ($payslip['department'] ?? 'N/A') . ") Tj\n";

    // Pay period
    $content .= "0 -20 Td\n";
    $startDate = $payslip['pay_period_start'] ?? null;
    $endDate = $payslip['pay_period_end'] ?? null;
    if ($startDate && $endDate) {
        $content .= "(Pay Period: " . date('d/m/Y', strtotime($startDate)) . " - " . date('d/m/Y', strtotime($endDate)) . ") Tj\n";
    } else {
        $content .= "(Pay Period: N/A) Tj\n";
    }

    // Earnings
    $content .= "0 -25 Td\n";
    $content .= "(EARNINGS) Tj\n";
    $content .= "0 -15 Td\n";
    $content .= "(Basic Salary: KES " . number_format($payslip['basic_salary'] ?? 0, 2) . ") Tj\n";

    if (($payslip['total_allowances'] ?? 0) > 0) {
        $content .= "0 -15 Td\n";
        $content .= "(Allowances: KES " . number_format($payslip['total_allowances'], 2) . ") Tj\n";
    }

    $content .= "0 -20 Td\n";
    $content .= "(GROSS PAY: KES " . number_format($payslip['gross_pay'] ?? 0, 2) . ") Tj\n";

    // Deductions
    $content .= "0 -25 Td\n";
    $content .= "(DEDUCTIONS) Tj\n";

    if (($payslip['paye_tax'] ?? 0) > 0) {
        $content .= "0 -15 Td\n";
        $content .= "(PAYE Tax: KES " . number_format($payslip['paye_tax'], 2) . ") Tj\n";
    }

    if (($payslip['nssf_deduction'] ?? 0) > 0) {
        $content .= "0 -15 Td\n";
        $content .= "(NSSF: KES " . number_format($payslip['nssf_deduction'], 2) . ") Tj\n";
    }

    if (($payslip['nhif_deduction'] ?? 0) > 0) {
        $content .= "0 -15 Td\n";
        $content .= "(NHIF: KES " . number_format($payslip['nhif_deduction'], 2) . ") Tj\n";
    }

    $content .= "0 -20 Td\n";
    $content .= "(TOTAL DEDUCTIONS: KES " . number_format($payslip['total_deductions'] ?? 0, 2) . ") Tj\n";

    // Net pay
    $content .= "0 -25 Td\n";
    $content .= "(NET PAY: KES " . number_format($payslip['net_pay'] ?? 0, 2) . ") Tj\n";

    $content .= "ET\n";

    // Add content object
    $text .= "4 0 obj\n<<\n/Length " . strlen($content) . "\n>>\nstream\n";
    $text .= $content;
    $text .= "endstream\nendobj\n";

    // Font object
    $text .= "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";

    // Cross-reference table
    $text .= "xref\n0 6\n";
    $text .= "0000000000 65535 f \n";
    $text .= "0000000009 00000 n \n";
    $text .= "0000000074 00000 n \n";
    $text .= "0000000120 00000 n \n";
    $text .= "0000000179 00000 n \n";
    $text .= sprintf("%010d 00000 n \n", strlen($content) + 200);

    // Trailer
    $text .= "trailer\n<<\n/Size 6\n/Root 1 0 R\n>>\n";
    $text .= "startxref\n" . (strlen($content) + 300) . "\n%%EOF\n";

    return $text;
}

?>

