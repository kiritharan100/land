<?php
// Endpoint: update_premium.php
// Purpose: Update premium amount for a lease schedule and log the change in ltl_premium_change
// Input (POST): lease_id, schedule_id, amount (new premium)
// Output: JSON { success: bool, message: string, old_amount?: float, new_amount?: float }

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

$response = [ 'success' => false, 'message' => '' ];
try {
    // Permission check (act_id 8 required)
    if (!isset($_SESSION['permissions']) || !in_array(8, $_SESSION['permissions'])) {
        throw new Exception('Permission denied');
    }

    $lease_id    = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $new_amount_raw = $_POST['amount'] ?? '';
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

    if ($lease_id <= 0 || $schedule_id <= 0) {
        throw new Exception('Invalid identifiers');
    }
    if ($new_amount_raw === '' || !is_numeric($new_amount_raw)) {
        throw new Exception('Invalid premium amount');
    }
    $new_amount = (float)$new_amount_raw;
    if ($new_amount < 0) {
        throw new Exception('Premium cannot be negative');
    }

    // Fetch current premium
    $old_amount = null;
    if ($stmt = mysqli_prepare($con, 'SELECT premium FROM lease_schedules WHERE schedule_id=? AND lease_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 'ii', $schedule_id, $lease_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $old_amount = (float)$row['premium'];
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Schedule not found');
        }
        mysqli_stmt_close($stmt);
    } else {
        throw new Exception('DB error: ' . mysqli_error($con));
    }

    if ($old_amount === $new_amount) {
        $response['success'] = true;
        $response['message'] = 'No changes';
        $response['old_amount'] = $old_amount;
        $response['new_amount'] = $new_amount;
        echo json_encode($response);
        return;
    }

    // Start transaction
    mysqli_begin_transaction($con);
    try {
        // Log change
        if ($logStmt = mysqli_prepare($con, 'INSERT INTO ltl_premium_change (lease_id, schedule_id, old_amount, new_amount, created_by, record_on) VALUES (?,?,?,?,?,NOW())')) {
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            mysqli_stmt_bind_param($logStmt, 'iiddi', $lease_id, $schedule_id, $old_amount, $new_amount, $uid);
            if (!mysqli_stmt_execute($logStmt)) {
                $err = mysqli_error($con); mysqli_stmt_close($logStmt); throw new Exception('Log insert failed: ' . $err);
            }
            mysqli_stmt_close($logStmt);
        } else {
            throw new Exception('Prepare log failed: ' . mysqli_error($con));
        }

        // Update premium in lease_schedules
        if ($updStmt = mysqli_prepare($con, 'UPDATE lease_schedules SET premium=? WHERE schedule_id=? AND lease_id=?')) {
            mysqli_stmt_bind_param($updStmt, 'dii', $new_amount, $schedule_id, $lease_id);
            if (!mysqli_stmt_execute($updStmt)) {
                $err = mysqli_error($con); mysqli_stmt_close($updStmt); throw new Exception('Premium update failed: ' . $err);
            }
            mysqli_stmt_close($updStmt);
        } else {
            throw new Exception('Prepare update failed: ' . mysqli_error($con));
        }

        mysqli_commit($con);
    } catch (Exception $inner) {
        mysqli_rollback($con);
        throw $inner;
    }
    // Log the action
    if (function_exists('UserLog')) {
        $detail = sprintf('Premium amount changed: Lease ID=%d, Schedule ID=%d, Old Amount=%.2f, New Amount=%.2f',
            $lease_id, $schedule_id, $old_amount, $new_amount);
        UserLog(2, 'LTL Edit Premium', $detail, $ben_id);
    }

    $response['success'] = true;
    $response['message'] = 'Premium updated';
    $response['old_amount'] = $old_amount;
    $response['new_amount'] = $new_amount;
} catch (Exception $ex) {
    $response['success'] = false;
    $response['message'] = $ex->getMessage();
}

echo json_encode($response);
