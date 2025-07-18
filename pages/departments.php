<?php
/**
 * Departments Management
 */

// Security check
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'hr'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
        case 'edit':
            $result = saveDepartment($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'delete':
            $result = deleteDepartment($_POST['department_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

/**
 * Save department (add or edit)
 */
function saveDepartment($data, $action) {
    global $db;
    
    try {
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $managerId = !empty($data['manager_id']) ? $data['manager_id'] : null;
        
        if (empty($name)) {
            return ['message' => 'Department name is required.', 'type' => 'danger'];
        }
        
        if ($action === 'add') {
            // Check if department already exists
            $stmt = $db->prepare("SELECT id FROM departments WHERE name = ? AND company_id = ?");
            $stmt->execute([$name, $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                return ['message' => 'Department with this name already exists.', 'type' => 'danger'];
            }
            
            // Insert new department
            $stmt = $db->prepare("
                INSERT INTO departments (company_id, name, description, manager_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['company_id'], $name, $description, $managerId]);
            
            return ['message' => 'Department added successfully!', 'type' => 'success'];
        } else {
            // Update existing department
            $departmentId = $data['department_id'];
            
            $stmt = $db->prepare("
                UPDATE departments 
                SET name = ?, description = ?, manager_id = ?, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$name, $description, $managerId, $departmentId, $_SESSION['company_id']]);
            
            return ['message' => 'Department updated successfully!', 'type' => 'success'];
        }
        
    } catch (Exception $e) {
        return ['message' => 'Error saving department: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete department
 */
function deleteDepartment($departmentId) {
    global $db;
    
    try {
        // Check if department has employees
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE department_id = ?");
        $stmt->execute([$departmentId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['message' => 'Cannot delete department with active employees. Please reassign employees first.', 'type' => 'danger'];
        }
        
        // Delete department
        $stmt = $db->prepare("DELETE FROM departments WHERE id = ? AND company_id = ?");
        $stmt->execute([$departmentId, $_SESSION['company_id']]);
        
        return ['message' => 'Department deleted successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting department: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get departments list with employee count
$stmt = DatabaseUtils::prepare($db, "
    SELECT d.*,
           CONCAT(e.first_name, ' ', e.last_name) as manager_name,
           COUNT(emp.id) as employee_count
    FROM departments d
    LEFT JOIN employees e ON d.manager_id = e.id
    LEFT JOIN employees emp ON d.id = emp.department_id AND emp.employment_status = 'active'
    WHERE d.company_id = ?
    GROUP BY d.id
    ORDER BY d.name
");
$stmt->execute([$_SESSION['company_id']]);
$departments = $stmt->fetchAll();

// Get employees for manager selection
$nameConcat = DatabaseUtils::concat(['first_name', "' '", 'last_name']);
$stmt = $db->prepare("
    SELECT id, $nameConcat as name, employee_number
    FROM employees
    WHERE company_id = ? AND employment_status = 'active'
    ORDER BY first_name, last_name
");
$stmt->execute([$_SESSION['company_id']]);
$employees = $stmt->fetchAll();

// Get department for editing
$department = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    $department = $stmt->fetch();
    
    if (!$department) {
        header('Location: index.php?page=departments');
        exit;
    }
}
?>

<!-- Departments Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.dept-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.dept-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.dept-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.dept-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--kenya-green);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.dept-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.btn-add-dept {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-add-dept:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,107,63,0.3);
    color: white;
}

.employee-count {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.manager-info {
    background: rgba(206,17,38,0.1);
    color: var(--kenya-red);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid">
    <!-- Departments Hero Section -->
    <div class="dept-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-building me-3"></i>
                        Department Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        🏢 Organize your workforce into departments and manage organizational structure
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php?page=departments&action=add" class="btn btn-light btn-lg">
                        <i class="fas fa-plus me-2"></i>Add Department
                    </a>
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

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Department Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="dept-card">
                    <div class="p-4">
                        <h4 class="mb-4">
                            <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> text-primary me-2"></i>
                            <?php echo $action === 'add' ? 'Add New Department' : 'Edit Department'; ?>
                        </h4>
                        
                        <form method="POST">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Department Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($department['name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="manager_id" class="form-label">Department Manager</label>
                                        <select class="form-select" id="manager_id" name="manager_id">
                                            <option value="">Select manager (optional)</option>
                                            <?php foreach ($employees as $emp): ?>
                                                <option value="<?php echo $emp['id']; ?>" 
                                                        <?php echo ($department['manager_id'] ?? '') == $emp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($emp['name']); ?> 
                                                    (<?php echo htmlspecialchars($emp['employee_number']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="Brief description of the department's role and responsibilities..."><?php echo htmlspecialchars($department['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="index.php?page=departments" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Departments
                                </a>
                                <button type="submit" class="btn btn-add-dept">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'add' ? 'Add Department' : 'Update Department'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Department Guidelines -->
                <div class="dept-card">
                    <div class="p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Department Guidelines
                        </h5>
                        
                        <div class="mb-3">
                            <h6 class="text-success">✅ Best Practices:</h6>
                            <ul class="small text-muted">
                                <li>Use clear, descriptive department names</li>
                                <li>Assign experienced employees as managers</li>
                                <li>Keep department descriptions concise</li>
                                <li>Review department structure regularly</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-info">💡 Tips:</h6>
                            <ul class="small text-muted">
                                <li>Departments help organize payroll reports</li>
                                <li>Managers can approve leave applications</li>
                                <li>Use departments for cost center tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Departments List -->
        <div class="row">
            <div class="col-12">
                <div class="dept-card">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>
                                <i class="fas fa-list text-primary me-2"></i>
                                All Departments
                            </h4>
                            <a href="index.php?page=departments&action=add" class="btn btn-add-dept">
                                <i class="fas fa-plus me-2"></i>Add Department
                            </a>
                        </div>
                        
                        <?php if (!empty($departments)): ?>
                            <div class="row">
                                <?php foreach ($departments as $dept): ?>
                                    <div class="col-lg-6 col-xl-4">
                                        <div class="dept-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="mb-0 text-success">
                                                    <i class="fas fa-building me-2"></i>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </h5>
                                                <span class="employee-count">
                                                    <?php echo $dept['employee_count']; ?> employees
                                                </span>
                                            </div>
                                            
                                            <?php if ($dept['description']): ?>
                                                <p class="text-muted mb-3 small">
                                                    <?php echo htmlspecialchars($dept['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($dept['manager_name']): ?>
                                                <div class="manager-info mb-3">
                                                    <i class="fas fa-user-tie me-2"></i>
                                                    Manager: <?php echo htmlspecialchars($dept['manager_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    Created: <?php echo formatDate($dept['created_at']); ?>
                                                </small>
                                                
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?page=departments&action=edit&id=<?php echo $dept['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($dept['employee_count'] == 0): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-building fa-4x text-muted mb-3"></i>
                                <h5>No Departments Found</h5>
                                <p class="text-muted">Start organizing your workforce by creating departments.</p>
                                <a href="index.php?page=departments&action=add" class="btn btn-add-dept">
                                    <i class="fas fa-plus me-2"></i>Add First Department
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the department "<span id="deptName"></span>"?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="department_id" id="deleteDeptId">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteDepartment(id, name) {
    document.getElementById('deleteDeptId').value = id;
    document.getElementById('deptName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
