<?php
/**
 * Employee Management Page
 */

if (!hasPermission('hr')) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $employeeId = $_POST['employee_id'] ?? null;
        $firstName = sanitizeInput($_POST['first_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $idNumber = sanitizeInput($_POST['id_number']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $hireDate = $_POST['hire_date'];
        $basicSalary = $_POST['basic_salary'];
        $departmentId = $_POST['department_id'] ?: null;
        $positionId = $_POST['position_id'] ?: null;
        $contractType = $_POST['contract_type'];
        
        if (empty($firstName) || empty($lastName) || empty($idNumber) || empty($hireDate) || empty($basicSalary)) {
            $message = 'Please fill in all required fields';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                // Check if ID number already exists
                $stmt = $db->prepare("SELECT id FROM employees WHERE id_number = ?");
                $stmt->execute([$idNumber]);
                
                if ($stmt->fetch()) {
                    $message = 'Employee with this ID number already exists';
                    $messageType = 'danger';
                } else {
                    $employeeNumber = generateEmployeeNumber($_SESSION['company_id']);
                    
                    $stmt = $db->prepare("
                        INSERT INTO employees (
                            company_id, employee_number, first_name, middle_name, last_name, 
                            id_number, email, phone, hire_date, basic_salary, department_id, 
                            position_id, contract_type
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['company_id'], $employeeNumber, $firstName, $middleName, 
                        $lastName, $idNumber, $email, $phone, $hireDate, $basicSalary, 
                        $departmentId, $positionId, $contractType
                    ])) {
                        $message = 'Employee added successfully';
                        $messageType = 'success';
                        logActivity('employee_add', "Added employee: $firstName $lastName");
                    } else {
                        $message = 'Failed to add employee';
                        $messageType = 'danger';
                    }
                }
            } else {
                // Update employee
                $stmt = $db->prepare("
                    UPDATE employees SET 
                        first_name = ?, middle_name = ?, last_name = ?, id_number = ?, 
                        email = ?, phone = ?, hire_date = ?, basic_salary = ?, 
                        department_id = ?, position_id = ?, contract_type = ?
                    WHERE id = ? AND company_id = ?
                ");
                
                if ($stmt->execute([
                    $firstName, $middleName, $lastName, $idNumber, $email, $phone, 
                    $hireDate, $basicSalary, $departmentId, $positionId, $contractType,
                    $employeeId, $_SESSION['company_id']
                ])) {
                    $message = 'Employee updated successfully';
                    $messageType = 'success';
                    logActivity('employee_update', "Updated employee: $firstName $lastName");
                } else {
                    $message = 'Failed to update employee';
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Get employees list
if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, p.title as position_title
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN job_positions p ON e.position_id = p.id
        WHERE e.company_id = ?
        ORDER BY e.first_name, e.last_name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employees = $stmt->fetchAll();
}

// Get employee for editing
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: index.php?page=employees');
        exit;
    }
}

// Get departments and positions for form
if ($action === 'add' || $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM departments WHERE company_id = ? ORDER BY name");
    $stmt->execute([$_SESSION['company_id']]);
    $departments = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM job_positions WHERE company_id = ? ORDER BY title");
    $stmt->execute([$_SESSION['company_id']]);
    $positions = $stmt->fetchAll();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-users"></i> Employee Management</h2>
                <?php if ($action === 'list'): ?>
                    <a href="index.php?page=employees&action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Employee
                    </a>
                <?php else: ?>
                    <a href="index.php?page=employees" class="btn btn-secondary">
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
        <!-- Employee List -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">Employees (<?php echo count($employees); ?>)</h5>
                    </div>
                    <div class="col-auto">
                        <input type="text" class="form-control search-input" placeholder="Search employees...">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($employees)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee #</th>
                                    <th>Name</th>
                                    <th>ID Number</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Basic Salary</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['employee_number']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="employee-avatar me-3">
                                                    <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($emp['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['id_number']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['position_title'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo formatCurrency($emp['basic_salary']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $emp['employment_status']; ?>">
                                                <?php echo ucfirst($emp['employment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?page=employees&action=view&id=<?php echo $emp['id']; ?>" 
                                                   class="btn btn-outline-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="index.php?page=employees&action=edit&id=<?php echo $emp['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="index.php?page=payslips&employee_id=<?php echo $emp['id']; ?>" 
                                                   class="btn btn-outline-success" title="Payslips">
                                                    <i class="fas fa-receipt"></i>
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
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Employees Found</h5>
                        <p class="text-muted">Start by adding your first employee to the system.</p>
                        <a href="index.php?page=employees&action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Employee
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Employee Form -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i> 
                    <?php echo $action === 'add' ? 'Add New Employee' : 'Edit Employee'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                       value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_number" class="form-label">ID Number *</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" 
                                       value="<?php echo htmlspecialchars($employee['id_number'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Hire Date *</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                       value="<?php echo $employee['hire_date'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo ($employee['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="position_id" class="form-label">Position</label>
                                <select class="form-select" id="position_id" name="position_id">
                                    <option value="">Select Position</option>
                                    <?php foreach ($positions as $pos): ?>
                                        <option value="<?php echo $pos['id']; ?>" 
                                                <?php echo ($employee['position_id'] ?? '') == $pos['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pos['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contract_type" class="form-label">Contract Type *</label>
                                <select class="form-select" id="contract_type" name="contract_type" required>
                                    <option value="">Select Type</option>
                                    <option value="permanent" <?php echo ($employee['contract_type'] ?? '') === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                                    <option value="contract" <?php echo ($employee['contract_type'] ?? '') === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="casual" <?php echo ($employee['contract_type'] ?? '') === 'casual' ? 'selected' : ''; ?>>Casual</option>
                                    <option value="intern" <?php echo ($employee['contract_type'] ?? '') === 'intern' ? 'selected' : ''; ?>>Intern</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="basic_salary" class="form-label">Basic Salary (KES) *</label>
                                <input type="number" class="form-control currency-input" id="basic_salary" name="basic_salary" 
                                       value="<?php echo $employee['basic_salary'] ?? ''; ?>" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <a href="index.php?page=employees" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Add Employee' : 'Update Employee'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
