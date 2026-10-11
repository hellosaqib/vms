<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth/auth.php';
require_once __DIR__ . '/../../includes/config/config.php';

global $URL;
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

logoutUser();

echo json_encode([
    'success' => true,
    'message' => 'Logout successful.'
]);
header("Location: $URL/login.php");
exit;
?>