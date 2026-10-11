<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "View Job Card";

// Get job card number from URL
$job_card_number = $_GET['jc'] ?? '';

if (empty($job_card_number)) {
  setError("Job card number is required");
  header("Location: view-all-job-cards.php");
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
    setError("Job card not found: " . htmlspecialchars($job_card_number));
    header("Location: view-all-job-cards.php");
    exit();
  }

  // Get standard tasks for reference
  $standard_tasks = [];
  $task_sql = "SELECT task_id, task_name FROM standard_tasks WHERE task_status = 'active'";
  $task_stmt = $db->query($task_sql);
  $standard_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  setError("Error fetching standard tasks: " . $e->getMessage());
  header("Location: view-all-job-cards.php");
  exit();
}

// Process selected tasks
$selected_task_names = [];
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
// In your form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $job_card_id = $_POST['job_card_id'];
  $job_card_number = $job_card['job_card_number']; // Get from your existing job_card data
  $updated_tasks = $_POST['completed_tasks'] ?? []; // Always an array!
  $bulkAction = $_POST['bulkAction'] ?? '';
  $current_date = date('Y-m-d H:i:s');

  // Get cost and description data
  $task_costs = $_POST['task_cost'] ?? [];
  $task_descriptions = $_POST['task_description'] ?? [];
  $total_cost_input = $_POST['total_cost'] ?? 0;
  $job_card_notes = $_POST['notes'] ?? '';

  try {
    // Start transaction for data consistency
    $db->beginTransaction();

    // 1. Update task statuses if bulk action is provided
    if (!empty($bulkAction) && !empty($updated_tasks)) {
      foreach ($updated_tasks as $task_id) {
        $sql = "UPDATE job_card_task_progress 
                        SET status = ?, date_completed = ? 
                        WHERE task_id = ? AND job_card_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$bulkAction, $current_date, $task_id, $job_card_id]);
      }
    }

    // 2. Update task costs and descriptions
    $calculated_total_cost = 0;
    foreach ($task_costs as $task_id => $cost) {
      $description = $task_descriptions[$task_id] ?? '';
      $cost = floatval($cost);
      $calculated_total_cost += $cost;

      $sql = "UPDATE job_card_task_progress 
                    SET cost = ?, description = ? 
                    WHERE task_id = ? AND job_card_id = ?";
      $stmt = $db->prepare($sql);
      $stmt->execute([$cost, $description, $task_id, $job_card_id]);
    }

    // 3. Update job card with total cost and notes
    $final_total_cost = ($total_cost_input > 0) ? floatval($total_cost_input) : $calculated_total_cost;

    $sql = "UPDATE job_cards 
                SET total_cost = ?, internal_notes = ?, updated_at = ? 
                WHERE job_card_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$final_total_cost, $job_card_notes, $current_date, $job_card_id]);

    // 4. Check if all tasks are completed and update vehicle status if needed
    $pending_tasks_sql = "SELECT COUNT(*) as total_pending_tasks 
                             FROM job_card_task_progress 
                             WHERE status = 'pending' AND job_card_id = ?";
    $pending_stmt = $db->prepare($pending_tasks_sql);
    $pending_stmt->execute([$job_card_id]);
    $pending_data = $pending_stmt->fetch(PDO::FETCH_ASSOC);
    $pending_task_count = $pending_data['total_pending_tasks'];

    if ($pending_task_count == 0) {
      // All tasks completed - update job card and vehicle status
      $sql = "UPDATE job_cards 
                    SET status = 'Completed', date_completed = ? 
                    WHERE job_card_id = ?";
      $stmt = $db->prepare($sql);
      $stmt->execute([$current_date, $job_card_id]);

      // Update vehicle status to available
      $vehicle_sql = "UPDATE vehicles 
                           SET status = 'available' 
                           WHERE vehicle_id = ?";
      $vehicle_stmt = $db->prepare($vehicle_sql);
      $vehicle_stmt->execute([$job_card['vehicle_id']]);

      setSuccess("Job card completed successfully! Vehicle status updated to available.");
    } else {
      setSuccess("Job card updated successfully! " . $pending_task_count . " task(s) remaining.");
    }

    // Commit transaction
    $db->commit();

    header("Location: view-job-card.php?jc=" . $job_card_number);
    exit();
  } catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    setError("Error updating job card: " . $e->getMessage());
    header("Location: view-job-card.php?jc=" . $job_card_number);
    exit();
  }
}

