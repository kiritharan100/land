<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

$out = ['success' => false, 'message' => ''];
try {
    if (!isset($_SESSION['permissions']) || !in_array(8, $_SESSION['permissions'])) {
        throw new Exception('Permission denied');
    }
    $lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $amount_raw = $_POST['amount'] ?? '';
    if ($lease_id <= 0 || $schedule_id <= 0) throw new Exception('Invalid identifiers');
    if ($amount_raw === '' || !is_numeric($amount_raw)) throw new Exception('Invalid amount');
    $amount = (float)$amount_raw;
    if ($amount < 0) throw new Exception('Amount must be >= 0');

    $ben_id = null;
    //get ben_id using lease.lease_id
    if ($stmtL = mysqli_prepare($con, 'SELECT beneficiary_id FROM leases WHERE lease_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmtL, 'i', $lease_id);
        mysqli_stmt_execute($stmtL);
        $resL = mysqli_stmt_get_result($stmtL);
        if ($resL && ($rowL = mysqli_fetch_assoc($resL))) {
            $ben_id = isset($rowL['beneficiary_id']) ? (int)$rowL['beneficiary_id'] : null;
        }
        mysqli_stmt_close($stmtL);
    }

    // Load current penalty figures
    $cur_pen = 0.0; $pen_paid = 0.0;
    if ($st = mysqli_prepare($con, 'SELECT panalty, panalty_paid FROM lease_schedules WHERE schedule_id=? AND lease_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 'ii', $schedule_id, $lease_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $cur_pen = (float)$row['panalty'];
            $pen_paid = (float)$row['panalty_paid'];
        } else {
            mysqli_stmt_close($st);
            throw new Exception('Schedule not found');
        }
        mysqli_stmt_close($st);
    } else {
        throw new Exception('DB error: ' . mysqli_error($con));
    }

    $outstanding = max(0.0, $cur_pen - $pen_paid);
    $apply = min($amount, $outstanding); // do not over-write-off

    if ($apply <= 0) {
        $out['success'] = true; $out['message'] = 'Nothing to write off';
        $out['old_panalty'] = $cur_pen; $out['new_panalty'] = $cur_pen; $out['applied'] = 0.0; $out['outstanding'] = $outstanding;
        echo json_encode($out); return;
    }

    mysqli_begin_transaction($con);
    try {
        // Insert into ltl_write_off
        if ($st1 = mysqli_prepare($con, 'INSERT INTO ltl_write_off (lease_id, schedule_id, write_off_amount, created_by, created_on, status) VALUES (?,?,?,?,NOW(),1)')) {
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            mysqli_stmt_bind_param($st1, 'iidi', $lease_id, $schedule_id, $apply, $uid);
            if (!mysqli_stmt_execute($st1)) { $err = mysqli_error($con); mysqli_stmt_close($st1); throw new Exception('Log insert failed: ' . $err); }
            mysqli_stmt_close($st1);
        } else { throw new Exception('Prepare log failed: ' . mysqli_error($con)); }

        // Update penalty amount by reducing written-off value
        $new_pen = max(0.0, $cur_pen - $apply);
        if ($st2 = mysqli_prepare($con, 'UPDATE lease_schedules SET panalty=?, penalty_updated_by=?, penalty_remarks=? WHERE schedule_id=? AND lease_id=?')) {
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $remark = 'Write-off ' . number_format($apply,2,'.','') . ' on ' . date('Y-m-d H:i');
            mysqli_stmt_bind_param($st2, 'disii', $new_pen, $uid, $remark, $schedule_id, $lease_id);
            if (!mysqli_stmt_execute($st2)) { $err = mysqli_error($con); mysqli_stmt_close($st2); throw new Exception('Update failed: ' . $err); }
            mysqli_stmt_close($st2);
        } else { throw new Exception('Prepare update failed: ' . mysqli_error($con)); }

        mysqli_commit($con);
        //user log
        if (function_exists('UserLog')) {
            $detail = sprintf('Written off penalty: Lease ID=%d, Schedule ID=%d, Amount=%.2f, Old Penalty=%.2f, New Penalty=%.2f',
                $lease_id, $schedule_id, $apply, $cur_pen, $new_pen);
            UserLog(2, 'LTL Write Off Penalty', $detail, $ben_id);
        }


        $out['success'] = true; $out['message'] = 'Penalty written off';
        $out['old_panalty'] = $cur_pen; $out['new_panalty'] = $new_pen; $out['applied'] = $apply; $out['outstanding'] = max(0.0, $new_pen - $pen_paid);
    } catch (Exception $e) {
        mysqli_rollback($con); throw $e;
    }
} catch (Exception $ex) {
    $out['success'] = false; $out['message'] = $ex->getMessage();
}

echo json_encode($out);
