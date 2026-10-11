<?php
declare(strict_types=1);

/**
 * BritDriveAI Presentation Builder
 *
 * Converts trusted AI tool results into structured presentation data.
 *
 * IMPORTANT:
 * - No database queries.
 * - No AI calls.
 * - No permission decisions.
 * - No HTML rendering.
 *
 * The data has already passed through the authorised
 * BritDrive data layer and AI tool adapter.
 */


/**
 * Build a presentation object from a completed AI tool call.
 *
 * @param string|null $toolName
 * @param mixed $toolResult
 *
 * @return array|null
 */
function aiBuildPresentation(
    ?string $toolName,
    mixed $toolResult
): ?array {

    /*
     * No presentation is possible without
     * a recognised tool and structured result.
     */
    if (
        $toolName === null ||
        !is_array($toolResult)
    ) {
        return null;
    }


    /*
     * ---------------------------------------------------------
     * METRIC
     * ---------------------------------------------------------
     */

    if ($toolName === 'sales_get_count') {

        $count = (int) (
            $toolResult['count']
            ?? 0
        );

        return [
            'type' => 'metric',

            'data' => [
                'label' => 'Sales',

                'value' => $count,

                'formatted_value' =>
                    number_format($count)
            ]
        ];
    }


    /*
     * ---------------------------------------------------------
     * TOTAL SALES VALUE
     * ---------------------------------------------------------
     */

    if ($toolName === 'sales_get_total_value') {

        $value = (float) (
            $toolResult['total_sale_value']
            ?? 0
        );

        $formattedValue =
            $toolResult['formatted_value']
            ?? $toolResult['total_sale_value_formatted']
            ?? formatCurrency($value);

        return [
            'type' => 'metric',

            'data' => [
                'label' => 'Total Sales Value',

                'value' => $value,

                'formatted_value' => $formattedValue,

                'currency' =>
                    $toolResult['currency']
                    ?? 'GBP',

                'currency_symbol' =>
                    $toolResult['currency_symbol']
                    ?? '£'
            ]
        ];
    }


    /*
     * ---------------------------------------------------------
     * FINANCIAL SUMMARY
     * ---------------------------------------------------------
     */

    if (
        $toolName === 'sales_get_financial_summary'
    ) {

        return [
            'type' => 'financial_summary',

            'data' => [

                'sales_count' =>
                    (int) (
                        $toolResult['sales_count']
                        ?? 0
                    ),

                'total_sale_value' =>
                    (float) (
                        $toolResult['total_sale_value']
                        ?? 0
                    ),

                'total_sale_value_formatted' =>
                    $toolResult['total_sale_value_formatted']
                    ?? '£0.00',


                'total_refunded' =>
                    (float) (
                        $toolResult['total_refunded']
                        ?? 0
                    ),

                'total_refunded_formatted' =>
                    $toolResult['total_refunded_formatted']
                    ?? '£0.00',


                'net_sales_value' =>
                    (float) (
                        $toolResult['net_sales_value']
                        ?? 0
                    ),

                'net_sales_value_formatted' =>
                    $toolResult['net_sales_value_formatted']
                    ?? '£0.00',


                'total_balance_due' =>
                    (float) (
                        $toolResult['total_balance_due']
                        ?? 0
                    ),

                'total_balance_due_formatted' =>
                    $toolResult['total_balance_due_formatted']
                    ?? '£0.00',


                'total_cost' =>
                    (float) (
                        $toolResult['total_cost']
                        ?? 0
                    ),

                'total_cost_formatted' =>
                    $toolResult['total_cost_formatted']
                    ?? '£0.00',


                'net_profit' =>
                    (float) (
                        $toolResult['net_profit']
                        ?? 0
                    ),

                'net_profit_formatted' =>
                    $toolResult['net_profit_formatted']
                    ?? '£0.00',


                'currency' =>
                    $toolResult['currency']
                    ?? 'GBP',

                'currency_symbol' =>
                    $toolResult['currency_symbol']
                    ?? '£'
            ]
        ];
    }


    /*
     * ---------------------------------------------------------
     * UNSUPPORTED TOOL
     * ---------------------------------------------------------
     *
     * The normal AI response will still be displayed.
     * Structured presentation will simply be omitted.
     */
    return null;
}
?>