<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "View vehicle";
$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Get vehicle ID from URL
$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($vehicleId <= 0) {
  header("Location: view-all-vehicles.php");
  exit;
}

// Get vehicle details
$stmt = $db->prepare("
    SELECT v.*, a.auction_name,a.city, u.full_name as created_by_name, jc.job_card_id,jc.job_card_number,jc.selected_tasks, jc.custom_tasks,
    s.sale_price,s.sale_date,s.net_profit,s.total_cost,s.total_revenue
    FROM vehicles v
    LEFT JOIN auctions a ON v.auction_id = a.auction_id
    LEFT JOIN users u ON v.created_by = u.id
    LEFT JOIN job_cards jc ON v.vehicle_id = jc.vehicle_id
    LEFT JOIN sales s ON v.vehicle_id = s.vehicle_id
    WHERE v.vehicle_id = ?
");
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
  setError('Vehicle not found');
  header("Location: view-all-vehicles.php");
  exit;
}

// Get all costs from job_card_task_progress (both repairs and expenses)
$allCosts = $db->prepare("
    SELECT 
        jctp.*, 
        st.task_name,
        st.task_type,
        jc.job_card_number
    FROM job_card_task_progress jctp
    JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
    LEFT JOIN standard_tasks st ON jctp.task_id = st.task_id
    WHERE jc.vehicle_id = ? AND jctp.cost IS NOT NULL AND jctp.cost > 0
    ORDER BY jctp.created_at DESC
");
$allCosts->execute([$vehicleId]);
$allCosts = $allCosts->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals by task type
$totalRepairs = 0;
$totalExpenses = 0;

foreach ($allCosts as $cost) {
  if ($cost['task_type'] === 'repair') {
    $totalRepairs += $cost['cost'];
  } else {
    $totalExpenses += $cost['cost'];
  }
}

$totalInvestment = $vehicle['purchase_price'] + $totalRepairs + $totalExpenses;

$profit = $vehicle['net_profit'] ?? null;

// Pending Tasks by Vehicle
$pendingTasksQuery = $db->prepare("
    SELECT 
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        COUNT(jctp.task_id) AS pending_tasks,
        GROUP_CONCAT(DISTINCT st.task_name SEPARATOR ', ') AS task_names

    FROM vehicles v

    JOIN job_cards jc 
        ON v.vehicle_id = jc.vehicle_id

    JOIN job_card_task_progress jctp 
        ON jc.job_card_id = jctp.job_card_id

    JOIN standard_tasks st 
        ON jctp.task_id = st.task_id

    WHERE jc.status IN ('Pending', 'In Progress')
    AND jctp.status IN ('pending', 'in_progress')
    AND v.vehicle_id = ?

    GROUP BY 
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber

    ORDER BY pending_tasks DESC

    LIMIT 8
");
$pendingTasksQuery->execute([$vehicleId]);
$pendingTasks = $pendingTasksQuery->fetchAll(PDO::FETCH_ASSOC);
//include '../includes/header.php';
// Get standard tasks for reference
$standard_tasks = [];
$task_sql = "SELECT task_id, task_name FROM standard_tasks WHERE task_status = 'active'";
$task_stmt = $db->query($task_sql);
$standard_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process selected tasks
$selected_task_names = [];
if (!empty($vehicle['selected_tasks'])) {
  $selected_task_ids = explode(',', $vehicle['selected_tasks']);
  //print_r($selected_task_ids);
  //    die;
  foreach ($selected_task_ids as $task_id) {
    foreach ($standard_tasks as $task) {
      if ($task['task_id'] == $task_id) {

        $selected_task_names[] = $task['task_name'];
        break;
      }
    }
  }
}

$task_statuses = [];
try {
  $status_sql = "SELECT task_id, status FROM job_card_task_progress 
                   WHERE job_card_id = ?";
  $status_stmt = $db->prepare($status_sql);
  $status_stmt->execute([$vehicle['job_card_id']]);
  $task_status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

  // Convert to task_id => status mapping
  foreach ($task_status_data as $task_status) {
    $task_statuses[$task_status['task_id']] = $task_status['status'];
  }
} catch (PDOException $e) {
  // If table doesn't exist yet, initialize empty array
  $task_statuses = [];
}

$current_date = date('Y-m-d H:i:s');
try {
  $status_sql = "SELECT COUNT(*) as total_pending_tasks 
FROM job_card_task_progress 
WHERE status = 'pending' AND job_card_id = ?;";
  $status_stmt = $db->prepare($status_sql);
  $status_stmt->execute([$vehicle['job_card_id']]);
  $task_status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);

  // $taskCount = $task_status_data['total_pending_tasks'];
  // if ($taskCount == 0) {
  //   $sql = "UPDATE job_cards 
  //             SET status = 'Completed' , date_completed = ? 
  //             WHERE job_card_id = ?";
  //   $stmt = $db->prepare($sql);
  //   $stmt->execute(array($current_date, $vehicle['job_card_id']));

  //   $vehicle_sql = "UPDATE vehicles 
  //             SET status = 'available' 
  //             WHERE vehicle_id = ?";
  //   $stmt1 = $db->prepare($vehicle_sql);
  //   $stmt1->execute(array($vehicle['vehicle_id']));
  // }
} catch (PDOException $e) {
  // If table doesn't exist yet, initialize empty array
  //$task_statuses = [];
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
  <link href="vendors/swiper/swiper-bundle.min.css" rel="stylesheet" />

  <style>
    /* Custom CSS for Vehicle View */
    .vehicle-detail-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
      margin-bottom: 24px;
      overflow: hidden;
    }

    .vehicle-detail-card:hover {
      transform: translateY(-2px);
    }

    .vehicle-detail-card .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-bottom: none;
      padding: 1.25rem 1.5rem;
    }

    .vehicle-detail-card .card-body {
      padding: 1.5rem;
    }

    .vehicle-badge {
      font-size: 0.75rem;
      padding: 0.35rem 0.75rem;
      border-radius: 50px;
      font-weight: 600;
    }

    .badge-sold {
      background-color: #198754;
    }

    .badge-available {
      background-color: #0dcaf0;
    }

    .badge-purchased {
      background-color: #ffc107;
      color: #000;
    }

    .badge-in-progress {
      background-color: #fd7e14;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
    }

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 0.75rem 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      color: #6c757d;
      font-weight: 600;
      font-size: 0.875rem;
    }

    .info-value {
      color: #212529;
      text-align: right;
      font-weight: 500;
      max-width: 60%;
      word-break: break-word;
    }

    .financial-box {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 10px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .financial-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .financial-total {
      font-size: 1.25rem;
      font-weight: 700;
      color: #2c3e50;
      margin-top: 1rem;
    }

    .profit-badge {
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 700;
      display: inline-block;
    }

    .profit-positive {
      background-color: rgba(25, 135, 84, 0.15);
      color: #198754;
    }

    .profit-negative {
      background-color: rgba(220, 53, 69, 0.15);
      color: #dc3545;
    }

    .image-slider-container {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .task-list-container {
      max-height: 400px;
      overflow-y: auto;
    }

    .task-item {
      padding: 0.75rem;
      border-radius: 8px;
      margin-bottom: 0.5rem;
      border-left: 4px solid #dee2e6;
    }

    .task-pending {
      border-left-color: #ffc107;
      background-color: rgba(255, 193, 7, 0.05);
    }

    .task-completed {
      border-left-color: #198754;
      background-color: rgba(25, 135, 84, 0.05);
    }

    .task-in-progress {
      border-left-color: #0dcaf0;
      background-color: rgba(13, 202, 240, 0.05);
    }

    .task-cancelled {
      border-left-color: #dc3545;
      background-color: rgba(220, 53, 69, 0.05);
    }

    .cost-table {
      font-size: 0.875rem;
    }

    .cost-table th {
      font-weight: 600;
      color: #6c757d;
      background-color: #f8f9fa;
    }

    .cost-table td {
      vertical-align: middle;
    }

    .mobile-actions {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: white;
      padding: 1rem;
      box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      display: none;
    }

    @media (max-width: 768px) {
      .mobile-actions {
        display: flex;
        gap: 0.5rem;
      }

      .vehicle-detail-card .card-body {
        padding: 1rem;
      }

      .info-grid {
        grid-template-columns: 1fr;
      }

      .info-item {
        flex-direction: column;
        gap: 0.25rem;
      }

      .info-value {
        text-align: left;
        max-width: 100%;
      }

      .image-slider-container {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        border-radius: 0;
      }

      .swiper-nav {
        display: none;
      }
    }

    @media (max-width: 576px) {
      .vehicle-detail-card .card-header h5 {
        font-size: 1.25rem;
      }

      .btn-group-responsive {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .btn-group-responsive .btn {
        flex: 1;
        min-width: 120px;
      }
    }

    .section-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .section-title i {
      color: #667eea;
    }

    .action-buttons {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .whatsapp-share-btn {
      background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
      border: none;
      color: white;
    }

    .whatsapp-share-btn:hover {
      background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
      color: white;
      transform: translateY(-2px);
    }

    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }
  </style>
  <link href="custom.css" rel="stylesheet" />

</head>

<body>
  <!-- ===============================================-->
  <!--    Main Content-->
  <!-- ===============================================-->
  <main class="main" id="top">
    <!-- Left Menu -->
    <?php include("left-menu.php"); ?>
    <!-- Page Content -->

    <!-- Page Content -->
    <div class="content">
    <?php displayMessage(); ?>
      <div class="container-fluid py-4">

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="h3 mb-2">Vehicle Details</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="list.php">Vehicles</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></li>
              </ol>
            </nav>
          </div>
          <div class="d-none d-md-flex action-buttons">
            <button id="whatsappShareBtn" class="btn whatsapp-share-btn">
              <i class="fab fa-whatsapp me-2"></i>Share
            </button>
            <a href="view-all-vehicles.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
          </div>
        </div>

        <!-- Vehicle Header Info -->
        <div class="row mb-4">

          <div class=" quick-actions-responsive">

            <!-- Vehicles -->
            <a href="view-all-vehicles.php" class="quick-action-item">
              <div class="action-icon bg-success">
                <i class="fas fa-car"></i>
              </div>
              <span class="action-label">Vehicles</span>
            </a>

            <!-- Add Job Card -->
            <a href="add-new-job-card.php?id=<?= $vehicleId ?>" class="quick-action-item">
              <div class="action-icon bg-danger">
                <i class="far fa-clipboard"></i>
              </div>
              <span class="action-label">Add Job Card</span>
            </a>

            <!-- Add Vehicle -->
            <a href="add-vehicle.php" class="quick-action-item">
              <div class="action-icon bg-primary">
                <i class="fas fa-plus-circle"></i>
              </div>
              <span class="action-label">Add Vehicle</span>
            </a>

            <!-- Add Expense -->
            <!-- <a href="add-expense.php?id=<?= $vehicleId ?>" class="quick-action-item">
              <div class="action-icon bg-secondary">
                <i class="fas fa-money-bill"></i>
              </div>
              <span class="action-label">Add Expense</span>
            </a> -->

            <!-- Add Repair -->
            <!-- <a href="add-repair.php?id=<?= $vehicleId ?>" class="quick-action-item">
              <div class="action-icon bg-warning">
                <i class="fas fa-wrench"></i>
              </div>
              <span class="action-label">Add Repair</span>
            </a> -->


            <!-- Job Cards -->
            <a href="job-cards.php" class="quick-action-item">
              <div class="action-icon bg-purple">
                <i class="fas fa-clipboard-list"></i>
              </div>
              <span class="action-label">Job Cards</span>
            </a>
            <!-- Specs Sheet -->
            <a href="print-vehicle.php?id=<?= $vehicleId ?>" class="quick-action-item">
              <div class="action-icon bg-orange">
                <i class="fas fa-print"></i>
              </div>
              <span class="action-label">Specs Sheet</span>
            </a>



            <?php
            $inactive_statuses = ["reserved","sold", "refunded_to_customer"];
            if (!in_array($vehicle['status'], $inactive_statuses)):
            ?>
              <a href="sale-vehicle.php?id=<?= $vehicleId ?>" class="quick-action-item">
                <div class="action-icon bg-danger">
                  <i class="fas fa-tag"></i>
                </div>
                <span class="action-label">Mark as Sold</span>
              </a>
              <a href="edit-vehicle.php?id=<?= $vehicleId ?>" class="quick-action-item">
                <div class="action-icon bg-primary">
                  <i class="fas fa-edit"></i>
                </div>
                <span class="action-label">Edit</span>
              </a>
            <?php endif; ?>
          </div>


          <div class="col-12">
            <div class="card ">
              <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                  <h3 class="mb-1">
                    <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?>
                    <span class="badge vehicle-badge badge-<?= $vehicle['status'] ?> ms-4">
                      <?= ucfirst($vehicle['status']) ?>
                    </span>
                  </h3>
                  <p class="mb-0"><?= $vehicle['year'] ?> • <?= htmlspecialchars($vehicle['regnumber']) ?> • VIN: <?= htmlspecialchars($vehicle['VIN']) ?></p>
                </div>
                <div class="text-end d-none d-md-block">
                  <div class="financial-total text-danger">
                    Total Investment: £<?= number_format($totalInvestment, 2) ?>
                  </div>
                  <?php if ($vehicle['sale_date']): ?>
                    <div class="fs-7 badge badge-phoenix <?= $profit >= 0 ? 'badge-phoenix-success' : 'badge-phoenix-danger' ?> mt-2">
                      <?= $profit >= 0 ? 'Profit' : 'Loss' ?>: £<?= number_format(abs($profit), 2) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
          <!-- Left Column - Images and Tasks -->
          <div class="col-lg-8">
            <!-- Image Gallery -->
            <?php
            $images = $db->prepare("
                  SELECT * FROM vehicle_images 
                  WHERE vehicle_id = ?
                  ORDER BY is_primary DESC, uploaded_at ASC
                ");
            $images->execute([$vehicleId]);
            $images = $images->fetchAll(PDO::FETCH_ASSOC);

            if ($images): ?>
              <div class="card mb-4">
                <div class="card-header">
                  <h5 class="mb-0"><i class="fas fa-images me-2"></i>Vehicle Images</h5>
                </div>
                <div class="card-body p-0">

                  <div class="image-slider-container">
                    <div class="swiper theme-slider" data-swiper='{"spaceBetween":8,"loop":true,"loopedSlides":5,"navigation":{"nextEl":".swiper-button-next","prevEl":".swiper-button-prev"},"thumb":{"spaceBetween":8,"slidesPerView":5,"loop":true,"freeMode":true,"grabCursor":true,"loopedSlides":5,"centeredSlides":true,"slideToClickedSlide":true,"watchSlidesVisibility":true,"watchSlidesProgress":true},"slideToClickedSlide":true}'>
                      <div class="swiper-wrapper">
                        <?php foreach ($images as $image): ?>
                          <div class="swiper-slide">
                            <img class="img-fluid w-100" src="<?= htmlspecialchars($image['image_path']) ?>" alt="Vehicle Image" style="height: 400px; object-fit: cover;">
                          </div>
                        <?php endforeach ?>
                      </div>
                    </div>
                    <div class="swiper-nav">
                      <div class="swiper-button-next"><span class="fas fa-chevron-right"></span></div>
                      <div class="swiper-button-prev"><span class="fas fa-chevron-left"></span></div>
                    </div>
                  </div>

                </div>
              </div>

            <?php endif; ?>
            <!-- Vehicle Information -->
            <div class="card  mb-4">
              <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Vehicle Information</h5>
              </div>
              <div class="card-body">
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">Make</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['make']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Model</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['model']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Year</span>
                    <span class="info-value"><?= $vehicle['year'] ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Trim</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['trim']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">VIN / Chassis</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['VIN']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Engine #</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['engine_no']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Registration</span>
                    <span class="info-value text-danger fw-bold"><?= htmlspecialchars($vehicle['regnumber']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Color</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['color']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Body Style</span>
                    <span class="info-value"><?= ucfirst($vehicle['body_style']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Transmission</span>
                    <span class="info-value"><?= ucfirst($vehicle['transmission']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Fuel Type</span>
                    <span class="info-value"><?= ucfirst($vehicle['fuel_type']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Mileage</span>
                    <span class="info-value"><?= number_format($vehicle['mileage']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Engine Size</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['engine_size']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Owners</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['owners']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Keys</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['keys_count']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Logbook</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['logbook_status']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">MOT Expiry</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['mot_expiry']) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">No. of Services</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['number_of_services']) ?: 'N/A' ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">V5 Status</span>
                    <span class="info-value"><?= $vehicle['v5_status'] ?></span>
                  </div>
                  <?php if ($vehicle['notes']): ?>
                    <div class="info-item">
                      <span class="info-label">Notes</span>
                      <span class="info-value"><?= htmlspecialchars($vehicle['notes']) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Quick Add Costs -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-plus-circle me-2"></i>
            Quick Add Cost
        </h5>
    </div>

    <div class="card-body">
        <form action="save-quick-cost.php" method="POST">

            <input type="hidden" name="vehicle_id" value="<?= $vehicle['vehicle_id'] ?>">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Task Type</label>
                    <select name="task_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="repair">Repair</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Task Name</label>
                    <input type="text"
                           name="task_name"
                           class="form-control"
                           placeholder="e.g. Brake Pads, MOT, Fuel"
                           required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Cost (£)</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           name="cost"
                           class="form-control"
                           required>
                </div>

                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="description"
                              class="form-control"
                              rows="3"
                              placeholder="Optional notes"></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Add Cost
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>
            <!-- Costs & Expenses -->
            <div class="card ">
              <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Costs & Expenses</h5>
              </div>
              <div class="card-body">
                <div class="financial-box mb-4">
                  <div class="financial-item">
                    <span>Purchase Price</span>
                    <span class="fw-bold">£<?= number_format($vehicle['purchase_price'], 2) ?></span>
                  </div>
                  <div class="financial-item">
                    <span>Repair Costs</span>
                    <span class="fw-bold">£<?= number_format($totalRepairs, 2) ?></span>
                  </div>
                  <div class="financial-item">
                    <span>Other Expenses</span>
                    <span class="fw-bold">£<?= number_format($totalExpenses, 2) ?></span>
                  </div>
                  <div class="financial-item financial-total">
                    <span>Total Investment</span>
                    <span>£<?= number_format($totalInvestment, 2) ?></span>
                  </div>
                  <?php if ($vehicle['sale_date']): ?>
                    <div class="financial-item">
                      <span>Sale Price</span>
                      <span class="fw-bold">£<?= number_format($vehicle['sale_price'], 2) ?></span>
                    </div>
                    <div class="financial-item financial-total">
                      <span>Profit/Loss</span>
                      <span class="profit-badge <?= $profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                        £<?= number_format($profit, 2) ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if ($allCosts): ?>
                  <div class="table-responsive">
                    <table class="table cost-table">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Date</th>
                          <th>Type</th>
                          <th>Description</th>
                          <th>Cost</th>
                          <th>Status</th>
                          <th>Job Card</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($allCosts as $cost): ?>
                          <tr>
                            <td><?= $cost['task_id'] ?></td>
                            <td><?= date('M j, Y', strtotime($cost['created_at'])) ?></td>
                            <td>
                              <span class="badge bg-<?= $cost['task_type'] === 'repair' ? 'warning' : 'info' ?>">
                                <?= ucfirst($cost['task_type']) ?>
                              </span>
                            </td>
                            <td>
                              <?= htmlspecialchars($cost['task_name']) ?: 'Custom Task' ?>
                              <?php if (!empty($cost['description'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($cost['description']) ?></small>
                              <?php endif; ?>
                            </td>
                            <td class="fw-bold">£<?= number_format($cost['cost'], 2) ?></td>
                            <td>
                              <span class="badge bg-<?=
                                                    $cost['status'] === 'completed' ? 'success' : ($cost['status'] === 'in_progress' ? 'warning' : 'secondary')
                                                    ?>">
                                <?= ucfirst(str_replace('_', ' ', $cost['status'])) ?>
                              </span>
                            </td>
                            <td>
                              <a href="view-job-card.php?jc=<?= htmlspecialchars($cost['job_card_number']) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($cost['job_card_number']) ?>
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                        <tr class="table-dark">
                          <td colspan="4" class="text-end">Total:</td>
                          <td class="fw-bold">£<?= number_format($totalRepairs + $totalExpenses, 2) ?></td>
                          <td colspan="2"></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="empty-state">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <p class="mb-0">No costs recorded for this vehicle</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Right Column - Tasks and Financial Info -->
          <div class="col-lg-4">
            <!-- Tasks -->
            <div class="card  mb-4">
              <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Tasks</h5>
              </div>
              <div class="card-body">
                <?php if (!empty($pendingTasks)): ?>
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h6 class="mb-0">Job Card #<?= htmlspecialchars($vehicle['job_card_number']) ?></h6>
                      <a href="view-job-card.php?jc=<?= htmlspecialchars($vehicle['job_card_number']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt"></i>
                      </a>
                    </div>

                    <div class="task-list-container">
                      <?php foreach ($selected_task_ids as $index => $task_id):
                        $task_status = $task_statuses[$task_id] ?? 'pending';
                        $status_class = '';
                        $status_text = '';

                        switch ($task_status) {
                          case 'completed':
                            $status_class = 'task-completed';
                            $status_text = 'Completed';
                            break;
                          case 'in_progress':
                            $status_class = 'task-in-progress';
                            $status_text = 'In Progress';
                            break;
                          case 'cancelled':
                            $status_class = 'task-cancelled';
                            $status_text = 'Cancelled';
                            break;
                          default:
                            $status_class = 'task-pending';
                            $status_text = 'Pending';
                        }
                      ?>
                        <div class="task-item <?= $status_class ?>">
                          <div class="d-flex justify-content-between align-items-center">
                            <div class="fw-medium"><?= $selected_task_names[$index] ?></div>
                            <span class="badge bg-<?=
                                                  $task_status === 'completed' ? 'success' : ($task_status === 'in_progress' ? 'warning' : ($task_status === 'cancelled' ? 'danger' : 'secondary'))
                                                  ?>">
                              <?= $status_text ?>
                            </span>
                          </div>
                          <?php if ($selected_task_names[$index] == "Car Collection" && isset($vehicle['auction_name'])): ?>
                            <small class="text-muted">from <?= htmlspecialchars($vehicle['auction_name']) ?> - <?= htmlspecialchars($vehicle['city']) ?></small>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>

                      <?php if ($vehicle['custom_tasks']): ?>
                        <div class="task-item task-pending">
                          <div class="d-flex justify-content-between align-items-center">
                            <div class="fw-medium">Custom Task</div>
                            <span class="badge bg-secondary">Pending</span>
                          </div>
                          <small class="text-muted"><?= htmlspecialchars($vehicle['custom_tasks']) ?></small>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p class="mb-0">No tasks assigned</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Financial Information -->
            <div class="card ">
              <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Financial Summary</h5>
              </div>
              <div class="card-body">
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">Purchase Date</span>
                    <span class="info-value"><?= date('M j, Y', strtotime($vehicle['purchase_date'])) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Purchase Price</span>
                    <span class="info-value fw-bold">£<?= number_format($vehicle['purchase_price'], 2) ?></span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Auction</span>
                    <span class="info-value"><?= htmlspecialchars($vehicle['auction_name']) ?: 'N/A' ?></span>
                  </div>

                  <?php if ($vehicle['sale_date']): ?>
                    <div class="info-item">
                      <span class="info-label">Sale Date</span>
                      <span class="info-value"><?= date('M j, Y', strtotime($vehicle['sale_date'])) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Sale Price</span>
                      <span class="info-value fw-bold">£<?= number_format($vehicle['sale_price'], 2) ?></span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Profit/Loss</span>
                      <span class="info-value fw-bold <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                        £<?= number_format($profit, 2) ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <div class="info-item">
                      <span class="info-label">Current Status</span>
                      <span class="info-value">
                        <span class="badge vehicle-badge badge-<?= $vehicle['status'] ?>">
                          <?= ucfirst($vehicle['status']) ?>
                        </span>
                      </span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Total Investment</span>
                      <span class="info-value fw-bold">£<?= number_format($totalInvestment, 2) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Created By -->
            <div class="card vehicle-detail-card mt-4">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                      <div class="avatar avatar-xl">
                        <div class="avatar-name rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                          <span><?= strtoupper(substr($vehicle['created_by_name'], 0, 1)) ?></span>
                        </div>
                      </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                      <h6 class="mb-1">Created By</h6>
                      <p class="mb-0"><?= htmlspecialchars($vehicle['created_by_name']) ?></p>
                    </div>
                  </div>

                  <div class="text-end">
                    <h6 class="mb-1">Created at</h6>
                    <small class="mb-0 text-danger">
                      <?php
                      // Format the date nicely
                      $date = new DateTime($vehicle['created_at']);
                      echo $date->format('d M Y, h:i A');
                      ?>
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile Action Buttons -->
    <div class="mobile-actions">
      <div class="container-fluid">
        <div class="btn-group-responsive">
          <button id="whatsappShareBtnMobile" class="btn whatsapp-share-btn btn-sm">
            <i class="fab fa-whatsapp"></i>
          </button>
          <?php if ($vehicle['status'] != "sold"): ?>
            <a href="sale-vehicle.php?id=<?= $vehicleId ?>" class="btn btn-warning btn-sm">
              <i class="fas fa-tag"></i>
            </a>
            <a href="edit-vehicle.php?id=<?= $vehicleId ?>" class="btn btn-primary btn-sm">
              <i class="fas fa-edit"></i>
            </a>
          <?php endif; ?>
          <a href="view-all-vehicles.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i>
          </a>
        </div>
      </div>
      <?php include_once("footer.php"); ?>

    </div>

    <!-- Footer -->


  </main>
  <!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->
  <script>
    // WhatsApp Share Function
    function shareOnWhatsApp() {
      const vehicle = {
        make: '<?= addslashes($vehicle["make"]) ?>',
        model: '<?= addslashes($vehicle["model"]) ?>',
        year: '<?= $vehicle["year"] ?>',
        price: '<?= isset($vehicle["sale_price"]) ? number_format($vehicle["sale_price"], 2) : number_format($totalInvestment, 2) ?>',
        mileage: '<?= number_format($vehicle["mileage"]) ?>',
        color: '<?= addslashes($vehicle["color"]) ?>',
        transmission: '<?= ucfirst($vehicle["transmission"]) ?>',
        fuel: '<?= ucfirst($vehicle["fuel_type"]) ?>',
        reg: '<?= $vehicle["regnumber"] ?>',
        vin: '<?= $vehicle["VIN"] ?>'
      };

      let message = `🚗 *${vehicle.year} ${vehicle.make} ${vehicle.model}*\n\n`;
      message += `📌 *Registration:* ${vehicle.reg}\n`;
      message += `💰 *Price:* £${vehicle.price}\n`;
      message += `🛣️ *Mileage:* ${vehicle.mileage}\n`;
      message += `🎨 *Color:* ${vehicle.color}\n`;
      message += `⚙️ *Transmission:* ${vehicle.transmission}\n`;
      message += `⛽ *Fuel Type:* ${vehicle.fuel}\n`;
      message += `🔢 *VIN:* ${vehicle.vin}\n\n`;
      message += `View full details at your Vehicle Management System.`;

      const encodedMessage = encodeURIComponent(message);
      window.open(`https://wa.me/?text=${encodedMessage}`, '_blank');
    }

    // Attach event listeners
    document.getElementById('whatsappShareBtn')?.addEventListener('click', shareOnWhatsApp);
    document.getElementById('whatsappShareBtnMobile')?.addEventListener('click', shareOnWhatsApp);
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
  <script src="vendors/swiper/swiper-bundle.min.js"></script>

</body>


</html>