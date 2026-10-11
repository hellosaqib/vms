<?php

/**
 * SalesAI Access Control
 *
 * Controls what SalesAI is allowed to access or do
 * for the currently authenticated user.
 *
 * Authentication and permission calculation are handled by:
 *     includes/auth/auth.php
 *     includes/auth/permissions.php
 *
 * SalesAI must use the existing effective-permission system.
 *
 * VMS operational data remains in the `britdrive` database.
 * Authentication / roles / permissions remain in `britdrive_ai`.
 */


/**
 * Check whether SalesAI is available to the current user.
 *
 * Authentication should already have been handled before
 * SalesAI is reached.
 */
function aiIsAvailable(): bool
{
    return !empty($_SESSION['user_id']);
}


/**
 * Check whether SalesAI can access a particular data area.
 *
 * This function does not query the VMS database.
 * It only checks the existing permission system.
 */
function aiCanAccess(string $area): bool
{
    switch ($area) {

        case 'sales':
            return hasPermission('sales.view');

        case 'vehicles':
            return hasPermission('vehicles.view');

        case 'customers':
            return hasPermission('customers.view');

        case 'tasks':
            return hasPermission('tasks.view');

        case 'finance':
            return hasPermission('finance.view');

        case 'returns':
            return hasPermission('returns.view');

        case 'auctions':
            return hasPermission('auctions.view');

        case 'finance_companies':
            return hasPermission('finance_companies.view');

        case 'commission':
            return hasPermission('commission.view');

        case 'earning_goals':
            return hasPermission('earning_goals.view');

        default:
            return false;
    }
}


/**
 * Determine the effective SalesAI access scope for sales.
 *
 * Possible values:
 *     none
 *     own
 *     team
 *     branch
 *     all
 *     custom
 *
 * The permission system determines the scope.
 * SalesAI does not check user IDs or roles directly.
 */
function aiSalesScope(): string
{
    return getPermissionScope('sales.view');
}


/**
 * Check whether SalesAI can access all sales.
 */
function aiCanAccessAllSales(): bool
{
    return aiSalesScope() === 'all';
}


/**
 * Check whether SalesAI can access the current user's own sales.
 *
 * At this stage this deliberately checks only the "own" scope.
 * Team, branch and custom scopes will be handled when their
 * corresponding business rules are implemented.
 */
function aiCanAccessOwnSales(): bool
{
    return aiSalesScope() === 'own';
}


/**
 * Check whether SalesAI has any level of sales access.
 */
function aiCanViewSales(): bool
{
    return aiSalesScope() !== 'none';
}


/**
 * Check whether SalesAI can access a specific sales record.
 *
 * This function only makes the access decision.
 * The actual sales record must be retrieved through the
 * controlled data layer in includes/ai/data.php.
 *
 * @param array $sale
 */
function aiCanAccessSale(array $sale): bool
{
    $scope = aiSalesScope();

    /*
     * No sales permission.
     */
    if ($scope === 'none') {
        return false;
    }

    /*
     * Full sales access.
     */
    if ($scope === 'all') {
        return true;
    }

    /*
     * Own sales access.
     *
     * sales.sales_person contains the VMS user identifier.
     */
    if ($scope === 'own') {

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        if (!isset($sale['sales_person'])) {
            return false;
        }

        return (int) $sale['sales_person'] === (int) $_SESSION['user_id'];
    }

    /*
     * Team, branch and custom scopes will be implemented
     * once their business rules are defined.
     *
     * Until then, deny access rather than risk exposing data.
     */
    return false;
}
?>