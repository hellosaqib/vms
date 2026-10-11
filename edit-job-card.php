<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Edit Job Card";

// Get job card ID from URL
$job_card_id = $_GET['jc_id'] ?? '';

if (empty($job_card_id)) {
    $_SESSION['error_message'] = "Job card ID is required";
    header("Location: job-cards.php");
    exit();
}

// Initialize variables
$errors = [];
$success = '';

// Fetch job card details
$job_card = [];
$vehicles = [];
$staff = [];
$standard_tasks = [];

try {
    // Get job card details
    $sql = "SELECT jc.*, v.make, v.model, v.year, v.regnumber, v.color,
                   v.VIN, v.mileage, v.fuel_type, v.transmission,
                   ass.full_name as assigned_staff_name,
                   cr.full_name as created_by_name,
                   a.auction_name
            FROM job_cards jc
            LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
            LEFT JOIN auctions a ON v.auction_id = a.auction_id
            LEFT JOIN users ass ON jc.assigned_to = ass.id
            LEFT JOIN users cr ON jc.created_by = cr.id
            WHERE jc.job_card_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$job_card_id]);
    $job_card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job_card) {
        $_SESSION['error_message'] = "Job card not found";
        header("Location: job-cards.php");
        exit();
    }

    // Get available vehicles
    $vehicle_sql = "SELECT vehicle_id, make, model, year, regnumber 
                    FROM vehicles 
                    WHERE status = 'available' OR vehicle_id = ?
                    ORDER BY make, model";
    $vehicle_stmt = $db->prepare($vehicle_sql);
    $vehicle_stmt->execute([$job_card['vehicle_id']]);
    $vehicles = $vehicle_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get staff members
    $staff_sql = "SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name";
    $staff_stmt = $db->query($staff_sql);
    $staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get standard tasks
    $task_sql = "SELECT task_id, task_name, description FROM standard_tasks WHERE task_status = 'active' ORDER BY task_id";
    $task_stmt = $db->query($task_sql);
    $standard_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current selected tasks
    $selected_tasks = [];
    if (!empty($job_card['selected_tasks'])) {
        $selected_tasks = explode(',', $job_card['selected_tasks']);
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching job card: " . $e->getMessage();
    header("Location: job-cards.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $job_card_id = $_POST['job_card_id'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? '';
    // $job_description = trim($_POST['job_description'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';
    // $customer_notes = trim($_POST['customer_notes'] ?? '');
    $selected_tasks_post = $_POST['tasks'] ?? [];
    $custom_task = trim($_POST['custom_task'] ?? '');
    $internal_notes = trim($_POST['internal_notes'] ?? '');

    // Validate required fields
    if (empty($job_card_id)) {
        $errors['job_card_id'] = 'Please select a Job Card';
    }

    if (empty($assigned_to)) {
        $errors['assigned_to'] = 'Please assign to a staff member';
    }

    // if (empty($job_description)) {
    //     $errors['job_description'] = 'Job description is required';
    // }

    // If no errors, update database
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Convert selected tasks array to string
            $selected_tasks_string = implode(',', $selected_tasks_post);

            // Update job card
            $update_sql = "UPDATE job_cards 
                          SET assigned_to = ?, 
                              priority = ?, status = 'Pending', internal_notes = ?,
                              selected_tasks = ?, custom_tasks = ?, updated_at = CURRENT_TIMESTAMP
                          WHERE job_card_id = ?";
            
            $stmt = $db->prepare($update_sql);
            $stmt->execute([
                $assigned_to, $priority, $internal_notes,
                $selected_tasks_string, $custom_task, $job_card_id
            ]);

            $db->commit();

            $_SESSION['success_message'] = "Job card #{$job_card['job_card_number']} updated successfully!";
            header("Location: view-job-card.php?jc=" . urlencode($job_card['job_card_number']));
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Error updating job card: " . $e->getMessage();
        }
    }
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
      <!-- <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?> -->
      <br>
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-6">
            <!-- <div class="mb-8">
              <h2 class="mb-2">Add New Repair</h2>
              <h5 class="text-body-tertiary fw-semibold">Add new repair</h5>
            </div> -->

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
            <!-- <hr class="bg-body-secondary" /> -->
            <div class="row flex-between-center mb-4 g-3">

              <div class="container">
                <h2>
                    Edit Job Card # <?= $job_card['job_card_number'] ?>
                </h2>

                <?php if (!empty($errors)): ?>
                  <div class="alert alert-danger">
                    <ul>
                      <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
