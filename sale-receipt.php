<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if we have a receipt to print
// $saleId = $_SESSION['print_receipt'] ?? 0;
// unset($_SESSION['print_receipt']);

if(isset($_GET['id'])){
    $saleId=$_GET['id'];
}

if (!$saleId) {
    
    header("Location: view-all-vehicles.php");
    exit;
}

// Get sale details with proper query separation

$stmt = $db->prepare("
    SELECT s.*, 
           v.make, v.model, v.year, v.VIN, v.mileage,
           c.first_name, c.last_name, c.phone, c.address,
           u.username as sales_person_name
    FROM sales s
    JOIN vehicles v ON s.vehicle_id = v.vehicle_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN users u ON s.sales_person = u.id
    WHERE v.vehicle_id = ? 
    AND v.status = 'sold'
");
$stmt->execute([$saleId]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    $_SESSION['error'] = 'Sale record not found';
    header("Location: view-all-vehicles.php");
    exit;
}

// Set PDF headers if you want PDF output
// header('Content-Type: application/pdf');
// header('Content-Disposition: inline; filename="receipt_'.$saleId.'.pdf"');

// For HTML output:
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Receipt #<?= $saleId ?></title>
    <style>
        body { font-family: Arial, sans-serif; }
        .receipt-container { width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; margin-bottom: 20px; }
        .receipt-title { font-size: 24px; font-weight: bold; }
        .receipt-details { margin: 30px 0; }
        .detail-row { display: flex; margin-bottom: 10px; }
        .detail-label { width: 150px; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; }
        .text-right { text-align: right; }
        .signature-line { margin-top: 50px; border-top: 1px solid #000; width: 300px; }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="receipt-title">VEHICLE SALE RECEIPT</div>
            <div>Receipt #<?= $saleId ?></div>
            <div><?= date('F j, Y', strtotime($sale['sale_date'])) ?></div>
        </div>
        
        <div class="receipt-details">
            <div class="detail-row">
                <div class="detail-label">Vehicle:</div>
                <div><?= $sale['year'] ?> <?= $sale['make'] ?> <?= $sale['model'] ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">VIN:</div>
                <div><?= $sale['VIN'] ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Mileage:</div>
                <div><?= number_format($sale['mileage']) ?></div>
            </div>
            
            <?php if ($sale['first_name']): ?>
            <div class="divider"></div>
            <div class="detail-row">
                <div class="detail-label">Sold To:</div>
                <div>
                    <?= $sale['first_name'] ?> <?= $sale['last_name'] ?><br>
                    <?= $sale['phone'] ?><br>
                    <?= $sale['address'] ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            <div class="detail-row">
                <div class="detail-label">Sale Price:</div>
                <div>£<?= number_format($sale['sale_price'], 2) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Method:</div>
                <div><?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></div>
            </div>
            
            <?php if ($sale['commission'] > 0): ?>
            <div class="detail-row">
                <div class="detail-label">Sales Commission:</div>
                <div>£<?= number_format($sale['commission'], 2) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($sale['notes'])): ?>
            <div class="divider"></div>
            <div class="detail-row">
                <div class="detail-label">Notes:</div>
                <div><?= nl2br(htmlspecialchars($sale['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <div>Thank you for your business!</div>
            <div><?= htmlspecialchars($sale['sales_person_name']) ?></div>
            <div class="signature-line"></div>
            <div style="text-align: left;">Authorized Signature</div>
        </div>
    </div>
    
    <script>
    // Auto-print when page loads
    // window.onload = function() {
    //     window.print();
    //     setTimeout(function() {
    //         window.close();
    //     }, 1000);
    // };
    </script>
    <div style="position:fixed;bottom:20px;width:100%;text-align:center;">
        <button onclick="window.print()">Print</button>
        <!-- <button onclick="window.close()">Close</button> -->
    </div>
</body>
</html>