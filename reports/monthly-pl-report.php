<?php
$requiredPermission = 'manage_reports';
require_once '../includes/check_auth.php';
require_once '../includes/functions.php';
$pageTitle = 'Monthly Profit/Loss Report - Car Trade System';

include '../header.php';

// Get selected month (default to current month)
$selectedMonth = $_GET['month'] ?? date('Y-m');
$prevMonth = date('Y-m', strtotime($selectedMonth . ' -1 month'));
$nextMonth = date('Y-m', strtotime($selectedMonth . ' +1 month'));

// 1. Fetch Revenue Data
$revenueQuery = $db->prepare("
    SELECT 
        make,
        model,
        COUNT(*) as units_sold,
        SUM(sale_price) as total_revenue,
        SUM(sale_price - purchase_price) as gross_profit
    FROM vehicles
    WHERE status = 'sold'
    AND DATE_FORMAT(sale_date, '%Y-%m') = ?
    GROUP BY make, model
    ORDER BY total_revenue DESC
");
$revenueQuery->execute([$selectedMonth]);
$revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Cost Data
$costQueries = [
    'purchases' => "SELECT COALESCE(SUM(purchase_price), 0) FROM vehicles WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?",
    'repairs' => "SELECT COALESCE(SUM(cost), 0) FROM repairs WHERE DATE_FORMAT(date_completed, '%Y-%m') = ?",
    'expenses' => "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?"
];

$costs = [];
foreach ($costQueries as $key => $query) {
    $stmt = $db->prepare($query);
    $stmt->execute([$selectedMonth]);
    $costs[$key] = $stmt->fetchColumn();
}

// 3. Calculate Profit Metrics
$revenue = array_sum(array_column($revenueData, 'total_revenue'));
$totalCosts = array_sum($costs);
$netProfit = $revenue - $totalCosts;
$margin = $revenue ? ($netProfit / $revenue) * 100 : 0;

// 4. Get Top/Bottom Performers
usort($revenueData, function ($a, $b) {
    return $b['gross_profit'] <=> $a['gross_profit'];
});
$topPerformers = array_slice($revenueData, 0, 3);
$bottomPerformers = array_slice($revenueData, -3, 3);

?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title><?php echo ($pageTitle); ?></title>

    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $URL ?>/assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $URL ?>/assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $URL ?>/assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?= $URL ?>/assets/img/favicons/favicon.ico">
    <link rel="manifest" href="<?= $URL ?>/assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="<?= $URL ?>/assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <script src="<?= $URL ?>/vendors/simplebar/simplebar.min.js"></script>
    <script src="<?= $URL ?>/assets/js/config.js"></script>

    <!-- ===============================================-->
    <!--    Stylesheets   -->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $URL ?>/assets/css/line.css">
    <!-- <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl"> -->
    <link href="<?= $URL ?>/assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
    <!-- <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl"> -->
    <link href="<?= $URL ?>/assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
    <script>
        var phoenixIsRTL = window.config.config.phoenixIsRTL;
        if (phoenixIsRTL) {
            var linkDefault = document.getElementById('style-default');
            var userLinkDefault = document.getElementById('user-style-default');
            linkDefault.setAttribute('disabled', true);
            userLinkDefault.setAttribute('disabled', true);
            document.querySelector('html').setAttribute('dir', 'rtl');
        } else {
            var linkRTL = document.getElementById('style-rtl');
            var userLinkRTL = document.getElementById('user-style-rtl');
            // linkRTL.setAttribute('disabled', true);
            // userLinkRTL.setAttribute('disabled', true);
        }
    </script>
    <link href="<?= $URL ?>/vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">



</head>

<body>
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <?php include("../left-menu.php"); ?>
        <!-- Main Content -->
        <div class="content">
            <div class="pb-5">
                <div class="row gy-3 mb-6 justify-content-between">
                    <div class="col-md-8 col-auto">
                        <h2 class="mb-2 text-body-emphasis">Monthly Profit/Loss Report</h2>
                        <div class="month-selector">
                            <a href="?month=<?= $prevMonth ?>">◀ Previous</a>
                            <input class="form-control" maxlength="20" type="month" value="<?= $selectedMonth ?>"
                                onchange="window.location.href='?month='+this.value">
                            <a href="?month=<?= $nextMonth ?>">Next ▶</a>
                        </div>
                    </div>
                    <div class="col-md-2 col-auto text-end">

                        <button class="btn btn-primary">Export PDF</button>
                    </div>
                </div>
                <!-- New Code -->
                <hr>



                <p>Report generated: <?= date('Y-m-d') ?></p>
                <div class="<?= $netProfit >= 0 ? 'positive' : 'negative' ?>">
                    Status: <?= $netProfit >= 0 ? '✅ Profitable' : '❌ Loss' ?>
                </div>
                <hr>

                <!-- 2. Key Financial Summary -->
                <h2>Financial Summary</h2>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card  h-100 bg-success bg-opacity-10 border-success  p-5 <?= $netProfit >= 0 ? 'profit' : 'loss' ?>">
                            <h3>Net Profit/Loss</h3>
                            <p>£<?= number_format($netProfit, 2) ?>
                                <small>(Margin: <?= number_format($margin, 1) ?>%)</small>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-5  h-100 bg-success bg-opacity-10 border-success">
                            <h3>Total Revenue</h3>
                            <p>£<?= number_format($revenue, 2) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-5">
                            <h3>Total Costs</h3>
                            <p>£<?= number_format($totalCosts, 2) ?></p>
                            <small>
                                Purchases: £<?= number_format($costs['purchases'], 2) ?> |
                                Repairs: £<?= number_format($costs['repairs'], 2) ?> |
                                Expenses: £<?= number_format($costs['expenses'], 2) ?>
                            </small>
                        </div>
                    </div>
                </div>



                <!-- 3. Revenue Breakdown -->
                <h2>Revenue by Model</h2>
                <table>
                    <tr>
                        <th>Make/Model</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                        <th>Gross Profit</th>
                    </tr>
                    <?php foreach ($revenueData as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['make']) ?> <?= htmlspecialchars($item['model']) ?></td>
                            <td><?= $item['units_sold'] ?></td>
                            <td>£<?= number_format($item['total_revenue'], 2) ?></td>
                            <td class="<?= $item['gross_profit'] >= 0 ? 'positive' : 'negative' ?>">
                                £<?= number_format($item['gross_profit'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <!-- 4. Cost Analysis -->
                <h2>Cost Breakdown</h2>
                <table>
                    <tr>
                        <th>Cost Type</th>
                        <th>Amount</th>
                    </tr>
                    <tr>
                        <td>Vehicle Purchases</td>
                        <td>£<?= number_format($costs['purchases'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Repairs</td>
                        <td>£<?= number_format($costs['repairs'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Other Expenses</td>
                        <td>£<?= number_format($costs['expenses'], 2) ?></td>
                    </tr>
                </table>
<br>
                <!-- 5. Profitability Insights -->
                <h2>Top Performers</h2>
                <table>
                    <tr>
                        <th>Model</th>
                        <th>Gross Profit</th>
                        <th>Margin</th>
                    </tr>
                    <?php foreach ($topPerformers as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['make']) ?> <?= htmlspecialchars($item['model']) ?></td>
                            <td class="positive">£<?= number_format($item['gross_profit'], 2) ?></td>
                            <td><?= number_format(($item['gross_profit'] / $item['total_revenue']) * 100, 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2>Potential Loss Leaders</h2>
                <table>
                    <tr>
                        <th>Model</th>
                        <th>Gross Profit</th>
                    </tr>
                    <?php foreach ($bottomPerformers as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['make']) ?> <?= htmlspecialchars($item['model']) ?></td>
                            <td class="<?= $item['gross_profit'] >= 0 ? 'positive' : 'negative' ?>">
                                £<?= number_format($item['gross_profit'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <!--  -->







                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100 bg-success bg-opacity-10 border-success">
                            <div class="card-body">
                                <h3 class="card-title text-success">Total Sales</h3>
                                <h2 class="mb-0">£<?php //number_format($totalSales, 2); 
                                                    ?></h2>
                                <p class="mb-0 fs-9 text-body-tertiary">
                                    <?php //count($sales); 
                                    ?> vehicles sold
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 bg-primary bg-opacity-10 border-primary">
                            <div class="card-body">
                                <h3 class="card-title text-primary">Net Profit</h3>
                                <h2 class="mb-0">£<?= number_format($totalProfit, 2) ?></h2>
                                <p class="mb-0 fs-9 text-body-tertiary">
                                    <?= $totalSales > 0 ? round(($totalProfit / $totalSales) * 100, 2) : 0 ?>% margin
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 bg-info bg-opacity-10 border-info">
                            <div class="card-body">
                                <h3 class="card-title text-info">Avg. Days in Stock</h3>
                                <h2 class="mb-0"><?= round($avgDaysInInventory) ?></h2>
                                <p class="mb-0 fs-9 text-body-tertiary">
                                    Before sale
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row gy-3 mb-6 justify-content-between">
                    <div class="col-md-9 col-auto">
                        <h2 class="mb-2 text-body-emphasis">Sales Details</h2>
                        <h5 class="text-body-tertiary fw-semibold">
                            <?= date('M j', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>
                        </h5>
                    </div>
                    <div class="col-md-3 col-auto text-end">
                        <h4 class="text-danger"><?= date('M j, Y', time()) ?></h4>
                    </div>
                </div>



                <div class="row flex-between-center mb-4 g-3">
                    <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;make&quot;,&quot;model&quot;,&quot;year&quot;,&quot;mileage&quot;,&quot;price&quot;,&quot;status&quot;],&quot;page&quot;:15,&quot;pagination&quot;:true}">
                        <div class="row">
                            <div class="col-8">
                                <button class="btn btn-sm btn-outline-danger me-1 mb-1" id="printReport" type="button"><i class="fas fa-print me-1"></i> Print</button>
                                <a class="btn btn-sm btn-outline-success me-1 mb-1" href="?export=excel" type="button"><i class="fas fa-file-excel me-2"></i> Excel</a>
                                <button class="btn btn-sm btn-outline-info me-1 mb-1" href="?export=csv" type="button"><i class="fas fa-file-csv me-2"></i> CSV</button>

                            </div>
                            <div class="col-4 search-box mb-3">
                                <form class="position-relative">
                                    <input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                                    <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                                    </svg>
                                </form>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm fs-9 mb-0">
                                <thead>
                                    <tr>
                                        <th class="sort border-top" data-sort="id">Vehicle</th>
                                        <th class="sort border-top " data-sort="year">Sold Date</th>
                                        <th class="sort border-top" data-sort="status">Customer</th>
                                        <th class="sort border-top" data-sort="price">Sale Price</th>
                                        <th class="sort border-top" data-sort="price">Purchase Price</th>
                                        <th class="sort border-top border-translucent ps-3" data-sort="make">Repair Costs</th>
                                        <th class="sort border-top " data-sort="expenses"> Expenses</th>
                                        <th class="sort border-top " data-sort="total_cost">Net Profit</th>
                                        <th class="sort border-top" data-sort="mileage">Days in Stock</th>
                                        <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody class="list">
                                    <?php foreach ($sales as $sale):
                                        $profitClass = $sale['net_profit'] >= 0 ? 'text-success' : 'text-danger';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($sale['make']) ?> <?= htmlspecialchars($sale['model']) ?></strong>
                                                <small class="text-muted d-block"><?= $sale['year'] ?> • <?= $sale['vin'] ?></small>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($sale['sale_date'])) ?></td>
                                            <td>
                                                <?= htmlspecialchars($sale['first_name']) ?> <?= htmlspecialchars($sale['last_name']) ?>
                                                <?php if ($sale['customer_phone']): ?>
                                                    <small class="text-muted d-block"><?= $sale['customer_phone'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">£<?= number_format($sale['sale_price'], 2) ?></td>
                                            <td class="text-end">£<?= number_format($sale['purchase_price'], 2) ?></td>
                                            <td class="text-end">£<?= number_format($sale['total_repair_costs'] ?? 0, 2) ?></td>
                                            <td class="text-end">£<?= number_format($sale['other_expenses'] ?? 0, 2) ?></td>
                                            <td class="text-end <?= $profitClass ?>">
                                                £<?= number_format($sale['net_profit'], 2) ?>
                                                <?php if ($sale['sale_price'] > 0): ?>
                                                    <small class="d-block text-muted">
                                                        (<?= number_format(($sale['net_profit'] / $sale['sale_price']) * 100, 2) ?>%)
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $sale['days_in_inventory'] ?></td>
                                            <td class="text-end">
                                                <a href="../view-sale.php?id=<?= $sale['vehicle_id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="View Sale Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                No sales found for this period
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($sales)): ?>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <th colspan="3" class="text-end">Totals:</th>
                                            <th class="text-end">£<?= number_format($totalSales, 2) ?></th>
                                            <th class="text-end">£<?= number_format(array_sum(array_column($sales, 'purchase_price')), 2) ?></th>
                                            <th class="text-end">£<?= number_format(array_sum(array_column($sales, 'total_repair_costs')), 2) ?></th>
                                            <th class="text-end">£<?= number_format(array_sum(array_column($sales, 'other_expenses')), 2) ?></th>
                                            <th class="text-end <?= $totalProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                                £<?= number_format($totalProfit, 2) ?>
                                            </th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 10 <span class="text-body-tertiary"> Items of </span>43</span>
                            <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                                    </svg><!-- <span class="fas fa-chevron-left"></span> Font Awesome fontawesome.com --></button>
                                <ul class="mb-0 pagination">
                                    <li class="active"><button class="page" type="button" data-i="1" data-page="10">1</button></li>
                                    <li><button class="page" type="button" data-i="2" data-page="10">2</button></li>
                                    <li><button class="page" type="button" data-i="3" data-page="10">3</button></li>
                                    <li class="disabled"><button class="page" type="button">...</button></li>
                                </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                                    </svg><!-- <span class="fas fa-chevron-right"></span> Font Awesome fontawesome.com --></button>
                            </div>
                        </div>
                    </div>


                </div>



                <!-- Print Stylesheet -->
                <style media="print">
                    @page {
                        size: landscape;
                        margin: 0.5cm;
                    }

                    body * {
                        visibility: visible;
                    }

                    .card,
                    .card * {
                        visibility: visible;
                    }

                    .card {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        border: none;
                    }

                    .dropdown,
                    .btn-group {
                        display: none !important;
                    }

                    .badge {
                        border: 1px solid #000 !important;
                        color: #000 !important;
                    }

                    table {
                        width: 100% !important;
                        font-size: 12px !important;
                    }
                </style>


                <!-- JavaScript for Print -->
                <script>
                    document.getElementById('printReport').addEventListener('click', function() {
                        // Store original content
                        const originalTitle = document.title;
                        // Update title for print
                        document.title = "Inventory Snapshot - <?php echo $reportDate; ?>";

                        // Trigger print
                        window.print();

                        // Restore original title
                        document.title = originalTitle;
                    });
                </script>

            </div>

            <!-- Footer -->
            <?php include("../footer.php"); ?>
        </div>

        <!-- Chat Now -->
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->



    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="<?= $URL ?>/vendors/popper/popper.min.js"></script>
    <script src="<?= $URL ?>/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="<?= $URL ?>/vendors/anchorjs/anchor.min.js"></script>
    <script src="<?= $URL ?>/vendors/is/is.min.js"></script>
    <script src="<?= $URL ?>/vendors/fontawesome/all.min.js"></script>
    <script src="<?= $URL ?>/vendors/lodash/lodash.min.js"></script>
    <script src="<?= $URL ?>/vendors/list.js/list.min.js"></script>
    <script src="<?= $URL ?>/vendors/feather-icons/feather.min.js"></script>
    <script src="<?= $URL ?>/vendors/dayjs/dayjs.min.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet/leaflet.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter.min.js"></script>
    <script src="<?= $URL ?>/assets/js/phoenix.js"></script>
    <!-- <script src="vendors/echarts/echarts.min.js"></script> -->
    <script src="assets/js/projectmanagement-dashboard.js"></script>

</body>


</html>