<?php
declare(strict_types=1);

/**
 * BritDrive AI - Payment Functions
 *
 * Functions for reading sale payment data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a payment by ID.
 */
function getPaymentById(int $paymentId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sp.payment_id,
            sp.sale_id,
            sp.invoice_no,
            sp.transaction_type,
            sp.transaction_date,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            sp.status,
            sp.notes,
            sp.processed_by,
            sp.created_at,
            sp.updated_at,
            sp.paid_by
        FROM sale_payments sp
        WHERE sp.payment_id = ?
        LIMIT 1
    ");

    $stmt->execute([$paymentId]);

    $payment = $stmt->fetch();

    return $payment ?: null;
}


/**
 * Get all payments belonging to a sale.
 */
function getPaymentsBySaleId(int $saleId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sp.payment_id,
            sp.sale_id,
            sp.invoice_no,
            sp.transaction_type,
            sp.transaction_date,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            sp.status,
            sp.notes,
            sp.processed_by,
            sp.created_at,
            sp.updated_at,
            sp.paid_by
        FROM sale_payments sp
        WHERE sp.sale_id = ?
        ORDER BY sp.transaction_date ASC, sp.payment_id ASC
    ");

    $stmt->execute([$saleId]);

    return $stmt->fetchAll();
}


/**
 * Get total number of payment records.
 */
function getPaymentCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM sale_payments
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent payment records.
 */
function getRecentPayments(int $limit = 20): array
{
    global $db;

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            sp.payment_id,
            sp.sale_id,
            sp.invoice_no,
            sp.transaction_type,
            sp.transaction_date,
            sp.amount,
            sp.payment_method,
            sp.reference_number,
            sp.status,
            sp.paid_by
        FROM sale_payments sp
        ORDER BY sp.transaction_date DESC, sp.payment_id DESC
        LIMIT {$limit}
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll();
}
?>