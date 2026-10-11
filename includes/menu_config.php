<?php
global $URL;
return [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'layout',
        'url' => $URL.'/index.php',
        'permission' => 'view_dashboard'
    ],
    'Auctions' => [
        'title' => 'Auctions',
        'icon' => 'gavel',
        'permission' => 'manage_settings',
        'submenu' => [
            'add-auction' => [
                'title' => 'Add New Auction',
                'url' => $URL.'/add-auction.php',
                'permission' => 'manage_settings'
            ],
            'view-auctions' => [
                'title' => 'View All Auctions',
                'url' => $URL.'/view-all-auctions.php',
                'permission' => 'manage_settings'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'FinanceCompanies' => [
        'title' => 'Finance Companies',
        'icon' => 'building',
        'permission' => 'manage_settings',
        'submenu' => [
            'add-new-finance-company' => [
                'title' => 'Add New Finance Company',
                'url' => $URL.'/add-new-finance-company.php',
                'permission' => 'manage_settings'
            ],
            'view-all-finance-companies' => [
                'title' => 'View All Finance Companies',
                'url' => $URL.'/view-all-finance-companies.php',
                'permission' => 'manage_settings'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'Sales' => [
        'title' => 'Sales',
        'icon' => 'pound-sign',
        'permission' => 'manage_sales',
        'submenu' => [
            'sales-history' => [
                'title' => 'My Sales History',
                'url' => $URL.'/sales-history.php',
                'permission' => 'manage_sales'
            ],
            'view-all-invoices' => [
                'title' => 'View All Invoices',
                'url' => $URL.'/view-all-invoices.php',
                'permission' => 'manage_sales'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'Refund' => [
        'title' => 'Refunds',
        'icon' => 'fas fa-exchange-alt',
        'permission' => 'manage_refunds',
        'submenu' => [
            'add-new-refund' => [
                'title' => 'Add New Refund',
                'url' => $URL.'/create-refund.php',
                'permission' => 'manage_refunds'
            ],
            'view-all-refunds' => [
                'title' => 'View All Refunds',
                'url' => $URL.'/refund-management.php',
                'permission' => 'manage_refunds'
            ],
        ]
    ],
    'Vehicles' => [
        'title' => 'Vehicle',
        'icon' => 'car',
        'permission' => 'manage_vehicles',
        'submenu' => [
            'add-vehicle' => [
                'title' => 'Add New Vehicle',
                'url' => $URL.'/add-vehicle.php',
                'permission' => 'manage_vehicles'
            ],
            'view-vehicles' => [
                'title' => 'View All Vehicles',
                'url' => $URL.'/view-all-vehicles.php',
                'permission' => 'manage_vehicles'
            ],
            // Add other CRM submenu items...
        ]
    ],
    // 'Repair' => [
    //     'title' => 'Repair',
    //     'icon' => 'tools',
    //     'permission' => 'manage_repairs',
    //     'submenu' => [
    //         'add-repair' => [
    //             'title' => 'Add New Repair',
    //             'url' => $URL.'/add-repair.php',
    //             'permission' => 'manage_repairs'
    //         ],
    //         'view-repairs' => [
    //             'title' => 'View All Repairs',
    //             'url' => $URL.'/view-all-repairs.php',
    //             'permission' => 'manage_repairs'
    //         ],
    //         // Add other CRM submenu items...
    //     ]
    // ],
    'Expense' => [
        'title' => 'Expenses',
        'icon' => 'money-bill-wave',
        'permission' => 'manage_expenses',
        'submenu' => [
            'expense-dashboard' => [
                'title' => 'Expense Dashboard',
                'url' => $URL.'/expense-dashboard.php',
                'permission' => 'manage_expenses'
            ],
            'add-expense' => [
                'title' => 'Add General Expense',
                'url' => $URL.'/add-expense.php',
                'permission' => 'manage_expenses'
            ],
            'view-expenses' => [
                'title' => 'Add General Repair',
                'url' => $URL.'/add-repair.php',
                'permission' => 'manage_expenses'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'Reports' => [
        'title' => 'Reports',
        'icon' => 'archive',
        'permission' => 'manage_reports',
        'submenu' => [
            'accounts-report' => [
                'title' => 'Accounts Report',
                'url' => $URL.'/accounts-report.php',
                'permission' => 'manage_settings'
            ],
            'gross-profit-report' => [
                'title' => 'Gross Profit Report',
                'url' => $URL.'/gross-profit.php',
                'permission' => 'manage_reports'
            ],
            'inventory-aging-report' => [
                'title' => 'Inventory Aging Report',
                'url' => $URL.'/inventory-aging-report.php',
                'permission' => 'manage_reports'
            ],
            'profit-loss-report' => [
                'title' => 'Profit & Loss Statement',
                'url' => $URL.'/profit-loss-report.php',
                'permission' => 'manage_reports'
            ],
            'roi-by-vehicle-reports' => [
                'title' => 'ROI By Vehicle Report',
                'url' => $URL.'/roi-by-vehicle-report.php',
                'permission' => 'manage_reports'
            ],
            'workshop-efficiency-reports' => [
                'title' => 'Workshop Efficiency Report',
                'url' => $URL.'/workshop-efficiency-report.php',
                'permission' => 'manage_reports'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'Job-Cards' => [
        'title' => 'Job Cards',
        'icon' => 'clipboard',
        'permission' => 'manage_job_cards',
        'submenu' => [
            'add-job-card' => [
                'title' => 'Add New Job Card',
                'url' => $URL.'/add-new-job-card.php',
                'permission' => 'manage_settings'
            ],
            'view-job-cards' => [
                'title' => 'View All Job Cards',
                'url' => $URL.'/view-all-job-cards.php',
                'permission' => 'manage_job_cards'
            ],
            'view-all-cost' => [
                'title' => 'View All Costs',
                'url' => $URL.'/view-all-costs.php',
                'permission' => 'manage_job_cards'
            ],
            
            // Add other CRM submenu items...
        ]
    ],
    // 'Customers' => [
    //     'title' => 'Customers',
    //     'icon' => 'user-check',
    //     'permission' => 'manage_customers',
    //     'submenu' => [
    //         'add-customer' => [
    //             'title' => 'Add New customer',
    //             'url' => $URL.'/add-new-customer.php',
    //             'permission' => 'manage_customers'
    //         ],
    //         'manage-customers' => [
    //             'title' => 'Manage All Customers',
    //             'url' => $URL.'/manage-customers.php',
    //             'permission' => 'manage_customers',
    //         ],
    //         // Add other CRM submenu items...
    //     ]
    // ],
    'users' => [
        'title' => 'Users',
        'icon' => 'users',
        'permission' => 'manage_settings',
        'submenu' => [
            'add-newusers' => [
                'title' => 'Add New user',
                'url' => $URL.'/add-new-user.php',
                'permission' => 'manage_settings'
            ],
            'manage-users' => [
                'title' => 'Manage All users',
                'url' => $URL.'/manage-users.php',
                'permission' => 'manage_settings'
            ],
            // Add other CRM submenu items...
        ]
    ],
    'settings' => [
        'title' => 'Settings',
        'icon' => 'cog',
        'permission' => 'manage_settings',
        'submenu' => [
            'settings-dashboard' => [
                'title' => 'Settings Dashboard',
                'url' => $URL.'/settings-dashboard.php',
                'permission' => 'manage_settings'
            ],
            // 'manage-users' => [
            //     'title' => 'Add New Permissions',
            //     'url' => $URL.'/manage-users.php',
            //     'permission' => 'manage_settings'
            // ],
            // Add other CRM submenu items...
        ]
    ],
    'logout' => [
        'title' => 'Logout',
        'icon' => 'log-out',
        'url' => $URL.'/logout.php',
        'permission' => 'view_dashboard'
    ],
    // Add other main menu items...
];
?>