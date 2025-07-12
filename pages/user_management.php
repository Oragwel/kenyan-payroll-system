<?php
/**
 * User Management System
 * Admin-only page for managing system users
 */

// Security check - Admin only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'add':
        case 'edit':
            $result = saveUser($_POST, $action);
            $message = $result['message'];
            $messageType = $result['type'];
            if ($result['type'] === 'success') {
                $action = 'list'; // Redirect to list after successful save
            }
            break;
        case 'delete':
            $result = deleteUser($_POST['user_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
        case 'toggle_status':
            $result = toggleUserStatus($_POST['user_id']);
            $message = $result['message'];
            $messageType = $result['type'];
            $action = 'list';
            break;
    }
}

/**
 * Save user (add or edit)
 */
function saveUser($data, $action) {
    global $db;
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $role = $data['role'];
    $isActive = isset($data['is_active']) ? 1 : 0;
    $userId = $data['user_id'] ?? null;
    
    // Validation
    if (empty($username) || empty($email) || empty($role)) {
        return ['message' => 'All fields are required', 'type' => 'danger'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['message' => 'Invalid email format', 'type' => 'danger'];
    }
    
    if (!in_array($role, ['admin', 'hr', 'employee', 'accountant'])) {
        return ['message' => 'Invalid role selected', 'type' => 'danger'];
    }
    
    try {
        if ($action === 'add') {
            $password = $data['password'];
            if (empty($password) || strlen($password) < 6) {
                return ['message' => 'Password must be at least 6 characters', 'type' => 'danger'];
            }
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                return ['message' => 'Username or email already exists', 'type' => 'danger'];
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $role, $isActive]);
            
            logActivity('user_add', "Added new user: $username");
            return ['message' => 'User created successfully', 'type' => 'success'];
            
        } else { // edit
            // Check if username or email already exists for other users
            $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $userId]);
            if ($stmt->fetch()) {
                return ['message' => 'Username or email already exists', 'type' => 'danger'];
            }
            
            if (!empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    return ['message' => 'Password must be at least 6 characters', 'type' => 'danger'];
                }
                $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $email, $passwordHash, $role, $isActive, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $isActive, $userId]);
            }
            
            logActivity('user_edit', "Updated user: $username");
            return ['message' => 'User updated successfully', 'type' => 'success'];
        }
    } catch (Exception $e) {
        return ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Delete user
 */
function deleteUser($userId) {
    global $db;
    
    try {
        // Don't allow deleting the current user
        if ($userId == $_SESSION['user_id']) {
            return ['message' => 'Cannot delete your own account', 'type' => 'danger'];
        }
        
        // Get user info for logging
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['message' => 'User not found', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        logActivity('user_delete', "Deleted user: " . $user['username']);
        return ['message' => 'User deleted successfully', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error deleting user: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Toggle user active status
 */
function toggleUserStatus($userId) {
    global $db;
    
    try {
        // Don't allow disabling the current user
        if ($userId == $_SESSION['user_id']) {
            return ['message' => 'Cannot disable your own account', 'type' => 'danger'];
        }
        
        $stmt = $db->prepare("SELECT username, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['message' => 'User not found', 'type' => 'danger'];
        }
        
        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        
        $statusText = $newStatus ? 'activated' : 'deactivated';
        logActivity('user_status_change', "User {$user['username']} $statusText");
        
        return ['message' => "User {$statusText} successfully", 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating user status: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get user data for edit
if ($action === 'edit' && $userId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $editUser = $stmt->fetch();
    
    if (!$editUser) {
        $message = 'User not found';
        $messageType = 'danger';
        $action = 'list';
    }
}

// Get all users for list
if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as activity_count,
               (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_activity
        FROM users u 
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-users-cog"></i> User Management</h2>
                <?php if ($action === 'list'): ?>
                    <a href="index.php?page=user_management&action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                <?php else: ?>
                    <a href="index.php?page=user_management" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> System Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Last Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-info ms-1">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $user['role'] === 'admin' ? 'danger' : 
                                                            ($user['role'] === 'hr' ? 'warning' : 'primary'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at']); ?></td>
                                                <td>
                                                    <?php if ($user['last_activity']): ?>
                                                        <?php echo formatDate($user['last_activity']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="index.php?page=user_management&action=edit&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Toggle user status?')">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" name="action" value="toggle_status" 
                                                                        class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" name="action" value="delete" 
                                                                        class="btn btn-outline-danger" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No Users Found</h5>
                                <p class="text-muted">Start by adding your first user to the system.</p>
                                <a href="index.php?page=user_management&action=add" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First User
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit User Form -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                            <?php echo $action === 'add' ? 'Add New User' : 'Edit User'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                            <?php endif; ?>

                            <div class="col-md-6">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">
                                    Password <?php echo $action === 'add' ? '*' : '(leave blank to keep current)'; ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password"
                                       <?php echo $action === 'add' ? 'required' : ''; ?>>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>

                            <div class="col-md-6">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                        Admin (Full Access)
                                    </option>
                                    <option value="hr" <?php echo ($editUser['role'] ?? '') === 'hr' ? 'selected' : ''; ?>>
                                        HR Manager
                                    </option>
                                    <option value="accountant" <?php echo ($editUser['role'] ?? '') === 'accountant' ? 'selected' : ''; ?>>
                                        Accountant
                                    </option>
                                    <option value="employee" <?php echo ($editUser['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>
                                        Employee
                                    </option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           <?php echo ($editUser['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active User
                                    </label>
                                    <div class="form-text">Inactive users cannot log in to the system</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" name="action" value="<?php echo $action; ?>" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        <?php echo $action === 'add' ? 'Create User' : 'Update User'; ?>
                                    </button>
                                    <a href="index.php?page=user_management" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.badge {
    font-size: 0.75em;
}
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.table th {
    border-top: none;
    font-weight: 600;
    background-color: #f8f9fa;
}
.card-header {
    background-color: #006b3f;
    color: white;
    border-bottom: none;
}
.card-header h5 {
    margin: 0;
    font-weight: 600;
}
</style>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const action = document.querySelector('button[type="submit"]').value;

            if (action === 'add' && password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                password.focus();
                return false;
            }

            if (action === 'edit' && password.value && password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                password.focus();
                return false;
            }
        });
    }
});
</script>
