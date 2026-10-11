<?php
declare(strict_types=1);

/**
 * BritDrive AI - Reservation Functions
 *
 * Functions for reading sale reservation data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a reservation by ID.
 */
function getReservationById(int $reservationId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sr.id,
            sr.sale_id,
            sr.deposit_amount,
            sr.status,
            sr.cancelled_at,
            sr.cancelled_by,
            sr.cancellation_reason,
            sr.notes,
            sr.created_by,
            sr.created_at
        FROM sale_reservations sr
        WHERE sr.id = ?
        LIMIT 1
    ");

    $stmt->execute([$reservationId]);

    $reservation = $stmt->fetch();

    return $reservation ?: null;
}


/**
 * Get all reservations belonging to a sale.
 */
function getReservationsBySaleId(int $saleId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            sr.id,
            sr.sale_id,
            sr.deposit_amount,
            sr.status,
            sr.cancelled_at,
            sr.cancelled_by,
            sr.cancellation_reason,
            sr.notes,
            sr.created_by,
            sr.created_at
        FROM sale_reservations sr
        WHERE sr.sale_id = ?
        ORDER BY sr.created_at DESC, sr.id DESC
    ");

    $stmt->execute([$saleId]);

    return $stmt->fetchAll();
}


/**
 * Get total number of reservation records.
 */
function getReservationCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM sale_reservations
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent reservations.
 */
function getRecentReservations(int $limit = 20): array
{
    global $db;

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            sr.id,
            sr.sale_id,
            sr.deposit_amount,
            sr.status,
            sr.cancelled_at,
            sr.cancelled_by,
            sr.cancellation_reason,
            sr.notes,
            sr.created_by,
            sr.created_at
        FROM sale_reservations sr
        ORDER BY sr.created_at DESC, sr.id DESC
        LIMIT {$limit}
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll();
}
?>