<?php

declare(strict_types=1);

/**
 * BritDrive AI - Permissions
 */

require_once __DIR__ . '/auth.php';


/**
 * Check whether the current user has a permission.
 *
 * Individual user permissions take priority over role permissions.
 *
 * Example:
 * hasPermission('sales.view')
 */
function hasPermission(string $permissionCode): bool
{
    global $db;

    $user = getCurrentUser();

    if (!$user) {
        return false;
    }

    $userId = (int) $user['id'];
    $roleId = (int) $user['role_id'];

    /*
     * Administrator role has full access.
     */
    if ($roleId === 6) {
        return true;
    }

    /*
     * First check for an individual user override.
     *
     * Only active, non-expired overrides are considered.
     */
    $stmt = $db->prepare("
        SELECT
            up.effect
        FROM user_permissions up
        INNER JOIN permissions p
            ON p.id = up.permission_id
        WHERE up.user_id = ?
          AND p.code = ?
          AND p.is_active = 1
          AND (
                up.expires_at IS NULL
                OR up.expires_at > NOW()
              )
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        $permissionCode
    ]);

    $userPermission = $stmt->fetch();

    if ($userPermission) {
        return $userPermission['effect'] === 'allow';
    }

    /*
     * No individual override.
     *
     * Check the user's role permissions.
     */
    $stmt = $db->prepare("
        SELECT
            rp.permission_id
        FROM role_permissions rp
        INNER JOIN permissions p
            ON p.id = rp.permission_id
        WHERE rp.role_id = ?
          AND p.code = ?
          AND p.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        $roleId,
        $permissionCode
    ]);

    return (bool) $stmt->fetch();
}

/**
 * Get the effective scope for a permission.
 *
 * Returns:
 * own | team | branch | all | custom | none
 */
function getPermissionScope(string $permissionCode): string
{
    global $db;

    $user = getCurrentUser();

    if (!$user) {
        return 'none';
    }

    $userId = (int) $user['id'];
    $roleId = (int) $user['role_id'];

    // Administrator has full access.
    if ($roleId === 6) {
        return 'all';
    }

    /*
     * 1. Check user-specific permission override.
     *
     * A user override takes priority over the role permission.
     */
    $sql = "
        SELECT
            up.effect,
            ps.code AS scope_code
        FROM user_permissions up
        INNER JOIN permissions p
            ON p.id = up.permission_id
        LEFT JOIN permission_scopes ps
            ON ps.id = up.scope_id
        WHERE up.user_id = ?
          AND p.code = ?
          AND p.is_active = 1
          AND (
                up.expires_at IS NULL
                OR up.expires_at > NOW()
              )
        ORDER BY up.id DESC
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $permissionCode]);

    $override = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($override) {

        // Explicit deny means no access.
        if ($override['effect'] === 'deny') {
            return 'none';
        }

        // Allow with a defined scope.
        return $override['scope_code'] ?: 'none';
    }

    /*
     * 2. No user override.
     *    Use the user's role permission.
     */
    $sql = "
        SELECT
            ps.code AS scope_code
        FROM role_permissions rp
        INNER JOIN permissions p
            ON p.id = rp.permission_id
        INNER JOIN permission_scopes ps
            ON ps.id = rp.scope_id
        WHERE rp.role_id = ?
          AND p.code = ?
          AND p.is_active = 1
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$roleId, $permissionCode]);

    $rolePermission = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rolePermission) {
        return $rolePermission['scope_code'];
    }

    return 'none';
}
/**
 * Require the current user to have a specific permission.
 *
 * If the user does not have the permission,
 * return HTTP 403 and stop execution.
 *
 * Example:
 * requirePermission('sales.view');
 */
function requirePermission(string $permissionCode): void
{
    if (!hasPermission($permissionCode)) {
        http_response_code(403);

        echo '403 - Access Denied';
        exit;
    }
}
?>