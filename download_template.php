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

    // CSV headers
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
        'contract_type',
        'kra_pin',
        'nssf_number',
        'nhif_number'
    ];

    // Write headers
    fputcsv($output, $headers);

    // Sample data rows
    $sampleData = [
        [
            'John',
            'Doe',
            'Smith',
            '12345678',
            'john.smith@company.co.ke',
            '+254700123456',
            '2024-01-15',
            '50000.00',
            'Information Technology',
            'Software Developer',
            'permanent',
            'A123456789B',
            '123456',
            '654321'
        ],
        [
            'Jane',
            'Mary',
            'Doe',
            '87654321',
            'jane.doe@company.co.ke',
            '+254701234567',
            '2024-02-01',
            '75000.00',
            'Human Resources',
            'HR Manager',
            'permanent',
            'B987654321C',
            '789012',
            '210987'
        ],
        [
            'Peter',
            '',
            'Kamau',
            '11223344',
            'peter.kamau@company.co.ke',
            '+254702345678',
            '2024-03-01',
            '35000.00',
            'Finance',
            'Accountant',
            'contract',
            'C112233445D',
            '345678',
            '876543'
        ]
    ];

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
