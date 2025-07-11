<?php
/**
 * Installation Step 5: Company Information Setup
 */

// Default values
$defaults = [
    'company_name' => '',
    'company_address' => 'Nairobi, Kenya',
    'company_phone' => '+254 700 000 000',
    'company_email' => '',
    'company_website' => '',
    'kra_pin' => '',
    'nssf_number' => '',
    'nhif_number' => ''
];

// Get saved values or use defaults
$companyConfig = $_SESSION['company_config'] ?? $defaults;
?>

<div class="company-config-section">
    <h2>🏢 Company Information Setup</h2>
    <p>Configure your company details. This information will appear on payslips, reports, and throughout the system.</p>

    <form method="POST" action="install.php?step=5" id="companyForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
            <div>
                <h5>🏢 Basic Company Information</h5>
                
                <div class="form-group">
                    <label for="company_name" class="form-label">Company Name *</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" 
                           value="<?php echo htmlspecialchars($companyConfig['company_name']); ?>" 
                           required maxlength="255">
                    <small class="form-text">Your official company name as registered</small>
                </div>

                <div class="form-group">
                    <label for="company_email" class="form-label">Company Email *</label>
                    <input type="email" class="form-control" id="company_email" name="company_email" 
                           value="<?php echo htmlspecialchars($companyConfig['company_email']); ?>" required>
                    <small class="form-text">Main contact email for the company</small>
                </div>

                <div class="form-group">
                    <label for="company_phone" class="form-label">Company Phone</label>
                    <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                           value="<?php echo htmlspecialchars($companyConfig['company_phone']); ?>">
                    <small class="form-text">Main company phone number</small>
                </div>

                <div class="form-group">
                    <label for="company_website" class="form-label">Company Website</label>
                    <input type="url" class="form-control" id="company_website" name="company_website" 
                           value="<?php echo htmlspecialchars($companyConfig['company_website']); ?>" 
                           placeholder="https://www.yourcompany.co.ke">
                    <small class="form-text">Optional - company website URL</small>
                </div>

                <div class="form-group">
                    <label for="company_address" class="form-label">Company Address *</label>
                    <textarea class="form-control" id="company_address" name="company_address" 
                              rows="3" required><?php echo htmlspecialchars($companyConfig['company_address']); ?></textarea>
                    <small class="form-text">Full company address including city and postal code</small>
                </div>
            </div>

            <div>
                <h5>🇰🇪 Kenyan Statutory Information</h5>
                
                <div class="alert alert-warning">
                    <h6>📋 Required for Compliance</h6>
                    <p style="margin: 0; font-size: 0.9rem;">These details are required for generating statutory reports and ensuring compliance with Kenyan employment law.</p>
                </div>

                <div class="form-group">
                    <label for="kra_pin" class="form-label">KRA PIN *</label>
                    <input type="text" class="form-control" id="kra_pin" name="kra_pin" 
                           value="<?php echo htmlspecialchars($companyConfig['kra_pin']); ?>" 
                           required maxlength="11" placeholder="P051234567A">
                    <small class="form-text">Kenya Revenue Authority PIN (11 characters)</small>
                </div>

                <div class="form-group">
                    <label for="nssf_number" class="form-label">NSSF Employer Number</label>
                    <input type="text" class="form-control" id="nssf_number" name="nssf_number" 
                           value="<?php echo htmlspecialchars($companyConfig['nssf_number']); ?>" 
                           maxlength="20" placeholder="1234567">
                    <small class="form-text">National Social Security Fund employer number</small>
                </div>

                <div class="form-group">
                    <label for="nhif_number" class="form-label">NHIF/SHIF Employer Number</label>
                    <input type="text" class="form-control" id="nhif_number" name="nhif_number" 
                           value="<?php echo htmlspecialchars($companyConfig['nhif_number']); ?>" 
                           maxlength="20" placeholder="12345">
                    <small class="form-text">National Hospital Insurance Fund / Social Health Insurance Fund number</small>
                </div>

                <div style="background: #e8f5e8; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <h6>🎯 Why This Information Matters</h6>
                    <ul style="font-size: 0.9rem; margin: 0.5rem 0 0 0;">
                        <li><strong>Payslips:</strong> Company details appear on all payslips</li>
                        <li><strong>Reports:</strong> Required for P9, P10, and other statutory reports</li>
                        <li><strong>Compliance:</strong> Ensures proper tax and contribution reporting</li>
                        <li><strong>Branding:</strong> Professional appearance throughout the system</li>
                    </ul>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
            <h5>🎨 System Branding</h5>
            <p>Your company information will be used throughout the system:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                <div>✅ Dashboard headers and titles</div>
                <div>✅ Payslip generation</div>
                <div>✅ Email notifications</div>
                <div>✅ Statutory reports</div>
                <div>✅ System footer and branding</div>
                <div>✅ PDF document headers</div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <button type="submit" class="btn-installer" id="saveCompanyBtn">
                🏢 Save Company Information
            </button>
        </div>
    </form>

    <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
        <h5>📝 Next: System Configuration</h5>
        <p>After saving your company information, you'll configure:</p>
        <ul>
            <li><strong>Payroll Settings:</strong> PAYE rates, NSSF, SHIF, Housing Levy rates</li>
            <li><strong>System Preferences:</strong> Currency, timezone, date formats</li>
            <li><strong>Default Leave Types:</strong> Annual, sick, maternity leave configurations</li>
            <li><strong>Security Settings:</strong> Session timeout and password policies</li>
        </ul>
    </div>

    <div style="margin-top: 1rem; padding: 1rem; background: #e8f5e8; border-radius: 8px; border-left: 4px solid var(--kenya-green);">
        <h5>💡 Pro Tips</h5>
        <ul style="margin: 0;">
            <li><strong>Accuracy is important:</strong> Double-check all statutory numbers</li>
            <li><strong>Keep records:</strong> Save these details in a secure location</li>
            <li><strong>Updates:</strong> You can modify this information later in system settings</li>
            <li><strong>Compliance:</strong> Ensure all numbers are current and valid</li>
        </ul>
    </div>
