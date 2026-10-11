<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/dashboard_functions.php'; // New functions file
$pageTitle = 'Settings Dashboard ';
//include 'header.php';
// Check if user has permission to access settings
if (!$auth->hasPermission('manage_settings')) {
    header('Location: index.php');
    exit;
}

// Get all users with their roles and permission counts
$stmt = $db->query("
    SELECT 
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.is_active,
        r.name as role_name,
        r.id as role_id,
        COUNT(DISTINCT rp_default.permission_id) as role_permissions_count,
        COUNT(DISTINCT rp_custom.permission_id) as custom_permissions_count
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN role_permissions rp_default ON (rp_default.role_id = u.role_id AND rp_default.user_id IS NULL)
    LEFT JOIN role_permissions rp_custom ON (rp_custom.user_id = u.id)
    WHERE u.is_active = 1
    GROUP BY u.id
    ORDER BY u.full_name
");
$users = $stmt->fetchAll();
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
                        <h1 class="page-title">Manage User Permissions</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="settings-dashboard.php">Settings</a></li>
                                <li class="breadcrumb-item active">User Permissions</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-auto">
                        <a href="settings-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus mr-1"></i> Back to Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                
                <div class="card-body">

                <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;permission_name&quot;,&quot;description&quot;,&quot;user_by_roles&quot;,&quot;custom_grants&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                                <div class="row">
                                    <div class="col-8">
                                        <h3>Active Users</h3>
                                        <p class="text-body-tertiary lh-sm mb-0">Manage user permissions and access levels</p>
                                    </div>
                                    <div class="col-4 search-box mb-5">
                                        <form class="position-relative">
                                            <input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                                            <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                                <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                                            </svg>
                                        </form>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm fs-9 mb-0">
                                        <thead>
                                            <tr>
                                                <th class="sort border-top  ps-3" data-sort="role_name">Users</th>
                                                <th class="sort border-top border-translucent" data-sort="description">Role</th>
                                                <th class="sort border-top " data-sort="custom_grants">Permissions</th>
                                                <th class="sort border-top " data-sort="used_by_roles">Custom Persmissions</th>
                                                <th class="sort border-top " data-sort="used_by_roles">Status</th>
                                                <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody class="list">
                                            <?php if (empty($users)): ?>
                                                <tr>
                                                    <td colspan="5" class="alert alert-info">
                                                        <i class="fas fa-info-circle mr-2"></i> No active users found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td class="align-middle ps-3 permission_name">
                                                           
                                                        
                                                            
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($user['full_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                                                    </div>
                                                    </td>
                                                        <td class="align-middle description">
                                                            <span class="badge badge-phoenix badge-phoenix-secondary"><?= htmlspecialchars($user['role_name']) ?></span>
                                                        </td>
                                                        <td class="align-middle used_by_roles"> 
                                                        <span class="text-muted"><?= $user['role_permissions_count'] ?> role permissions</span>        
                                                    </td>
                                                        <td class="align-middle custom_grants">
                                                            <?php if ($user['custom_permissions_count'] > 0): ?>
                                                    <span class="badge badge-phoenix badge-phoenix-warning">
                                                        <i class="fas fa-star mr-1"></i>
                                                        <?= $user['custom_permissions_count'] ?> custom
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                                        </td>
                                                        <td class="align-middle status">
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge badge-phoenix badge-phoenix-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-phoenix badge-phoenix-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                                        <td class="align-middle text-end">
                                                            <div class="btn-group">
                                                    <a href="edit-user-permissions.php?user_id=<?= $user['id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Edit Permissions">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <!-- <a href="view-user-permissions.php?user_id=<?= $user['id'] ?>" 
                                                       class="btn btn-sm btn-outline-info" 
                                                       title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a> -->
                                                </div>
                                                        </td>
                                                    </tr>
                                            <?php endforeach;
                                            endif; ?>

                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between mt-3"><span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span>43</span>
                                    <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                                <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                                            </svg><!-- <span class="fas fa-chevron-left"></span> Font Awesome fontawesome.com --></button>
                                        <ul class="mb-0 pagination">
                                            <li class="active"><button class="page" type="button" data-i="1" data-page="5">1</button></li>
                                            <li><button class="page" type="button" data-i="2" data-page="5">2</button></li>
                                            <li><button class="page" type="button" data-i="3" data-page="5">3</button></li>
                                            <li class="disabled"><button class="page" type="button">...</button></li>
                                        </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                                <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                                            </svg><!-- <span class="fas fa-chevron-right"></span> Font Awesome fontawesome.com --></button>
                                    </div>
                                </div>
                            </div>    

                    
                </div>
            </div>

            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-primary mr-2"></i>
                        About User Permissions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Permission Hierarchy</h6>
                            <ul class="text-muted">
                                <li><strong>Role Permissions:</strong> Default permissions assigned to user's role</li>
                                <li><strong>Custom Permissions:</strong> Additional permissions granted to specific users</li>
                                <li><strong>Total Access:</strong> Role Permissions + Custom Permissions</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Best Practices</h6>
                            <ul class="text-muted">
                                <li>Use roles for standard permission sets</li>
                                <li>Grant custom permissions only when necessary</li>
                                <li>Regularly review custom permissions</li>
                                <li>Document reasons for custom grants</li>
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

    <!-- Dashboard Charts -->
    
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

