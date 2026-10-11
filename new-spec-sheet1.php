<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/refund_calculations.php';
require_once 'includes/errors.php';

$pageTitle = "Create New Refund";
$refundCalculator = new RefundCalculator($db);

// Check permissions
if (!hasPermission('manage_settings')) {
    header('Location: access-denied.php');
    exit;
}

$success = $error = '';
$sale_data = null;
$refund_details = null;
$max_refundable = 0;

// Handle sale lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_sale'])) 
{
    
    $invoice_no = trim($_POST['invoice_no']);
    $errors = [];
    if (empty($invoice_no)) {
        $errors['invoice_no'] = 'Please enter an invoice number';
    }
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    s.sale_id, s.invoice_no, s.sale_price, s.sale_date, s.status as sale_status,
                    s.balance_due, s.service_fee, s.delivery_charges, s.deposit_received,
                    s.part_exchange_allowance, s.finance_amount, s.px_vehicle_id, s.finance_company_name, s.payment_method,
                    s.discount_amount,
                    v.vehicle_id, v.make, v.model, v.regnumber, v.year, v.mileage, v.status as vehicle_status,
                    pxv.vehicle_id as px_vehicle_id, pxv.make as px_make, pxv.model as px_model, 
                    pxv.regnumber as px_regnumber, pxv.year as px_year, pxv.status as px_vehicle_status,
                    c.customer_id, c.first_name, c.last_name, c.email, c.phone,
                    u.full_name as salesperson_name
                FROM sales s
                LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                LEFT JOIN vehicles pxv ON s.px_vehicle_id = pxv.vehicle_id
                LEFT JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN users u ON s.sales_person = u.id
                WHERE s.invoice_no = ?
            ");
            $stmt->execute([$invoice_no]);
            $sale_data = $stmt->fetch(PDO::FETCH_ASSOC);
            // echo("<pre>");
            // print_r($sale_data);
            // echo("</pre>");
            // die;
            // In the sale lookup section (around line 50), update the query:
            if ($sale_data) {
                // NEW: Check if sale was fully refunded
                $fully_refunded = false;
                if ($sale_data['sale_status'] === 'refunded') {
                    $fully_refunded = true;
                }

                // Also check if there was a full_return refund
                if (!$fully_refunded) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as full_refund_count 
                        FROM refunds 
                        WHERE sale_id = ? 
                        AND refund_type = 'full_return'
                        AND vehicle_returned = 1
                    ");
                    $stmt->execute([$sale_data['sale_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $fully_refunded = ($result['full_refund_count'] > 0);
                }
                $allowed_statuses = ['completed', 'partially_refunded'];

                if ($fully_refunded) {
                    $error = "This sale was fully refunded. No further refunds can be processed.";
                    $sale_data = null;
                } elseif (!in_array($sale_data['sale_status'], $allowed_statuses)) {
                    $error = "This sale is not completed. Only completed sales can be refunded.";
                    $sale_data = null;
                } else {
                    // Additional validation

                    if (!in_array($sale_data['sale_status'], $allowed_statuses)) {
                        
                        $error = "This sale is not completed. Only completed sales can be refunded.";
                        $sale_data = null;
                    } else {
                        
                        $refund_details = $refundCalculator->getRefundDetails($sale_data['sale_id']);
            //        echo("<pre>");
            // print_r($refund_details);
            // echo("</pre>");
            // die;     
                        if ($refund_details) {
                            
                            $max_refundable = $refund_details['max_refundable'];
                            
                            // NEW: Check if P/X vehicle was already returned in previous refunds
                            $px_already_returned = false;
                            if (!empty($sale_data['px_vehicle_id'])) {
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) as px_returned_count 
                                    FROM refunds 
                                    WHERE sale_id = ? 
                                    AND px_vehicle_returned = 1
                                ");
                                $stmt->execute([$sale_data['sale_id']]);
                                $px_result = $stmt->fetch(PDO::FETCH_ASSOC);
                                $px_already_returned = ($px_result['px_returned_count']);
                            }
                        }
                    }
                }
                saveFormData($sale_data);
            } else {
                setError("Invoice number not found");
            }
            // Save sale data in session for display after redirect


        } catch (PDOException $e) {
            setError("Error searching for sale: " . $e->getMessage());
        }
    } else {
        setFormErrors($errors);
        saveFormData($_POST);
        setError('Please correct the errors below');
    }

    //        header("Location: create-refund.php");
    //      exit;
}else{
    echo("yo are here");
die;
}

