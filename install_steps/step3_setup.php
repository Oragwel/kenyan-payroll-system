<?php
/**
 * Installation Step 3: Database Setup & Table Creation
 */

// Check if we have database configuration
if (!isset($_SESSION['db_config'])) {
    header('Location: install.php?step=2');
    exit;
}

$dbConfig = $_SESSION['db_config'];
$setupComplete = false;
$setupError = null;

// Handle database setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_database'])) {
    try {
        $result = setupDatabase();
        if ($result['success']) {
            $setupComplete = true;
            $success[] = "Database and tables created successfully!";
        } else {
            $setupError = $result['error'];
            $errors[] = $result['error'];
        }
    } catch (Exception $e) {
        $setupError = $e->getMessage();
        $errors[] = $e->getMessage();
    }
}
?>

<div class="database-setup-section">
    <h2>⚙️ Database Setup & Table Creation</h2>
    <p>Now we'll create the database and all required tables for your Kenyan Payroll Management System.</p>

    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
        <h5>📋 Database Configuration Summary</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div><strong>Host:</strong> <?php echo htmlspecialchars($dbConfig['host']); ?></div>
            <div><strong>Port:</strong> <?php echo htmlspecialchars($dbConfig['port']); ?></div>
            <div><strong>Database:</strong> <?php echo htmlspecialchars($dbConfig['database']); ?></div>
            <div><strong>Username:</strong> <?php echo htmlspecialchars($dbConfig['username']); ?></div>
        </div>
        <a href="install.php?step=2" style="color: var(--kenya-green); text-decoration: none; font-size: 0.9rem;">
            ← Change database settings
        </a>
    </div>

    <?php if (!$setupComplete && !$setupError): ?>
        <div class="alert alert-warning">
            <h5>🚀 Ready to Create Database Structure</h5>
            <p>The following will be created:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 1rem 0;">
                <div>
                    <h6>📊 Core Tables:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>companies</li>
                        <li>users</li>
                        <li>employees</li>
                        <li>departments</li>
                        <li>job_positions</li>
                    </ul>
                </div>
                <div>
                    <h6>💰 Payroll Tables:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>payroll_periods</li>
                        <li>payroll_records</li>
                        <li>allowances</li>
                        <li>deductions</li>
                    </ul>
                </div>
                <div>
                    <h6>🏖️ HR Tables:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>leave_types</li>
                        <li>leave_applications</li>
                        <li>attendance</li>
                    </ul>
                </div>
                <div>
                    <h6>⚙️ System Tables:</h6>
                    <ul style="font-size: 0.9rem;">
                        <li>cms_settings</li>
                        <li>system_settings</li>
                        <li>audit_logs</li>
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST" style="text-align: center;">
            <button type="submit" name="setup_database" class="btn-installer" style="font-size: 1.1rem; padding: 1rem 2rem;">
                🗄️ Create Database & Tables
            </button>
        </form>

    <?php elseif ($setupError): ?>
        <div class="alert alert-danger">
            <h5>❌ Database Setup Failed</h5>
            <p><?php echo htmlspecialchars($setupError); ?></p>
            
            <h6>🔧 Common Solutions:</h6>
            <ul>
                <li><strong>Permission denied:</strong> Ensure user has CREATE privileges</li>
                <li><strong>Database exists:</strong> Drop existing database or use different name</li>
                <li><strong>Connection lost:</strong> Check if MySQL server is still running</li>
                <li><strong>Syntax errors:</strong> Ensure MySQL version 5.7+ is being used</li>
            </ul>
            
            <div style="margin-top: 1rem;">
                <a href="install.php?step=2" class="btn-installer" style="background: #6c757d; margin-right: 1rem;">
                    ← Back to Database Config
                </a>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="setup_database" class="btn-installer">
                        🔄 Try Again
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-success">
            <h5>✅ Database Setup Complete!</h5>
            <p>All database tables have been created successfully. Your Kenyan Payroll Management System database is ready!</p>
            
            <div style="margin-top: 1rem;">
                <h6>📊 Created Successfully:</h6>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.9rem;">
                    <div>✅ Database: <?php echo htmlspecialchars($dbConfig['database']); ?></div>
                    <div>✅ 15+ Tables created</div>
                    <div>✅ Indexes and relationships</div>
                    <div>✅ Default data structure</div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="install.php?step=4" class="btn-installer">
                👑 Create Admin Account →
            </a>
        </div>
    <?php endif; ?>

    <div style="margin-top: 2rem; padding: 1rem; background: #e8f5e8; border-radius: 8px; border-left: 4px solid var(--kenya-green);">
        <h5>🔒 Security Note</h5>
        <p>The database structure includes:</p>
        <ul>
            <li><strong>Encrypted passwords</strong> using PHP's password_hash()</li>
            <li><strong>Role-based access control</strong> for different user types</li>
            <li><strong>Audit logging</strong> for tracking system changes</li>
            <li><strong>Data validation</strong> constraints and foreign keys</li>
            <li><strong>Secure session management</strong> for user authentication</li>
        </ul>
    </div>

    <?php if ($setupComplete): ?>
        <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
            <h5>📝 Next Steps</h5>
            <p>With the database ready, you'll now:</p>
            <ol>
                <li><strong>Create Admin Account:</strong> Set up your administrator user</li>
                <li><strong>Company Information:</strong> Configure your company details</li>
                <li><strong>System Settings:</strong> Set payroll rates and preferences</li>
                <li><strong>Start Using:</strong> Access your complete payroll system!</li>
            </ol>
        </div>
    <?php endif; ?>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_database']) && !$setupError): ?>
<script>
// Auto-redirect to next step after successful setup
setTimeout(function() {
    window.location.href = 'install.php?step=4';
}, 3000);

// Show progress animation
let progress = 0;
const progressInterval = setInterval(function() {
    progress += 10;
    if (progress <= 100) {
        console.log('Database setup progress: ' + progress + '%');
    } else {
        clearInterval(progressInterval);
    }
}, 200);
</script>
<?php endif; ?>
