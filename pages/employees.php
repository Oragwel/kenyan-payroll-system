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

// Helper function to convert empty strings to NULL
function nullIfEmpty($value) {
    return isset($value) && trim($value) !== '' ? trim($value) : null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'bulk_import') {
        // Handle CSV bulk import
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $result = handleBulkImport($_FILES['csv_file']);
            $message = $result['message'];
            $messageType = $result['type'];
        } else {
            $message = 'Please select a valid CSV file';
            $messageType = 'danger';
        }
    } elseif ($action === 'add' || $action === 'edit') {
        $employeeId    = $_POST['employee_id'] ?? null;
        $firstName     = sanitizeInput($_POST['first_name']);
        $middleName    = nullIfEmpty(sanitizeInput($_POST['middle_name']));    // Optional
        $lastName      = sanitizeInput($_POST['last_name']);
        $idNumber      = nullIfEmpty(sanitizeInput($_POST['id_number']));      // Optional
        $email         = nullIfEmpty($_POST['email']);           // Optional
        $phone         = nullIfEmpty($_POST['phone']);           // Optional
        $hireDate      = nullIfEmpty($_POST['hire_date']);       // Optional (must be date or NULL)
        $basicSalary   = nullIfEmpty($_POST['basic_salary']);    // Optional
        $departmentId  = nullIfEmpty($_POST['department_id']);   // Optional
        $positionId    = nullIfEmpty($_POST['position_id']);     // Optional
        $contractType  = nullIfEmpty($_POST['contract_type']);   // Optional
        $bankCode      = nullIfEmpty($_POST['bank_code']);       // Optional
        $bankName      = nullIfEmpty($_POST['bank_name']);       // Optional
        $bankBranch    = nullIfEmpty($_POST['bank_branch']);     // Optional
        $accountNumber = nullIfEmpty($_POST['account_number']);  // Optional

        if (empty($firstName) || empty($lastName) || empty($basicSalary)) {
            $message = 'Please fill in all required fields (First Name, Last Name, Basic Salary)';
            $messageType = 'danger';
        } else {
            if ($action === 'add') {
                // Check if ID number already exists (only if ID number is provided)
                $canProceed = true;
                if (!empty($idNumber)) {
                    $stmt = $db->prepare("SELECT id FROM employees WHERE id_number = ?");
                    $stmt->execute([$idNumber]);

                    if ($stmt->fetch()) {
                        $message = 'Employee with this ID number already exists';
                        $messageType = 'danger';
                        $canProceed = false;
                    }
                }
                $hireDate = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;

                if ($canProceed) {
                    $employeeNumber = generateEmployeeNumber($_SESSION['company_id']);

                    $stmt = $db->prepare("
                        INSERT INTO employees (
                            company_id, employee_number, first_name, middle_name, last_name,
                            id_number, email, phone, hire_date, basic_salary, department_id,
                            position_id, contract_type, bank_code, bank_name, bank_branch, account_number
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if ($stmt->execute([
                        $_SESSION['company_id'], $employeeNumber, $firstName, $middleName, $lastName,
                        $idNumber, $email, $phone, $hireDate, $basicSalary,
                        $departmentId, $positionId, $contractType, $bankCode, $bankName, $bankBranch, $accountNumber
                    ])) {
                        $message = 'Employee added successfully';
                        $messageType = 'success';
                        logActivity('employee_add', "Added employee: $firstName $middleName $lastName");
                    } else {
                        $message = 'Failed to add employee';
                        $messageType = 'danger';
                    }
                }
            } else {
                $stmt = $db->prepare("
                    UPDATE employees SET
                        first_name = ?, middle_name = ?, last_name = ?, id_number = ?,
                        email = ?, phone = ?, hire_date = ?, basic_salary = ?,
                        department_id = ?, position_id = ?, contract_type = ?,
                        bank_code = ?, bank_name = ?, bank_branch = ?, account_number = ?
                    WHERE id = ? AND company_id = ?
                ");

                if ($stmt->execute([
                    $firstName, $middleName, $lastName, $idNumber, $email, $phone,
                    $hireDate, $basicSalary, $departmentId, $positionId, $contractType,
                    $bankCode, $bankName, $bankBranch, $accountNumber,
                    $employeeId, $_SESSION['company_id']
                ])) {
                    $message = 'Employee updated successfully';
                    $messageType = 'success';
                    logActivity('employee_update', "Updated employee: $firstName $middleName $lastName");
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

// Get employee for editing or viewing
if (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, p.title as position_title
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN job_positions p ON e.position_id = p.id
        WHERE e.id = ? AND e.company_id = ?
    ");
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

/**
 * Handle bulk CSV import of employees
 */
function handleBulkImport($file) {
    global $db;

    $uploadDir = 'uploads/csv/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'employees_import_' . date('Y-m-d_H-i-s') . '.csv';
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['message' => 'Failed to upload file', 'type' => 'danger'];
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['message' => 'Failed to read CSV file', 'type' => 'danger'];
    }

    $header = fgetcsv($handle);
    $expectedHeaders = [
        'first_name', 'middle_name', 'last_name', 'id_number', 'email',
        'phone', 'hire_date', 'basic_salary', 'department_name',
        'position_title', 'employment_type', 'kra_pin', 'nssf_number', 'nhif_number',
        'bank_code', 'bank_name', 'bank_branch', 'account_number'
    ];

    // Normalize headers (remove BOM and trim)
    $header = array_map(function($h) {
        return trim(str_replace("\xEF\xBB\xBF", '', $h));
    }, $header);

    // Validate headers
    $missingHeaders = array_diff($expectedHeaders, $header);
    if (!empty($missingHeaders)) {
        fclose($handle);
        unlink($filePath);
        return [
            'message' => 'Missing required columns: ' . implode(', ', $missingHeaders) .
                        '. Please download the template and ensure all columns are present.',
            'type' => 'danger'
        ];
    }

    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $row = 1;

    // Get departments and positions for lookup
    $stmt = $db->prepare("SELECT id, name FROM departments WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $departments = [];
    while ($dept = $stmt->fetch()) {
        $departments[strtolower($dept['name'])] = $dept['id'];
    }

    $stmt = $db->prepare("SELECT id, title FROM job_positions WHERE company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $positions = [];
    while ($pos = $stmt->fetch()) {
        $positions[strtolower($pos['title'])] = $pos['id'];
    }

    while (($data = fgetcsv($handle)) !== FALSE) {
        $row++;

        if (count($data) !== count($header)) {
            $errors[] = "Row $row: Column count mismatch";
            $errorCount++;
            continue;
        }

        $employee = array_combine($header, $data);

        // Validate required fields (only first_name, last_name, and basic_salary are required)
        if (empty($employee['first_name']) || empty($employee['last_name']) ||
            empty($employee['basic_salary'])) {
            $errors[] = "Row $row: Missing required fields (first_name, last_name, basic_salary)";
            $errorCount++;
            continue;
        }

        // Check if employee already exists (only if ID number is provided)
        if (!empty($employee['id_number'])) {
            $stmt = $db->prepare("SELECT id FROM employees WHERE id_number = ? AND company_id = ?");
            $stmt->execute([$employee['id_number'], $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                $errors[] = "Row $row: Employee with ID {$employee['id_number']} already exists";
                $errorCount++;
                continue;
            }
        }

        // Lookup department and position IDs
        $departmentId = null;
        if (!empty($employee['department_name'])) {
            $departmentId = $departments[strtolower($employee['department_name'])] ?? null;
        }

        $positionId = null;
        if (!empty($employee['position_title'])) {
            $positionId = $positions[strtolower($employee['position_title'])] ?? null;
        }

        // Validate employment type (optional, defaults to permanent)
        $validEmploymentTypes = ['permanent', 'contract', 'casual', 'intern'];
        $contractType = !empty($employee['employment_type']) ? strtolower($employee['employment_type']) : 'permanent';
        if (!in_array($contractType, $validEmploymentTypes)) {
            $contractType = 'permanent';
        }

        // Validate date format (only if hire_date is provided)
        $hireDate = null;
        if (!empty($employee['hire_date'])) {
            $hireDate = date('Y-m-d', strtotime($employee['hire_date']));
            if (!$hireDate || $hireDate === '1970-01-01') {
                $errors[] = "Row $row: Invalid hire date format";
                $errorCount++;
                continue;
            }
        }

        // Validate salary
        $basicSalary = floatval($employee['basic_salary']);
        if ($basicSalary <= 0) {
            $errors[] = "Row $row: Invalid salary amount";
            $errorCount++;
            continue;
        }

        // Validate email if provided
        if (!empty($employee['email']) && !filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row $row: Invalid email format";
            $errorCount++;
            continue;
        }

        // Validate phone number format if provided
        if (!empty($employee['phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $employee['phone']);
            if (!preg_match('/^\+254[0-9]{9}$/', $phone) && !preg_match('/^0[0-9]{9}$/', $phone)) {
                // Try to format Kenyan number
                if (preg_match('/^[0-9]{9}$/', $phone)) {
                    $employee['phone'] = '+254' . $phone;
                } elseif (preg_match('/^0([0-9]{9})$/', $phone, $matches)) {
                    $employee['phone'] = '+254' . $matches[1];
                } else {
                    $errors[] = "Row $row: Invalid phone number format (use +254XXXXXXXXX or 0XXXXXXXXX)";
                    $errorCount++;
                    continue;
                }
            }
        }

        // Validate KRA PIN format if provided
        if (!empty($employee['kra_pin']) && !preg_match('/^[A-Z]\d{9}[A-Z]$/', $employee['kra_pin'])) {
            $errors[] = "Row $row: Invalid KRA PIN format (should be like P123456789A)";
            $errorCount++;
            continue;
        }

        try {
            $employeeNumber = generateEmployeeNumber($_SESSION['company_id']);

            $stmt = $db->prepare("
                INSERT INTO employees (
                    company_id, employee_number, first_name, middle_name, last_name,
                    id_number, email, phone, hire_date, basic_salary, department_id,
                    position_id, contract_type, kra_pin, nssf_number, nhif_number,
                    bank_code, bank_name, bank_branch, account_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['company_id'],
                $employeeNumber,
                $employee['first_name'],
                $employee['middle_name'],
                $employee['last_name'],
                $employee['id_number'],
                $employee['email'],
                $employee['phone'],
                $hireDate,
                $basicSalary,
                $departmentId,
                $positionId,
                $contractType,
                $employee['kra_pin'],
                $employee['nssf_number'],
                $employee['nhif_number'],
                $employee['bank_code'],
                $employee['bank_name'],
                $employee['bank_branch'],
                $employee['account_number']
            ]);

            $successCount++;

        } catch (Exception $e) {
            $errors[] = "Row $row: Database error - " . $e->getMessage();
            $errorCount++;
        }
    }

    fclose($handle);
    unlink($filePath); // Clean up uploaded file

    // Log the import activity
    logActivity('bulk_import', "Imported $successCount employees, $errorCount errors");

    $message = "Import completed: $successCount employees imported successfully";
    if ($errorCount > 0) {
        $message .= ", $errorCount errors occurred";
        if (count($errors) <= 10) {
            $message .= ":\n" . implode("\n", $errors);
        } else {
            $message .= ". First 10 errors:\n" . implode("\n", array_slice($errors, 0, 10));
        }
    }

    return [
        'message' => $message,
        'type' => $successCount > 0 ? 'success' : 'danger'
    ];
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-users"></i> Employee Management</h2>
                <?php if ($action === 'list'): ?>
                    <div class="btn-group">
                        <a href="index.php?page=employees&action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Employee
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                            <i class="fas fa-upload"></i> Bulk Import
                        </button>
                        <button type="button" class="btn btn-info" onclick="downloadTemplate()">
                            <i class="fas fa-download"></i> CSV Template
                        </button>
                    </div>
                <?php elseif ($action === 'bulk_import'): ?>
                    <a href="index.php?page=employees" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
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
                                                    <small class="text-muted"><?php echo htmlspecialchars($emp['email'] ?? ''); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['id_number'] ?? ''); ?></td>
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
                                <label for="middle_name" class="form-label">Middle Name <small class="text-muted">(optional)</small></label>
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
                                <label for="id_number" class="form-label">ID Number <small class="text-muted">(optional)</small></label>
                                <input type="text" class="form-control" id="id_number" name="id_number"
                                       value="<?php echo htmlspecialchars($employee['id_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <small class="text-muted">(optional)</small></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number <small class="text-muted">(optional)</small></label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Hire Date <small class="text-muted">(optional)</small></label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date"
                                       value="<?php echo $employee['hire_date'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department <small class="text-muted">(optional)</small></label>
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
                                <label for="position_id" class="form-label">Position <small class="text-muted">(optional)</small></label>
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
                                <label for="contract_type" class="form-label">Employment Type <small class="text-muted">(optional)</small></label>
                                <select class="form-select" id="contract_type" name="contract_type">
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

                    <!-- Banking Information Section -->
                    <div class="card mt-4">
                        <div class="card-header" style="background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green)); color: white;">
                            <h6 class="mb-0"><i class="fas fa-university"></i> Banking Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="bank_code" class="form-label">Bank <small class="text-muted">(optional)</small></label>
                                        <select class="form-select" id="bank_code" name="bank_code" onchange="updateBankName()">
                                            <option value="">Select Bank</option>
                                            <option value="01" <?php echo ($employee['bank_code'] ?? '') === '01' ? 'selected' : ''; ?>>Kenya Commercial Bank (KCB)</option>
                                            <option value="02" <?php echo ($employee['bank_code'] ?? '') === '02' ? 'selected' : ''; ?>>Standard Chartered Bank</option>
                                            <option value="03" <?php echo ($employee['bank_code'] ?? '') === '03' ? 'selected' : ''; ?>>Barclays Bank of Kenya</option>
                                            <option value="04" <?php echo ($employee['bank_code'] ?? '') === '04' ? 'selected' : ''; ?>>Bank of Baroda</option>
                                            <option value="05" <?php echo ($employee['bank_code'] ?? '') === '05' ? 'selected' : ''; ?>>Bank of India</option>
                                            <option value="06" <?php echo ($employee['bank_code'] ?? '') === '06' ? 'selected' : ''; ?>>Bank of Africa Kenya</option>
                                            <option value="07" <?php echo ($employee['bank_code'] ?? '') === '07' ? 'selected' : ''; ?>>Prime Bank</option>
                                            <option value="08" <?php echo ($employee['bank_code'] ?? '') === '08' ? 'selected' : ''; ?>>Imperial Bank</option>
                                            <option value="09" <?php echo ($employee['bank_code'] ?? '') === '09' ? 'selected' : ''; ?>>Citibank</option>
                                            <option value="10" <?php echo ($employee['bank_code'] ?? '') === '10' ? 'selected' : ''; ?>>Habib Bank AG Zurich</option>
                                            <option value="11" <?php echo ($employee['bank_code'] ?? '') === '11' ? 'selected' : ''; ?>>Equity Bank</option>
                                            <option value="12" <?php echo ($employee['bank_code'] ?? '') === '12' ? 'selected' : ''; ?>>Cooperative Bank of Kenya</option>
                                            <option value="13" <?php echo ($employee['bank_code'] ?? '') === '13' ? 'selected' : ''; ?>>National Bank of Kenya</option>
                                            <option value="14" <?php echo ($employee['bank_code'] ?? '') === '14' ? 'selected' : ''; ?>>Diamond Trust Bank</option>
                                            <option value="15" <?php echo ($employee['bank_code'] ?? '') === '15' ? 'selected' : ''; ?>>Consolidated Bank of Kenya</option>
                                            <option value="16" <?php echo ($employee['bank_code'] ?? '') === '16' ? 'selected' : ''; ?>>Credit Bank</option>
                                            <option value="17" <?php echo ($employee['bank_code'] ?? '') === '17' ? 'selected' : ''; ?>>African Banking Corporation</option>
                                            <option value="18" <?php echo ($employee['bank_code'] ?? '') === '18' ? 'selected' : ''; ?>>Trans National Bank</option>
                                            <option value="19" <?php echo ($employee['bank_code'] ?? '') === '19' ? 'selected' : ''; ?>>CFC Stanbic Bank</option>
                                            <option value="20" <?php echo ($employee['bank_code'] ?? '') === '20' ? 'selected' : ''; ?>>I&M Bank</option>
                                            <option value="21" <?php echo ($employee['bank_code'] ?? '') === '21' ? 'selected' : ''; ?>>Fidelity Commercial Bank</option>
                                            <option value="22" <?php echo ($employee['bank_code'] ?? '') === '22' ? 'selected' : ''; ?>>Dubai Bank Kenya</option>
                                            <option value="23" <?php echo ($employee['bank_code'] ?? '') === '23' ? 'selected' : ''; ?>>Guaranty Trust Bank</option>
                                            <option value="24" <?php echo ($employee['bank_code'] ?? '') === '24' ? 'selected' : ''; ?>>Family Bank</option>
                                            <option value="25" <?php echo ($employee['bank_code'] ?? '') === '25' ? 'selected' : ''; ?>>Giro Commercial Bank</option>
                                            <option value="26" <?php echo ($employee['bank_code'] ?? '') === '26' ? 'selected' : ''; ?>>Guardian Bank</option>
                                            <option value="27" <?php echo ($employee['bank_code'] ?? '') === '27' ? 'selected' : ''; ?>>Gulf African Bank</option>
                                            <option value="28" <?php echo ($employee['bank_code'] ?? '') === '28' ? 'selected' : ''; ?>>Victoria Commercial Bank</option>
                                            <option value="29" <?php echo ($employee['bank_code'] ?? '') === '29' ? 'selected' : ''; ?>>Chase Bank Kenya</option>
                                            <option value="30" <?php echo ($employee['bank_code'] ?? '') === '30' ? 'selected' : ''; ?>>Middle East Bank Kenya</option>
                                            <option value="31" <?php echo ($employee['bank_code'] ?? '') === '31' ? 'selected' : ''; ?>>Paramount Universal Bank</option>
                                            <option value="32" <?php echo ($employee['bank_code'] ?? '') === '32' ? 'selected' : ''; ?>>Jamii Bora Bank</option>
                                            <option value="33" <?php echo ($employee['bank_code'] ?? '') === '33' ? 'selected' : ''; ?>>Development Bank of Kenya</option>
                                            <option value="34" <?php echo ($employee['bank_code'] ?? '') === '34' ? 'selected' : ''; ?>>Housing Finance Company of Kenya</option>
                                            <option value="35" <?php echo ($employee['bank_code'] ?? '') === '35' ? 'selected' : ''; ?>>NIC Bank</option>
                                            <option value="36" <?php echo ($employee['bank_code'] ?? '') === '36' ? 'selected' : ''; ?>>Commercial Bank of Africa</option>
                                            <option value="37" <?php echo ($employee['bank_code'] ?? '') === '37' ? 'selected' : ''; ?>>Sidian Bank</option>
                                            <option value="38" <?php echo ($employee['bank_code'] ?? '') === '38' ? 'selected' : ''; ?>>UBA Kenya Bank</option>
                                            <option value="39" <?php echo ($employee['bank_code'] ?? '') === '39' ? 'selected' : ''; ?>>Ecobank Kenya</option>
                                            <option value="40" <?php echo ($employee['bank_code'] ?? '') === '40' ? 'selected' : ''; ?>>Spire Bank</option>
                                            <option value="41" <?php echo ($employee['bank_code'] ?? '') === '41' ? 'selected' : ''; ?>>Mayfair Bank</option>
                                            <option value="42" <?php echo ($employee['bank_code'] ?? '') === '42' ? 'selected' : ''; ?>>Access Bank Kenya</option>
                                            <option value="43" <?php echo ($employee['bank_code'] ?? '') === '43' ? 'selected' : ''; ?>>Kingdom Bank</option>
                                            <option value="44" <?php echo ($employee['bank_code'] ?? '') === '44' ? 'selected' : ''; ?>>DIB Bank Kenya</option>
                                            <option value="45" <?php echo ($employee['bank_code'] ?? '') === '45' ? 'selected' : ''; ?>>NCBA Bank Kenya</option>
                                            <option value="46" <?php echo ($employee['bank_code'] ?? '') === '46' ? 'selected' : ''; ?>>Absa Bank Kenya</option>
                                            <option value="47" <?php echo ($employee['bank_code'] ?? '') === '47' ? 'selected' : ''; ?>>HFC Limited</option>
                                            <option value="48" <?php echo ($employee['bank_code'] ?? '') === '48' ? 'selected' : ''; ?>>SBM Bank Kenya</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="bank_name" class="form-label">Bank Name <small class="text-muted">(optional)</small></label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name"
                                               value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>" readonly>
                                        <small class="form-text text-muted">Auto-populated when bank is selected</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="account_number" class="form-label">Account Number <small class="text-muted">(optional)</small></label>
                                        <input type="text" class="form-control" id="account_number" name="account_number"
                                               value="<?php echo htmlspecialchars($employee['account_number'] ?? ''); ?>"
                                               placeholder="Enter account number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bank_branch" class="form-label">Bank Branch <small class="text-muted">(optional)</small></label>
                                        <input type="text" class="form-control" id="bank_branch" name="bank_branch"
                                               value="<?php echo htmlspecialchars($employee['bank_branch'] ?? ''); ?>"
                                               placeholder="e.g., Nairobi Branch, Mombasa Road">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <small>
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Banking Information:</strong> This information will be used for salary payments and official records.
                                            Ensure all details are accurate and match your bank account.
                                        </small>
                                    </div>
                                </div>
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
    <?php elseif ($action === 'view'): ?>
        <!-- View Employee Details -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-eye"></i> Employee Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Employee Number:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['employee_number']); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Full Name:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . $employee['last_name']); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>ID Number:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['id_number'] ?? 'Not provided'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Email:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['email'] ?? 'Not provided'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Phone:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['phone'] ?? 'Not provided'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Hire Date:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo $employee['hire_date'] ? date('F j, Y', strtotime($employee['hire_date'])) : 'Not provided'; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Department:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['department_name'] ?? 'Not assigned'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Position:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo htmlspecialchars($employee['position_title'] ?? 'Not assigned'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Employment Type:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo ucfirst($employee['contract_type'] ?? 'Not specified'); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Basic Salary:</strong>
                            </div>
                            <div class="col-md-8">
                                <?php echo formatCurrency($employee['basic_salary']); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Employment Status:</strong>
                            </div>
                            <div class="col-md-8">
                                <span class="badge status-<?php echo $employee['employment_status']; ?>">
                                    <?php echo ucfirst($employee['employment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-university"></i> Banking Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Bank:</strong><br>
                                <?php echo htmlspecialchars($employee['bank_name'] ?? 'Not provided'); ?></p>

                                <p><strong>Branch:</strong><br>
                                <?php echo htmlspecialchars($employee['bank_branch'] ?? 'Not provided'); ?></p>

                                <p><strong>Account Number:</strong><br>
                                <?php echo htmlspecialchars($employee['account_number'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="index.php?page=employees" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="index.php?page=employees&action=edit&id=<?php echo $employee['id']; ?>" class="btn btn-primary me-2">
                        <i class="fas fa-edit"></i> Edit Employee
                    </a>
                    <a href="index.php?page=payslips&employee_id=<?php echo $employee['id']; ?>" class="btn btn-success">
                        <i class="fas fa-file-invoice-dollar"></i> View Payslips
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green)); color: white;">
                <h5 class="modal-title" id="bulkImportModalLabel">
                    <i class="fas fa-upload"></i> Bulk Import Employees
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Import Instructions</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>📋 Required Fields:</h6>
                            <ul class="small">
                                <li><strong>first_name</strong> - Employee's first name (required)</li>
                                <li><strong>last_name</strong> - Employee's last name (required)</li>
                                <li><strong>basic_salary</strong> - Monthly salary amount (required)</li>
                                <li><strong>id_number</strong> - National ID number (optional, must be unique if provided)</li>
                                <li><strong>hire_date</strong> - Date format: YYYY-MM-DD (optional)</li>
                                <li><strong>employment_type</strong> - permanent, contract, casual, intern (optional, defaults to permanent)</li>
                                <li><strong>email</strong> - Employee email address (optional)</li>
                                <li><strong>phone</strong> - Phone number in +254XXXXXXXXX format (optional)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>📝 Important Notes:</h6>
                            <ul class="small">
                                <li>Department and position names must match existing records</li>
                                <li>Employment types: permanent, contract, casual, intern</li>
                                <li>Phone format: +254XXXXXXXXX (Kenyan format)</li>
                                <li>KRA PIN format: P123456789A</li>
                                <li>Duplicate ID numbers will be skipped</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form method="POST" action="index.php?page=employees&action=bulk_import" enctype="multipart/form-data" id="bulkImportForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Maximum file size: 5MB. Only CSV files are allowed.</div>
                    </div>

                    <div class="mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">CSV Template Structure</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>first_name*</th>
                                                <th>middle_name</th>
                                                <th>last_name*</th>
                                                <th>id_number*</th>
                                                <th>email</th>
                                                <th>phone</th>
                                                <th>hire_date*</th>
                                                <th>basic_salary*</th>
                                                <th>department_name</th>
                                                <th>position_title</th>
                                                <th>contract_type</th>
                                                <th>kra_pin</th>
                                                <th>nssf_number</th>
                                                <th>nhif_number</th>
                                                <th>bank_code</th>
                                                <th>bank_name</th>
                                                <th>bank_branch</th>
                                                <th>account_number</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="text-muted">
                                                <td>John</td>
                                                <td>Doe</td>
                                                <td>Smith</td>
                                                <td>12345678</td>
                                                <td>john@email.com</td>
                                                <td>+254700000000</td>
                                                <td>2024-01-15</td>
                                                <td>50000</td>
                                                <td>IT</td>
                                                <td>Developer</td>
                                                <td>permanent</td>
                                                <td>A123456789B</td>
                                                <td>123456</td>
                                                <td>654321</td>
                                                <td>11</td>
                                                <td>Equity Bank</td>
                                                <td>Nairobi Branch</td>
                                                <td>1234567890</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">* Required fields</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-info" onclick="downloadTemplate()">
                            <i class="fas fa-download"></i> Download Template
                        </button>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload"></i> Import Employees
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Kenyan Bank Codes and Names mapping
const kenyanBanks = {
    '01': 'Kenya Commercial Bank (KCB)',
    '02': 'Standard Chartered Bank',
    '03': 'Barclays Bank of Kenya',
    '04': 'Bank of Baroda',
    '05': 'Bank of India',
    '06': 'Bank of Africa Kenya',
    '07': 'Prime Bank',
    '08': 'Imperial Bank',
    '09': 'Citibank',
    '10': 'Habib Bank AG Zurich',
    '11': 'Equity Bank',
    '12': 'Cooperative Bank of Kenya',
    '13': 'National Bank of Kenya',
    '14': 'Diamond Trust Bank',
    '15': 'Consolidated Bank of Kenya',
    '16': 'Credit Bank',
    '17': 'African Banking Corporation',
    '18': 'Trans National Bank',
    '19': 'CFC Stanbic Bank',
    '20': 'I&M Bank',
    '21': 'Fidelity Commercial Bank',
    '22': 'Dubai Bank Kenya',
    '23': 'Guaranty Trust Bank',
    '24': 'Family Bank',
    '25': 'Giro Commercial Bank',
    '26': 'Guardian Bank',
    '27': 'Gulf African Bank',
    '28': 'Victoria Commercial Bank',
    '29': 'Chase Bank Kenya',
    '30': 'Middle East Bank Kenya',
    '31': 'Paramount Universal Bank',
    '32': 'Jamii Bora Bank',
    '33': 'Development Bank of Kenya',
    '34': 'Housing Finance Company of Kenya',
    '35': 'NIC Bank',
    '36': 'Commercial Bank of Africa',
    '37': 'Sidian Bank',
    '38': 'UBA Kenya Bank',
    '39': 'Ecobank Kenya',
    '40': 'Spire Bank',
    '41': 'Mayfair Bank',
    '42': 'Access Bank Kenya',
    '43': 'Kingdom Bank',
    '44': 'DIB Bank Kenya',
    '45': 'NCBA Bank Kenya',
    '46': 'Absa Bank Kenya',
    '47': 'HFC Limited',
    '48': 'SBM Bank Kenya'
};

// Update bank name when bank code is selected
function updateBankName() {
    const bankCodeSelect = document.getElementById('bank_code');
    const bankNameInput = document.getElementById('bank_name');

    if (bankCodeSelect && bankNameInput) {
        const selectedCode = bankCodeSelect.value;
        if (selectedCode && kenyanBanks[selectedCode]) {
            bankNameInput.value = kenyanBanks[selectedCode];
        } else {
            bankNameInput.value = '';
        }
    }
}

// Initialize bank name on page load if bank code is already selected
document.addEventListener('DOMContentLoaded', function() {
    updateBankName();
});

// Download CSV template
function downloadTemplate() {
    window.open('download_template.php?type=employees', '_blank');
}

// File validation
document.getElementById('csv_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            this.value = '';
            return;
        }

        // Check file type
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a CSV file');
            this.value = '';
            return;
        }
    }
});

// Form submission with loading state
document.getElementById('bulkImportForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

    // Re-enable button after 30 seconds as fallback
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 30000);
});

// Search functionality for employee list
<?php if ($action === 'list'): ?>
document.querySelector('.search-input').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
<?php endif; ?>

// Currency formatting
document.querySelectorAll('.currency-input').forEach(input => {
    input.addEventListener('blur', function() {
        const value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
});

// Account number validation
document.getElementById('account_number')?.addEventListener('input', function() {
    // Remove non-numeric characters
    this.value = this.value.replace(/[^0-9]/g, '');

    // Limit to 20 characters (typical max for Kenyan banks)
    if (this.value.length > 20) {
        this.value = this.value.substring(0, 20);
    }
});

// Bank code validation and formatting
document.getElementById('bank_code')?.addEventListener('change', function() {
    const accountField = document.getElementById('account_number');
    const selectedBank = this.options[this.selectedIndex].text;

    // Clear account number when bank changes
    if (accountField && accountField.value) {
        if (confirm('Changing the bank will clear the account number. Continue?')) {
            accountField.value = '';
        } else {
            // Revert to previous selection
            this.selectedIndex = 0;
            updateBankName();
        }
    }
});
</script>

<style>
:root {
    --kenya-green: #006b3f;
    --kenya-dark-green: #004d2e;
    --kenya-red: #ce1126;
    --kenya-black: #000000;
    --kenya-white: #ffffff;
}

.btn-group .btn {
    margin-right: 0;
}

.table th {
    background-color: var(--kenya-green);
    color: white;
    border-color: var(--kenya-dark-green);
}

.table-striped > tbody > tr:nth-of-type(odd) > td {
    background-color: rgba(0, 107, 63, 0.05);
}

.modal-header {
    border-bottom: 3px solid var(--kenya-red);
}

.alert-info {
    border-left: 4px solid var(--kenya-green);
}

.card-header {
    background-color: rgba(0, 107, 63, 0.1);
    border-bottom: 2px solid var(--kenya-green);
}

.btn-success {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
}

.btn-success:hover {
    background: linear-gradient(135deg, var(--kenya-dark-green), var(--kenya-green));
    transform: translateY(-1px);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    border: none;
}

.search-input {
    border: 2px solid var(--kenya-green);
}

.search-input:focus {
    border-color: var(--kenya-dark-green);
    box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
}
</style>
