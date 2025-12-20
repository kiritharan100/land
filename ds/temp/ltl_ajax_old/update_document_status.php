<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';

function json_out($ok, $data = null, $msg = ''){
  echo json_encode(['success'=>$ok, 'data'=>$data, 'message'=>$msg]);
  exit;
}

$id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0; // ltl_land_files.id
$doc_type_id = isset($_POST['doc_type_id']) ? trim($_POST['doc_type_id']) : '';
$approval_status = isset($_POST['approval_status']) ? trim($_POST['approval_status']) : '';
if ($doc_type_id === '') json_out(false, null, 'Missing document type');

// If this doc requires approval and status empty, default to Pending
if ($doc_type_id !== ''){
  if ($stmtT = mysqli_prepare($con, 'SELECT approval_required FROM ltl_land_document_type WHERE doc_type_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmtT, 's', $doc_type_id);
    mysqli_stmt_execute($stmtT);
    $resT = mysqli_stmt_get_result($stmtT);
    if ($resT && ($rowT = mysqli_fetch_assoc($resT))) {
      if ((int)$rowT['approval_required'] === 1 && $approval_status === '') { $approval_status = 'Pending'; }
      if ((int)$rowT['approval_required'] === 0 && $approval_status === '') { $approval_status = ''; }
    }
    mysqli_stmt_close($stmtT);
  }
}

// If $id == 0, try to resolve by ben md5 and create if needed
if ($id === 0) {
  $md5 = isset($_POST['md5']) ? $_POST['md5'] : '';
  $ben_id = 0;
  if ($md5 !== ''){
    if ($stmtB = mysqli_prepare($con, 'SELECT ben_id FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')){
      mysqli_stmt_bind_param($stmtB, 's', $md5);
      mysqli_stmt_execute($stmtB);
      $resB = mysqli_stmt_get_result($stmtB);
      if ($resB && ($rowB = mysqli_fetch_assoc($resB))) { $ben_id = (int)$rowB['ben_id']; }
      mysqli_stmt_close($stmtB);
    }
  }
  if ($ben_id > 0){
    // Find existing active record
    if ($stmtE = mysqli_prepare($con, 'SELECT id FROM ltl_land_files WHERE ben_id=? AND file_type=? AND status=1 LIMIT 1')){
      mysqli_stmt_bind_param($stmtE, 'is', $ben_id, $doc_type_id);
      mysqli_stmt_execute($stmtE);
      $resE = mysqli_stmt_get_result($stmtE);
      if ($resE && ($rowE = mysqli_fetch_assoc($resE))) { $id = (int)$rowE['id']; }
      mysqli_stmt_close($stmtE);
    }
    if ($id === 0){
      // Insert placeholder row with empty file_url (not NULL)
      if ($stmtI = mysqli_prepare($con, "INSERT INTO ltl_land_files (ben_id, file_type, file_url, status, approval_status) VALUES (?, ?, '', 1, ?)")){
        mysqli_stmt_bind_param($stmtI, 'iss', $ben_id, $doc_type_id, $approval_status);
        $ok = mysqli_stmt_execute($stmtI);
        $newId = $ok ? mysqli_insert_id($con) : 0;
        $err = mysqli_error($con);
        mysqli_stmt_close($stmtI);
        if ($ok && $newId > 0) { json_out(true, ['id'=>$newId]); } else { json_out(false, null, 'Insert failed: ' . $err); }
      }
      json_out(false, null, 'DB error');
    }
  } else {
    json_out(false, null, 'Invalid beneficiary');
  }
}

if ($stmt = mysqli_prepare($con, 'UPDATE ltl_land_files SET approval_status=? WHERE id=?')){
  mysqli_stmt_bind_param($stmt, 'si', $approval_status, $id);
  $ok = mysqli_stmt_execute($stmt);
  $err = mysqli_error($con);
  mysqli_stmt_close($stmt);
  if ($ok) { json_out(true, ['id'=>$id]); } else { json_out(false, null, 'Update failed: ' . $err); }
}

json_out(false, null, 'DB error');
