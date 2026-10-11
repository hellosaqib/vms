<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

$pageTitle = "Refund Receipt";

if (!hasPermission('manage_sales')) {
    setError("You don't have permission to manage refunds.");
    header('Location: index.php');
    exit;
}

// Check if refund ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setError("Invalid refund ID.");
    header('Location: refund-management.php');
    exit;
}

$refund_id = (int)$_GET['id'];
$business_keeps = 0;
// Fetch refund details
try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            s.invoice_no as sale_invoice, s.sale_date, s.sale_price, 
            s.service_fee, s.delivery_charges, s.discount_amount,
            s.payment_method as original_payment_method,
            s.finance_amount, s.finance_company_name, s.finance_company_address, s.finance_company_phone, s.finance_company_email,
            s.part_exchange_allowance, s.deposit_received,
            v.vehicle_id, v.make, v.model, v.regnumber, v.year, v.vin, v.mileage,
            pxv.vehicle_id as px_vehicle_id, pxv.make as px_make, pxv.model as px_model, 
            pxv.regnumber as px_regnumber, pxv.year as px_year,
            c.customer_id, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.post_code,
            uc.full_name as created_by_name,
            up.full_name as processed_by_name
        FROM refunds r
        LEFT JOIN sales s ON r.sale_id = s.sale_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN vehicles pxv ON s.px_vehicle_id = pxv.vehicle_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN users uc ON r.created_by = uc.id
        LEFT JOIN users up ON r.processed_by = up.id
        WHERE r.refund_id = ?
    ");
    $stmt->execute([$refund_id]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$refund) {
        setError("Refund not found.");
        header('Location: refund-management.php');
        exit;
    }

    // Fetch refund deductions separately
    $stmt = $db->prepare("
        SELECT * FROM refund_deductions 
        WHERE refund_id = ? 
        ORDER BY deduction_id
    ");
    $stmt->execute([$refund_id]);
    $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $px_deduction_amount = 0;
    $other_deductions_amount = 0;

    foreach ($deductions as $deduction) {
        if ($deduction['deduction_type'] == 'Part-Exchange Allowance') {
            $px_deduction_amount = $deduction['amount'];
        } else {
            $other_deductions_amount += $deduction['amount'];
        }
    }
    $totalDeductions = $px_deduction_amount + $other_deductions_amount;
} catch (PDOException $e) {
    setError("Error fetching refund details: " . $e->getMessage());
    header('Location: refund-management.php');
    exit;
}

