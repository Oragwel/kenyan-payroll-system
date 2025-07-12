<?php
/**
 * Kenyan Payroll Management System
 * Main entry point for the application
 */

session_start();

// Comprehensive installation check
require_once 'includes/installation_check.php';
enforceInstallationCheck();

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'secure_auth.php';

// Simple routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Initialize secure authentication
global $secureAuth;

// Check if user is logged in with enhanced security
if (!isset($_SESSION['user_id']) && $page !== 'auth') {
    header('Location: check_remember_me.php');
    exit;
}

// Validate session security for authenticated users (only if secureAuth is available)
if (isset($_SESSION['user_id']) && $secureAuth && !$secureAuth->validateSession()) {
    header('Location: check_remember_me.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Payroll Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php include 'includes/header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <div class="<?php echo isset($_SESSION['user_id']) ? 'main-content' : 'auth-container'; ?>">
        <?php
        // Role-based dashboard routing
        if ($page === 'dashboard' && isset($_SESSION['user_role'])) {
            switch ($_SESSION['user_role']) {
                case 'admin':
                    include 'pages/dashboard.php'; // Admin-only dashboard
                    break;
                case 'hr':
                    include 'pages/hr_dashboard.php'; // HR dashboard
                    break;
                case 'employee':
                default:
                    include 'pages/employee_dashboard.php'; // Employee dashboard
                    break;
            }
        } else {
            // Include the appropriate page
            $page_file = "pages/{$page}.php";
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                include 'pages/404.php';
            }
        }
        ?>
    </div>

    <!-- Payroll Calculator Modal -->
    <div class="modal fade" id="payrollCalculatorModal" tabindex="-1" aria-labelledby="payrollCalculatorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="payrollCalculatorModalLabel">
                        <i class="fas fa-calculator me-2"></i>
                        🇰🇪 Kenyan Payroll Calculator
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-edit text-primary"></i>
                                Salary Information
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Contract Type</label>
                                <select class="form-control" id="modalContractType" onchange="calculateModalPayroll()">
                                    <option value="permanent">Permanent Employee</option>
                                    <option value="contract">Contract (NSSF & Housing Levy Exempt)</option>
                                    <option value="casual">Casual Labourer</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Basic Salary (KES)</label>
                                <input type="number" class="form-control" id="modalBasicSalary"
                                       value="75000" onchange="calculateModalPayroll()" placeholder="Enter basic salary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">House Allowance (KES)</label>
                                <input type="number" class="form-control" id="modalHouseAllowance"
                                       value="20000" onchange="calculateModalPayroll()" placeholder="Enter house allowance">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Transport Allowance (KES)</label>
                                <input type="number" class="form-control" id="modalTransportAllowance"
                                       value="8000" onchange="calculateModalPayroll()" placeholder="Enter transport allowance">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-pie text-success"></i>
                                Salary Breakdown
                            </h6>
                            <div class="bg-light p-3 rounded">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Gross Pay:</strong></span>
                                    <strong class="text-primary" id="modalGrossPay">KES 103,000</strong>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>PAYE Tax:</span>
                                    <span class="text-danger" id="modalPaye">KES 20,976</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>NSSF (6%):</span>
                                    <span class="text-warning" id="modalNssf">KES 1,080</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>SHIF (2.75%):</span>
                                    <span class="text-info" id="modalShif">KES 2,833</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Housing Levy (1.5%):</span>
                                    <span class="text-secondary" id="modalHousing">KES 1,545</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-success">Net Pay:</strong>
                                    <strong class="text-success fs-5" id="modalNetPay">KES 76,566</strong>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted" id="modalExemptions">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="modalExemptionText">All statutory deductions apply</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="text-start flex-grow-1">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Calculations based on 2024 Kenyan tax rates and statutory deductions
                        </small>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="copyCalculationResults()">
                        <i class="fas fa-copy me-1"></i>
                        Copy Results
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
