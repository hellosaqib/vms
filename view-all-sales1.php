<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "View All Sales";

// Check permissions
if (
  !hasPermission('manage_sales') &&
  !hasPermission('manage_own_sales')
) {
  $_SESSION['error'] = "You don't have permission to view sales.";
  header('Location: index.php');
  exit;
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Filters
$filters = [];
$params = [];

// Base query
$query = "
    SELECT 
        s.sale_id,
        s.invoice_no,
        s.sale_date,
        s.sale_price,
        s.payment_method,
        s.status as sale_status,
        s.balance_due,
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        v.year,
        c.customer_id,
        c.first_name,
        c.last_name,
        c.phone,
        u.full_name as salesperson_name,
        s.includes_part_exchange,
        s.part_exchange_allowance,
        s.px_vehicle_id
    FROM sales s
    LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN users u ON s.sales_person = u.id
    WHERE 1=1
";

// Apply filters
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
  $query .= " AND s.sale_date >= ?";
  $params[] = $_GET['date_from'];
  $filters['date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
  $query .= " AND s.sale_date <= ?";
  $params[] = $_GET['date_to'];
  $filters['date_to'] = $_GET['date_to'];
}

if (isset($_GET['salesperson']) && !empty($_GET['salesperson'])) {
  $query .= " AND s.sales_person = ?";
  $params[] = $_GET['salesperson'];
  $filters['salesperson'] = $_GET['salesperson'];
}

if (isset($_GET['customer']) && !empty($_GET['customer'])) {
  $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ?)";
  $customerSearch = "%" . $_GET['customer'] . "%";
  $params[] = $customerSearch;
  $params[] = $customerSearch;
  $params[] = $customerSearch;
  $filters['customer'] = $_GET['customer'];
}

if (isset($_GET['vehicle']) && !empty($_GET['vehicle'])) {
  $query .= " AND (v.make LIKE ? OR v.model LIKE ? OR v.regnumber LIKE ?)";
  $vehicleSearch = "%" . $_GET['vehicle'] . "%";
  $params[] = $vehicleSearch;
  $params[] = $vehicleSearch;
  $params[] = $vehicleSearch;
  $filters['vehicle'] = $_GET['vehicle'];
}

if (isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
  $query .= " AND s.payment_method = ?";
  $params[] = $_GET['payment_method'];
  $filters['payment_method'] = $_GET['payment_method'];
}

// Permission-based filtering
if (
  !hasPermission('manage_sale', $db, $_SESSION['user_id']) &&
  hasPermission('manage_own_sale', $db, $_SESSION['user_id'])
) {
  $query .= " AND s.sales_person = ?";
  $params[] = $_SESSION['user_id'];
}

// Order and limit
$query .= " ORDER BY s.sale_date DESC, s.sale_id DESC LIMIT $start, $perPage";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM sales s 
               LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
               LEFT JOIN customers c ON s.customer_id = c.customer_id
               WHERE 1=1";

$countParams = [];
foreach ($filters as $key => $value) {
  // Add filter conditions to count query (simplified for brevity)
  // In practice, you'd reconstruct the WHERE conditions
}

$totalStmt = $db->prepare($countQuery);
$totalStmt->execute($countParams);
$totalSales = $totalStmt->fetchColumn();
$totalPages = ceil($totalSales / $perPage);

// Get salespeople for filter dropdown
$salespeopleStmt = $db->prepare("SELECT id, full_name FROM users WHERE role IN ('admin', 'staff') ORDER BY full_name");
$salespeopleStmt->execute();
$salespeople = $salespeopleStmt->fetchAll(PDO::FETCH_ASSOC);




// Current month and year
$currentMonth = date('m');
$currentYear = date('Y');
$lastMonth = date('m', strtotime('-1 month'));
$lastMonthYear = date('Y', strtotime('-1 month'));

