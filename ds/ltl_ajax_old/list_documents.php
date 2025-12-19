<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

function json_out($ok, $data = null, $msg = ''){
  echo json_encode(['success'=>$ok, 'data'=>$data, 'message'=>$msg]);
  exit;
}

// Resolve ben_id from id (md5) or ben_id
$ben_id = null;
if (isset($_GET['ben_id']) && ctype_digit($_GET['ben_id'])) {
  $ben_id = (int) $_GET['ben_id'];
} else if (!empty($_GET['id'])) {
  $id = $_GET['id'];
  if ($stmt = mysqli_prepare($con, 'SELECT ben_id FROM beneficiaries WHERE md5_ben_id = ? LIMIT 1')){
    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) { $ben_id = (int)$row['ben_id']; }
    mysqli_stmt_close($stmt);
  }
}
if (!$ben_id) json_out(false, null, 'Invalid beneficiary');

// Fetch document types (active)
$types = [];
$sqlT = 'SELECT doc_type_id, doc_name, doc_group, order_no, approval_required, status, print_url FROM ltl_land_document_type WHERE status=1 ORDER BY order_no ASC, doc_name ASC';
$resT = mysqli_query($con, $sqlT);
if ($resT) {
  while($t = mysqli_fetch_assoc($resT)) { $types[] = $t; }
}

// Fetch existing files for this beneficiary (active)
$files = [];
if ($stmtF = mysqli_prepare($con, 'SELECT id, ben_id, file_type, file_url, status, submitted_date, received_date, referance_no, approval_status FROM ltl_land_files WHERE status=1 AND ben_id=?')){
  mysqli_stmt_bind_param($stmtF, 'i', $ben_id);
  mysqli_stmt_execute($stmtF);
  $resF = mysqli_stmt_get_result($stmtF);
  if ($resF){
    while($f = mysqli_fetch_assoc($resF)){
      $files[$f['file_type']] = $f; // file_type acts as doc_type_id mapping
    }
  }
  mysqli_stmt_close($stmtF);
}

// If only one doc requested
if (!empty($_GET['_one'])){
  $one = $_GET['_one'];
  $out = [];
  foreach($types as $t){
    if ((string)$t['doc_type_id'] === (string)$one){
      $out[] = [
        'doc_type_id' => $t['doc_type_id'],
        'doc_name' => $t['doc_name'],
        'doc_group' => $t['doc_group'],
        'order_no' => (int)$t['order_no'],
        'approval_required' => (int)$t['approval_required'],
        'file' => isset($files[$t['doc_type_id']]) ? $files[$t['doc_type_id']] : null,
      ];
      break;
    }
  }
  json_out(true, $out);
}

// Merge for full list
$out = [];
foreach($types as $t){
  $out[] = [
    'doc_type_id' => $t['doc_type_id'],
    'doc_name' => $t['doc_name'],
    'doc_group' => $t['doc_group'],
    'order_no' => (int)$t['order_no'],
    'approval_required' => (int)$t['approval_required'],
    'print_url' => $t['print_url'],
    'file' => isset($files[$t['doc_type_id']]) ? $files[$t['doc_type_id']] : null,
  ];
}

json_out(true, $out);
