<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
$out = ['success'=>false,'message'=>''];
try {
  if (!isset($_SESSION['permissions']) || !in_array(8, $_SESSION['permissions'])) {
    throw new Exception('Permission denied');
  }
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($id <= 0) throw new Exception('Invalid ID');

  // Fetch write-off record
  if ($st = mysqli_prepare($con,'SELECT id, lease_id, schedule_id, write_off_amount, status FROM ltl_write_off WHERE id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st,'i',$id);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    if (!$res || !($row = mysqli_fetch_assoc($res))) { mysqli_stmt_close($st); throw new Exception('Write-off not found'); }
    mysqli_stmt_close($st);
  } else { throw new Exception('DB error: ' . mysqli_error($con)); }

  if ((int)$row['status'] === 0) {
    $out['success'] = true; $out['message'] = 'Already cancelled'; echo json_encode($out); return; }

  $lease_id = (int)$row['lease_id'];
  $schedule_id = (int)$row['schedule_id'];
  $amount = (float)$row['write_off_amount'];

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

  mysqli_begin_transaction($con);
  try {
    // Reverse penalty reduction
    if ($st2 = mysqli_prepare($con,'UPDATE lease_schedules SET panalty = panalty + ? WHERE schedule_id=? AND lease_id=?')) {
      mysqli_stmt_bind_param($st2,'dii',$amount,$schedule_id,$lease_id);
      if (!mysqli_stmt_execute($st2)) { $err=mysqli_error($con); mysqli_stmt_close($st2); throw new Exception('Penalty revert failed: ' . $err); }
      mysqli_stmt_close($st2);
    } else { throw new Exception('Prepare revert failed: ' . mysqli_error($con)); }

    // Soft cancel write-off
    if ($st3 = mysqli_prepare($con,'UPDATE ltl_write_off SET status=0 WHERE id=?')) {
      mysqli_stmt_bind_param($st3,'i',$id);
      if (!mysqli_stmt_execute($st3)) { $err=mysqli_error($con); mysqli_stmt_close($st3); throw new Exception('Cancellation failed: ' . $err); }
      mysqli_stmt_close($st3);
    } else { throw new Exception('Prepare cancel failed: ' . mysqli_error($con)); }

    mysqli_commit($con);
    //user log
    if (function_exists('UserLog')) {
        $detail = sprintf('Cancelled write-off: ID=%d, Lease ID=%d, Schedule ID=%d, Amount=%.2f',
            $id, $lease_id, $schedule_id, $amount);
        UserLog(2, 'LTL Cancel Write Off', $detail, $ben_id);
    }   
    $out['success']=true; $out['message']='Cancelled';
  } catch (Exception $e) {
    mysqli_rollback($con); throw $e;
  }

} catch (Exception $ex) {
  $out['success']=false; $out['message']=$ex->getMessage();
}

echo json_encode($out);
