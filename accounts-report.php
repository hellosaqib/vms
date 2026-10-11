<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "View All Invoices";
$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Check permissions
if (
  !hasPermission('manage_sales') &&
  !hasPermission('manage_own_sales')
) {
  $_SESSION['error'] = "You don't have permission to view invoices.";
  header('Location: index.php');
  exit;
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $perPage;

// Filters
$filters = [];
$params = [];

// Base query for invoices
$query = "
    SELECT 
        s.sale_id,
        s.invoice_no,
        s.sale_date,
        s.sale_price,
        s.payment_method,
        s.discount_amount,
        s.status as sale_status,
        s.balance_due,
        s.total_refunded,
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        v.year,
        v.purchase_date,
        v.purchase_price,
        c.customer_id,
        c.first_name,
        c.last_name,
        c.phone,
        u.full_name as salesperson_name,
        s.includes_part_exchange,
        s.part_exchange_allowance
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

if (isset($_GET['invoice_no']) && !empty($_GET['invoice_no'])) {
  $query .= " AND s.invoice_no LIKE ?";
  $params[] = '%' . $_GET['invoice_no'] . '%';
  $filters['invoice_no'] = $_GET['invoice_no'];
}

if (isset($_GET['customer']) && !empty($_GET['customer'])) {
  $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ?)";
  $customerSearch = "%" . $_GET['customer'] . "%";
  $params[] = $customerSearch;
  $params[] = $customerSearch;
  $params[] = $customerSearch;
  $filters['customer'] = $_GET['customer'];
}

if (isset($_GET['status']) && !empty($_GET['status']) && $_GET['status'] !== 'all') {
  $query .= " AND s.status = ?";
  $params[] = $_GET['status'];
  $filters['status'] = $_GET['status'];
}

