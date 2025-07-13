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
    if ($action === 'delete') {
        // Handle individual employee deletion
        $employeeId = $_POST['employee_id'] ?? null;
        if ($employeeId) {
            try {
                // Check if employee has payroll records
                $stmt = $db->prepare("SELECT COUNT(*) FROM payroll_records WHERE employee_id = ?");
                $stmt->execute([$employeeId]);
                $payrollCount = $stmt->fetchColumn();

                if ($payrollCount > 0) {
                    // Don't delete, just deactivate
                    $stmt = $db->prepare("UPDATE employees SET employment_status = 'terminated', termination_date = CURDATE() WHERE id = ? AND company_id = ?");
                    $stmt->execute([$employeeId, $_SESSION['company_id']]);
                    $message = 'Employee has been deactivated (has payroll records)';
                    $messageType = 'warning';
                } else {
                    // Safe to delete
                    $stmt = $db->prepare("DELETE FROM employees WHERE id = ? AND company_id = ?");
                    $stmt->execute([$employeeId, $_SESSION['company_id']]);
                    $message = 'Employee deleted successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error deleting employee: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'bulk_delete') {
        // Handle bulk employee deletion
        $employeeIds = $_POST['employee_ids'] ?? [];
        if (!empty($employeeIds)) {
            try {
                $deletedCount = 0;
                $deactivatedCount = 0;

                foreach ($employeeIds as $employeeId) {
                    // Check if employee has payroll records
                    $stmt = $db->prepare("SELECT COUNT(*) FROM payroll_records WHERE employee_id = ?");
                    $stmt->execute([$employeeId]);
                    $payrollCount = $stmt->fetchColumn();

                    if ($payrollCount > 0) {
                        // Don't delete, just deactivate
                        $stmt = $db->prepare("UPDATE employees SET employment_status = 'terminated', termination_date = CURDATE() WHERE id = ? AND company_id = ?");
                        $stmt->execute([$employeeId, $_SESSION['company_id']]);
                        $deactivatedCount++;
                    } else {
                        // Safe to delete
                        $stmt = $db->prepare("DELETE FROM employees WHERE id = ? AND company_id = ?");
                        $stmt->execute([$employeeId, $_SESSION['company_id']]);
                        $deletedCount++;
                    }
                }

                $message = "Bulk operation completed: {$deletedCount} deleted, {$deactivatedCount} deactivated";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error in bulk deletion: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'No employees selected for deletion';
            $messageType = 'warning';
        }
    } elseif ($action === 'bulk_import') {
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
                        <button type="button" class="btn btn-danger" onclick="toggleBulkDelete()" id="bulkDeleteBtn" style="display: none;">
                            <i class="fas fa-trash"></i> Bulk Delete
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
                        <form id="bulkDeleteForm" method="POST" action="index.php?page=employees&action=bulk_delete">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        <label for="selectAll" class="ms-1">Select</label>
                                    </th>
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
                                        <td>
                                            <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" class="employee-checkbox" onchange="updateBulkDeleteButton()">
                                        </td>
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
                                                <button type="button" class="btn btn-outline-danger" title="Delete"
                                                        onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </form>

                        <!-- Bulk Delete Controls -->
                        <div id="bulkDeleteControls" style="display: none;" class="mt-3 p-3 bg-light border rounded">
                            <div class="d-flex justify-content-between align-items-center">
                                <span id="selectedCount">0 employees selected</span>
                                <div>
                                    <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
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
                                        <label for="bank_code" class="form-label">Bank Code <small class="text-muted">(optional)</small></label>
                                        <select class="form-select searchable-select" id="bank_code" name="bank_code" onchange="updateBankName()" data-placeholder="Search by code or bank name...">
                                            <option value="">Select Bank Code</option>
                                            <option value="12053" <?php echo ($employee['bank_code'] ?? '') === '12053' ? 'selected' : ''; ?>>12053 - National Bank</option>
                                            <option value="68058" <?php echo ($employee['bank_code'] ?? '') === '68058' ? 'selected' : ''; ?>>68058 - Equity Bank</option>
                                            <option value="01169" <?php echo ($employee['bank_code'] ?? '') === '01169' ? 'selected' : ''; ?>>01169 - KCB Bank</option>
                                            <option value="11081" <?php echo ($employee['bank_code'] ?? '') === '11081' ? 'selected' : ''; ?>>11081 - Cooperative Bank</option>
                                            <option value="03017" <?php echo ($employee['bank_code'] ?? '') === '03017' ? 'selected' : ''; ?>>03017 - Absa Bank</option>
                                            <option value="74004" <?php echo ($employee['bank_code'] ?? '') === '74004' ? 'selected' : ''; ?>>74004 - Premier Bank</option>
                                            <option value="72006" <?php echo ($employee['bank_code'] ?? '') === '72006' ? 'selected' : ''; ?>>72006 - Gulf African Bank</option>
                                            <option value="02053" <?php echo ($employee['bank_code'] ?? '') === '02053' ? 'selected' : ''; ?>>02053 - Standard Chartered Bank</option>
                                            <option value="04012" <?php echo ($employee['bank_code'] ?? '') === '04012' ? 'selected' : ''; ?>>04012 - Bank of Baroda</option>
                                            <option value="05013" <?php echo ($employee['bank_code'] ?? '') === '05013' ? 'selected' : ''; ?>>05013 - Bank of India</option>
                                            <option value="06014" <?php echo ($employee['bank_code'] ?? '') === '06014' ? 'selected' : ''; ?>>06014 - Bank of Africa Kenya</option>
                                            <option value="07015" <?php echo ($employee['bank_code'] ?? '') === '07015' ? 'selected' : ''; ?>>07015 - Prime Bank</option>
                                            <option value="08058" <?php echo ($employee['bank_code'] ?? '') === '08058' ? 'selected' : ''; ?>>08058 - Imperial Bank</option>
                                            <option value="09169" <?php echo ($employee['bank_code'] ?? '') === '09169' ? 'selected' : ''; ?>>09169 - Citibank</option>
                                            <option value="10081" <?php echo ($employee['bank_code'] ?? '') === '10081' ? 'selected' : ''; ?>>10081 - Habib Bank AG Zurich</option>
                                            <option value="14017" <?php echo ($employee['bank_code'] ?? '') === '14017' ? 'selected' : ''; ?>>14017 - Diamond Trust Bank</option>
                                            <option value="15004" <?php echo ($employee['bank_code'] ?? '') === '15004' ? 'selected' : ''; ?>>15004 - Consolidated Bank of Kenya</option>
                                            <option value="16006" <?php echo ($employee['bank_code'] ?? '') === '16006' ? 'selected' : ''; ?>>16006 - Credit Bank</option>
                                            <option value="17053" <?php echo ($employee['bank_code'] ?? '') === '17053' ? 'selected' : ''; ?>>17053 - African Banking Corporation</option>
                                            <option value="18058" <?php echo ($employee['bank_code'] ?? '') === '18058' ? 'selected' : ''; ?>>18058 - Trans National Bank</option>
                                            <option value="19169" <?php echo ($employee['bank_code'] ?? '') === '19169' ? 'selected' : ''; ?>>19169 - CFC Stanbic Bank</option>
                                            <option value="20081" <?php echo ($employee['bank_code'] ?? '') === '20081' ? 'selected' : ''; ?>>20081 - I&M Bank</option>
                                            <option value="21017" <?php echo ($employee['bank_code'] ?? '') === '21017' ? 'selected' : ''; ?>>21017 - Fidelity Commercial Bank</option>
                                            <option value="22004" <?php echo ($employee['bank_code'] ?? '') === '22004' ? 'selected' : ''; ?>>22004 - Dubai Bank Kenya</option>
                                            <option value="23006" <?php echo ($employee['bank_code'] ?? '') === '23006' ? 'selected' : ''; ?>>23006 - Guaranty Trust Bank</option>
                                            <option value="24053" <?php echo ($employee['bank_code'] ?? '') === '24053' ? 'selected' : ''; ?>>24053 - Family Bank</option>
                                            <option value="25058" <?php echo ($employee['bank_code'] ?? '') === '25058' ? 'selected' : ''; ?>>25058 - Giro Commercial Bank</option>
                                            <option value="26169" <?php echo ($employee['bank_code'] ?? '') === '26169' ? 'selected' : ''; ?>>26169 - Guardian Bank</option>
                                            <option value="28081" <?php echo ($employee['bank_code'] ?? '') === '28081' ? 'selected' : ''; ?>>28081 - Victoria Commercial Bank</option>
                                            <option value="29017" <?php echo ($employee['bank_code'] ?? '') === '29017' ? 'selected' : ''; ?>>29017 - Chase Bank Kenya</option>
                                            <option value="30004" <?php echo ($employee['bank_code'] ?? '') === '30004' ? 'selected' : ''; ?>>30004 - Middle East Bank Kenya</option>
                                            <option value="31006" <?php echo ($employee['bank_code'] ?? '') === '31006' ? 'selected' : ''; ?>>31006 - Paramount Universal Bank</option>
                                            <option value="32053" <?php echo ($employee['bank_code'] ?? '') === '32053' ? 'selected' : ''; ?>>32053 - Jamii Bora Bank</option>
                                            <option value="33058" <?php echo ($employee['bank_code'] ?? '') === '33058' ? 'selected' : ''; ?>>33058 - Development Bank of Kenya</option>
                                            <option value="34169" <?php echo ($employee['bank_code'] ?? '') === '34169' ? 'selected' : ''; ?>>34169 - Housing Finance Company of Kenya</option>
                                            <option value="35081" <?php echo ($employee['bank_code'] ?? '') === '35081' ? 'selected' : ''; ?>>35081 - NIC Bank</option>
                                            <option value="36017" <?php echo ($employee['bank_code'] ?? '') === '36017' ? 'selected' : ''; ?>>36017 - Commercial Bank of Africa</option>
                                            <option value="37004" <?php echo ($employee['bank_code'] ?? '') === '37004' ? 'selected' : ''; ?>>37004 - Sidian Bank</option>
                                            <option value="38006" <?php echo ($employee['bank_code'] ?? '') === '38006' ? 'selected' : ''; ?>>38006 - UBA Kenya Bank</option>
                                            <option value="39053" <?php echo ($employee['bank_code'] ?? '') === '39053' ? 'selected' : ''; ?>>39053 - Ecobank Kenya</option>
                                            <option value="40058" <?php echo ($employee['bank_code'] ?? '') === '40058' ? 'selected' : ''; ?>>40058 - Spire Bank</option>
                                            <option value="41169" <?php echo ($employee['bank_code'] ?? '') === '41169' ? 'selected' : ''; ?>>41169 - Mayfair Bank</option>
                                            <option value="42081" <?php echo ($employee['bank_code'] ?? '') === '42081' ? 'selected' : ''; ?>>42081 - Access Bank Kenya</option>
                                            <option value="43017" <?php echo ($employee['bank_code'] ?? '') === '43017' ? 'selected' : ''; ?>>43017 - Kingdom Bank</option>
                                            <option value="44004" <?php echo ($employee['bank_code'] ?? '') === '44004' ? 'selected' : ''; ?>>44004 - DIB Bank Kenya</option>
                                            <option value="45006" <?php echo ($employee['bank_code'] ?? '') === '45006' ? 'selected' : ''; ?>>45006 - NCBA Bank Kenya</option>
                                            <option value="47053" <?php echo ($employee['bank_code'] ?? '') === '47053' ? 'selected' : ''; ?>>47053 - HFC Limited</option>
                                            <option value="48058" <?php echo ($employee['bank_code'] ?? '') === '48058' ? 'selected' : ''; ?>>48058 - SBM Bank Kenya</option>
                                            <option value="16019" <?php echo ($employee['bank_code'] ?? '') === '16019' ? 'selected' : ''; ?>>16019 - Stanbic Bank</option>
                                            <option value="49020" <?php echo ($employee['bank_code'] ?? '') === '49020' ? 'selected' : ''; ?>>49020 - First Community Bank</option>
                                            <option value="50021" <?php echo ($employee['bank_code'] ?? '') === '50021' ? 'selected' : ''; ?>>50021 - Oriental Commercial Bank</option>
                                            <option value="51022" <?php echo ($employee['bank_code'] ?? '') === '51022' ? 'selected' : ''; ?>>51022 - Equatorial Commercial Bank</option>
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
                                            <strong>Banking Information:</strong> Select the bank code to auto-fill the bank name. This information will be used for salary payments and official records.
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
// Bank code to bank name mapping
const kenyanBanks = {
    // Primary banks with official codes
    '12053': 'National Bank',
    '68058': 'Equity Bank',
    '01169': 'KCB Bank',
    '11081': 'Cooperative Bank',
    '03017': 'Absa Bank',
    '74004': 'Premier Bank',
    '72006': 'Gulf African Bank',

    // Additional banks with numeric codes
    '02053': 'Standard Chartered Bank',
    '04012': 'Bank of Baroda',
    '05013': 'Bank of India',
    '06014': 'Bank of Africa Kenya',
    '07015': 'Prime Bank',
    '08058': 'Imperial Bank',
    '09169': 'Citibank',
    '10081': 'Habib Bank AG Zurich',
    '14017': 'Diamond Trust Bank',
    '15004': 'Consolidated Bank of Kenya',
    '16006': 'Credit Bank',
    '17053': 'African Banking Corporation',
    '18058': 'Trans National Bank',
    '19169': 'CFC Stanbic Bank',
    '20081': 'I&M Bank',
    '21017': 'Fidelity Commercial Bank',
    '22004': 'Dubai Bank Kenya',
    '23006': 'Guaranty Trust Bank',
    '24053': 'Family Bank',
    '25058': 'Giro Commercial Bank',
    '26169': 'Guardian Bank',
    '28081': 'Victoria Commercial Bank',
    '29017': 'Chase Bank Kenya',
    '30004': 'Middle East Bank Kenya',
    '31006': 'Paramount Universal Bank',
    '32053': 'Jamii Bora Bank',
    '33058': 'Development Bank of Kenya',
    '34169': 'Housing Finance Company of Kenya',
    '35081': 'NIC Bank',
    '36017': 'Commercial Bank of Africa',
    '37004': 'Sidian Bank',
    '38006': 'UBA Kenya Bank',
    '39053': 'Ecobank Kenya',
    '40058': 'Spire Bank',
    '41169': 'Mayfair Bank',
    '42081': 'Access Bank Kenya',
    '43017': 'Kingdom Bank',
    '44004': 'DIB Bank Kenya',
    '45006': 'NCBA Bank Kenya',
    '47053': 'HFC Limited',
    '48058': 'SBM Bank Kenya',
    '16019': 'Stanbic Bank',
    '49020': 'First Community Bank',
    '50021': 'Oriental Commercial Bank',
    '51022': 'Equatorial Commercial Bank'
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
    initializeSearchableSelect();
});

// Download CSV template
function downloadTemplate() {
    window.open('download_template.php?type=employees', '_blank');
}

// Searchable Select Implementation
function initializeSearchableSelect() {
    const selectElement = document.getElementById('bank_code');
    if (!selectElement || !selectElement.classList.contains('searchable-select')) return;

    // Create container
    const container = document.createElement('div');
    container.className = 'searchable-select-container';
    selectElement.parentNode.insertBefore(container, selectElement);

    // Create input
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control searchable-select-input';
    input.placeholder = selectElement.dataset.placeholder || 'Search...';
    input.autocomplete = 'off';

    // Create dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'searchable-select-dropdown';

    // Store original options
    const options = Array.from(selectElement.options).map(option => ({
        value: option.value,
        text: option.textContent,
        selected: option.selected
    }));

    // Set initial value if there's a selected option
    const selectedOption = options.find(opt => opt.selected);
    if (selectedOption) {
        input.value = selectedOption.text;
    }

    // Hide original select
    selectElement.style.display = 'none';

    // Add elements to container
    container.appendChild(input);
    container.appendChild(dropdown);
    container.appendChild(selectElement);

    // Filter and display options
    function filterOptions(searchTerm = '') {
        dropdown.innerHTML = '';
        const filteredOptions = options.filter(option => {
            if (!option.value) return false; // Skip empty option
            return option.text.toLowerCase().includes(searchTerm.toLowerCase()) ||
                   option.value.includes(searchTerm);
        });

        filteredOptions.forEach((option, index) => {
            const optionElement = document.createElement('div');
            optionElement.className = 'searchable-select-option';
            optionElement.textContent = option.text;
            optionElement.dataset.value = option.value;

            if (option.selected) {
                optionElement.classList.add('selected');
            }

            optionElement.addEventListener('click', function() {
                selectOption(option);
            });

            dropdown.appendChild(optionElement);
        });

        return filteredOptions;
    }

    // Select an option
    function selectOption(option) {
        input.value = option.text;
        selectElement.value = option.value;

        // Update selected state
        options.forEach(opt => opt.selected = false);
        option.selected = true;

        // Update visual state
        dropdown.querySelectorAll('.searchable-select-option').forEach(el => {
            el.classList.remove('selected');
        });
        dropdown.querySelector(`[data-value="${option.value}"]`)?.classList.add('selected');

        hideDropdown();
        updateBankName(); // Trigger bank name update

        // Trigger change event
        selectElement.dispatchEvent(new Event('change'));
    }

    // Show dropdown
    function showDropdown() {
        filterOptions(input.value);
        dropdown.style.display = 'block';
    }

    // Hide dropdown
    function hideDropdown() {
        dropdown.style.display = 'none';
    }

    // Event listeners
    input.addEventListener('focus', showDropdown);
    input.addEventListener('input', function() {
        filterOptions(this.value);
        showDropdown();
    });

    // Keyboard navigation
    input.addEventListener('keydown', function(e) {
        const visibleOptions = dropdown.querySelectorAll('.searchable-select-option');
        const highlighted = dropdown.querySelector('.highlighted');
        let currentIndex = highlighted ? Array.from(visibleOptions).indexOf(highlighted) : -1;

        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < visibleOptions.length - 1) {
                    if (highlighted) highlighted.classList.remove('highlighted');
                    visibleOptions[currentIndex + 1].classList.add('highlighted');
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    if (highlighted) highlighted.classList.remove('highlighted');
                    visibleOptions[currentIndex - 1].classList.add('highlighted');
                }
                break;

            case 'Enter':
                e.preventDefault();
                if (highlighted) {
                    const option = options.find(opt => opt.value === highlighted.dataset.value);
                    if (option) selectOption(option);
                }
                break;

            case 'Escape':
                hideDropdown();
                break;
        }
    });

    // Click outside to close
    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            hideDropdown();
        }
    });

    // Initial filter
    filterOptions();
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

