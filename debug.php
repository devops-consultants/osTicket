<?php
// Display all PHP errors and warnings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the main osTicket bootstrapping file
require_once('bootstrap.php');

// Print out basic info
echo "PHP version: " . phpversion() . "<br>";
echo "Current working directory: " . getcwd() . "<br>";
echo "Included files:<br>";
$included_files = get_included_files();
foreach($included_files as $file) {
    echo "$file<br>";
}

// Try loading the validator class to see if it's causing errors
try {
    require_once(INCLUDE_DIR.'class.validator.php');
    echo "Successfully loaded class.validator.php<br>";
} catch (Exception $e) {
    echo "Error loading class.validator.php: " . $e->getMessage() . "<br>";
}
?>
