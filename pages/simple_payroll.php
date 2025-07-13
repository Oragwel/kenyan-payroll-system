<?php
/**
 * Simplified Payroll Management - Direct and User-Friendly
 */

if (!hasPermission('hr')) {
    header('Location: index.php?page=dashboard');
    exit;
}

$message = '';
$messageType = '';

// Handle direct payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_payroll') {
        $selectedEmployees = $_POST['employees'] ?? [];
        $payPeriod = $_POST['pay_period'] ?? date('Y-m');
        $payDate = $_POST['pay_date'] ?? date('Y-m-d');
        
        if (empty($selectedEmployees)) {
            $message = 'Please select at least one employee';
            $messageType = 'warning';
        } else {
            // Use flexible validation
            require_once 'flexible_date_validation.php';

            $validation = validateQuickPayroll($payPeriod, $payDate);

            if (!$validation['valid']) {
                $message = implode(' ', $validation['errors']);
                $messageType = 'warning';
            } else {
                try {
                    $db->beginTransaction();

                    $startDate = $validation['start_date'];
                    $endDate = $validation['end_date'];

                    // Check if period already exists (non-blocking)
                    $existingPeriod = checkPayrollPeriodExists($_SESSION['company_id'], $startDate, $endDate);

                    if ($existingPeriod) {
                        $batchName = $existingPeriod . ' (Reprocessed)';
                    } else {
                        $batchName = generatePeriodName($startDate, $endDate);
                    }

                    // Create payroll batch
                    $stmt = $db->prepare("
                        INSERT INTO payroll_periods (company_id, period_name, start_date, end_date, pay_date, created_by, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'completed')
                    ");
                
                $stmt->execute([
                    $_SESSION['company_id'], 
                    $batchName, 
                    $startDate, 
                    $endDate, 
                    $payDate, 
                    $_SESSION['user_id']
                ]);
                
                $payrollPeriodId = $db->lastInsertId();
                $processedCount = 0;
                
                // Process selected employees
                foreach ($selectedEmployees as $employeeId) {
                    // Get employee data
                    $stmt = $db->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
                    $stmt->execute([$employeeId, $_SESSION['company_id']]);
                    $employee = $stmt->fetch();
                    
                    if ($employee) {
                        // Calculate payroll
                        $payrollData = processEmployeePayroll(
                            $employee['id'],
                            $payrollPeriodId,
                            $employee['basic_salary'],
                            [], // Allowances
                            [], // Deductions
                            30, // Days worked
                            0,  // Overtime hours
                            0,  // Overtime rate
                            $employee['contract_type'] ?? 'permanent'
                        );
                        
                        // Insert payroll record
                        $stmt = $db->prepare("
                            INSERT INTO payroll_records (
                                employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                                paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                                total_deductions, net_pay, days_worked, overtime_hours, overtime_amount, company_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $employee['id'], $payrollPeriodId, $payrollData['basic_salary'],
                            $payrollData['gross_pay'], $payrollData['taxable_income'],
                            $payrollData['paye_tax'], $payrollData['nssf_deduction'],
                            $payrollData['nhif_deduction'], $payrollData['housing_levy'],
                            $payrollData['total_allowances'], $payrollData['total_deductions'],
                            $payrollData['net_pay'], $payrollData['days_worked'],
                            $payrollData['overtime_hours'], $payrollData['overtime_amount'],
                            $_SESSION['company_id']
                        ]);
                        
                        $processedCount++;
                    }
                }
                
                $db->commit();
                $message = "✅ Payroll processed successfully for $processedCount employees!";
                $messageType = 'success';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error processing payroll: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all active employees
$stmt = $db->prepare("
    SELECT e.*, d.name as department_name, p.title as position_title
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN job_positions p ON e.position_id = p.id
    WHERE e.company_id = ? AND e.employment_status = 'active'
    ORDER BY e.first_name, e.last_name
");
$stmt->execute([$_SESSION['company_id']]);
$employees = $stmt->fetchAll();

// Get recent payroll periods
$stmt = $db->prepare("
    SELECT * FROM payroll_periods 
    WHERE company_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['company_id']]);
$recentPayrolls = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-money-check-alt"></i> Quick Payroll Processing</h2>
        <div class="btn-group">
            <a href="index.php?page=payroll" class="btn btn-outline-secondary">
                <i class="fas fa-cog"></i> Advanced Payroll
            </a>
            <a href="index.php?page=payslips" class="btn btn-outline-info">
                <i class="fas fa-file-invoice"></i> View Payslips
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Quick Payroll Processing -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-rocket"></i> Process Payroll - Quick & Easy</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="quickPayrollForm">
                        <input type="hidden" name="action" value="process_payroll">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Pay Period</label>
                                <input type="month" class="form-control" name="pay_period" 
                                       value="<?php echo date('Y-m'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pay Date</label>
                                <input type="date" class="form-control" name="pay_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Select Employees</h6>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                                        <i class="fas fa-check-double"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="selectNone()">
                                        <i class="fas fa-times"></i> Clear All
                                    </button>
                                </div>
                            </div>
                            
                            <div class="employee-list" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($employees as $emp): ?>
                                    <div class="form-check employee-item p-3 border rounded mb-2">
                                        <input class="form-check-input" type="checkbox" name="employees[]" 
                                               value="<?php echo $emp['id']; ?>" id="emp_<?php echo $emp['id']; ?>">
                                        <label class="form-check-label w-100" for="emp_<?php echo $emp['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($emp['department_name'] ?? 'No Department'); ?> • 
                                                        <?php echo htmlspecialchars($emp['position_title'] ?? 'No Position'); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <strong class="text-success"><?php echo formatCurrency($emp['basic_salary']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo ucfirst($emp['contract_type'] ?? 'permanent'); ?></small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-play"></i> Process Payroll Now
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Recent Payrolls -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-bolt"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="processAllEmployees()">
                            <i class="fas fa-users"></i> Process All Employees
                        </button>
                        <button type="button" class="btn btn-info" onclick="processMonthlyPayroll()">
                            <i class="fas fa-calendar"></i> Monthly Payroll
                        </button>
                        <a href="index.php?page=payslips" class="btn btn-success">
                            <i class="fas fa-file-pdf"></i> Generate Payslips
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Payrolls -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-history"></i> Recent Payrolls</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPayrolls)): ?>
                        <p class="text-muted">No payroll records yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentPayrolls as $payroll): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <strong><?php echo htmlspecialchars($payroll['period_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($payroll['pay_date'])); ?>
                                </small>
                                <span class="badge bg-success float-end">Completed</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('input[name="employees[]"]').forEach(cb => cb.checked = true);
}

function selectNone() {
    document.querySelectorAll('input[name="employees[]"]').forEach(cb => cb.checked = false);
}

function processAllEmployees() {
    selectAll();
    document.getElementById('quickPayrollForm').submit();
}

function processMonthlyPayroll() {
    // Set current month and select all employees
    document.querySelector('input[name="pay_period"]').value = '<?php echo date('Y-m'); ?>';
    selectAll();
    document.getElementById('quickPayrollForm').submit();
}

// Form submission with loading state
document.getElementById('quickPayrollForm').addEventListener('submit', function(e) {
    const selectedEmployees = document.querySelectorAll('input[name="employees[]"]:checked');
    
    if (selectedEmployees.length === 0) {
        e.preventDefault();
        alert('Please select at least one employee to process payroll.');
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});
</script>

<style>
.employee-item {
    transition: all 0.2s;
    cursor: pointer;
}

.employee-item:hover {
    background-color: #f8f9fa;
    border-color: #007bff !important;
}

.employee-item input:checked + label {
    background-color: #e3f2fd;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
}
</style>
