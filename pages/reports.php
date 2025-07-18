<?php
/**
 * Reports System - Comprehensive reporting for payroll and statutory compliance
 */

// Security check
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$reportType = $_GET['type'] ?? 'payroll';
$message = '';
$messageType = '';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $format = $_POST['format'] ?? 'html';
    
    if ($startDate && $endDate) {
        switch ($reportType) {
            case 'payroll':
                $reportData = generatePayrollReport($startDate, $endDate);
                break;
            case 'statutory':
                $reportData = generateStatutoryReport($startDate, $endDate);
                break;
            case 'employee':
                $reportData = generateEmployeeReport($startDate, $endDate);
                break;
        }
        
        if ($format === 'pdf') {
            generatePDFReport($reportData, $reportType);
            exit;
        }
    }
}

/**
 * Generate payroll report
 */
function generatePayrollReport($startDate, $endDate) {
    global $db;

    $employeeNameConcat = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);
    $stmt = $db->prepare("
        SELECT
            e.employee_number,
            $employeeNameConcat as employee_name,
            e.id_number,
            d.name as department,
            pp.period_name,
            pp.pay_date,
            pr.basic_salary,
            pr.gross_pay,
            pr.paye_tax,
            pr.nssf_deduction,
            pr.nhif_deduction,
            pr.housing_levy,
            pr.total_deductions,
            pr.net_pay
        FROM payroll_records pr
        JOIN employees e ON pr.employee_id = e.id
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ? 
        AND pp.pay_date BETWEEN ? AND ?
        ORDER BY pp.pay_date DESC, e.employee_number
    ");
    $stmt->execute([$_SESSION['company_id'], $startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Generate statutory report
 */
function generateStatutoryReport($startDate, $endDate) {
    global $db;

    // Check if statutory columns exist
    $hasKraPin = DatabaseUtils::tableExists($db, 'employees') &&
                 $db->query("PRAGMA table_info(employees)")->fetchAll();

    $employeeNameConcat2 = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);

    // Build query with conditional columns
    $kraPin = "'' as kra_pin";
    $nssfNumber = "'' as nssf_number";
    $nhifNumber = "'' as nhif_number";

    // Check if columns exist (simplified approach)
    try {
        $db->query("SELECT kra_pin FROM employees LIMIT 1");
        $kraPin = "e.kra_pin";
        $nssfNumber = "e.nssf_number";
        $nhifNumber = "e.nhif_number";
    } catch (Exception $e) {
        // Columns don't exist, use defaults
    }

    $stmt = $db->prepare("
        SELECT
            e.employee_number,
            $employeeNameConcat2 as employee_name,
            e.id_number,
            $kraPin,
            $nssfNumber,
            $nhifNumber,
            pp.period_name,
            pr.gross_pay,
            pr.taxable_income,
            pr.paye_tax,
            pr.nssf_deduction,
            pr.nhif_deduction,
            pr.housing_levy,
            (pr.paye_tax + pr.nssf_deduction + pr.nhif_deduction + pr.housing_levy) as total_statutory
        FROM payroll_records pr
        JOIN employees e ON pr.employee_id = e.id
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        WHERE e.company_id = ? 
        AND pp.pay_date BETWEEN ? AND ?
        ORDER BY e.employee_number, pp.pay_date
    ");
    $stmt->execute([$_SESSION['company_id'], $startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Generate employee report
 */
function generateEmployeeReport($startDate, $endDate) {
    global $db;

    $employeeNameConcat3 = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);
    $stmt = $db->prepare("
        SELECT
            e.employee_number,
            $employeeNameConcat3 as employee_name,
            e.email,
            e.phone,
            e.hire_date,
            e.basic_salary,
            e.employment_status,
            e.contract_type,
            d.name as department,
            p.title as position,
            COUNT(pr.id) as payroll_periods,
            AVG(pr.net_pay) as avg_net_pay,
            SUM(pr.net_pay) as total_earnings
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN job_positions p ON e.position_id = p.id
        LEFT JOIN payroll_records pr ON e.id = pr.employee_id
        LEFT JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        WHERE e.company_id = ?
        AND (pp.pay_date BETWEEN ? AND ? OR pp.pay_date IS NULL)
        GROUP BY e.id
        ORDER BY e.employee_number
    ");
    $stmt->execute([$_SESSION['company_id'], $startDate, $endDate]);
    return $stmt->fetchAll();
}

/**
 * Get report summary statistics
 */
function getReportSummary($startDate, $endDate) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT pr.employee_id) as total_employees,
            COUNT(DISTINCT pp.id) as total_periods,
            SUM(pr.gross_pay) as total_gross,
            SUM(pr.paye_tax) as total_paye,
            SUM(pr.nssf_deduction) as total_nssf,
            SUM(pr.nhif_deduction) as total_shif,
            SUM(pr.housing_levy) as total_housing,
            SUM(pr.net_pay) as total_net
        FROM payroll_records pr
        JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
        JOIN employees e ON pr.employee_id = e.id
        WHERE e.company_id = ?
        AND pp.pay_date BETWEEN ? AND ?
    ");
    $stmt->execute([$_SESSION['company_id'], $startDate, $endDate]);
    return $stmt->fetch();
}
?>

<!-- Reports Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.reports-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.report-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.report-nav {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.report-nav .nav-link {
    color: var(--kenya-dark-green);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}

.report-nav .nav-link:hover {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
}

.report-nav .nav-link.active {
    background: var(--kenya-green);
    color: white;
}

.summary-card {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.report-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.report-table th {
    background: var(--kenya-green);
    color: white;
    border: none;
    padding: 1rem;
    font-weight: 600;
}

.report-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.btn-generate {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.reports-summary-card {
            background: rgba(0, 77, 46, 0.2); /* semi-transparent kenya dark green */
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(206,17,38,0.3);
    color: white;
}
</style>

<div class="container-fluid">
    <!-- Reports Hero Section -->
    <div class="reports-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-chart-bar me-3"></i>
                        Reports & Analytics
                    </h1>
                    <p class="mb-0 opacity-75">
                        📊 Comprehensive payroll and statutory reporting for Kenyan compliance
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="reports-summary-card">
                        <h5 class="mb-1">Report Center</h5>
                        <small class="opacity-75">Generate professional reports</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Navigation -->
    <div class="report-nav">
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?php echo $reportType === 'payroll' ? 'active' : ''; ?>" 
                   href="index.php?page=reports&type=payroll">
                    <i class="fas fa-money-bill-wave me-2"></i>Payroll Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $reportType === 'statutory' ? 'active' : ''; ?>" 
                   href="index.php?page=reports&type=statutory">
                    <i class="fas fa-shield-alt me-2"></i>Statutory Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $reportType === 'employee' ? 'active' : ''; ?>" 
                   href="index.php?page=reports&type=employee">
                    <i class="fas fa-users me-2"></i>Employee Reports
                </a>
            </li>
        </ul>
    </div>

    <!-- Report Generation Form -->
    <div class="row">
        <div class="col-lg-4">
            <div class="report-card">
                <div class="p-4">
                    <h5 class="mb-3">
                        <i class="fas fa-cog text-primary me-2"></i>
                        Generate Report
                    </h5>
                    
                    <form method="POST">
                        <input type="hidden" name="generate_report" value="1">
                        
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="format" class="form-label">Format</label>
                            <select class="form-select" id="format" name="format">
                                <option value="html">View Online</option>
                                <option value="pdf">Download PDF</option>
                                <option value="excel">Export Excel</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-generate w-100">
                            <i class="fas fa-chart-line me-2"></i>
                            Generate Report
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <?php if (isset($reportData)): ?>
                <?php $summary = getReportSummary($_POST['start_date'], $_POST['end_date']); ?>
                <div class="summary-card">
                    <h6 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Report Summary
                    </h6>
                    <div class="row text-center">
                        <div class="col-6 mb-2">
                            <h4><?php echo number_format($summary['total_employees']); ?></h4>
                            <small class="opacity-75">Employees</small>
                        </div>
                        <div class="col-6 mb-2">
                            <h4><?php echo formatCurrency($summary['total_gross']); ?></h4>
                            <small class="opacity-75">Gross Pay</small>
                        </div>
                        <div class="col-6">
                            <h4><?php echo formatCurrency($summary['total_paye']); ?></h4>
                            <small class="opacity-75">PAYE Tax</small>
                        </div>
                        <div class="col-6">
                            <h4><?php echo formatCurrency($summary['total_net']); ?></h4>
                            <small class="opacity-75">Net Pay</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-8">
            <?php if (isset($reportData) && !empty($reportData)): ?>
                <!-- Report Results -->
                <div class="report-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php if ($reportType === 'payroll'): ?>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Period</th>
                                        <th>Gross Pay</th>
                                        <th>Deductions</th>
                                        <th>Net Pay</th>
                                    <?php elseif ($reportType === 'statutory'): ?>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>ID Number</th>
                                        <th>PAYE</th>
                                        <th>NSSF</th>
                                        <th>SHIF</th>
                                        <th>Housing Levy</th>
                                    <?php else: ?>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Total Earnings</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <?php if ($reportType === 'payroll'): ?>
                                            <td><?php echo htmlspecialchars($row['employee_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['period_name']); ?></td>
                                            <td><?php echo formatCurrency($row['gross_pay']); ?></td>
                                            <td><?php echo formatCurrency($row['total_deductions']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency($row['net_pay']); ?></td>
                                        <?php elseif ($reportType === 'statutory'): ?>
                                            <td><?php echo htmlspecialchars($row['employee_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                            <td><?php echo formatCurrency($row['paye_tax']); ?></td>
                                            <td><?php echo formatCurrency($row['nssf_deduction']); ?></td>
                                            <td><?php echo formatCurrency($row['nhif_deduction']); ?></td>
                                            <td><?php echo formatCurrency($row['housing_levy']); ?></td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars($row['employee_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $row['employment_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($row['employment_status']); ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold"><?php echo formatCurrency($row['total_earnings'] ?? 0); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Data Message -->
                <div class="report-card">
                    <div class="p-5 text-center">
                        <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                        <h4>Generate Your First Report</h4>
                        <p class="text-muted">
                            Select a date range and click "Generate Report" to view 
                            <?php echo ucfirst($reportType); ?> data.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
