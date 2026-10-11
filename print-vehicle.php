<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Specs Sheet";
$user = $auth->getUser();
// $roleName = $auth->getRoleName();

// Get vehicle ID from URL
$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$specSheetPrefs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_spec_prefs'])) {
  $specSheetPrefs = $_POST['spec_fields'] ?? [];
  $_SESSION['spec_sheet_preferences'] = $specSheetPrefs;
  setcookie('spec_sheet_prefs', json_encode($specSheetPrefs), time() + 31536000, '/'); // 1 year
  header("Location: view-vehicle.php?id=" . $vehicleId);
  exit;
}

// Load saved preferences
if (isset($_SESSION['spec_sheet_preferences'])) {
  $specSheetPrefs = $_SESSION['spec_sheet_preferences'];
} elseif (isset($_COOKIE['spec_sheet_prefs'])) {
  $specSheetPrefs = json_decode($_COOKIE['spec_sheet_prefs'], true) ?: [];
} else {
  // Default: show all fields
  $specSheetPrefs = [];
}

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
        COUNT(jctp.task_id) as pending_tasks,
        GROUP_CONCAT(DISTINCT st.task_name SEPARATOR ', ') as task_names
    FROM vehicles v
    JOIN job_cards jc ON v.vehicle_id = jc.vehicle_id
    JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id
    JOIN standard_tasks st ON jctp.task_id = st.task_id
    WHERE jc.status IN ('Pending', 'In Progress')
    AND jctp.status IN ('pending', 'in_progress')
    AND v.vehicle_id = ?
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
    /* RESET & GLOBAL */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #eef2f5;
      font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      padding: 2rem 1.5rem;
      color: #1e2a3e;
    }

    /* MAIN CONTAINER – SPEC SHEET */
    .spec-sheet {
      max-width: 1280px;
      margin: 0 auto;
      background: white;
      border-radius: 28px;
      box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.15);
      overflow: hidden;
      transition: all 0.2s;
    }

    /* PRINT STYLES – crisp, minimal, high readability */
    @media print {
      body {
        background: white;
        padding: 0;
        margin: 0;
      }

      .spec-sheet {
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
      }

      .no-print {
        display: none !important;
      }

      .action-bar,
      .print-hide-btn,
      .badge-status,
      .footer-note {
        display: none;
      }

      .vehicle-header h1 {
        font-size: 28pt;
      }

      .info-grid-item {
        break-inside: avoid;
        page-break-inside: avoid;
      }

      .cost-breakdown,
      .financial-summary {
        break-inside: avoid;
      }

      .vehicle-image-area img {
        max-height: 280px;
        object-fit: contain;
      }
    }

    /* header section – showroom style */
    .vehicle-header {
      background: linear-gradient(135deg, #999999 0%, #cccccc 100%);
      /* color: white; */
      padding: 0.8rem 0.5rem;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
    }

    .title-section h1 {
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.3px;
      margin-bottom: 0.4rem;
    }

    .title-section .reg-badge {
      font-family: monospace;
      background: rgba(255, 255, 255, 0.2);
      display: inline-block;
      padding: 0.2rem 0.9rem;
      border-radius: 40px;
      font-size: 1.15rem;
      font-weight: 500;
      margin-top: 6px;
    }

    .price-tag {
      text-align: right;
    }

    .price-tag .price-label {
      font-size: 0.85rem;
      opacity: 0.8;
      letter-spacing: 0.5px;
    }

    .price-tag .amount {
      font-size: 2.2rem;
      font-weight: 800;
      line-height: 1.2;
    }

    .price-tag .vat-note {
      font-size: 0.7rem;
      opacity: 0.7;
    }

    .badge-status {
      display: inline-block;
      background: #ffb347;
      color: #2c2c2c;
      border-radius: 50px;
      padding: 0.2rem 0.9rem;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 1rem;
      vertical-align: middle;
    }

    /* action bar (print control) */
    .action-bar {
      background: #f8fafc;
      padding: 0.9rem 2rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .btn-print {
      background: #1e293b;
      border: none;
      padding: 0.5rem 1.2rem;
      border-radius: 40px;
      color: white;
      font-weight: 600;
      font-size: 0.8rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
    }

    .btn-print:hover {
      background: #0f172a;
    }

    /* main two column layout */
    .spec-content {
      display: flex;
      flex-wrap: wrap;
    }

    .spec-left {
      flex: 2;
      min-width: 280px;
      padding: 2rem 1.8rem 2rem 2.2rem;
      background: white;
    }

    .spec-right {
      flex: 1.2;
      background: #fefef5;
      padding: 2rem 1.8rem 2rem 1.8rem;
      border-left: 1px solid #e9edf2;
    }

    /* image gallery / main image display */
    .vehicle-image-area {
      margin-bottom: 2rem;
      background: #f9fafb;
      border-radius: 24px;
      overflow: hidden;
      border: 1px solid #eef2f8;
    }

    .main-image {
      width: 100%;
      max-height: 320px;
      object-fit: cover;
      display: block;
      transition: 0.2s;
    }

    .thumbnail-strip {
      display: flex;
      gap: 8px;
      padding: 12px;
      background: white;
      flex-wrap: wrap;
      border-top: 1px solid #eef2f8;
    }

    .thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 10px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: 0.1s;
    }

    .thumb.active {
      border-color: #1e6f5c;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    /* info grids */
    .info-section-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin: 1.5rem 0 1rem 0;
      /* border-left: 5px solid #2c7a6e; */
      padding-left: 12px;
      color: #0f2d38;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 2fr));
      gap: 0.9rem 1.5rem;
      background: #ffffff;
      border-radius: 20px;
      padding: 0.2rem 0;
    }

    .info-grid-item {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      border-bottom: 1px dashed #e2e8f0;
      padding: 0.55rem 0;
    }

    .info-label {
      font-weight: 600;
      color: #2c5f6e;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .info-value {
      font-weight: 500;
      color: #1e2f3d;
      text-align: right;
      font-size: 0.9rem;
    }

    /* financial cards */
    .financial-card {
      background: #ffffff;
      border-radius: 24px;
      padding: 1.2rem;
      margin-bottom: 1.8rem;
      box-shadow: 0 6px 12px -8px rgba(0, 0, 0, 0.05);
      border: 1px solid #eef2fa;
    }

    .financial-row {
      display: flex;
      justify-content: space-between;
      padding: 0.6rem 0;
      border-bottom: 1px solid #eef2f8;
    }

    .financial-row.total {
      font-weight: 800;
      font-size: 1.1rem;
      border-bottom: none;
      margin-top: 0.5rem;
      padding-top: 0.8rem;
      border-top: 2px solid #cfdfe8;
    }

    .profit-loss {
      font-size: 1.3rem;
      font-weight: 800;
      text-align: center;
      background: #f1f9f4;
      border-radius: 40px;
      padding: 0.6rem;
    }

    .text-success {
      color: #1f6e43;
    }

    .text-danger {
      color: #bc5a3c;
    }

    /* tasks block – simplified for customers */
    .tasks-simple {
      background: #ffffffd9;
      border-radius: 20px;
      padding: 0.6rem 0;
    }

    .task-badge {
      font-size: 0.7rem;
      background: #eef2ff;
      display: inline-block;
      padding: 0.2rem 0.7rem;
      border-radius: 50px;
      margin: 0.2rem 0.2rem;
    }

    .condition-note {
      margin-top: 1.2rem;
      font-size: 0.8rem;
      background: #fef7e0;
      padding: 0.8rem;
      border-radius: 16px;
      color: #7a6300;
    }

    /* footer */
    .footer-disclaimer {
      background: #f1f5f9;
      text-align: center;
      font-size: 0.7rem;
      color: #5a6e7c;
      padding: 1rem;
      border-top: 1px solid #dee4ec;
    }

    hr {
      margin: 1rem 0;
    }

    .mobile-flex {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    @media (max-width: 780px) {
      body {
        padding: 0.8rem;
      }

      .vehicle-header {
        flex-direction: column;
        text-align: center;
        gap: 12px;
      }

      .price-tag {
        text-align: center;
      }

      .spec-left,
      .spec-right {
        padding: 1.5rem;
      }

      .info-grid-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }

      .info-value {
        text-align: left;
      }
    }
  </style>
  <style>
    /* SPEC SHEET PREVIEW STYLES */
    .spec-editor-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
      margin-bottom: 2rem;
      overflow: hidden;
    }

    .editor-header {
      /* background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); */
      color: white;
      padding: 1rem 1.5rem;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .editor-header h4 {
      margin: 0;
      font-size: 1rem;
      font-weight: 600;
    }

    .toggle-icon {
      font-size: 1.2rem;
      transition: transform 0.3s;
    }

    .editor-content {
      padding: 1.5rem;
      display: none;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
    }

    .editor-content.open {
      display: block;
    }

    .checkbox-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem;
      background: white;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
    }

    .checkbox-item:hover {
      background: #f1f5f9;
      border-color: #3498db;
    }

    .checkbox-item input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #3498db;
    }

    .checkbox-item label {
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 500;
      color: #2c3e50;
      margin: 0;
    }

    .spec-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #e2e8f0;
    }

    .btn-save-spec {
      background: #3498db;
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-reset-spec {
      background: #e2e8f0;
      color: #2c3e50;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-select-all {
      background: #27ae60;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.8rem;
    }

    /* SPEC SHEET TABLE STYLES */
    .vehicle-specs-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 16px;
      overflow: hidden;
      margin: 0.5rem 0 1rem 0;
      font-size: 0.85rem;
    }

    .vehicle-specs-table tr {
      border-bottom: 1px solid #e9edf2;
    }

    .vehicle-specs-table td {
      padding: 10px 12px;
      vertical-align: top;
    }

    .spec-label {
      font-weight: 700;
      color: #2c5f6e;
      width: 28%;
      background-color: #f9fbfd;
      letter-spacing: 0.3px;
      font-size: 0.8rem;
      text-transform: uppercase;
    }

    .spec-value {
      font-weight: 500;
      color: #1e2f3d;
      width: 72%;
    }

    .hidden-spec-row {
      display: none;
    }

    @media print {
      .spec-editor-container {
        display: none !important;
      }

      .vehicle-specs-table td {
        padding: 8px 10px;
      }

      .hidden-spec-row {
        display: none !important;
      }
    }

    @media (max-width: 768px) {
      .checkbox-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      }
    }
  </style>

  <style>
    @media print {
      body * {
        visibility: hidden;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;

      }

      .content-area,
      .content-area * {
        visibility: visible;
      }

      .content-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }

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
    <div class="content ">
      <?php // displayMessage(); ?>
          <h2 class="mb-2">Specs Sheet for <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></h2>

      <div class="row">

      <div class="col-lg-6 col-sm-12 spec-controls-container">
        <!-- Price Editor Card -->