$stmt = $db->prepare("
    SELECT 
        refund_id,
        refund_type,
        net_refund_amount,
        total_deductions,
        max_refundable_after,
        created_at,
        status,
        refund_reason
    FROM refunds 
    WHERE sale_id = ? 
    AND (
        created_at < ? 
        OR 
        (created_at = ? AND refund_id < ?)
    )
    AND status IN ('processed', 'approved')
    ORDER BY created_at ASC, refund_id ASC
");
$stmt->execute([
    $refund['sale_id'],
    $refund['created_at'],
    $refund['created_at'],
    $refund_id
]);
$previousRefunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Calculate total previously refunded
$totalPreviousDeductions = 0;
$totalPreviousRefunded = 0;
$prev = [];
foreach ($previousRefunds as $prev) {
    if ($prev['refund_type'] != 'repair_compensation') {
        $totalPreviousDeductions += $prev['total_deductions'];
        $totalPreviousRefunded += $prev['net_refund_amount'];
    }
}
$totalPreviousImpact = $totalPreviousDeductions + $totalPreviousRefunded;

if ($refund['refund_type'] == "full_return") {
    $business_keeps = $refund['total_deductions'] - $other_deductions_amount;
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
    <link href="vendors/swiper/swiper-bundle.min.css" rel="stylesheet" />

    <style>
        @media print {
            body * {
                visibility: hidden;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;

            }

            .invoice-area,
            .invoice-area * {
                visibility: visible;
            }

            .invoice-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

        }
    </style>
    <style>
        /* Screen Styles */

        /* Common Styles */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }


        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .company-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 10px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            background: #f8f9fa;
            padding: 4px 6px;
            border-left: 4px solid #2c3e50;
            margin-bottom: 15px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0px;
        }

        .info-group {
            margin-bottom: 0px;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0px 0;
        }

        th {
            background: #2c3e50;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }

        tr {
            font-size: x-small;
        }

        td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }

        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }

        .declaration-box {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            background: #f8f9fa;
            font-size: x-small;
        }

        .signature-area {
            /* margin-top: 40px; */
            /* border-top: 0px solid #ddd; */
            padding-top: 20px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
            min-height: 25px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 12px;
            color: #666;
        }

        .alert-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 5px;
            margin: 15px 0;
            border-radius: 5px;
            font-size: smaller;
        }
    </style>

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
            <div class="button-group no-print">
                <button onclick="window.print()" class="btn btn-phoenix-primary">🖨️ Print Invoice</button>
                <!-- <a href="view-all-sales.php" class="btn btn-success">📋 Back to Sales</a> -->
            </div><br>
            <!-- ============================================-->
            <!-- <section> begin ============================-->

            <section class="pt-0 pb-3 bg-body-emphasis dark__bg-gray-1200 invoice-area" style="width: 100%;">
                <div class="" style="margin-left:5px; width: 99%; border:0px solid red;">
                    <div class="header-section">
                        <table class="table table-borderless mb-0" style="border:0px solid red;">
                            <tr>
                                <td class="align-items-center"><img src="uploads/images/britdrive-01.svg" width="150" style="margin-top: -10;" alt="BritDrive Motors" class="company-logo">
                                    <div class="mt-3">
                                        <h4 class="receipt-title">REFUND RECEIPT #: RF-<?= str_pad($refund['refund_id'], 5, '0', STR_PAD_LEFT) ?></h4>
                                        <!-- <div class="receipt-number">
                                        Receipt #: <strong>RF-<?= str_pad($refund['refund_id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                    </div> -->
                                    </div>
                                </td>
                                <td class="text-end">
                                    <h4>BritDrive Motors</h4>
                                    <p class="mb-1">158 Derby Road</p>
                                    <p class="mb-1">Nottingham, NG16 4AA</p>
                                    <p class="mb-1">info@britdrivemotors.co.uk</p>
                                    <p class="mb-1">VAT Number: [Your VAT Here]</p>
                                    <p class="mb-0"><b>Refund Date : <?php echo htmlspecialchars(date('d-m-Y', strtotime($refund['created_at']))); ?></b></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class=" p-3 pt-0 pb-0 mb-3 rounded-2" style="background-color:#ddd; border:0px solid red;">
                        <table class="table table-borderless mb-0" style="border:0px solid red;">
                            <tr>

                                <td width="500">
                                    <h6 class="mb-2">Original Sale Information:</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-0">
                                            Invoice No : <?php echo htmlspecialchars($refund['sale_invoice'] ?? 'N/A'); ?><br>
                                            Vehicle: <?php echo htmlspecialchars($refund['make'] . " " . $refund['model']); ?><br>
                                            Reg Number: <?php echo htmlspecialchars($refund['regnumber']); ?><br>
                                            Sale Date : <?= date('d-m-Y', strtotime($refund['sale_date'])) ?><br>
                                            Sale Price : £<?= number_format($refund['sale_price'], 2) ?><br>

                                        </p>
                                    </div>
                                </td>
                                <td width="230">

                                    <h6 class="mb-2"> Refund Status :</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'processed' => 'info',
                                            'cancelled' => 'danger'
                                        ];
                                        $status_color = $status_colors[$refund['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $status_color ?> status-badge">
                                            <?= strtoupper($refund['status']) ?>
                                        </span>

                                        <?php if ($refund['processed_date']): ?>
                                            <p class="text-muted mt-2 mb-0">
                                                Processed on: <?= date('F j, Y H:i', strtotime($refund['processed_date'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td width="300">
                                    <h6 class="mb-2"> Customer Information :</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-2">
                                            <?php echo htmlspecialchars($refund['first_name'] . " " . $refund['last_name']); ?><br>
                                            <?php echo htmlspecialchars($refund['address']); ?><br>
                                            <?php echo htmlspecialchars($refund['city'] . "," . $refund['post_code']); ?><br>
                                            <?php echo htmlspecialchars($refund['phone']); ?><br>
                                            <?php echo htmlspecialchars($refund['email']); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </div>
     <table class="table table-bordered receipt-summary">
    <thead>
        <tr>
            <th class="bg-success-subtle text-dark text-center">Original Transaction</th>
            <th class="bg-info-subtle text-center">Business Costs & History</th>
            <th class="bg-danger-subtle text-center">Refund Summary</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <!-- CARD 1: Original Transaction -->
            <td class="align-top">
                <div class="transaction-details"  style="padding-left: 10px;">
                    <?php if ($refund['finance_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Finance Amount:</span>
                        <span class="fw-bold">£<?= number_format($refund['finance_amount'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($refund['part_exchange_allowance'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>PX Allowance:</span>
                        <span class="fw-bold">£<?= number_format($refund['part_exchange_allowance'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($refund['deposit_received'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Cash Received:</span>
                        <span class="fw-bold">£<?= number_format($refund['deposit_received'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between border-top pt-2 mt-2">
                        <strong>TOTAL RECEIVED:</strong>
                        <strong class="text-success">
                            £<?= number_format(($refund['finance_amount'] ?? 0) + ($refund['part_exchange_allowance'] ?? 0) + ($refund['deposit_received'] ?? 0), 2) ?>
                        </strong>
                    </div>
                </div>
            </td>

            <!-- CARD 2: Business Costs & History -->
            <td class="align-top">
                <div class="business-costs">
                    <?php if ($totalPreviousImpact > 0): ?>
                    <h6 class="mb-2 text-dark">Previous Transactions:</h6>
                    <?php foreach ($previousRefunds as $prev): ?>
                        <?php if ($prev['refund_type'] != 'repair_compensation'): ?>
                        <div class="previous-refund mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Refund #<?= $prev['refund_id'] ?></small>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($prev['created_at'])) ?></small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Refund to Customer:</span>
                                <span class="text-success">£<?= number_format($prev['net_refund_amount'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Business Deductions:</span>
                                <span class="text-danger">£<?= number_format($prev['total_deductions'], 2) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-history fa-2x mb-2"></i><br>
                        No previous refunds
                    </div>
                    <?php endif; ?>
                    
                    
                </div>
            </td>

            <!-- CARD 3: Refund Summary -->
            <td class="align-top">
                <div class="refund-summary">
                    <!-- Available Funds -->
                    <div class="available-funds mb-3" style="padding-right:10px;">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Cash Received:</span>
                            <span>£<?= number_format($refund['deposit_received'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Already Refunded:</span>
                            <span class="text-danger">-£<?= number_format($totalPreviousImpact ?? 0, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1">
                            <strong>Available Funds:</strong>
                            <strong class="text-primary">
                                £<?= number_format(isset($prev['max_refundable_after']) ? $prev['max_refundable_after'] : $refund['deposit_received'], 2) ?>
                            </strong>
                        </div>
                    </div>

                    <!-- Current Refund -->
                    <div class="current-refund mt-1 pt-1" style="padding-right:10px;">
                        <div class="d-flex justify-content-between">
                            <span class="text-success"><strong>Current Refund:</strong></span>
                            <span class="text-success  fw-bold">£<?= number_format($refund['net_refund_amount'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Current Deductions -->
                    <?php if (!empty($deductions) || $business_keeps > 0): ?>
                    <div class="current-deductions" style="padding-right: 10px;">
                        <h6 class="mb-2 text-dark">Current Deductions:</h6>
                        
                        <?php if (isset($business_keeps)): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Business Costs:</span>
                            <span class="text-danger">£<?= number_format($business_keeps, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php foreach ($deductions as $deduction): ?>
                            <?php if ($deduction['deduction_type'] != 'Part-Exchange Allowance'): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= htmlspecialchars($deduction['deduction_type']) ?>:</span>
                                <span class="text-danger">£<?= number_format($deduction['amount'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Remaining Balance -->
                    <div class="remaining-balance mt-1 pt-1 border-top" style="padding-right: 10px;">
                        <div class="d-flex justify-content-between">
                            <strong class="text-dark">Remaining Balance:</strong>
                            <strong class="<?= $refund['max_refundable_after'] > 0 ? 'text-warning' : 'text-success' ?>">
                                £<?= number_format($refund['max_refundable_after'], 2) ?>
                            </strong>
                        </div>
                        <?php if ($refund['max_refundable_after'] > 0): ?>
                        <small class="text-muted">Available for future refunds</small>
                        <?php else: ?>
                        <small class="text-success">✓ All funds accounted for</small>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>
    </tbody>
</table>

<style>
.receipt-summary {
    font-size: 14px;
}
.receipt-summary th {
    padding: 12px;
    font-size: 14px;
}
.receipt-summary td {
    padding: 15px;
    vertical-align: top;
}
.transaction-details div,
.business-costs div,
.refund-summary div {
    font-size: 13px;
    margin-bottom: 8px;
}
.previous-refund {
    font-size: 13px;
    border-left: 3px solid #6c757d;
}
.available-funds,
.current-refund,
.current-deductions {
    padding-bottom: 0px;
}
.remaining-balance {
    font-size: 15px;
}
</style>
                    <div class="px-0 ">
                        <div class="row">
                                <div class="col-6 mb-3">
                                    <h6>Refund Reason *</h6>
                                    <p class="border p-2" name="refund_reason" rows="3"
                                        placeholder="Detailed reason for the refund..." readonly><?= htmlspecialchars($refund['refund_reason'] ?? 'N/A') ?></p>
                                </div>

                                <?php if (!empty($refund['customer_agreement'])): ?>
                                    <div class="col-6 mb-3">
                                        <h6>Customer Agreement / Notes</h6>
                                        <p class="border p-2" name="customer_agreement" rows="2"
                                            placeholder="Any agreement details or special notes..." readonly><?= htmlspecialchars($refund['customer_agreement'] ?? 'N/A') ?></p>
                                    </div>
                                <?php endif; ?>
                                
                            
                        </div>
                        <!-- Footer Note -->
                        <div class="mt-2 text-start">
                            <p class="text-muted mb-0">
                                Thank you for your business. This refund has been processed and should appear in your account within 3-5 business days.
                            </p>

                        </div><br><br>
                        <div class="section">
                            <div class="grid-2 signature-area">
                                <div>
                                    <div class="signature-line"></div>
                                    <div class="label">BUYER'S SIGNATURE</div>
                                    <div class="value">Date: ________________ Time: ________________</div>
                                </div>
                                <div>
                                    <div class="signature-line"></div>
                                    <div class="label">SELLER SIGNATURE</div>
                                    <div class="value">Date: ________________ Time: ________________</div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div><!-- end of .container-->
            </section><!-- <section> close ============================-->
            <!-- ============================================-->



        </div>
        <?php include_once("footer.php"); ?>

        </div>
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

    <!-- <div class="offcanvas offcanvas-end settings-panel border-0" id="settings-offcanvas" tabindex="-1" aria-labelledby="settings-offcanvas">
      <div class="offcanvas-header align-items-start border-bottom flex-column border-translucent">
        <div class="pt-1 w-100 mb-6 d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-2 me-2 lh-sm"><span class="fas fa-palette me-2 fs-8"></span>Theme Customizer</h5>
            <p class="mb-0 fs-9">Explore different styles according to your preferences</p>
          </div><button class="btn p-1 fw-bolder" type="button" data-bs-dismiss="offcanvas" aria-label="Close"><span class="fas fa-times fs-8"> </span></button>
        </div><button class="btn btn-phoenix-secondary w-100" data-theme-control="reset"><span class="fas fa-arrows-rotate me-2 fs-10"></span>Reset to default</button>
      </div>
      <div class="offcanvas-body scrollbar px-card" id="themeController">
        <div class="setting-panel-item mt-0">
          <h5 class="setting-panel-item-title">Color Scheme</h5>
          <div class="row gx-2">
            <div class="col-4"><input class="btn-check" id="themeSwitcherLight" name="theme-color" type="radio" value="light" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherLight"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/default-light.png" alt=""/></span><span class="label-text">Light</span></label></div>
            <div class="col-4"><input class="btn-check" id="themeSwitcherDark" name="theme-color" type="radio" value="dark" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherDark"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/default-dark.png" alt=""/></span><span class="label-text"> Dark</span></label></div>
            <div class="col-4"><input class="btn-check" id="themeSwitcherAuto" name="theme-color" type="radio" value="auto" data-theme-control="phoenixTheme" /><label class="btn d-inline-block btn-navbar-style fs-9" for="themeSwitcherAuto"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype mb-0" src="assets/img/generic/auto.png" alt=""/></span><span class="label-text"> Auto</span></label></div>
          </div>
        </div>
        <div class="border border-translucent rounded-3 p-4 setting-panel-item bg-body-emphasis">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="setting-panel-item-title mb-1">RTL </h5>
            <div class="form-check form-switch mb-0"><input class="form-check-input ms-auto" type="checkbox" data-theme-control="phoenixIsRTL" /></div>
          </div>
          <p class="mb-0 text-body-tertiary">Change text direction</p>
        </div>
        <div class="border border-translucent rounded-3 p-4 setting-panel-item bg-body-emphasis">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="setting-panel-item-title mb-1">Support Chat </h5>
            <div class="form-check form-switch mb-0"><input class="form-check-input ms-auto" type="checkbox" data-theme-control="phoenixSupportChat" /></div>
          </div>
          <p class="mb-0 text-body-tertiary">Toggle support chat</p>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Navigation Type</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarPositionVertical" name="navigation-type" type="radio" value="vertical" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionVertical"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/default-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/default-dark.png" alt=""/></span><span class="label-text">Vertical</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionHorizontal" name="navigation-type" type="radio" value="horizontal" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionHorizontal"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/top-default-dark.png" alt=""/></span><span class="label-text"> Horizontal</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionCombo" name="navigation-type" type="radio" value="combo" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionCombo"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/nav-combo-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/nav-combo-dark.png" alt=""/></span><span class="label-text"> Combo</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarPositionTopDouble" name="navigation-type" type="radio" value="dual-nav" data-theme-control="phoenixNavbarPosition" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarPositionTopDouble"> <span class="rounded d-block"><img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/dual-light.png" alt=""/><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/dual-dark.png" alt=""/></span><span class="label-text"> Dual nav</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Vertical Navbar Appearance</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbar-style-default" type="radio" name="config.name" value="default" data-theme-control="phoenixNavbarVerticalStyle" /><label class="btn d-block w-100 btn-navbar-style fs-9" for="navbar-style-default"> <img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/default-light.png" alt="" /><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/default-dark.png" alt="" /><span class="label-text d-dark-none"> Default</span><span class="label-text d-light-none">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbar-style-dark" type="radio" name="config.name" value="darker" data-theme-control="phoenixNavbarVerticalStyle" /><label class="btn d-block w-100 btn-navbar-style fs-9" for="navbar-style-dark"> <img class="img-fluid img-prototype d-dark-none" src="assets/img/generic/vertical-darker.png" alt="" /><img class="img-fluid img-prototype d-light-none" src="assets/img/generic/vertical-lighter.png" alt="" /><span class="label-text d-dark-none"> Darker</span><span class="label-text d-light-none">Lighter</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Horizontal Navbar Shape</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarShapeDefault" name="navbar-shape" type="radio" value="default" data-theme-control="phoenixNavbarTopShape" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarShapeDefault"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-default-dark.png" alt=""/></span><span class="label-text">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarShapeSlim" name="navbar-shape" type="radio" value="slim" data-theme-control="phoenixNavbarTopShape" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarShapeSlim"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-slim.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-slim-dark.png" alt=""/></span><span class="label-text"> Slim</span></label></div>
          </div>
        </div>
        <div class="setting-panel-item">
          <h5 class="setting-panel-item-title">Horizontal Navbar Appearance</h5>
          <div class="row gx-2">
            <div class="col-6"><input class="btn-check" id="navbarTopDefault" name="navbar-top-style" type="radio" value="default" data-theme-control="phoenixNavbarTopStyle" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarTopDefault"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/top-default.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-style-darker.png" alt=""/></span><span class="label-text">Default</span></label></div>
            <div class="col-6"><input class="btn-check" id="navbarTopDarker" name="navbar-top-style" type="radio" value="darker" data-theme-control="phoenixNavbarTopStyle" /><label class="btn d-inline-block btn-navbar-style fs-9" for="navbarTopDarker"> <span class="mb-2 rounded d-block"><img class="img-fluid img-prototype d-dark-none mb-0" src="assets/img/generic/navbar-top-style-light.png" alt=""/><img class="img-fluid img-prototype d-light-none mb-0" src="assets/img/generic/top-style-lighter.png" alt=""/></span><span class="label-text d-dark-none">Darker</span><span class="label-text d-light-none">Lighter</span></label></div>
          </div>
        </div><a class="bun btn-primary d-grid mb-3 text-white mt-5 btn btn-primary" href="https://themes.getbootstrap.com/product/phoenix-admin-dashboard-webapp-template/" target="_blank">Purchase template</a>
      </div>
    </div>
    <a class="card setting-toggle" href="#settings-offcanvas" data-bs-toggle="offcanvas">
      <div class="card-body d-flex align-items-center px-2 py-1">
        <div class="position-relative rounded-start" style="height:34px;width:28px">
          <div class="settings-popover"><span class="ripple"><span class="fa-spin position-absolute all-0 d-flex flex-center"><span class="icon-spin position-absolute all-0 d-flex flex-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="#ffffff" xmlns="http://www.w3.org/2000/svg"><path d="M19.7369 12.3941L19.1989 12.1065C18.4459 11.7041 18.0843 10.8487 18.0843 9.99495C18.0843 9.14118 18.4459 8.28582 19.1989 7.88336L19.7369 7.59581C19.9474 7.47484 20.0316 7.23291 19.9474 7.03131C19.4842 5.57973 18.6843 4.28943 17.6738 3.20075C17.5053 3.03946 17.2527 2.99914 17.0422 3.12011L16.393 3.46714C15.6883 3.84379 14.8377 3.74529 14.1476 3.3427C14.0988 3.31422 14.0496 3.28621 14.0002 3.25868C13.2568 2.84453 12.7055 2.10629 12.7055 1.25525V0.70081C12.7055 0.499202 12.5371 0.297594 12.2845 0.257272C10.7266 -0.105622 9.16879 -0.0653007 7.69516 0.257272C7.44254 0.297594 7.31623 0.499202 7.31623 0.70081V1.23474C7.31623 2.09575 6.74999 2.8362 5.99824 3.25599C5.95774 3.27861 5.91747 3.30159 5.87744 3.32493C5.15643 3.74527 4.26453 3.85902 3.53534 3.45302L2.93743 3.12011C2.72691 2.99914 2.47429 3.03946 2.30587 3.20075C1.29538 4.28943 0.495411 5.57973 0.0322686 7.03131C-0.051939 7.23291 0.0322686 7.47484 0.242788 7.59581L0.784376 7.8853C1.54166 8.29007 1.92694 9.13627 1.92694 9.99495C1.92694 10.8536 1.54166 11.6998 0.784375 12.1046L0.242788 12.3941C0.0322686 12.515 -0.051939 12.757 0.0322686 12.9586C0.495411 14.4102 1.29538 15.7005 2.30587 16.7891C2.47429 16.9504 2.72691 16.9907 2.93743 16.8698L3.58669 16.5227C4.29133 16.1461 5.14131 16.2457 5.8331 16.6455C5.88713 16.6767 5.94159 16.7074 5.99648 16.7375C6.75162 17.1511 7.31623 17.8941 7.31623 18.7552V19.2891C7.31623 19.4425 7.41373 19.5959 7.55309 19.696C7.64066 19.7589 7.74815 19.7843 7.85406 19.8046C9.35884 20.0925 10.8609 20.0456 12.2845 19.7729C12.5371 19.6923 12.7055 19.4907 12.7055 19.2891V18.7346C12.7055 17.8836 13.2568 17.1454 14.0002 16.7312C14.0496 16.7037 14.0988 16.6757 14.1476 16.6472C14.8377 16.2446 15.6883 16.1461 16.393 16.5227L17.0422 16.8698C17.2527 16.9907 17.5053 16.9504 17.6738 16.7891C18.7264 15.7005 19.4842 14.4102 19.9895 12.9586C20.0316 12.757 19.9474 12.515 19.7369 12.3941ZM10.0109 13.2005C8.1162 13.2005 6.64257 11.7893 6.64257 9.97478C6.64257 8.20063 8.1162 6.74905 10.0109 6.74905C11.8634 6.74905 13.3792 8.20063 13.3792 9.97478C13.3792 11.7893 11.8634 13.2005 10.0109 13.2005Z" fill="#2A7BE4"></path></svg></span></span></span></div>
        </div><small class="text-uppercase text-body-tertiary fw-bold py-2 pe-2 ps-1 rounded-end">customize</small>
      </div>
    </a> -->
    <script>
        document.getElementById('whatsappShareBtn').addEventListener('click', function() {
            // Vehicle details
            const vehicle = {
                make: '<?= addslashes($vehicle["make"]) ?>',
                model: '<?= addslashes($vehicle["model"]) ?>',
                year: '<?= $vehicle["year"] ?>',
                price: '<?= isset($vehicle["sale_price"]) ? number_format($vehicle["sale_price"], 2) : "Price on request" ?>',
                mileage: '<?= number_format($vehicle["mileage"]) ?>',
                color: '<?= addslashes($vehicle["color"]) ?>',
                transmission: '<?= ucfirst($vehicle["transmission"]) ?>',
                fuel: '<?= ucfirst($vehicle["fuel_type"]) ?>',
                features: '<?= addslashes($vehicle["trim"] . ", " . $vehicle["body_style"]) ?>',
                vin: '<?= $vehicle["VIN"] ?>',
                status: '<?= ucfirst($vehicle["status"]) ?>'
            };

            // Get image URLs
            const imageUrls = [];
            document.querySelectorAll('.vehicle-image').forEach(img => {
                imageUrls.push(img.src);
            });

            // Create attractive message
            let message = `🚗 *${vehicle.year} ${vehicle.make} ${vehicle.model}* 🚗\n\n`;
            message += `💰 *Price:* $${vehicle.price}\n`;
            message += `🛣️ *Mileage:* ${vehicle.mileage} km\n`;
            message += `🎨 *Color:* ${vehicle.color}\n`;
            message += `⚙️ *Transmission:* ${vehicle.transmission}\n`;
            message += `⛽ *Fuel Type:* ${vehicle.fuel}\n`;
            message += `✨ *Features:* ${vehicle.features}\n`;
            message += `🔢 *VIN:* ${vehicle.vin}\n`;
            message += `📝 *Status:* ${vehicle.status}\n\n`;

            // Add image links (maximum 5)
            if (imageUrls.length > 0) {
                message += `📸 *Vehicle Images:*\n`;
                const maxImages = Math.min(imageUrls.length, 5);
                for (let i = 0; i < maxImages; i++) {
                    message += `${i+1}. ${imageUrls[i]}\n`;
                }
                if (imageUrls.length > 5) {
                    message += `\n+${imageUrls.length - 5} more images available`;
                }
            }

            message += `\nFor more details, contact us!\n`;
            message += `📞 Phone: [YOUR_PHONE_NUMBER]\n`;
            message += `🌐 Website: [YOUR_WEBSITE_URL]`;

            // Encode for WhatsApp
            const encodedMessage = encodeURIComponent(message);
            window.open(`https://wa.me/?text=${encodedMessage}`, '_blank');
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
    <script src="vendors/echarts/echarts.min.js"></script>
    <script src="assets/js/ecommerce-dashboard.js"></script>
    <script src="vendors/swiper/swiper-bundle.min.js"></script>

</body>


</html>