/* Searchable Select Styles */
.searchable-select-container {
    position: relative;
}

.searchable-select-input {
    width: 100%;
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    cursor: pointer;
}

.searchable-select-input:focus {
    border-color: var(--kenya-green);
    box-shadow: 0 0 0 0.2rem rgba(0, 107, 63, 0.25);
    outline: 0;
}

.searchable-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.searchable-select-option {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
}

.searchable-select-option:hover {
    background-color: #f8f9fa;
}

.searchable-select-option.selected {
    background-color: var(--kenya-green);
    color: white;
}

.searchable-select-option.highlighted {
    background-color: #e9ecef;
}

/* Delete functionality styles */
.employee-checkbox:checked {
    background-color: var(--kenya-red);
    border-color: var(--kenya-red);
}

#bulkDeleteControls {
    border-left: 4px solid var(--kenya-red);
}
</style>

<script>
// Delete functionality
function deleteEmployee(employeeId, employeeName) {
    if (confirm(`Are you sure you want to delete employee "${employeeName}"?\n\nNote: If the employee has payroll records, they will be deactivated instead of deleted.`)) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?page=employees&action=delete';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'employee_id';
        input.value = employeeId;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });

    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteControls = document.getElementById('bulkDeleteControls');
    const selectedCount = document.getElementById('selectedCount');

    if (checkboxes.length > 0) {
        bulkDeleteBtn.style.display = 'inline-block';
        bulkDeleteControls.style.display = 'block';
        selectedCount.textContent = `${checkboxes.length} employee${checkboxes.length > 1 ? 's' : ''} selected`;
    } else {
        bulkDeleteBtn.style.display = 'none';
        bulkDeleteControls.style.display = 'none';
    }

    // Update select all checkbox
    const allCheckboxes = document.querySelectorAll('.employee-checkbox');
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
}

function toggleBulkDelete() {
    const bulkDeleteControls = document.getElementById('bulkDeleteControls');
    bulkDeleteControls.style.display = bulkDeleteControls.style.display === 'none' ? 'block' : 'none';
}

function confirmBulkDelete() {
    const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select employees to delete.');
        return;
    }

    if (confirm(`Are you sure you want to delete ${checkboxes.length} employee${checkboxes.length > 1 ? 's' : ''}?\n\nNote: Employees with payroll records will be deactivated instead of deleted.`)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });

    document.getElementById('selectAll').checked = false;
    updateBulkDeleteButton();
}
</script>
