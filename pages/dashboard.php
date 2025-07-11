<?php
/**
 * Enhanced Backend Dashboard with Kenyan Flag Theme
 * ADMIN ONLY ACCESS - Comprehensive payroll management dashboard
 */

// SECURITY: Check if user is admin - redirect if not
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Log unauthorized access attempt
    if (isset($_SESSION['user_id'])) {
        error_log("Unauthorized dashboard access attempt by user ID: " . $_SESSION['user_id'] . " with role: " . ($_SESSION['user_role'] ?? 'none'));
    }

    // Redirect to appropriate page based on user role
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'hr':
                header('Location: index.php?page=employees');
                break;
            case 'employee':
                header('Location: index.php?page=profile');
                break;
            default:
                header('Location: index.php?page=auth&action=login');
                break;
        }
    } else {
        header('Location: index.php?page=auth&action=login');
    }
    exit;
}

// Get comprehensive dashboard statistics (Admin only)
$stats = [];
$charts = [];
$alerts = [];

// Admin-only dashboard content
if ($_SESSION['user_role'] === 'admin') {
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

<!-- Modern Kenyan Dashboard Styles -->
<style>
:root {
    /* Kenyan Flag Colors */
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
    --kenya-gold: #ffd700;
}

body {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 3rem 0;
    margin: -30px -30px 40px -30px;
    position: relative;
    overflow: hidden;
    border-radius: 0 0 30px 30px;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: -50px;
    left: -100px;
    width: 120%;
    height: 200px;
    background: linear-gradient(
        45deg,
        var(--kenya-black) 0%,
        var(--kenya-black) 20%,
        var(--kenya-red) 20%,
        var(--kenya-red) 35%,
        var(--kenya-white) 35%,
        var(--kenya-white) 50%,
        var(--kenya-red) 50%,
        var(--kenya-red) 65%,
        var(--kenya-green) 65%,
        var(--kenya-green) 100%
    );
    transform: rotate(-8deg);
    opacity: 0.15;
    z-index: 1;
}

.dashboard-hero::after {
    content: '';
    position: absolute;
    bottom: -50px;
    right: -100px;
    width: 120%;
    height: 150px;
    background: linear-gradient(
        -45deg,
        transparent 0%,
        var(--kenya-green) 20%,
        var(--kenya-white) 40%,
        var(--kenya-red) 60%,
        var(--kenya-black) 80%,
        transparent 100%
    );
    transform: rotate(12deg);
    opacity: 0.1;
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.modern-card {
    background: white;
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    position: relative;
}

.modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, var(--kenya-black), var(--kenya-red), var(--kenya-white), var(--kenya-green));
}

.modern-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.stat-card {
    padding: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    opacity: 0.05;
    background-size: 100px 100px;
    background-image:
        radial-gradient(circle at 25px 25px, rgba(255,255,255,0.3) 2px, transparent 2px),
        radial-gradient(circle at 75px 75px, rgba(255,255,255,0.3) 2px, transparent 2px);
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(45deg, currentColor, rgba(255,255,255,0.8));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 1.1rem;
    font-weight: 600;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.stat-sublabel {
    font-size: 0.9rem;
    opacity: 0.7;
}

.stat-icon {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    font-size: 3rem;
    opacity: 0.2;
}

.kenya-green-card {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
}

.kenya-red-card {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    color: white;
}

.kenya-black-card {
    background: linear-gradient(135deg, var(--kenya-black), #333333);
    color: white;
}

.kenya-white-card {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    color: var(--kenya-black);
    border: 3px solid var(--kenya-green);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--kenya-dark-green);
    margin-bottom: 1.5rem;
    position: relative;
    padding-left: 1rem;
}

.section-title::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(to bottom, var(--kenya-green), var(--kenya-red));
    border-radius: 2px;
}

.deduction-item {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.deduction-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    transition: all 0.3s ease;
}

.deduction-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.deduction-paye::before { background: var(--kenya-green); }
.deduction-nssf::before { background: var(--kenya-red); }
.deduction-shif::before { background: var(--kenya-black); }
.deduction-housing::before { background: var(--kenya-green); }

.progress-modern {
    height: 12px;
    border-radius: 10px;
    background: rgba(0,0,0,0.1);
    overflow: hidden;
    margin: 0.5rem 0;
}

.progress-bar-modern {
    height: 100%;
    border-radius: 10px;
    transition: all 0.6s ease;
    position: relative;
    overflow: hidden;
}

