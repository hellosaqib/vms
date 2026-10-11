<?php
// Authentication & Config
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/reports_functions.php'; // New functions file
$pageTitle = 'ROI By Vehicle Report';
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

// Generate the report
$reportData = generateROIByVehicleReport($db, $filters);


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
                        <h2 class="mb-2 text-body-emphasis">ROI by Vehicle Report</h2>
                        <h5 class="text-body-tertiary fw-semibold">Return on Investment Analysis</h5>
                    </div>
                    <div class="col-md-3 col-auto text-end">
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <button class="btn btn-success" onclick="generatePDF()">
                                <i class="fas fa-file-pdf me-2"></i>PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Time Period</label>
                                <select name="period" class="form-select">
                                    <option value="today" <?= ($_GET['period'] ?? '') == 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="week" <?= ($_GET['period'] ?? '') == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                    <option value="month" <?= ($_GET['period'] ?? '') == 'month' ? 'selected' : '' ?>>This Month</option>
                                    <option value="quarter" <?= ($_GET['period'] ?? '') == 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                                    <option value="year" <?= ($_GET['period'] ?? '') == 'year' ? 'selected' : '' ?>>This Year</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vehicle Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Vehicles</option>
                                    <option value="sold" <?= ($_GET['status'] ?? '') == 'sold' ? 'selected' : '' ?>>Sold Only</option>
                                    <option value="available" <?= ($_GET['status'] ?? '') == 'available' ? 'selected' : '' ?>>Available Only</option>
                                    <option value="in_preparation" <?= ($_GET['status'] ?? '') == 'in_preparation' ? 'selected' : '' ?>>In Preparation Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="roi-by-vehicle.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ROI Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-success">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-success">Average ROI</div>
                                <div class="h4 mb-0 font-weight-bold <?= getROIPercentageClass($reportData['total_summary']['avg_roi']) ?>">
                                    <?= number_format($reportData['total_summary']['avg_roi'], 1) ?>%
                                </div>
                                <small class="text-muted">Across sold vehicles</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-primary">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-primary">Total Profit</div>
                                <div class="h4 mb-0 font-weight-bold <?= getProfitClass($reportData['total_summary']['total_profit']) ?>">
                                    <?= formatCurrency($reportData['total_summary']['total_profit']) ?>
                                </div>
                                <small class="text-muted">Net profit across all vehicles</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-info">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-info">Best ROI</div>
                                <div class="h4 mb-0 font-weight-bold text-success">
                                    <?= number_format($reportData['total_summary']['best_roi'], 1) ?>%
                                </div>
                                <small class="text-muted">Highest performing vehicle</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-warning">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-warning">Total Investment</div>
                                <div class="h4 mb-0 font-weight-bold"><?= formatCurrency($reportData['total_summary']['total_costs']) ?></div>
                                <small class="text-muted">Purchase + Repair costs</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROI Category Breakdown -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">ROI Performance Categories</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php foreach ($reportData['roi_summary'] as $category => $summary): ?>
                                <div class="col-xl-2 col-md-3 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <span class="badge <?= getROICategoryClass($category) ?> mb-2"><?= $category ?></span>
                                            <div class="h4 mb-0"><?= $summary['count'] ?> vehicles</div>
                                            <small class="text-muted <?= getProfitClass($summary['total_profit'] ?? 0)  ?>">
                                                <?= formatCurrency($summary['total_profit']) ?? 0 ?> profit
                                            </small>
                                            <?php if ($summary['count'] > 0): ?>
                                                <div class="small text-muted">
                                                    Avg: <?= number_format($summary['avg_roi'] ?? 0, 1) ?>% ROI
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ROI Details Table -->
                 <div class="card pt-3">
                    <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;vehicle&quot;,&quot;purchase_price&quot;,&quot;repair_costs&quot;,&quot;total_investment&quot;,&quot;sale_price&quot;,&quot;net_profit&quot;,&quot;roi&quot;,&quot;roi_category&quot;,&quot;days&quot;,&quot;status&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">

                        <div class="row">
                            <div class="col-8">
                                <h3 class="p-3">Vehicle ROI Analysis </h3>
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
                                            <th class="sort border-top ps-2" data-sort="vehicle">Vehicle</th>
                                            <th class="sort border-top " data-sort="purchase_price">Purchase Price</th>
                                            <th class="sort border-top" data-sort="repair_costs">Total Costs</th>
                                            <th class="sort border-top " data-sort="total_investment">Total Investment</th>
                                            <th class="sort border-top" data-sort="sale_price">Total Revenue</th>
                                            <th class="sort border-top" data-sort="net_profit">Net Profit</th>
                                            <th class="sort border-top" data-sort="roi">ROI%</th>
                                            <th class="sort border-top" data-sort="roi-category">ROI Category</th>
                                            <th class="sort border-top" data-sort="days">Days</th>
                                            <th class="sort border-top" data-sort="status">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="list">
                                        <?php foreach ($reportData['roi_data'] as $vehicle): ?>
                                        <tr>
                                            <td class="ps-3 align-middle vehicle">
                                                <div class="fw-bold"><?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($vehicle['regnumber']) ?></small>
                                            </td>
                                            <td class="align-middle purchase_price"><?= formatCurrency($vehicle['purchase_price']) ?></td>
                                            <td class="align-middle repair_costs">
                                                <?php if ($vehicle['total_cost'] > 0): ?>
                                                    <span class="text-warning"><?= formatCurrency($vehicle['total_cost']-$vehicle['purchase_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle total_investment fw-bold"><?= formatCurrency($vehicle['total_cost']) ?></td>
                                            <td class="align-middle sale_price">
                                                <?php if ($vehicle['total_revenue']): ?>
                                                    <?= formatCurrency($vehicle['total_revenue']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle net_profit fw-bold <?= getProfitClass($vehicle['net_profit']) ?>">
                                                <?php if ($vehicle['net_profit'] !== null): ?>
                                                    <?= formatCurrency($vehicle['net_profit']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle roi fw-bold <?= getROIPercentageClass($vehicle['roi_percentage']) ?>">
                                                <?php if ($vehicle['roi_percentage'] !== null): ?>
                                                    <?= $vehicle['roi_percentage'] ?>%
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle roi_category">
                                                <span class="badge <?= getROICategoryClass($vehicle['roi_category']) ?>">
                                                    <?= $vehicle['roi_category'] ?>
                                                </span>
                                            </td>
                                            <td class="align-middle days">
                                                <?= $vehicle['days_in_inventory'] ?>
                                            </td>
                                            <td class="align-middle status">
                                                <span class="badge badge-phoenix badge-phoenix-<?=
                                                                        $vehicle['status'] == 'sold' ? 'success' : ($vehicle['status'] == 'available' ? 'info' : 'warning')
                                                                        ?>">
                                                    <?= ucfirst($vehicle['status']) ?>
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
                
                <!-- ROI Insights -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">💡 ROI Insights & Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Performance Analysis:</h6>
                                <ul>
                                    <li><strong>Excellent ROI (>30%):</strong> <?= $reportData['roi_summary']['Excellent']['count'] ?> vehicles</li>
                                    <li><strong>Good ROI (15-30%):</strong> <?= $reportData['roi_summary']['Good']['count'] ?> vehicles</li>
                                    <li><strong>Break-even (0-15%):</strong> <?= $reportData['roi_summary']['Break-even']['count'] ?> vehicles</li>
                                    <li><strong>Loss Making:</strong> <?= $reportData['roi_summary']['Loss']['count'] ?> vehicles</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Strategic Recommendations:</h6>
                                <ul>
                                    <?php if ($reportData['roi_summary']['Loss']['count'] > $reportData['roi_summary']['Excellent']['count']): ?>
                                        <li>🔍 <strong>Review buying strategy</strong> - Too many loss-making vehicles</li>
                                        <li>💰 <strong>Focus on profitable models</strong> - Replicate successful purchases</li>
                                        <li>🔧 <strong>Control repair costs</strong> - High repairs killing profitability</li>
                                    <?php elseif ($reportData['total_summary']['avg_roi'] > 20): ?>
                                        <li>✅ <strong>Excellent performance</strong> - Keep current strategy</li>
                                        <li>📈 <strong>Scale successful approach</strong> - Increase inventory</li>
                                        <li>🎯 <strong>Focus on top performers</strong> - Buy similar vehicles</li>
                                    <?php else: ?>
                                        <li>⚡ <strong>Optimize pricing</strong> - Small increases can boost ROI</li>
                                        <li>🛒 <strong>Negotiate better purchases</strong> - Lower acquisition costs</li>
                                        <li>📊 <strong>Analyze top performers</strong> - Identify winning patterns</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once("footer.php"); ?>
        </div>

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