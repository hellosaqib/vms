<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Add New Auction";
$user = $auth->getUser();
$roleName = $auth->getRoleName();

if (!hasPermission('manage_auctions')) {
  header("Location: access-denied.php");
  exit;
}

// ==============================================
// PART 1: HANDLE FORM SUBMISSION (POST REQUEST)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate and process form data
  $required = ['txtName', 'txtCity'];
  $errors = [];

  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      $fieldName = str_replace('txt', '', $field);
      $errors[$field] = ucfirst($fieldName) . ' is required';
    }
  }

  // Additional validation
  if (!empty($_POST['txtEmail']) && !filter_var($_POST['txtEmail'], FILTER_VALIDATE_EMAIL)) {
    $errors['txtEmail'] = 'Please enter a valid email address';
  }

  if (!empty($_POST['txtPhone']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $_POST['txtPhone'])) {
    $errors['txtPhone'] = 'Please enter a valid phone number';
  }

  $Date = time();

  // If NO ERRORS - insert into database
  if (empty($errors)) {
    try {
      $stmt = $db->prepare("
                INSERT INTO auctions (
                    auction_name, address, city, contact_phone, contact_email, website, notes, created
                ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      $stmt->execute([
        $_POST['txtName'],
        $_POST['txtAddress'] ?? null,
        $_POST['txtCity'],
        $_POST['txtPhone'] ?? null,
        $_POST['txtEmail'] ?? null,
        $_POST['txtWebsite'] ?? null,
        $_POST['txtNotes'] ?? null,
        $Date
      ]);

      // SUCCESS - Redirect with success message
      setSuccess('Auction added successfully!');
      header("Location: view-all-auctions.php");
      exit;
    } catch (PDOException $e) {
      // Database error
      $errors['general'] = "Database error: " . $e->getMessage();

      setFormErrors($errors);
      saveFormData($_POST);
      setError('Please correct the errors below');
      // Redirect back to self with error flag
      header("Location: add-auction.php?error=1");
      exit;
    }
  }
      setFormErrors($errors);
      saveFormData($_POST);
      setError('Please correct the errors below');
      // Redirect back to self with error flag
      header("Location: add-auction.php?error=1");
      exit;
    // Redirect back to SAME PAGE with error flag
  header("Location: add-auction.php?error=1");
  exit;
}

// Get auctions for dropdown (if needed elsewhere)
$auctions = $db->query("SELECT * FROM auctions ORDER BY auction_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Add these meta tags to prevent caching -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

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
  <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
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
      <!-- Show errors if exists -->
      <?php displayMessage(); ?>
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-sm-12 col-md-12 col-xl-4">
            <div class="mb-8">
              <h2 class="mb-2">Add New Auction</h2>
              <h5 class="text-body-tertiary fw-semibold">Add new auction from where vehicle can be purchased</h5>
            </div>
            
            <div class="card">
              <div class="card-header">
                <h4>Auction Details</h4>

              </div>
              <div class="card-body">
                <div class="row flex-between-center mb-4 g-3">
                  <div class="container">
                    <form method="post">

                      <div class="row">
                        <div class="col-12">

                          <!-- Auction Name Field -->
                          <div class="form-group mb-3">
                            <label for="txtName" class="form-label">Auction Name*</label>
                            <input type="text"
                              class="form-control <?php echo hasError('txtName') ? 'is-invalid' : ''; ?>"
                              id="txtName"
                              name="txtName"
                              value="<?php echo getFormData('txtName'); ?>" required>
                            <?php if (hasError('txtName')): ?>
                              <div class="error-text"><?php echo getFormError('txtName'); ?></div>
                            <?php endif; ?>
                          </div>

                          <!-- Address Field -->
                          <div class="form-group mb-3">
                            <label for="txtAddress" class="form-label">Address</label>
                            <input type="text"
                              class="form-control"
                              name="txtAddress"
                              value="<?php echo getFormData('txtAddress'); ?>">
                          </div>

                          <!-- City Field -->
                          <div class="form-group mb-3">
                            <label for="txtCity" class="form-label">City*</label>
                            <input type="text"
                              class="form-control <?php hasError('txtCity') ? 'is-invalid' : ''; ?>"
                              id="txtCity"
                              name="txtCity"
                              value="<?php echo getFormData('txtCity'); ?>"
                              required>
                            <?php if (hasError('txtCity')): ?>
                              <div class="error-text"><?php echo getFormError('txtCity'); ?></div>
                            <?php endif; ?>
                          </div>

                          <!-- Phone Field -->
                          <div class="form-group mb-3">
                            <label for="txtPhone" class="form-label">Phone</label>
                            <input type="text"
                              class="form-control"
                              id="txtPhone"
                              name="txtPhone"
                              value="<?php echo getFormData('txtPhone'); ?>">
                          </div>

                          <!-- Email Field -->
                          <div class="form-group mb-3">
                            <label for="txtEmail" class="form-label">Email</label>
                            <input type="email"
                              class="form-control"
                              id="txtEmail"
                              name="txtEmail"
                              value="<?php echo getFormData('txtEmail'); ?>">
                          </div>

                          <!-- Website Field -->
                          <div class="form-group mb-3">
                            <label for="txtWebsite" class="form-label">Website</label>
                            <input type="text"
                              class="form-control"
                              id="txtWebsite"
                              name="txtWebsite"
                              value="<?php echo getFormData('txtWebsite'); ?>">
                          </div>

                          <!-- Notes Field -->
                          <div class="form-group mb-3">
                            <label for="txtNotes" class="form-label">Notes</label>
                            <textarea class="form-control "
                              id="txtNotes"
                              name="txtNotes"
                              rows="3"><?php echo getFormData('txtNotes'); ?></textarea>
                          </div>
                        </div>
                      </div>


                      <div class="row">
                        <div class="col-12">
                          <hr />
                        </div>
                      </div>

                      <div class="row mt-3">
                        <div class="col-12">
                          <button type="submit" class="btn btn-success">Add Auction</button>
                          <a href="view-all-auctions.php" class="btn btn-secondary">Cancel</a>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php include_once('footer.php'); ?>
    </div>
  </main>

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

  <script>
    // Clear error styling when user starts typing
    document.querySelectorAll('input, textarea').forEach(function(field) {
      field.addEventListener('input', function() {
        if (this.classList.contains('is-invalid')) {
          this.classList.remove('is-invalid');
          // Find and hide error message if exists
          var errorDiv = this.nextElementSibling;
          if (errorDiv && errorDiv.classList.contains('error-text')) {
            errorDiv.style.display = 'none';
          }
        }
      });
    });

    // Remove error parameter from URL after page loads
    window.addEventListener('load', function() {
      if (window.location.search.includes('error=')) {
        // Create clean URL without error parameter
        var cleanURL = window.location.pathname;
        var searchParams = new URLSearchParams(window.location.search);
        searchParams.delete('error');

        if (searchParams.toString()) {
          cleanURL += '?' + searchParams.toString();
        }

        // Update URL without reloading page
        if (window.history.replaceState) {
          window.history.replaceState({}, document.title, cleanURL);
        }
      }
    });
  </script>
</body>

</html>