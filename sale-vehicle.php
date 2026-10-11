<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Sale Vehicle";

$vehicle_id = $_GET['id'] ?? 0;
$success = $error = '';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

$financeCompanies = [];

$stmt = $db->query("
    SELECT *
    FROM finance_companies
    WHERE status = 1
    ORDER BY company_name
");

$financeCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
function generateInvoiceNumber($sale_date)
{
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
  //    echo "<pre>POST Data: ";
  //    print_r($_POST);
  //    echo "</pre>";

  // Check if we're reaching the database code
  // echo "Reached POST processing...<br>";

  try {
        $db->beginTransaction();
    // echo "Transaction started...<br>";

    // Basic sale data
    //$vehicle_id = $_POST['vehicle_id'];
    $sale_price = $_POST['sale_price'];
    $sale_date = $_POST['sale_date'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'] ?? '';
    $px_notes = $_POST['px_notes'] ?? '';
    //    $customer_type = $_POST['customer_type'];
    $service_fee = $_POST['service_fee'] ?? 0;
    $deposit_received = $_POST['deposit_received'] ?? 0;
    $delivery_charges = $_POST['delivery_charges'] ?? 0;
    $additional_services = $_POST['additional_services'] ?? 0;
    $finance_amount = $_POST['finance_amount'] ?? 0;
    $discount_amount = $_POST['discount_amount'] ?? 0;
    $is_sold_as_seen = isset($_POST['is_sold_as_seen']) ? 1 : 0;
    $hpi_clear_status = $_POST['hpi_clear_status'] ?? 'not_checked';
    $includes_part_exchange = isset($_POST['includes_part_exchange']) ? 1 : 0;
    $includes_finance = isset($_POST['includes_finance']) ? 1 : 0;
    $part_exchange_allowance = $_POST['part_exchange_allowance'] ?? 0;

    $total_revenue = $sale_price + $service_fee + $delivery_charges + $additional_services - $discount_amount;
    $balance_due = $total_revenue - ($part_exchange_allowance + $deposit_received);
    // Calculate balance due
    $stmt = $db->prepare("
                          SELECT 
                              v.purchase_price,
                              COALESCE(SUM(jc.total_cost), 0) as job_card_costs,
                              COALESCE(SUM(r.cost), 0) as repair_costs,
                              COALESCE(SUM(e.amount), 0) as expense_costs
                          FROM vehicles v
                          LEFT JOIN job_cards jc ON v.vehicle_id = jc.vehicle_id
                          LEFT JOIN repairs r ON v.vehicle_id = r.vehicle_id  
                          LEFT JOIN expenses e ON v.vehicle_id = e.vehicle_id
                          WHERE v.vehicle_id = ?
                          ");
    $stmt->execute([$vehicle_id]);
    $costData = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_cost = $costData['purchase_price']
      + $costData['job_card_costs']
      + $costData['repair_costs']
      + $costData['expense_costs'];

    $net_profit = $total_revenue - $total_cost;
    // echo($total_revenue ."=". $sale_price. "+" .$service_fee. "+" .$delivery_charges. "-" .$discount_amount."<br>");
    // echo($total_cost."=".$costData['purchase_price']."+".$costData['job_card_costs']."+".$costData['repair_costs']."+".$costData['expense_costs']);
    // echo("<br>Net Profit =".$net_profit);
    // die;
    // Validate new customer data
    $title = trim($_POST['title'] ?? '');
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $post_code = trim($_POST['post_code'] ?? '');
    $county = trim($_POST['county'] ?? '');

    if (empty($title) || empty($first_name) || empty($last_name) || empty($phone)) {
      throw new Exception("Title, first name, last name, and phone are required for new customers.");
    }

    // Insert new customer
    $stmt = $db->prepare("
                    INSERT INTO customers (title, first_name, last_name, email, phone, address, city, state, post_code, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
    $stmt->execute([
      $title,
      $first_name,
      $last_name,
      $email,
      $phone,
      $address,
      $city,
      $county,
      $post_code,
      $_SESSION['user_id']
    ]);
    $customer_id = $db->lastInsertId();


    //    echo ($customer_id);
    //    die;
    // Handle Finance vehicle if included
    //$part_exchange_vehicle_id = null;

    // Handle part-exchange vehicle if included
    //*************************************** */
    $part_exchange_vehicle_id = null;
    if ($includes_part_exchange) {

      $px_make = trim($_POST['px_make']);
      $px_model = trim($_POST['px_model']);
      $px_regnumber = trim($_POST['px_regnumber']);
      $px_mileage = $_POST['px_mileage'] ?? 0;
      $px_engine_no = $_POST['px_engine_no'] ?? 0;
      $px_vin = trim($_POST['px_vin'] ?? '');
      $px_color = trim($_POST['px_color'] ?? '');
      $px_notes = trim($_POST['px_notes'] ?? '');
      //echo("PX Vehicle:".$px_make." - ".$px_model." - ".$px_regnumber." - ".$px_mileage." - ".$px_color." - ".$px_notes." - ".$_SESSION['user_id']." \n ");
      //var_dump($px_regnumber, $px_make, $px_model, $px_mileage, $px_color, $part_exchange_allowance, $_SESSION['user_id'], $px_notes);
      //echo "Connected DB: " . $db->query("SELECT DATABASE()")->fetchColumn();
      if (empty($px_make) || empty($px_model) || empty($px_regnumber)) {
        throw new Exception("Part-exchange vehicle make, model, and registration are required.");
      }

      // Create part-exchange vehicle
      try {
        $stmt = $db->prepare("
        INSERT INTO vehicles (regnumber, make, model, mileage, color, vin, engine_no, status, purchase_date, purchase_price, created_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'part_exchange', CURDATE(), ?, ?, ?)
    ");

        $result = $stmt->execute([
          $px_regnumber,
          $px_make,
          $px_model,
          $px_mileage,
          $px_color,
          $px_vin,
          $px_engine_no,
          $part_exchange_allowance,
          $_SESSION['user_id'],
          $px_notes
        ]);

        // if($result) {
        //     echo "<div class='alert alert-success'>Part-exchange vehicle added successfully!</div>";
        //     $newVehicleId = $db->lastInsertId();
        //     echo "<script>console.log('New Vehicle ID: $newVehicleId');</script>";
        // } else {
        //     $error = $stmt->errorInfo();
        //     echo "<div class='alert alert-danger'>";
        //     echo "<strong>Insert Failed:</strong> " . $error[2];
        //     echo "<br>Placeholders: 11, Values provided: " . count([
        //         $px_regnumber, $px_make, $px_model, $px_mileage, $px_color, 
        //         $px_vin, $px_engine_no, $part_exchange_allowance, 
        //         $_SESSION['user_id'], $px_notes
        //     ]);
        //     echo "</div>";
        // }

      } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Database Error:</strong> " . $e->getMessage();
        echo "<br>Error Code: " . $e->getCode();
        echo "</div>";
      }
      $part_exchange_vehicle_id = $db->lastInsertId();
      //       echo ("PX Vehicle ID:" . $part_exchange_vehicle_id . "\n");
    }

    $invoice_no = generateInvoiceNumber($sale_date);
    // if($includes_finance) {
    //   $finance_company_id = $_POST['finance_company_name'];
    //   $stmt = $db->prepare("SELECT * FROM finance_companies WHERE finance_company_id = ?");
    //   $stmt->execute([$finance_company_id]);
    //   $financeCompany = $stmt->fetch(PDO::FETCH_ASSOC);
    // } else{
    //   $finance_company_id = null;
    // }
    $finance_company_id = !empty($_POST['finance_company_name']) ? $_POST['finance_company_name'] : NULL;
    // $finance_company_name = $_POST['finance_company_name'];
    // $finance_company_address = $_POST['finance_company_address'];
    // $finance_company_email = $_POST['finance_company_email'];
    // $finance_company_phone = $_POST['finance_company_phone'];
    // Create sale record

    //echo "Values: $invoice_no, $vehicle_id, $customer_id, $sale_price, $sale_date, $payment_method, {user_id}, $notes, $px_notes, $service_fee, $deposit_received, $delivery_charges, $part_exchange_vehicle_id, $part_exchange_allowance, $finance_amount, $discount_amount, $balance_due, $is_sold_as_seen, $hpi_clear_status, $includes_part_exchange<br>";

    $stmt = $db->prepare("
            INSERT INTO sales (invoice_no,vehicle_id, customer_id, sale_price, sale_date, payment_method, sales_person, notes, px_notes,
                             service_fee, deposit_received, delivery_charges, additional_services, px_vehicle_id, part_exchange_allowance, 
                             finance_amount, discount_amount, balance_due, is_sold_as_seen, hpi_clear_status, includes_part_exchange, 
                             finance_company_id, total_revenue, net_profit, total_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)
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
      $px_notes,
      $service_fee,
      $deposit_received,
      $delivery_charges,
      $additional_services,
      $part_exchange_vehicle_id,
      $part_exchange_allowance,
      $finance_amount,
      $discount_amount,
      $balance_due,
      $is_sold_as_seen,
      $hpi_clear_status,
      $includes_part_exchange,
      $finance_company_id,
      $total_revenue,
      $net_profit,
      $total_cost
    ]);

    $sale_id = $db->lastInsertId();
    //    echo ("Sale ID:" . $sale_id . "\n");

    // --------------------------------------------------
    // CREATE SALES COMMISSION
    // --------------------------------------------------
    $sales_person = $_SESSION['user_id']; // Assuming the salesperson is the logged-in user
    if (!empty($sales_person) && !empty($sale_id)) {

      // Get salesperson's current commission rate
      $commissionQuery = $db->prepare("
        SELECT commission_rate
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

      $commissionQuery->execute([$sales_person]);

      $userCommission = $commissionQuery->fetch(PDO::FETCH_ASSOC);

      $commissionRate = (float)($userCommission['commission_rate'] ?? 0);

      // For now: commission = fixed amount per car sold
      $commissionAmount = $commissionRate;

      // Create commission record
      $commissionInsert = $db->prepare("
        INSERT INTO sales_commissions (
            sale_id,
            user_id,
            commission_rate,
            commission_amount,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

      $commissionInsert->execute([
        $sale_id,
        $sales_person,
        $commissionRate,
        $commissionAmount
      ]);
    }

  
    
    $current_date = date('Y-m-d');
    $user_id = $_SESSION['user_id'];


    // Calculate total amount customer needs to pay (after part exchange)
    //$total_amount_due = $sale_price + $service_fee + $delivery_charges  - $part_exchange_allowance - $discount_amount;

    // Generate unique reference for this sale
    $base_reference = $invoice_no;

    // s1. Handle Deposit Payment (if any)
    if ($deposit_received > 0) {
      $depositQuery = $db->prepare("
        INSERT INTO sale_payments 
        (sale_id, invoice_no, transaction_type, transaction_date, amount, payment_method, 
         reference_number, status, notes, processed_by, created_at) 
        VALUES (?, ?, 'payment', ?, ?, 'cash', ?, 'completed', ?, ?, NOW())
    ");

      $depositReference = $base_reference . '-DEP';
      $depositQuery->execute([
        $sale_id,
        $invoice_no,
        $sale_date,
        $deposit_received,
        $depositReference,
        'Deposit payment from customer',
        $user_id
      ]);
    }

    // 2. Handle Finance Payment (from finance company)
    // if ($finance_amount > 0 && $payment_method === 'finance') {

    //   $financePaymentQuery = $db->prepare("
    //     INSERT INTO sale_payments 
    //     (sale_id, invoice_no,  transaction_type, transaction_date, amount, payment_method, 
    //      reference_number, status, notes, processed_by, created_at) 
    //     VALUES (?, ?, 'payment', ?, ?, 'finance', ?, 'pending', ?, ?, NOW())
    // ");

    //   $financeReference = $base_reference . '-FINANCE';
    //   $financePaymentQuery->execute([
    //     $sale_id,
    //     $invoice_no,
    //     $sale_date,
    //     $finance_amount,  // Only the finance amount (£13,000)
    //     $financeReference,
    //     'Finance payment from finance company',
    //     $user_id
    //   ]);

    //   // Also update sales status to pending
    //     $updateStatusQuery = $db->prepare("UPDATE sales SET status = 'pending' WHERE sale_id = ?");
    //     $updateStatusQuery->execute([$sale_id]);
    // }

    // 3. Handle Customer Balance Payment (what customer still owes)
    //$customer_balance = $total_amount_due - $deposit_received - $finance_amount;

    if($balance_due > 0):
      $vehicle_status = 'reserved';
      $sale_status = 'pending';
    else:
      $vehicle_status = 'sold';
      $sale_status = 'completed';
    endif;

      //   // Also update sales status to pending
      $updateStatusQuery = $db->prepare("UPDATE sales SET status = ? WHERE sale_id = ?");
      $updateStatusQuery->execute([$sale_status,$sale_id]);

      // Update vehicle status and sale date
      $stmt = $db->prepare("UPDATE vehicles SET status = ?, sale_date = ? WHERE vehicle_id = ?");
      $stmt->execute([$vehicle_status, $sale_date, $vehicle_id]);


        $db->commit();
    setSuccess("Sale completed successfully! Payment records created.");
    header("Location: sale-invoice.php?inv=$invoice_no");
    exit;
  } catch (Exception $e) {
      $db->rollBack();
    //echo "SALES INSERT FAILED: " . $e->getMessage() . "<br>";
    //echo "Error Info: " . print_r($stmt->errorInfo(), true) . "<br>";
    die;
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
      //linkRTL.setAttribute('disabled', true);
      //userLinkRTL.setAttribute('disabled', true);
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
                              <!-- <?= htmlspecialchars($vehicle['notes']) ?> -->
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="card mb-4">
                        <div class="card-header">
                          <h5>Customer Information</h5>
                        </div>
                        <div class="card-body">
                          <!-- New Customer Section -->
                          <div id="newCustomerSection">
                            <div class="row mb-3">


                            </div>
                            <div class="row mb-3">
                              <div class="col-md-2">
                                <label for="title" class="form-label">Title*</label>
                                <select class="form-select" id="title" name="title" required>
                                  <option value="">Select Title</option>
                                  <option value="Mr">Mr</option>
                                  <option value="Mrs">Mrs</option>
                                  <option value="Miss">Miss</option>
                                  <option value="Ms">Ms</option>
                                  <option value="Dr">Dr</option>
                                  <option value="Prof">Prof</option>
                                </select>
                              </div>
                              <div class="col-md-5">
                                <label for="first_name" class="form-label">First Name*</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                              </div>
                              <div class="col-md-5">
                                <label for="last_name" class="form-label">Last Name*</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="phone" class="form-label">Phone*</label>
                                <input type="text" class="form-control" id="phone" name="phone" required>
                              </div>
                              <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-12">
                                <style>
                                  #context {
                                    display: block;
                                    align-items: center;
                                    gap: 8px;
                                  }

                                  #context .form-control {
                                    flex: 1;
                                    width: 100%;
                                    margin-bottom: 10px;
                                  }

                                  #context .btn {
                                    flex-shrink: 0;
                                    white-space: nowrap;
                                  }

                                  #context p .idpc-error {
                                    display: block !important;
                                    flex-basis: 100% !important;
                                    width: 100% !important;
                                    margin-top: 10px !important;
                                  }
                                </style>

                                <label>Search your Address</label>
                                <div id="context"></div>
                              </div>
                            </div>
                            <div class="row mb-3">
                              <div class="col-md-6">
                                  <label for="address" class="form-label">Address</label>
                                  <input type="text" class="form-control" id="line_1" name="address">
                                </div>
                                <div class="col-md-6">
                                  <label for="city" class="form-label">City</label>
                                  <input type="text" class="form-control" id="post_town" name="city">
                                </div>
                              <div class="col-md-6">
                                <label for="county" class="form-label">County / Estate</label>
                                <input type="text" class="form-control" id="county" name="county">
                              </div>
                              <div class="col-md-6">
                                <label for="post_code" class="form-label">Post Code</label>
                                <input type="text" class="form-control" id="postcode" name="post_code">
                              </div>
                              
                            </div>


                            <script src="https://cdn.jsdelivr.net/npm/@ideal-postcodes/postcode-lookup-bundled@3"></script>
                            <script>
                              IdealPostcodes.PostcodeLookup.setup({
                                // apiKey: "ak_mt79fl3id0CnOGWP3Y5IrSG1Jh8Gy",
                                apiKey: "ak_test",
                                context: "#context",
                                inputClass: "form-control",
                                buttonClass: "btn btn-primary",
                                selectClass: "form-select mt-3",
                                messageClass: "alert alert-subtle-danger fade show mt-2",

                                outputFields: {
                                  line_1: "#line_1",
                                  line_2: "#line_2",
                                  line_3: "#line_3",
                                  post_town: "#post_town",
                                  county: "#county",
                                  postcode: "#postcode",
                               },
                              });
                            </script>

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
                                value="" required>
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
                                <option value="card_payment">Card Payment</option>
                                <option value="other">Other</option>
                              </select>
                            </div>
                          </div>
                          <!-- Additional Financial Fields -->
                          <div class="row mb-3">
                            <div class="col-md-3">
                              <label for="service_fee" class="form-label">Service Fee (£)</label>
                              <input type="number" step="0.01" class="form-control" id="service_fee" name="service_fee" value="95">
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
                              <label for="discount_amount" class="form-label">Discount (£)</label>
                              <input type="number" step="0.01" class="form-control" id="discount_amount" name="discount_amount" value="0">
                            </div>
                            <div class="col-md-3">
                              <label for="additional_services" class="form-label">Additional Services (£)</label>
                              <input type="number" step="0.01" class="form-control" id="additional_services" name="additional_services" value="0">
                            </div>
                          </div>

                          <!-- Finance Toggle -->

                          <div id="financeSection" style="display: none;" class="finance-section">
                            <h6 class="mb-3">Finance Section</h6>
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
                                      data-email="<?= htmlspecialchars($company['email']) ?>">
                                      <?= htmlspecialchars($company['company_name']) ?>
                                    </option>

                                  <?php endforeach; ?>

                                </select>
                              </div>
                              <div class="col-md-6">
                                <label for="finance_amount" class="form-label">Finance Amount (£)</label>
                                <input type="number" step="0.01" class="form-control" id="finance_amount" name="finance_amount" value="0" required>
                              </div>
                            </div>

                            <div class="row mb-3">
                              <div class="col-md-6">
                                <label for="finance_company_address" class="form-label">Company Address</label>
                                <textarea class="form-control" id="finance_company_address" name="finance_company_address" rows="2" readonly></textarea>
                              </div>
                              <div class="col-md-6">
                                <div class="row">
                                  <div class="col-md-6">
                                    <label for="finance_company_phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="finance_company_phone" name="finance_company_phone" value="" readonly>
                                  </div>
                                  <div class="col-md-6">
                                    <label for="finance_company_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="finance_company_email" name="finance_company_email" value="" readonly>
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
                                <input type="text" class="form-control" id="px_make" name="px_make" required>
                              </div>
                              <div class="col-md-3">
                                <label for="px_model" class="form-label">Model*</label>
                                <input type="text" class="form-control" id="px_model" name="px_model" required>
                              </div>
                              <div class="col-md-3">
                                <label for="px_regnumber" class="form-label">Registration*</label>
                                <input type="text" class="form-control" id="px_regnumber" name="px_regnumber" required>
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
                                <label for="px_engine_no" class="form-label">Engine No</label>
                                <input type="text" class="form-control" id="px_engine_no" name="px_engine_no" value="">
                              </div>
                              <div class="col-md-3">
                                <label for="px_vin" class="form-label">VIN</label>
                                <input type="text" class="form-control" id="px_vin" name="px_vin" value="">
                              </div>
                              <div class="col-md-3">
                                <label for="part_exchange_allowance" class="form-label">Allowance Given (£)*</label>
                                <input type="number" step="0.01" class="form-control" id="part_exchange_allowance" name="part_exchange_allowance" value="0" required>
                              </div>

                              <div class="col-md-12">
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
                                <option value="Clear">HPI Clear</option>
                                <option value="Declared as Cat-S">Declared Cat-S</option>
                                <option value="Declared as Cat-N">Declared Cat-N</option>
                                <option value="Declared as Cat-U">Declared Cat-U</option>
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
                                    <td><strong>Finance Amount (Due) :</strong></td>
                                    <td>£<span id="displayFinanceAmount">0.00</span></td>
                                  </tr>
                                  <!-- <tr class="table-info">
                                    <td><strong>Total Amount Received:</strong></td>
                                    <td><strong>£<span id="displayTotalReceived">0.00</span></strong></td>
                                  </tr> -->

                                  <!-- <tr class="table-active">
                                    <td><strong>Total Deductions:</strong></td>
                                    <td><strong>£<span id="displayTotalDeductions">0.00</span></strong></td>
                                  </tr> -->

                                  <tr class="table-success">
                                    <td><strong>Balance Due:</strong></td>
                                    <td><strong>£<span id="displayBalanceDue">0.00</span></strong></td>
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
    <?php include_once("footer.php"); ?>
    </div>

    <!-- Chat Now -->

  </main><!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->

  <!-- ===============================================-->
  <!--    JavaScripts-->
  <!-- ===============================================-->
  <script>
    // Define functions FIRST, outside DOMContentLoaded

    function togglePartExchangeSection() {
      const partExchangeCheckbox = document.getElementById('includes_part_exchange');
      const partExchangeSection = document.getElementById('partExchangeSection');

      if (!partExchangeCheckbox || !partExchangeSection) return;

      const pxFields = ['px_make', 'px_model', 'px_regnumber', 'part_exchange_allowance'];

      if (partExchangeCheckbox.checked) {
        partExchangeSection.style.display = 'block';
        // Add required only when visible
        pxFields.forEach(field => {
          const element = document.getElementById(field);
          if (element) element.setAttribute('required', 'required');
        });
      } else {
        partExchangeSection.style.display = 'none';
        // Remove required when hidden
        pxFields.forEach(field => {
          const element = document.getElementById(field);
          if (element) {
            element.removeAttribute('required');

          }
        });
      }
      calculateBalance();
    }

    function toggleFinanceSection() {
      const paymentMethod = document.getElementById('payment_method');
      const financeSection = document.getElementById('financeSection');
      const financeAmountField = document.getElementById('finance_amount');
      const financeCompanyName = document.getElementById('finance_company_name');

      if (!paymentMethod || !financeSection || !financeAmountField || !financeCompanyName) return;

      const paymentMethodValue = paymentMethod.value;

      if (paymentMethodValue === 'finance') {
        financeSection.style.display = 'block';
        // Add required only when visible
        financeCompanyName.setAttribute('required', 'required');
        financeAmountField.setAttribute('required', 'required');
      } else {
        financeSection.style.display = 'none';
        // Remove required when hidden
        financeCompanyName.removeAttribute('required');
        financeAmountField.removeAttribute('required');
        financeAmountField.value = 0;
        finance_company_name.value = "";
        finance_company_address.value = "";
        finance_company_email.value = "";
        finance_company_phone.value = "";
      }
      calculateBalance();
    }

    function calculateBalance() {
      const salePrice = document.getElementById('sale_price');
      const serviceFee = document.getElementById('service_fee');
      const deliveryCharges = document.getElementById('delivery_charges');
      const depositReceived = document.getElementById('deposit_received');
      const discountAmount = document.getElementById('discount_amount');
      const paymentMethod = document.getElementById('payment_method');
      const financeAmountField = document.getElementById('finance_amount');
      const additionalServices = document.getElementById('additional_services');

      if (!salePrice || !serviceFee || !deliveryCharges || !depositReceived || !discountAmount || !paymentMethod) return;

      const salePriceVal = parseFloat(salePrice.value) || 0;
      const serviceFeeVal = parseFloat(serviceFee.value) || 0;
      const deliveryChargesVal = parseFloat(deliveryCharges.value) || 0;
      const depositReceivedVal = parseFloat(depositReceived.value) || 0;
      const discountAmountVal = parseFloat(discountAmount.value) || 0;
      const additionalServicesVal = parseFloat(additionalServices.value) || 0;

      const paymentMethodValue = paymentMethod.value;
      let financeAmountVal = 0;

      if (paymentMethodValue === 'finance' && financeAmountField) {
        financeAmountVal = parseFloat(financeAmountField.value) || 0;
      }

      const partExchangeChecked = document.getElementById('includes_part_exchange') ?
        document.getElementById('includes_part_exchange').checked : false;
      const partExchangeAllowanceVal = partExchangeChecked ?
        (parseFloat(document.getElementById('part_exchange_allowance').value) || 0) : 0;

      const subtotal = salePriceVal + serviceFeeVal + deliveryChargesVal + additionalServicesVal - discountAmountVal;
      const totalDeductions = depositReceivedVal + partExchangeAllowanceVal;
      const balanceDue = (subtotal - totalDeductions);
      const totalReceived = depositReceivedVal + partExchangeAllowanceVal;

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
      // document.getElementById('displayTotalDeductions').textContent = totalDeductions.toFixed(2);

      document.getElementById('displayBalanceDue').textContent = balanceDue.toFixed(2);
      // document.getElementById('displayTotalReceived').textContent = totalReceived.toFixed(2);
    }

    // Now initialize everything inside DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
      // Get elements
      const partExchangeCheckbox = document.getElementById('includes_part_exchange');
      const paymentMethod = document.getElementById('payment_method');

      // Add event listeners

      if (partExchangeCheckbox) {
        partExchangeCheckbox.addEventListener('change', togglePartExchangeSection);
      }

      if (paymentMethod) {
        paymentMethod.addEventListener('change', toggleFinanceSection);
      }

      // Financial field listeners
      const financialFields = ['sale_price', 'service_fee', 'delivery_charges', 'additional_services', 'deposit_received', 'finance_amount', 'discount_amount', 'part_exchange_allowance'];
      financialFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          field.addEventListener('input', calculateBalance);
        }
      });

      // Initialize on page load
      togglePartExchangeSection();
      toggleFinanceSection();
      calculateBalance();
    });

    document.getElementById('finance_company_name').addEventListener('change', function() {

      const option = this.options[this.selectedIndex];

      document.getElementById('finance_company_address').value =
        option.dataset.address || '';

      document.getElementById('finance_company_phone').value =
        option.dataset.phone || '';

      document.getElementById('finance_company_email').value =
        option.dataset.email || '';

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
      if (!document.getElementById('first_name').value ||
        !document.getElementById('last_name').value ||
        !document.getElementById('phone').value) {
        isValid = false;
        highlightField(document.getElementById('first_name'));
        highlightField(document.getElementById('last_name'));
        highlightField(document.getElementById('phone'));

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