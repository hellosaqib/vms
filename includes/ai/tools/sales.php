<?php

/**
 * BritDriveAI
 * Sales AI Tools
 *
 * AI-facing adapter for the shared sales data layer.
 *
 * IMPORTANT:
 * - No SQL belongs in this file.
 * - No business calculations belong in this file.
 * - All actual work is delegated to includes/data/sales.php.
 * - The data layer remains responsible for authentication,
 *   authorization and sales scope.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../data/sales.php';


/* ============================================================
   AI TOOL DEFINITIONS
   ============================================================ */

/**
 * Add consistent GBP presentation to a monetary value.
 *
 * Numeric values remain available for calculations.
 * The formatted value is provided for AI presentation.
 */
function aiSalesMoney(float|int|string|null $value): array
{
    $numericValue = (float) ($value ?? 0);

    return [
        'value' => $numericValue,
        'formatted' => formatCurrency($numericValue)
    ];
}

/**
 * Add GBP formatted versions of common monetary fields
 * returned by sales analysis functions.
 */
function aiFormatSalesMoneyFields(array $row): array
{
    $moneyFields = [
        'total_value' => 'total_value_formatted',
        'total_sales_value' => 'total_sales_value_formatted',
        'average_value' => 'average_value_formatted',
        'average_sale_value' => 'average_sale_value_formatted',
        'minimum_value' => 'minimum_value_formatted',
        'maximum_value' => 'maximum_value_formatted',
        'sale_value' => 'sale_value_formatted',
        'final_sale_value' => 'final_sale_value_formatted',
        'total_revenue' => 'total_revenue_formatted',
        'balance_due' => 'balance_due_formatted',
        'total_balance_due' => 'total_balance_due_formatted',
        'total_refunded' => 'total_refunded_formatted',
        'net_value' => 'net_value_formatted',
        'net_sales_value' => 'net_sales_value_formatted',
        'total_cost' => 'total_cost_formatted',
        'net_profit' => 'net_profit_formatted',
        'commission_amount' => 'commission_amount_formatted',
        'total_commission' => 'total_commission_formatted',
        'payment_amount' => 'payment_amount_formatted',
        'total_payments' => 'total_payments_formatted',
    ];

    foreach ($moneyFields as $field => $formattedField) {

        if (
            array_key_exists($field, $row) &&
            $row[$field] !== null &&
            is_numeric($row[$field])
        ) {
            $row[$formattedField] =
                formatCurrency((float) $row[$field]);
        }
    }

    $row['currency'] = 'GBP';
    $row['currency_symbol'] = '£';

    return $row;
}

function aiFormatSalesMoneyRows(array $results): array
{
    foreach ($results as $index => $row) {
        if (is_array($row)) {
            $results[$index] =
                aiFormatSalesMoneyFields($row);
        }
    }

    return $results;
}
/**
 * Return all sales tools available to BritDriveAI.
 */
