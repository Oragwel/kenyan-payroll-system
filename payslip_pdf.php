<?php
/**
 * Standalone Payslip PDF Generator
 * This file handles PDF generation without going through the main page structure
 */

// Start session and include necessary files
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Security check
if (!isset($_SESSION['user_id'])) {
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
    SELECT pr.*, pp.period_name, pp.pay_date,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_number, e.id_number, e.phone, e.email,
           d.name as department_name, p.title as position_title,
           c.name as company_name, c.address as company_address
    FROM payroll_records pr
    JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
    JOIN employees e ON pr.employee_id = e.id
    JOIN companies c ON e.company_id = c.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN job_positions p ON e.position_id = p.id
    WHERE pr.id = ? AND e.company_id = ?
");
$stmt->execute([$payslipId, $_SESSION['company_id']]);
$payslip = $stmt->fetch();

if (!$payslip) {
    header('Location: index.php?page=payslips');
    exit;
}

// Security check - employees can only view their own payslips
if ($_SESSION['user_role'] === 'employee' && $payslip['employee_id'] != $_SESSION['employee_id']) {
    header('Location: index.php?page=payslips');
    exit;
}

// Clear any output buffer and set headers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($payslip['employee_name']); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #006b3f;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #006b3f, #004d2e);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header .company-info {
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .employee-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-section h3 {
            margin: 0 0 10px 0;
            color: #006b3f;
            font-size: 16px;
            border-bottom: 1px solid #006b3f;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .payslip-details {
            padding: 20px;
        }
        
        .earnings-deductions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .section {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .section-header {
            background: #006b3f;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            text-align: center;
        }
        
        .section-content {
            padding: 15px;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        
        .amount-row:last-child {
            border-bottom: none;
            font-weight: bold;
            border-top: 2px solid #006b3f;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .net-pay {
            background: #e8f5e8;
            border: 2px solid #006b3f;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            margin-top: 20px;
        }
        
        .net-pay h3 {
            margin: 0 0 10px 0;
            color: #006b3f;
        }
        
        .net-pay .amount {
            font-size: 24px;
            font-weight: bold;
            color: #006b3f;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            body { margin: 0; padding: 10px; }
            .payslip-container { border: 1px solid #000; }
            .no-print { display: none; }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #006b3f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .print-button:hover {
            background: #004d2e;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Payslip</button>
    
    <div class="payslip-container">
        <div class="header">
            <h1>PAYSLIP</h1>
            <div class="company-info">
                <strong><?php echo htmlspecialchars($payslip['company_name'] ?? 'Company Name'); ?></strong><br>
                <?php echo htmlspecialchars($payslip['company_address'] ?? 'Company Address'); ?>
            </div>
        </div>
        
        <div class="employee-info">
            <div class="info-section">
                <h3>Employee Information</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span><?php echo htmlspecialchars($payslip['employee_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee No:</span>
                    <span><?php echo htmlspecialchars($payslip['employee_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID Number:</span>
                    <span><?php echo htmlspecialchars($payslip['id_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span><?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Position:</span>
                    <span><?php echo htmlspecialchars($payslip['position_title'] ?? 'N/A'); ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Pay Period Information</h3>
                <div class="info-row">
                    <span class="info-label">Period:</span>
                    <span><?php echo htmlspecialchars($payslip['period_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pay Date:</span>
                    <span><?php echo date('F j, Y', strtotime($payslip['pay_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Days Worked:</span>
                    <span><?php echo $payslip['days_worked']; ?> days</span>
                </div>
                <?php if ($payslip['overtime_hours'] > 0): ?>
                <div class="info-row">
                    <span class="info-label">Overtime Hours:</span>
                    <span><?php echo $payslip['overtime_hours']; ?> hours</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="payslip-details">
            <div class="earnings-deductions">
                <div class="section">
                    <div class="section-header">EARNINGS</div>
                    <div class="section-content">
                        <div class="amount-row">
                            <span>Basic Salary</span>
                            <span><?php echo formatCurrency($payslip['basic_salary']); ?></span>
                        </div>
                        <?php if ($payslip['overtime_amount'] > 0): ?>
                        <div class="amount-row">
                            <span>Overtime Pay</span>
                            <span><?php echo formatCurrency($payslip['overtime_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payslip['total_allowances'] > 0): ?>
                        <div class="amount-row">
                            <span>Allowances</span>
                            <span><?php echo formatCurrency($payslip['total_allowances']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="amount-row">
                            <span><strong>GROSS PAY</strong></span>
                            <span><strong><?php echo formatCurrency($payslip['gross_pay']); ?></strong></span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-header">DEDUCTIONS</div>
                    <div class="section-content">
                        <div class="amount-row">
                            <span>PAYE Tax</span>
                            <span><?php echo formatCurrency($payslip['paye_tax']); ?></span>
                        </div>
                        <div class="amount-row">
                            <span>NSSF</span>
                            <span><?php echo formatCurrency($payslip['nssf_deduction']); ?></span>
                        </div>
                        <div class="amount-row">
                            <span>NHIF</span>
                            <span><?php echo formatCurrency($payslip['nhif_deduction']); ?></span>
                        </div>
                        <?php if ($payslip['housing_levy'] > 0): ?>
                        <div class="amount-row">
                            <span>Housing Levy</span>
                            <span><?php echo formatCurrency($payslip['housing_levy']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payslip['total_deductions'] > ($payslip['paye_tax'] + $payslip['nssf_deduction'] + $payslip['nhif_deduction'] + $payslip['housing_levy'])): ?>
                        <div class="amount-row">
                            <span>Other Deductions</span>
                            <span><?php echo formatCurrency($payslip['total_deductions'] - ($payslip['paye_tax'] + $payslip['nssf_deduction'] + $payslip['nhif_deduction'] + $payslip['housing_levy'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="amount-row">
                            <span><strong>TOTAL DEDUCTIONS</strong></span>
                            <span><strong><?php echo formatCurrency($payslip['total_deductions']); ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="net-pay">
                <h3>NET PAY</h3>
                <div class="amount"><?php echo formatCurrency($payslip['net_pay']); ?></div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated payslip. No signature is required.</p>
            <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>
    </div>
    
    <script>
        // Auto-focus for printing
        window.onload = function() {
            // Optional: Auto-print when page loads
            // window.print();
        };
    </script>
</body>
</html>
