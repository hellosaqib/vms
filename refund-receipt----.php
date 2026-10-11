<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

$user = $auth->getUser();
$roleName = $auth->getRoleName();

$pageTitle = "Refund Receipt";

if (!hasPermission('manage_sales')) {
    $_SESSION['error'] = "You don't have permission to manage refunds.";
    header('Location: index.php');
    exit;
}

// Check if refund ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid refund ID.";
    header('Location: refund-management.php');
    exit;
}

$refund_id = (int)$_GET['id'];

// Fetch refund details
try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            s.invoice_no as sale_invoice, s.sale_date, s.sale_price, 
            s.service_fee, s.delivery_charges, s.discount_amount,
            s.payment_method as original_payment_method,
            s.finance_amount, s.finance_company_name, s.finance_company_address, 
            s.finance_company_phone, s.finance_company_email,
            s.part_exchange_allowance, s.deposit_received,
            v.vehicle_id, v.make, v.model, v.regnumber, v.year, v.vin, v.mileage,
            pxv.vehicle_id as px_vehicle_id, pxv.make as px_make, pxv.model as px_model, 
            pxv.regnumber as px_regnumber, pxv.year as px_year,
            c.customer_id, c.first_name, c.last_name, c.email, c.phone, 
            c.address, c.city, c.post_code,
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
        $_SESSION['error'] = "Refund not found.";
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
        if ($deduction['type'] == 'Part-Exchange Allowance') {
            $px_deduction_amount = $deduction['amount'];
        } else {
            $other_deductions_amount += $deduction['amount'];
        }
    }
    $totalDeductions = $px_deduction_amount + $other_deductions_amount;

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching refund details: " . $e->getMessage();
    header('Location: refund-management.php');
    exit;
}

$stmt = $db->prepare("
    SELECT 
        refund_id,
        net_refund_amount,
        total_deductions,
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
    $currentRefund['sale_id'],
    $currentRefund['created_at'],
    $currentRefund['created_at'],
    $refund_id
]);
$previousRefunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Calculate total previously refunded
$totalPreviousRefunded = 0;
foreach ($previousRefunds as $prev) {
    $totalPreviousDeductions += $prev['total_deductions'];
    $totalPreviousRefunded += $prev['net_refund_amount'];
}

?>

