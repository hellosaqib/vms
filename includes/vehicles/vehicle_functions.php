<?php
declare(strict_types=1);

/**
 * BritDrive AI - Vehicle Functions
 *
 * Functions for reading vehicle data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';

/**
 * Get a single vehicle by ID.
 *
 * @param int $vehicleId
 * @return array|null
 */
function getVehicleById(int $vehicleId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            v.vehicle_id,
            v.VIN,
            v.engine_no,
            v.regnumber,
            v.make,
            v.model,
            v.year,
            v.trim,
            v.mileage,
            v.color,
            v.body_style,
            v.transmission,
            v.fuel_type,
            v.auction_id,
            v.purchase_date,
            v.purchase_price,
            v.sale_date,
            v.sale_price,
            v.status,
            v.engine_size,
            v.owners,
            v.v5_status,
            v.number_of_services,
            v.keys_count,
            v.logbook_status,
            v.last_v5c_issued,
            v.tax_status,
            v.tax_due_date,
            v.mot_expiry,
            v.notes,
            v.created_at,
            v.created_by,
            v.updated_at
        FROM vehicles v
        WHERE v.vehicle_id = ?
        LIMIT 1
    ");

    $stmt->execute([$vehicleId]);

    $vehicle = $stmt->fetch();

    return $vehicle ?: null;
}

/**
 * Get all currently available vehicles.
 *
 * @return array
 */
function getAvailableVehicles(): array
{
    global $db;

    $stmt = $db->query("
        SELECT
            v.vehicle_id,
            v.VIN,
            v.regnumber,
            v.make,
            v.model,
            v.year,
            v.trim,
            v.mileage,
            v.color,
            v.transmission,
            v.fuel_type,
            v.purchase_date,
            v.purchase_price,
            v.sale_price,
            v.status,
            v.mot_expiry,
            v.tax_due_date
        FROM vehicles v
        WHERE v.status = 'available'
        ORDER BY v.created_at DESC
    ");

    return $stmt->fetchAll();
}

/**
 * Get the total number of vehicles.
 *
 * @return int
 */
function getVehicleCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM vehicles
    ");

    return (int) $stmt->fetchColumn();
}
?>