if (isset($_GET['salesperson']) && !empty($_GET['salesperson'])) {
  $query .= " AND s.sales_person = ?";
  $params[] = $_GET['salesperson'];
  $filters['salesperson'] = $_GET['salesperson'];
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
$query .= " ORDER BY s.sale_date DESC, s.sale_id DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count for pagination (simplified)
$countQuery = "SELECT COUNT(*) as total FROM sales s 
               LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
               LEFT JOIN customers c ON s.customer_id = c.customer_id
               WHERE 1=1";

$countParams = [];
if (isset($filters['date_from'])) {
  $countQuery .= " AND s.sale_date >= ?";
  $countParams[] = $filters['date_from'];
}
if (isset($filters['date_to'])) {
  $countQuery .= " AND s.sale_date <= ?";
  $countParams[] = $filters['date_to'];
}
if (isset($filters['status']) && $filters['status'] !== 'all') {
  $countQuery .= " AND s.status = ?";
  $countParams[] = $filters['status'];
}

// Permission-based count filtering
if (
  !hasPermission('manage_sale', $db, $_SESSION['user_id']) &&
  hasPermission('manage_own_sale', $db, $_SESSION['user_id'])
) {
  $countQuery .= " AND s.sales_person = ?";
  $countParams[] = $_SESSION['user_id'];
}

$totalStmt = $db->prepare($countQuery);
$totalStmt->execute($countParams);
$totalInvoices = $totalStmt->fetchColumn();
$totalPages = ceil($totalInvoices / $perPage);
$total = $totalPages;

// Get salespeople for filter dropdown
$salespeopleStmt = $db->prepare("SELECT id, full_name FROM users WHERE role IN ('admin', 'staff') ORDER BY full_name");
$salespeopleStmt->execute();
$salespeople = $salespeopleStmt->fetchAll(PDO::FETCH_ASSOC);

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
      //linkRTL.setAttribute('disabled', true);
      //userLinkRTL.setAttribute('disabled', true);
    }
  </script>
  <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* Container styles */
        #report-container {
            padding: 20px;
            margin: 20px auto;
            /* background: white; */
        }
        
        
        
        /* Button styles */
        .btn-group {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-print:hover {
            background: #218838;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-pdf:hover {
            background: #c82333;
        }
        
                /* ===== PRINT STYLES - FIXED ===== */
        @media print {
            /* Hide everything except the print area */
             /* Show only the report */
            body * {
                visibility: hidden;
            }
            #report-container, #report-container * {
                visibility: visible;
            }
            #report-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
            }
        }

    </style>

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
              <h1 class="h2">Complete Report</h1>
              <!-- <div class="btn-toolbar mb-2 mb-md-0">
                <a href="view-all-sales.php" class="btn btn-outline-primary me-2">
                  <i class="fas fa-list"></i> View Sales
                </a>
                <a href="sale-vehicle.php" class="btn btn-success">
                  <i class="fas fa-plus"></i> New Sale
                </a>
              </div> -->
              <div class="btn-group no-print">
                          <button class="btn btn-print" onclick="printReport()">
                              🖨️ Print Report
                          </button>
                          <button class="btn btn-pdf" onclick="exportToPDF()">
                              📄 Export as PDF
                          </button>
                        </div>
            </div>
            <!-- Filters Card -->
            <div class="card filter-card mb-4">
              <div class="card-header">
                <h6 class="mb-0">🔍 Filter Invoices</h6>
              </div>
              <div class="card-body">
                <form method="GET" class="row g-3">
                  <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $filters['date_from'] ?? '' ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $filters['date_to'] ?? '' ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Invoice #</label>
                    <input type="text" class="form-control" name="invoice_no" placeholder="Invoice number..." value="<?= $filters['invoice_no'] ?? '' ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                      <option value="all">All Statuses</option>
                      <option value="completed" <?= isset($filters['status']) && $filters['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                      <option value="pending" <?= isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                      <option value="refunded" <?= isset($filters['status']) && $filters['status'] == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                      <option value="partially_refunded" <?= isset($filters['status']) && $filters['status'] == 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
                      <option value="cancelled" <?= isset($filters['status']) && $filters['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Customer Search</label>
                    <input type="text" class="form-control" name="customer" placeholder="Name or phone..." value="<?= $filters['customer'] ?? '' ?>">
                  </div>
                  <div class="col-md-4">
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
                  <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-50 me-2">
                      <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view-all-invoices.php" class="btn btn-outline-secondary w-50">
                      <i class="fas fa-times"></i> Clear
                    </a>
                  </div>
                </form>
              </div>
            </div>

            <div class="row flex-between-center mb-4 g-3 " id="report-container">
              <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;invoice_no&quot;,&quot;sale_date&quot;,&quot;vehicle&quot;,&quot;customer&quot;,&quot;price&quot;,&quot;status&quot;,&quot;sale_person&quot;,&quot;notes&quot;],&quot;page&quot;:10,&quot;pagination&quot;:false}">
                <div class="row">
                  <div class="col-8">
                    <h3>Complete Report 
                      <!-- (<?= number_format($totalInvoices) ?>) <?php if (!empty($filters)): ?>
                        - <span class="badge bg-info">Filtered: <?= count($invoices) ?></span>
                      <?php endif; ?> -->
                    </h3>
                    <!-- <p class="text-body-tertiary lh-sm mb-0">Payment received across all channels</p> -->
                  </div>
                  <div class="col-4 search-box mb-3">
                    <!-- <form class="position-relative">
                      <input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                      <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                      </svg>

                    </form> -->
                        
                </div>
                <div class="table-responsive">
                  <table class="table table-striped table-sm fs-9 mb-0">
                    <thead>
                      <tr>
                        <th class="sort border-top" data-sort="invoice_no">Invoice Number</th>
                        <th class="sort border-top " data-sort="vehicle">Vehicle</th>
                        <th class="sort border-top " data-sort="sale_date">Purchase Date</th>
                        <th class="sort border-top " data-sort="price">Purchase Price</th>
                        <th class="sort border-top " data-sort="sale_date">Sale Date</th>
                        <th class="sort border-top " data-sort="price">Price</th>
                        <!-- <th class="sort border-top" data-sort="status">Staus</th>
                        <th class="sort border-top" data-sort="sale_person">Sale Person</th>
                        <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th> -->
                      </tr>
                    </thead>
                    <tbody class="list">
                      <?php if (empty($invoices)): ?>
                        <tr>
                          <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-file-invoice fa-2x mb-2"></i><br>
                            No invoices found matching your criteria.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($invoices as $invoice): 
                            $salePrice = $invoice['sale_price'] - $invoice['discount_amount'];
                          ?>
                          <tr>
                            <td class="align-middle ps-3 invoice_no">
                              <a style="text-decoration: none;" href="edit-invoice.php?inv=<?= $invoice['invoice_no'] ?>">
                                <strong class="text-primary"><?= htmlspecialchars($invoice['invoice_no']) ?></strong>
                              </a>
                              <?php if ($invoice['includes_part_exchange']): ?>
                                <!-- <span class="badge badge-phoenix badge-phoenix-warning">P/X</span> -->
                              <?php endif; ?>
                            </td>
                            <td class="align-middle vehicle">
                              <div class="fw-bold"><?= htmlspecialchars($invoice['make'] . ' ' . $invoice['model']) ?></div>
                              <small class="text-muted"><?= htmlspecialchars($invoice['regnumber']) ?> • <?= $invoice['year'] ?></small>
                            </td>
                            <td class="align-middle purchase_date"><?= date('d-m-Y', strtotime($invoice['purchase_date'])) ?></td>
                            <td class="align-middle price">
                              <div class="fw-bold">£<?= number_format($invoice['purchase_price'], 2) ?></div>
                              <?php if ($invoice['total_refunded'] > 0): ?>
                                <!-- <small class="text-danger">Refunded: £<?= number_format($invoice['total_refunded'], 2) ?></small> -->
                              <?php elseif ($invoice['balance_due'] > 0): ?>
                                <!-- <small class="text-warning">Balance: £<?= number_format($invoice['balance_due'], 2) ?></small> -->
                              <?php else: ?>
                                <!-- <small class="text-success">Paid</small> -->
                              <?php endif; ?>
                            </td>
                            <td class="align-middle sale_date"><?= date('d-m-Y', strtotime($invoice['sale_date'])) ?></td>
                            <td class="align-middle price">
                              <div class="fw-bold">£<?= number_format($salePrice, 2) ?></div>
                              <?php if ($invoice['total_refunded'] > 0): ?>
                                <!-- <small class="text-danger">Refunded: £<?= number_format($invoice['total_refunded'], 2) ?></small> -->
                              <?php elseif ($invoice['balance_due'] > 0): ?>
                                <!-- <small class="text-warning">Balance: £<?= number_format($invoice['balance_due'], 2) ?></small> -->
                              <?php else: ?>
                                <!-- <small class="text-success">Paid</small> -->
                              <?php endif; ?>
                            </td>
                            <!-- <td class="align-middle status">
                              <?php
                              $statusConfig = [
                                'completed' => ['class' => 'badge-phoenix badge-phoenix-success', 'icon' => '✓', 'title' => 'Completed'],
                                'pending' => ['class' => 'badge-phoenix badge-phoenix-warning', 'icon' => '⏳', 'title' => 'Pending Payment'],
                                'refunded' => ['class' => 'badge-phoenix badge-phoenix-secondary', 'icon' => '↩️', 'title' => 'Fully Refunded'],
                                'partially_refunded' => ['class' => 'badge-phoenix badge-phoenix-info', 'icon' => '↪️', 'title' => 'Partially Refunded'],
                                'cancelled' => ['class' => 'badge-phoenix badge-phoenix-danger', 'icon' => '❌', 'title' => 'Cancelled']
                              ];
                              $status = $statusConfig[$invoice['sale_status']] ?? $statusConfig['completed'];
                              ?>
                              <span class="badge status-badge <?= $status['class'] ?>" title="<?= $status['title'] ?>">
                                <?= $status['icon'] ?> <?= ucfirst($invoice['sale_status']) ?>
                              </span>
                            </td>
                            <td class="align-middle sale_person"><?= htmlspecialchars($invoice['salesperson_name']) ?></td>
                            <td class="align-right text-end">
                              <div class="btn-group btn-group-sm">

                                <a href="sale-invoice.php?inv=<?= $invoice['invoice_no'] ?>"
                                  target="_blank"
                                  class="btn btn-outline-success"
                                  title="Print Invoice">
                                  <i class="fas fa-print"></i>
                                </a>
                                <?php if (hasPermission('manage_sales')): ?>
                                  <a href="edit-invoice.php?inv=<?= $invoice['invoice_no'] ?>"
                                    class="btn btn-outline-primary"
                                    title="Edit Invoice">
                                    <i class="fas fa-edit"></i>
                                  </a>
                                  <a href="process-payment.php?inv=<?= $invoice['invoice_no'] ?>"
                                    class="btn btn-outline-danger"
                                    title="Process Payment">
                                    <i class="fas fa-money-bill"></i>
                                  </a>

                                <?php endif; ?>
                              </div>
                            </td> -->
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- <div class="d-flex justify-content-between mt-3">
                  <span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span><?= $total ?></span>
                  <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                      </svg></button>
                    <ul class="mb-0 pagination">
                      <li class="active"><button class="page" type="button" data-i="1" data-page="5">1</button></li>
                      <li><button class="page" type="button" data-i="2" data-page="5">2</button></li>
                      <li><button class="page" type="button" data-i="3" data-page="5">3</button></li>
                      <li class="disabled"><button class="page" type="button">...</button></li>
                    </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                      </svg></button>
                  </div>
                </div> -->
              </div>


            </div>

          </div>





  </main>
  </div>
  </div>


  </div>
  </div>


  </div>

  <!-- Footer -->
        <?php include_once("footer.php"); ?>

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
       <script>
        // ===== PRINT FUNCTION =====
        function printReport() {
            window.print();
        }
        
        // ===== PDF EXPORT FUNCTION =====
        function exportToPDF() {
            const element = document.getElementById('report-container');
            
            // Show loading state
            const pdfBtn = document.querySelector('.btn-pdf');
            pdfBtn.textContent = '⏳ Generating...';
            pdfBtn.disabled = true;
            
            const opt = {
                margin:        [10, 10, 10, 10],
                filename:     'sold_vehicles_report.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF:        { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };
            
            html2pdf()
                .set(opt)
                .from(element)
                .save()
                .then(function() {
                    // Restore button
                    pdfBtn.textContent = '📄 Export as PDF';
                    pdfBtn.disabled = false;
                })
                .catch(function(error) {
                    console.error('PDF generation failed:', error);
                    pdfBtn.textContent = '❌ Failed - Try Again';
                    pdfBtn.disabled = false;
                    setTimeout(() => {
                        pdfBtn.textContent = '📄 Export as PDF';
                    }, 3000);
                });
        }
    </script>

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