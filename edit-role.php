<?php
// Authentication & Config
// $requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
$pageTitle = 'Edit Role ';
//include 'header.php';
// Check if user has permission to access settings
if (!$auth->hasPermission('manage_settings')) {
    header('Location: index.php');
    exit;
}

$role_id = $_GET['role_id'] ?? 0;

// Get role details
$stmt = $db->prepare("SELECT id, name, description FROM roles WHERE id = ?");
$stmt->execute([$role_id]);
$role = $stmt->fetch();

if (!$role) {
    header('Location: view-all-roles.php');
    exit;
}

// Get all permissions
$stmt = $db->query("SELECT id, name, description FROM permissions ORDER BY name");
$allPermissions = $stmt->fetchAll();

// Get current role permissions
$stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ? AND user_id IS NULL");
$stmt->execute([$role_id]);
$currentPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle delete role
if (isset($_POST['delete_role'])) {
    // Check if role has any users
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_active = 1");
    $stmt->execute([$role_id]);
    $userCount = $stmt->fetchColumn();
    
    if ($userCount > 0) {
        $_SESSION['error_message'] = "Cannot delete role '{$role['name']}' because it has {$userCount} active user(s). Please reassign users first.";
        header("Location: view-all-roles.php");
        exit;
    }
    else {
        // Remove all permissions for this role
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Delete the role
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        
        
        $_SESSION['success_message'] = "Role '{$role['name']}' deleted successfully";
        header("Location: view-all-roles.php");
        exit;
        
    } 
}

// Handle form submission
if (isset($_POST['update_role'])) {
    $role_name = trim($_POST['role_name']);
    $role_description = trim($_POST['role_description']);
    $selectedPermissions = $_POST['permissions'] ?? [];

    // Update role details
    $stmt = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$role_name, $role_description, $role_id]);

    // Remove ALL permissions for this role (both role defaults AND user custom)
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Add new role default permissions (user_id = NULL)
        foreach ($selectedPermissions as $permission_id) {
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, user_id) VALUES (?, ?, NULL)");
            $stmt->execute([$role_id, $permission_id]);
        }

    $_SESSION['success_message'] = "Role '{$role_name}' updated successfully";
    header("Location: view-all-roles.php");
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
                        <h1 class="page-title">Edit Role</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="settings-dashboard.php">Settings</a></li>
                                <li class="breadcrumb-item"><a href="view-all-roles.php">Roles</a></li>
                                <li class="breadcrumb-item active">Edit Role</li>
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
                                           value="<?= htmlspecialchars($role['name']) ?>" required
                                           <?= $role['id'] == 1 ? 'readonly' : '' ?>>
                                    <?php if ($role['id'] == 1): ?>
                                        <small class="form-text text-muted">Administrator role cannot be modified</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role_description">Description</label>
                                    <input type="text" class="form-control" id="role_description" name="role_description" 
                                           value="<?= htmlspecialchars($role['description']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Role Permissions</h5>
                        <p class="card-subtitle">Select the permissions assigned to this role by default</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allPermissions)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No permissions found in the system.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($allPermissions as $permission): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="permission-card card">
                                        <div class="card-body p-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?= $permission['id'] ?>" 
                                                       id="perm_<?= $permission['id'] ?>"
                                                       <?= in_array($permission['id'], $currentPermissions) ? 'checked' : '' ?>
                                                       <?= $role['id'] == 1 ? 'checked disabled' : '' ?>>
                                                <label class="form-check-label w-100" for="perm_<?= $permission['id'] ?>">
                                                    <strong><?= htmlspecialchars($permission['name']) ?></strong>
                                                    <?php if ($role['id'] == 1): ?>
                                                        <span class="badge badge-primary ml-2">Required</span>
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
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-footer">
                        <button type="submit" name="update_role" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Update Role
                        </button>
                        <a href="view-all-roles.php" class="btn btn-secondary">Cancel</a>
                        
                        <?php if ($role['id'] != 1): ?>
                            <button type="submit" name="delete_role" 
                               class="btn btn-outline-danger float-right"
                               onclick="return confirm('Are you sure you want to delete this role?')">
                                <i class="fas fa-trash mr-1"></i> Delete Role
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Current Users Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users mr-2"></i>
                        Users with this Role
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $db->prepare("
                        SELECT id, username, full_name, email 
                        FROM users 
                        WHERE role_id = ? AND is_active = 1 
                        ORDER BY full_name
                    ");
                    $stmt->execute([$role_id]);
                    $users = $stmt->fetchAll();
                    
                    if (empty($users)): ?>
                        <p class="text-muted">No users assigned to this role.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <a href="edit-user-permissions.php?user_id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Permissions
                                                </a>
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



            <?php include("footer.php"); ?>
        </div>
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

    <script>
        $(document).ready(function() {
            $('#rolesTable').DataTable({
                "pageLength": 25,
                "order": [
                    [0, 'asc']
                ]
            });
        });

        // function confirmDelete(roleId, roleName) {
        //     if (confirm('Are you sure you want to delete the role "' + roleName + '"? This action cannot be undone.')) {
        //         window.location.href = 'delete-role.php?role_id=' + roleId;
        //     }
        // }
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