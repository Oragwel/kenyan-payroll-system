<?php
/**
 * Sidebar navigation
 */
?>
<div class="sidebar bg-light border-end" id="sidebar">
    <div class="sidebar-content">
        <div class="list-group list-group-flush">
            <a href="index.php?page=dashboard" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            
            <?php if (hasPermission('hr')): ?>
                <!-- HR/Admin Menu Items -->
                <div class="list-group-item bg-secondary text-white">
                    <small><strong>EMPLOYEE MANAGEMENT</strong></small>
                </div>
                
                <a href="index.php?page=employees" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'employees' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Employees
                </a>
                
                <a href="index.php?page=departments" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'departments' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Departments
                </a>
                
                <a href="index.php?page=positions" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'positions' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i> Job Positions
                </a>
                
                <div class="list-group-item bg-secondary text-white">
                    <small><strong>PAYROLL MANAGEMENT</strong></small>
                </div>
                
                <a href="index.php?page=payroll" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'payroll' ? 'active' : ''; ?>">
                    <i class="fas fa-calculator"></i> Payroll Processing
                </a>
                
                <a href="index.php?page=allowances" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'allowances' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> Allowances
                </a>
                
                <a href="index.php?page=deductions" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'deductions' ? 'active' : ''; ?>">
                    <i class="fas fa-minus-circle"></i> Deductions
                </a>
                
                <div class="list-group-item bg-secondary text-white">
                    <small><strong>ATTENDANCE & LEAVES</strong></small>
                </div>
                
                <a href="index.php?page=attendance" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Attendance
                </a>
                
                <a href="index.php?page=leaves" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'leaves' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Leave Management
                </a>
                
                <a href="index.php?page=leave_types" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'leave_types' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Leave Types
                </a>
                
                <div class="list-group-item bg-secondary text-white">
                    <small><strong>REPORTS & COMPLIANCE</strong></small>
                </div>
                
                <a href="index.php?page=reports" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Reports
                </a>
                
                <a href="index.php?page=statutory" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'statutory' ? 'active' : ''; ?>">
                    <i class="fas fa-gavel"></i> Statutory Reports
                </a>
                
                <?php if (hasPermission('admin')): ?>
                    <div class="list-group-item bg-secondary text-white">
                        <small><strong>SYSTEM ADMINISTRATION</strong></small>
                    </div>
                    
                    <a href="index.php?page=companies" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'companies' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> Company Settings
                    </a>
                    
                    <a href="index.php?page=users" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> User Management
                    </a>
                    
                    <a href="index.php?page=system_settings" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'system_settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> System Settings
                    </a>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Employee Menu Items -->
                <div class="list-group-item bg-secondary text-white">
                    <small><strong>MY INFORMATION</strong></small>
                </div>
                
                <a href="index.php?page=profile" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> My Profile
                </a>
                
                <a href="index.php?page=payslips" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'payslips' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i> My Payslips
                </a>
                
                <a href="index.php?page=leaves" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'leaves' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> My Leaves
                </a>
                
                <a href="index.php?page=attendance" class="list-group-item list-group-item-action <?php echo ($_GET['page'] ?? '') === 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> My Attendance
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.sidebar {
    position: fixed;
    top: 70px;
    left: 0;
    width: 250px;
    height: calc(100vh - 70px);
    overflow-y: auto;
    z-index: 1000;
    transition: transform 0.3s ease-in-out;
}

@media (max-width: 767.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0 !important;
    }
}

.sidebar-content {
    padding: 0;
}

.list-group-item {
    border-left: none;
    border-right: none;
    border-radius: 0;
}

.list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.list-group-item.active:hover {
    background-color: #0d6efd;
}

.list-group-item i {
    width: 20px;
    margin-right: 10px;
}
</style>
