# 🇰🇪 Kenyan Payroll Management System

[![Production Ready](https://img.shields.io/badge/Status-Production%20Ready-brightgreen)](https://github.com/Oragwel/kenyan-payroll-system)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://mysql.com)
[![Kenyan Compliance](https://img.shields.io/badge/Kenyan%20Law-Compliant-green)](https://kra.go.ke)

A **comprehensive, enterprise-level payroll management system** built with PHP and MySQL, specifically designed for Kenyan employment structure and statutory compliance requirements. Features beautiful Kenyan flag-themed design and complete business functionality.

## 🎯 **PRODUCTION-READY ENTERPRISE FEATURES**

### 💼 **Complete Employee Lifecycle Management**
- **Employee Registration**: Full employee onboarding with Kenyan ID validation
- **Department Management**: Organizational structure with department heads
- **Job Positions**: Role definitions with salary ranges and career progression
- **Contract Types**: Support for Permanent, Contract, Casual, and Intern employees
- **Employee Analytics**: Comprehensive workforce insights and reporting

### 💰 **Advanced Payroll Processing**
- **Automated Calculations**: Smart payroll processing with Kenyan statutory compliance
- **Multiple Pay Periods**: Monthly, bi-weekly, and custom payroll cycles
- **Allowances & Deductions**: Flexible allowance and deduction management
- **Overtime Processing**: Automatic overtime calculations with configurable rates
- **Payslip Generation**: Professional PDF payslips with company branding

### 📊 **Comprehensive Reporting & Analytics**
- **Interactive Dashboards**: Beautiful charts with Kenyan flag color themes
- **Payroll Reports**: Detailed payroll summaries and cost analysis
- **Statutory Reports**: PAYE, NSSF, SHIF, Housing Levy compliance reports
- **Employee Reports**: Individual performance and earnings analytics
- **Export Options**: PDF, Excel, and CSV export capabilities

### 🏖️ **Leave Management System**
- **Leave Applications**: Employee self-service leave requests
- **Approval Workflow**: Multi-level approval with HR oversight
- **Leave Balance Tracking**: Real-time balance calculations and validation
- **Leave Types**: Annual, sick, maternity, paternity, and custom leave types
- **Calendar Integration**: Visual leave calendar and conflict detection

### 🕐 **Attendance Management**
- **Real-time Clock System**: Live clock in/out with timestamp validation
- **Manual Entry**: HR can add/edit attendance records
- **Hours Calculation**: Automatic working hours and overtime computation
- **Attendance Reports**: Comprehensive attendance analytics and insights
- **Mobile Responsive**: Clock in/out from any device

### ⚙️ **System Administration**
- **Content Management**: Full CMS for frontend customization
- **Settings Management**: Configurable payroll rates and system preferences
- **User Management**: Role-based access control with security features
- **Backup System**: Automated database backup and recovery
- **Audit Trails**: Complete system activity logging

### 🎨 **Beautiful Kenyan Heritage Design**
- **Flag Color Theme**: Authentic Kenyan flag colors throughout the system
- **Cultural Pride**: Professional design maintaining Kenyan heritage
- **Mobile Responsive**: Beautiful interface on all devices
- **Modern UI/UX**: Intuitive navigation with smooth animations

## 🇰🇪 **KENYAN STATUTORY COMPLIANCE**

### **PAYE Tax Calculation (2024 Rates)**
- **KES 0 - 24,000**: 10% tax rate
- **KES 24,001 - 32,333**: 25% tax rate
- **KES 32,334 - 500,000**: 30% tax rate
- **KES 500,001 - 800,000**: 32.5% tax rate
- **KES 800,001+**: 35% tax rate
- **Personal Relief**: KES 2,400 monthly

### **NSSF Contributions**
- **Rate**: 6% of pensionable pay
- **Maximum Pensionable Pay**: KES 18,000
- **Employee & Employer**: Equal contributions

### **SHIF (Social Health Insurance Fund)**
- **Rate**: 2.75% of gross pay
- **Minimum Contribution**: KES 300
- **Rounded Calculations**: Whole number contributions

### **Housing Levy**
- **Rate**: 1.5% of gross pay
- **Exemptions**: Casual and contract employees
- **Compliance**: Full KRA integration ready

### **Employment Types Support**
- **Permanent Employees**: Full statutory benefits
- **Contract Employees**: Customizable benefit packages
- **Casual Laborers**: Exempt from Housing Levy and NSSF
- **Interns**: Flexible compensation structures

## 👥 **USER ROLES & PERMISSIONS**

### **🔧 Administrator**
- Complete system access and configuration
- User management and role assignment
- System settings and backup management
- Content management and customization
- Advanced analytics and reporting

### **👔 HR Manager**
- Employee lifecycle management
- Leave approval and management
- Attendance monitoring and reporting
- Payroll oversight and validation
- Department and position management

### **👤 Employee**
- Personal dashboard with analytics
- Payslip viewing and download
- Leave application and tracking
- Attendance clock in/out
- Profile management

## 🚀 **QUICK START GUIDE**

### **System Requirements**
- **PHP**: 8.0+ (recommended) or 7.4+
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache/Nginx with mod_rewrite
- **Browser**: Modern browser (Chrome, Firefox, Safari, Edge)
- **Storage**: 500MB+ for system and data

### **⚡ Installation (5 Minutes)**

#### **1. Clone Repository**
```bash
git clone https://github.com/Oragwel/kenyan-payroll-system.git
cd kenyan-payroll-system
```

#### **2. Database Setup**
```sql
-- Create database
CREATE DATABASE kenyan_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema (auto-created on first run)
-- The system will create all required tables automatically
```

#### **3. Configuration**
Update `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'kenyan_payroll';
private $username = 'your_db_username';
private $password = 'your_db_password';
```

#### **4. Launch System**
```bash
# For development (PHP built-in server)
php -S localhost:8000

# For production, configure your web server to point to the project directory
```

#### **5. First Login**
- **URL**: `http://localhost:8000` (or your domain)
- **Default Admin**:
  - Username: `admin`
  - Password: `password`
- **Change default password immediately after first login**

### **🎯 Demo Credentials**
```
👑 Administrator:
   Username: admin
   Password: password

👔 HR Manager:
   Username: hr
   Password: password

👤 Employee:
   Username: employee
   Password: password
```

## 📁 **SYSTEM ARCHITECTURE**

### **Directory Structure**
```
kenyan-payroll-system/
├── 🎨 assets/
│   ├── css/                   # Kenyan flag-themed styles
│   ├── js/                    # Interactive functionality
│   └── images/                # System images and icons
├── ⚙️ config/
│   ├── config.php             # Application configuration
│   ├── database.php           # Database connection
│   └── constants.php          # System constants
├── 🗄️ includes/
│   ├── functions.php          # Core business logic
│   ├── auth.php               # Authentication functions
│   ├── header.php             # Navigation header
│   ├── sidebar.php            # Role-based sidebar
│   └── footer.php             # System footer
├── 📄 pages/
│   ├── auth.php               # Login/logout system
│   ├── dashboard.php          # Role-based dashboards
│   ├── employees.php          # Employee management
│   ├── payroll.php            # Payroll processing
│   ├── reports.php            # Comprehensive reporting
│   ├── leaves.php             # Leave management
│   ├── attendance.php         # Attendance tracking
│   ├── departments.php        # Department management
│   ├── positions.php          # Job positions
│   ├── payslips.php           # Payslip viewer
│   ├── settings.php           # System settings
│   ├── profile.php            # User profiles
│   ├── cms.php                # Content management
│   └── 404.php                # Error handling
├── 📤 uploads/                # File uploads (logos, documents)
├── 🔄 backups/                # System backups
├── 🌐 index.php               # Main application entry
├── 🏠 landing.html             # Dynamic landing page
├── 🧮 demo.html               # Payroll calculator demo
├── 📋 generate_landing.php    # Landing page generator
└── 📖 README.md               # This documentation
```

### **Database Schema**
- **companies**: Multi-tenant company management
- **users**: User authentication and roles
- **employees**: Complete employee records
- **departments**: Organizational structure
- **job_positions**: Role definitions and salary ranges
- **payroll_periods**: Pay period management
- **payroll_records**: Payroll calculations and history
- **allowances/deductions**: Flexible compensation components
- **leave_types**: Leave category definitions
- **leave_applications**: Leave request workflow
- **attendance**: Time tracking and hours worked
- **cms_settings**: Content management configuration
- **system_settings**: Application preferences
- **audit_logs**: Security and activity tracking

## 🎮 **USAGE GUIDE**

### **👥 Employee Management**
```
1. Navigate to "Employees" → "Add Employee"
2. Complete employee registration with:
   - Personal details (Name, ID, contacts)
   - Employment information (Department, position, salary)
   - Statutory details (KRA PIN, NSSF, NHIF numbers)
3. Set employment terms and contract type
4. Assign to department and position
5. Configure allowances and deductions
```

### **💰 Payroll Processing**
```
1. Go to "Payroll" → "Process Payroll"
2. Select payroll period (monthly/custom)
3. Add employees to payroll batch
4. Configure period-specific allowances
5. Review calculations and statutory deductions
6. Process and approve payroll
7. Generate payslips and reports
```

### **📊 Advanced Reporting**
```
1. Access "Reports & Analytics"
2. Choose report type:
   - Payroll Summary Reports
   - Statutory Compliance Reports (P9, P10)
   - Employee Analytics
3. Set filters (date range, departments, employees)
4. Generate interactive reports
5. Export to PDF/Excel for official use
```

### **🏖️ Leave Management**
```
Employee Side:
1. "My Leave Applications" → "Apply for Leave"
2. Select leave type and dates
3. Provide reason and submit

HR Side:
1. "Leave Management" → Review applications
2. Approve/reject with comments
3. Monitor leave balances and patterns
```

## 🔒 **ENTERPRISE SECURITY**

### **Multi-Layer Security**
- ✅ **Role-Based Access Control (RBAC)**
- ✅ **Session Management** with timeout
- ✅ **Input Validation** and sanitization
- ✅ **SQL Injection Prevention** with prepared statements
- ✅ **CSRF Protection** on all forms
- ✅ **Password Hashing** with bcrypt
- ✅ **Audit Logging** for all critical actions
- ✅ **File Upload Security** with type validation

### **Compliance & Privacy**
- ✅ **Data Encryption** for sensitive information
- ✅ **Backup Security** with encrypted backups
- ✅ **Access Logging** for compliance audits
- ✅ **GDPR Considerations** for data protection

## 🌟 **WHAT MAKES THIS SPECIAL**

### **🇰🇪 Authentically Kenyan**
- **Cultural Pride**: Beautiful Kenyan flag colors throughout
- **Local Compliance**: 100% compliant with Kenyan employment law
- **Currency Support**: Native KES formatting and calculations
- **Professional Design**: Enterprise-quality while honoring heritage

### **🚀 Production Ready**
- **Scalable Architecture**: Handles growing businesses
- **Performance Optimized**: Fast loading and responsive
- **Mobile Friendly**: Works perfectly on all devices
- **Enterprise Features**: Backup, audit, security, reporting

### **💡 Developer Friendly**
- **Clean Code**: Well-structured, documented PHP
- **Modular Design**: Easy to extend and customize
- **Modern Standards**: Follows PHP best practices
- **Open Source**: MIT license for flexibility

## 📈 **ROADMAP & FUTURE ENHANCEMENTS**

### **Planned Features**
- 🔄 **API Integration**: REST API for third-party integrations
- 📱 **Mobile App**: Native mobile application
- 🤖 **AI Analytics**: Predictive payroll insights
- 🌐 **Multi-Language**: Swahili and English support
- 💳 **Payment Integration**: M-Pesa and bank integrations
- 📧 **Email Notifications**: Automated payslip delivery

## 🤝 **CONTRIBUTING**

We welcome contributions! Here's how:

```bash
1. Fork the repository
2. Create feature branch: git checkout -b feature/amazing-feature
3. Commit changes: git commit -m 'Add amazing feature'
4. Push to branch: git push origin feature/amazing-feature
5. Open a Pull Request
```

### **Development Guidelines**
- Follow PSR-12 coding standards
- Maintain Kenyan flag color theme
- Add tests for new features
- Update documentation
- Ensure mobile responsiveness

## 📞 **SUPPORT & COMMUNITY**

### **Get Help**
- 📚 **Documentation**: Comprehensive guides and tutorials
- 🐛 **Issues**: Report bugs on GitHub
- 💬 **Discussions**: Community support and feature requests
- 📧 **Email**: Direct support for enterprise users

### **Community**
- ⭐ **Star** the repository if you find it useful
- 🍴 **Fork** to create your own version
- 📢 **Share** with other Kenyan businesses
- 🤝 **Contribute** to make it even better

## 📄 **LICENSE**

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

### **Commercial Use**
- ✅ Use in commercial projects
- ✅ Modify and distribute
- ✅ Private use
- ✅ Patent use

## 🎯 **VERSION HISTORY**

### **Version 2.0.0** (Current - Production Ready)
- ✅ Complete payroll management system
- ✅ Advanced analytics and reporting
- ✅ Leave and attendance management
- ✅ Content management system
- ✅ Beautiful Kenyan heritage design
- ✅ Enterprise security features
- ✅ Mobile responsive design

### **Version 1.0.0** (Initial Release)
- ✅ Basic payroll functionality
- ✅ Employee management
- ✅ Kenyan statutory compliance

---

## 🇰🇪 **PROUDLY KENYAN**

> *"This system represents the innovation and excellence of Kenyan technology. Built by Kenyans, for Kenyan businesses, with pride in our heritage and commitment to excellence."*

**Ready to revolutionize your payroll management? Get started today!** 🚀

---

**⚠️ Important**: This system is designed specifically for Kenyan payroll requirements. Always ensure compliance with current Kenyan employment and tax laws. Consult with legal and tax professionals for production deployment.