</div>

<script>
// KRA PIN formatting and validation
document.getElementById('kra_pin').addEventListener('input', function() {
    let value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    // KRA PIN format: P051234567A (starts with P, ends with letter, 9 digits in between)
    if (value.length > 11) {
        value = value.substring(0, 11);
    }
    
    this.value = value;
    
    // Validate format
    const kraPattern = /^P\d{9}[A-Z]$/;
    if (value.length === 11) {
        if (kraPattern.test(value)) {
            this.style.borderColor = '#00b894';
        } else {
            this.style.borderColor = '#d63031';
        }
    } else {
        this.style.borderColor = '#e9ecef';
    }
});

// Phone number formatting
document.getElementById('company_phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value.startsWith('254')) {
        value = '+' + value;
    } else if (value.startsWith('0')) {
        value = '+254' + value.substring(1);
    } else if (value.length > 0 && !value.startsWith('+')) {
        value = '+254' + value;
    }
    this.value = value;
});

// Website URL formatting
document.getElementById('company_website').addEventListener('blur', function() {
    let value = this.value.trim();
    if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
        this.value = 'https://' + value;
    }
});

// Form validation
document.getElementById('companyForm').addEventListener('submit', function(e) {
    const kraPin = document.getElementById('kra_pin').value;
    const kraPattern = /^P\d{9}[A-Z]$/;
    
    if (kraPin && !kraPattern.test(kraPin)) {
        e.preventDefault();
        alert('Please enter a valid KRA PIN format (e.g., P051234567A)');
        return false;
    }
    
    // Disable button to prevent double submission
    document.getElementById('saveCompanyBtn').disabled = true;
    document.getElementById('saveCompanyBtn').innerHTML = '⏳ Saving Information...';
});

// Auto-generate email from company name
document.getElementById('company_name').addEventListener('blur', function() {
    const emailField = document.getElementById('company_email');
    if (!emailField.value && this.value) {
        const domain = this.value.toLowerCase()
            .replace(/[^a-z0-9]/g, '')
            .substring(0, 10);
        emailField.value = `info@${domain}.co.ke`;
    }
});

// NSSF and NHIF number validation (numbers only)
document.getElementById('nssf_number').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

document.getElementById('nhif_number').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});
</script>

<style>
.form-control:focus {
    border-color: var(--kenya-green);
    box-shadow: 0 0 0 3px rgba(0,107,63,0.1);
}

.form-control.valid {
    border-color: #00b894;
}

.form-control.invalid {
    border-color: #d63031;
}
</style>
