<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Sale Vehicle";

$vehicle_id = $_GET['id'] ?? 0;
$success = $error = '';

// Fetch vehicle details
$vehicle = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, a.auction_name 
        FROM vehicles v 
        LEFT JOIN auctions a ON v.auction_id = a.auction_id 
        WHERE v.vehicle_id = ?
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        $_SESSION['error'] = "Vehicle not found.";
        header('Location: view-all-vehicles.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Error fetching vehicle: " . $e->getMessage();
}

// Fetch existing customers for dropdown
$customers = [];
try {
    $stmt = $db->query("SELECT customer_id, first_name, last_name, phone FROM customers ORDER BY first_name, last_name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching customers: " . $e->getMessage();
}

// Function to generate invoice number
function generateInvoiceNumber($sale_date) {
  global $db;
    $year = date('Y', strtotime($sale_date));
    
    // Find the highest invoice number for this year
    $stmt = $db->prepare("
        SELECT invoice_no FROM sales 
        WHERE invoice_no LIKE ? 
        ORDER BY invoice_no DESC 
        LIMIT 1
    ");
    $stmt->execute(["INV-$year-%"]);
    $last_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_invoice) {
        // Extract number and increment
        $last_number = intval(substr($last_invoice['invoice_no'], -3));
        $next_number = $last_number + 1;
    } else {
        // First invoice of the year
        $next_number = 1;
    }
    
    return "INV-$year-" . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Basic sale data
        //$vehicle_id = $_POST['vehicle_id'];
        $sale_price = $_POST['sale_price'];
        $sale_date = $_POST['sale_date'];
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        $customer_type = $_POST['customer_type'];
        $service_fee = $_POST['service_fee'] ?? 0;
        $deposit_received = $_POST['deposit_received'] ?? 0;
        $delivery_charges = $_POST['delivery_charges'] ?? 0;
        $finance_amount = $_POST['finance_amount'] ?? 0;
        $is_sold_as_seen = isset($_POST['is_sold_as_seen']) ? 1 : 0;
        $hpi_clear_status = $_POST['hpi_clear_status'] ?? 'not_checked';
        $includes_part_exchange = isset($_POST['includes_part_exchange']) ? 1 : 0;
        $part_exchange_allowance = $_POST['part_exchange_allowance'] ?? 0;
        
        $balance_due = $sale_price + $service_fee + $delivery_charges - $deposit_received - $part_exchange_allowance - $finance_amount;
        // Calculate balance due
        
        
        // Handle customer based on type
        if ($customer_type === 'existing') {
            $customer_id = $_POST['existing_customer_id'];
            if (empty($customer_id)) {
                throw new Exception("Please select a customer.");
            }
        } else { // new customer
            // Validate new customer data
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $post_code = trim($_POST['post_code'] ?? '');
            
            if (empty($first_name) || empty($last_name) || empty($phone)) {
                throw new Exception("First name, last name, and phone are required for new customers.");
            }
            
            // Check if customer already exists with same phone
            $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ?");
            $stmt->execute([$phone]);
            $existing_customer = $stmt->fetch();
            
            if ($existing_customer) {
                $customer_id = $existing_customer['customer_id'];
            } else {
                // Insert new customer
                $stmt = $db->prepare("
                    INSERT INTO customers (first_name, last_name, email, phone, address, city, post_code, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $first_name, 
                    $last_name, 
                    $email, 
                    $phone, 
                    $address, 
                    $city, 
                    $post_code, 
                    $_SESSION['user_id']
                ]);
                $customer_id = $db->lastInsertId();
            }
        }
        
        // Handle part-exchange vehicle if included
        $part_exchange_vehicle_id = null;
        if ($includes_part_exchange) {
            $px_make = trim($_POST['px_make']);
            $px_model = trim($_POST['px_model']);
            $px_regnumber = trim($_POST['px_regnumber']);
            $px_mileage = $_POST['px_mileage'] ?? 0;
            $px_color = trim($_POST['px_color'] ?? '');
            $px_notes = trim($_POST['px_notes'] ?? '');
            
            if (empty($px_make) || empty($px_model) || empty($px_regnumber)) {
                throw new Exception("Part-exchange vehicle make, model, and registration are required.");
            }
            
            // Create part-exchange vehicle
            $stmt = $db->prepare("
                INSERT INTO vehicles (regnumber, make, model, mileage, color, status, purchase_date, purchase_price, created_by, notes)
                VALUES (?, ?, ?, ?, ?, 'part_exchange', CURDATE(), ?, ?, ?)
            ");
            $stmt->execute([
                $px_regnumber,
                $px_make,
                $px_model,
                $px_mileage,
                $px_color,
                $part_exchange_allowance, // Use allowance as purchase price
                $_SESSION['user_id'],
                $px_notes
            ]);
            $part_exchange_vehicle_id = $db->lastInsertId();
        }
        $invoice_no = generateInvoiceNumber($sale_date);

        // Create sale record
        $stmt = $db->prepare("
            INSERT INTO sales (invoice_no,vehicle_id, customer_id, sale_price, sale_date, payment_method, sales_person, notes,
                             service_fee, deposit_received, delivery_charges, part_exchange_allowance, finance_amount,
                             balance_due, is_sold_as_seen, hpi_clear_status, includes_part_exchange)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_no,
            $vehicle_id,
            $customer_id,
            $sale_price,
            $sale_date,
            $payment_method,
            $_SESSION['user_id'],
            $notes,
            $service_fee,
            $deposit_received,
            $delivery_charges,
            $part_exchange_allowance,
            $finance_amount,
            $balance_due,
            $is_sold_as_seen,
            $hpi_clear_status,
            $includes_part_exchange
        ]);
        
        // Update vehicle status to 'sold'
        $stmt = $db->prepare("UPDATE vehicles SET status = 'sold' WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        
        $db->commit();
        
        $_SESSION['success'] = "Vehicle sold successfully!" . ($includes_part_exchange ? " Part-exchange vehicle added to inventory." : "");
        header("Location: sale-invoice.php?inv=$invoice_no");
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error processing sale: " . $e->getMessage();
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
      <!-- <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?> -->
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-6">

            <br>

            <div class="row flex-between-center mb-4 g-3">
              <form method="post">
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
                  <h2>Mark Vehicle as Sold: <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?> (<?= $vehicle['year'] ?>)</h2>
                  <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 col-xxl-8">
                      <div class="card mb-4">
                        <div class="card-header">
                          <h5>Vehicle Information</h5>
                        </div>
                        <div class="card-body">
                          <div class="row">
                            <div class="col-md-6">
                              <p><strong>Make/Model:</strong> <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?></p>
                              <p><strong>Year:</strong> <?= $vehicle['year'] ?></p>
                              <p><strong>Chasis#/VIN:</strong> <?= htmlspecialchars($vehicle['VIN']) ?: 'N/A' ?></p>
                            </div>
                            <div class="col-md-6">
                              <!-- <p><strong>Purchase Price:</strong> $<?= number_format($vehicle['purchase_price'], 2) ?></p> -->
                              <p><strong>Reg Number:</strong> <?= $vehicle['regnumber'] ?></p>
                              <p><strong>Color:</strong> <?= htmlspecialchars($vehicle['color']) ?: 'N/A' ?></p>
                              <p><strong>Mileage:</strong> <?= htmlspecialchars($vehicle['mileage']) ?: 'N/A' ?></p>
                            </div>
                            <div class="col-md-6">
                                <?= htmlspecialchars($vehicle['notes']) ?>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="card mb-4">
                        <div class="card-header">
                          <h5>Customer Information</h5>
                        </div>
                        <div class="card-body">
                          <!-- Customer Type Selection -->
                          <div class="row mb-3">
                            <div class="col-12">
                              <label class="form-label">Customer Type*</label>
                              <div>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="customer_type"
                                    id="existing_customer" value="existing" checked>
                                  <label class="form-check-label" for="existing_customer">
                                    Existing Customer
                                  </label>
                                </div>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="customer_type"
                                    id="new_customer" value="new">
                                  <label class="form-check-label" for="new_customer">
                                    New Customer
                                  </label>
                                </div>
                              </div>
                            </div>
                          </div>
                          <!-- Existing Customer Section -->
                          <div id="existingCustomerSection">
                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="existing_customer_id" class="form-label">Select Customer*</label>
                                <select class="form-select" id="existing_customer_id" name="existing_customer_id">
                                  <option value="">-- Select Customer --</option>
                                  <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['customer_id'] ?>">
                                      <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' - ' . $customer['phone']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                            </div>
                          </div>

                          <!-- New Customer Section -->
                          <div id="newCustomerSection" style="display: none;">
                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name">
                              </div>
                              <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name">
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="phone" class="form-label">Phone*</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                              </div>
                              <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                              </div>
                              <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city">
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="post_code" class="form-label">Post Code</label>
                                <input type="text" class="form-control" id="post_code" name="post_code">
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
                                value="<?= $vehicle['purchase_price'] * 1.2 ?>" required>
                            </div>
                            <div class="col-md-4">
                              <label for="sale_date" class="form-label">Sale Date*</label>
                              <input type="date" class="form-control" id="sale_date" name="sale_date"
                                value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                              <label for="payment_method" class="form-label">Payment Method*</label>
                              <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="finance">Finance</option>
                                <option value="check">Check</option>
                                <option value="other">Other</option>
                              </select>
                            </div>
                          </div>
                          <!-- Additional Financial Fields -->
                          <div class="row mb-3">
                            <div class="col-md-3">
                              <label for="service_fee" class="form-label">Service Fee (£)</label>
                              <input type="number" step="0.01" class="form-control" id="service_fee" name="service_fee" value="0">
                            </div>
                            <div class="col-md-3">
                              <label for="deposit_received" class="form-label">Deposit Received (£)</label>
                              <input type="number" step="0.01" class="form-control" id="deposit_received" name="deposit_received" value="0">
                            </div>
                            <div class="col-md-3">
                              <label for="delivery_charges" class="form-label">Delivery Charges (£)</label>
                              <input type="number" step="0.01" class="form-control" id="delivery_charges" name="delivery_charges" value="0">
                            </div>
                            <div class="col-md-3">
                              <label for="finance_amount" class="form-label">Finance Amount (£)</label>
                              <input type="number" step="0.01" class="form-control" id="finance_amount" name="finance_amount" value="0">
                            </div>
                          </div>

                          <!-- Part Exchange Toggle -->
                          <div class="row mb-3">
                            <div class="col-12">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="includes_part_exchange"
                                  id="includes_part_exchange" value="1">
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
                                <input type="text" class="form-control" id="px_make" name="px_make">
                              </div>
                              <div class="col-md-3">
                                <label for="px_model" class="form-label">Model*</label>
                                <input type="text" class="form-control" id="px_model" name="px_model">
                              </div>
                              <div class="col-md-3">
                                <label for="px_regnumber" class="form-label">Registration*</label>
                                <input type="text" class="form-control" id="px_regnumber" name="px_regnumber">
                              </div>
                              <div class="col-md-3">
                                <label for="px_mileage" class="form-label">Mileage</label>
                                <input type="number" class="form-control" id="px_mileage" name="px_mileage" value="0">
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-3">
                                <label for="px_color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="px_color" name="px_color">
                              </div>
                              <div class="col-md-3">
                                <label for="part_exchange_allowance" class="form-label">Allowance Given (£)*</label>
                                <input type="number" step="0.01" class="form-control" id="part_exchange_allowance" name="part_exchange_allowance" value="0">
                              </div>
                              <div class="col-md-6">
                                <label for="px_notes" class="form-label">Condition Notes</label>
                                <textarea class="form-control" id="px_notes" name="px_notes" rows="2"></textarea>
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
                                <option value="not_checked">Not Checked</option>
                                <option value="clear">HPI Clear</option>
                                <option value="finance_owed">Finance Owed</option>
                              </select>
                            </div>
                            <div class="form-group">
                              <label for="notes">Notes</label>
                              <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                          </div>


                        </div>
                      </div>
                    </div>

                    <div class="col-12 col-md-12 col-lg-4 col-xxl-4">
                      <!-- Financial Summary -->
<style>.financial-summary {
    position: sticky;
    top: 75px;
    background: white;
    
}</style>
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
                                  <tr class="table-active">
                                    <td><strong>Total Deductions:</strong></td>
                                    <td><strong>£<span id="displayTotalDeductions">0.00</span></strong></td>
                                  </tr>
                                  <tr class="table-success">
                                    <td><strong>Balance Due:</strong></td>
                                    <td><strong>£<span id="displayBalanceDue">0.00</span></strong></td>
                                  </tr>
                                  <tr class="table-info">
                                    <td><strong>Total Price Received:</strong></td>
                                    <td><strong>£<span id="displayTotalReceived">0.00</span></strong></td>
                                  </tr>
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
                    <button type="submit" class="btn btn-success">Mark as Sold</button>
                    <a href="view.php?id=<?= $vehicleId ?>" class="btn btn-secondary">Cancel</a>
                  </div>
                </div>
              </form>

            </div>
            <!-- <script>
              // Show new customer form if checkbox is checked
              document.getElementById('add_new_customer').addEventListener('change', function() {
                if (this.checked) {
                  window.location.href = 'add-customer.php?return_to=<?= urlencode("sale-vehicle.php?id=$vehicleId") ?>';
                }
              });
            </script> -->


            </form>
          </div>


        </div>
      </div>

    </div>
    </div>


    </div>

    <!-- Footer -->
    <!-- <footer class="footer position-absolute">
      <div class="row g-0 justify-content-between align-items-center h-100">
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 mt-2 mt-sm-0 text-body">Thank you for creating with Saqib Abbas<span class="d-none d-sm-inline-block"></span><span class="d-none d-sm-inline-block mx-1">|</span><br class="d-sm-none" />2025 &copy;<a class="mx-1" href="https://swiftcodershub.com">Swift Coders</a></p>
        </div>
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 text-body-tertiary text-opacity-85">v1.20.1</p>
        </div>
      </div>
    </footer> -->
    </div>

    <!-- Chat Now -->

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

  <!-- ===============================================-->
  <!--    JavaScripts-->
  <!-- ===============================================-->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Customer type toggle
      const existingCustomerRadio = document.getElementById('existing_customer');
      const newCustomerRadio = document.getElementById('new_customer');
      const existingCustomerSection = document.getElementById('existingCustomerSection');
      const newCustomerSection = document.getElementById('newCustomerSection');
      const existingCustomerSelect = document.getElementById('existing_customer_id');

      // Part exchange toggle
      const partExchangeCheckbox = document.getElementById('includes_part_exchange');
      const partExchangeSection = document.getElementById('partExchangeSection');

      // Financial fields
      const salePrice = document.getElementById('sale_price');
      const serviceFee = document.getElementById('service_fee');
      const deliveryCharges = document.getElementById('delivery_charges');
      const depositReceived = document.getElementById('deposit_received');
      const partExchangeAllowance = document.getElementById('part_exchange_allowance');
      const financeAmount = document.getElementById('finance_amount');
      const totalDueSpan = document.getElementById('totalDue');
      const balanceSpan = document.getElementById('balanceDisplay');

      function toggleCustomerSections() {
        if (existingCustomerRadio.checked) {
          existingCustomerSection.style.display = 'block';
          newCustomerSection.style.display = 'none';
          existingCustomerSelect.required = true;
          // Clear new customer fields
          document.getElementById('first_name').required = false;
          document.getElementById('last_name').required = false;
          document.getElementById('phone').required = false;
        } else {
          existingCustomerSection.style.display = 'none';
          newCustomerSection.style.display = 'block';
          existingCustomerSelect.required = false;
          // Set new customer fields as required
          document.getElementById('first_name').required = true;
          document.getElementById('last_name').required = true;
          document.getElementById('phone').required = true;
        }
      }

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

      function calculateBalance() {
        const salePriceVal = parseFloat(salePrice.value) || 0;
        const serviceFeeVal = parseFloat(serviceFee.value) || 0;
        const deliveryChargesVal = parseFloat(deliveryCharges.value) || 0;
        const depositReceivedVal = parseFloat(depositReceived.value) || 0;
        const partExchangeAllowanceVal = parseFloat(partExchangeAllowance.value) || 0;
        const financeAmountVal = parseFloat(financeAmount.value) || 0;

        // Calculations
        const subtotal = salePriceVal + serviceFeeVal + deliveryChargesVal;
        const totalDeductions = depositReceivedVal + partExchangeAllowanceVal + financeAmountVal;
        const balanceDue = subtotal - totalDeductions;
        const totalReceived = depositReceivedVal + financeAmountVal; // Cash received (deposit + finance)

        // Update displays
        document.getElementById('displaySoldPrice').textContent = salePriceVal.toFixed(2);
        document.getElementById('displayServiceFee').textContent = serviceFeeVal.toFixed(2);
        document.getElementById('displayDeliveryCharges').textContent = deliveryChargesVal.toFixed(2);
        document.getElementById('displaySubtotal').textContent = subtotal.toFixed(2);

        document.getElementById('displayDepositReceived').textContent = depositReceivedVal.toFixed(2);
        document.getElementById('displayPXAllowance').textContent = partExchangeAllowanceVal.toFixed(2);
        document.getElementById('displayFinanceAmount').textContent = financeAmountVal.toFixed(2);
        document.getElementById('displayTotalDeductions').textContent = totalDeductions.toFixed(2);

        document.getElementById('displayBalanceDue').textContent = balanceDue.toFixed(2);
        document.getElementById('displayTotalReceived').textContent = totalReceived.toFixed(2);
      }


      // Add event listeners
      existingCustomerRadio.addEventListener('change', toggleCustomerSections);
      newCustomerRadio.addEventListener('change', toggleCustomerSections);
      partExchangeCheckbox.addEventListener('change', togglePartExchangeSection);

      // Financial field listeners
      [salePrice, serviceFee, deliveryCharges, depositReceived, partExchangeAllowance, financeAmount].forEach(field => {
        field.addEventListener('input', calculateBalance);
      });

      // Initialize on page load
      toggleCustomerSections();
      togglePartExchangeSection();
      calculateBalance();
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