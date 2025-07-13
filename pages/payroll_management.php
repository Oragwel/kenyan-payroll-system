<?php
/**
 * Payroll Management - Bulk Operations and Cleanup
 */

if (!hasPermission('hr')) {
    header('Location: index.php?page=dashboard');
    exit;
}

$message = '';
$messageType = '';

// Handle operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_single_period') {
        $periodId = $_POST['period_id'] ?? '';
        if ($periodId) {
            try {
                $db->beginTransaction();

                // Get period name for logging
                $stmt = $db->prepare("SELECT period_name FROM payroll_periods WHERE id = ? AND company_id = ?");
                $stmt->execute([$periodId, $_SESSION['company_id']]);
                $period = $stmt->fetch();

                if ($period) {
                    // First delete payroll records
                    $stmt = $db->prepare("DELETE FROM payroll_records WHERE payroll_period_id = ?");
                    $stmt->execute([$periodId]);
                    $deletedRecords = $stmt->rowCount();

                    // Then delete the period
                    $stmt = $db->prepare("DELETE FROM payroll_periods WHERE id = ? AND company_id = ?");
                    $stmt->execute([$periodId, $_SESSION['company_id']]);
                    $deletedPeriods = $stmt->rowCount();

                    $db->commit();
                    $message = "✅ Successfully deleted payroll period '{$period['period_name']}' and $deletedRecords payroll records.";
                    $messageType = 'success';
                } else {
                    $db->rollBack();
                    $message = 'Payroll period not found.';
                    $messageType = 'danger';
                }

            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error deleting payroll period: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid payroll period ID.';
            $messageType = 'danger';
        }
    }

    if ($action === 'delete_periods') {
        $selectedPeriods = $_POST['periods'] ?? [];
        if (!empty($selectedPeriods)) {
            try {
                $db->beginTransaction();
                
                $deletedRecords = 0;
                $deletedPeriods = 0;
                
                foreach ($selectedPeriods as $periodId) {
                    // First delete payroll records
                    $stmt = $db->prepare("DELETE FROM payroll_records WHERE payroll_period_id = ?");
                    $stmt->execute([$periodId]);
                    $deletedRecords += $stmt->rowCount();
                    
                    // Then delete the period
                    $stmt = $db->prepare("DELETE FROM payroll_periods WHERE id = ? AND company_id = ?");
                    $stmt->execute([$periodId, $_SESSION['company_id']]);
                    $deletedPeriods += $stmt->rowCount();
                }
                
                $db->commit();
                $message = "✅ Successfully deleted $deletedPeriods payroll periods and $deletedRecords payroll records.";
                $messageType = 'success';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error deleting payroll data: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Please select at least one payroll period to delete.';
            $messageType = 'warning';
        }
    }
    
    if ($action === 'delete_duplicates') {
        try {
            $db->beginTransaction();
            
            // Delete duplicate payroll records (keep the latest one for each employee-period combination)
            $stmt = $db->prepare("
                DELETE pr1 FROM payroll_records pr1
                INNER JOIN payroll_records pr2 
                WHERE pr1.employee_id = pr2.employee_id 
                  AND pr1.payroll_period_id = pr2.payroll_period_id 
                  AND pr1.id < pr2.id
                  AND pr1.company_id = ?
            ");
            $stmt->execute([$_SESSION['company_id']]);
            $deletedDuplicates = $stmt->rowCount();
            
            $db->commit();
            $message = "✅ Successfully removed $deletedDuplicates duplicate payroll records.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Error removing duplicates: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get payroll periods with statistics
$stmt = $db->prepare("
    SELECT pp.*, u.username as created_by_name,
           COUNT(pr.id) as total_records,
           COUNT(DISTINCT pr.employee_id) as unique_employees,
           SUM(pr.net_pay) as total_net_pay,
           (COUNT(pr.id) - COUNT(DISTINCT pr.employee_id)) as duplicate_count
    FROM payroll_periods pp
    LEFT JOIN users u ON pp.created_by = u.id
    LEFT JOIN payroll_records pr ON pp.id = pr.payroll_period_id
    WHERE pp.company_id = ?
    GROUP BY pp.id
    ORDER BY pp.created_at DESC
");
$stmt->execute([$_SESSION['company_id']]);
$payrollPeriods = $stmt->fetchAll();

// Get duplicate statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_duplicates,
        COUNT(DISTINCT employee_id, payroll_period_id) as unique_combinations
    FROM payroll_records pr
    JOIN payroll_periods pp ON pr.payroll_period_id = pp.id
    WHERE pp.company_id = ?
    HAVING COUNT(*) > COUNT(DISTINCT employee_id, payroll_period_id)
");
$stmt->execute([$_SESSION['company_id']]);
$duplicateStats = $stmt->fetch();
?>

<style>
.payroll-mgmt-hero {
    background: linear-gradient(135deg, #006b3f 0%, #004d2e 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.mgmt-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.mgmt-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}
</style>

<div class="container-fluid">
    <!-- Payroll Management Hero Section -->
    <div class="payroll-mgmt-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-cogs me-3"></i>
                        Payroll Management & Cleanup
                    </h2>
                    <p class="mb-0 opacity-75">
                        🗂️ Manage payroll periods, delete records, and clean up duplicate entries
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h6 class="mb-1 text-white">Bulk Operations</h6>
                        <small class="opacity-75">Delete periods individually or in bulk</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="fas fa-list"></i> Payroll Periods Overview</h4>
            <p class="text-muted mb-0">Manage and clean up your payroll data</p>
        </div>
        <div>
            <a href="index.php?page=payroll" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payroll
            </a>
            <a href="index.php?page=simple_payroll" class="btn btn-primary">
                <i class="fas fa-plus"></i> Process Payroll
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Duplicate Detection Alert -->
    <?php if ($duplicateStats && $duplicateStats['total_duplicates'] > $duplicateStats['unique_combinations']): ?>
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Duplicate Records Detected!</h5>
            <p>Found <strong><?php echo $duplicateStats['total_duplicates'] - $duplicateStats['unique_combinations']; ?></strong> duplicate payroll records.</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="delete_duplicates">
                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to remove all duplicate payroll records? This will keep only the latest record for each employee per period.')">
                    <i class="fas fa-broom"></i> Remove All Duplicates
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Bulk Operations -->
    <div class="mgmt-card">
        <div class="card-header">
            <h5><i class="fas fa-tasks"></i> Bulk Operations & Individual Deletion</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="bulkForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="selectNone()">
                            <i class="fas fa-square"></i> Select None
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" name="action" value="delete_periods" class="btn btn-danger" 
                                onclick="return confirmBulkDelete()">
                            <i class="fas fa-trash"></i> Delete Selected Periods
                        </button>
                    </div>
                </div>

                <!-- Payroll Periods Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                </th>
                                <th>Period Name</th>
                                <th>Period</th>
                                <th>Pay Date</th>
                                <th>Records</th>
                                <th>Unique Employees</th>
                                <th>Duplicates</th>
                                <th>Total Net Pay</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payrollPeriods)): ?>
                                <?php foreach ($payrollPeriods as $period): ?>
                                    <tr class="<?php echo $period['duplicate_count'] > 0 ? 'table-warning' : ''; ?>">
                                        <td>
                                            <input type="checkbox" name="periods[]" value="<?php echo $period['id']; ?>" 
                                                   class="period-checkbox">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($period['period_name'] ?? 'N/A'); ?></strong></td>
                                        <td>
                                            <?php echo formatDate($period['start_date']); ?> - 
                                            <?php echo formatDate($period['end_date']); ?>
                                        </td>
                                        <td><?php echo formatDate($period['pay_date']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $period['total_records']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $period['unique_employees']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($period['duplicate_count'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $period['duplicate_count']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatCurrency($period['total_net_pay'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $period['status'] === 'completed' ? 'success' : 
                                                    ($period['status'] === 'processing' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($period['status'] ?? 'unknown'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($period['created_by_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=payroll&action=view&id=<?php echo $period['id']; ?>"
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="index.php?page=payslips&employee_id=&period_id=<?php echo $period['id']; ?>"
                                                   class="btn btn-outline-primary" title="View Payslips">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger"
                                                        onclick="deleteSinglePeriod(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['period_name'] ?? 'Unknown', ENT_QUOTES); ?>')"
                                                        title="Delete Period">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted">No payroll periods found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Periods</h5>
                    <h2 class="text-primary"><?php echo count($payrollPeriods); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Records</h5>
                    <h2 class="text-info"><?php echo array_sum(array_column($payrollPeriods, 'total_records')); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Duplicate Records</h5>
                    <h2 class="text-warning"><?php echo array_sum(array_column($payrollPeriods, 'duplicate_count')); ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.period-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
}

function selectNone() {
    document.querySelectorAll('.period-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
}

function toggleAll() {
    const selectAll = document.getElementById('selectAllCheckbox').checked;
    document.querySelectorAll('.period-checkbox').forEach(cb => cb.checked = selectAll);
}

function confirmBulkDelete() {
    const selected = document.querySelectorAll('.period-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one payroll period to delete.');
        return false;
    }

    return confirm(`Are you sure you want to delete ${selected.length} payroll period(s) and all associated payroll records? This action cannot be undone.`);
}

function deleteSinglePeriod(periodId, periodName) {
    if (confirm(`Are you sure you want to delete the payroll period "${periodName}" and all associated payroll records?\n\nThis action cannot be undone.`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_single_period';

        const periodIdInput = document.createElement('input');
        periodIdInput.type = 'hidden';
        periodIdInput.name = 'period_id';
        periodIdInput.value = periodId;

        form.appendChild(actionInput);
        form.appendChild(periodIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
