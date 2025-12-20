<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

function json_out($ok, $data = null, $msg = ''){
  echo json_encode(['success'=>$ok, 'data'=>$data, 'message'=>$msg]);
  exit;
}

// Permission check: only users with permission 22 may delete documents
if (!hasPermission(22)){
  json_out(false, null, 'Permission denied: you are not allowed to delete documents.');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) json_out(false, null, 'Invalid id');

if ($stmt = mysqli_prepare($con, 'UPDATE ltl_land_files SET status=0 WHERE id=?')){
  // Before updating DB, attempt to locate and delete the physical file (if present)
  $file_url = null;
  if ($stmtF = mysqli_prepare($con, 'SELECT file_url,ben_id FROM ltl_land_files WHERE id=? LIMIT 1')){
    mysqli_stmt_bind_param($stmtF, 'i', $id);
    mysqli_stmt_execute($stmtF);
    $resF = mysqli_stmt_get_result($stmtF);
    if ($resF && ($rowF = mysqli_fetch_assoc($resF))) { $file_url = $rowF['file_url']; $ben_id = $rowF['ben_id']; }
    mysqli_stmt_close($stmtF);
  }

  $file_deleted = false;
  if (!empty($file_url)){
    $docPath = ltrim($file_url, '/\\');
    $fullPath = realpath(dirname(__DIR__,2) . DIRECTORY_SEPARATOR . $docPath) ?: (dirname(__DIR__,2) . DIRECTORY_SEPARATOR . $docPath);
    $filesRoot = realpath(dirname(__DIR__,2) . DIRECTORY_SEPARATOR . 'files');
    if ($fullPath && $filesRoot && strpos($fullPath, $filesRoot) === 0 && is_file($fullPath)){
      $file_deleted = @unlink($fullPath);
    }
  }

  mysqli_stmt_bind_param($stmt, 'i', $id);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  $msg = 'Document deleted.';
  if (!empty($file_url) && !$file_deleted) $msg .= ' File not found or not deleted.';
 UserLog("2", "LTL Document Deleted", "File ID=$id | URL=$file_url | document Deleted",$ben_id);
  json_out(true, ['id'=>$id, 'file_deleted'=>$file_deleted, 'file_url'=>$file_url], $msg);
}

json_out(false, null, 'DB error');
