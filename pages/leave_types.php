<?php
/**
 * Leave Types Management System
 * Manage different types of leave (Annual, Sick, Maternity, etc.)
 */

// Security check - HR/Admin only
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$leaveTypeId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
        case 'edit':
            $result = saveLeaveType($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'list';
            }
            break;
        case 'delete':
            $result = deleteLeaveType($_POST['leave_type_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
        case 'toggle_status':
            $result = toggleLeaveTypeStatus($_POST['leave_type_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
    }
}

/**
 * Save leave type (add or edit)
 */
function saveLeaveType($data, $action) {
    global $db;
    
    $name = trim($data['name']);
    $daysPerYear = intval($data['days_per_year']);
    $isPaid = isset($data['is_paid']) ? 1 : 0;
    $carryForward = isset($data['carry_forward']) ? 1 : 0;
    $maxCarryForward = intval($data['max_carry_forward'] ?? 0);
    $description = trim($data['description']);
    $leaveTypeId = $data['leave_type_id'] ?? null;
    
    // Validation
    if (empty($name)) {
        return ['message' => 'Leave type name is required', 'type' => 'danger'];
    }
    
    if ($daysPerYear < 0 || $daysPerYear > 365) {
        return ['message' => 'Days per year must be between 0 and 365', 'type' => 'danger'];
    }
    
    if ($carryForward && $maxCarryForward < 0) {
        return ['message' => 'Maximum carry forward days cannot be negative', 'type' => 'danger'];
    }
    
    try {
        // Check which columns exist
        $stmt = $db->prepare("SHOW COLUMNS FROM leave_types");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $hasIsPaid = in_array('is_paid', $columns);
        $hasMaxCarryForward = in_array('max_carry_forward', $columns);
        $hasDescription = in_array('description', $columns);

        if ($action === 'add') {
            // Check if leave type already exists
            $stmt = $db->prepare("SELECT id FROM leave_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$name, $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                return ['message' => 'Leave type already exists', 'type' => 'danger'];
            }

            // Build dynamic INSERT query based on available columns
            $insertColumns = ['company_id', 'name', 'days_per_year', 'carry_forward'];
            $insertValues = [$_SESSION['company_id'], $name, $daysPerYear, $carryForward];

            if ($hasIsPaid) {
                $insertColumns[] = 'is_paid';
                $insertValues[] = $isPaid;
            }
            if ($hasMaxCarryForward) {
                $insertColumns[] = 'max_carry_forward';
                $insertValues[] = $maxCarryForward;
            }
            if ($hasDescription) {
                $insertColumns[] = 'description';
                $insertValues[] = $description;
            }

            $sql = "INSERT INTO leave_types (" . implode(', ', $insertColumns) . ") VALUES (" . str_repeat('?,', count($insertColumns) - 1) . "?)";
            $stmt = $db->prepare($sql);
            $stmt->execute($insertValues);

            logActivity('leave_type_add', "Added leave type: $name ($daysPerYear days/year)");
            return ['message' => 'Leave type created successfully', 'type' => 'success'];

        } else { // edit
            // Build dynamic UPDATE query based on available columns
            $updateParts = ['name = ?', 'days_per_year = ?', 'carry_forward = ?'];
            $updateValues = [$name, $daysPerYear, $carryForward];

            if ($hasIsPaid) {
                $updateParts[] = 'is_paid = ?';
                $updateValues[] = $isPaid;
            }
            if ($hasMaxCarryForward) {
                $updateParts[] = 'max_carry_forward = ?';
                $updateValues[] = $maxCarryForward;
            }
            if ($hasDescription) {
                $updateParts[] = 'description = ?';
                $updateValues[] = $description;
            }

            $updateValues[] = $leaveTypeId;
            $updateValues[] = $_SESSION['company_id'];

            $sql = "UPDATE leave_types SET " . implode(', ', $updateParts) . " WHERE id = ? AND company_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($updateValues);

            logActivity('leave_type_edit', "Updated leave type: $name ($daysPerYear days/year)");
            return ['message' => 'Leave type updated successfully', 'type' => 'success'];
        }
    } catch (Exception $e) {
        return ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete leave type
 */
function deleteLeaveType($leaveTypeId) {
    global $db;
    
    try {
        // Check if leave type is in use
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_applications WHERE leave_type_id = ?");
        $stmt->execute([$leaveTypeId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['message' => 'Cannot delete leave type that has been used in leave applications', 'type' => 'danger'];
        }
        
        // Get leave type name for logging
        $stmt = $db->prepare("SELECT name FROM leave_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$leaveTypeId, $_SESSION['company_id']]);
        $leaveType = $stmt->fetch();
        
        if (!$leaveType) {
            return ['message' => 'Leave type not found', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("DELETE FROM leave_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$leaveTypeId, $_SESSION['company_id']]);
        
        logActivity('leave_type_delete', "Deleted leave type: " . $leaveType['name']);
        return ['message' => 'Leave type deleted successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting leave type: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Toggle leave type active status
 */
function toggleLeaveTypeStatus($leaveTypeId) {
    global $db;

    try {
        // Check if is_active column exists
        $stmt = $db->prepare("SHOW COLUMNS FROM leave_types LIKE 'is_active'");
        $stmt->execute();
        $hasIsActiveColumn = $stmt->rowCount() > 0;

        if (!$hasIsActiveColumn) {
            return ['message' => 'Status management not available. Please refresh the page.', 'type' => 'warning'];
        }

        $stmt = $db->prepare("SELECT name, is_active FROM leave_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$leaveTypeId, $_SESSION['company_id']]);
        $leaveType = $stmt->fetch();

        if (!$leaveType) {
            return ['message' => 'Leave type not found', 'type' => 'danger'];
        }

        $newStatus = $leaveType['is_active'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE leave_types SET is_active = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$newStatus, $leaveTypeId, $_SESSION['company_id']]);

        $statusText = $newStatus ? 'activated' : 'deactivated';
        logActivity('leave_type_status_change', "Leave type '{$leaveType['name']}' $statusText");

        return ['message' => "Leave type {$statusText} successfully", 'type' => 'success'];

    } catch (Exception $e) {
        return ['message' => 'Error updating leave type status: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Create leave_types table if it doesn't exist and add missing columns
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS leave_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            days_per_year INT NOT NULL DEFAULT 0,
            is_paid BOOLEAN DEFAULT TRUE,
            carry_forward BOOLEAN DEFAULT FALSE,
            max_carry_forward INT DEFAULT 0,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");

    // Add missing columns to existing table if they don't exist
    $columns_to_add = [
        'is_paid' => 'ALTER TABLE leave_types ADD COLUMN is_paid BOOLEAN DEFAULT TRUE',
        'carry_forward' => 'ALTER TABLE leave_types ADD COLUMN carry_forward BOOLEAN DEFAULT FALSE',
        'max_carry_forward' => 'ALTER TABLE leave_types ADD COLUMN max_carry_forward INT DEFAULT 0',
        'description' => 'ALTER TABLE leave_types ADD COLUMN description TEXT',
        'is_active' => 'ALTER TABLE leave_types ADD COLUMN is_active BOOLEAN DEFAULT TRUE',
        'updated_at' => 'ALTER TABLE leave_types ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];

    foreach ($columns_to_add as $column => $sql) {
        try {
            // Check if column exists
            $stmt = $db->prepare("SHOW COLUMNS FROM leave_types LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->rowCount() == 0) {
                $db->exec($sql);
            }
        } catch (Exception $e) {
            // Column might already exist or other error, continue
        }
    }
    
    // Insert default Kenyan leave types if none exist
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_types WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $defaultLeaveTypes = [
            ['Annual Leave', 21, 1, 1, 5, 'Annual vacation leave as per Kenyan Employment Act'],
            ['Sick Leave', 7, 1, 0, 0, 'Medical leave for illness or injury'],
            ['Maternity Leave', 90, 1, 0, 0, 'Maternity leave for female employees (3 months)'],
            ['Paternity Leave', 14, 1, 0, 0, 'Paternity leave for male employees (2 weeks)'],
            ['Compassionate Leave', 3, 1, 0, 0, 'Leave for family emergencies or bereavement'],
            ['Study Leave', 10, 0, 0, 0, 'Educational leave for professional development'],
            ['Emergency Leave', 2, 1, 0, 0, 'Urgent personal matters requiring immediate attention']
        ];
        
        $stmt = $db->prepare("
            INSERT INTO leave_types (company_id, name, days_per_year, is_paid, carry_forward, max_carry_forward, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($defaultLeaveTypes as $leaveType) {
            $stmt->execute([
                $_SESSION['company_id'],
                $leaveType[0], // name
                $leaveType[1], // days_per_year
                $leaveType[2], // is_paid
                $leaveType[3], // carry_forward
                $leaveType[4], // max_carry_forward
                $leaveType[5]  // description
            ]);
        }
    }
} catch (Exception $e) {
    // Table creation failed, but continue
}

// Get data based on action
if ($action === 'list' || $action === 'add' || $action === 'edit') {
    // Check if is_active column exists
    $hasIsActiveColumn = false;
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM leave_types LIKE 'is_active'");
        $stmt->execute();
        $hasIsActiveColumn = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Continue without is_active column
    }

    // Get leave types with usage statistics
    $orderBy = $hasIsActiveColumn ? "ORDER BY lt.is_active DESC, lt.name" : "ORDER BY lt.name";

    $stmt = $db->prepare("
        SELECT lt.*,
               COUNT(la.id) as applications_count,
               SUM(CASE WHEN la.status = 'approved' THEN la.days_requested ELSE 0 END) as total_days_used
        FROM leave_types lt
        LEFT JOIN leave_applications la ON lt.id = la.leave_type_id
        WHERE lt.company_id = ?
        GROUP BY lt.id
        $orderBy
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $leaveTypes = $stmt->fetchAll();

    // Add default values for missing columns
    foreach ($leaveTypes as &$type) {
        if (!isset($type['is_paid'])) $type['is_paid'] = 1;
        if (!isset($type['is_active'])) $type['is_active'] = 1;
        if (!isset($type['carry_forward'])) $type['carry_forward'] = 0;
        if (!isset($type['max_carry_forward'])) $type['max_carry_forward'] = 0;
        if (!isset($type['days_per_year'])) $type['days_per_year'] = 0;
        if (!isset($type['description'])) $type['description'] = '';
    }
}

if ($action === 'edit' && $leaveTypeId) {
    $stmt = $db->prepare("SELECT * FROM leave_types WHERE id = ? AND company_id = ?");
    $stmt->execute([$leaveTypeId, $_SESSION['company_id']]);
    $editLeaveType = $stmt->fetch();
    
    if (!$editLeaveType) {
        $message = 'Leave type not found';
        $messageType = 'danger';
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Types Management - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
            --kenya-black: #000000;
        }

        .leave-types-header {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .leave-type-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .leave-type-card:hover {
            transform: translateY(-2px);
        }

        .btn-leave-type {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-leave-type:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,107,63,0.3);
            color: white;
        }

        .badge-paid {
            background: var(--kenya-green);
        }

        .badge-unpaid {
            background: var(--kenya-red);
        }

        .badge-carry-forward {
            background: var(--kenya-gold);
            color: var(--kenya-black);
        }

        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }

        .leave-type-stats {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .kenyan-leave-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left: 4px solid var(--kenya-gold);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="leave-types-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-list-ul me-3"></i>
                        Leave Types Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        📋 Configure and manage different types of leave for your organization
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php?page=leave_types&action=add" class="btn btn-light btn-lg">
                        <i class="fas fa-plus me-2"></i>Add Leave Type
                    </a>
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

        <?php if ($action === 'list'): ?>
            <!-- Leave Types List -->
            <div class="leave-type-card">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Leave Types Configuration
                        </h5>
                        <span class="badge bg-light text-dark">
                            <?php echo count($leaveTypes); ?> types configured
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($leaveTypes)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Days/Year</th>
                                        <th>Payment</th>
                                        <th>Carry Forward</th>
                                        <th>Usage Stats</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveTypes as $type): ?>
                                        <tr class="<?php echo !$type['is_active'] ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                    <?php if ($type['description']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($type['description']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $type['days_per_year'] ?? 0; ?> days</span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $type['is_paid'] ? 'badge-paid' : 'badge-unpaid'; ?>">
                                                    <?php echo $type['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (isset($type['carry_forward']) && $type['carry_forward']): ?>
                                                    <span class="badge badge-carry-forward">
                                                        Max <?php echo $type['max_carry_forward'] ?? 0; ?> days
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No carry forward</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong><?php echo $type['applications_count']; ?></strong> applications<br>
                                                    <strong><?php echo number_format($type['total_days_used']); ?></strong> days used
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $type['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?page=leave_types&action=edit&id=<?php echo $type['id']; ?>"
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <form method="POST" style="display: inline;"
                                                          onsubmit="return confirm('Toggle leave type status?')">
                                                        <input type="hidden" name="leave_type_id" value="<?php echo $type['id']; ?>">
                                                        <button type="submit" name="action" value="toggle_status"
                                                                class="btn btn-outline-<?php echo $type['is_active'] ? 'warning' : 'success'; ?>"
                                                                title="<?php echo $type['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $type['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <?php if ($type['applications_count'] == 0): ?>
                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Are you sure you want to delete this leave type?')">
                                                            <input type="hidden" name="leave_type_id" value="<?php echo $type['id']; ?>">
                                                            <button type="submit" name="action" value="delete"
                                                                    class="btn btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled title="Cannot delete - has applications">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>No Leave Types Found</h5>
                            <p class="text-muted">Start by creating your first leave type.</p>
                            <a href="index.php?page=leave_types&action=add" class="btn btn-leave-type">
                                <i class="fas fa-plus me-2"></i>Add First Leave Type
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Leave Type Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="leave-type-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add' ? 'Add New Leave Type' : 'Edit Leave Type'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="leave_type_id" value="<?php echo $editLeaveType['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Leave Type Name *</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?php echo htmlspecialchars($editLeaveType['name'] ?? ''); ?>"
                                                   placeholder="e.g., Annual Leave, Sick Leave" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="days_per_year" class="form-label">Days Per Year *</label>
                                            <input type="number" class="form-control" id="days_per_year" name="days_per_year"
                                                   value="<?php echo $editLeaveType['days_per_year'] ?? ''; ?>"
                                                   min="0" max="365" placeholder="21" required>
                                            <div class="form-text">Maximum days allowed per year</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Payment Status</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_paid" name="is_paid"
                                                       <?php echo ($editLeaveType['is_paid'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_paid">
                                                    <strong>Paid Leave</strong> - Employee receives full salary during leave
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Carry Forward Policy</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="carry_forward" name="carry_forward"
                                                       <?php echo ($editLeaveType['carry_forward'] ?? 0) ? 'checked' : ''; ?>
                                                       onchange="toggleCarryForwardOptions()">
                                                <label class="form-check-label" for="carry_forward">
                                                    <strong>Allow Carry Forward</strong> - Unused days can be carried to next year
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="carryForwardOptions" style="display: <?php echo ($editLeaveType['carry_forward'] ?? 0) ? 'block' : 'none'; ?>;">
                                        <div class="mb-3">
                                            <label for="max_carry_forward" class="form-label">Maximum Carry Forward Days</label>
                                            <input type="number" class="form-control" id="max_carry_forward" name="max_carry_forward"
                                                   value="<?php echo $editLeaveType['max_carry_forward'] ?? '0'; ?>"
                                                   min="0" max="365" placeholder="5">
                                            <div class="form-text">Maximum days that can be carried forward</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"
                                                      placeholder="Describe the purpose and conditions of this leave type..."><?php echo htmlspecialchars($editLeaveType['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=leave_types" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to List
                                    </a>
                                    <button type="submit" name="action" value="<?php echo $action; ?>" class="btn btn-leave-type">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add' ? 'Create Leave Type' : 'Update Leave Type'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Kenyan Leave Guidelines -->
                    <div class="leave-type-card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-flag me-2"></i>
                                🇰🇪 Kenyan Employment Act Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="kenyan-leave-info">
                                <h6>Statutory Leave Requirements:</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-calendar text-success me-2"></i>
                                        <strong>Annual Leave:</strong> Minimum 21 days per year
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-thermometer-half text-warning me-2"></i>
                                        <strong>Sick Leave:</strong> 7 days per year (with medical certificate)
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-baby text-pink me-2"></i>
                                        <strong>Maternity Leave:</strong> 3 months (90 days)
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-male text-primary me-2"></i>
                                        <strong>Paternity Leave:</strong> 2 weeks (14 days)
                                    </li>
                                </ul>
                            </div>

                            <hr>

                            <h6>Best Practices:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Annual leave should be carried forward (max 5 days)
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Sick leave requires medical certificate
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Compassionate leave for family emergencies
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Study leave for professional development
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <?php if ($action === 'edit' && isset($editLeaveType)): ?>
                        <div class="leave-type-card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Usage Statistics
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get usage stats for this leave type
                                $stmt = $db->prepare("
                                    SELECT
                                        COUNT(*) as total_applications,
                                        SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END) as approved_days,
                                        SUM(CASE WHEN status = 'pending' THEN days_requested ELSE 0 END) as pending_days
                                    FROM leave_applications
                                    WHERE leave_type_id = ? AND YEAR(created_at) = YEAR(NOW())
                                ");
                                $stmt->execute([$editLeaveType['id']]);
                                $stats = $stmt->fetch();
                                ?>
                                <div class="leave-type-stats">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="h4 mb-0"><?php echo $stats['total_applications']; ?></div>
                                            <small>Applications</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h4 mb-0"><?php echo $stats['approved_days']; ?></div>
                                            <small>Days Used</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h4 mb-0"><?php echo $stats['pending_days']; ?></div>
                                            <small>Pending</small>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Statistics for current year</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle carry forward options
        function toggleCarryForwardOptions() {
            const carryForwardCheckbox = document.getElementById('carry_forward');
            const carryForwardOptions = document.getElementById('carryForwardOptions');

            if (carryForwardCheckbox.checked) {
                carryForwardOptions.style.display = 'block';
            } else {
                carryForwardOptions.style.display = 'none';
                document.getElementById('max_carry_forward').value = '0';
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const daysPerYear = document.getElementById('days_per_year');
                    const maxCarryForward = document.getElementById('max_carry_forward');

                    if (daysPerYear && (parseInt(daysPerYear.value) < 0 || parseInt(daysPerYear.value) > 365)) {
                        e.preventDefault();
                        alert('Days per year must be between 0 and 365');
                        daysPerYear.focus();
                        return false;
                    }

                    if (maxCarryForward && parseInt(maxCarryForward.value) > parseInt(daysPerYear.value)) {
                        e.preventDefault();
                        alert('Maximum carry forward days cannot exceed days per year');
                        maxCarryForward.focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
