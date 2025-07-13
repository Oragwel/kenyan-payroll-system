<?php
/**
 * HR Dashboard - Limited access for HR users
 */

// SECURITY: Check if user has HR permissions
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['hr', 'admin'])) {
    header('Location: index.php?page=employee_dashboard');
    exit;
}

// Get HR dashboard statistics
$stats = [];

// Get employee statistics
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_employees,
        SUM(CASE WHEN employment_status = 'active' THEN 1 ELSE 0 END) as active_employees,
        SUM(CASE WHEN contract_type = 'permanent' AND employment_status = 'active' THEN 1 ELSE 0 END) as permanent_employees,
        SUM(CASE WHEN contract_type = 'contract' AND employment_status = 'active' THEN 1 ELSE 0 END) as contract_employees,
        SUM(CASE WHEN contract_type = 'casual' AND employment_status = 'active' THEN 1 ELSE 0 END) as casual_employees
    FROM employees
    WHERE company_id = ?
");
$stmt->execute([$_SESSION['company_id']]);
$stats = $stmt->fetch();

// Get leave statistics (only for active employees)
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    WHERE e.company_id = ? AND e.employment_status = 'active'
");
$stmt->execute([$_SESSION['company_id']]);
$leaveStats = $stmt->fetch();
$stats = array_merge($stats, $leaveStats);

// Get recent leave applications
$stmt = $db->prepare("
    SELECT la.*, e.first_name, e.last_name, lt.name as leave_type_name
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    WHERE e.company_id = ?
    ORDER BY la.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['company_id']]);
$recentLeaves = $stmt->fetchAll();

// Get recent employee additions (only active employees)
$stmt = $db->prepare("
    SELECT e.*, d.name as department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.company_id = ? AND e.employment_status = 'active'
    ORDER BY e.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['company_id']]);
$recentEmployees = $stmt->fetchAll();

