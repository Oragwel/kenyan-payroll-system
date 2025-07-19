<?php
/**
 * Deductions Management System
 * Manage deduction types and employee deductions
 */

// Security check - HR/Admin only
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$deductionId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add_type':
        case 'edit_type':
            $result = saveDeductionType($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'list';
            }
            break;
        case 'delete_type':
            $result = deleteDeductionType($_POST['deduction_type_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
        case 'assign':
            $result = assignDeductionToEmployee($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'employee_deductions';
            }
            break;
        case 'update_employee_deduction':
            $result = updateEmployeeDeduction($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'employee_deductions';
            break;
        case 'remove_employee_deduction':
            $result = removeEmployeeDeduction($_POST['employee_deduction_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'employee_deductions';
            break;
    }
}

/**
 * Save deduction type (add or edit)
 */
function saveDeductionType($data, $action) {
    global $db;
    
    $name = trim($data['name']);
    $description = trim($data['description']);
    $isStatutory = isset($data['is_statutory']) ? 1 : 0;
    $deductionTypeId = $data['deduction_type_id'] ?? null;
    
    // Validation
    if (empty($name)) {
        return ['message' => 'Deduction name is required', 'type' => 'danger'];
    }
    
    try {
        if ($action === 'add_type') {
            // Check if deduction type already exists
            $stmt = $db->prepare("SELECT id FROM deduction_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$name, $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                return ['message' => 'Deduction type already exists', 'type' => 'danger'];
            }
            
            $stmt = $db->prepare("INSERT INTO deduction_types (company_id, name, description, is_statutory) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['company_id'], $name, $description, $isStatutory]);
            
            logActivity('deduction_type_add', "Added deduction type: $name");
            return ['message' => 'Deduction type created successfully', 'type' => 'success'];
            
        } else { // edit_type
            $stmt = $db->prepare("UPDATE deduction_types SET name = ?, description = ?, is_statutory = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$name, $description, $isStatutory, $deductionTypeId, $_SESSION['company_id']]);
            
            logActivity('deduction_type_edit', "Updated deduction type: $name");
            return ['message' => 'Deduction type updated successfully', 'type' => 'success'];
        }
    } catch (Exception $e) {
        return ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete deduction type
 */
function deleteDeductionType($deductionTypeId) {
    global $db;
    
    try {
        // Check if deduction type is in use
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee_deductions WHERE deduction_type_id = ?");
        $stmt->execute([$deductionTypeId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['message' => 'Cannot delete deduction type that is assigned to employees', 'type' => 'danger'];
        }
        
        // Get deduction type name for logging
        $stmt = $db->prepare("SELECT name FROM deduction_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$deductionTypeId, $_SESSION['company_id']]);
        $deductionType = $stmt->fetch();
        
        if (!$deductionType) {
            return ['message' => 'Deduction type not found', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("DELETE FROM deduction_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$deductionTypeId, $_SESSION['company_id']]);
        
        logActivity('deduction_type_delete', "Deleted deduction type: " . $deductionType['name']);
        return ['message' => 'Deduction type deleted successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting deduction type: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Assign deduction to employee
 */
function assignDeductionToEmployee($data) {
    global $db;
    
    $employeeId = $data['employee_id'];
    $deductionTypeId = $data['deduction_type_id'];
    $amount = floatval($data['amount']);
    $effectiveDate = $data['effective_date'];
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    
    // Validation
    if (empty($employeeId) || empty($deductionTypeId) || $amount <= 0) {
        return ['message' => 'All fields are required and amount must be greater than 0', 'type' => 'danger'];
    }
    
    try {
        // Check if employee already has this deduction type active
        $stmt = $db->prepare("
            SELECT id FROM employee_deductions 
            WHERE employee_id = ? AND deduction_type_id = ? AND is_active = 1
        ");
        $stmt->execute([$employeeId, $deductionTypeId]);
        if ($stmt->fetch()) {
            return ['message' => 'Employee already has this deduction type assigned', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("
            INSERT INTO employee_deductions (employee_id, deduction_type_id, amount, effective_date, end_date, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$employeeId, $deductionTypeId, $amount, $effectiveDate, $endDate]);
        
        // Get employee and deduction type names for logging
        $stmt = $db->prepare("
            SELECT CONCAT(e.first_name, ' ', e.last_name) as employee_name, dt.name as deduction_name
            FROM employees e, deduction_types dt
            WHERE e.id = ? AND dt.id = ?
        ");
        $stmt->execute([$employeeId, $deductionTypeId]);
        $info = $stmt->fetch();
        
        logActivity('deduction_assign', "Assigned {$info['deduction_name']} (KES " . number_format($amount, 2) . ") to {$info['employee_name']}");
        return ['message' => 'Deduction assigned successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error assigning deduction: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update employee deduction
 */
function updateEmployeeDeduction($data) {
    global $db;
    
    $employeeDeductionId = $data['employee_deduction_id'];
    $amount = floatval($data['amount']);
    $effectiveDate = $data['effective_date'];
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    $isActive = isset($data['is_active']) ? 1 : 0;
    
    try {
        $stmt = $db->prepare("
            UPDATE employee_deductions 
            SET amount = ?, effective_date = ?, end_date = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $effectiveDate, $endDate, $isActive, $employeeDeductionId]);
        
        logActivity('deduction_update', "Updated employee deduction (ID: $employeeDeductionId)");
        return ['message' => 'Employee deduction updated successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating deduction: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Remove employee deduction
 */
function removeEmployeeDeduction($employeeDeductionId) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE employee_deductions SET is_active = 0 WHERE id = ?");
        $stmt->execute([$employeeDeductionId]);
        
        logActivity('deduction_remove', "Removed employee deduction (ID: $employeeDeductionId)");
        return ['message' => 'Employee deduction removed successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error removing deduction: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Create deduction tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS deduction_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_statutory BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS employee_deductions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            deduction_type_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            effective_date DATE NOT NULL,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(id) ON DELETE CASCADE
        )
    ");
    
    // Insert default statutory deductions if they don't exist
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM deduction_types WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $defaultDeductions = [
            ['PAYE Tax', 'Pay As You Earn income tax', 1],
            ['NSSF Contribution', 'National Social Security Fund contribution', 1],
            ['SHIF/NHIF Contribution', 'Social Health Insurance Fund contribution', 1],
            ['Housing Levy', 'Affordable Housing Levy (1.5% of gross salary)', 1],
            ['Loan Repayment', 'Staff loan repayment', 0],
            ['Insurance Premium', 'Life/Medical insurance premium', 0],
            ['Pension Contribution', 'Voluntary pension contribution', 0],
            ['Union Dues', 'Trade union membership fees', 0]
        ];
        
        $stmt = $db->prepare("INSERT INTO deduction_types (company_id, name, description, is_statutory) VALUES (?, ?, ?, ?)");
        foreach ($defaultDeductions as $deduction) {
            $stmt->execute([$_SESSION['company_id'], $deduction[0], $deduction[1], $deduction[2]]);
        }
    }
} catch (Exception $e) {
    // Table creation failed, but continue
}

// Get data based on action
if ($action === 'list' || $action === 'add_type' || $action === 'edit_type') {
    // Get deduction types
    if (DatabaseUtils::tableExists($db, 'deduction_types')) {
        $stmt = $db->prepare("SELECT * FROM deduction_types WHERE company_id = ? ORDER BY is_statutory DESC, name");
        $stmt->execute([$_SESSION['company_id']]);
        $deductionTypes = $stmt->fetchAll();
    } else {
        $deductionTypes = [];
    }
}

if ($action === 'edit_type' && $deductionId) {
    if (DatabaseUtils::tableExists($db, 'deduction_types')) {
        $stmt = $db->prepare("SELECT * FROM deduction_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$deductionId, $_SESSION['company_id']]);
        $editDeductionType = $stmt->fetch();

        if (!$editDeductionType) {
            $message = 'Deduction type not found';
            $messageType = 'danger';
            $action = 'list';
        }
    } else {
        $editDeductionType = null;
        $message = 'Deduction types table not available';
        $messageType = 'danger';
        $action = 'list';
    }
}

if ($action === 'employee_deductions' || $action === 'assign') {
    // Get employees
    $stmt = $db->prepare("
        SELECT id, employee_number, first_name, last_name, department_id
        FROM employees 
        WHERE company_id = ? AND employment_status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employees = $stmt->fetchAll();
    
    // Get deduction types
    $stmt = $db->prepare("SELECT * FROM deduction_types WHERE company_id = ? ORDER BY is_statutory DESC, name");
    $stmt->execute([$_SESSION['company_id']]);
    $deductionTypes = $stmt->fetchAll();
    
    // Get employee deductions
    $stmt = $db->prepare("
        SELECT ed.*, 
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               e.employee_number,
               dt.name as deduction_name,
               dt.is_statutory
        FROM employee_deductions ed
        JOIN employees e ON ed.employee_id = e.id
        JOIN deduction_types dt ON ed.deduction_type_id = dt.id
        WHERE e.company_id = ?
        ORDER BY e.first_name, e.last_name, dt.name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employeeDeductions = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deductions Management - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
            --kenya-black: #000000;
        }

        .deductions-header {
            background: linear-gradient(135deg, var(--kenya-red) 0%, #8b0000 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .deduction-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .deduction-card:hover {
            transform: translateY(-2px);
        }

        .btn-deduction {
            background: linear-gradient(135deg, var(--kenya-red), #8b0000);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-deduction:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(206,17,38,0.3);
            color: white;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--kenya-red);
            font-weight: 600;
        }

        .nav-tabs .nav-link.active {
            background: var(--kenya-red);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .badge-statutory {
            background: var(--kenya-red);
        }

        .badge-voluntary {
            background: var(--kenya-green);
        }

        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }

        .statutory-section {
            border-left: 4px solid var(--kenya-red);
            background: #fff5f5;
        }

        .voluntary-section {
            border-left: 4px solid var(--kenya-green);
            background: #f0fff4;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="deductions-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-minus-circle me-3"></i>
                        Deductions Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        📉 Manage statutory and voluntary deductions for payroll processing
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="index.php?page=deductions&action=add_type" class="btn btn-light">
                            <i class="fas fa-plus me-2"></i>Add Deduction Type
                        </a>
                        <a href="index.php?page=deductions&action=assign" class="btn btn-outline-light">
                            <i class="fas fa-user-minus me-2"></i>Assign to Employee
                        </a>
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

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($action, ['list', 'add_type', 'edit_type']) ? 'active' : ''; ?>"
                   href="index.php?page=deductions&action=list">
                    <i class="fas fa-list me-2"></i>Deduction Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($action, ['employee_deductions', 'assign']) ? 'active' : ''; ?>"
                   href="index.php?page=deductions&action=employee_deductions">
                    <i class="fas fa-users me-2"></i>Employee Deductions
                </a>
            </li>
        </ul>

        <?php if ($action === 'list'): ?>
            <!-- Deduction Types List -->
            <div class="deduction-card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>
                        Deduction Types
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($deductionTypes)): ?>
                        <!-- Statutory Deductions -->
                        <?php
                        $statutoryDeductions = array_filter($deductionTypes, function($d) { return $d['is_statutory']; });
                        $voluntaryDeductions = array_filter($deductionTypes, function($d) { return !$d['is_statutory']; });
                        ?>

                        <?php if (!empty($statutoryDeductions)): ?>
                            <div class="statutory-section p-3 mb-4 rounded">
                                <h6 class="text-danger mb-3">
                                    <i class="fas fa-gavel me-2"></i>
                                    Statutory Deductions (Government Required)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($statutoryDeductions as $type): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                        <span class="badge badge-statutory ms-2">Statutory</span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($type['description'] ?: 'No description'); ?></td>
                                                    <td><?php echo formatDate($type['created_at']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="index.php?page=deductions&action=edit_type&id=<?php echo $type['id']; ?>"
                                                               class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Voluntary Deductions -->
                        <?php if (!empty($voluntaryDeductions)): ?>
                            <div class="voluntary-section p-3 rounded">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-hand-holding-heart me-2"></i>
                                    Voluntary Deductions (Optional)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($voluntaryDeductions as $type): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                        <span class="badge badge-voluntary ms-2">Voluntary</span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($type['description'] ?: 'No description'); ?></td>
                                                    <td><?php echo formatDate($type['created_at']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="index.php?page=deductions&action=edit_type&id=<?php echo $type['id']; ?>"
                                                               class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <form method="POST" style="display: inline;"
                                                                  onsubmit="return confirm('Are you sure you want to delete this deduction type?')">
                                                                <input type="hidden" name="deduction_type_id" value="<?php echo $type['id']; ?>">
                                                                <button type="submit" name="action" value="delete_type"
                                                                        class="btn btn-outline-danger" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5>No Deduction Types Found</h5>
                            <p class="text-muted">Start by creating your first deduction type.</p>
                            <a href="index.php?page=deductions&action=add_type" class="btn btn-deduction">
                                <i class="fas fa-plus me-2"></i>Add First Deduction Type
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'add_type' || $action === 'edit_type'): ?>
            <!-- Add/Edit Deduction Type Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="deduction-card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add_type' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add_type' ? 'Add New Deduction Type' : 'Edit Deduction Type'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit_type'): ?>
                                    <input type="hidden" name="deduction_type_id" value="<?php echo $editDeductionType['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Deduction Name *</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?php echo htmlspecialchars($editDeductionType['name'] ?? ''); ?>"
                                                   placeholder="e.g., Loan Repayment, Insurance Premium" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Deduction Category</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_statutory" name="is_statutory"
                                                       <?php echo ($editDeductionType['is_statutory'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_statutory">
                                                    <strong>Statutory Deduction</strong> - Required by law (PAYE, NSSF, SHIF, Housing Levy)
                                                </label>
                                            </div>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle text-info me-1"></i>
                                                Statutory deductions cannot be deleted once created
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"
                                                      placeholder="Describe the purpose and conditions of this deduction..."><?php echo htmlspecialchars($editDeductionType['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=deductions" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to List
                                    </a>
                                    <button type="submit" name="action" value="<?php echo $action; ?>" class="btn btn-deduction">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add_type' ? 'Create Deduction Type' : 'Update Deduction Type'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Guidelines -->
                    <div class="deduction-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Deduction Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6>Statutory Deductions (🇰🇪 Kenya):</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-gavel text-danger me-2"></i>
                                    <strong>PAYE Tax</strong> - Income tax (progressive rates)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-shield-alt text-primary me-2"></i>
                                    <strong>NSSF</strong> - Pension contribution (6% of pensionable pay)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-heartbeat text-success me-2"></i>
                                    <strong>SHIF/NHIF</strong> - Health insurance (2.75% of gross salary)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-home text-warning me-2"></i>
                                    <strong>Housing Levy</strong> - 1.5% of gross salary
                                </li>
                            </ul>

                            <hr>

                            <h6>Voluntary Deductions:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-money-bill text-success me-2"></i>
                                    Loan repayments
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-umbrella text-primary me-2"></i>
                                    Insurance premiums
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-piggy-bank text-warning me-2"></i>
                                    Savings contributions
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-users text-info me-2"></i>
                                    Union dues
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'employee_deductions'): ?>
            <!-- Employee Deductions List -->
            <div class="deduction-card">
                <div class="card-header bg-warning text-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Employee Deductions
                        </h5>
                        <a href="index.php?page=deductions&action=assign" class="btn btn-dark btn-sm">
                            <i class="fas fa-plus me-2"></i>Assign Deduction
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($employeeDeductions)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Deduction Type</th>
                                        <th>Amount (KES)</th>
                                        <th>Effective Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Category</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeDeductions as $deduction): ?>
                                        <tr class="<?php echo !$deduction['is_active'] ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($deduction['employee_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($deduction['employee_number']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($deduction['deduction_name']); ?></td>
                                            <td>
                                                <strong class="text-danger">-KES <?php echo number_format($deduction['amount'], 2); ?></strong>
                                            </td>
                                            <td><?php echo formatDate($deduction['effective_date']); ?></td>
                                            <td>
                                                <?php if ($deduction['end_date']): ?>
                                                    <?php echo formatDate($deduction['end_date']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Ongoing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $deduction['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $deduction['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $deduction['is_statutory'] ? 'badge-statutory' : 'badge-voluntary'; ?>">
                                                    <?php echo $deduction['is_statutory'] ? 'Statutory' : 'Voluntary'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editDeductionModal<?php echo $deduction['id']; ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($deduction['is_active'] && !$deduction['is_statutory']): ?>
                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Remove this deduction from employee?')">
                                                            <input type="hidden" name="employee_deduction_id" value="<?php echo $deduction['id']; ?>">
                                                            <button type="submit" name="action" value="remove_employee_deduction"
                                                                    class="btn btn-outline-danger" title="Remove">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal for each deduction -->
                                        <div class="modal fade" id="editDeductionModal<?php echo $deduction['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Employee Deduction</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="employee_deduction_id" value="<?php echo $deduction['id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label">Employee</label>
                                                                <input type="text" class="form-control"
                                                                       value="<?php echo htmlspecialchars($deduction['employee_name']); ?>" readonly>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Deduction Type</label>
                                                                <input type="text" class="form-control"
                                                                       value="<?php echo htmlspecialchars($deduction['deduction_name']); ?>" readonly>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="amount<?php echo $deduction['id']; ?>" class="form-label">Amount (KES) *</label>
                                                                        <input type="number" class="form-control"
                                                                               id="amount<?php echo $deduction['id']; ?>" name="amount"
                                                                               value="<?php echo $deduction['amount']; ?>"
                                                                               step="0.01" min="0" required>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="effective_date<?php echo $deduction['id']; ?>" class="form-label">Effective Date *</label>
                                                                        <input type="date" class="form-control"
                                                                               id="effective_date<?php echo $deduction['id']; ?>" name="effective_date"
                                                                               value="<?php echo $deduction['effective_date']; ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="end_date<?php echo $deduction['id']; ?>" class="form-label">End Date</label>
                                                                        <input type="date" class="form-control"
                                                                               id="end_date<?php echo $deduction['id']; ?>" name="end_date"
                                                                               value="<?php echo $deduction['end_date']; ?>">
                                                                        <div class="form-text">Leave blank for ongoing deduction</div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <div class="form-check mt-4">
                                                                            <input class="form-check-input" type="checkbox"
                                                                                   id="is_active<?php echo $deduction['id']; ?>" name="is_active"
                                                                                   <?php echo $deduction['is_active'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_active<?php echo $deduction['id']; ?>">
                                                                                Active Deduction
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="action" value="update_employee_deduction" class="btn btn-primary">
                                                                Update Deduction
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-minus fa-3x text-muted mb-3"></i>
                            <h5>No Employee Deductions Found</h5>
                            <p class="text-muted">Start by assigning deductions to employees.</p>
                            <a href="index.php?page=deductions&action=assign" class="btn btn-deduction">
                                <i class="fas fa-plus me-2"></i>Assign First Deduction
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'assign'): ?>
            <!-- Assign Deduction to Employee Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="deduction-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-minus me-2"></i>
                                Assign Deduction to Employee
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="employee_id" class="form-label">Employee *</label>
                                            <select class="form-select" id="employee_id" name="employee_id" required>
                                                <option value="">Select Employee</option>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>">
                                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                        (<?php echo htmlspecialchars($employee['employee_number']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="deduction_type_id" class="form-label">Deduction Type *</label>
                                            <select class="form-select" id="deduction_type_id" name="deduction_type_id" required>
                                                <option value="">Select Deduction Type</option>
                                                <optgroup label="Statutory Deductions">
                                                    <?php foreach ($deductionTypes as $type): ?>
                                                        <?php if ($type['is_statutory']): ?>
                                                            <option value="<?php echo $type['id']; ?>"
                                                                    data-statutory="1">
                                                                <?php echo htmlspecialchars($type['name']); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <optgroup label="Voluntary Deductions">
                                                    <?php foreach ($deductionTypes as $type): ?>
                                                        <?php if (!$type['is_statutory']): ?>
                                                            <option value="<?php echo $type['id']; ?>"
                                                                    data-statutory="0">
                                                                <?php echo htmlspecialchars($type['name']); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">Amount (KES) *</label>
                                            <input type="number" class="form-control" id="amount" name="amount"
                                                   step="0.01" min="0" placeholder="0.00" required>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="effective_date" class="form-label">Effective Date *</label>
                                            <input type="date" class="form-control" id="effective_date" name="effective_date"
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">End Date</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date">
                                            <div class="form-text">Leave blank for ongoing deduction</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="alert alert-info" id="deductionInfo" style="display: none;">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <span id="deductionDetails"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=deductions&action=employee_deductions" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Employee Deductions
                                    </a>
                                    <button type="submit" name="action" value="assign" class="btn btn-deduction">
                                        <i class="fas fa-user-minus me-2"></i>Assign Deduction
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Assignment Guidelines -->
                    <div class="deduction-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                Assignment Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6>Statutory Deductions (🇰🇪):</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-gavel text-danger me-2"></i>
                                    <strong>PAYE:</strong> Calculated automatically based on tax bands
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-shield-alt text-primary me-2"></i>
                                    <strong>NSSF:</strong> 6% of pensionable pay (max KES 2,160)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-heartbeat text-success me-2"></i>
                                    <strong>SHIF:</strong> 2.75% of gross salary
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-home text-warning me-2"></i>
                                    <strong>Housing Levy:</strong> 1.5% of gross salary
                                </li>
                            </ul>

                            <hr>

                            <h6>Important Notes:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Each employee can have only one active deduction per type
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-info text-primary me-2"></i>
                                    Deductions reduce net pay
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-info me-2"></i>
                                    End date is optional for permanent deductions
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-gavel text-danger me-2"></i>
                                    Statutory deductions are mandatory
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            const deductionTypeSelect = document.getElementById('deduction_type_id');
            const deductionInfo = document.getElementById('deductionInfo');
            const deductionDetails = document.getElementById('deductionDetails');

            if (deductionTypeSelect) {
                deductionTypeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];

                    if (selectedOption.value) {
                        const isStatutory = selectedOption.dataset.statutory === '1';

                        let details = `<strong>${selectedOption.text}</strong> - `;

                        if (isStatutory) {
                            details += '<span class="badge badge-statutory">Statutory</span> ';
                            details += '<span class="text-danger">Required by Kenyan law</span>';
                        } else {
                            details += '<span class="badge badge-voluntary">Voluntary</span> ';
                            details += '<span class="text-success">Optional deduction</span>';
                        }

                        deductionDetails.innerHTML = details;
                        deductionInfo.style.display = 'block';
                    } else {
                        deductionInfo.style.display = 'none';
                    }
                });
            }

            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const amountInput = form.querySelector('input[name="amount"]');
                    if (amountInput && parseFloat(amountInput.value) <= 0) {
                        e.preventDefault();
                        alert('Amount must be greater than 0');
                        amountInput.focus();
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>
