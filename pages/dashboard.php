<?php
/**
 * Enhanced Backend Dashboard with Kenyan Flag Theme
 * Comprehensive payroll management dashboard
 */

// Get comprehensive dashboard statistics
$stats = [];
$charts = [];
$alerts = [];

if (hasPermission('hr')) {
    // Enhanced Employee Statistics
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_employees,
            SUM(CASE WHEN employment_status = 'active' THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN contract_type = 'permanent' THEN 1 ELSE 0 END) as permanent_employees,
            SUM(CASE WHEN contract_type = 'contract' THEN 1 ELSE 0 END) as contract_employees,
            SUM(CASE WHEN contract_type = 'casual' THEN 1 ELSE 0 END) as casual_employees,
            SUM(CASE WHEN contract_type = 'intern' THEN 1 ELSE 0 END) as intern_employees
        FROM employees
        WHERE company_id = ?
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employeeStats = $stmt->fetch();
    $stats = array_merge($stats, $employeeStats);

    // Payroll Statistics
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("
        SELECT
            SUM(pr.net_pay) as monthly_payroll,
            SUM(pr.gross_pay) as gross_payroll,
            SUM(pr.paye_tax) as total_paye,
            SUM(pr.nssf_deduction) as total_nssf,
            SUM(pr.nhif_deduction) as total_shif,
            SUM(pr.housing_levy) as total_housing_levy,
            COUNT(*) as payroll_records
        FROM payroll_records pr
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        WHERE pp.company_id = ? AND DATE_FORMAT(pp.start_date, '%Y-%m') = ?
    ");
    $stmt->execute([$_SESSION['company_id'], $currentMonth]);
    $payrollStats = $stmt->fetch();
    $stats = array_merge($stats, $payrollStats);

    // Leave Statistics
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_leave_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_leaves
        FROM leave_applications la
        JOIN employees e ON la.employee_id = e.id
        WHERE e.company_id = ?
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $leaveStats = $stmt->fetch();
    $stats = array_merge($stats, $leaveStats);

    // Recent Activities
    $stmt = $db->prepare("
        SELECT * FROM payroll_periods
        WHERE company_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $recentPayrolls = $stmt->fetchAll();

    // Department Statistics
    $stmt = $db->prepare("
        SELECT
            d.name as department_name,
            COUNT(e.id) as employee_count,
            AVG(e.basic_salary) as avg_salary
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status = 'active'
        WHERE d.company_id = ?
        GROUP BY d.id, d.name
        ORDER BY employee_count DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $departmentStats = $stmt->fetchAll();

    // Monthly Payroll Trend (Last 6 months)
    $stmt = $db->prepare("
        SELECT
            DATE_FORMAT(pp.start_date, '%Y-%m') as month,
            DATE_FORMAT(pp.start_date, '%M %Y') as month_name,
            SUM(pr.net_pay) as total_net_pay,
            COUNT(pr.id) as employee_count
        FROM payroll_periods pp
        JOIN payroll_records pr ON pp.id = pr.payroll_period_id
        WHERE pp.company_id = ?
        AND pp.start_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(pp.start_date, '%Y-%m')
        ORDER BY pp.start_date DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $monthlyTrends = $stmt->fetchAll();

    // System Alerts
    if ($stats['pending_leaves'] > 5) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fas fa-calendar-times',
            'message' => "You have {$stats['pending_leaves']} pending leave applications requiring attention."
        ];
    }

    if (empty($recentPayrolls)) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'fas fa-calculator',
            'message' => 'No payroll has been processed yet. Start by processing your first payroll period.'
        ];
    }

    if ($stats['active_employees'] == 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'fas fa-users',
            'message' => 'No active employees found. Add employees to start using the payroll system.'
        ];
    }
}

// Employee-specific dashboard data
if (isset($_SESSION['employee_id'])) {
    // Get employee personal stats
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

<!-- Kenyan Flag Theme Dashboard Styles -->
<style>
:root {
    /* Kenyan Flag Colors */
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.dashboard-header {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -20px 30px -20px;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        45deg,
        transparent 0%,
        var(--kenya-red) 25%,
        var(--kenya-white) 50%,
        var(--kenya-black) 75%,
        transparent 100%
    );
    opacity: 0.1;
}

.kenyan-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.kenyan-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
}

.kenyan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.stat-card-green {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
}

.stat-card-red {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    color: white;
}