<div class="card card-body mb-4 ">
    <div class="price-fields">
        <div class="price-field-group">
            <label>💰 Price (£)</label>
            <input type="text" id="priceInput" class="form-control" 
                   placeholder="e.g., £8,995" value="£<?= number_format(($vehicle['sale_price'] ?? $totalInvestment), 0) ?>">
        </div>
    </div>
</div>
          <div class="spec-editor-container no-print">
            <div class="editor-header">
              <h4>
                <i class="fas fa-sliders-h me-2"></i>
                🎨 Customize Spec Sheet - Choose what to display
              </h4>
              <!-- <span class="toggle-icon">▼</span> -->
            </div>

            <div class="editor-content" id="specEditorContent">
              <form method="POST" action="" id="specPrefsForm">
                <div class="checkbox-grid">
                  <?php
                  // Define all spec fields with their display labels and database keys
                  $specFields = [
                    'make_model' => ['label' => '🏭 Make & Model', 'db_field_make' => 'make', 'db_field_model' => 'model'],
                    'year' => ['label' => '📅 Year', 'db_fields' => ['year']],
                    'registration' => ['label' => '📅 Registration', 'db_fields' => ['regnumber']],
                    'mileage' => ['label' => '🛣️ Mileage', 'db_field' => 'mileage'],
                    'transmission' => ['label' => '⚙️ Transmission', 'db_field' => 'transmission'],
                    'fuel_type' => ['label' => '⛽ Fuel Type', 'db_field' => 'fuel_type'],
                    'engine_size' => ['label' => '🔧 Engine Size', 'db_field' => 'engine_size'],
                    'color' => ['label' => '🎨 Color', 'db_field' => 'color'],
                    'body_style' => ['label' => '🚗 Body Style', 'db_field' => 'body_style'],
                    'owners' => ['label' => '👥 Number of Owners', 'db_field' => 'owners'],
                    'mot_expiry' => ['label' => '📄 MOT Expiry', 'db_field' => 'mot_expiry'],
                    'logbook' => ['label' => '📘 Logbook / V5', 'db_field' => 'logbook_status'],
                    'service_history' => ['label' => '🔧 Service History', 'db_field' => 'number_of_services'],
                    'keys' => ['label' => '🔑 Keys', 'db_field' => 'keys_count'],
                    'v5_status' => ['label' => '📋 V5 Status', 'db_field' => 'v5_status'],
                  ];

                  foreach ($specFields as $key => $field):
                    $isChecked = empty($specSheetPrefs) || in_array($key, $specSheetPrefs);
                  ?>
                    <div class="checkbox-item">
                      <input type="checkbox"
                        name="spec_fields[]"
                        value="<?= $key ?>"
                        id="spec_<?= $key ?>"
                        <?= $isChecked ? 'checked' : '' ?>>
                      <label for="spec_<?= $key ?>"><?= $field['label'] ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- <div class="spec-actions">
                  <button type="button" class="btn-select-all" onclick="selectAllSpecs(true)">✓ Select All</button>
                  <button type="button" class="btn-select-all" onclick="selectAllSpecs(false)" style="background:#95a5a6;">✗ Deselect All</button>
                  <button type="reset" class="btn-reset-spec" onclick="resetToDefault()">↺ Reset to Default</button>
                  <button type="submit" name="save_spec_prefs" class="btn-save-spec">💾 Save & Update Preview</button>
                </div> -->
              </form>
            </div>
          </div>
          <!-- FINANCE CARD - Right Column -->
          <!-- FINANCE CARD - Simple Display Only (No Calculations) -->
          <div class="finance-editor-card">
            <div class="finance-header">
              <div class="finance-title-section">
                <i class="fas fa-chart-line"></i>
                <h3 class="text-white">Finance Options</h3>
              </div>
              <label class="finance-toggle">
                <input type="checkbox" id="financeAvailableCheckbox">
                <span class="toggle-slider"></span>
                <span class="toggle-label">Finance Available</span>
              </label>
            </div>

            <div class="finance-fields" id="financeFieldsContainer" style="display: none;">
              <!-- Deposit Amount -->
              <div class="finance-field-group">
                <label>💷 Deposit Amount (£)</label>
                <input type="text" id="depositInput" class="finance-input"
                  placeholder="e.g., £1,000" value="">
              </div>

              <!-- Monthly Installment -->
              <div class="finance-field-group">
                <label>📅 Monthly Installment (£)</label>
                <input type="text" id="monthlyInstallmentInput" class="finance-input"
                  placeholder="e.g., £199 per month" value="">
              </div>
            </div>

            <!-- Finance Preview -->

          </div>

          <!-- Add this updated CSS to replace the old finance styles -->
