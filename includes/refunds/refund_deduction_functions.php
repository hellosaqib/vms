<?php
declare(strict_types=1);

/**
 * BritDrive AI - Refund Deduction Functions
 *
 * Functions for reading refund deduction data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a refund deduction by ID.
 */
function getRefundDeductionById(int $deductionId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            rd.deduction_id,
            rd.refund_id,
            rd.deduction_type,
            rd.description,
            rd.amount,
            rd.created_at
        FROM refund_deductions rd
        WHERE rd.deduction_id = ?
        LIMIT 1
    ");

    $stmt->execute([$deductionId]);

    $deduction = $stmt->fetch();

    return $deduction ?: null;
}


/**
 * Get all deductions belonging to a refund.
 */
function getRefundDeductions(int $refundId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            rd.deduction_id,
            rd.refund_id,
            rd.deduction_type,
            rd.description,
            rd.amount,
            rd.created_at
        FROM refund_deductions rd
        WHERE rd.refund_id = ?
        ORDER BY rd.created_at ASC, rd.deduction_id ASC
    ");

    $stmt->execute([$refundId]);

    return $stmt->fetchAll();
}


/**
 * Get total number of refund deduction records.
 */
function getRefundDeductionCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM refund_deductions
    ");

    return (int) $stmt->fetchColumn();
}
?>