// 1. SALES METRICS
$metricsQuery = $db->prepare("
    SELECT 
        -- Total Sales (All Time)
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales) as total_sales,
        
        -- This Month Sales
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales 
         WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = ?) as month_sales,
        
        -- Last Month Sales
        (SELECT COALESCE(SUM(sale_price), 0) FROM sales 
         WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = ?) as last_month_sales,
        
        -- This Month Vehicles Sold
        (SELECT COUNT(*) FROM sales 
         WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = ?) as month_vehicles_sold,
        
        -- Total Vehicles Sold
        (SELECT COUNT(*) FROM sales) as total_vehicles_sold,
        
        -- Pending Payments
        (SELECT COALESCE(SUM(balance_due), 0) FROM sales WHERE balance_due > 0 AND status = 'pending') as pending_payments,
        
        -- Average Sale Price (This Month)
        (SELECT COALESCE(AVG(sale_price), 0) FROM sales 
         WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = ?) as avg_month_sale_price,
         
        -- Sales by Status
        (SELECT COUNT(*) FROM sales WHERE status = 'completed') as completed_sales,
        (SELECT COUNT(*) FROM sales WHERE status = 'pending') as pending_sales,
        (SELECT COUNT(*) FROM sales WHERE status = 'cancelled') as cancelled_sales
");

$metricsQuery->execute([
  $currentMonth,
  $currentYear,
  $lastMonth,
  $lastMonthYear,
  $currentMonth,
  $currentYear,
  $currentMonth,
  $currentYear
]);
$metrics = $metricsQuery->fetch(PDO::FETCH_ASSOC);

// Calculate growth percentages
$salesGrowth = 0;
if ($metrics['last_month_sales'] > 0) {
  $salesGrowth = (($metrics['month_sales'] - $metrics['last_month_sales']) / $metrics['last_month_sales']) * 100;
}

