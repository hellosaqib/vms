<?php
require_once __DIR__ . '/../../includes/config/config.php';
// global $URL;

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'title' => 'Dashboard',
        'icon'  => 'layout',
        'url'   => $URL . '/index.php',
    ],


    /*
    |--------------------------------------------------------------------------
    | Vehicles
    |--------------------------------------------------------------------------
    */
    'vehicles' => [
        'title' => 'Vehicles',
        'icon'  => 'car',

        'submenu' => [

            'view-vehicles' => [
                'title'      => 'View All Vehicles',
                'url'        => $URL . '/view-all-vehicles.php',
                'permission' => 'vehicles.view',
            ],

            'add-vehicle' => [
                'title'      => 'Add New Vehicle',
                'url'        => $URL . '/add-vehicle.php',
                'permission' => 'vehicles.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Auctions
    |--------------------------------------------------------------------------
    */
    'auctions' => [
        'title' => 'Auctions',
        'icon'  => 'gavel',

        'submenu' => [

            'view-auctions' => [
                'title'      => 'View All Auctions',
                'url'        => $URL . '/view-all-auctions.php',
                'permission' => 'auctions.view',
            ],

            'add-auction' => [
                'title'      => 'Add New Auction',
                'url'        => $URL . '/add-auction.php',
                'permission' => 'auctions.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    */
    'sales' => [
        'title' => 'Sales',
        'icon'  => 'pound-sign',

        'submenu' => [

            'sales-history' => [
                'title'      => 'Sales History',
                'url'        => $URL . '/sales-history.php',
                'permission' => 'sales.view',
            ],

            'view-all-invoices' => [
                'title'      => 'View All Invoices',
                'url'        => $URL . '/view-all-invoices.php',
                'permission' => 'sales.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */
    'customers' => [
        'title' => 'Customers',
        'icon'  => 'users',

        'submenu' => [

            'view-customers' => [
                'title'      => 'View Customers',
                'url'        => $URL . '/manage-customers.php',
                'permission' => 'customers.view',
            ],

            'add-customer' => [
                'title'      => 'Add New Customer',
                'url'        => $URL . '/add-new-customer.php',
                'permission' => 'customers.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Finance
    |--------------------------------------------------------------------------
    */
    'finance' => [
        'title' => 'Finance',
        'icon'  => 'credit-card',

        'submenu' => [

            'finance' => [
                'title'      => 'Finance',
                'url'        => $URL . '/finance.php',
                'permission' => 'finance.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Finance Companies
    |--------------------------------------------------------------------------
    */
    'finance-companies' => [
        'title' => 'Finance Companies',
        'icon'  => 'building',

        'submenu' => [

            'view-finance-companies' => [
                'title'      => 'View All Finance Companies',
                'url'        => $URL . '/view-all-finance-companies.php',
                'permission' => 'finance_companies.view',
            ],

            'add-finance-company' => [
                'title'      => 'Add New Finance Company',
                'url'        => $URL . '/add-new-finance-company.php',
                'permission' => 'finance_companies.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */
    'refunds' => [
        'title' => 'Refunds',
        'icon'  => 'undo',

        'submenu' => [

            'view-refunds' => [
                'title'      => 'View All Refunds',
                'url'        => $URL . '/refund-management.php',
                'permission' => 'refunds.view',
            ],

            'create-refund' => [
                'title'      => 'Create Refund',
                'url'        => $URL . '/create-refund.php',
                'permission' => 'refunds.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    */
    'tasks' => [
        'title' => 'Tasks',
        'icon'  => 'check-square',

        'submenu' => [

            'view-tasks' => [
                'title'      => 'View Tasks',
                'url'        => $URL . '/tasks.php',
                'permission' => 'tasks.view',
            ],

            'create-task' => [
                'title'      => 'Create Task',
                'url'        => $URL . '/create-task.php',
                'permission' => 'tasks.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Job Cards
    |--------------------------------------------------------------------------
    */
    'job-cards' => [
        'title' => 'Job Cards',
        'icon'  => 'clipboard',

        'submenu' => [

            'view-job-cards' => [
                'title'      => 'View All Job Cards',
                'url'        => $URL . '/view-all-job-cards.php',
                'permission' => 'tasks.view',
            ],

            'add-job-card' => [
                'title'      => 'Add New Job Card',
                'url'        => $URL . '/add-new-job-card.php',
                'permission' => 'tasks.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    */
    'commission' => [
        'title' => 'Commission',
        'icon'  => 'percent',

        'submenu' => [

            'view-commission' => [
                'title'      => 'View Commission',
                'url'        => $URL . '/commission.php',
                'permission' => 'commission.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Earning Goals
    |--------------------------------------------------------------------------
    */
    'earning-goals' => [
        'title' => 'Earning Goals',
        'icon'  => 'award',

        'submenu' => [

            'view-earning-goals' => [
                'title'      => 'View Earning Goals',
                'url'        => $URL . '/earning-goals.php',
                'permission' => 'earning_goals.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Staff Performance
    |--------------------------------------------------------------------------
    */
    'staff-performance' => [
        'title' => 'Staff Performance',
        'icon'  => 'bar-chart',

        'submenu' => [

            'view-performance' => [
                'title'      => 'View Staff Performance',
                'url'        => $URL . '/staff-performance.php',
                'permission' => 'staff_performance.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'title' => 'Reports',
        'icon'  => 'archive',

        'submenu' => [

            'accounts-report' => [
                'title'      => 'Accounts Report',
                'url'        => $URL . '/accounts-report.php',
                'permission' => 'reports.view',
            ],

            'gross-profit-report' => [
                'title'      => 'Gross Profit Report',
                'url'        => $URL . '/gross-profit.php',
                'permission' => 'reports.view',
            ],

            'inventory-aging-report' => [
                'title'      => 'Inventory Aging Report',
                'url'        => $URL . '/inventory-aging-report.php',
                'permission' => 'reports.view',
            ],

            'profit-loss-report' => [
                'title'      => 'Profit & Loss Statement',
                'url'        => $URL . '/profit-loss-report.php',
                'permission' => 'reports.view',
            ],

            'roi-by-vehicle-report' => [
                'title'      => 'ROI By Vehicle Report',
                'url'        => $URL . '/roi-by-vehicle-report.php',
                'permission' => 'reports.view',
            ],

            'workshop-efficiency-report' => [
                'title'      => 'Workshop Efficiency Report',
                'url'        => $URL . '/workshop-efficiency-report.php',
                'permission' => 'reports.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    'users' => [
        'title' => 'Users',
        'icon'  => 'users',

        'submenu' => [

            'view-users' => [
                'title'      => 'Manage Users',
                'url'        => $URL . '/manage-users.php',
                'permission' => 'users.view',
            ],

            'add-user' => [
                'title'      => 'Add New User',
                'url'        => $URL . '/add-new-user.php',
                'permission' => 'users.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'title' => 'Roles',
        'icon'  => 'shield',

        'submenu' => [

            'view-roles' => [
                'title'      => 'Manage Roles',
                'url'        => $URL . '/manage-roles.php',
                'permission' => 'roles.view',
            ],

            'create-role' => [
                'title'      => 'Create Role',
                'url'        => $URL . '/create-role.php',
                'permission' => 'roles.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'title' => 'Permissions',
        'icon'  => 'key',

        'submenu' => [

            'view-permissions' => [
                'title'      => 'Manage Permissions',
                'url'        => $URL . '/manage-permissions.php',
                'permission' => 'permissions.view',
            ],

            'create-permission' => [
                'title'      => 'Create Permission',
                'url'        => $URL . '/create-permission.php',
                'permission' => 'permissions.create',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Settings',
        'icon'  => 'cog',

        'submenu' => [

            'settings-dashboard' => [
                'title'      => 'Settings Dashboard',
                'url'        => $URL . '/settings-dashboard.php',
                'permission' => 'settings.view',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */
    'logout' => [
        'title' => 'Logout',
        'icon'  => 'log-out',
        'url'   => $URL . '/logout.php',
    ],

];