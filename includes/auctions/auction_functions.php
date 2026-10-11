<?php
declare(strict_types=1);

/**
 * BritDrive AI - Auction Functions
 *
 * Functions for reading auction data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get an auction by ID.
 */
function getAuctionById(int $auctionId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            a.auction_id,
            a.auction_name,
            a.address,
            a.city,
            a.contact_phone,
            a.contact_email,
            a.website,
            a.notes,
            a.created
        FROM auctions a
        WHERE a.auction_id = ?
        LIMIT 1
    ");

    $stmt->execute([$auctionId]);

    $auction = $stmt->fetch();

    return $auction ?: null;
}


/**
 * Get all auctions.
 */
function getAuctions(): array
{
    global $db;

    $stmt = $db->query("
        SELECT
            a.auction_id,
            a.auction_name,
            a.address,
            a.city,
            a.contact_phone,
            a.contact_email,
            a.website,
            a.notes,
            a.created
        FROM auctions a
        ORDER BY a.auction_name ASC
    ");

    return $stmt->fetchAll();
}


/**
 * Get total number of auctions.
 */
function getAuctionCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM auctions
    ");

    return (int) $stmt->fetchColumn();
}
?>