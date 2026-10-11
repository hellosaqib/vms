<?php 
require_once __DIR__ . '/includes/auth/auth.php';
echo '<pre>';
echo "auth.php loaded\n";

startAuthSession();

echo "startAuthSession() completed\n";

echo "Session status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "\nCurrent user:\n";
print_r(getCurrentUser());
echo "SESSION:\n";
print_r($_SESSION);

echo '</pre>';
?>