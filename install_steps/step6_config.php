<?php
/**
 * Installation Step 6: System Configuration
 */

// Check if we have all previous configurations
if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_config']) || !isset($_SESSION['company_config'])) {
    header('Location: install.php?step=2');
    exit;
}

$installationComplete = false;
$installationError = null;

// Handle final installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_installation'])) {
    try {
        $result = completeInstallation();
        if ($result['success']) {
            $installationComplete = true;
            $success[] = "Installation completed successfully!";
        } else {
            $installationError = $result['error'];
            $errors[] = $result['error'];
        }
    } catch (Exception $e) {
        $installationError = $e->getMessage();
        $errors[] = $e->getMessage();
    }
}
?>

<div class="system-config-section">
    <h2>🎯 System Configuration & Final Setup</h2>
    <p>Review your configuration and complete the installation of your Kenyan Payroll Management System.</p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
        <!-- Database Configuration Summary -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--kenya-green);">
            <h5>🗄️ Database Configuration</h5>
            <div style="font-size: 0.9rem;">
                <div><strong>Host:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['host']); ?></div>
                <div><strong>Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['database']); ?></div>
                <div><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['username']); ?></div>
                <div style="color: #00b894; margin-top: 0.5rem;">✅ Connected & Tables Created</div>
            </div>
        </div>

        <!-- Admin Account Summary -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--kenya-red);">
            <h5>👑 Administrator Account</h5>
            <div style="font-size: 0.9rem;">
                <div><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['username']); ?></div>
                <div><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['email']); ?></div>
                <div><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['first_name'] . ' ' . $_SESSION['admin_config']['last_name']); ?></div>
                <div style="color: #00b894; margin-top: 0.5rem;">✅ Ready to Create</div>
            </div>
        </div>

        <!-- Company Information Summary -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; border-left: 4px solid #000000;">
            <h5>🏢 Company Information</h5>
            <div style="font-size: 0.9rem;">
                <div><strong>Company:</strong> <?php echo htmlspecialchars($_SESSION['company_config']['company_name']); ?></div>
                <div><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['company_config']['company_email']); ?></div>
                <div><strong>KRA PIN:</strong> <?php echo htmlspecialchars($_SESSION['company_config']['kra_pin']); ?></div>
                <div style="color: #00b894; margin-top: 0.5rem;">✅ Information Complete</div>
            </div>
        </div>
    </div>

    <?php if (!$installationComplete && !$installationError): ?>
        <div class="alert alert-warning">
            <h5>🚀 Ready for Final Installation</h5>
            <p>The system will now:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 1rem 0;">
                <div>
                    <h6>📊 Create System Data:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Company record</li>
                        <li>Administrator user</li>
                        <li>Default leave types</li>
                        <li>System settings</li>
                    </ul>
                </div>
                <div>
                    <h6>⚙️ Configure Defaults:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Kenyan PAYE rates (2024)</li>
                        <li>NSSF contribution rates</li>
                        <li>SHIF rates and minimums</li>
                        <li>Housing Levy settings</li>
                    </ul>
                </div>
                <div>
                    <h6>🔒 Security Setup:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Password encryption</li>
                        <li>Session configuration</li>
                        <li>Role-based permissions</li>
                        <li>Audit logging</li>
                    </ul>
                </div>
                <div>
                    <h6>🎨 System Preparation:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>CMS configuration</li>
                        <li>Default dashboard</li>
                        <li>Kenyan flag themes</li>
                        <li>Installation completion</li>
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST" style="text-align: center;">
            <button type="submit" name="complete_installation" class="btn-installer" style="font-size: 1.2rem; padding: 1.5rem 3rem;">
                🎉 Complete Installation
            </button>
        </form>

    <?php elseif ($installationError): ?>
        <div class="alert alert-danger">
            <h5>❌ Installation Failed</h5>
            <p><?php echo htmlspecialchars($installationError); ?></p>
            
            <h6>🔧 Common Solutions:</h6>
            <ul>
                <li><strong>Database connection lost:</strong> Check if MySQL is still running</li>
                <li><strong>Permission errors:</strong> Ensure database user has INSERT privileges</li>
                <li><strong>Duplicate entries:</strong> Clear any existing data and try again</li>
                <li><strong>File permissions:</strong> Check config directory is writable</li>
            </ul>
            
            <div style="margin-top: 1rem;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="complete_installation" class="btn-installer">
                        🔄 Try Again
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="installation-summary">
            <h2>🎉 Installation Complete!</h2>
            <p>Your Kenyan Payroll Management System has been successfully installed and configured.</p>
            <div class="kenyan-flag" style="margin: 1rem auto; width: 200px;"></div>
            <p><strong>You can now access your fully functional enterprise payroll system!</strong></p>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="index.php" class="btn-installer" style="font-size: 1.2rem; padding: 1.5rem 3rem;">
                🚀 Access Your Payroll System
            </a>
        </div>
    <?php endif; ?>

    <?php if ($installationComplete): ?>
        <div style="margin-top: 2rem; padding: 1.5rem; background: #e8f5e8; border-radius: 12px; border-left: 4px solid var(--kenya-green);">
            <h5>🎯 What's Next?</h5>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div>
                    <h6>👥 Employee Management:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Add departments and positions</li>
                        <li>Register employees</li>
                        <li>Set up allowances and deductions</li>
                    </ul>
                </div>
                <div>
                    <h6>💰 Payroll Processing:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Create payroll periods</li>
                        <li>Process monthly payroll</li>
                        <li>Generate payslips</li>
                    </ul>
                </div>
                <div>
                    <h6>📊 Reports & Analytics:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>View dashboard analytics</li>
                        <li>Generate statutory reports</li>
                        <li>Export to PDF/Excel</li>
                    </ul>
                </div>
                <div>
                    <h6>⚙️ System Configuration:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>Customize system settings</li>
                        <li>Update company information</li>
                        <li>Manage user accounts</li>
                    </ul>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
            <h5>🔐 Your Login Credentials</h5>
            <div style="background: white; padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                <div><strong>URL:</strong> <a href="index.php" target="_blank">index.php</a></div>
                <div><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['admin_config']['username']); ?></div>
                <div><strong>Password:</strong> [The password you created]</div>
            </div>
            <p style="margin-top: 0.5rem; margin-bottom: 0; font-size: 0.9rem;"><strong>Important:</strong> Save these credentials in a secure location!</p>
        </div>

        <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
            <h5>📚 Resources & Support</h5>
            <ul style="margin: 0;">
                <li><strong>Documentation:</strong> Check the README.md file for detailed guides</li>
                <li><strong>Help System:</strong> Access built-in help from the dashboard</li>
                <li><strong>Updates:</strong> Keep your system updated for security and features</li>
                <li><strong>Backup:</strong> Regular backups are available in System Settings</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_installation']) && !$installationError): ?>
<script>
// Auto-redirect to system after successful installation
setTimeout(function() {
    window.location.href = 'index.php';
}, 5000);

// Show completion animation
let progress = 0;
const progressInterval = setInterval(function() {
    progress += 5;
    if (progress <= 100) {
        console.log('Installation progress: ' + progress + '%');
    } else {
        clearInterval(progressInterval);
        console.log('Installation complete! Redirecting...');
    }
}, 100);
</script>
<?php endif; ?>
