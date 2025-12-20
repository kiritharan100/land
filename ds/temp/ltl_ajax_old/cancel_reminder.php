<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }


  $ben_id = null;
  //get ben_id using lease.lease_id from ltl_reminders table
  if ($q = mysqli_prepare($con, 'SELECT r.lease_id,l.beneficiary_id,r.reminders_type,r.sent_date FROM ltl_reminders r 
  LEFT JOIN leases l ON r.lease_id = l.lease_id WHERE r.id =    ? LIMIT 1')) {
    mysqli_stmt_bind_param($q, 'i', $id);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
      $ben_id   = isset($row['beneficiary_id'])   ? (int)$row['beneficiary_id']   : null;
    }
    mysqli_stmt_close($q);
  }
 

if ($st = mysqli_prepare($con,'UPDATE ltl_reminders SET status=0 WHERE id=?')) {
  mysqli_stmt_bind_param($st,'i',$id);
  if (mysqli_stmt_execute($st)) {
    if (function_exists('UserLog')) {
      $detail = sprintf('Cancelled reminder: id=%d | type=%s | sent_date=%s', (int)$id, $row['reminders_type'], $row['sent_date']);
      UserLog(2,'LTL Cancel Reminders', $detail,$ben_id);
       
    }
    echo json_encode(['success'=>true]);
  } else {
    echo json_encode(['success'=>false,'message'=>'Update failed']);
  }
  mysqli_stmt_close($st);
} else {
  echo json_encode(['success'=>false,'message'=>'Prepare failed']);
}
