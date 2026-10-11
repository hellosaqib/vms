<?php
// Authentication & Config
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/dashboard_functions.php'; // New functions file
$pageTitle = 'Dashboard - Car Trade System';
include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

// =============================================================================
// EXECUTIVE SUMMARY METRICS
// =============================================================================
try {
    $executiveMetrics = getExecutiveSummaryMetrics($db);
} catch (Exception $e) {
    error_log("Dashboard Executive Metrics Error: " . $e->getMessage());
    $executiveMetrics = [];
}

// =============================================================================
// SALES PERFORMANCE METRICS
// =============================================================================
try {
    $salesMetrics = getSalesPerformanceMetrics($db);
} catch (Exception $e) {
    error_log("Dashboard Sales Metrics Error: " . $e->getMessage());
    $salesMetrics = [];
}

// =============================================================================
// OPERATIONAL EFFICIENCY METRICS
// =============================================================================
try {
    $operationalMetrics = getOperationalMetrics($db);
} catch (Exception $e) {
    error_log("Dashboard Operational Metrics Error: " . $e->getMessage());
    $operationalMetrics = [];
}

// =============================================================================
// CHART DATA
// =============================================================================
try {
    $chartData = getChartData($db);
} catch (Exception $e) {
    error_log("Dashboard Chart Data Error: " . $e->getMessage());
    $chartData = [];
}

// =============================================================================
// ALERTS & ACTIONS
// =============================================================================
try {
    $alertsData = getAlertsData($db);
} catch (Exception $e) {
    error_log("Dashboard Alerts Error: " . $e->getMessage());
    $alertsData = [];
}

// Calculate some derived metrics for display
$totalAlerts = count($alertsData['urgent_jobs'] ?? []) + 
               count($alertsData['high_cost_vehicles'] ?? []) + 
               count($alertsData['overdue_tasks'] ?? []);
