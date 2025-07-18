<?php
/**
 * Statutory Reporting System for Kenyan Compliance
 * Generate PAYE, NSSF, SHIF/NHIF, and Housing Levy reports
 */

// Security check - HR/Admin only
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$reportType = $_GET['type'] ?? 'paye';
$message = '';
$messageType = '';

// CRITICAL: Fix payroll_records table structure BEFORE any operations
try {
    // Add ALL missing columns to payroll_records table if they don't exist
    $columnsToAdd = [
        'taxable_income' => 'ALTER TABLE payroll_records ADD COLUMN taxable_income DECIMAL(12,2) DEFAULT 0 AFTER gross_pay',
        'paye_tax' => 'ALTER TABLE payroll_records ADD COLUMN paye_tax DECIMAL(12,2) DEFAULT 0 AFTER taxable_income',
        'nssf_deduction' => 'ALTER TABLE payroll_records ADD COLUMN nssf_deduction DECIMAL(12,2) DEFAULT 0 AFTER paye_tax',
        'nhif_deduction' => 'ALTER TABLE payroll_records ADD COLUMN nhif_deduction DECIMAL(12,2) DEFAULT 0 AFTER nssf_deduction',
        'housing_levy' => 'ALTER TABLE payroll_records ADD COLUMN housing_levy DECIMAL(12,2) DEFAULT 0 AFTER nhif_deduction',
        'total_allowances' => 'ALTER TABLE payroll_records ADD COLUMN total_allowances DECIMAL(12,2) DEFAULT 0 AFTER housing_levy',
        'total_deductions' => 'ALTER TABLE payroll_records ADD COLUMN total_deductions DECIMAL(12,2) DEFAULT 0 AFTER total_allowances',
        'overtime_hours' => 'ALTER TABLE payroll_records ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0 AFTER total_deductions',
        'overtime_amount' => 'ALTER TABLE payroll_records ADD COLUMN overtime_amount DECIMAL(12,2) DEFAULT 0 AFTER overtime_hours',
        'days_worked' => 'ALTER TABLE payroll_records ADD COLUMN days_worked INT DEFAULT 30 AFTER overtime_amount'
    ];

    foreach ($columnsToAdd as $column => $sql) {
        try {
            // Check if column exists using database-agnostic approach
            $db->query("SELECT $column FROM payroll_records LIMIT 1");
            // Column exists, skip
        } catch (Exception $e) {
            // Column doesn't exist, try to add it
            try {
                $db->exec($sql);
            } catch (Exception $e2) {
                // Column might already exist or other error, continue
            }
        }
    }

    // Update taxable_income for existing records where it's 0 or NULL
    try {
        $db->exec("UPDATE payroll_records SET taxable_income = gross_pay WHERE taxable_income IS NULL OR taxable_income = 0");
    } catch (Exception $e) {
        // Continue if update fails
    }

} catch (Exception $e) {
    // Continue even if table fixes fail
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'generate':
            $result = generateStatutoryReport($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'export':
            exportStatutoryReport($_POST);
            exit;
            break;
    }
}

/**
 * Generate statutory report
 */
