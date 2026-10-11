<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
$pageTitle = 'View All Permissions ';
//include 'header.php';

// Check if user has permission to access settings
if (!$auth->hasPermission('manage_settings')) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_POST) {
    $permission_name = trim($_POST['permission_name']);
    $permission_description = trim($_POST['permission_description']);

    // Validate permission name
    if (empty($permission_name)) {
        $error = "Permission name is required";
    } else if (!preg_match('/^[a-z_]+$/', $permission_name)) {
        $error = "Permission name must contain only lowercase letters and underscores";
    } else {
        // Check if permission name already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM permissions WHERE name = ?");
        $stmt->execute([$permission_name]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Permission name '{$permission_name}' already exists";
        }
    }

    if (!isset($error)) {
        try {
            // Insert new permission
            $stmt = $db->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)");
            $stmt->execute([$permission_name, $permission_description]);

            $_SESSION['success_message'] = "Permission '{$permission_name}' created successfully";
            header("Location: view-all-permissions.php");
            exit;
        } catch (Exception $e) {
            $error = "Error creating permission: " . $e->getMessage();
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
        <?php include("left-menu.php"); ?>

        <!-- Main Content -->
        <div class="content">
                <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="page-title">Add New Permission</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="settings-dashboard.php">Settings</a></li>
                                <li class="breadcrumb-item"><a href="view-all-permissions.php">Permissions</a></li>
                                <li class="breadcrumb-item active">Add Permission</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-auto">
                        <a href="view-all-permissions.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Permissions
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
                <!-- Permission Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Permission Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="permission_name">Permission Name *</label>
                                    <input type="text" class="form-control" id="permission_name" name="permission_name" 
                                           value="<?= htmlspecialchars($_POST['permission_name'] ?? '') ?>" required
                                           pattern="[a-z_]+" title="Lowercase letters and underscores only"
                                           placeholder="manage_inventory">
                                    <small class="form-text text-muted">
                                        System identifier (lowercase, underscores only). Example: manage_vehicles
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="permission_description">Description *</label>
                                    <input type="text" class="form-control" id="permission_description" name="permission_description" 
                                           value="<?= htmlspecialchars($_POST['permission_description'] ?? '') ?>" required
                                           placeholder="Add, edit, and delete inventory items">
                                    <small class="form-text text-muted">
                                        Clear description of what this permission allows
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i> Create Permission
                        </button>
                        <a href="view-all-permissions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>

            <!-- Common Permission Examples -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Common Permission Examples
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>📊 Reporting</h6>
                            <ul class="text-muted small">
                                <li><code>view_sales_reports</code></li>
                                <li><code>export_reports</code></li>
                                <li><code>view_analytics</code></li>
                                <li><code>manage_dashboards</code></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>🔧 Operations</h6>
                            <ul class="text-muted small">
                                <li><code>manage_inventory</code></li>
                                <li><code>approve_repairs</code></li>
                                <li><code>schedule_maintenance</code></li>
                                <li><code>manage_suppliers</code></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>👥 User Management</h6>
                            <ul class="text-muted small">
                                <li><code>manage_departments</code></li>
                                <li><code>view_audit_logs</code></li>
                                <li><code>reset_passwords</code></li>
                                <li><code>manage_notifications</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <?php include("footer.php"); ?>
        </div>

    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->


    <style>
        .stat-card {
            padding: 20px;
        }

        .stat-card h3 {
            margin: 0;
            font-weight: bold;
        }

        .role-tags {
            margin-top: 10px;
        }
    </style>
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