<style>
/* Finance Card Styles (Left Column Controls) */
.finance-editor-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 20px;
    margin-top: 1.5rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.finance-header {
    background: linear-gradient(135deg, #0f2b3d 0%, #1a4a6f 100%);
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.finance-title-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.finance-title-section i {
    font-size: 1.4rem;
}

.finance-title-section h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Toggle Switch */
.finance-toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.85rem;
}

.finance-toggle input {
    display: none;
}

.toggle-slider {
    position: relative;
    width: 50px;
    height: 24px;
    background-color: #cbd5e1;
    border-radius: 34px;
    transition: 0.3s;
    display: inline-block;
}

.toggle-slider:before {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    border-radius: 50%;
    transition: 0.3s;
}

.finance-toggle input:checked + .toggle-slider {
    background-color: #27ae60;
}

.finance-toggle input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-label {
    font-weight: 500;
}

/* Finance Fields (Left Column) */
.finance-fields {
    padding: 1.5rem;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}

.finance-field-group {
    margin-bottom: 1.2rem;
}

.finance-field-group label {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.finance-input {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.finance-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
}

/* Finance Preview (Right Column) - Hidden by default */
.finance-preview {
    padding: 0.5rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e6f3ff 100%);
    border-top: 2px solid #3498db;
    border-radius: 20px;
    display: none;
}

.preview-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #cbd5e1;
}

