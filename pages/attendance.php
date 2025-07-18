<?php
/**
 * Attendance Management System
 */

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'clock_in':
            $result = clockIn();
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'clock_out':
            $result = clockOut();
            $message = $result['message'];
            $messageType = $result['type'];
            break;
        case 'add_manual':
            if (hasPermission('hr')) {
                $result = addManualAttendance($_POST);
                $message = $result['message'];
                $messageType = $result['type'];
            }
            break;
    }
}

/**
 * Clock in employee
 */
function clockIn() {
    global $db;
    
    try {
        $today = date('Y-m-d');
        
        // Check if already clocked in today
        $stmt = $db->prepare("
            SELECT id FROM attendance 
            WHERE employee_id = ? AND date = ? AND clock_out IS NULL
        ");
        $stmt->execute([$_SESSION['employee_id'], $today]);
        if ($stmt->fetch()) {
            return ['message' => 'You are already clocked in for today.', 'type' => 'warning'];
        }
        
        // Clock in
        $stmt = $db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, created_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$_SESSION['employee_id'], $today]);
        
        return ['message' => 'Clocked in successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error clocking in: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Clock out employee
 */
function clockOut() {
    global $db;
    
    try {
        $today = date('Y-m-d');
        
        // Find today's attendance record
        $stmt = $db->prepare("
            SELECT id, clock_in FROM attendance 
            WHERE employee_id = ? AND date = ? AND clock_out IS NULL
        ");
        $stmt->execute([$_SESSION['employee_id'], $today]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            return ['message' => 'No clock-in record found for today.', 'type' => 'warning'];
        }
        
        // Calculate hours worked
        $clockIn = new DateTime($attendance['clock_in']);
        $clockOut = new DateTime();
        $hoursWorked = $clockOut->diff($clockIn)->h + ($clockOut->diff($clockIn)->i / 60);
        
        // Clock out
        $stmt = $db->prepare("
            UPDATE attendance 
            SET clock_out = NOW(), hours_worked = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([round($hoursWorked, 2), $attendance['id']]);
        
        return ['message' => 'Clocked out successfully! Hours worked: ' . round($hoursWorked, 2), 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error clocking out: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

/**
 * Add manual attendance (HR only)
 */
function addManualAttendance($data) {
    global $db;
    
    try {
        $employeeId = $data['employee_id'];
        $date = $data['date'];
        $clockIn = $data['clock_in'];
        $clockOut = $data['clock_out'] ?? null;
        
        // Calculate hours if clock out is provided
        $hoursWorked = null;
        if ($clockOut) {
            $start = new DateTime($date . ' ' . $clockIn);
            $end = new DateTime($date . ' ' . $clockOut);
            $hoursWorked = $end->diff($start)->h + ($end->diff($start)->i / 60);
        }
        
        // Insert attendance record
        $stmt = $db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, clock_out, hours_worked, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$employeeId, $date, $date . ' ' . $clockIn, 
                       $clockOut ? $date . ' ' . $clockOut : null, $hoursWorked]);
        
        return ['message' => 'Manual attendance record added successfully!', 'type' => 'success'];
        
    } catch (Exception $e) {
        return ['message' => 'Error adding attendance: ' . $e->getMessage(), 'type' => 'danger'];
    }
}

// Get today's attendance status for current user
$todayAttendance = null;
if (isset($_SESSION['employee_id'])) {
    $stmt = $db->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND date = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$_SESSION['employee_id'], date('Y-m-d')]);
    $todayAttendance = $stmt->fetch();
}

// Get attendance records based on user role
if (hasPermission('hr')) {
    // HR can see all attendance
    $employeeNameConcat = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);
    $stmt = $db->prepare("
        SELECT a.*,
               $employeeNameConcat as employee_name,
               e.employee_number,
               d.name as department_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        ORDER BY a.date DESC, e.employee_number
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['company_id']]);
} else {
    // Employees see only their attendance
    $employeeNameConcat2 = DatabaseUtils::concat(['e.first_name', "' '", 'e.last_name']);
    $stmt = $db->prepare("
        SELECT a.*,
               $employeeNameConcat2 as employee_name,
               e.employee_number
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        WHERE a.employee_id = ?
        ORDER BY a.date DESC
        LIMIT 30
    ");
    $stmt->execute([$_SESSION['employee_id']]);
}
$attendanceRecords = $stmt->fetchAll();

// Get employees for manual entry (HR only)
$employees = [];
if (hasPermission('hr')) {
    $employeeNameConcat3 = DatabaseUtils::concat(['first_name', "' '", 'last_name']);
    $stmt = $db->prepare("
        SELECT id, $employeeNameConcat3 as name, employee_number
        FROM employees
        WHERE company_id = ? AND employment_status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$_SESSION['company_id']]);
    $employees = $stmt->fetchAll();
}

// Create attendance table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            clock_in DATETIME,
            clock_out DATETIME,
            hours_worked DECIMAL(4,2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_employee_date (employee_id, date)
        )
    ");
} catch (Exception $e) {
    // Table creation failed, but continue
}
?>