function aiGetSalesTools(): array
{
    return [

        /* ----------------------------------------------------
           1. SALES COUNT
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_count',
                'description' =>
                'Get the number of sales the current user is authorised to access. ' .
                    'Optional filters can restrict the result by date, salesperson, status, ' .
                    'payment method, customer or vehicle.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Start date in YYYY-MM-DD format.'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'End date in YYYY-MM-DD format.'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer',
                            'description' => 'Optional salesperson user ID.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Optional sale status.'
                        ],
                        'payment_method' => [
                            'type' => 'string',
                            'description' => 'Optional payment method.'
                        ],
                        'customer_id' => [
                            'type' => 'integer',
                            'description' => 'Optional customer ID.'
                        ],
                        'vehicle_id' => [
                            'type' => 'integer',
                            'description' => 'Optional vehicle ID.'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           2. TOTAL SALE VALUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_total_value',
                'description' =>
                'Get the total final customer sale value for authorised sales. ' .
                    'Final sale value is sales.total_revenue, not the base vehicle sale_price.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Start date in YYYY-MM-DD format.'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'End date in YYYY-MM-DD format.'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ],
                        'customer_id' => [
                            'type' => 'integer'
                        ],
                        'vehicle_id' => [
                            'type' => 'integer'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           3. AVERAGE SALE VALUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_average_value',
                'description' =>
                'Get the average final customer sale value for authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           4. MINIMUM SALE VALUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_minimum_value',
                'description' =>
                'Get the minimum final customer sale value among authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           5. MAXIMUM SALE VALUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_maximum_value',
                'description' =>
                'Get the maximum final customer sale value among authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           6. SALES SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_summary',
                'description' =>
                'Get a sales summary including count, total final sale value, average, ' .
                    'minimum and maximum sale values for authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           7. SALES BY DATE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_date',
                'description' =>
                'Get all authorised sales made on a specific date.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Date in YYYY-MM-DD format.'
                        ]
                    ],
                    'required' => ['date'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           8. SALES BY DATE RANGE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_date_range',
                'description' =>
                'Get authorised sales made between two dates.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => [
                        'date_from',
                        'date_to'
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           9. SALES BY SALESPERSON
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_salesperson',
                'description' =>
                'Get sales belonging to a specified salesperson, subject to the ' .
                    'current user permissions and scope.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'salesperson_id' => [
                            'type' => 'integer',
                            'description' => 'Salesperson user ID.'
                        ]
                    ],
                    'required' => ['salesperson_id'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           10. SALESPERSON SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_salesperson_summary',
                'description' =>
                'Get sales performance summary for a salesperson including sales count, ' .
                    'final sale value, refunds, net sales value and profit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'salesperson_id' => [
                            'type' => 'integer'
                        ]
                    ],
                    'required' => ['salesperson_id'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           11. TOP SALESPEOPLE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_top_salespeople',
                'description' =>
                'Get the highest-performing authorised salespeople ranked by final sale value.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of salespeople to return.'
                        ],
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           12. ALL SALESPEOPLE SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_salespeople_summary',
                'description' =>
                'Get sales performance summaries for all salespeople visible to the current user.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           13. SALES BY STATUS
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_status',
                'description' =>
                'Get authorised sales matching a specific sale status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['status'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           14. STATUS SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_status_summary',
                'description' =>
                'Summarise authorised sales by status including count, final sale value, refunds and net sales value.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           15. SALES BY PAYMENT METHOD
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_payment_method',
                'description' =>
                'Get authorised sales using a specific payment method.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'payment_method' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['payment_method'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           16. PAYMENT METHOD SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_payment_method_summary',
                'description' =>
                'Summarise authorised sales by payment method using final sale value.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           17. SALE BY ID
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_sale_by_id',
                'description' =>
                'Get one authorised sale by its sale ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sale_id' => [
                            'type' => 'integer'
                        ]
                    ],
                    'required' => ['sale_id'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           18. SALE BY INVOICE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_sale_by_invoice',
                'description' =>
                'Find an authorised sale using its invoice number.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_no' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['invoice_no'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           19. SALE BY REGISTRATION
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_sale_by_regnumber',
                'description' =>
                'Find authorised sales using a vehicle registration number.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'regnumber' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['regnumber'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           20. SALES BY CUSTOMER
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_by_customer',
                'description' =>
                'Get authorised sales belonging to a customer.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customer_id' => [
                            'type' => 'integer'
                        ]
                    ],
                    'required' => ['customer_id'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           21. SALE DETAILS
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_sale_details',
                'description' =>
                'Get detailed information about one authorised sale including vehicle, customer, salesperson and finance company information.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sale_id' => [
                            'type' => 'integer'
                        ]
                    ],
                    'required' => ['sale_id'],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           22. TOTAL BALANCE DUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_total_balance_due',
                'description' =>
                'Get the total outstanding balance due across authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           23. TOTAL REFUNDED
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_total_refunded',
                'description' =>
                'Get the total amount refunded across authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           24. NET SALES VALUE
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_net_value',
                'description' =>
                'Get net sales value calculated as final customer sale value minus refunds.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
        25. FINANCIAL SUMMARY
        ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_financial_summary',

                'description' =>
                'Get the current authenticated user\'s authorised financial summary of sales. ' .
                    'Use this tool when the user asks for a financial summary, financial overview, ' .
                    'sales finances, financial performance, my finances, my financial summary, ' .
                    'my sales financial summary, or similar requests. ' .
                    'The word "my" refers to the currently authenticated user and their authorised sales scope. ' .
                    'If no date range is specified, return the summary for all sales the user is authorised to access. ' .
                    'If the user specifies a date range, provide date_from and date_to. ' .
                    'The summary includes final sale value, refunds, net sales value, balance due, ' .
                    'total cost and net profit.',

                'parameters' => [
                    'type' => 'object',

                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' =>
                            'Optional start date in YYYY-MM-DD format. ' .
                                'Only provide this when the user specifies a date or date range.'
                        ],

                        'date_to' => [
                            'type' => 'string',
                            'description' =>
                            'Optional end date in YYYY-MM-DD format. ' .
                                'Only provide this when the user specifies a date or date range.'
                        ]
                    ],

                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           26. SALES TREND
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_trend',
                'description' =>
                'Get daily sales trends including count, final sale value, refunds, net sales value and profit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => [
                        'date_from',
                        'date_to'
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           27. DAILY SALES SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_daily_summary',
                'description' =>
                'Get daily sales summaries for a specified date range.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => [
                        'date_from',
                        'date_to'
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           28. MONTHLY SALES SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_monthly_summary',
                'description' =>
                'Get monthly sales summaries including count, final sale value, refunds, net sales value and profit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           29. COMPARE PERIODS
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_compare_periods',
                'description' =>
                'Compare two sales periods by sales count, final sale value, average sale value and percentage changes.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_from' => [
                            'type' => 'string'
                        ],
                        'first_to' => [
                            'type' => 'string'
                        ],
                        'second_from' => [
                            'type' => 'string'
                        ],
                        'second_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => [
                        'first_from',
                        'first_to',
                        'second_from',
                        'second_to'
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           30. GET SALES
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_sales',
                'description' =>
                'Retrieve a list of authorised sales, including vehicle, salesperson, sale date, payment and financial information. Use this tool when the user asks to see sales records, their latest or most recent sales, or sales matching specific filters. Results are ordered newest first by sale date and sale ID. Use the limit parameter to control how many records are returned.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Optional start date in YYYY-MM-DD format.'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Optional end date in YYYY-MM-DD format.'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer',
                            'description' => 'Optional salesperson filter, subject to server-side permissions.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Optional sale status filter.'
                        ],
                        'payment_method' => [
                            'type' => 'string',
                            'description' => 'Optional payment method filter.'
                        ],
                        'customer_id' => [
                            'type' => 'integer',
                            'description' => 'Optional customer filter.'
                        ],
                        'vehicle_id' => [
                            'type' => 'integer',
                            'description' => 'Optional vehicle filter.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of sales to return. For latest-sales requests, use a small number such as 5 or 10.'
                        ]
                    ],
                    'required' => [],
                    'additionalProperties' => false
                ]
            ]
        ],



        /* ----------------------------------------------------
           31. PAGINATED SALES
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_paginated',
                'description' =>
                'Retrieve a paginated list of authorised sales with safe sorting and filtering.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ],
                        'salesperson_id' => [
                            'type' => 'integer'
                        ],
                        'status' => [
                            'type' => 'string'
                        ],
                        'payment_method' => [
                            'type' => 'string'
                        ],
                        'page' => [
                            'type' => 'integer'
                        ],
                        'per_page' => [
                            'type' => 'integer'
                        ],
                        'sort' => [
                            'type' => 'string'
                        ],
                        'direction' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           32. COMMISSION SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_commission_summary',
                'description' =>
                'Get authorised sales commission totals by salesperson.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ],


        /* ----------------------------------------------------
           33. PAYMENT SUMMARY
           ---------------------------------------------------- */

        [
            'type' => 'function',
            'function' => [
                'name' => 'sales_get_payment_summary',
                'description' =>
                'Get completed payment totals by payment method for authorised sales.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string'
                        ],
                        'date_to' => [
                            'type' => 'string'
                        ]
                    ],
                    'additionalProperties' => false
                ]
            ]
        ]
    ];
}


