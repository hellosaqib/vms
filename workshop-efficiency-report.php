<?php
// Authentication & Config
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/reports_functions.php'; // New functions file
$pageTitle = 'Workshop Efficiency Report';
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
    $filters['job_card_status'] = $_GET['status'];
}

// Generate the report
$reportData = generateWorkshopEfficiencyReport($db, $filters);

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
                        <h2 class="mb-2 text-body-emphasis">Workshop Efficiency Report</h2>
                        <h5 class="text-body-tertiary fw-semibold">Workshop performance and repair cost analysis</h5>
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
                                <label class="form-label">Job Card Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="Completed" <?= ($_GET['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed Only</option>
                                    <option value="In Progress" <?= ($_GET['status'] ?? '') == 'In Progress' ? 'selected' : '' ?>>In Progress Only</option>
                                    <option value="Pending" <?= ($_GET['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="workshop-efficiency.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Workshop Performance Summary -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-primary">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-primary">Total Job Cards</div>
                                <div class="h4 mb-0 font-weight-bold"><?= $reportData['summary']['total_job_cards'] ?></div>
                                <small class="text-muted">
                                    <?= $reportData['summary']['completed_job_cards'] ?> completed
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-success">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-success">Task Completion Rate</div>
                                <div class="h4 mb-0 font-weight-bold"><?= number_format($reportData['summary']['overall_task_completion_rate'], 1) ?>%</div>
                                <small class="text-muted">
                                    <?= $reportData['summary']['completed_tasks'] ?>/<?= $reportData['summary']['total_tasks'] ?> tasks
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-warning">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-warning">Avg Completion Time</div>
                                <div class="h4 mb-0 font-weight-bold"><?= number_format($reportData['summary']['avg_days_taken'], 1) ?> days</div>
                                <small class="text-muted">Per job card</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-info">
                            <div class="card-body text-center py-3">
                                <div class="h6 mb-1 text-info">Total Repair Costs</div>
                                <div class="h4 mb-0 font-weight-bold"><?= formatCurrency($reportData['summary']['total_repair_costs']) ?></div>
                                <small class="text-muted">
                                    £<?= number_format($reportData['summary']['avg_cost_per_job'], 0) ?> avg per job
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Efficiency Rating Breakdown -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Workshop Efficiency Ratings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php foreach ($reportData['efficiency_summary'] as $rating => $summary): ?>
                                <div class="col-xl-2 col-md-4 col-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <span class="badge <?= getEfficiencyRatingClass($rating) ?> mb-2"><?= $rating ?></span>
                                            <div class="h4 mb-0"><?= $summary['count'] ?></div>
                                            <small class="text-muted">
                                                <?php if ($summary['avg_days'] > 0): ?>
                                                    Avg: <?= number_format($summary['avg_days'], 1) ?> days
                                                <?php else: ?>
                                                    Job cards
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Job Card Details Table -->
                <div class="card pt-3">
                    <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;job_card&quot;,&quot;vehicle&quot;,&quot;priority&quot;,&quot;status&quot;,&quot;tasks&quot;,&quot;completion&quot;,&quot;days&quot;,&quot;repair_costs&quot;,&quot;efficiency&quot;,&quot;assigned_to&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">

                        <div class="row">
                            <div class="col-8">
                                <h3 class="p-3">Job Card Performance Details </h3>
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
                                            <th class="sort border-top ps-2" data-sort="job_card">Job Card</th>
                                            <th class="sort border-top" data-sort="vehicle">Vehicle</th>
                                            <th class="sort border-top" data-sort="priority">Priority</th>
                                            <th class="sort border-top " data-sort="status">Status</th>
                                            <th class="sort border-top " data-sort="tasks">Tasks</th>
                                            <th class="sort border-top" data-sort="completion">Completion</th>
                                            <th class="sort border-top" data-sort="days">Days Taken</th>
                                            <th class="sort border-top" data-sort="repair_costs">Repair Costs</th>
                                            <th class="sort border-top" data-sort="efficiency">Efficiency</th>
                                            <th class="sort border-top" data-sort="assigned_to">Assigned To</th>
                                        </tr>
                                    </thead>
                                    <tbody class="list">
                                        <?php foreach ($reportData['workshop_data'] as $job): ?>
                                        <tr>
                                            <td class="ps-2 align-middle job_card">
                                                <div class="fw-bold"><?= htmlspecialchars($job['job_card_number']) ?></div>
                                                <small class="text-muted">
                                                    Created: <?= date('M j, Y', strtotime($job['created_at'])) ?>
                                                    <?php if ($job['date_started']): ?>
                                                        <br>Started: <?= date('M j, Y', strtotime($job['date_started'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="align-middle vehicle">
                                                <div class="fw-bold"><?= htmlspecialchars($job['make']) ?> <?= htmlspecialchars($job['model']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($job['regnumber']) ?></small>
                                            </td>
                                            <td class="align-middle priority">
                                                <span class="badge <?= getPriorityClass($job['priority']) ?>">
                                                    <?= $job['priority'] ?>
                                                </span>
                                            </td>
                                            <td class="align-middle status">
                                                <span class="badge <?= getStatusClass($job['status']) ?>">
                                                    <?= $job['status'] ?>
                                                </span>
                                            </td>
                                            <td class="align-middle tasks">
                                                <small>
                                                    <?= $job['completed_tasks'] + $job['cancelled_tasks'] ?>/<?= $job['total_tasks'] ?>
                                                </small>
                                            </td>
                                            <td class="align-middle completion">
                                                <span class="fw-bold <?= $job['completion_rate'] >= 80 ? 'text-success' : ($job['completion_rate'] >= 50 ? 'text-warning' : 'text-danger') ?>">
                                                    <?= $job['completion_rate'] ?>%
                                                </span>
                                            </td>
                                            <td class="align-middle days fw-bold <?= getWorkshopDaysClass($job['days_taken']) ?>">
                                                <?= $job['days_taken'] ?> days
                                            </td>
                                            <td class="align-middle repair_costs">
                                                <?php if ($job['repair_costs'] > 0): ?>
                                                    <span class="text-warning fw-bold"><?= formatCurrency($job['repair_costs']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle efficiency">
                                                <span class="badge <?= getEfficiencyRatingClass($job['efficiency_rating']) ?>">
                                                    <?= $job['efficiency_rating'] ?>
                                                </span>
                                            </td>
                                            <td class="align-middle assigned_to">
                                                <?php if ($job['assigned_to']): ?>
                                                    <small><?= htmlspecialchars($job['assigned_to']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
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
                
                
                <!-- Workshop Insights -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 Workshop Performance Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Efficiency Analysis:</h6>
                                <ul>
                                    <li><strong>Excellent Efficiency (≤3 days):</strong> <?= $reportData['efficiency_summary']['Excellent']['count'] ?> job cards</li>
                                    <li><strong>Good Efficiency (4-7 days):</strong> <?= $reportData['efficiency_summary']['Good']['count'] ?> job cards</li>
                                    <li><strong>Average Efficiency (8-14 days):</strong> <?= $reportData['efficiency_summary']['Average']['count'] ?> job cards</li>
                                    <li><strong>Poor Efficiency (>14 days):</strong> <?= $reportData['efficiency_summary']['Poor']['count'] ?> job cards</li>
                                    <li><strong>Currently In Progress:</strong> <?= $reportData['efficiency_summary']['In Progress']['count'] ?> job cards</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Recommendations:</h6>
                                <ul>
                                    <?php if ($reportData['efficiency_summary']['Poor']['count'] > $reportData['efficiency_summary']['Excellent']['count']): ?>
                                        <li>⚡ <strong>Improve turnaround time</strong> - Too many slow job cards</li>
                                        <li>🔧 <strong>Review resource allocation</strong> - Assign more staff to backlog</li>
                                        <li>📋 <strong>Standardize processes</strong> - Create efficient workflows</li>
                                    <?php elseif ($reportData['summary']['avg_days_taken'] <= 5): ?>
                                        <li>✅ <strong>Excellent performance</strong> - Maintain current processes</li>
                                        <li>📈 <strong>Scale operations</strong> - Consider taking on more work</li>
                                        <li>🎯 <strong>Focus on cost control</strong> - Maintain efficiency while watching costs</li>
                                    <?php else: ?>
                                        <li>⏱️ <strong>Reduce completion time</strong> - Target under 7 days average</li>
                                        <li>💰 <strong>Monitor repair costs</strong> - Keep average under £500 per job</li>
                                        <li>👥 <strong>Balance workload</strong> - Ensure even distribution among staff</li>
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