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

// For debugging - let's first check if we have data
if (!$payslip) {
    die("No payslip data found for ID: " . $payslipId);
}

// Set headers for HTML download (not PDF for now, to debug)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
header('Cache-Control: no-cache, must-revalidate');

// Start output buffering
ob_start();

// Generate HTML content for PDF
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: 80mm auto;
            margin: 2mm;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 8px;
            line-height: 1.1;
            margin: 0;
            padding: 2mm;
            color: #000;
            background: white;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }
        .company-name {
            font-size: 10px;
            font-weight: bold;
        }
        .section {
            margin: 2mm 0;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            margin: 1mm 0;
            text-decoration: underline;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 0.5mm 0;
        }
        .total-row {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 1mm 0;
            font-weight: bold;
        }
        .net-pay {
            text-align: center;
            border: 1px solid #000;
            padding: 2mm;
            margin: 2mm 0;
            font-weight: bold;
            font-size: 10px;
        }
        .footer {
            text-align: center;
            font-size: 6px;
            margin-top: 2mm;
            border-top: 1px solid #000;
            padding-top: 1mm;
        }
    </style>
</head>
<body>
    <!-- Debug: Show all payslip data -->
    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 10px;">
        <strong>DEBUG INFO:</strong><br>
        <?php
        echo "Payslip ID: " . $payslipId . "<br>";
        echo "Company ID: " . $_SESSION['company_id'] . "<br>";
        echo "Data found: " . (empty($payslip) ? 'NO' : 'YES') . "<br>";
        if (!empty($payslip)) {
            echo "Employee: " . ($payslip['first_name'] ?? 'N/A') . " " . ($payslip['last_name'] ?? 'N/A') . "<br>";
            echo "Employee Number: " . ($payslip['emp_id'] ?? 'N/A') . "<br>";
            echo "Department: " . ($payslip['department'] ?? 'N/A') . "<br>";
            echo "Basic Salary: " . ($payslip['basic_salary'] ?? 'N/A') . "<br>";
        }
        ?>
    </div>

    <div class="header">
        <div class="company-name"><?php echo htmlspecialchars($payslip['company_name'] ?? 'Company Name'); ?></div>
        <div><?php echo htmlspecialchars($payslip['company_address'] ?? 'Company Address'); ?></div>
        <div>Tel: <?php echo htmlspecialchars($payslip['company_phone'] ?? 'Phone'); ?></div>
    </div>
    
    <div class="section-title">PAYSLIP</div>
    
    <div class="section">
        <div class="row">
            <span>Employee:</span>
            <span><?php echo htmlspecialchars(($payslip['first_name'] ?? '') . ' ' . ($payslip['last_name'] ?? '')); ?></span>
        </div>
        <div class="row">
            <span>ID:</span>
            <span><?php echo htmlspecialchars($payslip['emp_id'] ?? 'N/A'); ?></span>
        </div>
        <div class="row">
            <span>Department:</span>
            <span><?php echo htmlspecialchars($payslip['department'] ?? 'N/A'); ?></span>
        </div>
        <div class="row">
            <span>Position:</span>
            <span><?php echo htmlspecialchars($payslip['position'] ?? 'N/A'); ?></span>
        </div>
    </div>
    
    <div class="section">
        <div class="row">
            <span>Pay Period:</span>
            <span><?php
                $start = $payslip['pay_period_start'] ?? null;
                $end = $payslip['pay_period_end'] ?? null;
                if ($start && $end) {
                    echo date('d/m/Y', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end));
                } else {
                    echo 'N/A';
                }
            ?></span>
        </div>
        <div class="row">
            <span>Pay Date:</span>
            <span><?php
                $payDate = $payslip['pay_date'] ?? null;
                echo $payDate ? date('d/m/Y', strtotime($payDate)) : 'N/A';
            ?></span>
        </div>
    </div>
    
    <div class="section-title">EARNINGS</div>
    <div class="section">
        <div class="row">
            <span>Basic Salary:</span>
            <span>KES <?php echo number_format($payslip['basic_salary'] ?? 0, 2); ?></span>
        </div>
        <?php if (($payslip['total_allowances'] ?? 0) > 0): ?>
        <div class="row">
            <span>Allowances:</span>
            <span>KES <?php echo number_format($payslip['total_allowances'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if (($payslip['overtime_amount'] ?? 0) > 0): ?>
        <div class="row">
            <span>Overtime:</span>
            <span>KES <?php echo number_format($payslip['overtime_amount'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="row total-row">
            <span>GROSS PAY:</span>
            <span>KES <?php echo number_format($payslip['gross_pay'] ?? 0, 2); ?></span>
        </div>
    </div>
    
    <div class="section-title">DEDUCTIONS</div>
    <div class="section">
        <?php if ($payslip['paye_tax'] > 0): ?>
        <div class="row">
            <span>PAYE Tax:</span>
            <span>KES <?php echo number_format($payslip['paye_tax'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['nssf_deduction'] > 0): ?>
        <div class="row">
            <span>NSSF:</span>
            <span>KES <?php echo number_format($payslip['nssf_deduction'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['nhif_deduction'] > 0): ?>
        <div class="row">
            <span>NHIF:</span>
            <span>KES <?php echo number_format($payslip['nhif_deduction'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['housing_levy'] > 0): ?>
        <div class="row">
            <span>Housing Levy:</span>
            <span>KES <?php echo number_format($payslip['housing_levy'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="row total-row">
            <span>TOTAL DEDUCTIONS:</span>
            <span>KES <?php echo number_format($payslip['total_deductions'], 2); ?></span>
        </div>
    </div>
    
    <div class="net-pay">
        <div>NET PAY</div>
        <div style="font-size: 12px;">KES <?php echo number_format($payslip['net_pay'], 2); ?></div>
    </div>
    
    <div class="footer">
        Generated on <?php echo date('d/m/Y H:i'); ?><br>
        This is a computer generated payslip
    </div>
</body>
</html>
<?php

// Get the HTML content
$html = ob_get_clean();

// For now, output as HTML with PDF headers
// In production, you would use wkhtmltopdf or similar
echo $html;
?>
