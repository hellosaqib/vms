<?php
require_once __DIR__ . '/config/database.php';

function sanitizeFormData(&$data) {
    // Define field types
    $fieldTypes = [
        'int' => ['year', 'mileage', 'owners', 'keys_count', 'number_of_services', 
                  'auction_id', 'engine_size', 'purchase_price'],
        'float' => [], // Add any float fields if needed
        'date' => ['purchase_date', 'mot_expiry', 'tax_due_date', 'last_v5c_issued'],
        'bool' => [], // Add any boolean fields if needed
        'string' => ['VIN', 'reg_number', 'make', 'model', 'trim', 'color', 
                    'body_style', 'transmission', 'fuel_type', 'v5_status', 
                    'logbook_status', 'tax_status', 'notes']
    ];
    
    foreach ($data as $key => &$value) {
        // Determine field type
        $type = 'string'; // default
        foreach ($fieldTypes as $typeName => $fields) {
            if (in_array($key, $fields)) {
                $type = $typeName;
                break;
            }
        }
        
        // Sanitize based on type
        if ($value === '' || $value === null) {
            $value = null;
        } else {
            switch ($type) {
                case 'int':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;
                case 'date':
                    // Keep as is, assuming already formatted
                    break;
                case 'bool':
                    $value = (bool)$value;
                    break;
                case 'string':
                default:
                    // Trim and sanitize strings
                    $value = trim($value);
                    break;
            }
        }
    }
    
    return $data;
}

// function hasPermission($permission) {
//     global $db;
    
//     if (!isset($_SESSION['permissions'])) return false;
//     return in_array($permission, $_SESSION['permissions']);
// }
// Vehicle Functions

function getVehicleImagesById($id) {
    global $db; // Assuming $db is your PDO connection
    
    $stmt = $db->prepare("
        SELECT * 
        FROM vehicle_images 
        WHERE vehicle_id = :id
        ORDER BY image_id ASC
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDashboardSummaryStats() {
    global $db; // Your PDO connection
    
    $query = "
        SELECT
            COUNT(*) AS total_vehicles,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
            SUM(CASE WHEN status = 'in_repair' THEN 1 ELSE 0 END) AS in_repair,
            SUM(CASE WHEN status = 'sold' AND sale_date >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH 
                      AND sale_date < LAST_DAY(CURDATE()) + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS sold_this_month,
            COALESCE(SUM(CASE WHEN status = 'sold' AND sale_date >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH 
                             AND sale_date < LAST_DAY(CURDATE()) + INTERVAL 1 DAY THEN sale_price ELSE 0 END), 0) AS monthly_revenue,
            AVG(CASE WHEN status = 'sold' THEN DATEDIFF(sale_date, purchase_date) ELSE NULL END) AS avg_days_to_sell
        FROM vehicles
    ";
    
    try {
        $stmt = $db->query($query);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate additional derived metrics
        $stats['sold_percentage'] = $stats['total_vehicles'] > 0 
            ? round(($stats['sold_this_month'] / $stats['total_vehicles']) * 100, 1) 
            : 0;
            
        $stats['repair_percentage'] = $stats['total_vehicles'] > 0 
            ? round(($stats['in_repair'] / $stats['total_vehicles']) * 100, 1) 
            : 0;
        
        // Format for display
        $stats['monthly_revenue_formatted'] = '£' . number_format($stats['monthly_revenue'], 2);
        $stats['avg_days_to_sell_rounded'] = round($stats['avg_days_to_sell']);
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return [
            'total_vehicles' => 0,
            'available' => 0,
            'in_repair' => 0,
            'sold_this_month' => 0,
            'monthly_revenue' => 0,
            'avg_days_to_sell' => 0
        ];
    }
}

/**
 * Generates complete financial summary for sold vehicles
 * @param string|null $startDate Optional start date (YYYY-MM-DD)
 * @param string|null $endDate Optional end date (YYYY-MM-DD)
 * @return array Financial summary with all metrics
 */
function getSoldVehiclesFinancialSummary($startDate = null, $endDate = null) {
    global $db;
    
    $query = "
        SELECT
            /* Basic Counts */
            COUNT(*) AS total_vehicles_sold,
            
            /* Financial Totals */
            COALESCE(SUM(v.purchase_price), 0) AS total_purchase,
            COALESCE(SUM(v.sale_price), 0) AS total_sales,
            
            /* Cost Breakdown */
            COALESCE(SUM(
                (SELECT SUM(r.cost) FROM repairs r WHERE r.vehicle_id = v.vehicle_id)
            ), 0) AS total_repairs,
            
            COALESCE(SUM(
                (SELECT SUM(e.amount) FROM expenses e WHERE e.vehicle_id = v.vehicle_id)
            ), 0) AS total_expenses,
            
            /* Profit Calculation */
            COALESCE(SUM(v.sale_price - v.purchase_price), 0) AS gross_profit,
            
            COALESCE(SUM(
                v.sale_price - v.purchase_price - 
                (SELECT COALESCE(SUM(r.cost), 0) FROM repairs r WHERE r.vehicle_id = v.vehicle_id) -
                (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.vehicle_id = v.vehicle_id)
            ), 0) AS net_profit,
            
            /* Averages */
            COALESCE(AVG(v.sale_price - v.purchase_price), 0) AS avg_gross_per_vehicle,
            COALESCE(AVG(
                v.sale_price - v.purchase_price - 
                (SELECT COALESCE(SUM(r.cost), 0) FROM repairs r WHERE r.vehicle_id = v.vehicle_id) -
                (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.vehicle_id = v.vehicle_id)
            ), 0) AS avg_net_per_vehicle,
            
            /* Ratios */
            CASE WHEN SUM(v.sale_price) > 0 
                THEN (SUM(v.sale_price - v.purchase_price) / SUM(v.sale_price)) * 100 
                ELSE 0 
            END AS gross_margin,
            
            CASE WHEN SUM(v.sale_price) > 0 
                THEN (SUM(v.sale_price - v.purchase_price - 
                     (SELECT COALESCE(SUM(r.cost), 0) FROM repairs r WHERE r.vehicle_id = v.vehicle_id) -
                     (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.vehicle_id = v.vehicle_id))
                     / SUM(v.sale_price)) * 100 
                ELSE 0 
            END AS net_margin
        FROM vehicles v
        WHERE v.status = 'sold'
    ";
    
    // Add date filters if provided
    if ($startDate) {
        $query .= " AND v.sold_date >= :startDate";
    }
    if ($endDate) {
        $query .= " AND v.sold_date <= :endDate";
    }
    
    try {
        $stmt = $db->prepare($query);
        
        if ($startDate) $stmt->bindValue(':startDate', $startDate);
        if ($endDate) $stmt->bindValue(':endDate', $endDate);
        
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format results
        $summary['profit_status'] = ($summary['net_profit'] >= 0) ? 'profit' : 'loss';
        
        // Format all currency values
        $currencyFields = [
            'total_purchase', 'total_sales', 'total_repairs', 'total_expenses',
            'gross_profit', 'net_profit', 'avg_gross_per_vehicle', 'avg_net_per_vehicle'
        ];
        
        foreach ($currencyFields as $field) {
            $summary[$field.'_formatted'] = '£'.number_format($summary[$field], 2);
        }
        
        // Format percentages
        $summary['gross_margin_formatted'] = number_format($summary['gross_margin'], 1).'%';
        $summary['net_margin_formatted'] = number_format($summary['net_margin'], 1).'%';
        
        return $summary;
        
    } catch (PDOException $e) {
        error_log("Financial summary error: ".$e->getMessage());
        return false;
    }
}

?>