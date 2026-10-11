<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/refund_calculations.php';

$pageTitle = "Create New Refund";
$refundCalculator = new RefundCalculator($db);

// Check permissions
if (!hasPermission('manage_sales')) {
    $_SESSION['error'] = "You don't have permission to create refunds.";
    header('Location: dashboard.php');
    exit;
}

$success = $error = '';
$sale_data = null;
$refund_details = null;
$max_refundable = 0;

// Handle sale lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_sale'])) {
    $invoice_no = trim($_POST['invoice_no']);

    if (empty($invoice_no)) {
        $error = "Please enter an invoice number.";
    } else {
        try {
            $stmt = $db->prepare("
                SELECT 
                    s.sale_id, s.invoice_no, s.sale_price, s.sale_date, s.status as sale_status,
                    s.balance_due, s.service_fee, s.delivery_charges, s.deposit_received,
                    s.part_exchange_allowance, s.finance_amount, s.px_vehicle_id, s.finance_company_name, s.payment_method,
                    s.discount_amount,
                    v.vehicle_id, v.make, v.model, v.regnumber, v.year, v.mileage, v.status as vehicle_status,
                    pxv.vehicle_id as px_vehicle_id, pxv.make as px_make, pxv.model as px_model, 
                    pxv.regnumber as px_regnumber, pxv.year as px_year, pxv.status as px_vehicle_status,
                    c.customer_id, c.first_name, c.last_name, c.email, c.phone,
                    u.full_name as salesperson_name
                FROM sales s
                LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                LEFT JOIN vehicles pxv ON s.px_vehicle_id = pxv.vehicle_id
                LEFT JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN users u ON s.sales_person = u.id
                WHERE s.invoice_no = ?
            ");
            $stmt->execute([$invoice_no]);
            $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sale_data) {
                // Additional validation
                $allowed_statuses = ['completed', 'partially_refunded'];
                if (!in_array($sale_data['sale_status'], $allowed_statuses)) {
                    $error = "This sale is not completed. Only completed sales can be refunded.";
                    $sale_data = null;
                } else {
                    $refund_details = $refundCalculator->getRefundDetails($sale_data['sale_id']);
                    if ($refund_details) {
                        $max_refundable = $refund_details['max_refundable'];
                    }
                }
            } else {
                $error = "No sale found with invoice number: " . htmlspecialchars($invoice_no);
            }
        } catch (PDOException $e) {
            $error = "Error searching for sale: " . $e->getMessage();
        }
    }
}

