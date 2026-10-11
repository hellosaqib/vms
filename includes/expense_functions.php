<?php
require_once 'config.php';
include_once("cost_calculations.php");
/**
 * Get expense summary by date range
 */
function getExpenseSummary($start_date = null, $end_date = null) {
    global $db;
    
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!$end_date) $end_date = date('Y-m-d');
    
    $sql = "
        -- Job Card Expenses
        SELECT 'job_card' as source, COALESCE(SUM(cost), 0) as total 
        FROM job_card_task_progress jp 
        JOIN job_cards jc ON jp.job_card_id = jc.job_card_id 
        WHERE jp.date_completed BETWEEN ? AND ?
        
        UNION ALL
        
        -- General Expenses
        SELECT 'expense' as source, COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE date BETWEEN ? AND ?
        
        UNION ALL
        
        -- Repair Costs
        SELECT 'repair' as source, COALESCE(SUM(cost), 0) as total 
        FROM repairs 
        WHERE date_started BETWEEN ? AND ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [
        'job_card' => 0,
        'expense' => 0,
        'repair' => 0,
        'grand_total' => 0
    ];
    
    foreach ($data as $row) {
        $summary[$row['source']] = $row['total'] ?? 0;
    }
    
    $summary['grand_total'] = $summary['job_card'] + $summary['expense'] + $summary['repair'];
    
    return $summary;
}

/**
 * Get top expensive vehicles - NOW USING CENTRALIZED CALCULATION
 */
function getTopExpensiveVehicles($limit = 5) {
    // Use the centralized calculation for consistency
    return getTopExpensiveVehiclesConsistent($limit);
}
/**
 * Get all combined expenses with filters
 */
