<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$payslipId = $_GET['payslip_id'] ?? null;

if (!$payslipId) {
    header('Location: index.php?page=payslips');
    exit;
}

// Get payslip data
$stmt = $db->prepare("
    SELECT pr.*, e.first_name, e.last_name, e.employee_id as emp_id, e.department, e.position,
           pp.pay_period_start, pp.pay_period_end, pp.pay_date,
           c.name as company_name, c.address as company_address, c.phone as company_phone
    FROM payroll_records pr
    JOIN employees e ON pr.employee_id = e.id
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

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
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
    <div class="header">
        <div class="company-name"><?php echo htmlspecialchars($payslip['company_name']); ?></div>
        <div><?php echo htmlspecialchars($payslip['company_address']); ?></div>
        <div>Tel: <?php echo htmlspecialchars($payslip['company_phone']); ?></div>
    </div>
    
    <div class="section-title">PAYSLIP</div>
    
    <div class="section">
        <div class="row">
            <span>Employee:</span>
            <span><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></span>
        </div>
        <div class="row">
            <span>ID:</span>
            <span><?php echo htmlspecialchars($payslip['emp_id']); ?></span>
        </div>
        <div class="row">
            <span>Department:</span>
            <span><?php echo htmlspecialchars($payslip['department']); ?></span>
        </div>
        <div class="row">
            <span>Position:</span>
            <span><?php echo htmlspecialchars($payslip['position']); ?></span>
        </div>
    </div>
    
    <div class="section">
        <div class="row">
            <span>Pay Period:</span>
            <span><?php echo date('d/m/Y', strtotime($payslip['pay_period_start'])) . ' - ' . date('d/m/Y', strtotime($payslip['pay_period_end'])); ?></span>
        </div>
        <div class="row">
            <span>Pay Date:</span>
            <span><?php echo date('d/m/Y', strtotime($payslip['pay_date'])); ?></span>
        </div>
    </div>
    
    <div class="section-title">EARNINGS</div>
    <div class="section">
        <div class="row">
            <span>Basic Salary:</span>
            <span>KES <?php echo number_format($payslip['basic_salary'], 2); ?></span>
        </div>
        <?php if ($payslip['allowances'] > 0): ?>
        <div class="row">
            <span>Allowances:</span>
            <span>KES <?php echo number_format($payslip['allowances'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['overtime_pay'] > 0): ?>
        <div class="row">
            <span>Overtime:</span>
            <span>KES <?php echo number_format($payslip['overtime_pay'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="row total-row">
            <span>GROSS PAY:</span>
            <span>KES <?php echo number_format($payslip['gross_pay'], 2); ?></span>
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
        <?php if ($payslip['other_deductions'] > 0): ?>
        <div class="row">
            <span>Other Deductions:</span>
            <span>KES <?php echo number_format($payslip['other_deductions'], 2); ?></span>
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
