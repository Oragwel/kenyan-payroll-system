<?php
/**
 * Dynamic Landing Page Generator
 * Generates landing.html based on CMS settings
 */

require_once 'config/database.php';
require_once 'config/config.php';

/**
 * Get CMS settings from database
 */
function getCMSSettings() {
    global $db;
    
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM cms_settings");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Default values
    $defaults = [
        'hero_title' => 'Kenyan Payroll Management System',
        'hero_subtitle' => 'Comprehensive payroll solution designed for Kenyan employment structure and statutory compliance requirements.',
        'feature_1_title' => 'Statutory Compliance',
        'feature_1_description' => 'Automated PAYE, NSSF, SHIF & Housing Levy calculations',
        'feature_2_title' => 'Employee Management',
        'feature_2_description' => 'Complete employee lifecycle management',
        'feature_3_title' => 'Advanced Reporting',
        'feature_3_description' => 'Generate comprehensive payroll and statutory reports',
        'feature_4_title' => 'Mobile Responsive',
        'feature_4_description' => 'Access your payroll system from any device',
        'footer_text' => '🇰🇪 Proudly Kenyan • Built for Kenya • Compliant with Kenyan Law 🇰🇪',
        'company_name' => 'Your Company Name',
        'company_logo' => '',
        'primary_color' => '#006b3f',
        'secondary_color' => '#ce1126',
        'accent_color' => '#000000',
        'background_color' => '#ffffff',
        'text_color' => '#1f2937',
        'enable_kenyan_theme' => '1',
        'show_flag_ribbons' => '1'
    ];
    
    return array_merge($defaults, $settings);
}

/**
 * Generate dynamic landing page HTML
 */
