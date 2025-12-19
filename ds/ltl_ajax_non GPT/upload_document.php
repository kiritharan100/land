<?php

 

header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

function json_out($ok, $data = null, $msg = ''){
  echo json_encode(['success'=>$ok, 'data'=>$data, 'message'=>$msg]);
  exit;
}

// Permission: only users with permission id 23 may upload documents
if (!hasPermission(23)){
  json_out(false, null, 'Permission denied: you are not allowed to upload documents.');
}

// Resolve location context from cookie
$selected_client = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
$location_id = '';
if ($selected_client !== ''){
  if ($stmtC = mysqli_prepare($con, 'SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1')){
    mysqli_stmt_bind_param($stmtC, 's', $selected_client);
    mysqli_stmt_execute($stmtC);
    $resC = mysqli_stmt_get_result($stmtC);
    if ($resC && ($rowC = mysqli_fetch_assoc($resC))) { $location_id = $rowC['c_id']; }
    mysqli_stmt_close($stmtC);
  }
}
if ($location_id === '') $location_id = 'unknown';

// Resolve ben_id from id (md5) or ben_id
$ben_id = null;
if (isset($_POST['ben_id']) && ctype_digit((string)$_POST['ben_id'])) {
  $ben_id = (int) $_POST['ben_id'];
}
if (!$ben_id && !empty($_POST['id'])){
  $id = $_POST['id'];
  if ($stmt = mysqli_prepare($con, 'SELECT ben_id FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) { $ben_id = (int)$row['ben_id']; }
    mysqli_stmt_close($stmt);
  }
}
if (!$ben_id) json_out(false, null, 'Invalid beneficiary');

$doc_type_id = isset($_POST['doc_type_id']) ? trim($_POST['doc_type_id']) : '';
if ($doc_type_id === '') json_out(false, null, 'Missing document type');
$approval_status = isset($_POST['approval_status']) && $_POST['approval_status'] !== '' ? $_POST['approval_status'] : null;
// Optional reference number
$referance_no = isset($_POST['referance_no']) ? trim($_POST['referance_no']) : '';
// Determine approval requirement for this doc type
$approval_required = 0;
if ($stmtT = mysqli_prepare($con, 'SELECT approval_required FROM ltl_land_document_type WHERE doc_type_id=? LIMIT 1')){
  mysqli_stmt_bind_param($stmtT, 's', $doc_type_id);
  mysqli_stmt_execute($stmtT);
  $resT = mysqli_stmt_get_result($stmtT);
  if ($resT && ($rowT = mysqli_fetch_assoc($resT))) { $approval_required = (int)$rowT['approval_required']; }
  mysqli_stmt_close($stmtT);
}

// Dates
$submitted_date = isset($_POST['submitted_date']) ? trim($_POST['submitted_date']) : null;
$received_date = isset($_POST['received_date']) ? trim($_POST['received_date']) : null;
if ($approval_required === 1){
  if ($approval_status === null || $approval_status === '') { $approval_status = 'Pending'; }
  // Dates are optional; cast empty strings to NULL
  if ($submitted_date === '' || $submitted_date === false) { $submitted_date = null; }
  if ($received_date === '' || $received_date === false) { $received_date = null; }
} else {
  // If not required, ignore provided meta
  $approval_status = '';
  $submitted_date = null;
  $received_date = null;
}

if (!isset($_FILES['file'])) json_out(false, null, 'No file');
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) json_out(false, null, 'Upload error');

$allowedMime = ['application/pdf','image/png','image/jpeg'];
// $mime = mime_content_type($f['tmp_name']);
// if (!in_array($mime, $allowedMime, true)) json_out(false, null, 'Only PDF, PNG, JPG allowed');

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf','png','jpg','jpeg'], true)) {
    json_out(false, null, 'Only PDF, PNG, JPG allowed');
}





