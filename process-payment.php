<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Process Payment - Invoice";

// Check permissions
if (!hasPermission('manage_sales')) {
  $_SESSION['error'] = "You don't have permission to process payments.";
  header('Location: view-all-invoices.php');
  exit;
}

$invoice_no = $_GET['inv'] ?? '';
$success = $error = '';

// Validate invoice number
if (empty($invoice_no)) {
  $_SESSION['error'] = "Invoice number is required.";
  header('Location: view-all-invoices.php');
  exit;
}

// Fetch invoice details with all related data
$invoice = [];
try {
  $stmt = $db->prepare("
        SELECT 
            s.*,
            v.vehicle_id, v.make, v.model, v.regnumber, v.year, v.engine_no, v.vin, v.status as vehicle_status,
            c.customer_id, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.post_code,
            u.full_name as salesperson_name,
            a.auction_name,
            f.company_name as finance_company_name
        FROM sales s
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN users u ON s.sales_person = u.id
        LEFT JOIN auctions a ON v.auction_id = a.auction_id
        LEFT JOIN finance_companies f ON s.finance_company_id = f.finance_company_id
        WHERE s.invoice_no = ?
    ");
  $stmt->execute([$invoice_no]);
  $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$invoice) {
    setError("Invoice not found.");
    header('Location: view-all-invoices.php');
    exit;
  }
} catch (PDOException $e) {
  setError("Error fetching invoice: " . $e->getMessage());
  header('Location: process-payment.php');
  exit;
}

