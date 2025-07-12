/**
 * Main JavaScript file for Kenyan Payroll Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Currency formatting
    const currencyInputs = document.querySelectorAll('.currency-input');
    currencyInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d.]/g, '');
            if (value) {
                this.value = parseFloat(value).toFixed(2);
            }
        });
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(event) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                event.preventDefault();
            }
        });
    });

    // Print functionality
    const printButtons = document.querySelectorAll('.btn-print');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });

    // Export functionality
    const exportButtons = document.querySelectorAll('.btn-export');
    exportButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const format = this.dataset.format || 'csv';
            const table = document.querySelector('.table');
            
            if (table && format === 'csv') {
                exportTableToCSV(table, 'export.csv');
            }
        });
    });

    // Search functionality
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.card').querySelector('table tbody');
            
            if (table) {
                const rows = table.querySelectorAll('tr');
                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }
        });
    });

    // Payroll calculation preview
    const payrollForm = document.getElementById('payrollForm');
    if (payrollForm) {
        const inputs = payrollForm.querySelectorAll('input[type="number"]');
        inputs.forEach(function(input) {
            input.addEventListener('input', calculatePayrollPreview);
        });
    }
});

/**
 * Calculate payroll preview
 */
function calculatePayrollPreview() {
    const basicSalary = parseFloat(document.getElementById('basic_salary')?.value || 0);
    const allowances = Array.from(document.querySelectorAll('.allowance-amount'))
        .reduce((sum, input) => sum + parseFloat(input.value || 0), 0);
    
    const grossPay = basicSalary + allowances;
    
    // Calculate statutory deductions
    const nssf = calculateNSSF(grossPay);
    const nhif = calculateNHIF(grossPay);
    const housingLevy = grossPay * 0.015;
    const paye = calculatePAYE(grossPay - nssf);
    
    const totalDeductions = nssf + nhif + housingLevy + paye;
    const netPay = grossPay - totalDeductions;
    
    // Update preview
    updatePreviewElement('preview-gross-pay', grossPay);
    updatePreviewElement('preview-nssf', nssf);
    updatePreviewElement('preview-nhif', nhif);
    updatePreviewElement('preview-housing-levy', housingLevy);
    updatePreviewElement('preview-paye', paye);
    updatePreviewElement('preview-total-deductions', totalDeductions);
    updatePreviewElement('preview-net-pay', netPay);
}

/**
 * Update preview element with formatted currency
 */
function updatePreviewElement(id, amount) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = formatCurrency(amount);
    }
}

/**
 * Calculate NSSF contribution (exempted for contract employees)
 */
function calculateNSSF(grossPay, contractType = 'permanent') {
    // Contract employees are exempted from NSSF
    if (contractType === 'contract') {
        return 0;
    }

    const maxPensionable = 18000;
    const rate = 0.06;
    return Math.min(grossPay, maxPensionable) * rate;
}

/**
 * Calculate SHIF contribution (2.75% of gross pay with minimum KES 300)
 */
function calculateNHIF(grossPay) {
    const calculated = grossPay * 0.0275; // 2.75%
    return Math.ceil(Math.max(calculated, 300)); // Minimum KES 300, rounded up to whole number
}

/**
 * Calculate Housing Levy (exempted for contract employees)
 */
function calculateHousingLevy(grossPay, contractType = 'permanent') {
    // Contract employees are exempted from Housing Levy
    if (contractType === 'contract') {
        return 0;
    }

    return grossPay * 0.015; // 1.5% of gross pay
}

/**
 * Calculate PAYE tax
 */
