<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Edit Sale Invoice";

$invoice_no = $_GET['inv'] ?? '';
$success = $error = '';

// Fetch invoice details with all related data
$sale = [];
try {
    $stmt = $db->prepare("
        SELECT 
            s.*,
            v.vehicle_id, v.regnumber, v.make, v.model, v.year, v.color, v.mileage, v.VIN, 
            v.keys_count, v.engine_size, v.engine_no, v.mot_expiry, v.logbook_status, 
            v.v5_status, v.purchase_price,
            c.customer_id, c.title, c.first_name, c.last_name, c.email, c.phone, c.address, 
            c.city, c.state, c.post_code,
            u.full_name as sales_person_name,
            pxv.vehicle_id as px_vehicle_id, pxv.regnumber as px_regnumber, 
            pxv.make as px_make, pxv.model as px_model, pxv.mileage as px_mileage, 
            pxv.color as px_color, pxv.engine_no as px_engine_no,pxv.vin as px_vin, pxv.notes as px_notes
        FROM sales s
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN users u ON s.sales_person = u.id
        LEFT JOIN vehicles pxv ON s.px_vehicle_id = pxv.vehicle_id
        WHERE s.invoice_no = ?
    ");
    $stmt->execute([$invoice_no]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        $_SESSION['error'] = "Invoice not found.";
        header('Location: view-all-invoices.php');
        exit;
    }

    // Set the vehicle_id for the form
    $vehicle_id = $sale['vehicle_id'];
} catch (PDOException $e) {
    $error = "Error fetching invoice: " . $e->getMessage();
}

$financeCompanies = [];

$stmt = $db->query("
    SELECT *
    FROM finance_companies
    WHERE status = 1
    ORDER BY company_name
");

$financeCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);


// If a finance company is selected for this sale, find its details
$selectedCompanyAddress = $selectedCompanyPhone = $selectedCompanyEmail = '';
if (!empty($sale['finance_company_id']) && !empty($financeCompanies)) {
    foreach ($financeCompanies as $company) {
        echo($company['finance_company_id']."<br>");
         if ($company['finance_company_id'] === $sale['finance_company_id']) {
            $selectedCompanyAddress = $company['address'] ?? '';
            $selectedCompanyPhone   = $company['phone'] ?? '';
            $selectedCompanyEmail   = $company['email'] ?? '';

             break;
         }
    }
}



// Fetch payment history for this sale
$payments = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM sale_payments 
        WHERE sale_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$sale['sale_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching payments: " . $e->getMessage();
}

// Fetch existing customers for dropdown (same as before)
$customers = [];
try {
    $stmt = $db->query("SELECT customer_id, first_name, last_name, phone FROM customers ORDER BY first_name, last_name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching customers: " . $e->getMessage();
}

/*********************** */
// Handle form submission
/*********************** */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        //        $db->beginTransaction();

        // Get existing sale_id
        $sale_id = $sale['sale_id'];
        $invoice_no = $sale['invoice_no']; // Keep original invoice number

        // Basic sale data
        $sale_price = $_POST['sale_price'];
        $sale_date = $_POST['sale_date'];
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        $px_notes = $_POST['px_notes'] ?? '';
        $customer_id = $_POST['customer_id'];
        $service_fee = $_POST['service_fee'] ?? 0;
        $deposit_received = $_POST['deposit_received'] ?? 0;
        $delivery_charges = $_POST['delivery_charges'] ?? 0;
        $additional_services = $_POST['additional_services'] ?? 0;
        $finance_company_id = $_POST['finance_company_name'] ?? null;
        $finance_amount = $_POST['finance_amount'] ?? 0;
        $discount_amount = $_POST['discount_amount'] ?? 0;
        $is_sold_as_seen = isset($_POST['is_sold_as_seen']) ? 1 : 0;
        $hpi_clear_status = $_POST['hpi_clear_status'] ?? 'not_checked';
        $includes_part_exchange = isset($_POST['includes_part_exchange']) ? 1 : 0;
        $includes_finance = isset($_POST['includes_finance']) ? 1 : 0;
        $part_exchange_allowance = $_POST['part_exchange_allowance'] ?? 0;
        // Calculate new balance due
        $balance_due = $sale_price + $service_fee + $delivery_charges + $additional_services - $deposit_received - $part_exchange_allowance  - $discount_amount;

        // Handle customer updates
        if (isset($customer_id)) {

            // Update existing customer or create new one
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $post_code = trim($_POST['post_code'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($phone)) {
                throw new Exception("First name, last name, and phone are required.");
            }

            // Update existing customer
            $stmt = $db->prepare("
        UPDATE customers SET 
          first_name = ?, last_name = ?, email = ?, phone = ?, 
          address = ?, city = ?, post_code = ?
        WHERE customer_id = ?
      ");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $city,
                $post_code,
                $sale['customer_id']
            ]);
            $customer_id = $sale['customer_id'];
        }

        // Handle part-exchange updates
        $px_vehicle_id = $_POST['px_vehicle_id'] ?: null;

        if ($includes_part_exchange) {
            $px_make = trim($_POST['px_make']);
            $px_model = trim($_POST['px_model']);
            $px_regnumber = trim($_POST['px_regnumber']);
            $px_mileage = $_POST['px_mileage'] ?? 0;
            $px_engine_no = trim($_POST['px_engine_no']);
            $px_vin = trim($_POST['px_vin']);
            $px_color = trim($_POST['px_color'] ?? '');
            $px_notes = trim($_POST['px_notes'] ?? '');

            if (empty($px_make) || empty($px_model) || empty($px_regnumber)) {
                throw new Exception("Part-exchange vehicle make, model, and registration are required.");
            }

            if ($px_vehicle_id) {
                // UPDATE existing P/X vehicle (correcting details)
                $stmt = $db->prepare("
                                        UPDATE vehicles SET 
                                            regnumber = ?, make = ?, model = ?, mileage = ?, engine_no = ?, vin = ?, color = ?, notes = ?
                                        WHERE vehicle_id = ?
                                    ");
                $stmt->execute([
                    $px_regnumber,
                    $px_make,
                    $px_model,
                    $px_mileage,
                    $px_engine_no,
                    $px_vin,
                    $px_color,
                    $px_notes,
                    $px_vehicle_id
                ]);
                //  same px_vehicle_id will go on

            } else {
                // CREATE new P/X vehicle (first time adding P/X)
                $stmt = $db->prepare("
                                        INSERT INTO vehicles (regnumber, make, model, mileage, engine_no, vin, color, status, purchase_date, purchase_price, created_by, notes)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, 'part_exchange', CURDATE(), ?, ?, ?)
                                    ");
                $stmt->execute([
                    $px_regnumber,
                    $px_make,
                    $px_model,
                    $px_mileage,
                    $px_engine_no,
                    $px_vin,
                    $px_color,
                    $part_exchange_allowance,
                    $_SESSION['user_id'],
                    $px_notes
                ]);
                $px_vehicle_id = $db->lastInsertId();
            }
        } else {
            // P/X was REMOVED - DELETE the vehicle record
            if ($px_vehicle_id) {
                $stmt = $db->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
                $stmt->execute([$px_vehicle_id]);
            }
            $px_vehicle_id = null;
        }

        // Handle finance company updates
        if ($payment_method === "finance") {
            $finance_company_id = trim($_POST['finance_company_name']);
            $finance_amount = $_POST['finance_amount'] ?? 0;
        } else {
            // Not finance payment - clear all finance data
            $finance_company_id = null;
            $finance_amount = 0;
        }

        // Update sale record
        $stmt = $db->prepare("
            UPDATE sales SET 
                customer_id = ?, sale_price = ?, sale_date = ?, payment_method = ?, 
                sales_person = ?, notes = ?, px_notes = ?, service_fee = ?, deposit_received = ?, 
                delivery_charges = ?, additional_services = ?, px_vehicle_id = ?, part_exchange_allowance = ?, finance_amount = ?, 
                discount_amount = ?, balance_due = ?, is_sold_as_seen = ?, hpi_clear_status = ?, 
                includes_part_exchange = ?, finance_company_id = ?
            WHERE sale_id = ?
        ");

        $stmt->execute([
            $customer_id,
            $sale_price,
            $sale_date,
            $payment_method,
            $_SESSION['user_id'],
            $notes,
            $px_notes,
            $service_fee,
            $deposit_received,
            $delivery_charges,
            $additional_services,
            $px_vehicle_id,
            $part_exchange_allowance,
            $finance_amount,
            $discount_amount,
            $balance_due,
            $is_sold_as_seen,
            $hpi_clear_status,
            $includes_part_exchange,
            $finance_company_id,
            $sale_id
        ]);

        // TODO: Handle payment record updates (more complex - we'll do this next)
        // For now, we'll just update the sale record
        //die;
        //$db->commit();
        $_SESSION['success'] = "Invoice updated successfully!";
        header("Location: view-all-invoices.php?inv=" . urlencode($invoice_no));
        exit;
    } catch (Exception $e) {
        //    $db->rollBack();
        $error = "Error updating invoice: " . $e->getMessage();
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
    <style>
        .form-control:required,
        .form-select:required {
            border-left: 3px solid #007bff;
        }

        .form-control:invalid,
        .form-select:invalid {
            border-color: #dc3545;
        }

        .form-control:valid,
        .form-select:valid {
            border-color: #28a745;
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
            <!-- <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?> -->
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12 col-xxl-12">

                        <br>

                        <div class="row flex-between-center mb-4 g-3">
                            <form method="post">
                                <input type="hidden" name="px_vehicle_id" value="<?php echo $sale['px_vehicle_id'] ?? ''; ?>">
                                <input type="hidden" name="customer_id" value="<?php echo $sale['customer_id'] ?? ''; ?>">
                                <div class="container">
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <ul>
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?= $error ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <h2>Edit Sale Invoice: <?= htmlspecialchars($sale['invoice_no']) ?></h2>
                                    <div class="row">
                                        <div class="col-12 col-md-12 col-lg-8 col-xxl-8">
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5>Vehicle Information</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Make/Model:</strong> <?= htmlspecialchars($sale['make']) ?> <?= htmlspecialchars($sale['model']) ?></p>
                                                            <p><strong>Year:</strong> <?= $sale['year'] ?></p>
                                                            <p><strong>Chasis#/VIN:</strong> <?= htmlspecialchars($sale['VIN']) ?: 'N/A' ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <!-- <p><strong>Purchase Price:</strong> $<?= number_format($sale['purchase_price'], 2) ?></p> -->
                                                            <p><strong>Reg Number:</strong> <?= $sale['regnumber'] ?></p>
                                                            <p><strong>Color:</strong> <?= htmlspecialchars($sale['color']) ?: 'N/A' ?></p>
                                                            <p><strong>Mileage:</strong> <?= htmlspecialchars($sale['mileage']) ?: 'N/A' ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <!-- <?= htmlspecialchars($sale['notes']) ?> -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5>Customer Information</h5>
                                                </div>
                                                <div class="card-body">

                                                    <!-- Existing Customer Section -->
                                                    <div id="existingCustomerSection">
                                                        <div class="row mb-3">
                                                            <div class="col-md-2">
                                                                <label for="title" class="form-label">Title*</label>
                                                                <select class="form-select" id="title" name="title" required>
                                                                <option value="">Select Title</option>
                                                                <option value="Mr" <?= ($sale['title'] === 'Mr') ? 'selected' : '' ?>>Mr</option>
                                                                <option value="Mrs" <?= ($sale['title'] === 'Mrs') ? 'selected' : '' ?>>Mrs</option>
                                                                <option value="Miss" <?= ($sale['title'] === 'Miss') ? 'selected' : '' ?>>Miss</option>
                                                                <option value="Ms" <?= ($sale['title'] === 'Ms') ? 'selected' : '' ?>>Ms</option>
                                                                <option value="Dr" <?= ($sale['title'] === 'Dr') ? 'selected' : '' ?>>Dr</option>
                                                                <option value="Prof" <?= ($sale['title'] === 'Prof') ? 'selected' : '' ?>>Prof</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="first_name" class="form-label">First Name*</label>
                                                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= $sale['first_name'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="last_name" class="form-label">Last Name*</label>
                                                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= $sale['last_name'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="phone" class="form-label">Phone*</label>
                                                                <input type="text" class="form-control" id="phone" name="phone" value="<?= $sale['phone'] ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="email" class="form-label">Email</label>
                                                                <input type="email" class="form-control" id="email" name="email" value="<?= $sale['email'] ?>">
                                                            </div>

                                                            <div class="col-md-4">
                                                                <label for="city" class="form-label">City</label>
                                                                <input type="text" class="form-control" id="city" name="city" value="<?= $sale['city'] ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label for="post_code" class="form-label">Post Code</label>
                                                                <input type="text" class="form-control" id="post_code" name="post_code" value="<?= $sale['post_code'] ?>">
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="address" class="form-label">Address</label>
                                                                <textarea class="form-control" id="address" name="address"><?= $sale['address'] ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5>Sale Details</h5>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Sale Details -->
                                                    <!-- Sale Financial Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="sale_price" class="form-label">Sale Price (£)*</label>
                                                            <input type="number" step="0.01" class="form-control" id="sale_price" name="sale_price"
                                                                value="<?= $sale['sale_price'] ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="sale_date" class="form-label">Sale Date*</label>
                                                            <input type="date" class="form-control" id="sale_date" name="sale_date"
                                                                value="<?= $sale['sale_date'] ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label for="payment_method" class="form-label">Payment Method*</label>
                                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                                <option value="cash" <?= ($sale['payment_method'] == 'cash' ? 'selected' : '') ?>>Cash</option>
                                                                <option value="bank_transfer" <?= ($sale['payment_method'] == 'bank_transfer' ? 'selected' : '') ?>>Bank Transfer</option>
                                                                <option value="finance" <?= ($sale['payment_method'] == 'finance' ? 'selected' : '') ?>>Finance</option>
                                                                <option value="check" <?= ($sale['payment_method'] == 'check' ? 'selected' : '') ?>>Check</option>
                                                                <option value="other" <?= ($sale['payment_method'] == 'other' ? 'selected' : '') ?>>Other</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <!-- Additional Financial Fields -->
                                                    <div class="row mb-3">
                                                        <div class="col-md-3">
                                                            <label for="service_fee" class="form-label">Service Fee (£)</label>
                                                            <input type="number" step="0.01" class="form-control" id="service_fee" name="service_fee" value="<?= $sale['service_fee'] ?>">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="deposit_received" class="form-label">Deposit Received (£)</label>
                                                            <input type="number" step="0.01" class="form-control" id="deposit_received" name="deposit_received" value="<?= $sale['deposit_received'] ?>">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="delivery_charges" class="form-label">Delivery Charges (£)</label>
                                                            <input type="number" step="0.01" class="form-control" id="delivery_charges" name="delivery_charges" value="<?= $sale['delivery_charges'] ?>">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="additional_services" class="form-label">Additional Services (£)</label>
                                                            <input type="number" step="0.01" class="form-control" id="additional_services" name="additional_services" value="<?= $sale['additional_services'] ?>">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="discount_amount" class="form-label">Discount (£)</label>
                                                            <input type="number" step="0.01" class="form-control" id="discount_amount" name="discount_amount" value="<?= $sale['discount_amount'] ?>">
                                                        </div>
                                                    </div>
                                                    <!-- Finance Toggle -->

                                                    <div id="financeSection" style="display: none;" class="finance-section">
                                                        <h4 class="p-2" style="background: #ccc;">Finance Section</h4>
                                                        <!-- <p class="fs-9">Deduct: Allowance for used vehicle which I/We certify to confirm to
                                                                the following description is my property and free from any lien or
                                                                legal encumbrance & is free from any outstanding finance. The car
                                                                is fully HPI clear.
                                                            </p> -->
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label for="finance_company_name" class="form-label">Company Name *</label>
                                                                <select class="form-select"
                                                                id="finance_company_name"
                                                                name="finance_company_name"
                                                                required>

                                                                <option value="">Select Finance Company</option>

                                                                <?php foreach ($financeCompanies as $company): ?>

                                                                    <option
                                                                    value="<?= $company['finance_company_id'] ?>"
                                                                    data-name="<?= htmlspecialchars($company['company_name']) ?>"
                                                                    data-address="<?= htmlspecialchars($company['address']) ?>"
                                                                    data-phone="<?= htmlspecialchars($company['phone']) ?>"
                                                                    data-email="<?= htmlspecialchars($company['email']) ?>"
                                                                    <?= ($sale['finance_company_id'] == $company['finance_company_id'] ? 'selected' : '') ?>>
                                                                    <?= htmlspecialchars($company['company_name']) ?>
                                                                    </option>

                                                                <?php endforeach; ?>

                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="finance_amount" class="form-label">Finance Amount (£)</label>
                                                                <input type="number" step="0.01" class="form-control" id="finance_amount" name="finance_amount" value="<?= $sale['finance_amount'] ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col-md-5">
                                                                <label for="finance_company_address" class="form-label">Company Address</label>
                                                                <textarea class="form-control" id="finance_company_address" name="finance_company_address" rows="2" readonly><?= $selectedCompanyAddress  ?></textarea>
                                                            </div>
                                                            <div class="col-md-7">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <label for="finance_company_phone" class="form-label">Phone</label>
                                                                        <input type="text" class="form-control" id="finance_company_phone" name="finance_company_phone" value="<?= $selectedCompanyPhone ?>" readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label for="finance_company_email" class="form-label">Email</label>
                                                                        <input type="email" class="form-control" id="finance_company_email" name="finance_company_email" value="<?= $selectedCompanyEmail ?>" readonly>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Part Exchange Toggle -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="includes_part_exchange"
                                                                    id="includes_part_exchange" value="<?= $sale['includes_part_exchange'] ?>" <?= ($sale['includes_part_exchange'] == 1 ? 'checked' : '') ?>>
                                                                <label class="form-check-label fw-bold" for="includes_part_exchange">
                                                                    This sale includes part-exchange vehicle
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div id="partExchangeSection" style="display: none;" class="part-exchange-section">
                                                        <h6 class="mb-3">Part-Exchange Vehicle Details</h6>
                                                        <p class="fs-9">Deduct: Allowance for used vehicle which I/We certify to confirm to
                                                            the following description is my property and free from any lien or
                                                            legal encumbrance & is free from any outstanding finance. The car
                                                            is fully HPI clear.
                                                        </p>
                                                        <div class="row mb-3">
                                                            <div class="col-md-3">
                                                                <label for="px_make" class="form-label">Make*</label>
                                                                <input type="text" class="form-control" id="px_make" name="px_make" value="<?= $sale['px_make'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="px_model" class="form-label">Model*</label>
                                                                <input type="text" class="form-control" id="px_model" name="px_model" value="<?= $sale['px_model'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="px_regnumber" class="form-label">Registration*</label>
                                                                <input type="text" class="form-control" id="px_regnumber" name="px_regnumber" value="<?= $sale['px_regnumber'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="px_mileage" class="form-label">Mileage</label>
                                                                <input type="number" class="form-control" id="px_mileage" name="px_mileage" value="<?= $sale['px_mileage'] ?>">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-3">
                                                            <div class="col-md-3">
                                                                <label for="px_color" class="form-label">Color</label>
                                                                <input type="text" class="form-control" id="px_color" name="px_color" value="<?= $sale['px_color'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="px_engine_no" class="form-label">Engine No*</label>
                                                                <input type="text" class="form-control" id="px_engine_no" name="px_engine_no" value="<?= $sale['px_engine_no'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="px_vin" class="form-label">VIN*</label>
                                                                <input type="text" class="form-control" id="px_vin" name="px_vin" value="<?= $sale['px_vin'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="part_exchange_allowance" class="form-label">Allowance Given (£)*</label>
                                                                <input type="number" step="0.01" class="form-control" id="part_exchange_allowance" name="part_exchange_allowance" value="<?= $sale['part_exchange_allowance'] ?>">
                                                            </div>

                                                            <div class="col-md-12">
                                                                <label for="px_notes" class="form-label">Condition Notes</label>
                                                                <textarea class="form-control" id="px_notes" name="px_notes" rows="2"><?= $sale['px_notes'] ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>


                                                </div>

                                            </div>
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5>Declaration</h5>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Legal Declarations -->
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_sold_as_seen"
                                                                    id="is_sold_as_seen" value="1" checked>
                                                                <label class="form-check-label" for="is_sold_as_seen">
                                                                    Vehicle sold "as seen" - no warranty provided<br>
                                                                    I declare that I am the buyer of the vehicle described above the price
                                                                    stated below and the above vehicle is sold as seen, tried, and tested.
                                                                    There is no warranty with the car. The deposit is non-refundable.

                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="hpi_clear_status" class="form-label">HPI Status</label>
                                                            <select class="form-select" id="hpi_clear_status" name="hpi_clear_status">
                                                                <option value="Clear" <?= ($sale['hpi_clear_status'] == 'Clear' ? 'selected' : '') ?>>HPI Clear</option>
                                                                <option value="Declared as Cat-S" <?= ($sale['hpi_clear_status'] == 'Declared as Cat-S' ? 'selected' : '') ?>>Declared Cat-S</option>
                                                                <option value="Declared as Cat-N" <?= ($sale['hpi_clear_status'] == 'Declared as Cat-N' ? 'selected' : '') ?>>Declared Cat-N</option>
                                                                <option value="Declared as Cat-U" <?= ($sale['hpi_clear_status'] == 'Declared as Cat-U' ? 'selected' : '') ?>>Declared Cat-U</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="notes">Notes</label>
                                                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= $sale['notes'] ?></textarea>
                                                        </div>
                                                    </div>


                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-12 col-lg-4 col-xxl-4">
                                            <!-- Financial Summary -->
                                            <style>
                                                .financial-summary {
                                                    position: sticky;
                                                    top: 75px;
                                                    background: white;

                                                }
                                            </style>
                                            <div class="card mb-4 financial-summary">
                                                <div class="card-header">
                                                    <h5>Financial Summary</h5>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Legal Declarations -->
                                                    <!-- Financial Summary -->
                                                    <div class="financial-summary">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <table class="table table-sm table-borderless">
                                                                    <tr>
                                                                        <td><strong>Sold Price:</strong></td>
                                                                        <td>£<span id="displaySoldPrice">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Service Fee:</strong></td>
                                                                        <td>£<span id="displayServiceFee">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Delivery Charges:</strong></td>
                                                                        <td>£<span id="displayDeliveryCharges">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Additional Services:</strong></td>
                                                                        <td>£<span id="displayAdditionalServices">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Discount Amount:</strong></td>
                                                                        <td>£<span id="displayDiscountAmount">0.00</span></td>
                                                                    </tr>
                                                                    <tr class="table-active">
                                                                        <td><strong>Subtotal:</strong></td>
                                                                        <td><strong>£<span id="displaySubtotal">0.00</span></strong></td>
                                                                    </tr>
                                                                </table>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr>
                                                                        <td><strong>Deposit Received:</strong></td>
                                                                        <td>£<span id="displayDepositReceived">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>P/X Allowance:</strong></td>
                                                                        <td>£<span id="displayPXAllowance">0.00</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Finance Amount:</strong></td>
                                                                        <td>£<span id="displayFinanceAmount">0.00</span></td>
                                                                    </tr>
                                                                    
                                                                    <!-- <tr class="table-active">
                                                                        <td><strong>Total Deductions:</strong></td>
                                                                        <td><strong>£<span id="displayTotalDeductions">0.00</span></strong></td>
                                                                    </tr> -->
                                                                    <tr class="table-success">
                                                                        <td><strong>Balance Due:</strong></td>
                                                                        <td><strong>£<span id="displayBalanceDue">0.00</span></strong></td>
                                                                    </tr>
                                                                    <!-- <tr class="table-info">
                                                                        <td><strong>Total Price Received:</strong></td>
                                                                        <td><strong>£<span id="displayTotalReceived">0.00</span></strong></td>
                                                                    </tr> -->
                                                                </table>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-success">Update Invoice</button>
                                        <a href="index.php" class="btn btn-secondary">Back to Home</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        

                    </div>
                </div>

            </div>
        </div>

        </div>
        </div>


        </div>
        <?php include_once("footer.php"); ?>

        </div>


    </main>
    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->


    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Customer type toggle
            // Part exchange toggle
            const partExchangeCheckbox = document.getElementById('includes_part_exchange');
            const partExchangeSection = document.getElementById('partExchangeSection');

            // Finance toggle
            const financeCheckbox = document.getElementById('includes_finance');
            const financeSection = document.getElementById('financeSection');

            // Financial fields
            const salePrice = document.getElementById('sale_price');
            const serviceFee = document.getElementById('service_fee');
            const deliveryCharges = document.getElementById('delivery_charges');
            const additionalServices = document.getElementById('additional_services');
            const depositReceived = document.getElementById('deposit_received');
            const partExchangeAllowance = document.getElementById('part_exchange_allowance');
            const financeAmount = document.getElementById('finance_amount');
            const discountAmount = document.getElementById('discount_amount');
            const totalDueSpan = document.getElementById('totalDue');
            const balanceSpan = document.getElementById('balanceDisplay');


            function togglePartExchangeSection() {
                if (partExchangeCheckbox.checked) {
                    partExchangeSection.style.display = 'block';
                    // Set part exchange fields as required
                    document.getElementById('px_make').required = true;
                    document.getElementById('px_model').required = true;
                    document.getElementById('px_regnumber').required = true;
                    document.getElementById('part_exchange_allowance').required = true;
                } else {
                    partExchangeSection.style.display = 'none';
                    // Clear part exchange requirements
                    document.getElementById('px_make').required = false;
                    document.getElementById('px_model').required = false;
                    document.getElementById('px_regnumber').required = false;
                    document.getElementById('part_exchange_allowance').required = false;
                }

                calculateBalance();
            }

            function toggleFinanceSection() {
                const paymentMethod = document.getElementById('payment_method').value;
                const financeSection = document.getElementById('financeSection');
                const financeCompanyName = document.getElementById('finance_company_name');
                const financeCompanyAddress = document.getElementById('finance_company_address');
                const financeCompanyPhone = document.getElementById('finance_company_phone');
                const financeCompanyEmail = document.getElementById('finance_company_email');
                const financeAmountField = document.getElementById('financeAmount');

                if (paymentMethod === 'finance') {
                    financeSection.style.display = 'block';
                    // Set finance fields as required
                    document.getElementById('finance_amount').required = true;
                    financeCompanyName.required = true;
                    //financeAmountField.required = true;

                } else {
                    //  document.getElementById('finance_amount').value = 0;
                    financeSection.style.display = 'none';
                    // Clear finance requirements
                    // financeCompanyName.required = false;
                    //financeAmountField.required = false;
                    document.getElementById('finance_amount').required = false;
                    //   document.getElementById('finance_amount').value = 0;
                    //   document.getElementById('finance_company_name').value = '';
                    //   document.getElementById('finance_company_address').value = '';
                    //   document.getElementById('finance_company_phone').value = '';
                    //   document.getElementById('finance_company_email').value = '';
                }

                calculateBalance();
            }



            function calculateBalance() {
                const salePriceVal = parseFloat(salePrice.value) || 0;
                const serviceFeeVal = parseFloat(serviceFee.value) || 0;
                const deliveryChargesVal = parseFloat(deliveryCharges.value) || 0;
                const additionalServicesVal = parseFloat(additionalServices.value) || 0;
                const depositReceivedVal = parseFloat(depositReceived.value) || 0;
                const discountAmountVal = parseFloat(discountAmount.value) || 0;


                // Only include finance amount if payment method is finance
                const paymentMethod = document.getElementById('payment_method').value;
                const financeAmountVal = (paymentMethod === 'finance') ?
                    (parseFloat(document.getElementById('finance_amount').value) || 0) : 0;

                // Only include part exchange allowance if part exchange is checked
                const partExchangeChecked = document.getElementById('includes_part_exchange').checked;
                const partExchangeAllowanceVal = partExchangeChecked ?
                    (parseFloat(document.getElementById('part_exchange_allowance').value) || 0) : 0;

                // Calculations
                const subtotal = salePriceVal + serviceFeeVal + deliveryChargesVal + additionalServicesVal  - discountAmountVal;
                const totalDeductions = depositReceivedVal + partExchangeAllowanceVal ;
                const balanceDue = subtotal - totalDeductions;
                const totalReceived = depositReceivedVal + financeAmountVal;

                // Update displays
                document.getElementById('displaySoldPrice').textContent = salePriceVal.toFixed(2);
                document.getElementById('displayServiceFee').textContent = serviceFeeVal.toFixed(2);
                document.getElementById('displayDeliveryCharges').textContent = deliveryChargesVal.toFixed(2);
                document.getElementById('displayAdditionalServices').textContent = additionalServicesVal.toFixed(2);
                document.getElementById('displaySubtotal').textContent = subtotal.toFixed(2);

                document.getElementById('displayDepositReceived').textContent = depositReceivedVal.toFixed(2);
                document.getElementById('displayPXAllowance').textContent = partExchangeAllowanceVal.toFixed(2);
                document.getElementById('displayFinanceAmount').textContent = financeAmountVal.toFixed(2);
                document.getElementById('displayDiscountAmount').textContent = discountAmountVal.toFixed(2);
                //document.getElementById('displayTotalDeductions').textContent = totalDeductions.toFixed(2);

                document.getElementById('displayBalanceDue').textContent = balanceDue.toFixed(2);
                //document.getElementById('displayTotalReceived').textContent = totalReceived.toFixed(2);
                console.log('Payment Method:', paymentMethod);
            }



            // Add event listeners
            partExchangeCheckbox.addEventListener('change', togglePartExchangeSection);
            // Add event listener for payment method change
            document.getElementById('payment_method').addEventListener('change', toggleFinanceSection);
            //financeCheckbox.addEventListener('change', toggleFinanceSection);

            // Financial field listeners
            [salePrice, serviceFee, deliveryCharges, additionalServices, depositReceived, partExchangeAllowance, financeAmount, discountAmount].forEach(field => {
                field.addEventListener('input', calculateBalance);
            });

            // Initialize on page load
            togglePartExchangeSection();
            toggleFinanceSection();
            calculateBalance();
        });
    </script>

    <script>
        // Add form submit event listener
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault(); // Prevent form submission
                alert('Please fill all required fields before submitting.');
            }
        });

        function validateForm() {
            let isValid = true;

            // Check sale price and date (always required)
            if (!document.getElementById('sale_price').value ||
                !document.getElementById('sale_date').value ||
                !document.getElementById('payment_method').value) {
                isValid = false;
            }

            // Check customer information based on type
            if (document.getElementById('existing_customer').checked) {
                if (!document.getElementById('existing_customer_id').value) {
                    isValid = false;
                    highlightField(document.getElementById('existing_customer_id'));
                }
            } else {
                if (!document.getElementById('first_name').value ||
                    !document.getElementById('last_name').value ||
                    !document.getElementById('phone').value) {
                    isValid = false;
                    highlightField(document.getElementById('first_name'));
                    highlightField(document.getElementById('last_name'));
                    highlightField(document.getElementById('phone'));
                }
            }

            // Check finance fields if payment method is finance
            if (document.getElementById('payment_method').value === 'finance') {
                if (!document.getElementById('finance_company_name').value ||
                    !document.getElementById('finance_amount').value ||
                    parseFloat(document.getElementById('finance_amount').value) <= 0) {
                    isValid = false;
                    highlightField(document.getElementById('finance_company_name'));
                    highlightField(document.getElementById('finance_amount'));
                }
            }

            // Check part exchange fields if part exchange is included
            if (document.getElementById('includes_part_exchange').checked) {
                if (!document.getElementById('px_make').value ||
                    !document.getElementById('px_model').value ||
                    !document.getElementById('px_regnumber').value ||
                    !document.getElementById('part_exchange_allowance').value) {
                    isValid = false;
                    highlightField(document.getElementById('px_make'));
                    highlightField(document.getElementById('px_model'));
                    highlightField(document.getElementById('px_regnumber'));
                    highlightField(document.getElementById('part_exchange_allowance'));
                }
            }

            return isValid;
        }

        function highlightField(field) {
            if (field) {
                field.style.borderColor = 'red';
                // Remove red border when user starts typing
                field.addEventListener('input', function() {
                    this.style.borderColor = '';
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // ... your existing initialization code ...

            // Initialize form validation
            toggleCustomerSections();
            togglePartExchangeSection();
            toggleFinanceSection();

            // Add real-time validation
            const requiredFields = document.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
            });
        });

        function validateField(field) {
            if (!field.value) {
                field.style.borderColor = 'red';
            } else {
                field.style.borderColor = '';
            }
        }
    </script>

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