.preview-details {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.preview-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #ecf0f1;
    font-size: 0.9rem;
}

.preview-row.total {
    font-weight: 800;
    color: #2c3e50;
    border-bottom: none;
    font-size: 1rem;
    align-items: center;

}

.preview-footer {
    text-align: center;
    font-size: 0.7rem;
    color: #7f8c8d;
}

/* PRINT STYLES - For the preview on right column */
@media print {
    /* Hide controls on left column when printing */
    .spec-editor-container,
    .finance-editor-card {
        display: none ;
    }
    
    /* Show finance preview on right column when printing */
    .finance-preview {
        display: block;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        page-break-inside: avoid;
        margin-top: 1rem;
    }
}
</style>

<script>
// Update finance preview visibility and values
function updateFinancePreview() {
    const isFinanceAvailable = document.getElementById('financeAvailableCheckbox')?.checked || false;
    const financePreview = document.getElementById('financePreview');
    const financeFields = document.getElementById('financeFieldsContainer');
    
    if (isFinanceAvailable) {
        // Show fields on left and preview on right
        if (financeFields) financeFields.style.display = 'block';
        if (financePreview) financePreview.style.display = 'block';
    } else {
        // Hide fields on left and preview on right
        if (financeFields) financeFields.style.display = 'none';
        if (financePreview) financePreview.style.display = 'none';
    }
    
    // Update the values in preview
    let deposit = document.getElementById('depositInput')?.value || '';
    let monthly = document.getElementById('monthlyInstallmentInput')?.value || '';
    
    const previewDeposit = document.getElementById('previewDeposit');
    const previewMonthly = document.getElementById('previewMonthly');
    
    if (previewDeposit) {
        previewDeposit.innerText = deposit === '' ? '—' : deposit;
    }
    if (previewMonthly) {
        previewMonthly.innerText = monthly === '' ? '—' : monthly;
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    const financeCheckbox = document.getElementById('financeAvailableCheckbox');
    const depositInput = document.getElementById('depositInput');
    const monthlyInput = document.getElementById('monthlyInstallmentInput');
    
    if (financeCheckbox) {
        financeCheckbox.addEventListener('change', updateFinancePreview);
    }
    if (depositInput) {
        depositInput.addEventListener('input', updateFinancePreview);
    }
    if (monthlyInput) {
        monthlyInput.addEventListener('input', updateFinancePreview);
    }
    
    // Initial update
    updateFinancePreview();
});
</script>
        </div>

        <div class="col-lg-6 col-sm-12">
          <div class=" no-print mt-3 mb-3">
    <button class="btn-print" id="printSpecBtn">
      <span>🖨️</span> Print / Save as PDF
    </button>
    <!-- <button class="btn-print" id="downloadPdfHint" style="background:#2c6e5c;">
      📄 Showroom Sheet
    </button> -->
  </div>
          <div class="spec-sheet content-area" id="vehicleSpecSheet">
            <!-- header area with price and status -->
            <div class="vehicle-header">
              <div class="title-section">
                <h2>
                  <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?>
                  <span class="badge-status">Finance Available</span>
                </h2>
                <div class="reg-badge">
                  <?= htmlspecialchars($vehicle['regnumber']) ?> • <?= $vehicle['year'] ?> • <?= ucfirst($vehicle['fuel_type'] ?: '') ?>
                </div>
              </div>
              <div class="price-tag">
                <!-- <div class="price-label">FOR SALE / PRICE</div> -->
                <div class="amount">
                  <img class="pt-0" src="uploads/images/britdrive-01.svg" width="200px" />

                  <!-- £<?= number_format(($vehicle['sale_price'] ?? $totalInvestment), 0) ?>
        <?php if ($vehicle['sale_price'] && $vehicle['status'] === 'sold'): ?>
          <span style="font-size: 0.8rem;">(Sold)</span>
        <?php elseif (!$vehicle['sale_price']): ?>
          <span style="font-size: 0.7rem;">+vat/on road</span>
        <?php endif; ?> -->
                </div>
                <!-- <?php if ($vehicle['sale_price'] && $profit !== null): ?>
        <div class="vat-note <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
          <?= $profit >= 0 ? 'Profit' : 'Margin' ?>: £<?= number_format(abs($profit), 2) ?>
        </div>
      <?php endif; ?> -->
              </div>
            </div>

            <!-- print buttons (hide on print) -->
            

            <div class="spec-content p-2">





              <!-- LEFT COLUMN: Main images + detailed specs -->
              <div class="spec-left p-1">
                <!-- Image gallery area -->
                <?php
                $images = $db->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, uploaded_at ASC");
                $images->execute([$vehicleId]);
                $images = $images->fetchAll(PDO::FETCH_ASSOC);
                $hasImages = !empty($images);
                $primaryImage = $hasImages ? $images[0]['image_path'] : '';
                ?>
                <div class="vehicle-image-area" style="height: 220px;">
                  <img id="mainVehicleImg" class="main-image" style="height: 220px;" src="<?= $hasImages ? htmlspecialchars($primaryImage) : 'uploads/vehicles/default-car.jpg' ?>" alt="<?= htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']) ?>">

                </div>

                <!-- KEY VEHICLE INFO (easy for employees) -->

                <!-- VEHICLE INFORMATION - TABLE FORMAT (Print Friendly) -->
                <!-- SPEC SHEET TABLE (Dynamic based on checkboxes) -->
                <div class="info-section-title">📋 Vehicle Specifications</div>
                <!-- SPEC SHEET - 4 COLUMN LAYOUT WITH INDEPENDENT CELL TOGGLES -->
                <?php
                // Define all specs with their own unique identifiers
                $specsList = [
                  'make_model' => ['label' => 'Make & Model', 'value' => htmlspecialchars($vehicle['make']) . ' ' . htmlspecialchars($vehicle['model'])],
                  'year' => ['label' => 'Year', 'value' => $vehicle['year']],
                  'registration' => ['label' => 'Registration', 'value' => htmlspecialchars($vehicle['regnumber'])],
                  'mileage' => ['label' => 'Mileage', 'value' => number_format($vehicle['mileage']) . ' miles'],
                  'transmission' => ['label' => 'Transmission', 'value' => ucfirst($vehicle['transmission'] ?: 'N/A')],
                  'fuel_type' => ['label' => 'Fuel Type', 'value' => ucfirst($vehicle['fuel_type'] ?: 'N/A')],
                  'engine_size' => ['label' => 'Engine Size', 'value' => htmlspecialchars($vehicle['engine_size'] ?: '-')],
                  'color' => ['label' => 'Color', 'value' => htmlspecialchars($vehicle['color'] ?: 'Standard')],
                  'body_style' => ['label' => 'Body Style', 'value' => ucfirst($vehicle['body_style'] ?: 'Saloon')],
                  'owners' => ['label' => 'Owners', 'value' => (int)$vehicle['owners'] . ' previous'],
                  'mot_expiry' => ['label' => 'MOT Expiry', 'value' => htmlspecialchars($vehicle['mot_expiry'] ?: 'TBC')],
                  'logbook' => ['label' => 'Logbook / V5', 'value' => htmlspecialchars($vehicle['logbook_status'] ?: 'Available')],
                  'service_history' => ['label' => 'Service History', 'value' => ($vehicle['number_of_services'] ?: '0') . ' service(s)'],
                  'keys' => ['label' => 'Keys', 'value' => ($vehicle['keys_count'] ?: '1') . ' set(s)'],
                  'v5_status' => ['label' => 'V5 Status', 'value' => htmlspecialchars($vehicle['v5_status'] ?: 'Ready')],
                ];
                ?>

                <table class="vehicle-specs-table" id="specSheetTable">
                  <tbody id="specTableBody">
                    <!-- Specs will be arranged in rows of 2 pairs each (4 columns total) -->
                  </tbody>
                </table>

                <script>
                  // Define all spec items (same as PHP array but in JavaScript for dynamic filtering)
                  const allSpecs = <?= json_encode($specsList) ?>;

                  // Get saved preferences (or use all specs as default)
                  let visibleSpecs = <?= json_encode(empty($specSheetPrefs) ? array_keys($specsList) : $specSheetPrefs) ?>;

                  // Function to render the table with current visible specs (4 column layout)
                  function renderSpecTable() {
                    const tbody = document.getElementById('specTableBody');
                    if (!tbody) return;

                    tbody.innerHTML = '';

                    // Get only visible specs in order
                    const visibleSpecsList = [];
                    const specOrder = <?= json_encode(array_keys($specsList)) ?>;

                    for (const specKey of specOrder) {
                      if (visibleSpecs.includes(specKey) && allSpecs[specKey]) {
                        visibleSpecsList.push({
                          key: specKey,
                          label: allSpecs[specKey].label,
                          value: allSpecs[specKey].value
                        });
                      }
                    }

                    // Arrange in rows of 2 pairs (4 columns per row)
                    for (let i = 0; i < visibleSpecsList.length; i += 2) {
                      const row = document.createElement('tr');

                      // First pair (label + value)
                      const spec1 = visibleSpecsList[i];
                      const td1_label = document.createElement('td');
                      const td1_value = document.createElement('td');
                      td1_label.className = 'spec-label';
                      td1_label.setAttribute('data-spec-key', spec1.key);
                      td1_label.innerHTML = spec1.label;
                      td1_value.className = 'spec-value';
                      td1_value.setAttribute('data-spec-key', spec1.key + '_value');
                      td1_value.innerHTML = spec1.value;
                      row.appendChild(td1_label);
                      row.appendChild(td1_value);

                      // Second pair (if exists)
                      if (visibleSpecsList[i + 1]) {
                        const spec2 = visibleSpecsList[i + 1];
                        const td2_label = document.createElement('td');
                        const td2_value = document.createElement('td');
                        td2_label.className = 'spec-label';
                        td2_label.setAttribute('data-spec-key', spec2.key);
                        td2_label.innerHTML = spec2.label;
                        td2_value.className = 'spec-value';
                        td2_value.setAttribute('data-spec-key', spec2.key + '_value');
                        td2_value.innerHTML = spec2.value;
                        row.appendChild(td2_label);
                        row.appendChild(td2_value);
                      } else {
                        // Fill empty cells to maintain 4-column layout
                        const td_empty1 = document.createElement('td');
                        const td_empty2 = document.createElement('td');
                        td_empty1.colSpan = 1;
                        td_empty2.colSpan = 1;
                        td_empty1.innerHTML = '';
                        td_empty2.innerHTML = '';
                        td_empty1.style.border = 'none';
                        td_empty2.style.border = 'none';
                        row.appendChild(td_empty1);
                        row.appendChild(td_empty2);
                      }

                      tbody.appendChild(row);
                    }

                    // Show empty state if no specs selected
                    if (visibleSpecsList.length === 0) {
                      const emptyRow = document.createElement('tr');
                      const emptyCell = document.createElement('td');
                      emptyCell.colSpan = 4;
                      emptyCell.style.textAlign = 'center';
                      emptyCell.style.padding = '40px';
                      emptyCell.style.color = '#95a5a6';
                      emptyCell.innerHTML = '⚠️ No specifications selected. Please check some options above.';
                      emptyRow.appendChild(emptyCell);
                      tbody.appendChild(emptyRow);
                    }
                  }

                  // Function to update visibility of a single spec cell (hide/show)
                  function toggleSpecVisibility(specKey, isVisible) {
                    if (isVisible) {
                      if (!visibleSpecs.includes(specKey)) {
                        visibleSpecs.push(specKey);
                        // Re-sort to maintain original order
                        const specOrder = <?= json_encode(array_keys($specsList)) ?>;
                        visibleSpecs.sort((a, b) => specOrder.indexOf(a) - specOrder.indexOf(b));
                      }
                    } else {
                      const index = visibleSpecs.indexOf(specKey);
                      if (index > -1) {
                        visibleSpecs.splice(index, 1);
                      }
                    }
                    renderSpecTable();
                  }

                  // Apply all checkbox changes at once
                  function applySpecChanges() {
                    const checkboxes = document.querySelectorAll('input[name="spec_fields[]"]');
                    visibleSpecs = [];
                    checkboxes.forEach(cb => {
                      if (cb.checked) {
                        visibleSpecs.push(cb.value);
                      }
                    });
                    // Maintain original order
                    const specOrder = <?= json_encode(array_keys($specsList)) ?>;
                    visibleSpecs.sort((a, b) => specOrder.indexOf(a) - specOrder.indexOf(b));
                    renderSpecTable();
                  }

                  // Reset to show all specs
                  function resetAllSpecs() {
                    const checkboxes = document.querySelectorAll('input[name="spec_fields[]"]');
                    checkboxes.forEach(cb => {
                      cb.checked = true;
                    });
                    applySpecChanges();
                  }

                  // Initialize on page load
                  document.addEventListener('DOMContentLoaded', function() {
                    renderSpecTable();

                    // Add real-time update to checkboxes
                    const checkboxes = document.querySelectorAll('input[name="spec_fields[]"]');
                    checkboxes.forEach(cb => {
                      cb.addEventListener('change', applySpecChanges);
                    });

                    // Update visibleSpecs array from saved preferences
                    const savedPrefs = <?= json_encode($specSheetPrefs) ?>;
                    if (savedPrefs && savedPrefs.length > 0) {
                      const checkboxesList = document.querySelectorAll('input[name="spec_fields[]"]');
                      checkboxesList.forEach(cb => {
                        cb.checked = savedPrefs.includes(cb.value);
                      });
                      applySpecChanges();
                    }

                    // Open editor tooltip for first-time users
                    if (savedPrefs.length === 0) {
                      setTimeout(() => {
                        const content = document.getElementById('specEditorContent');
                        if (content && !content.classList.contains('open')) {
                          content.classList.add('open');
                          const icon = document.querySelector('.toggle-icon');
                          if (icon) icon.style.transform = 'rotate(180deg)';
                        }
                      }, 800);
                    }
                  });

                  // Override form submission to save preferences before POST
                  function savePreferencesAndSubmit() {
                    // Get current visible specs before submitting
                    const checkboxes = document.querySelectorAll('input[name="spec_fields[]"]');
                    const selectedSpecs = [];
                    checkboxes.forEach(cb => {
                      if (cb.checked) {
                        selectedSpecs.push(cb.value);
                      }
                    });
                    // Update hidden input or just submit normally
                    document.getElementById('specPrefsForm').submit();
                  }
                </script>

                <style>
                  .vehicle-specs-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    border-radius: 16px;
                    overflow: hidden;
                    margin: 0.5rem 0 1rem 0;
                    font-size: 0.85rem;
                  }

                  .vehicle-specs-table tr {
                    border-bottom: 1px solid #e9edf2;
                  }

                  .vehicle-specs-table td {
                    padding: 12px 14px;
                    vertical-align: middle;
                  }

                  .spec-label {
                    font-weight: 700;
                    color: #2c5f6e;
                    width: 25%;
                    background-color: #f9fbfd;
                    letter-spacing: 0.3px;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                  }

                  .spec-value {
                    font-weight: 500;
                    color: #1e2f3d;
                    width: 25%;
                  }

                  @media print {
                    .vehicle-specs-table td {
                      padding: 8px 10px;
                    }

                    .spec-label {
                      background-color: #f2f4f8 !important;
                      -webkit-print-color-adjust: exact;
                      print-color-adjust: exact;
                    }
                  }

                  @media (max-width: 768px) {

                    .vehicle-specs-table,
                    .vehicle-specs-table tbody,
                    .vehicle-specs-table tr,
                    .vehicle-specs-table td {
                      display: block;
                      width: 100%;
                    }

                    .vehicle-specs-table tr {
                      margin-bottom: 8px;
                      border: 1px solid #e2e8f0;
                      border-radius: 10px;
                    }

                    .spec-label {
                      width: 100%;
                      background: #f1f5f9;
                    }

                    .spec-value {
                      width: 100%;
                      padding-left: 14px;
                    }
                  }
                </style>



                <?php if (!empty($vehicle['notes'])): ?>
                  <div class="info-section-title">📝 Additional notes</div>
                  <div style="background:#fef8e7; border-radius: 20px; padding: 0.8rem 1.2rem; margin-top: 0.5rem;">
                    <?= nl2br(htmlspecialchars($vehicle['notes'])) ?>
                  </div>
                <?php endif; ?>


              </div>

              <!-- RIGHT COLUMN: pricing summary + investment & selling points -->
              <div class="spec-right p-2">

                <div class="financial-card text-center">
                  <!-- <div class="profit-loss"> -->
                    <h2 class="fw-bold text-danger" id="displayPrice">Price: <br>£<?= number_format(($vehicle['sale_price'] ?? $totalInvestment), 0) ?></h2>
                  <!-- </div> -->
                   <script>
