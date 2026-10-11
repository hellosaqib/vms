<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Add New Expense";

if (!hasPermission('manage_expenses')) {
    setError("Access Denied");
    header("Location: access-denied.php");
    exit;
}

// Get vehicle ID if provided
$vehicleId = isset($_GET['id']) ? (int)$_GET['id'] : NULL;

// Verify vehicle exists if ID provided
if ($vehicleId > 0) {
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        setError("Vehicle not found");
        header("Location: expense-dashboard.php");
        exit;
    }
}
// ==============================================
// PART 1: FORM SUBMISSION HANDLER (POST)
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $required = ['expense_type', 'description', 'amount', 'date'];
    $errors = [];

        // Validate required fields
    if (empty($_POST['expense_type'])) {
        $errors['expense_type'] = 'Expense type is required';
    }
    
    if (empty($_POST['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors['amount'] = 'Please enter a valid amount greater than 0';
    }
    
    if (empty($_POST['date'])) {
        $errors['date'] = 'Date is required';
    }
    // Validate date format
    if (!empty($_POST['date']) && strtotime($_POST['date']) > time()) {
        $errors['date'] = 'Date cannot be in the future';
    }
    // If validation failed
    if (!empty($errors)) {
        setFormErrors($errors);
        saveFormData($_POST);
        setError('Please correct the errors below');
        
        // Redirect back with vehicle_id if it exists
        $redirectUrl = "add-expense.php";
        if (!empty($_POST['vehicle_id'])) {
            $redirectUrl .= "?vehicle_id=" . (int)$_POST['vehicle_id'];
        }
        header("Location: " . $redirectUrl);
        exit;
    }

        try 
        {
          
          $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : NULL;

            $stmt = $db->prepare("
                INSERT INTO expenses (
                    vehicle_id, expense_type, description, amount, 
                    date, vendor_name, receipt_number, notes, added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $vehicle_id,  
                $_POST['expense_type'],
                $_POST['description'],
                $_POST['amount'],
                $_POST['date'],
                $_POST['vendor_name'] ?? null,
                $_POST['receipt_number'] ?? null,
                $_POST['notes'] ?? null,
                $_SESSION['user_id']
            ]);

        // Clear form data
        clearFormData();
        clearFormErrors();
        
        // SUCCESS - set message and redirect
        setSuccess('Expense added successfully!');
        
        // Redirect based on vehicle association
        if ($vehicle_id > 0) {
            header("Location: view-vehicle.php?id=" . $vehicle_id);
        } else {
            header("Location: expense-dashboard.php");
        }

            exit;
        } catch (PDOException $e) {
        saveFormData($_POST);
        setError('Database error: ' . $e->getMessage());
        $redirectUrl = "add-expense.php";
        if (!empty($_POST['vehicle_id'])) {
            $redirectUrl .= "?vehicle_id=" . (int)$_POST['vehicle_id'];
        }
        header("Location: " . $redirectUrl);
        }
    }


// ==============================================
// PART 2: PAGE LOAD (GET REQUEST)
// ==============================================

// Get vehicle info if vehicleId is set
$vehicle = null;
if ($vehicleId > 0) {
    try {
        $stmt = $db->prepare("SELECT vehicle_id, make, model, year, regnumber FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        setError('Error loading vehicle details');
    }
}

// Check if we have form data from error redirect
$formData = $_SESSION['form_data'] ?? [];

// If vehicleId is set, pre-fill the vehicle_id in form data
if ($vehicleId > 0 && empty($formData['vehicle_id'])) {
    $formData['vehicle_id'] = $vehicleId;
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
            <?php displayMessage(); ?>
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12 col-xxl-6">

                        <!-- <hr class="bg-body-secondary" /> -->
                        <div class="row flex-between-center mb-4 g-3">

                            <div class="container">
                                <h2>
                                    <?php if (isset($vehicle)): ?>
                                        Add Expense for: <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?> (<?= $vehicle['year'] ?>)
                                    <?php else: ?>
                                        Add New Expense
                                    <?php endif; ?>
                                </h2>

<hr>
                                <form method="POST" class="expense-form">
                                    <div class="mb-3">
                                        <label class="form-label">Vehicle (Optional):</label>
                                        <select name="vehicle_id" class="form-select">
                                            <option value="">-- General / Business Expense --</option>
                                            <?php
                                            $vehicles = $db->query("SELECT vehicle_id, regnumber, make, model FROM vehicles WHERE status != 'sold' ORDER BY regnumber")->fetchAll();
                                            foreach ($vehicles as $v) {
                                                $selected = ($vehicleId == $v['vehicle_id']) ? 'selected' : '';
                                                echo "<option value='{$v['vehicle_id']}' $selected>{$v['regnumber']} - {$v['make']} {$v['model']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Leave as "General Expense" for business costs like office rent, utilities, etc.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Expense Type: <span class="text-danger">*</span></label>
                                        <select name="expense_type" class="form-select" required>
                                            <option value="transport">Transport</option>
                                            <option value="storage">Storage</option>
                                            <option value="cleaning">Cleaning</option>
                                            <option value="registration">Registration</option>
                                            <option value="insurance">Insurance</option>
                                            <option value="detailing">Detailing</option>
                                            <option value="advertising">Advertising</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description: <span class="text-danger">*</span></label>
                                        <input type="text" name="description" class="form-control" placeholder="e.g., Vehicle transport from auction, Office rent payment" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Amount (£): <span class="text-danger">*</span></label>
                                        <input type="number" name="amount" step="1" class="form-control" placeholder="0.00" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Date: <span class="text-danger">*</span></label>
                                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Vendor Name:</label>
                                        <input type="text" name="vendor_name" class="form-control" placeholder="e.g., ABC Transport Ltd, Office Landlord">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Receipt Number:</label>
                                        <input type="text" name="receipt_number" class="form-control" placeholder="Optional receipt/invoice number">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Notes:</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this expense"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="javascript:history.back()" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Add Expense</button>
                                    </div>
                                </form>
                                
                            </div>



                        </div>
                    </div>

                </div>
            </div>


        </div>

        <!-- Footer -->
        <!-- <?php include_once("footer.php"); ?> -->
        </div>

        <!-- Chat Now -->

    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

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