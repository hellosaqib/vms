<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Edit Auction";
$user = $auth->getUser();

if (!hasPermission('manage_settings')) {
    setError('Access denied');
    header("Location: access-denied.php");
    exit;
}

$auction_id = $_GET['id'] ?? 0;

// ==============================================
// PART 1: FORM SUBMISSION HANDLER (POST)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate required fields
    if (empty($_POST['txtAuctionName'])) {
        $errors['txtAuctionName'] = 'Auction name is required';
    }
    
    if (empty($_POST['txtCity'])) {
        $errors['txtCity'] = 'City is required';
    }
    
    // Validate email format if provided
    if (!empty($_POST['txtEmail']) && !filter_var($_POST['txtEmail'], FILTER_VALIDATE_EMAIL)) {
        $errors['txtEmail'] = 'Please enter a valid email address';
    }
    
    // Validate phone format if provided
    if (!empty($_POST['txtPhone']) && !preg_match('/^[\d\s\-\+\(\)]+$/', $_POST['txtPhone'])) {
        $errors['txtPhone'] = 'Please enter a valid phone number';
    }
    
    // Validate website URL format if provided
    if (!empty($_POST['txtWebSite']) && !filter_var($_POST['txtWebSite'], FILTER_VALIDATE_URL)) {
        $errors['txtWebSite'] = 'Please enter a valid website URL';
    }
    
    // If validation failed
    if (!empty($errors)) {
        setFormErrors($errors);
        saveFormData($_POST);
        setError('Please correct the errors below');
        header("Location: edit-auction.php?id=" . $auction_id);
        exit;
    }
    
    // Validation passed - update auction
    try {
        // Prepare data
        $data = [
            'auction_id' => (int)$_POST['txtAuctionId'],
            'auction_name' => trim($_POST['txtAuctionName']),
            'address' => trim($_POST['txtAddress'] ?? ''),
            'city' => trim($_POST['txtCity']),
            'contact_phone' => trim($_POST['txtPhone'] ?? ''),
            'contact_email' => trim($_POST['txtEmail'] ?? ''),
            'website' => trim($_POST['txtWebSite'] ?? ''),
            'notes' => trim($_POST['txtNotes'] ?? '')
        ];
        
        // Update auction
        $update_sql = "UPDATE auctions 
                      SET auction_name = ?, address = ?, city = ?, contact_phone = ?, 
                          contact_email = ?, website = ?, notes = ?
                      WHERE auction_id = ?";
        
        $stmt = $db->prepare($update_sql);
        $stmt->execute([
            $data['auction_name'],
            $data['address'],
            $data['city'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['website'],
            $data['notes'],
            $data['auction_id']
        ]);
        
        // SUCCESS - set message and redirect
        setSuccess('Auction updated successfully!');
        header("Location: view-all-auctions.php");
        exit;
        
    } catch (PDOException $e) {
        // DATABASE ERROR - save form data and show error
        saveFormData($_POST);
        setError('Database error: ' . $e->getMessage());
        header("Location: edit-auction.php?id=" . $auction_id);
        exit;
    }
}

// ==============================================
// PART 2: PAGE LOAD (GET REQUEST)
// ==============================================

// Fetch auction details
try {
    $stmt = $db->prepare("SELECT * FROM auctions WHERE auction_id = ?");
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auction) {
        setError('Auction not found');
        header("Location: view-all-auctions.php");
        exit;
    }
} catch (PDOException $e) {
    setError('Error loading auction: ' . $e->getMessage());
    header("Location: view-all-auctions.php");
    exit;
}

// Check if we have form data from error redirect, otherwise use database data
$formData = $_SESSION['form_data'] ?? $auction;

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
                    <div class="col-sm-12 col-md-12 col-xl-4">
                        <div class="mb-8">
                            <h2 class="mb-2">Edit Auction Details</h2>
                            <h5 class="text-body-tertiary fw-semibold">Edit Auction Details who buy cars from you</h5>
                        </div>
                        <form method="post" name="form1">
                            <input class="form-control" type="hidden" id="txtAuctionId" name="txtAuctionId" value="<?= $auction_id ?>">
                            <div class="col-12 p-3" style="border:1px solid #ccc;">
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtAuctionName">Auction Name <span class="text-danger">*</span></label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtAuctionName" name="txtAuctionName" value="<?php echo getFormData('auction_name'); ?>" required>
                                        <?php if (hasError('auction_name')): ?>
                                            <div class="error-text"><?php echo getFormError('auction_name'); ?></div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtAddress">Address</label></div>
                                    <div class="col-8"><textarea class="form-control" id="txtAddress" name="txtAddress"><?php echo getFormData('address'); ?></textarea></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtCity">City <span class="text-danger">*</span></label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtCity" name="txtCity" value="<?php echo getFormData('city'); ?>" required>
                                        <?php if (hasError('txtCity')): ?>
                                            <div class="error-text"><?php echo getFormError('txtCity'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtPhone">Phone</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtPhone" name="txtPhone" value="<?php echo getFormData('contact_phone'); ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtEmail">Email</label></div>
                                    <div class="col-8"><input class="form-control" type="email" id="txtEmail" name="txtEmail" value="<?php echo getFormData('contact_email'); ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtWebSite">Website</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtWebSite" name="txtWebSite" value="<?php echo getFormData('website'); ?>" ></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtNotes">Notes</label></div>
                                    <div class="col-8"><textarea class="form-control" id="txtNotes" name="txtNotes"><?php echo getFormData('notes'); ?></textarea></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"></div>
                                    <div class="col-8"><button type="submit" name="btnUpdate" class="btn btn-primary">Update Auction Record</button></div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        <?php include_once("footer.php"); ?>
            
        </div>
        <!-- Footer -->
    

    </main>
    <!-- ===============================================-->
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