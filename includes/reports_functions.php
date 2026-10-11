<?php

/**
 * Reports Functions for Car Trade System
 * Modular approach for reusable data functions
 */

/**
 * Core Data Functions - Reusable across multiple reports
 */

function getVehicleFinancials($db, $filters = [])
{
    $whereConditions = [];
    $params = [];

    // Date filters
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "s.sale_date >= ?";
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "s.sale_date <= ?";
        $params[] = $filters['end_date'];
    }
    if (!empty($filters['vehicle_status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $filters['vehicle_status'];
    }

    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }

    $query = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            v.purchase_price,
            v.purchase_date,
            v.status,
            s.sale_id,
            s.sale_price,
            s.sale_date,
            s.service_fee,
            s.delivery_charges,
            s.discount_amount,
            s.deposit_received,
            s.part_exchange_allowance,
            s.finance_amount,
            
            -- Use actual total_revenue from sales table (this is the correct value)
            COALESCE(s.total_revenue, 0) as total_revenue,
            
            -- Use actual total_cost from sales table
            COALESCE(s.total_cost, 0) as total_cost,
            
            -- Use actual net_profit from sales table
            COALESCE(s.net_profit, 0) as net_profit,
            
            -- Gross profit = sale_price - purchase_price
            CASE 
                WHEN v.status = 'sold' AND s.sale_price IS NOT NULL THEN 
                    (s.sale_price - v.purchase_price)
                ELSE 0 
            END as gross_profit,
            
            -- Gross margin percentage
            CASE 
                WHEN v.status = 'sold' AND v.purchase_price > 0 THEN 
                    ROUND(((s.sale_price - v.purchase_price) / v.purchase_price) * 100, 1)
                ELSE 0 
            END as gross_margin_percent,
            
            -- Net margin percentage
            CASE 
                WHEN v.status = 'sold' AND COALESCE(s.total_revenue, 0) > 0 THEN 
                    ROUND((COALESCE(s.net_profit, 0) / COALESCE(s.total_revenue, 0)) * 100, 1)
                ELSE 0 
            END as net_margin_percent,
            
            -- Business keeps = service_fee + delivery_charges + deductions
            (COALESCE(s.service_fee, 0) + 
            COALESCE(s.delivery_charges, 0)) as business_keeps_minimum,
            
            -- Days in inventory
            CASE 
                WHEN s.sale_date IS NOT NULL THEN 
                    DATEDIFF(s.sale_date, v.purchase_date)
                ELSE 
                    DATEDIFF(CURRENT_DATE, v.purchase_date)
            END as days_in_inventory
        FROM vehicles v
        LEFT JOIN sales s ON v.vehicle_id = s.vehicle_id
        $whereClause
        ORDER BY v.purchase_date DESC
    ");

    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function getTimePeriodData($period = 'month')
{
    $dateRanges = [];

    switch ($period) {
        case 'today':
            $dateRanges = [
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d')
            ];
            break;
        case 'week':
            $dateRanges = [
                'start_date' => date('Y-m-d', strtotime('-1 week')),
                'end_date' => date('Y-m-d')
            ];
            break;
        case 'month':
            $dateRanges = [
                'start_date' => date('Y-m-01'),
                'end_date' => date('Y-m-t')
            ];
            break;
        case 'quarter':
            $quarter = ceil(date('n') / 3);
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $dateRanges = [
                'start_date' => date("Y-{$startMonth}-01"),
                'end_date' => date("Y-{$endMonth}-t")
            ];
            break;
        case 'year':
            $dateRanges = [
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31')
            ];
            break;
        default:
            $dateRanges = [
                'start_date' => date('Y-m-01'),
                'end_date' => date('Y-m-t')
            ];
    }

    return $dateRanges;
}

/**
 * Report Generation Functions
 */

function generateGrossProfitReport($db, $filters = []) {
    // ONLY include sold vehicles - remove any other status filters
    $filters['vehicle_status'] = 'sold';
    
    // Get the financial data (only sold vehicles)
    $soldVehicles = getVehicleFinancials($db, $filters);
    // Calculate summary totals ONLY for sold vehicles
    $summary = [
        'total_vehicles' => count($soldVehicles),
        'total_revenue' => 0,
        'total_purchase_cost' => 0,
        'total_costs' => 0,
        'total_gross_profit' => 0,
        'total_net_profit' => 0,
        'avg_gross_profit_per_vehicle' => 0,
        'avg_net_profit_per_vehicle' => 0,
        'avg_days_to_sell' => 0,
        'gross_margin_percent' => 0,
        'net_margin_percent' => 0
    ];
    
    $daysToSell = [];
    
    foreach ($soldVehicles as $vehicle) {
        $summary['total_revenue'] += $vehicle['total_revenue'];
        $summary['total_purchase_cost'] += $vehicle['purchase_price'];
        $summary['total_costs'] += $vehicle['total_cost'];
        $summary['total_gross_profit'] += $vehicle['gross_profit'];
        $summary['total_net_profit'] += $vehicle['net_profit'];
        $daysToSell[] = $vehicle['days_in_inventory'];
    }
    
    // Calculate averages only if we have sold vehicles
    if ($summary['total_vehicles'] > 0) {
        $summary['avg_gross_profit_per_vehicle'] = $summary['total_gross_profit'] / $summary['total_vehicles'];
        $summary['avg_net_profit_per_vehicle'] = $summary['total_net_profit'] / $summary['total_vehicles'];
        $summary['avg_days_to_sell'] = array_sum($daysToSell) / count($daysToSell);
    }
    
    // Calculate margin percentages
    if ($summary['total_revenue'] > 0) {
        $summary['gross_margin_percent'] = ($summary['total_gross_profit'] / $summary['total_revenue']) * 100;
        $summary['net_margin_percent'] = ($summary['total_net_profit'] / $summary['total_revenue']) * 100;
    }
    
    return [
        'vehicle_data' => $soldVehicles, // ONLY sold vehicles
        'summary' => $summary,
        'filters' => $filters
    ];
}

/**
 * Utility Functions for Reports
 */

function formatCurrency($amount)
{
    return '£' . number_format($amount, 2);
}

function formatPercentage($percentage)
{
    return number_format($percentage, 1) . '%';
}

function getProfitabilityClass($profit)
{
    if ($profit > 0) {
        return 'text-success';
    } elseif ($profit < 0) {
        return 'text-danger';
    } else {
        return 'text-warning';
    }
}

function getMarginClass($margin)
{
    if ($margin > 20) {
        return 'text-success';
    } elseif ($margin > 10) {
        return 'text-warning';
    } else {
        return 'text-danger';
    }
}

/**
 * Inventory Aging Report Functions
 */

function getInventoryAgingData($db, $filters = [])
{
    $whereConditions = [];
    $params = [];

    // Status filter - only show unsold vehicles
    $whereConditions[] = "v.status IN ('available', 'in_preparation', 'purchased', 'part_exchange', 'sold') ";

    if (!empty($filters['vehicle_status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $filters['vehicle_status'];
    }

    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }

    $query = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            v.purchase_price,
            v.purchase_date,
            v.status,
            -- Get balance due from sales table
            COALESCE(s.balance_due, 0) as balance_due,
            
            -- Get sale status for display clarity
            COALESCE(s.status, 'not_sold') as sale_status,

            -- Days in inventory
            DATEDIFF(CURRENT_DATE, v.purchase_date) as days_in_inventory,
            -- Repair costs
            COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_cards jc 
                JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id 
                WHERE jc.vehicle_id = v.vehicle_id
                AND jctp.cost > 0
            ), 0) as repair_costs,
            -- Total investment
            (v.purchase_price + COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_cards jc 
                JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id 
                WHERE jc.vehicle_id = v.vehicle_id
                AND jctp.cost > 0
            ), 0)) as total_investment,
            -- Aging categories
            CASE 
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 30 THEN '0-30 days'
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 60 THEN '31-60 days'
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 90 THEN '61-90 days'
                ELSE '90+ days'
            END as aging_category
        FROM vehicles v
        LEFT JOIN sales s ON v.vehicle_id = s.vehicle_id  -- Changed to LEFT JOIN
        $whereClause
        ORDER BY days_in_inventory DESC
    ");

    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function generateInventoryAgingReport($db, $filters = [])
{
    // Get the aging data
    $agingData = getInventoryAgingData($db, $filters);

    // Calculate summary by aging categories
    $agingSummary = [
        '0-30 days' => ['count' => 0, 'total_value' => 0],
        '31-60 days' => ['count' => 0, 'total_value' => 0],
        '61-90 days' => ['count' => 0, 'total_value' => 0],
        '90+ days' => ['count' => 0, 'total_value' => 0]
    ];

    $totalSummary = [
        'total_vehicles' => count($agingData),
        'total_inventory_value' => 0,
        'total_repair_costs' => 0,
        'avg_days_in_inventory' => 0,
        'oldest_vehicle_days' => 0
    ];

    foreach ($agingData as $vehicle) {
        // Update aging category summary
        $agingSummary[$vehicle['aging_category']]['count']++;
        $agingSummary[$vehicle['aging_category']]['total_value'] += $vehicle['purchase_price'];

        // Update total summary
        $totalSummary['total_inventory_value'] += $vehicle['purchase_price'];
        $totalSummary['total_repair_costs'] += $vehicle['repair_costs'];

        // Track oldest vehicle
        if ($vehicle['days_in_inventory'] > $totalSummary['oldest_vehicle_days']) {
            $totalSummary['oldest_vehicle_days'] = $vehicle['days_in_inventory'];
        }
    }

    // Calculate average days
    if ($totalSummary['total_vehicles'] > 0) {
        $totalSummary['avg_days_in_inventory'] = array_sum(array_column($agingData, 'days_in_inventory')) / $totalSummary['total_vehicles'];
    }

    return [
        'aging_data' => $agingData,
        'aging_summary' => $agingSummary,
        'total_summary' => $totalSummary,
        'filters' => $filters
    ];
}

