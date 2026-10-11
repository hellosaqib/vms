<?php
/**
 * BritDrive VMS
 * Sales Data Layer
 *
 * Purpose:
 * - Shared sales/business data functions
 * - Used by normal VMS pages and BritDriveAI
 * - Enforces sales.view permission and scope
 *
 * Business definitions:
 * - sale_price     = base vehicle price
 * - total_revenue  = final customer sale value
 * - balance_due    = final sale value - deposit - part exchange
 * - total_refunded = amount refunded
 * - net_sales_value = final sale value - refunded amount
 * - net_profit     = recorded profit
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/currency.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../auth/permissions.php';


/* ============================================================
   INTERNAL HELPERS
   ============================================================ */

/**
 * Check that the current user can access sales data.
 *
 * Currently supported:
 * - own
 * - all
 *
 * Unsupported scopes deliberately throw an exception.
 */
function _salesAccess(PDO $db): array
{
    requireLogin();

    if (!hasPermission('sales.view')) {
        throw new RuntimeException('You do not have permission to view sales.');
    }

    $user = getCurrentUser();

    if (!$user || empty($user['id'])) {
        throw new RuntimeException('Unable to identify the current user.');
    }

    $scope = getPermissionScope('sales.view');

    if ($scope === 'none') {
        throw new RuntimeException('Sales access is denied.');
    }

    if (!in_array($scope, ['own', 'all'], true)) {
        throw new RuntimeException(
            'Sales permission scope "' . $scope . '" is not currently supported.'
        );
    }

    return [
        'user'  => $user,
        'userId' => (int) $user['id'],
        'scope' => $scope
    ];
}


/**
 * Apply the user's sales scope to a WHERE clause.
 */
function _salesApplyScope(
    array &$where,
    array &$params,
    array $access,
    string $salespersonColumn = 's.sales_person'
): void {
    if ($access['scope'] === 'own') {
        $where[] = $salespersonColumn . ' = :sales_scope_user_id';
        $params[':sales_scope_user_id'] = $access['userId'];
    }
}


/**
 * Validate a date string.
 */
function _salesValidateDate(?string $date, string $fieldName = 'date'): ?string
{
    if ($date === null || $date === '') {
        return null;
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException(
            'Invalid ' . $fieldName . '. Expected YYYY-MM-DD.'
        );
    }

    return $date;
}


/**
 * Validate sale status.
 */
function _salesValidateStatus(?string $status): ?string
{
    if ($status === null || $status === '') {
        return null;
    }

    $allowed = [
        'completed',
        'pending',
        'cancelled',
        'refunded',
        'partially_refunded'
    ];

    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid sale status.');
    }

    return $status;
}


/**
 * Validate payment method.
 */
function _salesValidatePaymentMethod(?string $paymentMethod): ?string
{
    if ($paymentMethod === null || $paymentMethod === '') {
        return null;
    }

    $allowed = [
        'cash',
        'bank_transfer',
        'finance',
        'card_payment',
        'other'
    ];

    if (!in_array($paymentMethod, $allowed, true)) {
        throw new InvalidArgumentException('Invalid payment method.');
    }

    return $paymentMethod;
}


/**
 * Build common sales filters.
 */