<!-- Attendance Styles -->
<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.attendance-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.attendance-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.attendance-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.clock-widget {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    color: white;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.clock-time {
    font-size: 3rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.clock-date {
    font-size: 1.25rem;
    opacity: 0.8;
    margin-bottom: 1.5rem;
}

.btn-clock-in {
    background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
    border: none;
    color: white;
    padding: 1rem 2rem;
    border-radius: 15px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.btn-clock-in:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,107,63,0.4);
    color: white;
}

.btn-clock-out {
    background: linear-gradient(135deg, var(--kenya-red), #a00e1f);
    border: none;
    color: white;
    padding: 1rem 2rem;
    border-radius: 15px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.btn-clock-out:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(206,17,38,0.4);
    color: white;
}

.status-present { color: var(--kenya-green); }
.status-absent { color: var(--kenya-red); }
.status-partial { color: #f59e0b; }

.time-display {
    background: var(--kenya-light-green);
    color: var(--kenya-dark-green);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-family: monospace;
}
</style>

<div class="container-fluid">
    <!-- Attendance Hero Section -->
    <div class="attendance-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-clock me-3"></i>
                        Attendance Management
                    </h1>
                    <p class="mb-0 opacity-75">
                        🕐 Track working hours and manage attendance records
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h5 class="mb-1" id="currentTime"><?php echo date('H:i:s'); ?></h5>
                        <small class="opacity-75"><?php echo date('l, F j, Y'); ?></small>
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

    <div class="row">
        <?php if (!hasPermission('hr')): ?>
            <!-- Employee Clock In/Out Widget -->
            <div class="col-lg-4">
                <div class="clock-widget">
                    <div class="clock-time" id="clockDisplay"><?php echo date('H:i:s'); ?></div>
                    <div class="clock-date"><?php echo date('l, F j, Y'); ?></div>
                    
                    <?php if ($todayAttendance): ?>
                        <?php if ($todayAttendance['clock_out']): ?>
                            <!-- Already clocked out -->
                            <div class="mb-3">
                                <div class="alert alert-success bg-white bg-opacity-20 border-0 text-white">
                                    <h6><i class="fas fa-check-circle me-2"></i>Work Complete</h6>
                                    <p class="mb-1">Clocked in: <?php echo date('H:i', strtotime($todayAttendance['clock_in'])); ?></p>
                                    <p class="mb-1">Clocked out: <?php echo date('H:i', strtotime($todayAttendance['clock_out'])); ?></p>
                                    <p class="mb-0">Hours worked: <?php echo $todayAttendance['hours_worked']; ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Clocked in, can clock out -->
                            <div class="mb-3">
                                <div class="alert alert-warning bg-white bg-opacity-20 border-0 text-white">
                                    <h6><i class="fas fa-clock me-2"></i>Currently Working</h6>
                                    <p class="mb-0">Clocked in at: <?php echo date('H:i', strtotime($todayAttendance['clock_in'])); ?></p>
                                </div>
                            </div>
                            <form method="POST" action="index.php?page=attendance&action=clock_out">
                                <button type="submit" class="btn btn-clock-out btn-lg">
                                    <i class="fas fa-sign-out-alt me-2"></i>Clock Out
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Can clock in -->
                        <form method="POST" action="index.php?page=attendance&action=clock_in">
                            <button type="submit" class="btn btn-clock-in btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Clock In
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="<?php echo hasPermission('hr') ? 'col-12' : 'col-lg-8'; ?>">
            <div class="attendance-card">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>
                            <i class="fas fa-list text-primary me-2"></i>
                            <?php echo hasPermission('hr') ? 'All Attendance Records' : 'My Attendance History'; ?>
                        </h4>
                        <?php if (hasPermission('hr')): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal">
                                <i class="fas fa-plus me-2"></i>Add Manual Entry
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($attendanceRecords)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-success">
                                    <tr>
                                        <?php if (hasPermission('hr')): ?>
                                            <th>Employee</th>
                                        <?php endif; ?>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRecords as $record): ?>
                                        <tr>
                                            <?php if (hasPermission('hr')): ?>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['employee_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['employee_number']); ?></small>
                                                </td>
                                            <?php endif; ?>
                                            <td><?php echo formatDate($record['date']); ?></td>
                                            <td>
                                                <?php if ($record['clock_in']): ?>
                                                    <span class="time-display"><?php echo date('H:i', strtotime($record['clock_in'])); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['clock_out']): ?>
                                                    <span class="time-display"><?php echo date('H:i', strtotime($record['clock_out'])); ?></span>
                                                <?php else: ?>
                                                    <span class="text-warning">Working</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['hours_worked']): ?>
                                                    <strong><?php echo number_format($record['hours_worked'], 2); ?>h</strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['clock_out']): ?>
                                                    <span class="badge bg-success">Present</span>
                                                <?php elseif ($record['clock_in']): ?>
                                                    <span class="badge bg-warning">Working</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clock fa-4x text-muted mb-3"></i>
                            <h5>No Attendance Records</h5>
                            <p class="text-muted">
                                <?php echo hasPermission('hr') ? 'No attendance records found.' : 'Start tracking your time by clocking in.'; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Attendance Modal (HR Only) -->
