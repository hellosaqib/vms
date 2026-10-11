<?php
declare(strict_types=1);

/**
 * BritDrive AI - Finance Company Functions
 *
 * Functions for reading finance company data from the existing
 * BritDrive VMS database.
 */

require_once __DIR__ . '/../config/vms_database.php';


/**
 * Get a finance company by ID.
 */
function getFinanceCompanyById(int $financeCompanyId): ?array
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            fc.finance_company_id,
            fc.company_name
        FROM finance_companies fc
        WHERE fc.finance_company_id = ?
        LIMIT 1
    ");

    $stmt->execute([$financeCompanyId]);

    $financeCompany = $stmt->fetch();

    return $financeCompany ?: null;
}


/**
 * Get all finance companies.
 */
function getFinanceCompanies(): array
{
    global $db;

    $stmt = $db->query("
        SELECT
            fc.finance_company_id,
            fc.company_name
        FROM finance_companies fc
        ORDER BY fc.company_name ASC
    ");

    return $stmt->fetchAll();
}


/**
 * Get total number of finance companies.
 */
function getFinanceCompanyCount(): int
{
    global $db;

    $stmt = $db->query("
        SELECT COUNT(*)
        FROM finance_companies
    ");

    return (int) $stmt->fetchColumn();
}
?>