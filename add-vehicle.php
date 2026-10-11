<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Add New Vehicles";
$user = $auth->getUser();
$roleName = $auth->getRoleName();

if (!hasPermission('manage_sales')) {
  header("Location: access-denied.php");
  exit;
}
// Get auctions for dropdown
$auctions = $db->query("SELECT * FROM auctions ORDER BY auction_name")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate and process form data
  $errors = [];
  $formData = [];

  $formData = $_POST;

  $requiredFields = [
    'make' => 'Make is required',
    // 'model' => 'Model is required',
    'reg_number' => 'Registration number is required',
    'purchase_date' => 'Puchase Date is required',
    'purchase_price' => 'Pruchase Price is required',
  ];

  foreach ($requiredFields as $field => $errorMessage) {
    if (empty(trim($_POST[$field] ?? ''))) {
      $errors[$field] = $errorMessage;
    }
  }

  // Duplicate vehicle check by registration number
  if (empty($errors['reg_number']) && !empty($_POST['reg_number'])) {
    try {
      $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE regnumber = ?");
      $checkStmt->execute([strtoupper(trim($_POST['reg_number']))]);
      $existingVehicle = $checkStmt->fetch(PDO::FETCH_ASSOC);

      if ($existingVehicle) {
      setFormErrors($errors);
      saveFormData($_POST);
      setError("Vehicle with registration number '" . htmlspecialchars($_POST['reg_number']) . "' already exists!");
      // Redirect back to self with error flag
      header("Location: view-all-vehicles.php?=1");
      die;
      }
    } catch (PDOException $e) {
      $errors[] = "Error checking vehicle duplicate: " . $e->getMessage();
      setFormErrors($errors);
      saveFormData($_POST);
      setError("Error checking vehicle duplicate:");
      // Redirect back to self with error flag
      header("Location: add-vehicle.php?error=1");
      die;
    }
  }

  $_POST = sanitizeFormData($_POST);

  // echo ("VIN: " . $_POST['vin'] . "<br/>" .
  //   "Reg: " . $_POST['reg_number'] . "<br/>" .
  //   "Make: " . $_POST['make'] . "<br/>" .
  //   "Model: " . $_POST['model'] . "<br/>" .
  //   "Year: " . $_POST['year'] . "<br/>" .
  //   "Trim: " . $_POST['trim'] . "<br/>" .
  //   "Color: " . $_POST['color'] . "<br/>" .
  //   "Mileage: " . $_POST['mileage'] . "<br/>" .
  //   "Owners: " . $_POST['owners'] . "<br/>" .
  //   "Keys: " . $_POST['keys_count'] . "<br/>" .
  //   "V5: " . $_POST['v5_status'] . "<br/>" .
  //   "SH: " . $_POST['number_of_services'] . "<br/>" .
  //   "MOT: " . $_POST['mot_expiry'] . "<br/>" .
  //   "Logbook: " . $_POST['logbook_status'] . "<br/><hr>" .
  //   "Engine Size: " . $_POST['engine_size'] . "<br/>" .
  //   "Body Style: " . $_POST['body_style'] . "<br/>" .
  //   "Transmission: " . $_POST['transmission'] . "<br/>" .
  //   "Fuel Type: " . $_POST['fuel_type'] . "<br/><hr>" .
  //   "Auction: " . $_POST['auction_id'] . "<br/>" .
  //   "Purchase Date: " . $_POST['purchase_date'] . "<br/>" .
  //   "Purchase Price: " . $_POST['purchase_price'] . "<br/>" .
  //   "Notes: " . $_POST['notes'] . "<br/>" .
  //   "Admin User:" . $_SESSION['user_id'] .  "<br/>" .
  //   "Created_at: ".time()
  // );
  // die;

  if ($_POST['mileage'] === '' || $_POST['mileage'] === null) {
    // Option A: Set to NULL (if column allows NULL)
    $_POST['mileage'] = null;
  }

  $Date = time();
  if (empty($errors)) {
    try {
      $stmt = $db->prepare("
                INSERT INTO vehicles (
                    VIN, engine_no , regnumber, make, model, year, trim, mileage, color, body_style, 
                    transmission, fuel_type, auction_id, purchase_date, 
                    purchase_price, status, notes, created_by, engine_size, owners, 
                    v5_status, number_of_services, keys_count, logbook_status, last_v5c_issued, tax_status, tax_due_date, mot_expiry
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      $stmt->execute([
        $_POST['VIN'] ?? null,
        $_POST['engine_no'] ?? null,
        $_POST['reg_number'],
        $_POST['make'],
        $_POST['model'] ?? null,
        $_POST['year'] ?? null,
        $_POST['trim'] ?? null,
        $_POST['mileage'] ?? null,
        $_POST['color'] ?? null,
        $_POST['body_style'] ?? null,
        $_POST['transmission'] ?? null,
        $_POST['fuel_type'] ?? null,
        !empty($_POST['auction_id']) ? $_POST['auction_id'] : null,
        $_POST['purchase_date'],
        $_POST['purchase_price'],
        'purchased',
        $_POST['notes'] ?? null,
        $_SESSION['user_id'],
        $_POST['engine_size'] ?? null,
        $_POST['owners'] ?? null,
        $_POST['v5_status'],
        $_POST['number_of_services'] ?? null,
        $_POST['keys_count'] ?? null,
        $_POST['logbook_status'] ?? null,
        $_POST['last_v5c_issued'] ?? null,
        $_POST['tax_status'] ?? null,
        $_POST['tax_due_date'] ?? null,
        $_POST['mot_expiry'] ?? null
      ]);

      $vehicleId = $db->lastInsertId();
      // Handle image uploads
      if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = 'uploads/vehicles/';

        // Create directory if not exists
        if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['images']['tmp_name'][0] as $key => $tmpName) {
          // Get file extension
          $fileExt = pathinfo($_FILES['images']['name'][0], PATHINFO_EXTENSION);
          // Create filename using vehicle ID
          $fileName = 'vehicle_' . $vehicleId . '_' . ($key + 1) . '.' . strtolower($fileExt);
          $targetPath = $uploadDir . $fileName;

          if (move_uploaded_file($tmpName, $targetPath)) {
            // Insert image record
            $Query = $db->prepare("INSERT INTO vehicle_images (vehicle_id, image_path, is_primary, uploaded_by) VALUES (?, ?, ?, ?)");
            $Query->execute([
              $vehicleId,
              'uploads/vehicles/' . $fileName,
              ($key === 0) ? 1 : 0, // First image as primary
              $_SESSION['user_id']
            ]);
          }
        }
      }

      // $db->commit();
      setSuccess('Vehicle and images added successfully!');
      header("Location: view-vehicle.php?id=$vehicleId");
      exit;
    } catch (PDOException $e) {
      $errors[] = "Database error: " . $e->getMessage();
      setFormErrors($errors);
      saveFormData($_POST);
      setError('Please correct the errors below');
      header("Location: add-vehicle.php?error=1");
      exit;
    }
  } else {
    setFormErrors($errors);
    saveFormData($_POST);
    setError('Please correct the errors below');
    header("Location: add-vehicle.php?error=1");
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
      // linkRTL.setAttribute('disabled', true);
      // userLinkRTL.setAttribute('disabled', true);
    }
  </script>
  <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
  <style>
    .error-text {
      color: #dc3545;
      font-size: 0.875em;
      margin-top: 0.25rem;
      display: block;
    }

    .is-invalid {
      border-color: #dc3545 !important;
    }

    .error-alert {
      margin-bottom: 1.5rem;
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
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-12">
            <div class="mb-8">
              <h2 class="mb-2">Add New Vehicle</h2>
              <h5 class="text-body-tertiary fw-semibold">Add new vehicle which is purchased</h5>
            </div>
            
            <div class="row border-top flex-between-center mb-4 g-3">
              <div class="container">
                <div class=" col-md-4 p-5">
                  <input style="text-transform: uppercase;" class="form-control <?php echo isset($errors['regNumber']) ? 'is-invalid' : ''; ?>" type="text" id="regNumber" placeholder="Enter UK Reg (e.g., GK11NLF)" name="regNumber">
                  <br>
                  <button class="btn btn-success" onclick="fetchVehicleDetails()">Get Vehicle Info from DVLA</button>
                </div>

                <script>
                  function fetchVehicleDetails() {
                    const regNumber = document.getElementById('regNumber').value.trim();
                    if (!regNumber) {
                      alert('Please enter a registration number');
                      return;
                    }

                    console.log('Fetching details for:', regNumber); // Debug log

                    // Show loading state
                    const fetchBtn = document.querySelector('button[onclick="fetchVehicleDetails()"]');
                    fetchBtn.disabled = true;
                    fetchBtn.innerHTML = 'Loading...';

                    fetch('fetch-vehicle.php', {
                        method: 'POST',
                        headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `reg_number=${encodeURIComponent(regNumber)}`
                      })
                      .then(response => {
                        console.log('Response received:', response); // Debug log
                        return response.json();
                      })
                      .then(data => {
                        console.log('Data received:', data); // Debug log
                        if (data.error) {
                          alert('Error: ' + data.error);
                        } else {
                          // Populate the form fields with fetched data
                          populateFormWithData(data.data);

                        }
                      })
                      .catch(error => {
                        console.error('Fetch error:', error); // Debug log
                        alert('Error fetching vehicle details: ' + error.message);
                      })
                      .finally(() => {
                        fetchBtn.disabled = false;
                        fetchBtn.innerHTML = '🚗 Fetch Vehicle Details';
                      });
                  }

                  function populateFormWithData(data) {
                    console.log('Populating form with:', data); // Debug log

                    if (!data) {
                      alert('No vehicle data received');
                      return;
                    }

                    // Map API field names to form field IDs/names
                    const fieldMap = {
                      'make': data.make,
                      'model': data.model,
                      'year': data.yearOfManufacture,
                      'color': data.colour,
                      'engine_size': data.engineCapacity,
                      'fuel_type': data.fuelType,
                      'mot_expiry': data.motExpiryDate,
                      'last_v5c_issued': data.dateOfLastV5CIssued,
                      'tax_status': data.taxStatus,
                      'tax_due_date': data.taxDueDate,
                    };

                    // Populate fields - always update when fetching new data
                    Object.keys(fieldMap).forEach(fieldName => {
                      const field = document.querySelector(`[name="${fieldName}"]`);
                      if (field && fieldMap[fieldName] && fieldMap[fieldName] !== 'N/A') {
                        field.value = fieldMap[fieldName];
                        console.log(`Updated ${fieldName}:`, fieldMap[fieldName]);
                      }
                    });

                    // Auto-fill registration number if empty
                    const regField = document.querySelector('[name="reg_number"]');
                    if (regField && data.registrationNumber && data.registrationNumber !== 'N/A') {
                      regField.value = data.registrationNumber;
                    }

                    //alert('Vehicle details fetched successfully! Form has been auto-filled.');
                  }
                </script>



                <form method="post" enctype="multipart/form-data">
                  <br>
                  <div class="row">
                    <div class="card col-md-4 pt-4 pb-4">
                      <h4>Vehicle Details</h4>

                      <div class="form-group">
                        <label>Registration Number<span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo hasError('reg_number') ? 'is-invalid' : ''; ?>" name="reg_number"
                          value="<?php echo getFormData('reg_number'); ?>"
                          title="Valid UK registration number"
                          required>
                           <?php if (hasError('reg_number')): ?>
                              <div class="error-text"><?php echo getFormError('reg_number'); ?></div>
                            <?php endif; ?>
                        
                      </div>
                      <div class="form-group">
                        <label for="make">Make<span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo hasError('make') ? 'is-invalid' : ''; ?>" id="make" name="make" value="<?php echo getFormData('make'); ?>">
                        <?php if (hasError('make')): ?>
                              <div class="error-text"><?php echo getFormError('make'); ?></div>
                            <?php endif; ?>
                      </div>
                      <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" class="form-control" id="model" name="model" value="<?php echo getFormData('model'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" class="form-control" id="year" name="year" min="1900" max="<?= date('Y') + 1 ?>" value="<?php echo getFormData('year'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="trim">Trim</label>
                        <input type="text" class="form-control" id="trim" name="trim" value="<?php echo getFormData('trim'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="color">Color</label>
                        <input type="text" class="form-control" id="color" name="color" value="<?php echo getFormData('color'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="engine_no">Engine Number</label>
                        <input type="text" class="form-control" id="engine_no" name="engine_no" value="<?php echo getFormData('engine_no'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="VIN">VIN / Chasis Number</label>
                        <input type="text" class="form-control" id="VIN" name="VIN" value="<?php echo getFormData('VIN'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="mileage">Mileage</label>
                        <input type="number" class="form-control" id="mileage" name="mileage" value="<?php echo getFormData('mileage'); ?>">
                      </div>
                      <div class="form-group">
                        <label>Owners</label>
                        <input type="number" class="form-control" name="owners" value="<?php echo getFormData('owners') ?? null; ?>">
                      </div>
                      <div class="form-group">
                        <label>Keys</label>
                        <input type="number" class="form-control" name="keys_count" value="<?php echo getFormData('keys_count'); ?>">
                      </div>
                      <div class="form-group">
                        <label>V5 Status</label>
                        <select name="v5_status" class="form-control">
                          <option value="Yes" <?php echo getFormData('v5_status') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                          <option value="No" <?php echo getFormData('v5_status') == 'No' ? 'selected' : '' ?>>No</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Last V5C Issued</label>
                        <input type="text" class="form-control" name="last_v5c_issued" value="<?php echo getFormData('last_v5c_issued'); ?>">
                      </div>
                      <div class="form-group">
                        <label>Road Tax Status</label>
                        <input type="text" class="form-control" name="tax_status" value="<?php echo getFormData('tax_status'); ?>">
                      </div>
                      <div class="form-group">
                        <label>Road Tax Due Date</label>
                        <input type="text" class="form-control" name="tax_due_date" value="<?php echo getFormData('tax_due_date'); ?>">
                      </div>

                      <div class="form-group">
                        <label>Number of Service</label>
                        <input type="number" class="form-control" name="number_of_services" value="<?php echo getFormData('number_of_services'); ?>">
                      </div>
                      <div class="form-group">
                        <label>MOT Expiry ()</label>
                        <input type="text" class="form-control" name="mot_expiry" value="<?php echo getFormData('mot_expiry'); ?>">
                      </div>
                      <div class="form-group">
                        <label>Logbook Status</label>
                        <select name="logbook_status" class="form-control">
                          <option value="Present" <?php echo getFormData('logbook_status') == 'Present' ? 'selected' : '' ?>>Present</option>
                          <option value="Missing" <?php echo getFormData('logbook_status') == 'Missing' ? 'selected' : '' ?>>Missing</option>
                        </select>
                      </div>
                    </div>
                    <div class="card col-md-4 pt-4 pb-4">
                      <h4>Specifications</h4>
                      <div class="form-group">
                        <label>Engine Size (CC):</label>

                        <input type="number" class="form-control" step="0.1" name="engine_size" value="<?php echo getFormData('engine_size'); ?>">
                      </div>
                      <div class="form-group">
                        <label for="body_style">Body Style</label>
                        <select class="form-control" id="body_style" name="body_style">
                          <option value="">-- Select Body Style --</option>
                          <option value="sedan">Sedan</option>
                          <option value="coupe">Coupe</option>
                          <option value="suv">SUV</option>
                          <option value="truck">Truck</option>
                          <option value="van">Van</option>
                          <option value="hatchback">Hatchback</option>
                          <option value="other">Other</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="transmission">Transmission</label>
                        <select class="form-control" id="transmission" name="transmission">
                          <option value="">-- Select Transmission --</option>
                          <option value="automatic">Automatic</option>
                          <option value="manual">Manual</option>
                          <option value="other">Other</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="fuel_type">Fuel Type</label>
                        <input type="text" name=fuel_type class="form-control" value="<?php echo getFormData('fuel_type'); ?>">

                      </div>

                      <h4 class="mt-4">Purchase Details</h4>
                      <div class="form-group">
                        <label for="auction_id">Auction</label>
                        <select class="form-control" id="auction_id" name="auction_id">
                          <option value="">-- Select Auction --</option>
                          <?php foreach ($auctions as $auction): ?>
                            <option value="<?= $auction['auction_id'] ?>">
                              <?= htmlspecialchars($auction['auction_name']) ?> - <?= htmlspecialchars($auction['city']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="purchase_date">Purchase Date<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo getFormData('purchase_date'); ?>" required>
                      </div>
                      <div class="form-group">
                        <label for="purchase_price">Purchase Price<span class="text-danger">*</span></label>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">£</span>
                          </div>
                          <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" value="<?php echo getFormData('purchase_price'); ?>" required>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo getFormData('notes'); ?>                         
                        </textarea>
                      </div>
                    </div>
                    <div class="card col-md-4 pt-4 pb-4">
                      <h4>Vehicle Images</h4>
                      <div class="form-group">
                        <div class="custom-file">
                          <label class="custom-file-label" for="images">Choose images (max 10)</label>
                          <input type="file" class="custom-file-input" id="images" name="images[]" multiple accept="image/*">
                        </div>
                        <small class="form-text text-muted">
                          First image will be used as primary. Max 5MB per image.
                        </small>
                        <div id="image-preview" class="mt-2"></div>
                      </div>
                    </div>
                  </div>
                  <!-- <div id="result"></div> -->

                  <div class="row">
                    <div class="col-md-12">
                      <hr />
                    </div>
                  </div>
                  <div class="row mt-3">
                    <div class="col-md-12">
                      <button type="submit" class="btn btn-primary">Add Vehicle</button>
                      <a href="view-all-vehicles.php" class="btn btn-secondary">Cancel</a>
                    </div>
                  </div>
                </form>
              </div>


            </div>
          </div>

        </div>
      </div>


    </div>

    <!-- Footer -->
    <?php   include_once("footer.php"); ?>
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
    // Preview selected images
    document.getElementById('images').addEventListener('change', function(e) {
      const preview = document.getElementById('image-preview');
      preview.innerHTML = '';

      if (this.files) {
        [].forEach.call(this.files, function(file) {
          if (!file.type.match('image.*')) return;

          const reader = new FileReader();
          reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-thumbnail mr-2 mb-2';
            img.style.maxHeight = '100px';
            preview.appendChild(img);
          }
          reader.readAsDataURL(file);
        });
      }
    });

    // Show filename in file input
    document.querySelector('.custom-file-input').addEventListener('change', function(e) {
      const label = this.nextElementSibling;
      const files = [].map.call(this.files, function(file) {
        return file.name;
      }).join(', ');
      label.textContent = files || 'Choose images';
    });

</script>
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Submitting...';
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