<?php
/**
 * Dashboard page
 */

// Get dashboard statistics
$stats = [];

if (hasPermission('hr')) {
    // Get employee count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ? AND employment_status = 'active'");
    $stmt->execute([$_SESSION['company_id']]);
    $stats['active_employees'] = $stmt->fetch()['count'];
    
    // Get current month payroll total
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("
        SELECT SUM(net_pay) as total 
        FROM payroll_records pr 
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id 
        WHERE pp.company_id = ? AND DATE_FORMAT(pp.start_date, '%Y-%m') = ?
    ");
    $stmt->execute([$_SESSION['company_id'], $currentMonth]);
    $stats['monthly_payroll'] = $stmt->fetch()['total'] ?? 0;
    
    // Get pending leave applications
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM leave_applications la 
        JOIN employees e ON la.employee_id = e.id 
        WHERE e.company_id = ? AND la.status = 'pending'
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $stats['pending_leaves'] = $stmt->fetch()['count'];
    
    // Get recent payroll periods
    $stmt = $db->prepare("
        SELECT * FROM payroll_periods 
        WHERE company_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $recentPayrolls = $stmt->fetchAll();
}

// Get employee-specific data
if (isset($_SESSION['employee_id'])) {
    // Get latest payslip
    $stmt = $db->prepare("
        SELECT pr.*, pp.period_name, pp.pay_date 
        FROM payroll_records pr 
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id 
        WHERE pr.employee_id = ? 
        ORDER BY pp.pay_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $latestPayslip = $stmt->fetch();
    
    // Get leave balance
    $stmt = $db->prepare("
        SELECT lt.name, lt.days_per_year,
               COALESCE(SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END), 0) as used_days
        FROM leave_types lt
        LEFT JOIN leave_applications la ON lt.id = la.leave_type_id AND la.employee_id = ?
        WHERE lt.company_id = ?
        GROUP BY lt.id, lt.name, lt.days_per_year
    ");
    $stmt->execute([$_SESSION['employee_id'], $_SESSION['company_id']]);
    $leaveBalances = $stmt->fetchAll();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                <div class="text-muted">
                    Welcome back, <?php echo $_SESSION['username']; ?>!
                </div>
            </div>
        </div>
    </div>

    <?php if (hasPermission('hr')): ?>
        <!-- Admin/HR Dashboard -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($stats['active_employees']); ?></h4>
                                <p class="mb-0">Active Employees</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo formatCurrency($stats['monthly_payroll']); ?></h4>
                                <p class="mb-0">Monthly Payroll</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['pending_leaves']; ?></h4>
                                <p class="mb-0">Pending Leaves</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-times fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo date('M Y'); ?></h4>
                                <p class="mb-0">Current Period</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Recent Payroll Periods</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentPayrolls)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Pay Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPayrolls as $payroll): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payroll['period_name']); ?></td>
                                                <td><?php echo formatDate($payroll['pay_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $payroll['status'] === 'completed' ? 'success' : 
                                                            ($payroll['status'] === 'processing' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($payroll['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="index.php?page=payroll&action=view&id=<?php echo $payroll['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No payroll periods found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="index.php?page=employees&action=add" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add Employee
                            </a>
                            <a href="index.php?page=payroll&action=create" class="btn btn-success">
                                <i class="fas fa-calculator"></i> Process Payroll
                            </a>
                            <a href="index.php?page=reports" class="btn btn-info">
                                <i class="fas fa-file-alt"></i> Generate Reports
                            </a>
                            <a href="index.php?page=leaves" class="btn btn-warning">
                                <i class="fas fa-calendar-check"></i> Manage Leaves
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Employee Dashboard -->
        <div class="row">
            <div class="col-md-8">
                <?php if ($latestPayslip): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-receipt"></i> Latest Payslip - <?php echo htmlspecialchars($latestPayslip['period_name']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Basic Salary:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['basic_salary']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total Allowances:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['total_allowances']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Gross Pay:</strong></td>
                                            <td><strong><?php echo formatCurrency($latestPayslip['gross_pay']); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>PAYE Tax:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['paye_tax']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>NSSF:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['nssf_deduction']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>NHIF/SHIF:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['nhif_deduction']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Housing Levy:</strong></td>
                                            <td><?php echo formatCurrency($latestPayslip['housing_levy']); ?></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td><strong>Net Pay:</strong></td>
                                            <td><strong><?php echo formatCurrency($latestPayslip['net_pay']); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5>No Payslips Available</h5>
                            <p class="text-muted">Your payslips will appear here once payroll is processed.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt"></i> Leave Balance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($leaveBalances)): ?>
                            <?php foreach ($leaveBalances as $balance): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><?php echo htmlspecialchars($balance['name']); ?></span>
                                        <span><?php echo ($balance['days_per_year'] - $balance['used_days']); ?>/<?php echo $balance['days_per_year']; ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo ($balance['used_days'] / $balance['days_per_year']) * 100; ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No leave types configured.</p>
                        <?php endif; ?>
                        
                        <div class="d-grid mt-3">
                            <a href="index.php?page=leaves&action=apply" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Apply for Leave
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
