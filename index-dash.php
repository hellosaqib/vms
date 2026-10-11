<?php
// dashboard.php
$requiredPermission = 'view_dashboard';
require_once 'includes/check_auth.php';
require_once 'includes/functions.php';
require_once 'includes/dashboard_functions.php'; // New functions file
$pageTitle = 'Dashboard - Vehicle Management System';

$user = $auth->getUser();
$roleName = $auth->getRoleName();


// Set date range from GET parameters or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Initialize calculator
$calculator = new DashboardCalculator($db, $startDate, $endDate);
$metrics = $calculator->getAllMetrics();

// Page title
$pageTitle = "Dashboard - Financial Overview";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Phoenix Admin Theme CSS -->
    <link rel="stylesheet" href="assets/css/phoenix-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --success-color: #06d6a0;
            --danger-color: #ef476f;
            --warning-color: #ffd166;
            --info-color: #118ab2;
            --dark-color: #2b2d42;
            --light-color: #f8f9fa;
        }
        
        .dashboard-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .card-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .profit-positive { color: var(--success-color); }
        .profit-negative { color: var(--danger-color); }
        .profit-neutral { color: var(--warning-color); }
        
        .trend-up { color: var(--success-color); }
        .trend-down { color: var(--danger-color); }
        
        .date-filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .inventory-table img {
            width: 50px;
            height: 35px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .metric-change {
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Include your theme header/navigation -->
    <!-- <?php include 'includes/header.php'; ?> -->
    
    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Financial Dashboard</h1>
                        <p class="text-muted mb-0">Real-time business performance metrics</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <button class="btn btn-success" id="exportBtn">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Date Range Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card date-filter-card">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label text-white"><i class="fas fa-calendar me-2"></i>Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-white"><i class="fas fa-calendar me-2"></i>End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <div class="btn-group" role="group">
                                    <button type="submit" name="range" value="today" class="btn btn-light">Today</button>
                                    <button type="submit" name="range" value="month" class="btn btn-light">This Month</button>
                                    <button type="submit" name="range" value="year" class="btn btn-light">This Year</button>
                                    <button type="submit" name="range" value="all" class="btn btn-light">All Time</button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-light w-100">
                                    <i class="fas fa-filter me-2"></i>Apply Filter
                                </button>
                            </div>
                        </form>
                        <div class="mt-3 text-white">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Showing data from <?php echo date('d M Y', strtotime($startDate)); ?> to <?php echo date('d M Y', strtotime($endDate)); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ROW 1: KEY FINANCIAL METRICS -->
        <div class="row mb-4">
            <!-- Total Cash Collected -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="card-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h6 class="card-label">CASH COLLECTED</h6>
                        <h2 class="card-value"><?php echo DashboardCalculator::formatCurrency($metrics['cash_collected']); ?></h2>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Actual money received</small>
                            <span class="metric-change trend-up">
                                <i class="fas fa-arrow-up me-1"></i>100%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Net Cash Profit -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="card-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h6 class="card-label">NET CASH PROFIT</h6>
                        <h2 class="card-value <?php echo $metrics['net_cash_profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo DashboardCalculator::formatCurrency($metrics['net_cash_profit']); ?>
                        </h2>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Cash In - Cash Out</small>
                            <span class="metric-change <?php echo $metrics['net_cash_profit'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                <?php echo $metrics['net_cash_profit'] >= 0 ? '<i class="fas fa-arrow-up me-1"></i>' : '<i class="fas fa-arrow-down me-1"></i>'; ?>
                                <?php echo $metrics['gross_revenue'] > 0 ? DashboardCalculator::formatPercentage(($metrics['net_cash_profit'] / $metrics['gross_revenue']) * 100) : '0%'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Net Business Profit (Your Method) -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="card-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h6 class="card-label">NET BUSINESS PROFIT</h6>
                        <h2 class="card-value <?php echo $metrics['net_business_profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo DashboardCalculator::formatCurrency($metrics['net_business_profit']); ?>
                        </h2>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Gross - Costs - Refunds</small>
                            <span class="metric-change <?php echo $metrics['net_business_profit'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                <?php echo $metrics['net_business_profit'] >= 0 ? '<i class="fas fa-arrow-up me-1"></i>' : '<i class="fas fa-arrow-down me-1"></i>'; ?>
                                <?php echo DashboardCalculator::formatPercentage($metrics['profit_margin']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cash Paid Out -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="card-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h6 class="card-label">CASH PAID OUT</h6>
                        <h2 class="card-value"><?php echo DashboardCalculator::formatCurrency($metrics['cash_paid_out']); ?></h2>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Refunds to customers</small>
                            <span class="metric-change trend-down">
                                <i class="fas fa-arrow-down me-1"></i>
                                <?php echo $metrics['gross_revenue'] > 0 ? DashboardCalculator::formatPercentage(($metrics['cash_paid_out'] / $metrics['gross_revenue']) * 100) : '0%'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ROW 2: PERFORMANCE METRICS -->
        <div class="row mb-4">
            <!-- Vehicles Sold -->
            <div class="col-xl-2 col-lg-4 col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <div class="card-icon bg-info bg-opacity-10 text-info mx-auto">
                            <i class="fas fa-car"></i>
                        </div>
                        <h6 class="card-label">VEHICLES SOLD</h6>
                        <h2 class="card-value"><?php echo $metrics['vehicles_sold']; ?></h2>
                        <small class="text-muted">Total units sold</small>
                    </div>
                </div>
            </div>
            
            <!-- Average Profit per Vehicle -->
            <div class="col-xl-2 col-lg-4 col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <div class="card-icon bg-success bg-opacity-10 text-success mx-auto">
                            <i class="fas fa-pound-sign"></i>
                        </div>
                        <h6 class="card-label">AVG PROFIT/VEHICLE</h6>
                        <h2 class="card-value <?php echo $metrics['avg_profit_per_vehicle'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo DashboardCalculator::formatCurrency($metrics['avg_profit_per_vehicle']); ?>
                        </h2>
                        <small class="text-muted">Per unit average</small>
                    </div>
                </div>
            </div>
            
            <!-- Profit Margin -->
            <div class="col-xl-2 col-lg-4 col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <div class="card-icon bg-primary bg-opacity-10 text-primary mx-auto">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <h6 class="card-label">PROFIT MARGIN</h6>
                        <h2 class="card-value <?php echo $metrics['profit_margin'] >= 10 ? 'profit-positive' : ($metrics['profit_margin'] >= 5 ? 'profit-neutral' : 'profit-negative'); ?>">
                            <?php echo DashboardCalculator::formatPercentage($metrics['profit_margin']); ?>
                        </h2>
                        <small class="text-muted">Net profit percentage</small>
                    </div>
                </div>
            </div>
            
            <!-- Purchase Costs -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h6 class="card-label">PURCHASE COSTS</h6>
                        <h2 class="card-value"><?php echo DashboardCalculator::formatCurrency($metrics['purchase_costs']); ?></h2>
                        <div class="progress mt-2" style="height: 8px;">
                            <?php 
                            $purchasePercentage = $metrics['gross_revenue'] > 0 ? ($metrics['purchase_costs'] / $metrics['gross_revenue']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo min($purchasePercentage, 100); ?>%">
                            </div>
                        </div>
                        <small class="text-muted">Vehicle acquisition costs</small>
                    </div>
                </div>
            </div>
            
            <!-- Repair Costs -->
            <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h6 class="card-label">REPAIR & PREP COSTS</h6>
                        <h2 class="card-value"><?php echo DashboardCalculator::formatCurrency($metrics['repair_costs']); ?></h2>
                        <div class="progress mt-2" style="height: 8px;">
                            <?php 
                            $repairPercentage = $metrics['gross_revenue'] > 0 ? ($metrics['repair_costs'] / $metrics['gross_revenue']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo min($repairPercentage, 100); ?>%">
                            </div>
                        </div>
                        <small class="text-muted">Job cards + repairs + expenses</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ROW 3: INVENTORY & CHARTS -->
        <div class="row mb-4">
            <!-- Current Inventory -->
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-warehouse me-2"></i>
                            Current Inventory
                            <span class="badge bg-light text-dark float-end"><?php echo $metrics['inventory']['count']; ?> vehicles</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Reg</th>
                                        <th>Purchase</th>
                                        <th>Repairs</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($metrics['inventory']['vehicles']) > 0): ?>
                                        <?php foreach ($metrics['inventory']['vehicles'] as $vehicle): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="fas fa-car text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($vehicle['make']); ?></strong>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($vehicle['model']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($vehicle['regnumber']); ?></span></td>
                                            <td><?php echo DashboardCalculator::formatCurrency($vehicle['purchase_price']); ?></td>
                                            <td><?php echo DashboardCalculator::formatCurrency($vehicle['repair_costs']); ?></td>
                                            <td><strong><?php echo DashboardCalculator::formatCurrency($vehicle['total_investment']); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-active">
                                            <td colspan="4"><strong>Total Inventory Value</strong></td>
                                            <td><strong class="text-primary"><?php echo DashboardCalculator::formatCurrency($metrics['inventory']['total_value']); ?></strong></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-car fa-2x mb-3 d-block"></i>
                                                No vehicles in inventory
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Revenue Trend -->
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Monthly Revenue Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <!-- Simple bar chart using HTML/CSS -->
                            <div class="d-flex align-items-end h-75 mt-4">
                                <?php 
                                $maxRevenue = 0;
                                foreach ($metrics['monthly_trends'] as $month) {
                                    if ($month['gross_revenue'] > $maxRevenue) {
                                        $maxRevenue = $month['gross_revenue'];
                                    }
                                }
                                foreach ($metrics['monthly_trends'] as $month):
                                    $height = $maxRevenue > 0 ? ($month['gross_revenue'] / $maxRevenue) * 100 : 0;
                                ?>
                                <div class="d-flex flex-column align-items-center mx-2" style="flex: 1;">
                                    <div class="bg-primary rounded-top" style="height: <?php echo $height; ?>%; width: 30px;"></div>
                                    <div class="mt-2 small text-muted">
                                        <?php echo date('M', strtotime($month['month'] . '-01')); ?>
                                    </div>
                                    <div class="small fw-bold">
                                        <?php echo DashboardCalculator::formatCurrency($month['gross_revenue']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Refund Analysis -->
            <div class="col-xl-4 col-lg-12 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Refund Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($metrics['refund_analysis']) > 0): ?>
                            <div class="row">
                                <?php foreach ($metrics['refund_analysis'] as $refund): ?>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted d-block"><?php echo ucfirst(str_replace('_', ' ', $refund['refund_type'])); ?></small>
                                                <h6 class="mb-0"><?php echo $refund['count']; ?> cases</h6>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-danger"><?php echo DashboardCalculator::formatCurrency($refund['total_refunded']); ?></div>
                                                <small class="text-success">+<?php echo DashboardCalculator::formatCurrency($refund['total_deductions']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between">
                                    <span>Total Refunds:</span>
                                    <strong class="text-danger"><?php echo DashboardCalculator::formatCurrency($metrics['cash_paid_out']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Deductions Kept:</span>
                                    <strong class="text-success">
                                        <?php 
                                        $totalDeductions = 0;
                                        foreach ($metrics['refund_analysis'] as $refund) {
                                            $totalDeductions += $refund['total_deductions'];
                                        }
                                        echo DashboardCalculator::formatCurrency($totalDeductions);
                                        ?>
                                    </strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Net Refund Impact:</span>
                                    <strong class="<?php echo ($metrics['cash_paid_out'] - $totalDeductions) <= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo DashboardCalculator::formatCurrency($metrics['cash_paid_out'] - $totalDeductions); ?>
                                    </strong>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x mb-3 d-block text-success"></i>
                                No refunds in selected period
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ROW 4: SUMMARY & EXPORT -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-file-alt me-2"></i>
                            Financial Summary Report
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-primary me-2"></i>
                                        <strong>Gross Revenue:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['gross_revenue']); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-warning me-2"></i>
                                        <strong>Total Purchase Costs:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['purchase_costs']); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-info me-2"></i>
                                        <strong>Total Repair Costs:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['repair_costs']); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-danger me-2"></i>
                                        <strong>Total Refunds Paid:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['cash_paid_out']); ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-success me-2"></i>
                                        <strong>Net Business Profit:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['net_business_profit']); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-success me-2"></i>
                                        <strong>Profit Margin:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatPercentage($metrics['profit_margin']); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-dark me-2"></i>
                                        <strong>Vehicles Sold:</strong> 
                                        <span class="float-end"><?php echo $metrics['vehicles_sold']; ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-circle text-dark me-2"></i>
                                        <strong>Inventory Value:</strong> 
                                        <span class="float-end"><?php echo DashboardCalculator::formatCurrency($metrics['inventory']['total_value']); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button class="btn btn-outline-primary me-2" id="printSummary">
                                <i class="fas fa-print me-2"></i>Print Summary
                            </button>
                            <button class="btn btn-outline-success" id="emailReport">
                                <i class="fas fa-envelope me-2"></i>Email Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for Export and Print -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Export to Excel
        document.getElementById('exportBtn').addEventListener('click', function() {
            alert('Export feature would generate an Excel file with all dashboard data.');
            // In real implementation, this would call an export.php script
        });
        
        // Print Summary
        document.getElementById('printSummary').addEventListener('click', function() {
            window.print();
        });
        
        // Email Report
        document.getElementById('emailReport').addEventListener('click', function() {
            const email = prompt('Enter email address to send report:');
            if (email) {
                alert('Report would be emailed to ' + email);
                // In real implementation, this would call an email script
            }
        });
        
        // Date range quick filters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('range')) {
            const range = urlParams.get('range');
            const today = new Date();
            
            let startDate, endDate;
            
            switch(range) {
                case 'today':
                    startDate = today.toISOString().split('T')[0];
                    endDate = startDate;
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'year':
                    startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'all':
                    startDate = '2020-01-01';
                    endDate = today.toISOString().split('T')[0];
                    break;
            }
            
            // Update date inputs
            document.querySelector('input[name="start_date"]').value = startDate;
            document.querySelector('input[name="end_date"]').value = endDate;
        }
    });
    </script>
    
    <!-- Include your theme footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>