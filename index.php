<?php

require_once __DIR__ . '/includes/config/database.php';

// Authentication
require_once __DIR__ . '/includes/auth/auth.php';

// Permissions
require_once __DIR__ . '/includes/auth/permissions.php';

// Existing VMS functions
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/dashboard_functions.php';
require_once __DIR__ . '/includes/errors.php';

$pageTitle = 'Dashboard - Vehicle Management System';
// Get current authenticated user
$user = getCurrentUser();

// If no authenticated user, send to login
if (!$user) {
    header('Location: login.php');
    exit;
}
requirePermission("dashboard.view");
// Current user's role
echo $roleName = $user['role_name'] ?? '';

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
  <!--    PWA Settings-->
  <!-- ===============================================-->
  <!-- PWA Meta Tags -->
  <meta name="application-name" content="BritDrive">
  <meta name="description" content="Vehicle Management System">
  <meta name="mobile-web-app-capable" content="yes">

  <!-- iOS Settings -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="BritDrive">
  <meta name="format-detection" content="telephone=no">
  <!-- Microsoft Settings -->
  <meta name="msapplication-TileColor" content="#0d6efd">
  <meta name="msapplication-tap-highlight" content="no">
  <meta name="msapplication-TileImage" content="uploads/images/britdrive.png">

  <!-- Theme Color (ONLY ONE - remove the duplicate below) -->
  <meta name="theme-color" content="#ffffff">

  <link rel="manifest" href="<?php echo $URL; ?>/manifest.json" crossorigin="use-credentials">


  <!-- Apple Touch Icons -->
  <link rel="apple-touch-icon" href="<?php echo $URL; ?>/assets/icons/icon-180x180.png">
  <link rel="apple-touch-icon" sizes="16x16" href="<?php echo $URL; ?>/assets/icons/icon-16x16.png">
  <link rel="apple-touch-icon" sizes="32x32" href="<?php echo $URL; ?>/assets/icons/icon-32x32.png">
  <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $URL; ?>/assets/icons/icon-72x72.png">
  <link rel="apple-touch-icon" sizes="96x96" href="<?php echo $URL; ?>/assets/icons/icon-96x96.png">
  <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $URL; ?>/assets/icons/icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $URL; ?>/assets/icons/icon-152x152.png">
  <link rel="apple-touch-icon" sizes="167x167" href="<?php echo $URL; ?>/assets/icons/icon-167x167.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $URL; ?>/assets/icons/icon-180x180.png">
  <link rel="apple-touch-icon" sizes="192x192" href="<?php echo $URL; ?>/assets/icons/icon-192x192.png">
  <link rel="apple-touch-icon" sizes="384x384" href="<?php echo $URL; ?>/assets/icons/icon-381x384.png">
  <link rel="apple-touch-icon" sizes="512x512" href="<?php echo $URL; ?>/assets/icons/icon-512x512.png">

  <!-- ===============================================-->
  <!--    Favicons-->
  <!-- ===============================================-->
  <!-- <link rel="icon" href="<?php echo $URL; ?>/assets/icons/favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?php echo $URL; ?>/assets/icons/favicon.ico" type="image/x-icon">
  <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $URL; ?>/assets/icons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $URL; ?>/assets/icons/favicon-16x16.png"> -->

  <!-- ===============================================-->
  <!--    Service Worker Registration-->
  <!-- ===============================================-->
  <!-- <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        // Get base path dynamically
        const basePath = '<?php echo $URL; ?>';

        navigator.serviceWorker.register(basePath + '/service-worker.js', {
            scope: basePath + '/'
          })
          .then(function(registration) {

            // Check for updates
            registration.addEventListener('updatefound', function() {});
          })
          .catch(function(error) {});
      });
    } else {}

    // Detect if app is running as PWA
    window.addEventListener('load', function() {
      // Check if running in standalone mode
      if (window.matchMedia('(display-mode: standalone)').matches) {
        document.documentElement.setAttribute('data-pwa', 'true');
      }

      // Listen for install prompt
      let deferredPrompt;
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // You can show an install button here
        // showInstallPromotion();
      });
    });
  </script> -->

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

  <script src="vendors/simplebar/simplebar.min.js"></script>
  <script src="assets/js/config.js"></script>

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
  <link href="custom.css" rel="stylesheet">


</head>

<body>
  <!-- ===============================================-->
  <!--    Main Content-->
  <!-- ===============================================-->
  <main class="main" id="top">
    <?php include("left-menu.php");    ?>
<?php //require __DIR__ . '/layout/left-menu.php'; ?>

    <!-- Main Content -->
    <div class="content">
      <?php displayMessage(); ?>
      <div class="row">
        <!-- Content -->
        <div class="col-xl-9">
          <div class="pb-5">
            <!-- Header -->
            
            <h5 class="mb-3">Quick Actions</h5>

            <br>
            <div class="mb-5">
              <div class="d-flex mb-2 " id="scrollspyStats">
                <!-- <span class="fa-stack me-1 ms-n1"><i class="fas fa-circle fa-stack-2x text-primary"></i><i class="fa-inverse fa-stack-1x text-primary-subtle fas fa-percentage"></i></span> -->
                <div class="col">
                  <h3 class="mb-0 text-primary position-relative fw-bold">
                    <span class="bg-body pe-2"><span class="text-danger">Your </span> Performance</span><span class="border border-primary position-absolute top-50 translate-middle-y w-100 start-0 z-n1"></span>
                  </h3>
                  <p class="mb-0"><?= date('F Y'); ?> · Compared with <?= date('F Y', strtotime('-1 month')); ?></p>
                </div>
              </div>
              
            </div>