// Handle refund creation - WITH ENHANCED VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_refund'])) {
    try {
//        $db->beginTransaction();

       // ========== INPUT VALIDATION & SANITIZATION ==========
        $errors = [];

        // Required fields validation
        $required_fields = [
            'sale_id' => 'Sale ID',
            'refund_type' => 'Refund Type',
            'gross_refund_amount' => 'Refund Amount',
            'refund_reason' => 'Refund Reason',
            'payment_method' => 'Payment Method',
            'refund_date' => 'Refund Date',
            'reference_number' => 'Reference Number'
        ];

        foreach ($required_fields as $field => $name) {
            if (empty($_POST[$field])) {
                $errors[] = "$name is required";
            }
        }

        // Sale ID validation
        $sale_id = (int)$_POST['sale_id'];
        if ($sale_id <= 0) {
            $errors[] = "Invalid sale ID";
        }

        // Refund type validation
        $valid_refund_types = ['full_return', 'partial_keep', 'repair_compensation'];
        $refund_type = $_POST['refund_type'];
        if (!in_array($refund_type, $valid_refund_types)) {
            $errors[] = "Invalid refund type selected";
        }

        // Amount validation
        $gross_refund_amount = (float)$_POST['gross_refund_amount'];
        if ($gross_refund_amount <= 0) {
            $errors[] = "Refund amount must be greater than 0";
        }

        // Date validation
        $refund_date = $_POST['refund_date'];
        if (strtotime($refund_date) > time()) {
            $errors[] = "Refund date cannot be in the future";
        }

        // Checkbox validations
        $vehicle_returned = isset($_POST['vehicle_returned']) ? 1 : 0;
        $px_vehicle_returned = isset($_POST['px_vehicle_returned']) ? 1 : 0;

        // Business logic validation
        if ($refund_type === 'full_return' && !$vehicle_returned) {
            $errors[] = "Vehicle must be returned for full refund";
        }

        if ($refund_type === 'repair_compensation' && $vehicle_returned) {
            $errors[] = "Vehicle cannot be returned for repair compensation";
        }

        if ($refund_type === 'repair_compensation' && $px_vehicle_returned) {
            $errors[] = "Part-exchange vehicle cannot be returned for repair compensation";
        }

        // ========== DEDUCTION VALIDATION ==========
        $deductions = [];
        $total_deductions = 0;

        if (isset($_POST['deductions']) && is_array($_POST['deductions'])) {
            foreach ($_POST['deductions'] as $index => $deduction) {
                if (!empty($deduction['type']) && !empty($deduction['amount'])) {
                    $deduction_type = trim($deduction['type']);
                    $deduction_amount = (float)$deduction['amount'];
                    $deduction_description = trim($deduction['description'] ?? '');

                    if ($deduction_amount <= 0) {
                        $errors[] = "Deduction amount must be greater than 0";
                        continue;
                    }

                    $deductions[] = [
                        'type' => htmlspecialchars($deduction_type),
                        'description' => htmlspecialchars($deduction_description),
                        'amount' => $deduction_amount
                    ];
                    $total_deductions += $deduction_amount;
                }
            }
        }

        // ========== CHECK MAX REFUNDABLE AMOUNT ==========
    //    $px_allowance = $refund_details['sale_data']['part_exchange_allowance'] ?? 0;
        $px_allowance = 0;

        $refund_details = $refundCalculator->getRefundDetails($sale_id);
        if (!$refund_details) {
            $errors[] = "Could not retrieve refund details for this sale";
        } else {
            $max_refundable = $refund_details['max_refundable'];
            $sales_type = $refund_details['sales_type'];

            if ($gross_refund_amount > $max_refundable) {
                $errors[] = "Refund amount cannot exceed maximum refundable amount of £" .
                    number_format($adjusted_max_refundable, 2);
            }
        }

        // ========== STOP IF VALIDATION ERRORS ==========
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

       

        // ========== CALCULATE NET REFUND ==========
        $net_refund_amount = $gross_refund_amount - $total_deductions;
$px_allowance = $refund_details['sale_data']['part_exchange_allowance'] ?? 0;

        // Adjust for P/X vehicle return
        if ($px_vehicle_returned && $px_allowance > 0) {
            $net_refund_amount = max(0, $net_refund_amount - $px_allowance);
        }
        // echo($px_allowance."---");
        // echo($net_refund_amount);
//        die;

        if ($net_refund_amount < 0) {
            throw new Exception("Net refund amount cannot be negative after deductions");
        }

        // ========== SANITIZE TEXT INPUTS ==========
        $refund_reason = htmlspecialchars(trim($_POST['refund_reason']));
        $customer_agreement = htmlspecialchars(trim($_POST['customer_agreement'] ?? ''));
        $reference_number = htmlspecialchars(trim($_POST['reference_number']));

        // ========== CONTINUE WITH EXISTING PROCESSING LOGIC ==========
        // Determine vehicle status after refund
        $vehicle_status_after = 'sold'; // Default - customer keeps vehicle
        if ($vehicle_returned) {
            if ($refund_type === 'full_return' || $refund_type === 'partial_return') {
                $vehicle_status_after = 'available';
            }
        }

        // Insert refund record - UPDATED with px_vehicle_returned
        $stmt = $db->prepare("
    INSERT INTO refunds (
        sale_id, original_invoice_no, refund_type, refund_amount, 
        total_deductions, net_refund_amount, vehicle_returned, 
        px_vehicle_returned, vehicle_status_after, refund_reason, 
        customer_agreement, status, created_by
    ) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?)
");

        $stmt->execute([
            $sale_id,
            $_POST['original_invoice_no'],
            $refund_type,
            $gross_refund_amount,
            $total_deductions,
            $net_refund_amount,
            $vehicle_returned,
            $px_vehicle_returned, // NEW
            $vehicle_status_after,
            $refund_reason,
            $customer_agreement,
            $_SESSION['user_id']
        ]);

        $refund_id = $db->lastInsertId();

        // Insert deductions
        foreach ($_POST['deductions'] as $index => $deduction) {
            $stmt = $db->prepare("
        INSERT INTO refund_deductions (refund_id, deduction_type, description, amount)
        VALUES (?, ?, ?, ?)
    ");
            $stmt->execute([
                $refund_id,
                $deduction['type'],
                $deduction['description'],
                $deduction['amount']
            ]);
        }

        // Record refund transaction in sale_payments (with NEGATIVE amount)
        $refund_transaction = [
            'sale_id' => $sale_id,
            'invoice_no' => $_POST['original_invoice_no'] . '-REF',
            'transaction_date' => $refund_date,
            'amount' => -$net_refund_amount, // Negative amount for refund
            'payment_method' => $_POST['payment_method'],
            'reference_number' => $reference_number,
            'notes' => "Refund: $refund_reason",
            'processed_by' => $_SESSION['user_id']
        ];

        $transaction_id = $refundCalculator->recordRefundTransaction($refund_transaction);

        if (!$transaction_id) {
            throw new Exception("Failed to record refund transaction.");
        }

        // === UPDATE SALES STATUS ===
        $sale_status = 'completed'; // default

        if ($refund_type === 'full_return') {
            $sale_status = 'refunded';
        } 

        $stmt = $db->prepare("UPDATE sales SET status = ?, updated_at = NOW() WHERE sale_id = ?");
        $stmt->execute([$sale_status, $sale_id]);

        // === UPDATE VEHICLE STATUS (if returned) ===
        if ($vehicle_returned && $refund_type === 'full_return') {
            $vehicle_status_after = 'available';

            $stmt = $db->prepare("UPDATE vehicles SET status = ?, updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_status_after, $_POST['vehicle_id']]);

            // Add refund note to vehicle
            $refund_note = " - Returned via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$refund_note, $_POST['vehicle_id']]);
        }

        // === HANDLE PART-EXCHANGE VEHICLE RETURN ===
        if ($px_vehicle_returned && !empty($refund_details['sale_data']['px_vehicle_id'])) {
            // Update P/X vehicle status to 'refunded_to_customer'
            $stmt = $db->prepare("UPDATE vehicles SET status = 'refunded_to_customer', updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$refund_details['sale_data']['px_vehicle_id']]);

            // Add note to P/X vehicle
            $px_refund_note = " - Returned to customer via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$px_refund_note, $refund_details['sale_data']['px_vehicle_id']]);
        }

        // === HANDLE FINANCE COMPANY NOTIFICATION ===
        if ($refund_details['sale_data']['payment_method'] == 'finance' && $refund_type === 'full_return') {
            $finance_note = " [FINANCE: Contact " . $refund_details['sale_data']['finance_company_name'] . " to process £" . number_format($refund_details['sale_data']['finance_amount'], 2) . " refund]";
            $stmt = $db->prepare("UPDATE refunds SET customer_agreement = CONCAT(COALESCE(customer_agreement, ''), ?) WHERE refund_id = ?");
            $stmt->execute([$finance_note, $refund_id]);
        }

        
        // === ADD REFUND NOTE TO ORIGINAL SALE ===
        $sale_note = " | Refund issued: £" . number_format($net_refund_amount, 2) . " via " . strtoupper($refund_type) . " on " . date('Y-m-d');
        $stmt = $db->prepare("UPDATE sales SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE sale_id = ?");
        $stmt->execute([$sale_note, $sale_id]);


        //$db->commit();

        $_SESSION['success'] = "Refund created successfully! Refund ID: RF-" . str_pad($refund_id, 5, '0', STR_PAD_LEFT);
        header("Location: refund-details.php?id=$refund_id");
        exit;
    } catch (Exception $e) {
        //$db->rollBack();
        $error = "Error creating refund: " . $e->getMessage();
    }
}

// Handle refund creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_refund_old'])) {
    try {
        $db->beginTransaction();

        // Get form data
        $sale_id = (int)$_POST['sale_id'];
        $refund_type = $_POST['refund_type'];
        $gross_refund_amount = (float)$_POST['gross_refund_amount'];
        $vehicle_returned = isset($_POST['vehicle_returned']) ? 1 : 0;
        $px_vehicle_returned = isset($_POST['px_vehicle_returned']) ? 1 : 0; // NEW: P/X vehicle return
        $refund_reason = trim($_POST['refund_reason']);
        $customer_agreement = trim($_POST['customer_agreement'] ?? '');
        $payment_method = $_POST['payment_method'];
        $refund_date = $_POST['refund_date'];
        $reference_number = trim($_POST['reference_number']);

        // Get refund details for sales type handling
        $refund_details = $refundCalculator->getRefundDetails($sale_id);
        $sales_type = $refund_details['sales_type'];

        // Process deductions
        $deductions = [];
        $total_deductions = 0;

        if (isset($_POST['deductions']) && is_array($_POST['deductions'])) {
            foreach ($_POST['deductions'] as $deduction) {
                if (!empty($deduction['type']) && !empty($deduction['amount']) && $deduction['amount'] > 0) {
                    $deduction_amount = (float)$deduction['amount'];
                    $deductions[] = [
                        'type' => $deduction['type'],
                        'description' => $deduction['description'] ?? '',
                        'amount' => $deduction_amount
                    ];
                    $total_deductions += $deduction_amount;
                }
            }
        }

        // Calculate net refund amount
        $net_refund_amount = $gross_refund_amount - $total_deductions;

        if ($net_refund_amount < 0) {
            throw new Exception("Net refund amount cannot be negative. Deductions exceed refund amount.");
        }

        // Validate refund amount doesn't exceed maximum
        $remaining_refundable = $refund_details['max_refundable'];
        if ($gross_refund_amount > $remaining_refundable) {
            throw new Exception("Gross refund amount cannot exceed remaining refundable amount of £" . number_format($remaining_refundable, 2));
        }

        // Determine vehicle status after refund
        $vehicle_status_after = 'sold'; // Default - customer keeps vehicle
        if ($vehicle_returned) {
            if ($refund_type === 'full_return' || $refund_type === 'partial_return') {
                $vehicle_status_after = 'available';
            }
        }

        // Insert refund record - UPDATED with px_vehicle_returned
        $stmt = $db->prepare("
            INSERT INTO refunds (
                sale_id, original_invoice_no, refund_type, refund_amount, 
                total_deductions, net_refund_amount, vehicle_returned, 
                px_vehicle_returned, vehicle_status_after, refund_reason, 
                customer_agreement, status, created_by
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        $stmt->execute([
            $sale_id,
            $_POST['original_invoice_no'],
            $refund_type,
            $gross_refund_amount,
            $total_deductions,
            $net_refund_amount,
            $vehicle_returned,
            $px_vehicle_returned, // NEW
            $vehicle_status_after,
            $refund_reason,
            $customer_agreement,
            $_SESSION['user_id']
        ]);

        $refund_id = $db->lastInsertId();

        // Insert deductions
        foreach ($deductions as $deduction) {
            $stmt = $db->prepare("
                INSERT INTO refund_deductions (refund_id, deduction_type, description, amount)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $refund_id,
                $deduction['type'],
                $deduction['description'],
                $deduction['amount']
            ]);
        }

        // Record refund transaction in sale_payments (with NEGATIVE amount)
        $refund_transaction = [
            'sale_id' => $sale_id,
            'invoice_no' => $_POST['original_invoice_no'] . '-REF',
            'transaction_date' => $refund_date,
            'amount' => -$net_refund_amount, // Negative amount for refund
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'notes' => "Refund: $refund_reason",
            'processed_by' => $_SESSION['user_id']
        ];

        $transaction_id = $refundCalculator->recordRefundTransaction($refund_transaction);

        if (!$transaction_id) {
            throw new Exception("Failed to record refund transaction.");
        }

        // === UPDATE SALES STATUS ===
        $sale_status = 'completed'; // default

        if ($refund_type === 'full_return') {
            $sale_status = 'refunded';
        } elseif (in_array($refund_type, ['partial_return', 'partial_keep', 'repair_compensation'])) {
            $sale_status = 'partially_refunded';
        }

        $stmt = $db->prepare("UPDATE sales SET status = ?, updated_at = NOW() WHERE sale_id = ?");
        $stmt->execute([$sale_status, $sale_id]);

        // === UPDATE VEHICLE STATUS (if returned) ===
        if ($vehicle_returned && in_array($refund_type, ['full_return', 'partial_return'])) {
            $vehicle_status_after = 'available';

            $stmt = $db->prepare("UPDATE vehicles SET status = ?, updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_status_after, $_POST['vehicle_id']]);

            // Add refund note to vehicle
            $refund_note = " - Returned via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$refund_note, $_POST['vehicle_id']]);
        }

        // === HANDLE PART-EXCHANGE VEHICLE RETURN ===
        if ($px_vehicle_returned && !empty($refund_details['sale_data']['px_vehicle_id'])) {
            // Update P/X vehicle status to 'refunded_to_customer'
            $stmt = $db->prepare("UPDATE vehicles SET status = 'refunded_to_customer', updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$refund_details['sale_data']['px_vehicle_id']]);

            // Add note to P/X vehicle
            $px_refund_note = " - Returned to customer via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$px_refund_note, $refund_details['sale_data']['px_vehicle_id']]);
        }

        // === HANDLE FINANCE COMPANY NOTIFICATION ===
        if ($sale_data['payment_method'] == 'finance' && $refund_type === 'full_return') {
            $finance_note = " [FINANCE: Contact " . $sale_data['finance_company_name'] . " to process £" . number_format($sale_data['finance_amount'], 2) . " refund]";
            $stmt = $db->prepare("UPDATE refunds SET customer_agreement = CONCAT(COALESCE(customer_agreement, ''), ?) WHERE refund_id = ?");
            $stmt->execute([$finance_note, $refund_id]);
        }

        // === UPDATE BALANCE DUE IN SALES TABLE ===
        $stmt = $db->prepare("
            UPDATE sales 
            SET balance_due = balance_due - ? 
            WHERE sale_id = ?
        ");
        $stmt->execute([$net_refund_amount, $sale_id]);

        $db->commit();

        $_SESSION['success'] = "Refund created successfully! Refund ID: RF-" . str_pad($refund_id, 5, '0', STR_PAD_LEFT);
        header("Location: refund-details.php?id=$refund_id");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error creating refund: " . $e->getMessage();
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
    <style>
        .validation-error {
            margin-bottom: 1rem;
        }

        .deduction-row:has(.is-invalid) {
            border: 1px solid #dc3545;
            border-radius: 0.375rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
    </style>
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
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12 col-xxl-12">
                        <!-- Page Header -->
                        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <h1 class="h2">Create New Refund</h1>
                            <div class="btn-toolbar mb-2 mb-md-0">
                                <a href="refund-management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Refunds
                                </a>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Validation Error:</strong><br>
                                <?= nl2br(htmlspecialchars($error)) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Step 1: Sale Lookup -->
                        <?php if (!$sale_data): ?>
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0 text-white">Step 1: Find Original Sale</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="lookup_sale" value="1">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label class="form-label">Enter Invoice Number *</label>
                                                <input type="text" class="form-control" name="invoice_no"
                                                    placeholder="e.g., INV-2025-001"
                                                    value="<?= htmlspecialchars($_POST['invoice_no'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-search"></i> Find Sale
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">
                                            Enter the original invoice number from the sale. This ensures we find the exact transaction.
                                        </small>
                                    </form>

                                    <!-- Help section for finding invoice numbers -->
                                    <div class="mt-4 p-3 bg-light rounded">
                                        <h6><i class="fas fa-info-circle text-info"></i> Need help finding invoice numbers?</h6>
                                        <ul class="mb-0">
                                            <li>Check the original sales invoice document</li>
                                            <li>Look in <a href="view-all-invoices.php">View All Invoices</a></li>
                                            <li>Check sales records in the system</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>

                            <!-- Sales Type Alert -->
                            <?php if ($refund_details):
                                $sales_type = $refund_details['sales_type'];

                                switch ($sales_type) {
                                    case 'normal':
                                        $alert_type = 'alert-info';
                                        $icon = 'fas fa-cash';
                                        $title = 'Normal Sale';
                                        $message = 'Full refund available up to customer payments.';
                                        break;

                                    case 'partexchange_only':
                                        $alert_type = 'alert-warning';
                                        $icon = 'fas fa-exchange-alt';
                                        $title = 'Sale with Part-Exchange';
                                        $message = 'Refund includes cash amount. Part-exchange vehicle can be returned to customer.';
                                        break;

                                    case 'finance_only':
                                        $alert_type = 'alert-warning';
                                        $icon = 'fas fa-university';
                                        $title = 'Sale with Finance';
                                        $message = 'Only customer deposit is refundable. Finance amount must be handled separately with the finance company.';
                                        break;

                                    case 'finance_partexchange':
                                        $alert_type = 'alert-danger';
                                        $icon = 'fas fa-exclamation-triangle';
                                        $title = 'Complex Sale - Finance + Part-Exchange';
                                        $message = 'Multiple components: refund deposit only, finance handled separately, part-exchange vehicle can be returned.';
                                        break;
                                }
                            ?>
                                <!-- <div class="alert <?= $alert_type ?> alert-dismissible fade show">
                                    <i class="<?= $icon ?> me-2"></i>
                                    <strong><?= $title ?>:</strong> <?= $message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div> -->
                            <?php endif; ?>

                            <!-- Step 2: Refund Creation Form -->
                            <form method="POST" id="refundForm">
                                <input type="hidden" name="create_refund" value="1">
                                <input type="hidden" name="sale_id" value="<?= $sale_data['sale_id'] ?>">
                                <input type="hidden" name="original_invoice_no" value="<?= $sale_data['invoice_no'] ?>">
                                <input type="hidden" name="vehicle_id" value="<?= $sale_data['vehicle_id'] ?>">

                                <!-- Sale Information -->
                                <div class="card sale-info-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Sale Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Invoice:</strong> <?= htmlspecialchars($sale_data['invoice_no']) ?><br>
                                                <strong>Sale Date:</strong> <?= date('F j, Y', strtotime($sale_data['sale_date'])) ?><br>
                                                <strong>Salesperson:</strong> <?= htmlspecialchars($sale_data['salesperson_name']) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Vehicle:</strong> <?= htmlspecialchars($sale_data['make'] . ' ' . $sale_data['model']) ?><br>
                                                <strong>Registration:</strong> <?= htmlspecialchars($sale_data['regnumber']) ?><br>
                                                <strong>Year:</strong> <?= htmlspecialchars($sale_data['year']) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Customer:</strong> <?= htmlspecialchars($sale_data['first_name'] . ' ' . $sale_data['last_name']) ?><br>
                                                <strong>Phone:</strong> <?= htmlspecialchars($sale_data['phone']) ?><br>
                                                <strong>Sale Price:</strong> £<?= number_format($sale_data['sale_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-8">
                                        <!-- Refund Details -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0">Refund Details</h5>
                                            </div>
                                            <div class="card-body">

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Refund Type *</label>
                                                        <select class="form-select" name="refund_type" id="refundType" required>
                                                            <option value="">Select Refund Type</option>
                                                            <option value="full_return">Full Return (Vehicle Returned)</option>
                                                            <!-- <option value="partial_return">Partial Return (Vehicle Returned with Deductions)</option> -->
                                                            <option value="partial_keep">Partial Refund (Customer Keeps Vehicle)</option>
                                                            <option value="repair_compensation">Repair Compensation</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Refund Amount *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">£</span>
                                                            <input type="number" step="0.01" class="form-control"
                                                                name="gross_refund_amount" id="grossRefundAmount"
                                                                min="0.01" max="<?= $max_refundable ?>"
                                                                value="<?= min($max_refundable, $sale_data['deposit_received']) ?>" required>
                                                        </div>
                                                        <small class="form-text text-muted">Maximum: £<?= number_format($max_refundable, 2) ?></small>
                                                    </div>
                                                </div>

                                                <!-- Main Vehicle Return -->
                                                <div id="vehicleReturnSection">
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="vehicle_returned" id="vehicleReturned" value="1">
                                                                <label class="form-check-label" for="vehicleReturned">
                                                                    Main Vehicle is being returned to inventory
                                                                </label>
                                                            </div>
                                                            <small class="form-text text-muted">
                                                                This will make the vehicle available for sale again.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Part Exchange Vehicle Return Section -->

                                                <?php if (!empty($sale_data['px_vehicle_id'])): ?>
                                                    <div id="pxVehicleSection">

                                                        <div class="row mb-3">
                                                            <div class="col-12">
                                                                <div class="card border-warning">
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-md-8">
                                                                                <strong><i class="fas fa-exchange-alt"></i> Part Exchanged Vehicle:</strong>
                                                                                <?= htmlspecialchars($sale_data['px_make'] . ' ' . $sale_data['px_model']) ?>
                                                                                (<?= htmlspecialchars($sale_data['px_year']) ?>)
                                                                                <?= htmlspecialchars($sale_data['px_regnumber']) ?>
                                                                            </div>
                                                                            <div class="col-md-12 mt-2">
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="checkbox" name="px_vehicle_returned" id="pxVehicleReturned" value="1">
                                                                                    <label class="form-check-label" for="pxVehicleReturned">
                                                                                        <strong>Return P/X Vehicle to Customer</strong>
                                                                                    </label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <small class="text-muted">
                                                                            This will return the part-exchange vehicle to the customer (status: refunded_to_customer).
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Finance Company Notice -->
                                                <?php if ($sale_data['payment_method'] == 'finance'): ?>
                                                    <div id="financeSection">
                                                        <div class="row mb-3">
                                                            <div class="col-12">
                                                                <div class="card border-info">
                                                                    <div class="card-body">
                                                                        <h6 class="text-info"><i class="fas fa-university"></i> Finance Information</h6>
                                                                        <p class="mb-1"><strong>Finance Company:</strong> <?= htmlspecialchars($sale_data['finance_company_name'] ?? 'N/A') ?></p>
                                                                        <p class="mb-1"><strong>Finance Amount:</strong> £<?= number_format($sale_data['finance_amount'], 2) ?></p>
                                                                        <small class="text-muted">
                                                                            Finance amount will be returned to the finance company separately.
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label class="form-label">Refund Reason *</label>
                                                    <textarea class="form-control" name="refund_reason" rows="3"
                                                        placeholder="Detailed reason for the refund..." required></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Customer Agreement / Notes</label>
                                                    <textarea class="form-control" name="customer_agreement" rows="2"
                                                        placeholder="Any agreement details or special notes..."></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deductions Section -->
                                        <div class="card mb-4">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Deductions</h5>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addDeduction">
                                                    <i class="fas fa-plus"></i> Add Deduction
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div id="deductionsContainer">
                                                    <!-- Deductions will be added here dynamically -->
                                                </div>
                                                <div class="text-end mt-3">
                                                    <strong>Total Deductions: £<span id="totalDeductions">0.00</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <!-- Calculation Summary -->
                                        <!-- Sale Summary Card -->
                                        <div class="card sale-info-card mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0 text-white">Sale Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Original Sale Breakdown -->
                                                <h6 class="text-primary mb-3">Original Sale Details</h6>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Vehicle Sale Price:</span>
                                                    <strong>£<?= number_format($sale_data['sale_price'], 2) ?></strong>
                                                </div>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Service Fee:</span>
                                                    <strong>£<?= number_format($sale_data['service_fee'], 2) ?></strong>
                                                </div>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Delivery Charges:</span>
                                                    <strong>£<?= number_format($sale_data['delivery_charges'], 2) ?></strong>
                                                </div>
                                                <div class="mb-3 d-flex justify-content-between border-bottom pb-2">
                                                    <span><strong>Subtotal:</strong></span>
                                                    <strong>£<?= number_format($sale_data['sale_price'] + $sale_data['service_fee'] + $sale_data['delivery_charges'], 2) ?></strong>
                                                </div>

                                                <!-- Discounts & Payments -->
                                                <h6 class="text-primary mb-3 mt-3">Payments & Allowances</h6>
                                                <?php if (($sale_data['discount_amount'] ?? 0) > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-success">
                                                        <span>Discount Given:</span>
                                                        <strong>-£<?= number_format($sale_data['discount_amount'] ?? 0, 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($sale_data['finance_amount'] > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-info">
                                                        <span>Finance Amount:</span>
                                                        <strong>£<?= number_format($sale_data['finance_amount'], 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($sale_data['part_exchange_allowance'] > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-warning">
                                                        <span>Part-Exchange Allowance:</span>
                                                        <strong>£<?= number_format($sale_data['part_exchange_allowance'], 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-2 d-flex justify-content-between text-success">
                                                    <span>Customer Deposit/Paid:</span>
                                                    <strong>£<?= number_format($sale_data['deposit_received'], 2) ?></strong>
                                                </div>

                                                <div class="mb-0 d-flex justify-content-between border-top pt-2">
                                                    <span><strong>Total Amount Paid:</strong></span>
                                                    <strong>£<?= number_format(
                                                                    $sale_data['deposit_received'] +
                                                                        $sale_data['finance_amount'] +
                                                                        $sale_data['part_exchange_allowance'],
                                                                    2
                                                                ) ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Refund Eligibility Card -->
                                        <div class="card calculation-card mb-4">
                                            <div class="card-header bg-success text-white">
                                                <h5 class="mb-0 text-white">Refund Calculation</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Available Balance -->
                                                <div class="mb-3 p-3 bg-light rounded">
                                                    <div class="text-center">
                                                        <small class="text-muted">Available for Refund</small>
                                                        <h3 class="text-success mb-0">£<?= number_format($refund_details['max_refundable'], 2) ?></h3>
                                                        <small class="text-muted">
                                                            Customer Deposit: £<?= number_format($sale_data['deposit_received'], 2) ?>
                                                            <?php if ($refund_details['already_refunded'] > 0): ?>
                                                                <br>Already Refunded: £<?= number_format($refund_details['already_refunded'], 2) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>

                                                <!-- Calculation Lines Container -->
                                                <div id="calculationLines">
                                                    <!-- Current Refund -->
                                                    <div class="mb-2 d-flex justify-content-between">
                                                        <span>Refund Amount:</span>
                                                        <strong>£<span id="summaryRefundAmount"><?= number_format(min($max_refundable, $sale_data['deposit_received']), 2) ?></span></strong>
                                                    </div>

                                                    <!-- P/X Allowance line will be dynamically inserted here by JavaScript -->

                                                    <div class="mb-2 d-flex justify-content-between text-danger">
                                                        <span>Deductions:</span>
                                                        <strong>-£<span id="summaryDeductions">0.00</span></strong>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between fs-5 fw-bold">
                                                    <span>Net Refund to Customer:</span>
                                                    <strong class="text-success">£<span id="summaryNet"><?= number_format(min($max_refundable, $sale_data['deposit_received']), 2) ?></span></strong>
                                                </div>

                                                <!-- Simple Notes -->
                                                <?php if ($sale_data['finance_amount'] > 0): ?>
                                                    <div class="alert alert-info mt-3 p-2">
                                                        <small>
                                                            <i class="fas fa-info-circle"></i>
                                                            Finance amount (£<?= number_format($sale_data['finance_amount'], 2) ?>) handled separately
                                                        </small>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($sale_data['part_exchange_allowance'] > 0): ?>
                                                    <div class="alert alert-warning mt-2 p-2">
                                                        <small>
                                                            <i class="fas fa-exchange-alt"></i>
                                                            Part-exchange vehicle can be returned to customer
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Payment Details -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0">Payment Details</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Payment Method *</label>
                                                    <select class="form-select" name="payment_method" required>
                                                        <option value="">Select Method</option>
                                                        <option value="cash">Cash</option>
                                                        <option value="bank_transfer">Bank Transfer</option>
                                                        <option value="card">Card Refund</option>
                                                        <option value="cheque">Cheque</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Refund Date *</label>
                                                    <input type="date" class="form-control" name="refund_date"
                                                        value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Reference Number *</label>
                                                    <input type="text" class="form-control" name="reference_number"
                                                        placeholder="e.g., REF-001, Check #1234" required>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="fas fa-check-circle"></i> Create Refund
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <!-- Chat Now -->

    </main>


    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    <script>
        // Calculate totals - FIXED VERSION
        function calculateTotals() {
            let totalDeductions = 0;
            const refundAmount = parseFloat(document.getElementById('grossRefundAmount').value) || 0;
            const maxRefundable = <?= $max_refundable ?>;
            const pxAllowance = <?= $sale_data['part_exchange_allowance'] ?? 0 ?>;
            const pxVehicleReturned = document.getElementById('pxVehicleReturned')?.checked || false;

            console.log('Original Refund Amount:', refundAmount);
            console.log('P/X Allowance:', pxAllowance);
            console.log('P/X Vehicle Returned:', pxVehicleReturned);

            // Calculate total deductions from all deduction inputs
            document.querySelectorAll('.deduction-amount').forEach(input => {
                const deductionValue = parseFloat(input.value) || 0;
                totalDeductions += deductionValue;
            });

            // Calculate net refund amount
            let netRefund = refundAmount;

            // SUBTRACT P/X allowance from refund amount when P/X vehicle is returned
            if (pxVehicleReturned && pxAllowance > 0) {
                netRefund = Math.max(0, refundAmount - pxAllowance);
                console.log('P/X vehicle returned - subtracting allowance:', pxAllowance);
            }

            // SUBTRACT deductions
            netRefund = Math.max(0, netRefund - totalDeductions);

            console.log('Final Net Refund:', netRefund);

            // Update ALL displays - SHOW ORIGINAL REFUND AMOUNT in summary
            document.getElementById('totalDeductions').textContent = totalDeductions.toFixed(2);
            document.getElementById('summaryRefundAmount').textContent = refundAmount.toFixed(2); // Show original amount
            document.getElementById('summaryDeductions').textContent = totalDeductions.toFixed(2);
            document.getElementById('summaryNet').textContent = netRefund.toFixed(2); // Show final calculated amount

            // Highlight if net is negative or exceeds maximum
            const netElement = document.getElementById('summaryNet');

            if (netRefund < 0) {
                netElement.parentElement.classList.remove('text-success');
                netElement.parentElement.classList.add('text-danger');
            } else if (refundAmount > maxRefundable) {
                netElement.parentElement.classList.remove('text-success');
                netElement.parentElement.classList.add('text-warning');
            } else {
                netElement.parentElement.classList.remove('text-danger', 'text-warning');
                netElement.parentElement.classList.add('text-success');
            }

            // Show/hide P/X allowance line in calculation
            updatePxAllowanceDisplay(pxVehicleReturned, pxAllowance);
        }

        // Update P/X allowance display in calculation section - FIXED VERSION
        function updatePxAllowanceDisplay(pxVehicleReturned, pxAllowance) {
            let pxAllowanceLine = document.getElementById('pxAllowanceLine');
            const calculationLines = document.getElementById('calculationLines');
            const refundAmountLine = calculationLines.querySelector('.d-flex.justify-content-between:first-child');

            if (pxVehicleReturned && pxAllowance > 0) {
                if (!pxAllowanceLine) {
                    // Create P/X allowance line if it doesn't exist
                    pxAllowanceLine = document.createElement('div');
                    pxAllowanceLine.id = 'pxAllowanceLine';
                    pxAllowanceLine.className = 'mb-2 d-flex justify-content-between text-warning';
                    pxAllowanceLine.innerHTML = `
                <span>Part-Exchange Allowance Deduction:</span>
                <strong>-£${pxAllowance.toFixed(2)}</strong>
            `;
                    // Insert after the refund amount line
                    refundAmountLine.parentNode.insertBefore(pxAllowanceLine, refundAmountLine.nextSibling);
                } else {
                    // Update existing P/X allowance line
                    pxAllowanceLine.innerHTML = `
                <span>Part-Exchange Allowance Deduction:</span>
                <strong>-£${pxAllowance.toFixed(2)}</strong>
            `;
                }
            } else {
                // Remove P/X allowance line if not needed
                if (pxAllowanceLine) {
                    pxAllowanceLine.remove();
                }
            }
        }
        let deductionCount = 0;

        // Add new deduction row
        document.getElementById('addDeduction')?.addEventListener('click', function() {
            addDeductionRow();
        });

        function addDeductionRow(deduction = {}) {
            deductionCount++;
            const container = document.getElementById('deductionsContainer');

            const row = document.createElement('div');
            row.className = 'row deduction-row mb-3 p-2';
            row.innerHTML = `
    <div class="col-md-4">
        <label class="form-label">Deduction Type *</label>
        <input type="text" class="form-control deduction-type" 
               name="deductions[${deductionCount}][type]" 
               value="${deduction.type || ''}" 
               placeholder="e.g., Mileage Charge, Damage Fee, Admin Fee" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Amount *</label>
        <div class="input-group">
            <span class="input-group-text">£</span>
            <input type="number" step="0.01" class="form-control deduction-amount" 
                   name="deductions[${deductionCount}][amount]" 
                   value="${deduction.amount || ''}" min="0" required>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Description</label>
        <input type="text" class="form-control" name="deductions[${deductionCount}][description]" 
               value="${deduction.description || ''}" placeholder="Brief description">
    </div>
    <div class="col-md-1 d-flex align-items-end">
        <button type="button" class="btn btn-sm btn-outline-danger remove-deduction" style="margin-bottom: 8px;">
            <i class="fas fa-times"></i>
        </button>
    </div>
`;

            container.appendChild(row);

            // Add event listeners to NEW elements
            row.querySelector('.remove-deduction').addEventListener('click', function() {
                row.remove();
                calculateTotals();
            });

            row.querySelector('.deduction-amount').addEventListener('input', calculateTotals);

            calculateTotals();
        }

        // Update when refund amount changes
        document.getElementById('grossRefundAmount')?.addEventListener('input', calculateTotals);

        // Update when P/X vehicle returned checkbox changes
        document.getElementById('pxVehicleReturned')?.addEventListener('change', calculateTotals);

        // Handle refund type changes - show/hide sections accordingly
        document.getElementById('refundType')?.addEventListener('change', function() {
            const refundType = this.value;
            const vehicleReturnSection = document.getElementById('vehicleReturnSection');
            const pxVehicleSection = document.getElementById('pxVehicleSection');
            const financeSection = document.getElementById('financeSection');
            const vehicleCheckbox = document.getElementById('vehicleReturned');
            const pxVehicleCheckbox = document.getElementById('pxVehicleReturned');

            // Reset all sections to visible by default
            if (vehicleReturnSection) vehicleReturnSection.style.display = 'block';
            if (pxVehicleSection) pxVehicleSection.style.display = 'block';
            if (financeSection) financeSection.style.display = 'block';

            // Handle different refund types
            if (refundType === 'full_return') {
                // Full Return - show all sections, auto-check vehicle return
                if (vehicleCheckbox) {
                    vehicleCheckbox.checked = true;
                    vehicleCheckbox.disabled = true;

                }
                // Auto-check P/X for full returns if P/X exists
                if (pxVehicleCheckbox) {
                    pxVehicleCheckbox.checked = true;
                    pxVehicleCheckbox.disabled = true;
                }
            } else if (refundType === 'partial_keep') {
                // Partial Refund (Customer Keeps Vehicle) - hide vehicle return sections
                if (vehicleReturnSection) vehicleReturnSection.style.display = 'none';
                if (vehicleCheckbox) {
                    vehicleCheckbox.checked = false;
                    vehicleCheckbox.disabled = false;
                }
                // Keep P/X section visible but uncheck it
                if (pxVehicleCheckbox) {
                    pxVehicleCheckbox.checked = false;
                    pxVehicleCheckbox.disabled = false;
                }
            } else if (refundType === 'repair_compensation') {
                // Repair Compensation - hide all vehicle return sections and finance
                if (vehicleReturnSection) vehicleReturnSection.style.display = 'none';
                if (pxVehicleSection) pxVehicleSection.style.display = 'none';
                if (financeSection) financeSection.style.display = 'none';

                if (vehicleCheckbox) {
                    vehicleCheckbox.checked = false;
                    vehicleCheckbox.disabled = false;
                }
                if (pxVehicleCheckbox) {
                    pxVehicleCheckbox.checked = false;
                }
            } else {
                // For any other type (or no selection), reset to default
                if (vehicleCheckbox) {
                    vehicleCheckbox.checked = false;
                    vehicleCheckbox.disabled = false;
                }
                if (pxVehicleCheckbox) {
                    pxVehicleCheckbox.checked = false;
                }
            }

            // Recalculate totals after refund type change
            calculateTotals();
        });

        // Form validation before submission
        document.getElementById('refundForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            let isValid = true;
            const errorMessages = [];

            // Get form values
            const refundType = document.getElementById('refundType').value;
            const grossRefundAmount = parseFloat(document.getElementById('grossRefundAmount').value) || 0;
            const refundReason = document.querySelector('textarea[name="refund_reason"]').value.trim();
            const paymentMethod = document.querySelector('select[name="payment_method"]').value;
            const refundDate = document.querySelector('input[name="refund_date"]').value;
            const referenceNumber = document.querySelector('input[name="reference_number"]').value.trim();
            const vehicleReturned = document.getElementById('vehicleReturned')?.checked || false;
            const pxVehicleReturned = document.getElementById('pxVehicleReturned')?.checked || false;
            const netRefund = parseFloat(document.getElementById('summaryNet').textContent) || 0;
            const maxRefundable = <?= $max_refundable ?>;
            const pxAllowance = <?= $sale_data['part_exchange_allowance'] ?? 0 ?>;

            // 1. Required Fields Validation
            if (!refundType) {
                isValid = false;
                errorMessages.push('Please select a refund type');
            }

            if (!grossRefundAmount || grossRefundAmount <= 0) {
                isValid = false;
                errorMessages.push('Please enter a valid refund amount greater than 0');
            }

            if (!refundReason) {
                isValid = false;
                errorMessages.push('Please provide a reason for the refund');
            }

            if (!paymentMethod) {
                isValid = false;
                errorMessages.push('Please select a payment method');
            }

            if (!refundDate) {
                isValid = false;
                errorMessages.push('Please select a refund date');
            }

            if (!referenceNumber) {
                isValid = false;
                errorMessages.push('Please enter a reference number');
            }

            // 2. Amount Validation
            if (grossRefundAmount > maxRefundable) {
                isValid = false;
                errorMessages.push(`Refund amount cannot exceed maximum refundable amount of £${maxRefundable.toFixed(2)}`);
            }

            if (netRefund < 0) {
                isValid = false;
                errorMessages.push('Net refund amount cannot be negative. Please adjust the refund amount or deductions.');
            }

            // 3. Business Logic Validation
            if (refundType === 'full_return') {
                if (!vehicleReturned) {
                    isValid = false;
                    errorMessages.push('For Full Return, the main vehicle must be returned to inventory');
                }
            }

            if (refundType === 'repair_compensation') {
                if (vehicleReturned) {
                    isValid = false;
                    errorMessages.push('For Repair Compensation, vehicles cannot be returned');
                }
                if (pxVehicleReturned) {
                    isValid = false;
                    errorMessages.push('For Repair Compensation, part-exchange vehicles cannot be returned');
                }
            }

            // 4. Deduction Validation
            let deductionErrors = false;
            document.querySelectorAll('.deduction-row').forEach(row => {
                const deductionType = row.querySelector('.deduction-type').value.trim();
                const deductionAmount = parseFloat(row.querySelector('.deduction-amount').value) || 0;

                if (deductionType && deductionAmount <= 0) {
                    deductionErrors = true;
                }
                if (!deductionType && deductionAmount > 0) {
                    deductionErrors = true;
                }
            });

            if (deductionErrors) {
                isValid = false;
                errorMessages.push('Please ensure all deductions have both a type and a valid amount greater than 0');
            }

            // 5. Date Validation
            const today = new Date().toISOString().split('T')[0];
            if (refundDate > today) {
                isValid = false;
                errorMessages.push('Refund date cannot be in the future');
            }

            // Show errors or submit form
            if (!isValid) {
                // Display all error messages
                const errorHtml = errorMessages.map(msg =>
                    `<div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`
                ).join('');

                // Remove existing error alerts
                document.querySelectorAll('.validation-error').forEach(el => el.remove());

                // Add new error alerts at the top of the form
                const formHeader = this.querySelector('.card-header');
                const errorContainer = document.createElement('div');
                errorContainer.className = 'validation-error';
                errorContainer.innerHTML = errorHtml;
                this.insertBefore(errorContainer, formHeader.nextSibling);

                // Scroll to first error
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                return false;
            }

            // If validation passes, show loading state and submit
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Refund...';

            // Submit the form
            this.submit();
        });

        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotals();

            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
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
</body>

</html>