// Function to update price display
function updatePriceDisplay() {
    const priceInput = document.getElementById('priceInput');
    const displayPrice = document.getElementById('displayPrice');
    
    if (priceInput && displayPrice) {
        let priceValue = priceInput.value.trim();
        
        // If empty, show placeholder
        if (priceValue === '') {
            displayPrice.innerHTML = 'Price: £—';
        } else {
            // Ensure it has £ sign
            if (!priceValue.startsWith('£')) {
                priceValue = '£' + priceValue;
            }
            displayPrice.innerHTML = 'PRICE <br>' + priceValue;
        }
    }
}

// Add event listener when page loads
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('priceInput');
    if (priceInput) {
        priceInput.addEventListener('input', updatePriceDisplay);
    }
    
    // Initial update
    updatePriceDisplay();
});
                </script>
                </div>

                <!-- Finance Result Preview -->
                <div class="finance-preview" id="financePreview" style="display: none;">
                  <div class="preview-header">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Finance Available</span>
                  </div>

                  <div class="preview-details">
                    <div class="preview-row ">
                      <span>💷 Deposit:</span>
                      <span id="previewDeposit">0</span>
                    </div>
                    <div class="preview-row total">
                      <span>📅 Monthly Installment: </span><br>
                      <span id="previewMonthly">0</span>
                    </div>
                  </div>

                  <div class="preview-footer">
                    <small>⭐ Subject to status. Terms and conditions apply.</small>
                  </div>
                </div>
                <!-- quick selling highlights -->
                
