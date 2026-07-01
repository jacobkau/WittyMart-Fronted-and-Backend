
<?php
require_once 'includes/config.php';

echo "Session status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Environment: " . APP_ENV . "\n";
echo "Production: " . (IS_PRODUCTION ? 'Yes' : 'No') . "\n";
echo "DB connected: " . (testDatabaseConnection() ? 'Yes' : 'No') . "\n";
?>
