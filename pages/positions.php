<?php
/**
 * Job Positions Management
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
            $result = savePosition($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'delete':
            $result = deletePosition($_POST['position_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

/**
 * Save position (add or edit)
 */
function savePosition($data, $action) {
    global $db;
    
    try {
        $title = trim($data['title']);
        $description = trim($data['description'] ?? '');
        $minSalary = !empty($data['min_salary']) ? floatval($data['min_salary']) : null;
        $maxSalary = !empty($data['max_salary']) ? floatval($data['max_salary']) : null;
        $departmentId = !empty($data['department_id']) ? $data['department_id'] : null;
        
        if (empty($title)) {
            return ['message' => 'Position title is required.', 'type' => 'danger'];
        }
        
        if ($minSalary && $maxSalary && $minSalary > $maxSalary) {
            return ['message' => 'Minimum salary cannot be greater than maximum salary.', 'type' => 'danger'];
        }
        
        if ($action === 'add') {
            // Check if position already exists
            $stmt = $db->prepare("SELECT id FROM job_positions WHERE title = ? AND company_id = ?");
            $stmt->execute([$title, $_SESSION['company_id']]);
            if ($stmt->fetch()) {
                return ['message' => 'Position with this title already exists.', 'type' => 'danger'];
            }
            
            // Insert new position
            $stmt = $db->prepare("
                INSERT INTO job_positions (company_id, title, description, min_salary, max_salary, department_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['company_id'], $title, $description, $minSalary, $maxSalary, $departmentId]);
            
            return ['message' => 'Position added successfully!', 'type' => 'success'];
        } else {
            // Update existing position
            $positionId = $data['position_id'];
            
            $stmt = $db->prepare("
                UPDATE job_positions 
                SET title = ?, description = ?, min_salary = ?, max_salary = ?, department_id = ?, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$title, $description, $minSalary, $maxSalary, $departmentId, $positionId, $_SESSION['company_id']]);
            
            return ['message' => 'Position updated successfully!', 'type' => 'success'];
        }
        
    } catch (Exception $e) {
        return ['message' => 'Error saving position: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete position
 */
function deletePosition($positionId) {
    global $db;
    
    try {
        // Check if position has employees
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE position_id = ?");
        $stmt->execute([$positionId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['message' => 'Cannot delete position with active employees. Please reassign employees first.', 'type' => 'danger'];
        }
        
        // Delete position
        $stmt = $db->prepare("DELETE FROM job_positions WHERE id = ? AND company_id = ?");
        $stmt->execute([$positionId, $_SESSION['company_id']]);
        
        return ['message' => 'Position deleted successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting position: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get positions list with employee count and department info
$stmt = $db->prepare("
    SELECT p.*, 
           d.name as department_name,
           COUNT(e.id) as employee_count,
           AVG(e.basic_salary) as avg_salary
    FROM job_positions p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN employees e ON p.id = e.position_id AND e.employment_status = 'active'
    WHERE p.company_id = ?
    GROUP BY p.id
    ORDER BY d.name, p.title
");
$stmt->execute([$_SESSION['company_id']]);
$positions = $stmt->fetchAll();

// Get departments for dropdown
$stmt = $db->prepare("SELECT id, name FROM departments WHERE company_id = ? ORDER BY name");
$stmt->execute([$_SESSION['company_id']]);
$departments = $stmt->fetchAll();

// Get position for editing
$position = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM job_positions WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['company_id']]);
    $position = $stmt->fetch();
    
    if (!$position) {
        header('Location: index.php?page=positions');
        exit;
    }
}
?>

<!-- Positions Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.pos-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.pos-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.pos-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.position-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--kenya-red);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.position-item:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.btn-add-pos {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-add-pos:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(206,17,38,0.3);
    color: white;
}

.salary-range {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
}

.employee-count {
    background: rgba(206,17,38,0.1);
    color: var(--kenya-red);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.dept-badge {
    background: var(--kenya-green);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid">
    <!-- Positions Hero Section -->
    <div class="pos-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-briefcase me-3"></i>
                        Job Positions Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        💼 Define job roles, salary ranges, and organizational hierarchy
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php?page=positions&action=add" class="btn btn-light btn-lg">
                        <i class="fas fa-plus me-2"></i>Add Position
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
        <!-- Add/Edit Position Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="pos-card">
                    <div class="p-4">
                        <h4 class="mb-4">
                            <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> text-primary me-2"></i>
                            <?php echo $action === 'add' ? 'Add New Position' : 'Edit Position'; ?>
                        </h4>
                        
                        <form method="POST">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Position Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($position['title'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department_id" class="form-label">Department</label>
                                        <select class="form-select" id="department_id" name="department_id">
                                            <option value="">Select department (optional)</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo ($position['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_salary" class="form-label">Minimum Salary (KES)</label>
                                        <input type="number" class="form-control" id="min_salary" name="min_salary" 
                                               value="<?php echo $position['min_salary'] ?? ''; ?>" step="0.01" min="0">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_salary" class="form-label">Maximum Salary (KES)</label>
                                        <input type="number" class="form-control" id="max_salary" name="max_salary" 
                                               value="<?php echo $position['max_salary'] ?? ''; ?>" step="0.01" min="0">
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Job Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  placeholder="Describe the role, responsibilities, and requirements..."><?php echo htmlspecialchars($position['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="index.php?page=positions" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Positions
                                </a>
                                <button type="submit" class="btn btn-add-pos">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'add' ? 'Add Position' : 'Update Position'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Position Guidelines -->
                <div class="pos-card">
                    <div class="p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Position Guidelines
                        </h5>
                        
                        <div class="mb-3">
                            <h6 class="text-success">✅ Best Practices:</h6>
                            <ul class="small text-muted">
                                <li>Use clear, industry-standard job titles</li>
                                <li>Set realistic salary ranges</li>
                                <li>Include key responsibilities</li>
                                <li>Link positions to departments</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-info">💡 Salary Tips:</h6>
                            <ul class="small text-muted">
                                <li>Research market rates in Kenya</li>
                                <li>Consider experience levels</li>
                                <li>Account for statutory benefits</li>
                                <li>Review ranges annually</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Positions List -->
        <div class="row">
            <div class="col-12">
                <div class="pos-card">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>
                                <i class="fas fa-list text-primary me-2"></i>
                                All Job Positions
                            </h4>
                            <a href="index.php?page=positions&action=add" class="btn btn-add-pos">
                                <i class="fas fa-plus me-2"></i>Add Position
                            </a>
                        </div>
                        
                        <?php if (!empty($positions)): ?>
                            <div class="row">
                                <?php foreach ($positions as $pos): ?>
                                    <div class="col-lg-6 col-xl-4">
                                        <div class="position-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="mb-0 text-danger">
                                                    <i class="fas fa-briefcase me-2"></i>
                                                    <?php echo htmlspecialchars($pos['title']); ?>
                                                </h5>
                                                <span class="employee-count">
                                                    <?php echo $pos['employee_count']; ?> employees
                                                </span>
                                            </div>
                                            
                                            <?php if ($pos['department_name']): ?>
                                                <div class="mb-2">
                                                    <span class="dept-badge">
                                                        <i class="fas fa-building me-1"></i>
                                                        <?php echo htmlspecialchars($pos['department_name']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($pos['min_salary'] || $pos['max_salary']): ?>
                                                <div class="salary-range mb-3">
                                                    <i class="fas fa-money-bill-wave me-2"></i>
                                                    <?php if ($pos['min_salary'] && $pos['max_salary']): ?>
                                                        KES <?php echo number_format($pos['min_salary']); ?> - <?php echo number_format($pos['max_salary']); ?>
                                                    <?php elseif ($pos['min_salary']): ?>
                                                        From KES <?php echo number_format($pos['min_salary']); ?>
                                                    <?php else: ?>
                                                        Up to KES <?php echo number_format($pos['max_salary']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($pos['description']): ?>
                                                <p class="text-muted mb-3 small">
                                                    <?php echo htmlspecialchars(substr($pos['description'], 0, 100)); ?>
                                                    <?php echo strlen($pos['description']) > 100 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($pos['avg_salary']): ?>
                                                <div class="mb-3">
                                                    <small class="text-info">
                                                        <i class="fas fa-chart-line me-1"></i>
                                                        Avg. Salary: KES <?php echo number_format($pos['avg_salary']); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    Created: <?php echo formatDate($pos['created_at']); ?>
                                                </small>
                                                
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?page=positions&action=edit&id=<?php echo $pos['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($pos['employee_count'] == 0): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deletePosition(<?php echo $pos['id']; ?>, '<?php echo htmlspecialchars($pos['title']); ?>')" 
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
                                <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                                <h5>No Job Positions Found</h5>
                                <p class="text-muted">Start defining job roles and salary structures for your organization.</p>
                                <a href="index.php?page=positions&action=add" class="btn btn-add-pos">
                                    <i class="fas fa-plus me-2"></i>Add First Position
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
                <p>Are you sure you want to delete the position "<span id="posTitle"></span>"?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="position_id" id="deletePosId">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete Position</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deletePosition(id, title) {
    document.getElementById('deletePosId').value = id;
    document.getElementById('posTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Salary validation
document.addEventListener('DOMContentLoaded', function() {
    const minSalary = document.getElementById('min_salary');
    const maxSalary = document.getElementById('max_salary');
    
    function validateSalaries() {
        if (minSalary.value && maxSalary.value) {
            if (parseFloat(minSalary.value) > parseFloat(maxSalary.value)) {
                maxSalary.setCustomValidity('Maximum salary must be greater than minimum salary');
            } else {
                maxSalary.setCustomValidity('');
            }
        }
    }
    
    if (minSalary && maxSalary) {
        minSalary.addEventListener('input', validateSalaries);
        maxSalary.addEventListener('input', validateSalaries);
    }
});
</script>