function getAgingCategoryClass($category)
{
    switch ($category) {
        case '0-30 days':
            return 'bg-success';
        case '31-60 days':
            return 'bg-warning';
        case '61-90 days':
            return 'bg-warning';
        case '90+ days':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function getDaysClass($days)
{
    if ($days <= 30) return 'text-success';
    if ($days <= 60) return 'text-warning';
    if ($days <= 90) return 'text-warning';
    return 'text-danger';
}

/**
 * Profit & Loss Statement Functions
 */

function getProfitLossData($db, $filters = [])
{
    $whereConditions = [];
    $params = [];

    // Date filters for sales
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "s.sale_date >= ?";
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "s.sale_date <= ?";
        $params[] = $filters['end_date'];
    }

    // Only include sales with financial impact
    $whereConditions[] = "s.status IN ('completed', 'refunded', 'partially_refunded')";
    
    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }

    // Handle additional parameters for expenses subquery
    $expensesWhere = "WHERE 1=1";
    $expensesParams = [];
    
    if (!empty($filters['start_date'])) {
        $expensesWhere .= " AND date >= ?";
        $expensesParams[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $expensesWhere .= " AND date <= ?";
        $expensesParams[] = $filters['end_date'];
    }

    $query = $db->prepare("
        -- TOTAL REVENUE (from sales table)
        SELECT 
            'total_revenue' as category,
            'Total Sales Revenue' as description,
            COALESCE(SUM(s.total_revenue), 0) as amount,
            0 as is_expense,
            1 as display_order
        FROM sales s
        $whereClause
        
        UNION ALL
        
        -- Cost of Goods Sold (Vehicle purchase costs)
        SELECT 
            'cogs' as category,
            'Cost of Goods Sold' as description,
            COALESCE(SUM(v.purchase_price), 0) as amount,
            1 as is_expense,
            2 as display_order
        FROM sales s
        JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        $whereClause
        
        UNION ALL
        
        -- TOTAL OPERATING COSTS (from sales table)
        SELECT 
            'total_costs' as category,
            'Total Operating Costs' as description,
            COALESCE(SUM(s.total_cost), 0) as amount,
            1 as is_expense,
            3 as display_order
        FROM sales s
        $whereClause
        
        UNION ALL
        
        -- NET PROFIT (from sales table - this is the final profit)
        SELECT 
            'net_profit' as category,
            'Net Profit/Loss' as description,
            COALESCE(SUM(s.net_profit), 0) as amount,
            CASE WHEN COALESCE(SUM(s.net_profit), 0) >= 0 THEN 0 ELSE 1 END as is_expense,
            4 as display_order
        FROM sales s
        $whereClause
        
        UNION ALL
        
        -- Other operating expenses (general business expenses not tied to vehicles)
        SELECT 
            'other_expenses' as category,
            'Other Operating Expenses' as description,
            COALESCE(SUM(amount), 0) as amount,
            1 as is_expense,
            5 as display_order
        FROM expenses
        $expensesWhere
        AND vehicle_id IS NULL
    ");

    // Combine all parameters (sales + expenses)
    $allParams = array_merge($params, $params, $params, $params, $expensesParams);

    $query->execute($allParams);
    $result = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Sort by display_order
    usort($result, function($a, $b) {
        return $a['display_order'] <=> $b['display_order'];
    });
    
    return $result;
}

function generateProfitLossReport($db, $filters = [])
{
    // Get the P&L data
    $plData = getProfitLossData($db, $filters);

    // Initialize totals
    $totals = [
        'total_revenue' => 0,
        'total_cogs' => 0,
        'total_operating_costs' => 0,
        'total_other_expenses' => 0,
        'total_discounts' => 0,
        'gross_profit' => 0,
        'total_expenses' => 0,
        'net_profit' => 0
    ];

    // Calculate totals from P&L data
    foreach ($plData as $item) {
        switch ($item['category']) {
            case 'net_profit':
                $totals['net_profit'] = $item['amount'];
                break;
            case 'total_revenue':
                $totals['total_revenue'] = $item['amount'];
                break;
            case 'cogs':
                $totals['total_cogs'] = $item['amount'];
                break;
            case 'total_costs':
                $totals['total_operating_costs'] = $item['amount'];
                break;
            case 'other_expenses':
                $totals['total_other_expenses'] += $item['amount'];
                break;
            case 'discounts':
                $totals['total_discounts'] = $item['amount'];
                break;
            case 'vehicle_expenses':
            case 'direct_repairs':
                // These are already included in total_costs from sales table
                // We're just showing them for breakdown purposes
                break;
        }
    }

    // Calculate derived totals
    $totals['gross_profit'] = $totals['total_revenue'] - $totals['total_cogs'];
    $totals['total_expenses'] = $totals['total_operating_costs'];
    //$totals['net_profit'] = $totals['total_revenue'] - $totals['total_expenses'];

    // Calculate percentages
    $percentages = [
        'gross_margin' => $totals['total_revenue'] > 0 ? ($totals['gross_profit'] / $totals['total_revenue']) * 100 : 0,
        'net_margin' => $totals['total_revenue'] > 0 ? ($totals['net_profit'] / $totals['total_revenue']) * 100 : 0,
        'cogs_percentage' => $totals['total_revenue'] > 0 ? ($totals['total_cogs'] / $totals['total_revenue']) * 100 : 0,
        'operating_costs_percentage' => $totals['total_revenue'] > 0 ? ($totals['total_expenses'] / $totals['total_revenue']) * 100 : 0
    ];

    return [
        'pl_data' => $plData,
        'totals' => $totals,
        'percentages' => $percentages,
        'filters' => $filters
    ];
}

function getProfitLossClass($amount)
{
    if ($amount > 0) {
        return 'text-success';
    } elseif ($amount < 0) {
        return 'text-danger';
    } else {
        return 'text-warning';
    }
}

function getPLCategoryClass($category)
{
    switch ($category) {
        case 'revenue':
            return 'bg-success';
        case 'cogs':
            return 'bg-danger';
        case 'repairs_sold':
            return 'bg-warning';
        case 'repairs_unsold':
            return 'bg-warning';
        case 'other_expenses':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

/**
 * ROI by Vehicle Report Functions
 */

function getROIByVehicleData($db, $filters = [])
{
    $whereConditions = [];
    $params = [];

    // Date filters for sales
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "(s.sale_date >= ? OR s.sale_date IS NULL)";
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "(s.sale_date <= ? OR s.sale_date IS NULL)";
        $params[] = $filters['end_date'];
    }

    // Vehicle status filter
    if (!empty($filters['vehicle_status'])) {
        $whereConditions[] = "v.status = ?";
        $params[] = $filters['vehicle_status'];
    }

    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }

    $query = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            v.purchase_price,
            v.purchase_date,
            v.status,
            s.sale_id,
            s.sale_date,
            
            -- Core financial metrics from sales table
            COALESCE(s.total_revenue, 0) as total_revenue,
            COALESCE(s.total_cost, 0) as total_cost,
            COALESCE(s.net_profit, 0) as net_profit,
            
            -- PRIMARY ROI METRIC: ROI on Purchase Price
            CASE 
                WHEN v.status = 'sold' AND v.purchase_price > 0 THEN 
                    ROUND((COALESCE(s.net_profit, 0) / v.purchase_price) * 100, 1)
                ELSE NULL 
            END as roi_percentage,
            
            -- Secondary useful metrics
            CASE 
                WHEN v.status = 'sold' AND v.purchase_price > 0 THEN 
                    ROUND((COALESCE(s.total_cost, 0) / v.purchase_price) * 100, 1)
                ELSE NULL 
            END as cost_ratio_percentage,
            
            CASE 
                WHEN v.status = 'sold' AND COALESCE(s.total_revenue, 0) > 0 THEN 
                    ROUND((COALESCE(s.net_profit, 0) / COALESCE(s.total_revenue, 0)) * 100, 1)
                ELSE NULL 
            END as profit_margin_percentage,
            
            -- Days in inventory
            CASE 
                WHEN s.sale_date IS NOT NULL THEN 
                    DATEDIFF(s.sale_date, v.purchase_date)
                ELSE 
                    DATEDIFF(CURRENT_DATE, v.purchase_date)
            END as days_in_inventory,
            
            -- Profit per day (efficiency metric)
            CASE 
                WHEN v.status = 'sold' AND s.sale_date IS NOT NULL AND 
                     DATEDIFF(s.sale_date, v.purchase_date) > 0 THEN
                    ROUND(COALESCE(s.net_profit, 0) / DATEDIFF(s.sale_date, v.purchase_date), 2)
                ELSE NULL 
            END as profit_per_day,
            
            -- ROI Category based on ROI on Purchase Price
            CASE 
                WHEN v.status = 'sold' AND v.purchase_price > 0 THEN 
                    CASE 
                        WHEN (COALESCE(s.net_profit, 0) / v.purchase_price) * 100 > 30 THEN 'Excellent'
                        WHEN (COALESCE(s.net_profit, 0) / v.purchase_price) * 100 > 15 THEN 'Good'
                        WHEN (COALESCE(s.net_profit, 0) / v.purchase_price) * 100 > 0 THEN 'Break-even'
                        ELSE 'Loss'
                    END
                WHEN v.status != 'sold' THEN 'Pending Sale'
                ELSE 'N/A'
            END as roi_category,
            
            -- Inventory age category
            CASE 
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 30 THEN '0-30 days'
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 60 THEN '31-60 days'
                WHEN DATEDIFF(CURRENT_DATE, v.purchase_date) <= 90 THEN '61-90 days'
                ELSE '90+ days'
            END as inventory_age_category
            
        FROM vehicles v
        LEFT JOIN sales s ON v.vehicle_id = s.vehicle_id
        $whereClause
        ORDER BY 
            CASE WHEN v.status = 'sold' THEN 1 ELSE 2 END,
            roi_percentage DESC
    ");

    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function generateROIByVehicleReport($db, $filters = [])
{
    // Get the ROI data
    $roiData = getROIByVehicleData($db, $filters);

    // Calculate summary by ROI categories
    $roiSummary = [
        'Excellent' => ['count' => 0, 'total_profit' => 0, 'avg_roi' => 0, 'avg_profit_per_day' => 0, 'total_investment' => 0],
        'Good' => ['count' => 0, 'total_profit' => 0, 'avg_roi' => 0, 'avg_profit_per_day' => 0, 'total_investment' => 0],
        'Break-even' => ['count' => 0, 'total_profit' => 0, 'avg_roi' => 0, 'avg_profit_per_day' => 0, 'total_investment' => 0],
        'Loss' => ['count' => 0, 'total_profit' => 0, 'avg_roi' => 0, 'avg_profit_per_day' => 0, 'total_investment' => 0],
        'Pending Sale' => ['count' => 0, 'total_investment' => 0, 'avg_days' => 0, 'avg_age_category' => '']
    ];

    $totalSummary = [
        'total_vehicles' => count($roiData),
        'sold_vehicles' => 0,
        'unsold_vehicles' => 0,
        'total_purchase_investment' => 0,
        'total_costs' => 0,
        'total_revenue' => 0,
        'total_profit' => 0,
        'avg_roi' => 0,
        'avg_cost_ratio' => 0,
        'avg_profit_margin' => 0,
        'best_roi' => 0,
        'worst_roi' => 0,
        'avg_profit_per_day' => 0,
        'avg_days_to_sell' => 0
    ];

    // Track inventory aging for unsold vehicles
    $inventoryAging = [
        '0-30 days' => ['count' => 0, 'total_investment' => 0],
        '31-60 days' => ['count' => 0, 'total_investment' => 0],
        '61-90 days' => ['count' => 0, 'total_investment' => 0],
        '90+ days' => ['count' => 0, 'total_investment' => 0]
    ];

    $roiValues = [];
    $costRatioValues = [];
    $profitMarginValues = [];
    $profitPerDayValues = [];
    $daysToSell = [];

    foreach ($roiData as $vehicle) {
        // Update ROI category summary
        if (isset($roiSummary[$vehicle['roi_category']])) {
            $roiSummary[$vehicle['roi_category']]['count']++;

            if ($vehicle['status'] == 'sold') {
                $roiSummary[$vehicle['roi_category']]['total_profit'] += $vehicle['net_profit'];
                $roiSummary[$vehicle['roi_category']]['total_investment'] += $vehicle['purchase_price'];
                
                if ($vehicle['roi_percentage'] !== null) {
                    $roiSummary[$vehicle['roi_category']]['avg_roi'] += $vehicle['roi_percentage'];
                }
                
                if ($vehicle['profit_per_day'] !== null) {
                    $roiSummary[$vehicle['roi_category']]['avg_profit_per_day'] += $vehicle['profit_per_day'];
                }
            } else {
                // For pending sales
                $roiSummary[$vehicle['roi_category']]['total_profit'] = 0;
                $roiSummary[$vehicle['roi_category']]['total_investment'] += $vehicle['purchase_price'];
                $roiSummary[$vehicle['roi_category']]['avg_days'] += $vehicle['days_in_inventory'];
                
                // Track inventory aging
                if (isset($inventoryAging[$vehicle['inventory_age_category']])) {
                    $inventoryAging[$vehicle['inventory_age_category']]['count']++;
                    $inventoryAging[$vehicle['inventory_age_category']]['total_investment'] += $vehicle['purchase_price'];
                }
            }
        }

        // Update total summary
        $totalSummary['total_purchase_investment'] += $vehicle['purchase_price'];
        $totalSummary['total_costs'] += $vehicle['total_cost'];
        $totalSummary['total_revenue'] += $vehicle['total_revenue'];

        if ($vehicle['status'] == 'sold') {
            $totalSummary['sold_vehicles']++;
            $totalSummary['total_profit'] += $vehicle['net_profit'];
            
            // Collect values for averages
            if ($vehicle['roi_percentage'] !== null) {
                $roiValues[] = $vehicle['roi_percentage'];
            }
            
            if ($vehicle['cost_ratio_percentage'] !== null) {
                $costRatioValues[] = $vehicle['cost_ratio_percentage'];
            }
            
            if ($vehicle['profit_margin_percentage'] !== null) {
                $profitMarginValues[] = $vehicle['profit_margin_percentage'];
            }
            
            if ($vehicle['profit_per_day'] !== null) {
                $profitPerDayValues[] = $vehicle['profit_per_day'];
            }
            
            // Collect days to sell
            $daysToSell[] = $vehicle['days_in_inventory'];
        } else {
            $totalSummary['unsold_vehicles']++;
        }
    }

    // Calculate averages for ROI categories
    foreach ($roiSummary as $category => &$summary) {
        if ($summary['count'] > 0) {
            if (in_array($category, ['Excellent', 'Good', 'Break-even', 'Loss'])) {
                // For sold vehicles
                $summary['avg_roi'] = $summary['count'] > 0 ? $summary['avg_roi'] / $summary['count'] : 0;
                $summary['avg_profit_per_day'] = $summary['count'] > 0 ? $summary['avg_profit_per_day'] / $summary['count'] : 0;
            } else {
                // For pending sales
                $summary['avg_days'] = $summary['count'] > 0 ? $summary['avg_days'] / $summary['count'] : 0;
            }
        }
    }

    // Calculate overall statistics - ONLY FOR SOLD VEHICLES
    if ($totalSummary['sold_vehicles'] > 0) {
        if (!empty($roiValues)) {
            $totalSummary['avg_roi'] = array_sum($roiValues) / count($roiValues);
            $totalSummary['best_roi'] = max($roiValues);
            $totalSummary['worst_roi'] = min($roiValues);
        }
        
        if (!empty($costRatioValues)) {
            $totalSummary['avg_cost_ratio'] = array_sum($costRatioValues) / count($costRatioValues);
        }
        
        if (!empty($profitMarginValues)) {
            $totalSummary['avg_profit_margin'] = array_sum($profitMarginValues) / count($profitMarginValues);
        }
        
        if (!empty($profitPerDayValues)) {
            $totalSummary['avg_profit_per_day'] = array_sum($profitPerDayValues) / count($profitPerDayValues);
        }
        
        if (!empty($daysToSell)) {
            $totalSummary['avg_days_to_sell'] = array_sum($daysToSell) / count($daysToSell);
        }
        
        // Calculate overall ROI on purchase investment
        if ($totalSummary['total_purchase_investment'] > 0) {
            $totalSummary['overall_roi'] = ($totalSummary['total_profit'] / $totalSummary['total_purchase_investment']) * 100;
        }
    }

    return [
        'roi_data' => $roiData,
        'roi_summary' => $roiSummary,
        'inventory_aging' => $inventoryAging,
        'total_summary' => $totalSummary,
        'filters' => $filters
    ];
}

function getROICategoryClass($category)
{
    switch ($category) {
        case 'Excellent':
            return 'bg-success';
        case 'Good':
            return 'bg-info';
        case 'Break-even':
            return 'bg-warning';
        case 'Loss':
            return 'bg-danger';
        case 'Pending Sale':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

function getROIPercentageClass($roi)
{
    if ($roi > 30) return 'text-success';
    if ($roi > 15) return 'text-info';
    if ($roi > 0) return 'text-warning';
    return 'text-danger';
}

function getProfitClass($profit)
{
    if ($profit > 0) return 'text-success';
    if ($profit < 0) return 'text-danger';
    return 'text-warning';
}

/**
 * Workshop Efficiency Report Functions
 */

function getWorkshopEfficiencyData($db, $filters = []) {
    $whereConditions = [];
    $params = [];
    
    // Date filters
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "jc.created_at >= ?";
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "jc.created_at <= ?";
        $params[] = $filters['end_date'];
    }
    
    // Status filter
    if (!empty($filters['job_card_status'])) {
        $whereConditions[] = "jc.status = ?";
        $params[] = $filters['job_card_status'];
    }
    
    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query = $db->prepare("
        SELECT 
            jc.job_card_id,
            jc.job_card_number,
            jc.priority,
            jc.status,
            jc.date_started,
            jc.date_completed,
            jc.created_at,
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            u.full_name as assigned_to,
            -- Task counts
            (SELECT COUNT(*) FROM job_card_task_progress jctp 
             WHERE jctp.job_card_id = jc.job_card_id) as total_tasks,
            (SELECT COUNT(*) FROM job_card_task_progress jctp 
             WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM job_card_task_progress jctp 
             WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'cancelled') as cancelled_tasks,
            -- Repair costs
            COALESCE((
                SELECT SUM(jctp.cost) 
                FROM job_card_task_progress jctp 
                WHERE jctp.job_card_id = jc.job_card_id
                AND jctp.cost > 0
            ), 0) as repair_costs,
            -- Completion time - USING created_at as fallback
            CASE 
                WHEN jc.date_completed IS NOT NULL THEN 
                    DATEDIFF(jc.date_completed, COALESCE(jc.date_started, jc.created_at))
                ELSE 
                    DATEDIFF(CURRENT_DATE, COALESCE(jc.date_started, jc.created_at))
            END as days_taken,
            -- Completion rate
            CASE 
                WHEN (SELECT COUNT(*) FROM job_card_task_progress jctp 
                      WHERE jctp.job_card_id = jc.job_card_id) > 0 THEN
                    ROUND(((SELECT COUNT(*) FROM job_card_task_progress jctp 
                           WHERE jctp.job_card_id = jc.job_card_id AND jctp.status IN ('completed', 'cancelled')) / 
                          (SELECT COUNT(*) FROM job_card_task_progress jctp 
                           WHERE jctp.job_card_id = jc.job_card_id)) * 100, 1)
                ELSE 0 
            END as completion_rate,
            -- Efficiency rating - USING created_at as fallback
            CASE 
                WHEN jc.status = 'Completed' THEN 
                    CASE 
                        WHEN DATEDIFF(jc.date_completed, COALESCE(jc.date_started, jc.created_at)) <= 3 THEN 'Excellent'
                        WHEN DATEDIFF(jc.date_completed, COALESCE(jc.date_started, jc.created_at)) <= 7 THEN 'Good'
                        WHEN DATEDIFF(jc.date_completed, COALESCE(jc.date_started, jc.created_at)) <= 14 THEN 'Average'
                        ELSE 'Poor'
                    END
                ELSE 'In Progress'
            END as efficiency_rating
        FROM job_cards jc
        LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON jc.assigned_to = u.id
        $whereClause
        ORDER BY jc.created_at DESC
    ");
    
    $query->execute($params);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function generateWorkshopEfficiencyReport($db, $filters = [])
{
    // Get the workshop data
    $workshopData = getWorkshopEfficiencyData($db, $filters);

    // Calculate summary metrics
    $summary = [
        'total_job_cards' => count($workshopData),
        'completed_job_cards' => 0,
        'in_progress_job_cards' => 0,
        'pending_job_cards' => 0,
        'total_repair_costs' => 0,
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'avg_completion_rate' => 0,
        'avg_days_taken' => 0
    ];

    $efficiencySummary = [
        'Excellent' => ['count' => 0, 'avg_days' => 0],
        'Good' => ['count' => 0, 'avg_days' => 0],
        'Average' => ['count' => 0, 'avg_days' => 0],
        'Poor' => ['count' => 0, 'avg_days' => 0],
        'In Progress' => ['count' => 0, 'avg_days' => 0]
    ];

    $completionRates = [];
    $completionTimes = [];

    foreach ($workshopData as $job) {
        // Update status counts
        switch ($job['status']) {
            case 'Completed':
                $summary['completed_job_cards']++;
                break;
            case 'In Progress':
                $summary['in_progress_job_cards']++;
                break;
            case 'Pending':
                $summary['pending_job_cards']++;
                break;
        }

        // Update totals
        $summary['total_repair_costs'] += $job['repair_costs'];
        $summary['total_tasks'] += $job['total_tasks'];
        $summary['completed_tasks'] += $job['completed_tasks'];

        // Update efficiency summary
        if (isset($efficiencySummary[$job['efficiency_rating']])) {
            $efficiencySummary[$job['efficiency_rating']]['count']++;
            if ($job['days_taken'] > 0) {
                $efficiencySummary[$job['efficiency_rating']]['avg_days'] += $job['days_taken'];
            }
        }

        // Collect data for averages
        $completionRates[] = $job['completion_rate'];
        if ($job['status'] == 'Completed') {
            $completionTimes[] = $job['days_taken'];
        }
    }

    // Calculate averages
    if (!empty($completionRates)) {
        $summary['avg_completion_rate'] = array_sum($completionRates) / count($completionRates);
    }

    if (!empty($completionTimes)) {
        $summary['avg_days_taken'] = array_sum($completionTimes) / count($completionTimes);
    }

    // Calculate average days for each efficiency rating
    foreach ($efficiencySummary as $rating => &$data) {
        if ($data['count'] > 0 && $data['avg_days'] > 0) {
            $data['avg_days'] = $data['avg_days'] / $data['count'];
        }
    }

    // Calculate cost per completed job card
    $summary['avg_cost_per_job'] = $summary['completed_job_cards'] > 0 ?
        $summary['total_repair_costs'] / $summary['completed_job_cards'] : 0;

    // Calculate task completion rate
    $summary['overall_task_completion_rate'] = $summary['total_tasks'] > 0 ?
        ($summary['completed_tasks'] / $summary['total_tasks']) * 100 : 0;

    return [
        'workshop_data' => $workshopData,
        'summary' => $summary,
        'efficiency_summary' => $efficiencySummary,
        'filters' => $filters
    ];
}

function getEfficiencyRatingClass($rating)
{
    switch ($rating) {
        case 'Excellent':
            return 'bg-success';
        case 'Good':
            return 'bg-info';
        case 'Average':
            return 'bg-warning';
        case 'Poor':
            return 'bg-danger';
        case 'In Progress':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

function getPriorityClass($priority)
{
    switch ($priority) {
        case 'Urgent':
            return 'bg-danger';
        case 'High':
            return 'bg-warning';
        case 'Medium':
            return 'bg-info';
        case 'Low':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

function getStatusClass($status)
{
    switch ($status) {
        case 'Completed':
            return 'bg-success';
        case 'In Progress':
            return 'bg-warning';
        case 'Pending':
            return 'bg-info';
        case 'Cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function getWorkshopDaysClass($days)
{
    if ($days <= 3) return 'text-success';
    if ($days <= 7) return 'text-info';
    if ($days <= 14) return 'text-warning';
    return 'text-danger';
}
