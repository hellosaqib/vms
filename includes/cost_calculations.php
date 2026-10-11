<?php
/**
 * Centralized Cost Calculations for Vehicle Management System
 * Ensures consistent cost calculations across all dashboards and reports
 */

/**
 * Get complete vehicle costs from all sources
 */
function getVehicleTotalCosts($vehicle_id) {
    global $db;
    
    $query = $db->prepare("
        SELECT 
            -- Job Card Costs
            COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_card_task_progress jctp
                JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
                WHERE jc.vehicle_id = ? AND jctp.cost > 0
            ), 0) as job_card_costs,
            
            -- General Repair Costs
            COALESCE((
                SELECT SUM(cost) 
                FROM repairs 
                WHERE vehicle_id = ? AND cost > 0
            ), 0) as repair_costs,
            
            -- Additional Expenses
            COALESCE((
                SELECT SUM(amount) 
                FROM expenses 
                WHERE vehicle_id = ? AND amount > 0
            ), 0) as expense_costs
    ");
    
    $query->execute([$vehicle_id, $vehicle_id, $vehicle_id]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $result['total_costs'] = $result['job_card_costs'] + $result['repair_costs'] + $result['expense_costs'];
    
    return $result;
}

/**
 * Get top expensive vehicles (consistent across all dashboards)
 */
function getTopExpensiveVehiclesConsistent($limit = 5) {
    global $db;
    
    $query = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            v.purchase_price,
            v.sale_price,
            v.status,
            -- Job Card Costs
            COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_card_task_progress jctp
                JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
                WHERE jc.vehicle_id = v.vehicle_id AND jctp.cost > 0
            ), 0) as job_card_costs,
            
            -- General Repair Costs
            COALESCE((
                SELECT SUM(cost) 
                FROM repairs 
                WHERE vehicle_id = v.vehicle_id AND cost > 0
            ), 0) as repair_costs,
            
            -- Additional Expenses
            COALESCE((
                SELECT SUM(amount) 
                FROM expenses 
                WHERE vehicle_id = v.vehicle_id AND amount > 0
            ), 0) as expense_costs
            
        FROM vehicles v
        WHERE v.status != 'sold'
        HAVING (job_card_costs + repair_costs + expense_costs) > 0
        ORDER BY (job_card_costs + repair_costs + expense_costs) DESC
        LIMIT " . (int)$limit
    );
    
    $query->execute();
    $vehicles = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Add calculated fields for consistency
    foreach ($vehicles as &$vehicle) {
        $vehicle['total_costs'] = $vehicle['job_card_costs'] + $vehicle['repair_costs'] + $vehicle['expense_costs'];
        $vehicle['total_investment'] = $vehicle['purchase_price'] + $vehicle['total_costs'];
        $vehicle['cost_percentage'] = $vehicle['purchase_price'] > 0 ? 
            round(($vehicle['total_costs'] / $vehicle['purchase_price']) * 100, 1) : 0;
    }
    
    return $vehicles;
}

/**
 * Get all vehicles with complete cost breakdown
 */
function getAllVehiclesWithCosts() {
    global $db;
    
    $query = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            v.purchase_price,
            v.sale_price,
            v.status,
            -- Job Card Costs
            COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_card_task_progress jctp
                JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
                WHERE jc.vehicle_id = v.vehicle_id AND jctp.cost > 0
            ), 0) as job_card_costs,
            
            -- General Repair Costs
            COALESCE((
                SELECT SUM(cost) 
                FROM repairs 
                WHERE vehicle_id = v.vehicle_id AND cost > 0
            ), 0) as repair_costs,
            
            -- Additional Expenses
            COALESCE((
                SELECT SUM(amount) 
                FROM expenses 
                WHERE vehicle_id = v.vehicle_id AND amount > 0
            ), 0) as expense_costs
            
        FROM vehicles v
        ORDER BY v.regnumber
    ");
    
    $query->execute();
    $vehicles = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Add calculated fields
    foreach ($vehicles as &$vehicle) {
        $vehicle['total_costs'] = $vehicle['job_card_costs'] + $vehicle['repair_costs'] + $vehicle['expense_costs'];
        $vehicle['total_investment'] = $vehicle['purchase_price'] + $vehicle['total_costs'];
        $vehicle['cost_percentage'] = $vehicle['purchase_price'] > 0 ? 
            round(($vehicle['total_costs'] / $vehicle['purchase_price']) * 100, 1) : 0;
            
        // Profitability for sold vehicles
        if ($vehicle['sale_price'] && $vehicle['status'] == 'sold') {
            $vehicle['profit'] = $vehicle['sale_price'] - $vehicle['total_investment'];
            $vehicle['profit_margin'] = $vehicle['sale_price'] > 0 ? 
                round(($vehicle['profit'] / $vehicle['sale_price']) * 100, 1) : 0;
        } else {
            $vehicle['profit'] = 0;
            $vehicle['profit_margin'] = 0;
        }
    }
    
    return $vehicles;
}
?>