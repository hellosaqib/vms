<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Add New Repair";

if (!hasPermission('manage_expenses')) {
    setError("Access Denied");
    header("Location: access-denied.php");
    exit;
}

// Get vehicle ID if provided
$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get vehicle ID if provided
$vStatus = isset($_GET['status']) ? (int)$_GET['status'] : '0';

// Verify vehicle exists if ID provided
if ($vehicleId > 0) {
  $stmt = $db->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
  $stmt->execute([$vehicleId]);
  $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$vehicle) {
    setError('Vehicle not found');
    header("Location: view-all-vehicles.php");
    exit;
  }
}
// ==============================================
// PART 1: FORM SUBMISSION HANDLER (POST)
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate form data
  $required = ['vehicle_id', 'repair_type', 'description', 'cost', 'date_started'];
  $errors = [];

      // Validate required fields
    if (empty($_POST['vehicle_id'])) {
        $errors['vehicle_id'] = 'Vehicle selection is required';
    }
    
    if (empty($_POST['repair_type'])) {
        $errors['repair_type'] = 'Repair type is required';
    }
    
    if (empty($_POST['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    if (empty($_POST['cost']) || !is_numeric($_POST['cost']) || $_POST['cost'] <= 0) {
        $errors['cost'] = 'Please enter a valid cost greater than 0';
    }
    
    if (empty($_POST['date_started'])) {
        $errors['date_started'] = 'Start date is required';
    }
    
    // Validate dates
    if (!empty($_POST['date_started']) && strtotime($_POST['date_started']) > time()) {
        $errors['date_started'] = 'Start date cannot be in the future';
    }
    
    if (!empty($_POST['date_completed']) && strtotime($_POST['date_completed']) > time()) {
        $errors['date_completed'] = 'Completion date cannot be in the future';
    }
    
    if (!empty($_POST['date_started']) && !empty($_POST['date_completed'])) {
        if (strtotime($_POST['date_completed']) < strtotime($_POST['date_started'])) {
            $errors['date_completed'] = 'Completion date cannot be before start date';
        }
    }
    
    if (!empty($_POST['warranty_until']) && strtotime($_POST['warranty_until']) < strtotime($_POST['date_started'])) {
        $errors['warranty_until'] = 'Warranty expiry cannot be before repair date';
    }
    
    // If validation failed
    if (!empty($errors)) {
        setFormErrors($errors);
        saveFormData($_POST);
        setError('Please correct the errors below');
        
        // Redirect back with vehicle_id if it exists
        $redirectUrl = "add-repair.php";
        if (!empty($_POST['vehicle_id'])) {
            $redirectUrl .= "?vehicle_id=" . (int)$_POST['vehicle_id'];
            if (!empty($_GET['status'])) {
                $redirectUrl .= "&status=" . $_GET['status'];
            }
        }
        header("Location: " . $redirectUrl);
        exit;
    }


    try 
    {
      $stmt = $db->prepare("
                INSERT INTO repairs (
                    vehicle_id, repair_type, description, cost, 
                    date_started, date_completed, mechanic_name, 
                    shop_name, warranty_until, receipt_number, added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      $stmt->execute([
        $_POST['vehicle_id'],
        $_POST['repair_type'],
        $_POST['description'],
        $_POST['cost'],
        $_POST['date_started'],
        $_POST['date_completed'] ?? null,
        $_POST['mechanic_name'] ?? null,
        $_POST['shop_name'] ?? null,
        $_POST['warranty_until'] ?? null,
        $_POST['receipt_number'] ?? null,
        $_SESSION['user_id']
      ]);

              // Clear form data
        clearFormData();
        clearFormErrors();
        
        // SUCCESS - set message and redirect
        setSuccess('Repair added successfully!');
        
        // Redirect based on context
        if ($vStatus === '1' && $vehicleId > 0) {
            header("Location: view-vehicle.php?id=" . $vehicle_id);
        } else {
            header("Location: expense-dashboard.php");
        }
        exit;

    } catch (PDOException $e) {
              // DATABASE ERROR - save form data and show error
        saveFormData($_POST);
        setError('Database error: ' . $e->getMessage());
        
        $redirectUrl = "add-repair.php";
        if (!empty($_POST['vehicle_id'])) {
            $redirectUrl .= "?vehicle_id=" . (int)$_POST['vehicle_id'];
            if (!empty($_GET['status'])) {
                $redirectUrl .= "&status=" . $_GET['status'];
            }
        }
        header("Location: " . $redirectUrl);
        exit;
    }
  }



//include '../includes/header.php';
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
                    <div class="col-12 col-xxl-6">
                        <div class="row flex-between-center mb-4 g-3">
                            <div class="container">
                                <h2>
                                    <?php if (isset($vehicle)): ?>
                                        Add Repair for: <?= htmlspecialchars($vehicle['regnumber']) ?> - <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?>
                                    <?php else: ?>
                                        Add Emergency Repair
                                    <?php endif; ?>
                                </h2>
                                <small class="text-body-tertiary">Use this form for repairs not covered by job cards - emergency fixes, external shop work, warranty repairs, etc.</small>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= $error ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="repair-form">
                                    <!-- Vehicle Selection -->
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle: <span class="text-danger">*</span></label>
                                        <select name="vehicle_id" class="form-select" required>
                                            <option value="">Select Vehicle</option>
                                            <?php
                                            $vehicles = $db->query("SELECT vehicle_id, regnumber, make, model FROM vehicles WHERE status != 'sold' ORDER BY regnumber")->fetchAll();
                                            foreach ($vehicles as $v) {
                                                $selected = ($vehicleId == $v['vehicle_id']) ? 'selected' : '';
                                                echo "<option value='{$v['vehicle_id']}' $selected>{$v['regnumber']} - {$v['make']} {$v['model']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Repair Type -->
                                    <div class="mb-3">
                                        <label class="form-label">Repair Type: <span class="text-danger">*</span></label>
                                        <select name="repair_type" class="form-select" required>
                                            <option value="">Select Repair Type</option>
                                            <option value="engine">Engine</option>
                                            <option value="transmission">Transmission</option>
                                            <option value="electrical">Electrical</option>
                                            <option value="body">Body Work</option>
                                            <option value="interior">Interior</option>
                                            <option value="suspension">Suspension</option>
                                            <option value="brakes">Brakes</option>
                                            <option value="tires">Tires</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div class="mb-3">
                                        <label class="form-label">Description: <span class="text-danger">*</span></label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Describe the repair work done..." required></textarea>
                                    </div>
                                    
                                    <!-- Cost -->
                                    <div class="mb-3">
                                        <label class="form-label">Cost (£): <span class="text-danger">*</span></label>
                                        <input type="number" name="cost" step="0.01" class="form-control" placeholder="0.00" required>
                                    </div>
                                    
                                    <!-- Dates -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Date Started: <span class="text-danger">*</span></label>
                                            <input type="date" name="date_started" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Date Completed (Optional):</label>
                                            <input type="date" name="date_completed" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <!-- Shop & Mechanic -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Shop Name:</label>
                                            <input type="text" name="shop_name" class="form-control" placeholder="e.g., ABC Auto Repairs">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Mechanic Name (Optional):</label>
                                            <input type="text" name="mechanic_name" class="form-control" placeholder="e.g., John Smith">
                                        </div>
                                    </div>
                                    
                                    <!-- Warranty -->
                                    <div class="mb-3">
                                        <label class="form-label">Warranty Until (Optional):</label>
                                        <input type="date" name="warranty_until" class="form-control">
                                        <div class="form-text">If this repair comes with a warranty, specify the end date.</div>
                                    </div>
                                    
                                    <!-- Receipt -->
                                    <div class="mb-3">
                                        <label class="form-label">Receipt Number:</label>
                                        <input type="text" name="receipt_number" class="form-control" placeholder="Invoice or receipt number">
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="expense-dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Add Repair</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
 <?php include_once("footer.php"); ?>
          </div>

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