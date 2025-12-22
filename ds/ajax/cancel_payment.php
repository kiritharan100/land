 <?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
include '../../db.php';
require_once __DIR__ . '/payment_allocator.php';

header('Content-Type: application/json');

if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $payment_id = intval($_POST['payment_id']);
        
        if ($payment_id <= 0) {
            throw new Exception("Invalid payment ID");
        }
        
        // Get payment details before cancellation
        $payment_sql = "SELECT lp.*, l.lease_number,l.file_number ,l.beneficiary_id, l.lease_type_id
                       FROM lease_payments lp 
                       LEFT JOIN leases l ON lp.lease_id = l.lease_id 
                       WHERE lp.payment_id = ?";
        $stmt = $con->prepare($payment_sql);
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        if (isset($payment['status']) && (int)$payment['status'] === 0) {
            throw new Exception("Payment is already cancelled");
        }

        // Allow cancellation only for the most recent active payment on the lease.
        $latest_sql = "SELECT payment_id 
                       FROM lease_payments 
                       WHERE lease_id = ? AND status = 1 
                       ORDER BY payment_date DESC, payment_id DESC 
                       LIMIT 1";
        $stmt_latest = $con->prepare($latest_sql);
        $stmt_latest->bind_param("i", $payment['lease_id']);
        $stmt_latest->execute();
        $latest_row = $stmt_latest->get_result()->fetch_assoc();
        $stmt_latest->close();

        if (!$latest_row || (int)$latest_row['payment_id'] !== $payment_id) {
            throw new Exception("Only the latest active payment can be cancelled.");
        }

        
        // Start transaction
        $con->begin_transaction();

        // 1) Mark the payment as cancelled (status = 0) instead of deleting
        $delete_sql = "UPDATE lease_payments SET status = 0 WHERE payment_id = ?";
        $stmt_delete = $con->prepare($delete_sql);
        $stmt_delete->bind_param("i", $payment_id);
        if (!$stmt_delete->execute()) {
            throw new Exception("Failed to cancel payment: " . $stmt_delete->error);
        }


        // 1b) Mark detail rows of this cancelled payment as inactive (status = 0)
        $cancelDetailSql = "UPDATE lease_payments_detail SET status = 0 WHERE payment_id = ?";
        $stmt_cancel_detail = $con->prepare($cancelDetailSql);
        $stmt_cancel_detail->bind_param("i", $payment_id);
        if (!$stmt_cancel_detail->execute()) {
            throw new Exception("Failed to cancel payment detail rows: " . $stmt_cancel_detail->error);
        }


        // 2) Reset all schedule payment allocations for this lease to zero,
        //    including discount_apply so discount is fully reversed.
        $reset_sql = "UPDATE lease_schedules 
                      SET paid_rent = 0, 
                          panalty_paid = 0, 
                          premium_paid = 0, 
                          total_paid = 0,
                          discount_apply = 0
                      WHERE lease_id = ?";
        $stmt_reset = $con->prepare($reset_sql);
        $stmt_reset->bind_param("i", $payment['lease_id']);
        if (!$stmt_reset->execute()) {
            throw new Exception('Failed to reset schedules: ' . $stmt_reset->error);
        }

        // 3) Reapply remaining ACTIVE payments (status = 1) for this lease
        //    in chronological order to rebuild allocations.
        $payments_sql = "SELECT payment_id, lease_id, payment_date, amount
                         FROM lease_payments 
                         WHERE lease_id = ? AND status = 1
                         ORDER BY payment_date ASC, payment_id ASC";
        $stmt_payments = $con->prepare($payments_sql);
        $stmt_payments->bind_param("i", $payment['lease_id']);
        if (!$stmt_payments->execute()) {
            throw new Exception('Failed to fetch remaining payments: ' . $stmt_payments->error);
        }
        $res_payments = $stmt_payments->get_result();

        $discount_rate = fetchLeaseDiscountRate($con, isset($payment['lease_type_id']) ? intval($payment['lease_type_id']) : null, intval($payment['lease_id']));
        $schedule_state = loadLeaseSchedulesForPayment($con, intval($payment['lease_id']));

        $updatePaymentSql = "UPDATE lease_payments SET 
                schedule_id=?,
                rent_paid=?,
                panalty_paid=?,
                premium_paid=?,
                discount_apply=?,
                current_year_payment=?,
                payment_type=?
             WHERE payment_id=?";
        $updatePaymentStmt = $con->prepare($updatePaymentSql);
        if (!$updatePaymentStmt) {
            throw new Exception('Failed to prepare payment update');
        }

        $updateScheduleSql = "UPDATE lease_schedules SET 
                paid_rent = paid_rent + ?,
                panalty_paid = panalty_paid + ?,
                premium_paid = premium_paid + ?,
                total_paid = total_paid + ?,
                discount_apply = discount_apply + ?
             WHERE schedule_id = ?";
        $updateScheduleStmt = $con->prepare($updateScheduleSql);
        if (!$updateScheduleStmt) {
            $updatePaymentStmt->close();
            throw new Exception('Failed to prepare schedule update');
        }

        $insertDetailSql = "INSERT INTO lease_payments_detail (
                payment_id, schedule_id, rent_paid, penalty_paid, premium_paid,
                discount_apply, current_year_payment, status
            ) VALUES (?,?,?,?,?,?,?,?)";
        $insertDetailStmt = $con->prepare($insertDetailSql);
        if (!$insertDetailStmt) {
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            throw new Exception('Failed to prepare payment detail insert');
        }

        $deleteDetailStmt = $con->prepare("DELETE FROM lease_payments_detail WHERE payment_id = ?");
        if (!$deleteDetailStmt) {
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            throw new Exception('Failed to prepare detail cleanup');
        }

        while ($p = $res_payments->fetch_assoc()) {
            $paymentId = intval($p['payment_id']);
            $paymentAmount = floatval($p['amount']);
            $paymentDate = $p['payment_date'];

            if ($paymentAmount <= 0) {
                continue;
            }

            $allocation = allocateLeasePayment($schedule_state, $paymentDate, $paymentAmount, $discount_rate);
            $allocations = $allocation['allocations'];
            $totals = $allocation['totals'];
            $currentScheduleId = $allocation['current_schedule_id'];
            $remainingAfter = $allocation['remaining'];

            if ($remainingAfter > 0.01) {
                throw new Exception('Unable to reallocate payment ID '.$paymentId.' completely');
            }

            if (empty($allocations)) {
                $schedule_state = $allocation['schedules'];
                continue;
            }

            $totalActual = $totals['rent'] + $totals['penalty'] + $totals['premium'];
            if (abs($totalActual - $paymentAmount) > 0.01) {
                throw new Exception('Reallocation mismatch for payment ID '.$paymentId);
            }

            $paymentType = 'mixed';
            $newRent = $totals['rent'];
            $newPenalty = $totals['penalty'];
            $newPremium = $totals['premium'];
            $newDiscount = $totals['discount'];
            $newCurrentYear = $totals['current_year_payment'];

            $updatePaymentStmt->bind_param(
                'idddddsi',
                $currentScheduleId,
                $newRent,
                $newPenalty,
                $newPremium,
                $newDiscount,
                $newCurrentYear,
                $paymentType,
                $paymentId
            );
            if (!$updatePaymentStmt->execute()) {
                throw new Exception('Failed to update payment '.$paymentId.': '.$updatePaymentStmt->error);
            }

            $deleteDetailStmt->bind_param('i', $paymentId);
            $deleteDetailStmt->execute();

            foreach ($allocations as $sid => $alloc) {
                $rentInc = $alloc['rent'];
                $penInc = $alloc['penalty'];
                $premInc = $alloc['premium'];
                $discInc = $alloc['discount'];
                $curYearInc = $alloc['current_year_payment'];
                $totalPaidSchedule = $alloc['total_paid'];

                $scheduleId = intval($sid);

                $updateScheduleStmt->bind_param(
                    'dddddi',
                    $rentInc,
                    $penInc,
                    $premInc,
                    $totalPaidSchedule,
                    $discInc,
                    $scheduleId
                );
                if (!$updateScheduleStmt->execute()) {
                    throw new Exception('Failed to update schedule '.$scheduleId.': '.$updateScheduleStmt->error);
                }

                $hasDetail = ($rentInc > 0) || ($penInc > 0) || ($premInc > 0) || ($discInc > 0);
                if ($hasDetail) {
                    $status = 1;
                    $insertDetailStmt->bind_param(
                        'iidddddi',
                        $paymentId,
                        $scheduleId,
                        $rentInc,
                        $penInc,
                        $premInc,
                        $discInc,
                        $curYearInc,
                        $status
                    );
                    if (!$insertDetailStmt->execute()) {
                        throw new Exception('Failed to insert payment detail for payment '.$paymentId);
                    }
                }
            }

            $schedule_state = $allocation['schedules'];
        }

        $deleteDetailStmt->close();
        $insertDetailStmt->close();
        $updateScheduleStmt->close();
        $updatePaymentStmt->close();
        $stmt_payments->close();

        // Commit transaction after replay
        $con->commit();
        
        // Log the action
        if (function_exists('UserLog')) {
            $ben_id = intval($payment['beneficiary_id'] ?? 0);
            UserLog(
                '2', 
                'LTL Cancel Payment', 
                "Cancelled payment: {$payment['reference_number']}, Amount: {$payment['amount']}, Lease_file: {$payment['file_number']}",
                $ben_id
            );
        }

        // Trigger penalty recalculation for this specific lease
        try {
            $_REQUEST['lease_id'] = $payment['lease_id'];
            ob_start();
            include '../cal_panalty.php';
            ob_get_clean();
        } catch (Exception $e) {
            // Non-fatal
        }

        $response['success'] = true;
        $response['message'] = "Payment {$payment['receipt_number']} has been cancelled successfully and allocations rebuilt.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($con->in_transaction) {
            $con->rollback();
        }
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
