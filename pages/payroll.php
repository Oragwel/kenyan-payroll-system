<?php
/**
 * Payroll Processing Page
 */

if (!hasPermission('hr')) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Handle payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'process') {
    $periodName = sanitizeInput($_POST['period_name']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $payDate = $_POST['pay_date'];
    
    if (empty($periodName) || empty($startDate) || empty($endDate) || empty($payDate)) {
        $message = 'Please fill in all required fields';
        $messageType = 'danger';
    } else {
        // Use corrected date validation
        require_once 'corrected_date_validation.php';

        $validation = validatePayrollDates($startDate, $endDate, $payDate, $_SESSION['company_id']);

        if (!$validation['valid']) {
            $message = implode(' ', $validation['errors']);
            $messageType = 'danger';
        }
    }

    // Only proceed if no validation errors
    if (empty($message)) {
        try {
            $db->beginTransaction();
            
            // Create payroll period
            $stmt = $db->prepare("
                INSERT INTO payroll_periods (company_id, period_name, start_date, end_date, pay_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['company_id'], $periodName, $startDate, $endDate, $payDate, $_SESSION['user_id']]);
            $payrollPeriodId = $db->lastInsertId();
            
            // Get all active employees with contract type
            $stmt = $db->prepare("
                SELECT e.*,
                       COALESCE(SUM(ea.amount), 0) as total_allowances,
                       COALESCE(SUM(ed.amount), 0) as total_deductions
                FROM employees e
                LEFT JOIN employee_allowances ea ON e.id = ea.employee_id AND ea.is_active = 1
                LEFT JOIN employee_deductions ed ON e.id = ed.employee_id AND ed.is_active = 1
                WHERE e.company_id = ? AND e.employment_status = 'active'
                GROUP BY e.id
            ");
            $stmt->execute([$_SESSION['company_id']]);
            $employees = $stmt->fetchAll();

            $processedCount = 0;

            foreach ($employees as $employee) {
                // Calculate payroll for each employee based on contract type
                $payrollData = processEmployeePayroll(
                    $employee['id'],
                    $payrollPeriodId,
                    $employee['basic_salary'],
                    [], // Allowances will be fetched separately
                    [], // Deductions will be fetched separately
                    30, // Default days worked
                    0,  // Overtime hours
                    0,  // Overtime rate
                    $employee['contract_type'] // Pass contract type for exemptions
                );
                
                // Insert payroll record
                $stmt = $db->prepare("
                    INSERT INTO payroll_records (
                        employee_id, payroll_period_id, basic_salary, gross_pay, taxable_income,
                        paye_tax, nssf_deduction, nhif_deduction, housing_levy, total_allowances,
                        total_deductions, net_pay, days_worked, overtime_hours, overtime_amount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $employee['id'], $payrollPeriodId, $payrollData['basic_salary'],
                    $payrollData['gross_pay'], $payrollData['taxable_income'],
                    $payrollData['paye_tax'], $payrollData['nssf_deduction'],
                    $payrollData['nhif_deduction'], $payrollData['housing_levy'],
                    $payrollData['total_allowances'], $payrollData['total_deductions'],
                    $payrollData['net_pay'], $payrollData['days_worked'],
                    $payrollData['overtime_hours'], $payrollData['overtime_amount']
                ]);
                
                $processedCount++;
            }
            
            // Update payroll period status
            $stmt = $db->prepare("UPDATE payroll_periods SET status = 'completed' WHERE id = ?");
            $stmt->execute([$payrollPeriodId]);
            
            $db->commit();
            
            $message = "Payroll processed successfully for $processedCount employees";
            $messageType = 'success';
            logActivity('payroll_process', "Processed payroll for period: $periodName");
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Failed to process payroll: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get payroll periods
if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT pp.*, u.username as created_by_name,
               COUNT(pr.id) as employee_count,
               SUM(pr.net_pay) as total_net_pay
        FROM payroll_periods pp
        LEFT JOIN users u ON pp.created_by = u.id
        LEFT JOIN payroll_records pr ON pp.id = pr.payroll_period_id
        WHERE pp.company_id = ?
        GROUP BY pp.id
        ORDER BY pp.created_at DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $payrollPeriods = $stmt->fetchAll();
}

// Get payroll details for viewing
if ($action === 'view' && isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT pp.*, u.username as created_by_name
        FROM payroll_periods pp
        LEFT JOIN users u ON pp.created_by = u.id
        WHERE pp.id = ? AND pp.company_id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    $payrollPeriod = $stmt->fetch();
    
    if ($payrollPeriod) {
        $stmt = $db->prepare("
            SELECT pr.*, e.employee_number, e.first_name, e.last_name
            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            WHERE pr.payroll_period_id = ?
            ORDER BY e.first_name, e.last_name
        ");
        $stmt->execute([$_GET['id']]);
        $payrollRecords = $stmt->fetchAll();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-calculator"></i> Payroll Management</h2>
                <?php if ($action === 'list'): ?>
                    <a href="index.php?page=payroll&action=create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Process New Payroll
                    </a>
                <?php else: ?>
                    <a href="index.php?page=payroll" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <!-- Payroll Periods List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Payroll Periods</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($payrollPeriods)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Period Name</th>
                                    <th>Period</th>
                                    <th>Pay Date</th>
                                    <th>Employees</th>
                                    <th>Total Net Pay</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payrollPeriods as $period): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($period['period_name']); ?></strong></td>
                                        <td>
                                            <?php echo formatDate($period['start_date']); ?> - 
                                            <?php echo formatDate($period['end_date']); ?>
                                        </td>
                                        <td><?php echo formatDate($period['pay_date']); ?></td>
                                        <td><?php echo $period['employee_count']; ?></td>
                                        <td><?php echo formatCurrency($period['total_net_pay'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $period['status'] === 'completed' ? 'success' : 
                                                    ($period['status'] === 'processing' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($period['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($period['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=payroll&action=view&id=<?php echo $period['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="index.php?page=reports&type=payroll&period_id=<?php echo $period['id']; ?>" 
                                                   class="btn btn-outline-success" title="Generate Report">
                                                    <i class="fas fa-file-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calculator fa-3x text-muted mb-3"></i>
                        <h5>No Payroll Periods Found</h5>
                        <p class="text-muted">Start by processing your first payroll period.</p>
                        <a href="index.php?page=payroll&action=create" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Process New Payroll
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action === 'create'): ?>
        <!-- Create New Payroll -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Process New Payroll</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="index.php?page=payroll&action=process" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_name" class="form-label">Period Name *</label>
                                <input type="text" class="form-control" id="period_name" name="period_name" 
                                       placeholder="e.g., January 2024" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pay_date" class="form-label">Pay Date *</label>
                                <input type="date" class="form-control" id="pay_date" name="pay_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Period Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Period End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Payroll Processing Information</h6>
                        <ul class="mb-0">
                            <li>This will process payroll for all active employees</li>
                            <li>Statutory deductions (PAYE, NSSF, NHIF, Housing Levy) will be calculated automatically</li>
                            <li>Employee allowances and deductions will be included</li>
                            <li>Once processed, payroll records cannot be modified</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Date Validation Rules</h6>
                        <ul class="mb-0">
                            <li><strong>Period Dates:</strong> Start and end dates cannot be in the future</li>
                            <li><strong>Pay Date:</strong> Must be today or up to 30 days in the future</li>
                            <li><strong>Date Logic:</strong> End date must be after start date, pay date must be after end date</li>
                            <li><strong>Overlapping Periods:</strong> New periods cannot overlap with existing ones</li>
                            <li><strong>Year Restriction:</strong> Payroll can only be generated for past months within the current year</li>
                        </ul>
                    </div>

                    <?php
                    // Show suggested dates
                    require_once 'corrected_date_validation.php';
                    $suggested = getSuggestedPayrollDates();
                    if ($suggested): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb"></i> Suggested Period</h6>
                            <p class="mb-2"><strong><?php echo $suggested['period_name']; ?></strong></p>
                            <small>
                                <strong>Start:</strong> <?php echo $suggested['start_date']; ?> |
                                <strong>End:</strong> <?php echo $suggested['end_date']; ?> |
                                <strong>Pay Date:</strong> <?php echo $suggested['pay_date']; ?>
                            </small>
                            <br>
                            <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="useSuggestedDates()">
                                <i class="fas fa-magic"></i> Use These Dates
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end">
                        <a href="index.php?page=payroll" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> Process Payroll
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($action === 'view' && isset($payrollPeriod)): ?>
        <!-- View Payroll Details -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-eye"></i> Payroll Details - <?php echo htmlspecialchars($payrollPeriod['period_name']); ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <strong>Period:</strong><br>
                        <?php echo formatDate($payrollPeriod['start_date']); ?> - <?php echo formatDate($payrollPeriod['end_date']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Pay Date:</strong><br>
                        <?php echo formatDate($payrollPeriod['pay_date']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php echo $payrollPeriod['status'] === 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($payrollPeriod['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-3">
                        <strong>Created By:</strong><br>
                        <?php echo htmlspecialchars($payrollPeriod['created_by_name']); ?>
                    </div>
                </div>
                
                <?php if (!empty($payrollRecords)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Basic Salary</th>
                                    <th>Gross Pay</th>
                                    <th>PAYE</th>
                                    <th>NSSF</th>
                                    <th>NHIF</th>
                                    <th>Housing Levy</th>
                                    <th>Net Pay</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payrollRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_number']); ?></small>
                                        </td>
                                        <td><?php echo formatCurrency($record['basic_salary']); ?></td>
                                        <td><?php echo formatCurrency($record['gross_pay']); ?></td>
                                        <td><?php echo formatCurrency($record['paye_tax']); ?></td>
                                        <td><?php echo formatCurrency($record['nssf_deduction']); ?></td>
                                        <td><?php echo formatCurrency($record['nhif_deduction']); ?></td>
                                        <td><?php echo formatCurrency($record['housing_levy']); ?></td>
                                        <td><strong><?php echo formatCurrency($record['net_pay']); ?></strong></td>
                                        <td>
                                            <a href="index.php?page=payslip&id=<?php echo $record['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Payslip">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No payroll records found for this period.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const form = document.querySelector('.needs-validation');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const payDateInput = document.getElementById('pay_date');

    if (!form || !startDateInput || !endDateInput || !payDateInput) {
        return; // Exit if form elements not found
    }

    // Get today's date in YYYY-MM-DD format
    const today = new Date().toISOString().split('T')[0];

    // Set maximum date for start and end date (today)
    startDateInput.setAttribute('max', today);
    endDateInput.setAttribute('max', today);

    // Set minimum date for pay date (today) and maximum (30 days from today)
    const maxPayDate = new Date();
    maxPayDate.setDate(maxPayDate.getDate() + 30);
    const maxPayDateStr = maxPayDate.toISOString().split('T')[0];

    payDateInput.setAttribute('min', today);
    payDateInput.setAttribute('max', maxPayDateStr);

    // Real-time validation functions
    function validateStartDate() {
        const startDate = startDateInput.value;
        const errorDiv = document.getElementById('start_date_error') || createErrorDiv('start_date_error');
        const currentYear = new Date().getFullYear();
        const startYear = new Date(startDate).getFullYear();

        if (startDate > today) {
            startDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'Start date cannot be in the future.';
            errorDiv.style.display = 'block';
            return false;
        } else if (startYear !== currentYear) {
            startDateInput.classList.add('is-invalid');
            errorDiv.textContent = `Start date must be within the current year (${currentYear}).`;
            errorDiv.style.display = 'block';
            return false;
        } else {
            startDateInput.classList.remove('is-invalid');
            startDateInput.classList.add('is-valid');
            errorDiv.style.display = 'none';
            return true;
        }
    }

    function validateEndDate() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const errorDiv = document.getElementById('end_date_error') || createErrorDiv('end_date_error');
        const currentYear = new Date().getFullYear();
        const endYear = new Date(endDate).getFullYear();

        if (endDate > today) {
            endDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'End date cannot be in the future.';
            errorDiv.style.display = 'block';
            return false;
        } else if (endYear !== currentYear) {
            endDateInput.classList.add('is-invalid');
            errorDiv.textContent = `End date must be within the current year (${currentYear}).`;
            errorDiv.style.display = 'block';
            return false;
        } else if (startDate && endDate <= startDate) {
            endDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'End date must be after start date.';
            errorDiv.style.display = 'block';
            return false;
        } else {
            endDateInput.classList.remove('is-invalid');
            endDateInput.classList.add('is-valid');
            errorDiv.style.display = 'none';
            return true;
        }
    }

    function validatePayDate() {
        const endDate = endDateInput.value;
        const payDate = payDateInput.value;
        const errorDiv = document.getElementById('pay_date_error') || createErrorDiv('pay_date_error');

        if (payDate < today) {
            payDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'Pay date cannot be in the past.';
            errorDiv.style.display = 'block';
            return false;
        } else if (payDate > maxPayDateStr) {
            payDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'Pay date cannot be more than 30 days in the future.';
            errorDiv.style.display = 'block';
            return false;
        } else if (endDate && payDate <= endDate) {
            payDateInput.classList.add('is-invalid');
            errorDiv.textContent = 'Pay date must be after the period end date.';
            errorDiv.style.display = 'block';
            return false;
        } else {
            payDateInput.classList.remove('is-invalid');
            payDateInput.classList.add('is-valid');
            errorDiv.style.display = 'none';
            return true;
        }
    }

    function createErrorDiv(id) {
        const errorDiv = document.createElement('div');
        errorDiv.id = id;
        errorDiv.className = 'invalid-feedback';
        errorDiv.style.display = 'none';

        // Insert after the corresponding input
        const input = document.getElementById(id.replace('_error', ''));
        if (input && input.parentNode) {
            input.parentNode.appendChild(errorDiv);
        }

        return errorDiv;
    }

    // Add event listeners for real-time validation
    startDateInput.addEventListener('change', function() {
        validateStartDate();
        if (endDateInput.value) validateEndDate();
        if (payDateInput.value) validatePayDate();
    });

    endDateInput.addEventListener('change', function() {
        validateEndDate();
        if (payDateInput.value) validatePayDate();
    });

    payDateInput.addEventListener('change', validatePayDate);

    // Form submission validation
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();

        const isStartDateValid = validateStartDate();
        const isEndDateValid = validateEndDate();
        const isPayDateValid = validatePayDate();

        if (isStartDateValid && isEndDateValid && isPayDateValid) {
            // Show confirmation dialog
            const startDate = new Date(startDateInput.value).toLocaleDateString();
            const endDate = new Date(endDateInput.value).toLocaleDateString();
            const payDate = new Date(payDateInput.value).toLocaleDateString();

            const confirmMessage = `Are you sure you want to process payroll for the period ${startDate} to ${endDate}?\n\nPay Date: ${payDate}\n\nThis action cannot be undone.`;

            if (confirm(confirmMessage)) {
                // Disable submit button to prevent double submission
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }

                form.submit();
            }
        } else {
            // Show error message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Validation Error:</strong> Please correct the highlighted fields before submitting.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            form.insertBefore(alertDiv, form.firstChild);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        form.classList.add('was-validated');
    });

    // Auto-generate period name based on dates
    function generatePeriodName() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const periodNameInput = document.getElementById('period_name');

        if (startDate && endDate && !periodNameInput.value) {
            const start = new Date(startDate);
            const end = new Date(endDate);

            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
                // Same month and year
                periodNameInput.value = `${monthNames[start.getMonth()]} ${start.getFullYear()}`;
            } else {
                // Different months or years
                periodNameInput.value = `${monthNames[start.getMonth()]} ${start.getFullYear()} - ${monthNames[end.getMonth()]} ${end.getFullYear()}`;
            }
        }
    }

    startDateInput.addEventListener('change', generatePeriodName);
    endDateInput.addEventListener('change', generatePeriodName);

    // Function to use suggested dates
    window.useSuggestedDates = function() {
        <?php if ($suggested): ?>
        document.getElementById('start_date').value = '<?php echo $suggested['start_date']; ?>';
        document.getElementById('end_date').value = '<?php echo $suggested['end_date']; ?>';
        document.getElementById('pay_date').value = '<?php echo $suggested['pay_date']; ?>';
        document.getElementById('period_name').value = '<?php echo $suggested['period_name']; ?>';

        // Trigger validation
        validateStartDate();
        validateEndDate();
        validatePayDate();
        generatePeriodName();
        <?php endif; ?>
    };
});
</script>

<style>
.is-invalid {
    border-color: #dc3545;
}

.is-valid {
    border-color: #198754;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.alert {
    margin-bottom: 1rem;
}

.form-control:focus {
    border-color: #006b3f;
    box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--kenya-dark-green), var(--kenya-green));
    transform: translateY(-1px);
}

:root {
    --kenya-green: #006b3f;
    --kenya-dark-green: #004d2e;
    --kenya-red: #ce1126;
}
</style>
