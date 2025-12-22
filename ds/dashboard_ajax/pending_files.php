<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Colombo');

$res = [
  'success' => false,
  'location_id' => null,
  'pending_count' => 0,
  'message' => ''
];

try {
  $locParam = null;
  if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
  }
  $res['location_id'] = $locParam;

$filters = [];
  $types = '';
  $params = [];
  if ($locParam !== null) {
    $filters[] = 'b.location_id = ?';
    $types .= 'i';
    $params[] = $locParam;
  }
$whereParts = $filters;
$whereParts[] = "(l.lease_id IS NULL OR l.file_number IS NULL OR l.file_number = '' OR l.file_number = 'Pending')";
$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

// Latest lease per beneficiary (or none), count where file number missing/pending
$sql = "SELECT COUNT(*) AS pending_cnt
        FROM beneficiaries b
        LEFT JOIN (
          SELECT l2.beneficiary_id, l2.file_number, l2.lease_id
          FROM leases l2
          INNER JOIN (
            SELECT beneficiary_id, MAX(lease_id) AS max_id
            FROM leases
            GROUP BY beneficiary_id
          ) lm ON lm.beneficiary_id = l2.beneficiary_id AND lm.max_id = l2.lease_id
        ) l ON l.beneficiary_id = b.ben_id
        $whereSql";

if ($stmt = mysqli_prepare($con, $sql)) {
  if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }
  if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_bind_result($stmt, $pendingCnt);
    mysqli_stmt_fetch($stmt);
    $res['pending_count'] = (int)$pendingCnt;
    $res['success'] = true;
    $res['message'] = 'OK';
  } else {
    $res['message'] = 'Execution failed';
  }
  mysqli_stmt_close($stmt);
} else {
  $res['message'] = 'Preparation failed';
}

} catch (Throwable $e) {
  $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res);
