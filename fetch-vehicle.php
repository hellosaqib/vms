<?php
include("includes/config.php");
global $b;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$regNumber = trim($_POST['reg_number'] ?? '');

if (empty($regNumber)) {
    echo json_encode(['error' => 'Registration number is required']);
    exit;
}

// DVLA API Call
$apiUrl = 'https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles';
$apiKey = 'wTFeES42o3a9lDjKiP8hI4tp3YTBYSX8S5rDzxLa'; // Replace with your actual key

$headers = [
    'x-api-key: ' . $apiKey,
    'Content-Type: application/json',
];

$postData = json_encode(['registrationNumber' => $regNumber]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Failed to fetch vehicle details']);
    exit;
}

$vehicleData = json_decode($response, true);

// Save to MySQL Database
try {
    //$pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // $stmt = $db->prepare("
    //     INSERT INTO vehicles (
    //         regnumber, make, model, year_of_manufacture, 
    //         engine_size, fuel_type, colour, mot_status, tax_status
    //     ) VALUES (
    //         :reg_number, :make, :model, :year, 
    //         :engine_size, :fuel_type, :colour, :mot_status, :tax_status
    //     ) ON DUPLICATE KEY UPDATE
    //         make = VALUES(make),
    //         model = VALUES(model),
    //         year_of_manufacture = VALUES(year_of_manufacture),
    //         engine_size = VALUES(engine_size),
    //         fuel_type = VALUES(fuel_type),
    //         colour = VALUES(colour),
    //         mot_status = VALUES(mot_status),
    //         tax_status = VALUES(tax_status)
    // ");

    // $stmt->execute([
    //     ':reg_number' => $regNumber,
    //     ':make' => $vehicleData['make'] ?? null,
    //     ':model' => $vehicleData['model'] ?? null,
    //     ':year' => $vehicleData['yearOfManufacture'] ?? null,
    //     ':engine_size' => $vehicleData['engineCapacity'] ?? null,
    //     ':fuel_type' => $vehicleData['fuelType'] ?? null,
    //     ':colour' => $vehicleData['colour'] ?? null,
    //     ':mot_status' => $vehicleData['motStatus'] ?? null,
    //     ':tax_status' => $vehicleData['taxStatus'] ?? null,
    // ]);

    echo json_encode(['success' => true, 'data' => $vehicleData]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>