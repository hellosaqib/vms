<?php
//$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
$pageTitle = 'Edit Finance Company';

$formData=[];
$company_id = $_GET['company_id'];

$Query = $db->prepare("SELECT * FROM finance_companies WHERE finance_company_id = ?");
$Query->execute([$company_id]);
$formData = $Query->fetch(PDO::FETCH_ASSOC); 

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btnUpdate'])) {
        $companyId = $_POST['txtCompanyId'];
        $companyName = $_POST['txtCompanyName'];
        $contactPerson = $_POST['txtContactPerson'];
        $Email = $_POST['txtEmail'];
        $Phone = $_POST['txtPhone'];
        $Address = $_POST['txtAddress'];
        $AccountDetails = $_POST['txtAccountDetails'];
    
        $is_active = $_POST['is_active'];
        if($is_active == 'on'){$is_active="active";}else{$is_active="inactive";}

        
        $update_sql = "UPDATE finance_companies 
                          SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, account_details = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                          WHERE finance_company_id = ?";
            
            $stmt = $db->prepare($update_sql);
            $stmt->execute([$companyName,$contactPerson,$Email,$Phone,$Address,$AccountDetails,$is_active,$companyId]);
            
            $_SESSION['success_message'] = "Finance Company #{$companyName} updated successfully!";
            header("Location: view-all-finance-companies.php");
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
                            <h2 class="mb-2">Edit Finance COmpany</h2>
                            <h5 class="text-body-tertiary fw-semibold">Edit finance company working with you</h5>
                        </div>
                        <form method="post" name="form1">
                            <input class="form-control" type="hidden" id="txtCompanyId" name="txtCompanyId" value="<?= $_GET['company_id']; ?>" required>
                            <div class="col-5 p-3" style="border:1px solid #ccc;">
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtCompanyName">Company Name</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtCompanyName" name="txtCompanyName" value="<?= $formData['company_name']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtContactPerson">Contact Person</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtContactPerson" name="txtContactPerson" value="<?= $formData['contact_person']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="txtEmail">Email</label></div>
                                    <div class="col-8"><input class="form-control" type="email" id="txtEmail" name="txtEmail" value="<?= $formData['email']; ?>" required></div>
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
                                    <div class="col-4"><label for="txtAccountDetails">Account Details</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="txtAccountDetails" name="txtAccountDetails" value="<?= $formData['account_details']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="is_active">Status</label></div>
                                    <div class="col-8">
                                        <div class="form-check form-switch">
                                            <?php if($formData['status']=='active'){ ?>
                                                <input class="form-check-input" id="flexSwitchCheckChecked" name="is_active" type="checkbox" checked="" />
                                            <?php }else{ ?>
                                                    <input class="form-check-input" id="flexSwitchCheckChecked" name="is_active" type="checkbox" />
                                                <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"></div>
                                    <div class="col-8"><button type="submit" name="btnUpdate" class="btn btn-primary">Update Company Record</button></div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            
        </div>
        <!-- Footer -->
        <?php // include_once("footer.php"); ?>
        <footer class="footer">
            <div class="row g-0 justify-content-between align-items-center h-100">
                <div class="col-12 col-sm-auto text-center">
                    <p class="mb-0 mt-2 mt-sm-0 text-body">
                    </p>
                </div>
                <div class="col-12 col-sm-auto text-center">
                    <p class="mb-0 text-body-tertiary text-opacity-85">Thank you for creating with Saqib Abbas
                    <span class="d-none d-sm-inline-block"></span><span class="d-none d-sm-inline-block mx-1">|</span><br class="d-sm-none" />2025 &copy;<a class="mx-1" href="https://swiftcodershub.com">Swift Coders</a> v1.20.1</p>
                </div>
            </div>
        </footer>

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