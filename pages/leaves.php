<?php
/**
 * Leave Management System
 */

$action = $_GET['action'] ?? 'index';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'apply':
            $result = submitLeaveApplication($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'approve':
        case 'reject':
            if (hasPermission('hr')) {
                $result = processLeaveApplication($_POST['application_id'], $action, $_POST['comments'] ?? '');
                $message = $result['message'];
                $messageType = $result['type'];
            }
            break;
    }
}

/**
 * Submit leave application
 */
function submitLeaveApplication($data) {
    global $db;
    
    try {
        // Validate dates
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        $today = new DateTime();
        
        if ($startDate < $today) {
            return ['message' => 'Start date cannot be in the past.', 'type' => 'danger'];
        }
        
        if ($endDate < $startDate) {
            return ['message' => 'End date cannot be before start date.', 'type' => 'danger'];
        }
        
        // Calculate days
        $interval = $startDate->diff($endDate);
        $daysRequested = $interval->days + 1;
        
        // Check leave balance
        $stmt = $db->prepare("
            SELECT lt.days_per_year,
                   COALESCE(SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END), 0) as used_days
            FROM leave_types lt
            LEFT JOIN leave_applications la ON lt.id = la.leave_type_id 
                AND la.employee_id = ? 
                AND YEAR(la.start_date) = YEAR(NOW())
            WHERE lt.id = ?
            GROUP BY lt.id, lt.days_per_year
        ");
        $stmt->execute([$_SESSION['employee_id'], $data['leave_type_id']]);
        $leaveBalance = $stmt->fetch();
        
        if ($leaveBalance) {
            $remainingDays = $leaveBalance['days_per_year'] - $leaveBalance['used_days'];
            if ($daysRequested > $remainingDays) {
                return ['message' => "Insufficient leave balance. You have {$remainingDays} days remaining.", 'type' => 'danger'];
            }
        }
        
        // Insert application
        $stmt = $db->prepare("
            INSERT INTO leave_applications (
                employee_id, leave_type_id, start_date, end_date, 
                days_requested, reason, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['employee_id'],
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $daysRequested,
            $data['reason']
        ]);
        
        return ['message' => 'Leave application submitted successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error submitting application: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Process leave application (approve/reject)
 */
function processLeaveApplication($applicationId, $action, $comments) {
    global $db;
    
    try {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $stmt = $db->prepare("
            UPDATE leave_applications
            SET status = ?, approved_by = ?, approved_at = NOW(), comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $_SESSION['user_id'], $comments, $applicationId]);
        
        return ['message' => "Leave application {$status} successfully!", 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error processing application: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get data based on user role and action
if (hasPermission('hr')) {
    // HR can see all applications
    $stmt = $db->prepare("
        SELECT la.*,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               e.employee_number,
               lt.name as leave_type_name,
               CONCAT(u.first_name, ' ', u.last_name) as approved_by_name
        FROM leave_applications la
        JOIN employees e ON la.employee_id = e.id
        JOIN leave_types lt ON la.leave_type_id = lt.id
        LEFT JOIN users u ON la.approved_by = u.id
        WHERE e.company_id = ?
        ORDER BY la.created_at DESC
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $applications = $stmt->fetchAll();
} else {
    // Employees see only their applications
    $stmt = $db->prepare("
        SELECT la.*, lt.name as leave_type_name,
               CONCAT(u.first_name, ' ', u.last_name) as approved_by_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        LEFT JOIN users u ON la.approved_by = u.id
        WHERE la.employee_id = ?
        ORDER BY la.created_at DESC
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $applications = $stmt->fetchAll();
}

// Get leave types
$stmt = $db->prepare("SELECT * FROM leave_types WHERE company_id = ? ORDER BY name");
$stmt->execute([$_SESSION['company_id']]);
$leaveTypes = $stmt->fetchAll();

// Get leave balance for current user
if (isset($_SESSION['employee_id'])) {
    $stmt = $db->prepare("
        SELECT lt.name, lt.days_per_year,
               COALESCE(SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END), 0) as used_days
        FROM leave_types lt
        LEFT JOIN leave_applications la ON lt.id = la.leave_type_id 
            AND la.employee_id = ? 
            AND YEAR(la.start_date) = YEAR(NOW())
        WHERE lt.company_id = ?
        GROUP BY lt.id, lt.name, lt.days_per_year
        ORDER BY lt.name
    ");
    $stmt->execute([$_SESSION['employee_id'], $_SESSION['company_id']]);
    $leaveBalances = $stmt->fetchAll();
}
?>

<!-- Leave Management Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.leave-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.leave-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.leave-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.balance-card {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.status-pending { color: #f59e0b; }
.status-approved { color: #10b981; }
.status-rejected { color: #ef4444; }

.btn-apply {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-apply:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,107,63,0.3);
    color: white;
}

.btn-approve {
    background: var(--kenya-green);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}

.btn-reject {
    background: var(--kenya-red);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid">
    <!-- Leave Hero Section -->
    <div class="leave-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-check me-3"></i>
                        Leave Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        🏖️ Manage leave applications and track leave balances
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (!hasPermission('hr')): ?>
                        <a href="index.php?page=leaves&action=apply" class="btn btn-light btn-lg">
                            <i class="fas fa-plus me-2"></i>Apply for Leave
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'apply'): ?>
        <!-- Leave Application Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="leave-card">
                    <div class="p-4">
                        <h4 class="mb-4">
                            <i class="fas fa-calendar-plus text-success me-2"></i>
                            Apply for Leave
                        </h4>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="leave_type_id" class="form-label">Leave Type</label>
                                        <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                            <option value="">Select leave type</option>
                                            <?php foreach ($leaveTypes as $type): ?>
                                                <option value="<?php echo $type['id']; ?>">
                                                    <?php echo htmlspecialchars($type['name']); ?> 
                                                    (<?php echo $type['days_per_year']; ?> days/year)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">Reason for Leave</label>
                                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                                  placeholder="Please provide a reason for your leave application..." required></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="index.php?page=leaves" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Applications
                                </a>
                                <button type="submit" class="btn btn-apply">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Leave Balance -->
                <?php if (!empty($leaveBalances)): ?>
                    <div class="leave-card">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-pie text-info me-2"></i>
                                Your Leave Balance
                            </h5>
                            
                            <?php foreach ($leaveBalances as $balance): ?>
                                <div class="balance-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($balance['name']); ?></h6>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ($balance['days_per_year'] - $balance['used_days']); ?>/<?php echo $balance['days_per_year']; ?>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-light" 
                                             style="width: <?php echo (($balance['days_per_year'] - $balance['used_days']) / $balance['days_per_year']) * 100; ?>%;">
                                        </div>
                                    </div>
                                    <small class="opacity-75">
                                        <?php echo ($balance['days_per_year'] - $balance['used_days']); ?> days remaining
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Leave Applications List -->
        <div class="row">
            <div class="col-12">
                <div class="leave-card">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>
                                <i class="fas fa-list text-primary me-2"></i>
                                <?php echo hasPermission('hr') ? 'All Leave Applications' : 'My Leave Applications'; ?>
                            </h4>
                            <?php if (!hasPermission('hr')): ?>
                                <a href="index.php?page=leaves&action=apply" class="btn btn-apply">
                                    <i class="fas fa-plus me-2"></i>Apply for Leave
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($applications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-success">
                                        <tr>
                                            <?php if (hasPermission('hr')): ?>
                                                <th>Employee</th>
                                            <?php endif; ?>
                                            <th>Leave Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <?php if (hasPermission('hr')): ?>
                                                <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <?php if (hasPermission('hr')): ?>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($app['employee_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($app['employee_number']); ?></small>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($app['leave_type_name']); ?></td>
                                                <td><?php echo formatDate($app['start_date']); ?></td>
                                                <td><?php echo formatDate($app['end_date']); ?></td>
                                                <td><?php echo $app['days_requested']; ?> days</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $app['status'] === 'approved' ? 'success' : 
                                                            ($app['status'] === 'pending' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($app['created_at']); ?></td>
                                                <?php if (hasPermission('hr') && $app['status'] === 'pending'): ?>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                            <button type="submit" name="action" value="approve" class="btn btn-approve btn-sm me-1">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                            <button type="submit" name="action" value="reject" class="btn btn-reject btn-sm">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </form>
                                                    </td>
                                                <?php elseif (hasPermission('hr')): ?>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo $app['status'] === 'approved' ? 'Approved' : 'Rejected'; ?>
                                                            <?php if ($app['approved_by_name']): ?>
                                                                by <?php echo htmlspecialchars($app['approved_by_name']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-check fa-4x text-muted mb-3"></i>
                                <h5>No Leave Applications</h5>
                                <p class="text-muted">
                                    <?php echo hasPermission('hr') ? 'No leave applications have been submitted yet.' : 'You haven\'t applied for any leave yet.'; ?>
                                </p>
                                <?php if (!hasPermission('hr')): ?>
                                    <a href="index.php?page=leaves&action=apply" class="btn btn-apply">
                                        <i class="fas fa-plus me-2"></i>Apply for Leave
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-calculate days when dates change
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    function updateEndDateMin() {
        if (startDate.value) {
            endDate.min = startDate.value;
        }
    }
    
    if (startDate && endDate) {
        startDate.addEventListener('change', updateEndDateMin);
        updateEndDateMin();
    }
});
</script>
