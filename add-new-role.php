<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
$pageTitle = 'Add New Role ';
//include 'header.php';
// Check if user has permission to access settings
if (!$auth->hasPermission('manage_settings')) {
    header('Location: index.php');
    exit;
}

// Get all permissions
$stmt = $db->query("SELECT id, name, description FROM permissions ORDER BY name");
$allPermissions = $stmt->fetchAll();

// Handle form submission
if ($_POST) {
    $role_name = trim($_POST['role_name']);
    $role_description = trim($_POST['role_description']);
    $selectedPermissions = $_POST['permissions'] ?? [];

    // Validate role name
    if (empty($role_name)) {
        $error = "Role name is required";
    } else {
        // Check if role name already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
        $stmt->execute([$role_name]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Role name '{$role_name}' already exists";
        }
    }

    if (!isset($error)) {
        // Use transaction for safety
        $db->beginTransaction();

        try {
            // Insert new role
            $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute([$role_name, $role_description]);
            $new_role_id = $db->lastInsertId();

            // Add role permissions
            foreach ($selectedPermissions as $permission_id) {
                $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, user_id) VALUES (?, ?, NULL)");
                $stmt->execute([$new_role_id, $permission_id]);
            }

            $db->commit();

            $_SESSION['success_message'] = "Role '{$role_name}' created successfully";
            header("Location: view-all-roles.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error creating role: " . $e->getMessage();
        }
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
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/images/britdrive.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/images/britdrive.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/uploads/images/britdrive.png">
    <link rel="shortcut icon" type="image/x-icon" href="/uploads/images/britdrive.ico">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="/uploads/images/britdrive.png">
    <meta name="theme-color" content="#ffffff">
    <script src="vendors/simplebar/simplebar.min.js"></script>
    <script src="assets/js/config.js"></script>

    <!-- ===============================================-->
    <!--    Stylesheets   -->
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
    <!-- In your head section -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        .right-sidebar {
            position: sticky;
            right: 0;
            top: 65px;
            z-index: 100;

        }
    </style>
    <style>
        .metric-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .metric-positive {
            border-left-color: #00d97e;
        }

        .metric-warning {
            border-left-color: #f6c343;
        }

        .metric-danger {
            border-left-color: #e63757;
        }

        .metric-info {
            border-left-color: #2c7be5;
        }

        .alert-badge {
            position: absolute;
            top: -8px;
            right: -8px;
        }

        .sparkline {
            width: 60px;
            height: 30px;
        }
    </style>
</head>

<body>
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <?php include("left-menu.php");
        ?>


        <!-- Main Content -->
        <div class="content">
                <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="page-title">Add New Role</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="settings-dashboard.php">Settings</a></li>
                                <li class="breadcrumb-item"><a href="view-all-roles.php">Roles</a></li>
                                <li class="breadcrumb-item active">Add New Role</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-auto">
                        <a href="view-all-roles.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Roles
                        </a>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Role Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Role Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role_name">Role Name *</label>
                                    <input type="text" class="form-control" id="role_name" name="role_name" 
                                           value="<?= htmlspecialchars($_POST['role_name'] ?? '') ?>" required
                                           placeholder="e.g., Accountant, Supervisor">
                                    <small class="form-text text-muted">Unique name for the new role</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role_description">Description</label>
                                    <input type="text" class="form-control" id="role_description" name="role_description" 
                                           value="<?= htmlspecialchars($_POST['role_description'] ?? '') ?>"
                                           placeholder="e.g., Handles financial transactions and reports">
                                    <small class="form-text text-muted">Brief description of the role's purpose</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Role Permissions</h5>
                        <p class="card-subtitle">Select the default permissions for this role</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allPermissions)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No permissions found in the system.
                            </div>
                        <?php else: ?>
                            <!-- Quick Select Buttons -->
                            <div class="mb-4">
                                <label class="mr-2">Quick Select:</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">
                                    <i class="fas fa-check-square mr-1"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPermissions()">
                                    <i class="fas fa-square mr-1"></i> Deselect All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="selectCommonPermissions()">
                                    <i class="fas fa-user-cog mr-1"></i> Common Staff Permissions
                                </button>
                            </div>

                            <div class="row">
                                <?php foreach ($allPermissions as $permission): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="permission-card card">
                                        <div class="card-body p-3">
                                            <div class="form-check">
                                                <input class="form-check-input permission-checkbox" 
                                                       type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?= $permission['id'] ?>" 
                                                       id="perm_<?= $permission['id'] ?>"
                                                       <?= isset($_POST['permissions']) && in_array($permission['id'], $_POST['permissions']) ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100" for="perm_<?= $permission['id'] ?>">
                                                    <strong><?= htmlspecialchars($permission['name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i> Create Role
                        </button>
                        <a href="view-all-roles.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>

            <!-- Predefined Role Templates -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Role Template Suggestions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>👨‍💼 Manager Role</h6>
                            <ul class="text-muted small">
                                <li>view_dashboard</li>
                                <li>manage_vehicles</li>
                                <li>manage_customers</li>
                                <li>manage_sales</li>
                                <li>manage_reports</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>🔧 Workshop Staff</h6>
                            <ul class="text-muted small">
                                <li>view_dashboard</li>
                                <li>manage_repairs</li>
                                <li>manage_job_cards</li>
                                <li>manage_expenses</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>📊 Accountant</h6>
                            <ul class="text-muted small">
                                <li>view_dashboard</li>
                                <li>manage_sales</li>
                                <li>manage_expenses</li>
                                <li>manage_reports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




            <?php include("footer.php"); ?>
        </div>
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

        <style>
    .permission-card:hover {
        background-color: #f8f9fa;
    }
    </style>

    <script>
        function selectAllPermissions() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAllPermissions() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        function selectCommonPermissions() {
            // Deselect all first
            deselectAllPermissions();
            
            // Select common staff permissions
            const commonPermissions = ['view_dashboard', 'manage_repairs', 'manage_job_cards', 'manage_expenses'];
            
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                const label = checkbox.closest('.form-check').querySelector('strong').textContent.trim();
                if (commonPermissions.includes(label)) {
                    checkbox.checked = true;
                }
            });
        }
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
    <!-- <script src="vendors/echarts/echarts.min.js"></script> -->
    <script src="assets/js/projectmanagement-dashboard.js"></script>

</body>


</html>