<?php
//$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
$pageTitle = 'Edit Customer Details';

$formData=[];
$customer_id = $_GET['customerid'];

$Query = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
$Query->execute([$customer_id]);
$formData = $Query->fetch(PDO::FETCH_ASSOC); 

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btnUpdate'])) {
        $customerId = $_POST['txtCustomerId'];
        $FirstName = $_POST['txtFirstName'];
        $LastName = $_POST['txtLastName'];
        $Email = $_POST['txtEmail'];
        $Phone = $_POST['txtPhone'];
        $Address = $_POST['txtAddress'];
        $City = $_POST['txtCity'];
        $State = $_POST['txtState'];
        $PostCode = $_POST['txtPostCode'];
        $Notes = $_POST['txtNotes'];
    
        $update_sql = "UPDATE customers 
                          SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, post_code = ?, notes = ?
                          WHERE customer_id = ?";
            
            $stmt = $db->prepare($update_sql);
            $stmt->execute([$FirstName,$LastName,$Email,$Phone,$Address,$City,$State,$PostCode,$Notes,$customerId]);
            
            $_SESSION['success_message'] = "Customer Details updated successfully!";
            header("Location: manage-customers.php");
            exit();
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
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12 col-xl-12 col-xxl-6">
                        <div class="mb-8">
                            <h2 class="mb-2">Edit Customer Details</h2>
                            <h5 class="text-body-tertiary fw-semibold">Edit Customer Details who buy cars from you</h5>
                        </div>
                        <form method="post" name="form1">
                            <input class="form-control" type="hidden" id="txtCustomerId" name="txtCustomerId" value="<?= $customer_id ?>" required>
                            <div class="col-5 p-3" style="border:1px solid #ccc;">
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtFirstName">First Name</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtFirstName" name="txtFirstName" value="<?= $formData['first_name']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtLastName">Last Name</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtLastName" name="txtLastName" value="<?= $formData['last_name']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtEmail">Email</label></div>
                                    <div class="col-8"><input class="form-control" type="email" id="txtEmail" name="txtEmail" value="<?= $formData['email']; ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtPhone">Phone</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtPhone" name="txtPhone" value="<?= $formData['phone']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtAddress">Address</label></div>
                                    <div class="col-8"><textarea class="form-control" id="txtAddress" name="txtAddress"><?= $formData['address']; ?></textarea></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtCity">City</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtCity" name="txtCity" value="<?= $formData['city']; ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtState">State</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtState" name="txtState" value="<?= $formData['state']; ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtPostCode">Post Code</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtPostCode" name="txtPostCode" value="<?= $formData['post_code']; ?>"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtNotes">Notes</label></div>
                                    <div class="col-8"><textarea class="form-control" id="txtNotes" name="txtNotes"><?= $formData['notes']; ?></textarea></div>
                                </div>
                                
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"></div>
                                    <div class="col-8"><button type="submit" name="btnUpdate" class="btn btn-primary">Update Customer Record</button></div>
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