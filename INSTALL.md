# 🇰🇪 Kenyan Payroll Management System - Installation Guide

## 🚀 Quick Installation

### **Step 1: Clone the Repository**
```bash
git clone https://github.com/Oragwel/kenyan-payroll-system.git
cd kenyan-payroll-system
```

### **Step 2: Set Up Web Server**

#### **Option A: XAMPP (Recommended for Development)**
1. Install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services
3. Copy project to `htdocs/kenyan-payroll/`
4. Access: `http://localhost/kenyan-payroll/install.php`

#### **Option B: PHP Built-in Server**
```bash
php -S localhost:8000
# Access: http://localhost:8000/install.php
```

#### **Option C: Production Server**
1. Upload files to your web server
2. Ensure PHP 7.4+ and MySQL 5.7+ are available
3. Set proper file permissions
4. Access: `https://yourdomain.com/install.php`

### **Step 3: Run the Installation Wizard**

Navigate to `install.php` in your browser and follow the guided setup:

1. **🏠 Welcome & Requirements Check**
   - Verifies PHP version, extensions, and permissions
   - Shows system compatibility status

2. **🗄️ Database Configuration**
   - Configure MySQL connection settings
   - Test database connectivity
   - Create database automatically

3. **⚙️ Database Setup**
   - Creates all required tables
   - Sets up relationships and indexes
   - Configures database structure

4. **👑 Admin Account Creation**
   - Create your administrator user
   - Set secure login credentials
   - Configure admin permissions

5. **🏢 Company Information**
   - Enter your company details
   - Configure Kenyan statutory information
   - Set up compliance data

6. **🎯 System Configuration**
   - Review all settings
   - Complete final setup
   - Initialize system defaults

7. **🎉 Installation Complete**
   - Access your payroll system
   - Login with admin credentials
   - Start managing payroll!

## 📋 System Requirements

### **Minimum Requirements**
- **PHP**: 7.4+ (8.0+ recommended)
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache/Nginx with mod_rewrite
- **Memory**: 128MB+ PHP memory limit
- **Storage**: 500MB+ available space

### **Required PHP Extensions**
- PDO and PDO_MySQL
- JSON
- Session
- OpenSSL (recommended)
- Mbstring (recommended)

### **File Permissions**
- `config/` directory: writable
- `uploads/` directory: writable
- `backups/` directory: writable (created automatically)

## 🔧 Manual Installation (Advanced)

If you prefer manual setup:

### **1. Database Setup**
```sql
CREATE DATABASE kenyan_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### **2. Configuration**
Copy `config/database.php.example` to `config/database.php` and update:
```php
private $host = 'localhost';
private $db_name = 'kenyan_payroll';
private $username = 'your_username';
private $password = 'your_password';
```

### **3. Run Installation**
Access `install.php` to complete the setup process.

## 🛠️ Troubleshooting

### **Common Issues**

#### **Database Connection Failed**
- Verify MySQL is running
- Check database credentials
- Ensure database exists or user has CREATE privileges

#### **Permission Denied**
```bash
# Fix file permissions
chmod 755 config/ uploads/
chown -R www-data:www-data /path/to/project/
```

#### **PHP Extensions Missing**
```bash
# Ubuntu/Debian
sudo apt-get install php-pdo php-mysql php-json php-mbstring

# CentOS/RHEL
sudo yum install php-pdo php-mysql php-json php-mbstring
```

#### **Memory Limit Issues**
Update `php.ini`:
```ini
memory_limit = 256M
max_execution_time = 300
```

### **XAMPP Specific Issues**

#### **Apache Won't Start**
- Check if port 80 is in use
- Change to port 8080 in httpd.conf
- Run XAMPP as administrator

#### **MySQL Won't Start**
- Check if port 3306 is in use
- Stop other MySQL services
- Check XAMPP error logs

## 🔒 Security Considerations

### **After Installation**
1. **Remove installer files** (optional):
   ```bash
   rm install.php install_test_db.php
   rm -rf install_steps/
   ```

2. **Set strong passwords** for all user accounts

3. **Configure SSL/HTTPS** for production environments

4. **Regular backups** using the built-in backup system

5. **Keep system updated** with latest security patches

## 📞 Support

### **Installation Help**
- Check the main README.md for detailed documentation
- Review system requirements carefully
- Ensure all PHP extensions are installed
- Verify database permissions

### **Common Solutions**
- **Blank page**: Check PHP error logs
- **Database errors**: Verify connection settings
- **Permission errors**: Check file/folder permissions
- **Memory errors**: Increase PHP memory limit

## 🎯 Next Steps

After successful installation:

1. **Login** with your admin credentials
2. **Configure company settings** in System Settings
3. **Add departments and positions**
4. **Register employees**
5. **Set up payroll periods**
6. **Process your first payroll**

## 🇰🇪 Kenyan Compliance

The system comes pre-configured with:
- ✅ **PAYE tax rates** (2024)
- ✅ **NSSF contribution rates**
- ✅ **SHIF rates and minimums**
- ✅ **Housing Levy calculations**
- ✅ **Statutory report formats**

---

**🎉 Congratulations!** You're now ready to use the most comprehensive Kenyan Payroll Management System available!

For detailed usage instructions, see the main [README.md](README.md) file.