// HR Analytics Data
// Monthly employee additions (last 6 months, only active employees)
$stmt = $db->prepare("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        DATE_FORMAT(created_at, '%M') as month_name,
        COUNT(*) as new_employees
    FROM employees
    WHERE company_id = ? AND employment_status = 'active'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
");
$stmt->execute([$_SESSION['company_id']]);
$hrEmployeeGrowth = $stmt->fetchAll();

// Department-wise employee distribution
$stmt = $db->prepare("
    SELECT
        d.name as department_name,
        COUNT(e.id) as employee_count,
        AVG(e.basic_salary) as avg_salary
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status = 'active'
    WHERE d.company_id = ?
    GROUP BY d.id, d.name
    HAVING employee_count > 0
    ORDER BY employee_count DESC
");
$stmt->execute([$_SESSION['company_id']]);
$departmentAnalytics = $stmt->fetchAll();

// Leave trends by month (only for active employees)
$stmt = $db->prepare("
    SELECT
        DATE_FORMAT(la.created_at, '%Y-%m') as month,
        DATE_FORMAT(la.created_at, '%M') as month_name,
        COUNT(*) as total_applications,
        SUM(CASE WHEN la.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN la.status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    WHERE e.company_id = ? AND e.employment_status = 'active'
    AND la.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(la.created_at, '%Y-%m')
    ORDER BY la.created_at ASC
");
$stmt->execute([$_SESSION['company_id']]);
$leaveTrends = $stmt->fetchAll();
?>

<!-- HR Dashboard Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.hr-hero {
    background: linear-gradient(135deg, var(--kenya-red) 0%, #a00e1f 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.hr-welcome-card {
    background: rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 1rem;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.text-white-75 {
    color: rgba(255, 255, 255, 0.75) !important;
}

.hr-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.hr-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.stat-card-hr {
    padding: 1.5rem;
    text-align: center;
    border-radius: 15px;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.stat-card-hr:hover {
    transform: translateY(-3px);
}

.stat-card-hr.green {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
}

.stat-card-hr.red {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    color: white;
}

.stat-card-hr.black {
    background: linear-gradient(135deg, var(--kenya-black), #333333);
    color: white;
}

.stat-number-hr {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.quick-action-hr {
    background: white;
    border: 2px solid var(--kenya-red);
    color: var(--kenya-red);
    padding: 1rem;
    border-radius: 10px;
    text-decoration: none;
    display: block;
    text-align: center;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.quick-action-hr:hover {
    background: var(--kenya-red);
    color: white;
    transform: translateY(-2px);
}

.quick-action-hr.primary {
    background: var(--kenya-red);
    color: white;
}

.quick-action-hr.primary:hover {
    background: #a00e1f;
}
</style>

<div class="container-fluid">
    <!-- HR Hero Section -->
    <div class="hr-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-users-cog me-3"></i>
                        HR Management Dashboard
                    </h2>
                    <p class="mb-0 opacity-75">
                        Human Resources & Employee Management
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="hr-welcome-card">
                        <h5 class="mb-1 text-white">Welcome, <?php echo $_SESSION['username']; ?>!</h5>
                        <small class="text-white-75">HR Manager • <?php echo date('F j, Y'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HR Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card-hr green">
                <i class="fas fa-users fa-3x mb-3 opacity-75"></i>
                <div class="stat-number-hr"><?php echo number_format($stats['active_employees'] ?? 0); ?></div>
                <h6>Active Employees</h6>
                <small class="opacity-75"><?php echo number_format($stats['total_employees'] ?? 0); ?> total</small>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card-hr red">
                <i class="fas fa-calendar-times fa-3x mb-3 opacity-75"></i>
                <div class="stat-number-hr"><?php echo number_format($stats['pending_leaves'] ?? 0); ?></div>
                <h6>Pending Leaves</h6>
                <small class="opacity-75"><?php echo number_format($stats['total_applications'] ?? 0); ?> total applications</small>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card-hr black">
                <i class="fas fa-user-tie fa-3x mb-3 opacity-75"></i>
                <div class="stat-number-hr"><?php echo number_format($stats['permanent_employees'] ?? 0); ?></div>
                <h6>Permanent Staff</h6>
                <small class="opacity-75">Full-time employees</small>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card-hr green">
                <i class="fas fa-check-circle fa-3x mb-3 opacity-75"></i>
                <div class="stat-number-hr"><?php echo number_format($stats['approved_leaves'] ?? 0); ?></div>
                <h6>Approved Leaves</h6>
                <small class="opacity-75">This period</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Leave Applications -->
        <div class="col-lg-8">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-calendar-check text-warning me-2"></i>
                        Recent Leave Applications
                    </h5>
                    <?php if (!empty($recentLeaves)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLeaves as $leave): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                            <td><?php echo formatDate($leave['start_date']); ?></td>
                                            <td><?php echo $leave['days_requested']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $leave['status'] === 'approved' ? 'success' : 
                                                        ($leave['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($leave['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="index.php?page=leaves&action=review&id=<?php echo $leave['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No recent leave applications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt text-warning me-2"></i>
                        HR Quick Actions
                    </h5>
                    
                    <a href="index.php?page=employees&action=add" class="quick-action-hr">
                        <i class="fas fa-user-plus me-2"></i>
                        Add New Employee
                    </a>
                    
                    <a href="index.php?page=employees" class="quick-action-hr">
                        <i class="fas fa-users me-2"></i>
                        Manage Employees
                    </a>
                    
                    <a href="index.php?page=leaves" class="quick-action-hr primary">
                        <i class="fas fa-calendar-check me-2"></i>
                        Review Leave Applications
                    </a>
                    
                    <a href="index.php?page=reports&type=hr" class="quick-action-hr">
                        <i class="fas fa-chart-bar me-2"></i>
                        HR Reports
                    </a>
                    
                    <a href="index.php?page=departments" class="quick-action-hr">
                        <i class="fas fa-building me-2"></i>
                        Manage Departments
                    </a>
                </div>
            </div>

            <!-- Recent Employees -->
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-user-plus text-success me-2"></i>
                        Recent Employees
                    </h5>
                    <?php if (!empty($recentEmployees)): ?>
                        <?php foreach ($recentEmployees as $employee): ?>
                            <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($employee['department_name'] ?? 'No Department'); ?> • 
                                        <?php echo htmlspecialchars($employee['job_title']); ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php echo formatDate($employee['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No recent employees.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Distribution -->
    <div class="row">
        <div class="col-12">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie text-info me-2"></i>
                        Employee Distribution by Contract Type
                    </h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <h4 class="text-success"><?php echo number_format($stats['permanent_employees'] ?? 0); ?></h4>
                                <h6>Permanent</h6>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo $stats['total_employees'] > 0 ? ($stats['permanent_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <h4 class="text-danger"><?php echo number_format($stats['contract_employees'] ?? 0); ?></h4>
                                <h6>Contract</h6>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-danger" 
                                         style="width: <?php echo $stats['total_employees'] > 0 ? ($stats['contract_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <h4 class="text-dark"><?php echo number_format($stats['casual_employees'] ?? 0); ?></h4>
                                <h6>Casual</h6>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-dark" 
                                         style="width: <?php echo $stats['total_employees'] > 0 ? ($stats['casual_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 border rounded">
                                <h4 class="text-secondary"><?php echo number_format(($stats['total_employees'] ?? 0) - ($stats['permanent_employees'] ?? 0) - ($stats['contract_employees'] ?? 0) - ($stats['casual_employees'] ?? 0)); ?></h4>
                                <h6>Others</h6>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-secondary" 
                                         style="width: <?php echo $stats['total_employees'] > 0 ? ((($stats['total_employees'] ?? 0) - ($stats['permanent_employees'] ?? 0) - ($stats['contract_employees'] ?? 0) - ($stats['casual_employees'] ?? 0)) / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HR Analytics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="section-title mb-4">
                <i class="fas fa-chart-line me-2"></i>
                HR Analytics & Insights
            </h3>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Employee Growth Chart -->
        <div class="col-lg-6">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar text-success me-2"></i>
                        Employee Growth (6 Months)
                    </h5>
                    <canvas id="hrEmployeeGrowthChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Distribution Chart -->
        <div class="col-lg-6">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie text-info me-2"></i>
                        Department Distribution
                    </h5>
                    <canvas id="departmentChart" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Leave Trends Chart -->
        <div class="col-12">
            <div class="hr-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-area text-warning me-2"></i>
                        Leave Application Trends
                    </h5>
                    <canvas id="leaveTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- HR Analytics JavaScript -->
<script>
// Kenyan Flag Colors for HR Charts
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

// 1. HR Employee Growth Chart
const hrEmployeeGrowthCtx = document.getElementById('hrEmployeeGrowthChart');
if (hrEmployeeGrowthCtx) {
    const growthData = <?php echo json_encode($hrEmployeeGrowth); ?>;

    new Chart(hrEmployeeGrowthCtx, {
        type: 'bar',
        data: {
            labels: growthData.map(item => item.month_name || 'N/A'),
            datasets: [{
                label: 'New Employees',
                data: growthData.map(item => parseInt(item.new_employees) || 0),
                backgroundColor: kenyaColors.red,
                borderColor: '#a00e1f',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'New Employees: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
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
            }
        }
    });
}

// 2. Department Distribution Chart
const departmentCtx = document.getElementById('departmentChart');
if (departmentCtx) {
    const deptData = <?php echo json_encode($departmentAnalytics); ?>;

    new Chart(departmentCtx, {
        type: 'doughnut',
        data: {
            labels: deptData.map(item => item.department_name || 'Unknown'),
            datasets: [{
                data: deptData.map(item => parseInt(item.employee_count)),
                backgroundColor: [
                    kenyaColors.red,
                    kenyaColors.green,
                    kenyaColors.black,
                    kenyaColors.gold,
                    kenyaColors.darkGreen,
                    '#6b7280'
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
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' employees (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// 3. Leave Trends Chart
const leaveTrendsCtx = document.getElementById('leaveTrendsChart');
if (leaveTrendsCtx) {
    const leaveData = <?php echo json_encode($leaveTrends); ?>;

    new Chart(leaveTrendsCtx, {
        type: 'line',
        data: {
            labels: leaveData.map(item => item.month_name || 'N/A'),
            datasets: [{
                label: 'Total Applications',
                data: leaveData.map(item => parseInt(item.total_applications) || 0),
                borderColor: kenyaColors.red,
                backgroundColor: 'rgba(206,17,38,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: kenyaColors.red,
                pointBorderColor: kenyaColors.white,
                pointBorderWidth: 2,
                pointRadius: 6
            }, {
                label: 'Approved',
                data: leaveData.map(item => parseInt(item.approved) || 0),
                borderColor: kenyaColors.green,
                backgroundColor: 'rgba(0,107,63,0.1)',
                fill: false,
                tension: 0.4,
                pointBackgroundColor: kenyaColors.green,
                pointBorderColor: kenyaColors.white,
                pointBorderWidth: 2,
                pointRadius: 4
            }, {
                label: 'Pending',
                data: leaveData.map(item => parseInt(item.pending) || 0),
                borderColor: kenyaColors.gold,
                backgroundColor: 'rgba(255,215,0,0.1)',
                fill: false,
                tension: 0.4,
                pointBackgroundColor: kenyaColors.gold,
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
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
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
        }, 300 + (index * 100));
    });
});
</script>