<?php if (hasPermission('hr')): ?>
<div class="modal fade" id="manualAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Manual Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=attendance&action=add_manual">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" id="employee_id" name="employee_id" required>
                            <option value="">Select employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                    (<?php echo htmlspecialchars($emp['employee_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="clock_in" class="form-label">Clock In Time</label>
                                <input type="time" class="form-control" id="clock_in" name="clock_in" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="clock_out" class="form-label">Clock Out Time (Optional)</label>
                                <input type="time" class="form-control" id="clock_out" name="clock_out">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Real-time clock update
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: false });

    const clockDisplay = document.getElementById('clockDisplay');
    const currentTime = document.getElementById('currentTime');

    if (clockDisplay) clockDisplay.textContent = timeString;
    if (currentTime) currentTime.textContent = timeString;
}

// Update clock every second
setInterval(updateClock, 1000);

// Initialize clock on page load
document.addEventListener('DOMContentLoaded', function() {
    updateClock();

    // Auto-refresh page every 5 minutes to update attendance status
    setTimeout(function() {
        window.location.reload();
    }, 300000); // 5 minutes
});

// Confirmation for clock actions
document.addEventListener('DOMContentLoaded', function() {
    const clockButtons = document.querySelectorAll('.btn-clock-in, .btn-clock-out');

    clockButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.classList.contains('btn-clock-in') ? 'clock in' : 'clock out';
            const currentTime = new Date().toLocaleTimeString();

            if (!confirm(`Are you sure you want to ${action} at ${currentTime}?`)) {
                e.preventDefault();
            }
        });
    });
});
</script>
