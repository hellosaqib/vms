<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/dashboard_functions.php'; // New functions file
$pageTitle = 'Settings Dashboard ';
//include 'header.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

// Check if user has permission to access settings
if (!$auth->hasPermission('manage_settings')) {
    header('Location: access-denied.php');
    exit;
}

// Get statistics for dashboard
$stmt = $db->query("SELECT COUNT(*) as total FROM permissions");
$totalPermissions = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM roles");
$totalRoles = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$totalUsers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(DISTINCT user_id) as total FROM role_permissions WHERE user_id IS NOT NULL");
$usersWithCustomPermissions = $stmt->fetch()['total'];

// Get recent activity
// $stmt = $db->query("
//     SELECT u.full_name, rp.granted_at, p.name as permission_name 
//     FROM role_permissions rp 
//     JOIN users u ON rp.user_id = u.id 
//     JOIN permissions p ON rp.permission_id = p.id 
//     WHERE rp.user_id IS NOT NULL 
//     ORDER BY rp.granted_at DESC 
//     LIMIT 5
// ");
// $recentPermissions = $stmt->fetchAll();

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
                                <h1 class="page-title">System Settings</h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Settings</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon bg-primary">
                                            <i class="fas fa-key"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4><?= $totalPermissions ?></h4>
                                            <span>Total Permissions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon bg-success">
                                            <i class="fas fa-users-cog"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4><?= $totalRoles ?></h4>
                                            <span>System Roles</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon bg-info">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4><?= $totalUsers ?></h4>
                                            <span>Active Users</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="card-icon bg-warning">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4><?= $usersWithCustomPermissions ?></h4>
                                            <span>Custom Permissions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access Sections -->
                    <div class="row">
                        <!-- Permissions Management -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-key text-primary mr-2"></i>
                                        Permissions Management
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Manage system permissions and access controls</p>
                                    <div class="list-group">
                                        <a href="view-all-permissions.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-list mr-2"></i>View All Permissions
                                        </a>
                                        <a href="add-new-permission.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-plus mr-2"></i>Add New Permission
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Roles Management -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-users-cog text-success mr-2"></i>
                                        Roles Management
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Manage user roles and default permissions</p>
                                    <div class="list-group">
                                        <a href="view-all-roles.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-list mr-2"></i>View All Roles
                                        </a>
                                        <a href="add-role.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-plus mr-2"></i>Add New Role
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Permissions -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-shield text-info mr-2"></i>
                                        User Permissions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Manage individual user permissions and overrides</p>
                                    <div class="list-group">
                                        <a href="manage-user-permissions.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-users mr-2"></i>Manage User Access
                                        </a>
                                        <a href="edit-user-permissions.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-edit mr-2"></i>Grant Custom Permissions
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-history mr-2"></i>
                                        Recent Permission Grants
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentPermissions)): ?>
                                        <p class="text-muted">No recent permission grants found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Permission</th>
                                                        <th>Granted At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentPermissions as $activity): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($activity['full_name']) ?></td>
                                                            <td>
                                                                <span class="badge badge-primary">
                                                                    <?= htmlspecialchars($activity['permission_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?= date('M j, Y g:i A', strtotime($activity['granted_at'])) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue vs Profit Chart
            const revenueCtx = document.getElementById('revenueProfitChart').getContext('2d');
            const revenueProfitChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'month_name')) ?>,
                    datasets: [{
                            label: 'Revenue (£)',
                            data: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'monthly_revenue')) ?>,
                            borderColor: '#2c7be5',
                            backgroundColor: 'rgba(44, 123, 229, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Profit (£)',
                            data: <?= json_encode(array_column($chartData['revenue_profit'] ?? [], 'monthly_profit')) ?>,
                            borderColor: '#00d97e',
                            backgroundColor: 'rgba(0, 217, 126, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '£' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Inventory Health Chart
            const inventoryCtx = document.getElementById('inventoryHealthChart').getContext('2d');
            const inventoryHealthChart = new Chart(inventoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'status')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($chartData['inventory_health'] ?? [], 'vehicle_count')) ?>,
                        backgroundColor: [
                            '#00d97e', '#f6c343', '#2c7be5', '#fd7e14', '#6e84a3'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
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
    <!-- <script src="vendors/echarts/echarts.min.js"></script> -->
    <script src="assets/js/projectmanagement-dashboard.js"></script>

</body>


</html>