?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo ($pageTitle); ?></title>

  <!-- Favicons -->
  <link rel="apple-touch-icon" sizes="180x180" href="/uploads/images/britdrive.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/uploads/images/britdrive.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/uploads/images/britdrive.png">
  <link rel="shortcut icon" type="image/x-icon" href="/uploads/images/britdrive.ico">

  <!-- Stylesheets -->
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
  <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/line.css">
  <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
  <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <style>
    .metric-card {
      transition: all 0.3s ease;
      border-left: 4px solid #dee2e6;
    }
    .metric-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .metric-positive { border-left-color: #00d97e; }
    .metric-warning { border-left-color: #f6c343; }
    .metric-danger { border-left-color: #e63757; }
    .metric-info { border-left-color: #2c7be5; }
    
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
  <main class="main" id="top">
    <?php include("left-menu.php"); ?>
    
    <div class="content">
      <div class="pb-5">
        <!-- Header -->
        <div class="row gy-3 mb-3 justify-content-between border-bottom">
          <div class="col-md-9 col-auto">
            <h2 class="mb-2 text-body-emphasis">Executive Dashboard</h2>
            <h5 class="text-body-tertiary fw-semibold">Welcome <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($roleName) ?>)</h5>
          </div>
          <div class="col-md-3 col-auto text-end">
            <h5 class="text-muted"><?= date('M j, Y') ?></h5>
            <?php if ($totalAlerts > 0): ?>
              <span class="badge bg-danger"><?= $totalAlerts ?> Alerts</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
          <div class="col-12">
            <h5>Quick Actions</h5>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-phoenix-secondary" href="view-all-vehicles.php">
                <i class="fas fa-car-side me-2"></i>All Vehicles
              </a>
              <a class="btn btn-phoenix-success" href="add-vehicle.php">
                <i class="fas fa-car me-2"></i>New Vehicle
              </a>
              <a class="btn btn-phoenix-danger" href="add-new-job-card.php">
                <i class="far fa-list-alt me-2"></i>New Job Card
              </a>
              <a class="btn btn-phoenix-info" href="add-all-job-cards.php">
                <i class="fas fa-clipboard-list me-2"></i>All Job Cards
              </a>
            </div>
          </div>
        </div>

        <!-- =================================================================== -->
        <!-- EXECUTIVE SUMMARY ROW -->
        <!-- =================================================================== -->
        <div class="row g-3 mb-4">
          <div class="col-12">
            <h4 class="border-bottom pb-2">📊 Executive Summary</h4>
          </div>

          <!-- Total Revenue YTD -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-positive h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="text-muted mb-1">Total Revenue YTD</h6>
                    <h3 class="mb-0">£<?= number_format($executiveMetrics['revenue']['revenue_ytd'] ?? 0, 0) ?></h3>
                    <small class="text-muted">
                      <?= $executiveMetrics['revenue']['vehicles_sold_ytd'] ?? 0 ?> vehicles sold
                    </small>
                  </div>
                  <div class="bg-success rounded p-2">
                    <i class="fas fa-pound-sign text-white fa-lg"></i>
                  </div>
                </div>
                <div class="mt-2">
                  <span class="badge bg-success">YTD Performance</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Net Profit with Margin -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-info h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="text-muted mb-1">Net Profit YTD</h6>
                    <h3 class="mb-0">£<?= number_format($executiveMetrics['profit']['net_profit'] ?? 0, 0) ?></h3>
                    <small class="text-muted">
                      <?= number_format($executiveMetrics['profit']['profit_margin'] ?? 0, 1) ?>% margin
                    </small>
                  </div>
                  <div class="bg-info rounded p-2">
                    <i class="fas fa-chart-line text-white fa-lg"></i>
                  </div>
                </div>
                <div class="mt-2">
                  <span class="badge bg-info">
                    <?= $executiveMetrics['profit']['profitable_vehicles'] ?? 0 ?> profitable
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventory Turnover -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-warning h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="text-muted mb-1">Inventory Turnover</h6>
                    <h3 class="mb-0"><?= number_format($executiveMetrics['turnover']['turnover_rate'] ?? 0, 1) ?>x</h3>
                    <small class="text-muted">
                      <?= $executiveMetrics['turnover']['current_inventory'] ?? 0 ?> in stock
                    </small>
                  </div>
                  <div class="bg-warning rounded p-2">
                    <i class="fas fa-sync-alt text-white fa-lg"></i>
                  </div>
                </div>
                <div class="mt-2">
                  <span class="badge bg-warning text-dark">
                    £<?= number_format($executiveMetrics['turnover']['avg_inventory_value'] ?? 0, 0) ?> avg value
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- ROI per Vehicle -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-positive h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="text-muted mb-1">Avg ROI per Vehicle</h6>
                    <h3 class="mb-0"><?= number_format($executiveMetrics['roi']['avg_roi'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      Max: <?= number_format($executiveMetrics['roi']['max_roi'] ?? 0, 1) ?>%
                    </small>
                  </div>
                  <div class="bg-success rounded p-2">
                    <i class="fas fa-percentage text-white fa-lg"></i>
                  </div>
                </div>
                <div class="mt-2">
                  <span class="badge bg-success">Strong Returns</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- =================================================================== -->
        <!-- SALES PERFORMANCE ROW -->
        <!-- =================================================================== -->
        <div class="row g-3 mb-4">
          <div class="col-12">
            <h4 class="border-bottom pb-2">💰 Sales Performance</h4>
          </div>

          <!-- Sales Conversion Rate -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-info h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Sales Conversion Rate</h6>
                    <h3 class="mb-0"><?= number_format($salesMetrics['conversion']['conversion_rate'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      <?= $salesMetrics['conversion']['sold_ytd'] ?? 0 ?> sold / 
                      <?= $salesMetrics['conversion']['available_now'] ?? 0 ?> available
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Average Days to Sell -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-warning h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Avg Days to Sell</h6>
                    <h3 class="mb-0"><?= number_format($salesMetrics['days_to_sell']['avg_days_to_sell'] ?? 0, 0) ?> days</h3>
                    <small class="text-muted">
                      Fastest: <?= number_format($salesMetrics['days_to_sell']['min_days_to_sell'] ?? 0, 0) ?> days
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Gross Profit Margin -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-positive h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Gross Profit Margin</h6>
                    <h3 class="mb-0"><?= number_format($salesMetrics['gross_margin']['gross_margin'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      £<?= number_format($salesMetrics['gross_margin']['avg_gross_per_vehicle'] ?? 0, 0) ?> per vehicle
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Vehicle Acquisition Rate -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-info h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Vehicle Acquisition</h6>
                    <h3 class="mb-0"><?= number_format($salesMetrics['acquisition']['vehicles_acquired_ytd'] ?? 0, 0) ?> YTD</h3>
                    <small class="text-muted">
                      <?= $salesMetrics['acquisition']['monthly_acquisition'] ?? 0 ?> this month
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- =================================================================== -->
        <!-- OPERATIONAL EFFICIENCY ROW -->
        <!-- =================================================================== -->
        <div class="row g-3 mb-4">
          <div class="col-12">
            <h4 class="border-bottom pb-2">⚙️ Operational Efficiency</h4>
          </div>

          <!-- Job Card Completion Rate -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-positive h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Job Card Completion</h6>
                    <h3 class="mb-0"><?= number_format($operationalMetrics['completion_rate']['completion_rate'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      <?= $operationalMetrics['completion_rate']['completed'] ?? 0 ?> of 
                      <?= $operationalMetrics['completion_rate']['total_job_cards'] ?? 0 ?> completed
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Average Preparation Cost -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-warning h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Avg Preparation Cost</h6>
                    <h3 class="mb-0">£<?= number_format($operationalMetrics['preparation']['avg_preparation_cost'] ?? 0, 0) ?></h3>
                    <small class="text-muted">
                      <?= $operationalMetrics['preparation']['job_cards_with_costs'] ?? 0 ?> job cards
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Workshop Utilization -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-info h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Workshop Utilization</h6>
                    <h3 class="mb-0"><?= number_format($operationalMetrics['utilization']['utilization_rate'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      <?= $operationalMetrics['utilization']['active_mechanics'] ?? 0 ?> mechanics active
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Task Completion Rate -->
          <div class="col-xl-3 col-md-6">
            <div class="card metric-card metric-positive h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>
                    <h6 class="text-muted mb-1">Task Completion Rate</h6>
                    <h3 class="mb-0"><?= number_format($operationalMetrics['task_efficiency']['task_completion_rate'] ?? 0, 1) ?>%</h3>
                    <small class="text-muted">
                      <?= $operationalMetrics['task_efficiency']['completed_tasks'] ?? 0 ?> tasks done
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- =================================================================== -->
        <!-- CHARTS & ANALYTICS ROW -->
        <!-- =================================================================== -->
        <div class="row g-4">
          <!-- Revenue vs Profit Trend -->
          <div class="col-xl-8">
            <div class="card h-100">
              <div class="card-header">
                <h5 class="mb-0">📈 Revenue vs Profit Trend - <?= date('Y') ?></h5>
              </div>
              <div class="card-body">
                <canvas id="revenueProfitChart" height="300"></canvas>
              </div>
            </div>
          </div>

          <!-- Inventory Health -->
          <div class="col-xl-4">
            <div class="card h-100">
              <div class="card-header">
                <h5 class="mb-0">🚗 Inventory Health</h5>
              </div>
              <div class="card-body">
                <canvas id="inventoryHealthChart" height="300"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- =================================================================== -->
        <!-- ALERTS & ACTIONS SIDEBAR -->
        <!-- =================================================================== -->
        <div class="row mt-4">
          <div class="col-12">
            <h4 class="border-bottom pb-2">🚨 Alerts & Actions Needed</h4>
          </div>
        </div>

        <div class="row g-4">
          <!-- Urgent Job Cards -->
          <div class="col-xl-4">
            <div class="card border-danger">
              <div class="card-header bg-danger text-white">
                <h6 class="mb-0">🔴 Urgent Job Cards</h6>
              </div>
              <div class="card-body">
                <?php if (!empty($alertsData['urgent_jobs'])): ?>
                  <?php foreach ($alertsData['urgent_jobs'] as $job): ?>
                    <div class="border-start border-danger border-4 ps-3 mb-3">
                      <div class="fw-bold"><?= htmlspecialchars($job['make']) ?> <?= htmlspecialchars($job['model']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($job['regnumber']) ?></small>
                      <div class="mt-1">
                        <span class="badge bg-danger"><?= $job['pending_tasks'] ?> pending tasks</span>
                        <span class="badge bg-warning text-dark"><?= $job['days_open'] ?> days</span>
                      </div>
                      <a href="view-job-card.php?jc=<?= $job['job_card_number'] ?>" class="btn btn-sm btn-outline-danger mt-2">View</a>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center text-muted py-3">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p>No urgent job cards</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- High-Cost Vehicles -->
          <div class="col-xl-4">
            <div class="card border-warning">
              <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">⚠️ High-Cost Vehicles</h6>
              </div>
              <div class="card-body">
                <?php if (!empty($alertsData['high_cost_vehicles'])): ?>
                  <?php foreach ($alertsData['high_cost_vehicles'] as $vehicle): ?>
                    <div class="border-start border-warning border-4 ps-3 mb-3">
                      <div class="fw-bold"><?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($vehicle['regnumber']) ?></small>
                      <div class="mt-1">
                        <span class="badge bg-warning text-dark">
                          Investment: £<?= number_format($vehicle['total_investment'], 0) ?>
                        </span>
                      </div>
                      <a href="view-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-outline-warning mt-2">Review</a>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center text-muted py-3">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p>No high-cost vehicles</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Overdue Tasks -->
          <div class="col-xl-4">
            <div class="card border-info">
              <div class="card-header bg-info text-white">
                <h6 class="mb-0">⏰ Overdue Tasks</h6>
              </div>
              <div class="card-body">
                <?php if (!empty($alertsData['overdue_tasks'])): ?>
                  <?php foreach ($alertsData['overdue_tasks'] as $task): ?>
                    <div class="border-start border-info border-4 ps-3 mb-3">
                      <div class="fw-bold"><?= htmlspecialchars($task['task_name']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($task['make']) ?> <?= htmlspecialchars($task['model']) ?></small>
                      <div class="mt-1">
                        <span class="badge bg-danger"><?= $task['days_pending'] ?> days overdue</span>
                      </div>
                      <a href="view-job-card.php?jc=<?= $task['job_card_number'] ?>" class="btn btn-sm btn-outline-info mt-2">View</a>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center text-muted py-3">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p>No overdue tasks</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php include("footer.php"); ?>
    </div>
  </main>

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
          datasets: [
            {
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
</body>
</html>