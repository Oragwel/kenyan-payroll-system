<?php
/**
 * Header navigation
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-calculator"></i> Kenyan Payroll
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php?page=dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#payrollCalculatorModal">
                        <i class="fas fa-calculator text-success"></i> 🇰🇪 Calculator
                    </a>
                </li>
                
                <?php if (hasPermission('hr')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="employeesDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> Employees
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=employees">View All</a></li>
                            <li><a class="dropdown-item" href="index.php?page=employees&action=add">Add Employee</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=departments">Departments</a></li>
                            <li><a class="dropdown-item" href="index.php?page=positions">Job Positions</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="payrollDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-calculator"></i> Payroll
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#payrollCalculatorModal">
                                <i class="fas fa-calculator text-success"></i> 🇰🇪 Payroll Calculator
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=simple_payroll">
                                <i class="fas fa-bolt text-primary"></i> Quick Payroll
                            </a></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll">Payroll Periods</a></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll&action=create">Process Payroll</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll_management">
                                <i class="fas fa-cogs text-warning"></i> Payroll Management
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=allowances">Allowances</a></li>
                            <li><a class="dropdown-item" href="index.php?page=deductions">Deductions</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="leavesDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar-alt"></i> Leaves
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=leaves">Leave Applications</a></li>
                            <li><a class="dropdown-item" href="index.php?page=leave_types">Leave Types</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-file-alt"></i> Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=reports&type=payroll">Payroll Reports</a></li>
                            <li><a class="dropdown-item" href="index.php?page=reports&type=statutory">Statutory Reports</a></li>
                            <li><a class="dropdown-item" href="index.php?page=reports&type=employee">Employee Reports</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=payslips">
                            <i class="fas fa-receipt"></i> My Payslips
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=leaves">
                            <i class="fas fa-calendar-alt"></i> My Leaves
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=profile">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['username']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php?page=profile">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><a class="dropdown-item" href="index.php?page=settings">
                            <i class="fas fa-cog"></i> Settings
                        </a></li>
                        <?php if (hasPermission('admin')): ?>
                            <li><a class="dropdown-item" href="index.php?page=security_dashboard">
                                <i class="fas fa-shield-alt"></i> Security Dashboard
                            </a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="secure_auth.php?action=logout">
                            <i class="fas fa-sign-out-alt"></i> Secure Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
body {
    padding-top: 70px;
}

.main-content {
    margin-left: 0;
    padding: 20px;
}

@media (min-width: 768px) {
    .main-content {
        margin-left: 250px;
    }
}
</style>
