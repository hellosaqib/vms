<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/expense_functions.php'; // New functions file
require_once 'includes/errors.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

$pageTitle = "Expense Dashboard";

if (!hasPermission('manage_expenses')) {
    setError("Access denied");
    header("Location: access-denied.php");
    exit;
}

// Date range calculation based on filters
$date_range = $_GET['date_range'] ?? '7_days';
$month = $_GET['month'] ?? '';

// Calculate dates based on quick range selector
switch ($date_range) {
    case '7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'this_quarter':
        $current_month = date('n');
        $current_quarter = ceil($current_month / 3);
        $start_month = (($current_quarter - 1) * 3) + 1;
        $start_date = date('Y-' . sprintf('%02d', $start_month) . '-01');
        $end_date = date('Y-' . sprintf('%02d', $start_month + 2) . '-t');
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
}

// Apply month filter if selected
if (!empty($month)) {
    $year = date('Y');
    $start_date = $year . '-' . $month . '-01';
    $end_date = $year . '-' . $month . '-' . date('t', strtotime($start_date));
}

// Ensure we have numeric values even if data is empty
$summary = getExpenseSummary($start_date, $end_date);
$top_vehicles = getTopExpensiveVehicles(5);
$recent_expenses = getCombinedExpenses([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'limit' => 20
]);

// Ensure we have arrays even if empty
if (empty($top_vehicles)) $top_vehicles = [];
if (empty($recent_expenses)) $recent_expenses = [];

