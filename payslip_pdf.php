<?php
/**
 * Standalone Payslip PDF Generator
 * This file handles PDF generation without going through the main page structure
 */

// Start session and include necessary files
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

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

        /* Screen Display - A4 Format */
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #006b3f;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Print/Download - 80mm Thermal Roll Format */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
                font-size: 12px;
                line-height: 1.3;
            }

            .payslip-container {
                width: 80mm;
                max-width: 80mm;
                margin: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 5mm;
            }

            .no-print {
                display: none !important;
            }
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

        /* Thermal Print Header Adjustments */
        @media print {
            .header {
                background: #000 !important;
                color: white !important;
                padding: 3mm !important;
                text-align: center;
                margin-bottom: 2mm;
            }

            .header h1 {
                font-size: 14px !important;
                margin: 0 0 1mm 0 !important;
                font-weight: bold;
            }

            .header .company-info {
                font-size: 10px !important;
                line-height: 1.2;
                margin-top: 1mm !important;
            }
        }

        .employee-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        /* Thermal Print Employee Info */
        @media print {
            .employee-info {
                display: block !important;
                padding: 2mm !important;
                background: white !important;
                border: none !important;
                margin-bottom: 2mm;
            }
        }
        
        .info-section h3 {
            margin: 0 0 10px 0;
            color: #006b3f;
            font-size: 16px;
            border-bottom: 1px solid #006b3f;
            padding-bottom: 5px;
        }

        /* Thermal Print Info Sections */
        @media print {
            .info-section {
                margin-bottom: 3mm !important;
            }

            .info-section h3 {
                font-size: 11px !important;
                margin: 0 0 1mm 0 !important;
                border-bottom: 1px solid #000 !important;
                padding-bottom: 0.5mm !important;
                color: #000 !important;
                font-weight: bold;
            }
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

        /* Thermal Print Info Rows */
        @media print {
            .info-row {
                display: flex !important;
                justify-content: space-between !important;
                margin-bottom: 1mm !important;
                font-size: 9px !important;
                border-bottom: 1px dotted #ccc !important;
                padding-bottom: 0.5mm !important;
            }

            .info-label {
                font-weight: bold !important;
                color: #000 !important;
            }
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

        /* Thermal Print Earnings/Deductions */
        @media print {
            .earnings-deductions {
                display: block !important;
                margin-bottom: 3mm !important;
            }
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

        /* Thermal Print Sections */
        @media print {
            .section {
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                margin-bottom: 2mm !important;
            }

            .section-header {
                background: #000 !important;
                color: white !important;
                padding: 1mm !important;
                font-size: 10px !important;
                text-align: center;
                font-weight: bold;
            }

            .section-content {
                padding: 1mm !important;
            }
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

        /* Thermal Print Amount Rows and Net Pay */
        @media print {
            .amount-row {
                display: flex !important;
                justify-content: space-between !important;
                margin-bottom: 1mm !important;
                padding: 0.5mm 0 !important;
                border-bottom: 1px dotted #ccc !important;
                font-size: 9px !important;
            }

            .amount-row:last-child {
                border-top: 1px solid #000 !important;
                padding-top: 1mm !important;
                margin-top: 1mm !important;
                font-weight: bold !important;
            }

            .net-pay {
                background: white !important;
                border: 2px solid #000 !important;
                border-radius: 0 !important;
                padding: 2mm !important;
                text-align: center;
                margin-top: 2mm !important;
            }

            .net-pay h3 {
                margin: 0 0 1mm 0 !important;
                font-size: 12px !important;
                font-weight: bold !important;
                color: #000 !important;
            }

            .net-pay .amount {
                font-size: 16px !important;
                font-weight: bold !important;
                color: #000 !important;
            }
        }

        .footer {
            background: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        /* Thermal Print Footer */
        @media print {
            .footer {
                background: white !important;
                padding: 2mm !important;
                border-top: 1px solid #000 !important;
                text-align: center;
                font-size: 8px !important;
                color: #000 !important;
                margin-top: 2mm !important;
            }
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

        .print-info {
            position: fixed;
            top: 70px;
            right: 20px;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            max-width: 200px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Thermal (80mm)</button>
    <div class="print-info no-print">
        <strong>Print Format:</strong><br>
        📺 Screen: A4 size<br>
        🖨️ Print: 80mm thermal roll
    </div>
    
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