<div style="background:#ecfdf3; border-radius: 20px; padding: 0.9rem 1rem; margin-top: 0.4rem;">
    <div style="font-weight: 700; display: flex; gap: 8px; align-items: center;">⭐ Customer highlights</div>
    <ul id="highlightsList" style="margin-top: 8px; margin-left: 1.2rem; font-size: 0.8rem; color:#2c5a49;">
        <!-- Features will appear here dynamically -->
    </ul>
</div>


                <!-- contact / employee helper (only if printed, shows as reference) -->
                <div class="condition-note" style="margin-top: 1rem; text-align:center; background:#e9f0fa;">
                  🚘 <strong>Ask our sales team for test drive</strong><br>
                  Vehicle ID: <?= $vehicle['vehicle_id'] ?> • Stock ready
                </div>
              </div>
            </div>
            <div class="p-2">
              <!-- VEHICLE INFORMATION - TABLE FORMAT (Print Friendly) -->

            </div>
            <div class="footer-disclaimer">
              ⚡ Generated on <?= date('d M Y, H:i') ?> • This spec sheet is for display & customer reference. All information is accurate to the best of our knowledge.

            </div>
          </div>
        </div>
      </div>



      <script>
        // Thumbnail switcher for main image
        function setMainImage(thumbElement) {
          const fullUrl = thumbElement.getAttribute('data-full');
          if (fullUrl) {
            document.getElementById('mainVehicleImg').src = fullUrl;
            // update active class
            document.querySelectorAll('.thumb').forEach(thumb => thumb.classList.remove('active'));
            thumbElement.classList.add('active');
          }
        }
        // make first thumb active if exists
        if (document.querySelector('.thumb')) {
          document.querySelector('.thumb').classList.add('active');
        }

        // Print functionality (clean print)
        const printBtn = document.getElementById('printSpecBtn');
        if (printBtn) {
          printBtn.addEventListener('click', function(e) {
            window.print();
          });
        }

        const downloadHint = document.getElementById('downloadPdfHint');
        if (downloadHint) {
          downloadHint.addEventListener('click', function() {
            alert('💡 Use browser "Print" > "Save as PDF" to generate a clean PDF sheet. Great for placing on car dashboard!');
          });
        }

        // optional: add data to showroom condition flag
        console.log("Vehicle spec ready – employee ready to sell");
      </script>
      <!-- necessary PHP variables already defined above: $vehicle, $totalInvestment, $totalRepairs, $totalExpenses, $profit,
     $images, $selected_task_ids, $selected_task_names, $task_statuses, $vehicleId etc. All from original backend -->


    </div>

    <!-- Mobile Action Buttons -->
    <div class="mobile-actions">
      <!-- <div class="container-fluid">
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
      </div> -->
      <?php include_once("footer.php"); ?>

    </div>

    <!-- Footer -->


  </main>
  <!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->

