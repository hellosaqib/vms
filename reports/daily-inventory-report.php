<?php
$requiredPermission = 'manage_reports';
require_once '../includes/check_auth.php';
require_once '../includes/functions.php';
$pageTitle = 'Daily Inventory Report - Car Trade System';

include '../header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Pagination parameters
$perPage = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $db->query("SELECT COUNT(*) FROM vehicles WHERE status NOT IN ('Sold')");
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Get paginated data
$query = "SELECT 
        v.vehicle_id,
        v.make,
        v.model,
        v.year,
        v.vin,
        v.regnumber,
        v.status,
        v.purchase_price,
        v.purchase_date,
        DATEDIFF(CURRENT_DATE, v.purchase_date) AS days_in_stock,
        COUNT(DISTINCT i.image_path) AS image_count,
        GROUP_CONCAT(DISTINCT r.repair_type SEPARATOR ', ') AS pending_repairs,
        SUM(IFNULL(r.cost, 0)) AS total_repair_costs,
        SUM(IFNULL(e.amount, 0)) AS other_expenses
    FROM 
        vehicles v
    LEFT JOIN 
        vehicle_images i ON v.vehicle_id = i.vehicle_id
    LEFT JOIN 
        repairs r ON v.vehicle_id = r.vehicle_id
    LEFT JOIN 
        expenses e ON v.vehicle_id = e.vehicle_id
    WHERE 
        v.status NOT IN ('Sold', 'Archived')
    GROUP BY 
        v.vehicle_id
    ORDER BY 
        v.status, days_in_stock DESC
          LIMIT :offset, :perPage";

$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current date for report
$reportDate = date('F j, Y');

// 1. Handle Export Requests
if (isset($_GET['export'])) {
    exportInventoryReport($db);
    exit;
}

// 2. Generate HTML Report
$reportDate = date('F j, Y');
$vehicles = getUnsoldInventory($db);

