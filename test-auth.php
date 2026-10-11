<?php

session_start();

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/auth.php';
require_once __DIR__ . '/includes/auth/permissions.php';

echo '<pre>';

echo "DB connection: ";
echo isset($db) && $db instanceof PDO ? "OK\n" : "FAILED\n";

echo "\nCurrent user:\n";
print_r(getCurrentUser());

echo "\nPermissions:\n";

$permissions = [
    'vehicles.view',
    'sales.view',
    'customers.view',
    'tasks.view',
    'finance.view',
    'auctions.view',
    'finance_companies.view',
    'commission.view',
    'earning_goals.view',
];

foreach ($permissions as $permission) {
    echo $permission . ': ';
    echo hasPermission($permission) ? 'ALLOW' : 'DENY';
    echo "\n";
}

echo '</pre>';

echo '<pre>';
echo "SESSION:\n";
print_r($_SESSION);
echo '</pre>';
?>