<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

$lease_id = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
$type      = isset($_POST['reminders_type']) ? trim($_POST['reminders_type']) : '';
$sent_date = isset($_POST['sent_date']) ? trim($_POST['sent_date']) : '';
$allowed   = ['Recovery Letter','Annexure 09','Annexure 12A','Annexure 12'];
$created_by = isset($user_id) ? (int)$user_id : 0; // from auth.php

//ben_id fetch
$ben_id = null; 
if ($stmtL = mysqli_prepare($con, 'SELECT beneficiary_id FROM leases WHERE lease_id=? LIMIT 1')) {
  mysqli_stmt_bind_param($stmtL, 'i', $lease_id);
  mysqli_stmt_execute($stmtL);
  $resL = mysqli_stmt_get_result($stmtL);
  if ($resL && ($rowL = mysqli_fetch_assoc($resL))) {
      $ben_id = isset($rowL['beneficiary_id']) ? (int)$rowL['beneficiary_id'] : null;
  }
  mysqli_stmt_close($stmtL);
}

if ($lease_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid lease']); exit; }
if ($type === '' || !in_array($type,$allowed,true)) { echo json_encode(['success'=>false,'message'=>'Select valid reminder type']); exit; }
if ($sent_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$sent_date)) { echo json_encode(['success'=>false,'message'=>'Invalid date']); exit; }

// Insert with created_by & created_on
$sql = 'INSERT INTO ltl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) VALUES (?,?,?,?,?,NOW())';
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st,'issii',$lease_id,$type,$sent_date,$dummyStatus,$created_by);
} else {
  // Fallback if including status placeholder fails (e.g. different schema) use inline status value
  $sql = 'INSERT INTO ltl_reminders (lease_id, reminders_type, sent_date, status, created_by, created_on) VALUES (?,?,?,?,?,NOW())';
  $st = mysqli_prepare($con, $sql);
}

// Adjust binding: if previous prepare used inline status (1) question marks count differs.
// Determine number of placeholders quickly
$placeholders = substr_count($sql,'?');
if ($st) {
  if ($placeholders === 5) {
    // lease_id, reminders_type, sent_date, status, created_by
    $status = 1;
    mysqli_stmt_bind_param($st,'issii',$lease_id,$type,$sent_date,$status,$created_by);
  } elseif ($placeholders === 4) {
    // lease_id, reminders_type, sent_date, created_by (status fixed inline to 1)
    mysqli_stmt_bind_param($st,'issi',$lease_id,$type,$sent_date,$created_by);
  } else {
    echo json_encode(['success'=>false,'message'=>'Unexpected statement structure']);
    exit;
  }
  if (mysqli_stmt_execute($st)) {
    if (function_exists('UserLog')) {
      $detail = sprintf('Added reminder: lease_id=%d | type=%s | sent_date=%s ', $lease_id, $type, $sent_date);
      UserLog('2','LTL Add Reminders',$detail,$ben_id);
    }
    echo json_encode(['success'=>true]);
  } else {
    echo json_encode(['success'=>false,'message'=>'DB insert failed']);
  }
  mysqli_stmt_close($st);
} else {
  echo json_encode(['success'=>false,'message'=>'Prepare failed']);
}
