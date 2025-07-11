# Kenyan Payroll Management System

A comprehensive monolithic payroll management system built with PHP and MySQL, specifically designed for Kenyan employment structure and statutory compliance requirements.

## Features

### Core Functionality
- **Employee Management**: Complete employee lifecycle management with Kenyan employment law compliance
- **Payroll Processing**: Automated calculation of salaries, allowances, and statutory deductions
- **Statutory Compliance**: Built-in support for PAYE, NSSF, NHIF/SHIF, and Housing Levy calculations
- **Leave Management**: Track and manage employee leave applications and balances
- **Attendance Tracking**: Monitor employee attendance and working hours
- **Reporting**: Generate comprehensive payroll and statutory reports

### Kenyan Statutory Compliance
- **PAYE Tax Calculation**: Automated calculation based on current Kenyan tax brackets
- **NSSF Contributions**: 6% of pensionable pay (max KES 18,000)
- **NHIF/SHIF Contributions**: Tiered contribution system based on gross pay
- **Housing Levy**: 1.5% of gross pay as per Kenyan regulations
- **Personal Relief**: KES 2,400 monthly personal relief
- **Insurance & Pension Relief**: Support for tax relief on insurance and pension contributions

### User Roles
- **Administrator**: Full system access and user management
- **HR Manager**: Employee and payroll management
- **Accountant**: Financial reporting and payroll oversight
- **Employee**: View personal payslips and leave information

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Installation

### 1. Clone or Download
```bash
git clone <repository-url>
cd kenyan-payroll-system
```

### 2. Database Setup
1. Create a MySQL database named `kenyan_payroll`
2. Import the database schema:
```sql
mysql -u username -p kenyan_payroll < database/schema.sql
```
3. Import sample data (optional):
```sql
mysql -u username -p kenyan_payroll < database/sample_data.sql
```

### 3. Configuration
1. Update database credentials in `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'kenyan_payroll';
private $username = 'your_username';
private $password = 'your_password';
```

2. Update application settings in `config/config.php` if needed

### 4. Web Server Setup
1. Point your web server document root to the project directory
2. Ensure PHP has write permissions to the `uploads/` directory
3. Enable PHP extensions: PDO, PDO_MySQL

### 5. Access the System
1. Open your web browser and navigate to the application URL
2. Default login credentials (if using sample data):
   - **Admin**: username: `admin`, password: `password`
   - **HR Manager**: username: `hr_manager`, password: `password`

## Directory Structure

```
kenyan-payroll-system/
├── assets/
│   ├── css/
│   │   └── style.css          # Custom styles
│   └── js/
│       └── main.js            # JavaScript functionality
├── config/
│   ├── config.php             # Application configuration
│   └── database.php           # Database connection
├── database/
│   ├── schema.sql             # Database schema
│   └── sample_data.sql        # Sample data
├── includes/
│   ├── functions.php          # Core functions
│   ├── header.php             # Navigation header
│   └── sidebar.php            # Sidebar navigation
├── pages/
│   ├── auth.php               # Authentication
│   ├── dashboard.php          # Main dashboard
│   ├── employees.php          # Employee management
│   ├── payroll.php            # Payroll processing
│   └── 404.php                # Error page
├── uploads/                   # File uploads directory
├── index.php                  # Main entry point
└── README.md                  # This file
```

## Usage

### Employee Management
1. Navigate to **Employees** → **Add Employee**
2. Fill in employee details including:
   - Personal information (Name, ID Number, Contact)
   - Employment details (Hire Date, Department, Position)
   - Salary information
3. Assign allowances and deductions as needed

### Payroll Processing
1. Go to **Payroll** → **Process New Payroll**
2. Set the payroll period and pay date
3. The system will automatically:
   - Calculate gross pay (basic salary + allowances)
   - Compute statutory deductions (PAYE, NSSF, NHIF, Housing Levy)
   - Apply other deductions
   - Generate payslips for all active employees

### Statutory Calculations

#### PAYE Tax Brackets (2024)
- 0 - 24,000: 10%
- 24,001 - 32,333: 25%
- 32,334 - 500,000: 30%
- 500,001 - 800,000: 32.5%
- 800,001+: 35%

#### NSSF Contribution
- 6% of pensionable pay (maximum KES 18,000)

#### NHIF/SHIF Contribution
- Tiered system based on gross pay (KES 150 - 1,700)

#### Housing Levy
- 1.5% of gross pay

### Reports
The system generates various reports including:
- Payroll summaries
- Statutory deduction reports
- Employee reports
- Leave reports

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- Role-based access control
- Session management
- Input sanitization and validation

## Customization

### Adding New Allowance Types
1. Go to **Payroll** → **Allowances**
2. Create new allowance types
3. Specify if taxable and pensionable

### Modifying Statutory Rates
Update the constants in `config/config.php`:
- `PAYE_RATES`: Tax brackets
- `NSSF_RATE`: NSSF contribution rate
- `SHIF_RATES`: NHIF/SHIF contribution rates
- `HOUSING_LEVY_RATE`: Housing levy rate

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Permission Denied Errors**
   - Check file permissions on uploads directory
   - Ensure web server has read access to all files

3. **Calculation Errors**
   - Verify statutory rates in `config/config.php`
   - Check employee allowances and deductions setup

## Support

For support and questions:
- Review the documentation
- Check the sample data for examples
- Verify configuration settings

## License

This project is developed for educational and business use. Please ensure compliance with local employment laws and regulations.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Changelog

### Version 1.0.0
- Initial release
- Core payroll functionality
- Kenyan statutory compliance
- Employee management
- Basic reporting

---

**Note**: This system is designed specifically for Kenyan employment laws and statutory requirements. Ensure all calculations and compliance features meet current legal requirements before production use.
