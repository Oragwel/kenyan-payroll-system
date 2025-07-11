<?php
/**
 * Employee Dashboard - Limited access for employees
 */

// SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=auth&action=login');
    exit;
}

// Get employee-specific data
$employeeData = [];
if (isset($_SESSION['employee_id'])) {
    // Get employee personal information
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, c.name as company_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN companies c ON e.company_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $employee = $stmt->fetch();
    
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
    
    // Get recent payslips (last 6 months)
    $stmt = $db->prepare("
        SELECT pr.*, pp.period_name, pp.pay_date 
        FROM payroll_records pr 
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id 
        WHERE pr.employee_id = ? 
        AND pp.pay_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ORDER BY pp.pay_date DESC 
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $recentPayslips = $stmt->fetchAll();
    
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
    
    // Get recent leave applications
    $stmt = $db->prepare("
        SELECT la.*, lt.name as leave_type_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        WHERE la.employee_id = ?
        ORDER BY la.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $recentLeaves = $stmt->fetchAll();

    // Employee Analytics Data
    // Salary progression (last 12 months)
    $stmt = $db->prepare("
        SELECT
            DATE_FORMAT(pp.pay_date, '%Y-%m') as month,
            DATE_FORMAT(pp.pay_date, '%M') as month_name,
            pr.gross_pay,
            pr.net_pay,
            pr.total_deductions
        FROM payroll_records pr
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        WHERE pr.employee_id = ?
        AND pp.pay_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        ORDER BY pp.pay_date ASC
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $salaryProgression = $stmt->fetchAll();

    // Leave usage analytics
    $stmt = $db->prepare("
        SELECT
            lt.name as leave_type,
            COUNT(la.id) as applications_count,
            SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END) as days_used,
            lt.days_per_year as days_allocated
        FROM leave_types lt
        LEFT JOIN leave_applications la ON lt.id = la.leave_type_id AND la.employee_id = ?
        WHERE lt.company_id = ?
        GROUP BY lt.id, lt.name, lt.days_per_year
    ");
    $stmt->execute([$_SESSION['employee_id'], $_SESSION['company_id']]);
    $leaveUsageAnalytics = $stmt->fetchAll();
}
?>

<!-- Employee Dashboard Styles -->
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

.employee-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.employee-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.employee-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.stat-item {
    text-align: center;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.stat-item.green { background: var(--kenya-light-green); color: var(--kenya-dark-green); }
.stat-item.red { background: rgba(206,17,38,0.1); color: var(--kenya-red); }
.stat-item.black { background: rgba(0,0,0,0.05); color: var(--kenya-black); }

.payslip-summary {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}
</style>

<div class="container-fluid">
    <!-- Employee Hero Section -->
    <div class="employee-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-user me-3"></i>
                        Welcome, <?php echo htmlspecialchars($employee['first_name'] ?? $_SESSION['username']); ?>!
                    </h2>
                    <p class="mb-0 opacity-75">
                        Employee Self-Service Portal
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h6 class="mb-1"><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></h6>
                        <small class="opacity-75"><?php echo htmlspecialchars($employee['job_title'] ?? 'Employee'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Latest Payslip -->
        <div class="col-lg-8 mb-4">
            <?php if ($latestPayslip): ?>
                <div class="employee-card">
                    <div class="payslip-summary">
                        <h4 class="mb-3">
                            <i class="fas fa-receipt me-2"></i>
                            Latest Payslip - <?php echo htmlspecialchars($latestPayslip['period_name']); ?>
                        </h4>
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h3><?php echo formatCurrency($latestPayslip['gross_pay']); ?></h3>
                                <small class="opacity-75">Gross Pay</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h3><?php echo formatCurrency($latestPayslip['total_deductions']); ?></h3>
                                <small class="opacity-75">Total Deductions</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h3 class="text-warning"><?php echo formatCurrency($latestPayslip['net_pay']); ?></h3>
                                <small class="opacity-75">Net Pay</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <h6 class="mb-3">Deduction Breakdown</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item green">
                                    <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                    <h6>PAYE Tax</h6>
                                    <h5><?php echo formatCurrency($latestPayslip['paye_tax']); ?></h5>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item red">
                                    <i class="fas fa-piggy-bank fa-2x mb-2"></i>
                                    <h6>NSSF</h6>
                                    <h5><?php echo formatCurrency($latestPayslip['nssf_deduction']); ?></h5>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item black">
                                    <i class="fas fa-heartbeat fa-2x mb-2"></i>
                                    <h6>SHIF</h6>
                                    <h5><?php echo formatCurrency($latestPayslip['nhif_deduction']); ?></h5>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item green">
                                    <i class="fas fa-home fa-2x mb-2"></i>
                                    <h6>Housing Levy</h6>
                                    <h5><?php echo formatCurrency($latestPayslip['housing_levy']); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="employee-card">
                    <div class="p-5 text-center">
                        <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                        <h4>No Payslips Available</h4>
                        <p class="text-muted">Your payslips will appear here once payroll is processed.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions & Leave Balance -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="employee-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt text-warning me-2"></i>
                        Quick Actions
                    </h5>
                    <div class="d-grid gap-2">
                        <a href="index.php?page=profile" class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a href="index.php?page=payslips" class="btn btn-outline-success">
                            <i class="fas fa-file-invoice me-2"></i> View Payslips
                        </a>
                        <a href="index.php?page=leaves&action=apply" class="btn btn-outline-warning">
                            <i class="fas fa-calendar-plus me-2"></i> Apply for Leave
                        </a>
                        <a href="index.php?page=leaves" class="btn btn-outline-info">
                            <i class="fas fa-calendar-check me-2"></i> My Leave Applications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="employee-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-calendar-alt text-success me-2"></i>
                        Leave Balance
                    </h5>
                    <?php if (!empty($leaveBalances)): ?>
                        <?php foreach ($leaveBalances as $balance): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($balance['name']); ?></span>
                                    <span class="badge bg-primary">
                                        <?php echo ($balance['days_per_year'] - $balance['used_days']); ?>/<?php echo $balance['days_per_year']; ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo (($balance['days_per_year'] - $balance['used_days']) / $balance['days_per_year']) * 100; ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo ($balance['days_per_year'] - $balance['used_days']); ?> days remaining
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No leave types configured.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payslips -->
    <?php if (!empty($recentPayslips)): ?>
        <div class="row">
            <div class="col-12">
                <div class="employee-card">
                    <div class="p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-history text-info me-2"></i>
                            Recent Payslips
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Pay Date</th>
                                        <th>Gross Pay</th>
                                        <th>Deductions</th>
                                        <th>Net Pay</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayslips as $payslip): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payslip['period_name']); ?></td>
                                            <td><?php echo formatDate($payslip['pay_date']); ?></td>
                                            <td><?php echo formatCurrency($payslip['gross_pay']); ?></td>
                                            <td><?php echo formatCurrency($payslip['total_deductions']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency($payslip['net_pay']); ?></td>
                                            <td>
                                                <a href="index.php?page=payslip&id=<?php echo $payslip['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Employee Analytics Section -->
    <?php if (!empty($salaryProgression) || !empty($leaveUsageAnalytics)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-4">
                    <i class="fas fa-chart-line text-success me-2"></i>
                    My Analytics & Insights
                </h4>
            </div>
        </div>

        <div class="row">
            <!-- Salary Progression Chart -->
            <?php if (!empty($salaryProgression)): ?>
                <div class="col-lg-8">
                    <div class="employee-card">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-area text-success me-2"></i>
                                My Salary Progression
                            </h5>
                            <canvas id="salaryProgressionChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Leave Usage Chart -->
            <?php if (!empty($leaveUsageAnalytics)): ?>
                <div class="col-lg-4">
                    <div class="employee-card">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-donut text-warning me-2"></i>
                                Leave Usage
                            </h5>
                            <canvas id="leaveUsageChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Employee Analytics JavaScript -->
<script>
// Kenyan Flag Colors for Employee Charts
const kenyaColors = {
    black: '#000000',
    red: '#ce1126',
    white: '#ffffff',
    green: '#006b3f',
    lightGreen: '#e8f5e8',
    darkGreen: '#004d2e',
    gold: '#ffd700'
};

// Chart.js default configuration
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#374151';

// 1. Salary Progression Chart
const salaryProgressionCtx = document.getElementById('salaryProgressionChart');
if (salaryProgressionCtx) {
    const salaryData = <?php echo json_encode($salaryProgression); ?>;

    new Chart(salaryProgressionCtx, {
        type: 'line',
        data: {
            labels: salaryData.map(item => item.month_name || 'N/A'),
            datasets: [{
                label: 'Gross Pay (KES)',
                data: salaryData.map(item => parseFloat(item.gross_pay) || 0),
                borderColor: kenyaColors.green,
                backgroundColor: kenyaColors.lightGreen,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: kenyaColors.green,
                pointBorderColor: kenyaColors.white,
                pointBorderWidth: 2,
                pointRadius: 6
            }, {
                label: 'Net Pay (KES)',
                data: salaryData.map(item => parseFloat(item.net_pay) || 0),
                borderColor: kenyaColors.darkGreen,
                backgroundColor: 'rgba(0,77,46,0.1)',
                fill: false,
                tension: 0.4,
                pointBackgroundColor: kenyaColors.darkGreen,
                pointBorderColor: kenyaColors.white,
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': KES ' +
                                   new Intl.NumberFormat().format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KES ' + new Intl.NumberFormat().format(value);
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
}

// 2. Leave Usage Chart
const leaveUsageCtx = document.getElementById('leaveUsageChart');
if (leaveUsageCtx) {
    const leaveData = <?php echo json_encode($leaveUsageAnalytics); ?>;

    new Chart(leaveUsageCtx, {
        type: 'doughnut',
        data: {
            labels: leaveData.map(item => item.leave_type || 'Unknown'),
            datasets: [{
                label: 'Days Used',
                data: leaveData.map(item => parseInt(item.days_used) || 0),
                backgroundColor: [
                    kenyaColors.green,
                    kenyaColors.red,
                    kenyaColors.gold,
                    kenyaColors.black,
                    kenyaColors.darkGreen
                ],
                borderWidth: 3,
                borderColor: kenyaColors.white,
                hoverBorderWidth: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const leaveType = leaveData[context.dataIndex];
                            const allocated = parseInt(leaveType.days_allocated) || 0;
                            const used = context.parsed;
                            const remaining = allocated - used;
                            return [
                                context.label + ': ' + used + ' days used',
                                'Remaining: ' + remaining + ' days',
                                'Allocated: ' + allocated + ' days'
                            ];
                        }
                    }
                }
            }
        }
    });
}

// Chart Animation Effects
document.addEventListener('DOMContentLoaded', function() {
    const charts = document.querySelectorAll('canvas');
    charts.forEach((chart, index) => {
        chart.style.opacity = '0';
        chart.style.transform = 'translateY(20px)';
        chart.style.transition = 'all 0.6s ease';

        setTimeout(() => {
            chart.style.opacity = '1';
            chart.style.transform = 'translateY(0)';
        }, 300 + (index * 200));
    });
});
</script>
