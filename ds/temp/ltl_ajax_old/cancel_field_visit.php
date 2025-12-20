<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0){ echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }

 
$ben_id = null;
$lease_id = null;
if ($q = mysqli_prepare($con, 'SELECT v.lease_id,v.date,v.officers_visited,v.visite_status,l.beneficiary_id FROM ltl_feald_visits v 
LEFT JOIN leases l ON v.lease_id = l.lease_id WHERE v.id = ? LIMIT 1')) {
  mysqli_stmt_bind_param($q, 'i', $id);
  mysqli_stmt_execute($q);
  $res = mysqli_stmt_get_result($q);
  if ($res && ($row = mysqli_fetch_assoc($res))) {
    $ben_id   = isset($row['beneficiary_id'])   ? (int)$row['beneficiary_id']   : null;
    $date     = isset($row['date'])            ? $row['date'] : '';
    $officers = isset($row['officers_visited']) ? $row['officers_visited'] : '';
    $vstatus  = isset($row['visite_status'])    ? $row['visite_status']    : '';

  }
  mysqli_stmt_close($q);
}


if ($st = mysqli_prepare($con, 'UPDATE ltl_feald_visits SET status=0 WHERE id=?')){
  mysqli_stmt_bind_param($st,'i',$id);
  if (mysqli_stmt_execute($st)){
    if (function_exists('UserLog')) {
        $detail = 'Cancelled field visit: id=' . (int)$id . 
                  ' | date=' . $date . 
                  ' | officers=' . $officers . 
                  ' | status=' . $vstatus;
        UserLog(2,'LTL Cancel Field Visits', $detail,$ben_id);
    }
    echo json_encode(['success'=>true,'message'=>'Cancelled']);
  } else {
    echo json_encode(['success'=>false,'message'=>$con->error]);
  }
  mysqli_stmt_close($st);
} else {
  echo json_encode(['success'=>false,'message'=>$con->error]);
}
