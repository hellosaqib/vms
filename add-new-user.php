<?php
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = 'Manage Users - BritDrive Admin Panel';

if (!hasPermission('manage_settings')) {
    header("Location: access-denied.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['create_user'])) {
      $errors = [];
// Validate required fields
        if (empty($_POST['username'])) {
            $errors['username'] = 'Username is required';
        }
        
        if (empty($_POST['fullname'])) {
            $errors['fullname'] = 'Full name is required';
        }
        
        if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        if (empty($_POST['password'])) {
            $errors['password'] = 'Password is required';
        } 
        // elseif (strlen($_POST['password']) < 6) {
        //     $errors['password'] = 'Password must be at least 6 characters';
        // }
        
        if (empty($_POST['role_id'])) {
            $errors['role_id'] = 'Role selection is required';
        }
        
        // Check if username already exists
        if (empty($errors['username'])) {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([trim($_POST['username'])]);
                if ($stmt->fetch()) {
                    $errors['username'] = 'Username already exists';
                }
            } catch (PDOException $e) {
                $errors[] = 'Error checking username: ' . $e->getMessage();
            }
        }
        
        // Check if email already exists
        if (empty($errors['email'])) {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([trim($_POST['email'])]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email already registered';
                }
            } catch (PDOException $e) {
                $errors[] = 'Error checking email: ' . $e->getMessage();
            }
        }
        
        // If validation failed
        if (!empty($errors)) {
            setFormErrors($errors);
            saveFormData($_POST);
            //setError('Please correct the errors below');
            header("Location: add-new-user.php");
            exit;
        }
    $username = $_POST['username'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $roleId = $_POST['role_id'];
    $commission = $_POST['commission_rate'];
    $is_active = $_POST['is_active'];
    if($is_active == 'on'){$is_active=1;}else{$is_active=0;}
    if ($userManager->createUser($username, $fullname, $email, $password, $roleId, $commission, $is_active)) {
      // Clear form data
                clearFormData();
                clearFormErrors();
                // SUCCESS - set message and redirect
                setSuccess('User created successfully!');
                header("Location: manage-users.php");
                exit;
    } else {
                  // DATABASE ERROR - save form data and show error
            saveFormData($_POST);
            setError('Error creating user: ' . $e->getMessage());
            header("Location: add-new-user.php");
            exit;
    }
  } elseif (isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];

    if ($userManager->deleteUser($userId)) {
      $success = 'User deleted successfully';
    } else {
      $error = 'Failed to delete user';
    }
  }
}

$user = $auth->getUser();
$roleName = $auth->getRoleName();
$users = $userManager->getAllUsers();
$roles = $userManager->getAllRoles();
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
            <div class="mb-8">
              <h2 class="mb-2">Add New User</h2>
              <h5 class="text-body-tertiary fw-semibold">Add new user who uses your admin panel</h5>
            </div>
            
            <form method="post">
              <div class="col-12 p-3" style="border:1px solid #ccc;">
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="username">Username</label></div>
                  <div class="col-9"><input class="form-control" type="text" id="username" name="username" value="<?php echo getFormData('username') ?? null; ?>" required></div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="fullname">Full Name</label></div>
                  <div class="col-9"><input class="form-control" type="text" id="fullname" name="fullname" value="<?php echo getFormData('fullname') ?? null; ?>" required></div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="email">Email</label></div>
                  <div class="col-9"><input class="form-control" type="email" id="email" name="email" value="<?php echo getFormData('email') ?? null; ?>" required></div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="password">Password</label></div>
                  <div class="col-9"><input class="form-control" type="password" id="password" name="password" required></div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="role_id">Role</label></div>
                  <div class="col-9">
                    <select id="role_id" class="form-control" name="role_id" required>
                      <?php     $selectedRoleId = getFormData('role_id');

                       foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?php echo ((string)$role['id'] === (string)$selectedRoleId) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="commission_rate">Commission</label></div>
                  <div class="col-9"><input class="form-control" type="number" id="commission_rate" name="commission_rate" step="0.01" value="<?php echo getFormData('commission_rate') ?? null; ?>" required></div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"><label for="flexSwitchCheckChecked">Status</label></div>
                  <div class="col-9">
                    <div class="form-switch">
                      <input class="form-check-input" name="is_active" id="flexSwitchCheckChecked" type="checkbox" checked="" />
                    </div>
                  </div>
                </div>
                <div class="row flex-between-center mb-4 g-3">
                  <div class="col-3"></div>
                  <div class="col-9"><button type="submit" name="create_user" class="btn btn-primary">Create User</button></div>
                </div>
              </div>
            </form>
          </div>

        </div>
      </div>

      
    </div>

    
    <!-- Footer -->
     <?php include_once("footer.php"); ?>
    </div>
    <!-- Chat Now -->
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