<?php
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
$pageTitle = 'Dashboard - Car Trade System';

include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();
//echo($_SESSION['id'].'<br>');
//echo($_SESSION['role_id'].'<br>');
//echo($_SESSION['username']); die;
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
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales) as total_sales,
        
        -- Total Purchase Cost of Sold Vehicles
        (SELECT COALESCE(SUM(v.purchase_price), 0) 
         FROM sales s 
         JOIN vehicles v ON s.vehicle_id = v.vehicle_id) as total_purchase_cost,
        
        -- This Month Sales
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE) 
         AND YEAR(sale_date) = YEAR(CURRENT_DATE)) as month_sales,
        
        -- This Month Purchase Cost
        (SELECT COALESCE(SUM(v.purchase_price), 0)
         FROM sales s 
         JOIN vehicles v ON s.vehicle_id = v.vehicle_id
         WHERE MONTH(s.sale_date) = MONTH(CURRENT_DATE) 
         AND YEAR(s.sale_date) = YEAR(CURRENT_DATE)) as month_purchase_cost,
         
        -- Last Month Sales
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(sale_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_sales,
         
        -- Last Month Purchase Cost  
        (SELECT COALESCE(SUM(v.purchase_price), 0)
         FROM sales s 
         JOIN vehicles v ON s.vehicle_id = v.vehicle_id
         WHERE MONTH(s.sale_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(s.sale_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_purchase_cost,
         
        -- Vehicle Counts
        (SELECT COUNT(*) FROM sales) as total_vehicles_sold,
        (SELECT COUNT(*) FROM sales 
         WHERE MONTH(sale_date) = MONTH(CURRENT_DATE) 
         AND YEAR(sale_date) = YEAR(CURRENT_DATE)) as month_vehicles_sold
");
$salesQuery->execute();
$salesData = $salesQuery->fetch(PDO::FETCH_ASSOC);

// Expenses Statistics from job_card_task_progress
$expensesQuery = $db->prepare("
    SELECT 
        -- Total Expenses from job_card_task_progress
        (SELECT COALESCE(SUM(cost), 0) FROM job_card_task_progress jctp 
         JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
         WHERE jctp.cost IS NOT NULL AND jctp.cost > 0) as total_expenses,
        
        -- This Month Expenses
        (SELECT COALESCE(SUM(cost), 0) FROM job_card_task_progress jctp 
         JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
         WHERE jctp.cost IS NOT NULL AND jctp.cost > 0
         AND MONTH(jctp.created_at) = MONTH(CURRENT_DATE) 
         AND YEAR(jctp.created_at) = YEAR(CURRENT_DATE)) as month_expenses,
         
        -- Last Month Expenses
        (SELECT COALESCE(SUM(cost), 0) FROM job_card_task_progress jctp 
         JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
         WHERE jctp.cost IS NOT NULL AND jctp.cost > 0
         AND MONTH(jctp.created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
         AND YEAR(jctp.created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_expenses,
         
        -- Total Repair Costs (tasks with task_type = 'repair')
        (SELECT COALESCE(SUM(jctp.cost), 0) FROM job_card_task_progress jctp 
         JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
         JOIN standard_tasks st ON jctp.task_id = st.task_id
         WHERE jctp.cost IS NOT NULL AND jctp.cost > 0
         AND st.task_type = 'repair') as total_repair_costs,
         
        -- Other Expenses (tasks with task_type != 'repair')
        (SELECT COALESCE(SUM(jctp.cost), 0) FROM job_card_task_progress jctp 
         JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
         JOIN standard_tasks st ON jctp.task_id = st.task_id
         WHERE jctp.cost IS NOT NULL AND jctp.cost > 0
         AND st.task_type != 'repair') as total_other_expenses
");
$expensesQuery->execute();
$expensesData = $expensesQuery->fetch(PDO::FETCH_ASSOC);

// CORRECT Profit Calculations
// Profit calculations remain the same but now use unified expense data
$total_profit = $salesData['total_sales'] - $salesData['total_purchase_cost'] - $expensesData['total_expenses'];
$month_profit = $salesData['month_sales'] - $salesData['month_purchase_cost'] - $expensesData['month_expenses'];
// Calculate Gross Profit (before expenses)
$total_gross_profit = $salesData['total_sales'] - $salesData['total_purchase_cost'];
$month_gross_profit = $salesData['month_sales'] - $salesData['month_purchase_cost'];

// Calculate percentage changes
$sales_change = 0;
if ($salesData['last_month_sales'] > 0) {
  $sales_change = (($salesData['month_sales'] - $salesData['last_month_sales']) / $salesData['last_month_sales']) * 100;
}

$profit_change = 0;
$last_month_profit = $salesData['last_month_sales'] - $salesData['last_month_purchase_cost'] - $expensesData['last_month_expenses'];
if ($last_month_profit > 0) {
  $profit_change = (($month_profit - $last_month_profit) / abs($last_month_profit)) * 100;
}

// Profit Margins
$total_profit_margin = $salesData['total_sales'] > 0 ? ($total_profit / $salesData['total_sales']) * 100 : 0;
$month_profit_margin = $salesData['month_sales'] > 0 ? ($month_profit / $salesData['month_sales']) * 100 : 0;
$gross_margin = $salesData['total_sales'] > 0 ? ($total_gross_profit / $salesData['total_sales']) * 100 : 0;


$expenses_change = 0;
if ($expensesData['last_month_expenses'] > 0) {
  $expenses_change = (($expensesData['month_expenses'] - $expensesData['last_month_expenses']) / $expensesData['last_month_expenses']) * 100;
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
         WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'completed') as completed_tasks,
         (SELECT COUNT(*) FROM job_card_task_progress jctp 
         WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'cancelled') as cancelled_tasks
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
// Charts - Start

// Monthly Sales Data for Current Year
$salesChartQuery = $db->prepare("
    SELECT 
        MONTH(sale_date) as month_number,
        MONTHNAME(sale_date) as month_name,
        SUM(sale_price) as monthly_sales,
        COUNT(*) as vehicles_sold
    FROM sales 
    WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
    GROUP BY MONTH(sale_date), MONTHNAME(sale_date)
    ORDER BY month_number
");
$salesChartQuery->execute();
$monthlySalesData = $salesChartQuery->fetchAll(PDO::FETCH_ASSOC);

// Create arrays for chart data
$months = [];
$salesAmounts = [];
$vehicleCounts = [];

// Fill all months (even with zero sales)
$allMonths = [
  1 => 'Jan',
  2 => 'Feb',
  3 => 'Mar',
  4 => 'Apr',
  5 => 'May',
  6 => 'Jun',
  7 => 'Jul',
  8 => 'Aug',
  9 => 'Sep',
  10 => 'Oct',
  11 => 'Nov',
  12 => 'Dec'
];

foreach ($allMonths as $monthNum => $monthName) {
  $months[] = $monthName;

  // Find data for this month
  $monthData = array_filter($monthlySalesData, function ($item) use ($monthNum) {
    return $item['month_number'] == $monthNum;
  });

  if (!empty($monthData)) {
    $data = array_values($monthData)[0];
    $salesAmounts[] = floatval($data['monthly_sales']);
    $vehicleCounts[] = intval($data['vehicles_sold']);
  } else {
    $salesAmounts[] = 0;
    $vehicleCounts[] = 0;
  }
}

// Calculate growth percentage
$totalSales = array_sum($salesAmounts);
$previousYearSales = 0; // You can query previous year if needed
$growthPercentage = $previousYearSales > 0 ? (($totalSales - $previousYearSales) / $previousYearSales) * 100 : 0;

// Calculate month-over-month growth
$currentMonthSales = end($salesAmounts);
$previousMonthSales = prev($salesAmounts);
$monthGrowth = $previousMonthSales > 0 ? (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100 : 0;

// Monthly Profit Data for Current Year

// Monthly Profit Data for Current Year - CORRECTED VERSION
// Monthly Profit Data from job_card_task_progress
$profitChartQuery = $db->prepare("
    SELECT 
        months.month_number,
        months.month_name,
        COALESCE(SUM(s.sale_price), 0) as monthly_sales,
        COALESCE(SUM(v.purchase_price), 0) as monthly_purchase_cost,
        COALESCE(SUM(jctp.cost), 0) as total_expenses,
        COALESCE(SUM(CASE WHEN st.task_type = 'repair' THEN jctp.cost ELSE 0 END), 0) as repair_expenses,
        COALESCE(SUM(CASE WHEN st.task_type != 'repair' THEN jctp.cost ELSE 0 END), 0) as other_expenses
    FROM (
        SELECT 1 as month_number, 'Jan' as month_name
        UNION SELECT 2, 'Feb' UNION SELECT 3, 'Mar' UNION SELECT 4, 'Apr'
        UNION SELECT 5, 'May' UNION SELECT 6, 'Jun' UNION SELECT 7, 'Jul'
        UNION SELECT 8, 'Aug' UNION SELECT 9, 'Sep' UNION SELECT 10, 'Oct'
        UNION SELECT 11, 'Nov' UNION SELECT 12, 'Dec'
    ) months
    LEFT JOIN sales s ON MONTH(s.sale_date) = months.month_number 
                      AND YEAR(s.sale_date) = YEAR(CURRENT_DATE)
    LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
    LEFT JOIN job_card_task_progress jctp ON MONTH(jctp.created_at) = months.month_number 
                                          AND YEAR(jctp.created_at) = YEAR(CURRENT_DATE)
                                          AND jctp.cost IS NOT NULL AND jctp.cost > 0
    LEFT JOIN standard_tasks st ON jctp.task_id = st.task_id
    GROUP BY months.month_number, months.month_name
    ORDER BY months.month_number
");
$profitChartQuery->execute();
$monthlyProfitData = $profitChartQuery->fetchAll(PDO::FETCH_ASSOC);

// Create arrays for chart data
$profitMonths = [];
$profitAmounts = [];
$salesAmounts = [];
$purchaseCosts = [];
$expenseAmounts = [];
$grossProfitAmounts = [];

// Fill all months (even with zero data)
$allMonths = [
  1 => 'Jan',
  2 => 'Feb',
  3 => 'Mar',
  4 => 'Apr',
  5 => 'May',
  6 => 'Jun',
  7 => 'Jul',
  8 => 'Aug',
  9 => 'Sep',
  10 => 'Oct',
  11 => 'Nov',
  12 => 'Dec'
];

foreach ($allMonths as $monthNum => $monthName) {
  $profitMonths[] = $monthName;

  // Find data for this month
  $monthData = array_filter($monthlyProfitData, function ($item) use ($monthNum) {
    return $item['month_number'] == $monthNum;
  });

  if (!empty($monthData)) {
    $data = array_values($monthData)[0];
    $sales = floatval($data['monthly_sales']);
    $purchaseCost = floatval($data['monthly_purchase_cost']);
    $repairs = floatval($data['repair_expenses']);
    $otherExpenses = floatval($data['other_expenses']);
    $totalExpenses = $repairs + $otherExpenses;

    // CORRECT Profit calculations
    $grossProfit = $sales - $purchaseCost;  // Sales - Purchase Cost
    $netProfit = $grossProfit - $totalExpenses;  // Gross Profit - Expenses

    $salesAmounts[] = $sales;
    $purchaseCosts[] = $purchaseCost;
    $expenseAmounts[] = $totalExpenses;
    $grossProfitAmounts[] = $grossProfit;
    $profitAmounts[] = $netProfit;  // This is the TRUE net profit
  } else {
    $salesAmounts[] = 0;
    $purchaseCosts[] = 0;
    $expenseAmounts[] = 0;
    $grossProfitAmounts[] = 0;
    $profitAmounts[] = 0;
  }
}

// Calculate totals
$totalProfit = array_sum($profitAmounts);
$totalSales = array_sum($salesAmounts);
$totalPurchaseCost = array_sum($purchaseCosts);
$totalExpenses = array_sum($expenseAmounts);
$totalGrossProfit = array_sum($grossProfitAmounts);

// Current vs previous month growth
$currentMonthProfit = end($profitAmounts);
$previousMonthProfit = prev($profitAmounts);
$profitGrowth = $previousMonthProfit != 0 ? (($currentMonthProfit - $previousMonthProfit) / abs($previousMonthProfit)) * 100 : 0;

// Profit margins
$netProfitMargin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;
$grossProfitMargin = $totalSales > 0 ? ($totalGrossProfit / $totalSales) * 100 : 0;


// Get vehicle status counts
$statusQuery = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM vehicles 
    GROUP BY status
");
$statusQuery->execute();
$statusData = $statusQuery->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentages
$totalVehicles = array_sum(array_column($statusData, 'count'));
$statusPercentages = [];
foreach ($statusData as $status) {
  $statusPercentages[$status['status']] = $totalVehicles > 0 ? ($status['count'] / $totalVehicles) * 100 : 0;
}

// Status configuration
$statusConfig = [
  'available' => ['label' => 'Available', 'color' => '#00d97e', 'icon' => '✅'],
  'in_preparation' => ['label' => 'In Preparation', 'color' => '#f6c343', 'icon' => '🔧'],
  'purchased' => ['label' => 'Purchased', 'color' => '#2c7be5', 'icon' => '🛒'],
  'part_exchange' => ['label' => 'Part Exchange', 'color' => '#fd7e14', 'icon' => '🔄'],
  'sold' => ['label' => 'Sold', 'color' => '#6e84a3', 'icon' => '💰']
];

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
          <div class="col-lg-8 col-xxl-9">
            <!--  -->
            <div class="row mb-2">
              <div class="col-12 ">
                <h3>Quick Actions</h3>
                <a class="btn btn-phoenix-secondary mb-2" href="view-all-vehicles.php"> <i class="fas fa-car-side fs-4"></i><br> All Vehicles</a>
                <a class="btn btn-phoenix-success mb-2" href="add-vehicle.php"> <i class="fas fa-car fs-4"></i><br> New Vehicle</a>
                <a class="btn btn-phoenix-danger mb-2" href="add-new-job-card.php"> <i class="far fa-list-alt fs-4"></i><br> New Job Card</a>
                <!-- <a class="btn btn-phoenix-primary mb-2" href="add-repair.php"> <i class="fas fa-tools fs-4"></i><br>New Repair</a> -->
                <!-- <a class="btn btn-phoenix-warning mb-2" href="add-expense.php"> <i class="fas fa-money-bill-wave-alt fs-4"></i><br>New Expense</a> -->
                <a class="btn btn-phoenix-info mb-2" href="add-all-job-cards.php"> <i class="fas fa-clipboard-list fs-4"></i><br>All Job Cards</a>
              </div>
            </div>
            <div class="row pb-3 mb-3 gy-3">
              <!-- <h4 class="mb-0 mt-3"><u>Total Inventory in System</u></h4><br> -->
              <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 col-xxl-7">
                <!-- Compact Vehicle Status Distribution -->
                <div class="card">
                  <div class="card-header p-3">
                    <h4 class="mb-0">🚗 Total Inventory</h4>
                  </div>
                  <div class="card-body py-3">
                    <!-- Mini Donut Chart -->
                    <div class="row align-items-center mb-3">
                      <div class="col-6 text-center position-relative p-0">
                        <canvas id="statusDonutChart" width="100" height="100"></canvas>
                        <div id="donutCenterText" class="position-absolute top-50 start-50 translate-middle text-center">
                          <div class="h5 mb-0 fw-bold text-dark"><?= $totalVehicles ?></div>
                          <small class="text-muted">Total Vehicles</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="space-y-2">
                          <?php foreach ($statusConfig as $status => $config):
                            if (isset($statusPercentages[$status])):
                              $percentage = $statusPercentages[$status];
                              $count = 0;
                              foreach ($statusData as $s) {
                                if ($s['status'] == $status) {
                                  $count = $s['count'];
                                  break;
                                }
                              }
                          ?>
                              <div class="d-flex align-items-center mb-2">
                                <div class="flex-shrink-0" style="width: 20px;">
                                  <?= $config['icon'] ?>
                                </div>
                                <div class="flex-grow-1 ms-2">
                                  <div class="d-flex justify-content-between small">
                                    <span class="fw-semi-bold"><?= $config['label'] ?></span>
                                    <span><?= $count ?> (<?= number_format($percentage, 0) ?>%)</span>
                                  </div>
                                  <div class="progress" style="height: 6px;">
                                    <div class="progress-bar"
                                      style="width: <?= $percentage ?>%; background-color: <?= $config['color'] ?>">
                                    </div>
                                  </div>
                                </div>
                              </div>
                          <?php endif;
                          endforeach; ?>
                        </div>
                      </div>
                    </div>

                    <!-- Status Progress Bars -->

                  </div>
                </div>

                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('statusDonutChart').getContext('2d');

                    // Prepare data for donut chart
                    const statusLabels = [];
                    const statusData = [];
                    const statusColors = [];
                    const statusIcons = [];

                    <?php foreach ($statusConfig as $status => $config): ?>
                      <?php if (isset($statusPercentages[$status]) && $statusPercentages[$status] > 0): ?>
                        statusLabels.push('<?= $config['label'] ?>');
                        statusData.push(<?= $statusPercentages[$status] ?>);
                        statusColors.push('<?= $config['color'] ?>');
                      <?php endif; ?>
                    <?php endforeach; ?>

                    const statusDonutChart = new Chart(ctx, {
                      type: 'doughnut',
                      data: {
                        labels: statusLabels,
                        datasets: [{
                          data: statusData,
                          backgroundColor: statusColors,
                          borderWidth: 2,
                          borderColor: '#fff'
                        }]
                      },
                      options: {
                        cutout: '60%',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                          legend: {
                            display: false
                          },
                          tooltip: {
                            callbacks: {
                              label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                              }
                            }
                          }
                        },
                        animation: {
                          animateScale: true,
                          animateRotate: true
                        }
                      }
                    });
                  });
                </script>

                <style>
                  .space-y-2>*+* {
                    margin-top: 0.5rem;
                  }

                  .progress {
                    background-color: #f8f9fa;
                  }
                </style>
              </div>
              <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-12 col-xxl-5">
                <!-- Additional Financial Metrics -->
                <div class="row mb-2">
                  <!-- This Month Sales -->
                  <div class="col-xl-3 col-md-6 col-6 col-xxl-6 mb-3 p-1">
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
                  <div class="col-xl-3 col-md-6 col-6 col-xxl-6 mb-3 p-1">
                    <div class="card border-danger">
                      <div class="card-body text-center py-2">
                        <div class="h6 mb-1 text-danger">This Month Expenses</div>
                        <div class="h4 mb-0 font-weight-bold">£<?= number_format($expensesData['month_expenses'] ?? 0, 2) ?></div>
                        <small class="text-muted">Repairs & Operational</small>
                      </div>
                    </div>
                  </div>

                  <!-- This Month Profit -->
                  <div class="col-xl-3 col-md-6 col-6 col-xxl-6 mb-3 p-1">
                    <div class="card border-info">
                      <div class="card-body text-center py-2">
                        <div class="h6 mb-1 text-info">This Month Profit</div>
                        <div class="h4 mb-0 font-weight-bold">£<?= number_format($month_profit, 2) ?></div>
                        <small class="text-muted">Monthly net profit</small>
                      </div>
                    </div>
                  </div>

                  <!-- Average Sale Price -->
                  <div class="col-xl-3 col-md-6 col-6 col-xxl-6 mb-3 p-1">
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
                  <div class="col-md-12 col-6 mb-3">
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
                  <div class="col-md-12 col-6 mb-3">
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
              </div>

            </div>
            <div class="row g-4">

              <!-- Financial Summary Section -->
              <?php if ($_SESSION['role_id'] == 1) { ?>
                <div class="row mb-2 pr-1">
                  <div class="col-12">
                    <h4 class="mb-3">💰 Financial Summary</h4>
                  </div>

                  <!-- Total Sales -->
                  <div class="col-xl-4 col-md-6 mb-4  p-1">
                    <div class="card border-success h-100">
                      <div class="card-body p-3">
                        <div class="row align-items-center ">
                          <div class="col mr-1">
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

                            </div>
                          </div>
                          <div class="col-auto">
                            <i class="fas fa-pound-sign fa-2x text-success"></i>
                          </div>
                        </div>
                        <div class="mt-0">
                          This Month: £<?= number_format($salesData['month_sales'] ?? 0, 2) ?><br>
                          <small class="text-muted">
                            <?= $salesData['total_vehicles_sold'] ?? 0 ?> vehicles sold
                            (Avg: £<?= number_format($avg_sale_price, 2) ?>)
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Total Expenses -->
                  <div class="col-xl-4 col-md-6 mb-4 p-1">
                    <div class="card border-danger h-100">
                      <div class="card-body border p-3">

                        <div class="row align-items-center ">
                          <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                              Total Expenses
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                              £<?= number_format($expensesData['total_other_expenses']+$expensesData['total_repair_costs']) ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                              <span class="<?= $expenses_change > 0 ? 'text-danger' : 'text-success' ?>">
                                <i class="fas fa-arrow-<?= $expenses_change > 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($expenses_change), 1) ?>%
                              </span>

                            </div>
                          </div>
                          <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                          </div>
                        </div>
                        <div class="mt-0">
                          This Month: £<?= number_format($expensesData['month_expenses'] ?? 0, 2) ?><br>
                          <small class="text-muted">
                            Repairs + Operational expenses
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Total Profit -->
                  <div class="col-xl-4 col-md-6 mb-4 p-1">
                    <div class="card border-info h-100">
                      <div class="card-body p-3">
                        <div class="row align-items-center">
                          <div class="col mr-0">
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
                              </span><br>

                            </div>
                          </div>
                          <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-info"></i>
                          </div>
                        </div>
                        <div class="mt-0">
                          This Month: £<?= number_format($month_profit, 2) ?><br>
                          <small class="text-muted">
                            Net profit after all expenses
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>




              <?php } ?>
              <div class="row mb-1 p-0">
                <!-- Monthly Chart -->
                <div class="col-xl-12 col-xxl-6">
                  <!-- Monthly Sales Trend Chart -->
                  <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center p-3">
                      <h4 class="mb-0">📈 Monthly Sales Trend - <?= date('Y') ?></h4>
                      <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle dropdown-caret-none" type="button"
                          data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <ul class="dropdown-menu">
                          <li><a class="dropdown-item" href="reports.php?report=sales">View Detailed Report</a></li>
                          <li><a class="dropdown-item" href="sales.php">View All Sales</a></li>
                        </ul>
                      </div>
                    </div>
                    <div class="card-body">
                      <!-- Summary Stats -->
                      <div class="row mb-4">
                        <div class="col-md-4">
                          <div class="border-end border-200">
                            <div class="text-700 fw-semi-bold">Total Sales</div>
                            <div class="h4 text-primary fw-bold">£<?= number_format($totalSales, 2) ?></div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="border-end border-200">
                            <div class="text-700 fw-semi-bold">Vehicles Sold</div>
                            <div class="h4 text-success fw-bold"><?= array_sum($vehicleCounts) ?></div>
                          </div>
                        </div>
                        <?php
                        $growthColor = 'text-muted'; // Default neutral
                        if ($monthGrowth > 5) {
                          $growthColor = 'text-success'; // Good growth
                        } elseif ($monthGrowth > 0) {
                          $growthColor = 'text-info'; // Slight growth
                        } elseif ($monthGrowth < -5) {
                          $growthColor = 'text-danger'; // Significant decline
                        } elseif ($monthGrowth <= 0) {
                          $growthColor = 'text-warning'; // Slight decline
                        }

                        ?>

                        <div class="col-md-4">
                          <div class="">
                            <div class="text-700 fw-semi-bold">Monthly Growth</div>
                            <div class="h4 fw-bold <?= $growthColor ?>">
                              <?= number_format($monthGrowth, 1) ?>%
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Chart Container -->
                      <div class="position-relative">
                        <canvas id="monthlySalesChart" height="300"></canvas>
                      </div>

                      <!-- Month-wise breakdown (optional) -->
                      <div class="mt-3">
                        <div class="row g-2 text-center">
                          <?php
                          $topMonths = array_slice($monthlySalesData, 0, 3);
                          foreach ($topMonths as $month):
                          ?>
                            <div class="col-4">
                              <div class="border rounded p-2">
                                <div class="fw-bold text-primary"><?= substr($month['month_name'], 0, 3) ?></div>
                                <div class="text-success">£<?= number_format($month['monthly_sales'], 2) ?></div>
                                <small class="text-muted"><?= $month['vehicles_sold'] ?> vehicles</small>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                  <script>
                    document.addEventListener('DOMContentLoaded', function() {
                      const ctx = document.getElementById('monthlySalesChart').getContext('2d');

                      const monthlySalesChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                          labels: <?= json_encode($months) ?>,
                          datasets: [{
                              label: 'Sales Revenue (£)',
                              data: <?= json_encode($salesAmounts) ?>,
                              borderColor: '#2c7be5',
                              backgroundColor: 'rgba(44, 123, 229, 0.1)',
                              tension: 0.4,
                              fill: true,
                              yAxisID: 'y'
                            },
                            {
                              label: 'Vehicles Sold',
                              data: <?= json_encode($vehicleCounts) ?>,
                              borderColor: '#00d97e',
                              backgroundColor: 'rgba(0, 217, 126, 0.1)',
                              tension: 0.4,
                              fill: false,
                              yAxisID: 'y1',
                              borderDash: [5, 5]
                            }
                          ]
                        },
                        options: {
                          responsive: true,
                          maintainAspectRatio: false,
                          interaction: {
                            mode: 'index',
                            intersect: false,
                          },
                          plugins: {
                            legend: {
                              position: 'top',
                            },
                            tooltip: {
                              callbacks: {
                                label: function(context) {
                                  let label = context.dataset.label || '';
                                  if (label) {
                                    label += ': ';
                                  }
                                  if (context.datasetIndex === 0) {
                                    label += '£' + context.parsed.y.toLocaleString();
                                  } else {
                                    label += context.parsed.y + ' vehicles';
                                  }
                                  return label;
                                }
                              }
                            }
                          },
                          scales: {
                            x: {
                              grid: {
                                color: 'rgba(0,0,0,0.05)'
                              }
                            },
                            y: {
                              type: 'linear',
                              display: true,
                              position: 'left',
                              title: {
                                display: true,
                                text: 'Sales Revenue (£)'
                              },
                              grid: {
                                color: 'rgba(0,0,0,0.05)'
                              },
                              ticks: {
                                callback: function(value) {
                                  return '£' + value.toLocaleString();
                                }
                              }
                            },
                            y1: {
                              type: 'linear',
                              display: true,
                              position: 'right',
                              title: {
                                display: true,
                                text: 'Vehicles Sold'
                              },
                              grid: {
                                drawOnChartArea: false,
                              },
                              ticks: {
                                // Force whole numbers only
                                stepSize: 1,
                                precision: 0,
                                callback: function(value) {
                                  if (value % 1 === 0) { // Only show whole numbers
                                    return value;
                                  }
                                  return ''; // Hide decimal values
                                }
                              },
                              // Set min to 0 and adjust max based on data
                              suggestedMin: 0,
                              suggestedMax: Math.max(...<?= json_encode($vehicleCounts) ?>) + 1
                            }
                          }
                        }
                      });
                    });
                  </script>
                </div>
                <div class="col-xl-12 col-xxl-6">
                  <!-- Monthly Profit Trend Chart -->
                  <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center p-3">
                      <h4 class="mb-0">💰 Monthly Profit Trend - <?= date('Y') ?></h4>
                      <div class="dropdown">
                        <button class="btn btn-link text-dark dropdown-toggle dropdown-caret-none" type="button"
                          data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <ul class="dropdown-menu">
                          <li><a class="dropdown-item" href="reports.php?report=profit">Profit & Loss Report</a></li>
                          <li><a class="dropdown-item" href="expenses.php">View Expenses</a></li>
                        </ul>
                      </div>
                    </div>
                    <div class="card-body">
                      <!-- Summary Stats -->
                      <div class="row mb-4">
                        <div class="col-md-4">
                          <div class="border-end border-200">
                            <div class="text-700 fw-semi-bold">Total Gross Profit</div>
                            <div class="h3 text-success fw-bold">£<?= number_format($totalGrossProfit, 2) ?></div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="border-end border-200">
                            <div class="text-700 fw-semi-bold">Profit Margin</div>
                            <div class="h3 text-info fw-bold"><?= number_format($grossProfitMargin, 1) ?>%</div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="">
                            <div class="text-700 fw-semi-bold">Monthly Growth</div>
                            <div class="h3 fw-bold <?= $profitGrowth > 0 ? 'text-success' : ($profitGrowth < 0 ? 'text-danger' : 'text-warning') ?>">
                              <?= number_format($profitGrowth, 1) ?>%
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Chart Container -->
                      <div class="position-relative">
                        <canvas id="monthlyProfitChart" height="300"></canvas>
                      </div>

                      <!-- Expense Breakdown (optional) -->
                      <div class="mt-3">
                        <div class="row g-2 text-center">
                          <div class="col-4">
                            <div class="border rounded p-2">
                              <div class="fw-bold text-success">Total Gross Profit</div>
                              <div>£<?= number_format($totalGrossProfit, 2) ?></div>
                            </div>
                          </div>
                          <div class="col-4">
                            <div class="border rounded p-2">
                              <div class="fw-bold text-primary">Total Sales</div>
                              <div>£<?= number_format($totalSales, 2) ?></div>
                            </div>
                          </div>
                          <div class="col-4">
                            <div class="border rounded p-2">
                              <div class="fw-bold text-danger">Total Expenses</div>
                              <div>£<?= number_format($totalExpenses, 2) ?></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <script>
                    document.addEventListener('DOMContentLoaded', function() {
                      const ctx = document.getElementById('monthlyProfitChart').getContext('2d');

                      const monthlyProfitChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                          labels: <?= json_encode($profitMonths) ?>,
                          datasets: [{
                              label: 'NET Profit (£)',
                              data: <?= json_encode($profitAmounts) ?>,
                              borderColor: '#00d97e',
                              backgroundColor: 'rgba(0, 217, 126, 0.1)',
                              tension: 0.4,
                              fill: true,
                              yAxisID: 'y'
                            },
                            {
                              label: 'Gross Profit (£)',
                              data: <?= json_encode($grossProfitAmounts) ?>,
                              borderColor: '#2c7be5',
                              backgroundColor: 'transparent',
                              tension: 0.4,
                              fill: false,
                              borderDash: [5, 5],
                              yAxisID: 'y'
                            },
                            {
                              label: 'Sales (£)',
                              data: <?= json_encode($salesAmounts) ?>,
                              borderColor: '#6e84a3',
                              backgroundColor: 'transparent',
                              tension: 0.4,
                              fill: false,
                              borderDash: [2, 2],
                              yAxisID: 'y'
                            },
                            {
                              label: 'Expenses (£)',
                              data: <?= json_encode($expenseAmounts) ?>,
                              borderColor: '#e63757',
                              backgroundColor: 'transparent',
                              tension: 0.4,
                              fill: false,
                              borderDash: [2, 2],
                              yAxisID: 'y'
                            }
                          ]
                        },
                        options: {
                          responsive: true,
                          maintainAspectRatio: false,
                          interaction: {
                            mode: 'index',
                            intersect: false,
                          },
                          plugins: {
                            legend: {
                              position: 'top',
                            },
                            tooltip: {
                              callbacks: {
                                label: function(context) {
                                  let label = context.dataset.label || '';
                                  if (label) {
                                    label += ': ';
                                  }
                                  label += '£' + context.parsed.y.toLocaleString();
                                  return label;
                                }
                              }
                            }
                          },
                          scales: {
                            x: {
                              grid: {
                                color: 'rgba(0,0,0,0.05)'
                              }
                            },
                            y: {
                              type: 'linear',
                              display: true,
                              position: 'left',
                              title: {
                                display: true,
                                text: 'Amount (£)'
                              },
                              grid: {
                                color: 'rgba(0,0,0,0.05)'
                              },
                              ticks: {
                                callback: function(value) {
                                  return '£' + value.toLocaleString();
                                }
                              }
                            }
                          }
                        }
                      });
                    });
                  </script>
                </div>

              </div>
            </div>

          </div>

          <!-- Right Side bar -->

          <div class="col-md-3 col-lg-4 col-xl-4 col-xxl-3 right-sidebar ">
            <div class="row gy-3">
              <!-- Right Sidebar - Pending Job Cards & Tasks -->
              <div class="col-md-12">
                <!-- Pending Job Cards -->
                <div class="card mb-4">
                  <div class="card-header bg-warning p-3">
                    <h5 class="mb-0 text-white">📋 Pending Job Cards</h5>
                  </div>
                  <div class="card-body p-0">
                    <!-- Priority Summary -->
                    <div class="p-3 border-bottom">
                      <div class="row text-center">
                        <div class="col-3">
                          <div class="text-danger">
                            <div class="h5 mb-0"><?= $priorityCounts['Urgent'] ?></div>
                            <small>Urgent</small>
                          </div>
                        </div>
                        <div class="col-3">
                          <div class="text-warning">
                            <div class="h5 mb-0"><?= $priorityCounts['High'] ?></div>
                            <small>High</small>
                          </div>
                        </div>
                        <div class="col-3">
                          <div class="text-info">
                            <div class="h5 mb-0"><?= $priorityCounts['Medium'] ?></div>
                            <small>Medium</small>
                          </div>
                        </div>
                        <div class="col-3">
                          <div class="text-success">
                            <div class="h5 mb-0"><?= $priorityCounts['Low'] ?></div>
                            <small>Low</small>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Job Cards List -->
                    <div style="max-height: 300px; overflow-y: auto;">
                      <?php if (empty($pendingJobCards)): ?>
                        <div class="p-3 text-center text-muted">
                          🎉 No pending job cards!
                        </div>
                      <?php else: ?>
                        <?php foreach ($pendingJobCards as $job):
                          $progress = $job['total_tasks'] > 0 ? (($job['completed_tasks'] + $job['cancelled_tasks']) / $job['total_tasks']) * 100 : 0;
                          $priorityClass = [
                            'Urgent' => 'border-danger',
                            'High' => 'border-warning',
                            'Medium' => 'border-info',
                            'Low' => 'border-success'
                          ][$job['priority']];
                        ?>
                          <div class="p-3 border-bottom <?= $priorityClass ?> border-start border-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                              <div>
                                <small class="text-muted"><?= $job['job_card_number'] ?></small>
                                <div class="fw-bold">
                                  <?= htmlspecialchars($job['make']) ?> <?= htmlspecialchars($job['model']) ?> - <small class="text-muted"><?= htmlspecialchars($job['regnumber']) ?></small>
                                </div>

                              </div>
                              <span class="badge bg-<?= strtolower($job['priority']) == 'urgent' ? 'danger' : strtolower($job['priority']) ?>">
                                <?= $job['priority'] ?>
                              </span>
                            </div>

                            <!-- Progress Bar -->
                            <?php if ($job['total_tasks'] > 0): ?>
                              <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                  <span>Progress</span>
                                  <span><?= ($job['completed_tasks'] + $job['cancelled_tasks']) ?>/<?= $job['total_tasks'] ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                  <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                                </div>
                              </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center">
                              <small class="text-muted">
                                <?php if ($job['assigned_to']): ?>
                                  👤 <?= htmlspecialchars($job['assigned_to']) ?>
                                <?php else: ?>
                                  <span class="text-danger">Unassigned</span>
                                <?php endif; ?>
                              </small>
                              <a href="view-job-card.php?jc=<?= $job['job_card_number'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <!-- <div class="p-3 bg-light">
                      <a href="view-all-job-cards.php" class="btn btn-warning btn-sm w-100 mb-2">
                        View All Job Cards
                      </a>
                      <a href="add-new-job-card.php" class="btn btn-success btn-sm w-100">
                        + Create New Job Card
                      </a>
                    </div> -->
                  </div>
                </div>

                <!-- Pending Tasks by Vehicle -->
                <div class="card">
                  <div class="card-header bg-info p-3">
                    <h5 class="mb-0 text-white">🔧 Pending Tasks by Vehicle</h5>
                  </div>
                  <div class="card-body p-0">
                    <div class="p-3 border-bottom">
                      <div class="text-center">
                        <div class="h4 mb-0 text-info"><?= $totalPendingTasks ?></div>
                        <small class="text-muted">Total Pending Tasks</small>
                      </div>
                    </div>

                    <div style="max-height: 300px; overflow-y: auto;">
                      <?php if (empty($pendingTasks)): ?>
                        <div class="p-3 text-center text-muted">
                          ✅ All tasks completed!
                        </div>
                      <?php else: ?>
                        <?php foreach ($pendingTasks as $task): ?>
                          <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                              <div>
                                <div class="fw-bold">
                                  <?= htmlspecialchars($task['make']) ?> <?= htmlspecialchars($task['model']) ?> -
                                  <small class="text-muted"><?= htmlspecialchars($task['regnumber']) ?></small>
                                </div>

                              </div>
                              <span class="badge bg-danger"><?= $task['pending_tasks'] ?> tasks</span>
                            </div>

                            <small class="text-muted">
                              <?= htmlspecialchars($task['task_names']) ?>
                            </small>

                            <!-- <div class="mt-2">
                              <a href="view-vehicle.php?id=<?= $task['vehicle_id'] ?>" class="btn btn-sm btn-outline-info">View Vehicle</a>
                            </div> -->
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <!-- <div class="p-3 bg-light">
                      <a href="view-tasks.php?status=in_preparation" class="btn btn-info btn-sm w-100">
                        Manage Vehicle Tasks
                      </a>
                    </div> -->
                  </div>
                </div>
              </div>

              <style>
                .border-danger {
                  border-left-color: #dc3545 !important;
                }

                .border-warning {
                  border-left-color: #ffc107 !important;
                }

                .border-info {
                  border-left-color: #0dcaf0 !important;
                }

                .border-success {
                  border-left-color: #198754 !important;
                }

                .bg-urgent {
                  background-color: #dc3545 !important;
                }

                .bg-high {
                  background-color: #ffc107 !important;
                  color: #000 !important;
                }

                .bg-medium {
                  background-color: #0dcaf0 !important;
                }

                .bg-low {
                  background-color: #198754 !important;
                }

                .card {
                  transition: transform 0.2s;
                }

                .card:hover {
                  transform: translateY(-2px);
                }
              </style>
            </div>
          </div>



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