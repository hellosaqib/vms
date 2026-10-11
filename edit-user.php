<?php
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
$pageTitle = 'Manage Users - Smartways';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $userId = $_POST['user_id'];
        $data = [
            'username' => $_POST['username'],
            'fullname' => $_POST['fullname'],
            'email' => $_POST['email'],
            'role_id' => $_POST['role_id'],
            'commission_rate' => $_POST['commission_rate'],
        ];
    
        $is_active = $_POST['is_active'];
        if($is_active == 'on'){$is_active=1;}else{$is_active=0;}
        $data['is_active'] = $is_active;
    
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }

        if ($userManager->updateUser($userId, $data)) {
            $success = 'User updated successfully';
        } else {
            $error = 'Failed to update user';
        }
    }
}

$user = $auth->getUser();
$roleName = $auth->getRoleName();
$users = $userManager->getAllUsers();
$roles = $userManager->getAllRoles();
$editUser = $userManager->getUser($_GET['userid']);
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
                    <div class="col-sm-12 col-md-12 col-xl-12">
                        <div class="mb-8">
                            <h2 class="mb-2">Edit User</h2>
                            <h5 class="text-body-tertiary fw-semibold">Edit user who uses your admin panel</h5>
                        </div>
                        <form method="post" name="form1">
                            <input class="form-control" type="hidden" id="username" name="user_id" value="<?= $_GET['userid']; ?>" required>
                            <div class="col-12 p-3" style="border:1px solid #ccc;">
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="username">Username</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="username" name="username" value="<?= $editUser['username']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="fullname">Full Name</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="fullname" name="fullname" value="<?= $editUser['full_name']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="email">Email</label></div>
                                    <div class="col-8"><input class="form-control" type="email" id="email" name="email" value="<?= $editUser['email']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="password">Password</label></div>
                                    <div class="col-8"><input class="form-control" type="text" id="password" name="password"></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="role_id">Role</label></div>
                                    <div class="col-8">
                                        <select id="role_id" class="form-control" name="role_id" required>
                                            <option>Select New Role</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?= $role['id'] ?>" <?php if($editUser['role_id']==$role['id']) {echo("selected");} ?>><?= htmlspecialchars($role['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="commission_rate">Commission</label></div>
                                    <div class="col-8"><input class="form-control" type="number" id="commission_rate" name="commission_rate" step="0.01" value="<?= $editUser['commission_rate']; ?>" required></div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"><label for="is_active">Status</label></div>
                                    <div class="col-8">
                                        <div class="form-check form-switch">
                                            <?php if($editUser['is_active']==1){ ?>
                                                <input class="form-check-input" id="flexSwitchCheckChecked" name="is_active" type="checkbox" checked="" />
                                            <?php }else{ ?>
                                                    <input class="form-check-input" id="flexSwitchCheckChecked" name="is_active" type="checkbox" />
                                                <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row flex-between-center mb-4 g-3">
                                    <div class="col-4"></div>
                                    <div class="col-8"><button type="submit" name="update_user" class="btn btn-primary">Update User</button></div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <!-- Edit User Modal (shown when edit button is clicked) -->
            <div id="editUserModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Edit User</h3>
                    <form method="post" id="editUserForm">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="edit_password" name="password">
                        </div>

                        <div class="form-group">
                            <label for="edit_role_id">Role</label>
                            <select id="edit_role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="form-check form-switch">
                                <input class="form-check-input" id="flexSwitchCheckChecked" name="is_active" type="checkbox" checked="" />
                                <label class="form-check-label" for="flexSwitchCheckChecked">Checked switch checkbox input</label>
                            </div>
                        </div>

                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </form>
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