// 3. Export Function
function exportInventoryReport($db) {
    $data = getUnsoldInventory($db);
    
    header('Content-Type: ' . ($_GET['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv'));
    header('Content-Disposition: attachment; filename="inventory_snapshot_' . date('Y-m-d') . '.' . ($_GET['export'] === 'excel' ? 'xls' : 'csv') . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV/Excel Headers
    fputcsv($output, [
        'Make', 'Model', 'Year', 'VIN', 'Reg Number', 'Status',
        'Purchase Price', 'Repair Costs', 'Other Expenses', 'Days in Stock', 'Pending Repairs'
    ]);
    
    // Data Rows
    foreach ($data as $vehicle) {
        fputcsv($output, [
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['year'],
            $vehicle['vin'],
            $vehicle['regnumber'],
            $vehicle['status'],
            $vehicle['purchase_price'],
            $vehicle['total_repair_costs'],
            $vehicle['other_expenses'],
            $vehicle['days_in_stock'],
            $vehicle['pending_repairs'] ?? 'None'
        ]);
    }
    
    fclose($output);
    exit;
}

// 4. Data Fetch Function
function getUnsoldInventory($db) {
    $query1="SELECT 
        v.vehicle_id,
        v.make,
        v.model,
        v.year,
        v.vin,
        v.regnumber,
        v.status,
        v.purchase_price,
        v.purchase_date,
        DATEDIFF(CURRENT_DATE, v.purchase_date) AS days_in_stock,
        COUNT(DISTINCT i.image_path) AS image_count,
        GROUP_CONCAT(DISTINCT r.repair_type SEPARATOR ', ') AS pending_repairs,
        SUM(IFNULL(r.cost, 0)) AS total_repair_costs,
        SUM(IFNULL(e.amount, 0)) AS other_expenses
    FROM 
        vehicles v
    LEFT JOIN 
        vehicle_images i ON v.vehicle_id = i.vehicle_id
    LEFT JOIN 
        repairs r ON v.vehicle_id = r.vehicle_id
    LEFT JOIN 
        expenses e ON v.vehicle_id = e.vehicle_id
    WHERE 
        v.status NOT IN ('Sold', 'Archived')
    GROUP BY 
        v.vehicle_id
    ORDER BY 
        v.status, days_in_stock DESC";

$stmt = $db->prepare($query1);
$stmt->execute();
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $URL ?>/assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $URL ?>/assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $URL ?>/assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?= $URL ?>/assets/img/favicons/favicon.ico">
    <link rel="manifest" href="<?= $URL ?>/assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="<?= $URL ?>/assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <script src="<?= $URL ?>/vendors/simplebar/simplebar.min.js"></script>
    <script src="<?= $URL ?>/assets/js/config.js"></script>

    <!-- ===============================================-->
    <!--    Stylesheets   -->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $URL ?>/assets/css/line.css">
    <!-- <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl"> -->
    <link href="<?= $URL ?>/assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
    <!-- <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl"> -->
    <link href="<?= $URL ?>/assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
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
    <link href="<?= $URL ?>/vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="<?= $URL ?>/vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">



</head>

<body>
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <?php include("../left-menu.php"); ?>
        <!-- Main Content -->
        <div class="content">
            <div class="pb-5">
                <div class="row gy-3 mb-6 justify-content-between">
                    <div class="col-md-9 col-auto">
                        <h2 class="mb-2 text-body-emphasis">Daily Inventory Snapshot</h2>
                        <h5 class="text-body-tertiary fw-semibold">Here’s what’s going on at your business daily</h5>
                    </div>
                    <div class="col-md-3 col-auto text-end">
                        <h4 class="text-danger"><?= date('M j, Y', time()) ?></h4>
                    </div>
                </div>



<div class="row flex-between-center mb-4 g-3">
                  <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;make&quot;,&quot;model&quot;,&quot;year&quot;,&quot;mileage&quot;,&quot;price&quot;,&quot;status&quot;],&quot;page&quot;:15,&quot;pagination&quot;:true}">
                    <div class="row">
                      <div class="col-8">
                        <button class="btn btn-sm btn-outline-danger me-1 mb-1" id="printReport" type="button"><i class="fas fa-print me-1"></i> Print</button>
                        <a class="btn btn-sm btn-outline-success me-1 mb-1" href="?export=excel" type="button"><i class="fas fa-file-excel me-2"></i> Excel</a>
                        <button class="btn btn-sm btn-outline-info me-1 mb-1" href="?export=csv" type="button"><i class="fas fa-file-csv me-2"></i> CSV</button>
            
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
                            <th class="sort border-top" data-sort="id">Vehicle</th>
                            <th class="sort border-top " data-sort="year">Year</th>
                            <th class="sort border-top" data-sort="status">Status</th>
                            <th class="sort border-top" data-sort="price">Purchase Price</th>
                            <th class="sort border-top border-translucent ps-3" data-sort="make">Repair Costs</th>
                            <th class="sort border-top " data-sort="expenses"> Expenses</th>
                            <th class="sort border-top " data-sort="total_cost">Total Cost</th>
                            <th class="sort border-top" data-sort="mileage">Days in Stock</th>
                            <th class="sort border-top" data-sort="mileage">Pending Repairs</th>
                            
                            <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                          </tr>
                        </thead>
                        <tbody class="list">
                          <?php foreach ($vehicles as $vehicle): 
                            $isAging = $vehicle['days_in_stock'] > 60;
                            $hasRepairs = !empty($vehicle['pending_repairs']);
                        ?>
                            <tr>
                              <td class="align-middle ps-3 id">
                                    <strong><?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></strong>
                            <small class="text-muted d-block">Reg: <?= htmlspecialchars($vehicle['regnumber']) ?></small>
                                </td>
                                <td class="align-left year"><?= $vehicle['year'] ?></td>
                              <td class="allign-middle status">
                                <span class="badge badge-phoenix fs-10 badge-phoenix-<?=
                                                                                      $vehicle['status'] == 'sold' ? 'success' : ($vehicle['status'] == 'in_repair' ? 'warning' : 'info')
                                                                                      ?>">
                                  <?= ucfirst($vehicle['status']) ?>
                                </span>
                              </td>
                              <td class="align-left price">£<?= number_format($vehicle['purchase_price'], 2) ?></td>
                              <td class="align-left price">£<?= number_format($vehicle['total_repair_costs'], 2) ?></td>
                              <td class="align-left price">£<?= number_format($vehicle['other_expenses'], 2) ?></td>
                              <td class="align-left price">£<?= ($vehicle['purchase_price']+$vehicle['total_repair_costs']+$vehicle['other_expenses']) ?></td>
                              
                             <td>
                            <?php echo $vehicle['days_in_stock']; ?> days
                            <?php if ($isAging): ?>
                            <i class="fas fa-exclamation-triangle text-danger ms-1" title="Aging stock"></i>
                            <?php endif; ?>
                        </td>
                            <td>
                            <?php if ($hasRepairs): ?>
                            <span class="badge bg-warning-light text-warning-dark">
                                <i class="fas fa-wrench me-1"></i>
                                <?php echo htmlspecialchars($vehicle['pending_repairs']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>  
                              
                              <td class="text-end align-middle">
                                <a href="<?= $URL?>/view-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="<?= $URL?>/edit-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                              </td>
                            </tr>
                          <?php endforeach; ?>

                        </tbody>
                      </table>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 10 <span class="text-body-tertiary"> Items of </span>43</span>
                      <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                            <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                          </svg><!-- <span class="fas fa-chevron-left"></span> Font Awesome fontawesome.com --></button>
                        <ul class="mb-0 pagination">
                          <li class="active"><button class="page" type="button" data-i="1" data-page="10">1</button></li>
                          <li><button class="page" type="button" data-i="2" data-page="10">2</button></li>
                          <li><button class="page" type="button" data-i="3" data-page="10">3</button></li>
                          <li class="disabled"><button class="page" type="button">...</button></li>
                        </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                            <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                          </svg><!-- <span class="fas fa-chevron-right"></span> Font Awesome fontawesome.com --></button>
                      </div>
                    </div>
                  </div>


                </div>


<!-- Print Stylesheet -->
<style media="print">
    @page { size: landscape; margin: 0.5cm; }
    body * { visibility: visible; }
    .card, .card * { visibility: visible; }
    .card { position: absolute; left: 0; top: 0; width: 100%; border: none; }
    .dropdown, .btn-group { display: none !important; }
    .badge { border: 1px solid #000 !important; color: #000 !important; }
    table { width: 100% !important; font-size: 12px !important; }
</style>


<!-- JavaScript for Print -->
<script>
document.getElementById('printReport').addEventListener('click', function() {
    // Store original content
    const originalTitle = document.title;
    // Update title for print
    document.title = "Inventory Snapshot - <?php echo $reportDate; ?>";
    
    // Trigger print
    window.print();
    
    // Restore original title
    document.title = originalTitle;
});
</script>

            </div>

            <!-- Footer -->
            <?php include("../footer.php"); ?>
        </div>

        <!-- Chat Now -->
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->



    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="<?= $URL ?>/vendors/popper/popper.min.js"></script>
    <script src="<?= $URL ?>/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="<?= $URL ?>/vendors/anchorjs/anchor.min.js"></script>
    <script src="<?= $URL ?>/vendors/is/is.min.js"></script>
    <script src="<?= $URL ?>/vendors/fontawesome/all.min.js"></script>
    <script src="<?= $URL ?>/vendors/lodash/lodash.min.js"></script>
    <script src="<?= $URL ?>/vendors/list.js/list.min.js"></script>
    <script src="<?= $URL ?>/vendors/feather-icons/feather.min.js"></script>
    <script src="<?= $URL ?>/vendors/dayjs/dayjs.min.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet/leaflet.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
    <script src="<?= $URL ?>/vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter.min.js"></script>
    <script src="<?= $URL ?>/assets/js/phoenix.js"></script>
    <!-- <script src="vendors/echarts/echarts.min.js"></script> -->
    <!-- <script src="assets/js/projectmanagement-dashboard.js"></script> -->

</body>


</html>