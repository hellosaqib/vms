<?php
declare(strict_types=1);

/**
 * BritDrive AI - Commission Functions
 *
 * Functions for reading sales commission data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a commission by ID.
 */
function getCommissionById(int $commissionId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sc.commission_id,
            sc.sale_id,
            sc.user_id,
            sc.commission_rate,
            sc.commission_amount,
            sc.status,
            sc.created_at,
            sc.approved_at,
            sc.paid_at,
            sc.notes,

            s.invoice_no,
            s.vehicle_id,
            s.sale_date,
            s.sale_price,
            s.status AS sale_status,

            u.full_name AS salesperson_name

        FROM sales_commissions sc

        LEFT JOIN sales s
            ON s.sale_id = sc.sale_id

        LEFT JOIN users u
            ON u.id = sc.user_id

        WHERE sc.commission_id = ?

        LIMIT 1
    ");

    $stmt->execute([$commissionId]);

    $commission = $stmt->fetch();

    return $commission ?: null;
}


/**
 * Get all commissions belonging to a sale.
 */
function getCommissionsBySaleId(int $saleId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sc.commission_id,
            sc.sale_id,
            sc.user_id,
            sc.commission_rate,
            sc.commission_amount,
            sc.status,
            sc.created_at,
            sc.approved_at,
            sc.paid_at,
            sc.notes,

            u.full_name AS salesperson_name

        FROM sales_commissions sc

        LEFT JOIN users u
            ON u.id = sc.user_id

        WHERE sc.sale_id = ?

        ORDER BY sc.created_at DESC, sc.commission_id DESC
    ");

    $stmt->execute([$saleId]);

    return $stmt->fetchAll();
}


/**
 * Get all commissions belonging to a salesperson.
 */
function getCommissionsByUserId(int $userId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sc.commission_id,
            sc.sale_id,
            sc.user_id,
            sc.commission_rate,
            sc.commission_amount,
            sc.status,
            sc.created_at,
            sc.approved_at,
            sc.paid_at,
            sc.notes,

            s.invoice_no,
            s.vehicle_id,
            s.sale_date,
            s.sale_price,
            s.status AS sale_status

        FROM sales_commissions sc

        LEFT JOIN sales s
            ON s.sale_id = sc.sale_id

        WHERE sc.user_id = ?

        ORDER BY sc.created_at DESC, sc.commission_id DESC
    ");

    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}


/**
 * Get total number of commission records.
 */
function getCommissionCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM sales_commissions
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent commission records.
 */
function getRecentCommissions(int $limit = 20): array
{
    global $db;

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            sc.commission_id,
            sc.sale_id,
            sc.user_id,
            sc.commission_rate,
            sc.commission_amount,
            sc.status,
            sc.created_at,
            sc.approved_at,
            sc.paid_at,

            s.invoice_no,
            s.vehicle_id,
            s.sale_date,

            u.full_name AS salesperson_name

        FROM sales_commissions sc

        LEFT JOIN sales s
            ON s.sale_id = sc.sale_id

        LEFT JOIN users u
            ON u.id = sc.user_id

        ORDER BY sc.created_at DESC, sc.commission_id DESC

        LIMIT {$limit}
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll();
}
?>