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

// Create PDF using basic HTML to PDF conversion
// Since we don't have TCPDF installed, we'll use a simple approach with DomPDF-like functionality

// Start output buffering to capture HTML
ob_start();

// Generate the PDF content as HTML with specific PDF styling
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 5mm;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            color: #000;
            background: white;
        }
        
        .payslip-container {
            width: 70mm;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
        }
        
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 1mm;
        }
        
        .company-info {
            font-size: 8px;
            line-height: 1.1;
        }
        
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2mm 0 1mm 0;
            text-decoration: underline;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            font-size: 9px;
        }
        
        .info-label {
            font-weight: bold;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5mm;
            font-size: 9px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 0.5mm;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 1mm 0;
            margin: 2mm 0;
        }
        
        .net-pay {
            text-align: center;
            border: 2px solid #000;
            padding: 2mm;
            margin: 2mm 0;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            font-size: 7px;
            margin-top: 3mm;
            border-top: 1px solid #000;
            padding-top: 1mm;
        }
        
        .separator {
            border-bottom: 1px solid #000;
            margin: 2mm 0;
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name"><?php echo htmlspecialchars($payslip['company_name']); ?></div>
            <div class="company-info">
                <?php echo htmlspecialchars($payslip['company_address']); ?><br>
                Tel: <?php echo htmlspecialchars($payslip['company_phone']); ?>
            </div>
        </div>
        
        <div class="section-title">PAYSLIP</div>
        
        <!-- Employee Information -->
        <div class="info-row">
            <span class="info-label">Employee:</span>
            <span><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">ID:</span>
            <span><?php echo htmlspecialchars($payslip['emp_id']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Department:</span>
            <span><?php echo htmlspecialchars($payslip['department']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Position:</span>
            <span><?php echo htmlspecialchars($payslip['position']); ?></span>
        </div>
        
        <div class="separator"></div>
        
        <!-- Pay Period -->
        <div class="info-row">
            <span class="info-label">Pay Period:</span>
            <span><?php echo date('d/m/Y', strtotime($payslip['pay_period_start'])) . ' - ' . date('d/m/Y', strtotime($payslip['pay_period_end'])); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Pay Date:</span>
            <span><?php echo date('d/m/Y', strtotime($payslip['pay_date'])); ?></span>
        </div>
        
        <div class="separator"></div>
        
        <!-- Earnings -->
        <div class="section-title">EARNINGS</div>
        <div class="amount-row">
            <span>Basic Salary:</span>
            <span>KES <?php echo number_format($payslip['basic_salary'], 2); ?></span>
        </div>
        <?php if ($payslip['allowances'] > 0): ?>
        <div class="amount-row">
            <span>Allowances:</span>
            <span>KES <?php echo number_format($payslip['allowances'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['overtime_pay'] > 0): ?>
        <div class="amount-row">
            <span>Overtime:</span>
            <span>KES <?php echo number_format($payslip['overtime_pay'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row">
            <span>GROSS PAY:</span>
            <span>KES <?php echo number_format($payslip['gross_pay'], 2); ?></span>
        </div>
        
        <!-- Deductions -->
        <div class="section-title">DEDUCTIONS</div>
        <?php if ($payslip['paye_tax'] > 0): ?>
        <div class="amount-row">
            <span>PAYE Tax:</span>
            <span>KES <?php echo number_format($payslip['paye_tax'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['nssf_deduction'] > 0): ?>
        <div class="amount-row">
            <span>NSSF:</span>
            <span>KES <?php echo number_format($payslip['nssf_deduction'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['nhif_deduction'] > 0): ?>
        <div class="amount-row">
            <span>NHIF:</span>
            <span>KES <?php echo number_format($payslip['nhif_deduction'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['housing_levy'] > 0): ?>
        <div class="amount-row">
            <span>Housing Levy:</span>
            <span>KES <?php echo number_format($payslip['housing_levy'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payslip['other_deductions'] > 0): ?>
        <div class="amount-row">
            <span>Other Deductions:</span>
            <span>KES <?php echo number_format($payslip['other_deductions'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row">
            <span>TOTAL DEDUCTIONS:</span>
            <span>KES <?php echo number_format($payslip['total_deductions'], 2); ?></span>
        </div>
        
        <!-- Net Pay -->
        <div class="net-pay">
            <div style="font-size: 11px;">NET PAY</div>
            <div style="font-size: 14px; margin-top: 1mm;">KES <?php echo number_format($payslip['net_pay'], 2); ?></div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Generated on <?php echo date('d/m/Y H:i'); ?><br>
            This is a computer generated payslip
        </div>
    </div>
</body>
</html>
<?php

// Get the HTML content
$html = ob_get_clean();

// Set headers for PDF download
$filename = 'Payslip_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payslip['first_name'] . '_' . $payslip['last_name']) . '_' . date('Y-m-d', strtotime($payslip['pay_date'])) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

// For now, we'll output the HTML with PDF-like styling
// In a production environment, you would use a proper PDF library like TCPDF or DomPDF
echo $html;
?>
