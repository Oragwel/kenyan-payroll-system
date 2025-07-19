<?php
/**
 * Security Dashboard for Administrators
 */

if (!hasPermission('admin')) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Include database utilities for cross-database compatibility
require_once 'includes/DatabaseUtils.php';
DatabaseUtils::initialize($database);

// Get security statistics
$securityStats = [];

// Recent login attempts
$stmt = $db->prepare("
    SELECT 
        ip_address,
        username,
        success,
        attempt_time,
        user_agent
    FROM login_attempts 
    ORDER BY attempt_time DESC 
    LIMIT 20
");
$stmt->execute();
$recentAttempts = $stmt->fetchAll();

// Failed login attempts in last 24 hours
$twentyFourHoursAgo = DatabaseUtils::hoursAgo(24);
$stmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM login_attempts
    WHERE success = ?
    AND attempt_time > ?
");
$stmt->execute([DatabaseUtils::falseValue(), $twentyFourHoursAgo]);
$securityStats['failed_attempts_24h'] = $stmt->fetch()['count'];

// Successful logins in last 24 hours
$stmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM login_attempts
    WHERE success = ?
    AND attempt_time > ?
");
$stmt->execute([DatabaseUtils::trueValue(), $twentyFourHoursAgo]);
$securityStats['successful_logins_24h'] = $stmt->fetch()['count'];

// Unique IPs in last 24 hours
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT ip_address) as count
    FROM login_attempts
    WHERE attempt_time > ?
");
$stmt->execute([$twentyFourHoursAgo]);
$securityStats['unique_ips_24h'] = $stmt->fetch()['count'];

// Security events
$stmt = $db->prepare("
    SELECT 
        sl.*,
        u.username
    FROM security_logs sl
    LEFT JOIN users u ON sl.user_id = u.id
    ORDER BY sl.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$securityEvents = $stmt->fetchAll();

// Currently active sessions
$stmt = $db->prepare("
    SELECT DISTINCT
        u.username,
        u.role,
        la.ip_address,
        MAX(la.attempt_time) as last_login
    FROM login_attempts la
    JOIN users u ON la.username = u.username
    WHERE la.success = ?
    AND la.attempt_time > ?
    GROUP BY u.username, u.role, la.ip_address
    ORDER BY last_login DESC
");
$eightHoursAgo = DatabaseUtils::hoursAgo(8);
$stmt->execute([DatabaseUtils::trueValue(), $eightHoursAgo]);
$activeSessions = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shield-alt text-success"></i> Security Dashboard</h2>
                <div class="text-muted">
                    <i class="fas fa-clock"></i> Last updated: <?php echo date('Y-m-d H:i:s'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-success"><?php echo $securityStats['successful_logins_24h']; ?></h4>
                            <p class="mb-0">Successful Logins (24h)</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-sign-in-alt fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-danger"><?php echo $securityStats['failed_attempts_24h']; ?></h4>
                            <p class="mb-0">Failed Attempts (24h)</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-info"><?php echo $securityStats['unique_ips_24h']; ?></h4>
                            <p class="mb-0">Unique IPs (24h)</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-globe fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="text-warning"><?php echo count($activeSessions); ?></h4>
                            <p class="mb-0">Active Sessions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Login Attempts -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Login Attempts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Username</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($attempt['attempt_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td>
                                            <code><?php echo htmlspecialchars($attempt['ip_address']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($attempt['success']): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($attempt['user_agent'], 0, 50)); ?>...
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Sessions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-clock"></i> Active Sessions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($activeSessions)): ?>
                        <?php foreach ($activeSessions as $session): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                <div>
                                    <strong><?php echo htmlspecialchars($session['username']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-user-tag"></i> <?php echo ucfirst($session['role']); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($session['ip_address']); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($session['last_login'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-success">
                                        <i class="fas fa-circle"></i> Online
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No active sessions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Events -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-check"></i> Security Events</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                    <th>Severity</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($securityEvents as $event): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($event['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['username'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_type'] ?? $event['action'] ?? 'Unknown'); ?></td>
                                        <td><code><?php echo htmlspecialchars($event['ip_address']); ?></code></td>
                                        <td>
                                            <?php
                                            $severityClass = [
                                                'low' => 'success',
                                                'medium' => 'warning',
                                                'high' => 'danger',
                                                'critical' => 'dark'
                                            ];
                                            $class = $severityClass[$event['severity']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>">
                                                <?php echo ucfirst($event['severity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($event['description'] ?? $event['details'] ?? 'No details'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-danger {
    border-left: 4px solid #dc3545 !important;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}
</style>
