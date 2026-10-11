<?php
declare(strict_types=1);

/**
 * BritDrive AI - Sales Functions
 *
 * Functions for reading sales data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../auth/permissions.php';

/**
 * Get a single sale by ID.
 */
function getSaleById(int $saleId): ?array
{
    global $db;

    $stmt = $db->prepare("
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
            s.service_fee,
            s.deposit_received,
            s.delivery_charges,
            s.additional_services,
            s.balance_due,
            s.finance_amount,
            s.finance_company_id,
            s.includes_part_exchange,
            s.px_vehicle_id,
            s.part_exchange_allowance,
            s.discount_amount,
            s.total_revenue,
            s.total_cost,
            s.net_profit,
            s.status,
            s.total_refunded,
            s.refund_date,
            s.refund_reason,
            s.notes,
            s.created_at,
            s.updated_at,

            v.regnumber,
            v.make AS vehicle_make,
            v.model AS vehicle_model,
            v.year AS vehicle_year,

            c.first_name AS customer_first_name,
            c.last_name AS customer_last_name,

            u.full_name AS salesperson_name,

            fc.company_name AS finance_company_name

        FROM sales s

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

        LEFT JOIN customers c
            ON c.customer_id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.sales_person

        LEFT JOIN finance_companies fc
            ON fc.finance_company_id = s.finance_company_id

        WHERE s.sale_id = ?

        LIMIT 1
    ");

    $stmt->execute([$saleId]);

    $sale = $stmt->fetch();

    return $sale ?: null;
}


/**
 * Get the total number of sales records.
 */
function getSalesCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM sales
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent sales visible to the current user.
 *
 * Administrators and other authorised roles can see all sales.
 * Salespeople can see only their own sales.
 */
function getRecentSales(int $limit = 20): array
{
    global $db;

    $user = getCurrentUser();

    if (!$user) {
        return [];
    }

    if (!hasPermission('sales.view')) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $where = [];
    $params = [];

    /*
     * Current business rule:
     * Salespeople can only see their own sales.
     */
    if ($user['role_code'] === 'salesperson') {
        $where[] = 's.sales_person = ?';
        $params[] = (int) $user['id'];
    }

    $whereSql = '';

    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

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
            s.finance_amount,
            s.finance_company_id,
            s.total_revenue,
            s.total_cost,
            s.net_profit,
            s.status,
            s.total_refunded,

            v.regnumber,
            v.make AS vehicle_make,
            v.model AS vehicle_model,

            c.first_name AS customer_first_name,
            c.last_name AS customer_last_name,

            u.full_name AS salesperson_name,

            fc.company_name AS finance_company_name

        FROM sales s

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

        LEFT JOIN customers c
            ON c.customer_id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.sales_person

        LEFT JOIN finance_companies fc
            ON fc.finance_company_id = s.finance_company_id

        {$whereSql}

        ORDER BY s.sale_date DESC, s.sale_id DESC

        LIMIT {$limit}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}
?>