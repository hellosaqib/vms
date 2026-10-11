<?php 
// Handle refund creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_refund_old'])) {
    try {
        $db->beginTransaction();

        // Get form data
        $sale_id = (int)$_POST['sale_id'];
        $refund_type = $_POST['refund_type'];
        $gross_refund_amount = (float)$_POST['gross_refund_amount'];
        $vehicle_returned = isset($_POST['vehicle_returned']) ? 1 : 0;
        $px_vehicle_returned = isset($_POST['px_vehicle_returned']) ? 1 : 0; // NEW: P/X vehicle return
        $refund_reason = trim($_POST['refund_reason']);
        $customer_agreement = trim($_POST['customer_agreement'] ?? '');
        $payment_method = $_POST['payment_method'];
        $refund_date = $_POST['refund_date'];
        $reference_number = trim($_POST['reference_number']);

        // Get refund details for sales type handling
        $refund_details = $refundCalculator->getRefundDetails($sale_id);
        $sales_type = $refund_details['sales_type'];
        // Process deductions
        $deductions = [];
        $total_deductions = 0;

        if (isset($_POST['deductions']) && is_array($_POST['deductions'])) {
            foreach ($_POST['deductions'] as $deduction) {
                if (!empty($deduction['type']) && !empty($deduction['amount']) && $deduction['amount'] > 0) {
                    $deduction_amount = (float)$deduction['amount'];
                    $deductions[] = [
                        'type' => $deduction['type'],
                        'description' => $deduction['description'] ?? '',
                        'amount' => $deduction_amount
                    ];
                    $total_deductions += $deduction_amount;
                }
            }
        }

        // Calculate net refund amount
        $net_refund_amount = $gross_refund_amount - $total_deductions;

        if ($net_refund_amount < 0) {
            throw new Exception("Net refund amount cannot be negative. Deductions exceed refund amount.");
        }

        // Validate refund amount doesn't exceed maximum
        $remaining_refundable = $refund_details['max_refundable'];
        if ($gross_refund_amount > $remaining_refundable) {
            throw new Exception("Gross refund amount cannot exceed remaining refundable amount of £" . number_format($remaining_refundable, 2));
        }

        // Determine vehicle status after refund
        $vehicle_status_after = 'sold'; // Default - customer keeps vehicle
        if ($vehicle_returned) {
            if ($refund_type === 'full_return' || $refund_type === 'partial_return') {
                $vehicle_status_after = 'available';
            }
        }

        // Insert refund record - UPDATED with px_vehicle_returned
        $stmt = $db->prepare("
            INSERT INTO refunds (
                sale_id, original_invoice_no, refund_type, refund_amount, 
                total_deductions, net_refund_amount, vehicle_returned, 
                px_vehicle_returned, vehicle_status_after, refund_reason, 
                customer_agreement, status, created_by
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        $stmt->execute([
            $sale_id,
            $_POST['original_invoice_no'],
            $refund_type,
            $gross_refund_amount,
            $total_deductions,
            $net_refund_amount,
            $vehicle_returned,
            $px_vehicle_returned, // NEW
            $vehicle_status_after,
            $refund_reason,
            $customer_agreement,
            $_SESSION['user_id']
        ]);

        $refund_id = $db->lastInsertId();

        // Insert deductions
        foreach ($deductions as $deduction) {
            $stmt = $db->prepare("
                INSERT INTO refund_deductions (refund_id, deduction_type, description, amount)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $refund_id,
                $deduction['type'],
                $deduction['description'],
                $deduction['amount']
            ]);
        }

        // Record refund transaction in sale_payments (with NEGATIVE amount)
        $refund_transaction = [
            'sale_id' => $sale_id,
            'invoice_no' => $_POST['original_invoice_no'] . '-REF',
            'transaction_date' => $refund_date,
            'amount' => -$net_refund_amount, // Negative amount for refund
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'notes' => "Refund: $refund_reason",
            'processed_by' => $_SESSION['user_id']
        ];

        $transaction_id = $refundCalculator->recordRefundTransaction($refund_transaction);

        if (!$transaction_id) {
            throw new Exception("Failed to record refund transaction.");
        }

        // === UPDATE SALES STATUS ===
        $sale_status = 'completed'; // default

        if ($refund_type === 'full_return') {
            $sale_status = 'refunded';
        } elseif (in_array($refund_type, ['partial_return', 'partial_keep', 'repair_compensation'])) {
            $sale_status = 'partially_refunded';
        }

        $stmt = $db->prepare("UPDATE sales SET status = ?, updated_at = NOW() WHERE sale_id = ?");
        $stmt->execute([$sale_status, $sale_id]);

        // === UPDATE VEHICLE STATUS (if returned) ===
        if ($vehicle_returned && in_array($refund_type, ['full_return', 'partial_return'])) {
            $vehicle_status_after = 'available';

            $stmt = $db->prepare("UPDATE vehicles SET status = ?, updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_status_after, $_POST['vehicle_id']]);

            // Add refund note to vehicle
            $refund_note = " - Returned via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$refund_note, $_POST['vehicle_id']]);
        }

        // === HANDLE PART-EXCHANGE VEHICLE RETURN ===
        if ($px_vehicle_returned && !empty($refund_details['sale_data']['px_vehicle_id'])) {
            // Update P/X vehicle status to 'refunded_to_customer'
            $stmt = $db->prepare("UPDATE vehicles SET status = 'refunded_to_customer', updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$refund_details['sale_data']['px_vehicle_id']]);

            // Add note to P/X vehicle
            $px_refund_note = " - Returned to customer via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$px_refund_note, $refund_details['sale_data']['px_vehicle_id']]);
        }

        // === HANDLE FINANCE COMPANY NOTIFICATION ===
        if ($sale_data['payment_method'] == 'finance' && $refund_type === 'full_return') {
            $finance_note = " [FINANCE: Contact " . $sale_data['finance_company_name'] . " to process £" . number_format($sale_data['finance_amount'], 2) . " refund]";
            $stmt = $db->prepare("UPDATE refunds SET customer_agreement = CONCAT(COALESCE(customer_agreement, ''), ?) WHERE refund_id = ?");
            $stmt->execute([$finance_note, $refund_id]);
        }

        $total_costs = $total_costs + $net_refund_amount;
        $net_profit = $total_revenue - $total_costs;
        // === UPDATE BALANCE DUE IN SALES TABLE ===
        $stmt = $db->prepare("
            UPDATE sales 
            SET balance_due = balance_due - ? 
            WHERE sale_id = ?
        ");
        $stmt->execute([$net_refund_amount, $sale_id]);

        $db->commit();

        $_SESSION['success'] = "Refund created successfully! Refund ID: RF-" . str_pad($refund_id, 5, '0', STR_PAD_LEFT);
        header("Location: refund-details.php?id=$refund_id");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error creating refund: " . $e->getMessage();
    }
}
?>