function generateStatutoryReport($data) {
    global $db;

    $reportType = $data['report_type'];
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];

    try {
        // Check which columns exist in payroll_records table using database-agnostic approach
        $hasTaxableIncome = false;
        $hasTotalAllowances = false;

        try {
            $db->query("SELECT taxable_income FROM payroll_records LIMIT 1");
            $hasTaxableIncome = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        try {
            $db->query("SELECT total_allowances FROM payroll_records LIMIT 1");
            $hasTotalAllowances = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        $hasAllowances = false;
        $hasOtherDeductions = false;

        try {
            $db->query("SELECT allowances FROM payroll_records LIMIT 1");
            $hasAllowances = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        try {
            $db->query("SELECT other_deductions FROM payroll_records LIMIT 1");
            $hasOtherDeductions = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        // Check if statutory columns exist in employees table
        $hasKraPin = false;
        $hasNssfNumber = false;
        $hasNhifNumber = false;

        try {
            $db->query("SELECT kra_pin FROM employees LIMIT 1");
            $hasKraPin = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        try {
            $db->query("SELECT nssf_number FROM employees LIMIT 1");
            $hasNssfNumber = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        try {
            $db->query("SELECT nhif_number FROM employees LIMIT 1");
            $hasNhifNumber = true;
        } catch (Exception $e) {
            // Column doesn't exist
        }

        // Build dynamic SELECT query based on available columns
        $employeeNameConcat = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);
        $selectFields = [
            'e.employee_number',
            "$employeeNameConcat as employee_name",
            'e.id_number',
            $hasKraPin ? 'e.kra_pin' : "'' as kra_pin",
            $hasNssfNumber ? 'e.nssf_number' : "'' as nssf_number",
            $hasNhifNumber ? 'e.nhif_number' : "'' as nhif_number",
            'pp.period_name',
            'pp.pay_date',
            'pr.basic_salary',
            'pr.gross_pay',
            'pr.paye_tax',
            'pr.nssf_deduction',
            'pr.nhif_deduction',
            'pr.housing_levy',
            'pr.total_deductions',
            'pr.net_pay'
        ];

        // Add conditional fields
        if ($hasTaxableIncome) {
            $selectFields[] = 'pr.taxable_income';
        } else {
            $selectFields[] = 'pr.gross_pay as taxable_income';
        }

        if ($hasTotalAllowances) {
            $selectFields[] = 'pr.total_allowances';
        } elseif ($hasAllowances) {
            $selectFields[] = 'pr.allowances as total_allowances';
        } else {
            $selectFields[] = '0 as total_allowances';
        }

        // Get payroll data for the period
        $sql = "
            SELECT " . implode(', ', $selectFields) . "
            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
            WHERE e.company_id = ?
            AND pp.pay_date BETWEEN ? AND ?
            ORDER BY e.employee_number, pp.pay_date
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$_SESSION['company_id'], $startDate, $endDate]);
        $payrollData = $stmt->fetchAll();
        
        if (empty($payrollData)) {
            return ['message' => 'No payroll data found for the selected period', 'type' => 'warning'];
        }
        
        // Calculate totals based on report type
        $totalAmount = 0;
        foreach ($payrollData as $record) {
            switch ($reportType) {
                case 'paye':
                    $totalAmount += $record['paye_tax'];
                    break;
                case 'nssf':
                    $totalAmount += $record['nssf_deduction'];
                    break;
                case 'shif':
                    $totalAmount += $record['nhif_deduction'];
                    break;
                case 'housing_levy':
                    $totalAmount += $record['housing_levy'];
                    break;
            }
        }
        
        // Save report record
        $stmt = $db->prepare("
            INSERT INTO statutory_reports (company_id, report_type, period_start, period_end, total_amount, generated_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['company_id'], $reportType, $startDate, $endDate, $totalAmount, $_SESSION['user_id']]);
        
        logActivity('statutory_report_generate', "Generated $reportType report for period $startDate to $endDate (Total: KES " . number_format($totalAmount, 2) . ")");
        
        return ['message' => 'Statutory report generated successfully', 'type' => 'success', 'data' => $payrollData, 'total' => $totalAmount];
        
    } catch (Exception $e) {
        return ['message' => 'Error generating report: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Export statutory report
 */
function exportStatutoryReport($data) {
    $reportType = $data['report_type'];
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    $format = $data['format'] ?? 'csv';
    
    // Generate report data
    $result = generateStatutoryReport($data);
    if ($result['type'] !== 'success') {
        return;
    }
    
    $payrollData = $result['data'];
    $filename = strtoupper($reportType) . "_Report_" . date('Y-m-d', strtotime($startDate)) . "_to_" . date('Y-m-d', strtotime($endDate));
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers based on report type
        switch ($reportType) {
            case 'paye':
                fputcsv($output, ['Employee Number', 'Employee Name', 'ID Number', 'KRA PIN', 'Period', 'Gross Pay', 'Taxable Income', 'PAYE Tax']);
                foreach ($payrollData as $record) {
                    fputcsv($output, [
                        $record['employee_number'],
                        $record['employee_name'],
                        $record['id_number'],
                        $record['kra_pin'],
                        $record['period_name'],
                        $record['gross_pay'],
                        $record['taxable_income'],
                        $record['paye_tax']
                    ]);
                }
                break;
                
            case 'nssf':
                fputcsv($output, ['Employee Number', 'Employee Name', 'ID Number', 'NSSF Number', 'Period', 'Gross Pay', 'NSSF Contribution']);
                foreach ($payrollData as $record) {
                    fputcsv($output, [
                        $record['employee_number'],
                        $record['employee_name'],
                        $record['id_number'],
                        $record['nssf_number'],
                        $record['period_name'],
                        $record['gross_pay'],
                        $record['nssf_deduction']
                    ]);
                }
                break;
                
            case 'shif':
                fputcsv($output, ['Employee Number', 'Employee Name', 'ID Number', 'NHIF Number', 'Period', 'Gross Pay', 'SHIF Contribution']);
                foreach ($payrollData as $record) {
                    fputcsv($output, [
                        $record['employee_number'],
                        $record['employee_name'],
                        $record['id_number'],
                        $record['nhif_number'],
                        $record['period_name'],
                        $record['gross_pay'],
                        $record['nhif_deduction']
                    ]);
                }
                break;
                
            case 'housing_levy':
                fputcsv($output, ['Employee Number', 'Employee Name', 'ID Number', 'Period', 'Gross Pay', 'Housing Levy']);
                foreach ($payrollData as $record) {
                    fputcsv($output, [
                        $record['employee_number'],
                        $record['employee_name'],
                        $record['id_number'],
                        $record['period_name'],
                        $record['gross_pay'],
                        $record['housing_levy']
                    ]);
                }
                break;
        }
        
        fclose($output);
    }
}

// Create statutory_reports table if it doesn't exist and add missing columns to payroll_records
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS statutory_reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            report_type ENUM('paye', 'nssf', 'shif', 'housing_levy') NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL,
            file_path VARCHAR(255),
            status ENUM('generated', 'submitted') DEFAULT 'generated',
            generated_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES users(id)
        )
    ");

    // Note: Column addition is now handled at the top of the file

} catch (Exception $e) {
    // Table creation failed, but continue
}

// Get recent reports
$recentReports = [];
if (DatabaseUtils::tableExists($db, 'statutory_reports')) {
    $stmt = $db->prepare("
        SELECT sr.*,
               u.username as generated_by_name
        FROM statutory_reports sr
        LEFT JOIN users u ON sr.generated_by = u.id
        WHERE sr.company_id = ?
        ORDER BY sr.generated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $recentReports = $stmt->fetchAll();
}

// Get summary statistics
$reportStats = [];
if (DatabaseUtils::tableExists($db, 'statutory_reports')) {
    $twelveMonthsAgo = DatabaseUtils::monthsAgo(12);
    $stmt = $db->prepare("
        SELECT
            report_type,
            COUNT(*) as report_count,
            SUM(total_amount) as total_amount,
            MAX(generated_at) as last_generated
        FROM statutory_reports
        WHERE company_id = ?
        AND generated_at >= ?
        GROUP BY report_type
    ");
    $stmt->execute([$_SESSION['company_id'], $twelveMonthsAgo]);
    $reportStats = $stmt->fetchAll();
}

// Get company info for reports
$stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statutory Reporting - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
            --kenya-black: #000000;
        }

        .statutory-header {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .statutory-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .statutory-card:hover {
            transform: translateY(-2px);
        }

        .btn-statutory {
            background: linear-gradient(135deg, var(--kenya-black), #333333);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-statutory:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            color: white;
        }

        .report-type-card {
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .report-type-card:hover {
            border-color: var(--kenya-green);
            transform: translateY(-3px);
        }

        .report-type-card.active {
            border-color: var(--kenya-red);
            background: #fff5f5;
        }

        .statutory-summary-card {
            background: rgba(0, 77, 46, 0.2); /* semi-transparent kenya dark green */
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .badge-paye { background: var(--kenya-red); }
        .badge-nssf { background: var(--kenya-green); }
        .badge-shif { background: var(--kenya-gold); color: var(--kenya-black); }
        .badge-housing { background: var(--kenya-black); }

        .kenyan-compliance-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left: 4px solid var(--kenya-gold);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
        }

        .stats-card {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="statutory-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-shield-alt me-3"></i>
                        Statutory Reporting Center
                    </h1>
                    <p class="mb-0 opacity-75">
                        🇰🇪 Generate compliance reports for KRA, NSSF, SHIF, and Housing Levy
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="statutory-summary-card">
                        <h5 class="mb-1"><?php echo htmlspecialchars($company['name'] ?? 'Company'); ?></h5>
                        <small class="opacity-75">KRA PIN: <?php echo htmlspecialchars($company['kra_pin'] ?? 'Not Set'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'dashboard' || $action === 'generate'): ?>
            <!-- Dashboard -->
            <div class="row">
                <!-- Report Generation -->
                <div class="col-lg-8">
                    <div class="statutory-summery-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-file-alt me-2"></i>
                                Generate Statutory Report
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?page=statutory&action=generate">
                                <div class="row">
                                    <div class="col-12 mb-4">
                                        <label class="form-label">Select Report Type *</label>
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <div class="report-type-card p-3 rounded text-center <?php echo $reportType === 'paye' ? 'active' : ''; ?>"
                                                     onclick="selectReportType('paye')">
                                                    <i class="fas fa-calculator fa-2x text-danger mb-2"></i>
                                                    <h6>PAYE Tax</h6>
                                                    <small class="text-muted">Pay As You Earn</small>
                                                    <input type="radio" name="report_type" value="paye"
                                                           <?php echo $reportType === 'paye' ? 'checked' : ''; ?> style="display: none;">
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="report-type-card p-3 rounded text-center <?php echo $reportType === 'nssf' ? 'active' : ''; ?>"
                                                     onclick="selectReportType('nssf')">
                                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                                    <h6>NSSF</h6>
                                                    <small class="text-muted">Pension Contributions</small>
                                                    <input type="radio" name="report_type" value="nssf"
                                                           <?php echo $reportType === 'nssf' ? 'checked' : ''; ?> style="display: none;">
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="report-type-card p-3 rounded text-center <?php echo $reportType === 'shif' ? 'active' : ''; ?>"
                                                     onclick="selectReportType('shif')">
                                                    <i class="fas fa-heartbeat fa-2x text-warning mb-2"></i>
                                                    <h6>SHIF/NHIF</h6>
                                                    <small class="text-muted">Health Insurance</small>
                                                    <input type="radio" name="report_type" value="shif"
                                                           <?php echo $reportType === 'shif' ? 'checked' : ''; ?> style="display: none;">
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="report-type-card p-3 rounded text-center <?php echo $reportType === 'housing_levy' ? 'active' : ''; ?>"
                                                     onclick="selectReportType('housing_levy')">
                                                    <i class="fas fa-home fa-2x text-dark mb-2"></i>
                                                    <h6>Housing Levy</h6>
                                                    <small class="text-muted">Affordable Housing</small>
                                                    <input type="radio" name="report_type" value="housing_levy"
                                                           <?php echo $reportType === 'housing_levy' ? 'checked' : ''; ?> style="display: none;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="start_date" class="form-label">Period Start Date *</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date"
                                                   value="<?php echo date('Y-m-01'); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">Period End Date *</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date"
                                                   value="<?php echo date('Y-m-t'); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" class="btn btn-statutory me-2">
                                            <i class="fas fa-file-alt me-2"></i>Generate Report
                                        </button>
                                        <button type="submit" name="action" value="export" class="btn btn-outline-success">
                                            <i class="fas fa-download me-2"></i>Export CSV
                                        </button>
                                    </div>
                                    <small class="text-muted align-self-center">
                                        Reports are generated based on processed payroll data
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Compliance Guidelines -->
                    <div class="statutory-card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-flag me-2"></i>
                                🇰🇪 Kenyan Compliance Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="kenyan-compliance-info">
                                <h6>Statutory Requirements:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-calculator text-danger me-2"></i>
                                        <strong>PAYE:</strong> Monthly submission to KRA
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-shield-alt text-success me-2"></i>
                                        <strong>NSSF:</strong> 6% of pensionable pay (max KES 2,160)
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-heartbeat text-warning me-2"></i>
                                        <strong>SHIF:</strong> 2.75% of gross salary
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-home text-dark me-2"></i>
                                        <strong>Housing Levy:</strong> 1.5% of gross salary
                                    </li>
                                </ul>
                            </div>

                            <hr>

                            <h6>Submission Deadlines:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-danger me-2"></i>
                                    PAYE: 9th of following month
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-success me-2"></i>
                                    NSSF: 15th of following month
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-warning me-2"></i>
                                    SHIF: 9th of following month
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-dark me-2"></i>
                                    Housing Levy: 9th of following month
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics and Recent Reports -->
            <div class="row">
                <!-- Statistics -->
                <div class="col-lg-8">
                    <div class="statutory-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Statutory Reporting Statistics (Last 12 Months)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportStats)): ?>
                                <div class="row">
                                    <?php foreach ($reportStats as $stat): ?>
                                        <div class="col-md-3 mb-3">
                                            <div class="stats-card">
                                                <div class="h4 mb-1">
                                                    <?php
                                                    switch ($stat['report_type']) {
                                                        case 'paye': echo '<i class="fas fa-calculator text-danger"></i>'; break;
                                                        case 'nssf': echo '<i class="fas fa-shield-alt text-success"></i>'; break;
                                                        case 'shif': echo '<i class="fas fa-heartbeat text-warning"></i>'; break;
                                                        case 'housing_levy': echo '<i class="fas fa-home text-dark"></i>'; break;
                                                    }
                                                    ?>
                                                </div>
                                                <h6><?php echo strtoupper($stat['report_type']); ?></h6>
                                                <div class="small">
                                                    <strong><?php echo $stat['report_count']; ?></strong> reports<br>
                                                    <strong>KES <?php echo number_format($stat['total_amount'], 2); ?></strong><br>
                                                    <small class="text-muted">Last: <?php echo formatDate($stat['last_generated']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <h5>No Reports Generated Yet</h5>
                                    <p class="text-muted">Generate your first statutory report to see statistics here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Reports -->
                <div class="col-lg-4">
                    <div class="statutory-card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Recent Reports
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentReports)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($recentReports, 0, 5) as $report): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="badge badge-<?php echo $report['report_type']; ?> mb-1">
                                                        <?php echo strtoupper($report['report_type']); ?>
                                                    </span>
                                                    <div class="small">
                                                        <?php echo formatDate($report['period_start']); ?> -
                                                        <?php echo formatDate($report['period_end']); ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        By <?php echo htmlspecialchars($report['generated_by_name']); ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <strong class="small">KES <?php echo number_format($report['total_amount'], 2); ?></strong>
                                                    <div class="small text-muted"><?php echo formatDate($report['created_at']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-file-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-0">No reports generated yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Report type selection
        function selectReportType(type) {
            // Remove active class from all cards
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.classList.remove('active');
            });

            // Add active class to selected card
            event.currentTarget.classList.add('active');

            // Check the radio button
            document.querySelector(`input[value="${type}"]`).checked = true;

            // Update URL parameter
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            window.history.replaceState({}, '', url);
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    const reportType = document.querySelector('input[name="report_type"]:checked');

                    if (!reportType) {
                        e.preventDefault();
                        alert('Please select a report type');
                        return false;
                    }

                    if (new Date(startDate) > new Date(endDate)) {
                        e.preventDefault();
                        alert('Start date cannot be after end date');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