function _salesBuildFilters(
    PDO $db,
    array $access,
    array $filters = []
): array {
    $where = [];
    $params = [];

    _salesApplyScope($where, $params, $access);

    if (!empty($filters['date_from'])) {
        $dateFrom = _salesValidateDate(
            (string) $filters['date_from'],
            'date_from'
        );

        $where[] = 's.sale_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if (!empty($filters['date_to'])) {
        $dateTo = _salesValidateDate(
            (string) $filters['date_to'],
            'date_to'
        );

        $where[] = 's.sale_date <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    if (!empty($filters['salesperson_id'])) {
        $salespersonId = (int) $filters['salesperson_id'];

        if ($salespersonId <= 0) {
            throw new InvalidArgumentException('Invalid salesperson ID.');
        }

        /*
         * Important:
         * Under own scope, this becomes an additional restriction.
         * It cannot bypass the own-scope condition.
         */
        $where[] = 's.sales_person = :salesperson_id';
        $params[':salesperson_id'] = $salespersonId;
    }

    if (!empty($filters['status'])) {
        $status = _salesValidateStatus(
            (string) $filters['status']
        );

        $where[] = 's.status = :status';
        $params[':status'] = $status;
    }

    if (!empty($filters['payment_method'])) {
        $paymentMethod = _salesValidatePaymentMethod(
            (string) $filters['payment_method']
        );

        $where[] = 's.payment_method = :payment_method';
        $params[':payment_method'] = $paymentMethod;
    }

    if (!empty($filters['customer_id'])) {
        $customerId = (int) $filters['customer_id'];

        if ($customerId <= 0) {
            throw new InvalidArgumentException('Invalid customer ID.');
        }

        $where[] = 's.customer_id = :customer_id';
        $params[':customer_id'] = $customerId;
    }

    if (!empty($filters['vehicle_id'])) {
        $vehicleId = (int) $filters['vehicle_id'];

        if ($vehicleId <= 0) {
            throw new InvalidArgumentException('Invalid vehicle ID.');
        }

        $where[] = 's.vehicle_id = :vehicle_id';
        $params[':vehicle_id'] = $vehicleId;
    }

    return [
        'where'  => $where,
        'params' => $params
    ];
}


/**
 * Return WHERE SQL.
 */
function _salesWhere(array $where): string
{
    return empty($where)
        ? ''
        : ' WHERE ' . implode(' AND ', $where);
}


/**
 * Fetch one sale.
 */
function _salesFetchOne(
    PDO $db,
    string $sql,
    array $params = []
): ?array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


/**
 * Fetch many sales.
 */
function _salesFetchAll(
    PDO $db,
    string $sql,
    array $params = []
): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* ============================================================
   1. SALES COUNT
   ============================================================ */

function getSalesCount(
    PDO $db,
    array $filters = []
): int {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT COUNT(*)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (int) $stmt->fetchColumn();
}


/* ============================================================
   2. TOTAL SALE VALUE
   ============================================================ */

/**
 * Final customer sale value.
 *
 * Uses sales.total_revenue, NOT sales.sale_price.
 */
