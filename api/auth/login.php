<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');


/**
 * Send a JSON response and stop execution.
 */
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
 * Only allow POST requests.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}


/*
 * Read JSON request body.
 */
$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid request.'
    ], 400);
}


$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');


/*
 * Validate required fields.
 */
if ($username === '' || $password === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Username and password are required.'
    ], 422);
}


/*
 * Attempt login.
 */
if (!loginUser($username, $password)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid username or password.'
    ], 401);
}


/*
 * Get the authenticated user.
 */
$user = getCurrentUser();

if (!$user) {
    jsonResponse([
        'success' => false,
        'message' => 'Unable to load user account.'
    ], 500);
}


/*
 * Return only safe user information.
 */
jsonResponse([
    'success' => true,
    'message' => 'Login successful.',
    'user' => [
        'id'         => (int) $user['id'],
        'username'   => $user['username'],
        'full_name'  => $user['full_name'],
        'email'      => $user['email'],
        'role_id'    => (int) $user['role_id'],
        'role_code'  => $user['role_code'],
        'role_name'  => $user['role_name']
    ]
]);
?>