<?php
// Authentication & Config
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/reports_functions.php'; // New functions file
$pageTitle = 'My Sales History';
include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Handle filters
$filters = [];
if (isset($_GET['period'])) {
    $dateRange = getTimePeriodData($_GET['period']);
    $filters = array_merge($filters, $dateRange);
}

if (isset($_GET['status']) && $_GET['status'] != '') {
    $filters['vehicle_status'] = $_GET['status'];
}

$userId = $_SESSION['user_id'];

$commissionQuery = $db->prepare("
    SELECT
        sc.commission_id,
        sc.sale_id,
        sc.commission_rate,
        sc.commission_amount,
        sc.status,
        sc.created_at,
        sc.approved_at,
        sc.paid_at,

        s.sale_date,
        s.invoice_no,

        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber

    FROM sales_commissions sc

    INNER JOIN sales s
        ON sc.sale_id = s.sale_id

    INNER JOIN vehicles v
        ON s.vehicle_id = v.vehicle_id

    WHERE sc.user_id = ?

    ORDER BY sc.created_at DESC
");

$commissionQuery->execute([$userId]);

$commissions = $commissionQuery->fetchAll(PDO::FETCH_ASSOC);

$totalCommission = 0;
$totalPaid = 0;
$totalPending = 0;
$totalApproved = 0;

foreach ($commissions as $commission) {

    $amount = (float)$commission['commission_amount'];

    if ($commission['status'] !== 'cancelled') {
        $totalCommission += $amount;
    }

    if ($commission['status'] === 'paid') {
        $totalPaid += $amount;
    }

    if ($commission['status'] === 'pending') {
        $totalPending += $amount;
    }

    if ($commission['status'] === 'approved') {
        $totalApproved += $amount;
    }
}

$totalOutstanding = $totalPending + $totalApproved;
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
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/images/britdrive.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/images/britdrive.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/uploads/images/britdrive.png">
    <link rel="shortcut icon" type="image/x-icon" href="/uploads/images/britdrive.ico">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="/uploads/images/britdrive.png">
    <meta name="theme-color" content="#ffffff">
    <script src="vendors/simplebar/simplebar.min.js"></script>
    <script src="assets/js/config.js"></script>

    <!-- ===============================================-->
    <!--    Stylesheets   -->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/line.css">
    <!-- <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl"> -->
    <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
    <!-- <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl"> -->
    <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
    <style>
        .right-sidebar {
            position: sticky;
            right: 0;
            top: 65px;
            z-index: 100;

        }
    </style>
    <style>
        .metric-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .metric-positive {
            border-left-color: #00d97e;
        }

        .metric-warning {
            border-left-color: #f6c343;
        }

        .metric-danger {
            border-left-color: #e63757;
        }

        .metric-info {
            border-left-color: #2c7be5;
        }

        .alert-badge {
            position: absolute;
            top: -8px;
            right: -8px;
        }

        .sparkline {
            width: 60px;
            height: 30px;
        }
    </style>
</head>

<body>
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <?php include("left-menu.php");
        ?>
        <!-- Main Content -->
        <div class="content">
            <div class="pb-5">
                <div class="row gy-3 mb-3 justify-content-between border-bottom">
                    <div class="col-md-9 col-auto">
                        <h2 class="mb-2 text-body-emphasis">My Sales History</h2>
                        <h5 class="text-body-tertiary fw-semibold">View your completed sales and commissions</h5>
                    </div>
                    <div class="col-md-3 col-auto text-end">
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print/PDF
                        </button>
                    </div>
                </div>

                <!-- Report Filters - SIMPLIFIED -->
                <!-- <div class="card mb-4">
                    <div class="card-header p-3">
                        <h5 class="mb-0">Report Period</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Time Period</label>
                                <select name="period" class="form-select">
                                    <option value="today" <?= ($_GET['period'] ?? '') == 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= ($_GET['period'] ?? '') == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="month" <?= ($_GET['period'] ?? '') == 'month' ? 'selected' : '' ?>>This Month</option>
                                    <option value="quarter" <?= ($_GET['period'] ?? '') == 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                                    <option value="year" <?= ($_GET['period'] ?? '') == 'year' ? 'selected' : '' ?>>This Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="gross-profit.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This report shows your sales and commission data.
                                For current inventory analysis, use the Inventory Aging Report.
                            </small>
                        </div>
                    </div>
                </div> -->
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">

    <!-- Total Earned -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">

                <p class="text-muted mb-1">
                    Total Commission
                </p>

                <h3 class="mb-0">
                    £<?= number_format($totalCommission, 2) ?>
                </h3>

            </div>
        </div>
    </div>


    <!-- Paid -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">

                <p class="text-muted mb-1">
                    Paid
                </p>

                <h3 class="mb-0 text-success">
                    £<?= number_format($totalPaid, 2) ?>
                </h3>

            </div>
        </div>
    </div>


    <!-- Approved -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">

                <p class="text-muted mb-1">
                    Approved
                </p>

                <h3 class="mb-0">
                    £<?= number_format($totalApproved, 2) ?>
                </h3>

            </div>
        </div>
    </div>


    <!-- Outstanding -->
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">

                <p class="text-muted mb-1">
                    Outstanding
                </p>

                <h3 class="mb-0 text-warning">
                    £<?= number_format($totalOutstanding, 2) ?>
                </h3>

            </div>
        </div>
    </div>

</div>

                <!-- Vehicle Profitability Table -->
                <div class="card pt-3">
                    <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;sale_date&quot;,&quot;vehicle&quot;,&quot;invoice_no&quot;,&quot;commission_rate&quot;,&quot;commission_amount&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">

                        <div class="row">
                            <div class="col-8">
                                <h3 class="p-3">My Sales History</h3>
                                <!-- <p class="text-body-tertiary lh-sm mb-0">Payment received across all channels</p> -->
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
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm fs-12 mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="sort border-top" data-sort="id">Id</th>
                                            <th class="sort border-top" data-sort="sale_date">Date</th>
                                            <th class="sort border-top ps-2" data-sort="vehicle">Vehicle</th>
                                            <th class="sort border-top " data-sort="invoice_no">Invoice</th>
                                            <th class="sort border-top " data-sort="commission_rate">Commission Rate</th>
                                            <th class="sort border-top" data-sort="commission_amount">Commission Amount</th>
                                            <th class="sort border-top" data-sort="status">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="list">
                                        <?php foreach ($commissions as $commission): ?>
                                            <tr>
                                                <td class="align-middle id"><?= htmlspecialchars($commission['commission_id']) ?></td>
                                                <td class="align-middle sale_date"><?= date('d M Y', strtotime($commission['sale_date'])) ?></td>
                                                <td class="align-middle ps-2 vehicle">
                                                    <div class="fw-bold"><?= htmlspecialchars($commission['make']) ?> <?= htmlspecialchars($commission['model']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($commission['regnumber']) ?></small>
                                                </td>
                                                <td class="align-middle invoice_no"><?= htmlspecialchars($commission['invoice_no']) ?></td>
                                                <td class="align-middle commission_rate"><?= $commission['commission_rate'] ?></td>
                                                <td class="align-middle commission_amount">
                                                        <?= formatCurrency($commission['commission_amount']) ?>
                                                </td>
                                                <td class="align-middle status">
                                                    <span class="badge badge-phoenix badge-phoenix-<?=
                                                                            $commission['status'] == 'sold' ? 'success' : ($commission['status'] == 'available' ? 'info' : 'warning')
                                                                            ?>">
                                                        <?= ucfirst($commission['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between mt-3 p-3"><span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span>43</span>
                                <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                            <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                                        </svg><!-- <span class="fas fa-chevron-left"></span> Font Awesome fontawesome.com --></button>
                                    <ul class="mb-0 pagination">
                                        <li class="active"><button class="page" type="button" data-i="1" data-page="5">1</button></li>
                                        <li><button class="page" type="button" data-i="2" data-page="5">2</button></li>
                                        <li><button class="page" type="button" data-i="3" data-page="5">3</button></li>
                                        <li class="disabled"><button class="page" type="button">...</button></li>
                                    </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                            <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                                        </svg><!-- <span class="fas fa-chevron-right"></span> Font Awesome fontawesome.com --></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include("footer.php"); ?>

        </div>

        <script>
            function exportToPDF() {
                alert('PDF export functionality would be implemented here');
                // Would use a PDF library like TCPDF or Dompdf
            }

            function exportToCSV() {
                // Simple CSV export
                let csv = [];
                let headers = ['Vehicle', 'Purchase Date', 'Purchase Price', 'Sale Price', 'Repair Costs', 'Gross Profit', 'Net Profit', 'Margin %', 'Days', 'Status'];
                csv.push(headers.join(','));

                <?php foreach ($reportData['vehicle_data'] as $vehicle): ?>
                    csv.push([
                        '<?= $vehicle['make'] ?> <?= $vehicle['model'] ?>',
                        '<?= $vehicle['purchase_date'] ?>',
                        '<?= $vehicle['purchase_price'] ?>',
                        '<?= $vehicle['sale_price'] ?>',
                        '<?= $vehicle['repair_costs'] ?>',
                        '<?= $vehicle['gross_profit'] ?>',
                        '<?= $vehicle['net_profit'] ?>',
                        '<?= $vehicle['gross_margin_percent'] ?>',
                        '<?= $vehicle['days_in_inventory'] ?>',
                        '<?= $vehicle['status'] ?>'
                    ].join(','));
                <?php endforeach; ?>

                let csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
                let encodedUri = encodeURI(csvContent);
                let link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "gross_profit_report_<?= date('Y-m-d') ?>.csv");
                document.body.appendChild(link);
                link.click();
            }
        </script>



    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    <!-- JavaScript Libraries -->
    <script src="vendors/popper/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/anchorjs/anchor.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="vendors/fontawesome/all.min.js"></script>
    <script src="assets/js/phoenix.js"></script>

    <!-- Dashboard Charts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue vs Profit Chart
            const revenueCtx = document.getElementById('revenueProfitChart').getContext('2d');
            const revenueProfitChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'month_name')) ?>,
                    datasets: [{
                            label: 'Revenue (£)',
                            data: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'monthly_revenue')) ?>,
                            borderColor: '#2c7be5',
                            backgroundColor: 'rgba(44, 123, 229, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Profit (£)',
                            data: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'monthly_profit')) ?>,
                            borderColor: '#00d97e',
                            backgroundColor: 'rgba(0, 217, 126, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '£' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Inventory Health Chart
            const inventoryCtx = document.getElementById('inventoryHealthChart').getContext('2d');
            const inventoryHealthChart = new Chart(inventoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'status')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'vehicle_count')) ?>,
                        backgroundColor: [
                            '#00d97e', '#f6c343', '#2c7be5', '#fd7e14', '#6e84a3'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>

    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->

    <script src="vendors/popper/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/anchorjs/anchor.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="vendors/fontawesome/all.min.js"></script>
    <script src="vendors/lodash/lodash.min.js"></script>
    <script src="vendors/list.js/list.min.js"></script>
    <script src="vendors/feather-icons/feather.min.js"></script>
    <script src="vendors/dayjs/dayjs.min.js"></script>
    <script src="vendors/leaflet/leaflet.js"></script>
    <script src="vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
    <script src="vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter.min.js"></script>
    <script src="assets/js/phoenix.js"></script>
    <!-- <script src="vendors/echarts/echarts.min.js"></script> -->
    <script src="assets/js/projectmanagement-dashboard.js"></script>

</body>


</html>