<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "Edit Vehicle";
$user = $auth->getUser();

// Check permissions
if (!hasPermission('manage_vehicles')) {
    setError('Access denied');
    header('Location: access-denied.php');
    exit;
}

$vehicle_id = $_GET['id'] ?? 0;

// ==============================================
// PART 1: FORM SUBMISSION HANDLER (POST)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate required fields
    if (empty($_POST['regnumber'])) $errors['regnumber'] = 'Registration is required';
    if (empty($_POST['make'])) $errors['make'] = 'Make is required';
    // if (empty($_POST['model'])) $errors['model'] = 'Model is required';
    // if (empty($_POST['year'])) $errors['year'] = 'Year is required';
    
    // Validate year format
    // if (!empty($_POST['year']) && (!is_numeric($_POST['year']) || $_POST['year'] < 1900 || $_POST['year'] > date('Y') + 1)) {
    //     $errors['year'] = 'Please enter a valid year';
    // }
    
    // Validate mileage
    // if (!empty($_POST['mileage']) && !is_numeric($_POST['mileage'])) {
    //     $errors['mileage'] = 'Mileage must be a number';
    // }
      $_POST = sanitizeFormData($_POST);
    // Validate purchase price
    if (!empty($_POST['purchase_price']) && !is_numeric($_POST['purchase_price'])) {
        $errors['purchase_price'] = 'Purchase price must be a number';
    }
    
    // If validation failed
    if (!empty($errors)) {
        setFormErrors($errors);
        saveFormData($_POST);
        setError('Please correct the errors below');
        header("Location: edit-vehicle.php?id=" . $vehicle_id);
        exit;
    }
    
    // Validation passed - update vehicle
    try {
        // Prepare data
        $data = [
            'regnumber' => trim($_POST['regnumber']),
            'make' => trim($_POST['make']),
            'model' => trim($_POST['model']),
            'year' => (int)$_POST['year'],
            'trim' => trim($_POST['trim'] ?? ''),
            'mileage' => (int)($_POST['mileage'] ?? 0),
            'color' => trim($_POST['color']),
            'body_style' => $_POST['body_style'] ?? '',
            'transmission' => $_POST['transmission'] ?? '',
            'fuel_type' => $_POST['fuel_type'] ?? '',
            'engine_size' => trim($_POST['engine_size'] ?? ''),
            'owners' => (int)($_POST['owners'] ?? 0),
            'v5_status' => $_POST['v5_status'] ?? 'No',
            'number_of_services' => (int)($_POST['number_of_services'] ?? 0),
            'keys_count' => (int)($_POST['keys_count'] ?? 0),
            'logbook_status' => $_POST['logbook_status'] ?? 'Missing',
            'mot_expiry' => !empty($_POST['mot_expiry']) ? $_POST['mot_expiry'] : null,
            'purchase_date' => $_POST['purchase_date'],
            'purchase_price' => (float)($_POST['purchase_price'] ?? 0),
            'auction_id' => !empty($_POST['auction_id']) ? $_POST['auction_id'] : null,
            'notes' => trim($_POST['notes'] ?? ''),
            'VIN' => trim($_POST['VIN'] ?? ''),
            'engine_no' => trim($_POST['engine_no'] ?? ''),
            'vehicle_id' => $vehicle_id
        ];
        
        // Update vehicle
        $stmt = $db->prepare("
            UPDATE vehicles SET 
                regnumber = ?, make = ?, model = ?, year = ?, trim = ?, mileage = ?, 
                color = ?, body_style = ?, transmission = ?, fuel_type = ?, engine_size = ?,
                owners = ?, v5_status = ?, number_of_services = ?, keys_count = ?, 
                logbook_status = ?, mot_expiry = ?, purchase_date = ?, purchase_price = ?,
                auction_id = ?, notes = ?, VIN = ?, engine_no = ?
            WHERE vehicle_id = ?
        ");
        
        $stmt->execute([
            $data['regnumber'], $data['make'], $data['model'], $data['year'], $data['trim'],
            $data['mileage'], $data['color'], $data['body_style'], $data['transmission'],
            $data['fuel_type'], $data['engine_size'], $data['owners'], $data['v5_status'],
            $data['number_of_services'], $data['keys_count'], $data['logbook_status'],
            $data['mot_expiry'], $data['purchase_date'], $data['purchase_price'],
            $data['auction_id'], $data['notes'], $data['VIN'], $data['engine_no'],
            $data['vehicle_id']
        ]);
        
        // Handle image uploads
        if (isset($_FILES['vehicle_images']) && !empty($_FILES['vehicle_images']['name'][0])) {
            $uploadDir = 'uploads/vehicles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['vehicle_images']['name'] as $key => $name) {
                if ($_FILES['vehicle_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['vehicle_images']['tmp_name'][$key];
                    $fileSize = $_FILES['vehicle_images']['size'][$key];
                    $fileType = $_FILES['vehicle_images']['type'][$key];
                    
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($fileType, $allowedTypes)) {
                        continue;
                    }
                    
                    // Validate file size (5MB max)
                    if ($fileSize > 5 * 1024 * 1024) {
                        continue;
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'vehicle_' . $vehicle_id . '_' . time() . '_' . $key . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($tmpName, $filepath)) {
                        $description = $_POST['image_descriptions'][$key] ?? '';
                        $isPrimary = 0; // Default not primary
                        
                        $stmt = $db->prepare("
                            INSERT INTO vehicle_images (vehicle_id, image_path, description, is_primary, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$vehicle_id, $filepath, $description, $isPrimary, $_SESSION['user_id']]);
                    }
                }
            }
        }
        
        // Handle primary image setting
        if (isset($_POST['primary_image']) && !empty($_POST['primary_image'])) {
            // Reset all images to not primary
            $stmt = $db->prepare("UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_id]);
            
            // Set selected image as primary
            $stmt = $db->prepare("UPDATE vehicle_images SET is_primary = 1 WHERE image_id = ? AND vehicle_id = ?");
            $stmt->execute([$_POST['primary_image'], $vehicle_id]);
        }
        
        // SUCCESS - set message and redirect
        setSuccess('Vehicle updated successfully!');
        header('Location: view-all-vehicles.php');
        exit;
        
    } catch (Exception $e) {
        // DATABASE ERROR - save form data and show error
        saveFormData($_POST);
        setError('Database error: ' . $e->getMessage());
        header("Location: edit-vehicle.php?id=" . $vehicle_id);
        exit;
    }
}

