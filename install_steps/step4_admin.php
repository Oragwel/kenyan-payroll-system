<?php
/**
 * Installation Step 4: Admin Account Creation
 */

// Default values
$defaults = [
    'username' => 'admin',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'phone' => ''
];

// Get saved values or use defaults
$adminConfig = $_SESSION['admin_config'] ?? $defaults;
?>

<div class="admin-config-section">
    <h2>👑 Create Administrator Account</h2>
    <p>Create your main administrator account. This user will have full access to all system features and settings.</p>

    <div class="alert alert-warning">
        <h5>🔒 Important Security Information</h5>
        <ul>
            <li>✅ <strong>Choose a strong password</strong> - minimum 8 characters with mixed case, numbers, and symbols</li>
            <li>✅ <strong>Use a unique username</strong> - avoid common names like 'admin' or 'administrator'</li>
            <li>✅ <strong>Provide a valid email</strong> - used for password recovery and system notifications</li>
            <li>✅ <strong>Remember these credentials</strong> - you'll need them to access the system</li>
        </ul>
    </div>

    <form method="POST" action="install.php?step=4" id="adminForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div>
                <h5>🔐 Login Credentials</h5>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($adminConfig['username']); ?>" 
                           required minlength="3" maxlength="50">
                    <small class="form-text">3-50 characters, letters, numbers, and underscores only</small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($adminConfig['email']); ?>" required>
                    <small class="form-text">Used for system notifications and password recovery</small>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="8">
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="form-text">Minimum 8 characters with mixed case, numbers, and symbols</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required minlength="8">
                    <div id="passwordMatch"></div>
                </div>
            </div>

            <div>
                <h5>👤 Personal Information</h5>
                
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name *</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($adminConfig['first_name']); ?>" 
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($adminConfig['last_name']); ?>" 
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($adminConfig['phone']); ?>" 
                           placeholder="+254 700 000 000">
                    <small class="form-text">Optional - Kenyan format preferred</small>
                </div>

                <div style="background: #e8f5e8; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <h6>🎯 Admin Privileges</h6>
                    <p style="margin: 0; font-size: 0.9rem;">As administrator, you will have access to:</p>
                    <ul style="font-size: 0.9rem; margin: 0.5rem 0 0 0;">
                        <li>Complete employee management</li>
                        <li>Payroll processing and reports</li>
                        <li>System settings and configuration</li>
                        <li>User management and permissions</li>
                        <li>Content management system</li>
                        <li>Backup and security features</li>
                    </ul>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <button type="submit" class="btn-installer" id="createAdminBtn">
                👑 Create Administrator Account
            </button>
        </div>
    </form>

    <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
        <h5>💡 Password Security Tips</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <div>
                <h6>✅ Strong Password Examples:</h6>
                <ul style="font-size: 0.9rem;">
                    <li>MyPayroll2024!</li>
                    <li>Kenya@Secure123</li>
                    <li>Admin$Strong2024</li>
                </ul>
            </div>
            <div>
                <h6>❌ Avoid These:</h6>
                <ul style="font-size: 0.9rem;">
                    <li>password, admin, 123456</li>
                    <li>Your name or company name</li>
                    <li>Common dictionary words</li>
                </ul>
            </div>
        </div>
    </div>

    <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
        <h5>🔄 What Happens Next?</h5>
        <p>After creating your admin account:</p>
        <ol>
            <li><strong>Company Setup:</strong> Configure your company information and branding</li>
            <li><strong>System Configuration:</strong> Set payroll rates and system preferences</li>
            <li><strong>Installation Complete:</strong> Access your fully functional payroll system</li>
            <li><strong>First Login:</strong> Use these credentials to log into the system</li>
        </ol>
    </div>
</div>

<script>
// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Uppercase letter');
    
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Lowercase letter');
    
    // Number check
    if (/\d/.test(password)) strength++;
    else feedback.push('Number');
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('Special character');
    
    // Display strength
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['#d63031', '#e17055', '#fdcb6e', '#00b894', '#00a085'];
    
    strengthDiv.innerHTML = `
        <div style="margin-top: 0.5rem;">
            <div style="background: #e9ecef; height: 4px; border-radius: 2px;">
                <div style="background: ${strengthColors[strength-1] || '#d63031'}; height: 100%; width: ${(strength/5)*100}%; border-radius: 2px; transition: all 0.3s ease;"></div>
            </div>
            <small style="color: ${strengthColors[strength-1] || '#d63031'};">
                ${strengthLevels[strength-1] || 'Very Weak'}
                ${feedback.length > 0 ? ' - Missing: ' + feedback.join(', ') : ''}
            </small>
        </div>
    `;
});

// Password confirmation checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            matchDiv.innerHTML = '<small style="color: #00b894;">✅ Passwords match</small>';
        } else {
            matchDiv.innerHTML = '<small style="color: #d63031;">❌ Passwords do not match</small>';
        }
    } else {
        matchDiv.innerHTML = '';
    }
});

// Form validation
document.getElementById('adminForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
        return false;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }
    
    // Disable button to prevent double submission
    document.getElementById('createAdminBtn').disabled = true;
    document.getElementById('createAdminBtn').innerHTML = '⏳ Creating Account...';
});

// Username validation
document.getElementById('username').addEventListener('input', function() {
    this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
});

// Phone number formatting for Kenyan numbers
document.getElementById('phone').addEventListener('input', function() {
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
</script>

<style>
.password-strength {
    margin-top: 0.5rem;
}

.form-control:invalid {
    border-color: #d63031;
}

.form-control:valid {
    border-color: #00b894;
}
</style>