<!-- Total Commissions till date -->
            <div class="mb-5">
              <div class="d-flex mb-2 " id="scrollspyStats">
                <!-- <span class="fa-stack me-1 ms-n1"><i class="fas fa-circle fa-stack-2x text-primary"></i><i class="fa-inverse fa-stack-1x text-primary-subtle fas fa-percentage"></i></span> -->
                <div class="col">
                  <h3 class="mb-0 text-primary position-relative fw-bold">
                    <span class="bg-body pe-2"><span class="text-danger">Overall</span> Performance</span><span class="border border-primary position-absolute top-50 translate-middle-y w-100 start-0 z-n1"></span>
                  </h3>
                  <p class="mb-0">Here's your overall sales performance till date</p>
                </div>
              </div>
              <div class="px-3 mb-5">
                <div class="row justify-content-between">
                  <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card metric-card metric-positive h-100">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="text-muted mb-1">Total Sold Cars</h6>
                            <h3 class="mb-0"><?= number_format($totalCars) ?></h3>
                            
                          </div>
                          <div class="bg-success rounded p-2">
                            <i class="fas fa-car text-white fa-lg"></i>
                          </div>
                        </div>
                        
                      </div>
                    </div>
                  </div>

                  <!-- Total Sales -->
                  <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card metric-card metric-info h-100">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="text-muted mb-1">Total Sales</h6>
                            <h3 class="mb-0">£<?= number_format($totalSales, 2) ?></h3>
                            
                          </div>
                          <div class="bg-info rounded p-2">
                            <i class="fas fa-pound-sign text-white fa-lg"></i>
                          </div>
                        </div>

                      </div>
                    </div>
                  </div>

                  <!-- Commission Generated -->
                  <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card metric-card metric-warning h-100">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="text-muted mb-1">Commission Generated</h6>
                            <h3 class="mb-0">£<?= number_format($totalCommission, 2) ?></h3>
                            

                          </div>
                          <div class="bg-warning rounded p-2">
                            <i class="fas fa-money-bill-alt text-white fa-lg"></i>
                          </div>
                        </div>
                        
                      </div>
                    </div>
                  </div>

                  <!-- Average Sales -->
                  <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card metric-card metric-positive h-100">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="text-muted mb-1">Average Sales</h6>
                            <h3 class="mb-0">£<?= number_format($averageSale, 2) ?></h3>
                            

                          </div>
                          <div class="bg-success rounded p-2">
                            <i class="fas fa-pound-sign text-white fa-lg"></i>
                          </div>
                        </div>
                        
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Monthly Performance Overview in chart -->
              <div class="row g-4">
                <!-- Monthly Performance Overview (Bar Chart) -->
                <div class="col-xl-8">
                  <div class="card h-100">
                    <div class="card-header">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">💰 Monthly Performance Overview - <?= date('Y') ?></h5>
                        <div class="d-flex align-items-center gap-2">
                          <!-- Chart Type Toggle -->
                          <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-type="bar" title="Bar Chart">
                              <i class="fas fa-chart-bar"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-type="line" title="Line Chart">
                              <i class="fas fa-chart-line"></i>
                            </button>
                          </div>
                          <!-- <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadFinancialBtn" title="Export Chart">
                            <i class="fas fa-download"></i>
                          </button> -->
                          <!-- Show All Toggle -->

                        </div>

                      </div>
                      <!-- Toggle Controls -->
                      <div class="row g-2">
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleRevenue" data-index="0" checked>
                            <label class="form-check-label" for="toggleRevenue">
                              <span class="badge badge-phoenix badge-phoenix-primary">Sold Cars</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleCost" data-index="1" checked>
                            <label class="form-check-label" for="toggleCost">
                              <span class="badge badge-phoenix badge-phoenix-warning text-dark">Sales</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleProfit" data-index="2" checked>
                            <label class="form-check-label" for="toggleProfit">
                              <span class="badge badge-phoenix badge-phoenix-success">Commission</span>
                            </label>
                          </div>
                        </div>
                        <!-- <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleRefunds" data-index="3">
                            <label class="form-check-label" for="toggleRefunds">
                              <span class="badge badge-phoenix badge-phoenix-danger">Refunds</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="showAllToggle" checked>
                            <label class="form-check-label" for="showAllToggle">Show All</label>
                          </div>
                        </div> -->
                      </div>
                    </div>
                    <div class="card-body p-0">
                      <div style="position: relative; height: 350px; width: 100%;">
                        <canvas id="monthlyFinancialChart"></canvas>
                      </div>
                    </div>

                  </div>
                </div>
                <!-- Inventory Health -->
                <!-- Inventory Health -->
                <div class="col-xl-4">
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-0">🚗 Inventory Health</h5>
                      <!-- <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadInventoryBtn" title="Download Report">
                        <i class="fas fa-download"></i>
                      </button> -->
                    </div>
                    <div class="card-body">

                      <canvas id="inventoryHealthChart" height="350"></canvas>
                    </div>

                  </div>
                </div>
              </div>                              
            






            
            <br>
            <!-- =================================================================== -->
            <!-- EXECUTIVE SUMMARY ROW -->
            <!-- =================================================================== -->
            <?php if ($roleName == "Administrator"): ?>
              <div class="row g-3 mb-4">
                <div class="col-12">
                  <h4 class="border-bottom pb-2">📊 Current Month Summary</h4>
                </div>

                <!-- Total Revenue YTD -->
                <div class="col-xl-3 col-md-6">
                  <div class="card metric-card metric-positive h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <h6 class="text-muted mb-1">Total Revenue <span class="text-danger">This Month</span></h6>
                          <h3 class="mb-0">£<?= number_format($currentMonthSummary['revenue']['revenue_month'], 2) ?></h3>
                          <small class="text-muted">
                            <?= $currentMonthSummary['revenue']['sales_count_month'] ?? 0 ?> vehicles sold
                          </small>
                        </div>
                        <div class="bg-success rounded p-2">
                          <i class="fas fa-pound-sign text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-success">This Month Performance</span>
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
                          <h6 class="text-muted mb-1">Net Profit <span class="text-danger">This Month</span></h6>
                          <h3 class="mb-0">£<?= number_format($currentMonthSummary['profit']['net_profit_month'], 2) ?></h3>
                          <small class="text-muted">
                            <?= number_format($currentMonthSummary['profit']['profit_margin_month'], 1) . "%" ?> margin
                          </small>
                        </div>
                        <div class="bg-info rounded p-2">
                          <i class="fas fa-chart-line text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-info">
                          <?= $currentMonthSummary['profit']['profitable_sales'] ?? 0 ?> profitable
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
                          <h3 class="mb-0"><?= number_format($currentMonthSummary['turnover']['turnover_rate'], 2) ?>x</h3>
                          <small class="text-muted">
                            <?= $currentMonthSummary['turnover']['current_inventory'] ?? 0 ?> in stock
                          </small>
                        </div>
                        <div class="bg-warning rounded p-2">
                          <i class="fas fa-sync-alt text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-warning">
                          £<?= number_format($currentMonthSummary['turnover']['avg_inventory_value'] ?? 0, 0) ?> avg value
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
                          <h3 class="mb-0"><?= number_format($currentMonthSummary['roi']['avg_roi_percentage'] ?? 0, 1) . "%" ?></h3>
                          <small class="text-muted">
                            Max: <?= number_format($currentMonthSummary['roi']['max_roi'], 1) ?>%
                          </small>
                          <small class="text-muted">Min: <?= number_format($currentMonthSummary['roi']['min_roi'], 1) ?>%</small>

                        </div>
                        <div class="bg-success rounded p-2">
                          <i class="fas fa-percentage text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-success">Strong Returns</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
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
                          <h3 class="mb-0">£<?= number_format($summary['revenue']['revenue_ytd'], 2) ?></h3>
                          <small class="text-muted">
                            <?= $summary['revenue']['sales_count_ytd'] ?? 0 ?> vehicles sold
                          </small>
                        </div>
                        <div class="bg-success rounded p-2">
                          <i class="fas fa-pound-sign text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-success">YTD Performance</span>
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
                          <h3 class="mb-0">£<?= number_format($summary['profit']['net_profit_ytd'], 2) ?></h3>
                          <small class="text-muted">
                            <?= number_format($summary['profit']['profit_margin_ytd'], 1) . "%" ?> margin
                          </small>
                        </div>
                        <div class="bg-info rounded p-2">
                          <i class="fas fa-chart-line text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-info">
                          <?= $summary['profit']['profitable_sales'] ?? 0 ?> profitable
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
                          <h3 class="mb-0"><?= number_format($summary['turnover']['turnover_rate'], 2) ?>x</h3>
                          <small class="text-muted">
                            <?= $summary['turnover']['current_inventory'] ?? 0 ?> in stock
                          </small>
                        </div>
                        <div class="bg-warning rounded p-2">
                          <i class="fas fa-sync-alt text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-warning">
                          £<?= number_format($summary['turnover']['avg_inventory_value'] ?? 0, 0) ?> avg value
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
                          <h3 class="mb-0"><?= number_format($summary['roi']['avg_roi_percentage'] ?? 0, 1) . "%" ?></h3>
                          <small class="text-muted">
                            Max: <?= number_format($summary['roi']['max_roi'], 1) ?>%
                          </small>
                          <small class="text-muted">Min: <?= number_format($summary['roi']['min_roi'], 1) ?>%</small>

                        </div>
                        <div class="bg-success rounded p-2">
                          <i class="fas fa-percentage text-white fa-lg"></i>
                        </div>
                      </div>
                      <div class="mt-2">
                        <span class="badge badge-phoenix badge-phoenix-success">Strong Returns</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <!-- =================================================================== -->
            <!-- SALES PERFORMANCE ROW -->
            <!-- =================================================================== -->
            <!-- SALES PERFORMANCE ROW -->
            <?php if ($roleName == "Administrator"): ?>
              <div class="row g-3 mb-4">
                <div class="col-12">
                  <h4 class="border-bottom pb-2">💰 Sales Performance</h4>
                </div>

                <!-- Net Conversion Rate -->
                <div class="col-xl-3 col-md-6">
                  <div class="card metric-card metric-info h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between">
                        <div>
                          <h6 class="text-muted mb-1">Net Conversion Rate</h6>
                          <h3 class="mb-0"><?= number_format($salesMetrics['conversion']['net_conversion_rate'] ?? 0, 1) ?>%</h3>
                          <small class="text-muted">
                            <?= $salesMetrics['conversion']['sold_ytd'] ?? 0 ?> sold /
                            <?= $salesMetrics['conversion']['refunded_ytd'] ?? 0 ?> refunded
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


                <!-- Vehicle Acquisition (Net) -->
                <div class="col-xl-3 col-md-6">
                  <div class="card metric-card metric-info h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between">
                        <div>
                          <h6 class="text-muted mb-1">Net Vehicle Acquisition</h6>
                          <h3 class="mb-0"><?= number_format($salesMetrics['acquisition']['vehicles_acquired_ytd'] ?? 0, 0) ?> YTD</h3>
                          <small class="text-muted">
                            <?= $salesMetrics['acquisition']['monthly_acquisition'] ?? 0 ?> this month,
                            <?= $salesMetrics['acquisition']['vehicles_returned_ytd'] ?? 0 ?> returned
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>


                <!-- Refund Summary -->
                <div class="col-xl-3 col-md-6">
                  <div class="card metric-card metric-warning h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between">
                        <div>
                          <h6 class="text-muted mb-1">Refund Impact</h6>
                          <h3 class="mb-0"><?= number_format($salesMetrics['conversion']['refund_rate'] ?? 0, 1) ?>%</h3>
                          <small class="text-muted">
                            £<?= number_format($salesMetrics['refunds']['total_refund_amount_ytd'] ?? 0, 0) ?> total,
                            <?= $salesMetrics['refunds']['pending_refunds'] ?? 0 ?> pending
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
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
            <?php if ($roleName == "Administrator"): ?>

              <div class="row g-4">
                <!-- Monthly Financial Overview (Bar Chart) -->
                <div class="col-xl-8">
                  <div class="card h-100">
                    <div class="card-header">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">💰 Monthly Financial Overview - <?= date('Y') ?></h5>
                        <div class="d-flex align-items-center gap-2">
                          <!-- Chart Type Toggle -->
                          <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-type="bar" title="Bar Chart">
                              <i class="fas fa-chart-bar"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-type="line" title="Line Chart">
                              <i class="fas fa-chart-line"></i>
                            </button>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadFinancialBtn" title="Export Chart">
                            <i class="fas fa-download"></i>
                          </button>
                          <!-- Show All Toggle -->

                        </div>

                      </div>
                      <!-- Toggle Controls -->
                      <div class="row g-2">
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleRevenue" data-index="0" checked>
                            <label class="form-check-label" for="toggleRevenue">
                              <span class="badge badge-phoenix badge-phoenix-primary">Revenue</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleCost" data-index="1" checked>
                            <label class="form-check-label" for="toggleCost">
                              <span class="badge badge-phoenix badge-phoenix-warning text-dark">Cost</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleProfit" data-index="2" checked>
                            <label class="form-check-label" for="toggleProfit">
                              <span class="badge badge-phoenix badge-phoenix-success">Profit</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input metric-toggle" type="checkbox" id="toggleRefunds" data-index="3">
                            <label class="form-check-label" for="toggleRefunds">
                              <span class="badge badge-phoenix badge-phoenix-danger">Refunds</span>
                            </label>
                          </div>
                        </div>
                        <div class="col-auto">
                          <div class="form-check form-switch form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="showAllToggle" checked>
                            <label class="form-check-label" for="showAllToggle">Show All</label>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="card-body p-0">
                      <div style="position: relative; height: 350px; width: 100%;">
                        <canvas id="monthlyFinancialChart"></canvas>
                      </div>
                    </div>

                  </div>
                </div>
                <!-- Inventory Health -->
                <!-- Inventory Health -->
                <div class="col-xl-4">
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-0">🚗 Inventory Health</h5>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadInventoryBtn" title="Download Report">
                        <i class="fas fa-download"></i>
                      </button>
                    </div>
                    <div class="card-body">

                      <canvas id="inventoryHealthChart" height="350"></canvas>
                    </div>

                  </div>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($roleName == "Administrator"): ?>

            <!-- Profitability by Make/Model Table -->

            <div class="row mt-4">
              <div class="col-12">
                <div class="card">

                  <div class="card-body pt-4">
                    <?php if (!empty($profitabilityData)): ?>
                      <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;vehicle&quot;,&quot;sold&quot;,&quot;total_revenue&quot;,&quot;net_profit&quot;,&quot;avg_prep_cost&quot;,&quot;refund_rate&quot;,&quot;avg_sell_time&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                        <div class="row">
                          <div class="col-sm-12 col-md-12 col-xl-8">
                            <h3>💰 Profitability by Make / Model (Last 6 Months)</h3>
                            <!-- <p class="text-body-tertiary lh-sm mb-0">Payment received across all channels</p> -->
                          </div>
                          <div class="col-sm-12 col-md-12 col-xl-4 search-box mb-3">
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
                                <th class="sort border-top border-translucent ps-3" data-sort="vehicle">Make/Model </th>
                                <th class="sort border-top " data-sort="sold">Sold</th>
                                <th class="sort border-top " data-sort="total_revenue">Total Revenue</th>
                                <th class="sort border-top" data-sort="net_profit">Net Profit</th>
                                <th class="sort border-top" data-sort="avg_prep_cost">Avg. Prep Cost</th>
                                <th class="sort border-top" data-sort="refund_rate">Refund Rate</th>
                                <th class="sort border-top" data-sort="avg_sell_time">Avg. Sell Time</th>
                                <th class="sort align-left pe-0 border-top" scope="col">Recommendation</th>
                              </tr>
                            </thead>
                            <tbody class="list">
                              <?php foreach ($profitabilityData as $vehicle):
                                // Determine colors
                                $profitColor = getProfitColor($vehicle['avg_profit_per_vehicle']);
                                $prepCostColor = getPrepCostColor($vehicle['avg_prep_cost']);
                                $refundColor = getRefundColor($vehicle['refund_rate']);
                                $sellTimeColor = getSellTimeColor($vehicle['avg_sell_days']);
                                $recommendationColor = getRecommendationColor($vehicle['recommendation_stars']);
                              ?>
                                <tr class="vehicle-row"
                                  data-make="<?= htmlspecialchars($vehicle['make']) ?>"
                                  data-model="<?= htmlspecialchars($vehicle['model']) ?>"
                                  data-profit="<?= $vehicle['avg_profit_per_vehicle'] ?>"
                                  data-refund-rate="<?= $vehicle['refund_rate'] ?>"
                                  style="cursor: pointer;">

                                  <!-- MAKE/MODEL -->
                                  <td class="align-left vehicle ps-3">
                                    <div class="fw-bold">
                                      <?= htmlspecialchars($vehicle['make_model']) ?>
                                      <?php if (!empty($vehicle['badge'])): ?>
                                        <span class="ms-1"><?= $vehicle['badge'] ?></span>
                                      <?php endif; ?>
                                    </div>
                                  </td>

                                  <!-- SOLD -->
                                  <td class="align-left sold"><?= $vehicle['total_sold'] ?></td>

                                  <!-- TOTAL REVENUE -->
                                  <td class="align-left total_revenue">£<?= number_format($vehicle['total_revenue'], 0) ?></td>

                                  <!-- NET PROFIT -->
                                  <td class="align-left net_profit">
                                    <span class="fw-bold text-<?= $profitColor ?>">
                                      £<?= number_format($vehicle['total_profit'], 0) ?>
                                      <br>
                                      <small class="text-muted">(£<?= number_format($vehicle['avg_profit_per_vehicle'], 0) ?>)</small>
                                    </span>
                                  </td>

                                  <!-- AVG. PREP COST -->
                                  <td class="align-left avg_prep_cost">
                                    <span class="text-<?= $prepCostColor ?>">
                                      £<?= number_format($vehicle['avg_prep_cost'], 0) ?>
                                    </span>
                                  </td>

                                  <!-- REFUND RATE -->
                                  <td class="align-left refund_rate">
                                    <span class="text-<?= $refundColor ?>">
                                      <?= number_format($vehicle['refund_rate'], 1) ?>%
                                      <br>
                                      <small class="text-muted">(<?= $vehicle['refund_count'] ?>/<?= $vehicle['total_sold'] ?>)</small>
                                    </span>
                                  </td>

                                  <!-- AVG. SELL TIME -->
                                  <td class="align-left avg_sell_time">
                                    <span class="text-<?= $sellTimeColor ?>">
                                      <?= number_format($vehicle['avg_sell_days'], 0) ?> days
                                    </span>
                                  </td>

                                  <!-- RECOMMENDATION -->
                                  <td class="align-left ">
                                    <span class="fw-bold text-<?= $recommendationColor ?>">
                                      <?= $vehicle['recommendation_stars'] ?>
                                    </span>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
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
                    <?php else: ?>
                      <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <h5>No profitability data yet</h5>
                        <p>Make sales in the last 6 months to see analysis</p>
                      </div>
                    <?php endif; ?>
                  </div>
                  <small class="p-4">
                    💰 - Highest profit ¦
                    ✅ - Lowest refund rate ¦
                    ⚡ - Fastest selling ¦
                    🏆 - Most sold
                  </small>
                </div>
              </div>
            </div>
                      <?php endif; ?>

          </div>
        </div>
        <!-- Right Sidebar - Pending Job Cards & Tasks -->
        <div class="col-xl-3">
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
                    $progress = getJobCardProgress($job);
                    $priorityClass = getPriorityBorderClass($job['priority']);
                  ?>
                    <div class="p-3 border-bottom <?= $priorityClass ?> border-start border-4">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                          <small class="text-muted"><?= $job['job_card_number'] ?></small>
                          <div class="fw-bold">
                            <?= htmlspecialchars($job['make']) ?> <?= htmlspecialchars($job['model']) ?> -
                            <small class="text-muted"><?= htmlspecialchars($job['regnumber']) ?></small>
                          </div>
                        </div>
                        <span class="badge <?= getPriorityBadgeClass($job['priority']) ?>">
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
                        <span class="badge badge-phoenix badge-phoenix-danger"><?= $task['pending_tasks'] ?> tasks</span>
                      </div>

                      <small class="text-muted">
                        <?= htmlspecialchars($task['task_names']) ?>
                      </small>

                      <div class="mt-2">
                        <a href="view-job-card.php?jc=<?= $task['job_card_number'] ?>" class="btn btn-sm btn-outline-info">View</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <!-- Install buttons section -->
          <div class="card p-3 mt-3" id="installButtonsContainer">
            <p>Install BritDrive for better experience</p>

            <!-- Android Install Button (hidden by default) -->
            <button id="installButton" class="btn btn-subtle-success mb-2 d-block" style="box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
              <i class="fab fa-android me-1 fs-7"></i> Install Android App
            </button>

            <!-- iOS Install Button (always visible on iOS) -->
            <a id="iosInstallButton" class="btn btn-subtle-primary d-block " style="box-shadow: 0 2px 10px rgba(0,0,0,0.2);"
              href="install-instructions.php"> <i class="fab fa-apple me-1 fs-7"></i> Install Apple App
            </a>
          </div>
        </div>
      </div>


      <?php include("footer.php"); ?>
    </div>
  </main><!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->

  <!-- Dashboard Charts -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      /***************************
       * MONTHLY FINANCIAL CHART *
       ***************************/
      const financialCtx = document.getElementById('monthlyFinancialChart').getContext('2d');

      // Prepare data for financial chart
      const monthLabels = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'month_name')) ?>;
      const revenueData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'monthly_total_revenue')) ?>;
      const costData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'monthly_total_cost')) ?>;
      const profitData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'monthly_net_profit')) ?>;
      const refundData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'monthly_refund_amount')) ?>;
      const netRevenueData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'net_revenue_after_refunds')) ?>;
      const netProfitData = <?= json_encode(array_column($chartData['monthly_financial'] ?? [], 'net_profit_after_refunds')) ?>;

      // Debug log
      console.log('Financial Chart Data:', {
        months: monthLabels,
        revenue: revenueData,
        cost: costData,
        profit: profitData,
        refunds: refundData,
        netRevenue: netRevenueData,
        netProfit: netProfitData
      });

      // Create financial chart
      const monthlyFinancialChart = new Chart(financialCtx, {
        type: 'bar',
        data: {
          labels: monthLabels,
          datasets: [{
              label: 'Total Revenue',
              data: revenueData,
              backgroundColor: 'rgba(44, 123, 229, 0.9)',
              borderColor: '#2c7be5',
              borderWidth: 2,
              barPercentage: 0.7,
              categoryPercentage: 0.7,
              hidden: false
            },
            {
              label: 'Total Cost',
              data: costData,
              backgroundColor: 'rgba(253, 126, 20, 0.9)',
              borderColor: '#fd7e14',
              borderWidth: 2,
              barPercentage: 0.7,
              categoryPercentage: 0.7,
              hidden: false
            },
            {
              label: 'Net Profit',
              data: profitData,
              backgroundColor: function(context) {
                const value = profitData[context.dataIndex];
                return value >= 0 ?
                  'rgba(0, 217, 126, 0.9)' :
                  'rgba(220, 53, 69, 0.9)';
              },
              borderColor: function(context) {
                const value = profitData[context.dataIndex];
                return value >= 0 ? '#00d97e' : '#dc3545';
              },
              borderWidth: 2,
              barPercentage: 0.7,
              categoryPercentage: 0.7,
              hidden: false
            },
            {
              label: 'Refunds',
              data: refundData,
              backgroundColor: 'rgba(232, 62, 140, 0.9)',
              borderColor: '#e83e8c',
              borderWidth: 2,
              barPercentage: 0.7,
              categoryPercentage: 0.7,
              hidden: true
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              callbacks: {
                beforeLabel: function(context) {
                  const monthIndex = context.dataIndex;
                  const month = monthLabels[monthIndex];
                  const refundAmount = refundData[monthIndex] || 0;
                  const netRevenue = netRevenueData[monthIndex] || 0;
                  const netProfit = netProfitData[monthIndex] || 0;


                  return;
                },
                label: function(context) {
                  let label = context.dataset.label || '';
                  label += ': £' + context.parsed.y.toLocaleString();

                  // Add profit percentage for profit tooltip
                  if (context.dataset.label === 'Net Profit' && context.parsed.y !== 0) {
                    const revenue = revenueData[context.dataIndex];
                    if (revenue > 0) {
                      const percentage = (context.parsed.y / revenue * 100).toFixed(1);
                      label += ` (${percentage}%)`;
                    }
                  }

                  return label;
                }
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              ticks: {
                maxRotation: 0
              }
            },
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return '£' + value.toLocaleString();
                }
              },
              title: {
                display: true,
                text: 'Amount (£)'
              }
            }
          }
        }
      });

      // Chart Type Toggle (Bar/Line)
      document.querySelectorAll('[data-type]').forEach(button => {
        button.addEventListener('click', function(e) {
          const type = this.dataset.type;

          // Update active button
          this.closest('.btn-group').querySelectorAll('button').forEach(btn => {
            btn.classList.remove('active');
          });
          this.classList.add('active');

          // Change chart type
          monthlyFinancialChart.config.type = type;

          // Adjust bar width for bar chart
          if (type === 'bar') {
            monthlyFinancialChart.data.datasets.forEach(dataset => {
              dataset.barPercentage = 0.7;
              dataset.categoryPercentage = 0.7;
            });
          } else {
            // Remove bar-specific properties for line chart
            monthlyFinancialChart.data.datasets.forEach(dataset => {
              delete dataset.barPercentage;
              delete dataset.categoryPercentage;
            });
          }

          monthlyFinancialChart.update();
        });
      });

      // Metric toggles
      document.querySelectorAll('.metric-toggle').forEach(toggle => {
        toggle.addEventListener('change', function(e) {
          const datasetIndex = parseInt(this.dataset.index);
          const isVisible = this.checked;
          monthlyFinancialChart.setDatasetVisibility(datasetIndex, isVisible);
          monthlyFinancialChart.update();
          updateShowAllToggle();
        });
      });

      // "Show All" toggle
      document.getElementById('showAllToggle').addEventListener('change', function(e) {
        const showAll = this.checked;
        document.querySelectorAll('.metric-toggle').forEach(toggle => {
          toggle.checked = showAll;
          const datasetIndex = parseInt(toggle.dataset.index);
          monthlyFinancialChart.setDatasetVisibility(datasetIndex, showAll);
        });
        monthlyFinancialChart.update();
      });

      function updateShowAllToggle() {
        const allToggles = document.querySelectorAll('.metric-toggle');
        const allChecked = Array.from(allToggles).every(toggle => toggle.checked);
        const allUnchecked = Array.from(allToggles).every(toggle => !toggle.checked);
        const showAllToggle = document.getElementById('showAllToggle');

        if (allChecked) {
          showAllToggle.checked = true;
          showAllToggle.indeterminate = false;
        } else if (allUnchecked) {
          showAllToggle.checked = false;
          showAllToggle.indeterminate = false;
        } else {
          showAllToggle.checked = false;
          showAllToggle.indeterminate = true;
        }
      }

      // Badge click handlers
      document.querySelectorAll('.card-header .badge').forEach(badge => {
        badge.addEventListener('click', function(e) {
          e.stopPropagation();
          const toggleId = this.closest('label').getAttribute('for');
          const toggle = document.getElementById(toggleId);
          if (toggle) {
            toggle.checked = !toggle.checked;
            toggle.dispatchEvent(new Event('change'));
          }
        });
      });

      // Debug button functionality
      const debugBtn = document.getElementById('debugBtn');
      if (debugBtn) {
        debugBtn.addEventListener('click', function() {
          const currentMonth = new Date().getMonth(); // 0-indexed
          const currentMonthData = {
            month: monthLabels[currentMonth],
            revenue: revenueData[currentMonth],
            cost: costData[currentMonth],
            profit: profitData[currentMonth],
            refunds: refundData[currentMonth],
            netRevenue: netRevenueData[currentMonth],
            netProfit: netProfitData[currentMonth]
          };

          console.log('Current Month Data:', currentMonthData);

          alert(`Current Month (${currentMonthData.month}):\n` +
            `Revenue: £${currentMonthData.revenue}\n` +
            `Cost: £${currentMonthData.cost}\n` +
            `Profit: £${currentMonthData.profit}\n` +
            `Refunds: £${currentMonthData.refunds}\n` +
            `Net Revenue: £${currentMonthData.netRevenue}\n` +
            `Net Profit: £${currentMonthData.netProfit}\n\n` +
            `Check console for full data.`);
        });
      }

      updateShowAllToggle();

      /*************************
       * INVENTORY HEALTH CHART *
       *************************/
      const inventoryCtx = document.getElementById('inventoryHealthChart').getContext('2d');

      // FIXED: Use 'display_status' instead of 'status'
      const inventoryLabels = <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'display_status')) ?>;
      const inventoryData = <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'vehicle_count')) ?>;
      const inventoryValues = <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'total_inventory_value')) ?>;
      const inventoryAges = <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'avg_age_days')) ?>;

      console.log('Inventory Chart Data:', {
        labels: inventoryLabels,
        counts: inventoryData,
        values: inventoryValues,
        ages: inventoryAges
      });

      // Color mapping - updated for your actual labels
      const statusColors = {
        'part_exchange': '#fd7e14', // Orange
        'sold': '#6e84a3', // Gray
        'available': '#00d97e', // Green
        'in_preparation': '#f6c343', // Yellow
        'purchased': '#2c7be5', // Blue
        'refunded_to_customer': '#e83e8c', // Pink
        'Returned (Refund)': '#e83e8c', // Pink
        'Unknown': '#95aac9' // Light blue
      };

      // Generate background colors
      const backgroundColors = inventoryLabels.map(label =>
        statusColors[label] || '#95aac9'
      );

      console.log('Background Colors:', backgroundColors);

      // Calculate total inventory value
      const totalValue = inventoryValues.reduce((a, b) => a + parseFloat(b), 0);
      const totalVehicles = inventoryData.reduce((a, b) => a + parseInt(b), 0);

      const inventoryHealthChart = new Chart(inventoryCtx, {
        type: 'doughnut',
        data: {
          labels: inventoryLabels,
          datasets: [{
            data: inventoryData,
            backgroundColor: backgroundColors,
            borderWidth: 2,
            borderColor: '#fff',
            hoverBorderWidth: 3,
            hoverBorderColor: '#000'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            centerText: {
              text: `£${formatCurrency(totalValue, 0)}`,
              subText: `${totalVehicles} Total Vehicles`,
              color: '#2c7be5',
              subColor: '#6e84a3',
              fontStyle: 'bold',
              fontSize: 24,
              subFontSize: 14,
              fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
            },
            legend: {
              position: 'bottom',
              labels: {
                padding: 15,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                  size: 11,
                  family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                },
                color: '#333'
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';

                  const statusIndex = context.dataIndex;
                  const inventoryValue = inventoryValues[statusIndex] || 0;
                  const avgAge = inventoryAges[statusIndex] || 0;
                  const avgCost = <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'avg_cost')) ?>[statusIndex] || 0;

                  return [
                    `${label}: ${value} vehicle${value !== 1 ? 's' : ''}`,
                    `Inventory Value: £${formatCurrency(inventoryValue, 2)}`,
                    `Average Cost: £${formatCurrency(avgCost, 2)}`,
                    `Average Age: ${Math.round(avgAge)} days`,
                    `(${percentage}% of total inventory)`
                  ];
                }
              },
              backgroundColor: 'rgba(0, 0, 0, 0.85)',
              titleColor: '#fff',
              bodyColor: '#fff',
              titleFont: {
                size: 12,
                weight: 'bold'
              },
              bodyFont: {
                size: 11
              },
              padding: 12,
              cornerRadius: 6
            }
          },
          cutout: '65%',
          animation: {
            duration: 1000,
            easing: 'easeOutQuart'
          }
        },
        plugins: [{
          id: 'centerText',
          beforeDraw: function(chart) {
            if (chart.config.options.plugins.centerText) {
              const centerConfig = chart.config.options.plugins.centerText;
              const ctx = chart.ctx;

              const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
              const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

              ctx.save();

              // Main text (currency value)
              ctx.font = `${centerConfig.fontStyle || 'normal'} ${centerConfig.fontSize || 24}px ${centerConfig.fontFamily || 'Arial'}`;
              ctx.fillStyle = centerConfig.color || '#000';
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.fillText(centerConfig.text, centerX, centerY - (centerConfig.fontSize || 24) * 0.2);

              // Subtext (label)
              if (centerConfig.subText) {
                ctx.font = `${centerConfig.subFontStyle || 'normal'} ${centerConfig.subFontSize || 14}px ${centerConfig.fontFamily || 'Arial'}`;
                ctx.fillStyle = centerConfig.subColor || '#666';
                ctx.fillText(centerConfig.subText, centerX, centerY + (centerConfig.subFontSize || 14) * 1.2);
              }

              ctx.restore();
            }
          }
        }]
      });

      // Helper function to format currency
      function formatCurrency(value, decimals = 2) {
        return parseFloat(value).toLocaleString('en-GB', {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals
        });
      }
      // Inventory chart download button (in header)
      document.getElementById('downloadInventoryBtn')?.addEventListener('click', function() {
        const link = document.createElement('a');
        const date = new Date().toISOString().split('T')[0];
        link.download = `inventory-report-${date}.png`;
        link.href = inventoryHealthChart.toBase64Image();
        link.click();

        // Optional: Show download confirmation
        this.innerHTML = '<i class="fas fa-check"></i>';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-success');
        this.title = 'Downloaded!';

        setTimeout(() => {
          this.innerHTML = '<i class="fas fa-download"></i>';
          this.classList.remove('btn-success');
          this.classList.add('btn-outline-secondary');
          this.title = 'Download Report';
        }, 2000);
      });


      // Financial chart download button
      document.getElementById('downloadFinancialBtn')?.addEventListener('click', function() {
        const link = document.createElement('a');
        const date = new Date().toISOString().split('T')[0];
        link.download = `financial-chart-${date}.png`;
        link.href = monthlyFinancialChart.toBase64Image();
        link.click();

        // Show download confirmation
        this.innerHTML = '<i class="fas fa-check"></i>';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-success');
        this.title = 'Downloaded!';

        setTimeout(() => {
          this.innerHTML = '<i class="fas fa-download"></i>';
          this.classList.remove('btn-success');
          this.classList.add('btn-outline-secondary');
          this.title = 'Download Chart';
        }, 2000);
      });
      /**************************
       * ADDITIONAL ENHANCEMENTS *
       **************************/

      // Auto-refresh data every 5 minutes (optional)
      let refreshInterval;
      const autoRefreshCheckbox = document.getElementById('autoRefreshToggle');

      if (autoRefreshCheckbox) {
        autoRefreshCheckbox.addEventListener('change', function(e) {
          if (this.checked) {
            refreshInterval = setInterval(() => {
              console.log('Auto-refreshing chart data...');
              // You would need to implement a data refresh function here
              // For now, just update the charts
              monthlyFinancialChart.update();
              inventoryHealthChart.update();
            }, 300000); // 5 minutes
          } else {
            clearInterval(refreshInterval);
          }
        });
      }

      // Print chart data on window resize for debugging
      let resizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
          console.log('Chart resized - Financial chart type:', monthlyFinancialChart.config.type);
          console.log('Visible datasets:', monthlyFinancialChart.data.datasets
            .map((ds, idx) => ({
              label: ds.label,
              hidden: ds.hidden
            })));
        }, 250);
      });
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add click handler for vehicle rows
      document.querySelectorAll('.vehicle-row').forEach(row => {
        row.addEventListener('click', function() {
          const make = this.dataset.make;
          const model = this.dataset.model;
          const profit = parseFloat(this.dataset.profit);
          const refundRate = parseFloat(this.dataset.refundRate);

          // Show modal with details (you need to create this modal)
          showVehicleDetailsModal(make, model, profit, refundRate);
        });
      });

      function showVehicleDetailsModal(make, model, profit, refundRate) {
        // Create or use existing modal
        let modal = document.getElementById('vehicleDetailsModal');

        if (!modal) {
          // Create modal HTML
          modal = document.createElement('div');
          modal.id = 'vehicleDetailsModal';
          modal.className = 'modal fade';
          modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">📊 Vehicle Profit Analysis</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="vehicleDetailsContent">
                            Loading...
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="viewAllVehicles('${make}', '${model}')">
                                View All Vehicles
                            </button>
                        </div>
                    </div>
                </div>
            `;
          document.body.appendChild(modal);
        }

        // Load content via AJAX or show basic info
        const content = `
            <h4>${make} ${model}</h4>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Profit Breakdown</h6>
                            <table class="table table-sm">
                                <tr><td>Avg Profit per Vehicle:</td><td class="text-end">£${profit.toFixed(0)}</td></tr>
                                <tr><td>Refund Rate:</td><td class="text-end">${refundRate.toFixed(1)}%</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Quick Stats</h6>
                            <p>Detailed analysis would be loaded here...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('vehicleDetailsContent').innerHTML = content;

        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      }

      // Global function for modal footer button
      window.viewAllVehicles = function(make, model) {
        window.location.href = `vehicles.php?make=${encodeURIComponent(make)}&model=${encodeURIComponent(model)}`;
      };
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
  <!-- PWA Installation Prompt & Service Worker -->
  <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('service-worker.js')
          .then(function(registration) {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
          })
          .catch(function(error) {
            console.log('ServiceWorker registration failed: ', error);
          });
      });
    }

    // PWA Installation Prompt
    let deferredPrompt;
    const installButton = document.getElementById('installButton');

    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent Chrome 67 and earlier from automatically showing the prompt
      e.preventDefault();
      // Stash the event so it can be triggered later
      deferredPrompt = e;

      // Show install button
      if (installButton) {
        installButton.style.display = 'block';
        installButton.addEventListener('click', (e) => {
          // Show the install prompt
          deferredPrompt.prompt();
          // Wait for the user to respond to the prompt
          deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
              console.log('User accepted the install prompt');
            } else {
              console.log('User dismissed the install prompt');
            }
            deferredPrompt = null;
          });
        });
      }
    });

    // Detect if app is installed
    window.addEventListener('appinstalled', (evt) => {
      console.log('BritDrive was installed successfully!');
      // Hide install button
      if (installButton) {
        installButton.style.display = 'none';
      }

      // Show welcome message
      showNotification('BritDrive installed successfully! 🎉');
    });

    // Check if running in standalone mode (installed)
    function isRunningStandalone() {
      return window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone ||
        document.referrer.includes('android-app://');
    }

    // Show notification
    function showNotification(message) {
      if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('BritDrive', {
          body: message,
          icon: 'assets/icons/icon-192x192.png'
        });
      }
    }

    // Request notification permission
    function requestNotificationPermission() {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
          if (permission === 'granted') {
            console.log('Notification permission granted');
          }
        });
      }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Check if installed
      if (isRunningStandalone()) {
        console.log('Running in installed mode');
        document.body.classList.add('pwa-installed');
      }

      // Request notification permission (optional)
      setTimeout(requestNotificationPermission, 2000);
    });
  </script>

</body>

</html>