<script>
// Array to store all features
let customerFeatures = [];

// Function to render all features in the highlights section
function renderFeatures() {
    const highlightsList = document.getElementById('highlightsList');
    if (!highlightsList) return;
    
    // Clear existing list
    highlightsList.innerHTML = '';
    
    // If no features, show default message
    if (customerFeatures.length === 0) {
        const defaultItem = document.createElement('li');
        defaultItem.innerHTML = '✨ Click "Add Feature" to highlight key selling points';
        defaultItem.style.color = '#95a5a6';
        defaultItem.style.listStyle = 'none';
        highlightsList.appendChild(defaultItem);
        return;
    }
    
    // Render each feature as a list item with a remove option
    customerFeatures.forEach((feature, index) => {
        const li = document.createElement('li');
        li.style.display = 'flex';
        li.style.justifyContent = 'space-between';
        li.style.alignItems = 'left';
        li.style.marginBottom = '8px';

        
        const featureText = document.createElement('span');
        featureText.innerHTML = `⭐  ${feature}`;
        featureText.style.textIndent='-3.5em';
        
        li.appendChild(featureText);
        highlightsList.appendChild(li);
    });
}

// Function to add a new feature
function addFeature() {
    const input = document.getElementById('featureInput');
    const featureText = input.value.trim();
    
    if (featureText === '') {
        alert('Please enter a feature');
        return;
    }
    
    // Add to array
    customerFeatures.push(featureText);
    
    // Clear input
    input.value = '';
    
    // Re-render the list
    renderFeatures();
}

