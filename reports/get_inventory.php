<?php
$requiredPermission = 'manage_reports';
require_once '../includes/functions.php';


header('Content-Type: application/json');

try {
        $query = "
        SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.year,
            v.vin,
            v.regnumber,
            v.status,
            v.purchase_price,
            v.sale_price,
            COUNT(DISTINCT i.image_path) as image_count,
            GROUP_CONCAT(DISTINCT r.repair_type SEPARATOR ', ') as pending_repairs
        FROM vehicles v
        LEFT JOIN vehicle_images i ON v.vehicle_id = i.vehicle_id
        LEFT JOIN repairs r ON v.vehicle_id = r.vehicle_id AND r.date_completed IS NULL
        GROUP BY v.vehicle_id
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'timestamp' => time()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}

exit;
?>
