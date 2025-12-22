<?php
session_start();
include '../../db.php';
require_once __DIR__ . '/payment_allocator.php';

header('Content-Type: application/json');

/**
 * Ensure a column exists on a table (best-effort; keeps legacy data intact).
 */
function ensure_column_exists(mysqli $con, string $table, string $column, string $definition): void
{
    $tableEsc = mysqli_real_escape_string($con, $table);
    $colEsc   = mysqli_real_escape_string($con, $column);

    $checkSql = "SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = '$tableEsc'
                   AND COLUMN_NAME  = '$colEsc'
                 LIMIT 1";

    $exists = mysqli_query($con, $checkSql);
    if ($exists && mysqli_num_rows($exists) === 0) {
        @mysqli_query($con, "ALTER TABLE `$tableEsc` ADD COLUMN `$colEsc` $definition");
    }
}

// Align schema with payment logic (idempotent).
ensure_column_exists($con, 'lease_schedules', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
ensure_column_exists($con, 'lease_payments', 'discount_apply', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
ensure_column_exists($con, 'lease_payments', 'current_year_payment', 'DECIMAL(12,2) NOT NULL DEFAULT 0');

/**
 * Early JSON response helper.
 */
function respond(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if (!$_POST) {
    respond(['success' => false, 'message' => 'No data received']);
}

$response = ['success' => false, 'message' => ''];

try {
    // Basic required fields.
    if (empty($_POST['lease_id']) || empty($_POST['payment_date']) || empty($_POST['amount'])) {
        throw new Exception('Missing required fields');
    }

    // Resolve location/client from cookie when present.
    $location_id = 0;
    if (isset($_COOKIE['client_cook'])) {
        $selected_client = $_COOKIE['client_cook'];
        $sel_query       = "SELECT c_id FROM client_registration WHERE md5_client='$selected_client'";
        $result          = mysqli_query($con, $sel_query);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $location_id = intval($row['c_id']);
        }
    }

    // Input parsing.
    $lease_id           = intval($_POST['lease_id']);
    $lease_type_id      = intval($_POST['lease_type_id']);
    $payment_date       = $_POST['payment_date'];
    $amount             = floatval($_POST['amount']);
    $payment_method     = $_POST['payment_method'] ?? 'cash';
    $reference_number   = $_POST['reference_number'] ?? '';
    $notes              = $_POST['notes'] ?? '';
    $ben_id             = intval($_POST['ben_id'] ?? 0);
    $payment_sms        = intval($_POST['payment_sms'] ?? 0);
    $sms_language       = trim($_POST['sms_language'] ?? 'English');
    $telephone          = trim($_POST['telephone'] ?? '');

    if ($amount <= 0) {
        throw new Exception('Payment amount must be greater than zero');
    }

    $receipt_number = 'RCPT-' . date('Ymd-His') . '-' . rand(100, 999);
    $user_id        = $_SESSION['user_id'] ?? 1;

    // Allocation step: fetch discount, schedules, then allocate payment.
    $discount_rate = fetchLeaseDiscountRate($con, $lease_type_id, $lease_id);
    $schedules     = loadLeaseSchedulesForPayment($con, $lease_id);
    if (empty($schedules)) {
        throw new Exception('No schedules available for this lease');
    }

    $allocation      = allocateLeasePayment($schedules, $payment_date, $amount, $discount_rate);
    $allocations     = $allocation['allocations'];
    $totals          = $allocation['totals'];
    $schedule_id     = $allocation['current_schedule_id'];
    $remaining_after = $allocation['remaining'];

    if ($remaining_after > 0.01) {
        throw new Exception('Unable to allocate entire payment amount');
    }
    if (empty($allocations)) {
        throw new Exception('Payment does not correspond to any outstanding amounts');
    }

    $total_rent_paid      = $totals['rent'];
    $penalty_paid         = $totals['penalty'];
    $premium_paid         = $totals['premium'];
    $discount_to_apply    = $totals['discount'];
    $current_year_payment = $totals['current_year_payment'];
    $payment_type         = 'mixed';
    $discount_applied     = ($discount_to_apply > 0);
    $discount_amount      = $discount_to_apply;

    // Sanity check against amount received.
    $total_actual_paid = $total_rent_paid + $penalty_paid + $premium_paid;
    if (abs($total_actual_paid - $amount) > 0.01) {
        throw new Exception('Payment amount does not match allocated totals');
    }

    // Persist payment + allocation details.
    $con->begin_transaction();

    $payment_sql = "INSERT INTO lease_payments (
            lease_id, location_id, schedule_id, payment_date, amount,
            rent_paid, panalty_paid, premium_paid, discount_apply,
            current_year_payment, payment_type,
            receipt_number, payment_method, reference_number, notes, created_by
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $con->prepare($payment_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare payment insert');
    }

    $stmt->bind_param(
        'iiisddddddsssssi',
        $lease_id,
        $location_id,
        $schedule_id,
        $payment_date,
        $amount,
        $total_rent_paid,
        $penalty_paid,
        $premium_paid,
        $discount_to_apply,
        $current_year_payment,
        $payment_type,
        $receipt_number,
        $payment_method,
        $reference_number,
        $notes,
        $user_id
    );
    $stmt->execute();
    if ($stmt->errno) {
        $stmt->close();
        throw new Exception('Failed to save payment: ' . $stmt->error);
    }
    $stmt->close();

    $payment_id = $con->insert_id;

    $updateSql = "UPDATE lease_schedules SET 
            paid_rent = paid_rent + ?,
            panalty_paid = panalty_paid + ?,
            premium_paid = premium_paid + ?,
            total_paid = total_paid + ?,
            discount_apply = discount_apply + ?
         WHERE schedule_id = ?";

    $updateStmt = $con->prepare($updateSql);
    if (!$updateStmt) {
        throw new Exception('Failed to prepare schedule update');
    }

    $detailSql = "INSERT INTO lease_payments_detail (
            payment_id, schedule_id, rent_paid, penalty_paid, premium_paid,
            discount_apply, current_year_payment, status
        ) VALUES (?,?,?,?,?,?,?,?)";
    $detailStmt = $con->prepare($detailSql);
    if (!$detailStmt) {
        $updateStmt->close();
        throw new Exception('Failed to prepare payment detail insert');
    }

    foreach ($allocations as $sid => $alloc) {
        $rentIncrement         = $alloc['rent'];
        $penaltyIncrement      = $alloc['penalty'];
        $premiumIncrement      = $alloc['premium'];
        $discountIncrement     = $alloc['discount'];
        $currentYearIncrement  = $alloc['current_year_payment'];
        $totalPaidSchedule     = $alloc['total_paid'];

        $updateStmt->bind_param(
            'dddddi',
            $rentIncrement,
            $penaltyIncrement,
            $premiumIncrement,
            $totalPaidSchedule,
            $discountIncrement,
            $sid
        );
        $updateStmt->execute();
        if ($updateStmt->errno) {
            $detailStmt->close();
            $updateStmt->close();
            throw new Exception('Failed to update schedule totals: ' . $updateStmt->error);
        }

        $hasDetail = ($rentIncrement > 0)
            || ($penaltyIncrement > 0)
            || ($premiumIncrement > 0)
            || ($discountIncrement > 0);

        if ($hasDetail) {
            $status = 1;
            $detailStmt->bind_param(
                'iidddddi',
                $payment_id,
                $sid,
                $rentIncrement,
                $penaltyIncrement,
                $premiumIncrement,
                $discountIncrement,
                $currentYearIncrement,
                $status
            );
            $detailStmt->execute();
            if ($detailStmt->errno) {
                $detailStmt->close();
                $updateStmt->close();
                throw new Exception('Failed to insert payment detail: ' . $detailStmt->error);
            }
        }
    }

    $detailStmt->close();
    $updateStmt->close();

    $con->commit();

    // Recalculate penalties for this lease after payment success (non-blocking).
    try {
        $_REQUEST['lease_id'] = $lease_id;
        ob_start();
        include __DIR__ . '/../cal_panalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // Ignore penalty calculation errors to avoid breaking payment flow.
    }

    // Optional SMS notification (format kept identical to original behavior).
    if ($payment_sms === 1 && preg_match('/^[0-9]{10}$/', $telephone)) {
        $tpl_sql  = "SELECT english_sms, tamil_sms, sinhala_sms 
                     FROM sms_templates 
                     WHERE sms_name = 'Payment' AND status = 1 LIMIT 1";
        $tpl_stmt = $con->prepare($tpl_sql);
        $tpl_stmt->execute();
        $tpl = $tpl_stmt->get_result()->fetch_assoc();

        if ($tpl) {
            $message = $tpl['english_sms'];
            if ($sms_language === 'Tamil') {
                $message = $tpl['tamil_sms'];
            } elseif ($sms_language === 'Sinhala') {
                $message = $tpl['sinhala_sms'];
            }

            $message = str_replace('@Receipt_no', $reference_number, $message);
            $message = str_replace('@paid_amount', number_format($amount, 2), $message);

            require_once __DIR__ . '/../../sms_helper.php';
            $sms      = new SMS_Helper();
            $sms_type = 'Payment SMS';

            $result = $sms->sendSMS($lease_id, $telephone, $message, $sms_type);
            if (!$result['success']) {
                $response['sms_warning'] = 'SMS failed: ' . $result['comment'];
            }
        }
    }

    // User log + response payload.
    $log_detail  = "Lease ID=$lease_id";
    $log_detail .= ' | Amount=' . number_format($amount, 2);
    $log_detail .= " | Rec No=$reference_number";
    $log_detail .= " | Method=$payment_method";
    $log_detail .= " | Date=$payment_date";
    $log_detail .= ' | Discount=' . ($discount_applied ? 'YES' : 'NO');
    UserLog('2', 'LTL New Payment', $log_detail, $ben_id);

    $response['success']         = true;
    $response['discount']        = $discount_applied ? 'YES' : 'NO';
    $response['discount_amount'] = number_format($discount_amount, 2);
    $response['message']         = 'Payment saved successfully';
} catch (Exception $e) {
    if ($con->in_transaction) {
        $con->rollback();
    }
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

respond($response);
