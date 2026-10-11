<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "View all vehicles";
$user = $auth->getUser();
$roleName = $auth->getRoleName();

if (isset($_GET['delid'])) {
  $vehicle_id = (int)$_GET['delid'];

  $stmt = $db->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
  $stmt->execute([$vehicle_id]);

  if ($stmt->rowCount() > 0) {
    setSuccess("Vehicle ID# " . $vehicle_id . " has been deleted");
  } else {
    setWarning("Vehicle not found");
  }
  
}
// Pagination setup
// $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// $perPage = 10;
// $start = ($page > 1) ? ($page * $perPage) - $perPage : 0;
// Get total vehicles for pagination
$total = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
//$pages = ceil($total / $perPage);
// Check if status filter is provided
$hasStatusFilter = isset($_GET['status']) && !empty($_GET['status']);
$vehicles = [];

// Build query based on filter
if ($hasStatusFilter) {
  $vstatus = $_GET['status'];
  // Validate status to prevent SQL injection
  $allowedStatuses = ['available', 'in_preparation', 'purchased', 'part_exchange', 'sold','reserved'];
  if (!in_array($vstatus, $allowedStatuses)) {
    $vstatus = 'available'; // Default if invalid
  }

  $stmt = $db->prepare("
    SELECT v.*, a.auction_name, s.invoice_no
    FROM vehicles v
    LEFT JOIN auctions a ON v.auction_id = a.auction_id
    LEFT JOIN sales s 
        ON v.vehicle_id = s.vehicle_id
        AND s.status NOT IN ('cancelled', 'refunded')
    WHERE v.status = ?
    ORDER BY v.purchase_date DESC
");
  $stmt->execute([$vstatus]);
} else {

  // Show all vehicles when no status filter
  $stmt = $db->prepare("
    SELECT v.*, a.auction_name, s.invoice_no
    FROM vehicles v
    LEFT JOIN auctions a ON v.auction_id = a.auction_id
    LEFT JOIN sales s 
        ON v.vehicle_id = s.vehicle_id
        AND s.status NOT IN ('cancelled', 'refunded')
    WHERE v.status != 'sold'
    ORDER BY v.purchase_date DESC
");
  $stmt->execute();
}

$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

// print_r($inventoryData);

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
$purchased = $statusCounts['purchased'] ?? 0;
$part_exchange = $statusCounts['part_exchange'] ?? 0;
$refunded = $statusCounts['refunded_to_customer'] ?? 0;
$reserved = $statusCounts['reserved'] ?? 0;
//include '../includes/header.php';
//$purchased = $purchased + $part_exchange;
// Debug: Check the actual counts
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
      <?php displayMessage(); ?>
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-12">
            <div class="row gx-6 gy-3 mb-4 align-items-center">
              <div class="col-auto">
                <h2 class="mb-0">All Vehicles<span class="fw-normal text-body-tertiary ms-3">(<?= $total ?>)</span></h2>
              </div>
              <div class="col-auto"><a class="btn btn-primary px-5" href="add-vehicle.php"><span class="fa-solid fa-plus me-2"></span>Add Vehicle</a></div>
            </div>
            <div class="row justify-content-between align-items-end mb-4 g-3">
              <div class="col-9 col-sm-auto">
                <ul class="nav nav-links mx-n2">
                  <li class="nav-item"><a class="nav-link px-2 py-1 active" aria-current="page" href="view-all-vehicles.php"><span>All</span><span class="text-body-tertiary fw-semibold">(<?= $totalVehicles ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=available"><span>Available</span><span class="text-body-tertiary fw-semibold">(<?= $available  ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=in_preparation"><span>In&nbsp;Preparation</span><span class="text-body-tertiary fw-semibold">(<?= $in_preparation ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=sold"><span>Sold</span><span class="text-body-tertiary fw-semibold">(<?= $sold ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=purchased"><span>Purchased</span><span class="text-body-tertiary fw-semibold">(<?= $purchased ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=part_exchange"><span>Part&nbsp;Exchange</span><span class="text-body-tertiary fw-semibold">(<?= $part_exchange ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=refunded_to_customer"><span>Refunded</span><span class="text-body-tertiary fw-semibold">(<?= $refunded ?>)</span></a></li>
                  <li class="nav-item"><a class="nav-link px-2 py-1" href="view-all-vehicles.php?status=reserved"><span>Reserved</span><span class="text-body-tertiary fw-semibold">(<?= $reserved ?>)</span></a></li>
                </ul>
              </div>
              <div class="col-4 col-sm-auto ">
                <div class="d-flex">

                  <ul class="nav nav-underline " id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <a class="nav-link active" id="home-tab" data-bs-toggle="tab" href="#tab-home" role="tab" aria-controls="tab-home" aria-selected="false" tabindex="-1"><i class="fas fa-th-large"></i></a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a class="nav-link" id="profile-tab" data-bs-toggle="tab" href="#tab-profile" role="tab" aria-controls="tab-profile" aria-selected="true"><i class="fas fa-th-list"></i></a>
                    </li>
                  </ul>



                </div>
              </div>
            </div>
            <!-- <div class="row align-items-center g-4">
              <div class="col-12 col-md-auto">
                <div class="d-flex align-items-center"><span class="fa-stack" style="min-height: 46px;min-width: 46px;"><span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-success-light" data-fa-transform="down-4 rotate--10 left-4"></span><span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-success" data-fa-transform="up-4 right-3 grow-2"></span><span class="fa-stack-1x fa-solid fa-star text-success " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0">57 new orders</h4>
                    <p class="text-body-secondary fs-9 mb-0">Awating processing</p>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-auto">
                <div class="d-flex align-items-center"><span class="fa-stack" style="min-height: 46px;min-width: 46px;"><span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-warning-light" data-fa-transform="down-4 rotate--10 left-4"></span><span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-warning" data-fa-transform="up-4 right-3 grow-2"></span><span class="fa-stack-1x fa-solid fa-pause text-warning " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0">5 orders</h4>
                    <p class="text-body-secondary fs-9 mb-0">On hold</p>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-auto">
                <div class="d-flex align-items-center"><span class="fa-stack" style="min-height: 46px;min-width: 46px;"><span class="fa-solid fa-square fa-stack-2x dark__text-opacity-50 text-danger-light" data-fa-transform="down-4 rotate--10 left-4"></span><span class="fa-solid fa-circle fa-stack-2x stack-circle text-stats-circle-danger" data-fa-transform="up-4 right-3 grow-2"></span><span class="fa-stack-1x fa-solid fa-xmark text-danger " data-fa-transform="shrink-2 up-8 right-6"></span></span>
                  <div class="ms-3">
                    <h4 class="mb-0">15 products</h4>
                    <p class="text-body-secondary fs-9 mb-0">Out of stock</p>
                  </div>
                </div>
              </div>
            </div> -->

            <div class="tab-content mt-3" id="myTabContent">
              <div class="tab-pane fade show active" id="tab-home" role="tabpanel" aria-labelledby="home-tab">
                <div class="row mb-4">
                  <div class="col-md-12 ms-auto">
                    <div class="search-box">
                      <input type="text" id="cardSearchInput" class="form-control" placeholder="Search by Reg, Make or Model..." autocomplete="off">
                      <!-- <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
            </svg> -->
                    </div>
                  </div>
                </div>
                <div class="row">
                  <?php
                  foreach ($vehicles as $vehicle)
                  {
                    $images = getVehicleImagesById($vehicle['vehicle_id']);
                    if (!empty($images)) 
                    {
                      $featuredImage = $images['0'];
                    } else {
                      $featuredImage = '';
                    }


                  ?>
                    <div class=" col-md-3 col-sm-6 mb-5">

                      <div class="card">
                        <div class=" text-white overflow-hidden" style=" max-width:30rem; position:relative;">
                          <!--  -->
                        <?php 
                        
                          if($roleName == 'Administrator' && $vehicle['status'] != 'sold' )
                          {
                          ?>
                       
                            <a href="view-all-vehicles.php?delid=<?= $vehicle['vehicle_id'] ?>" onclick="return confirm('Are you sure to Delete <?= htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) ?>?')" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" style="z-index: 10;">
                                <i class="fas fa-trash"></i></a>
                          <?php }?>
                          <a href="view-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>">
                            <?php if (isset($featuredImage['image_path'])) { ?>
                              <img class="card-img-top" style="height: 200px; object-fit: cover; object-position: center;"
                                src="<?= $URL ?>/<?= $featuredImage['image_path'] ?>" alt="...">
                            <?php } else { ?>
                              <img class="card-img-top" src="uploads/vehicles/default-car.jpg" alt="...">
                            <?php } ?>


                            <div class="card-img-overlay d-flex align-items-end">
                              
                              <div>
                                <!-- <h4 class="card-title text-white">Card title</h4> -->
                                <span class="badge badge-phoenix badge-phoenix-primary fs-8"><b><?= htmlspecialchars($vehicle['regnumber']) ?></b></span>
                                
                                

                              </div>

                            </div>


                          </a>
                        </div>
                        <div class="card-body p-2">
                          <a href="view-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>">
                            <h5 class="card-title mb-2">
                              <?= htmlspecialchars($vehicle['make'] ?? '') ?> <?= htmlspecialchars($vehicle['model'] ?? '') ?> <?= htmlspecialchars($vehicle['engine_size'] ?? '') ?> <?= htmlspecialchars($vehicle['trim'] ?? '') ?> <?= htmlspecialchars($vehicle['body_style'] ?? '') ?>
                            </h5>
                          </a>
                          <p class="card-text text-body-quaternary" style="padding-top:0px; font-size: small;">
                            <i class="fas fa-tachometer-alt"></i> : <?= htmlspecialchars($vehicle['mileage']) ?> &nbsp;<i class="fas fa-users"></i> : 4 &nbsp;<i class="fas fa-key"></i> : <?= htmlspecialchars($vehicle['keys_count'] ?? '') ?> &nbsp;V5 : <?php if ($vehicle['v5_status'] == "Present") {
                                                                                                                                                                                                                                                        echo ('<i class="fas fa-check-circle"></i>');
                                                                                                                                                                                                                                                      } else {
                                                                                                                                                                                                                                                        echo ('<i class="fas fa-times-circle"></i>');
                                                                                                                                                                                                                                                      } ?> &nbsp;SH : <?= htmlspecialchars($vehicle['number_of_services'] ?? '') ?>
                            <span style="float:right;" class="badge badge-phoenix fs-10 badge-phoenix-<?php
                                                                                                      switch ($vehicle['status']) {
                                                                                                        case 'purchased':
                                                                                                          echo 'warning';
                                                                                                          break;
                                                                                                        case 'sold':
                                                                                                          echo 'success';
                                                                                                          break;
                                                                                                        case 'available':
                                                                                                          echo 'primary';
                                                                                                          break;
                                                                                                        case 'part_exchange':
                                                                                                          echo 'warning';
                                                                                                          break;
                                                                                                        case 'in_preparation':
                                                                                                          echo 'danger';
                                                                                                          break;
                                                                                                        default:
                                                                                                          echo 'info';
                                                                                                      }
                                                                                                      ?>
                                        ">
                              <?= ucfirst($vehicle['status']) ?>
                            </span>
                          </p>
                        </div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>
              <div class="tab-pane fade" id="tab-profile" role="tabpanel" aria-labelledby="profile-tab">
                <hr class="bg-body-secondary" />
                <div class="row flex-between-center mb-4 g-3">
                  <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;make&quot;,&quot;model&quot;,&quot;year&quot;,&quot;mileage&quot;,&quot;price&quot;,&quot;status&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                    <div class="row">
                      <div class="col-8">
                        <h3>All Vehicles</h3>
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
                            <th class="sort border-top border-translucent ps-3" data-sort="make">Make</th>
                            <th class="sort border-top " data-sort="model">Model</th>
                            <th class="sort border-top " data-sort="year">Year</th>
                            <th class="sort border-top" data-sort="mileage">Mileage</th>
                            <th class="sort border-top" data-sort="price">Purchase Price</th>
                            <th class="sort border-top" data-sort="status">Status</th>
                            <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                          </tr>
                        </thead>
                        <tbody class="list">
                          <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                              <td class="align-middle ps-3 id"><?= $vehicle['vehicle_id'] ?></td>
                              <td class="align-left make"><?= htmlspecialchars($vehicle['make']) ?></td>
                              <td class="align-left model"> <?= htmlspecialchars($vehicle['model']) ?></td>
                              <td class="align-left year"><?= $vehicle['year'] ?></td>
                              <td class="align-left mileage"><?= number_format($vehicle['mileage']) ?></td>
                              <td class="align-left price">£<?= number_format($vehicle['purchase_price'], 2) ?></td>
                              <td class="allign-middle status">
                                <span class="badge badge-phoenix fs-10 badge-phoenix-<?php
                                                                                      switch ($vehicle['status']) {
                                                                                        case 'purchased':
                                                                                          echo 'warning';
                                                                                          break;
                                                                                        case 'sold':
                                                                                          echo 'success';
                                                                                          break;
                                                                                        case 'available':
                                                                                          echo 'primary';
                                                                                          break;
                                                                                        case 'part_exchange':
                                                                                          echo 'warning';
                                                                                          break;
                                                                                        case 'in_preparation':
                                                                                          echo 'danger';
                                                                                          break;
                                                                                        case 'Reserved':
                                                                                          echo 'danger'; 
                                                                                          break;
                                                                                        default:
                                                                                          echo 'info';
                                                                                          break;
                                                                                      }
                                                                                      ?>
                                        ">
                                  <?= ucfirst($vehicle['status']) ?>
                                </span>
                              </td>
                              <td class="text-end align-middle">
                                <?php if ($vehicle['sale_date']): ?>
                                  <a class="btn btn-sm btn-danger" href="sale-invoice.php?inv=<?= $vehicle['invoice_no'] ?>"><i class="fas fa-receipt"></i> Invoice</a>
                                  <!-- <button onclick="printReceipt(<?= $vehicle['vehicle_id'] ?>)"
                                    class="btn btn-sm btn-danger">
                                    <i class="fas fa-receipt"></i> Receipt
                                  </button>
                                  <script>
                                    function printReceipt(vehicleId) {
                                      // Open receipt in perfectly sized window
                                      const receiptWindow = window.open(
                                        `sale-invoice.php?id=${vehicleId}`,
                                        'Receipt',
                                        'width=800,height=700,toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no'
                                      );

                                      // Focus the new window
                                      receiptWindow.focus();
                                    }
                                  </script> -->

                                <?php endif; ?>
                                <a href="view-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit-vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
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


                </div>
              </div>
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('cardSearchInput');
      const cards = document.querySelectorAll('#tab-home .col-md-3');

      if (searchInput) {
        searchInput.addEventListener('keyup', function() {
          const searchTerm = this.value.toLowerCase().trim();

          cards.forEach(card => {
            // Get registration number
            const regElement = card.querySelector('.badge-phoenix-primary b');
            const regNumber = regElement ? regElement.textContent.toLowerCase() : '';

            // Get make and model from card title
            const titleElement = card.querySelector('.card-title');
            const makeModel = titleElement ? titleElement.textContent.toLowerCase() : '';

            // Check if search term matches reg number OR make/model
            const matchesReg = regNumber.includes(searchTerm);
            const matchesMakeModel = makeModel.includes(searchTerm);

            if (matchesReg || matchesMakeModel) {
              card.style.display = '';
            } else {
              card.style.display = 'none';
            }
          });

          // Show/hide "no results" message
          let visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
          let noResultsMsg = document.getElementById('noResultsMessage');

          if (visibleCards.length === 0) {
            if (!noResultsMsg) {
              noResultsMsg = document.createElement('div');
              noResultsMsg.id = 'noResultsMessage';
              noResultsMsg.className = 'col-12 text-center py-5';
              noResultsMsg.innerHTML = '<p class="text-muted">No vehicles found matching your search.</p>';
              document.querySelector('#tab-home .row:last-child').appendChild(noResultsMsg);
            }
          } else {
            if (noResultsMsg) {
              noResultsMsg.remove();
            }
          }
        });
      }
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
  <script src="vendors/echarts/echarts.min.js"></script>
  <script src="assets/js/ecommerce-dashboard.js"></script>
</body>


</html>