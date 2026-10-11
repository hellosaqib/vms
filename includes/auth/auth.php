<?php
declare(strict_types=1);

/**
 * BritDrive AI - Authentication
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Start the application session.
 */
function startAuthSession(): void
{

    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

/**
 * Check whether a user is currently logged in.
 */
function isLoggedIn(): bool
{
    startAuthSession();

    return isset($_SESSION['user_id']);
}

/**
 * Get the currently logged-in user.
 *
 * Returns null if nobody is logged in.
 */
function getCurrentUser(): ?array
{
    global $db;

    startAuthSession();

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.role_id,
            u.commission_rate,
            u.is_active,
            r.code AS role_code,
            r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['user_id']]);

    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        logoutUser();
        return null;
    }

    return $user;
}

/**
 * Log a user in.
 */
function loginUser(string $username, string $password): bool
{
    global $db;

    startAuthSession();

    $stmt = $db->prepare("
        SELECT
            u.id,
            u.username,
            u.password_hash,
            u.is_active
        FROM users u
        WHERE u.username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    /*
     * Prevent session fixation.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];

    /*
     * Update last login information.
     */
    $update = $db->prepare("
        UPDATE users
        SET
            last_login_at = NOW(),
            last_login_ip = ?
        WHERE id = ?
    ");

    $update->execute([
        $_SERVER['REMOTE_ADDR'] ?? null,
        $user['id']
    ]);

    return true;
}

/**
 * Log the current user out.
 */
function logoutUser(): void
{
    startAuthSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Require the user to be logged in.
 *
 * Use this at the beginning of protected pages.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}
?>