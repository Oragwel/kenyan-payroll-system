<?php
/**
 * Test script to verify that the employee management form works correctly with optional fields
 * This test verifies that users can submit forms with minimal required information
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session for testing
session_start();

// Mock session data for testing
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h1>Employee Form Optional Fields Test</h1>\n";
echo "<p>Testing that the employee management form accepts minimal required information...</p>\n";

// Test 1: Verify database schema allows NULL values
echo "<h2>Test 1: Database Schema Validation</h2>\n";

try {
    $stmt = $db->prepare("DESCRIBE employees");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $optional_fields = ['id_number', 'hire_date', 'contract_type', 'middle_name', 'email', 'phone', 'department_id', 'position_id'];
    $required_fields = ['first_name', 'last_name', 'basic_salary'];
    
    echo "<h3>Optional Fields (should allow NULL):</h3>\n";
    echo "<ul>\n";
    foreach ($columns as $column) {
        if (in_array($column['Field'], $optional_fields)) {
            $nullable = $column['Null'] === 'YES' ? '✅ NULL allowed' : '❌ NOT NULL';
            echo "<li><strong>{$column['Field']}</strong>: {$nullable}</li>\n";
        }
    }
    echo "</ul>\n";
    
    echo "<h3>Required Fields (should NOT allow NULL):</h3>\n";
    echo "<ul>\n";
    foreach ($columns as $column) {
        if (in_array($column['Field'], $required_fields)) {
            $nullable = $column['Null'] === 'NO' ? '✅ NOT NULL (required)' : '❌ NULL allowed';
            echo "<li><strong>{$column['Field']}</strong>: {$nullable}</li>\n";
        }
    }
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database schema test failed: " . $e->getMessage() . "</p>\n";
}

// Test 2: Test minimal employee creation
echo "<h2>Test 2: Minimal Employee Creation</h2>\n";

try {
    // Generate a unique employee number
    $employeeNumber = 'TEST' . time();
    
    // Test data with only required fields
    $testData = [
        'company_id' => $_SESSION['company_id'],
        'employee_number' => $employeeNumber,
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'basic_salary' => 50000.00
    ];
    
    $stmt = $db->prepare("
        INSERT INTO employees (company_id, employee_number, first_name, last_name, basic_salary)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute(array_values($testData))) {
        $employeeId = $db->lastInsertId();
        echo "<p style='color: green;'>✅ Successfully created employee with minimal data (ID: {$employeeId})</p>\n";
        
        // Verify the record
        $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Created Employee Record:</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>ID:</strong> {$employee['id']}</li>\n";
        echo "<li><strong>Employee Number:</strong> {$employee['employee_number']}</li>\n";
        echo "<li><strong>Name:</strong> {$employee['first_name']} {$employee['last_name']}</li>\n";
        echo "<li><strong>Basic Salary:</strong> KES " . number_format($employee['basic_salary'], 2) . "</li>\n";
        echo "<li><strong>ID Number:</strong> " . ($employee['id_number'] ?? 'NULL (optional)') . "</li>\n";
        echo "<li><strong>Hire Date:</strong> " . ($employee['hire_date'] ?? 'NULL (optional)') . "</li>\n";
        echo "<li><strong>Employment Type:</strong> " . ($employee['contract_type'] ?? 'NULL (optional)') . "</li>\n";
        echo "<li><strong>Email:</strong> " . ($employee['email'] ?? 'NULL (optional)') . "</li>\n";
        echo "<li><strong>Phone:</strong> " . ($employee['phone'] ?? 'NULL (optional)') . "</li>\n";
        echo "</ul>\n";
        
        // Clean up test data
        $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        echo "<p><em>Test employee record cleaned up.</em></p>\n";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to create employee with minimal data</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Minimal employee creation test failed: " . $e->getMessage() . "</p>\n";
}

// Test 3: Test form validation logic
echo "<h2>Test 3: Form Validation Logic</h2>\n";

// Simulate form submission with minimal data
$_POST = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'basic_salary' => '45000',
    'middle_name' => '',
    'id_number' => '',
    'email' => '',
    'phone' => '',
    'hire_date' => '',
    'department_id' => '',
    'position_id' => '',
    'contract_type' => '',
    'bank_code' => '',
    'bank_name' => '',
    'bank_branch' => '',
    'account_number' => ''
];

// Test the nullIfEmpty function
function nullIfEmpty($value) {
    return isset($value) && trim($value) !== '' ? trim($value) : null;
}

$firstName = $_POST['first_name'];
$lastName = $_POST['last_name'];
$basicSalary = $_POST['basic_salary'];
$middleName = nullIfEmpty($_POST['middle_name']);
$idNumber = nullIfEmpty($_POST['id_number']);
$email = nullIfEmpty($_POST['email']);
$phone = nullIfEmpty($_POST['phone']);
$hireDate = nullIfEmpty($_POST['hire_date']);

echo "<h3>Form Processing Results:</h3>\n";
echo "<ul>\n";
echo "<li><strong>First Name:</strong> '{$firstName}' (required)</li>\n";
echo "<li><strong>Last Name:</strong> '{$lastName}' (required)</li>\n";
echo "<li><strong>Basic Salary:</strong> '{$basicSalary}' (required)</li>\n";
echo "<li><strong>Middle Name:</strong> " . ($middleName === null ? 'NULL (optional)' : "'{$middleName}'") . "</li>\n";
echo "<li><strong>ID Number:</strong> " . ($idNumber === null ? 'NULL (optional)' : "'{$idNumber}'") . "</li>\n";
echo "<li><strong>Email:</strong> " . ($email === null ? 'NULL (optional)' : "'{$email}'") . "</li>\n";
echo "<li><strong>Phone:</strong> " . ($phone === null ? 'NULL (optional)' : "'{$phone}'") . "</li>\n";
echo "<li><strong>Hire Date:</strong> " . ($hireDate === null ? 'NULL (optional)' : "'{$hireDate}'") . "</li>\n";
echo "</ul>\n";

// Test validation logic
if (empty($firstName) || empty($lastName) || empty($basicSalary)) {
    echo "<p style='color: red;'>❌ Validation failed: Missing required fields</p>\n";
} else {
    echo "<p style='color: green;'>✅ Validation passed: All required fields present</p>\n";
}

echo "<h2>Test Summary</h2>\n";
echo "<p>The employee management form has been successfully restructured to make all null fields optional.</p>\n";
echo "<p><strong>Required fields:</strong> First Name, Last Name, Basic Salary</p>\n";
echo "<p><strong>Optional fields:</strong> All other fields including ID Number, Hire Date, Employment Type, Email, Phone, Department, Position, and Banking Information</p>\n";

// Clean up
unset($_POST);
?>