// Handle refund creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_refund'])) {
    try {
        //$db->beginTransaction();

        // ========== INPUT VALIDATION & SANITIZATION ==========
        $errors = [];

        // Sale ID validation
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        if ($sale_id <= 0) {
            $errors['sale_id'] = "Invalid sale ID";
        }

        // Refund type validation
        $valid_refund_types = ['full_return', 'partial_keep', 'repair_compensation'];
        $refund_type = $_POST['refund_type'] ?? '';
        if (!in_array($refund_type, $valid_refund_types)) {
            $errors['refund_type'] = "Invalid refund type selected";
        }

        // Amount validation
        $cash_refund_amount = (float)($_POST['cash_refund_amount'] ?? 0);
        
        if ($cash_refund_amount <= 0) {
            $errors['cash_refund_amount'] = "Refund amount must be greater than 0";
        }

        // Date validation
        $refund_date = $_POST['refund_date'] ?? '';
        if (strtotime($refund_date) > time()) {
            $errors['refund_date'] = "Refund date cannot be in the future";
        }

        // Checkbox validations
        $bkCheckBox = isset($_POST['business_keeps']) ? 1 : 0;
        $vehicle_returned = isset($_POST['vehicle_returned']) ? 1 : 0;
        $px_vehicle_returned = isset($_POST['px_vehicle_returned']) ? 1 : 0;




        // Business logic validation
        if ($refund_type === 'full_return' && !$vehicle_returned) {
            $errors['vehicle_returned'] = "Vehicle must be returned for full refund";
        }
        if ($refund_type === 'repair_compensation' && $vehicle_returned) {
            $errors['vehicle_returned'] = "Vehicle cannot be returned for repair compensation";
        }
        if ($refund_type === 'repair_compensation' && $px_vehicle_returned) {
            $errors['px_vehicle_returned'] = "Part-exchange vehicle cannot be returned for repair compensation";
        }

        // ========== DEDUCTION VALIDATION ==========
        $deductions = [];
        $total_deductions = 0;

        if (isset($_POST['deductions']) && is_array($_POST['deductions'])) {
            foreach ($_POST['deductions'] as $deduction) {
                if (!empty($deduction['type']) && !empty($deduction['amount'])) {
                    $deduction_amount = (float)$deduction['amount'];

                    if ($deduction_amount <= 0) {
                        // $errors["deductions_{$index}_amount"] = "Deduction amount must be greater than 0";
                        continue;
                    }

                    $deductions[] = [
                        'type' => htmlspecialchars(trim($deduction['type'])),
                        'description' => htmlspecialchars(trim($deduction['description'] ?? '')),
                        'amount' => $deduction_amount
                    ];
                    $total_deductions += $deduction_amount;
                }
            }
        }

        // ========== GET SALE DATA & VALIDATE MAX REFUNDABLE ==========
        $refund_details = $refundCalculator->getRefundDetails($sale_id);

        if (!$refund_details) {
            $errors['general'] = "Could not retrieve refund details for this sale";
        } else {
            // Extract sale data
            $already_refunded = $refund_details['already_refunded'];
            $max_refundable = $refund_details['max_refundable'];
            $total_revenue = $refund_details['sale_data']['total_revenue'];
            $net_profit = $refund_details['sale_data']['net_profit'];
            $total_cost = $refund_details['sale_data']['total_cost'];
            $service_fee = $refund_details['sale_data']['service_fee'];
            $delivery_charges = $refund_details['sale_data']['delivery_charges'];
            $discount_amount = $refund_details['sale_data']['discount_amount'];
            $deposit_received = $refund_details['sale_data']['deposit_received'];
            $px_allowance = $refund_details['sale_data']['part_exchange_allowance'] ?? 0;
            $finance_amount = $refund_details['sale_data']['finance_amount'] ?? 0;
            $total_previous_deductions = $refund_details['sale_data']['total_previous_deductions'];
            $sale_revenue = $deposit_received + $finance_amount + $px_allowance;
            $current_revenue = $total_revenue;
            // Validate refund amount

            if ($cash_refund_amount > $max_refundable) {
                $errors['cash_refund_amount'] = "Refund amount cannot exceed maximum refundable amount of £" . number_format($max_refundable, 2);
            }
        }

        // ========== STOP IF VALIDATION ERRORS ==========
        if (!empty($errors)) {
            setFormErrors($errors);
            saveFormData($_POST);
            setError('Please correct the errors below');
            header("Location: create-refund.php");
            exit;
        }


        // ========== CALCULATE NET REFUND AMOUNTS ==========


        $net_refund_amount = $cash_refund_amount;
        $max_refundable_before = $max_refundable;

        // Calculate max_refundable_after based on refund type
        switch ($refund_type) {
            case 'repair_compensation':
                $max_refundable_before = $max_refundable;
                $available_after_deductions = max(0, $max_refundable_before - $total_deductions);
                $net_refund_amount = min($cash_refund_amount, $available_after_deductions);

                $max_refundable_after = max(0, $max_refundable_before - $total_deductions);
                break;
            case 'full_return':
                // For full return, business keeps services + delivery + deductions
                // $business_keeps = $service_fee + $delivery_charges + $total_deductions;
                // $max_refundable_after = $deposit_received - $business_keeps;

                // if ($cash_refund_amount > $max_refundable_after) {
                //     throw new Exception("Cash refund cannot exceed £" . number_format($max_refundable_after, 2));
                // }
                //     $px_deduction_amount = 0;
                //     $other_deductions_amount = 0;

                //     foreach ($deductions as $deduction) {
                //         if ($deduction['type'] == 'Part-Exchange Allowance') {
                //             $px_deduction_amount = $deduction['amount'];
                //         } else {
                //             $other_deductions_amount += $deduction['amount'];
                //         }
                //     }
                //  $max_refundable_after = $max_refundable_before - ($business_keeps + $other_deductions_amount + $cash_refund_amount);
                break;
            case 'partial_keep': // partial_keep
                $max_refundable_before = $max_refundable;
                $available_after_deductions = max(0, $max_refundable_before - $total_deductions);
                $net_refund_amount = min($cash_refund_amount, $available_after_deductions);

                $max_refundable_after = max(0, $max_refundable_before - ($net_refund_amount + $total_deductions));
        }
        // echo("<br>".$net_refund_amount);
        // Validate net refund amount
        if ($net_refund_amount < 0) {
            throw new Exception("Net refund amount cannot be negative");
        }
        if ($net_refund_amount > $cash_refund_amount) {
            throw new Exception("Calculated net refund exceeds requested amount");
        }

        // ========== SANITIZE TEXT INPUTS ==========
        $refund_reason = htmlspecialchars(trim($_POST['refund_reason']));
        $customer_agreement = htmlspecialchars(trim($_POST['customer_agreement'] ?? ''));
        $reference_number = htmlspecialchars(trim($_POST['reference_number']));
        $payment_method = $_POST['payment_method'];
        $original_invoice_no = $_POST['original_invoice_no'] ?? '';
        $vehicle_id = $_POST['vehicle_id'] ?? '';

        // ========== DETERMINE VEHICLE STATUS ==========
        $vehicle_status_after = 'sold';
        if ($vehicle_returned && $refund_type === 'full_return') {
            $vehicle_status_after = 'available';
        }

        $px_allowance_in_deductions = 0;
        $other_deductions_total = 0;
        $business_keeps = 0;

        // ========== CALCULATE FINANCIAL UPDATES ==========
        switch ($refund_type) {
            case 'full_return':
                // Business keeps services + delivery + ALL deductions
                if ($bkCheckBox > 0) {
                    $business_keeps = $service_fee + $delivery_charges;
                } else {
                    $business_keeps = 0;
                }


                // Separate P/X from other deductions
                $px_deduction_amount = 0;
                $other_deductions_amount = 0;

                foreach ($deductions as $deduction) {
                    if ($deduction['type'] == 'Part-Exchange Allowance') {
                        $px_deduction_amount = $deduction['amount'];
                    } else {
                        $other_deductions_amount += $deduction['amount'];
                    }
                }

                if ($bkCheckBox > 0) {
                    $business_keeps = $service_fee + $delivery_charges;
                } else {
                    $business_keeps = 0;
                }


                // Calculate cash available for refund
                $cash_available_for_refund = $max_refundable_before - $business_keeps - $other_deductions_amount;
                $available_after_deductions = $cash_available_for_refund;
                // Validate cash refund amount
                if ($cash_refund_amount > $cash_available_for_refund) {
                    throw new Exception("For full return, cash refund cannot exceed £" .
                        number_format($cash_available_for_refund, 2));
                }
                // Mathematical voiding - show each component
                // This is for tracking/logging, final revenue will be 0

                $total_revenue = $current_revenue - ($finance_amount + $px_deduction_amount + $business_keeps + $other_deductions_amount + $cash_refund_amount);
                // Final revenue is 0 (sale voided)
                $total_voided = $finance_amount + $px_deduction_amount + $business_keeps + $other_deductions_amount + $cash_refund_amount;
                // echo ($total_revenue . " = " . $current_revenue . " - (" . $finance_amount . " + " . $px_deduction_amount . " + " . $business_keeps . " + " . $other_deductions_amount . " + " . $cash_refund_amount . ")");
                //$total_revenue = 0;
                $total_previous_deductions = $refund_details['sale_data']['total_previous_deductions'];
                $net_profit = ($business_keeps + $other_deductions_amount + $total_previous_deductions) - $discount_amount;
                $max_refundable_after = $cash_available_for_refund - $net_refund_amount;
                $total_deductions = $business_keeps + $other_deductions_amount;
                // echo ("<br>" . $net_profit . " = (" . $business_keeps . " + " . $other_deductions_amount . " + " . $total_previous_deductions . ") - " . $discount_amount);
                break;


            case 'repair_compensation':
                $total_revenue = $total_revenue - $total_deductions;
                $total_cost = $total_cost + $cash_refund_amount;
                $net_profit = ($total_revenue - $total_cost) + $total_deductions;
                break;

            case 'partial_keep':
                $total_revenue = $total_revenue - ($cash_refund_amount + $total_deductions);
                $net_profit = ($total_revenue - $total_cost) + $total_deductions;
                break;
        }

        // echo("<br>Sale Revenue: £".$sale_revenue);
        // echo("<br>Already Refunded:	-£".$already_refunded);
        // echo("<br>Current Revenue:	£".$current_revenue);
        // echo("<br>Voiding Breakdown");
        // echo("<br>Business Keeps (Services + Delivery Charges):	-£".$business_keeps);
        // echo("<br>Finance Amount:	-£".$finance_amount);
        // echo("<br>P/X Deduction:	-£".$px_deduction_amount);
        // echo("<br>Other Deductions:	-£".$other_deductions_amount);
        // echo("<br>Cash Available to Customer:	-£".$cash_available_for_refund);
        // echo("<br>Total Voided:	-£".$total_voided);
        // echo("<br>Revenue: £".$total_revenue." - SALE VOIDED");
        // echo("<br>Total sale value has been mathematically voided");
        // echo("<br>Net profit:	£".$net_profit);
        // echo("<br>Max_refundabale_before:".$max_refundable_before);
        // echo("<br>deductions:".$total_deductions);
        // echo("<br>Available_after_deduction:".$available_after_deductions);
        // echo("<br>Net_Refund_Amount:".$net_refund_amount);
        //  echo("<br>Max_refundabale_after:".$max_refundable_after);
        //   // $total_cost = $total_cost + $net_refund_amount;
        //  $net_profit = ($total_revenue-$total_cost)+$total_deductions;
        // echo("<br>Total Cost :".$total_cost);
        // echo("<br>Net Profit :".$net_profit." = (".$total_revenue." - ".$total_cost.") + ".$total_deductions );
        // echo("<br>".$total_revenue." = ".$total_revenue." - (".$finance_amount." + ".$business_keeps." + ".$cash_refund_amount);
        // //echo("<br>".$net_profit." = (".$service_fee." + ".$delivery_charges." + ".$total_deductions.") - ".$discount_amount);
        // echo("<br>============================================================");
        // echo("<br>total_revenue: ".$total_revenue);
        // die;

        // ========== INSERT REFUND RECORD ==========
        $stmt = $db->prepare("
            INSERT INTO refunds (
                sale_id, original_invoice_no, refund_type, 
                max_refundable_before, total_deductions, net_refund_amount, 
                max_refundable_after, vehicle_returned, px_vehicle_returned, 
                vehicle_status_after, refund_reason, customer_agreement, 
                status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?)
        ");

        $stmt->execute([
            $sale_id,
            $original_invoice_no,
            $refund_type,
            $max_refundable_before,
            $total_deductions,
            $net_refund_amount,
            $max_refundable_after,
            $vehicle_returned,
            $px_vehicle_returned,
            $vehicle_status_after,
            $refund_reason,
            $customer_agreement,
            $_SESSION['user_id']
        ]);

        $refund_id = $db->lastInsertId();

        // ========== INSERT DEDUCTIONS ==========
        foreach ($deductions as $deduction) {
            $stmt = $db->prepare("
                INSERT INTO refund_deductions (refund_id, deduction_type, description, amount, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $refund_id,
                $deduction['type'],
                $deduction['description'],
                $deduction['amount']
            ]);
        }

        // ========== RECORD TRANSACTIONS/EXPENSES ==========
        if ($refund_type === 'full_return') {
            // $reference_number = $original_invoice_no . '-REF-' . $refund_id;
            // 1. Finance refund transaction (to finance company)
            $stmt = $db->prepare("
                INSERT INTO sale_payments (
                    sale_id, invoice_no, transaction_type, transaction_date, 
                    amount, payment_method, reference_number, status, 
                    notes, processed_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            // Finance refund
            $stmt->execute([
                $sale_id,
                $original_invoice_no,
                'refund',
                $refund_date,
                -$finance_amount,  // Negative
                'finance',
                'FINIANCE-REF-' . $refund_id,
                'completed',
                "Finance £" . number_format($finance_amount, 2) . " refunded to " .
                    ($refund_details['sale_data']['finance_company_name'] ?? 'finance company') .
                    " - Refund #$refund_id",
                $_SESSION['user_id']
            ]);

            // 2. Cash refund to customer
            $stmt->execute([
                $sale_id,
                $original_invoice_no,
                'refund',
                $refund_date,
                -$cash_refund_amount,  // Negative
                $payment_method,
                $reference_number,
                'completed',
                "Cash refund £" . number_format($cash_refund_amount, 2) .
                    " to customer - Refund #$refund_id: $refund_reason",
                $_SESSION['user_id']
            ]);

            // 3. Business keeps transactions (optional but recommended)
            // Services & Delivery kept

            // if ($services_kept > 0) {
            //     $stmt->execute([
            //         $sale_id,
            //         $original_invoice_no,
            //         'payment',
            //         $refund_date,
            //         $business_keeps,  // Positive
            //         'business_keeps',
            //         'SERV-' . $refund_id,
            //         'completed',
            //         "Services & delivery charges retained: £" .
            //             number_format($services_kept, 2) . " - Refund #$refund_id",
            //         $_SESSION['user_id']
            //     ]);
            // }

            // Other deductions kept (excluding P/X)
            $other_deductions = $total_deductions - $px_deduction_amount;
            if ($other_deductions > 0) {
                $stmt->execute([
                    $sale_id,
                    $original_invoice_no,
                    'payment',
                    $refund_date,
                    $other_deductions,  // Positive
                    'business_keeps',
                    'DED-' . $refund_id,
                    'completed',
                    "Deductions retained: £" . number_format($other_deductions, 2) .
                        " - Refund #$refund_id",
                    $_SESSION['user_id']
                ]);
            }
        } elseif ($refund_type === 'repair_compensation') {

            // Record as expense
            $stmt = $db->prepare("
                INSERT INTO expenses (
                    vehicle_id, expense_type, description, amount, 
                    date, vendor_name, receipt_number, notes, added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $vehicle_id,
                'repair_compensation',
                $original_invoice_no . '-REF-' . $refund_id,
                $net_refund_amount,
                $refund_date,
                null,
                null,
                "Refund #$refund_id: $refund_reason",
                $_SESSION['user_id']
            ]);
            // 2. Business keeps deductions (NEW - add this)
            if ($total_deductions > 0) {
                $stmt = $db->prepare("
                    INSERT INTO sale_payments (
                        sale_id, invoice_no, transaction_date, 
                        amount, payment_method, reference_number, 
                        notes, processed_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $sale_id,
                    $original_invoice_no,
                    $refund_date,
                    $total_deductions,  // POSITIVE (business keeps)
                    'business_keeps',
                    'DED-' . $refund_id,
                    "Deductions retained: £" . number_format($total_deductions, 2) .
                        " - " . ucfirst(str_replace('_', ' ', $refund_type)) .
                        " Refund #$refund_id",
                    $_SESSION['user_id']
                ]);
            }
        } else {
            // Record refund transaction
            $refund_transaction = [
                'sale_id' => $sale_id,
                'invoice_no' => $original_invoice_no,
                'transaction_date' => $refund_date,
                'amount' => -$net_refund_amount,
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
                'notes' => "Refund #$refund_id: $refund_reason",
                'processed_by' => $_SESSION['user_id']
            ];
            $transaction_id = $refundCalculator->recordRefundTransaction($refund_transaction);
            if (!$transaction_id) {
                 echo("you are here");
                throw new Exception("Failed to record refund transaction.");
            }
           
            // 2. Business keeps deductions (NEW - add this)
            if ($total_deductions > 0) {
                $stmt = $db->prepare("
                    INSERT INTO sale_payments (
                        sale_id, invoice_no, transaction_date, 
                        amount, payment_method, reference_number, 
                        notes, processed_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $sale_id,
                    $original_invoice_no,
                    $refund_date,
                    $total_deductions,  // POSITIVE (business keeps)
                    'business_keeps',
                    'DED-' . $refund_id,
                    "Deductions retained: £" . number_format($total_deductions, 2) .
                        " - " . ucfirst(str_replace('_', ' ', $refund_type)) .
                        " Refund #$refund_id",
                    $_SESSION['user_id']
                ]);
            }
        }



        // ========== UPDATE SALES STATUS ==========
        $sale_status = $refund_type === 'full_return' ? 'refunded' : 'partially_refunded';
        $stmt = $db->prepare("UPDATE sales SET status = ?, updated_at = NOW() WHERE sale_id = ?");
        $stmt->execute([$sale_status, $sale_id]);

        // ========== UPDATE VEHICLE STATUS (if returned) ==========
        if ($vehicle_returned && $refund_type === 'full_return' && !empty($vehicle_id)) {
            $stmt = $db->prepare("UPDATE vehicles SET status = ?, updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_status_after, $vehicle_id]);

            $refund_note = " - Returned via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$refund_note, $vehicle_id]);
        }

        // ========== HANDLE PART-EXCHANGE VEHICLE RETURN ==========
        if ($px_vehicle_returned && !empty($refund_details['sale_data']['px_vehicle_id'])) {
            $stmt = $db->prepare("UPDATE vehicles SET status = 'refunded_to_customer', updated_at = NOW() WHERE vehicle_id = ?");
            $stmt->execute([$refund_details['sale_data']['px_vehicle_id']]);

            $px_refund_note = " - Returned to customer via refund #$refund_id on " . date('Y-m-d');
            $stmt = $db->prepare("UPDATE vehicles SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE vehicle_id = ?");
            $stmt->execute([$px_refund_note, $refund_details['sale_data']['px_vehicle_id']]);
        }

        // ========== HANDLE FINANCE COMPANY NOTIFICATION ==========
        if ($refund_details['sale_data']['payment_method'] == 'finance' && $refund_type === 'full_return') {
            $finance_note = " [FINANCE: Contact " . $refund_details['sale_data']['finance_company_name'] .
                " to process £" . number_format($refund_details['sale_data']['finance_amount'], 2) . " refund]";
            $stmt = $db->prepare("UPDATE refunds SET customer_agreement = CONCAT(COALESCE(customer_agreement, ''), ?) WHERE refund_id = ?");
            $stmt->execute([$finance_note, $refund_id]);
        }

        // ========== ADD REFUND NOTE TO ORIGINAL SALE ==========
        $sale_note = " | Refund #$refund_id issued: £" . number_format($net_refund_amount, 2) .
            " via " . strtoupper(str_replace('_', ' ', $refund_type)) . " on " . date('Y-m-d');
        $stmt = $db->prepare("UPDATE sales SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE sale_id = ?");
        $stmt->execute([$sale_note, $sale_id]);

        // ========== UPDATE SALES FINANCIAL DATA ==========
        $stmt = $db->prepare("UPDATE sales SET total_revenue = ?, net_profit = ?, total_cost = ? WHERE sale_id = ?");
        $stmt->execute([$total_revenue, $net_profit, $total_cost, $sale_id]);

        //$db->commit();

        clearFormData();
        clearFormErrors();
        unset($_SESSION['refund_sale_data'], $_SESSION['refund_details'], $_SESSION['max_refundable']);

        // Set success message and redirect
        setSuccess("Refund created successfully! Refund ID: RF-" . str_pad($refund_id, 5, '0', STR_PAD_LEFT));

        header("Location: refund-receipt.php?id=$refund_id");
        exit;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
          //  $db->rollBack();
        }
        saveFormData($_POST);
        setError('Error creating refund: ' . $e->getMessage());
        header("Location: create-refund.php");
    }
}

?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title><?php echo ($pageTitle); ?></title>

    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    <script src="vendors/simplebar/simplebar.min.js"></script>
    <script src="assets/js/config.js"></script>

    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
    <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/line.css">
    <!-- <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl"> -->
    <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
    <!-- <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl"> -->
    <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
    <style>
        .validation-error {
            margin-bottom: 1rem;
        }

        .deduction-row:has(.is-invalid) {
            border: 1px solid #dc3545;
            border-radius: 0.375rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
    </style>
    <script>
        var phoenixIsRTL = window.config.config.phoenixIsRTL;
        if (phoenixIsRTL) {
            var linkDefault = document.getElementById('style-default');
            var userLinkDefault = document.getElementById('user-style-default');
            linkDefault.setAttribute('disabled', true);
            userLinkDefault.setAttribute('disabled', true);
            document.querySelector('html').setAttribute('dir', 'rtl');
        } else {
            var linkRTL = document.getElementById('style-rtl');
            var userLinkRTL = document.getElementById('user-style-rtl');
            linkRTL.setAttribute('disabled', true);
            userLinkRTL.setAttribute('disabled', true);
        }
    </script>
    <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
</head>

<body>
    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
        <!-- Left Menu -->
        <?php include("left-menu.php"); ?>
        <!-- Page Content -->
        <div class="content">
            <?php displayMessage(); ?>
            <div class="pb-5">
                <div class="row g-4">
                    <div class="col-12 col-xxl-12">
                        <!-- Page Header -->
                        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <h1 class="h2">Create New Refund</h1>
                            <div class="btn-toolbar mb-2 mb-md-0">
                                <a href="refund-management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Refunds
                                </a>
                            </div>
                        </div>


                        <?php if ($error && strpos($error, 'fully refunded') !== false): ?>
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0 text-white"><i class="fas fa-ban"></i> Sale Fully Refunded</h5>
                                </div>
                                <div class="card-body text-center py-4">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                    <h4 class="text-danger mb-3">FULL REFUND ALREADY PROCESSED</h4>
                                    <div class="alert alert-success d-inline-block mb-3">
                                        <h5 class="mb-0 text-white">✓ REFUND COMPLETED - NO FURTHER ACTION NEEDED</h5>
                                    </div>
                                    <p class="text-muted">
                                        Invoice: <strong><?= htmlspecialchars($_POST['invoice_no'] ?? '') ?></strong> was fully refunded.
                                    </p>
                                    <div class="mt-3">
                                        <a href="refund-management.php" class="btn btn-primary">
                                            <i class="fas fa-arrow-left"></i> Back to Refunds
                                        </a>
                                        <a href="create-refund.php" class="btn btn-outline-primary">
                                            <i class="fas fa-plus"></i> Create New Refund
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Step 1: Sale Lookup -->
                        <?php if (!$sale_data): ?>
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0 text-white">Step 1: Find Original Sale</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" >
                                        <input type="hidden" name="lookup_sale" value="1">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label class="form-label">Enter Invoice Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control <?php echo hasError('invoice_no') ? 'is-invalid' : ''; ?>" name="invoice_no"
                                                    placeholder="e.g., INV-2025-001"
                                                    value="<?= getFormData('invoice_no'); ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-search"></i> Find Sale
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">
                                            Enter the original invoice number from the sale. This ensures we find the exact transaction.
                                        </small>
                                    </form>

                                    <!-- Help section for finding invoice numbers -->
                                    <div class="mt-4 p-3 bg-light rounded">
                                        <h6><i class="fas fa-info-circle text-info"></i> Need help finding invoice numbers?</h6>
                                        <ul class="mb-0">
                                            <li>Check the original sales invoice document</li>
                                            <li>Look in <a href="view-all-invoices.php">View All Invoices</a></li>
                                            <li>Check sales records in the system</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>

                            <!-- Sales Type Alert -->
                            <?php if ($refund_details):
                                $sales_type = $refund_details['sales_type'];

                                switch ($sales_type) {
                                    case 'normal':
                                        $alert_type = 'alert-info';
                                        $icon = 'fas fa-cash';
                                        $title = 'Normal Sale';
                                        $message = 'Full refund available up to customer payments.';
                                        break;

                                    case 'partexchange_only':
                                        $alert_type = 'alert-warning';
                                        $icon = 'fas fa-exchange-alt';
                                        $title = 'Sale with Part-Exchange';
                                        $message = 'Refund includes cash amount. Part-exchange vehicle can be returned to customer.';
                                        break;

                                    case 'finance_only':
                                        $alert_type = 'alert-warning';
                                        $icon = 'fas fa-university';
                                        $title = 'Sale with Finance';
                                        $message = 'Only customer deposit is refundable. Finance amount must be handled separately with the finance company.';
                                        break;

                                    case 'finance_partexchange':
                                        $alert_type = 'alert-danger';
                                        $icon = 'fas fa-exclamation-triangle';
                                        $title = 'Complex Sale - Finance + Part-Exchange';
                                        $message = 'Multiple components: refund deposit only, finance handled separately, part-exchange vehicle can be returned.';
                                        break;
                                }
                            ?>
                                <!-- <div class="alert <?= $alert_type ?> alert-dismissible fade show">
                                    <i class="<?= $icon ?> me-2"></i>
                                    <strong><?= $title ?>:</strong> <?= $message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div> -->
                            <?php endif; ?>

                            <!-- Step 2: Refund Creation Form -->
                            <form method="POST" id="refundForm">
                                <input type="hidden" name="create_refund" value="1">
                                <input type="hidden" name="sale_id" value="<?= $sale_data['sale_id'] ?>">
                                <input type="hidden" name="original_invoice_no" value="<?= $sale_data['invoice_no'] ?>">
                                <input type="hidden" name="vehicle_id" value="<?= $sale_data['vehicle_id'] ?>">

                                <!-- Sale Information -->
                                <div class="card sale-info-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Sale Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Invoice:</strong> <?= htmlspecialchars($sale_data['invoice_no']) ?><br>
                                                <strong>Sale Date:</strong> <?= date('F j, Y', strtotime($sale_data['sale_date'])) ?><br>
                                                <strong>Salesperson:</strong> <?= htmlspecialchars($sale_data['salesperson_name']) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Vehicle:</strong> <?= htmlspecialchars($sale_data['make'] . ' ' . $sale_data['model']) ?><br>
                                                <strong>Registration:</strong> <?= htmlspecialchars($sale_data['regnumber']) ?><br>
                                                <strong>Year:</strong> <?= htmlspecialchars($sale_data['year']) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Customer:</strong> <?= htmlspecialchars($sale_data['first_name'] . ' ' . $sale_data['last_name']) ?><br>
                                                <strong>Phone:</strong> <?= htmlspecialchars($sale_data['phone']) ?><br>
                                                <strong>Sale Price:</strong> £<?= number_format($sale_data['sale_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-8">
                                        <!-- Refund Details -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0">Refund Details</h5>
                                            </div>
                                            <div class="card-body">

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Refund Type <span class="text-danger">*</span></label>
                                                        <select class="form-select" name="refund_type" id="refundType" required>
                                                            <option value="">Select Refund Type</option>
                                                            <option value="full_return">Full Return (Vehicle Returned)</option>
                                                            <!-- <option value="partial_return">Partial Return (Vehicle Returned with Deductions)</option> -->
                                                            <option value="partial_keep">Partial Refund (Customer Keeps Vehicle)</option>
                                                            <option value="repair_compensation">Repair Compensation</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Refund Amount <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">£</span>

                                                            <!-- With this line (add oninput event) -->
                                                            <input type="number" step="0.01" class="form-control"
                                                                name="cash_refund_amount" id="cashRefundAmount"
                                                                min="0.01" max="<?= $max_refundable ?>"
                                                                value="<?= $max_refundable ?>"
                                                                oninput="calculateTotals()" required>
                                                        </div>
                                                        <!-- <h5 class="mt-2 text-danger">Maximum: £<?= number_format($max_refundable, 2) ?></h5> -->
                                                    </div>
                                                </div>

                                                <!-- Business Keeps Section - Hidden by default -->
                                                <div id="BusinessKeepsSection" style="display: none;">
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="card border-warning">
                                                                <div class="card-body">
                                                                    <div class="row">
                                                                        <div class="col-md-8">
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" name="business_keeps"
                                                                                    id="BusinessKeeps" value="1" checked>
                                                                                <label class="form-check-label" for="BusinessKeeps">
                                                                                    <strong>Deduct Business Costs</strong><br>
                                                                                    <small class="text-muted">
                                                                                        Service Fee: £<?= number_format($refund_details['sale_data']['service_fee'] ?? 0, 2) ?> +
                                                                                        Delivery Charges: £<?= number_format($refund_details['sale_data']['delivery_charges'] ?? 0, 2) ?>
                                                                                    </small>
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                        <!-- <div class="col-md-4 text-end">
                            <span class="badge bg-warning fs-8">
                                £<?= number_format(($refund_details['sale_data']['service_fee'] ?? 0) + ($refund_details['sale_data']['delivery_charges'] ?? 0), 2) ?>
                            </span>
                        </div> -->
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Main Vehicle Return -->
                                                <div id="vehicleReturnSection" style="display: none;">
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="card border-success">
                                                                <div class="card-body">
                                                                    <div class="row">
                                                                        <div class="col-md-8">
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" name="vehicle_returned" id="vehicleReturned" value="1">
                                                                                <label class="form-check-label" for="vehicleReturned">
                                                                                    Main Vehicle is being returned to inventory
                                                                                </label>
                                                                            </div>
                                                                            <small class="form-text text-muted">
                                                                                This will make the vehicle available for sale again.
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Part Exchange Vehicle Return Section -->

                                                <!-- Part Exchange Vehicle Return Section -->
                                                <?php if (!empty($sale_data['px_vehicle_id'])): ?>
                                                    <div id="pxVehicleSection" style="display: none;">
                                                        <div class="row mb-3">
                                                            <div class="col-12">
                                                                <div class="card <?= $px_already_returned ? 'border-danger' : 'border-warning' ?>">
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-md-8">
                                                                                <strong><i class="fas fa-exchange-alt"></i> Part Exchanged Vehicle:</strong>
                                                                                <?= htmlspecialchars($sale_data['px_make'] . ' ' . $sale_data['px_model']) ?>
                                                                                (<?= htmlspecialchars($sale_data['px_year']) ?>)
                                                                                <?= htmlspecialchars($sale_data['px_regnumber']) ?>

                                                                                <?php if ($px_already_returned): ?>
                                                                                    <span class="badge bg-danger ms-2">
                                                                                        <i class="fas fa-check-circle"></i> Already Returned to Customer
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <div class="col-md-12 mt-2">
                                                                                <?php if ($px_already_returned): ?>
                                                                                    <!-- Already returned - show message -->
                                                                                    <div class="alert alert-danger">
                                                                                        <i class="fas fa-exclamation-triangle"></i>
                                                                                        This part-exchange vehicle was already returned to the customer in a previous refund.
                                                                                    </div>
                                                                                <?php else: ?>
                                                                                    <!-- Not returned yet - show checkbox -->
                                                                                    <div class="form-check">
                                                                                        <input class="form-check-input" type="checkbox"
                                                                                            name="px_vehicle_returned"
                                                                                            id="pxVehicleReturned"
                                                                                            value="1"
                                                                                            onchange="calculateTotals()">
                                                                                        <label class="form-check-label" for="pxVehicleReturned">
                                                                                            <strong>Return P/X Vehicle to Customer</strong>
                                                                                        </label>
                                                                                    </div>
                                                                                    <small class="text-muted">
                                                                                        This will return the part-exchange vehicle to the customer (status: refunded_to_customer).
                                                                                    </small>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Finance Company Notice -->
                                                <?php if ($sale_data['payment_method'] == 'finance'): ?>
                                                    <div id="financeSection" style="display: none;">
                                                        <div class="row mb-3">

                                                            <div class="col-12">
                                                                <div class="card border-info">
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-md-8">
                                                                                <div class="form-check">
                                                                                    <h6 class="text-info"><i class="fas fa-university"></i> Finance Information</h6>
                                                                                    <p class="mb-1"><strong>Finance Company:</strong> <?= htmlspecialchars($sale_data['finance_company_name'] ?? 'N/A') ?></p>

                                                                                </div>
                                                                            </div>
                                                                            <!-- <div class="col-md-4 text-end">
                                                                                <span class="badge bg-warning fs-8">
                                                                                    £<?= number_format($sale_data['finance_amount'], 2) ?>
                                                                                </span>
                                                                            </div> -->
                                                                        </div>

                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label class="form-label">Refund Reason <span class="text-danger">*</span></label>
                                                    <textarea class="form-control" name="refund_reason" rows="3"
                                                        placeholder="Detailed reason for the refund..." required></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Customer Agreement / Notes</label>
                                                    <textarea class="form-control" name="customer_agreement" rows="2"
                                                        placeholder="Any agreement details or special notes..."></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deductions Section -->
                                        <div class="card mb-4">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Deductions</h5>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addDeduction">
                                                    <i class="fas fa-plus"></i> Add Deduction
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div id="deductionsContainer">
                                                    <!-- Deductions will be added here dynamically -->
                                                </div>
                                                <div class="text-end mt-3">
                                                    <strong>Total Deductions: £<span id="totalDeductions">0.00</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <!-- Calculation Summary -->
                                        <!-- Sale Summary Card -->
                                        <div class="card sale-info-card mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0 text-white">Sale Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Original Sale Breakdown -->
                                                <h6 class="text-primary mb-3">Original Sale Details</h6>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Vehicle Sale Price:</span>
                                                    <strong>£<?= number_format($sale_data['sale_price'], 2) ?></strong>
                                                </div>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Service Fee:</span>
                                                    <strong>£<?= number_format($sale_data['service_fee'], 2) ?></strong>
                                                </div>
                                                <div class="mb-2 d-flex justify-content-between">
                                                    <span>Delivery Charges:</span>
                                                    <strong>£<?= number_format($sale_data['delivery_charges'], 2) ?></strong>
                                                </div>
                                                <?php if (($sale_data['discount_amount'] ?? 0) > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-success">
                                                        <span>Discount Given:</span>
                                                        <strong>-£<?= number_format($sale_data['discount_amount'] ?? 0, 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-3 d-flex justify-content-between border-bottom pb-2">
                                                    <span><strong>Subtotal:</strong></span>
                                                    <strong>£<?= number_format($sale_data['sale_price'] + $sale_data['service_fee'] + $sale_data['delivery_charges'] - $sale_data['discount_amount'], 2) ?></strong>
                                                </div>

                                                <!-- Discounts & Payments -->
                                                <h6 class="text-primary mb-3 mt-3">Payments & Allowances</h6>

                                                <?php if ($sale_data['finance_amount'] > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-info">
                                                        <span>Finance Amount:</span>
                                                        <strong>£<?= number_format($sale_data['finance_amount'], 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($sale_data['part_exchange_allowance'] > 0): ?>
                                                    <div class="mb-2 d-flex justify-content-between text-warning">
                                                        <span>Part-Exchange Allowance:</span>
                                                        <strong>£<?= number_format($sale_data['part_exchange_allowance'], 2) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-2 d-flex justify-content-between text-success">
                                                    <span>Customer Deposit/Paid:</span>
                                                    <strong>£<?= number_format($sale_data['deposit_received'], 2) ?></strong>
                                                </div>

                                                <div class="mb-0 d-flex justify-content-between border-top pt-2">
                                                    <span><strong>Total Amount Paid:</strong></span>
                                                    <strong>£<?= number_format(
                                                                    $sale_data['deposit_received'] +
                                                                        $sale_data['finance_amount'] +
                                                                        $sale_data['part_exchange_allowance'],
                                                                    2
                                                                ) ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Refund Eligibility Card -->
                                        <div id="calculation-card" class="card calculation-card mb-4">
                                            <div class="card-header bg-success text-white">
                                                <h5 class="mb-0 text-white">Refund Calculation</h5>
                                            </div>
                                            <div class="card-body">
                                                <!-- Available Balance -->
                                                <!-- <div class="mb-3 p-3 bg-light rounded">
                                                    <div class="text-center">
                                                        <small class="text-muted">Available for Refund</small>
                                                        <h3 class="text-success mb-0">£<?= number_format($refund_details['max_refundable'], 2) ?></h3>
                                                        <small class="text-muted">
                                                            Customer Deposit: £<?= number_format($sale_data['deposit_received'], 2) ?>
                                                            <?php if ($refund_details['already_refunded'] > 0): ?>
                                                                <br>Already Refunded: £<?= number_format($refund_details['already_refunded'], 2) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="mb-2 d-flex justify-content-between">
                                                        <span>Max Refundable Amount:</span>
                                                        <strong>£<span id="maxRefundAmount"><?= number_format(min($max_refundable, $sale_data['deposit_received']), 2) ?></span></strong>
                                                    </div> -->

                                                <!-- Calculation Lines Container -->
                                                <!-- <div id="calculationLines">
                                                    <div class="mb-2 d-flex justify-content-between">
                                                        <span>Refund Amount:</span>
                                                        <strong>£<span id="summaryRefundAmount"><?= number_format(min($max_refundable, $sale_data['deposit_received']), 2) ?></span></strong>
                                                    </div>


                                                    <div class="mb-2 d-flex justify-content-between text-danger">
                                                        <span>Deductions:</span>
                                                        <strong>-£<span id="summaryDeductions">0.00</span></strong>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between fs-5 fw-bold">
                                                    <span>Net Refund to Customer:</span>
                                                    <strong class="text-success">£<span id="summaryNet"><?= number_format(min($max_refundable, $sale_data['deposit_received']), 2) ?></span></strong>
                                                </div> -->

                                                <!-- In the calculation summary section -->
                                                <div id="calculationLines">
                                                    <!-- Maximum Refundable Pool -->
                                                    <div class="mb-2 d-flex justify-content-between text-primary">
                                                        <span><i class="fas fa-database"></i> Maximum Refundable (Pool):</span>
                                                        <strong>£<span id="summaryMaxRefundable"><?= number_format($max_refundable, 2) ?></span></strong>
                                                    </div>

                                                    <!-- Cash Refund Requested -->
                                                    <div class="mb-2 d-flex justify-content-between">
                                                        <span><i class="fas fa-money-bill-wave"></i> Cash Refund Requested:</span>
                                                        <strong>£<span id="summaryCashRefund"><?= number_format($max_refundable, 2) ?></span></strong>
                                                    </div>

                                                    <!-- Total Deductions -->
                                                    <div class="mb-2 d-flex justify-content-between text-danger">
                                                        <span><i class="fas fa-minus-circle"></i> Total Deductions:</span>
                                                        <strong>-£<span id="summaryDeductions">0.00</span></strong>
                                                    </div>

                                                    <hr class="my-2">

                                                    <!-- Available After Deductions -->
                                                    <div class="mb-2 d-flex justify-content-between text-info">
                                                        <span><i class="fas fa-check-circle"></i> Available After Deductions:</span>
                                                        <strong>£<span id="summaryAvailableAfterDeductions"><?= number_format($max_refundable, 2) ?></span></strong>
                                                    </div>

                                                    <!-- Max Refundable After This Refund -->
                                                    <div class="mb-2 d-flex justify-content-between text-secondary">
                                                        <span><i class="fas fa-forward"></i> Remaining for Future:</span>
                                                        <strong>£<span id="summaryMaxRefundableAfter">0.00</span></strong>
                                                    </div>
                                                </div>
                                                <hr>
                                                <!-- What Customer Actually Receives -->
                                                <div class="d-flex justify-content-between fs-5 fw-bold">
                                                    <span><i class="fas fa-hand-holding-usd"></i> Customer Receives:</span>
                                                    <strong class="text-success">£<span id="summaryNet"><?= number_format($max_refundable, 2) ?></span></strong>
                                                </div>

                                                <!-- Warning message container -->
                                                <div id="warningMessage" class="mt-2"></div>




                                                <!-- Simple Notes -->
                                                <?php if ($sale_data['finance_amount'] > 0): ?>
                                                    <div class="alert alert-info mt-3 p-2">
                                                        <small>
                                                            <i class="fas fa-info-circle"></i>
                                                            Finance amount (£<?= number_format($sale_data['finance_amount'], 2) ?>) handled separately
                                                        </small>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($sale_data['part_exchange_allowance'] > 0): ?>
                                                    <div class="alert alert-warning mt-2 p-2">
                                                        <small>
                                                            <i class="fas fa-exchange-alt"></i>
                                                            Part-exchange vehicle can be returned to customer
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Payment Details -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0">Payment Details</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                                    <select class="form-select" name="payment_method" required>
                                                        <option value="">Select Method</option>
                                                        <option value="cash">Cash</option>
                                                        <option value="bank_transfer">Bank Transfer</option>
                                                        <option value="card">Card Refund</option>
                                                        <option value="cheque">Cheque</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Refund Date <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" name="refund_date"
                                                        value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Reference Number <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="reference_number"
                                                        placeholder="e.g., REF-001, Check #1234" required>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="fas fa-check-circle"></i> Create Refund
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include_once("footer.php"); ?>
        </div>


        <!-- Chat Now -->

    </main>


    <!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->
    <script>
        // Add this near the top of your JavaScript section
        const PX_ALLOWANCE = <?= $sale_data['part_exchange_allowance'] ?? 0 ?>;
        const MAX_REFUNDABLE = <?= $max_refundable ?>;
        const SERVICE_FEE = <?= $sale_data['service_fee'] ?? 0 ?>;
        const DELIVERY_CHARGES = <?= $sale_data['delivery_charges'] ?? 0 ?>;
        const DEPOSIT_RECEIVED = <?= $sale_data['deposit_received'] ?? 0 ?>;
        const DISCOUNT_AMOUNT = <?= $sale_data['discount_amount'] ?? 0 ?>;
        const FINANCE_AMOUNT = <?= $sale_data['finance_amount'] ?? 0 ?>;
        const TOTAL_REVENUE = <?= $sale_data['total_revenue'] ?? 0 ?>;
        const BUSINESS_KEEPS = SERVICE_FEE + DELIVERY_CHARGES;
        let pxDeductionId = null; // To track the P/X deduction row ID
        let deductionCount = 0;
        let businessKeepsAmount = 0;


        function showWarningMessage(message) {
            const warningContainer = document.getElementById('warningMessage');
            if (!warningContainer) return;

            warningContainer.innerHTML = `
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
        }

        function clearWarningMessage() {
            const warningContainer = document.getElementById('warningMessage');
            if (warningContainer) {
                warningContainer.innerHTML = '';
            }
        }

        function handlePxDeduction(pxVehicleReturned) {
            const pxDeductionExists = document.querySelector('[data-px-deduction]');

            console.log('handlePxDeduction called:', pxVehicleReturned, 'PX_ALLOWANCE:', PX_ALLOWANCE);

            if (pxVehicleReturned && PX_ALLOWANCE > 0) {
                // If P/X is checked and allowance > 0, add the deduction if it doesn't exist
                if (!pxDeductionExists) {
                    console.log('Adding P/X deduction');
                    addPxDeduction();
                }
            } else {
                // If P/X is NOT checked, remove the deduction if it exists
                if (pxDeductionExists) {
                    console.log('Removing P/X deduction');
                    removePxDeduction();
                }
            }
        }

        // Calculate totals - UPDATED VERSION
        function calculateTotals() {
            console.log('calculateTotals called');

            // Get current values
            const cashRefundAmount = parseFloat(document.getElementById('cashRefundAmount').value) || 0;
            const pxVehicleReturned = document.getElementById('pxVehicleReturned')?.checked || false;
            const refundType = document.getElementById('refundType').value;

            console.log('cashRefundAmount:', cashRefundAmount, 'pxVehicleReturned:', pxVehicleReturned);

            // Handle P/X deduction first
            handlePxDeduction(pxVehicleReturned);

            // Calculate total deductions from all deduction inputs
            let totalDeductions = 0;
            document.querySelectorAll('.deduction-amount').forEach(input => {
                const deductionValue = parseFloat(input.value) || 0;
                totalDeductions += deductionValue;
            });

            console.log('totalDeductions after sum:', totalDeductions);

            // 1. Start with the maximum refundable amount (customer's available pool)
            const maxRefundableBefore = MAX_REFUNDABLE;

            // 2. Special handling for FULL RETURN
            let availableAfterDeductions;
            let netRefundToCustomer;
            let maxRefundableAfter;
            let isRequestValid = true;
            let businessKeeps = 0;

            if (refundType === "full_return") {

                // For FULL RETURN: Business keeps services + delivery 
                const businessKeepsCheckbox = document.getElementById('BusinessKeeps');
                if (businessKeepsCheckbox && businessKeepsCheckbox.checked) {
                    businessKeeps = (SERVICE_FEE + DELIVERY_CHARGES);
                } else {
                    businessKeeps = 0; // Waived
                }
                const maxCashRefundPossible = maxRefundableBefore - businessKeeps;

                console.log('FULL RETURN - businessKeeps:', businessKeeps, 'maxCashRefundPossible:', maxCashRefundPossible);

                if (cashRefundAmount > maxCashRefundPossible) {
                    // Request exceeds available after business keeps
                    netRefundToCustomer = Math.max(0, maxCashRefundPossible);
                    maxRefundableAfter = 0;
                    isRequestValid = false;

                    showWarningMessage(
                        `For full return, business keeps £${businessKeeps.toFixed(2)} (services + delivery + deductions). ` +
                        `Maximum cash refund possible: £${maxCashRefundPossible.toFixed(2)}`
                    );
                } else {
                    // Valid request
                    netRefundToCustomer = cashRefundAmount;
                    maxRefundableAfter = maxCashRefundPossible - cashRefundAmount;
                    clearWarningMessage();
                }
                availableAfterDeductions = maxCashRefundPossible;

            } else if (refundType === "repair_compensation") {

                // For REPAIR COMPENSATION
                availableAfterDeductions = Math.max(0, maxRefundableBefore - totalDeductions);

                if (cashRefundAmount <= availableAfterDeductions) {
                    netRefundToCustomer = cashRefundAmount;
                    maxRefundableAfter = maxRefundableBefore - totalDeductions;
                } else {
                    netRefundToCustomer = Math.max(0, availableAfterDeductions);
                    maxRefundableAfter = 0;
                    isRequestValid = false;
                }

            } else {

                // For PARTIAL KEEP and others
                availableAfterDeductions = Math.max(0, maxRefundableBefore - totalDeductions);

                if (cashRefundAmount + totalDeductions <= maxRefundableBefore) {
                    netRefundToCustomer = cashRefundAmount;
                    maxRefundableAfter = maxRefundableBefore - (cashRefundAmount + totalDeductions);
                } else {
                    netRefundToCustomer = Math.max(0, availableAfterDeductions);
                    maxRefundableAfter = 0;
                    isRequestValid = false;
                }
            }



            // Update display
            updateDisplayValues(
                maxRefundableBefore,
                cashRefundAmount,
                totalDeductions,
                maxRefundableAfter,
                availableAfterDeductions,
                netRefundToCustomer,
                isRequestValid,
                refundType,
                businessKeeps
            );
        }

        function updateDisplayValues(
            maxRefundableBefore,
            cashRefundAmount,
            totalDeductions,
            maxRefundableAfter,
            availableAfterDeductions,
            netRefundToCustomer,
            isRequestValid,
            refundType,
            businessKeeps
        ) {
            // Get PHP values (add these to your JavaScript variables)
            const CASH_DEPOSIT = <?= $refund_details['sale_data']['deposit_received'] ?? 0 ?>;
            const FINANCE_AMOUNT = <?= $refund_details['sale_data']['finance_amount'] ?? 0 ?>;
            const PX_ALLOWANCE = <?= $refund_details['sale_data']['part_exchange_allowance'] ?? 0 ?>;
            const SERVICE_FEE = <?= $refund_details['sale_data']['service_fee'] ?? 0 ?>;
            const DELIVERY_CHARGES = <?= $refund_details['sale_data']['delivery_charges'] ?? 0 ?>;
            const DISCOUNT_AMOUNT = <?= $refund_details['sale_data']['discount_amount'] ?? 0 ?>;
            const CURRENT_REVENUE = <?= $refund_details['sale_data']['total_revenue'] ?? 0 ?>;
            const ALREADY_REFUNDED = <?= $refund_details['already_refunded'] ?? 0 ?>;
            const ORIGINAL_REVENUE = CASH_DEPOSIT + FINANCE_AMOUNT + PX_ALLOWANCE;
            const BUSINESS_KEEPS = SERVICE_FEE + DELIVERY_CHARGES;

            // Update all display elements
            document.getElementById('totalDeductions').textContent = totalDeductions.toFixed(2);
            document.getElementById('summaryMaxRefundable').textContent = maxRefundableBefore.toFixed(2);
            document.getElementById('summaryCashRefund').textContent = cashRefundAmount.toFixed(2);
            document.getElementById('summaryDeductions').textContent = totalDeductions.toFixed(2);
            document.getElementById('summaryAvailableAfterDeductions').textContent = availableAfterDeductions.toFixed(2);
            document.getElementById('summaryMaxRefundableAfter').textContent = maxRefundableAfter.toFixed(2);
            document.getElementById('summaryNet').textContent = netRefundToCustomer.toFixed(2);

            // FULL RETURN SPECIAL DISPLAY
            if (refundType === "full_return") {
                // Calculate other deductions (excluding P/X if it's separate)
                let pxDeduction = 0;
                let otherDeductions = 0;

                const calculationCard = document.querySelector('.calculation-card');
                if (calculationCard) {
                    calculationCard.style.display = 'none';
                }

                // Check if there's a P/X deduction row
                const pxDeductionElement = document.querySelector('[data-px-deduction]');
                if (pxDeductionElement) {
                    const pxInput = pxDeductionElement.querySelector('.deduction-amount');
                    if (pxInput) {
                        pxDeduction = parseFloat(pxInput.value) || 0;
                        otherDeductions = totalDeductions - pxDeduction;
                    }
                } else {
                    otherDeductions = totalDeductions;
                }

                // For FULL RETURN: Business keeps depends on checkbox
                const businessKeepsCheckbox = document.getElementById('BusinessKeeps');
                if (businessKeepsCheckbox && businessKeepsCheckbox.checked) {
                    businessKeeps = BUSINESS_KEEPS;
                } else {
                    businessKeeps = 0; // Waived - SIMPLE!
                }

                // Calculate cash available for refund
                const cashAvailableForRefund = maxRefundableBefore - businessKeeps - otherDeductions;

                // Create or update full return breakdown container
                let breakdownContainer = document.getElementById('fullReturnBreakdown');
                if (!breakdownContainer) {
                    breakdownContainer = document.createElement('div');
                    breakdownContainer.id = 'fullReturnBreakdown';
                    breakdownContainer.className = 'card mt-4 border-primary';

                    // Insert after calculation summary card
                    const calcCard = document.querySelector('.calculation-card');
                    if (calcCard) {
                        calcCard.parentNode.insertBefore(breakdownContainer, calcCard.nextSibling);
                    }
                }

                // Build the breakdown display
                breakdownContainer.innerHTML = `
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 text-white"><i class="fas fa-exchange-alt"></i> Full Return - Sale Void Calculation</h5>
            </div>
            <div class="card-body">
                <!-- Revenue Summary -->
                <table class="table table-sm">
                    <tr>
                        <td>Sale Revenue:</td>
                        <td class="text-end text-primary">£${ORIGINAL_REVENUE.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td>Already Refunded:</td>
                        <td class="text-end text-danger">-£${ALREADY_REFUNDED.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td>Current Revenue:</td>
                        <td class="text-end text-success">£${CURRENT_REVENUE.toFixed(2)}</td>
                    </tr>
                </table>

                <!-- Voiding Breakdown -->
                <h6 class="border-bottom pb-2 mb-3">Voiding Breakdown</h6>
                <table class="table table-sm">
                    ${businessKeeps > 0 ? `
                    <tr>
                        <td>Business Keeps:</td>
                        <td class="text-end text-warning">-£${businessKeeps.toFixed(2)}</td>
                    </tr>` : ''}
                      ${FINANCE_AMOUNT > 0 ? `
                    <tr>
                        <td>Finance Amount:</td>
                        <td class="text-end">-£${FINANCE_AMOUNT.toFixed(2)}</td>
                    </tr>` : ''}
                    ${pxDeduction > 0 ? `
                    <tr>
                        <td>P/X Deduction:</td>
                        <td class="text-end">-£${pxDeduction.toFixed(2)}</td>
                    </tr>` : ''}
                    ${otherDeductions > 0 ? `
                    <tr>
                        <td>Other Deductions:</td>
                        <td class="text-end">-£${otherDeductions.toFixed(2)}</td>
                    </tr>` : ''}
                    <tr class="table-primary">
                        <td><strong>Cash Available to Customer:<strong></td>
                        <td class="text-end"><strong>-£${cashAvailableForRefund.toFixed(2)}<strong></td>
                    </tr>
                    
                </table>
                
                <!-- Refund to Customer -->
                <!--
                <div class="alert alert-success mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-2">Refund to Customer:</h6>
                            <p class="mb-1">Cash Refund: <strong>£${Math.min(cashRefundAmount, cashAvailableForRefund).toFixed(2)}</strong></p>
                            ${pxDeduction > 0 ? `<p class="mb-1">P/X Vehicle Returned: <strong>£${pxDeduction.toFixed(2)} value</strong></p>` : ''}
                            ${FINANCE_AMOUNT > 0 ? `<p class="mb-1">Finance Freedom: <strong>£${FINANCE_AMOUNT.toFixed(2)}</strong></p>` : ''}
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Business Summary:</h6>
                            <p class="mb-1">Services/Delivery Kept: <strong>£${(SERVICE_FEE + DELIVERY_CHARGES).toFixed(2)}</strong></p>
                            <p class="mb-1">Deductions Kept: <strong>£${totalDeductions.toFixed(2)}</strong></p>
                        </div>
                    </div>
                </div>
                -->
                <!-- Final Result -->
                <div class="text-center p-3 bg-light rounded">
                    <h4 class="text-danger mb-0">
                        <i class="fas fa-ban"></i> Revenue: £0.00 - SALE VOIDED
                    </h4>
                    <small class="text-muted">Total sale value has been mathematically voided</small>
                </div>
            </div>
        `;

                // Show the breakdown
                breakdownContainer.style.display = 'block';
                document.getElementById("cashRefundAmount").value = cashAvailableForRefund.toFixed(2);
            } else {

                const calculationCard = document.querySelector('.calculation-card');
                if (calculationCard) {
                    calculationCard.style.display = 'block';
                }

                // Hide full return breakdown for other refund types
                const breakdownContainer = document.getElementById('fullReturnBreakdown');
                if (breakdownContainer) {
                    breakdownContainer.style.display = 'none';
                }
            }

            // Visual feedback (keep your existing code)
            const cashRefundInput = document.getElementById('cashRefundAmount');
            const netElement = document.getElementById('summaryNet');
            const netParent = netElement.parentElement;

            // Reset classes
            netParent.classList.remove('text-success', 'text-warning', 'text-danger');
            cashRefundInput.classList.remove('is-invalid');

            if (!isRequestValid) {
                // Request exceeds available amount
                netParent.classList.add('text-danger');
                cashRefundInput.classList.add('is-invalid');

                if (refundType !== "full_return") {
                    showWarningMessage(
                        `Requested amount exceeds available funds. Maximum you can refund is £${availableAfterDeductions.toFixed(2)}`
                    );
                }
            } else if (cashRefundAmount === 0) {
                // No amount entered
                netParent.classList.add('text-warning');
                clearWarningMessage();
            } else {
                // Valid request
                netParent.classList.add('text-success');
                clearWarningMessage();
            }
        }

        function addPxDeduction() {
            if (pxDeductionId !== null) return; // Already exists

            console.log('addPxDeduction called, PX_ALLOWANCE:', PX_ALLOWANCE);

            // Create a special ID for P/X deduction
            pxDeductionId = 'px-allowance-' + Date.now();

            // Increment deduction count for unique name
            deductionCount++;

            // Create the deduction row manually instead of using addDeductionRow
            const container = document.getElementById('deductionsContainer');
            const row = document.createElement('div');
            row.className = 'row deduction-row mb-3 p-2 border-warning';
            row.setAttribute('data-px-deduction', pxDeductionId);

            row.innerHTML = `
        <div class="col-md-4">
            <label class="form-label">Deduction Type *</label>
            <input type="text" class="form-control deduction-type" 
                   name="deductions[${deductionCount}][type]" 
                   value="Part-Exchange Allowance" 
                   placeholder="e.g., Mileage Charge, Damage Fee, Admin Fee" required readonly>
        </div>
        <div class="col-md-4">
            <label class="form-label">Amount *</label>
            <div class="input-group">
                <span class="input-group-text">£</span>
                <input type="number" step="0.01" class="form-control deduction-amount" 
                       name="deductions[${deductionCount}][amount]" 
                       value="${PX_ALLOWANCE}" min="0" required readonly>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" name="deductions[${deductionCount}][description]" 
                   value="Deduction for returning part-exchange vehicle to customer" placeholder="Brief description" readonly>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-secondary remove-px-deduction" style="margin-bottom: 8px;" disabled>
                <i class="fas fa-lock text-muted"></i>
            </button>
        </div>
    `;

            container.appendChild(row);

            // Make all inputs readonly and styled
            row.querySelectorAll('input').forEach(input => {
                input.readOnly = true;
                input.classList.add('bg-light');
            });

            // Add click handler to the remove button
            const removeBtn = row.querySelector('.remove-px-deduction');
            removeBtn.onclick = function() {
                document.getElementById('pxVehicleReturned').checked = false;
                calculateTotals();
            };

            console.log('P/X deduction added successfully');
        }

        function removePxDeduction() {
            if (pxDeductionId === null) return;

            console.log('removePxDeduction called');

            // Find and remove the P/X deduction row
            const pxRow = document.querySelector(`[data-px-deduction="${pxDeductionId}"]`);
            if (pxRow) {
                pxRow.remove();
            }

            pxDeductionId = null;
        }

        function addDeductionRow(deduction = {}, deductionId = null) {
            deductionCount++;
            const container = document.getElementById('deductionsContainer');

            // Use provided ID or generate one
            const rowId = deductionId || 'deduction-' + deductionCount;

            const row = document.createElement('div');
            row.className = 'row deduction-row mb-3 p-2';

            row.innerHTML = `
        <div class="col-md-4">
            <label class="form-label">Deduction Type *</label>
            <input type="text" class="form-control deduction-type" 
                   name="deductions[${deductionCount}][type]" 
                   value="${deduction.type || ''}" 
                   placeholder="e.g., Mileage Charge, Damage Fee, Admin Fee" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Amount *</label>
            <div class="input-group">
                <span class="input-group-text">£</span>
                <input type="number" step="0.01" class="form-control deduction-amount" 
                       name="deductions[${deductionCount}][amount]" 
                       value="${deduction.amount || ''}" min="0" required>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" name="deductions[${deductionCount}][description]" 
                   value="${deduction.description || ''}" placeholder="Brief description">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-deduction" style="margin-bottom: 8px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

            container.appendChild(row);

            // Add event listener for removal
            const removeBtn = row.querySelector('.remove-deduction');
            removeBtn.addEventListener('click', function() {
                row.remove();
                calculateTotals();
            });

            // Add event listeners for dynamic calculation
            row.querySelector('.deduction-amount').addEventListener('input', calculateTotals);
            row.querySelector('.deduction-type').addEventListener('input', calculateTotals);

            return row;
        }

        // Initialize event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing event listeners');

            // Add deduction button
            document.getElementById('addDeduction')?.addEventListener('click', function() {
                console.log('Add deduction button clicked');
                addDeductionRow();
            });

            // Cash refund amount change
            const cashRefundInput = document.getElementById('cashRefundAmount');
            if (cashRefundInput) {
                cashRefundInput.addEventListener('input', calculateTotals);
                console.log('Added event listener to cashRefundAmount');
            }

            // P/X vehicle returned checkbox change
            const pxCheckbox = document.getElementById('pxVehicleReturned');
            if (pxCheckbox) {
                pxCheckbox.addEventListener('change', function() {
                    console.log('pxVehicleReturned changed to:', this.checked);
                    // Immediately recalculate totals
                    setTimeout(calculateTotals, 10);
                });
                console.log('Added event listener to pxVehicleReturned');
            }

            const businessKeepsCheckbox = document.getElementById('BusinessKeeps');
            if (businessKeepsCheckbox) {
                businessKeepsCheckbox.addEventListener('change', function() {

                    // Recalculate
                    setTimeout(calculateTotals, 10);
                });
            }


            // Refund type change
            const refundTypeSelect = document.getElementById('refundType');
            if (refundTypeSelect) {
                refundTypeSelect.addEventListener('change', function() {
                    console.log('refundType changed to:', this.value);
                    const refundType = this.value;
                    const vehicleCheckbox = document.getElementById('vehicleReturned');
                    const pxVehicleCheckbox = document.getElementById('pxVehicleReturned');
                    const pxVehicleSection = document.getElementById('pxVehicleSection');
                    const businessKeepsSection = document.getElementById('BusinessKeepsSection');
                    const businessKeepsCheckbox = document.getElementById('BusinessKeeps');
                    const businessKeepsReason = document.getElementById('BusinessKeepsReason');
                    const financeSection = document.getElementById('financeSection');
                    const vehicleReturnSection = document.getElementById('vehicleReturnSection');


                    // Handle different refund types
                    if (refundType === 'full_return') {
                        // Full Return - auto-check vehicle return
                        if (vehicleCheckbox) {
                            vehicleCheckbox.checked = true;
                            vehicleCheckbox.readOnly = true;
                            vehicleCheckbox.disabled = false;
                            pxVehicleSection.style.display = 'block';
                            pxVehicleCheckbox.checked = true;
                            // Show Business Keeps section for full returns
                            businessKeepsSection.style.display = 'block';
                            businessKeepsCheckbox.checked = true; // Default checked
                            financeSection.style.display = 'block';
                            vehicleReturnSection.style.display = 'block';

                        }
                        // Auto-check P/X for full returns if P/X exists
                        if (pxVehicleCheckbox) {
                            pxVehicleCheckbox.checked = true;
                            pxVehicleCheckbox.readOnly = true;
                            pxVehicleCheckbox.disabled = false;
                        }
                        if (businessKeepsCheckbox) {
                            businessKeepsCheckbox.checked = true;
                            businessKeepsCheckbox.readOnly = true;
                            businessKeepsCheckbox.disabled = false;
                        }
                    } else {
                        // For any other type, reset
                        if (vehicleCheckbox) {
                            vehicleCheckbox.checked = false;
                            vehicleCheckbox.disabled = true;
                            businessKeepsSection.style.display = 'none';
                            businessKeepsCheckbox.checked = false;
                        }
                        if (pxVehicleCheckbox) {
                            pxVehicleCheckbox.checked = false;
                            pxVehicleCheckbox.disabled = true;
                        }


                    }

                    // Recalculate after type change
                    setTimeout(calculateTotals, 10);
                });
            }

            // Form validation
            const refundForm = document.getElementById('refundForm');
            if (refundForm) {
                refundForm.addEventListener('submit', function(e) {
                    // Your existing form validation code...
                    // Keep your original form validation code here
                });
            }

            // Initialize calculations on page load
            console.log('Initial calculation on page load');
            calculateTotals();

            // Auto-close alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Make calculateTotals globally available for inline oninput handlers
        window.calculateTotals = calculateTotals;
    </script>
    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->

    <script src="vendors/popper/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/anchorjs/anchor.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="vendors/fontawesome/all.min.js"></script>
    <script src="vendors/lodash/lodash.min.js"></script>
    <script src="vendors/list.js/list.min.js"></script>
    <script src="vendors/feather-icons/feather.min.js"></script>
    <script src="vendors/dayjs/dayjs.min.js"></script>
    <script src="vendors/leaflet/leaflet.js"></script>
    <script src="vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
    <script src="vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter.min.js"></script>
    <script src="assets/js/phoenix.js"></script>
    <script src="vendors/echarts/echarts.min.js"></script>
    <script src="assets/js/ecommerce-dashboard.js"></script>
</body>

</html>