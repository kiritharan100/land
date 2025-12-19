<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__) . '/ajax/payment_allocator.php';
header('Content-Type: application/json');

$response = ['success'=>false,'message'=>''];

try{
    // ----------------------------------------------------
    // Resolve location_id from cookie (same as create)
    // ----------------------------------------------------
    $location_id = 0;
    if(isset($_COOKIE['client_cook'])){
        $selected_client = $_COOKIE['client_cook'];
        $sel_query = "SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1";
        if ($stmtC = mysqli_prepare($con, $sel_query)){
            mysqli_stmt_bind_param($stmtC, 's', $selected_client);
            mysqli_stmt_execute($stmtC);
            $resC = mysqli_stmt_get_result($stmtC);
            if ($resC && ($rowC = mysqli_fetch_assoc($resC))) {
                $location_id = (int)$rowC['c_id'];
            }
            mysqli_stmt_close($stmtC);
        }
    }

    // -----------------------------------------------------
    // Incoming IDs
    // ----------------------------------------------------
    $lease_id        = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $land_id         = isset($_POST['land_id']) ? (int)$_POST['land_id'] : 0;
    $beneficiary_id  = isset($_POST['beneficiary_id']) ? (int)$_POST['beneficiary_id'] : 0;
    if ($lease_id<=0) {
        throw new Exception('Missing lease, land or beneficiary');
    }
    // if ($lease_id<=0 || $land_id<=0 || $beneficiary_id<=0) { throw new Exception('Missing lease, land or beneficiary'); }

    // ----------------------------------------------------
    // LOAD OLD LEASE VALUES (for change detection)
    // ----------------------------------------------------
    $oldLease = null;
    if ($rsOld = mysqli_query(
        $con,
        "SELECT valuation_amount, start_date, annual_rent_percentage, end_date,
                revision_period, revision_percentage, duration_years, premium,lease_number,file_number
         FROM leases WHERE lease_id={$lease_id} LIMIT 1"
    )){
        if ($rowOld = mysqli_fetch_assoc($rsOld)) {
            $oldLease = $rowOld;
        }
        mysqli_free_result($rsOld);
    }

    // ----------------------------------------------------
    // Incoming fields
    // ----------------------------------------------------
    $valuation_amount       = floatval($_POST['valuation_amount'] ?? 0);
    $valuation_date         = $_POST['valuation_date'] ?? '';
    $value_date             = $_POST['value_date'] ?? '';
    $approved_date          = $_POST['approved_date'] ?? '';
    // if (empty($valuation_date)) { throw new Exception('Letter Date is required'); }
    // if (empty($value_date)) { throw new Exception('Valuvation Date is required'); }
    // if (empty($approved_date)) { throw new Exception('Approved Date is required'); }

    $annual_rent_percentage = floatval($_POST['annual_rent_percentage'] ?? 0);
    $revision_period        = (int)($_POST['revision_period'] ?? 0);
    $revision_percentage    = floatval($_POST['revision_percentage'] ?? 0);
    $start_date             = $_POST['start_date'] ?? '';
    $end_date               = $_POST['end_date'] ?? '';
    $duration_years         = (int)($_POST['duration_years'] ?? 0);
    $lease_type_id          = isset($_POST['lease_type_id1']) ? (int)$_POST['lease_type_id1'] : 0;
    $type_of_project        = isset($_POST['type_of_project']) ? mysqli_real_escape_string($con, $_POST['type_of_project']) : '';
    $name_of_the_project    = isset($_POST['name_of_the_project']) ? mysqli_real_escape_string($con, $_POST['name_of_the_project']) : '';
    $lease_number           = isset($_POST['lease_number']) ? mysqli_real_escape_string($con, $_POST['lease_number']) : '';
    $file_number            = isset($_POST['file_number']) ? mysqli_real_escape_string($con, $_POST['file_number']) : '';
    $premium_input          = isset($_POST['premium']) ? floatval(str_replace(',', '', $_POST['premium'])) : 0.0;


    // ----------------------------------------------------
    // Payment existence check (only active payments)
    // ----------------------------------------------------


    $changes = [];

// Helper to compare safely
 function detectChange($label, $oldValue, $newValue, &$changes) {

    // Convert to trimmed string
    $o = trim((string)$oldValue);
    $n = trim((string)$newValue);

    // Normalize null conditions
    $nullValues = ["", "null", "NULL", "0.00", "0", "0000-00-00"];

    if (in_array($o, $nullValues, true)) $o = "null";
    if (in_array($n, $nullValues, true)) $n = "null";

    // If both null → ignore
    if ($o === "null" && $n === "null") {
        return; // no change
    }

    // If both are numeric → compare numerically
    if (is_numeric($o) && is_numeric($n)) {
        if (floatval($o) == floatval($n)) {
            return; // no change
        }
        // numeric difference → format to 2 decimals
        $oFormatted = number_format(floatval($o), 2, '.', '');
        $nFormatted = number_format(floatval($n), 2, '.', '');
        $changes[] = "$label: $oFormatted > $nFormatted";
        return;
    }

    // Compare as strings
    if ($o !== $n) {
        $changes[] = "$label: $o > $n";
    }
}



detectChange("valuation_amount",       $oldLease['valuation_amount'] ?? "", $valuation_amount, $changes);
detectChange("valuation_date",         $oldLease['valuation_date'] ?? "",   $valuation_date, $changes);
detectChange("value_date",             $oldLease['value_date'] ?? "",       $value_date, $changes);
detectChange("approved_date",          $oldLease['approved_date'] ?? "",    $approved_date, $changes);
detectChange("annual_rent_percentage", $oldLease['annual_rent_percentage'] ?? "", $annual_rent_percentage, $changes);
detectChange("revision_period",        $oldLease['revision_period'] ?? "",  $revision_period, $changes);
detectChange("revision_percentage",    $oldLease['revision_percentage'] ?? "", $revision_percentage, $changes);
detectChange("start_date",             $oldLease['start_date'] ?? "",       $start_date, $changes);
detectChange("end_date",               $oldLease['end_date'] ?? "",         $end_date, $changes);
detectChange("duration_years",         $oldLease['duration_years'] ?? "",   $duration_years, $changes);
detectChange("premium",                $oldLease['premium'] ?? "",          $premium, $changes);
detectChange("lease_number",           $oldLease['lease_number'] ?? "",    $lease_number, $changes);    
detectChange("file_number",            $oldLease['file_number'] ?? "",     $file_number, $changes);



    $payments_count = 0;
    if ($stP = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt FROM lease_payments WHERE lease_id=? AND status=1')){
        mysqli_stmt_bind_param($stP, 'i', $lease_id);
        mysqli_stmt_execute($stP);
        $rp = mysqli_stmt_get_result($stP);
        if ($rp && ($row = mysqli_fetch_assoc($rp))) { 
            $payments_count = (int)$row['cnt']; 
        }
        mysqli_stmt_close($stP);
    }

    // ----------------------------------------------------
    // Compute effective percentage (economy vs base)
    // ----------------------------------------------------
    

   $effective_pct = $annual_rent_percentage; 
    if ($lease_type_id > 0) {
        $q = "SELECT base_rent_percent, economy_rate, economy_valuvation 
              FROM lease_master WHERE lease_type_id=$lease_type_id LIMIT 1";
        if ($rs = mysqli_query($con, $q)) {
            if ($lm = mysqli_fetch_assoc($rs)) {
                $base_pct = isset($lm['base_rent_percent']) ? floatval($lm['base_rent_percent']) : 0.0;
                $eco_rate = isset($lm['economy_rate']) ? floatval($lm['economy_rate']) : 0.0;
                $eco_val  = isset($lm['economy_valuvation']) ? floatval($lm['economy_valuvation']) : 0.0;
                if ($valuation_amount > 0 && $eco_val > 0 && $eco_rate > 0 && $valuation_amount <= $eco_val) {
                    $effective_pct = $eco_rate;
                } else {
                    $effective_pct = $base_pct > 0 ? $base_pct : $annual_rent_percentage;
                }
            }
            mysqli_free_result($rs);
        }
    }
    $annual_rent_percentage = $effective_pct;




    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // ----------------------------------------------------
    // Recalculate premium (same rule as before)
    // ----------------------------------------------------

     $initial_annual_rent = $valuation_amount * ($annual_rent_percentage / 100.0);
    $note = '';

    
    $premium = 0.0;
    if (!empty($start_date) && strtotime($start_date) < strtotime('2020-01-01')) {
             $sql = "SELECT * FROM lease_master WHERE lease_type_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $lease_type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $premium_times = floatval($row['premium_times']); 
        } else {
            $premium_times = 0.00;  // default if not found
        }


    $premium = $initial_annual_rent * $premium_times;
    
    }

    // ----------------------------------------------------
    // DETECT IF WE NEED FULL REBUILD + REPLAY
    // (valuation, start_date, % changed)
    // ----------------------------------------------------
    $need_rebuild_and_replay = false;
    if ($oldLease){
        $old_val   = floatval($oldLease['valuation_amount'] ?? 0);
        $old_start = $oldLease['start_date'] ?? '';
        $old_pct   = floatval($oldLease['annual_rent_percentage'] ?? 0);

        // if (round($old_val,2) != round($valuation_amount,2) ||
        //     $old_start != $start_date ||
        //     round($old_pct,4) != round($annual_rent_percentage,4)) {
        //     $need_rebuild_and_replay = true;
        // }

                  if (
              round($old_val,2) != round($valuation_amount,2) ||
              $old_start != $start_date ||
              round($old_pct,4) != round($annual_rent_percentage,4) ||
              (int)$oldLease['revision_period'] != (int)$revision_period ||
              round($oldLease['revision_percentage'],4) != round($revision_percentage,4) ||
              (int)$oldLease['duration_years'] != (int)$duration_years
          ) {
              $need_rebuild_and_replay = true;
          }
 

    }

    // ----------------------------------------------------
    // UPDATE LEASE
    // ----------------------------------------------------
    $sql = "UPDATE leases SET 
                beneficiary_id=?,
                location_id=?,
                lease_number=?,
                file_number=?,
                valuation_amount=?,
                valuation_date=?,
                value_date=?,
                approved_date=?,
                premium=?,
                annual_rent_percentage=?,
                revision_period=?,
                revision_percentage=?,
                start_date=?,
                end_date=?,
                duration_years=?,
                name_of_the_project=?,
                updated_by=?,
                updated_on=NOW()
            WHERE lease_id=?";

    if ($stmt = mysqli_prepare($con, $sql)){
        mysqli_stmt_bind_param(
            $stmt,
            'iissdsssddidssisii',
            $beneficiary_id, $location_id, $lease_number, $file_number,
            $valuation_amount, $valuation_date, $value_date, $approved_date,
            $premium, $annual_rent_percentage, $revision_period,
            $revision_percentage, $start_date, $end_date, $duration_years,
            $name_of_the_project, $uid, $lease_id
        );
        if (!mysqli_stmt_execute($stmt)){
            throw new Exception('Error updating lease: ' . mysqli_error($con));
        }
        mysqli_stmt_close($stmt);
    } else {
        throw new Exception('DB error: ' . mysqli_error($con));
    }


    if (empty($valuation_date) || $valuation_date == '0000-00-00') {

    // Remove all penalties
    mysqli_query($con, "
        UPDATE lease_schedules 
        SET panalty = 0,
            panalty_paid = 0
        WHERE lease_id = {$lease_id}
    ");

    // Flag to SKIP penalty calculation later
    $skip_penalty = true;

} else {
    $skip_penalty = false;
}

    // ----------------------------------------------------
    // SCHEDULE + PAYMENT HANDLING (NO DUPLICATES)
    // ----------------------------------------------------
   

    if ($need_rebuild_and_replay) {

        // Key fields changed → delete schedules + regenerate + replay payments
        if (!rebuildSchedulesAndReapplyPayments(
            $con,
            $lease_id,
            $initial_annual_rent,
            $premium,
            $revision_period,
            $revision_percentage,
            $start_date,
            $duration_years
        )){
            throw new Exception('Failed to rebuild schedules and reprocess payments.');
        }
 
        $note =  ' Schedules regenerated and payments reprocessed (because lease values changed).';

    } else {


        // Key values NOT changed
        if ($payments_count === 0) {

            // No payments → safe to fully regenerate schedules
            mysqli_query($con, "DELETE FROM lease_schedules WHERE lease_id=$lease_id");

            if (!generateLeaseSchedules(
                $con,
                $lease_id,
                $initial_annual_rent,
                $premium,
                $revision_period,
                $revision_percentage,
                $start_date,
                $duration_years
            )){
                throw new Exception('Failed to regenerate schedules.');
            }

            if (!$skip_penalty) {
                try {
                    $_REQUEST['lease_id'] = $lease_id;
                    ob_start();
                    include __DIR__ . '/../cal_panalty.php';
                    ob_end_clean();
                } catch (Exception $e) { }
            }

            $note = ' Schedules regenerated (no payments exist).';

        } else {
            // Payments exist + no key field change → DO NOTHING
            $note = ' Payments exist. Schedules NOT regenerated.';
        }
    }

    // ----------------------------------------------------
    // Recalculate penalties for this lease (final)
    // ----------------------------------------------------
    // try {
    //     $_REQUEST['lease_id'] = $lease_id;
    //     ob_start();
    //     include __DIR__ . '/../cal_panalty.php';
    //     ob_end_clean();
    // } catch (Exception $e) {
    //     // non-fatal
    // }


    // ----------------------------------------------------
// Run penalty calculation ONLY if valuation_date exists
// ----------------------------------------------------
if (!$skip_penalty) {
    try {
        $_REQUEST['lease_id'] = $lease_id;
        ob_start();
        include __DIR__ . '/../cal_panalty.php';
        ob_end_clean();
    } catch (Exception $e) { }
}



  if (function_exists('UserLog')) {

    if (count($changes) > 0) {
        $change_text = implode(" | ", $changes);
            $log_msg = "Lease ID=$lease_id | Lease No=$lease_number | Changes: $change_text";
            UserLog(
                "2",
                "LTL Lease Updated",
                $log_msg,
                $beneficiary_id // saves ben_id in log
            );
    } 


}


    $response['success']  = true;
    $response['lease_id'] = $lease_id;
    $response['message']  = 'Lease updated successfully!' . $note;

} catch (Exception $ex){
    $response['success'] = false;
    $response['message'] = $ex->getMessage();
}