// Get md5_ben_id for filename
$md5_ben_id = '';
if ($stmtB = mysqli_prepare($con, 'SELECT md5_ben_id FROM beneficiaries WHERE ben_id=? LIMIT 1')){
  mysqli_stmt_bind_param($stmtB, 'i', $ben_id);
  mysqli_stmt_execute($stmtB);
  $resB = mysqli_stmt_get_result($stmtB);
  if ($resB && ($rowB = mysqli_fetch_assoc($resB))) { $md5_ben_id = $rowB['md5_ben_id']; }
  mysqli_stmt_close($stmtB);
}
if ($md5_ben_id === '') $md5_ben_id = 'ben'.$ben_id;

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf','png','jpg','jpeg'], true)) json_out(false, null, 'Only PDF, PNG, JPG allowed');

$baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $location_id;
if (!is_dir($baseDir)) { @mkdir($baseDir, 0777, true); }
$ts = date('YmdHis');
$filename = $doc_type_id . '_' . $md5_ben_id . '_' . $ts . '.' . $ext;
$targetPath = $baseDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
  json_out(false, null, 'Failed to save file');
}

$file_url = '/files/' . $location_id . '/' . $filename;
$now = date('Y-m-d H:i:s');

// Upsert into ltl_land_files: try update existing active record for (ben_id, file_type=doc_type_id); else insert
$existing_id = null;
if ($stmtE = mysqli_prepare($con, 'SELECT id FROM ltl_land_files WHERE ben_id=? AND file_type=? AND status=1 LIMIT 1')){
  mysqli_stmt_bind_param($stmtE, 'is', $ben_id, $doc_type_id);
  mysqli_stmt_execute($stmtE);
  $resE = mysqli_stmt_get_result($stmtE);
  if ($resE && ($rowE = mysqli_fetch_assoc($resE))) { $existing_id = (int)$rowE['id']; }
  mysqli_stmt_close($stmtE);
}

if ($existing_id){
  if ($stmtU = mysqli_prepare($con, 'UPDATE ltl_land_files SET file_url=?, approval_status=?, submitted_date=?, received_date=?, referance_no=? WHERE id=?')){
    mysqli_stmt_bind_param($stmtU, 'sssssi', $file_url, $approval_status, $submitted_date, $received_date, $referance_no, $existing_id);
    $ok = mysqli_stmt_execute($stmtU);
    $err = mysqli_error($con);
    mysqli_stmt_close($stmtU);
    if ($ok) {
      UserLog(
        "2", 
        "LTL Document Updated", 
        "Ben ID=$ben_id | File Type=$doc_type_id | File URL=$file_url" 
    );

      json_out(true, ['id'=>$existing_id, 'file_url'=>$file_url]);
    } else {
      json_out(false, null, 'Update failed: ' . $err);
    }
  }
  json_out(false, null, 'DB error');
} else {
  if ($stmtI = mysqli_prepare($con, 'INSERT INTO ltl_land_files (ben_id, file_type, file_url, status, submitted_date, received_date, approval_status, referance_no) VALUES (?, ?, ?, 1, ?, ?, ?, ?)')){
    mysqli_stmt_bind_param($stmtI, 'issssss', $ben_id, $doc_type_id, $file_url, $submitted_date, $received_date, $approval_status, $referance_no);
    $ok = mysqli_stmt_execute($stmtI);
    $newId = $ok ? mysqli_insert_id($con) : 0;
    $err = mysqli_error($con);
    mysqli_stmt_close($stmtI);
    if ($ok && $newId > 0) {

      UserLog(
        "2", 
        "LTL Document Uploaded", 
        "Ben ID=$ben_id | File Type=$doc_type_id | File URL=$file_url",$ben_id
      );

      json_out(true, ['id'=>$newId, 'file_url'=>$file_url]);
    } else {
      json_out(false, null, 'Insert failed: ' . $err);
    }
  }
}

json_out(false, null, 'DB error');