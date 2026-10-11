<?php

$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
$pageTitle = 'Dashboard - Car Trade System';

include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();
//$stats = getDashboardSummaryStats();
//$summary = getSoldVehiclesFinancialSummary();

// Inventory Statistics Query
$inventoryQuery = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM vehicles 
    GROUP BY status
");
$inventoryQuery->execute();
$inventoryData = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easy access
$statusCounts = [];
foreach ($inventoryData as $row) {
    $statusCounts[$row['status']] = $row['count'];
}

// Calculate totals
$totalVehicles = array_sum($statusCounts);
$available = $statusCounts['available'] ?? 0;
$in_preparation = $statusCounts['in_preparation'] ?? 0;
$sold = $statusCounts['sold'] ?? 0;

// Sales Statistics
$salesQuery = $db->prepare("
    SELECT 
        -- Total Sales
        (SELECT SUM(sale_price) FROM sales) as total_sales,
        
        -- This Month Sales
        (SELECT SUM(sale_price) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE) 
         AND YEAR(sale_date) = YEAR(CURRENT_DATE)) as month_sales,
        
        -- Last Month Sales (for comparison)
        (SELECT SUM(sale_price) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(sale_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_sales,
         
        -- Total Vehicles Sold
        (SELECT COUNT(*) FROM sales) as total_vehicles_sold,
        
        -- This Month Vehicles Sold
        (SELECT COUNT(*) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE) 
         AND YEAR(sale_date) = YEAR(CURRENT_DATE)) as month_vehicles_sold
");
$salesQuery->execute();
$salesData = $salesQuery->fetch(PDO::FETCH_ASSOC);

// Expenses Statistics
$expensesQuery = $db->prepare("
    SELECT 
        -- Total Expenses (Repairs + Other)
        (SELECT COALESCE(SUM(cost), 0) FROM repairs) + 
        (SELECT COALESCE(SUM(amount), 0) FROM expenses) as total_expenses,
        
        -- This Month Expenses
        (SELECT COALESCE(SUM(cost), 0) FROM repairs 
         WHERE MONTH(date_started) = MONTH(CURRENT_DATE) 
         AND YEAR(date_started) = YEAR(CURRENT_DATE)) +
        (SELECT COALESCE(SUM(amount), 0) FROM expenses 
         WHERE MONTH(date) = MONTH(CURRENT_DATE) 
         AND YEAR(date) = YEAR(CURRENT_DATE)) as month_expenses,
         
        -- Last Month Expenses
        (SELECT COALESCE(SUM(cost), 0) FROM repairs 
         WHERE MONTH(date_started) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(date_started) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) +
        (SELECT COALESCE(SUM(amount), 0) FROM expenses 
         WHERE MONTH(date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_expenses
");
$expensesQuery->execute();
$expensesData = $expensesQuery->fetch(PDO::FETCH_ASSOC);

// Profit Calculations
$total_profit = $salesData['total_sales'] - $expensesData['total_expenses'];
$month_profit = $salesData['month_sales'] - $expensesData['month_expenses'];

// Calculate percentage changes
$sales_change = 0;
if ($salesData['last_month_sales'] > 0) {
    $sales_change = (($salesData['month_sales'] - $salesData['last_month_sales']) / $salesData['last_month_sales']) * 100;
}

$expenses_change = 0;
if ($expensesData['last_month_expenses'] > 0) {
    $expenses_change = (($expensesData['month_expenses'] - $expensesData['last_month_expenses']) / $expensesData['last_month_expenses']) * 100;
}

$profit_change = 0;
$last_month_profit = $salesData['last_month_sales'] - $expensesData['last_month_expenses'];
if ($last_month_profit > 0) {
    $profit_change = (($month_profit - $last_month_profit) / $last_month_profit) * 100;
}

// Average Sale Price
$avg_sale_price = $salesData['total_vehicles_sold'] > 0 ? $salesData['total_sales'] / $salesData['total_vehicles_sold'] : 0;
$avg_month_sale_price = $salesData['month_vehicles_sold'] > 0 ? $salesData['month_sales'] / $salesData['month_vehicles_sold'] : 0;

// Pending Job Cards with Task Progress
$jobCardsQuery = $db->prepare("
    SELECT 
        jc.job_card_id,
        jc.job_card_number,
        jc.priority,
        jc.status,
        jc.date_started,
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        u.full_name as assigned_to,
        -- Task counts
        (SELECT COUNT(*) FROM job_card_task_progress jctp 
         WHERE jctp.job_card_id = jc.job_card_id) as total_tasks,
        (SELECT COUNT(*) FROM job_card_task_progress jctp 
         WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'completed') as completed_tasks
    FROM job_cards jc
    LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
    LEFT JOIN users u ON jc.assigned_to = u.id
    WHERE jc.status IN ('Pending', 'In Progress')
    ORDER BY 
        CASE jc.priority 
            WHEN 'Urgent' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            WHEN 'Low' THEN 4
        END,
        jc.date_started ASC
    LIMIT 10
");
$jobCardsQuery->execute();
$pendingJobCards = $jobCardsQuery->fetchAll(PDO::FETCH_ASSOC);

// Pending Tasks by Vehicle
$pendingTasksQuery = $db->prepare("
    SELECT 
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        COUNT(jctp.task_id) as pending_tasks,
        GROUP_CONCAT(DISTINCT st.task_name SEPARATOR ', ') as task_names
    FROM vehicles v
    JOIN job_cards jc ON v.vehicle_id = jc.vehicle_id
    JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id
    JOIN standard_tasks st ON jctp.task_id = st.task_id
    WHERE jc.status IN ('Pending', 'In Progress')
    AND jctp.status IN ('pending', 'in_progress')
    GROUP BY v.vehicle_id
    ORDER BY pending_tasks DESC
    LIMIT 8
");
$pendingTasksQuery->execute();
$pendingTasks = $pendingTasksQuery->fetchAll(PDO::FETCH_ASSOC);

// Count by priority
$priorityCounts = [
    'Urgent' => 0,
    'High' => 0,
    'Medium' => 0,
    'Low' => 0
];

foreach ($pendingJobCards as $job) {
    if (isset($priorityCounts[$job['priority']])) {
        $priorityCounts[$job['priority']]++;
    }
}

$totalPendingTasks = array_sum(array_column($pendingTasks, 'pending_tasks'));

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
  <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
  <link rel="manifest" href="assets/img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
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
            <h2 class="mb-2 text-body-emphasis">Dashboard</h2>
            <h5 class="text-body-tertiary fw-semibold">Welcome <?= $user['full_name'] ?></h5>
          </div>
          <div class="col-md-3 col-auto text-end">
            <h4 class="text-danger"><?= date('M j, Y', time()) ?></h4>
          </div>
        </div>
        <div class="row">
          <!-- Content -->
          <div class="col-lg-9 col-xxl-9">
            <!-- <div class="row mb-3 gy-3">
              <h4 class="mb-0"><u>This Month's Inventory (<span class="text-danger"><?= $currentMonth ?></span>)</u></h4><br>
              <div class="col-12 col-xxl-12">
                <div class="row align-items-center g-3 g-xxl-0 h-100 align-content-between">
                  <div class="col-12 col-sm-6 col-md-3 col-lg-6 col-xl-3 col-xxl-3" >
                    <div class="d-flex align-items-center"><span class="fs-4 lh-1 uil uil-car-sideview text-primary-dark"></span>
                      <div class="ms-2">
                        <div class="d-flex align-items-end">
                          <h2 class="mb-0 me-2"><?= $monthlyStats['available_this_month'] + $monthlyStats['in_repair_this_month'] ?></h2>
                          <span class="fs-7 fw-semibold text-body">Vehicle</span>
                        </div>
                        <p class="text-body-secondary fs-9 mb-0">Vehicle Purchased</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-sm-6 col-md-3 col-lg-6 col-xl-3 col-xxl-3">
                    <div class="d-flex align-items-center"><span class="fs-4 lh-1 uil uil-car text-success-dark"></span>
                      <div class="ms-2">
                        <div class="d-flex align-items-end">
                          <h2 class="mb-0 me-2"><?= $monthlyStats['sold_this_month'] ?></h2><span class="fs-7 fw-semibold text-body">Sold</span>
                        </div>
                        <p class="text-body-secondary fs-9 mb-0">All time sold</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-sm-6 col-md-3 col-lg-6 col-xl-3 col-xxl-3">
                    <div class="d-flex align-items-center"><span class="fs-4 lh-1 uil uil-car-wash text-warning-dark"></span>
                      <div class="ms-2">
                        <div class="d-flex align-items-end">
                          <h2 class="mb-0 me-2"><?= $monthlyStats['in_repair_this_month'] ?></h2><span class="fs-7 fw-semibold text-body">In Repair</span>
                        </div>
                        <p class="text-body-secondary fs-9 mb-0">In Workshop</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-sm-6 col-md-3 col-lg-6 col-xl-3 col-xxl-3">
                    <div class="d-flex align-items-center"><span class="fs-4 lh-1 uil uil-car text-primary-dark"></span>
                      <div class="ms-2">
                        <div class="d-flex align-items-end">
                          <h2 class="mb-0 me-2"><?= $monthlyStats['available_this_month'] ?></h2><span class="fs-7 fw-semibold text-body">Available</span>
                        </div>
                        <p class="text-body-secondary fs-9 mb-0">Ready for sale</p>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div> -->
            <div class="row pb-3 mb-3 gy-3 ">
              <h3 class="mb-0 mt-3"><u>Total Inventory in System</u></h3><br>
              <div class="col-xl-2 col-md-4 col-6 mb-3">

                <div class="d-flex align-items-center"><span class="fa-stack" style="min-height: 46px;min-width: 46px;"><span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-success-light" data-fa-transform="down-4 rotate--10 left-4"></span><span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-success" data-fa-transform="up-4 right-3 grow-2"></span><span class="fa-stack-1x fa-solid fa-star text-success " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0"><?= $totalVehicles ?> Vehicles</h4>
                    <p class="text-body-secondary fs-9 mb-0">Total Purchased</p>
                  </div>
                </div>
              </div>
              <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="d-flex align-items-center">
                  <span class="fa-stack" style="min-height: 46px;min-width: 46px;">
                    <span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-warning-light" data-fa-transform="down-4 rotate--10 left-4"></span>
                    <span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-warning" data-fa-transform="up-4 right-3 grow-2"></span>
                    <span class="fa-stack-1x fa-solid fa-car-crash text-warning " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0"><?= $in_preparation ?> in Preparation</h4>
                    <p class="text-body-secondary fs-9 mb-0">Being worked on</p>
                  </div>
                </div>
              </div>
              <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="d-flex align-items-center"><span class="fa-stack" style="min-height: 46px;min-width: 46px;">
                    <span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-danger-light" data-fa-transform="down-4 rotate--10 left-4"></span>
                    <span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-danger" data-fa-transform="up-4 right-3 grow-2"></span>
                    <span class="fa-stack-1x fa-solid fa-car text-danger " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0"><?= $sold ?> Sold</h4>
                    <p class="text-body-secondary fs-9 mb-0">Completed Sales</p>
                  </div>
                </div>
              </div>
              <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="d-flex align-items-center">
                  <span class="fa-stack" style="min-height: 46px;min-width: 46px;">
                    <span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-primary-light" data-fa-transform="down-4 rotate--10 left-4"></span>
                    <span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-success" data-fa-transform="up-4 right-3 grow-2"></span>
                    <span class="fa-stack-1x fa-solid fa-car text-primary " data-fa-transform="shrink-2 up-8 right-6"></span>
                  </span>
                  <div class="ms-3">
                    <h4 class="mb-0"><?= $available ?> Available</h4>
                    <p class="text-body-secondary fs-9 mb-0">Available for Sale</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="row g-4">
              <div class="row mb-4">
    <!-- Purchased -->
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card border-success">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-secondary">Purchased</div>
                <div class="h4 mb-0 font-weight-bold"><?= $statusCounts['purchased'] ?? 0 ?></div>
                <small class="text-muted">New arrivals</small>
            </div>
        </div>
    </div>
    
    <!-- Part Exchange -->
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card border-warning">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-orange">Part Exchange</div>
                <div class="h4 mb-0 font-weight-bold"><?= $statusCounts['part_exchange'] ?? 0 ?></div>
                <small class="text-muted">Trade-ins</small>
            </div>
        </div>
    </div>
    
    <!-- Available Percentage -->
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1">Available %</div>
                <div class="h4 mb-0 font-weight-bold text-dark">
                    <?= $totalVehicles > 0 ? round(($available / $totalVehicles) * 100) : 0 ?>%
                </div>
                <small class="text-muted">Ready ratio</small>
            </div>
        </div>
    </div>
    
    <!-- Sold Percentage -->
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card border-success">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1">Sold %</div>
                <div class="h4 mb-0 font-weight-bold text-dark">
                    <?= $totalVehicles > 0 ? round(($sold / $totalVehicles) * 100) : 0 ?>%
                </div>
                <small class="text-muted">Sales ratio</small>
            </div>
        </div>
    </div>

            </div>

            <!-- Financial Summary Section -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="mb-3">💰 Financial Summary</h5>
    </div>
    
    <!-- Total Sales -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Sales
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            £<?= number_format($salesData['total_sales'] ?? 0, 2) ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-success">
                                <i class="fas fa-arrow-up"></i>
                                <?= number_format($sales_change, 1) ?>%
                            </span>
                            This Month: £<?= number_format($salesData['month_sales'] ?? 0, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-pound-sign fa-2x text-success"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <?= $salesData['total_vehicles_sold'] ?? 0 ?> vehicles sold 
                        (Avg: £<?= number_format($avg_sale_price, 2) ?>)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Expenses -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-danger h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Expenses
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            £<?= number_format($expensesData['total_expenses'] ?? 0, 2) ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="<?= $expenses_change > 0 ? 'text-danger' : 'text-success' ?>">
                                <i class="fas fa-arrow-<?= $expenses_change > 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($expenses_change), 1) ?>%
                            </span>
                            This Month: £<?= number_format($expensesData['month_expenses'] ?? 0, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        Repairs + Operational expenses
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Profit -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-info h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Profit
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            £<?= number_format($total_profit, 2) ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="<?= $profit_change >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="fas fa-arrow-<?= $profit_change >= 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($profit_change), 1) ?>%
                            </span>
                            This Month: £<?= number_format($month_profit, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-info"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        Net profit after all expenses
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Financial Metrics -->
<div class="row mb-4">
    <!-- This Month Sales -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-success">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-success">This Month Sales</div>
                <div class="h4 mb-0 font-weight-bold">£<?= number_format($salesData['month_sales'] ?? 0, 2) ?></div>
                <small class="text-muted">
                    <?= $salesData['month_vehicles_sold'] ?? 0 ?> vehicles
                </small>
            </div>
        </div>
    </div>
    
    <!-- This Month Expenses -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-danger">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-danger">This Month Expenses</div>
                <div class="h4 mb-0 font-weight-bold">£<?= number_format($expensesData['month_expenses'] ?? 0, 2) ?></div>
                <small class="text-muted">Repairs & Operational</small>
            </div>
        </div>
    </div>
    
    <!-- This Month Profit -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-info">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-info">This Month Profit</div>
                <div class="h4 mb-0 font-weight-bold">£<?= number_format($month_profit, 2) ?></div>
                <small class="text-muted">Monthly net profit</small>
            </div>
        </div>
    </div>
    
    <!-- Average Sale Price -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-warning">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1 text-warning">Avg Sale Price</div>
                <div class="h4 mb-0 font-weight-bold">£<?= number_format($avg_month_sale_price, 2) ?></div>
                <small class="text-muted">This month average</small>
            </div>
        </div>
    </div>
</div>

<!-- Profit Margin & Efficiency -->
<div class="row">
    <!-- Profit Margin -->
    <div class="col-md-6 mb-3">
        <div class="card border-dark">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1">Overall Profit Margin</div>
                <div class="h4 mb-0 font-weight-bold text-dark">
                    <?= $salesData['total_sales'] > 0 ? number_format(($total_profit / $salesData['total_sales']) * 100, 1) : 0 ?>%
                </div>
                <small class="text-muted">Net profit percentage</small>
            </div>
        </div>
    </div>
    
    <!-- Monthly Profit Margin -->
    <div class="col-md-6 mb-3">
        <div class="card border-dark">
            <div class="card-body text-center py-2">
                <div class="h6 mb-1">Monthly Profit Margin</div>
                <div class="h4 mb-0 font-weight-bold text-dark">
                    <?= $salesData['month_sales'] > 0 ? number_format(($month_profit / $salesData['month_sales']) * 100, 1) : 0 ?>%
                </div>
                <small class="text-muted">This month percentage</small>
            </div>
        </div>
    </div>
</div>
            <div class="row g-4">
          <h3>Financial Summary for <?= $summary['total_vehicles_sold'] ?> Sold Vehicles</h3>
          <div class="col-12 col-xxl-6">
            <div class="row align-items-center g-4">
              <div class="col-sm-6 col-md-4 col-xl-3 col-xxl-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex d-sm-block justify-content-between">
                      <div class="mb-sm-4">
                        <div class="d-flex align-items-center">
                          <div class="d-flex align-items-center icon-wrapper-sm shadow-primary-100" style="transform: rotate(-7.45deg);">
                            <span class="fa-solid fa-money-bill-alt text-primary fs-7 z-1 ms-2"></span>
                          </div>
                          <p class="text-body-tertiary fs-9 mb-0 ms-2 mt-3">Total Sale</p>
                        </div>
                        <p class="text-primary mt-2 fs-6 fw-bold mb-0 mb-sm-4"><?= $summary['total_sales_formatted'] ?> <br>
                          <span class="fs-8 text-body lh-lg">Total Revenue</span>
                        </p>
                      </div>

                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 col-xl-3 col-xxl-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex d-sm-block justify-content-between">
                      <div class="mb-sm-4">
                        <div class="d-flex align-items-center">
                          <div class="d-flex align-items-center icon-wrapper-sm shadow-info-100" style="transform: rotate(-7.45deg);"><span class="fa-solid fa-car-crash text-warning fs-7 z-1 ms-2"></span></div>
                          <p class="text-body-tertiary fs-9 mb-0 ms-2 mt-3">Total Repair Costs</p>
                        </div>
                        <p class="text-warning mt-2 fs-6 fw-bold mb-0 mb-sm-4"><?= $summary['total_repairs_formatted'] ?> <br><span class="fs-8 text-body lh-lg">Total Repairs</span></p>
                      </div>

                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-md-4 col-xl-3 col-xxl-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex d-sm-block justify-content-between">
                      <div class="mb-sm-4">
                        <div class="d-flex align-items-center">
                          <div class="d-flex align-items-center icon-wrapper-sm shadow-primary-100" style="transform: rotate(-7.45deg);"><span class="fas fa-file-invoice-dollar text-danger fs-7 z-1 ms-2"></span></div>
                          <p class="text-body-tertiary fs-9 mb-0 ms-2 mt-3">All Expenses</p>
                        </div>
                        <p class="text-danger mt-2 fs-6 fw-bold mb-0 mb-sm-4"><?= $summary['total_expenses_formatted'] ?><br> <span class="fs-8 text-body lh-lg">Total Expenses</span></p>
                      </div>

                    </div>
                  </div>
                </div>
              </div>
              <?php //$financialStats['profit_status'] = 'profit'; 
              ?>
              <div class="col-sm-6 col-md-4 col-xl-3 col-xxl-4">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex d-sm-block justify-content-between">
                      <div class=" mb-sm-4">
                        <div class="d-flex align-items-center">
                          <div class="d-flex align-items-center icon-wrapper-sm shadow-info-100" style="transform: rotate(-7.45deg);">
                            <?= ($summary['profit_status'] == 'profit') ? '<span class="fa-solid fa-arrow-up text-success fs-7 z-1 ms-2"></span>' : '<span class="fa-solid fa-arrow-down text-danger fs-7 z-1 ms-2"></span>' ?>

                          </div>
                          <p class="text-body-tertiary fs-9 mb-0 ms-2 mt-3"><?= ucfirst($summary['profit_status']) ?></p>
                        </div>
                        <?php if ($summary['profit_status'] == 'profit') {
                          $txtColor = "text-success";
                        } else {
                          $txtColor = "text-danger";
                        }  ?>
                        <p class="<?= $txtColor ?> mt-2 fs-6 fw-bold mb-0 mb-sm-4"><?= $summary['net_profit_formatted'] ?> <br>
                          <span class="fs-8 text-body lh-lg">Net <?= ucfirst($summary['profit_status']) ?></span>
                        </p>
                      </div>

                    </div>
                  </div>
                </div>
              </div>

            </div>
            <hr class="bg-body-secondary mb-6 mt-4" />

          </div>

        </div>
          </div>
          <!-- Right Side bar -->
          <div class="col-md-3 border">
            Side bar
          </div>

        </div>
        
        
        <div class="row">
          <div class="col-md-4 mb-2">
            <div class="card  p-5">
              <h4>Inventory Chart</h4>
              <canvas id="myChart"></canvas>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="card  p-5">
              <h4>Financial Chart</h4>
              <canvas id="financialChart"></canvas>
            </div>
          </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
        <script>
          document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('myChart');
            new Chart(ctx, {
              type: 'doughnut', // Changed from 'bar' to 'doughnut'
              data: {
                labels: ['Total Inventory', 'Avaiable', 'Sold', 'In Repair'],
                datasets: [{
                  label: '# of Votes',
                  data: [<?php echo ($inventoryStats['total_vehicles']); ?>, <?php echo ($inventoryStats['total_available']); ?>, <?php echo ($inventoryStats['total_sold_all_time']); ?>, <?php echo ($inventoryStats['total_in_repair']); ?>],
                  backgroundColor: [ // Added colors for each segment
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                  ],
                  borderWidth: 1
                }]
              },
              options: {
                responsive: true,
                plugins: {
                  legend: {
                    position: 'top', // Legend position
                  },
                  // title: {
                  //   display: true,
                  //   text: 'Financial Chart'
                  // }
                }
                // Removed the scales option as it's not needed for doughnut charts
              }
            });
          });
        </script>
        <script>
          document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('financialChart');
            new Chart(ctx, {
              type: 'pie', // Changed from 'bar' to 'doughnut'
              data: {
                labels: ['Total Inventory', 'Avaiable', 'Sold', 'In Repair'],
                datasets: [{
                  label: '# of Votes',
                  data: [<?php echo ($inventoryStats['total_vehicles']); ?>, <?php echo ($inventoryStats['total_available']); ?>, <?php echo ($inventoryStats['total_sold_all_time']); ?>, <?php echo ($inventoryStats['total_in_repair']); ?>],
                  backgroundColor: [ // Added colors for each segment
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                  ],
                  borderWidth: 1
                }]
              },
              options: {
                responsive: true,
                plugins: {
                  legend: {
                    position: 'top', // Legend position
                  },
                  // title: {
                  //   display: true,
                  //   text: 'Financial Chart'
                  // }
                }
                // Removed the scales option as it's not needed for doughnut charts
              }
            });
          });
        </script>
        <style>
          #myChart {
            width: 100%;
            max-width: 100%;
            height: 500px;
          }
        </style> <!-- <div class="echart-financial-chart" 
     data-echarts='<?php
                    //  echo json_encode([
                    //    'totalPurchase' => $summary['total_purchase'],
                    //    'repairCosts' => $summary['total_repairs'],
                    //    'expenses' => $summary['total_expenses'],
                    //    'netProfit' => $summary['net_profit']
                    //  ]); 
                    ?>'>
     
</div>
asds -->
        <!-- Pie Chart -->
        <!-- <div class="mx-n4 px-4 mx-lg-n6 px-lg-6 bg-body-emphasis pt-7 pb-3 border-y">
          <div class="row">
            <div class="col-12 col-xl-7 col-xxl-6">
              <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                  <h3 class="text-body-emphasis text-nowrap">Issues Discovered</h3>
                  <p class="text-body-tertiary mb-md-7">Newly found and yet to be solved</p>
                  <div class="d-flex align-items-center justify-content-between">
                    <p class="mb-0 fw-bold">Total Sold Vehicles </p>
                    <p class="mb-0 fs-9">Total count <span class="fw-bold"><?= $summary['total_vehicles_sold'] ?></span></p>
                  </div>
                  <hr class="bg-body-secondary mb-2 mt-2" />
                  <div class="d-flex align-items-center mb-1"><span class="d-inline-block bg-info-light bullet-item me-2"></span>
                    <p class="mb-0 fw-semibold text-body lh-sm flex-1">Pruchase Cost</p>
                    <h5 class="mb-0 text-body">78</h5>
                  </div>
                  <div class="d-flex align-items-center mb-1"><span class="d-inline-block bg-warning-light bullet-item me-2"></span>
                    <p class="mb-0 fw-semibold text-body lh-sm flex-1">Expenses</p>
                    <h5 class="mb-0 text-body">63</h5>
                  </div>
                  <div class="d-flex align-items-center mb-1"><span class="d-inline-block bg-danger-light bullet-item me-2"></span>
                    <p class="mb-0 fw-semibold text-body lh-sm flex-1">Repair Costs</p>
                    <h5 class="mb-0 text-body">56</h5>
                  </div>
                  <div class="d-flex align-items-center mb-1"><span class="d-inline-block bg-success-light bullet-item me-2"></span>
                    <p class="mb-0 fw-semibold text-body lh-sm flex-1">Total Sales</p>
                    <h5 class="mb-0 text-body">36</h5>
                  </div>
                  <div class="d-flex align-items-center"><span class="d-inline-block bg-primary bullet-item me-2"></span>
                    <p class="mb-0 fw-semibold text-body lh-sm flex-1">Net Profit</p>
                    <h5 class="mb-0 text-body">24</h5>
                  </div><button class="btn btn-outline-primary mt-5">See Details<span class="fas fa-angle-right ms-2 fs-10 text-center"></span></button>
                </div>
                <div class="col-12 col-md-6">
                  <div class="position-relative mb-sm-4 mb-xl-0">
                    <div class="echart-issue-chart" style="min-height:390px;width:100%"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-xl-5 col-xxl-6">
              <h3>Project: eleven Progress</h3>
              <p class="text-body-tertiary mb-0 mb-xl-3">Deadline &amp; progress</p>
              <div class="echart-zero-burnout-chart" style="min-height:320px;width:100%"></div>
            </div>
          </div>
        </div> -->
        <!-- <div class="mx-lg-n4 mt-3">
          <div class="row g-3">
            <div class="col-12 col-xl-6 col-xxl-7">
              <div class="card todo-list h-100">
                <div class="card-header border-bottom-0 pb-0">
                  <div class="row justify-content-between align-items-center mb-4">
                    <div class="col-auto">
                      <h3 class="text-body-emphasis">To do</h3>
                      <p class="mb-2 mb-md-0 mb-lg-2 text-body-tertiary">Task assigned to me</p>
                    </div>
                    <div class="col-auto w-100 w-md-auto">
                      <div class="row align-items-center g-0 justify-content-between">
                        <div class="col-12 col-sm-auto">
                          <div class="search-box w-100 mb-2 mb-sm-0" style="max-width:30rem;">
                            <form class="position-relative"><input class="form-control search-input search" type="search" placeholder="Search tasks" aria-label="Search" />
                              <span class="fas fa-search search-box-icon"></span>
                            </form>
                          </div>
                        </div>
                        <div class="col-auto d-flex">
                          <p class="mb-0 ms-sm-3 fs-9 text-body-tertiary fw-bold"><span class="fas fa-filter me-1 fw-extra-bold fs-10"></span>23 tasks</p><button class="btn btn-link p-0 ms-3 fs-9 text-primary fw-bold"><span class="fas fa-sort me-1 fw-extra-bold fs-10"></span>Sorting</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-body py-0 scrollbar to-do-list-body">
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-0" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Designing the dungeon</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-primary">DRAFT</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>2</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-1" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Hiring a motion graphic designer</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-warning">URGENT</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>2</a><a class="text-warning fw-bold fs-10 me-2" href="#!"><span class="fas fa-tasks me-1"></span>3</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-2" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Daily Meetings Purpose, participants</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-info">ON PROCESS</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>4</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Dec, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">05:00 AM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-3" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Finalizing the geometric shapes</label></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>3</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-4" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Daily meeting with team members</label></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center">
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">1 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-5" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Daily Standup Meetings</label></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-warning fw-bold fs-10 me-2" href="#!"><span class="fas fa-tasks me-1"></span>4</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">13 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">10:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-6" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Procrastinate for a month</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-info">ON PROCESS</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>3</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-7" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">warming up</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-secondary">CLOSE</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>3</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">12 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">12:00 PM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-8" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Make ready for release</label></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>2</a><a class="text-warning fw-bold fs-10 me-2" href="#!"><span class="fas fa-tasks me-1"></span>2</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">2o Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">1:00 AM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-9" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Modify the component</label></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>4</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">22 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">1:00 AM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="d-flex hover-actions-trigger py-3 border-translucent border-top border-bottom"><input class="form-check-input form-check-input-todolist flex-shrink-0 my-1 me-2 form-check-input-undefined" type="checkbox" id="checkbox-todo-10" data-event-propagation-prevent="data-event-propagation-prevent" />
                    <div class="row justify-content-between align-items-md-center btn-reveal-trigger border-translucent gx-0 flex-1 cursor-pointer" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="mb-1 mb-md-0 d-flex align-items-center lh-1"><label class="form-check-label mb-1 mb-md-0 mb-xl-1 mb-xxl-0 fs-8 me-2 line-clamp-1 text-body cursor-pointer">Delete overlapping tasks and articles</label><span class="badge badge-phoenix ms-auto fs-10 badge-phoenix-secondary">CLOSE</span></div>
                      </div>
                      <div class="col-12 col-md-auto col-xl-12 col-xxl-auto">
                        <div class="d-flex lh-1 align-items-center"><a class="text-body-tertiary fw-bold fs-10 me-2" href="#!"><span class="fas fa-paperclip me-1"></span>2</a>
                          <p class="text-body-tertiary fs-10 mb-md-0 me-2 me-md-3 me-xl-2 me-xxl-3 mb-0">25 Nov, 2021</p>
                          <div class="hover-md-hide hover-xl-show hover-xxl-hide">
                            <p class="text-body-tertiary fs-10 fw-bold mb-md-0 mb-0 ps-md-3 ps-xl-0 ps-xxl-3 border-start-md border-xl-0 border-start-xxl">1:00 AM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="d-none d-md-block d-xl-none d-xxl-block end-0 position-absolute" style="top: 23%;" data-event-propagation-prevent="data-event-propagation-prevent">
                      <div class="hover-actions end-0" data-event-propagation-prevent="data-event-propagation-prevent"><button class="btn btn-phoenix-secondary btn-icon me-1 fs-10 text-body px-0 me-1" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-edit"></span></button><button class="btn btn-phoenix-secondary btn-icon fs-10 text-danger px-0" data-event-propagation-prevent="data-event-propagation-prevent"><span class="fas fa-trash"></span></button></div>
                    </div>
                  </div>
                  <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                      <div class="modal-content bg-body overflow-hidden">
                        <div class="modal-header justify-content-between px-6 py-5 pe-sm-5 px-md-6 dark__bg-gray-1100">
                          <h3 class="text-body-highlight fw-bolder mb-0">Designing the Dungeon Blueprint</h3><button class="btn btn-phoenix-secondary btn-icon btn-icon-xl flex-shrink-0" type="button" data-bs-dismiss="modal" aria-label="Close"><span class="fa-solid fa-xmark"></span></button>
                        </div>
                        <div class="modal-body bg-body-highlight px-6 py-0">
                          <div class="row gx-14">
                            <div class="col-12 col-lg-7 border-end-lg">
                              <div class="py-6">
                                <div class="mb-7">
                                  <div class="d-flex align-items-center mb-3">
                                    <h4 class="text-body me-3">Description</h4><a class="btn btn-link text-decoration-none p-0" href="#!"><span class="fa-solid fa-pen"></span></a>
                                  </div>
                                  <p class="text-body-highlight mb-0">The female circus horse-rider is a recurring subject in Chagall’s work. In 1926 the art dealer Ambroise Vollard invited Chagall to make a project based on the circus. They visited Paris’s historic Cirque d’Hiver Bouglione together; Vollard lent Chagall his private box seats. Chagall completed 19 gouaches Chagall’s work. In 1926 the art dealer Ambroise Vollard invited Chagall to make a project based on the circus.</p>
                                </div>
                                <div class="mb-7">
                                  <h4 class="mb-3">Subtasks</h4>
                                  <div class="d-flex flex-between-center hover-actions-trigger py-3 border-top">
                                    <div class="form-check mb-1 mb-md-0 d-flex align-items-center lh-1 min-h-auto"><input class="subtask-checkbox form-check-input form-check-line-through mt-0 me-3" type="checkbox" id="subtaskundefined1" /><label class="form-check-label mb-0 fs-8" for="subtaskundefined1">Study Dragons</label></div>
                                    <div class="hover-actions end-0"><button class="btn btn-sm me-1 fs-10 text-body-tertiary px-0 me-3"><span class="fa-solid fa-pencil"></span></button><button class="btn btn-sm text-body-tertiary px-0"><span class="fa-solid fa-xmark fs-8"></span></button></div>
                                  </div>
                                  <div class="d-flex flex-between-center hover-actions-trigger py-3 border-top">
                                    <div class="form-check mb-1 mb-md-0 d-flex align-items-center lh-1 min-h-auto"><input class="subtask-checkbox form-check-input form-check-line-through mt-0 me-3" type="checkbox" id="subtaskundefined2" /><label class="form-check-label mb-0 fs-8" for="subtaskundefined2">Procrastinate a bit</label></div>
                                    <div class="hover-actions end-0"><button class="btn btn-sm me-1 fs-10 text-body-tertiary px-0 me-3"><span class="fa-solid fa-pencil"></span></button><button class="btn btn-sm text-body-tertiary px-0"><span class="fa-solid fa-xmark fs-8"></span></button></div>
                                  </div>
                                  <div class="d-flex flex-between-center hover-actions-trigger py-3 border-top border-bottom mb-3">
                                    <div class="form-check mb-1 mb-md-0 d-flex align-items-center lh-1 min-h-auto"><input class="subtask-checkbox form-check-input form-check-line-through mt-0 me-3" type="checkbox" id="subtaskundefined3" /><label class="form-check-label mb-0 fs-8" for="subtaskundefined3">Staring at the notebook for 5 mins</label></div>
                                    <div class="hover-actions end-0"><button class="btn btn-sm me-1 fs-10 text-body-tertiary px-0 me-3"><span class="fa-solid fa-pencil"></span></button><button class="btn btn-sm text-body-tertiary px-0"><span class="fa-solid fa-xmark fs-8"></span></button></div>
                                  </div><a class="fw-bold fs-9" href="#!"><span class="fas fa-plus me-1"></span>Add subtask</a>
                                </div>
                                <div class="mb-3">
                                  <div>
                                    <h4 class="mb-3">Files</h4>
                                  </div>
                                  <div class="border-top px-0 pt-4 pb-3">
                                    <div class="me-n3">
                                      <div class="d-flex flex-between-center">
                                        <div class="d-flex mb-1"><span class="fa-solid fa-image me-2 text-body-tertiary fs-9"></span>
                                          <p class="text-body-highlight mb-0 lh-1">Silly_sight_1.png</p>
                                        </div>
                                        <div class="btn-reveal-trigger"><button class="btn btn-sm dropdown-toggle dropdown-caret-none transition-none btn-reveal" type="button" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h"></span></button>
                                          <div class="dropdown-menu dropdown-menu-end py-2"><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item text-danger" href="#!">Delete</a><a class="dropdown-item" href="#!">Download</a><a class="dropdown-item" href="#!">Report abuse</a></div>
                                        </div>
                                      </div>
                                      <div class="d-flex fs-9 text-body-tertiary mb-2 flex-wrap"><span>768 kb</span><span class="text-body-quaternary mx-1">| </span><a href="#!">Shantinan Mekalan </a><span class="text-body-quaternary mx-1">| </span><span class="text-nowrap">21st Dec, 12:56 PM</span></div><img class="rounded-2" src="../assets/img/generic/40.png" alt="" style="width:230px" />
                                    </div>
                                  </div>
                                  <div class="border-top px-0 pt-4 pb-3">
                                    <div class="me-n3">
                                      <div class="d-flex flex-between-center">
                                        <div>
                                          <div class="d-flex align-items-center mb-1"><span class="fa-solid fa-image me-2 fs-9 text-body-tertiary"></span>
                                            <p class="text-body-highlight mb-0 lh-1">All_images.zip</p>
                                          </div>
                                          <div class="d-flex fs-9 text-body-tertiary mb-0 flex-wrap"><span>12.8 mb</span><span class="text-body-quaternary mx-1">| </span><a href="#!">Yves Tanguy </a><span class="text-body-quaternary mx-1">| </span><span class="text-nowrap">19th Dec, 08:56 PM</span></div>
                                        </div>
                                        <div class="btn-reveal-trigger"><button class="btn btn-sm dropdown-toggle dropdown-caret-none transition-none btn-reveal" type="button" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h"></span></button>
                                          <div class="dropdown-menu dropdown-menu-end py-2"><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item text-danger" href="#!">Delete</a><a class="dropdown-item" href="#!">Download</a><a class="dropdown-item" href="#!">Report abuse</a></div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="border-top px-0 pt-4 pb-3 border-bottom">
                                    <div class="me-n3">
                                      <div class="d-flex flex-between-center">
                                        <div>
                                          <div class="d-flex align-items-center mb-1 flex-wrap"><span class="fa-solid fa-file-lines me-2 fs-9 text-body-tertiary"></span>
                                            <p class="text-body-highlight mb-0 lh-1">Project.txt</p>
                                          </div>
                                          <div class="d-flex fs-9 text-body-tertiary mb-0 flex-wrap"><span>123 kb</span><span class="text-body-quaternary mx-1">| </span><a href="#!">Shantinan Mekalan </a><span class="text-body-quaternary mx-1">| </span><span class="text-nowrap">12th Dec, 12:56 PM</span></div>
                                        </div>
                                        <div class="btn-reveal-trigger"><button class="btn btn-sm dropdown-toggle dropdown-caret-none transition-none btn-reveal" type="button" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h"></span></button>
                                          <div class="dropdown-menu dropdown-menu-end py-2"><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item text-danger" href="#!">Delete</a><a class="dropdown-item" href="#!">Download</a><a class="dropdown-item" href="#!">Report abuse</a></div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="mt-3"><label class="btn btn-link p-0" for="customFile"><span class="fas fa-plus me-1"></span>Add file(s)</label><input class="d-none" id="customFile" type="file" /></div>
                                </div>
                              </div>
                            </div>
                            <div class="col-12 col-lg-5">
                              <div class="py-6">
                                <h4 class="mb-4 text-body-emphasis">Others Information</h4>
                                <h5 class="text-body-highlight mb-2">Status</h5><select class="form-select mb-4" aria-label="Default select example">
                                  <option selected="">Select</option>
                                  <option value="1">One</option>
                                  <option value="2">Two</option>
                                  <option value="3">Three</option>
                                </select>
                                <h5 class="text-body-highlight mb-2">Due Date</h5>
                                <div class="flatpickr-input-container mb-4"><input class="form-control datetimepicker ps-6" type="text" placeholder="Set the due date" data-options='{"disableMobile":true}' /><span class="uil uil-calendar-alt flatpickr-icon text-body-tertiary"></span></div>
                                <h5 class="text-body-highlight mb-2">Reminder</h5>
                                <div class="flatpickr-input-container mb-4"><input class="form-control datetimepicker ps-6" type="text" placeholder="Reminder" data-options='{"enableTime":true,"noCalendar":true,"dateFormat":"H:i","disableMobile":true,"static":true}' /><span class="uil uil-bell-school flatpickr-icon text-body-tertiary"></span></div>
                                <h5 class="text-body-highlight mb-2">Tag</h5>
                                <div class="choices-select-container mb-6"><select class="form-select" data-choices="data-choices" multiple="multiple" data-options='{"removeItemButton":true,"placeholder":true}'>
                                    <option value="">Select organizer...</option>
                                    <option>Massachusetts Institute of Technology</option>
                                    <option>University of Chicago</option>
                                    <option>GSAS Open Labs At Harvard</option>
                                    <option>California Institute of Technology</option>
                                  </select><span class="uil uil-tag-alt choices-icon text-body-tertiary" style="top: 26%;"></span></div>
                                <div class="text-end mb-9"><button class="btn btn-phoenix-danger">Delete Task</button></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer border-0"><a class="fw-bold fs-9 mt-4" href="#!"><span class="fas fa-plus me-1"></span>Add new task</a></div>
              </div>
            </div>
            <div class="col-12 col-xl-6 col-xxl-5">
              <div class="card h-100">
                <div class="card-body">
                  <div class="card-title mb-1">
                    <h3 class="text-body-emphasis">Activity</h3>
                  </div>
                  <p class="text-body-tertiary mb-4">Recent activity across all projects</p>
                  <div class="timeline-vertical timeline-with-details">
                    <div class="timeline-item position-relative">
                      <div class="row g-md-3">
                        <div class="col-12 col-md-auto d-flex">
                          <div class="timeline-item-date order-1 order-md-0 me-md-4">
                            <p class="fs-10 fw-semibold text-body-tertiary text-opacity-85 text-end">01 DEC, 2023<br class="d-none d-md-block" /> 10:30 AM</p>
                          </div>
                          <div class="timeline-item-bar position-md-relative me-3 me-md-0">
                            <div class="icon-item icon-item-sm rounded-7 shadow-none bg-primary-subtle"><span class="fa-solid fa-chess text-primary-dark fs-10"></span></div><span class="timeline-bar border-end border-dashed"></span>
                          </div>
                        </div>
                        <div class="col">
                          <div class="timeline-item-content ps-6 ps-md-3">
                            <h5 class="fs-9 lh-sm">Phoenix Template: Unleashing Creative Possibilities</h5>
                            <p class="fs-9">by <a class="fw-semibold" href="#!">Shantinon Mekalan</a></p>
                            <p class="fs-9 text-body-secondary mb-5">Discover limitless creativity with the Phoenix template! Our latest update offers an array of innovative features and design options.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="timeline-item position-relative">
                      <div class="row g-md-3">
                        <div class="col-12 col-md-auto d-flex">
                          <div class="timeline-item-date order-1 order-md-0 me-md-4">
                            <p class="fs-10 fw-semibold text-body-tertiary text-opacity-85 text-end">05 DEC, 2023<br class="d-none d-md-block" /> 12:30 AM</p>
                          </div>
                          <div class="timeline-item-bar position-md-relative me-3 me-md-0">
                            <div class="icon-item icon-item-sm rounded-7 shadow-none bg-primary-subtle"><span class="fa-solid fa-dove text-primary-dark fs-10"></span></div><span class="timeline-bar border-end border-dashed"></span>
                          </div>
                        </div>
                        <div class="col">
                          <div class="timeline-item-content ps-6 ps-md-3">
                            <h5 class="fs-9 lh-sm">Empower Your Digital Presence: The Phoenix Template Unveiled</h5>
                            <p class="fs-9">by <a class="fw-semibold" href="#!">Bookworm22</a></p>
                            <p class="fs-9 text-body-secondary mb-5">Unveiling the Phoenix template, a game-changer for your digital presence. With its powerful features and sleek design,</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="timeline-item position-relative">
                      <div class="row g-md-3">
                        <div class="col-12 col-md-auto d-flex">
                          <div class="timeline-item-date order-1 order-md-0 me-md-4">
                            <p class="fs-10 fw-semibold text-body-tertiary text-opacity-85 text-end">15 DEC, 2023<br class="d-none d-md-block" /> 2:30 AM</p>
                          </div>
                          <div class="timeline-item-bar position-md-relative me-3 me-md-0">
                            <div class="icon-item icon-item-sm rounded-7 shadow-none bg-primary-subtle"><span class="fa-solid fa-dungeon text-primary-dark fs-10"></span></div>
                          </div>
                        </div>
                        <div class="col">
                          <div class="timeline-item-content ps-6 ps-md-3">
                            <h5 class="fs-9 lh-sm">Phoenix Template: Simplified Design, Maximum Impact</h5>
                            <p class="fs-9">by <a class="fw-semibold" href="#!">Sharuka Nijibum</a></p>
                            <p class="fs-9 text-body-secondary mb-0">Introducing the Phoenix template, where simplified design meets maximum impact. Elevate your digital presence with its sleek and intuitive features.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div> -->

      </div>

      <!-- Footer -->
      <?php include("footer.php"); ?>
    </div>

    <!-- Chat Now -->
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