// Function to remove a feature by index
function removeFeature(index) {
    customerFeatures.splice(index, 1);
    renderFeatures();
}

// Function to toggle the feature editor visibility
function toggleFeatureEditor() {
    const editor = document.getElementById('featureEditor');
    if (editor.style.display === 'none' || editor.style.display === '') {
        editor.style.display = 'block';
    } else {
        editor.style.display = 'none';
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add the feature editor UI to the left column (controls area)
    const leftColumn = document.querySelector('.spec-controls-container');
    
    if (leftColumn) {
        // Check if feature editor already exists
        if (!document.getElementById('featureEditor')) {
            const featureEditorHTML = `
                <div class="feature-editor" style="background: white; border-radius: 20px; margin-top: 1.5rem; border: 1px solid #e2e8f0; overflow: hidden;">
                    <div class="feature-header" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-star"></i>
                            <h3 style="color: white; margin: 0; font-size: 1.1rem;"> Customer Highlights</h3>
                        </div>
                    </div>
                    <div id="featureEditor" style="display: block; padding: 1.5rem; background: #f8fafc;">
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <input type="text" id="featureInput" class="finance-input" placeholder="Enter a feature, e.g., 'Full service history', 'New tyres fitted'" style="flex: 1;">
                            <button onclick="addFeature()" style="background: #27ae60; color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 12px; cursor: pointer; font-weight: 600;">+ Add</button>
                        </div>
                        <div style="margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong>Current Features (${customerFeatures.length})</strong>
                                <button onclick="customerFeatures = []; renderFeatures();" style="background: #e74c3c; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.7rem;">Clear All</button>
                            </div>
                            <div id="featuresListPreview" style="max-height: 200px; overflow-y: auto; background: white; border-radius: 12px; padding: 0.5rem;">
                                <!-- Preview of features will show here -->
                            </div>
                        </div>
                        <div class="spec-actions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                            <small style="color: #7f8c8d;">💡 Features you add will appear in the Customer Highlights section on the right</small>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert after finance-editor-card
            const financeCard = document.querySelector('.finance-editor-card');
            if (financeCard) {
                financeCard.insertAdjacentHTML('afterend', featureEditorHTML);
            } else {
                leftColumn.insertAdjacentHTML('beforeend', featureEditorHTML);
            }
            
            // Add click toggle for feature editor
            const featureHeader = document.querySelector('.feature-header');
            if (featureHeader) {
                featureHeader.addEventListener('click', function() {
                    const editor = document.getElementById('featureEditor');
                    const icon = this.querySelector('.toggle-icon');
                    if (editor.style.display === 'none' || editor.style.display === '') {
                        editor.style.display = 'block';
                        icon.style.transform = 'rotate(180deg';
                        icon.style.transition = 'transform 0.3s';
                    } else {
                        editor.style.display = 'none';
                        icon.style.transform = 'rotate(0deg';
                    }
                });
            }
        }
    }
    
    // Update the preview list dynamically
    function updatePreviewList() {
        const previewContainer = document.getElementById('featuresListPreview');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '';
        
        if (customerFeatures.length === 0) {
            previewContainer.innerHTML = '<div style="padding: 1rem; text-align: center; color: #95a5a6;">No features added yet. Click "Add Feature" to start.</div>';
        } else {
            customerFeatures.forEach((feature, index) => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.justifyContent = 'space-between';
                item.style.alignItems = 'center';
                item.style.padding = '0.5rem';
                item.style.borderBottom = '1px solid #ecf0f1';
                
                const text = document.createElement('span');
                text.innerHTML = `⭐ ${feature}`;
                text.style.fontSize = '0.85rem';
                
                const removeBtn = document.createElement('button');
                removeBtn.innerHTML = '✖';
                removeBtn.style.background = 'none';
                removeBtn.style.border = 'none';
                removeBtn.style.color = '#e74c3c';
                removeBtn.style.cursor = 'pointer';
                removeBtn.style.fontSize = '12px';
                removeBtn.onclick = () => removeFeature(index);
                
                item.appendChild(text);
                item.appendChild(removeBtn);
                previewContainer.appendChild(item);
            });
        }
    }
    
    // Override renderFeatures to also update preview list
    const originalRender = renderFeatures;
    window.renderFeatures = function() {
        originalRender();
        updatePreviewList();
    };
    renderFeatures = window.renderFeatures;
    
    // Also update removeFeature to use new render
    window.removeFeature = function(index) {
        customerFeatures.splice(index, 1);
        renderFeatures();
    };
    
    // Initialize
    renderFeatures();
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
  <script src="vendors/swiper/swiper-bundle.min.js"></script>

</body>


</html>