<!DOCTYPE html>
<html lang="en-US">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> - BritDrive Motors</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Print Optimization */
        @media print {
            body {
                font-size: 12pt;
                line-height: 1.2;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            
            .invoice-container {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 20px !important;
            }
            
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
            
            .table th,
            .table td {
                padding: 4px !important;
                font-size: 11pt !important;
            }
            
            .text-success {
                color: #000 !important;
                font-weight: bold !important;
            }
            
            /* Force black and white for printing */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                forced-color-adjust: exact !important;
            }
        }
        
        /* Screen Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 1200px;
        }
        
        .header-section {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            max-height: 80px;
        }
        
        .receipt-title {
            color: #2c3e50;
            font-weight: 700;
            margin: 0;
        }
        
        .receipt-number {
            font-size: 1.2rem;
            color: #6c757d;
        }
        
        .info-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .info-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            padding: 10px 15px;
        }
        
        .info-card .card-body {
            padding: 15px;
        }
        
        .total-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        
        .detail-value {
            color: #212529;
        }
        
        .signature-box {
            border-top: 2px dashed #dee2e6;
            margin-top: 50px;
            padding-top: 30px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 40px 0 5px 0;
            min-height: 30px;
        }
        
        .footer-note {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .alert-note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .vehicle-info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                padding: 15px;
                margin: 10px;
            }
            
            .total-amount {
                font-size: 2rem;
            }
            
            .print-btn {
                position: static;
                margin-bottom: 20px;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Print Button -->
    <div class="container-fluid no-print">
        <div class="print-btn">
            <button onclick="window.print()" class="btn btn-primary btn-lg">
                <i class="fas fa-print me-2"></i>Print Receipt
            </button>
            <a href="refund-management.php" class="btn btn-secondary btn-lg ms-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Refunds
            </a>
        </div>
    </div>

    <!-- Invoice Container -->
    <div class="container-fluid">
        <div class="invoice-container">
            
            <!-- Header Section -->
            <div class="header-section">
                <table class="table table-borderless mb-0" style="border:0px solid red;">
                            <tr>
                                <td class="align-items-center"><img src="uploads/images/britdrive-01.svg" alt="BritDrive Motors" class="company-logo">
                        <div class="mt-3">
                            <h4 class="receipt-title">REFUND RECEIPT</h4>
                            <div class="receipt-number">
                                Receipt #: <strong>RF-<?= str_pad($refund['refund_id'], 5, '0', STR_PAD_LEFT) ?></strong>
                            </div>
                        </div></td>
                                <td class="text-end"><h5>BritDrive Motors</h5>
                        <p class="mb-1">158 Derby Road</p>
                        <p class="mb-1">Nottingham, NG16 4AA</p>
                        <p class="mb-1">info@britdrivemotors.co.uk</p>
                        <p class="mb-0">VAT Number: [Your VAT Here]</p></td>
                            </tr>
                </table>
            </div>

            <!-- Basic Info Row -->
            <!-- <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="card-header">Customer Information</div>
                        <div class="card-body">
                            <p class="mb-1"><strong><?= htmlspecialchars($refund['first_name'] . ' ' . $refund['last_name']) ?></strong></p>
                            <p class="mb-1"><?= htmlspecialchars($refund['address']) ?></p>
                            <p class="mb-1"><?= htmlspecialchars($refund['city'] . ', ' . $refund['post_code']) ?></p>
                            <p class="mb-1">Phone: <?= htmlspecialchars($refund['phone']) ?></p>
                            <p class="mb-0">Email: <?= htmlspecialchars($refund['email']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="card-header">Original Sale Details</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Invoice #: <?= htmlspecialchars($refund['sale_invoice'] ?? 'N/A') ?></strong></p>
                            <p class="mb-1">Sale Date: <?= date('d-m-Y', strtotime($refund['sale_date'])) ?></p>
                            <p class="mb-1">Vehicle: <?= htmlspecialchars($refund['make'] . ' ' . $refund['model']) ?></p>
                            <p class="mb-1">Reg: <?= htmlspecialchars($refund['regnumber']) ?></p>
                            <p class="mb-0">Sale Price: £<?= number_format($refund['sale_price'], 2) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="info-card">
                        <div class="card-header">Refund Status & Dates</div>
                        <div class="card-body">
                            <?php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'info',
                                'processed' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $statusColor = $statusColors[$refund['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $statusColor ?> status-badge mb-3">
                                <?= strtoupper($refund['status']) ?>
                            </span>
                            <p class="mb-1">Refund Date: <?= date('d-m-Y', strtotime($refund['created_at'])) ?></p>
                            <?php if ($refund['processed_date']): ?>
                                <p class="mb-0">Processed: <?= date('d-m-Y H:i', strtotime($refund['processed_date'])) ?></p>
                            <?php endif; ?>
                            <?php if ($refund['created_by_name']): ?>
                                <p class="mb-0 mt-2">Created by: <?= htmlspecialchars($refund['created_by_name']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div> -->
                    <div class=" p-0 pt-0 pb-0 mb-3 rounded-2" style="background-color:#ddd; border:0px solid red;">
                        <table class="table table-borderless mb-0" style="border:0px solid red;">
                            <tr>
                                
                                <td width="350">
                                    <h6 class="mb-2">Original Sale Information:</h6>
                                    <div class="fs-9 text-body-secondary mb-0">
                                        <p class="mb-0">
                                            Invoice No : <?php echo htmlspecialchars($refund['sale_invoice'] ?? 'N/A'); ?><br>
                                            <?php echo htmlspecialchars($refund['make'] . " " . $refund['model']); ?><br>
                                            Reg Number: <?php echo htmlspecialchars($refund['regnumber']); ?><br>
                                            Sale Date : <?= date('d-m-Y', strtotime($refund['sale_date'])) ?><br>
                                            <!-- Sale Price : £<?= number_format($refund['sale_price'], 2) ?><br> -->

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
            <!-- Vehicle Return Information -->
            <?php if ($refund['vehicle_returned'] == 1 || $refund['px_vehicle_returned'] == 1): ?>
                <div class="alert alert-info mb-4">
                    <h5><i class="fas fa-exchange-alt me-2"></i>Vehicle Return Information</h5>
                    <div class="row mt-3">
                        <?php if ($refund['vehicle_returned'] == 1): ?>
                            <div class="col-md-6">
                                <div class="vehicle-info-box">
                                    <h6><i class="fas fa-car me-2"></i>Main Vehicle Returned</h6>
                                    <p class="mb-1"><?= htmlspecialchars($refund['make'] . ' ' . $refund['model']) ?></p>
                                    <p class="mb-1">Registration: <?= htmlspecialchars($refund['regnumber']) ?></p>
                                    <p class="mb-0">VIN: <?= htmlspecialchars($refund['vin']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($refund['px_vehicle_returned'] == 1 && !empty($refund['px_make'])): ?>
                            <div class="col-md-6">
                                <div class="vehicle-info-box">
                                    <h6><i class="fas fa-exchange-alt me-2"></i>Part Exchange Vehicle Returned</h6>
                                    <p class="mb-1"><?= htmlspecialchars($refund['px_make'] . ' ' . $refund['px_model']) ?></p>
                                    <p class="mb-1">Registration: <?= htmlspecialchars($refund['px_regnumber']) ?></p>
                                    <p class="mb-0">Year: <?= htmlspecialchars($refund['px_year']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Finance Information -->
            <?php if ($refund['original_payment_method'] == 'finance'): ?>
                <div class="alert alert-warning mb-4">
                    <h5><i class="fas fa-university me-2"></i>Finance Settlement Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Finance Company:</strong> <?= htmlspecialchars($refund['finance_company_name'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Finance Amount:</strong> £<?= number_format($refund['finance_amount'], 2) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-0"><small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                The finance amount will be settled directly with the finance company. 
                                This may affect the timeline of your refund.
                            </small></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Refund Details -->
            <div class="row">
                <!-- Left Column - Refund Reason -->
                <div class="col-lg-7">
                    <div class="info-card mb-4">
                        <div class="card-header">Refund Reason</div>
                        <div class="card-body">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($refund['refund_reason'] ?? 'Not specified')) ?></p>
                        </div>
                    </div>
                    
                    <?php if(!empty($refund['customer_agreement'])): ?>
                    <div class="info-card">
                        <div class="card-header">Customer Agreement / Notes</div>
                        <div class="card-body">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($refund['customer_agreement'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column - Refund Calculation -->
                <div class="col-lg-5">
                    <div class="info-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Refund Calculation</h5>
                        </div>
                        <div class="card-body">
                            <!-- Sale Price -->
                            <div class="row mb-3">
                                <div class="col-8">
                                    <span class="detail-label">Original Sale Price:</span>
                                </div>
                                <div class="col-4 text-end">
                                    <span class="detail-value">£<?= number_format($refund['sale_price'], 2) ?></span>
                                </div>
                            </div>
                            
                            <!-- Deductions -->
                            <?php if (!empty($deductions)): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="border-bottom pb-2 mb-3">Adjustments & Deductions</h6>
                                        <?php foreach ($deductions as $deduction): ?>
                                            <div class="row mb-2">
                                                <div class="col-8">
                                                    <span><?= htmlspecialchars($deduction['deduction_type']) ?></span>
                                                    <?php if (!empty($deduction['description'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($deduction['description']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-4 text-end text-danger">
                                                    -£<?= number_format($deduction['amount'], 2) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Total Deductions -->
                                <div class="row mb-3 border-top pt-3">
                                    <div class="col-8">
                                        <strong>Total Deductions:</strong>
                                    </div>
                                    <div class="col-4 text-end text-danger">
                                        <strong>-£<?= number_format($totalDeductions, 2) ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Net Refund Amount -->
                            <div class="row mt-4 pt-4 border-top">
                                <div class="col-12 text-center">
                                    <h5 class="text-muted mb-2">NET REFUND AMOUNT</h5>
                                    <div class="total-amount">
                                        £<?= number_format($refund['net_refund_amount'], 2) ?>
                                    </div>
                                    <p class="text-muted mt-2">
                                        Refund Method: <?= strtoupper(str_replace('_', ' ', $refund['original_payment_method'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Notes -->
            <div class="footer-note">
                <div class="alert-note">
                    <p class="mb-2"><strong>Important Information:</strong></p>
                    <ul class="mb-0">
                        <li>This refund has been processed and should appear in your account within 3-5 business days</li>
                        <li>Please allow additional time for international transactions or finance settlements</li>
                        <li>For any queries, please contact our customer service at info@britdrivemotors.co.uk</li>
                        <li>Keep this receipt for your records and any future correspondence</li>
                    </ul>
                </div>
            </div>

            <!-- Signatures -->
            <div class="signature-box">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Customer Signature:</strong></p>
                        <div class="signature-line"></div>
                        <p class="mt-1 mb-0">Name: <?= htmlspecialchars($refund['first_name'] . ' ' . $refund['last_name']) ?></p>
                        <p class="mb-0">Date: _____________________</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Authorized Representative:</strong></p>
                        <div class="signature-line"></div>
                        <p class="mt-1 mb-0">Name: _____________________</p>
                        <p class="mb-0">Date: _____________________</p>
                        <p class="mb-0">Position: _____________________</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-5 pt-4 border-top">
                <p class="mb-1">BritDrive Motors | 158 Derby Road, Nottingham, NG16 4AA</p>
                <p class="mb-1">Phone: [Your Phone] | Email: info@britdrivemotors.co.uk</p>
                <p class="mb-0 text-muted">This is an official refund receipt. Please retain for your records.</p>
            </div>

        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto print option
        window.onload = function() {
            // Check if print parameter is set
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(function() {
                    window.print();
                }, 1000);
            }
        };
        
        // Add print watermark
        window.onafterprint = function() {
            console.log('Print completed');
        };
        
        // Add before print event
        window.onbeforeprint = function() {
            // You can add any pre-print modifications here
            document.body.classList.add('printing');
        };
    </script>
</body>
</html>