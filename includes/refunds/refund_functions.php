<?php
declare(strict_types=1);

/**
 * BritDrive AI - Refund Functions
 *
 * Functions for reading refund data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a refund by ID.
 */
function getRefundById(int $refundId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            r.refund_id,
            r.sale_id,
            r.original_invoice_no,
            r.refund_type,
            r.max_refundable_before,
            r.total_deductions,
            r.net_refund_amount,
            r.max_refundable_after,
            r.vehicle_returned,
            r.px_vehicle_returned,
            r.vehicle_status_after,
            r.refund_reason,
            r.repair_cost_estimate,
            r.customer_agreement,
            r.status,
            r.processed_by,
            r.processed_date,
            r.created_by,
            r.created_at,
            r.updated_at
        FROM refunds r
        WHERE r.refund_id = ?
        LIMIT 1
    ");

    $stmt->execute([$refundId]);

    $refund = $stmt->fetch();

    return $refund ?: null;
}


/**
 * Get all refunds belonging to a sale.
 */
function getRefundsBySaleId(int $saleId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            r.refund_id,
            r.sale_id,
            r.original_invoice_no,
            r.refund_type,
            r.max_refundable_before,
            r.total_deductions,
            r.net_refund_amount,
            r.max_refundable_after,
            r.vehicle_returned,
            r.px_vehicle_returned,
            r.vehicle_status_after,
            r.refund_reason,
            r.repair_cost_estimate,
            r.customer_agreement,
            r.status,
            r.processed_by,
            r.processed_date,
            r.created_by,
            r.created_at,
            r.updated_at
        FROM refunds r
        WHERE r.sale_id = ?
        ORDER BY r.created_at DESC, r.refund_id DESC
    ");

    $stmt->execute([$saleId]);

    return $stmt->fetchAll();
}


/**
 * Get total number of refund records.
 */
function getRefundCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM refunds
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent refunds.
 */
function getRecentRefunds(int $limit = 20): array
{
    global $db;

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            r.refund_id,
            r.sale_id,
            r.original_invoice_no,
            r.refund_type,
            r.net_refund_amount,
            r.total_deductions,
            r.refund_reason,
            r.status,
            r.processed_date,
            r.created_at
        FROM refunds r
        ORDER BY r.created_at DESC, r.refund_id DESC
        LIMIT {$limit}
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll();
}
?>