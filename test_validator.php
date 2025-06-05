<?php
// Display all PHP errors and warnings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define INCLUDE_DIR if it's not already defined
if (!defined('INCLUDE_DIR'))
    define('INCLUDE_DIR', dirname(__FILE__) . '/include/');

// Try loading the validator class directly
try {
    require_once(INCLUDE_DIR . 'class.validator.php');
    echo "Successfully loaded class.validator.php\n";
} catch (Throwable $e) {
    echo "Error loading class.validator.php: " . $e->getMessage() . "\n";
}
?>
