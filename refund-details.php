<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

$pageTitle = "Refund Receipt";
// Get invoice number from URL
$invoice_number = $_GET['inv'] ?? '';

if (empty($invoice_number)) {
    $_SESSION['error_message'] = "Invoice number is required";
    header("Location: view-all-invoices.php");
    exit();
}

// $sale_id = $_GET['id'] ?? 0;

// Fetch complete sale data with joins
try {
    $stmt = $db->prepare("
        SELECT 
            s.*,
            v.regnumber, v.make, v.model, v.year, v.color, v.mileage, v.VIN, v.keys_count, v.engine_size, v.engine_no,
            v.mot_expiry, v.logbook_status, v.v5_status, v.keys_count,
            c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.state, c.post_code,
            u.full_name as sales_person_name,
            pxv.regnumber as px_regnumber, pxv.make as px_make, pxv.model as px_model,
            pxv.mileage as px_mileage, pxv.color as px_color, pxv.engine_no as px_engine_no, pxv.vin as px_vin, pxv.notes as px_notes,
            fc.company_name, fc.contact_person, fc.phone as finance_phone, fc.email as finance_email, fc.address as finance_address
        FROM sales s
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN users u ON s.sales_person = u.id
        LEFT JOIN vehicles pxv ON s.px_vehicle_id = pxv.vehicle_id 
        LEFT JOIN finance_companies fc ON s.finance_company_id = fc.finance_company_id
        WHERE s.invoice_no = ?
    ");
    $stmt->execute([$invoice_number]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        die("Sale not found.");
    }
} catch (PDOException $e) {
    die("Error fetching sale data: " . $e->getMessage());
}

// Calculate totals
$subtotal = $sale['sale_price'] + $sale['service_fee'] + $sale['delivery_charges'];
$total_deductions = $sale['deposit_received'] + $sale['part_exchange_allowance'] + $sale['finance_amount'] + $sale['discount_amount'];
$balance_due = $sale['balance_due'];
$total_received = $sale['deposit_received'] + $sale['finance_amount'];

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
            <div class="button-group no-print">
                <button onclick="window.print()" class="btn btn-phoenix-primary">🖨️ Print Invoice</button>
                <!-- <a href="view-all-sales.php" class="btn btn-success">📋 Back to Sales</a> -->
            </div><br>
            <!-- ============================================-->
            <!-- <section> begin ============================-->
            <section class="pt-0 pb-3 bg-body-emphasis dark__bg-gray-1200 invoice-area" style="width: 100%;">
                <div class="" style="margin-left:5px; width: 99%; border:0px solid red;">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <h2 class="mb-0">Invoice</h2>
                        <div class="p-0">
                            <img class="pt-0" src="uploads/images/britdrive-01.svg" width="150" />
                        </div>
                    </div>
                    <div class=" p-3 pt-0 pb-0 mb-3 rounded-2" style="background-color:#ddd; border:0px solid red;">
                        <table class="table table-borderless mb-0" style="border:0px solid red;">
                            <tr>
                                <td width="470">
                                    <p class="fs-9 text-body-secondary mb-0"><b>Invoice No :  <?php echo htmlspecialchars($sale['invoice_no'] ?? 'N/A'); ?></b>
                                        <br>Invoice Date : <?php echo htmlspecialchars(date('Y-m-d', strtotime($sale['created_at']))); ?><br>
                                        <!-- Sale Person: Saqib Abbas<br> -->
                                        BritDrive Motors<br>
                                        158 Derby Road <br>
                                        Nottingham,NG16 4AA<br>
                                        info@britdrivemotors.co.uk
                                    </p>
                                </td>
                                <td width="300">
                                    <h6 class="mb-2">Vehicle Details:</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-2">
                                            <?php echo htmlspecialchars($sale['make'] . " " . $sale['model']); ?><br>
                                            Reg Number: <?php echo htmlspecialchars($sale['regnumber']); ?><br>
                                            VIN : <?= ($sale['VIN']) ?? 'N/A' ?><br>
                                            Engine# <?= ($sale['engine_no']) ?? 'N/A' ?><br>
                                            Mileage: <?php echo htmlspecialchars($sale['mileage']); ?><br>
                                        </p>
                                    </div>
                                </td>
                                <td width="230">
                                    <?php if($sale['payment_method'] == 'finance')
                                        { ?>
                                    <h6 class="mb-2"> Invoice To :</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-2">
                                            <?php echo htmlspecialchars($sale['finance_company_name']); ?><br>
                                            <?php echo htmlspecialchars($sale['finance_company_address']); ?><br>
                                            <?php echo htmlspecialchars($sale['finance_company_phone']); ?><br>
                                            <?php echo htmlspecialchars($sale['finance_company_email']); ?>
                                        </p>
                                    </div>
                                    <?php } ?>
                                </td>
                                <td width="300">
                                    <h6 class="mb-2"> Deliver To :</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-2">
                                            <?php echo htmlspecialchars($sale['first_name'] . " " . $sale['last_name']); ?><br>
                                            <?php echo htmlspecialchars($sale['address']); ?><br>
                                            <?php echo htmlspecialchars($sale['city'] . "," . $sale['post_code']); ?><br>
                                            <?php echo htmlspecialchars($sale['phone']); ?><br>
                                            <?php echo htmlspecialchars($sale['email']); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </div>

                    <div class="px-0 ">
                        <div class="row">
                            <div class="col-7 ">
                                <!-- Part Exchange Section -->
                                <?php if ($sale['includes_part_exchange']): ?>
                                    <div class="section mb-0" style=" border:0px solid red; font-size:smaller; width:100%;">
                                        <div class="section-title">Part Exchange Details</div>
                                        <div class="grid-3 ">
                                            <div >
                                                <div class="info-group ">
                                                    <span class="label">Make & Model:</span>
                                                    <span class="value"><?= htmlspecialchars($sale['px_make'] . ' ' . $sale['px_model']) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="info-group">
                                                    <span class="label">Reg No:</span>
                                                    <span class="value"><?= htmlspecialchars($sale['px_regnumber']) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="info-group">
                                                    <span class="label">Mileage:</span>
                                                    <span class="value"><?= $sale['px_mileage'] ?> miles</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="info-group">
                                                    <span class="label">Engine#:</span>
                                                    <span class="value"><?= htmlspecialchars($sale['px_engine_no']) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="info-group">
                                                    <span class="label">VIN:</span>
                                                    <span class="value"><?= htmlspecialchars($sale['px_vin']) ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="info-group">
                                                    <span class="label">Color:</span>
                                                    <span class="value"><?= $sale['px_color'] ?></span>
                                                </div>
                                            </div>

                                        </div>
                                        <?php if(!empty($sale['px_notes'])){ ?>
                                        <div class="pt-2 pb-2">
                                           <div class="info-group">
                                    <label class="label">P/X Notes:</label>
                                    <span class="declaration-box" style="width:100%;"><?= htmlspecialchars($sale['px_notes'] ?? ''); ?></span>
                                </div>
                                </div>
                                <?php } ?>
                                        <!-- <p><strong>PART-EXCHANGE DECLARATION</strong><br>
                                                    I/We certify that the part-exchange vehicle conforms to the following description and is my property, free from any lien or legal encumbrance & free from any outstanding finance. The car is fully HPI clear.
                                                </p> -->
                                    </div>

                                <?php endif; ?>
                                <div class="section mb-0">
                                    <!-- <div class="section-title">Legal Declarations</div> -->

                                    <div class="declaration-box">
                                        <div class="info-group">
                                            <span class="label">HPI Status:</span>
                                            <span class="value" style="text-transform: capitalize;"><?= str_replace('_', ' ', $sale['hpi_clear_status']) ?></span>
                                        </div>

                                        <?php if ($sale['is_sold_as_seen']): ?>
                                            <div class="">
                                                <strong>VEHICLE SOLD "AS SEEN"</strong><br>
                                                I declare that I am the buyer of the vehicle described above at the price stated below and the above vehicle is sold as seen, tried, and tested. There is no warranty with the car. The deposit is non-refundable.
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($sale['includes_part_exchange']): ?>
                                            <div class="">
                                                <strong>PART-EXCHANGE DECLARATION</strong><br>
                                                I/We certify that the part-exchange vehicle conforms to the following description and is my property, free from any lien or legal encumbrance & free from any outstanding finance. The car is fully HPI clear.
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                                        <?php if(!empty($sale['notes'])){ ?>
                                <div class="pt-2 pb-2">
                                    <label for="">General Notes:</label>
                                    <span class="alert-box" style="width:100%;"><?= htmlspecialchars($sale['notes'] ?? ''); ?></span>
                                </div>
                                            <?php } ?>
                            </div>
                            <div class="col-5">
                                <!-- Table  -->
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th style="text-align: right;">Amount (£)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Vehicle Sale Price</td>
                                            <td style="text-align: right;"><?= number_format($sale['sale_price'], 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Service Fee</td>
                                            <td style="text-align: right;"><?= number_format($sale['service_fee'], 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Delivery Charges</td>
                                            <td style="text-align: right;"><?= number_format($sale['delivery_charges'], 2) ?></td>
                                        </tr>
                                        <tr class="total-row">
                                            <td><strong>Subtotal</strong></td>
                                            <td style="text-align: right;"><strong><?= number_format($subtotal, 2) ?></strong></td>
                                        </tr>

                                        <!-- Deductions -->
                                        <tr>
                                            <td>Deposit Received</td>
                                            <td style="text-align: right;">-<?= number_format($sale['deposit_received'], 2) ?></td>
                                        </tr>
                                        <?php if ($sale['includes_part_exchange']): ?>
                                            <tr>
                                                <td>Part Exchange Allowance</td>
                                                <td style="text-align: right;">-<?= number_format($sale['part_exchange_allowance'], 2) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td>Finance Amount</td>
                                            <td style="text-align: right;">-<?= number_format($sale['finance_amount'], 2) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Discount</td>
                                            <td style="text-align: right;">-<?= number_format($sale['discount_amount'], 2) ?></td>
                                        </tr>
                                        
                                        <tr class="total-row">
                                            <td><strong>Total Deductions</strong></td>
                                            <td style="text-align: right;"><strong>-<?= number_format($total_deductions, 2) ?></strong></td>
                                        </tr>

                                        <!-- Final Balance -->
                                        <tr style="background: #e8f5e8;">
                                            <td><strong>BALANCE DUE</strong></td>
                                            <td style="text-align: right; font-size: 16px;"><strong>£<?= number_format($balance_due, 2) ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class=""><small>
                                            <strong>DECLARATIONS:</strong>

                                            For repairs, customers will have to bring the car to our approved garages and allow reasonable time to fix it. The courtesy car is only available at additional cost. The insurance and road tax for the courtesy car is customer responsibility. Recovery of the vehicle to our premises is customer responsibility. Any notice to be given under this agreement must be in writing and sent by post to the address of the person to whom it is addressed as set out overleaf and shall be deemed to have been received in due course of post. The diagnostics of the vehicle is customer responsibility. The parts, repair and labour is on the dealership (T&C applies). If the customer is unable to produce a diagnosis report from an approved VAT registered garage, no repair will be entertained. If the customer carries out a repair of the vehicle without the dealership approval the warranty will be voided. Admin/service fee and delivery charges are non-refundable. General wear and tear is not covered under warranty.


                                        </small>
                                        </div>
                            </div>
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