<?php
/**
 * Cleanup Duplicate Users
 * 
 * This script helps clean up duplicate users that might be causing
 * installation issues, particularly duplicate admin accounts.
 */

session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('Database connection failed. Please check your database configuration.');
}

$message = '';
$messageType = '';

// Handle cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'remove_duplicate_admin') {
        try {
            // Keep only the first admin user, remove duplicates
            $stmt = $db->prepare("
                DELETE u1 FROM users u1
                INNER JOIN users u2 
                WHERE u1.id > u2.id 
                AND u1.username = u2.username 
                AND u1.username = 'admin'
            ");
            $stmt->execute();
            $deletedRows = $stmt->rowCount();
            
            $message = "Removed $deletedRows duplicate admin users.";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error removing duplicates: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($action === 'remove_all_admin') {
        try {
            // Remove all admin users (use with caution!)
            $stmt = $db->prepare("DELETE FROM users WHERE username = 'admin'");
            $stmt->execute();
            $deletedRows = $stmt->rowCount();
            
            $message = "Removed all $deletedRows admin users. You can now create a fresh admin account.";
            $messageType = 'warning';
            
        } catch (Exception $e) {
            $message = 'Error removing admin users: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($action === 'remove_all_users') {
        try {
            // Remove all users (nuclear option!)
            $stmt = $db->prepare("DELETE FROM users");
            $stmt->execute();
            $deletedRows = $stmt->rowCount();
            
            $message = "Removed all $deletedRows users. You can now start fresh.";
            $messageType = 'warning';
            
        } catch (Exception $e) {
            $message = 'Error removing all users: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get current users
$users = [];
try {
    $stmt = $db->query("SELECT id, username, email, first_name, last_name, role, is_active, created_at FROM users ORDER BY username, id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = 'Error fetching users: ' . $e->getMessage();
    $messageType = 'danger';
}

// Find duplicates
$duplicates = [];
$usernames = [];
foreach ($users as $user) {
    if (isset($usernames[$user['username']])) {
        $duplicates[] = $user['username'];
    }
    $usernames[$user['username']][] = $user;
}
$duplicates = array_unique($duplicates);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Duplicate Users - Kenyan Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kenya-green: #006b3f;
            --kenya-red: #ce1126;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--kenya-green), #004d2e);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .btn-primary {
            background: var(--kenya-green);
            border-color: var(--kenya-green);
        }
        
        .duplicate-user {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-users-cog me-3"></i>Cleanup Duplicate Users</h1>
            <p class="mb-0">Remove duplicate users that are causing installation issues</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Duplicate Detection -->
        <?php if (!empty($duplicates)): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Duplicate Users Detected</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Found duplicate usernames:</strong> <?php echo implode(', ', $duplicates); ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_duplicate_admin">
                                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Remove duplicate admin users? This will keep only the first admin account.')">
                                    <i class="fas fa-user-minus me-2"></i>Remove Duplicate Admins
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_all_admin">
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Remove ALL admin users? You will need to create a new admin account.')">
                                    <i class="fas fa-user-times me-2"></i>Remove All Admins
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_all_users">
                                <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Remove ALL users? This cannot be undone!')">
                                    <i class="fas fa-trash me-2"></i>Remove All Users
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Users -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>Current Users (<?php echo count($users); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-user-slash fa-3x mb-3"></i>
                        <p>No users found in the database</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr class="<?php echo in_array($user['username'], $duplicates) ? 'duplicate-user' : ''; ?>">
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            <?php if (in_array($user['username'], $duplicates)): ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-1" title="Duplicate username"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="text-center">
            <a href="install.php?incomplete=1" class="btn btn-primary me-2">
                <i class="fas fa-play me-2"></i>Continue Installation
            </a>
            <a href="installation_status.php" class="btn btn-secondary me-2">
                <i class="fas fa-chart-line me-2"></i>Check Status
            </a>
            <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                <i class="fas fa-sync me-2"></i>Refresh
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