.progress-bar-modern::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 25%, rgba(255,255,255,0.3) 25%, rgba(255,255,255,0.3) 50%, transparent 50%, transparent 75%, rgba(255,255,255,0.3) 75%);
    background-size: 20px 20px;
    animation: progress-animation 1s linear infinite;
}

@keyframes progress-animation {
    0% { transform: translateX(-20px); }
    100% { transform: translateX(20px); }
}

.quick-action-btn {
    background: white;
    border: 2px solid var(--kenya-green);
    color: var(--kenya-green);
    padding: 1rem 1.5rem;
    border-radius: 15px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
    text-align: center;
    margin-bottom: 1rem;
}

.quick-action-btn:hover {
    background: var(--kenya-green);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,107,63,0.3);
}

.quick-action-btn.primary {
    background: var(--kenya-green);
    color: white;
    border-color: var(--kenya-green);
}

.quick-action-btn.primary:hover {
    background: var(--kenya-dark-green);
    border-color: var(--kenya-dark-green);
}

.alert-modern {
    border: none;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 5px solid;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.alert-success { border-left-color: var(--kenya-green); }
.alert-warning { border-left-color: var(--kenya-gold); }
.alert-danger { border-left-color: var(--kenya-red); }
.alert-info { border-left-color: var(--kenya-black); }

.kenyan-pride {
    background: linear-gradient(90deg, var(--kenya-black) 0%, var(--kenya-red) 25%, var(--kenya-white) 50%, var(--kenya-green) 75%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    text-align: center;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .dashboard-hero {
        margin: -20px -20px 30px -20px;
        padding: 2rem 0;
    }

    .stat-number {
        font-size: 2rem;
    }

    .modern-card {
        margin-bottom: 1.5rem;
    }
}
</style>

<div class="container-fluid">
    <!-- Modern Kenyan Hero Section -->
    <div class="dashboard-hero">
        <div class="hero-content">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="display-4 fw-bold mb-3">
                            <i class="fas fa-chart-line me-3"></i>
                            <span>Kenyan Payroll</span>
                            <span style="color: var(--kenya-gold);">Dashboard</span>
                        </h1>
                        <p class="lead mb-0 opacity-90">
                            🇰🇪 Professional payroll management system built for Kenyan businesses
                        </p>
                        <small class="opacity-75">
                            Compliant with KRA, NSSF, SHIF & Housing Levy regulations
                        </small>
                    </div>
                    <div class="col-lg-4 text-end">
                        <div class="bg-white bg-opacity-15 rounded-3 p-4 backdrop-blur">
                            <h4 class="mb-2">Welcome back!</h4>
                            <h5 class="mb-1 text-warning"><?php echo $_SESSION['username']; ?></h5>
                            <p class="mb-1 opacity-75"><?php echo ucfirst($_SESSION['user_role']); ?></p>
                            <small class="opacity-60"><?php echo date('l, F j, Y'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN ONLY DASHBOARD CONTENT -->
        <!-- Modern System Alerts -->
        <?php if (!empty($alerts)): ?>
            <div class="row mb-5">
                <div class="col-12">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-modern alert-<?php echo $alert['type']; ?> alert-dismissible fade show">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $alert['icon']; ?> fa-lg me-3"></i>
                                <div class="flex-grow-1">
                                    <?php echo $alert['message']; ?>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Modern KPI Cards -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card kenya-green-card stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?php echo number_format($stats['active_employees'] ?? 0); ?></div>
                    <div class="stat-label">Active Employees</div>
                    <div class="stat-sublabel">
                        <?php echo number_format($stats['total_employees'] ?? 0); ?> total employees
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card kenya-red-card stat-card">
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                    <div class="stat-number"><?php echo formatCurrency($stats['monthly_payroll'] ?? 0); ?></div>
                    <div class="stat-label">Monthly Payroll</div>
                    <div class="stat-sublabel">
                        <?php echo number_format($stats['payroll_records'] ?? 0); ?> employees paid
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card kenya-black-card stat-card">
                    <i class="fas fa-calendar-times stat-icon"></i>
                    <div class="stat-number"><?php echo number_format($stats['pending_leaves'] ?? 0); ?></div>
                    <div class="stat-label">Pending Leaves</div>
                    <div class="stat-sublabel">
                        <?php echo number_format($stats['total_leave_applications'] ?? 0); ?> total applications
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card kenya-white-card stat-card">
                    <i class="fas fa-receipt stat-icon"></i>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_paye'] ?? 0); ?></div>
                    <div class="stat-label">PAYE Tax</div>
                    <div class="stat-sublabel">
                        Monthly statutory compliance
                    </div>
                </div>
            </div>
        </div>
        <!-- Statutory Deductions Section -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt me-2"></i>
                    Monthly Statutory Deductions
                </h2>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card deduction-item deduction-paye">
                    <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--kenya-green);"></i>
                    <h3 class="fw-bold" style="color: var(--kenya-green);"><?php echo formatCurrency($stats['total_paye'] ?? 0); ?></h3>
                    <h6 class="text-muted mb-2">PAYE Tax</h6>
                    <small class="text-muted">Pay As You Earn</small>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card deduction-item deduction-nssf">
                    <i class="fas fa-piggy-bank fa-3x mb-3" style="color: var(--kenya-red);"></i>
                    <h3 class="fw-bold" style="color: var(--kenya-red);"><?php echo formatCurrency($stats['total_nssf'] ?? 0); ?></h3>
                    <h6 class="text-muted mb-2">NSSF</h6>
                    <small class="text-muted">National Social Security Fund</small>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card deduction-item deduction-shif">
                    <i class="fas fa-heartbeat fa-3x mb-3" style="color: var(--kenya-black);"></i>
                    <h3 class="fw-bold" style="color: var(--kenya-black);"><?php echo formatCurrency($stats['total_shif'] ?? 0); ?></h3>
                    <h6 class="text-muted mb-2">SHIF</h6>
                    <small class="text-muted">Social Health Insurance Fund</small>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="modern-card deduction-item deduction-housing">
                    <i class="fas fa-home fa-3x mb-3" style="color: var(--kenya-green);"></i>
                    <h3 class="fw-bold" style="color: var(--kenya-green);"><?php echo formatCurrency($stats['total_housing_levy'] ?? 0); ?></h3>
                    <h6 class="text-muted mb-2">Housing Levy</h6>
                    <small class="text-muted">Affordable Housing Program</small>
                </div>
            </div>
        </div>

        <!-- Employee Distribution -->
        <div class="row mb-5">
            <div class="col-lg-6">
                <div class="modern-card">
                    <div class="p-4">
                        <h3 class="section-title">
                            <i class="fas fa-users-cog me-2"></i>
                            Employee Distribution
                        </h3>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Permanent Employees</span>
                                <span class="badge rounded-pill" style="background: var(--kenya-green);">
                                    <?php echo number_format($stats['permanent_employees'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar-modern" style="background: var(--kenya-green); width: <?php echo $stats['total_employees'] > 0 ? ($stats['permanent_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Contract Employees</span>
                                <span class="badge rounded-pill" style="background: var(--kenya-red);">
                                    <?php echo number_format($stats['contract_employees'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar-modern" style="background: var(--kenya-red); width: <?php echo $stats['total_employees'] > 0 ? ($stats['contract_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Casual Labourers</span>
                                <span class="badge rounded-pill" style="background: var(--kenya-black);">
                                    <?php echo number_format($stats['casual_employees'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar-modern" style="background: var(--kenya-black); width: <?php echo $stats['total_employees'] > 0 ? ($stats['casual_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Interns</span>
                                <span class="badge rounded-pill bg-secondary">
                                    <?php echo number_format($stats['intern_employees'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar-modern bg-secondary" style="width: <?php echo $stats['total_employees'] > 0 ? ($stats['intern_employees'] / $stats['total_employees']) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="modern-card">
                    <div class="p-4">
                        <h3 class="section-title">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h3>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="index.php?page=employees&action=add" class="quick-action-btn">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Add Employee
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="index.php?page=payroll&action=create" class="quick-action-btn primary">
                                    <i class="fas fa-calculator me-2"></i>
                                    Process Payroll
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="index.php?page=reports" class="quick-action-btn">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Generate Reports
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="index.php?page=leaves" class="quick-action-btn">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Manage Leaves
                                </a>
                            </div>
                        </div>

                        <!-- Kenyan Pride Section -->
                        <div class="kenyan-pride">
                            <i class="fas fa-shield-check me-2"></i>
                            <strong>🇰🇪 Proudly Kenyan</strong><br>
                            <small>Fully compliant with KRA, NSSF, SHIF & Housing Levy regulations</small>
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

    <!-- END ADMIN ONLY DASHBOARD -->
</div>
