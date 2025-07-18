<?php
/**
 * Allowances Management System
 * Manage allowance types and employee allowances
 */

// Security check - HR/Admin only
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$allowanceId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add_type':
        case 'edit_type':
            $result = saveAllowanceType($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'list';
            }
            break;
        case 'delete_type':
            $result = deleteAllowanceType($_POST['allowance_type_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
        case 'assign':
            $result = assignAllowanceToEmployee($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'employee_allowances';
            }
            break;
        case 'update_employee_allowance':
            $result = updateEmployeeAllowance($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'employee_allowances';
            break;
        case 'remove_employee_allowance':
            $result = removeEmployeeAllowance($_POST['employee_allowance_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'employee_allowances';
            break;
    }
}

/**
 * Save allowance type (add or edit)
 */
function saveAllowanceType($data, $action) {
    global $db;
    
    $name = trim($data['name']);
    $description = trim($data['description']);
    $isTaxable = isset($data['is_taxable']) ? 1 : 0;
    $isPensionable = isset($data['is_pensionable']) ? 1 : 0;
    $allowanceTypeId = $data['allowance_type_id'] ?? null;
    
    // Validation
    if (empty($name)) {
        return ['message' => 'Allowance name is required', 'type' => 'danger'];
    }
    
    try {
        if ($action === 'add_type') {
            // Check if allowance type already exists
            $stmt = $db->prepare("SELECT id FROM allowance_types WHERE name = ? AND company_id = ?");
            $stmt->execute([$name, $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                return ['message' => 'Allowance type already exists', 'type' => 'danger'];
            }
            
            $stmt = $db->prepare("INSERT INTO allowance_types (company_id, name, description, is_taxable, is_pensionable) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['company_id'], $name, $description, $isTaxable, $isPensionable]);
            
            logActivity('allowance_type_add', "Added allowance type: $name");
            return ['message' => 'Allowance type created successfully', 'type' => 'success'];
            
        } else { // edit_type
            $stmt = $db->prepare("UPDATE allowance_types SET name = ?, description = ?, is_taxable = ?, is_pensionable = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$name, $description, $isTaxable, $isPensionable, $allowanceTypeId, $_SESSION['company_id']]);
            
            logActivity('allowance_type_edit', "Updated allowance type: $name");
            return ['message' => 'Allowance type updated successfully', 'type' => 'success'];
        }
    } catch (Exception $e) {
        return ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete allowance type
 */
function deleteAllowanceType($allowanceTypeId) {
    global $db;
    
    try {
        // Check if allowance type is in use
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employee_allowances WHERE allowance_type_id = ?");
        $stmt->execute([$allowanceTypeId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['message' => 'Cannot delete allowance type that is assigned to employees', 'type' => 'danger'];
        }
        
        // Get allowance type name for logging
        $stmt = $db->prepare("SELECT name FROM allowance_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$allowanceTypeId, $_SESSION['company_id']]);
        $allowanceType = $stmt->fetch();
        
        if (!$allowanceType) {
            return ['message' => 'Allowance type not found', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("DELETE FROM allowance_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$allowanceTypeId, $_SESSION['company_id']]);
        
        logActivity('allowance_type_delete', "Deleted allowance type: " . $allowanceType['name']);
        return ['message' => 'Allowance type deleted successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting allowance type: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Assign allowance to employee
 */
function assignAllowanceToEmployee($data) {
    global $db;
    
    $employeeId = $data['employee_id'];
    $allowanceTypeId = $data['allowance_type_id'];
    $amount = floatval($data['amount']);
    $effectiveDate = $data['effective_date'];
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    
    // Validation
    if (empty($employeeId) || empty($allowanceTypeId) || $amount <= 0) {
        return ['message' => 'All fields are required and amount must be greater than 0', 'type' => 'danger'];
    }
    
    try {
        // Check if employee already has this allowance type active
        $stmt = $db->prepare("
            SELECT id FROM employee_allowances 
            WHERE employee_id = ? AND allowance_type_id = ? AND is_active = 1
        ");
        $stmt->execute([$employeeId, $allowanceTypeId]);
        if ($stmt->fetch()) {
            return ['message' => 'Employee already has this allowance type assigned', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("
            INSERT INTO employee_allowances (employee_id, allowance_type_id, amount, effective_date, end_date, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$employeeId, $allowanceTypeId, $amount, $effectiveDate, $endDate]);
        
        // Get employee and allowance type names for logging
        $stmt = $db->prepare("
            SELECT CONCAT(e.first_name, ' ', e.last_name) as employee_name, at.name as allowance_name
            FROM employees e, allowance_types at
            WHERE e.id = ? AND at.id = ?
        ");
        $stmt->execute([$employeeId, $allowanceTypeId]);
        $info = $stmt->fetch();
        
        logActivity('allowance_assign', "Assigned {$info['allowance_name']} (KES " . number_format($amount, 2) . ") to {$info['employee_name']}");
        return ['message' => 'Allowance assigned successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error assigning allowance: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Update employee allowance
 */
function updateEmployeeAllowance($data) {
    global $db;
    
    $employeeAllowanceId = $data['employee_allowance_id'];
    $amount = floatval($data['amount']);
    $effectiveDate = $data['effective_date'];
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    $isActive = isset($data['is_active']) ? 1 : 0;
    
    try {
        $stmt = $db->prepare("
            UPDATE employee_allowances 
            SET amount = ?, effective_date = ?, end_date = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $effectiveDate, $endDate, $isActive, $employeeAllowanceId]);
        
        logActivity('allowance_update', "Updated employee allowance (ID: $employeeAllowanceId)");
        return ['message' => 'Employee allowance updated successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating allowance: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Remove employee allowance
 */
function removeEmployeeAllowance($employeeAllowanceId) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE employee_allowances SET is_active = 0 WHERE id = ?");
        $stmt->execute([$employeeAllowanceId]);
        
        logActivity('allowance_remove', "Removed employee allowance (ID: $employeeAllowanceId)");
        return ['message' => 'Employee allowance removed successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error removing allowance: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Create allowance tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS allowance_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_taxable BOOLEAN DEFAULT TRUE,
            is_pensionable BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS employee_allowances (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            allowance_type_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            effective_date DATE NOT NULL,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (allowance_type_id) REFERENCES allowance_types(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table creation failed, but continue
}

// Get data based on action
if ($action === 'list' || $action === 'add_type' || $action === 'edit_type') {
    // Get allowance types
    if (DatabaseUtils::tableExists($db, 'allowance_types')) {
        $stmt = $db->prepare("SELECT * FROM allowance_types WHERE company_id = ? ORDER BY name");
        $stmt->execute([$_SESSION['company_id']]);
        $allowanceTypes = $stmt->fetchAll();
    } else {
        $allowanceTypes = [];
    }
}

if ($action === 'edit_type' && $allowanceId) {
    if (DatabaseUtils::tableExists($db, 'allowance_types')) {
        $stmt = $db->prepare("SELECT * FROM allowance_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$allowanceId, $_SESSION['company_id']]);
        $editAllowanceType = $stmt->fetch();

        if (!$editAllowanceType) {
            $message = 'Allowance type not found';
            $messageType = 'danger';
            $action = 'list';
        }
    } else {
        $editAllowanceType = null;
        $message = 'Allowance types table not available';
        $messageType = 'danger';
        $action = 'list';
    }
}

if ($action === 'employee_allowances' || $action === 'assign') {
    // Get employees
    $stmt = $db->prepare("
        SELECT id, employee_number, first_name, last_name, department_id
        FROM employees 
        WHERE company_id = ? AND employment_status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employees = $stmt->fetchAll();
    
    // Get allowance types
    $stmt = $db->prepare("SELECT * FROM allowance_types WHERE company_id = ? ORDER BY name");
    $stmt->execute([$_SESSION['company_id']]);
    $allowanceTypes = $stmt->fetchAll();
    
    // Get employee allowances
    $stmt = $db->prepare("
        SELECT ea.*, 
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               e.employee_number,
               at.name as allowance_name,
               at.is_taxable,
               at.is_pensionable
        FROM employee_allowances ea
        JOIN employees e ON ea.employee_id = e.id
        JOIN allowance_types at ON ea.allowance_type_id = at.id
        WHERE e.company_id = ?
        ORDER BY e.first_name, e.last_name, at.name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employeeAllowances = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allowances Management - Kenyan Payroll System</title>
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-dark-green: #004d2e;
            --kenya-red: #ce1126;
            --kenya-gold: #ffd700;
        }

        .allowances-header {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .allowance-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .allowance-card:hover {
            transform: translateY(-2px);
        }

        .btn-allowance {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-allowance:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,107,63,0.3);
            color: white;
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

        .badge-taxable {
            background: var(--kenya-red);
        }

        .badge-pensionable {
            background: var(--kenya-gold);
            color: #000;
        }

        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="allowances-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-plus-circle me-3"></i>
                        Allowances Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        💰 Manage allowance types and employee allowance assignments
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="index.php?page=allowances&action=add_type" class="btn btn-light">
                            <i class="fas fa-plus me-2"></i>Add Allowance Type
                        </a>
                        <a href="index.php?page=allowances&action=assign" class="btn btn-outline-light">
                            <i class="fas fa-user-plus me-2"></i>Assign to Employee
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
                   href="index.php?page=allowances&action=list">
                    <i class="fas fa-list me-2"></i>Allowance Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($action, ['employee_allowances', 'assign']) ? 'active' : ''; ?>"
                   href="index.php?page=allowances&action=employee_allowances">
                    <i class="fas fa-users me-2"></i>Employee Allowances
                </a>
            </li>
        </ul>

        <?php if ($action === 'list'): ?>
            <!-- Allowance Types List -->
            <div class="allowance-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>
                        Allowance Types
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($allowanceTypes)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Tax Status</th>
                                        <th>Pension Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allowanceTypes as $type): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($type['description'] ?: 'No description'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $type['is_taxable'] ? 'badge-taxable' : 'bg-success'; ?>">
                                                    <?php echo $type['is_taxable'] ? 'Taxable' : 'Non-Taxable'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $type['is_pensionable'] ? 'badge-pensionable' : 'bg-secondary'; ?>">
                                                    <?php echo $type['is_pensionable'] ? 'Pensionable' : 'Non-Pensionable'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($type['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?page=allowances&action=edit_type&id=<?php echo $type['id']; ?>"
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;"
                                                          onsubmit="return confirm('Are you sure you want to delete this allowance type?')">
                                                        <input type="hidden" name="allowance_type_id" value="<?php echo $type['id']; ?>">
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
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5>No Allowance Types Found</h5>
                            <p class="text-muted">Start by creating your first allowance type.</p>
                            <a href="index.php?page=allowances&action=add_type" class="btn btn-allowance">
                                <i class="fas fa-plus me-2"></i>Add First Allowance Type
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'add_type' || $action === 'edit_type'): ?>
            <!-- Add/Edit Allowance Type Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="allowance-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add_type' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add_type' ? 'Add New Allowance Type' : 'Edit Allowance Type'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit_type'): ?>
                                    <input type="hidden" name="allowance_type_id" value="<?php echo $editAllowanceType['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Allowance Name *</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?php echo htmlspecialchars($editAllowanceType['name'] ?? ''); ?>"
                                                   placeholder="e.g., House Allowance, Transport Allowance" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tax & Pension Status</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_taxable" name="is_taxable"
                                                       <?php echo ($editAllowanceType['is_taxable'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_taxable">
                                                    <strong>Taxable</strong> - Subject to PAYE tax
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_pensionable" name="is_pensionable"
                                                       <?php echo ($editAllowanceType['is_pensionable'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_pensionable">
                                                    <strong>Pensionable</strong> - Subject to NSSF contributions
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"
                                                      placeholder="Describe the purpose and conditions of this allowance..."><?php echo htmlspecialchars($editAllowanceType['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=allowances" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to List
                                    </a>
                                    <button type="submit" name="action" value="<?php echo $action; ?>" class="btn btn-allowance">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add_type' ? 'Create Allowance Type' : 'Update Allowance Type'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Guidelines -->
                    <div class="allowance-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Allowance Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6>Common Allowance Types:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-home text-primary me-2"></i>
                                    <strong>House Allowance</strong> - Usually taxable
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-car text-success me-2"></i>
                                    <strong>Transport Allowance</strong> - May be non-taxable up to KES 3,000
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-utensils text-warning me-2"></i>
                                    <strong>Lunch Allowance</strong> - Usually non-taxable
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-phone text-info me-2"></i>
                                    <strong>Communication Allowance</strong> - Usually taxable
                                </li>
                            </ul>

                            <hr>

                            <h6>Tax Considerations:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Taxable allowances are subject to PAYE
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Pensionable allowances contribute to NSSF
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-info text-primary me-2"></i>
                                    Consult KRA guidelines for specific rules
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'employee_allowances'): ?>
            <!-- Employee Allowances List -->
            <div class="allowance-card">
                <div class="card-header bg-warning text-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Employee Allowances
                        </h5>
                        <a href="index.php?page=allowances&action=assign" class="btn btn-dark btn-sm">
                            <i class="fas fa-plus me-2"></i>Assign Allowance
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($employeeAllowances)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Allowance Type</th>
                                        <th>Amount (KES)</th>
                                        <th>Effective Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Tax/Pension</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeeAllowances as $allowance): ?>
                                        <tr class="<?php echo !$allowance['is_active'] ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($allowance['employee_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($allowance['employee_number']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($allowance['allowance_name']); ?></td>
                                            <td>
                                                <strong>KES <?php echo number_format($allowance['amount'], 2); ?></strong>
                                            </td>
                                            <td><?php echo formatDate($allowance['effective_date']); ?></td>
                                            <td>
                                                <?php if ($allowance['end_date']): ?>
                                                    <?php echo formatDate($allowance['end_date']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Ongoing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $allowance['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $allowance['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($allowance['is_taxable']): ?>
                                                    <span class="badge badge-taxable">Tax</span>
                                                <?php endif; ?>
                                                <?php if ($allowance['is_pensionable']): ?>
                                                    <span class="badge badge-pensionable">Pension</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editAllowanceModal<?php echo $allowance['id']; ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($allowance['is_active']): ?>
                                                        <form method="POST" style="display: inline;"
                                                              onsubmit="return confirm('Remove this allowance from employee?')">
                                                            <input type="hidden" name="employee_allowance_id" value="<?php echo $allowance['id']; ?>">
                                                            <button type="submit" name="action" value="remove_employee_allowance"
                                                                    class="btn btn-outline-danger" title="Remove">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal for each allowance -->
                                        <div class="modal fade" id="editAllowanceModal<?php echo $allowance['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Employee Allowance</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="employee_allowance_id" value="<?php echo $allowance['id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label">Employee</label>
                                                                <input type="text" class="form-control"
                                                                       value="<?php echo htmlspecialchars($allowance['employee_name']); ?>" readonly>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label">Allowance Type</label>
                                                                <input type="text" class="form-control"
                                                                       value="<?php echo htmlspecialchars($allowance['allowance_name']); ?>" readonly>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="amount<?php echo $allowance['id']; ?>" class="form-label">Amount (KES) *</label>
                                                                        <input type="number" class="form-control"
                                                                               id="amount<?php echo $allowance['id']; ?>" name="amount"
                                                                               value="<?php echo $allowance['amount']; ?>"
                                                                               step="0.01" min="0" required>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="effective_date<?php echo $allowance['id']; ?>" class="form-label">Effective Date *</label>
                                                                        <input type="date" class="form-control"
                                                                               id="effective_date<?php echo $allowance['id']; ?>" name="effective_date"
                                                                               value="<?php echo $allowance['effective_date']; ?>" required>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="end_date<?php echo $allowance['id']; ?>" class="form-label">End Date</label>
                                                                        <input type="date" class="form-control"
                                                                               id="end_date<?php echo $allowance['id']; ?>" name="end_date"
                                                                               value="<?php echo $allowance['end_date']; ?>">
                                                                        <div class="form-text">Leave blank for ongoing allowance</div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <div class="form-check mt-4">
                                                                            <input class="form-check-input" type="checkbox"
                                                                                   id="is_active<?php echo $allowance['id']; ?>" name="is_active"
                                                                                   <?php echo $allowance['is_active'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_active<?php echo $allowance['id']; ?>">
                                                                                Active Allowance
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="action" value="update_employee_allowance" class="btn btn-primary">
                                                                Update Allowance
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
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <h5>No Employee Allowances Found</h5>
                            <p class="text-muted">Start by assigning allowances to employees.</p>
                            <a href="index.php?page=allowances&action=assign" class="btn btn-allowance">
                                <i class="fas fa-plus me-2"></i>Assign First Allowance
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'assign'): ?>
            <!-- Assign Allowance to Employee Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="allowance-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-plus me-2"></i>
                                Assign Allowance to Employee
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
                                            <label for="allowance_type_id" class="form-label">Allowance Type *</label>
                                            <select class="form-select" id="allowance_type_id" name="allowance_type_id" required>
                                                <option value="">Select Allowance Type</option>
                                                <?php foreach ($allowanceTypes as $type): ?>
                                                    <option value="<?php echo $type['id']; ?>"
                                                            data-taxable="<?php echo $type['is_taxable']; ?>"
                                                            data-pensionable="<?php echo $type['is_pensionable']; ?>">
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
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
                                            <div class="form-text">Leave blank for ongoing allowance</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="alert alert-info" id="allowanceInfo" style="display: none;">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <span id="allowanceDetails"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?page=allowances&action=employee_allowances" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Employee Allowances
                                    </a>
                                    <button type="submit" name="action" value="assign" class="btn btn-allowance">
                                        <i class="fas fa-user-plus me-2"></i>Assign Allowance
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Assignment Guidelines -->
                    <div class="allowance-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                Assignment Guidelines
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6>Before Assigning:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Verify employee eligibility
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Confirm allowance amount
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Set appropriate effective date
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Consider tax implications
                                </li>
                            </ul>

                            <hr>

                            <h6>Important Notes:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-1">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Each employee can have only one active allowance per type
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-info text-primary me-2"></i>
                                    Allowances affect payroll calculations
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-calendar text-info me-2"></i>
                                    End date is optional for permanent allowances
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
            const allowanceTypeSelect = document.getElementById('allowance_type_id');
            const allowanceInfo = document.getElementById('allowanceInfo');
            const allowanceDetails = document.getElementById('allowanceDetails');

            if (allowanceTypeSelect) {
                allowanceTypeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];

                    if (selectedOption.value) {
                        const isTaxable = selectedOption.dataset.taxable === '1';
                        const isPensionable = selectedOption.dataset.pensionable === '1';

                        let details = `<strong>${selectedOption.text}</strong> - `;
                        let badges = [];

                        if (isTaxable) badges.push('<span class="badge badge-taxable">Taxable</span>');
                        if (isPensionable) badges.push('<span class="badge badge-pensionable">Pensionable</span>');
                        if (!isTaxable && !isPensionable) badges.push('<span class="badge bg-success">Non-Taxable & Non-Pensionable</span>');

                        details += badges.join(' ');

                        allowanceDetails.innerHTML = details;
                        allowanceInfo.style.display = 'block';
                    } else {
                        allowanceInfo.style.display = 'none';
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
