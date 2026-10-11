<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "View Job Card";
// Get job card number from URL
$job_card_number = $_GET['jc'] ?? '';

if (empty($job_card_number)) {
    $_SESSION['error_message'] = "Job card number is required";
    header("Location: job-cards.php");
    exit();
}

// Fetch job card details
$job_card = [];
$vehicle = [];
$assigned_staff = [];
$created_by = [];

try {
    // Get job card details
    $sql = "SELECT jc.*, a.auction_name, a.city, v.make, v.model, v.year, v.regnumber, v.color,
                   v.VIN, v.mileage, v.fuel_type, v.transmission, v.logbook_status, v.keys_count,
                   ass.full_name as assigned_staff_name,
                   cr.full_name as created_by_name
            FROM job_cards jc
            LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
            LEFT JOIN users ass ON jc.assigned_to = ass.id
            LEFT JOIN auctions a ON v.auction_id = a.auction_id
            LEFT JOIN users cr ON jc.created_by = cr.id
            WHERE jc.job_card_number = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$job_card_number]);
    $job_card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job_card) {
        $_SESSION['error_message'] = "Job card not found: " . htmlspecialchars($job_card_number);
        header("Location: job-cards.php");
        exit();
    }

    // Get standard tasks for reference
    $standard_tasks = [];
    $task_sql = "SELECT task_id, task_name FROM standard_tasks WHERE task_status = 'active'";
    $task_stmt = $db->query($task_sql);
    $standard_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching job card: " . $e->getMessage();
    header("Location: job-cards.php");
    exit();
}

// Process selected tasks
$selected_task_names = [];
$selected_task_cost = [];
$selected_task_desc = [];
if (!empty($job_card['selected_tasks'])) {
    $selected_task_ids = explode(',', $job_card['selected_tasks']);
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
// Check if print view is requested
$is_print_view = isset($_GET['print']);

// In your form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_card_id = $_POST['job_card_id'];
    $task_costs = $_POST['task_cost'] ?? [];
    $task_descriptions = $_POST['task_description'] ?? [];
    $job_card_notes = $_POST['job_card_notes'];
    
    $total_cost = 0;
    
    // Update each task with cost and description
    foreach ($task_costs as $task_id => $cost) {
        $description = $task_descriptions[$task_id] ?? '';
        $cost = floatval($cost);
        $total_cost += $cost;
        
        $sql = "UPDATE job_card_task_progress 
                SET cost = ?, description = ? 
                WHERE task_id = ? AND job_card_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$cost, $description, $task_id, $job_card_id]);
    }
    
    // Update total in job_cards table
    $sql = "UPDATE job_cards SET total_cost = ?, internal_notes = ? WHERE job_card_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$total_cost, $job_card_notes, $job_card_id]);
    
    $_SESSION['success_message'] = "Costs updated successfully!";
    header("Location: process-job-card.php?jc=" . $job_card_number);
    exit();
}

$task_statuses = [];
$task_costs = [];
$task_descriptions =[];
try {
    $status_sql = "SELECT * FROM job_card_task_progress 
                   WHERE job_card_id = ?";
    $status_stmt = $db->prepare($status_sql);
    $status_stmt->execute([$job_card['job_card_id']]);
    $task_status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert to task_id => status mapping
    foreach ($task_status_data as $task_status) {
        $task_statuses[$task_status['task_id']] = $task_status['status'];
        $task_costs[$task_status['task_id']] = $task_status['cost'];
        $task_descriptions[$task_status['task_id']] = $task_status['description'];

    }
} catch (PDOException $e) {
    // If table doesn't exist yet, initialize empty array
    $task_statuses = [];
}

$current_date = date('Y-m-d H:i:s');
// try {
//     $status_sql = "SELECT COUNT(*) as total_pending_tasks 
// FROM job_card_task_progress 
// WHERE status = 'pending' AND job_card_id = ?;";
//     $status_stmt = $db->prepare($status_sql);
//     $status_stmt->execute([$job_card['job_card_id']]);
//     $task_status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
//     $taskCount = $task_status_data['total_pending_tasks'];
//     if ($taskCount == 0) {
//         $sql = "UPDATE job_cards 
//               SET status = 'Completed' , date_completed = ? 
//               WHERE job_card_id = ?";
//         $stmt = $db->prepare($sql);
//         $stmt->execute(array($current_date, $job_card['job_card_id']));