$vehiclesGrowth = 0;
$lastMonthVehicles = 0; // You'd need to query this separately
if ($lastMonthVehicles > 0) {
  $vehiclesGrowth = (($metrics['month_vehicles_sold'] - $lastMonthVehicles) / $lastMonthVehicles) * 100;
}


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
  <!--    Stylesheets-->
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
      linkRTL.setAttribute('disabled', true);
      userLinkRTL.setAttribute('disabled', true);
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
    <!-- Left Menu -->
    <?php include("left-menu.php"); ?>
    <!-- Page Content -->

    <div class="content">
      <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>


      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
              <h1 class="h2">All Sales</h1>
              <div class="btn-toolbar mb-2 mb-md-0">
                <!-- <a href="sale-vehicle.php" class="btn btn-success">
                  <i class="fas fa-plus"></i> New Sale
                </a> -->
              </div>
            </div>
            <!-- Key Metrics Row -->
                <div class="row">
                    <!-- Total Sales -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-primary metric-card h-100">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col mr-1">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            £<?= number_format($metrics['total_sales'], 2) ?>
                                        </div>
                                        <div class="mt-2 mb-0 text-muted text-xs">
                                            <small>This Month: £<?= number_format($metrics['month_sales'], 2) ?></small>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-pound-sign fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicles Sold -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-success metric-card h-100">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col mr-1">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Vehicles Sold
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($metrics['total_vehicles_sold']) ?>
                                        </div>
                                        <div class="mt-2 mb-0 text-muted text-xs">
                                            <small>This Month: <?= $metrics['month_vehicles_sold'] ?> vehicles</small>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-car fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Average Sale Price -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-info metric-card h-100">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col mr-1">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Avg Sale Price
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            £<?= number_format($metrics['avg_month_sale_price'], 2) ?>
                                        </div>
                                        <div class="mt-2 mb-0 text-muted text-xs">
                                            <small>This Month Average</small>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Payments -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-warning metric-card h-100">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col mr-1">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Payments
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            £<?= number_format($metrics['pending_payments'], 2) ?>
                                        </div>
                                        <div class="mt-2 mb-0 text-muted text-xs">
                                            <small><?= $metrics['pending_sales'] ?> Pending sales</small>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- Filters Card -->
            <div class="card filter-card mb-4 p-1">
              <div class="card-header p-3">
                <h5 class="mb-0">🔍 Filter Sales</h5>
              </div>
              <div class="card-body p-1">
                <form method="GET" class="row g-3">
                  <div class="col-md-3 col-xl-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $filters['date_from'] ?? '' ?>">
                  </div>
                  <div class="col-md-3 col-xl-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $filters['date_to'] ?? '' ?>">
                  </div>
                  <div class="col-md-3 col-xl-3">
                    <label class="form-label">Salesperson</label>
                    <select class="form-select" name="salesperson">
                      <option value="">All Salespeople</option>
                      <?php foreach ($salespeople as $sp): ?>
                        <option value="<?= $sp['id'] ?>" <?= isset($filters['salesperson']) && $filters['salesperson'] == $sp['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($sp['full_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3 col-xl-3">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" name="payment_method">
                      <option value="">All Methods</option>
                      <option value="cash" <?= isset($filters['payment_method']) && $filters['payment_method'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                      <option value="bank_transfer" <?= isset($filters['payment_method']) && $filters['payment_method'] == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                      <option value="finance" <?= isset($filters['payment_method']) && $filters['payment_method'] == 'finance' ? 'selected' : '' ?>>Finance</option>
                      <option value="check" <?= isset($filters['payment_method']) && $filters['payment_method'] == 'check' ? 'selected' : '' ?>>Check</option>
                    </select>
                  </div>
                  <div class="col-md-3 col-xl-3">
                    <label class="form-label">Customer Search</label>
                    <input type="text" class="form-control" name="customer" placeholder="Name or phone..." value="<?= $filters['customer'] ?? '' ?>">
                  </div>
                  <div class="col-md-3 col-xl-3" >
                    <label class="form-label">Vehicle Search</label>
                    <input type="text" class="form-control" name="vehicle" placeholder="Make, model or reg..." value="<?= $filters['vehicle'] ?? '' ?>">
                  </div>
                  <div class="col-md-4 d-flex align-items-end col-xl-4">
                    <button type="submit" class="btn btn-primary w-50 me-2">
                      <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view-all-sales.php" class="btn btn-outline-secondary w-50">
                      <i class="fas fa-times"></i> Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>

            <div class="row flex-between-center mb-4 g-3">
              <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;vehicle&quot;,&quot;customer&quot;,&quot;price&quot;,&quot;p_method&quot;,&quot;saleperson&quot;,&quot;status&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                <div class="row">
                  <div class="col-8">
                    <h3>Sale Records (<?= count($sales) ?>)</h3>
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
                <div class="table-responsive">
                  <table class="table table-striped table-sm fs-9 mb-0">
                    <thead>
                      <tr>
                        <th class="sort border-top" data-sort="id">ID</th>
                        <th class="sort border-top border-translucent ps-3" data-sort="date">Date</th>
                        <th class="sort border-top " data-sort="invoice">Invoice#</th>
                        <th class="sort border-top " data-sort="vehicle">Vehicle</th>
                        <th class="sort border-top" data-sort="customer">Customer</th>
                        <th class="sort border-top" data-sort="price">Sale Price</th>
                        <th class="sort border-top" data-sort="p_method">Payment Method</th>
                        <th class="sort border-top" data-sort="saleperson">Sales Person</th>
                        <th class="sort border-top" data-sort="status">Status</th>
                        <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                      </tr>
                    </thead>
                    <tbody class="list">
                      <?php if (empty($sales)): ?>
                        <tr>
                          <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fas fa-receipt fa-2x mb-2"></i><br>
                            No sales found matching your criteria.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                          <tr>
                            <td class="align-middle ps-2 id"><?= $sale['sale_id'] ?></td>

                            <td class="align-middle date"><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></td>
                            <td class="align-middle invoice">
                              <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong>
                            </td>
                            <td class="align-middle vehicle">
                              <div class="fw-bold"><?= htmlspecialchars($sale['make'] . ' ' . $sale['model']) ?></div>
                              <small class="text-muted"><?= htmlspecialchars($sale['regnumber']) ?> • <?= $sale['year'] ?></small>
                              <?php if ($sale['includes_part_exchange']): ?>
                                <a href="view-vehicle.php?id=<?= $sale['px_vehicle_id'] ?>"><span class="badge badge-phoenix badge-phoenix-warning ">P/X</span></a>
                              <?php endif; ?>
                            </td>
                            <td class="align-middle customer">
                              <div><?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?></div>
                              <small class="text-muted"><?= htmlspecialchars($sale['phone']) ?></small>
                            </td>
                            <td class="align-middle price">
                              <div class="fw-bold">£<?= number_format($sale['sale_price'], 2) ?></div>
                              <?php if ($sale['balance_due'] > 0): ?>
                                <small class="text-danger">Balance: £<?= number_format($sale['balance_due'], 2) ?></small>
                              <?php else: ?>
                                <small class="text-success">Paid</small>
                              <?php endif; ?>
                            </td>
                            <td class="align-middle p_method">
                              <span class="badge badge-phoenix badge-phoenix-secondary">
                                <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?>
                              </span>
                            </td>
                            <td class="align-middle saleperson"><?= htmlspecialchars($sale['salesperson_name']) ?></td>
                            <td class="align-middle status">
                              <?php if ($sale['balance_due'] > 0): ?>
                                <span class="badge badge-phoenix badge-phoenix-warning">Balance Due</span>
                              <?php else: ?>
                                <span class="badge badge-phoenix badge-phoenix-success">Completed</span>
                              <?php endif; ?>
                            </td>
                            <td class="align-middle text-end">
                              <div class="btn-group btn-group-sm">
                                <a href="view-sale.php?id=<?= $sale['sale_id'] ?>"
                                  class="btn btn-outline-primary"
                                  title="View Sale">
                                  <i class="fas fa-eye"></i>
                                </a>
                                <a href="sale-invoice.php?inv=<?= $sale['invoice_no'] ?>"
                                  target="_blank"
                                  class="btn btn-outline-success"
                                  title="Print Invoice">
                                  <i class="fas fa-print"></i>
                                </a>
                                <?php if (hasPermission('manage_sale', $db, $_SESSION['user_id'])): ?>
                                  <a href="edit-sale.php?id=<?= $sale['sale_id'] ?>"
                                    class="btn btn-outline-warning"
                                    title="Edit Sale">
                                    <i class="fas fa-edit"></i>
                                  </a>
                                <?php endif; ?>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>

                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-between mt-3"><span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span>43</span>
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
      </div>


    </div>

    <!-- Footer -->
    <!-- <footer class="footer position-absolute">
      <div class="row g-0 justify-content-between align-items-center h-100">
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 mt-2 mt-sm-0 text-body">Thank you for creating with Saqib Abbas<span class="d-none d-sm-inline-block"></span><span class="d-none d-sm-inline-block mx-1">|</span><br class="d-sm-none" />2025 &copy;<a class="mx-1" href="https://swiftcodershub.com">Swift Coders</a></p>
        </div>
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 text-body-tertiary text-opacity-85">v1.20.1</p>
        </div>
      </div>
    </footer> -->
    </div>

    <!-- Chat Now -->

  </main><!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->

  <!-- <div class="offcanvas offcanvas-end settings-panel border-0" id="settings-offcanvas" tabindex="-1" aria-labelledby="settings-offcanvas">
      <div class="offcanvas-header align-items-start border-bottom flex-column border-translucent">
        <div class="pt-1 w-100 mb-6 d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-2 me-2 lh-sm"><span class="fas fa-palette me-2 fs-8"></span>Theme Customizer</h5>
            <p class="mb-0 fs-9">Explore different styles according to your preferences</p>
          </div><button class="btn p-1 fw-bolder" type="button" data-bs-dismiss="offcanvas" aria-label="Close"><span class="fas fa-times fs-8"> </span></button>
        </div><button class="btn btn-phoenix-secondary w-100" data-theme-control="reset"><span class="fas fa-arrows-rotate me-2 fs-10"></span>Reset to default</button>
      </div>
      <div class="offcanvas-body scrollbar px-card" id="themeController">
        <div class="setting-panel-item mt-0">
          <h5 class="setting-panel-item-title">Color Scheme</h5>
          <div class="row gx-2">
            <div class="col-4"><input class="btn-check" id="themeSwitcherLight" name="theme-color" type="radio" value="light" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherLight"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/default-light.png" alt=""/></span><span class="label-text">Light</span></label></div>
            <div class="col-4"><input class="btn-check" id="themeSwitcherDark" name="theme-color" type="radio" value="dark" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherDark"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/default-dark.png" alt=""/></span><span class="label-text"> Dark</span></label></div>
            <div class="col-4"><input class="btn-check" id="themeSwitcherAuto" name="theme-color" type="radio" value="auto" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherAuto"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/auto.png" alt=""/></span><span class="label-text"> Auto</span></label></div>
          </div>
        </div>
        <div class="border border-translucent rounded-3 p-4 setting-panel-item bg-body-emphasis">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="setting-panel-item-title mb-1">RTL </h5>
            <div class="form-check form-switch mb-0"><input class="form-check-input ms-auto" type="checkbox" data-theme-control="phoenixIsRTL" /></div>
          </div>
          <p class="mb-0 text-body-tertiary">Change text direction</p>
        </div>
        <div class="border border-translucent rounded-3 p-4 setting-panel-item bg-body-emphasis">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="setting-panel-item-title mb-1">Support Chat </h5>
            <div class="form-check form-switch mb-0"><input class="form-check-input ms-auto" type="checkbox" data-theme-control="phoenixSupportChat" /></div>
          </div>
          <p class="mb-0 text-body-tertiary">Toggle support chat</p>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Navigation Type</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarPositionVertical" name="navigation-type" type="radio" value="vertical" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionVertical"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/default-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/default-dark.png" alt=""/></span><span class="label-text">Vertical</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionHorizontal" name="navigation-type" type="radio" value="horizontal" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionHorizontal"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/top-default-dark.png" alt=""/></span><span class="label-text"> Horizontal</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionCombo" name="navigation-type" type="radio" value="combo" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionCombo"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/nav-combo-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/nav-combo-dark.png" alt=""/></span><span class="label-text"> Combo</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionTopDouble" name="navigation-type" type="radio" value="dual-nav" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionTopDouble"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/dual-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/dual-dark.png" alt=""/></span><span class="label-text"> Dual nav</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Vertical Navbar Appearance</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbar-style-default" type="radio" name="config.name" value="default" data-theme-control="phoenixNavbarVerticalStyle" /><label class="btn d-block w-100 btn-navbar-style fs-9" for="navbar-style-default"> <img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/default-light.png" alt="" /><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/default-dark.png" alt="" /><span class="label-text d-dark-none"> Default</span><span class="label-text d-light-none">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbar-style-dark" type="radio" name="config.name" value="darker" data-theme-control="phoenixNavbarVerticalStyle" /><label class="btn d-block w-100 btn-navbar-style fs-9" for="navbar-style-dark"> <img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/vertical-darker.png" alt="" /><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/vertical-lighter.png" alt="" /><span class="label-text d-dark-none"> Darker</span><span class="label-text d-light-none">Lighter</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Horizontal Navbar Shape</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarShapeDefault" name="navbar-shape" type="radio" value="default" data-theme-control="phoenixNavbarTopShape" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarShapeDefault"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-default-dark.png" alt=""/></span><span class="label-text">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarShapeSlim" name="navbar-shape" type="radio" value="slim" data-theme-control="phoenixNavbarTopShape" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarShapeSlim"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-slim.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-slim-dark.png" alt=""/></span><span class="label-text"> Slim</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Horizontal Navbar Appearance</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarTopDefault" name="navbar-top-style" type="radio" value="default" data-theme-control="phoenixNavbarTopStyle" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarTopDefault"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-style-darker.png" alt=""/></span><span class="label-text">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarTopDarker" name="navbar-top-style" type="radio" value="darker" data-theme-control="phoenixNavbarTopStyle" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarTopDarker"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/navbar-top-style-light.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-style-lighter.png" alt=""/></span><span class="label-text d-dark-none">Darker</span><span class="label-text d-light-none">Lighter</span></label></div>
          </div>
        </div><a class="bun btn-primary d-grid mb-3 text-white mt-5 btn btn-primary" href="https://themes.getbootstrap.com/product/phoenix-admin-dashboard-webapp-template/" target="_blank">Purchase template</a>
      </div>
    </div>
    <a class="card setting-toggle" href="#settings-offcanvas" data-bs-toggle="offcanvas">
      <div class="card-body d-flex align-items-center px-2 py-1">
        <div class="position-relative rounded-start" style="height:34px;width:28px">
          <div class="settings-popover"><span class="ripple"><span class="fa-spin position-absolute all-0 d-flex flex-center"><span class="icon-spin position-absolute all-0 d-flex flex-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="#ffffff" xmlns="http://www.w3.org/2000/svg"><path d="M19.7369 12.3941L19.1989 12.1065C18.4459 11.7041 18.0843 10.8487 18.0843 9.99495C18.0843 9.14118 18.4459 8.28582 19.1989 7.88336L19.7369 7.59581C19.9474 7.47484 20.0316 7.23291 19.9474 7.03131C19.4842 5.57973 18.6843 4.28943 17.6738 3.20075C17.5053 3.03946 17.2527 2.99914 17.0422 3.12011L16.393 3.46714C15.6883 3.84379 14.8377 3.74529 14.1476 3.3427C14.0988 3.31422 14.0496 3.28621 14.0002 3.25868C13.2568 2.84453 12.7055 2.10629 12.7055 1.25525V0.70081C12.7055 0.499202 12.5371 0.297594 12.2845 0.257272C10.7266 -0.105622 9.16879 -0.0653007 7.69516 0.257272C7.44254 0.297594 7.31623 0.499202 7.31623 0.70081V1.23474C7.31623 2.09575 6.74999 2.8362 5.99824 3.25599C5.95774 3.27861 5.91747 3.30159 5.87744 3.32493C5.15643 3.74527 4.26453 3.85902 3.53534 3.45302L2.93743 3.12011C2.72691 2.99914 2.47429 3.03946 2.30587 3.20075C1.29538 4.28943 0.495411 5.57973 0.0322686 7.03131C-0.051939 7.23291 0.0322686 7.47484 0.242788 7.59581L0.784376 7.8853C1.54166 8.29007 1.92694 9.13627 1.92694 9.99495C1.92694 10.8536 1.54166 11.6998 0.784375 12.1046L0.242788 12.3941C0.0322686 12.515 -0.051939 12.757 0.0322686 12.9586C0.495411 14.4102 1.29538 15.7005 2.30587 16.7891C2.47429 16.9504 2.72691 16.9907 2.93743 16.8698L3.58669 16.5227C4.29133 16.1461 5.14131 16.2457 5.8331 16.6455C5.88713 16.6767 5.94159 16.7074 5.99648 16.7375C6.75162 17.1511 7.31623 17.8941 7.31623 18.7552V19.2891C7.31623 19.4425 7.41373 19.5959 7.55309 19.696C7.64066 19.7589 7.74815 19.7843 7.85406 19.8046C9.35884 20.0925 10.8609 20.0456 12.2845 19.7729C12.5371 19.6923 12.7055 19.4907 12.7055 19.2891V18.7346C12.7055 17.8836 13.2568 17.1454 14.0002 16.7312C14.0496 16.7037 14.0988 16.6757 14.1476 16.6472C14.8377 16.2446 15.6883 16.1461 16.393 16.5227L17.0422 16.8698C17.2527 16.9907 17.5053 16.9504 17.6738 16.7891C18.7264 15.7005 19.4842 14.4102 19.9895 12.9586C20.0316 12.757 19.9474 12.515 19.7369 12.3941ZM10.0109 13.2005C8.1162 13.2005 6.64257 11.7893 6.64257 9.97478C6.64257 8.20063 8.1162 6.74905 10.0109 6.74905C11.8634 6.74905 13.3792 8.20063 13.3792 9.97478C13.3792 11.7893 11.8634 13.2005 10.0109 13.2005Z" fill="#2A7BE4"></path></svg></span></span></span></div>
        </div><small class="text-uppercase text-body-tertiary fw-bold py-2 pe-2 ps-1 rounded-end">customize</small>
      </div>
    </a> -->

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
  <script src="vendors/echarts/echarts.min.js"></script>
  <script src="assets/js/ecommerce-dashboard.js"></script>
</body>


</html>