/* ============================================================
   TOOL EXECUTION
   ============================================================ */

/**
 * Execute a BritDriveAI sales tool.
 *
 * IMPORTANT:
 * Every underlying data function performs its own
 * permission and scope validation.
 */
function aiExecuteSalesTool(
    string $toolName,
    array $arguments,
    PDO $db
): array {
    switch ($toolName) {

        case 'sales_get_count':
            return [
                'count' => getSalesCount(
                    $db,
                    $arguments
                )
            ];


        case 'sales_get_total_value':
            $value = getSalesTotalValue(
                $db,
                $arguments
            );

            return [
                'total_sale_value' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_average_value':
            $value = getSalesAverageValue(
                $db,
                $arguments
            );

            return [
                'average_sale_value' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_minimum_value':
            $value = getSalesMinimumValue(
                $db,
                $arguments
            );

            return [
                'minimum_sale_value' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_maximum_value':
            $value = getSalesMaximumValue(
                $db,
                $arguments
            );

            return [
                'maximum_sale_value' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_summary':
            $results = getSalesSummary(
                $db,
                $arguments
            );

            return aiFormatSalesMoneyFields($results);


        case 'sales_get_by_date':

            $results = getSalesByDate(
                $db,
                (string) ($arguments['date'] ?? '')
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_by_date_range':
            $results = getSalesByDateRange(
                $db,
                (string) ($arguments['date_from'] ?? ''),
                (string) ($arguments['date_to'] ?? '')
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_by_salesperson':
            $results = getSalesBySalesperson(
                $db,
                (int) ($arguments['salesperson_id'] ?? 0)
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_salesperson_summary':
            $results = getSalespersonSummary(
                $db,
                (int) ($arguments['salesperson_id'] ?? 0)
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_top_salespeople':
            $results = getTopSalespeople(
                $db,
                (int) ($arguments['limit'] ?? 10),
                $arguments
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_salespeople_summary':
            $results = getSalespeopleSalesSummary(
                $db,
                $arguments
            );
            return aiFormatSalesMoneyRows($results);

        case 'sales_get_by_status':
            $results = getSalesByStatus(
                $db,
                (string) ($arguments['status'] ?? ''),
                []
            );
            return aiFormatSalesMoneyRows($results);


        case 'sales_get_status_summary':
            return [
                'statuses' => getSalesStatusSummary(
                    $db,
                    $arguments
                )
            ];


        case 'sales_get_by_payment_method':
            $results = getSalesByPaymentMethod(
                $db,
                (string) ($arguments['payment_method'] ?? ''),
                []
            );
            return aiFormatSalesMoneyRows($results);


        case 'sales_get_payment_method_summary':
            $results = getPaymentMethodSummary(
                $db,
                $arguments
            );
            return aiFormatSalesMoneyRows($results);


        case 'sales_get_sale_by_id':
            $results = getSaleById(
                $db,
                (int) ($arguments['sale_id'] ?? 0)
            );
            return aiFormatSalesMoneyFields($results);


        case 'sales_get_sale_by_invoice':
            $results = getSaleByInvoice(
                $db,
                (string) ($arguments['invoice_no'] ?? '')
            );

            return aiFormatSalesMoneyFields($results);


        case 'sales_get_sale_by_regnumber':
            $results = getSaleByRegnumber(
                $db,
                (string) ($arguments['regnumber'] ?? '')
            );
            return aiFormatSalesMoneyFields($results);


        case 'sales_get_by_customer':
            $results = getSaleByCustomer(
                $db,
                (int) ($arguments['customer_id'] ?? 0)
            );
            return aiFormatSalesMoneyRows($results);


        case 'sales_get_sale_details':
            $results = getSaleDetails(
                $db,
                (int) ($arguments['sale_id'] ?? 0)
            );
            return aiFormatSalesMoneyFields($results);

        case 'sales_get_total_balance_due':
            $value = getTotalBalanceDue(
                $db,
                $arguments
            );


            return [
                'total_balance_due' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_total_refunded':
            $value = getTotalRefunded(
                $db,
                $arguments
            );

            return [
                'total_refunded' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_net_value':
            $value = getSalesNetValue(
                $db,
                $arguments
            );

            return [
                'net_sales_value' => $value,
                'formatted_value' => formatCurrency($value),
                'currency' => 'GBP',
                'currency_symbol' => '£'
            ];


        case 'sales_get_financial_summary':
            $results = getSalesFinancialSummary(
                $db,
                $arguments
            );

            return aiFormatSalesMoneyFields($results);


        case 'sales_get_trend':
            $results = getSalesTrend(
                $db,
                (string) ($arguments['date_from'] ?? ''),
                (string) ($arguments['date_to'] ?? '')
            );

            return [
                'trend' => aiFormatSalesMoneyRows($results)
            ];


        case 'sales_get_daily_summary':
            $results = getDailySalesSummary(
                $db,
                (string) ($arguments['date_from'] ?? ''),
                (string) ($arguments['date_to'] ?? '')
            );

            return [
                'daily_summary' => aiFormatSalesMoneyRows($results)
            ];


        case 'sales_get_monthly_summary':
            $results = getMonthlySalesSummary(
                $db,
                $arguments['date_from'] ?? null,
                $arguments['date_to'] ?? null
            );

            return [
                'monthly_summary' => aiFormatSalesMoneyRows($results)
            ];


        case 'sales_compare_periods':
            $results = compareSalesPeriods(
                $db,
                (string) ($arguments['first_from'] ?? ''),
                (string) ($arguments['first_to'] ?? ''),
                (string) ($arguments['second_from'] ?? ''),
                (string) ($arguments['second_to'] ?? '')
            );

            return aiFormatSalesMoneyFields($results);


        case 'sales_get_sales':
            $filters = $arguments;

            $limit = (int) ($filters['limit'] ?? 10);

            unset($filters['limit']);

            $results = getSales(
                $db,
                $filters,
                $limit
            );

            return [
                'sales' => aiFormatSalesMoneyRows($results)
            ];


        case 'sales_get_paginated':
            $results = getSalesPaginated(
                $db,
                $arguments,
                (int)($arguments['page'] ?? 1),
                (int)($arguments['per_page'] ?? 20),
                (string)($arguments['sort'] ?? 'sale_date'),
                (string)($arguments['direction'] ?? 'DESC')
            );

            // The shared function returns rows under "data".
            // Preserve pagination metadata while formatting money.
            if (isset($results['data']) && is_array($results['data'])) {
                $results['data'] = aiFormatSalesMoneyRows($results['data']);
            }

            return $results;


        case 'sales_get_commission_summary':
            $results = getSalesCommissionSummary(
                $db,
                $arguments
            );

            return [
                'commissions' => aiFormatSalesMoneyRows($results)
            ];


        case 'sales_get_payment_summary':
            $results = getSalesPaymentSummary(
                $db,
                $arguments
            );

            return [
                'payments' => aiFormatSalesMoneyRows($results)
            ];


        default:
            throw new RuntimeException(
                'Unknown BritDriveAI sales tool: ' . $toolName
            );
    }
}
