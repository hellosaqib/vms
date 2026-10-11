<?php
declare(strict_types=1);

/**
 * BritDrive AI - Customer Functions
 *
 * Functions for reading customer data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a customer by ID.
 */
function getCustomerById(int $customerId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.post_code,
            c.created_at
        FROM customers c
        WHERE c.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$customerId]);

    $customer = $stmt->fetch();

    return $customer ?: null;
}


/**
 * Get total number of customers.
 */
function getCustomerCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM customers
    ");

    return (int) $stmt->fetchColumn();
}


/**
 * Get recent customers.
 */
function getRecentCustomers(int $limit = 20): array
{
    global $db;

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.city,
            c.post_code,
            c.created_at
        FROM customers c
        ORDER BY c.created_at DESC, c.customer_id DESC
        LIMIT {$limit}
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll();
}


/**
 * Get the sales history for a customer.
 *
 * Uses sales.customer_id because vehicles.customer_id
 * is not currently reliable for identifying buyers.
 */
function getCustomerSales(int $customerId): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            s.sale_id,
            s.invoice_no,
            s.vehicle_id,
            s.sale_price,
            s.sale_date,
            s.payment_method,
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
            v.year AS vehicle_year,

            fc.company_name AS finance_company_name

        FROM sales s

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

        LEFT JOIN finance_companies fc
            ON fc.finance_company_id = s.finance_company_id

        WHERE s.customer_id = ?

        ORDER BY s.sale_date DESC, s.sale_id DESC
    ");

    $stmt->execute([$customerId]);

    return $stmt->fetchAll();
}