<?php
/**
 * Help & Support Page
 */

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=auth');
    exit;
}
?>

<style>
:root {
    --kenya-black: #000000;
    --kenya-red: #ce1126;
    --kenya-white: #ffffff;
    --kenya-green: #006b3f;
    --kenya-light-green: #e8f5e8;
    --kenya-dark-green: #004d2e;
}

.help-hero {
    background: linear-gradient(135deg, var(--kenya-green) 0%, var(--kenya-dark-green) 100%);
    color: white;
    padding: 2rem 0;
    margin: -30px -30px 30px -30px;
    border-radius: 0 0 20px 20px;
}

.help-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
}

.help-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.faq-item {
    border-bottom: 1px solid #eee;
    padding: 1rem 0;
}

.faq-item:last-child {
    border-bottom: none;
}

.faq-question {
    font-weight: 600;
    color: var(--kenya-dark-green);
    cursor: pointer;
    margin-bottom: 0.5rem;
}

.faq-answer {
    color: #666;
    display: none;
}

.contact-card {
    background: linear-gradient(135deg, var(--kenya-light-green), #f8f9fa);
    border: 2px solid var(--kenya-green);
    border-radius: 15px;
    padding: 1.5rem;
}
</style>

<div class="container-fluid">
    <!-- Help Hero Section -->
    <div class="help-hero">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-question-circle me-3"></i>
                        Help & Support Center
                    </h2>
                    <p class="mb-0 opacity-75">
                        Get help with the Kenyan Payroll System
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-15 rounded p-3">
                        <h6 class="mb-1">Need Assistance?</h6>
                        <small class="opacity-75">We're here to help!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Help -->
        <div class="col-lg-8">
            <div class="help-card">
                <div class="p-4">
                    <h4 class="mb-3">
                        <i class="fas fa-rocket text-primary me-2"></i>
                        Quick Start Guide
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-users text-success me-2"></i>Employee Management</h6>
                            <ul class="list-unstyled">
                                <li>• Add new employees</li>
                                <li>• Manage departments</li>
                                <li>• Set job positions</li>
                                <li>• Configure allowances</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-calculator text-warning me-2"></i>Payroll Processing</h6>
                            <ul class="list-unstyled">
                                <li>• Create payroll periods</li>
                                <li>• Process employee salaries</li>
                                <li>• Generate payslips</li>
                                <li>• Export reports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="help-card">
                <div class="p-4">
                    <h4 class="mb-3">
                        <i class="fas fa-question text-info me-2"></i>
                        Frequently Asked Questions
                    </h4>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <i class="fas fa-chevron-right me-2"></i>
                            How do I add a new employee?
                        </div>
                        <div class="faq-answer">
                            Go to Employee Management → Employees → Add Employee. Fill in the required information including personal details, job information, and salary details.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <i class="fas fa-chevron-right me-2"></i>
                            How do I process payroll?
                        </div>
                        <div class="faq-answer">
                            Use Quick Payroll for simple processing or Advanced Payroll for detailed control. Create a payroll period, select employees, and process their salaries.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <i class="fas fa-chevron-right me-2"></i>
                            How are Kenyan taxes calculated?
                        </div>
                        <div class="faq-answer">
                            The system automatically calculates PAYE tax, NSSF, SHIF, and Housing Levy according to current Kenyan tax regulations. Personal relief and employment type exemptions are applied automatically.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <i class="fas fa-chevron-right me-2"></i>
                            Can employees view their own payslips?
                        </div>
                        <div class="faq-answer">
                            Yes, employees can log in to view and download their payslips. They can also apply for leave and view their attendance records.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <i class="fas fa-chevron-right me-2"></i>
                            How do I generate reports?
                        </div>
                        <div class="faq-answer">
                            Go to Reports & Analytics to generate various reports including payroll summaries, employee reports, and statutory compliance reports.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="col-lg-4">
            <div class="contact-card">
                <h5 class="mb-3">
                    <i class="fas fa-headset text-primary me-2"></i>
                    Contact Support
                </h5>
                
                <div class="mb-3">
                    <strong>Email Support:</strong><br>
                    <a href="mailto:support@kenyanpayroll.com">support@kenyanpayroll.com</a>
                </div>
                
                <div class="mb-3">
                    <strong>Phone Support:</strong><br>
                    <a href="tel:+254700000000">+254 700 000 000</a>
                </div>
                
                <div class="mb-3">
                    <strong>Business Hours:</strong><br>
                    Monday - Friday: 8:00 AM - 6:00 PM<br>
                    Saturday: 9:00 AM - 1:00 PM
                </div>
                
                <hr>
                
                <h6><i class="fas fa-book text-success me-2"></i>Documentation</h6>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-decoration-none">User Manual</a></li>
                    <li><a href="#" class="text-decoration-none">API Documentation</a></li>
                    <li><a href="#" class="text-decoration-none">Video Tutorials</a></li>
                </ul>
            </div>

            <!-- System Info -->
            <div class="help-card">
                <div class="p-3">
                    <h6><i class="fas fa-info-circle text-info me-2"></i>System Information</h6>
                    <small class="text-muted">
                        <strong>Version:</strong> 2.0.0<br>
                        <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?><br>
                        <strong>Your Role:</strong> <?php echo ucfirst($_SESSION['user_role']); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFaq(element) {
    const answer = element.nextElementSibling;
    const icon = element.querySelector('i');
    
    if (answer.style.display === 'block') {
        answer.style.display = 'none';
        icon.className = 'fas fa-chevron-right me-2';
    } else {
        answer.style.display = 'block';
        icon.className = 'fas fa-chevron-down me-2';
    }
}
</script>
