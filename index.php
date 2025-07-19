<?php
/**
 * Kenyan Payroll Management System
 * Main entry point for the application
 */

session_start();

// Check if system is installed, if not redirect to installer
if (!file_exists('config/installed.txt')) {
    header('Location: install_new.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/DatabaseUtils.php';
require_once 'secure_auth.php';

// Initialize database utilities
DatabaseUtils::initialize($database);

// Simple routing
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Handle common URL mistakes - redirect payslip (singular) to payslips (plural)
if ($page === 'payslip') {
    $redirectUrl = 'index.php?page=payslips';

    // Preserve parameters with correct names
    if (isset($_GET['id'])) {
        $redirectUrl .= '&action=view&payslip_id=' . urlencode($_GET['id']);
    } elseif (isset($_GET['payslip_id'])) {
        $redirectUrl .= '&action=view&payslip_id=' . urlencode($_GET['payslip_id']);
    }

    // Preserve other parameters
    foreach ($_GET as $key => $value) {
        if (!in_array($key, ['page', 'id', 'payslip_id'])) {
            $redirectUrl .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// Handle PDF generation BEFORE any headers are sent
if ($page === 'payslips' && $action === 'pdf' && isset($_GET['payslip_id'])) {
    header('Location: payslip_pdf.php?payslip_id=' . urlencode($_GET['payslip_id']));
    exit;
}

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
                                <label class="form-label">Employment Type</label>
                                <select class="form-control" id="modalContractType">
                                    <option value="permanent">Permanent Employee</option>
                                    <option value="contract">Contract (NSSF & Housing Levy Exempt)</option>
                                    <option value="casual">Casual Labourer</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Basic Salary (KES)</label>
                                <input type="number" class="form-control" id="modalBasicSalary"
                                       value="75000" placeholder="Enter basic salary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">House Allowance (KES)</label>
                                <input type="number" class="form-control" id="modalHouseAllowance"
                                       value="20000" placeholder="Enter house allowance">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Transport Allowance (KES)</label>
                                <input type="number" class="form-control" id="modalTransportAllowance"
                                       value="8000" placeholder="Enter transport allowance">
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

    <!-- Modal Calculator Initialization Script -->
    <script>
    // Ensure modal calculator functions are available
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modal event listeners when modal is shown
        const payrollModal = document.getElementById('payrollCalculatorModal');
        if (payrollModal) {
            payrollModal.addEventListener('shown.bs.modal', function() {
                console.log('Modal opened, initializing calculator...');
                initializeModalCalculator();
            });
        }
    });

    function initializeModalCalculator() {
        // Calculate initial values
        calculateModalPayroll();

        // Add event listeners
        const contractType = document.getElementById('modalContractType');
        const basicSalary = document.getElementById('modalBasicSalary');
        const houseAllowance = document.getElementById('modalHouseAllowance');
        const transportAllowance = document.getElementById('modalTransportAllowance');

        if (contractType) {
            contractType.addEventListener('change', calculateModalPayroll);
        }

        if (basicSalary) {
            basicSalary.addEventListener('input', calculateModalPayroll);
            basicSalary.addEventListener('keyup', calculateModalPayroll);
        }

        if (houseAllowance) {
            houseAllowance.addEventListener('input', calculateModalPayroll);
            houseAllowance.addEventListener('keyup', calculateModalPayroll);
        }

        if (transportAllowance) {
            transportAllowance.addEventListener('input', calculateModalPayroll);
            transportAllowance.addEventListener('keyup', calculateModalPayroll);
        }

        console.log('Modal calculator initialized successfully');
    }

    // Modal Calculator Function
    function calculateModalPayroll() {
        console.log('Calculating modal payroll...');

        try {
            // Get input values
            const contractType = document.getElementById('modalContractType')?.value || 'permanent';
            const basicSalary = parseFloat(document.getElementById('modalBasicSalary')?.value) || 0;
            const houseAllowance = parseFloat(document.getElementById('modalHouseAllowance')?.value) || 0;
            const transportAllowance = parseFloat(document.getElementById('modalTransportAllowance')?.value) || 0;

            console.log('Input values:', {contractType, basicSalary, houseAllowance, transportAllowance});

            // Calculate gross pay
            const grossPay = basicSalary + houseAllowance + transportAllowance;

            // NSSF calculation (exempted for contract employees)
            let nssf = 0;
            if (contractType !== 'contract') {
                const pensionablePay = Math.min(grossPay, 18000);
                nssf = Math.round(pensionablePay * 0.06 * 100) / 100;
            }

            // Calculate taxable income (gross minus NSSF)
            const taxableIncome = Math.max(0, grossPay - nssf);

            // PAYE calculation
            let paye = 0;
            const payeBrackets = [
                {min: 0, max: 24000, rate: 0.10},
                {min: 24001, max: 32333, rate: 0.25},
                {min: 32334, max: 500000, rate: 0.30},
                {min: 500001, max: 800000, rate: 0.325},
                {min: 800001, max: Number.MAX_SAFE_INTEGER, rate: 0.35}
            ];

            for (let bracket of payeBrackets) {
                if (taxableIncome > bracket.min) {
                    const taxableAmount = Math.min(taxableIncome, bracket.max) - bracket.min + 1;
                    if (taxableAmount > 0) {
                        paye += taxableAmount * bracket.rate;
                    }
                }
            }

            // Apply personal relief
            paye = Math.max(0, paye - 2400);
            paye = Math.round(paye * 100) / 100;

            // SHIF calculation
            const shifCalculated = grossPay * 0.0275;
            const shif = Math.ceil(Math.max(shifCalculated, 300));

            // Housing Levy calculation (exempted for contract employees)
            let housingLevy = 0;
            if (contractType !== 'contract') {
                housingLevy = Math.round(grossPay * 0.015 * 100) / 100;
            }

            // Calculate net pay
            const netPay = grossPay - paye - nssf - shif - housingLevy;

            console.log('Calculated values:', {grossPay, paye, nssf, shif, housingLevy, netPay});

            // Update display
            const grossPayEl = document.getElementById('modalGrossPay');
            const payeEl = document.getElementById('modalPaye');
            const nssfEl = document.getElementById('modalNssf');
            const shifEl = document.getElementById('modalShif');
            const housingEl = document.getElementById('modalHousing');
            const netPayEl = document.getElementById('modalNetPay');
            const exemptionTextEl = document.getElementById('modalExemptionText');

            if (grossPayEl) grossPayEl.textContent = 'KES ' + grossPay.toLocaleString();
            if (payeEl) payeEl.textContent = 'KES ' + Math.round(paye).toLocaleString();
            if (nssfEl) nssfEl.textContent = 'KES ' + Math.round(nssf).toLocaleString() + (contractType === 'contract' ? ' (Exempted)' : '');
            if (shifEl) shifEl.textContent = 'KES ' + shif.toLocaleString();
            if (housingEl) housingEl.textContent = 'KES ' + Math.round(housingLevy).toLocaleString() + (contractType === 'contract' ? ' (Exempted)' : '');
            if (netPayEl) netPayEl.textContent = 'KES ' + Math.round(netPay).toLocaleString();

            // Update exemption text
            if (exemptionTextEl) {
                switch (contractType) {
                    case 'contract':
                        exemptionTextEl.textContent = 'Contract employee: NSSF & Housing Levy exempted';
                        break;
                    case 'casual':
                        exemptionTextEl.textContent = 'Casual labourer: All statutory deductions apply';
                        break;
                    case 'intern':
                        exemptionTextEl.textContent = 'Intern: All statutory deductions apply';
                        break;
                    default:
                        exemptionTextEl.textContent = 'Permanent employee: All statutory deductions apply';
                }
            }

            console.log('Modal calculator updated successfully');

        } catch (error) {
            console.error('Error in calculateModalPayroll:', error);
        }
    }

    // Copy results function
    function copyCalculationResults() {
        const contractType = document.getElementById('modalContractType')?.value || 'permanent';
        const grossPay = document.getElementById('modalGrossPay')?.textContent || '';
        const paye = document.getElementById('modalPaye')?.textContent || '';
        const nssf = document.getElementById('modalNssf')?.textContent || '';
        const shif = document.getElementById('modalShif')?.textContent || '';
        const housing = document.getElementById('modalHousing')?.textContent || '';
        const netPay = document.getElementById('modalNetPay')?.textContent || '';

        const results = `🇰🇪 Kenyan Payroll Calculation Results
Employment Type: ${contractType.charAt(0).toUpperCase() + contractType.slice(1)}

Gross Pay: ${grossPay}
PAYE Tax: ${paye}
NSSF: ${nssf}
SHIF: ${shif}
Housing Levy: ${housing}
Net Pay: ${netPay}

Generated by Kenyan Payroll Management System`;

        navigator.clipboard.writeText(results).then(() => {
            alert('Calculation results copied to clipboard!');
        }).catch(() => {
            alert('Failed to copy results to clipboard');
        });
    }
    </script>
</body>
</html>