$task_statuses = [];
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
try {
  $status_sql = "SELECT COUNT(*) as total_pending_tasks 
FROM job_card_task_progress 
WHERE status = 'pending' AND job_card_id = ?;";
  $status_stmt = $db->prepare($status_sql);
  $status_stmt->execute([$job_card['job_card_id']]);
  $task_status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
  $taskCount = $task_status_data['total_pending_tasks'];
  if ($taskCount == 0) {
    $sql = "UPDATE job_cards 
              SET status = 'Completed' , date_completed = ? 
              WHERE job_card_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute(array($current_date, $job_card['job_card_id']));

    $vehicle_sql = "UPDATE vehicles 
              SET status = 'available' 
              WHERE vehicle_id = ?";
    $stmt1 = $db->prepare($vehicle_sql);
    $stmt1->execute(array($job_card['vehicle_id']));
  }
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
      // linkRTL.setAttribute('disabled', true);
      // userLinkRTL.setAttribute('disabled', true);
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
      <?php displayMessage(); ?>
      <div class="button-group no-print">
        <button onclick="window.print()" class="btn btn-success">🖨️ Print Job Card</button>
        <a href="view-all-job-cards.php" class="btn btn-primary">📋 Back to Job Cards</a>
      </div><br>
      <style>
        /* Style for all input elements with type="text" */
        input[type="text"] {
          border-color: black;
          /* Sets the default border color to blue */
          border-style: solid;
          /* Ensure a border style is set */
          border-width: 1px;
          /* Sets border thickness */
        }

        /* Style when the input box is clicked (gains focus) */
        input[type="text"]:focus {
          /* border-color: red; Changes the border color to red on focus */
          outline: none;
          /* Removes the default browser outline for a cleaner look */
        }
      </style>
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
          <div class="bg-body dark__bg-gray-1100 p-4 mb-0 rounded-2">
            <div class="row ">
              <div class="col-12 col-lg-12 p-0">
                <table class="table table-borderless mb-0" style="border:0px solid red;" width="100%">
                  <tr>
                    <td>
                      <p class="fs-9 text-body-secondary fw-semibold mb-0"><strong>Job Card# <?php echo htmlspecialchars($job_card_number); ?></strong></p>
                      <p class="fs-9 text-body-secondary fw-semibold mb-0">Created at: <?php echo (date('d-m-Y', strtotime($job_card['created_at']))); ?></p>
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
            <!-- FOrm & Table -->
            <form method="post" name="form1">
              <input type="hidden" name="job_card_id" value="<?= $job_card['job_card_id'] ?>">
              <div class="row">
                <div class="col-12 col-xl-12 col-xxl-12">
                  <div class="p-3">
                    <div class="d-flex align-items-center justify-content-start my-3">
                      <div id="bulk-select-replace-element">
                        <h4>Task List</h4>
                      </div>
                      <div class="d-none ms-3" id="bulk-select-actions">
                        <div class="d-flex">
                          <select class="form-select form-select-sm" aria-label="Bulk actions" name="bulkAction">
                            <option selected="selected">Bulk actions</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                          </select>
                          <button class="btn btn-phoenix-success btn-sm ms-2" type="submit">Apply</button>
                        </div>
                      </div>
                    </div>

                    <div id="tableExample" data-list="{&quot;valueNames&quot;:[&quot;name&quot;,&quot;email&quot;,&quot;age&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                      <div class="table-responsive mx-n1 px-1">
                        <table class="table table-sm border-top border-translucent fs-9 mb-0">
                          <thead>

                            <tr>
                              <th class="white-space-nowrap fs-9 align-middle ps-0" style="max-width:20px; width:18px;">
                                <div class="form-check mb-0 fs-8">
                                  <?php if ($taskCount > 0) { ?>
                                    <input name="bulk_Action" class="form-check-input" id="bulk-select-example" type="checkbox" data-bulk-select="{&quot;body&quot;:&quot;bulk-select-body&quot;,&quot;actions&quot;:&quot;bulk-select-actions&quot;,&quot;replacedElement&quot;:&quot;bulk-select-replace-element&quot;}">
                                  <?php } ?>
                                </div>
                              </th>
                              <th class="sort align-middle ps-3" data-sort="name">Task Name</th>
                              <th class="sort align-middle ps-3" data-sort="name">Description</th>
                              <th class="sort align-middle ps-3 text-center" data-sort="name">Cost</th>
                            </tr>
                          </thead>
                          <tbody class="list" id="bulk-select-body">
                            <?php
                            foreach ($selected_task_ids as $index => $task_id) {
                              $task_status = $task_statuses[$task_id] ?? 'pending';
                              $task_cost = $task_costs[$task_id];
                              $task_desc = $task_descriptions[$task_id];
                              $is_completed = ($task_status === 'completed');
                              $is_cancelled = ($task_status === 'cancelled');
                              $is_disabled = $is_completed || $is_cancelled;
                              if ($task_cost == 0.00) {
                                $task_cost = "";
                              }
                              if ($task_statuses[$task_id] == "pending") {
                            ?>

                                <tr>
                                  <td class="fs-9 align-middle">
                                    <div class="form-check mb-0 fs-8">
                                      <input class="form-check-input" name="completed_tasks[]"
                                        value="<?php echo ($task_id); ?>"
                                        id="task_<?php echo ($taskid); ?>" <?php if ($is_completed): ?>checked readonly<?php endif; ?>
                                        <?php if ($is_disabled): ?>disabled<?php endif; ?> type="checkbox" data-bulk-select-row="{&quot;name&quot;:&quot;Anna&quot;,&quot;email&quot;:&quot;anna@example.com&quot;,&quot;age&quot;:18}">
                                    </div>
                                  </td>
                                  <td class="align-middle ps-3 name" width="250"><?= $selected_task_names[$index]; ?> <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                                                                        echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                                                                      } ?>
                                  </td>
                                  <td>
                                    <input class="form-control" type="text" id="task_description[<?= $task_id ?>]" name="task_description[<?= $task_id ?>]" value="<?= $task_desc ?>" />
                                  </td>
                                  <td width="100">
                                    <span style="float:right;">
                                      <input class="form-control" type="text" id="task_cost[<?= $task_id ?>]" name="task_cost[<?= $task_id ?>]" value="<?= $task_cost ?>" />
                                    </span>
                                  </td>

                                </tr>
                              <?php } else if ($task_statuses[$task_id] == "completed") {
                              ?>
                                <tr>
                                  <td class="fs-9 align-middle">
                                    <div class="form-check mb-0 fs-8">
                                      <input class="form-check-input" name="completed_tasks[]"
                                        value="<?php echo ($task_id); ?>"
                                        id="task_<?php echo ($taskid); ?>" <?php if ($is_completed): ?>checked readonly<?php endif; ?>
                                        <?php if ($is_disabled): ?>disabled<?php endif; ?> type="checkbox" data-bulk-select-row="{&quot;name&quot;:&quot;Anna&quot;,&quot;email&quot;:&quot;anna@example.com&quot;,&quot;age&quot;:18}">
                                    </div>
                                  </td>
                                  <td class="align-middle ps-3 name"><?= $selected_task_names[$index]; ?> <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                                                            echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                                                          } ?>
                                    <span style="float:right;" class="badge text-bg-success">Completed</span>
                                  </td>
                                  <td><input class="form-control" type="text" id="task_description[<?= $task_id ?>]" name="task_description[<?= $task_id ?>]" value="<?= $task_desc ?>" readonly /></td>

                                  <td width="100">
                                    <input class="form-control" type="text" id="task_cost[<?= $task_id ?>]" name="task_cost[<?= $task_id ?>]" value="<?= $task_cost ?>" readonly />
                                  </td>
                                </tr>
                              <?php
                              } else if ($task_statuses[$task_id] == "cancelled") {
                              ?>
                                <tr>
                                  <td class="fs-9 align-left">
                                    <div class="form-check mb-0 fs-8">
                                      <!-- <i class="far fa-check-square"></i> -->
                                    </div>
                                  </td>
                                  <td class="align-middle ps-3 name"><?= $selected_task_names[$index]; ?> <?php if ($selected_task_names[$index] == "Car Collection") {
                                                                                                            echo (" from " . $job_card['auction_name'] . " - " . $job_card['city']);
                                                                                                          } ?>
                                    <span style="float:right;" class="badge text-bg-danger">Cancelled</span>
                                  </td>
                                </tr>
                            <?php
                              }
                            }
                            ?>
                            <?php if ($job_card['custom_tasks']): ?>
                              <tr>
                                <td class="fs-9 align-middle">
                                  <div class="form-check mb-0 fs-8">
                                    <input class="form-check-input" type="checkbox" data-bulk-select-row="{&quot;name&quot;:&quot;Anna&quot;,&quot;email&quot;:&quot;anna@example.com&quot;,&quot;age&quot;:18}" />
                                  </div>
                                </td>
                                <td class="align-middle ps-3 name"><?= $job_card['custom_tasks']; ?></td>

                              </tr>
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
                                <div id="total_cost_display" style="float: right; font-size:20px; color: black; padding: 3px 0px; border:1px solid black; border-radius: 4px; font-weight: bold; min-width: 100px; text-align: center;">
                                  0.00
                                </div>
                                <input class="form-control" type="hidden" id="total_cost" name="total_cost" />
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                    </div>
                    <br>
                    <?php if ($job_card['internal_notes']): ?>
                      <h6 for="notes">Notes </h6>
                      <textarea readonly class="form-control" name="notes" readonly><?= $job_card['internal_notes']; ?></textarea>
                      <!-- <div class="card-footer border-0"><a class="fw-bold fs-9 mt-4" href="#!"><span class="fas fa-plus me-1"></span>Add new task</a></div> -->
                    <?php endif; ?>

                    <div class="row p-5 pt-9">
                      <div class="col-3 border-top">
                        Manager Signature
                      </div>
                      <div class="col-6 ">
                      </div>
                      <div class="col-3 border-top">
                        Staff Signature
                      </div>
                    </div>
                  </div>

                </div>

              </div>
            </form>
          </div>

        </div><!-- end of .container-->

      </section><!-- <section> close ============================-->
      <!-- ============================================-->


    </div>
    <?php include_once("footer.php"); ?>

    </div>
    <!-- Footer -->

  </main><!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Get all cost inputs - fixed selector
      const costInputs = document.querySelectorAll('input[name*="task_cost"]');
      const totalInput = document.getElementById('total_cost');


      function calculateTotal() {
        let total = 0;
        costInputs.forEach(input => {
          const value = parseFloat(input.value) || 0;
          total += value;
          console.log('Input value:', input.value, 'Running total:', total); // Debug log
        });
        //   totalInput.value = total.toFixed(2);
        // console.log('Final total:', totalInput.value); // Debug log
        const display = document.getElementById('total_cost_display');
        const hiddenInput = document.getElementById('total_cost');

        display.textContent = total.toFixed(2);
        hiddenInput.value = total.toFixed(2);

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