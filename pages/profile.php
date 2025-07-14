<?php
/**
 * User Profile Management
 */

$action = $_GET['action'] ?? 'view';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update':
            $result = updateProfile($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'password':
            $result = changePassword($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
            break;
    }
}

/**
 * Update user profile
 */
function updateProfile($data) {
    global $db;
    
    try {
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            return ['message' => 'First name, last name, and email are required.', 'type' => 'danger'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['message' => 'Please enter a valid email address.', 'type' => 'danger'];
        }
        
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            return ['message' => 'Email address is already in use by another user.', 'type' => 'danger'];
        }
        
        // Update user profile
        $stmt = $db->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $email, $phone, $_SESSION['user_id']]);
        
        // Update session data
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        
        return ['message' => 'Profile updated successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error updating profile: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Change user password
 */
function changePassword($data) {
    global $db;
    
    try {
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return ['message' => 'All password fields are required.', 'type' => 'danger'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['message' => 'New password and confirmation do not match.', 'type' => 'danger'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['message' => 'New password must be at least 8 characters long.', 'type' => 'danger'];
        }
        
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['message' => 'Current password is incorrect.', 'type' => 'danger'];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            UPDATE users 
            SET password = ?, password_changed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        return ['message' => 'Password changed successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error changing password: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get current user data
$stmt = $db->prepare("
    SELECT u.*, e.employee_number, e.hire_date, e.basic_salary,
           d.name as department_name, p.title as position_title
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN job_positions p ON e.position_id = p.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php?page=auth&action=logout');
    exit;
}
?>

<!-- Profile Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.profile-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.profile-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    font-weight: bold;
    margin: 0 auto 1rem;
}

.info-card {
    background: var(--kenya-light-green);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--kenya-green);
}

.btn-update {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-update:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,107,63,0.3);
    color: white;
}

.btn-password {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-password:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(206,17,38,0.3);
    color: white;
}

.profile-nav {
    background: white;
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.profile-nav .nav-link {
    color: var(--kenya-dark-green);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}

.profile-nav .nav-link:hover {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
}

.profile-nav .nav-link.active {
    background: var(--kenya-green);
    color: white;
}
</style>

<div class="container-fluid">
    <!-- Profile Hero Section -->
    <div class="profile-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-user me-3"></i>
                        My Profile
                    </h1>
                    <p class="mb-0 opacity-75">
                        👤 Manage your personal information and account settings
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="rounded p-3 text-white" style="background-color: var(--kenya-dark-green);">
                        <h5 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                        <small class="opacity-75"><?php echo ucfirst($user['role']); ?></small>
                    </div>
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

    <!-- Profile Navigation -->
    <div class="profile-nav">
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'view' ? 'active' : ''; ?>" 
                   href="index.php?page=profile&action=view">
                    <i class="fas fa-user me-2"></i>Profile Info
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'update' ? 'active' : ''; ?>" 
                   href="index.php?page=profile&action=update">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $action === 'password' ? 'active' : ''; ?>" 
                   href="index.php?page=profile&action=password">
                    <i class="fas fa-key me-2"></i>Change Password
                </a>
            </li>
        </ul>
    </div>

    <!-- Profile Content -->
    <?php switch ($action): 
        case 'view': ?>
            <!-- Profile View -->
            <div class="row">
                <div class="col-lg-4">
                    <div class="profile-card">
                        <div class="p-4 text-center">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="text-muted mb-3"><?php echo ucfirst($user['role']); ?></p>
                            
                            <?php if ($user['employee_number']): ?>
                                <div class="info-card">
                                    <h6 class="text-success mb-2">Employee Information</h6>
                                    <p class="mb-1"><strong>Employee #:</strong> <?php echo htmlspecialchars($user['employee_number']); ?></p>
                                    <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></p>
                                    <p class="mb-0"><strong>Position:</strong> <?php echo htmlspecialchars($user['position_title'] ?? 'N/A'); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2">
                                <a href="index.php?page=profile&action=update" class="btn btn-update">
                                    <i class="fas fa-edit me-2"></i>Edit Profile
                                </a>
                                <a href="index.php?page=profile&action=password" class="btn btn-password">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="p-4">
                            <h5 class="mb-4">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                Personal Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">First Name</label>
                                        <p class="fw-bold"><?php echo htmlspecialchars($user['first_name']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Last Name</label>
                                        <p class="fw-bold"><?php echo htmlspecialchars($user['last_name']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Email Address</label>
                                        <p class="fw-bold"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Phone Number</label>
                                        <p class="fw-bold"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Account Created</label>
                                        <p class="fw-bold"><?php echo formatDate($user['created_at']); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Last Updated</label>
                                        <p class="fw-bold"><?php echo formatDate($user['updated_at']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($user['hire_date']): ?>
                                <hr>
                                <h6 class="mb-3 text-success">Employment Details</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Hire Date</label>
                                            <p class="fw-bold"><?php echo formatDate($user['hire_date']); ?></p>
                                        </div>
                                    </div>
                                    <?php if ($user['basic_salary'] && $_SESSION['user_role'] !== 'employee'): ?>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Basic Salary</label>
                                                <p class="fw-bold"><?php echo formatCurrency($user['basic_salary']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
        case 'update': ?>
            <!-- Edit Profile Form -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="profile-card">
                        <div class="p-4">
                            <h4 class="mb-4">
                                <i class="fas fa-edit text-primary me-2"></i>
                                Edit Profile Information
                            </h4>

                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone"
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <a href="index.php?page=profile" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-update">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
        case 'password': ?>
            <!-- Change Password Form -->
            <div class="row">
                <div class="col-lg-6 mx-auto">
                    <div class="profile-card">
                        <div class="p-4">
                            <h4 class="mb-4">
                                <i class="fas fa-key text-warning me-2"></i>
                                Change Password
                            </h4>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Password Requirements</h6>
                                <ul class="mb-0 small">
                                    <li>Minimum 8 characters long</li>
                                    <li>Use a combination of letters, numbers, and symbols</li>
                                    <li>Avoid using personal information</li>
                                </ul>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                           minlength="8" required>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                           minlength="8" required>
                                </div>

                                <div class="text-end">
                                    <a href="index.php?page=profile" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-password">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php break;
    endswitch; ?>
</div>

<script>
// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    function validatePasswords() {
        if (newPassword && confirmPassword) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    }

    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
});
</script>