function getCombinedExpenses($filters = []) {
    global $db;
    
    $default_filters = [
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d'),
        'vehicle_id' => null,
        'source_type' => null,
        'limit' => 50
    ];
    
    $filters = array_merge($default_filters, $filters);
    
    $sql = "
        SELECT 
            'job_card' as source_type,
            jp.progress_id as id,
            jc.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            t.task_name as type,
            jp.description,
            jp.cost as amount,
            jp.date_completed as date,
            NULL as vendor_shop,
            jp.notes,
            u.full_name as added_by,
            jp.created_at
        FROM job_card_task_progress jp 
        JOIN job_cards jc ON jp.job_card_id = jc.job_card_id 
        JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
        JOIN standard_tasks t ON jp.task_id = t.task_id
        JOIN users u ON jp.assigned_to = u.id
        WHERE jp.date_completed BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'expense' as source_type,
            e.expense_id as id,
            e.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            e.expense_type as type,
            e.description,
            e.amount,
            e.date,
            e.vendor_name as vendor_shop,
            e.notes,
            u.full_name as added_by,
            e.date
        FROM expenses e
        LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
        JOIN users u ON e.added_by = u.id
        WHERE e.date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'repair' as source_type,
            r.repair_id as id,
            r.vehicle_id,
            v.regnumber,
            v.make,
            v.model,
            r.repair_type as type,
            r.description,
            r.cost as amount,
            r.date_started as date,
            r.shop_name as vendor_shop,
            NULL as notes,
            u.full_name as added_by,
            r.created_at
        FROM repairs r
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        JOIN users u ON r.added_by = u.id
        WHERE r.date_started BETWEEN ? AND ?
        
        ORDER BY date DESC
        LIMIT " . (int)$filters['limit'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $filters['start_date'], $filters['end_date'],
        $filters['start_date'], $filters['end_date'], 
        $filters['start_date'], $filters['end_date']
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly expense trends
 */
function getMonthlyExpenseTrend($months = 6) {
    global $db;
    
    $sql = "
        SELECT 
            DATE_FORMAT(jp.date_completed, '%Y-%m') as month,
            'job_card' as source,
            COALESCE(SUM(jp.cost), 0) as total
        FROM job_card_task_progress jp 
        JOIN job_cards jc ON jp.job_card_id = jc.job_card_id 
        WHERE jp.date_completed >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY month
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(e.date, '%Y-%m') as month,
            'expense' as source,
            COALESCE(SUM(e.amount), 0) as total
        FROM expenses e
        WHERE e.date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY month
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(r.date_started, '%Y-%m') as month,
            'repair' as source,
            COALESCE(SUM(r.cost), 0) as total
        FROM repairs r
        WHERE r.date_started >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY month
        
        ORDER BY month ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$months, $months, $months]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get expense breakdown by category
 */
function getExpenseBreakdown($start_date = null, $end_date = null) {
    global $db;
    
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!$end_date) $end_date = date('Y-m-d');
    
    $sql = "
        -- Expense types breakdown
        SELECT 
            e.expense_type as category,
            'expense' as source,
            COALESCE(SUM(e.amount), 0) as total
        FROM expenses e
        WHERE e.date BETWEEN ? AND ?
        GROUP BY e.expense_type
        
        UNION ALL
        
        -- Repair types breakdown
        SELECT 
            r.repair_type as category,
            'repair' as source,
            COALESCE(SUM(r.cost), 0) as total
        FROM repairs r
        WHERE r.date_started BETWEEN ? AND ?
        GROUP BY r.repair_type
        
        UNION ALL
        
        -- Job card tasks breakdown
        SELECT 
            t.task_name as category,
            'job_card' as source,
            COALESCE(SUM(jp.cost), 0) as total
        FROM job_card_task_progress jp 
        JOIN job_cards jc ON jp.job_card_id = jc.job_card_id 
        JOIN standard_tasks t ON jp.task_id = t.task_id
        WHERE jp.date_completed BETWEEN ? AND ?
        GROUP BY t.task_name
        
        ORDER BY total DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get vehicles profitability - NOW USING CENTRALIZED CALCULATION  
 */
function getVehiclesProfitability() {
    // Use centralized calculation
    return getAllVehiclesWithCosts();
}
/**
 * Get expenses by specific type
 */
function getExpensesByType($type, $start_date, $end_date) {
    global $db;
    
    switch ($type) {
        case 'job_card':
            $sql = "
                SELECT 
                    jp.progress_id as id,
                    jc.vehicle_id,
                    v.regnumber,
                    v.make,
                    v.model,
                    t.task_name as type,
                    jp.description,
                    jp.cost as amount,
                    jp.date_completed as date,
                    NULL as vendor_shop,
                    jp.notes,
                    u.full_name as added_by,
                    'job_card' as source_type
                FROM job_card_task_progress jp 
                JOIN job_cards jc ON jp.job_card_id = jc.job_card_id 
                JOIN vehicles v ON jc.vehicle_id = v.vehicle_id
                JOIN standard_tasks t ON jp.task_id = t.task_id
                JOIN users u ON jp.assigned_to = u.id
                WHERE jp.date_completed BETWEEN ? AND ?
                ORDER BY jp.date_completed DESC
            ";
            break;
            
        case 'expense':
            $sql = "
                SELECT 
                    e.expense_id as id,
                    e.vehicle_id,
                    v.regnumber,
                    v.make,
                    v.model,
                    e.expense_type as type,
                    e.description,
                    e.amount,
                    e.date,
                    e.vendor_name as vendor_shop,
                    e.notes,
                    u.full_name as added_by,
                    'expense' as source_type
                FROM expenses e
                LEFT JOIN vehicles v ON e.vehicle_id = v.vehicle_id
                JOIN users u ON e.added_by = u.id
                WHERE e.date BETWEEN ? AND ?
                ORDER BY e.date DESC
            ";
            break;
            
        case 'repair':
            $sql = "
                SELECT 
                    r.repair_id as id,
                    r.vehicle_id,
                    v.regnumber,
                    v.make,
                    v.model,
                    r.repair_type as type,
                    r.description,
                    r.cost as amount,
                    r.date_started as date,
                    r.shop_name as vendor_shop,
                    NULL as notes,
                    u.full_name as added_by,
                    'repair' as source_type
                FROM repairs r
                JOIN vehicles v ON r.vehicle_id = v.vehicle_id
                JOIN users u ON r.added_by = u.id
                WHERE r.date_started BETWEEN ? AND ?
                ORDER BY r.date_started DESC
            ";
            break;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Display expense table
 */
function displayExpenseTable($expenses, $title) {
    if (empty($expenses)) {
        echo '<div class="alert alert-warning">No expenses found for the selected period.</div>';
        return;
    }
    
    $total = array_sum(array_column($expenses, 'amount'));
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><?= $title ?> (<?= count($expenses) ?> records)</h6>
        <div class="fw-bold text-primary">Total: £<?= number_format($total, 2) ?></div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Vendor/Shop</th>
                    <th class="text-end">Amount</th>
                    <th>Added By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($expense['date'])) ?></td>
                    <td>
                        <?php if ($expense['vehicle_id']): ?>
                            <?= htmlspecialchars($expense['regnumber']) ?><br>
                            <small class="text-body-tertiary"><?= htmlspecialchars($expense['make']) ?> <?= htmlspecialchars($expense['model']) ?></small>
                        <?php else: ?>
                            <span class="text-body-tertiary">General Expense</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= 
                            $expense['source_type'] == 'job_card' ? 'success' : 
                            ($expense['source_type'] == 'expense' ? 'primary' : 'warning')
                        ?>">
                            <?= htmlspecialchars($expense['type']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($expense['description']) ?></td>
                    <td><?= htmlspecialchars($expense['vendor_shop'] ?? 'N/A') ?></td>
                    <td class="text-end fw-bold">£<?= number_format($expense['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($expense['added_by']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-secondary">
                <tr>
                    <td colspan="5" class="text-end fw-bold">Total:</td>
                    <td class="text-end fw-bold text-primary">£<?= number_format($total, 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php
}
?>