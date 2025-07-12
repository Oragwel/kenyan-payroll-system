<?php
/**
 * Enhanced Leave Management System
 * Comprehensive leave management with improved features
 */

// Security check
if (!isset($_SESSION['user_role'])) {
    header('Location: index.php?page=auth');
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$leaveId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'apply':
            $result = submitLeaveApplication($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'dashboard';
            }
            break;
        case 'process':
            if (hasPermission('hr')) {
                $result = processLeaveApplication($_POST);
                $message = $result['message'];
                $messageType = $result['type'];
                $action = 'pending';
            }
            break;
        case 'cancel':
            $result = cancelLeaveApplication($_POST['application_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'my_applications';
            break;
    }
}

/**
 * Submit leave application with enhanced validation
 */
function submitLeaveApplication($data) {
    global $db;
    
    try {
        // Enhanced validation
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        $today = new DateTime();
        $minNoticeDate = clone $today;
        $minNoticeDate->add(new DateInterval('P2D')); // 2 days minimum notice
        
        // Validation checks
        if ($startDate < $minNoticeDate) {
            return ['message' => 'Leave must be applied at least 2 days in advance.', 'type' => 'danger'];
        }
        
        if ($endDate < $startDate) {
            return ['message' => 'End date cannot be before start date.', 'type' => 'danger'];
        }
        
        // Calculate days (excluding weekends if specified)
        $interval = $startDate->diff($endDate);
        $daysRequested = $interval->days + 1;
        
        // Check for maximum consecutive days (30 days limit)
        if ($daysRequested > 30) {
            return ['message' => 'Maximum 30 consecutive days allowed per application.', 'type' => 'danger'];
        }
        
        // Check for overlapping applications
        $stmt = $db->prepare("
            SELECT COUNT(*) as overlap_count 
            FROM leave_applications 
            WHERE employee_id = ? 
            AND status IN ('pending', 'approved')
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (start_date <= ? AND end_date >= ?) OR
                (start_date >= ? AND end_date <= ?)
            )
        ");
        $stmt->execute([
            $_SESSION['employee_id'], 
            $data['start_date'], $data['start_date'],
            $data['end_date'], $data['end_date'],
            $data['start_date'], $data['end_date']
        ]);
        $overlap = $stmt->fetch();
        
        if ($overlap['overlap_count'] > 0) {
            return ['message' => 'You already have a leave application for overlapping dates.', 'type' => 'danger'];
        }
        
        // Check leave balance
        $stmt = $db->prepare("
            SELECT lt.days_per_year, lt.name,
                   COALESCE(SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END), 0) as used_days
            FROM leave_types lt
            LEFT JOIN leave_applications la ON lt.id = la.leave_type_id 
                AND la.employee_id = ? 
                AND YEAR(la.start_date) = YEAR(NOW())
            WHERE lt.id = ?
            GROUP BY lt.id, lt.days_per_year, lt.name
        ");
        $stmt->execute([$_SESSION['employee_id'], $data['leave_type_id']]);
        $leaveBalance = $stmt->fetch();
        
        if ($leaveBalance) {
            $remainingDays = $leaveBalance['days_per_year'] - $leaveBalance['used_days'];
            if ($daysRequested > $remainingDays) {
                return ['message' => "Insufficient {$leaveBalance['name']} balance. You have {$remainingDays} days remaining.", 'type' => 'danger'];
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
        
        // Log activity
        logActivity('leave_application', "Applied for {$daysRequested} days leave from {$data['start_date']} to {$data['end_date']}");
        
        return ['message' => 'Leave application submitted successfully! HR will review your request.', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error submitting application: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Process leave application with comments
 */
function processLeaveApplication($data) {
    global $db;
    
    try {
        $applicationId = $data['application_id'];
        $status = $data['action']; // 'approved' or 'rejected'
        $comments = trim($data['comments'] ?? '');
        
        // Get application details for logging
        $stmt = $db->prepare("
            SELECT la.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, lt.name as leave_type
            FROM leave_applications la
            JOIN employees e ON la.employee_id = e.id
            JOIN leave_types lt ON la.leave_type_id = lt.id
            WHERE la.id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
        
        if (!$application) {
            return ['message' => 'Leave application not found.', 'type' => 'danger'];
        }
        
        // Update application
        $stmt = $db->prepare("
            UPDATE leave_applications 
            SET status = ?, approved_by = ?, approved_at = NOW(), comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $_SESSION['user_id'], $comments, $applicationId]);
        
        // Log activity
        $actionText = $status === 'approved' ? 'Approved' : 'Rejected';
        logActivity('leave_' . $status, "{$actionText} leave application for {$application['employee_name']} ({$application['days_requested']} days)");
        
        return ['message' => "Leave application {$status} successfully!", 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error processing application: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Cancel leave application
 */
function cancelLeaveApplication($applicationId) {
    global $db;
    
    try {
        // Check if application belongs to current user and is pending
        $stmt = $db->prepare("
            SELECT * FROM leave_applications 
            WHERE id = ? AND employee_id = ? AND status = 'pending'
        ");
        $stmt->execute([$applicationId, $_SESSION['employee_id']]);
        $application = $stmt->fetch();
        
        if (!$application) {
            return ['message' => 'Cannot cancel this application.', 'type' => 'danger'];
        }
        
        // Update status to cancelled
        $stmt = $db->prepare("UPDATE leave_applications SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$applicationId]);
        
        logActivity('leave_cancelled', "Cancelled leave application for {$application['days_requested']} days");
        
        return ['message' => 'Leave application cancelled successfully.', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error cancelling application: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get leave statistics for dashboard
function getLeaveStatistics() {
    global $db;
    
    $stats = [];
    
    if (hasPermission('hr')) {
        // HR Statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM leave_applications la
            JOIN employees e ON la.employee_id = e.id
            WHERE e.company_id = ? AND YEAR(la.created_at) = YEAR(NOW())
        ");
        $stmt->execute([$_SESSION['company_id']]);
        $stats = $stmt->fetch();
    } else {
        // Employee Statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END) as total_days_taken
            FROM leave_applications 
            WHERE employee_id = ? AND YEAR(created_at) = YEAR(NOW())
        ");
        $stmt->execute([$_SESSION['employee_id']]);
        $stats = $stmt->fetch();
    }
    
    return $stats;
}

// Get data based on action
$stats = getLeaveStatistics();

// Get leave types
$stmt = $db->prepare("SELECT * FROM leave_types WHERE company_id = ? ORDER BY name");
$stmt->execute([$_SESSION['company_id']]);
$leaveTypes = $stmt->fetchAll();

// Get leave balances for current user
$leaveBalances = [];
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Leave Management - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
        }

        .leave-dashboard {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .balance-card {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .progress-ring {
            width: 60px;
            height: 60px;
        }

        .btn-leave {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-leave:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,107,63,0.3);
            color: white;
        }

        .leave-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--kenya-green);
            font-weight: 600;
        }

        .nav-tabs .nav-link.active {
            background: var(--kenya-green);
            color: white;
            border-radius: 8px 8px 0 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="leave-dashboard">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-check me-3"></i>
                        Enhanced Leave Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        🏖️ Comprehensive leave management with advanced features
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (!hasPermission('hr')): ?>
                        <a href="index.php?page=leave_management&action=apply" class="btn btn-light btn-lg">
                            <i class="fas fa-plus me-2"></i>Apply for Leave
                        </a>
                    <?php endif; ?>
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

        <?php if ($action === 'dashboard' || $action === ''): ?>
            <!-- Dashboard View -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card text-primary">
                        <div class="stat-number"><?php echo $stats['total_applications'] ?? 0; ?></div>
                        <div>Total Applications</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-warning">
                        <div class="stat-number"><?php echo $stats['pending_count'] ?? 0; ?></div>
                        <div>Pending</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-success">
                        <div class="stat-number"><?php echo $stats['approved_count'] ?? 0; ?></div>
                        <div>Approved</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-danger">
                        <div class="stat-number"><?php echo $stats['rejected_count'] ?? 0; ?></div>
                        <div>Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Leave Balances for Employees -->
            <?php if (!hasPermission('hr') && !empty($leaveBalances)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">
                            <i class="fas fa-chart-pie text-success me-2"></i>
                            Your Leave Balances (<?php echo date('Y'); ?>)
                        </h4>
                        <div class="row">
                            <?php foreach ($leaveBalances as $balance): ?>
                                <?php
                                $used = $balance['used_days'];
                                $total = $balance['days_per_year'];
                                $remaining = $total - $used;
                                $percentage = $total > 0 ? ($used / $total) * 100 : 0;
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="balance-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($balance['name']); ?></h6>
                                                <div class="h4 mb-0"><?php echo $remaining; ?> days</div>
                                                <small class="opacity-75">
                                                    <?php echo $used; ?> used of <?php echo $total; ?> total
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="progress" style="width: 60px; height: 8px;">
                                                    <div class="progress-bar bg-light"
                                                         style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small class="opacity-75"><?php echo round($percentage); ?>% used</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="leave-card p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Quick Actions
                        </h5>
                        <?php if (hasPermission('hr')): ?>
                            <a href="index.php?page=leave_management&action=pending" class="btn btn-leave me-2 mb-2">
                                <i class="fas fa-clock me-2"></i>Review Pending Applications
                            </a>
                            <a href="index.php?page=leave_management&action=calendar" class="btn btn-outline-primary me-2 mb-2">
                                <i class="fas fa-calendar me-2"></i>Leave Calendar
                            </a>
                            <a href="index.php?page=leave_management&action=reports" class="btn btn-outline-info mb-2">
                                <i class="fas fa-chart-bar me-2"></i>Leave Reports
                            </a>
                        <?php else: ?>
                            <a href="index.php?page=leave_management&action=apply" class="btn btn-leave me-2 mb-2">
                                <i class="fas fa-plus me-2"></i>Apply for Leave
                            </a>
                            <a href="index.php?page=leave_management&action=my_applications" class="btn btn-outline-primary me-2 mb-2">
                                <i class="fas fa-list me-2"></i>My Applications
                            </a>
                            <a href="index.php?page=leave_management&action=calendar" class="btn btn-outline-info mb-2">
                                <i class="fas fa-calendar me-2"></i>Team Calendar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="leave-card p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Leave Policy Highlights
                        </h5>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Minimum 2 days advance notice required
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Maximum 30 consecutive days per application
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Leave balance carries forward (where applicable)
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                HR approval required for all leave requests
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'apply'): ?>
            <!-- Enhanced Leave Application Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="leave-card">
                        <div class="p-4">
                            <h4 class="mb-4">
                                <i class="fas fa-calendar-plus text-success me-2"></i>
                                Apply for Leave
                            </h4>

                            <form method="POST" id="leaveApplicationForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="leave_type_id" class="form-label">Leave Type *</label>
                                            <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                                                <option value="">Select leave type</option>
                                                <?php foreach ($leaveTypes as $type): ?>
                                                    <option value="<?php echo $type['id']; ?>"
                                                            data-days="<?php echo $type['days_per_year']; ?>">
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                        (<?php echo $type['days_per_year']; ?> days/year)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="start_date" class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date"
                                                   min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" required>
                                            <div class="form-text">Minimum 2 days notice</div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">End Date *</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date"
                                                   min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="alert alert-info" id="daysCalculation" style="display: none;">
                                            <i class="fas fa-calculator me-2"></i>
                                            <span id="calculatedDays">0</span> days requested
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="reason" class="form-label">Reason for Leave *</label>
                                            <textarea class="form-control" id="reason" name="reason" rows="4"
                                                      placeholder="Please provide a detailed reason for your leave application..." required></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=leave_management" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                    <button type="submit" class="btn btn-leave">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Leave Balance Sidebar -->
                    <?php if (!empty($leaveBalances)): ?>
                        <div class="leave-card">
                            <div class="p-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-pie text-success me-2"></i>
                                    Your Leave Balances
                                </h5>
                                <?php foreach ($leaveBalances as $balance): ?>
                                    <?php
                                    $remaining = $balance['days_per_year'] - $balance['used_days'];
                                    $percentage = $balance['days_per_year'] > 0 ? ($balance['used_days'] / $balance['days_per_year']) * 100 : 0;
                                    ?>
                                    <div class="mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($balance['name']); ?></strong>
                                            <span class="badge bg-<?php echo $remaining > 5 ? 'success' : ($remaining > 0 ? 'warning' : 'danger'); ?>">
                                                <?php echo $remaining; ?> days left
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $percentage < 70 ? 'success' : ($percentage < 90 ? 'warning' : 'danger'); ?>"
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $balance['used_days']; ?> used of <?php echo $balance['days_per_year']; ?> total
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Leave Policy -->
                    <div class="leave-card">
                        <div class="p-4">
                            <h5 class="mb-3">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                Application Guidelines
                            </h5>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-clock text-warning me-2"></i>
                                    Apply at least 2 days in advance
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-calendar-times text-danger me-2"></i>
                                    Maximum 30 consecutive days
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    HR approval required
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-edit text-primary me-2"></i>
                                    Provide detailed reason
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Enhanced form validation and calculations
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const daysCalculation = document.getElementById('daysCalculation');
            const calculatedDays = document.getElementById('calculatedDays');

            function calculateDays() {
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);

                    if (endDate >= startDate) {
                        const timeDiff = endDate.getTime() - startDate.getTime();
                        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;

                        calculatedDays.textContent = daysDiff;
                        daysCalculation.style.display = 'block';

                        // Validate maximum days
                        if (daysDiff > 30) {
                            daysCalculation.className = 'alert alert-danger';
                            calculatedDays.textContent = daysDiff + ' (Exceeds 30-day limit)';
                        } else {
                            daysCalculation.className = 'alert alert-info';
                        }
                    } else {
                        daysCalculation.style.display = 'none';
                    }
                }
            }

            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                    calculateDays();
                });

                endDateInput.addEventListener('change', calculateDays);
            }

            // Form validation
            const form = document.getElementById('leaveApplicationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    const today = new Date();
                    const minDate = new Date(today.getTime() + (2 * 24 * 60 * 60 * 1000));

                    if (startDate < minDate) {
                        e.preventDefault();
                        alert('Leave must be applied at least 2 days in advance.');
                        return false;
                    }

                    if (endDate < startDate) {
                        e.preventDefault();
                        alert('End date cannot be before start date.');
                        return false;
                    }

                    const daysDiff = Math.ceil((endDate.getTime() - startDate.getTime()) / (1000 * 3600 * 24)) + 1;
                    if (daysDiff > 30) {
                        e.preventDefault();
                        alert('Maximum 30 consecutive days allowed per application.');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
