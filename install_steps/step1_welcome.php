<?php
/**
 * Installation Step 1: Welcome & Requirements Check
 */

$requirements = checkRequirements();
$allPassed = true;
$criticalFailed = false;

foreach ($requirements as $req) {
    if (!$req['status']) {
        $allPassed = false;
        if ($req['critical']) {
            $criticalFailed = true;
        }
    }
}
?>

<div class="welcome-section">
    <div class="installation-summary">
        <h2>🎉 Welcome to the Kenyan Payroll Management System!</h2>
        <p>Thank you for choosing our enterprise-level payroll solution designed specifically for Kenyan businesses.</p>
        <div class="kenyan-flag" style="margin: 1rem auto; width: 200px;"></div>
        <p><strong>This installer will guide you through the complete setup process in just a few minutes.</strong></p>
    </div>

    <div class="feature-grid">
        <div class="feature-card">
            <h4>💰 Complete Payroll Processing</h4>
            <p>Automated calculations with full Kenyan statutory compliance including PAYE, NSSF, SHIF, and Housing Levy.</p>
        </div>
        <div class="feature-card">
            <h4>👥 Employee Management</h4>
            <p>Complete employee lifecycle management with departments, positions, and role-based access control.</p>
        </div>
        <div class="feature-card">
            <h4>📊 Advanced Analytics</h4>
            <p>Beautiful dashboards and comprehensive reporting with Kenyan flag-themed design.</p>
        </div>
        <div class="feature-card">
            <h4>🏖️ Leave & Attendance</h4>
            <p>Complete leave management system with approval workflows and real-time attendance tracking.</p>
        </div>
        <div class="feature-card">
            <h4>🎨 Content Management</h4>
            <p>Full CMS for frontend customization while maintaining beautiful Kenyan heritage design.</p>
        </div>
        <div class="feature-card">
            <h4>🔒 Enterprise Security</h4>
            <p>Role-based access control, audit logging, and comprehensive security features.</p>
        </div>
    </div>

    <h3>📋 System Requirements Check</h3>
    <p>Before we begin, let's verify that your system meets all requirements:</p>

    <div class="requirements-list">
        <?php foreach ($requirements as $name => $req): ?>
            <div class="requirement-item <?php echo $req['status'] ? 'requirement-pass' : 'requirement-fail'; ?>">
                <div>
                    <strong><?php echo htmlspecialchars($name); ?></strong>
                    <?php if ($req['critical'] && !$req['status']): ?>
                        <span style="color: #d63031; font-weight: bold;"> (CRITICAL)</span>
                    <?php endif; ?>
                    <br>
                    <small><?php echo htmlspecialchars($req['description']); ?></small>
                </div>
                <div>
                    <div><strong>Required:</strong> <?php echo htmlspecialchars($req['required']); ?></div>
                    <div><strong>Current:</strong> <?php echo htmlspecialchars($req['current']); ?></div>
                    <div style="font-size: 1.5rem;">
                        <?php echo $req['status'] ? '✅' : '❌'; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($criticalFailed): ?>
        <div class="alert alert-danger">
            <h5>❌ Critical Requirements Not Met</h5>
            <p>Some critical requirements are not satisfied. Please resolve these issues before continuing:</p>
            <ul>
                <?php foreach ($requirements as $name => $req): ?>
                    <?php if ($req['critical'] && !$req['status']): ?>
                        <li><strong><?php echo htmlspecialchars($name); ?>:</strong> <?php echo htmlspecialchars($req['description']); ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <p><strong>Common Solutions:</strong></p>
            <ul>
                <li>Update PHP to version 7.4 or higher</li>
                <li>Install missing PHP extensions through your package manager</li>
                <li>Set proper directory permissions: <code>chmod 755 config/ uploads/</code></li>
                <li>Restart your web server after making changes</li>
            </ul>
        </div>
    <?php elseif (!$allPassed): ?>
        <div class="alert alert-warning">
            <h5>⚠️ Some Optional Requirements Not Met</h5>
            <p>The system can still be installed, but some features may not work optimally. Consider addressing these issues:</p>
            <ul>
                <?php foreach ($requirements as $name => $req): ?>
                    <?php if (!$req['critical'] && !$req['status']): ?>
                        <li><strong><?php echo htmlspecialchars($name); ?>:</strong> <?php echo htmlspecialchars($req['description']); ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <h5>✅ All Requirements Met!</h5>
            <p>Excellent! Your system meets all requirements for the Kenyan Payroll Management System. You're ready to proceed with the installation.</p>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 2rem;">
        <?php if (!$criticalFailed): ?>
            <a href="install.php?step=2" class="btn-installer">
                🚀 Start Installation
            </a>
        <?php else: ?>
            <button class="btn-installer" disabled style="opacity: 0.5; cursor: not-allowed;">
                ❌ Fix Requirements First
            </button>
            <br><br>
            <a href="install.php?step=1" class="btn-installer" style="background: #6c757d;">
                🔄 Recheck Requirements
            </a>
        <?php endif; ?>
    </div>

    <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
        <h5>📚 What happens next?</h5>
        <ol>
            <li><strong>Database Configuration:</strong> Set up your MySQL database connection</li>
            <li><strong>Database Setup:</strong> Create all required tables and structure</li>
            <li><strong>Admin Account:</strong> Create your administrator account</li>
            <li><strong>Company Information:</strong> Set up your company details</li>
            <li><strong>System Configuration:</strong> Configure payroll settings and preferences</li>
            <li><strong>Complete:</strong> Access your fully functional payroll system!</li>
        </ol>
        
        <p><strong>Estimated time:</strong> 5-10 minutes</p>
        <p><strong>What you'll need:</strong></p>
        <ul>
            <li>MySQL database credentials (host, username, password)</li>
            <li>Your company information</li>
            <li>Admin user details</li>
        </ul>
    </div>
</div>

<script>
// Auto-refresh requirements check every 30 seconds if there are failures
<?php if ($criticalFailed): ?>
setTimeout(function() {
    if (confirm('Would you like to recheck the system requirements?')) {
        window.location.reload();
    }
}, 30000);
<?php endif; ?>
</script>