//         $vehicle_sql = "UPDATE vehicles 
//               SET status = 'available' 
//               WHERE vehicle_id = ?";
//         $stmt1 = $db->prepare($vehicle_sql);
//         $stmt1->execute(array($job_card['vehicle_id']));
//     }
// } catch (PDOException $e) {
//     // If table doesn't exist yet, initialize empty array
//     //$task_statuses = [];
// }
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
        @media print {
            body * {
                visibility: hidden;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;

            }

            .jobcard-area,
            .jobcard-area * {
                visibility: visible;
            }

            .jobcard-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
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
            <div class="button-group no-print">
                <!-- <button onclick="window.print()" class="btn btn-success">🖨️ Print Job Card</button> -->
                <a href="view-all-job-cards.php" class="btn btn-primary">📋 Back to Job Cards</a>
            </div><br>


            <!-- ============================================-->
            <!-- <section> begin ============================-->
            <section class="pt-0 pb-3 bg-body-emphasis dark__bg-gray-1200 jobcard-area" style=" width: 100%; border:0px solid red;">

                <div class="" style="margin-left:5px; width: 99%; border:0px solid red;">

                    <div class="pt-1 d-flex justify-content-between align-items-end mb-4">
                        <h2 class="">Job Card</h2>
                        <div class="p-0">
                            <!-- <h3></h3> -->
                            <img class="pt-0" src="uploads/images/britdrive-01.svg" width="200" />
                        </div>
                    </div>
                    <div class="bg-body dark__bg-gray-1100 mb-0 rounded-2">
                        <div class="row ">
                            <div class="col-12 col-lg-12 p-3">
                                <table class="table table-borderless mb-0" style="border:0px solid red;" width="100%">
                                    <tr>
                                        <td>
                                            <p class="fs-9 text-body-secondary fw-semibold mb-0"><strong>Job Card# <?php echo htmlspecialchars($job_card_number); ?></strong></p>
                                            <p class="fs-9 text-body-secondary fw-semibold mb-0">Created at: <?php echo (date('d-m-Y g:i A', strtotime($job_card['created_at']))); ?></p>
                                            <p class="fs-9 text-body-secondary fw-semibold mb-0">Created by: <?php echo htmlspecialchars($job_card['created_by_name']); ?></p>

                                        </td>
                                        <td>
                                            <strong class="mb-2 me-3">Vehicle : <?php echo htmlspecialchars("{$job_card['make']} {$job_card['model']} ({$job_card['year']}) - {$job_card['regnumber']}"); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Mileage: <?php echo htmlspecialchars($job_card['mileage'] ?? ''); ?> |
                                                Logbook: <?php echo htmlspecialchars($job_card['logbook'] ?? ''); ?> |
                                                Keys: <?php echo htmlspecialchars($job_card['keys_count'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <h6 class="mb-2"> Priority :
                                                <span class="text-<?php
                                                                    switch ($job_card['priority']) {
                                                                        case 'High':
                                                                            echo 'danger-dark';
                                                                            break;
                                                                        case 'Urgent':
                                                                            echo 'danger';
                                                                            break;
                                                                        case 'Medium':
                                                                            echo 'warning';
                                                                            break;
                                                                        default:
                                                                            echo 'secondary';
                                                                    }
                                                                    ?>"><?php echo $job_card['priority']; ?></span>
                                            </h6>
                                        </td>
                                        <td>
                                            <h6 class="mb-2"> Assigned to :<br>

                                                <strong><?php echo htmlspecialchars($job_card['assigned_staff_name']); ?></strong>
                                            </h6>
                                        </td>
                                    </tr>

                                </table>

                            </div>

                        </div>
                    </div>
                    <div class="pt-0">
                        <form method="post" name="form1">
                            <input type="hidden" name="job_card_id" value="<?= $job_card['job_card_id'] ?>">
                            <div class="row">
                                <div class="col-12 col-xl-12 col-xxl-12">
                                    <div class="p-3">
                                        <div class="d-flex align-items-center justify-content-start my-3">
                                            <div>
                                                <h4>Task List</h4>
                                            </div>

                                        </div>

                                        <div id="tableExample">
                                            <div class="table-responsive mx-n1 px-1">
                                                <table class="table table-sm border-top border-translucent fs-9 mb-0">
                                                    <thead>

                                                        <tr>
                                                            <th class="white-space-nowrap fs-9 align-middle ps-0" style="max-width:20px; width:18px;">

                                                            </th>
                                                            <th width="250" class="sort align-middle ps-3" data-sort="name">Task Name</th>
                                                            <th>Description</th>
                                                            <th width="200" class="sort align-right ps-3" data-sort="name" style="text-align:center;">Cost</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="list" id="bulk-select-body">
                                                        <!-- <?php // foreach ($selected_task_names as $tNames) { 
                                                                ?> -->
                                                        <?php
                                                        foreach ($selected_task_ids as $index => $task_id) {
                                                            $task_status = $task_statuses[$task_id] ?? 'pending';
                                                            $task_cost = $task_costs[$task_id];
                                                            $task_desc = $task_descriptions[$task_id];
                                                            $is_completed = ($task_status === 'completed');
                                                            $is_cancelled = ($task_status === 'cancelled');
                                                            $is_disabled = $is_completed || $is_cancelled;
                                                            if($task_cost==0.00){$task_cost="";}
                                                            echo($task_cost);
                                                            
                                                            if ($task_statuses[$task_id] == "pending") {

                                                        ?>

                                                                <tr>
                                                                    <td class="fs-9 align-middle">
                                                                        <div class="form-check mb-0 fs-8">

                                                                        </div>
                                                                    </td>
                                                                    <td class="align-middle ps-3 name"><?= $selected_task_names[$index]; ?>
                                                                        <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                            echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                        } ?>
                                                                        <span class="badge text-bg-warning">Pending</span>
                                                                        </td>
                                                                        <td><input class="form-control" type="text" id="task_description[<?= $task_id ?>]" name="task_description[<?= $task_id ?>]" value="<?= $task_desc ?>" /></td>

                                                                    <td>
                                                                        <span style="float:right;" class="badge text-bg-warning">
                                                                            <input class="form-control" type="text" id="task_cost[<?= $task_id ?>]" name="task_cost[<?= $task_id ?>]" value="<?= $task_cost ?>" readonly />
                                                                        </span>
                                                                    </td>

                                                                </tr>
                                                            <?php } else if ($task_statuses[$task_id] == "completed") {
                                                            ?>
                                                                <tr>
                                                                    <td class="fs-9 align-middle">
                                                                        <div class="form-check mb-0 fs-8 text-success">
                                                                            <i class="fas fa-check-square"></i>
                                                                        </div>
                                                                    </td>
                                                                    <td class="align-middle ps-3 name"><?= $selected_task_names[$index]; ?>
                                                                        <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                            echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                        } ?>

                                                                    </td>
                                                                    <td><input class="form-control" type="text" id="task_description[<?= $task_id ?>]" name="task_description[<?= $task_id ?>]" value="<?= $task_desc ?>" /></td>

                                                                    <td>
                                                                        <span style="float:right;" class="badge text-bg-success">
                                                                            <input class="form-control" type="text" id="task_cost[<?= $task_id ?>]" name="task_cost[<?= $task_id ?>]" value="<?= $task_cost ?>" />
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            <?php
                                                            } else if ($task_statuses[$task_id] == "cancelled") {
                                                            ?>
                                                                <tr>
                                                                    <td class="fs-9 align-left">
                                                                        <div class="form-check mb-0 fs-8 text-secondary">
                                                                            <i class="far fa-minus-square"></i>
                                                                        </div>
                                                                    </td>
                                                                    <td class="align-middle ps-3 name"><?= $selected_task_names[$index]; ?> <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                                                                                                echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                                                                                            } ?>
                                                                        <span class="badge text-bg-secondary">Cancelled</span>
                                                                    </td>
                                                                    <td><input class="form-control" type="text" id="task_description[<?= $task_id ?>]" name="task_description[<?= $task_id ?>]" value="<?= $task_desc ?>" /></td>

                                                                    <td>
                                                                        <span style="float:right;" class="badge text-bg-secondary">
                                                                            <input class="form-control" type="text" id="task_cost[<?= $task_id ?>]" name="task_cost[<?= $task_id ?>]" value="<?= $task_cost ?>" readonly />
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                        <?php if ($job_card['custom_tasks']): ?>
                                                            <td class="align-middle ps-3 name"><?= $job_card['custom_tasks']; ?></td>
                                                            <td><input class="form-control" type="text" name="custom_task_description" /></td>
                                                            <td><span style="float:right;" class="badge text-bg-success">
                                                                <input class="form-control" type="text" id="custom_task_cost" name="custom_task_cost" /></span></td>
                                                        <?php endif; ?>
                                                        <tr>
                                                            <td class="fs-9 align-middle">
                                                                <div class="form-check mb-0 fs-8">

                                                                </div>
                                                            </td>
                                                            <td class="fs-9 align-middle">
                                                                <h4>Total Costs:</h4>
                                                            </td>
                                                            <td></td>
                                                            <td class="align-right ps-3 name">
                                                                <span style="float:right;" class="badge text-bg-danger">
                                                                    <input class="form-control" type="text" id="total_cost" name="total_cost" /></span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                        </div>
                                        <br>
                                        <h6 for="notes">Notes </h6>
                                        <textarea class="form-control" name="job_card_notes"><?= $job_card['internal_notes']; ?></textarea>
                                        <!-- <div class="card-footer border-0"><a class="fw-bold fs-9 mt-4" href="#!"><span class="fas fa-plus me-1"></span>Add new task</a></div> -->
                                        <br>
                                        <button type="submit" class="btn btn-success" style="float: right;">
                                            <i class="fas fa-sync"></i> Update Cost
                                        </button>

                                    </div>

                                </div>

                            </div>
                        </form>
                    </div>

                </div><!-- end of .container-->

            </section><!-- <section> close ============================-->
            <!-- ============================================-->


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
        <!-- </div> -->

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
    // Get all cost inputs - fixed selector
    const costInputs = document.querySelectorAll('input[name^="task_cost"]');
    const totalInput = document.getElementById('total_cost');
    
    console.log('Found cost inputs:', costInputs.length); // Debug log
    
    function calculateTotal() {
        let total = 0;
        costInputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
            console.log('Input value:', input.value, 'Running total:', total); // Debug log
        });
        totalInput.value = total.toFixed(2);
        console.log('Final total:', total); // Debug log
    }
    
    // Add event listeners to all cost inputs
    costInputs.forEach(input => {
        input.addEventListener('input', calculateTotal);
        input.addEventListener('change', calculateTotal); // Also trigger on change
    });
    
    // Calculate initial total
    calculateTotal();
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