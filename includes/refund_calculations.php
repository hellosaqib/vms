<?php
class RefundCalculator
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate maximum refundable amount for a sale
     */
    public function getMaxRefundableAmount($sale_id)
    {
        try {
            $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(
                    CASE 
                        WHEN transaction_type = 'payment' THEN amount 
                        WHEN transaction_type = 'refund' THEN -amount 
                    END
                ), 0) as net_amount_received
            FROM sale_payments 
            WHERE sale_id = ? 
            AND status = 'completed'
        ");
            $stmt->execute([$sale_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return max(0, (float)$result['net_amount_received']);
        } catch (PDOException $e) {
            error_log("Error calculating max refund: " . $e->getMessage());
            return 0;
        }
    }
    /**
     * Calculate net profit/loss after refund
     */
    public function calculateNetProfitAfterRefund($sale_id, $refund_amount)
    {
        try {
            // Get original sale data
            $stmt = $this->db->prepare("
                SELECT 
                    s.sale_price,
                    s.purchase_price,
                    s.service_fee,
                    s.delivery_charges,
                    s.part_exchange_allowance,
                    COALESCE((
                        SELECT SUM(amount) 
                        FROM sale_payments 
                        WHERE sale_id = s.sale_id 
                        AND transaction_type = 'payment' 
                        AND status = 'completed'
                    ), 0) as total_payments_received,
                    COALESCE((
                        SELECT SUM(amount) 
                        FROM sale_payments 
                        WHERE sale_id = s.sale_id 
                        AND transaction_type = 'refund' 
                        AND status = 'completed'
                    ), 0) as total_refunds_given
                FROM sales s 
                WHERE s.sale_id = ?
            ");
            $stmt->execute([$sale_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) return 0;

            // Calculate original profit
            $original_profit = $sale['sale_price']
                - $sale['purchase_price']
                + $sale['service_fee']
                + $sale['delivery_charges']
                - $sale['part_exchange_allowance'];

            // Calculate profit after refund
            $profit_after_refund = $original_profit - $refund_amount;

            return [
                'original_profit' => $original_profit,
                'profit_after_refund' => $profit_after_refund,
                'profit_change' => -$refund_amount,
                'total_payments_received' => $sale['total_payments_received'],
                'total_refunds_given' => $sale['total_refunds_given']
            ];
        } catch (PDOException $e) {
            error_log("Error calculating profit: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record refund transaction in sale_payments (with NEGATIVE amount)
     */
    public function recordRefundTransaction($refund_data)
    {
       //echo "DEBUG recordRefundTransaction: ".$refund_data['sale_id'].",".$refund_data['invoice_no'].",".$refund_data['transaction_date'].",".$refund_data['amount'].",".$refund_data['payment_method'].",".$refund_data['reference_number'].",".$refund_data['notes'].",".$refund_data['processed_by'];
        //die;
        try {

            $stmt = $this->db->prepare("
                INSERT INTO sale_payments (
                    sale_id, invoice_no, transaction_type, transaction_date, 
                    amount, payment_method, reference_number, status, notes, 
                    processed_by, paid_by
                ) 
                VALUES (?, ?, 'refund', ?, ?, ?, ?, 'completed', ?, ?, 'company')
            ");

            // Note: amount is NEGATIVE for refunds
            $stmt->execute([
                $refund_data['sale_id'],
                $refund_data['invoice_no'],
                $refund_data['transaction_date'],
                $refund_data['amount'],  // ← NEGATIVE AMOUNT
                $refund_data['payment_method'],
                $refund_data['reference_number'],
                $refund_data['notes'],
                $refund_data['processed_by']
            ]);

            return  $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error recording refund transaction: " . $e->getMessage());
            return false;
        }
    }
//     public function getRefundDetails($sale_id) {
//     try {
//         $stmt = $this->db->prepare("
//             SELECT 
//                 s.sale_id, s.invoice_no, s.sale_price, s.deposit_received,
//                 s.part_exchange_allowance, s.finance_amount, s.px_vehicle_id,
//                 s.finance_company_id, s.discount_amount,
//                 fc.company_name as finance_company_name,
//                 -- Get total refunds already given
//                 COALESCE((
//     SELECT SUM(net_refund_amount) 
//     FROM refunds 
//     WHERE sale_id = s.sale_id 
//     AND status IN ('approved', 'pending')  -- Only count actual refunds
// ), 0) as already_refunded
//             FROM sales s 
//             LEFT JOIN finance_companies fc ON s.finance_company_id = fc.finance_company_id
//             WHERE s.sale_id = ?
//         ");
//         $stmt->execute([$sale_id]);
//         $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         if (!$sale) return null;
        
//         // SIMPLE CALCULATION: Maximum refundable = deposit received - already refunded
//         $max_refundable = (float)$sale['deposit_received'] - (float)$sale['already_refunded'];
        
//         // Determine sales type
//         $has_finance = (float)$sale['finance_amount'] > 0;
//         $has_part_exchange = (float)$sale['part_exchange_allowance'] > 0;
        
//         if ($has_finance && $has_part_exchange) {
//             $sales_type = 'finance_partexchange';
//         } elseif ($has_finance) {
//             $sales_type = 'finance_only';
//         } elseif ($has_part_exchange) {
//             $sales_type = 'partexchange_only';
//         } else {
//             $sales_type = 'normal';
//         }
        
//         return [
//             'sales_type' => $sales_type,
//             'max_refundable' => max(0, $max_refundable),
//             'sale_data' => $sale,
//             'has_finance' => $has_finance,
//             'has_part_exchange' => $has_part_exchange,
//             'already_refunded' => (float)$sale['already_refunded']
//         ];
        
//     } catch (PDOException $e) {
//         error_log("Error getting refund details: " . $e->getMessage());
//         return null;
//     }
// }
public function getRefundDetails($sale_id) {
    try {
        $stmt = $this->db->prepare("
            SELECT 
                s.sale_id, s.invoice_no, s.sale_price, s.deposit_received,
                s.part_exchange_allowance, s.finance_amount, s.px_vehicle_id,s.payment_method,
                s.discount_amount, s.service_fee, s.delivery_charges,
                s.total_revenue, s.net_profit, s.total_cost,
                s.finance_company_name as finance_company_name,
                COALESCE((
                    SELECT SUM(total_deductions) 
                    FROM refunds 
                    WHERE sale_id = s.sale_id
                    ), 0) as total_previous_deductions,
                -- Get total refunds already given (using net_refund_amount)
                COALESCE((
                    SELECT 
                        -- Cash from non-repair refunds
                        SUM(CASE 
                            WHEN refund_type != 'repair_compensation' 
                            THEN net_refund_amount 
                            ELSE 0 
                        END) +
                        -- Deductions from ALL refunds
                        SUM(total_deductions)
                    FROM refunds 
                    WHERE sale_id = s.sale_id 
                    AND status IN ('approved', 'pending')
                ), 0) as already_refunded,
                -- Get max_refundable_before from last refund if exists
                COALESCE((
                    SELECT max_refundable_before
                    FROM refunds 
                    WHERE sale_id = s.sale_id 
                    AND status IN ('approved', 'pending')
                    ORDER BY created_at DESC 
                    LIMIT 1
                ), s.deposit_received) as current_max_refundable_before,
                -- Get max_refundable_after from last refund if exists
                COALESCE((
                    SELECT max_refundable_after
                    FROM refunds 
                    WHERE sale_id = s.sale_id 
                    AND status IN ('approved', 'pending')
                    ORDER BY created_at DESC 
                    LIMIT 1
                ), s.deposit_received) as current_max_refundable_after
            FROM sales s 
            WHERE s.sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) return null;
        
        // Calculate maximum refundable amount for a NEW refund
        // This is the max_refundable_before for a potential new refund
        $max_refundable_before = (float)$sale['current_max_refundable_after'];
        
        // Determine sales type
        $has_finance = (float)$sale['finance_amount'] > 0;
        $has_part_exchange = (float)$sale['part_exchange_allowance'] > 0;
        
        if ($has_finance && $has_part_exchange) {
            $sales_type = 'finance_partexchange';
        } elseif ($has_finance) {
            $sales_type = 'finance_only';
        } elseif ($has_part_exchange) {
            $sales_type = 'partexchange_only';
        } else {
            $sales_type = 'normal';
        }
        
        return [
            'sales_type' => $sales_type,
            'max_refundable' => max(0, $max_refundable_before), // For backward compatibility
            'max_refundable_before' => max(0, $max_refundable_before),
            'max_refundable_after' => (float)$sale['current_max_refundable_after'],
            'sale_data' => $sale,
            'has_finance' => $has_finance,
            'has_part_exchange' => $has_part_exchange,
            'already_refunded' => (float)$sale['already_refunded'],
            'current_max_refundable_before' => (float)$sale['current_max_refundable_before'],
            'current_max_refundable_after' => (float)$sale['current_max_refundable_after']
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting refund details: " . $e->getMessage());
        return null;
    }
}
}