function generateLandingPage() {
    $settings = getCMSSettings();
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($settings['hero_title']) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Dynamic Colors from CMS */
            --kenya-black: ' . $settings['accent_color'] . ';
            --kenya-red: ' . $settings['secondary_color'] . ';
            --kenya-white: ' . $settings['background_color'] . ';
            --kenya-green: ' . $settings['primary_color'] . ';
            --kenya-light-green: ' . adjustBrightness($settings['primary_color'], 0.9) . ';
            --kenya-dark-green: ' . adjustBrightness($settings['primary_color'], -0.2) . ';
            --text-color: ' . $settings['text_color'] . ';
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: \'Inter\', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-section::before {
            content: \'\';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><pattern id="grid" width="50" height="50" patternUnits="userSpaceOnUse"><path d="M 50 0 L 0 0 0 50" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>\');
            opacity: 0.2;
        }';

    if ($settings['show_flag_ribbons']) {
        $html .= '
        /* Kenyan Flag Ribbon */
        .hero-section::after {
            content: \'\';
            position: absolute;
            top: -50px;
            left: -100px;
            width: 120%;
            height: 200px;
            background: linear-gradient(
                45deg,
                var(--kenya-black) 0%,
                var(--kenya-black) 20%,
                var(--kenya-red) 20%,
                var(--kenya-red) 35%,
                var(--kenya-white) 35%,
                var(--kenya-white) 50%,
                var(--kenya-red) 50%,
                var(--kenya-red) 65%,
                var(--kenya-green) 65%,
                var(--kenya-green) 100%
            );
            transform: rotate(-8deg);
            opacity: 0.15;
            z-index: 1;
        }

        .kenyan-ribbon-1 {
            position: absolute;
            bottom: -50px;
            right: -100px;
            width: 120%;
            height: 150px;
            background: linear-gradient(
                -45deg,
                transparent 0%,
                var(--kenya-green) 20%,
                var(--kenya-white) 40%,
                var(--kenya-red) 60%,
                var(--kenya-black) 80%,
                transparent 100%
            );
            transform: rotate(12deg);
            opacity: 0.1;
            z-index: 1;
        }';
    }

    $html .= '
        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--kenya-white);
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .feature-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.15);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--kenya-red), var(--kenya-green));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Login Card */
        .login-card {
            background: var(--kenya-white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            border: 1px solid rgba(16,185,129,0.1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: \'\';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(
                90deg, 
                var(--kenya-black) 0%,
                var(--kenya-red) 25%,
                var(--kenya-white) 50%,
                var(--kenya-green) 75%,
                var(--kenya-green) 100%
            );
        }

        .btn-login {
            background: linear-gradient(135deg, var(--kenya-green), var(--kenya-dark-green));
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--kenya-white);
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,107,63,0.4);
            color: var(--kenya-white);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .login-card {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">';

    if ($settings['show_flag_ribbons']) {
        $html .= '
        <!-- Kenyan Flag Decorative Ribbons -->
        <div class="kenyan-ribbon-1"></div>';
    }

    $html .= '
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <!-- Left Side - Hero Content -->
                <div class="col-lg-7 col-md-6">
                    <div class="hero-content">
                        <h1 class="hero-title">
                            <i class="fas fa-calculator me-3" style="color: var(--kenya-red);"></i>
                            ' . htmlspecialchars($settings['hero_title']) . '
                        </h1>
                        <p class="hero-subtitle">
                            ' . htmlspecialchars($settings['hero_subtitle']) . '
                        </p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-shield-alt text-white"></i>
                                    </div>
                                    <h5 class="text-white mb-2">' . htmlspecialchars($settings['feature_1_title']) . '</h5>
                                    <p class="text-white-50 mb-0">' . htmlspecialchars($settings['feature_1_description']) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-users text-white"></i>
                                    </div>
                                    <h5 class="text-white mb-2">' . htmlspecialchars($settings['feature_2_title']) . '</h5>
                                    <p class="text-white-50 mb-0">' . htmlspecialchars($settings['feature_2_description']) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-chart-line text-white"></i>
                                    </div>
                                    <h5 class="text-white mb-2">' . htmlspecialchars($settings['feature_3_title']) . '</h5>
                                    <p class="text-white-50 mb-0">' . htmlspecialchars($settings['feature_3_description']) . '</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-card">
                                    <div class="feature-icon">
                                        <i class="fas fa-mobile-alt text-white"></i>
                                    </div>
                                    <h5 class="text-white mb-2">' . htmlspecialchars($settings['feature_4_title']) . '</h5>
                                    <p class="text-white-50 mb-0">' . htmlspecialchars($settings['feature_4_description']) . '</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Login Form -->
                <div class="col-lg-5 col-md-6">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <h2>
                                <i class="fas fa-lock text-success me-2"></i>
                                Secure Admin Login
                            </h2>
                            <p class="text-muted">Access your payroll management dashboard</p>
                        </div>

                        <form method="POST" action="index.php?page=auth&action=login">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-key me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Access Dashboard
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                For demo purposes, use: <strong>admin</strong> / <strong>password</strong>
                            </small>
                        </div>

                        <!-- Kenyan Pride Footer -->
                        <div class="text-center mt-3 p-2" style="background: linear-gradient(90deg, var(--kenya-black) 0%, var(--kenya-red) 25%, var(--kenya-white) 50%, var(--kenya-green) 75%, var(--kenya-green) 100%); border-radius: 8px; opacity: 0.8;">
                            <small style="color: var(--kenya-white); font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">
                                ' . htmlspecialchars($settings['footer_text']) . '
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

    return $html;
}

/**
 * Adjust color brightness
 */
function adjustBrightness($hex, $percent) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent)));
    $g = max(0, min(255, $g + ($g * $percent)));
    $b = max(0, min(255, $b + ($b * $percent)));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Generate and save the landing page
if (isset($_GET['generate']) || php_sapi_name() === 'cli') {
    $html = generateLandingPage();
    file_put_contents('landing.html', $html);
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Landing page generated successfully!']);
    } else {
        echo "Landing page generated successfully!\n";
    }
} else {
    // Show generation interface
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Landing Page Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center">
            <h2>Landing Page Generator</h2>
            <p class="text-muted">Generate a dynamic landing page based on your CMS settings</p>
            <a href="?generate=1" class="btn btn-success btn-lg">
                <i class="fas fa-magic"></i> Generate Landing Page
            </a>
        </div>
    </div>
</body>
</html>';
}
?>