$monthly_trends = getMonthlyExpenseTrend(6);
$expense_breakdown = getExpenseBreakdown($start_date, $end_date);
$profitability = getVehiclesProfitability();
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
    <!-- In your head section -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <?php displayMessage(); ?>
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12">
                        <!-- Page Header -->
                        <div class="row flex-between-center mb-4">
                            <div class="col-auto">
                                <h2 class="mb-2">Expense Dashboard</h2>
                                <h5 class="text-body-tertiary fw-semibold">Complete overview of all business expenses</h5>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-2">
                                    <a href="add-expense.php" class="btn btn-primary">Add Expense</a>
                                    <a href="add-repair.php" class="btn btn-warning">Add Repair</a>
                                </div>
                            </div>
                        </div>

                        <!-- Date Filter -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end">
                                    <!-- Quick Date Range Selector -->
                                    <div class="col-md-3">
                                        <label class="form-label">Quick Date Range</label>
                                        <select name="date_range" class="form-select" onchange="this.form.submit()">
                                            <option value="7_days" <?= ($_GET['date_range'] ?? '') == '7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                                            <option value="this_month" <?= ($_GET['date_range'] ?? '') == 'this_month' ? 'selected' : '' ?>>This Month</option>
                                            <option value="last_month" <?= ($_GET['date_range'] ?? '') == 'last_month' ? 'selected' : '' ?>>Previous Month</option>
                                            <option value="this_quarter" <?= ($_GET['date_range'] ?? '') == 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                                            <option value="this_year" <?= ($_GET['date_range'] ?? '') == 'this_year' ? 'selected' : '' ?>>This Year</option>
                                            <option value="custom" <?= ($_GET['date_range'] ?? '') == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                                        </select>
                                    </div>

                                    <!-- Month Selector -->
                                    <!-- <div class="col-md-2">
                                        <label class="form-label">Select Month</label>
                                        <select name="month" class="form-select" onchange="this.form.submit()">
                                            <option value="">All Months</option>
                                            <option value="01" <?= ($_GET['month'] ?? '') == '01' ? 'selected' : '' ?>>January</option>
                                            <option value="02" <?= ($_GET['month'] ?? '') == '02' ? 'selected' : '' ?>>February</option>
                                            <option value="03" <?= ($_GET['month'] ?? '') == '03' ? 'selected' : '' ?>>March</option>
                                            <option value="04" <?= ($_GET['month'] ?? '') == '04' ? 'selected' : '' ?>>April</option>
                                            <option value="05" <?= ($_GET['month'] ?? '') == '05' ? 'selected' : '' ?>>May</option>
                                            <option value="06" <?= ($_GET['month'] ?? '') == '06' ? 'selected' : '' ?>>June</option>
                                            <option value="07" <?= ($_GET['month'] ?? '') == '07' ? 'selected' : '' ?>>July</option>
                                            <option value="08" <?= ($_GET['month'] ?? '') == '08' ? 'selected' : '' ?>>August</option>
                                            <option value="09" <?= ($_GET['month'] ?? '') == '09' ? 'selected' : '' ?>>September</option>
                                            <option value="10" <?= ($_GET['month'] ?? '') == '10' ? 'selected' : '' ?>>October</option>
                                            <option value="11" <?= ($_GET['month'] ?? '') == '11' ? 'selected' : '' ?>>November</option>
                                            <option value="12" <?= ($_GET['month'] ?? '') == '12' ? 'selected' : '' ?>>December</option>
                                        </select>
                                    </div> -->

                                    <!-- Custom Date Range (shown only when custom is selected) -->
                                    <div class="col-md-3" id="customDateRange" style="display: <?= ($_GET['date_range'] ?? '') == 'custom' ? 'block' : 'none' ?>;">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                                    </div>
                                    <div class="col-md-3" id="customDateRangeEnd" style="display: <?= ($_GET['date_range'] ?? '') == 'custom' ? 'block' : 'none' ?>;">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary">Apply</button>
                                        <a href="expense-dashboard.php" class="btn btn-outline-secondary">Reset</a>
                                    </div>


                                </form>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row g-3 mb-4">
                            <h3 class="">
                                <?php
                                $date_range = $_GET['date_range'] ?? '7_days';

                                $headings = [
                                    '7_days' => 'Last 7 Days Expenses',
                                    'this_month' => 'This Month Expenses',
                                    'last_month' => 'Last Month Expenses',
                                    'this_quarter' => 'This Quarter Expenses',
                                    'this_year' => 'This Year Expenses',
                                    'custom' => 'Custom Period Expenses (' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)) . ')'
                                ];

                                echo $headings[$date_range] ?? 'Expense Overview';
                                ?>
                            </h3>
                            <div class="col-md-3">
                                <div class="card bg-danger bg-opacity-10 border-danger clickable-card" onclick="switchToTab('all')" style="cursor: pointer;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="text-danger">All Costs</h5>
                                                <h3 class="text-danger">£<?= number_format($summary['grand_total'], 2) ?></h3>
                                                <small class="text-body-tertiary">
                                                    <?= $start_date ?> to <?= $end_date ?>
                                                </small>
                                            </div>
                                            <div class="fs-4 text-danger">
                                                <i class="fas fa-money-bill-wave-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success bg-opacity-10 border-success clickable-card" onclick="switchToTab('jobcard')" style="cursor: pointer;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="text-success">Job Card Costs</h5>
                                                <h3 class="text-success">£<?= number_format($summary['job_card'], 2) ?></h3>
                                                <small class="text-body-tertiary">
                                                    <?= $summary['grand_total'] > 0 ? number_format(($summary['job_card'] / $summary['grand_total']) * 100, 1) : 0 ?>% of total
                                                </small>
                                            </div>
                                            <div class="fs-4 text-success">
                                                <i class="fas fa-tools"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-primary bg-opacity-10 border-primary clickable-card" onclick="switchToTab('expense')" style="cursor: pointer;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="text-primary">General Expenses</h5>
                                                <h3 class="text-primary">£<?= number_format($summary['expense'], 2) ?></h3>
                                                <small class="text-body-tertiary">
                                                    <?= $summary['grand_total'] > 0 ? number_format(($summary['expense'] / $summary['grand_total']) * 100, 1) : 0 ?>% of total
                                                </small>
                                            </div>
                                            <div class="fs-4 text-primary">
                                                <i class="fas fa-file-invoice"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning bg-opacity-10 border-warning clickable-card" onclick="switchToTab('repair')" style="cursor: pointer;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="text-warning">Repair Costs</h5>
                                                <h3 class="text-warning">£<?= number_format($summary['repair'], 2) ?></h3>
                                                <small class="text-body-tertiary">
                                                    <?= $summary['grand_total'] > 0 ? number_format(($summary['repair'] / $summary['grand_total']) * 100, 1) : 0 ?>% of total
                                                </small>
                                            </div>
                                            <div class="fs-4 text-warning">
                                                <i class="fas fa-wrench"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts and Data Section -->
                        <div class="row g-4">


                            <!-- Recent Expenses -->
                            <!-- Expense Details Tabs -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <ul class="nav nav-tabs card-header-tabs" id="expenseTabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="jobcard-tab" data-bs-toggle="tab" data-bs-target="#jobcard" type="button" role="tab">
                                                    <i class="fas fa-tools me-2"></i>Job Card Costs
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense" type="button" role="tab">
                                                    <i class="fas fa-file-invoice me-2"></i>General Expenses
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="repair-tab" data-bs-toggle="tab" data-bs-target="#repair" type="button" role="tab">
                                                    <i class="fas fa-wrench me-2"></i>Repair Costs
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                                                    <i class="fas fa-list me-2"></i>All Expenses
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-body">
                                        <div class="tab-content" id="expenseTabsContent">

                                            <!-- Job Card Costs Tab -->
                                            <div class="tab-pane fade show active" id="jobcard" role="tabpanel">
                                                <?php
                                                $jobcard_expenses = getExpensesByType('job_card', $start_date, $end_date);
                                                displayExpenseTable($jobcard_expenses, 'Job Card Expenses');
                                                ?>
                                            </div>

                                            <!-- General Expenses Tab -->
                                            <div class="tab-pane fade" id="expense" role="tabpanel">
                                                <?php
                                                $general_expenses = getExpensesByType('expense', $start_date, $end_date);
                                                displayExpenseTable($general_expenses, 'General Expenses');
                                                ?>
                                            </div>

                                            <!-- Repair Costs Tab -->
                                            <div class="tab-pane fade" id="repair" role="tabpanel">
                                                <?php
                                                $repair_expenses = getExpensesByType('repair', $start_date, $end_date);
                                                displayExpenseTable($repair_expenses, 'Repair Costs');
                                                ?>
                                            </div>

                                            <!-- All Expenses Tab -->
                                            <div class="tab-pane fade" id="all" role="tabpanel">
                                                <?php
                                                $all_expenses = getCombinedExpenses([
                                                    'start_date' => $start_date,
                                                    'end_date' => $end_date,
                                                    'limit' => 100
                                                ]);
                                                displayExpenseTable($all_expenses, 'All Expenses');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Expense Breakdown Chart -->
                            <div class="col-xl-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Expense Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expenseBreakdownChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Expensive Vehicles -->
                            <div class="col-xl-6">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Top 5 Most Expensive Vehicles</h5>
                                        <a href="vehicles-list.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Vehicle</th>
                                                        <th>Purchase</th>
                                                        <th>Total Costs</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($top_vehicles as $vehicle): ?>
                                                        <?php
                                                        $total_costs = $vehicle['job_card_costs'] + $vehicle['expense_costs'] + $vehicle['repair_costs'];
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($vehicle['regnumber']) ?></strong><br>
                                                                <small class="text-body-tertiary"><?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></small>
                                                            </td>
                                                            <td>£<?= number_format($vehicle['purchase_price'], 2) ?></td>
                                                            <td class="fw-bold text-danger">£<?= number_format($total_costs, 2) ?></td>
                                                            <td>
                                                                <span class="badge bg-<?=
                                                                                        $vehicle['status'] == 'sold' ? 'success' : ($vehicle['status'] == 'available' ? 'primary' : 'warning')
                                                                                        ?>">
                                                                    <?= ucfirst($vehicle['status']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <?php include("footer.php"); ?>
        </div>
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

    <script>
        // Show/hide custom date range based on selection
        document.addEventListener('DOMContentLoaded', function() {
            const dateRangeSelect = document.querySelector('select[name="date_range"]');
            const customDateRange = document.getElementById('customDateRange');
            const customDateRangeEnd = document.getElementById('customDateRangeEnd');

            function toggleCustomDateRange() {
                if (dateRangeSelect.value === 'custom') {
                    customDateRange.style.display = 'block';
                    customDateRangeEnd.style.display = 'block';
                } else {
                    customDateRange.style.display = 'none';
                    customDateRangeEnd.style.display = 'none';
                }
            }

            dateRangeSelect.addEventListener('change', toggleCustomDateRange);
            toggleCustomDateRange(); // Initial call
        });
    </script>
    <!-- JavaScript for Charts -->
    <script>
        // Expense Breakdown Chart
        const breakdownCtx = document.getElementById('expenseBreakdownChart').getContext('2d');
        const breakdownChart = new Chart(breakdownCtx, {
            type: 'doughnut',
            data: {
                labels: ['Job Card Costs', 'General Expenses', 'Repair Costs'],
                datasets: [{
                    data: [
                        <?= $summary['job_card'] ?>,
                        <?= $summary['expense'] ?>,
                        <?= $summary['repair'] ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#ffc107'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: £${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script>
        // Function to switch tabs when summary cards are clicked
        function switchToTab(tabName) {
            const tab = document.getElementById(tabName + '-tab');
            if (tab) {
                bootstrap.Tab.getInstance(tab) || new bootstrap.Tab(tab);
                tab.click();
            }
        }

        // Add hover effects to clickable cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.clickable-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
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