function calculatePAYE(taxableIncome) {
    const rates = [
        {min: 0, max: 24000, rate: 0.10},
        {min: 24001, max: 32333, rate: 0.25},
        {min: 32334, max: 500000, rate: 0.30},
        {min: 500001, max: 800000, rate: 0.325},
        {min: 800001, max: Infinity, rate: 0.35}
    ];
    
    let tax = 0;
    for (let bracket of rates) {
        if (taxableIncome > bracket.min) {
            const taxableAmount = Math.min(taxableIncome, bracket.max) - bracket.min + 1;
            if (taxableAmount > 0) {
                tax += taxableAmount * bracket.rate;
            }
        }
    }
    
    // Apply personal relief
    tax = Math.max(0, tax - 2400);
    return tax;
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return 'KES ' + new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

/**
 * Export table to CSV
 */
function exportTableToCSV(table, filename) {
    const csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(function(row) {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        
        cols.forEach(function(col) {
            csvRow.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        
        csv.push(csvRow.join(','));
    });
    
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Download CSV file
 */
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

/**
 * Show loading spinner
 */
function showLoading() {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = '<div class="spinner-border spinner-border-custom text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
    document.body.appendChild(spinner);
}

/**
 * Hide loading spinner
 */
function hideLoading() {
    const spinner = document.querySelector('.spinner-overlay');
    if (spinner) {
        spinner.remove();
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(toast);
        bsAlert.close();
    }, 5000);
}

/**
 * Validate Kenyan ID number
 */
function validateKenyanID(idNumber) {
    const pattern = /^\d{8}$/;
    return pattern.test(idNumber);
}

/**
 * Validate KRA PIN
 */
function validateKRAPIN(pin) {
    const pattern = /^[A-Z]\d{9}[A-Z]$/;
    return pattern.test(pin);
}

/**
 * Open Payroll Calculator Modal
 */
function openPayrollCalculator() {
    // Initialize calculator when modal opens
    setTimeout(() => {
        calculateModalPayroll();
        addModalEventListeners();
    }, 100);
}

/**
 * Add event listeners for modal calculator inputs
 */
function addModalEventListeners() {
    const contractTypeInput = document.getElementById('modalContractType');
    const basicSalaryInput = document.getElementById('modalBasicSalary');
    const houseAllowanceInput = document.getElementById('modalHouseAllowance');
    const transportAllowanceInput = document.getElementById('modalTransportAllowance');

    // Remove existing listeners to prevent duplicates
    [contractTypeInput, basicSalaryInput, houseAllowanceInput, transportAllowanceInput].forEach(input => {
        if (input) {
            input.removeEventListener('input', calculateModalPayroll);
            input.removeEventListener('change', calculateModalPayroll);
            input.removeEventListener('keyup', calculateModalPayroll);
        }
    });

    // Add new listeners
    if (contractTypeInput) {
        contractTypeInput.addEventListener('change', calculateModalPayroll);
    }

    if (basicSalaryInput) {
        basicSalaryInput.addEventListener('input', calculateModalPayroll);
        basicSalaryInput.addEventListener('keyup', calculateModalPayroll);
    }

    if (houseAllowanceInput) {
        houseAllowanceInput.addEventListener('input', calculateModalPayroll);
        houseAllowanceInput.addEventListener('keyup', calculateModalPayroll);
    }

    if (transportAllowanceInput) {
        transportAllowanceInput.addEventListener('input', calculateModalPayroll);
        transportAllowanceInput.addEventListener('keyup', calculateModalPayroll);
    }
}

/**
 * Modal Payroll Calculator Function - MATCHES EXACT PAYROLL PROCESSING LOGIC
 */
function calculateModalPayroll() {
    // Get input values
    const contractType = document.getElementById('modalContractType')?.value || 'permanent';
    const basicSalary = parseFloat(document.getElementById('modalBasicSalary')?.value) || 0;
    const houseAllowance = parseFloat(document.getElementById('modalHouseAllowance')?.value) || 0;
    const transportAllowance = parseFloat(document.getElementById('modalTransportAllowance')?.value) || 0;

    // Calculate gross pay
    const grossPay = basicSalary + houseAllowance + transportAllowance;

    // NSSF calculation (exempted for contract employees) - MATCHES config.php constants
    let nssf = 0;
    if (contractType !== 'contract') {
        const pensionablePay = Math.min(grossPay, 18000); // NSSF_MAX_PENSIONABLE from config
        nssf = Math.round(pensionablePay * 0.06 * 100) / 100; // 6% with proper rounding
    }

    // Calculate taxable income (gross minus NSSF) - MATCHES functions.php logic
    const taxableIncome = Math.max(0, grossPay - nssf);

    // PAYE calculation - MATCHES functions.php calculatePAYE() exactly
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

    // Apply personal relief - MATCHES PERSONAL_RELIEF constant
    paye = Math.max(0, paye - 2400);
    paye = Math.round(paye * 100) / 100; // Round to 2 decimal places

    // SHIF calculation - MATCHES functions.php calculateSHIF() exactly
    const shifCalculated = grossPay * 0.0275; // SHIF_RATE from config
    const shif = Math.ceil(Math.max(shifCalculated, 300)); // SHIF_MINIMUM from config

    // Housing Levy calculation (exempted for contract employees) - MATCHES functions.php
    let housingLevy = 0;
    if (contractType !== 'contract') {
        housingLevy = Math.round(grossPay * 0.015 * 100) / 100; // HOUSING_LEVY_RATE with rounding
    }

    // Calculate net pay
    const netPay = grossPay - paye - nssf - shif - housingLevy;

    // Update display with null checks and exemption indicators
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

    // Update exemption text based on contract type
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
}

/**
 * Copy calculation results to clipboard
 */
function copyCalculationResults() {
    const contractType = document.getElementById('modalContractType')?.value || 'permanent';
    const grossPay = document.getElementById('modalGrossPay')?.textContent || '';
    const paye = document.getElementById('modalPaye')?.textContent || '';
    const nssf = document.getElementById('modalNssf')?.textContent || '';
    const shif = document.getElementById('modalShif')?.textContent || '';
    const housing = document.getElementById('modalHousing')?.textContent || '';
    const netPay = document.getElementById('modalNetPay')?.textContent || '';

    const results = `🇰🇪 Kenyan Payroll Calculation Results
Contract Type: ${contractType.charAt(0).toUpperCase() + contractType.slice(1)}

Gross Pay: ${grossPay}
PAYE Tax: ${paye}
NSSF: ${nssf}
SHIF: ${shif}
Housing Levy: ${housing}
Net Pay: ${netPay}

Generated by Kenyan Payroll Management System`;

    navigator.clipboard.writeText(results).then(() => {
        showToast('Calculation results copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy results to clipboard', 'error');
    });
}
