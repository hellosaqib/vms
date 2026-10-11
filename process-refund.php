<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Process Refund - Invoice";
// Check permissions
if (!hasPermission('manage_sales')) {
  $_SESSION['error'] = "You don't have permission to process refunds.";
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

// Fetch invoice details
$stmt = $db->prepare("
    SELECT s.*, v.vehicle_id, v.make, v.model, v.regnumber
    FROM sales s
    LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
    WHERE s.invoice_no = ?
");
$stmt->execute([$invoice_no]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
  $_SESSION['error'] = "Invoice not found.";
  header('Location: view-all-invoices.php');
  exit;
}

// Calculate total paid amount for refund validation
$total_paid = 0;
try {
  $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_paid 
        FROM sale_payments 
        WHERE sale_id = ? AND transaction_type = 'payment' AND status = 'completed'
    ");
  $stmt->execute([$invoice['sale_id']]);
  $total_paid_result = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_paid = (float)$total_paid_result['total_paid'];
} catch (PDOException $e) {
  $total_paid = 0;
}

// Handle refund form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_refund') {
  try {
    $refund_amount = (float)$_POST['refund_amount'];
    $refund_method = trim($_POST['refund_method']);
    $refund_date = $_POST['refund_date'];
    $refund_reference = trim($_POST['refund_reference']);
    $refund_reason = trim($_POST['refund_reason']);

    // Validate refund data
    if ($refund_amount <= 0) {
      throw new Exception("Refund amount must be greater than 0.");
    }

    if ($refund_amount > $total_paid) {
      throw new Exception("Refund amount cannot exceed the total paid amount of £" . number_format($total_paid, 2));
    }

    if (empty($refund_method)) {
      throw new Exception("Refund method is required.");
    }

    // Calculate new balance and status
    $new_balance = $invoice['balance_due'] + $refund_amount;
    $is_full_refund = ($refund_amount >= $total_paid);
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

    // Make vehicle available again if it was a full refund
    if ($is_full_refund) {
      $stmt = $db->prepare("UPDATE vehicles SET status = 'available' WHERE vehicle_id = ?");
      $stmt->execute([$invoice['vehicle_id']]);
    }

    $_SESSION['success'] = "Refund of £" . number_format($refund_amount, 2) . " processed successfully.";
    header("Location: process-payment.php?inv=$invoice_no");
    exit;
  } catch (Exception $e) {
    $error = "Error processing refund: " . $e->getMessage();
  }
}

?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo ($pageTitle); ?></title>
  <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
  <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/line.css">
  <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
  <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">

</head>

<body>
  <main class="main" id="top">
    <?php include("left-menu.php"); ?>

    <div class="content">
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-8 offset-xxl-2">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
              <h1 class="h2">Process Refund - Invoice #<?= htmlspecialchars($invoice_no) ?></h1>
              <div class="btn-toolbar mb-2 mb-md-0">
                <a href="process-payment.php?inv=<?= $invoice_no ?>" class="btn btn-outline-primary">
                  <i class="fas fa-arrow-left"></i> Back to Payment
                </a>
              </div>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
              <div class="card-header bg-danger text-white p-3">
                <h5 class="mb-0 text-white">↩️ Process Refund</h5>
              </div>
              <div class="card-body">
                <form method="POST" id="refundForm">
                  <input type="hidden" name="action" value="process_refund">

                  <div class="mb-3">
                    <label class="form-label">Refund Amount *</label>
                    <div class="input-group">
                      <span class="input-group-text">£</span>
                      <input type="number" step="0.01" class="form-control" name="refund_amount" min="0.01" max="<?= $total_paid ?>" value="<?= $total_paid ?>" required>
                    </div>
                    <small class="form-text text-muted">
                      Maximum refundable amount: £<?= number_format($total_paid, 2) ?>
                    </small>
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
                      <input type="date" class="form-control" name="refund_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Reference Number *</label>
                    <input type="text" class="form-control" name="refund_reference" placeholder="e.g., REF-12345, Check #7890" required>
                    <small class="form-text text-muted">
                      Refund reference for tracking
                    </small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Refund Reason *</label>
                    <textarea class="form-control" name="refund_reason" rows="3" placeholder="Reason for refund..." required></textarea>
                  </div>

                  <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Note:</strong>
                    <ul class="mb-0 mt-2">
                      <li>A full refund will mark the sale as 'Cancelled' and make the vehicle available again.</li>
                      <li>A partial refund will update the invoice balance.</li>
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
      </div>
        <?php include_once("footer.php"); ?>

    </div>
  </main>

  <script src="vendors/popper/popper.min.js"></script>
  <script src="vendors/bootstrap/bootstrap.min.js"></script>
  <script src="vendors/anchorjs/anchor.min.js"></script>
  <script src="vendors/is/is.min.js"></script>
  <script src="vendors/fontawesome/all.min.js"></script>
  <script src="vendors/lodash/lodash.min.js"></script>
  <script src="vendors/list.js/list.min.js"></script>
  <script src="vendors/feather-icons/feather.min.js"></script>
  <script src="vendors/dayjs/dayjs.min.js"></script>
  <script src="assets/js/phoenix.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const refundForm = document.getElementById('refundForm');
      if (refundForm) {
        refundForm.addEventListener('submit', function(e) {
          const amountInput = this.querySelector('input[name="refund_amount"]');
          const maxAmount = parseFloat(amountInput.max);
          if (parseFloat(amountInput.value) > maxAmount) {
            alert('Refund amount cannot exceed the maximum refundable amount.');
            e.preventDefault();
            return false;
          }

          const submitBtn = this.querySelector('#submitRefund');
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Refund...';
        });
      }
    });
  </script>
  
</body>

</html>