function getSalesTotalValue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT COALESCE(SUM(s.total_revenue), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   3. AVERAGE SALE VALUE
   ============================================================ */

function getSalesAverageValue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT COALESCE(AVG(s.total_revenue), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   4. MINIMUM SALE VALUE
   ============================================================ */

function getSalesMinimumValue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT COALESCE(MIN(s.total_revenue), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   5. MAXIMUM SALE VALUE
   ============================================================ */

function getSalesMaximumValue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT COALESCE(MAX(s.total_revenue), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   6. SALES SUMMARY
   ============================================================ */

function getSalesSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters($db, $access, $filters);

    $sql = "
        SELECT
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(AVG(s.total_revenue), 0) AS average_sale_value,
            COALESCE(MIN(s.total_revenue), 0) AS minimum_sale_value,
            COALESCE(MAX(s.total_revenue), 0) AS maximum_sale_value
        FROM sales s
    " . _salesWhere($built['where']);

    return _salesFetchOne(
        $db,
        $sql,
        $built['params']
    ) ?: [
        'sales_count' => 0,
        'total_sale_value' => 0,
        'average_sale_value' => 0,
        'minimum_sale_value' => 0,
        'maximum_sale_value' => 0
    ];
}


/* ============================================================
   7. SALES BY DATE
   ============================================================ */

function getSalesByDate(
    PDO $db,
    string $date
): array {
    $date = _salesValidateDate($date);

    return getSalesByDateRange(
        $db,
        $date,
        $date
    );
}


/* ============================================================
   8. SALES BY DATE RANGE
   ============================================================ */

function getSalesByDateRange(
    PDO $db,
    string $dateFrom,
    string $dateTo
): array {
    $dateFrom = _salesValidateDate($dateFrom, 'date_from');
    $dateTo = _salesValidateDate($dateTo, 'date_to');

    if ($dateFrom > $dateTo) {
        throw new InvalidArgumentException(
            'date_from cannot be later than date_to.'
        );
    }

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,
            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person
        FROM sales s
    " . _salesWhere($built['where']) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   9. SALES BY SALESPERSON
   ============================================================ */

function getSalesBySalesperson(
    PDO $db,
    int $salespersonId,
    array $filters = []
): array {
    if ($salespersonId <= 0) {
        throw new InvalidArgumentException(
            'Invalid salesperson ID.'
        );
    }

    $filters['salesperson_id'] = $salespersonId;

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,
            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person
        FROM sales s
    " . _salesWhere($built['where']) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   10. SALESPERSON SUMMARY
   ============================================================ */

function getSalespersonSummary(
    PDO $db,
    int $salespersonId,
    array $filters = []
): array {
    if ($salespersonId <= 0) {
        throw new InvalidArgumentException(
            'Invalid salesperson ID.'
        );
    }

    $filters['salesperson_id'] = $salespersonId;

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sales_person,
            u.full_name AS salesperson_name,
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(AVG(s.total_revenue), 0) AS average_sale_value,
            COALESCE(SUM(s.total_refunded), 0) AS total_refunded,
            COALESCE(
                SUM(s.total_revenue - s.total_refunded),
                0
            ) AS net_sales_value,
            COALESCE(SUM(s.net_profit), 0) AS net_profit
        FROM sales s
        LEFT JOIN users u
            ON u.id = s.sales_person
    " . _salesWhere($built['where']) . "
        GROUP BY
            s.sales_person,
            u.full_name
    ";

    return _salesFetchOne(
        $db,
        $sql,
        $built['params']
    ) ?: [
        'sales_person' => $salespersonId,
        'salesperson_name' => null,
        'sales_count' => 0,
        'total_sale_value' => 0,
        'average_sale_value' => 0,
        'total_refunded' => 0,
        'net_sales_value' => 0,
        'net_profit' => 0
    ];
}


/* ============================================================
   11. TOP SALESPEOPLE
   ============================================================ */

function getTopSalespeople(
    PDO $db,
    int $limit = 10,
    array $filters = []
): array {
    $limit = max(1, min($limit, 100));

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sales_person,
            u.full_name AS salesperson_name,
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(AVG(s.total_revenue), 0) AS average_sale_value,
            COALESCE(SUM(s.net_profit), 0) AS net_profit
        FROM sales s
        LEFT JOIN users u
            ON u.id = s.sales_person
    " . _salesWhere($built['where']) . "
        GROUP BY
            s.sales_person,
            u.full_name
        ORDER BY
            total_sale_value DESC
        LIMIT " . $limit;

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   12. ALL SALESPEOPLE SUMMARY
   ============================================================ */

function getSalespeopleSalesSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sales_person,
            u.full_name AS salesperson_name,
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(AVG(s.total_revenue), 0) AS average_sale_value,
            COALESCE(SUM(s.total_refunded), 0) AS total_refunded,
            COALESCE(
                SUM(s.total_revenue - s.total_refunded),
                0
            ) AS net_sales_value,
            COALESCE(SUM(s.net_profit), 0) AS net_profit
        FROM sales s
        LEFT JOIN users u
            ON u.id = s.sales_person
    " . _salesWhere($built['where']) . "
        GROUP BY
            s.sales_person,
            u.full_name
        ORDER BY
            total_sale_value DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   13. SALES BY STATUS
   ============================================================ */

function getSalesByStatus(
    PDO $db,
    string $status,
    array $filters = []
): array {
    $status = _salesValidateStatus($status);

    $filters['status'] = $status;

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,
            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person
        FROM sales s
    " . _salesWhere($built['where']) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   14. SALES STATUS SUMMARY
   ============================================================ */

function getSalesStatusSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.status,
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(SUM(s.total_refunded), 0) AS total_refunded,
            COALESCE(
                SUM(s.total_revenue - s.total_refunded),
                0
            ) AS net_sales_value
        FROM sales s
    " . _salesWhere($built['where']) . "
        GROUP BY s.status
        ORDER BY sales_count DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   15. SALES BY PAYMENT METHOD
   ============================================================ */

function getSalesByPaymentMethod(
    PDO $db,
    string $paymentMethod,
    array $filters = []
): array {
    $paymentMethod = _salesValidatePaymentMethod(
        $paymentMethod
    );

    $filters['payment_method'] = $paymentMethod;

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,
            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person
        FROM sales s
    " . _salesWhere($built['where']) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   16. PAYMENT METHOD SUMMARY
   ============================================================ */

function getPaymentMethodSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.payment_method,
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_revenue), 0) AS total_sale_value,
            COALESCE(AVG(s.total_revenue), 0) AS average_sale_value
        FROM sales s
    " . _salesWhere($built['where']) . "
        GROUP BY s.payment_method
        ORDER BY total_sale_value DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   17. SALE BY ID
   ============================================================ */

function getSaleById(
    PDO $db,
    int $saleId
): ?array {
    if ($saleId <= 0) {
        throw new InvalidArgumentException(
            'Invalid sale ID.'
        );
    }

    $access = _salesAccess($db);

    $where = ['s.sale_id = :sale_id'];
    $params = [':sale_id' => $saleId];

    _salesApplyScope(
        $where,
        $params,
        $access
    );

    $sql = "
        SELECT
            s.*,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value
        FROM sales s
    " . _salesWhere($where);

    return _salesFetchOne(
        $db,
        $sql,
        $params
    );
}


/* ============================================================
   18. SALE BY INVOICE
   ============================================================ */

function getSaleByInvoice(
    PDO $db,
    string $invoiceNo
): ?array {
    $invoiceNo = trim($invoiceNo);

    if ($invoiceNo === '') {
        throw new InvalidArgumentException(
            'Invoice number cannot be empty.'
        );
    }

    $access = _salesAccess($db);

    $where = ['s.invoice_no = :invoice_no'];
    $params = [':invoice_no' => $invoiceNo];

    _salesApplyScope(
        $where,
        $params,
        $access
    );

    $sql = "
        SELECT
            s.*,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value
        FROM sales s
    " . _salesWhere($where);

    return _salesFetchOne(
        $db,
        $sql,
        $params
    );
}


/* ============================================================
   19. SALE BY REGISTRATION NUMBER
   ============================================================ */

function getSaleByRegnumber(
    PDO $db,
    string $regnumber
): array {
    $regnumber = trim($regnumber);

    if ($regnumber === '') {
        throw new InvalidArgumentException(
            'Registration number cannot be empty.'
        );
    }

    $access = _salesAccess($db);

    $where = ['v.regnumber = :regnumber'];
    $params = [':regnumber' => $regnumber];

    _salesApplyScope(
        $where,
        $params,
        $access
    );

    $sql = "
        SELECT
            s.*,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            v.regnumber,
            v.make,
            v.model,
            v.year
        FROM sales s
        INNER JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id
    " . _salesWhere($where) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $params
    );
}


/* ============================================================
   20. SALES BY CUSTOMER
   ============================================================ */

function getSaleByCustomer(
    PDO $db,
    int $customerId
): array {
    if ($customerId <= 0) {
        throw new InvalidArgumentException(
            'Invalid customer ID.'
        );
    }

    $access = _salesAccess($db);

    $where = ['s.customer_id = :customer_id'];
    $params = [':customer_id' => $customerId];

    _salesApplyScope(
        $where,
        $params,
        $access
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,
            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person
        FROM sales s
    " . _salesWhere($where) . "
        ORDER BY s.sale_date DESC, s.sale_id DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $params
    );
}


/* ============================================================
   21. SALE DETAILS
   ============================================================ */

function getSaleDetails(
    PDO $db,
    int $saleId
): ?array {
    if ($saleId <= 0) {
        throw new InvalidArgumentException(
            'Invalid sale ID.'
        );
    }

    $access = _salesAccess($db);

    $where = ['s.sale_id = :sale_id'];
    $params = [':sale_id' => $saleId];

    _salesApplyScope(
        $where,
        $params,
        $access
    );

    $sql = "
        SELECT
            s.*,

            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,

            v.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            v.year,
            v.mileage,
            v.color,
            v.transmission,
            v.fuel_type,

            c.customer_id,
            c.title,
            c.first_name,
            c.last_name,
            c.email AS customer_email,
            c.phone AS customer_phone,

            u.id AS salesperson_id,
            u.full_name AS salesperson_name,

            fc.finance_company_id,
            fc.company_name AS finance_company
        FROM sales s

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

        LEFT JOIN customers c
            ON c.customer_id = s.customer_id

        LEFT JOIN users u
            ON u.id = s.sales_person

        LEFT JOIN finance_companies fc
            ON fc.finance_company_id = s.finance_company_id

    " . _salesWhere($where);

    return _salesFetchOne(
        $db,
        $sql,
        $params
    );
}


/* ============================================================
   22. TOTAL BALANCE DUE
   ============================================================ */

function getTotalBalanceDue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT COALESCE(SUM(s.balance_due), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   23. TOTAL REFUNDED
   ============================================================ */

function getTotalRefunded(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT COALESCE(SUM(s.total_refunded), 0)
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   24. NET SALES VALUE
   ============================================================ */

/**
 * Final sale value after refunds.
 */
function getSalesNetValue(
    PDO $db,
    array $filters = []
): float {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT COALESCE(
            SUM(
                s.total_revenue - s.total_refunded
            ),
            0
        )
        FROM sales s
    " . _salesWhere($built['where']);

    $stmt = $db->prepare($sql);
    $stmt->execute($built['params']);

    return (float) $stmt->fetchColumn();
}


/* ============================================================
   25. SALES FINANCIAL SUMMARY
   ============================================================ */

function getSalesFinancialSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            COUNT(*) AS sales_count,

            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sale_value,

            COALESCE(
                SUM(s.total_refunded),
                0
            ) AS total_refunded,

            COALESCE(
                SUM(
                    s.total_revenue - s.total_refunded
                ),
                0
            ) AS net_sales_value,

            COALESCE(
                SUM(s.balance_due),
                0
            ) AS total_balance_due,

            COALESCE(
                SUM(s.total_cost),
                0
            ) AS total_cost,

            COALESCE(
                SUM(s.net_profit),
                0
            ) AS net_profit

        FROM sales s
    " . _salesWhere($built['where']);

    return _salesFetchOne(
        $db,
        $sql,
        $built['params']
    ) ?: [
        'sales_count' => 0,
        'total_sale_value' => 0,
        'total_refunded' => 0,
        'net_sales_value' => 0,
        'total_balance_due' => 0,
        'total_cost' => 0,
        'net_profit' => 0
    ];
}


/* ============================================================
   26. SALES TREND
   ============================================================ */

function getSalesTrend(
    PDO $db,
    string $dateFrom,
    string $dateTo
): array {
    $dateFrom = _salesValidateDate($dateFrom, 'date_from');
    $dateTo = _salesValidateDate($dateTo, 'date_to');

    if ($dateFrom > $dateTo) {
        throw new InvalidArgumentException(
            'date_from cannot be later than date_to.'
        );
    }

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]
    );

    $sql = "
        SELECT
            s.sale_date,
            COUNT(*) AS sales_count,
            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sale_value,
            COALESCE(
                SUM(s.total_refunded),
                0
            ) AS total_refunded,
            COALESCE(
                SUM(
                    s.total_revenue - s.total_refunded
                ),
                0
            ) AS net_sales_value,
            COALESCE(
                SUM(s.net_profit),
                0
            ) AS net_profit
        FROM sales s
    " . _salesWhere($built['where']) . "
        GROUP BY s.sale_date
        ORDER BY s.sale_date ASC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   27. DAILY SALES SUMMARY
   ============================================================ */

function getDailySalesSummary(
    PDO $db,
    string $dateFrom,
    string $dateTo
): array {
    return getSalesTrend(
        $db,
        $dateFrom,
        $dateTo
    );
}


/* ============================================================
   28. MONTHLY SALES SUMMARY
   ============================================================ */

function getMonthlySalesSummary(
    PDO $db,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array {
    $access = _salesAccess($db);

    $filters = [];

    if ($dateFrom !== null) {
        $filters['date_from'] = $dateFrom;
    }

    if ($dateTo !== null) {
        $filters['date_to'] = $dateTo;
    }

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            DATE_FORMAT(
                s.sale_date,
                '%Y-%m'
            ) AS sales_month,

            COUNT(*) AS sales_count,

            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sale_value,

            COALESCE(
                AVG(s.total_revenue),
                0
            ) AS average_sale_value,

            COALESCE(
                SUM(s.total_refunded),
                0
            ) AS total_refunded,

            COALESCE(
                SUM(
                    s.total_revenue - s.total_refunded
                ),
                0
            ) AS net_sales_value,

            COALESCE(
                SUM(s.net_profit),
                0
            ) AS net_profit

        FROM sales s
    " . _salesWhere($built['where']) . "
        GROUP BY sales_month
        ORDER BY sales_month ASC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   29. COMPARE SALES PERIODS
   ============================================================ */

function compareSalesPeriods(
    PDO $db,
    string $firstFrom,
    string $firstTo,
    string $secondFrom,
    string $secondTo
): array {
    $firstFrom = _salesValidateDate(
        $firstFrom,
        'firstFrom'
    );

    $firstTo = _salesValidateDate(
        $firstTo,
        'firstTo'
    );

    $secondFrom = _salesValidateDate(
        $secondFrom,
        'secondFrom'
    );

    $secondTo = _salesValidateDate(
        $secondTo,
        'secondTo'
    );

    if ($firstFrom > $firstTo) {
        throw new InvalidArgumentException(
            'First period is invalid.'
        );
    }

    if ($secondFrom > $secondTo) {
        throw new InvalidArgumentException(
            'Second period is invalid.'
        );
    }

    $first = getSalesSummary(
        $db,
        [
            'date_from' => $firstFrom,
            'date_to' => $firstTo
        ]
    );

    $second = getSalesSummary(
        $db,
        [
            'date_from' => $secondFrom,
            'date_to' => $secondTo
        ]
    );

    $firstValue = (float) $first['total_sale_value'];
    $secondValue = (float) $second['total_sale_value'];

    $firstCount = (int) $first['sales_count'];
    $secondCount = (int) $second['sales_count'];

    $valueChange = $secondValue - $firstValue;
    $countChange = $secondCount - $firstCount;

    $valuePercentage = $firstValue != 0
        ? ($valueChange / $firstValue) * 100
        : null;

    $countPercentage = $firstCount != 0
        ? ($countChange / $firstCount) * 100
        : null;

    return [
        'first_period' => [
            'date_from' => $firstFrom,
            'date_to' => $firstTo,
            'sales_count' => $firstCount,
            'total_sale_value' => $firstValue,
            'average_sale_value' => (float) $first['average_sale_value']
        ],

        'second_period' => [
            'date_from' => $secondFrom,
            'date_to' => $secondTo,
            'sales_count' => $secondCount,
            'total_sale_value' => $secondValue,
            'average_sale_value' => (float) $second['average_sale_value']
        ],

        'change' => [
            'sales_count' => $countChange,
            'sales_count_percentage' => $countPercentage,
            'total_sale_value' => $valueChange,
            'total_sale_value_percentage' => $valuePercentage
        ]
    ];
}


/* ============================================================
   30. GET SALES
   ============================================================ */

function getSales(
    PDO $db,
    array $filters = [],
    int $limit = 100
): array {
    $limit = max(1, min($limit, 500));

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,

            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,

            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person,

            u.full_name AS salesperson_name,

            v.regnumber,
            v.make,
            v.model,
            v.year

        FROM sales s

        LEFT JOIN users u
            ON u.id = s.sales_person

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

    " . _salesWhere($built['where']) . "

        ORDER BY
            s.sale_date DESC,
            s.sale_id DESC

        LIMIT " . $limit;

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   31. PAGINATED SALES
   ============================================================ */

function getSalesPaginated(
    PDO $db,
    array $filters = [],
    int $page = 1,
    int $perPage = 20,
    string $sort = 'sale_date',
    string $direction = 'DESC'
): array {
    $page = max(1, $page);
    $perPage = max(1, min($perPage, 100));

    $allowedSorts = [
        'sale_id' => 's.sale_id',
        'invoice_no' => 's.invoice_no',
        'sale_date' => 's.sale_date',
        'sale_price' => 's.sale_price',
        'total_revenue' => 's.total_revenue',
        'sale_value' => 's.total_revenue',
        'balance_due' => 's.balance_due',
        'total_refunded' => 's.total_refunded',
        'status' => 's.status'
    ];

    if (!isset($allowedSorts[$sort])) {
        throw new InvalidArgumentException(
            'Invalid sales sort field.'
        );
    }

    $direction = strtoupper($direction);

    if (!in_array($direction, ['ASC', 'DESC'], true)) {
        throw new InvalidArgumentException(
            'Invalid sort direction.'
        );
    }

    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $countSql = "
        SELECT COUNT(*)
        FROM sales s
    " . _salesWhere($built['where']);

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($built['params']);

    $totalRows = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT
            s.sale_id,
            s.invoice_no,
            s.sale_date,

            s.sale_price AS base_sale_price,
            s.total_revenue AS final_sale_value,
            s.total_revenue,

            s.payment_method,
            s.status,
            s.balance_due,
            s.total_refunded,
            s.sales_person,

            u.full_name AS salesperson_name,

            v.regnumber,
            v.make,
            v.model,
            v.year

        FROM sales s

        LEFT JOIN users u
            ON u.id = s.sales_person

        LEFT JOIN vehicles v
            ON v.vehicle_id = s.vehicle_id

    " . _salesWhere($built['where']) . "

        ORDER BY "
        . $allowedSorts[$sort]
        . " "
        . $direction
        . "

        LIMIT "
        . $perPage
        . "

        OFFSET "
        . $offset;

    $rows = _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );

    return [
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalRows > 0
                ? (int) ceil($totalRows / $perPage)
                : 0
        ]
    ];
}


/* ============================================================
   32. SALES COMMISSION SUMMARY
   ============================================================ */

function getSalesCommissionSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    $sql = "
        SELECT
            sc.user_id,
            u.full_name AS salesperson_name,

            COUNT(DISTINCT sc.sale_id) AS sales_count,

            COALESCE(
                SUM(sc.commission_amount),
                0
            ) AS commission_amount

        FROM sales_commissions sc

        INNER JOIN sales s
            ON s.sale_id = sc.sale_id

        LEFT JOIN users u
            ON u.id = sc.user_id
    " . _salesWhere(
        array_merge(
            $built['where'],
            [
                "sc.status <> 'cancelled'"
            ]
        )
    ) . "

        GROUP BY
            sc.user_id,
            u.full_name

        ORDER BY
            commission_amount DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}


/* ============================================================
   33. SALES PAYMENT SUMMARY
   ============================================================ */

function getSalesPaymentSummary(
    PDO $db,
    array $filters = []
): array {
    $access = _salesAccess($db);

    $built = _salesBuildFilters(
        $db,
        $access,
        $filters
    );

    /*
     * Payment summary is based on actual completed
     * sale payments, not the sale's payment_method field.
     */
    $where = $built['where'];

    $where[] = "sp.transaction_type = 'payment'";
    $where[] = "sp.status = 'completed'";

    $sql = "
        SELECT
            sp.payment_method,

            COUNT(*) AS payment_count,

            COALESCE(
                SUM(sp.amount),
                0
            ) AS payment_total

        FROM sale_payments sp

        INNER JOIN sales s
            ON s.sale_id = sp.sale_id

    " . _salesWhere($where) . "

        GROUP BY
            sp.payment_method

        ORDER BY
            payment_total DESC
    ";

    return _salesFetchAll(
        $db,
        $sql,
        $built['params']
    );
}
?>