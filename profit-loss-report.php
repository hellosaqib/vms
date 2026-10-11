<?php
// Authentication & Config
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/reports_functions.php'; // New functions file
$pageTitle = 'Profit & Loss Statement';
//include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Handle filters
$filters = [];
if (isset($_GET['period'])) {
    $dateRange = getTimePeriodData($_GET['period']);
    $filters = array_merge($filters, $dateRange);
}

// Custom date range
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

// Generate the report
$reportData = generateProfitLossReport($db, $filters);
// Debug output (remove after testing)
echo "<!-- Debug: Filters = " . print_r($filters, true) . " -->";
echo "<!-- Debug: Report Data Count = " . count($reportData['pl_data'] ?? []) . " -->";

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
                <h2 class="mb-2 text-body-emphasis">Profit & Loss Statement</h2>
                <h5 class="text-body-tertiary fw-semibold">Complete financial performance overview</h5>
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
                <h5 class="mb-0">Report Period</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Quick Period</label>
                        <select name="period" class="form-select">
                            <option value="today" <?= ($_GET['period'] ?? '') == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= ($_GET['period'] ?? '') == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="month" <?= ($_GET['period'] ?? '') == 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="quarter" <?= ($_GET['period'] ?? '') == 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                            <option value="year" <?= ($_GET['period'] ?? '') == 'year' ? 'selected' : '' ?>>This Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?= $_GET['start_date'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?= $_GET['end_date'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="profit-loss-report.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Key Financial Metrics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <div class="h6 mb-1 text-success">Total Revenue</div>
                        <div class="h4 mb-0 font-weight-bold"><?= formatCurrency($reportData['totals']['total_revenue']) ?></div>
                        <small class="text-muted">Sales income</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <div class="h6 mb-1 text-primary">Gross Profit</div>
                        <div class="h4 mb-0 font-weight-bold <?= getProfitLossClass($reportData['totals']['gross_profit']) ?>">
                            <?= formatCurrency($reportData['totals']['gross_profit']) ?>
                        </div>
                        <small class="text-muted"><?= number_format($reportData['percentages']['gross_margin'], 1) ?>% margin</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <div class="h6 mb-1 text-warning">Total Expenses</div>
                        <div class="h4 mb-0 font-weight-bold"><?= formatCurrency($reportData['totals']['total_expenses']) ?></div>
                        <small class="text-muted">Repairs + Operating</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <div class="h6 mb-1 text-info">Net Profit</div>
                        <div class="h4 mb-0 font-weight-bold <?= getProfitLossClass($reportData['totals']['net_profit']) ?>">
                            <?= formatCurrency($reportData['totals']['net_profit']) ?>
                        </div>
                        <small class="text-muted"><?= number_format($reportData['percentages']['net_margin'], 1) ?>% margin</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Statement -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Profit & Loss Statement</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Category</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">% of Revenue</th>
                                <th class="text-center">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- REVENUE SECTION -->
                            <tr class="table-success">
                                <td class="ps-3 fw-bold">REVENUE</td>
                                <td class="text-end fw-bold"><?= formatCurrency($reportData['totals']['total_revenue']) ?></td>
                                <td class="text-end fw-bold">100.0%</td>
                                <td class="text-center"><span class="badge bg-success">Income</span></td>
                            </tr>
                            
                            <!-- COST OF GOODS SOLD -->
                            <tr class="table-danger">
                                <td class="ps-3 fw-bold">COST OF GOODS SOLD</td>
                                <td class="text-end fw-bold"><?= formatCurrency($reportData['totals']['total_cogs']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($reportData['percentages']['cogs_percentage'], 1) ?>%</td>
                                <td class="text-center"><span class="badge bg-danger">Expense</span></td>
                            </tr>
                            
                            <!-- GROSS PROFIT -->
                            <tr class="table-primary">
                                <td class="ps-3 fw-bold">GROSS PROFIT</td>
                                <td class="text-end fw-bold <?= getProfitLossClass($reportData['totals']['gross_profit']) ?>">
                                    <?= formatCurrency($reportData['totals']['gross_profit']) ?>
                                </td>
                                <td class="text-end fw-bold <?= getProfitLossClass($reportData['totals']['gross_profit']) ?>">
                                    <?= number_format($reportData['percentages']['gross_margin'], 1) ?>%
                                </td>
                                <td class="text-center"><span class="badge bg-primary">Profit</span></td>
                            </tr>
                            
                            <!-- EXPENSES HEADER -->
                            <tr>
                                <td class="ps-3 fw-bold">OPERATING EXPENSES</td>
                                <td class="text-end fw-bold"><?= formatCurrency($reportData['totals']['total_expenses']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($reportData['percentages']['operating_costs_percentage'], 1) ?>%</td>
                                <td class="text-center"><span class="badge bg-warning">Expenses</span></td>
                            </tr>
                            
                            <!-- DETAILED EXPENSES -->
                            <?php foreach ($reportData['pl_data'] as $item): ?>
                                <?php if ($item['is_expense'] == 1 && $item['category'] != 'cogs'): ?>
                                    <tr>
                                        <td class="ps-4"><?= htmlspecialchars($item['description']) ?></td>
                                        <td class="text-end"><?= formatCurrency($item['amount']) ?></td>
                                        <td class="text-end">
                                            <?php if ($reportData['totals']['total_revenue'] > 0): ?>
                                                <?= number_format(($item['amount'] / $reportData['totals']['total_revenue']) * 100, 1) ?>%
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= getPLCategoryClass($item['category']) ?>">
                                                Expense
                                            </span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- NET PROFIT -->
                            <tr class="table-info">
                                <td class="ps-3 fw-bold">NET PROFIT</td>
                                <td class="text-end fw-bold <?= getProfitLossClass($reportData['totals']['net_profit']) ?>">
                                    <?= formatCurrency($reportData['totals']['net_profit']) ?>
                                </td>
                                <td class="text-end fw-bold <?= getProfitLossClass($reportData['totals']['net_profit']) ?>">
                                    <?= number_format($reportData['percentages']['net_margin'], 1) ?>%
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $reportData['totals']['net_profit'] >= 0 ? 'success' : 'danger' ?>">
                                        <?= $reportData['totals']['net_profit'] >= 0 ? 'Profit' : 'Loss' ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">💰 Financial Insights</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Profitability Analysis:</h6>
                        <ul>
                            <li><strong>Gross Margin:</strong> <?= number_format($reportData['percentages']['gross_margin'], 1) ?>% 
                                <?php if ($reportData['percentages']['gross_margin'] > 25): ?>
                                    <span class="badge bg-success">Excellent</span>
                                <?php elseif ($reportData['percentages']['gross_margin'] > 15): ?>
                                    <span class="badge bg-warning">Good</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Needs Improvement</span>
                                <?php endif; ?>
                            </li>
                            <li><strong>Net Margin:</strong> <?= number_format($reportData['percentages']['net_margin'], 1) ?>%
                                <?php if ($reportData['percentages']['net_margin'] > 15): ?>
                                    <span class="badge bg-success">Excellent</span>
                                <?php elseif ($reportData['percentages']['net_margin'] > 8): ?>
                                    <span class="badge bg-warning">Good</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Needs Improvement</span>
                                <?php endif; ?>
                            </li>
                            <li><strong>Expense Ratio:</strong> <?= number_format($reportData['percentages']['operating_costs_percentage'], 1) ?>% of revenue</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Recommendations:</h6>
                        <ul>
                            <?php if ($reportData['percentages']['net_margin'] < 8): ?>
                                <li>🔧 Focus on reducing repair costs</li>
                                <li>💰 Review vehicle pricing strategy</li>
                                <li>🛒 Negotiate better purchase prices</li>
                            <?php else: ?>
                                <li>✅ Business is performing well</li>
                                <li>📈 Consider scaling operations</li>
                                <li>🔍 Monitor expense ratios</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once("footer.php");?>
</div>


    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    

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