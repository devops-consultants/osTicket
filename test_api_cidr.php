<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('bootstrap.php');
require_once(INCLUDE_DIR.'class.validator.php');
require_once(INCLUDE_DIR.'class.api.php');

// Test values
$test_values = [
    '192.168.1.1',           // Standard IP
    '192.168.1.0/24',        // CIDR notation
    '10.0.0.0/8',            // Large network
    '2001:db8::1',           // IPv6
    '2001:db8::/64',         // IPv6 CIDR
    'invalid',               // Invalid
];

// Test validation
foreach ($test_values as $value) {
    echo "Testing: $value\n";
    echo "  is_ip: " . (Validator::is_ip($value) ? 'PASS' : 'FAIL') . "\n";
    echo "  is_valid_cidr: " . (Validator::is_valid_cidr($value) ? 'PASS' : 'FAIL') . "\n";
    
    // Test API validation logic
    $errors = [];
    $test_vars = [
        'ipaddr' => $value,
        'isactive' => 1,
        'can_create_tickets' => 1,
        'can_exec_cron' => 0,
        'notes' => 'Test API key'
    ];
    
    // This is a mock validation that mimics what happens in save() but doesn't actually save
    if(!$test_vars['ipaddr'] || (!Validator::is_ip($test_vars['ipaddr']) && !Validator::is_valid_cidr($test_vars['ipaddr'])))
        $errors['ipaddr'] = 'Valid IP or CIDR notation required (e.g. 192.168.1.1 or 192.168.1.0/24)';

    echo "  API validation: " . (empty($errors) ? 'PASS' : 'FAIL - ' . $errors['ipaddr']) . "\n\n";
}
