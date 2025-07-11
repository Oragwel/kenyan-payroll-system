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
 * Calculate NSSF contribution
 */
function calculateNSSF(grossPay) {
    const maxPensionable = 18000;
    const rate = 0.06;
    return Math.min(grossPay, maxPensionable) * rate;
}

/**
 * Calculate SHIF contribution (2.75% of gross pay with minimum KES 300)
 */
function calculateNHIF(grossPay) {
    const calculated = grossPay * 0.0275; // 2.75%
    return Math.max(calculated, 300); // Minimum KES 300
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
