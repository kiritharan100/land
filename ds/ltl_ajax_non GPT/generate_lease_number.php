<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

try {
  $location_id = 0; $client_name = '';
  if (isset($_COOKIE['client_cook']) && $_COOKIE['client_cook']){
    $md5 = $con->real_escape_string($_COOKIE['client_cook']);
    $res = $con->query("SELECT c_id, client_name FROM client_registration WHERE md5_client='$md5' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) { $location_id = (int)$row['c_id']; $client_name = $row['client_name']; }
  }
  $clean = preg_replace('/[^A-Za-z]/', '', strtoupper($client_name));
  $prefix = substr($clean . 'XX', 0, 2);
  if ($prefix === '') $prefix = 'NA';
  $year = date('Y');

  // Determine next sequence: take max trailing numeric segment among existing numbers for this location and year
  $maxSeq = 0;
  if ($location_id) {
    // Prefer file_number; ensure trailing numeric by regex (continuous across all years)
    if ($stmt1 = $con->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(file_number, '/', -1) AS UNSIGNED)) AS mx FROM leases WHERE location_id=? AND file_number REGEXP '/[0-9]+$'")){
      $stmt1->bind_param('i', $location_id);
      $stmt1->execute();
      $res1 = $stmt1->get_result();
      if ($res1 && ($row1 = $res1->fetch_assoc())) { $maxSeq = max($maxSeq, (int)$row1['mx']); }
      $stmt1->close();
    }
    // Also consider lease_number for legacy rows
    if ($stmt2 = $con->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(lease_number, '/', -1) AS UNSIGNED)) AS mx FROM leases WHERE location_id=? AND lease_number REGEXP '/[0-9]+$'")){
      $stmt2->bind_param('i', $location_id);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      if ($res2 && ($row2 = $res2->fetch_assoc())) { $maxSeq = max($maxSeq, (int)$row2['mx']); }
      $stmt2->close();
    }
  }
  $next = $maxSeq + 1; $number = str_pad((string)$next, 3, '0', STR_PAD_LEFT);

  $file_number  = "DS/{$prefix}/LND/{$year}/{$number}"; // JS will inject LS+type before year
  $lease_number = "DS/{$prefix}/{$year}/{$number}";
  echo json_encode(['success'=>true, 'lease_number'=>$lease_number, 'file_number'=>$file_number]);
} catch (Exception $e){
  echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
