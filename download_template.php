<?php
/**
 * CSV Template Download for Employee Bulk Import
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !hasPermission('hr')) {
    http_response_code(403);
    exit('Access denied');
}

$type = $_GET['type'] ?? 'employees';

if ($type === 'employees') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_import_template.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Core CSV headers (always included)
    $headers = [
        'first_name',
        'middle_name',
        'last_name',
        'id_number',
        'email',
        'phone',
        'hire_date',
        'basic_salary',
        'department_name',
        'position_title',
        'employment_type'
    ];

    // Optional headers (only include if columns exist in database)
    $optionalHeaders = [
        'kra_pin',
        'nssf_number',
        'nhif_number',
        'bank_code',
        'bank_name',
        'bank_branch',
        'account_number'
    ];

    // Check which optional columns exist
    foreach ($optionalHeaders as $column) {
        try {
            if (isset($database) && $database) {
                $database->query("SELECT $column FROM employees LIMIT 1");
                $headers[] = $column;
            }
        } catch (Exception $e) {
            // Column doesn't exist, don't include it
        }
    }

    // Write headers
    fputcsv($output, $headers);

    // Sample data rows - build dynamically based on available headers
    $baseSampleData = [
        [
            'first_name' => 'John',
            'middle_name' => 'Doe',
            'last_name' => 'Smith',
            'id_number' => '12345678',
            'email' => 'john.smith@company.co.ke',
            'phone' => '+254700123456',
            'hire_date' => '2024-01-15',
            'basic_salary' => '50000.00',
            'department_name' => 'Information Technology',
            'position_title' => 'Software Developer',
            'employment_type' => 'permanent',
            'kra_pin' => 'A123456789B',
            'nssf_number' => '123456',
            'nhif_number' => '654321',
            'bank_code' => '11',
            'bank_name' => 'Equity Bank',
            'bank_branch' => 'Nairobi Branch',
            'account_number' => '1234567890'
        ],
        [
            'first_name' => 'Jane',
            'middle_name' => 'Mary',
            'last_name' => 'Doe',
            'id_number' => '87654321',
            'email' => 'jane.doe@company.co.ke',
            'phone' => '+254701234567',
            'hire_date' => '2024-02-01',
            'basic_salary' => '75000.00',
            'department_name' => 'Human Resources',
            'position_title' => 'HR Manager',
            'employment_type' => 'permanent',
            'kra_pin' => 'B987654321C',
            'nssf_number' => '789012',
            'nhif_number' => '210987',
            'bank_code' => '01',
            'bank_name' => 'Kenya Commercial Bank (KCB)',
            'bank_branch' => 'Westlands Branch',
            'account_number' => '0987654321'
        ],
        [
            'first_name' => 'Peter',
            'middle_name' => '',
            'last_name' => 'Kamau',
            'id_number' => '11223344',
            'email' => 'peter.kamau@company.co.ke',
            'phone' => '+254702345678',
            'hire_date' => '2024-03-01',
            'basic_salary' => '35000.00',
            'department_name' => 'Finance',
            'position_title' => 'Accountant',
            'employment_type' => 'contract',
            'kra_pin' => 'C112233445D',
            'nssf_number' => '345678',
            'nhif_number' => '876543',
            'bank_code' => '12',
            'bank_name' => 'Cooperative Bank of Kenya',
            'bank_branch' => 'CBD Branch',
            'account_number' => '5432109876'
        ]
    ];

    // Build sample data rows based on available headers
    $sampleData = [];
    foreach ($baseSampleData as $baseRow) {
        $row = [];
        foreach ($headers as $header) {
            $row[] = $baseRow[$header] ?? '';
        }
        $sampleData[] = $row;
    }

    // Write sample data
    foreach ($sampleData as $row) {
        fputcsv($output, $row);
    }

    // Close the file pointer
    fclose($output);
    exit;
}

// If invalid type, return error
http_response_code(400);
exit('Invalid template type');
?>
