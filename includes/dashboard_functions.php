<?php
/**
 * Dashboard Metrics Functions
 * Professional car trade business metrics
 */

include_once("cost_calculations.php");

// getCurrentUserSalesMetrics($db, $userId) - Fetches sales metrics for the current user



function getUserSalesDashboardMetrics($db, $userId)
{
    $metrics = [];

    /*
    |--------------------------------------------------------------------------
    | CURRENT MONTH
    |--------------------------------------------------------------------------
    */

    $currentQuery = $db->prepare("
        SELECT
            COUNT(DISTINCT s.sale_id) AS sold_cars,

            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sales,

            COALESCE(
                (
                    SELECT SUM(sc.commission_amount)
                    FROM sales_commissions sc
                    WHERE sc.user_id = s.sales_person
                    AND sc.status != 'cancelled'
                    AND YEAR(sc.created_at) = YEAR(CURDATE())
                    AND MONTH(sc.created_at) = MONTH(CURDATE())
                ),
                0
            ) AS total_commission

        FROM sales s

        WHERE s.sales_person = ?
        AND YEAR(s.sale_date) = YEAR(CURDATE())
        AND MONTH(s.sale_date) = MONTH(CURDATE())
        AND s.status NOT IN ('cancelled', 'refunded', 'pending')
    ");

    $currentQuery->execute([$userId]);

    $metrics['current_month'] = $currentQuery->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | PREVIOUS MONTH
    |--------------------------------------------------------------------------
    */

    $previousQuery = $db->prepare("
        SELECT
            COUNT(DISTINCT s.sale_id) AS sold_cars,

            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sales,

            COALESCE(
                (
                    SELECT SUM(sc.commission_amount)
                    FROM sales_commissions sc
                    WHERE sc.user_id = s.sales_person
                    AND sc.status != 'cancelled'
                    AND YEAR(sc.created_at) =
                        YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                    AND MONTH(sc.created_at) =
                        MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                ),
                0
            ) AS total_commission

        FROM sales s

        WHERE s.sales_person = ?
        AND YEAR(s.sale_date) =
            YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(s.sale_date) =
            MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND s.status NOT IN ('cancelled', 'refunded', 'pending')
    ");

    $previousQuery->execute([$userId]);

    $metrics['previous_month'] = $previousQuery->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | ALL TIME TOTAL
    |--------------------------------------------------------------------------
    */

    $totalQuery = $db->prepare("
        SELECT
            COUNT(DISTINCT s.sale_id) AS sold_cars,

            COALESCE(
                SUM(s.total_revenue),
                0
            ) AS total_sales

        FROM sales s

        WHERE s.sales_person = ?
        AND s.status NOT IN ('cancelled', 'refunded', 'pending')
    ");

    $totalQuery->execute([$userId]);

    $metrics['total'] = $totalQuery->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | ALL TIME COMMISSION
    |--------------------------------------------------------------------------
    */

    $commissionQuery = $db->prepare("
        SELECT
            COALESCE(
                SUM(commission_amount),
                0
            ) AS total_commission

        FROM sales_commissions

        WHERE user_id = ?
        AND status != 'cancelled'
    ");

    $commissionQuery->execute([$userId]);

    $commission = $commissionQuery->fetch(PDO::FETCH_ASSOC);

    $metrics['total']['total_commission'] =
        (float)$commission['total_commission'];


    /*
    |--------------------------------------------------------------------------
    | CURRENT MONTH AVERAGE SALE
    |--------------------------------------------------------------------------
    */

    $currentCars =
        (int)$metrics['current_month']['sold_cars'];

    $currentSales =
        (float)$metrics['current_month']['total_sales'];

    $metrics['current_month']['average_sale'] =
        $currentCars > 0
            ? $currentSales / $currentCars
            : 0;


    /*
    |--------------------------------------------------------------------------
    | PREVIOUS MONTH AVERAGE SALE
    |--------------------------------------------------------------------------
    */

    $previousCars =
        (int)$metrics['previous_month']['sold_cars'];

    $previousSales =
        (float)$metrics['previous_month']['total_sales'];

    $metrics['previous_month']['average_sale'] =
        $previousCars > 0
            ? $previousSales / $previousCars
            : 0;


    /*
    |--------------------------------------------------------------------------
    | COMPARISON
    |--------------------------------------------------------------------------
    */

    $current = $metrics['current_month'];
    $previous = $metrics['previous_month'];


    // Absolute differences

    $metrics['comparison']['sold_cars'] =
        (int)$current['sold_cars']
        - (int)$previous['sold_cars'];

    $metrics['comparison']['total_sales'] =
        (float)$current['total_sales']
        - (float)$previous['total_sales'];

    $metrics['comparison']['total_commission'] =
        (float)$current['total_commission']
        - (float)$previous['total_commission'];

    $metrics['comparison']['average_sale'] =
        (float)$current['average_sale']
        - (float)$previous['average_sale'];


        
    /*
    |--------------------------------------------------------------------------
    | PERCENTAGE CHANGES
    |--------------------------------------------------------------------------
    */

// 1. CARS SOLD
$metrics['comparison']['sold_cars_percentage'] =
    $previous['sold_cars'] > 0
        ? (
            (
                $current['sold_cars'] - $previous['sold_cars']
            )
            / $previous['sold_cars']
        ) * 100
        : ($current['sold_cars'] > 0 ? 100 : 0);


// 2. TOTAL SALES
$metrics['comparison']['total_sales_percentage'] =
    $previous['total_sales'] > 0
        ? (
            (
                $current['total_sales'] - $previous['total_sales']
            )
            / $previous['total_sales']
        ) * 100
        : ($current['total_sales'] > 0 ? 100 : 0);


// 3. COMMISSION
$metrics['comparison']['total_commission_percentage'] =
    $previous['total_commission'] > 0
        ? (
            (
                $current['total_commission'] - $previous['total_commission']
            )
            / $previous['total_commission']
        ) * 100
        : ($current['total_commission'] > 0 ? 100 : 0);


// 4. AVERAGE SALE
$metrics['comparison']['average_sale_percentage'] =
    $previous['average_sale'] > 0
        ? (
            (
                $current['average_sale'] - $previous['average_sale']
            )
            / $previous['average_sale']
        ) * 100
        : ($current['average_sale'] > 0 ? 100 : 0);


    return $metrics;
}

function getSalesPerformanceMetrics($db) {
    $metrics = [];
    
    // 1. Sales Conversion Rate - REFUND AWARE
    $conversionQuery = $db->prepare("
        SELECT 
            -- Sold vehicles (excluding refunded)
            COALESCE((SELECT COUNT(*) FROM sales 
             WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
             AND status NOT IN ('refunded', 'cancelled')), 0) as sold_ytd,
            
            -- Refunded vehicles
            COALESCE((SELECT COUNT(*) FROM sales 
             WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
             AND status IN ('refunded', 'partially_refunded')), 0) as refunded_ytd,
            
            -- Current stock (excluding sold and refunded to customer)
            COALESCE((SELECT COUNT(*) FROM vehicles 
             WHERE status NOT IN ('sold', 'refunded_to_customer')), 0) as current_stock,
            
            -- Net conversion rate: sold ÷ (sold + unsold)
            COALESCE(((SELECT COALESCE(COUNT(*), 0) FROM sales 
              WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
              AND status NOT IN ('refunded', 'cancelled')) / 
             NULLIF((
                 (SELECT COALESCE(COUNT(*), 0) FROM sales 
                  WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
                  AND status NOT IN ('refunded', 'cancelled'))
                 +
                 (SELECT COALESCE(COUNT(*), 0) FROM vehicles 
                  WHERE status NOT IN ('sold', 'refunded_to_customer'))
             ), 0)) * 100, 0) as net_conversion_rate,
            
            -- Refund rate: refunded ÷ total sales
            COALESCE(((SELECT COALESCE(COUNT(*), 0) FROM sales 
              WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
              AND status IN ('refunded', 'partially_refunded')) / 
             NULLIF((SELECT COALESCE(COUNT(*), 0) FROM sales 
                     WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)), 0)) * 100, 0) as refund_rate
    ");
    $conversionQuery->execute();
    $metrics['conversion'] = $conversionQuery->fetch(PDO::FETCH_ASSOC);
    
    // 2. Average Days to Sell - EXCLUDE REFUNDED SALES
    $daysToSellQuery = $db->prepare("
        SELECT 
            COALESCE(AVG(DATEDIFF(s.sale_date, v.purchase_date)), 0) as avg_days_to_sell,
            COALESCE(MAX(DATEDIFF(s.sale_date, v.purchase_date)), 0) as max_days_to_sell,
            COALESCE(MIN(DATEDIFF(s.sale_date, v.purchase_date)), 0) as min_days_to_sell
        FROM sales s
        JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        WHERE YEAR(s.sale_date) = YEAR(CURRENT_DATE)
        AND s.sale_date >= v.purchase_date
        AND s.status NOT IN ('refunded', 'cancelled')
    ");
    $daysToSellQuery->execute();
    $metrics['days_to_sell'] = $daysToSellQuery->fetch(PDO::FETCH_ASSOC);
    
    // 3. Gross Profit Margin - REFUND AWARE
    $grossMarginQuery = $db->prepare("
        SELECT 
            -- Gross profit after refunds
            COALESCE(SUM(
                CASE 
                    WHEN s.status = 'refunded' THEN 0
                    WHEN s.status = 'partially_refunded' THEN 
                        ((s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - COALESCE(s.total_refunded, 0)) - v.purchase_price
                    ELSE 
                        (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - v.purchase_price
                END
            ), 0) as gross_profit_after_refunds,
            
            -- Gross margin after refunds
            COALESCE(
                CASE 
                    WHEN SUM(
                        CASE 
                            WHEN s.status = 'refunded' THEN 0
                            WHEN s.status = 'partially_refunded' THEN 
                                (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - COALESCE(s.total_refunded, 0)
                            ELSE 
                                (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount)
                        END
                    ) > 0 THEN 
                        (SUM(
                            CASE 
                                WHEN s.status = 'refunded' THEN 0
                                WHEN s.status = 'partially_refunded' THEN 
                                    ((s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - COALESCE(s.total_refunded, 0)) - v.purchase_price
                                ELSE 
                                    (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - v.purchase_price
                            END
                        ) / 
                        SUM(
                            CASE 
                                WHEN s.status = 'refunded' THEN 0
                                WHEN s.status = 'partially_refunded' THEN 
                                    (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - COALESCE(s.total_refunded, 0)
                                ELSE 
                                    (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount)
                            END
                        )) * 100 
                    ELSE 0 
                END, 0) as gross_margin_after_refunds,
            
            -- Average gross per vehicle after refunds
            COALESCE(AVG(
                CASE 
                    WHEN s.status = 'refunded' THEN 0 - v.purchase_price
                    WHEN s.status = 'partially_refunded' THEN 
                        ((s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - COALESCE(s.total_refunded, 0)) - v.purchase_price
                    ELSE 
                        (s.sale_price + s.service_fee + s.delivery_charges - s.discount_amount) - v.purchase_price
                END
            ), 0) as avg_gross_per_vehicle_after_refunds
        FROM sales s
        JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        WHERE YEAR(s.sale_date) = YEAR(CURRENT_DATE)
    ");
    $grossMarginQuery->execute();
    $metrics['gross_margin'] = $grossMarginQuery->fetch(PDO::FETCH_ASSOC);
    
    // 4. Vehicle Acquisition Rate - REFUND AWARE
    $acquisitionQuery = $db->prepare("
        SELECT 
            -- Vehicles acquired YTD (excluding those returned via refund)
            COALESCE(COUNT(*), 0) as vehicles_acquired_ytd,
            
            -- Net monthly acquisition
            COALESCE((SELECT COUNT(*) FROM vehicles 
             WHERE (
                 (YEAR(purchase_date) = YEAR(CURRENT_DATE) AND MONTH(purchase_date) = MONTH(CURRENT_DATE))
                 OR 
                 (status = 'part_exchange' AND YEAR(COALESCE(created_at, purchase_date)) = YEAR(CURRENT_DATE) 
                  AND MONTH(COALESCE(created_at, purchase_date)) = MONTH(CURRENT_DATE))
             )
             AND vehicle_id NOT IN (
                 SELECT vehicle_id FROM sales 
                 WHERE status = 'refunded' 
                 AND YEAR(sale_date) = YEAR(CURRENT_DATE)
             )), 0) as monthly_acquisition,
            
            -- Vehicles returned via refund YTD
            COALESCE((SELECT COUNT(*) FROM vehicles 
             WHERE status = 'refunded_to_customer'
             AND YEAR(COALESCE(updated_at, created_at)) = YEAR(CURRENT_DATE)), 0) as vehicles_returned_ytd
        FROM vehicles 
        WHERE (
            (YEAR(purchase_date) = YEAR(CURRENT_DATE))
            OR 
            (status = 'part_exchange' AND YEAR(COALESCE(created_at, purchase_date)) = YEAR(CURRENT_DATE))
        )
        AND vehicle_id NOT IN (
            SELECT vehicle_id FROM sales 
            WHERE status = 'refunded' 
            AND YEAR(sale_date) = YEAR(CURRENT_DATE)
        )
    ");
    $acquisitionQuery->execute();
    $metrics['acquisition'] = $acquisitionQuery->fetch(PDO::FETCH_ASSOC);
    
    // 5. Net Profit with All Costs - REFUND AWARE
    $netMarginQuery = $db->prepare("
        SELECT 
            -- Net profit after all costs and refunds
            COALESCE(SUM(
                CASE 
                    WHEN s.status = 'refunded' THEN 
                        0 - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0) - COALESCE(s.total_refunded, 0)
                    WHEN s.status = 'partially_refunded' THEN 
                        ((s.sale_price - COALESCE(s.total_refunded, 0)) - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0))
                    ELSE 
                        s.sale_price - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0)
                END
            ), 0) as net_profit_after_refunds,
            
            -- Net margin after refunds
            COALESCE(
                CASE 
                    WHEN SUM(
                        CASE 
                            WHEN s.status = 'refunded' THEN 0
                            WHEN s.status = 'partially_refunded' THEN s.sale_price - COALESCE(s.total_refunded, 0)
                            ELSE s.sale_price
                        END
                    ) > 0 THEN 
                        (SUM(
                            CASE 
                                WHEN s.status = 'refunded' THEN 
                                    0 - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0) - COALESCE(s.total_refunded, 0)
                                WHEN s.status = 'partially_refunded' THEN 
                                    ((s.sale_price - COALESCE(s.total_refunded, 0)) - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0))
                                ELSE 
                                    s.sale_price - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0)
                            END
                        ) / 
                        SUM(
                            CASE 
                                WHEN s.status = 'refunded' THEN 0
                                WHEN s.status = 'partially_refunded' THEN s.sale_price - COALESCE(s.total_refunded, 0)
                                ELSE s.sale_price
                            END
                        )) * 100 
                    ELSE 0 
                END, 0) as net_margin_after_refunds,
            
            -- Average net per vehicle after refunds
            COALESCE(AVG(
                CASE 
                    WHEN s.status = 'refunded' THEN 
                        0 - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0) - COALESCE(s.total_refunded, 0)
                    WHEN s.status = 'partially_refunded' THEN 
                        ((s.sale_price - COALESCE(s.total_refunded, 0)) - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0))
                    ELSE 
                        s.sale_price - v.purchase_price - COALESCE(jctp.total_costs, 0) - COALESCE(rep.total_repair_costs, 0) - COALESCE(exp.total_expense_costs, 0)
                END
            ), 0) as avg_net_per_vehicle_after_refunds
        FROM sales s
        JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN (
            SELECT jc.vehicle_id, SUM(COALESCE(jctp.cost, 0)) as total_costs
            FROM job_card_task_progress jctp
            JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
            WHERE jctp.cost > 0
            GROUP BY jc.vehicle_id
        ) jctp ON v.vehicle_id = jctp.vehicle_id
        LEFT JOIN (
            SELECT vehicle_id, SUM(COALESCE(cost, 0)) as total_repair_costs
            FROM repairs 
            WHERE cost > 0
            GROUP BY vehicle_id
        ) rep ON v.vehicle_id = rep.vehicle_id
        LEFT JOIN (
            SELECT vehicle_id, SUM(COALESCE(amount, 0)) as total_expense_costs
            FROM expenses 
            WHERE amount > 0
            GROUP BY vehicle_id
        ) exp ON v.vehicle_id = exp.vehicle_id
        WHERE YEAR(s.sale_date) = YEAR(CURRENT_DATE)
    ");
    $netMarginQuery->execute();
    $metrics['net_margin'] = $netMarginQuery->fetch(PDO::FETCH_ASSOC);
    
    // 6. Total Refunds Summary
    $refundSummaryQuery = $db->prepare("
        SELECT 
            COALESCE(COUNT(*), 0) as total_refunds_ytd,
            COALESCE(SUM(net_refund_amount), 0) as total_refund_amount_ytd,
            COALESCE(AVG(net_refund_amount), 0) as avg_refund_amount,
            COALESCE((SELECT COUNT(*) FROM refunds 
             WHERE status = 'pending' 
             AND YEAR(created_at) = YEAR(CURRENT_DATE)), 0) as pending_refunds
        FROM refunds 
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE)
        AND status IN ('approved', 'processed')
    ");
    $refundSummaryQuery->execute();
    $metrics['refunds'] = $refundSummaryQuery->fetch(PDO::FETCH_ASSOC);
    
    return $metrics;
}

function getOperationalMetrics($db) {
    $metrics = [];
    
    // 9. Job Card Completion Rate - FIXED
$completionQuery = $db->prepare("
    SELECT 
        COUNT(*) as total_job_cards,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('Pending', 'In Progress') THEN 1 ELSE 0 END) as in_progress,
        (SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as completion_rate
    FROM job_cards 
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)  -- Last 30 days for relevance
");
    $completionQuery->execute();
    $metrics['completion_rate'] = $completionQuery->fetch(PDO::FETCH_ASSOC);
    
   // 10. Average Preparation Cost & Time - FIXED
$prepMetricsQuery = $db->prepare("
            SELECT 
                COALESCE(AVG(jctp.cost), 0) as avg_preparation_cost,
                COALESCE(MAX(jctp.cost), 0) as max_preparation_cost,
                COALESCE(AVG(CASE 
                    WHEN jc.date_completed IS NOT NULL AND jc.created_at IS NOT NULL 
                    THEN DATEDIFF(jc.date_completed, jc.created_at) 
                    ELSE NULL 
                END), 0) as avg_prep_days,
                COUNT(DISTINCT jc.job_card_id) as job_cards_with_costs,
                COALESCE(SUM(jctp.cost), 0) as total_costs
            FROM job_cards jc
            LEFT JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id
            WHERE jc.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 90 DAY)
            AND (jctp.cost IS NULL OR jctp.cost > 0)
        ");
    $prepMetricsQuery->execute();
    $metrics['preparation'] = $prepMetricsQuery->fetch(PDO::FETCH_ASSOC);
    
    // 11. Workshop Utilization - FIXED
        $utilizationQuery = $db->prepare("
            SELECT 
                -- Current active job cards
                (SELECT COUNT(*) FROM job_cards WHERE status IN ('Pending', 'In Progress')) as active_job_cards,
                
                -- Vehicles in preparation
                (SELECT COUNT(*) FROM vehicles WHERE status = 'in_preparation') as total_in_prep,
                
                -- Active mechanics (any job card with assigned person)
                (SELECT COUNT(DISTINCT assigned_to) FROM job_cards 
                 WHERE assigned_to IS NOT NULL 
                 AND status IN ('Pending', 'In Progress', 'Completed')
                 AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)) as active_mechanics,
                
                -- Utilization rate based on vehicles in prep vs active work
                CASE 
                    WHEN (SELECT COUNT(*) FROM vehicles WHERE status = 'in_preparation') > 0 THEN
                        ((SELECT COUNT(*) FROM job_cards WHERE status IN ('Pending', 'In Progress')) / 
                         (SELECT COUNT(*) FROM vehicles WHERE status = 'in_preparation')) * 100
                    ELSE 0 
                END as utilization_rate,
                
                -- Recent activity (job cards created in last 7 days)
                (SELECT COUNT(*) FROM job_cards WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)) as recent_activity
        ");
    $utilizationQuery->execute();
    $metrics['utilization'] = $utilizationQuery->fetch(PDO::FETCH_ASSOC);
    
   // 12. Task Completion Efficiency - FIXED
$taskEfficiencyQuery = $db->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        (SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as task_completion_rate,
        AVG(CASE 
            WHEN date_completed IS NOT NULL AND created_at IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, created_at, date_completed) 
            ELSE NULL 
        END) as avg_hours_per_task,
        -- Add recent tasks only for better accuracy
        SUM(CASE WHEN created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_tasks
    FROM job_card_task_progress
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)  -- Last 30 days for relevance
");
    $taskEfficiencyQuery->execute();
    $metrics['task_efficiency'] = $taskEfficiencyQuery->fetch(PDO::FETCH_ASSOC);
    
    return $metrics;
}

function getChartData($db) {
    $charts = [];
    
    // 1. FIRST disable ONLY_FULL_GROUP_BY at the beginning
    $db->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    
    // 2. Monthly Financial Overview - FIXED
    $monthlyFinancialQuery = $db->prepare("
        WITH monthly_data AS (
            SELECT 
                months.month_number,
                months.month_name,
                -- Sales data
                COALESCE(SUM(s.total_revenue), 0) as monthly_total_revenue,
                COALESCE(SUM(s.total_cost), 0) as monthly_total_cost,
                COALESCE(SUM(s.net_profit), 0) as monthly_net_profit,
                
                -- Refunds (excluding repair_compensation)
                COALESCE((
                    SELECT SUM(r.net_refund_amount)
                    FROM refunds r
                    WHERE MONTH(r.created_at) = months.month_number 
                      AND YEAR(r.created_at) = YEAR(CURDATE())
                      AND r.status IN ('approved', 'processed')
                      AND r.refund_type != 'repair_compensation'
                ), 0) as monthly_refund_amount,
                
                -- Repair compensation
                COALESCE((
                    SELECT SUM(rd.amount)
                    FROM refund_deductions rd
                    JOIN refunds r ON rd.refund_id = r.refund_id
                    WHERE MONTH(r.created_at) = months.month_number 
                      AND YEAR(r.created_at) = YEAR(CURDATE())
                      AND r.status IN ('approved', 'processed')
                      AND rd.deduction_type = 'repair_compensation'
                ), 0) as monthly_repair_compensation
                
            FROM (
                SELECT 1 as month_number, 'Jan' as month_name 
                UNION SELECT 2, 'Feb' 
                UNION SELECT 3, 'Mar' 
                UNION SELECT 4, 'Apr' 
                UNION SELECT 5, 'May' 
                UNION SELECT 6, 'Jun' 
                UNION SELECT 7, 'Jul'
                UNION SELECT 8, 'Aug' 
                UNION SELECT 9, 'Sep' 
                UNION SELECT 10, 'Oct' 
                UNION SELECT 11, 'Nov' 
                UNION SELECT 12, 'Dec'
            ) months
            LEFT JOIN sales s ON MONTH(s.sale_date) = months.month_number 
                              AND YEAR(s.sale_date) = YEAR(CURDATE())
            GROUP BY months.month_number, months.month_name
        )
        
        SELECT 
            month_number,
            month_name,
            monthly_total_revenue,
            monthly_total_cost,
            monthly_net_profit,
            monthly_refund_amount,
            monthly_repair_compensation,
            (monthly_total_revenue - monthly_refund_amount) as net_revenue_after_refunds,
            (monthly_net_profit - monthly_refund_amount) as net_profit_after_refunds
        FROM monthly_data
        ORDER BY month_number
    ");
    
    try {
        $monthlyFinancialQuery->execute();
        $charts['monthly_financial'] = $monthlyFinancialQuery->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Monthly Financial Query Error: " . $e->getMessage());
        $charts['monthly_financial'] = [];
    }
    
    // 3. Inventory Health - FIXED (Simplified)
    $inventoryHealthQuery = $db->prepare("
        SELECT 
            CASE 
                WHEN status IS NULL THEN 'Unknown'
                WHEN status = 'refunded_to_customer' THEN 'Returned (Refund)'
                ELSE status
            END as display_status,
            COUNT(*) as vehicle_count,
            ROUND(AVG(
                CASE 
                    WHEN purchase_date IS NULL THEN 0
                    ELSE DATEDIFF(CURDATE(), purchase_date)
                END
            ), 1) as avg_age_days,
            ROUND(AVG(COALESCE(purchase_price, 0)), 2) as avg_cost,
            ROUND(SUM(COALESCE(purchase_price, 0)), 2) as total_inventory_value
        FROM vehicles 
        GROUP BY 
            CASE 
                WHEN status IS NULL THEN 'Unknown'
                WHEN status = 'refunded_to_customer' THEN 'Returned (Refund)'
                ELSE status
            END
        ORDER BY 
            CASE 
                WHEN status = 'available' THEN 1
                WHEN status = 'in_preparation' THEN 2
                WHEN status = 'purchased' THEN 3
                WHEN status = 'part_exchange' THEN 4
                WHEN status = 'refunded_to_customer' THEN 5
                WHEN status = 'sold' THEN 6
                ELSE 7
            END
    ");
    
    try {
        $inventoryHealthQuery->execute();
        $charts['inventory_health'] = $inventoryHealthQuery->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Inventory Health Query Error: " . $e->getMessage());
        $charts['inventory_health'] = [];
    }
    
    // 4. Cost Breakdown - FIXED (Added GROUP BY for first query)
    $costBreakdownQuery = $db->prepare("
        -- Job Card Costs
        SELECT 
            COALESCE(st.task_type, 'Preparation') as cost_type,
            SUM(COALESCE(jctp.cost, 0)) as total_cost,
            COUNT(*) as item_count,
            'job_card' as cost_source
        FROM job_card_task_progress jctp
        LEFT JOIN standard_tasks st ON jctp.task_id = st.task_id
        WHERE jctp.cost > 0
        GROUP BY COALESCE(st.task_type, 'Preparation')
        
        UNION ALL
        
        -- Repair Costs
        SELECT 
            'Repairs' as cost_type,
            SUM(COALESCE(cost, 0)) as total_cost,
            COUNT(*) as item_count,
            'repairs' as cost_source
        FROM repairs 
        WHERE cost > 0
        
        UNION ALL
        
        -- Expense Costs
        SELECT 
            COALESCE(expense_type, 'Other Expense') as cost_type,
            SUM(COALESCE(amount, 0)) as total_cost,
            COUNT(*) as item_count,
            'expenses' as cost_source
        FROM expenses 
        WHERE amount > 0
        GROUP BY COALESCE(expense_type, 'Other Expense')
        
        UNION ALL
        
        -- Refund Compensation Costs
        SELECT 
            'Refund Compensation' as cost_type,
            SUM(COALESCE(rd.amount, 0)) as total_cost,
            COUNT(*) as item_count,
            'refund' as cost_source
        FROM refund_deductions rd
        JOIN refunds r ON rd.refund_id = r.refund_id
        WHERE rd.deduction_type = 'repair_compensation'
        AND r.status IN ('approved', 'processed')
        
        ORDER BY total_cost DESC
    ");
    
    try {
        $costBreakdownQuery->execute();
        $charts['cost_breakdown'] = $costBreakdownQuery->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Cost Breakdown Query Error: " . $e->getMessage());
        $charts['cost_breakdown'] = [];
    }
    
    return $charts;
}
function getAlertsData($db) {
    $alerts = [];
    
    // Urgent Job Cards
    $alerts['urgent_jobs'] = getUrgentJobCards($db, 5);
    
    // High Cost Vehicles - USE THE NEW FUNCTION
    $alerts['high_cost_vehicles'] = getHighCostVehicles($db, 5);
    
    // Overdue Tasks
    $alerts['overdue_tasks'] = getOverdueTasks($db, 7, 5);
    
    return $alerts;
}
/**
 * Job Cards & Tasks Functions for Dashboard Sidebar
 */

function getPendingJobCardsWithProgress($db, $limit = 10) {
    // Remove parameter binding for LIMIT in MariaDB
    $query = $db->prepare("
        SELECT 
            jc.job_card_id,
            jc.job_card_number,
            jc.priority,
            jc.status,
            jc.date_started,
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
             WHERE jctp.job_card_id = jc.job_card_id AND jctp.status = 'cancelled') as cancelled_tasks
        FROM job_cards jc
        LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON jc.assigned_to = u.id
        WHERE jc.status IN ('Pending', 'In Progress')
        ORDER BY 
            CASE jc.priority 
                WHEN 'Urgent' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                WHEN 'Low' THEN 4
            END,
            jc.date_started ASC
        LIMIT " . (int)$limit
    );
    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function getPendingTasksByVehicle($db, $limit = 8) {
    // Remove parameter binding for LIMIT in MariaDB
    try {
    $query = $db->prepare("
    SELECT 
        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        (SELECT jc.job_card_number 
         FROM job_cards jc 
         WHERE jc.vehicle_id = v.vehicle_id 
         AND jc.status IN ('Pending', 'In Progress')
         ORDER BY jc.created_at DESC 
         LIMIT 1) as job_card_number,
        (SELECT COUNT(*) 
         FROM job_card_task_progress jctp 
         JOIN job_cards jc2 ON jctp.job_card_id = jc2.job_card_id
         WHERE jc2.vehicle_id = v.vehicle_id
         AND jctp.status IN ('pending', 'in_progress')) as pending_tasks,
        (SELECT GROUP_CONCAT(DISTINCT st.task_name SEPARATOR ', ')
         FROM standard_tasks st
         JOIN job_card_task_progress jctp2 ON st.task_id = jctp2.task_id
         JOIN job_cards jc3 ON jctp2.job_card_id = jc3.job_card_id
         WHERE jc3.vehicle_id = v.vehicle_id
         AND jctp2.status IN ('pending', 'in_progress')) as task_names
    FROM vehicles v
    WHERE EXISTS (
        SELECT 1 FROM job_cards jc4
        WHERE jc4.vehicle_id = v.vehicle_id
        AND jc4.status IN ('Pending', 'In Progress')
    )
    ORDER BY pending_tasks DESC
    LIMIT " . (int)$limit
);
    $query->execute();
    } catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>Database Error:</strong> " . $e->getMessage();
    echo "<br><small>Try one of the fixed queries above.</small>";
    echo "</div>";
    
    // Log the error
    error_log("Job Card Query Error: " . $e->getMessage());
    }
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function getPriorityCounts($pendingJobCards) {
    $priorityCounts = [
        'Urgent' => 0,
        'High' => 0,
        'Medium' => 0,
        'Low' => 0
    ];

    foreach ($pendingJobCards as $job) {
        if (isset($priorityCounts[$job['priority']])) {
            $priorityCounts[$job['priority']]++;
        }
    }

    return $priorityCounts;
}

function getTotalPendingTasks($pendingTasks) {
    return array_sum(array_column($pendingTasks, 'pending_tasks'));
}

function getUrgentJobCards($db, $limit = 5) {
    // Remove parameter binding for LIMIT in MariaDB
    $query = $db->prepare("
        SELECT 
            jc.job_card_id,
            jc.job_card_number,
            jc.priority,
            jc.status,
            v.make,
            v.model,
            v.regnumber,
            u.full_name as assigned_to,
            DATEDIFF(CURRENT_DATE, jc.date_started) as days_open,
            (SELECT COUNT(*) FROM job_card_task_progress jctp 
             WHERE jctp.job_card_id = jc.job_card_id AND jctp.status IN ('pending', 'in_progress')) as pending_tasks
        FROM job_cards jc
        LEFT JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON jc.assigned_to = u.id
        WHERE jc.priority = 'Urgent' 
        AND jc.status IN ('Pending', 'In Progress')
        ORDER BY jc.date_started ASC
        LIMIT " . (int)$limit
    );
    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function getOverdueTasks($db, $daysThreshold = 7, $limit = 5) {
    // Use parameter binding for WHERE clause but not for LIMIT
    $query = $db->prepare("
        SELECT 
            jctp.progress_id,
            st.task_name,
            jc.job_card_number,
            v.make,
            v.model,
            v.regnumber,
            DATEDIFF(CURRENT_DATE, jctp.created_at) as days_pending
        FROM job_card_task_progress jctp
        JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
        JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
        LEFT JOIN standard_tasks st ON jctp.task_id = st.task_id
        WHERE jctp.status IN ('pending', 'in_progress')
        AND DATEDIFF(CURRENT_DATE, jctp.created_at) > ?
        ORDER BY days_pending DESC
        LIMIT " . (int)$limit
    );
    $query->execute([$daysThreshold]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function getJobCardProgress($job) {
    $totalTasks = $job['total_tasks'];
    $completedTasks = $job['completed_tasks'] + $job['cancelled_tasks'];
    
    if ($totalTasks > 0) {
        return ($completedTasks / $totalTasks) * 100;
    }
    
    return 0;
}

function getPriorityBadgeClass($priority) {
    $classes = [
        'Urgent' => 'bg-danger',
        'High' => 'bg-warning',
        'Medium' => 'bg-info', 
        'Low' => 'bg-success'
    ];
    
    return $classes[$priority] ?? 'bg-secondary';
}

function getPriorityBorderClass($priority) {
    $classes = [
        'Urgent' => 'border-danger',
        'High' => 'border-warning',
        'Medium' => 'border-info',
        'Low' => 'border-success'
    ];
    
    return $classes[$priority] ?? 'border-secondary';
}

// Add this temporary function to debug high-cost vehicles
function debugHighCostVehicles($db) {
    $debugQuery = $db->prepare("
        SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.regnumber,
            v.status,
            v.purchase_price,
            COALESCE(SUM(jctp.cost), 0) as total_costs,
            (v.purchase_price + COALESCE(SUM(jctp.cost), 0)) as total_investment,
            (v.purchase_price * 1.3) as threshold_30_percent,
            ((v.purchase_price + COALESCE(SUM(jctp.cost), 0)) / v.purchase_price) * 100 as cost_percentage
        FROM vehicles v
        LEFT JOIN job_cards jc ON v.vehicle_id = jc.vehicle_id
        LEFT JOIN job_card_task_progress jctp ON jc.job_card_id = jctp.job_card_id
        WHERE v.status != 'sold'
        ORDER BY cost_percentage DESC
        LIMIT 10
    ");
    $debugQuery->execute();
    return $debugQuery->fetchAll(PDO::FETCH_ASSOC);
}

function getHighCostVehicles($db, $limit = 5) {
    // Use the centralized calculation for consistency
    return getTopExpensiveVehiclesConsistent($limit);
}

/**
 * Get Executive Summary Metrics (Simple Version)
 */
function getExecutiveSummaryMetrics($db) {
    $metrics = [];
    $current_year = date('Y');
    $current_month = date('n');
    
    // 1. TOTAL REVENUE YTD
    $revenueQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(total_revenue), 0) as revenue_ytd,
            COUNT(*) as sales_count_ytd
        FROM sales 
        WHERE YEAR(sale_date) = ?
        AND status NOT IN ('cancelled')
    ");
    $revenueQuery->execute([$current_year]);
    $metrics['revenue'] = $revenueQuery->fetch(PDO::FETCH_ASSOC);
    
    // 2. NET PROFIT YTD
    $profitQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(net_profit), 0) as net_profit_ytd,
            SUM(CASE WHEN net_profit > 0 THEN 1 ELSE 0 END) as profitable_sales,
            CASE 
                WHEN COALESCE(SUM(total_revenue), 0) > 0 
                THEN (COALESCE(SUM(net_profit), 0) / COALESCE(SUM(total_revenue), 0)) * 100 
                ELSE 0 
            END as profit_margin_ytd
        FROM sales 
        WHERE YEAR(sale_date) = ?
        AND status NOT IN ('cancelled')
    ");
    $profitQuery->execute([$current_year]);
    $metrics['profit'] = $profitQuery->fetch(PDO::FETCH_ASSOC);
    
    // 3. INVENTORY TURNOVER
    $turnoverQuery = $db->prepare("
        SELECT 
            -- Vehicles sold (final, not refunded)
            COALESCE(
                (SELECT COUNT(DISTINCT v.vehicle_id)
                 FROM vehicles v
                 JOIN sales s ON v.vehicle_id = s.vehicle_id
                 WHERE v.status = 'sold'
                 AND YEAR(s.sale_date) = ?
                 AND s.status NOT IN ('refunded', 'cancelled')
                ), 0) as vehicles_sold_final,
            
            -- Current inventory
            COALESCE(
                (SELECT COUNT(*) 
                 FROM vehicles 
                 WHERE status IN ('available', 'in_preparation', 'purchased', 'part_exchange')
                ), 0) as current_inventory,
            
            -- Average inventory value
            COALESCE(
                (SELECT AVG(purchase_price) 
                 FROM vehicles 
                 WHERE status IN ('available', 'in_preparation', 'purchased', 'part_exchange')
                 AND purchase_price > 0
                ), 0) as avg_inventory_value,
            
            -- Turnover rate
            CASE 
                WHEN COALESCE(
                    (SELECT COUNT(*) 
                     FROM vehicles 
                     WHERE status IN ('available', 'in_preparation', 'purchased', 'part_exchange')
                    ), 0) > 0 
                THEN (
                    COALESCE(
                        (SELECT COUNT(DISTINCT v.vehicle_id)
                         FROM vehicles v
                         JOIN sales s ON v.vehicle_id = s.vehicle_id
                         WHERE v.status = 'sold'
                         AND YEAR(s.sale_date) = ?
                         AND s.status NOT IN ('refunded', 'cancelled')
                        ), 0)
                    / 
                    COALESCE(
                        (SELECT COUNT(*) 
                         FROM vehicles 
                         WHERE status IN ('available', 'in_preparation', 'purchased', 'part_exchange')
                        ), 1)
                ) * (12 / NULLIF(?, 0))
                ELSE 0 
            END as turnover_rate
    ");
    $turnoverQuery->execute([$current_year, $current_year, $current_month]);
    $metrics['turnover'] = $turnoverQuery->fetch(PDO::FETCH_ASSOC);
    
    // 4. ROI METRICS
    $roiQuery = $db->prepare("
    SELECT 
        -- ROI using the already-calculated total_cost from sales table
        CASE 
            WHEN SUM(s.total_cost) > 0
            THEN (SUM(s.net_profit) / SUM(s.total_cost)) * 100
            ELSE 0
        END as avg_roi_percentage,
        
        -- Max ROI
        MAX(
            CASE 
                WHEN s.total_cost > 0
                THEN (s.net_profit / s.total_cost) * 100
                ELSE 0
            END
        ) as max_roi,
        
        -- Min ROI
        MIN(
            CASE 
                WHEN s.total_cost > 0
                THEN (s.net_profit / s.total_cost) * 100
                ELSE 0
            END
        ) as min_roi
        
    FROM sales s
    WHERE YEAR(s.sale_date) = ?
    AND s.status NOT IN ('cancelled')
    AND s.net_profit IS NOT NULL
    AND s.total_cost > 0
");
    $roiQuery->execute([$current_year]);
    $metrics['roi'] = $roiQuery->fetch(PDO::FETCH_ASSOC);
    
    return $metrics;
}
/**************************
 * Get Current Month Metrics
*************************** */
function getCurrentMonthMetric($db) {

    $metrics = [];

    $current_year  = date('Y');
    $current_month = date('n');

    // 1. CURRENT MONTH REVENUE
    $revenueQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(total_revenue), 0) AS revenue_month,
            COUNT(*) AS sales_count_month
        FROM sales
        WHERE YEAR(sale_date) = ?
        AND MONTH(sale_date) = ?
        AND status NOT IN ('cancelled')
    ");

    $revenueQuery->execute([$current_year, $current_month]);

    $metrics['revenue'] = $revenueQuery->fetch(PDO::FETCH_ASSOC);


    // 2. CURRENT MONTH NET PROFIT
    $profitQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(net_profit), 0) AS net_profit_month,

            SUM(
                CASE 
                    WHEN net_profit > 0 THEN 1 
                    ELSE 0 
                END
            ) AS profitable_sales,

            CASE 
                WHEN COALESCE(SUM(total_revenue), 0) > 0 
                THEN (
                    COALESCE(SUM(net_profit), 0) 
                    / COALESCE(SUM(total_revenue), 0)
                ) * 100
                ELSE 0
            END AS profit_margin_month

        FROM sales
        WHERE YEAR(sale_date) = ?
        AND MONTH(sale_date) = ?
        AND status NOT IN ('cancelled')
    ");

    $profitQuery->execute([$current_year, $current_month]);

    $metrics['profit'] = $profitQuery->fetch(PDO::FETCH_ASSOC);


    // 3. CURRENT MONTH INVENTORY TURNOVER
    $turnoverQuery = $db->prepare("
        SELECT

            -- Vehicles sold this month
            COALESCE(
                (
                    SELECT COUNT(DISTINCT v.vehicle_id)
                    FROM vehicles v
                    JOIN sales s 
                        ON v.vehicle_id = s.vehicle_id
                    WHERE v.status = 'sold'
                    AND YEAR(s.sale_date) = ?
                    AND MONTH(s.sale_date) = ?
                    AND s.status NOT IN ('refunded', 'cancelled')
                ),
                0
            ) AS vehicles_sold_month,

            -- Current inventory
            COALESCE(
                (
                    SELECT COUNT(*)
                    FROM vehicles
                    WHERE status IN (
                        'available',
                        'in_preparation',
                        'purchased',
                        'part_exchange'
                    )
                ),
                0
            ) AS current_inventory,

            -- Average inventory purchase value
            COALESCE(
                (
                    SELECT AVG(purchase_price)
                    FROM vehicles
                    WHERE status IN (
                        'available',
                        'in_preparation',
                        'purchased',
                        'part_exchange'
                    )
                    AND purchase_price > 0
                ),
                0
            ) AS avg_inventory_value,

            -- Annualised monthly turnover
            CASE
                WHEN COALESCE(
                    (
                        SELECT COUNT(*)
                        FROM vehicles
                        WHERE status IN (
                            'available',
                            'in_preparation',
                            'purchased',
                            'part_exchange'
                        )
                    ),
                    0
                ) > 0
                THEN (
                    COALESCE(
                        (
                            SELECT COUNT(DISTINCT v.vehicle_id)
                            FROM vehicles v
                            JOIN sales s
                                ON v.vehicle_id = s.vehicle_id
                            WHERE v.status = 'sold'
                            AND YEAR(s.sale_date) = ?
                            AND MONTH(s.sale_date) = ?
                            AND s.status NOT IN ('refunded', 'cancelled')
                        ),
                        0
                    )
                    /
                    COALESCE(
                        (
                            SELECT COUNT(*)
                            FROM vehicles
                            WHERE status IN (
                                'available',
                                'in_preparation',
                                'purchased',
                                'part_exchange'
                            )
                        ),
                        1
                    )
                ) * 12
                ELSE 0
            END AS turnover_rate

    ");

    $turnoverQuery->execute([
        $current_year,
        $current_month,
        $current_year,
        $current_month
    ]);

    $metrics['turnover'] = $turnoverQuery->fetch(PDO::FETCH_ASSOC);


    // 4. CURRENT MONTH ROI
    $roiQuery = $db->prepare("
        SELECT

            -- Average ROI
            CASE
                WHEN SUM(s.total_cost) > 0
                THEN (
                    SUM(s.net_profit) 
                    / SUM(s.total_cost)
                ) * 100
                ELSE 0
            END AS avg_roi_percentage,

            -- Maximum ROI
            MAX(
                CASE
                    WHEN s.total_cost > 0
                    THEN (
                        s.net_profit 
                        / s.total_cost
                    ) * 100
                    ELSE 0
                END
            ) AS max_roi,

            -- Minimum ROI
            MIN(
                CASE
                    WHEN s.total_cost > 0
                    THEN (
                        s.net_profit 
                        / s.total_cost
                    ) * 100
                    ELSE 0
                END
            ) AS min_roi

        FROM sales s

        WHERE YEAR(s.sale_date) = ?
        AND MONTH(s.sale_date) = ?
        AND s.status NOT IN ('cancelled')
        AND s.net_profit IS NOT NULL
        AND s.total_cost > 0
    ");

    $roiQuery->execute([$current_year, $current_month]);

    $metrics['roi'] = $roiQuery->fetch(PDO::FETCH_ASSOC);


    return $metrics;
}
/**
 * Get vehicle profitability by make/model
 * Returns data for the last 6 months
 */
function getVehicleProfitabilityByMakeModel() {
    global $db;
        
    $query = $db->prepare("
        SELECT 
            v.make,
            v.model,
            CONCAT(v.make, ' ', v.model) as make_model,
            
            -- REMOVED: Complex prep cost calculation from here
            0 as avg_prep_cost, -- Temporary placeholder

            -- Count of sold vehicles
            COUNT(DISTINCT s.sale_id) as total_sold,
            
            -- Total revenue
            SUM(COALESCE(s.total_revenue, 0)) as total_revenue,
            
            -- Total profit
            SUM(COALESCE(s.net_profit, 0)) as total_profit,
            
            -- Refund count
            SUM(CASE WHEN s.status IN ('refunded', 'partially_refunded') THEN 1 ELSE 0 END) as refund_count,
            
            -- Average sell time
            AVG(DATEDIFF(s.sale_date, v.purchase_date)) as avg_sell_days
            
        FROM vehicles v
        INNER JOIN sales s ON v.vehicle_id = s.vehicle_id
        WHERE s.sale_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
          AND s.status NOT IN ('cancelled')
          AND v.status = 'sold'
        
        -- ADD GROUP BY
        GROUP BY v.make, v.model
        
        HAVING total_sold > 0
        ORDER BY (SUM(COALESCE(s.net_profit, 0)) / COUNT(DISTINCT s.sale_id)) DESC
    ");
    
    $query->execute();
    $vehicles = $query->fetchAll(PDO::FETCH_ASSOC);
    
    
    // Calculate prep costs separately for each make/model
    foreach ($vehicles as &$vehicle) {
        // Get prep costs for this make/model
        $prepQuery = $db->prepare("
            SELECT AVG(
                COALESCE((
                    SELECT SUM(jctp.cost) 
                    FROM job_card_task_progress jctp
                    JOIN job_cards jc ON jctp.job_card_id = jc.job_card_id
                    WHERE jc.vehicle_id = v.vehicle_id AND jctp.cost > 0
                ), 0) +
                COALESCE((
                    SELECT SUM(cost) 
                    FROM repairs 
                    WHERE vehicle_id = v.vehicle_id AND cost > 0
                ), 0) +
                COALESCE((
                    SELECT SUM(amount) 
                    FROM expenses 
                    WHERE vehicle_id = v.vehicle_id AND amount > 0
                ), 0)
            ) as avg_prep_cost
            FROM vehicles v
            INNER JOIN sales s ON v.vehicle_id = s.vehicle_id
            WHERE v.make = ? AND v.model = ?
              AND s.sale_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
              AND s.status NOT IN ('cancelled')
              AND v.status = 'sold'
        ");
        
        $prepQuery->execute([$vehicle['make'], $vehicle['model']]);
        $prepCost = $prepQuery->fetchColumn();
        $vehicle['avg_prep_cost'] = $prepCost ?: 0;
        
        // Calculate averages
        $vehicle['avg_profit_per_vehicle'] = $vehicle['total_sold'] > 0 
            ? $vehicle['total_profit'] / $vehicle['total_sold'] 
            : 0;
        
        $vehicle['refund_rate'] = $vehicle['total_sold'] > 0 
            ? ($vehicle['refund_count'] / $vehicle['total_sold']) * 100 
            : 0;
        
        // Determine recommendation stars
        $vehicle['recommendation_stars'] = getRecommendationStars(
            $vehicle['avg_profit_per_vehicle'],
            $vehicle['refund_rate'],
            $vehicle['avg_sell_days']
        );
        
        // Determine badge
        $vehicle['badge'] = getPerformanceBadge($vehicle);
    }
    
    return $vehicles;
}

/**
 * Get recommendation stars based on performance metrics
 */
function getRecommendationStars($avgProfit, $refundRate, $avgSellDays) {
    $score = 0;
    
    // Profit scoring (0-3 points)
    if ($avgProfit >= 1500) $score += 3;
    elseif ($avgProfit >= 1000) $score += 2;
    elseif ($avgProfit >= 500) $score += 1;
    elseif ($avgProfit < 0) $score -= 2;
    
    // Refund rate scoring (0-2 points)
    if ($refundRate <= 5) $score += 2;
    elseif ($refundRate <= 10) $score += 1;
    elseif ($refundRate > 25) $score -= 1;
    
    // Sell time scoring (0-1 point)
    if ($avgSellDays <= 15) $score += 1;
    elseif ($avgSellDays > 40) $score -= 1;
    
    // Convert score to stars
    if ($score >= 4) return '⭐⭐⭐⭐⭐';
    elseif ($score >= 3) return '⭐⭐⭐⭐☆';
    elseif ($score >= 2) return '⭐⭐⭐☆☆';
    elseif ($score >= 1) return '⭐⭐☆☆☆';
    elseif ($score >= 0) return '⭐☆☆☆☆';
    else return '🚫';
}

/**
 * Get performance badge for exceptional models
 */
function getPerformanceBadge($vehicle) {
    // You'll need to compare against other vehicles
    // For now, return based on individual performance
    $badges = [];
    
    if ($vehicle['avg_profit_per_vehicle'] >= 1500) {
        $badges[] = '💰';
    }
    
    if ($vehicle['refund_rate'] <= 5) {
        $badges[] = '✅';
    }
    
    if ($vehicle['avg_sell_days'] <= 15) {
        $badges[] = '⚡';
    }
    
    if ($vehicle['total_sold'] >= 10) {
        $badges[] = '🏆';
    }
    
    return implode(' ', $badges);
}

// Helper functions for color coding
function getProfitColor($profit) {
    if ($profit >= 1000) return 'success';
    if ($profit >= 0) return 'warning';
    return 'danger';
}

function getPrepCostColor($cost) {
    if ($cost <= 500) return 'success';
    if ($cost <= 1000) return 'warning';
    return 'danger';
}

function getRefundColor($rate) {
    if ($rate <= 10) return 'success';
    if ($rate <= 20) return 'warning';
    return 'danger';
}

function getSellTimeColor($days) {
    if ($days <= 20) return 'success';
    if ($days <= 30) return 'warning';
    return 'danger';
}

function getRecommendationColor($stars) {
    if ($stars === '⭐⭐⭐⭐⭐') return 'success';
    if ($stars === '⭐⭐⭐⭐☆') return 'info';
    if ($stars === '⭐⭐⭐☆☆') return 'warning';
    if ($stars === '⭐⭐☆☆☆') return 'warning';
    if ($stars === '⭐☆☆☆☆') return 'danger';
    if ($stars === '🚫') return 'danger';
    return 'secondary';
}

?>