<br>
                <form method="post">
                    <input type="hidden" name="job_card_id" value="<?= $job_card['job_card_id'] ?>">
                    <div class="form-group">
                      <p for="vehicle_id">Vehicle :<b> <?= $job_card['make']." ".$job_card['model']." (".$job_card['year'].") - ".$job_card['regnumber'] ?></b></p>
                    </div>
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="assigned_to" class="required">Assigned To</label>
                        <select class="form-select <?php echo isset($errors['assigned_to']) ? 'is-invalid' : ''; ?>"
                          id="assigned_to" name="assigned_to" required>
                          <option value="">Select Staff Member</option>
                          <?php foreach ($staff as $member): ?>
                            <option value="<?php echo $member['id']; ?>"
                              <?php echo ($job_card['assigned_to'] == $member['id']) ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($member['full_name']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['assigned_to'])): ?>
                          <div class="invalid-feedback"><?php echo $errors['assigned_to']; ?></div>
                        <?php endif; ?>
                      </div>

                      <div class="form-group">
                        <label for="priority" >Priority</label>
                        <select class="form-select" id="priority" name="priority">
                          <option value="Low" <?php echo ($job_card['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                          <option value="Medium" <?php echo ($job_card['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                          <option value="High" <?php echo ($job_card['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                          <option value="Urgent" <?php echo ($job_card['priority'] == 'Urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                      </div>
                    </div>

                      <!-- <div class="form-group">
                        <label for="date_created">Date Created</label>
                        <input type="date" class="form-control" id="date_created" name="date_created"
                          value="<?php echo htmlspecialchars($date_created); ?>">
                      </div> -->

                      <!-- <div class="form-group">
                        <label for="job_description" class="required">Job Description</label>
                        <textarea class="form-control <?php echo isset($errors['job_description']) ? 'is-invalid' : ''; ?>"
                          id="job_description" name="job_description" rows="3" required><?php echo htmlspecialchars($job_description); ?></textarea>
                        <?php if (isset($errors['job_description'])): ?>
                          <div class="invalid-feedback"><?php echo $errors['job_description']; ?></div>
                        <?php endif; ?>
                      </div> -->

                      <div class="form-group">
                        <div class="card mt-4">
                          <div class="card-header">
                            <h5 class="mb-0 required">Select Tasks</h5>
                            <?php if (isset($errors['tasks'])): ?>
                              <div class="text-danger"><?php echo $errors['tasks']; ?></div>
                            <?php endif; ?>
                          </div>
                          <div class="card-body">
                            <div class="row">
                              <?php foreach ($standard_tasks as $task): ?>
                                <?php if ($task['task_name'] !== 'Others'): ?>
                                  <div class="col-md-6">
                                    <div class="form-check task-checkbox">
                                      <input class="form-check-input" type="checkbox"
                                        name="tasks[]"
                                        value="<?php echo $task['task_id']; ?>"
                                        id="task_<?php echo $task['task_id']; ?>"
                                        <?php echo in_array($task['task_id'], $selected_tasks) ? 'checked' : ''; ?>>
                                      <label class="form-check-label" for="task_<?php echo $task['task_id']; ?>">
                                        <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                        <?php if (!empty($task['description'])): ?>
                                          <br><small class="text-muted"><?php echo htmlspecialchars($task['description']); ?></small>
                                        <?php endif; ?>
                                      </label>
                                    </div>
                                  </div>
                                <?php endif; ?>
                              <?php endforeach; ?>
                            </div>

                            <!-- Custom Task Input -->
                            <div class="custom-task mt-4">
                              <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_custom_task"
                                  <?php echo !empty($job_card['custom_tasks']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_custom_task">
                                  <strong>Other Custom Tasks</strong>
                                </label>
                              </div>
                              <div class="mb-3">
                                <label for="custom_task">Describe custom tasks:</label>
                                <textarea class="form-control" id="custom_task" name="custom_task"
                                  rows="2" placeholder="e.g., Replace windshield, Fix air conditioning, etc."><?php echo htmlspecialchars($job_card['custom_tasks'] ); ?></textarea>
                                <small class="text-muted">Separate multiple tasks with commas</small>
                              </div>
                            </div>
                          </div>
                        </div>

                      </div>

                      <div class="form-group">
                        <label for="internal_notes">Internal Notes</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"><?php echo htmlspecialchars($job_card['internal_notes']); ?></textarea>
                      </div>
                    
                  </div>
<br/>
                  <div class="form-group">
                    <button type="submit" class="btn btn-success">Update Job Card</button>
                      <a href="view-all-job-cards.php" class="btn btn-secondary">Cancel</a>
                  </div>
                </form>
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