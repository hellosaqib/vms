<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth/auth.php';
require_once __DIR__ . '/includes/auth/permissions.php';
require_once __DIR__ . '/includes/ai/access.php';
require_once __DIR__ . '/includes/ai/data.php';
require_once __DIR__ . '/includes/config/config.php';

$pageTitle = "SalesAI - Proof of Concept";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

requireLogin();


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$user = getCurrentUser();

if (!$user) {
    http_response_code(403);
    exit('403 - User not available');
}


/*
|--------------------------------------------------------------------------
| SalesAI Access
|--------------------------------------------------------------------------
*/

if (!aiCanAccess('sales')) {
    http_response_code(403);
    exit('403 - SalesAI sales access denied');
}


/*
|--------------------------------------------------------------------------
| Sales Permission Scope
|--------------------------------------------------------------------------
*/

$scope = aiSalesScope();


if ($scope === 'none') {
    http_response_code(403);
    exit('403 - Sales access denied');
}


/*
|--------------------------------------------------------------------------
| Load Authorised Sales
|--------------------------------------------------------------------------
*/

if ($scope === 'own') {

    $sales = aiGetMySales($db, 100);

} elseif ($scope === 'all') {

    $sales = aiGetAllSales($db, 100);

} else {

    // For this POC we only support own/all.
    $sales = [];
}


/*
|--------------------------------------------------------------------------
| Basic Statistics
|--------------------------------------------------------------------------
*/

$salesCount = count($sales);

$totalSalesValue = 0;

foreach ($sales as $sale) {
    $totalSalesValue += (float) ($sale['sale_price'] ?? 0);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

</head>

<body>

    <h1>SalesAI - Proof of Concept</h1>

    <hr>

    <h2>Connection Test</h2>

    <p>
        <strong>User:</strong>
        <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
    </p>

    <p>
        <strong>Role:</strong>
        <?= htmlspecialchars($user['role_name'] ?? 'Unknown') ?>
    </p>

    <p>
        <strong>Authentication:</strong>
        ✓ Connected
    </p>

    <p>
        <strong>SalesAI Access:</strong>
        ✓ Available
    </p>

    <p>
        <strong>Sales Permission:</strong>
        ✓ Allowed
    </p>

    <p>
        <strong>Sales Scope:</strong>
        <?= htmlspecialchars($scope) ?>
    </p>

    <p>
        <strong>Authorised Sales:</strong>
        <?= $salesCount ?>
    </p>

    <p>
        <strong>Total Sale Value:</strong>
        £<?= number_format($totalSalesValue, 2) ?>
    </p>

    <hr>

    <h2>Authorised Sales Data</h2>

    <?php if (empty($sales)): ?>

        <p>No authorised sales found.</p>

    <?php else: ?>

        <table border="1" cellpadding="8" cellspacing="0">

            <thead>

                <tr>
                    <th>Sale ID</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Sale Price</th>
                    <th>Status</th>
                    <th>Sales Person</th>
                    <th>Balance Due</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($sales as $sale): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars((string) $sale['sale_id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $sale['invoice_no']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $sale['sale_date']) ?>
                        </td>

                        <td>
                            £<?= number_format((float) $sale['sale_price'], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $sale['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $sale['sales_person']) ?>
                        </td>

                        <td>
                            £<?= number_format((float) $sale['balance_due'], 2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <hr>

    <p>
        <strong>✓ SalesAI successfully accessed authorised sales data.</strong>
    </p>

</body>

</html>