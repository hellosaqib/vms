<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Cancel Sale";

$invoice_no = $_GET['inv'] ?? '';

if ($invoice_no == '') {

    setError("Invoice Number is required.");
    header('Location: view-all-invoices.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Pending Sale
|--------------------------------------------------------------------------
*/
try {

    $stmt = $db->prepare("
        SELECT
            s.*,
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            v.year,
            v.mileage,
            v.status AS vehicle_status,

            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,

            u.full_name AS salesperson_name

        FROM sales s

        LEFT JOIN vehicles v
            ON s.vehicle_id = v.vehicle_id

        LEFT JOIN customers c
            ON s.customer_id = c.customer_id

        LEFT JOIN users u
            ON s.sales_person = u.id

        WHERE s.invoice_no = ?
        LIMIT 1
    ");

    $stmt->execute([$invoice_no]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        setError("Invoice number not found.");
        header('Location: view-all-invoices.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Sale must be Pending
    |--------------------------------------------------------------------------
    */
    if ($sale['status'] !== 'pending') {
      
        setError(
            "This sale cannot be cancelled because its current status is: " .
            htmlspecialchars($sale['status'])
        );

        header('Location: view-all-invoices.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Vehicle must be Reserved
    |--------------------------------------------------------------------------
    */
    // if ($sale['vehicle_status'] !== 'reserved') {
    //     setError(
    //         "This sale cannot be cancelled because the vehicle is not currently reserved."
    //     );

    //     header('Location: view-all-invoices.php');
    //     exit;
    // }

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */
    $deposit_received = (float)$sale['deposit_received'];

} catch (PDOException $e) {

    error_log("Cancel sale lookup error: " . $e->getMessage());

    setError("Unable to load the sale. Please try again.");
    header('Location: view-all-invoices.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Handle Cancellation
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_sale'])) {

    $errors = [];

    /*
    |--------------------------------------------------------------------------
    | Security: use the sale ID from the loaded record
    |--------------------------------------------------------------------------
    */
    $sale_id = (int)$sale['sale_id'];
    $vehicle_id = (int)$sale['vehicle_id'];

    /*
    |--------------------------------------------------------------------------
    | Cancellation Reason
    |--------------------------------------------------------------------------
    */
    $refund_reason = trim($_POST['refund_reason'] ?? '');

    if ($refund_reason === '') {
        $errors['refund_reason'] = "Please select a cancellation reason.";
    }


    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    */
    $customer_agreement = trim($_POST['customer_agreement'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Deduction
    |--------------------------------------------------------------------------
    */
    $deduction_amount = (float)($_POST['deduction_amount'] ?? 0);

    if ($deduction_amount < 0) {
        $errors['deduction_amount'] = "Deduction cannot be negative.";
    }

    if ($deduction_amount > $deposit_received) {
        $errors['deduction_amount'] =
            "Deduction cannot exceed the deposit received of £" .
            number_format($deposit_received, 2);
    }


    /*
    |--------------------------------------------------------------------------
    | Refund Amount
    |--------------------------------------------------------------------------
    */
    $cash_refund_amount = round($deposit_received - $deduction_amount,2);

    if ($cash_refund_amount < 0) {
        $errors['cash_refund_amount'] = "Refund amount cannot be negative.";
    }


    /*
    |--------------------------------------------------------------------------
    | Payment Details
    |--------------------------------------------------------------------------
    */
    $payment_method = trim($_POST['payment_method'] ?? '');
    $refund_date = $_POST['refund_date'] ?? date('Y-m-d');
    $reference_number = trim($_POST['reference_number'] ?? '');

    if ($cash_refund_amount > 0 && $payment_method === '') {
        $errors['payment_method'] = "Please select a refund payment method.";
    }

    if ($cash_refund_amount > 0 && $reference_number === '') {
        $errors['reference_number'] = "Reference number is required.";
    }

    if (strtotime($refund_date) > time()) {
        $errors['refund_date'] = "Refund date cannot be in the future.";
    }


    /*
    |--------------------------------------------------------------------------
    | Stop if validation errors
    |--------------------------------------------------------------------------
    */
    if (!empty($errors)) {

        setFormErrors($errors);
        saveFormData($_POST);

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | START TRANSACTION
            |--------------------------------------------------------------------------
            */
            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Re-check Sale + Vehicle
            |--------------------------------------------------------------------------
            |
            | This prevents two people from cancelling the same sale.
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                SELECT
                    s.sale_id,
                    s.invoice_no,
                    s.deposit_received,
                    s.status AS sale_status,
                    s.vehicle_id,
                    v.status AS vehicle_status
                FROM sales s
                INNER JOIN vehicles v
                    ON s.vehicle_id = v.vehicle_id
                WHERE s.sale_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$sale_id]);

            $currentSale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentSale) {
                throw new Exception("Sale could not be found.");
            }

            if ($currentSale['sale_status'] !== 'pending') {
                throw new Exception(
                    "This sale is no longer pending and cannot be cancelled."
                );
            }

            if ($currentSale['vehicle_status'] !== 'reserved') {
                throw new Exception(
                    "This vehicle is no longer reserved."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Recalculate using database value
            |--------------------------------------------------------------------------
            */
            $deposit_received = (float)$currentSale['deposit_received'];

            if ($deduction_amount > $deposit_received) {
                throw new Exception(
                    "Deduction cannot exceed the deposit received."
                );
            }

            $cash_refund_amount = round(
                $deposit_received - $deduction_amount,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | Create Refund Record
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                INSERT INTO refunds (
                    sale_id,
                    original_invoice_no,
                    refund_type,
                    max_refundable_before,
                    total_deductions,
                    net_refund_amount,
                    max_refundable_after,
                    vehicle_returned,
                    px_vehicle_returned,
                    vehicle_status_after,
                    refund_reason,
                    customer_agreement,
                    status,
                    created_by
                )
                VALUES (
                    ?, ?, 'cancel_sale', ?, ?, ?, 0,
                    0, 0, 'available',
                    ?, ?, 'approved', ?
                )
            ");

            $stmt->execute([
                $sale_id,
                $currentSale['invoice_no'],
                $deposit_received,
                $deduction_amount,
                $cash_refund_amount,
                $refund_reason,
                $customer_agreement,
                $_SESSION['user_id']
            ]);

            $refund_id = $db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Record Deduction
            |--------------------------------------------------------------------------
            */
            if ($deduction_amount > 0) {

                $stmt = $db->prepare("
                    INSERT INTO refund_deductions (
                        refund_id,
                        deduction_type,
                        description,
                        amount,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $refund_id,
                    'Cancellation Deduction',
                    $refund_reason,
                    $deduction_amount
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Record Customer Refund Payment
            |--------------------------------------------------------------------------
            |
            | Only create a payment transaction when money is actually
            | being returned to the customer.
            |--------------------------------------------------------------------------
            */
            if ($cash_refund_amount > 0) {

                $stmt = $db->prepare("
                    INSERT INTO sale_payments (
                        sale_id,
                        invoice_no,
                        transaction_type,
                        transaction_date,
                        amount,
                        payment_method,
                        reference_number,
                        status,
                        notes,
                        processed_by,
                        created_at
                    )
                    VALUES (
                        ?, ?, 'refund', ?, ?, ?, ?, 'completed', ?, ?, NOW()
                    )
                ");

                $stmt->execute([
                    $sale_id,
                    $currentSale['invoice_no'],
                    $refund_date,
                    -$cash_refund_amount,
                    $payment_method,
                    $reference_number,
                    "Cancellation refund £" .
                        number_format($cash_refund_amount, 2) .
                        " - Refund #$refund_id. " .
                        $refund_reason,
                    $_SESSION['user_id']
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | If Deposit is Retained
            |--------------------------------------------------------------------------
            |
            | Record retained amount as business-kept money.
            |--------------------------------------------------------------------------
            */
            if ($deduction_amount > 0) {

                $stmt = $db->prepare("
                    INSERT INTO sale_payments (
                        sale_id,
                        invoice_no,
                        transaction_type,
                        transaction_date,
                        amount,
                        payment_method,
                        reference_number,
                        status,
                        notes,
                        processed_by,
                        created_at
                    )
                    VALUES (
                        ?, ?, 'payment', ?, ?, 'business_keeps', ?, 'completed', ?, ?, NOW()
                    )
                ");

                $stmt->execute([
                    $sale_id,
                    $currentSale['invoice_no'],
                    $refund_date,
                    $deduction_amount,
                    'DED-' . $refund_id,
                    "Deposit retained £" .
                        number_format($deduction_amount, 2) .
                        " after sale cancellation - Refund #$refund_id",
                    $_SESSION['user_id']
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Cancel Sale
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                UPDATE sales
                SET
                    status = 'cancelled',
                    balance_due = 0,
                    updated_at = NOW(),
                    notes = CONCAT(
                        COALESCE(notes, ''),
                        ?
                    )
                WHERE sale_id = ?
                  AND status = 'pending'
            ");

            $sale_note =
                " | Sale cancelled via Refund #$refund_id on " .
                date('Y-m-d') .
                ". Deposit £" .
                number_format($deposit_received, 2) .
                ", refunded £" .
                number_format($cash_refund_amount, 2) .
                ", retained £" .
                number_format($deduction_amount, 2) .
                ". Reason: " .
                $refund_reason;

            $stmt->execute([
                $sale_note,
                $sale_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception("Failed to cancel the sale.");
            }


            /*
            |--------------------------------------------------------------------------
            | Release Vehicle
            |--------------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                UPDATE vehicles
                SET
                    status = 'available',
                    updated_at = NOW(),
                    notes = CONCAT(
                        COALESCE(notes, ''),
                        ?
                    )
                WHERE vehicle_id = ?
                  AND status = 'reserved'
            ");

            $vehicle_note =
                " | Released from cancelled reservation via Refund #$refund_id on " .
                date('Y-m-d');

            $stmt->execute([
                $vehicle_note,
                $vehicle_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception("Failed to release the vehicle.");
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */
            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */
            clearFormData();
            clearFormErrors();

            setSuccess(
                "Sale cancelled successfully. Refund ID: RF-" .
                str_pad($refund_id, 5, '0', STR_PAD_LEFT)
            );

            header("Location: refund-receipt.php?id=" . $refund_id);
            exit;

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log("Cancel sale error: " . $e->getMessage());

            setError(
                "Unable to cancel the sale: " . $e->getMessage()
            );

            saveFormData($_POST);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">

    <script src="vendors/simplebar/simplebar.min.js"></script>
    <script src="assets/js/config.js"></script>

    <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/line.css">
    <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
    <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">

    <script>
        var phoenixIsRTL = window.config.config.phoenixIsRTL;

        if (phoenixIsRTL) {
            var linkDefault = document.getElementById('style-default');
            var userLinkDefault = document.getElementById('user-style-default');

            linkDefault.setAttribute('disabled', true);
            userLinkDefault.setAttribute('disabled', true);

            document.querySelector('html').setAttribute('dir', 'rtl');
        }
    </script>
</head>

<body>

<main class="main" id="top">

    <?php include("left-menu.php"); ?>

    <div class="content">

        <?php displayMessage(); ?>

        <div class="pb-5">

            <div class="row g-4">

                <div class="col-12">

                    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">

                        <h1 class="h2">
                            Cancel Sale Invoice: <?= htmlspecialchars($sale['invoice_no']) ?>
                        </h1>

                        <a href="view-all-invoices.php"
                           class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Invoices
                        </a>

                    </div>


                    <div class="card sale-info-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Sale Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Invoice:</strong> <?= htmlspecialchars($sale['invoice_no']) ?><br>
                                                <strong>Sale Date:</strong> <?= date('F j, Y', strtotime($sale['sale_date'])) ?><br>
                                                <strong>Sales Person:</strong> <?= htmlspecialchars($sale['salesperson_name']) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Vehicle:</strong> <?= htmlspecialchars($sale['make'] . ' ' . $sale['model']) ?><br>
                                                <strong>Registration:</strong> <?= htmlspecialchars($sale['regnumber']) ?><br>
                                                <strong>Year:</strong> <?= htmlspecialchars($sale['year'] ?? '') ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Customer:</strong> <?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?><br>
                                                <strong>Phone:</strong> <?= htmlspecialchars($sale['phone']) ?><br>
                                                <strong>Sale Price:</strong> £<?= number_format($sale['total_revenue'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                    <form method="POST"
                          action="cancel-sale.php?inv=<?= urlencode($sale['invoice_no']) ?>">

                        <input type="hidden"
                               name="cancel_sale"
                               value="1">


                        <div class="row">

                            <div class="col-lg-8">


                                <!-- Cancellation Details -->
                                <div class="card mb-4">

                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            Cancellation Details
                                        </h5>
                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">

                                            <label class="form-label">
                                                Cancellation Reason
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select name="refund_reason"
                                                    class="form-select <?= hasError('refund_reason') ? 'is-invalid' : '' ?>"
                                                    required>

                                                <option value="">
                                                    Select Reason
                                                </option>

                                                <option value="Customer changed mind">
                                                    Customer changed mind
                                                </option>

                                                <option value="Finance application rejected">
                                                    Finance application rejected
                                                </option>

                                                <option value="Customer failed to proceed">
                                                    Customer failed to proceed
                                                </option>

                                                <option value="Customer purchased another vehicle">
                                                    Customer purchased another vehicle
                                                </option>

                                                <option value="Vehicle issue">
                                                    Vehicle issue
                                                </option>

                                                <option value="Other">
                                                    Other
                                                </option>

                                            </select>

                                            <?php if (hasError('refund_reason')): ?>
                                                <div class="invalid-feedback">
                                                </div>
                                            <?php endif; ?>

                                        </div>


                                        <div class="mb-3">

                                            <label class="form-label">
                                                Notes
                                            </label>

                                            <textarea name="customer_agreement"
                                                      class="form-control"
                                                      rows="4"
                                                      placeholder="Enter any additional details about the cancellation..."><?= htmlspecialchars($_POST['customer_agreement'] ?? '') ?></textarea>

                                        </div>

                                    </div>

                                </div>


                                <!-- Deposit Resolution -->
                                <div class="card mb-4">

                                    <div class="card-header">

                                        <h5 class="mb-0">
                                            Deposit Resolution
                                        </h5>

                                    </div>

                                    <div class="card-body">

                                        <div class="row">

                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Deposit Received
                                                </label>

                                                <div class="input-group">

                                                    <span class="input-group-text">
                                                        £
                                                    </span>

                                                    <input type="text"
                                                           class="form-control"
                                                           value="<?= number_format($deposit_received, 2) ?>"
                                                           readonly>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Amount Retained / Deduction
                                                </label>

                                                <div class="input-group">

                                                    <span class="input-group-text">
                                                        £
                                                    </span>

                                                    <input type="number"
                                                           name="deduction_amount"
                                                           id="deductionAmount"
                                                           class="form-control"
                                                           min="0"
                                                           max="<?= number_format($deposit_received, 2, '.', '') ?>"
                                                           step="0.01"
                                                           value="<?= htmlspecialchars($_POST['deduction_amount'] ?? '0') ?>"
                                                           required>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <label class="form-label">
                                                    Refund to Customer
                                                </label>

                                                <div class="input-group">

                                                    <span class="input-group-text">
                                                        £
                                                    </span>

                                                    <input type="text"
                                                           id="refundAmountDisplay"
                                                           class="form-control"
                                                           value="<?= number_format($deposit_received, 2) ?>"
                                                           readonly>

                                                </div>

                                                <input type="hidden"
                                                       name="cash_refund_amount"
                                                       id="cashRefundAmount"
                                                       value="<?= number_format($deposit_received, 2, '.', '') ?>">

                                            </div>

                                        </div>

                                        <!-- <div class="alert alert-info mt-3 mb-0">

                                            <i class="fas fa-info-circle me-1"></i>

                                            Refund amount is automatically calculated as:

                                            <strong>
                                                Deposit Received − Amount Retained
                                            </strong>

                                        </div> -->

                                    </div>

                                </div>


                                <!-- Refund Payment -->
                                <div class="card mb-4" id="refundPaymentCard">

                                    <div class="card-header">

                                        <h5 class="mb-0">
                                            Refund Payment
                                        </h5>

                                    </div>

                                    <div class="card-body">

                                        <div class="row">

                                            <div class="col-md-6 mb-3">

                                                <label class="form-label">
                                                    Payment Method
                                                </label>

                                                <select name="payment_method"
                                                        id="paymentMethod"
                                                        class="form-select">

                                                    <option value="">
                                                        Select Method
                                                    </option>

                                                    <option value="cash">
                                                        Cash
                                                    </option>

                                                    <option value="bank_transfer">
                                                        Bank Transfer
                                                    </option>

                                                    <option value="card">
                                                        Card Refund
                                                    </option>

                                                    <option value="cheque">
                                                        Cheque
                                                    </option>

                                                    <option value="other">
                                                        Other
                                                    </option>

                                                </select>

                                            </div>


                                            <div class="col-md-6 mb-3">

                                                <label class="form-label">
                                                    Refund Date
                                                </label>

                                                <input type="date"
                                                       name="refund_date"
                                                       class="form-control"
                                                       value="<?= date('Y-m-d') ?>"
                                                       max="<?= date('Y-m-d') ?>">

                                            </div>

                                        </div>


                                        <div class="mb-3">

                                            <label class="form-label">
                                                Reference Number
                                            </label>

                                            <input type="text"
                                                   name="reference_number"
                                                   id="referenceNumber"
                                                   class="form-control"
                                                   placeholder="e.g. REF-001, bank reference, cheque number">

                                        </div>

                                    </div>

                                </div>

                                    <p class="mb-5 mt-2">Note: Cancelling this sale will change the sale status to
                                        <strong>Cancelled</strong> and release the vehicle back to
                                        <strong>Available</strong>.
                                    </p>
                                    
                                <!-- <div class="alert alert-danger">

                                    <strong>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Final Action
                                    </strong>

                                    <p class="mb-0 mt-2">
                                        Cancelling this sale will change the sale status to
                                        <strong>Cancelled</strong> and release the vehicle back to
                                        <strong>Available</strong>.
                                    </p>

                                </div> -->


                                <div class="d-flex justify-content-between">

                                    <a href="view-all-invoices.php"
                                       class="btn btn-secondary">
                                        Cancel
                                    </a>

                                    <button type="submit"
                                            class="btn btn-danger"
                                            onclick="return confirm('Are you sure you want to cancel this sale and release the vehicle?');">

                                        <i class="fas fa-times me-1"></i>
                                        Cancel Sale

                                    </button>

                                </div>

                            </div>


                            <div class="col-lg-4">

                                <div class="card">

                                    <div class="card-header bg-primary text-white">

                                        <h5 class="mb-0 text-white">
                                            Sale Summary
                                        </h5>

                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3 d-flex justify-content-between">

                                            <span>Sale Price:</span>

                                            <strong>
                                                £<?= number_format($sale['sale_price'], 2) ?>
                                            </strong>

                                        </div>


                                        <div class="mb-3 d-flex justify-content-between">

                                            <span>Deposit Received:</span>

                                            <strong class="text-success">
                                                £<?= number_format($deposit_received, 2) ?>
                                            </strong>

                                        </div>


                                        <div class="mb-3 d-flex justify-content-between">

                                            <span>Current Status:</span>

                                            <span class="badge bg-warning">
                                                <?= htmlspecialchars($sale['status']) ?>
                                            </span>

                                        </div>


                                        <hr>


                                        <div class="d-flex justify-content-between">

                                            <strong>Vehicle:</strong>

                                            <strong class="text-success">
                                                <?= htmlspecialchars($sale['vehicle_status']) ?>
                                            </strong>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>


<script src="vendors/popper/popper.min.js"></script>
<script src="vendors/bootstrap/bootstrap.min.js"></script>
<script src="vendors/fontawesome/all.min.js"></script>
<script src="assets/js/phoenix.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const deductionInput = document.getElementById('deductionAmount');
    const refundDisplay = document.getElementById('refundAmountDisplay');
    const refundInput = document.getElementById('cashRefundAmount');
    const refundPaymentCard = document.getElementById('refundPaymentCard');

    const deposit = <?= json_encode($deposit_received) ?>;

    function calculateRefund() {

        let deduction = parseFloat(deductionInput.value) || 0;

        if (deduction < 0) {
            deduction = 0;
        }

        if (deduction > deposit) {
            deduction = deposit;
            deductionInput.value = deposit.toFixed(2);
        }

        const refund = Math.max(0, deposit - deduction);

        refundDisplay.value = refund.toFixed(2);
        refundInput.value = refund.toFixed(2);

        /*
         * Payment details are only required when
         * money is actually being refunded.
         */
        const paymentMethod = document.getElementById('paymentMethod');
        const referenceNumber = document.getElementById('referenceNumber');

        if (refund > 0) {
            refundPaymentCard.style.display = 'block';

            paymentMethod.required = true;
            referenceNumber.required = true;
        } else {
            refundPaymentCard.style.display = 'none';

            paymentMethod.required = false;
            referenceNumber.required = false;

            paymentMethod.value = '';
            referenceNumber.value = '';
        }
    }

    deductionInput.addEventListener('input', calculateRefund);

    calculateRefund();

});

</script>

</body>
</html>