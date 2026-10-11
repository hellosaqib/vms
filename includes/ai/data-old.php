<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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


/* =========================================================
 * VEHICLES
 * ========================================================= */


function aiGetVehicle(PDO $db, int $vehicleId): ?array
{
    $sql = "
        SELECT
            v.*
        FROM vehicles v
        WHERE v.vehicle_id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$vehicleId]);

    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    return $vehicle ?: null;
}


/**
 * Search vehicles by registration, make, model or VIN.
 */
function aiSearchVehicles(
    PDO $db,
    string $search,
    int $limit = 20
): array {
    $search = trim($search);

    if ($search === '') {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            v.*
        FROM vehicles v
        WHERE
            v.regnumber LIKE ?
            OR v.make LIKE ?
            OR v.model LIKE ?
            OR v.VIN LIKE ?
        ORDER BY v.vehicle_id DESC
        LIMIT $limit
    ";

    $term = '%' . $search . '%';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $term,
        $term,
        $term,
        $term
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get currently available vehicles.
 */
function aiGetAvailableVehicles(
    PDO $db,
    int $limit = 100
): array {
    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            v.*
        FROM vehicles v
        WHERE v.status = 'available'
        ORDER BY v.vehicle_id DESC
        LIMIT $limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* =========================================================
 * SALES
 * ========================================================= */


/**
 * Get sales belonging to the current user.
 *
 * This function is specifically for "own" sales access.
 */
function aiGetMySales(
    PDO $db,
    int $limit = 50
): array {
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
 * IMPORTANT:
 * This function only returns all sales when the user's
 * effective sales.view scope is "all".
 */
function aiGetAllSales(
    PDO $db,
    int $limit = 100
): array {
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
 * Get one sale by sale ID.
 *
 * The sale is retrieved first and then checked against
 * the user's effective sales scope.
 */
function aiGetSale(
    PDO $db,
    int $saleId
): ?array {
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

    /*
     * Enforce record-level access.
     */
    if (!aiCanAccessSale($sale)) {
        return null;
    }

    return $sale;
}

/* =========================================================
 * CUSTOMERS
 * ========================================================= */
/* =========================================================
 * CUSTOMERS
 * ========================================================= */


/* =========================================================
 * CUSTOMERS
 * ========================================================= */


/**
 * Get a customer by customer ID.
 *
 * Access is controlled by the customers.view permission.
 *
 * Customer ownership is NOT determined by customers.created_by.
 * The created_by field refers to the user who created the
 * customer record, not the salesperson responsible for a sale.
 */
function aiGetCustomer(
    PDO $db,
    int $customerId
): ?array {
    if (!aiCanAccess('customers')) {
        return null;
    }

    $sql = "
        SELECT
            c.customer_id,
            c.title,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.state,
            c.post_code,
            c.notes,
            c.created_at
        FROM customers c
        WHERE c.customer_id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$customerId]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    return $customer ?: null;
}


/**
 * Search customers by name, email or phone.
 *
 * This function does not use customers.created_by
 * to determine ownership.
 */
function aiSearchCustomers(
    PDO $db,
    string $search,
    int $limit = 20
): array {
    if (!aiCanAccess('customers')) {
        return [];
    }

    $search = trim($search);

    if ($search === '') {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            c.customer_id,
            c.title,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.city,
            c.state,
            c.post_code,
            c.created_at
        FROM customers c
        WHERE
            c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        ORDER BY c.customer_id DESC
        LIMIT $limit
    ";

    $term = '%' . $search . '%';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $term,
        $term,
        $term,
        $term,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get recent customers.
 *
 * This is currently controlled only by customers.view.
 *
 * We deliberately do not apply an "own" filter here because
 * customer ownership is determined through sales.customer_id
 * and sales.sales_person, not customers.created_by.
 */
function aiGetRecentCustomers(
    PDO $db,
    int $limit = 50
): array {
    if (!aiCanAccess('customers')) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            c.customer_id,
            c.title,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.city,
            c.state,
            c.post_code,
            c.created_at
        FROM customers c
        ORDER BY c.created_at DESC, c.customer_id DESC
        LIMIT $limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>