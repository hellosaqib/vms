<?php

declare(strict_types=1);

/**
 * BritDrive SalesAI
 * Proof of Concept - Sales Data Only
 */

require_once __DIR__ . '/../config/database.php';


/**
 * Get the currently logged-in user ID.
 */
function aiGetCurrentUserId(): ?int
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $userId = filter_var(
        $_SESSION['user_id'],
        FILTER_VALIDATE_INT
    );

    return ($userId !== false && $userId > 0)
        ? $userId
        : null;
}


/**
 * Get sales belonging to the current user.
 *
 * Only available when sales.view scope is "own".
 */
function aiGetMySales(PDO $db, int $limit = 50): array
{
    if (!aiCanAccessOwnSales()) {
        return [];
    }

    $userId = aiGetCurrentUserId();

    if ($userId === null) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.vehicle_id,
            s.customer_id,
            s.sale_price,
            s.sale_date,
            s.payment_method,
            s.sales_person,
            s.commission,
            s.status,
            s.deposit_received,
            s.finance_amount,
            s.balance_due,
            s.total_refunded,
            s.refund_date,
            s.finance_company_id,
            s.total_revenue,
            s.net_profit,
            s.total_cost
        FROM sales s
        WHERE s.sales_person = ?
        ORDER BY s.sale_date DESC, s.sale_id DESC
        LIMIT $limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get all sales.
 *
 * Only available when sales.view scope is "all".
 */
function aiGetAllSales(PDO $db, int $limit = 100): array
{
    if (!aiCanAccessAllSales()) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.vehicle_id,
            s.customer_id,
            s.sale_price,
            s.sale_date,
            s.payment_method,
            s.sales_person,
            s.commission,
            s.status,
            s.deposit_received,
            s.finance_amount,
            s.balance_due,
            s.total_refunded,
            s.refund_date,
            s.finance_company_id,
            s.total_revenue,
            s.net_profit,
            s.total_cost
        FROM sales s
        ORDER BY s.sale_date DESC, s.sale_id DESC
        LIMIT $limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get one sale by ID.
 *
 * Access is checked after retrieving the sale.
 */
function aiGetSale(PDO $db, int $saleId): ?array
{
    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.vehicle_id,
            s.customer_id,
            s.sale_price,
            s.sale_date,
            s.sales_person,
            s.commission,
            s.service_fee,
            s.deposit_received,
            s.delivery_charges,
            s.additional_services,
            s.finance_amount,
            s.balance_due,
            s.includes_part_exchange,
            s.status,
            s.total_refunded,
            s.refund_date,
            s.refund_reason,
            s.discount_amount,
            s.finance_company_id,
            s.total_revenue,
            s.net_profit,
            s.total_cost,
            s.notes,
            s.created_at,
            s.updated_at
        FROM sales s
        WHERE s.sale_id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$saleId]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        return null;
    }

    if (!aiCanAccessSale($sale)) {
        return null;
    }

    return $sale;
}

function aiGetSalesCount(PDO $db): int
{
    $scope = aiSalesScope();

    if ($scope === 'none') {
        throw new RuntimeException('Sales access denied.');
    }

    $sql = "SELECT COUNT(*) FROM sales";
    $params = [];

    if ($scope === 'own') {
        $userId = aiGetCurrentUserId();

        if ($userId === null) {
            throw new RuntimeException('Authenticated user not found.');
        }

        $sql .= " WHERE sales_person = ?";
        $params[] = $userId;
    } elseif ($scope !== 'all') {
        throw new RuntimeException(
            "Sales scope '{$scope}' is not currently supported by the SalesAI POC."
        );
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}
?>