.stat-card-black {
    background: linear-gradient(135deg, var(--kenya-black), #333333);
    color: white;
}

.stat-card-white {
    background: linear-gradient(135deg, #f8f9fa, var(--kenya-white));
    color: var(--kenya-black);
    border: 2px solid var(--kenya-green);
}

.kenyan-badge {
    background: linear-gradient(45deg, var(--kenya-green), var(--kenya-red));
    color: white;
    border: none;
}

.alert-kenyan {
    border-left: 4px solid var(--kenya-green);
    background: var(--kenya-light-green);
    border-radius: 8px;
}
</style>

<div class="container-fluid">
    <!-- Kenyan-themed Header -->
    <div class="dashboard-header">
        <div class="container-fluid position-relative" style="z-index: 2;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-tachometer-alt me-3"></i>
                        <span style="color: var(--kenya-white);">Kenyan Payroll</span>
                        <span style="color: var(--kenya-red);">Dashboard</span>
                    </h1>
                    <p class="mb-0 opacity-75">
                        🇰🇪 Comprehensive payroll management for Kenyan businesses
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-20 rounded p-3">
                        <h5 class="mb-1">Welcome back!</h5>
                        <p class="mb-0"><strong><?php echo $_SESSION['username']; ?></strong></p>
                        <small class="opacity-75"><?php echo ucfirst($_SESSION['user_role']); ?> • <?php echo date('l, F j, Y'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (hasPermission('hr')): ?>
        <!-- System Alerts -->
        <?php if (!empty($alerts)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['type']; ?> alert-kenyan alert-dismissible fade show">
                            <i class="<?php echo $alert['icon']; ?> me-2"></i>
                            <?php echo $alert['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Enhanced KPI Cards with Kenyan Theme -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kenyan-card stat-card-green">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['active_employees'] ?? 0); ?></h3>
                                <p class="mb-0 opacity-75">Active Employees</p>
                                <small class="opacity-50">
                                    <?php echo number_format($stats['total_employees'] ?? 0); ?> total
                                </small>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-users fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kenyan-card stat-card-red">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo formatCurrency($stats['monthly_payroll'] ?? 0); ?></h3>
                                <p class="mb-0 opacity-75">Monthly Payroll</p>
                                <small class="opacity-50">
                                    <?php echo number_format($stats['payroll_records'] ?? 0); ?> employees paid
                                </small>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kenyan-card stat-card-black">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['pending_leaves'] ?? 0); ?></h3>
                                <p class="mb-0 opacity-75">Pending Leaves</p>
                                <small class="opacity-50">
                                    <?php echo number_format($stats['total_leave_applications'] ?? 0); ?> total applications
                                </small>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-calendar-times fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="kenyan-card stat-card-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-1"><?php echo formatCurrency($stats['total_paye'] ?? 0); ?></h3>
                                <p class="mb-0">PAYE Tax (Monthly)</p>
                                <small class="text-muted">
                                    Statutory compliance
                                </small>
                            </div>
                            <div class="text-end">
                                <i class="fas fa-receipt fa-3x text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Statutory Deductions Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="kenyan-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            Monthly Statutory Deductions Breakdown
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="p-3 rounded" style="background: var(--kenya-light-green);">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6>PAYE Tax</h6>
                                    <h4 class="text-success"><?php echo formatCurrency($stats['total_paye'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="p-3 rounded" style="background: rgba(206,17,38,0.1);">
                                    <i class="fas fa-piggy-bank fa-2x me-2" style="color: var(--kenya-red);"></i>
                                    <h6>NSSF</h6>
                                    <h4 style="color: var(--kenya-red);"><?php echo formatCurrency($stats['total_nssf'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="p-3 rounded" style="background: rgba(0,0,0,0.05);">
                                    <i class="fas fa-heartbeat fa-2x text-dark mb-2"></i>
                                    <h6>SHIF</h6>
                                    <h4 class="text-dark"><?php echo formatCurrency($stats['total_shif'] ?? 0); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="p-3 rounded" style="background: rgba(0,107,63,0.1);">
                                    <i class="fas fa-home fa-2x" style="color: var(--kenya-green);"></i>
                                    <h6>Housing Levy</h6>
                                    <h4 style="color: var(--kenya-green);"><?php echo formatCurrency($stats['total_housing_levy'] ?? 0); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="kenyan-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-users-cog text-primary me-2"></i>
                            Employee Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Permanent</span>
                                <span class="kenyan-badge badge"><?php echo number_format($stats['permanent_employees'] ?? 0); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="background: var(--kenya-green); width: <?php echo $stats['total_employees'] > 0 ? ($stats['permanent_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Contract</span>
                                <span class="kenyan-badge badge"><?php echo number_format($stats['contract_employees'] ?? 0); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="background: var(--kenya-red); width: <?php echo $stats['total_employees'] > 0 ? ($stats['contract_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Casual</span>
                                <span class="kenyan-badge badge"><?php echo number_format($stats['casual_employees'] ?? 0); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="background: var(--kenya-black); width: <?php echo $stats['total_employees'] > 0 ? ($stats['casual_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Interns</span>
                                <span class="kenyan-badge badge"><?php echo number_format($stats['intern_employees'] ?? 0); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-secondary" style="width: <?php echo $stats['total_employees'] > 0 ? ($stats['intern_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Quick Actions -->
        <div class="row">
            <div class="col-lg-8">
                <div class="kenyan-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            Recent Payroll Periods
                        </h5>
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
            <div class="col-lg-4">
                <div class="kenyan-card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="index.php?page=employees&action=add" class="btn btn-outline-success btn-lg">
                                <i class="fas fa-user-plus me-2"></i>
                                Add New Employee
                            </a>
                            <a href="index.php?page=payroll&action=create" class="btn btn-success btn-lg">
                                <i class="fas fa-calculator me-2"></i>
                                Process Payroll
                            </a>
                            <a href="index.php?page=reports" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-file-alt me-2"></i>
                                Generate Reports
                            </a>
                            <a href="index.php?page=leaves" class="btn btn-outline-warning btn-lg">
                                <i class="fas fa-calendar-check me-2"></i>
                                Manage Leaves
                            </a>
                        </div>

                        <!-- Kenyan Compliance Reminder -->
                        <div class="mt-4 p-3 rounded" style="background: var(--kenya-light-green); border-left: 4px solid var(--kenya-green);">
                            <small class="text-muted">
                                <i class="fas fa-shield-check text-success me-1"></i>
                                <strong>Kenyan Compliance:</strong><br>
                                All calculations follow current KRA, NSSF, SHIF, and Housing Levy regulations.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Employee Dashboard with Kenyan Theme -->
        <div class="row">
            <div class="col-lg-8">
                <?php if ($latestPayslip): ?>
                    <div class="kenyan-card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt text-success me-2"></i>
                                Latest Payslip - <?php echo htmlspecialchars($latestPayslip['period_name']); ?>
                            </h5>
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
