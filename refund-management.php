<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/refund_calculations.php';
require_once 'includes/errors.php';

$pageTitle = "Refund Management";

$refundCalculator = new RefundCalculator($db);

// Check permissions
if (!hasPermission('manage_sales')) {
    setError("You don't have permission to manage refunds.");
    header('Location: index.php');
    exit;
}

// Get all refunds
$refunds = [];
try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            s.invoice_no,
            s.sale_date,
            v.make,
            v.model, 
            v.regnumber,
            c.first_name,
            c.last_name,
            u.full_name as created_by_name
        FROM refunds r
        LEFT JOIN sales s ON r.sale_id = s.sale_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN users u ON r.created_by = u.id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setError("Error fetching refunds: " . $e->getMessage());
}


//-------------------------------------
// Fetch Refund Statistics (Simple Version)
try {
    // Current month calculation
    $current_month = date('Y-m');

    // Total Refunds
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_refunds,
            SUM(net_refund_amount) as total_refund_amount
        FROM refunds
    ");
    $stmt->execute();
    $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total Deductions
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_deductions,
            SUM(amount) as total_deduction_amount
        FROM refund_deductions
    ");
    $stmt->execute();
    $deduction_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // This Month Refunds
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as month_refunds,
            SUM(net_refund_amount) as month_refund_amount
        FROM refunds
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$current_month]);
    $month_refund_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // This Month Deductions
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as month_deductions,
            SUM(amount) as month_deduction_amount
        FROM refund_deductions
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$current_month]);
    $month_deduction_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setError("Error fetching statistics: " . $e->getMessage());
}
//-------------------------------------
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
                    <div class="col-12 col-xxl-12">
                        <!-- Page Header -->
                        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <h1 class="h2">Refund Management</h1>
                            <div class="btn-toolbar mb-2 mb-md-0">
                                <a href="create-refund.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Create New Refund
                                </a>
                            </div>
                        </div>
                        <!-- Simple Refund Statistics -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3 col-sm-6">
                                <div class="card border-primary border-start border-4 border-0 shadow-none h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h5 class="text-muted mb-2">Total Refunds</h5>
                                                <h3 class="mb-0"><?= $total_stats['total_refunds'] ?? 0 ?></h3>
                                                <p class="mb-0 text-muted fs-9">
                                                    £<?= number_format($total_stats['total_refund_amount'] ?? 0, 2) ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="avatar-title bg-primary bg-opacity-10 text-primary rounded fs-4">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="card border-warning border-start border-4 border-0 shadow-none h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h5 class="text-muted mb-2">Total Deductions</h5>
                                                <h3 class="mb-0"><?= $deduction_stats['total_deductions'] ?? 0 ?></h3>
                                                <p class="mb-0 text-muted fs-9">
                                                    £<?= number_format($deduction_stats['total_deduction_amount'] ?? 0, 2) ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="avatar-title bg-warning bg-opacity-10 text-warning rounded fs-4">
                                                    <i class="fas fa-minus-circle"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="card border-success border-start border-4 border-0 shadow-none h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h5 class="text-muted mb-2">This Month Refunds</h5>
                                                <h3 class="mb-0"><?= $month_refund_stats['month_refunds'] ?? 0 ?></h3>
                                                <p class="mb-0 text-muted fs-9">
                                                    £<?= number_format($month_refund_stats['month_refund_amount'] ?? 0, 2) ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="avatar-title bg-success bg-opacity-10 text-success rounded fs-4">
                                                    <i class="fas fa-calendar-check"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="card border-danger border-start border-4 border-0 shadow-none h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h5 class="text-muted mb-2">This Month Deductions</h5>
                                                <h3 class="mb-0"><?= $month_deduction_stats['month_deductions'] ?? 0 ?></h3>
                                                <p class="mb-0 text-muted fs-9">
                                                    £<?= number_format($month_deduction_stats['month_deduction_amount'] ?? 0, 2) ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="avatar-title bg-danger bg-opacity-10 text-danger rounded fs-4">
                                                    <i class="fas fa-chart-line"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Refunds Table -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Recent Refunds</h5>
                            </div>
                            <div class="card-body">
                                <!-- Table will be populated via AJAX or PHP -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Refund ID</th>
                                                <th>Invoice</th>
                                                <th>Customer</th>
                                                <th>Vehicle</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($refunds as $refund): ?>
                                                <tr>
                                                    <td>
                                                        <strong>RF-<?= str_pad($refund['refund_id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($refund['invoice_no']) ?></td>
                                                    <td><?= htmlspecialchars($refund['first_name'] . ' ' . $refund['last_name']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($refund['make'] . ' ' . $refund['model']) ?><br>
                                                        <small class="text-muted"><?= htmlspecialchars($refund['regnumber']) ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $type_labels = [
                                                            'full_return' => '<span class="badge bg-danger">Full Return</span>',
                                                            'partial_return' => '<span class="badge bg-warning">Partial Return</span>',
                                                            'partial_keep' => '<span class="badge bg-info">Partial Keep</span>',
                                                            'repair_compensation' => '<span class="badge bg-secondary">Repair Compensation</span>'
                                                        ];
                                                        echo $type_labels[$refund['refund_type']] ?? $refund['refund_type'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <strong>£<?= number_format($refund['net_refund_amount'], 2) ?></strong>
                                                        <?php if ($refund['total_deductions'] > 0): ?>
                                                            <br><small class="text-danger">-£<?= number_format($refund['total_deductions'], 2) ?> deductions</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_labels = [
                                                            'pending' => '<span class="badge bg-warning">Pending</span>',
                                                            'approved' => '<span class="badge bg-success">Approved</span>',
                                                            'processed' => '<span class="badge bg-info">Processed</span>',
                                                            'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
                                                        ];
                                                        echo $status_labels[$refund['status']] ?? $refund['status'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?= date('M j, Y', strtotime($refund['created_at'])) ?><br>
                                                        <small class="text-muted"><?= date('g:i A', strtotime($refund['created_at'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="refund-receipt.php?id=<?= $refund['refund_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if ($refund['status'] === 'pending'): ?>
                                                                <a href="process-refund.php?id=<?= $refund['refund_id'] ?>" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-check"></i> Process
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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