echo json_encode($response);

/**
 * Regenerate schedules and replay all ACTIVE (status=1) payments
 * using the same allocation + discount logic as the payment AJAX.
 */
function rebuildSchedulesAndReapplyPayments(
    $con,
    $lease_id,
    $initial_rent,
    $premium,
    $revision_period,
    $revision_percentage,
    $start_date,
    $duration_years = 30
){
    // 1) Load all active payments (status = 1) in date order
    $payments = [];
    if ($st = mysqli_prepare(
        $con,
        "SELECT * FROM lease_payments 
         WHERE lease_id=? AND status=1 
         ORDER BY payment_date ASC, payment_id ASC"
    )){
        mysqli_stmt_bind_param($st, 'i', $lease_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($row = mysqli_fetch_assoc($rs)) {
            $payments[] = $row;
        }
        mysqli_stmt_close($st);
    }

    // 2) Delete existing schedules for this lease
    if ($stDel = mysqli_prepare($con, 'DELETE FROM lease_schedules WHERE lease_id=?')){
        mysqli_stmt_bind_param($stDel, 'i', $lease_id);
        mysqli_stmt_execute($stDel);
        mysqli_stmt_close($stDel);
    }

    // 3) Regenerate schedules from scratch
    if (!generateLeaseSchedules(
        $con,
        $lease_id,
        $initial_rent,
        $premium,
        $revision_period,
        $revision_percentage,
        $start_date,
        $duration_years
    )){
        return false;
    }

    // 4) Recalculate penalties once so panalty values exist
    try {
        $_REQUEST['lease_id'] = $lease_id;
        ob_start();
        include __DIR__ . '/../cal_panalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // non-fatal
    }

    $discountRate = fetchLeaseDiscountRate($con, null, $lease_id);
    $scheduleState = loadLeaseSchedulesForPayment($con, $lease_id);

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
        return false;
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
        return false;
    }

    $insertDetailSql = "INSERT INTO lease_payments_detail (
            payment_id, schedule_id, rent_paid, penalty_paid, premium_paid,
            discount_apply, current_year_payment, status
        ) VALUES (?,?,?,?,?,?,?,?)";
    $insertDetailStmt = $con->prepare($insertDetailSql);
    if (!$insertDetailStmt) {
        $updateScheduleStmt->close();
        $updatePaymentStmt->close();
        return false;
    }

    $deleteDetailStmt = $con->prepare("DELETE FROM lease_payments_detail WHERE payment_id = ?");
    if (!$deleteDetailStmt) {
        $insertDetailStmt->close();
        $updateScheduleStmt->close();
        $updatePaymentStmt->close();
        return false;
    }

    foreach ($payments as $pay) {
        $paymentId = intval($pay['payment_id']);
        $amount = floatval($pay['amount'] ?? 0);
        $paymentDate = $pay['payment_date'];

        if ($amount <= 0) {
            continue;
        }

        $allocation = allocateLeasePayment($scheduleState, $paymentDate, $amount, $discountRate);
        $allocations = $allocation['allocations'];
        $totals = $allocation['totals'];
        $currentScheduleId = $allocation['current_schedule_id'];
        $remainingAfter = $allocation['remaining'];

        if ($remainingAfter > 0.01) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        if (empty($allocations)) {
            $scheduleState = $allocation['schedules'];
            continue;
        }

        $totalActual = $totals['rent'] + $totals['penalty'] + $totals['premium'];
        if (abs($totalActual - $amount) > 0.01) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
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
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        $deleteDetailStmt->bind_param('i', $paymentId);
        $deleteDetailStmt->execute();

        foreach ($allocations as $sid => $alloc) {
            $scheduleId = intval($sid);
            $rentInc = $alloc['rent'];
            $penInc = $alloc['penalty'];
            $premInc = $alloc['premium'];
            $discInc = $alloc['discount'];
            $curYearInc = $alloc['current_year_payment'];
            $totalPaidSchedule = $alloc['total_paid'];

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
                $deleteDetailStmt->close();
                $insertDetailStmt->close();
                $updateScheduleStmt->close();
                $updatePaymentStmt->close();
                return false;
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
                    $deleteDetailStmt->close();
                    $insertDetailStmt->close();
                    $updateScheduleStmt->close();
                    $updatePaymentStmt->close();
                    return false;
                }
            }
        }

        $scheduleState = $allocation['schedules'];
    }

    $deleteDetailStmt->close();
    $insertDetailStmt->close();
    $updateScheduleStmt->close();
    $updatePaymentStmt->close();

    return true;
}
 
 function generateLeaseSchedules(
    $con, 
    $lease_id, 
    $initial_rent, 
    $premium, 
    $revision_period, 
    $revision_percentage, 
    $start_date, 
    $duration_years = 30
){
    $start_ts = strtotime($start_date);
    if (!$start_ts) return false;

    $boundary_ts = strtotime('2020-01-01');
    $start_year  = (int)date('Y', $start_ts);
    $duration    = (int)$duration_years;
    if ($duration <= 0) return false;

    // ------------------------------------
    // PRE-2020 RULE APPLIES ONLY IF revision_period > 0
    // ------------------------------------
    $use_pre_rules = ($start_ts < $boundary_ts && $revision_period > 0);

    $pre_period_years = 5;
    $pre_pct          = 50.0;

    $post_period_years = ($revision_period > 0) ? (int)$revision_period : 0;
    $post_pct          = (float)$revision_percentage;

    // Determine FIRST revision date
    if ($use_pre_rules) {
        $next_rev_ts = strtotime("+{$pre_period_years} years", $start_ts);
    } else {
        $next_rev_ts = ($post_period_years > 0)
            ? strtotime("+{$post_period_years} years", $start_ts)
            : null;
    }

    // ------------------------------------
    // Load existing schedules
    // ------------------------------------
    $existing = [];
    $sqlEx = "SELECT schedule_id FROM lease_schedules WHERE lease_id=? ORDER BY schedule_year ASC";
    if ($stEx = mysqli_prepare($con, $sqlEx)) {
        mysqli_stmt_bind_param($stEx, 'i', $lease_id);
        mysqli_stmt_execute($stEx);
        $rsEx = mysqli_stmt_get_result($stEx);
        while ($row = mysqli_fetch_assoc($rsEx)) {
            $existing[] = $row;
        }
        mysqli_stmt_close($stEx);
    }
    $existingCount = count($existing);

    // ------------------------------------d
    // Generate Yearly Schedules
    // ------------------------------------
    $current_rent    = (float)$initial_rent;
    $revision_number = 0;



// Lease Shedule  generate each year

//test temp table 

$lease_id = (int)$lease_id; // safety cast

$sqlDel = "DELETE FROM lease_schedules_temp WHERE lease_id = $lease_id";
mysqli_query($con, $sqlDel);




    for ($year = 0; $year < $duration; $year++) {

        $year_start_ts = strtotime("+{$year} years", $start_ts);
        $year_end_ts   = strtotime("+1 year -1 day", $year_start_ts);
        $schedule_year   = (int)date('Y', $year_start_ts);
        $year_start_date = date('Y-m-d', $year_start_ts);
        $year_end_date   = date('Y-m-d', $year_end_ts);
        $due_date        = date('Y-m-d', strtotime($schedule_year . "-03-31"));

        
        $is_revision_year = 0;

        // ------------------------------------------------
        // REVISION LOGIC (Pre + Post)
        // ------------------------------------------------
        if ($next_rev_ts && $year_start_ts >= $next_rev_ts) {

            $is_revision_year = 1;
            $revision_number++;

            // Determine which rule applies
            $apply_pre_rule = ($use_pre_rules && $next_rev_ts < $boundary_ts);
            $pct_to_apply   = $apply_pre_rule ? $pre_pct : $post_pct;

            if ($pct_to_apply > 0) {
                $current_rent = $current_rent * (1 + ($pct_to_apply / 100.0));
            }

            // Compute next revision point
            if ($apply_pre_rule) {

                // Continue 5-year rule until 2020
                $candidate = strtotime("+{$pre_period_years} years", $next_rev_ts);

                if ($candidate < $boundary_ts) {
                    $next_rev_ts = $candidate;
                } else {
                    // Switch to post rules
                    $next_rev_ts = ($post_period_years > 0)
                        ? strtotime("+{$post_period_years} years", $next_rev_ts)
                        : null;
                }

            } else {
                // Pure post-2020 revisions
                $next_rev_ts = ($post_period_years > 0)
                    ? strtotime("+{$post_period_years} years", $next_rev_ts)
                    : null;
            }
        }

        // ------------------------------------
        // Premium (first year only)
        // ------------------------------------
        $first_year_premium = ($year == 0 && $start_ts < $boundary_ts)
            ? $premium
            : 0.0;

        // ------------------------------------
        // UPDATE existing OR INSERT new row
        // ------------------------------------
        if ($year < $existingCount) {

            $schedule_id = $existing[$year]['schedule_id'];

            $sqlUp = "UPDATE lease_schedules SET
                        schedule_year=?, start_date=?, end_date=?, due_date=?,
                        base_amount=?, premium=?, annual_amount=?,
                        revision_number=?, is_revision_year=?
                      WHERE schedule_id=?";

            $stUp = mysqli_prepare($con, $sqlUp);
            mysqli_stmt_bind_param(
                $stUp, 'isssdddiii',
                $schedule_year,
                $year_start_date, $year_end_date, $due_date,
                $initial_rent, $first_year_premium,
                $current_rent,
                $revision_number, $is_revision_year,
                $schedule_id
            );
            mysqli_stmt_execute($stUp);
            mysqli_stmt_close($stUp);

        } else {

            // Insert new schedule row
            $sqlIns = "INSERT INTO lease_schedules (
                        lease_id, schedule_year, start_date, end_date, due_date,
                        base_amount, premium, premium_paid, annual_amount,
                        revision_number, is_revision_year, status, created_on
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', NOW()
                    )";

            $stIns = mysqli_prepare($con, $sqlIns);
            mysqli_stmt_bind_param(
                $stIns, 'iisssdddii',
                $lease_id, $schedule_year,
                $year_start_date, $year_end_date, $due_date,
                $initial_rent, $first_year_premium,
                $current_rent,
                $revision_number, $is_revision_year
            );
            mysqli_stmt_execute($stIns);
            mysqli_stmt_close($stIns);
        }
    }

    return true;
}

 