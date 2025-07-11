<?php
/**
 * Payslips Viewer - View and download payslips
 */

$action = $_GET['action'] ?? 'list';
$employeeId = $_GET['employee_id'] ?? $_SESSION['employee_id'] ?? null;
$payslipId = $_GET['payslip_id'] ?? null;

// Security check - employees can only view their own payslips
if ($_SESSION['user_role'] === 'employee' && $employeeId != $_SESSION['employee_id']) {
    header('Location: index.php?page=payslips');
    exit;
}

// Get employee info if viewing specific employee's payslips
$employee = null;
if ($employeeId) {
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, p.title as position_title
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN job_positions p ON e.position_id = p.id
        WHERE e.id = ? AND e.company_id = ?
    ");
    $stmt->execute([$employeeId, $_SESSION['company_id']]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: index.php?page=payslips');
        exit;
    }
}

// Get payslips based on user role and filters
if ($action === 'view' && $payslipId) {
    // Get specific payslip
    $stmt = $db->prepare("
        SELECT pr.*, pp.period_name, pp.pay_date, pp.start_date, pp.end_date,
               e.employee_number, e.first_name, e.last_name, e.id_number,
               d.name as department_name, p.title as position_title
        FROM payroll_records pr
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        JOIN employees e ON pr.employee_id = e.id
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
    
    // Security check for employees
    if ($_SESSION['user_role'] === 'employee' && $payslip['employee_id'] != $_SESSION['employee_id']) {
        header('Location: index.php?page=payslips');
        exit;
    }
} else {
    // Get payslips list
    if (hasPermission('hr') && !$employeeId) {
        // HR can see all payslips
        $stmt = $db->prepare("
            SELECT pr.*, pp.period_name, pp.pay_date,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                   e.employee_number, e.id as employee_id
            FROM payroll_records pr
            JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
            JOIN employees e ON pr.employee_id = e.id
            WHERE e.company_id = ?
            ORDER BY pp.pay_date DESC, e.employee_number
        ");
        $stmt->execute([$_SESSION['company_id']]);
    } else {
        // Employee or specific employee payslips
        $targetEmployeeId = $employeeId ?: $_SESSION['employee_id'];
        $stmt = $db->prepare("
            SELECT pr.*, pp.period_name, pp.pay_date, pp.start_date, pp.end_date,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                   e.employee_number
            FROM payroll_records pr
            JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
            JOIN employees e ON pr.employee_id = e.id
            WHERE pr.employee_id = ? AND e.company_id = ?
            ORDER BY pp.pay_date DESC
        ");
        $stmt->execute([$targetEmployeeId, $_SESSION['company_id']]);
    }
    $payslips = $stmt->fetchAll();
}

/**
 * Generate PDF payslip
 */
function generatePayslipPDF($payslip) {
    // This would integrate with a PDF library like TCPDF or FPDF
    // For now, we'll create a simple HTML version that can be printed
    header('Content-Type: text/html');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Payslip - ' . htmlspecialchars($payslip['employee_name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #006b3f; padding-bottom: 10px; }
            .payslip-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .payslip-table th, .payslip-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .payslip-table th { background-color: #006b3f; color: white; }
            .total-row { background-color: #f0f8f0; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>PAYSLIP</h2>
            <p>Period: ' . htmlspecialchars($payslip['period_name']) . '</p>
            <p>Pay Date: ' . formatDate($payslip['pay_date']) . '</p>
        </div>
        
        <table class="payslip-table">
            <tr><th colspan="2">Employee Information</th></tr>
            <tr><td>Name</td><td>' . htmlspecialchars($payslip['employee_name']) . '</td></tr>
            <tr><td>Employee Number</td><td>' . htmlspecialchars($payslip['employee_number']) . '</td></tr>
            <tr><td>Department</td><td>' . htmlspecialchars($payslip['department_name'] ?? 'N/A') . '</td></tr>
            <tr><td>Position</td><td>' . htmlspecialchars($payslip['position_title'] ?? 'N/A') . '</td></tr>
        </table>
        
        <table class="payslip-table">
            <tr><th colspan="2">Earnings</th></tr>
            <tr><td>Basic Salary</td><td>' . formatCurrency($payslip['basic_salary']) . '</td></tr>
            <tr><td>Total Allowances</td><td>' . formatCurrency($payslip['total_allowances']) . '</td></tr>
            <tr><td>Overtime</td><td>' . formatCurrency($payslip['overtime_amount']) . '</td></tr>
            <tr class="total-row"><td>Gross Pay</td><td>' . formatCurrency($payslip['gross_pay']) . '</td></tr>
        </table>
        
        <table class="payslip-table">
            <tr><th colspan="2">Deductions</th></tr>
            <tr><td>PAYE Tax</td><td>' . formatCurrency($payslip['paye_tax']) . '</td></tr>
            <tr><td>NSSF</td><td>' . formatCurrency($payslip['nssf_deduction']) . '</td></tr>
            <tr><td>SHIF/NHIF</td><td>' . formatCurrency($payslip['nhif_deduction']) . '</td></tr>
            <tr><td>Housing Levy</td><td>' . formatCurrency($payslip['housing_levy']) . '</td></tr>
            <tr class="total-row"><td>Total Deductions</td><td>' . formatCurrency($payslip['total_deductions']) . '</td></tr>
        </table>
        
        <table class="payslip-table">
            <tr class="total-row"><th>NET PAY</th><th>' . formatCurrency($payslip['net_pay']) . '</th></tr>
        </table>
        
        <div class="no-print" style="margin-top: 20px; text-align: center;">
            <button onclick="window.print()">Print Payslip</button>
            <button onclick="window.close()">Close</button>
        </div>
    </body>
    </html>';
    exit;
}

// Handle PDF generation
if ($action === 'pdf' && $payslipId) {
    $stmt = $db->prepare("
        SELECT pr.*, pp.period_name, pp.pay_date,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               e.employee_number, d.name as department_name, p.title as position_title
        FROM payroll_records pr
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        JOIN employees e ON pr.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN job_positions p ON e.position_id = p.id
        WHERE pr.id = ? AND e.company_id = ?
    ");
    $stmt->execute([$payslipId, $_SESSION['company_id']]);
    $payslip = $stmt->fetch();
    
    if ($payslip) {
        generatePayslipPDF($payslip);
    }
}
?>

<!-- Payslips Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.payslip-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.payslip-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.payslip-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.payslip-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--kenya-green);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.payslip-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.payslip-detail {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.earnings-section {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.deductions-section {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.net-pay-section {
    background: linear-gradient(135deg, #000000, #333333);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.amount {
    font-size: 1.25rem;
    font-weight: 700;
}

.btn-download {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(206,17,38,0.3);
    color: white;
}
</style>

<div class="container-fluid">
    <!-- Payslips Hero Section -->
    <div class="payslip-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-receipt me-3"></i>
                        <?php if ($action === 'view'): ?>
                            Payslip Details
                        <?php else: ?>
                            Payslips
                        <?php endif; ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        💰 View and download your payroll information
                        <?php if ($employee): ?>
                            - <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($action === 'view'): ?>
                        <a href="index.php?page=payslips&action=pdf&payslip_id=<?php echo $payslipId; ?>" 
                           target="_blank" class="btn btn-light btn-lg">
                            <i class="fas fa-download me-2"></i>Download PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($action === 'view' && isset($payslip)): ?>
        <!-- Payslip Detail View -->
        <div class="row">
            <div class="col-lg-8">
                <div class="payslip-detail">
                    <!-- Employee Information -->
                    <div class="mb-4">
                        <h4 class="mb-3">
                            <i class="fas fa-user text-primary me-2"></i>
                            Employee Information
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></p>
                                <p><strong>Employee Number:</strong> <?php echo htmlspecialchars($payslip['employee_number']); ?></p>
                                <p><strong>ID Number:</strong> <?php echo htmlspecialchars($payslip['id_number']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($payslip['position_title'] ?? 'N/A'); ?></p>
                                <p><strong>Pay Period:</strong> <?php echo htmlspecialchars($payslip['period_name']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Earnings Section -->
                    <div class="earnings-section">
                        <h5 class="mb-3">
                            <i class="fas fa-plus-circle me-2"></i>
                            Earnings
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Basic Salary:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['basic_salary']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Allowances:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['total_allowances']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Overtime (<?php echo $payslip['overtime_hours']; ?> hrs):</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['overtime_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Days Worked:</span>
                                    <span class="amount"><?php echo $payslip['days_worked']; ?> days</span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <div class="d-flex justify-content-between">
                            <h6>Gross Pay:</h6>
                            <h6 class="amount"><?php echo formatCurrency($payslip['gross_pay']); ?></h6>
                        </div>
                    </div>

                    <!-- Deductions Section -->
                    <div class="deductions-section">
                        <h5 class="mb-3">
                            <i class="fas fa-minus-circle me-2"></i>
                            Statutory Deductions
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>PAYE Tax:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['paye_tax']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>NSSF:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['nssf_deduction']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>SHIF/NHIF:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['nhif_deduction']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Housing Levy:</span>
                                    <span class="amount"><?php echo formatCurrency($payslip['housing_levy']); ?></span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <div class="d-flex justify-content-between">
                            <h6>Total Deductions:</h6>
                            <h6 class="amount"><?php echo formatCurrency($payslip['total_deductions']); ?></h6>
                        </div>
                    </div>

                    <!-- Net Pay Section -->
                    <div class="net-pay-section">
                        <h4 class="mb-2">NET PAY</h4>
                        <h2 class="amount"><?php echo formatCurrency($payslip['net_pay']); ?></h2>
                        <small class="opacity-75">Pay Date: <?php echo formatDate($payslip['pay_date']); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Actions -->
                <div class="payslip-card">
                    <div class="p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-tools text-primary me-2"></i>
                            Actions
                        </h5>
                        
                        <div class="d-grid gap-2">
                            <a href="index.php?page=payslips&action=pdf&payslip_id=<?php echo $payslipId; ?>" 
                               target="_blank" class="btn btn-download">
                                <i class="fas fa-file-pdf me-2"></i>Download PDF
                            </a>
                            
                            <a href="javascript:window.print()" class="btn btn-outline-secondary">
                                <i class="fas fa-print me-2"></i>Print Payslip
                            </a>
                            
                            <a href="index.php?page=payslips<?php echo $employeeId ? '&employee_id=' . $employeeId : ''; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Payslips
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Payslip Summary -->
                <div class="payslip-card">
                    <div class="p-4">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Payslip Summary
                        </h6>
                        
                        <div class="mb-2">
                            <small class="text-muted">Taxable Income:</small>
                            <div class="fw-bold"><?php echo formatCurrency($payslip['taxable_income']); ?></div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">Tax Rate:</small>
                            <div class="fw-bold">
                                <?php echo $payslip['taxable_income'] > 0 ? number_format(($payslip['paye_tax'] / $payslip['taxable_income']) * 100, 1) : 0; ?>%
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">Take-home %:</small>
                            <div class="fw-bold text-success">
                                <?php echo number_format(($payslip['net_pay'] / $payslip['gross_pay']) * 100, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Payslips List -->
        <div class="row">
            <div class="col-12">
                <div class="payslip-card">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>
                                <i class="fas fa-list text-primary me-2"></i>
                                <?php if ($employee): ?>
                                    Payslips for <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                <?php elseif (hasPermission('hr')): ?>
                                    All Employee Payslips
                                <?php else: ?>
                                    My Payslips
                                <?php endif; ?>
                            </h4>
                            <?php if ($employee): ?>
                                <a href="index.php?page=payslips" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to All Payslips
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($payslips)): ?>
                            <div class="row">
                                <?php foreach ($payslips as $slip): ?>
                                    <div class="col-lg-6 col-xl-4">
                                        <div class="payslip-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0 text-success">
                                                    <i class="fas fa-receipt me-2"></i>
                                                    <?php echo htmlspecialchars($slip['period_name']); ?>
                                                </h6>
                                                <span class="badge bg-success">
                                                    <?php echo formatCurrency($slip['net_pay']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (hasPermission('hr') && !$employeeId): ?>
                                                <p class="mb-2">
                                                    <strong><?php echo htmlspecialchars($slip['employee_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($slip['employee_number']); ?></small>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted">Gross</small>
                                                    <div class="fw-bold"><?php echo formatCurrency($slip['gross_pay']); ?></div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Deductions</small>
                                                    <div class="fw-bold text-danger"><?php echo formatCurrency($slip['total_deductions']); ?></div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Net</small>
                                                    <div class="fw-bold text-success"><?php echo formatCurrency($slip['net_pay']); ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    Pay Date: <?php echo formatDate($slip['pay_date']); ?>
                                                </small>
                                                
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?page=payslips&action=view&payslip_id=<?php echo $slip['id']; ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="index.php?page=payslips&action=pdf&payslip_id=<?php echo $slip['id']; ?>" 
                                                       target="_blank" class="btn btn-outline-danger" title="Download PDF">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                                <h5>No Payslips Found</h5>
                                <p class="text-muted">
                                    <?php if ($employee): ?>
                                        No payslips have been generated for this employee yet.
                                    <?php else: ?>
                                        No payslips available. Payslips are generated after payroll processing.
                                    <?php endif; ?>
                                </p>
                                <?php if (hasPermission('hr')): ?>
                                    <a href="index.php?page=payroll" class="btn btn-success">
                                        <i class="fas fa-calculator me-2"></i>Process Payroll
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
