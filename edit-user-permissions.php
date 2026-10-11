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

$user_id = $_GET['user_id'] ?? 0;

// Get user details
$stmt = $db->prepare("
    SELECT u.id, u.username, u.full_name, u.email, u.role_id, r.name as role_name
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ? AND u.is_active = 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: manage-user-permissions.php');
    exit;
}

// Get all permissions
$stmt = $db->query("SELECT id, name, description FROM permissions ORDER BY name");
$allPermissions = $stmt->fetchAll();

// Get user's current permissions (role + custom)
// Get user's current permissions (role + custom)
$stmt = $db->prepare("
    SELECT 
        p.id, 
        p.name, 
        p.description,
        CASE 
            WHEN rp_custom.user_id IS NOT NULL THEN 1 
            ELSE 0 
        END as is_custom,
        CASE 
            WHEN rp_role.role_id IS NOT NULL THEN 1 
            ELSE 0 
        END as is_role_permission
    FROM permissions p
    LEFT JOIN role_permissions rp_role ON (p.id = rp_role.permission_id AND rp_role.role_id = ? AND rp_role.user_id IS NULL)
    LEFT JOIN role_permissions rp_custom ON (p.id = rp_custom.permission_id AND rp_custom.user_id = ?)
    ORDER BY p.name
");
$stmt->execute([$user['role_id'], $user_id]);
$userPermissions = $stmt->fetchAll();

// Handle reset to role defaults
if (isset($_POST['delete_role'])) {
    // Check if role has any users
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1");
    $stmt->execute([$role_id]);
    $userCount = $stmt->fetchColumn();
    
    if ($userCount > 0) {
        $_SESSION['error_message'] = "Cannot delete role '{$role['name']}' because it has {$userCount} active user(s). Please reassign users first.";
        header("Location: view-all-roles.php");
        exit;
    }else{
        // Remove all permissions for this role
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Delete the role
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        
        $db->commit();
        
        $_SESSION['success_message'] = "Role '{$role['name']}' deleted successfully";
        header("Location: view-all-roles.php");
        exit;
    }
}

// Handle form submission
if (isset($_POST['update_permissions'])) {
    $selectedPermissions = $_POST['permissions'] ?? [];
    
    // Remove existing custom permissions for this user
    $stmt = $db->prepare("DELETE FROM role_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Add new custom permissions
    foreach ($selectedPermissions as $permission_id) {
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$user['role_id'], $permission_id, $user_id]);
    }
    
    $_SESSION['success_message'] = "Permissions updated successfully for " . htmlspecialchars($user['full_name']);
    header("Location: manage-user-permissions.php");
    exit;
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
                        <h1 class="page-title">Edit User Permissions</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="settings-dashboard.php">Settings</a></li>
                                <li class="breadcrumb-item"><a href="manage-user-permissions.php">User Permissions</a></li>
                                <li class="breadcrumb-item active">Edit Permissions</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-auto">
                        <a href="manage-user-permissions.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Users
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Name:</strong> <?= htmlspecialchars($user['full_name']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Username:</strong> <?= htmlspecialchars($user['username']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Role:</strong> <span class="badge badge-phoenix badge-phoenix-info"><?= htmlspecialchars($user['role_name']) ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Email:</strong> <?= htmlspecialchars($user['email']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions Form -->
            <form method="POST" action="">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Manage Permissions</h5>
                        <p class="card-subtitle">Select additional permissions to grant to this user</p>
                    </div>
                    <div class="card-body">
                        <!-- <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> User already has all permissions assigned to their role (<strong><?= htmlspecialchars($user['role_name']) ?></strong>). 
                            Check additional permissions below to grant extra access.
                        </div> -->

                        <div class="row">
                            <?php foreach ($allPermissions as $permission): 
    $isCustom = false;
    $isRolePermission = false;
    
    // Find this permission in user's permissions
    foreach ($userPermissions as $userPerm) {
        if ($userPerm['id'] == $permission['id']) {
            $isCustom = (bool)$userPerm['is_custom'];
            $isRolePermission = (bool)$userPerm['is_role_permission'];
            break;
        }
    }
?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="permission-card card <?= $isRolePermission ? 'border-success' : '' ?>">
                                    <div class="card-body p-3">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?= $permission['id'] ?>" 
                                                   id="perm_<?= $permission['id'] ?>"
                                                   <?= ($isCustom || $isRolePermission) ? 'checked' : '' ?>
                                                   <?= $isRolePermission ? 'disabled' : '' ?>>
                                            <label class="form-check-label w-100" for="perm_<?= $permission['id'] ?>">
                                                <strong><?= htmlspecialchars($permission['name']) ?></strong>
                                                <?php if ($isRolePermission): ?>
                                                    <span class="badge badge-success ml-2">Role Permission</span>
                                                <?php elseif ($isCustom): ?>
                                                    <span class="badge badge-warning ml-2">Custom Granted</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (empty($allPermissions)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No permissions found in the system.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" name="update_permissions">
                            <i class="fas fa-save mr-1"></i> Update Permissions
                        </button>
                        <a href="manage-user-permissions.php" class="btn btn-secondary">Cancel</a>
                        
                        <?php 
                        $customCount = 0;
                        foreach ($userPermissions as $perm) {
                            if ($perm['is_custom']) $customCount++;
                        }
                        if ($customCount > 0): ?>
                        <button type="submit" name="reset_permissions" class="btn btn-outline-danger float-right" 
        onclick="return confirm('Are you sure you want to reset all custom permissions for this user?')">
    <i class="fas fa-undo mr-1"></i> Reset to Role Defaults
</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Current Permissions Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Current Access Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Role Permissions (Inherited)</h6>
                            <div class="permission-list">
                                <?php 
                                $rolePerms = array_filter($userPermissions, function($perm) {
                                    return !$perm['is_custom'];
                                });
                                if (empty($rolePerms)): ?>
                                    <p class="text-muted">No role permissions</p>
                                <?php else: ?>
                                    <?php foreach ($rolePerms as $perm): ?>
                                        <span class="badge badge-success mb-1"><?= htmlspecialchars($perm['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Custom Permissions (Additional)</h6>
                            <div class="permission-list">
                                <?php 
                                $customPerms = array_filter($userPermissions, function($perm) {
                                    return $perm['is_custom'];
                                });
                                if (empty($customPerms)): ?>
                                    <p class="text-muted">No custom permissions</p>
                                <?php else: ?>
                                    <?php foreach ($customPerms as $perm): ?>
                                        <span class="badge badge-warning mb-1"><?= htmlspecialchars($perm['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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