// ==============================================
// PART 2: PAGE LOAD (GET REQUEST)
// ==============================================

// Fetch vehicle details (for initial load)
try {
    $stmt = $db->prepare("
        SELECT v.*, a.auction_name, a.city 
        FROM vehicles v 
        LEFT JOIN auctions a ON v.auction_id = a.auction_id 
        WHERE v.vehicle_id = ?
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    saveFormData($vehicle);
    if (!$vehicle) {
        setError('Vehicle not found');
        header('Location: view-all-vehicles.php');
        exit;
    }
} catch (PDOException $e) {
    setError('Error loading vehicle: ' . $e->getMessage());
    header('Location: view-all-vehicles.php');
    exit;
}

// Fetch auctions for dropdown
$auctions = [];
try {
    $stmt = $db->query("SELECT auction_id, auction_name, city FROM auctions ORDER BY auction_name");
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Don't stop page load for this - just log it
    error_log('Error fetching auctions: ' . $e->getMessage());
}

// Fetch existing images
$images = [];
try {
    $stmt = $db->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, uploaded_at DESC");
    $stmt->execute([$vehicle_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Don't stop page load for this
    error_log('Error fetching images: ' . $e->getMessage());
}

// Check if we have form data from error redirect
$formData = $_SESSION['form_data'] ?? $vehicle;

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
    .error-text {
      color: #dc3545;
      font-size: 0.875em;
      margin-top: 0.25rem;
      display: block;
    }

    .is-invalid {
      border-color: #dc3545 !important;
    }

    .error-alert {
      margin-bottom: 1.5rem;
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
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="col-md-9 col-lg-12">
                            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                                <h1 class="h2">Edit Vehicle</h1>
                                <div class="btn-toolbar mb-2 mb-md-0">
                                    <a href="view-all-vehicles.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Vehicle
                                    </a>
                                </div>
                            </div>



                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Left Column - Vehicle Details -->
                                    <div class="col-lg-6">
                                        <div class="card mb-4">
                                            <div class="card-header p-3">
                                                <h5 class="mb-0">🚗 Vehicle Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="regnumber" class="form-label">Registration Number <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control <?php echo hasError('regnumber') ? 'is-invalid' : ''; ?>" id="regnumber" name="regnumber"
                                                            value="<?php echo getFormData('regnumber'); ?>" required>
                                                            <?php if (hasError('regnumber')): ?>
                                                                <div class="error-text"><?php echo getFormError('regnumber'); ?></div>
                                                            <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="VIN" class="form-label">Chasis / VIN</label>
                                                        <input type="text" class="form-control" id="VIN" name="VIN"
                                                            value="<?php echo getFormData('VIN'); ?>">
                                                    </div>
                                                    

                                                </div>

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="engine_no" class="form-label">Engine Number</label>
                                                        <input type="text" class="form-control" id="engine_no" name="engine_no"
                                                            value="<?php echo getFormData('engine_no'); ?>">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="make" class="form-label">Make <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="make" name="make"
                                                            value="<?php echo getFormData('make'); ?>" required>
                                                            <?php if (hasError('make')): ?>
                                                                <div class="error-text"><?php echo getFormError('make'); ?></div>
                                                            <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="model" class="form-label">Model</label>
                                                        <input type="text" class="form-control" id="model" name="model"
                                                            value="<?php echo getFormData('model'); ?>">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="year" class="form-label">Year </label>
                                                        <input type="number" class="form-control" id="year" name="year"
                                                            value="<?php echo getFormData('year'); ?>" max="<?= date('Y') + 1 ?>">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="trim" class="form-label">Trim</label>
                                                        <input type="text" class="form-control" id="trim" name="trim"
                                                            value="<?php echo getFormData('trim'); ?>">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="color" class="form-label">Color</label>
                                                        <input type="text" class="form-control" id="color" name="color"
                                                            value="<?php echo getFormData('color'); ?>">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="mileage" class="form-label">Mileage</label>
                                                        <input type="number" class="form-control" id="mileage" name="mileage"
                                                            value="<?php echo getFormData('mileage'); ?>">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="engine_size" class="form-label">Engine Size</label>
                                                        <input type="text" class="form-control" id="engine_size" name="engine_size"
                                                            value="<?php echo getFormData('engine_size'); ?>">
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="owners" class="form-label">Owners</label>
                                                        <input type="number" class="form-control" id="owners" name="owners"
                                                            value="<?php echo getFormData('owners'); ?>" min="0">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="body_style" class="form-label">Body Style</label>
                                                        <select class="form-select" id="body_style" name="body_style">
                                                            <option value="">Select...</option>
                                                            <option value="sedan" <?= $vehicle['body_style'] == 'sedan' ? 'selected' : '' ?>>Sedan</option>
                                                            <option value="coupe" <?= $vehicle['body_style'] == 'coupe' ? 'selected' : '' ?>>Coupe</option>
                                                            <option value="suv" <?= $vehicle['body_style'] == 'suv' ? 'selected' : '' ?>>SUV</option>
                                                            <option value="truck" <?= $vehicle['body_style'] == 'truck' ? 'selected' : '' ?>>Truck</option>
                                                            <option value="van" <?= $vehicle['body_style'] == 'van' ? 'selected' : '' ?>>Van</option>
                                                            <option value="hatchback" <?= $vehicle['body_style'] == 'hatchback' ? 'selected' : '' ?>>Hatchback</option>
                                                            <option value="other" <?= $vehicle['body_style'] == 'other' ? 'selected' : '' ?>>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="transmission" class="form-label">Transmission</label>
                                                        <select class="form-select" id="transmission" name="transmission">
                                                            <option value="">Select...</option>
                                                            <option value="automatic" <?= $vehicle['transmission'] == 'automatic' ? 'selected' : '' ?>>Automatic</option>
                                                            <option value="manual" <?= $vehicle['transmission'] == 'manual' ? 'selected' : '' ?>>Manual</option>
                                                            <option value="other" <?= $vehicle['transmission'] == 'other' ? 'selected' : '' ?>>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="fuel_type" class="form-label">Fuel Type</label>
                                                        <select class="form-select" id="fuel_type" name="fuel_type">
                                                            <option value="">Select...</option>
                                                            <option value="petrol" <?= $vehicle['fuel_type'] == 'petrol' ? 'selected' : '' ?>>Petrol</option>
                                                            <option value="diesel" <?= $vehicle['fuel_type'] == 'diesel' ? 'selected' : '' ?>>Diesel</option>
                                                            <option value="hybrid" <?= $vehicle['fuel_type'] == 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                                            <option value="electric" <?= $vehicle['fuel_type'] == 'electricity' ? 'selected' : '' ?>>Electricity</option>
                                                            <option value="other" <?= $vehicle['fuel_type'] == 'other' ? 'selected' : '' ?>>Other</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card mb-4">
                                            <div class="card-header p-3">
                                                <h5 class="mb-0">📄 Documentation</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label for="v5_status" class="form-label">V5 Status</label>
                                                        <select class="form-select" id="v5_status" name="v5_status">
                                                            <option value="No" <?= ($vehicle['v5_status'] ?? 'No') == 'No' ? 'selected' : '' ?>>No</option>
                                                            <option value="Yes" <?= ($vehicle['v5_status'] ?? 'No') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="logbook_status" class="form-label">Logbook Status</label>
                                                        <select class="form-select" id="logbook_status" name="logbook_status">
                                                            <option value="Missing" <?= ($vehicle['logbook_status'] ?? 'Missing') == 'Missing' ? 'selected' : '' ?>>Missing</option>
                                                            <option value="Present" <?= ($vehicle['logbook_status'] ?? 'Missing') == 'Present' ? 'selected' : '' ?>>Present</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label for="keys_count" class="form-label">Number of Keys</label>
                                                        <input type="number" class="form-control" id="keys_count" name="keys_count"
                                                            value="<?php echo getFormData('keys_count'); ?>" min="0">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="number_of_services" class="form-label">Number of Services</label>
                                                        <input type="number" class="form-control" id="number_of_services" name="number_of_services"
                                                            value="<?php echo getFormData('number_of_services'); ?>" min="0">
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="mot_expiry" class="form-label">MOT Expiry</label>
                                                        <input type="date" class="form-control" id="mot_expiry" name="mot_expiry"
                                                            value="<?= $vehicle['mot_expiry'] ? htmlspecialchars($vehicle['mot_expiry']) : '' ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column - Purchase & Images -->
                                    <div class="col-lg-6">
                                        <div class="card mb-4">
                                            <div class="card-header p-3">
                                                <h5 class="mb-0">💰 Purchase Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="purchase_date" name="purchase_date"
                                                            value="<?php echo getFormData('purchase_date'); ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label for="purchase_price" class="form-label">Purchase Price (£) <span class="text-danger">*</span></label>
                                                        <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price"
                                                            value="<?php echo getFormData('purchase_price'); ?>" required>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-12 mb-3">
                                                        <label for="auction_id" class="form-label">Auction Source</label>
                                                        <select class="form-select" id="auction_id" name="auction_id">
                                                            <option value="">Select Auction...</option>
                                                            <?php foreach ($auctions as $auction): ?>
                                                                <option value="<?= $auction['auction_id'] ?>"
                                                                    <?= $vehicle['auction_id'] == $auction['auction_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($auction['auction_name'] . " - " . $auction['city']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-12 mb-3">
                                                        <label for="notes" class="form-label">Notes</label>
                                                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo getFormData('notes'); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Current Images -->
                                        <div class="card mb-4">
                                            <div class="card-header p-3">
                                                <h5 class="mb-0">🖼️ Current Images</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($images)): ?>
                                                    <div class="alert alert-phoenix-info">No images uploaded yet.</div>
                                                <?php else: ?>
                                                    <div class="row image-preview-container">
                                                        <?php foreach ($images as $image): ?>
                                                            <div class="col-md-6 mb-3">
                                                                <div class="card current-image">

                                                                    <img src="<?= htmlspecialchars($image['image_path']) ?>"
                                                                        class="card-img-top"
                                                                        style="height: 120px; object-fit: cover;"
                                                                        alt="<?= htmlspecialchars($image['description'] ?? 'Vehicle image') ?>">
                                                                    <div class="card-body p-2">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="radio"
                                                                                name="primary_image"
                                                                                value="<?= $image['image_id'] ?>"
                                                                                <?= $image['is_primary'] ? 'checked' : '' ?>>
                                                                            <label class="form-check-label small">Set as Primary</label>
                                                                            <?php if ($image['is_primary']): ?>
                                                                                <span style="float: right;" class="primary-badge badge bg-success">Primary</span>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Upload New Images -->
                                        <div class="card">
                                            <div class="card-header p-3">
                                                <h5 class="mb-0">📤 Upload New Images</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-phoenix-info">
                                                    <small>You can upload multiple images. Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB per image.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="vehicle_images" class="form-label">Select Images</label>
                                                    <input type="file" class="form-control" id="vehicle_images"
                                                        name="vehicle_images[]" multiple
                                                        accept="image/jpeg,image/png,image/gif,image/webp">
                                                </div>

                                                <div id="imagePreviews" class="row mt-3"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <a href="view-vehicle.php?id=<?= $vehicle_id ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="row flex-between-center mb-4 g-3">



                        </div>
                    </div>

                </div>
            </div>


        </div>

        <!-- Footer -->
        <?php include_once("footer.php"); ?>

        </div>

        <!-- Chat Now -->

    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('vehicle_images');
            const imagePreviews = document.getElementById('imagePreviews');

            // Image preview functionality
            imageInput.addEventListener('change', function(e) {
                imagePreviews.innerHTML = '';
                const files = Array.from(e.target.files);

                files.forEach((file, index) => {
                    if (!file.type.match('image.*')) {
                        alert('Please select only image files.');
                        return;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size must be less than 5MB.');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const col = document.createElement('div');
                        col.className = 'col-md-6 mb-3';
                        col.innerHTML = `
                        <div class="card">
                            <img src="${e.target.result}" 
                                 class="card-img-top" 
                                 style="height: 120px; object-fit: cover;"
                                 alt="Preview">
                            <div class="card-body p-2">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger remove-preview" 
                                        data-index="${index}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                        imagePreviews.appendChild(col);
                    };
                    reader.readAsDataURL(file);
                });
            });

            // Remove preview image
            imagePreviews.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-preview')) {
                    const index = e.target.getAttribute('data-index');
                    e.target.closest('.col-md-6').remove();

                    // Update the file input
                    const dt = new DataTransfer();
                    const files = Array.from(imageInput.files);
                    files.splice(index, 1);
                    files.forEach(file => dt.items.add(file));
                    imageInput.files = dt.files;
                }
            });
        });
    </script>

    <!-- ===============================================-->
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