<?php
/**
 * Installation Step 2: Database Configuration
 */

// Default values for common setups
$defaults = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'kenyan_payroll',
    'username' => 'root',
    'password' => ''
];

// Get saved values or use defaults
$dbConfig = $_SESSION['db_config'] ?? $defaults;
?>

<div class="database-config-section">
    <h2>🗄️ Database Configuration</h2>
    <p>Configure your MySQL database connection. The installer will create the database and all required tables automatically.</p>

    <div class="alert alert-warning">
        <h5>📋 Before You Continue</h5>
        <p>Make sure you have:</p>
        <ul>
            <li>✅ MySQL server running (XAMPP, WAMP, MAMP, or standalone)</li>
            <li>✅ Database credentials (username and password)</li>
            <li>✅ Permission to create databases (or pre-created database)</li>
        </ul>
    </div>

    <form method="POST" action="install.php?step=2" id="databaseForm">
        <div class="form-group">
            <label for="host" class="form-label">Database Host</label>
            <input type="text" class="form-control" id="host" name="host" 
                   value="<?php echo htmlspecialchars($dbConfig['host']); ?>" required>
            <small class="form-text">Usually 'localhost' for local development</small>
        </div>

        <div class="form-group">
            <label for="port" class="form-label">Database Port</label>
            <input type="number" class="form-control" id="port" name="port" 
                   value="<?php echo htmlspecialchars($dbConfig['port']); ?>" required>
            <small class="form-text">Default MySQL port is 3306</small>
        </div>

        <div class="form-group">
            <label for="database" class="form-label">Database Name</label>
            <input type="text" class="form-control" id="database" name="database" 
                   value="<?php echo htmlspecialchars($dbConfig['database']); ?>" required>
            <small class="form-text">Will be created if it doesn't exist</small>
        </div>

        <div class="form-group">
            <label for="username" class="form-label">Database Username</label>
            <input type="text" class="form-control" id="username" name="username" 
                   value="<?php echo htmlspecialchars($dbConfig['username']); ?>" required>
            <small class="form-text">MySQL user with database creation privileges</small>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Database Password</label>
            <input type="password" class="form-control" id="password" name="password" 
                   value="<?php echo htmlspecialchars($dbConfig['password']); ?>">
            <small class="form-text">Leave empty if no password is set (common in XAMPP)</small>
        </div>

        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            <h5>🔧 Common Database Setups</h5>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div class="setup-card" onclick="usePreset('xampp')" style="cursor: pointer; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; transition: all 0.3s ease;">
                    <h6>🟢 XAMPP (Default)</h6>
                    <p><strong>Host:</strong> localhost<br>
                    <strong>Port:</strong> 3306<br>
                    <strong>Username:</strong> root<br>
                    <strong>Password:</strong> (empty)</p>
                </div>
                
                <div class="setup-card" onclick="usePreset('mamp')" style="cursor: pointer; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; transition: all 0.3s ease;">
                    <h6>🔵 MAMP</h6>
                    <p><strong>Host:</strong> localhost<br>
                    <strong>Port:</strong> 8889<br>
                    <strong>Username:</strong> root<br>
                    <strong>Password:</strong> root</p>
                </div>
                
                <div class="setup-card" onclick="usePreset('production')" style="cursor: pointer; padding: 1rem; border: 2px solid #e9ecef; border-radius: 8px; transition: all 0.3s ease;">
                    <h6>🔴 Production Server</h6>
                    <p><strong>Host:</strong> Custom<br>
                    <strong>Port:</strong> 3306<br>
                    <strong>Username:</strong> Custom<br>
                    <strong>Password:</strong> Required</p>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <button type="button" onclick="testConnection()" class="btn-installer" style="background: #17a2b8; margin-right: 1rem;">
                🔍 Test Connection
            </button>
            <button type="submit" class="btn-installer">
                ➡️ Continue to Database Setup
            </button>
        </div>
    </form>

    <div id="connectionResult" style="margin-top: 1rem;"></div>

    <div style="margin-top: 2rem; padding: 1rem; background: #e8f5e8; border-radius: 8px; border-left: 4px solid var(--kenya-green);">
        <h5>💡 Troubleshooting Tips</h5>
        <ul>
            <li><strong>Connection refused:</strong> Make sure MySQL server is running</li>
            <li><strong>Access denied:</strong> Check username and password</li>
            <li><strong>Unknown database:</strong> The installer will create it automatically</li>
            <li><strong>Port issues:</strong> Check if MySQL is running on a different port</li>
        </ul>
        
        <h6>🔧 Quick Fixes:</h6>
        <ul>
            <li><strong>XAMPP:</strong> Start Apache and MySQL in XAMPP Control Panel</li>
            <li><strong>MAMP:</strong> Start servers and check port settings</li>
            <li><strong>Command Line:</strong> <code>mysql -u root -p</code> to test connection</li>
        </ul>
    </div>
</div>

<script>
function usePreset(type) {
    const presets = {
        xampp: {
            host: 'localhost',
            port: '3306',
            username: 'root',
            password: ''
        },
        mamp: {
            host: 'localhost',
            port: '8889',
            username: 'root',
            password: 'root'
        },
        production: {
            host: '',
            port: '3306',
            username: '',
            password: ''
        }
    };
    
    const preset = presets[type];
    if (preset) {
        document.getElementById('host').value = preset.host;
        document.getElementById('port').value = preset.port;
        document.getElementById('username').value = preset.username;
        document.getElementById('password').value = preset.password;
        
        // Highlight selected preset
        document.querySelectorAll('.setup-card').forEach(card => {
            card.style.borderColor = '#e9ecef';
            card.style.background = 'white';
        });
        event.target.closest('.setup-card').style.borderColor = 'var(--kenya-green)';
        event.target.closest('.setup-card').style.background = 'var(--kenya-light-green)';
    }
}

function testConnection() {
    const formData = new FormData(document.getElementById('databaseForm'));
    const resultDiv = document.getElementById('connectionResult');
    
    resultDiv.innerHTML = '<div class="spinner"></div><p>Testing database connection...</p>';
    
    fetch('install_test_db.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h5>✅ Connection Successful!</h5>
                    <p>${data.message}</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h5>❌ Connection Failed</h5>
                    <p>${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <h5>❌ Test Failed</h5>
                <p>Could not test connection: ${error.message}</p>
            </div>
        `;
    });
}

// Auto-fill database name based on common patterns
document.getElementById('host').addEventListener('change', function() {
    if (this.value === 'localhost' && document.getElementById('database').value === '') {
        document.getElementById('database').value = 'kenyan_payroll';
    }
});
</script>

<style>
.setup-card:hover {
    border-color: var(--kenya-green) !important;
    background: var(--kenya-light-green) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,107,63,0.1);
}

.form-text {
    color: #6c757d;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}
</style>