// Get payment history
$payments = [];
try {
  $stmt = $db->prepare("
        SELECT sp.*, u.full_name as processed_by_name 
        FROM sale_payments sp 
        LEFT JOIN users u ON sp.processed_by = u.id 
        WHERE sp.sale_id = ? 
        ORDER BY sp.transaction_date DESC, sp.payment_id DESC
    ");
  $stmt->execute([$invoice['sale_id']]);
  $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
  //print_r($payments);
  //die;
} catch (PDOException $e) {
  // Table might not exist yet, log error but don't break
  setError("Error fetching payment history: " . $e->getMessage());
  header('Location: process-payment.php');
  exit;
}


// Calculate total received amount for refund validation
$total_received = 0;
try {
  $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_received 
        FROM sale_payments 
        WHERE sale_id = ? AND transaction_type = 'payment' AND status = 'completed'
    ");
  $stmt->execute([$invoice['sale_id']]);
  $total_received_result = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_received = (float)$total_received_result['total_received'];
  $balance_due = (float)$invoice['total_revenue'] - $total_received;
  $refundable_amount = max(0, $total_received - $balance_due);
} catch (PDOException $e) {
  $total_received = 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_payment') {
      // Handle balance payment
      $payment_amount = (float)$_POST['payment_amount'];
      $payment_method = trim($_POST['payment_method']);
      $payment_date = $_POST['payment_date'];
      $reference_number = trim($_POST['reference_number']);
      $payment_notes = trim($_POST['payment_notes'] ?? '');

      // Validate payment data
      if ($payment_amount <= 0) {
        throw new Exception("Payment amount must be greater than 0.");
      }


      if (empty($payment_method)) {
        throw new Exception("Payment method is required.");
      }

      if (empty($payment_date)) {
        throw new Exception("Payment date is required.");
      }
      // update deposit_recieved in sales table
      // $amount_recieved = $total_received + $payment_amount;

      // Update balance due
      $new_balance = $invoice['balance_due'] - $payment_amount;
      $new_status = $new_balance <= 0 ? 'completed' : 'pending';

      $stmt = $db->prepare("
                UPDATE sales 
                SET balance_due = ?, status = ?, updated_at = NOW()
                WHERE invoice_no = ?
            ");
      $stmt->execute([$new_balance, $new_status, $invoice_no]);

      // In the payment processing section
      $paid_by = $_POST['paid_by'];
          //  $finance_company = ($paid_by === 'finance_company') ? $_POST['finance_company'] : NULL;

      $stmt = $db->prepare("
          INSERT INTO sale_payments (
              sale_id, invoice_no, transaction_type, transaction_date, 
              amount, payment_method, reference_number, status, notes, 
              processed_by, paid_by
          ) 
          VALUES (?, ?, 'payment', ?, ?, ?, ?, 'completed', ?, ?, ?)
      ");
      $stmt->execute([
        $invoice['sale_id'],
        $invoice_no,
        $payment_date,
        $payment_amount,
        $payment_method,
        $reference_number,
        $payment_notes,
        $_SESSION['user_id'],
        $paid_by
      ]);

      // Update vehicle status if fully paid
      if ($new_balance <= 0) {
        $stmt = $db->prepare("UPDATE vehicles SET status = 'sold' WHERE vehicle_id = ?");
        $stmt->execute([$invoice['vehicle_id']]);
      }

      setSuccess("Payment of £" . number_format($payment_amount, 2) . " recorded successfully.");
    } elseif ($action === 'update_details') {
      // Update invoice details (limited fields)
      $customer_notes = trim($_POST['customer_notes'] ?? '');
      $internal_notes = trim($_POST['internal_notes'] ?? '');

      $stmt = $db->prepare("
                UPDATE sales 
                SET notes = ?, internal_notes = ?, updated_at = NOW()
                WHERE invoice_no = ?
            ");
      $stmt->execute([$customer_notes, $internal_notes, $invoice_no]);
      setSuccess("Invoice details updated successfully.");

    } elseif ($action === 'update_status') {
      // Update invoice status
      $new_status = $_POST['new_status'];
      $status_reason = trim($_POST['status_reason'] ?? '');

      $allowed_statuses = ['completed', 'pending', 'cancelled'];
      if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception("Invalid status selected.");
      }

      $stmt = $db->prepare("
                UPDATE sales 
                SET status = ?, updated_at = NOW(),
                notes = CONCAT(COALESCE(notes, ''), ' Status changed to: $new_status. $status_reason')
                WHERE invoice_no = ?
            ");
      $stmt->execute([$new_status, $invoice_no]);

      // If cancelled, make vehicle available again
      if ($new_status === 'cancelled') {
        $stmt = $db->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
        $stmt->execute([$invoice['vehicle_id']]);
      }

      setSuccess("Invoice status updated to " . ucfirst($new_status) . ".");
    } elseif ($action === 'process_refund') {
      // Handle refund processing
      $refund_amount = (float)$_POST['refund_amount'];
      $refund_method = trim($_POST['refund_method']);
      $refund_date = $_POST['refund_date'];
      $refund_reference = trim($_POST['refund_reference']);
      $refund_reason = trim($_POST['refund_reason']);

      // Validate refund data
      if ($refund_amount <= 0) {
        throw new Exception("Refund amount must be greater than 0.");
      }

      if ($refund_amount > $total_received) {
        throw new Exception("Refund amount cannot exceed total received amount.");
      }

      if (empty($refund_method)) {
        throw new Exception("Refund method is required.");
      }

      // Calculate new balance and status
      $new_balance = $balance_due + $refund_amount;
      $is_full_refund = ($refund_amount >= $total_received);
      $new_status = $is_full_refund ? 'cancelled' : $invoice['status'];

      // Update sales table
      $stmt = $db->prepare("
            UPDATE sales 
            SET balance_due = ?, status = ?, updated_at = NOW()
            WHERE invoice_no = ?
        ");
      $stmt->execute([$new_balance, $new_status, $invoice_no]);

      // Record refund transaction
      $stmt = $db->prepare("
            INSERT INTO sale_payments (
                sale_id, invoice_no, transaction_type, transaction_date, 
                amount, payment_method, reference_number, status, notes, processed_by
            ) 
            VALUES (?, ?, 'refund', ?, ?, ?, ?, 'completed', ?, ?)
        ");
      $stmt->execute([
        $invoice['sale_id'],
        $invoice_no,
        $refund_date,
        $refund_amount,
        $refund_method,
        $refund_reference,
        $refund_reason,
        $_SESSION['user_id']
      ]);

      // Make vehicle available again if full refund
      if ($is_full_refund) {
        $stmt = $db->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
        $stmt->execute([$invoice['vehicle_id']]);
      }

      setSuccess("Refund of £" . number_format($refund_amount, 2) . " processed successfully.");
    }

    // Refresh invoice data
    $stmt = $db->prepare("
            SELECT s.*, v.make, v.model, v.regnumber, v.engine_no, v.vin 
            FROM sales s 
            LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id 
            WHERE s.invoice_no = ?
        ");
    $stmt->execute([$invoice_no]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    setSuccess("Payment records created.");
    header("Location: process-payment.php?inv=$invoice_no");
    exit;
  } catch (Exception $e) {
    setError("Error updating invoice: " . $e->getMessage());
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
              <h1 class="h2">Process Payment - Invoice #<?= htmlspecialchars($invoice_no) ?></h1>
              <div class="btn-toolbar mb-2 mb-md-0">
                <a href="sale-invoice.php?inv=<?= $invoice_no ?>" class="btn btn-secondary me-2">
                  <i class="fas fa-eye"></i> View Invoice
                </a>
                <a href="view-all-invoices.php" class="btn btn-outline-primary">
                  <i class="fas fa-arrow-left"></i> Back to Invoices
                </a>
              </div>
            </div>


            <!-- Invoice Header -->
            <div class="card invoice-header text-white mb-4 p-0">
              <div class="card-body p-3">
                <div class="row align-items-center">
                  <div class="col-md-6">
                    <h3 class="card-title text-dark"><?= htmlspecialchars($invoice_no) ?></h3>
                    <p class="text-dark mb-0">
                      <strong><?= htmlspecialchars($invoice['make'] . ' ' . $invoice['model']) ?></strong>
                      (<?= htmlspecialchars($invoice['regnumber']) ?>)<br>
                      Engine#<?= htmlspecialchars($invoice['engine_no']) ?><br>
                      Chassis#<?= htmlspecialchars($invoice['vin']) ?>
                    </p>
                    <p class="text-dark mb-0">
                      Customer: <?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?><br>
                      Sale Date: <?= date('F j, Y', strtotime($invoice['sale_date'])) ?>
                    </p>
                  </div>
                  <div class="col-md-6 text-end">
                    <div class="h4 mb-1 text-dark">£<?= number_format($invoice['total_revenue'], 2) ?></div>
                    <?php if ($invoice['balance_due'] > 0): ?>
                      <div class="h4 text-warning">
                        <strong>Balance Due: £<?= number_format($invoice['balance_due'], 2) ?></strong>
                      </div>
                      <span class="badge badge-phoenix badge-phoenix-warning fs-6">
                        <?= ucfirst($invoice['status']) ?>
                      </span>
                    <?php else: ?>
                      <div class="balance-due text-success">
                        <i class="fas fa-check-circle"></i> <strong>Fully Paid</strong>
                      </div>
                      <span class="badge badge-phoenix badge-phoenix-success fs-6">
                        <?= ucfirst($invoice['status']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-lg-6">
                <!-- Tabs -->
                <ul class="nav nav-pills mb-4" id="editTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                      <i class="fas fa-money-bill-wave"></i> Record Payment
                    </button>
                  </li>
                </ul>

                <div class="tab-content" id="editTabsContent">
                  <!-- Record Payment Tab -->
                  <div class="tab-pane fade show active" id="payment" role="tabpanel">
                    <div class="row">
                      <div class="col-lg-12">
                        <div class="card">
                          <div class="card-header bg-success text-white p-3">
                            <h5 class="mb-0 text-white">💰 Record Payment</h5>
                          </div>
                          <div class="card-body">
                            <?php if ($invoice['balance_due'] > 0): ?>
                              <form method="POST" id="paymentForm">
                                <input type="hidden" name="action" value="update_payment">

                                <div class="mb-3">
                                  <label class="form-label">Payment Amount *</label>
                                  <div class="input-group">
                                    <span class="input-group-text">£</span>
                                    <input type="number" step="0.01" class="form-control"
                                      name="payment_amount"
                                      min="0.01"
                                      value="<?= $invoice['balance_due'] ?>"
                                      required
                                      onchange="validatePaymentAmount(this)">
                                  </div>
                                  <!-- <small class="form-text text-muted">
                                Balance Due: £<?= number_format($invoice['balance_due'], 2) ?>
                              </small> -->
                                  <div class="invalid-feedback" id="amountError"></div>
                                </div>

                                <div class="row">
                                  <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Method *</label>
                                    <select class="form-select" name="payment_method" required>
                                      <option value="">Select Method</option>
                                      <option value="cash">Cash</option>
                                      <option value="bank_transfer">Bank Transfer</option>
                                      <option value="card">Card</option>
                                      <option value="check">Cheque</option>
                                      <option value="other">Other</option>
                                    </select>
                                  </div>
                                  <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" name="payment_date"
                                      value="<?= date('Y-m-d') ?>"
                                      max="<?= date('Y-m-d') ?>"
                                      required>
                                  </div>
                                </div>
                                <div class="row">
                                  <div class="col-md-6 mb-3">
                                    <label class="form-label">Paid By *</label>
                                    <select class="form-select" name="paid_by" id="paidBy" required>
                                      <option value="customer">Customer</option>
                                      <option value="finance_company">Finance Company</option>
                                      
                                    </select>
                                  </div>
                                  <!-- Finance Company Selection (shown only when paid_by = finance_company) -->
                                  <div class="col-md-6 mb-3" id="financeCompanySection" style="display: none;">
                                    <label class="form-label">Finance Company *</label>
                                    <input type="text" class="form-control" name="finance_company"
                                      value="<?= $invoice['finance_company_name'] ?>">
                                  </div>
                                  
                                  <div class="col-md-6 mb-3">
                                    <label class="form-label">Reference Number *</label>
                                    <input type="text" class="form-control" name="reference_number"
                                      placeholder="e.g., BNK-REF-12345, CHK-7890, TXN-ABCD1234" required>
                                    <small class="form-text text-muted">
                                      Bank reference, check number, transaction ID
                                    </small>
                                  </div>
                                </div>
                                <div class="mb-3">
                                  <label class="form-label">Payment Notes</label>
                                  <textarea class="form-control" name="payment_notes" rows="2"
                                    placeholder="Payment reference or notes..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success w-100" id="submitPayment">
                                  <i class="fas fa-check-circle"></i> Record Payment
                                </button>
                              </form>
                            <?php else: ?>
                              <div class="badge badge-phoenix badge-phoenix-success text-center p-5" style="width:100%">
                                <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                                <strong>This invoice is fully paid.</strong><br>
                                No balance due.
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>


                    </div>
                  </div>

                  <!-- Update Details Tab -->
                  <!-- <div class="tab-pane fade" id="details" role="tabpanel">
                <div class="card">
                  <div class="card-header bg-info text-white">
                    <h6 class="mb-0">📝 Update Invoice Details</h6>
                  </div>
                  <div class="card-body">
                    <form method="POST">
                      <input type="hidden" name="action" value="update_details">
                      
                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label class="form-label">Customer Notes</label>
                          <textarea class="form-control" name="customer_notes" rows="4" 
                                    placeholder="Notes visible to customer..."><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
                          <small class="form-text text-muted">Visible on customer invoice</small>
                        </div>
                        <div class="col-md-6 mb-3">
                          <label class="form-label">Internal Notes</label>
                          <textarea class="form-control" name="internal_notes" rows="4" 
                                    placeholder="Internal notes for staff..."><?= htmlspecialchars($invoice['internal_notes'] ?? '') ?></textarea>
                          <small class="form-text text-muted">Internal use only</small>
                        </div>
                      </div>
                      
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Details
                      </button>
                    </form>
                  </div>
                </div>
              </div> -->

                  <!-- Change Status Tab -->
                  <div class="tab-pane fade" id="status" role="tabpanel">
                    <div class="card">
                      <div class="card-header bg-warning text-white p-3">
                        <h5 class="mb-0 p-0 text-white">🔄 Change Invoice Status</h5>
                      </div>
                      <div class="card-body">
                        <form method="POST">
                          <input type="hidden" name="action" value="update_status">

                          <div class="row">
                            <div class="col-md-6 mb-3">
                              <label class="form-label">New Status *</label>
                              <select class="form-select" name="new_status" required>
                                <option value="">Select Status</option>
                                <option value="completed" <?= $invoice['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="pending" <?= $invoice['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="cancelled" <?= $invoice['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Reason for Change</label>
                              <input type="text" class="form-control" name="status_reason"
                                placeholder="Reason for status change...">
                            </div>
                          </div>

                          <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong>
                            <ul class="mb-0 mt-2">
                              <li>Changing status to "Cancelled" will make the vehicle available for sale again.</li>
                              <li>Changing status to "Completed" will mark the sale as finished.</li>
                            </ul>
                          </div>

                          <button type="submit" class="btn btn-warning">
                            <i class="fas fa-sync"></i> Update Status
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                  <!-- Process Refund Tab -->
                  <div class="tab-pane fade" id="refund" role="tabpanel">
                    <div class="card">
                      <div class="card-header bg-danger text-white p-3">
                        <h5 class="mb-0 text-white">↩️ Process Refund</h5>
                      </div>
                      <div class="card-body">
                        <form method="POST" id="refundForm" novalidate>
                          <input type="hidden" name="action" value="process_refund">

                          <div class="mb-3">
                            <label class="form-label">Refund Amount *</label>
                            <div class="input-group">
                              <span class="input-group-text">£</span>
                              <input type="number" step="0.01" class="form-control"
                                name="refund_amount"
                                min="0.01"
                                max="<?= $refundable_amount ?>"
                                value="<?= $refundable_amount ?>"
                                required
                                onchange="validateRefundAmount(this)">
                            </div>
                            <small class="form-text text-muted">
                              Maximum refundable: £<?= number_format($refundable_amount, 2) ?>
                              (Total Paid: £<?= number_format($total_paid, 2) ?> - Balance Due: £<?= number_format($invoice['balance_due'], 2) ?>)
                            </small>
                            <div class="invalid-feedback" id="refundAmountError"></div>
                          </div>

                          <div class="row">
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Refund Method *</label>
                              <select class="form-select" name="refund_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card Refund</option>
                                <option value="check">Check</option>
                                <option value="other">Other</option>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Refund Date *</label>
                              <input type="date" class="form-control" name="refund_date"
                                value="<?= date('Y-m-d') ?>"
                                max="<?= date('Y-m-d') ?>"
                                required>
                            </div>
                          </div>

                          <div class="mb-3">
                            <label class="form-label">Reference Number *</label>
                            <input type="text" class="form-control" name="refund_reference"
                              placeholder="e.g., REF-12345, Check #7890" required>
                            <small class="form-text text-muted">
                              Refund reference for tracking
                            </small>
                          </div>

                          <div class="mb-3">
                            <label class="form-label">Refund Reason *</label>
                            <textarea class="form-control" name="refund_reason" rows="3"
                              placeholder="Reason for refund..." required></textarea>
                          </div>

                          <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Note:</strong>
                            <ul class="mb-0 mt-2">
                              <li>Full refund will make the vehicle available for sale again</li>
                              <li>Partial refund will update the invoice balance</li>
                            </ul>
                          </div>

                          <button type="submit" class="btn btn-danger w-100" id="submitRefund">
                            <i class="fas fa-undo"></i> Process Refund
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Tabs End -->
              </div>
              <div class="col-lg-6">
                <div class="card">
                  <div class="card-header bg-primary text-white p-3">
                    <h5 class="mb-0 text-white">📋 Payment History</h5>
                  </div>
                  <div class="card-body payment-history" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($payments)): ?>
                      <div class="text-center text-muted py-3">
                        <i class="fas fa-history fa-2x mb-2"></i><br>
                        No payment history recorded.
                      </div>
                    <?php else: ?>
                      <?php foreach ($payments as $payment): ?>
                        <div class="border-bottom pb-2 mb-2">
                          <div class="d-flex justify-content-between align-items-start">
                            <div>
                              <strong class="<?= ($payment['transaction_type'] == 'refund' ? 'text-danger' : 'text-success') ?>">£<?= number_format($payment['amount'], 2) ?></strong>
                              <div class="small text-muted">
                                <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                • Paid by: <?= $payment['paid_by'] === 'finance_company' ? 'Finance Company: ' . htmlspecialchars($invoice['finance_company_name']) : 'Customer' ?>
                                <?php if (!empty($payment['notes'])): ?>
                                  • <?= htmlspecialchars($payment['notes']) ?>
                                <?php endif; ?>
                              </div>
                              
                            </div>

                            <div class="text-end">
                              <small class="text-muted d-block"><?= date('M j, Y', strtotime($payment['transaction_date'])) ?></small>
                              <?php if (!empty($payment['created_at'])): ?>
                                <small class="text-muted"><?= date('g:i A', strtotime($payment['created_at'])) ?></small>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <!-- Edit Tabs -->


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
  <script>
    function validateRefundAmount(input) {
      const maxAmount = <?= $total_paid ?>;
      const amount = parseFloat(input.value);
      const errorDiv = document.getElementById('refundAmountError');

      if (amount <= 0) {
        input.classList.add('is-invalid');
        errorDiv.textContent = 'Refund amount must be greater than 0.';
        return false;
      } else {
        input.classList.remove('is-invalid');
        errorDiv.textContent = '';
        return true;
      }
    }

    function validatePaymentAmount(input) {
      const maxAmount = <?= $invoice['balance_due'] ?>;
      const amount = parseFloat(input.value);
      const errorDiv = document.getElementById('amountError');

      if (amount <= 0) {
        input.classList.add('is-invalid');
        errorDiv.textContent = 'Payment amount must be greater than 0.';
        return false;
      } else {
        input.classList.remove('is-invalid');
        errorDiv.textContent = '';
        return true;
      }
    }

    const refundForm = document.getElementById('refundForm');
    if (refundForm) {
      refundForm.addEventListener('submit', function(e) {
        const amountInput = this.querySelector('input[name="refund_amount"]');
        if (!validateRefundAmount(amountInput)) {
          e.preventDefault();
          return false;
        }

        const submitBtn = this.querySelector('#submitRefund');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Refund...';
      });
    }
    document.addEventListener('DOMContentLoaded', function() {
      const paymentForm = document.getElementById('paymentForm');
      if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
          const amountInput = this.querySelector('input[name="payment_amount"]');
          if (!validatePaymentAmount(amountInput)) {
            e.preventDefault();
            return false;
          }

          // Add loading state
          const submitBtn = this.querySelector('#submitPayment');
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
      }

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
  <!-- =============================================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const paymentMethod = document.getElementById('paymentMethod');
      const paidBy = document.getElementById('paidBy');
      const financeCompanySection = document.getElementById('financeCompanySection');
      const financeCompany = document.getElementById('financeCompany');

      // Show finance company section when paid_by is finance_company
      paidBy.addEventListener('change', function() {
        if (this.value === 'finance_company') {
          financeCompanySection.style.display = 'block';
          financeCompany.required = true;
          // Auto-select finance payment method
          paymentMethod.value = 'finance';
        } else {
          financeCompanySection.style.display = 'none';
          financeCompany.required = false;
          financeCompany.value = '';
        }
      });

      // Auto-set paid_by when finance payment method selected
      paymentMethod.addEventListener('change', function() {
        if (this.value === 'finance') {
          paidBy.value = 'finance_company';
          financeCompanySection.style.display = 'block';
          financeCompany.required = true;
        }
      });
    });
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