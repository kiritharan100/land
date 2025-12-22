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

  // Same remind date expression used in long_term_lease.php
  $remindExpr = "DATE(
    DATE_ADD(
        MAKEDATE(YEAR(CURDATE()), 1)
        + INTERVAL (MONTH(l.start_date) - 1) MONTH
        + INTERVAL (DAY(l.start_date) - 1) DAY
        - INTERVAL 1 MONTH,
        INTERVAL (YEAR(CURDATE()) - YEAR(
            MAKEDATE(YEAR(CURDATE()), 1)
            + INTERVAL (MONTH(l.start_date) - 1) MONTH
            + INTERVAL (DAY(l.start_date) - 1) DAY
            - INTERVAL 1 MONTH
        )) YEAR
    )
  )";

  $filters = ['l.lease_id IS NOT NULL'];
  $types = '';
  $params = [];
  if ($locParam !== null) {
    $filters[] = 'b.location_id = ?';
    $types .= 'i';
    $params[] = $locParam;
  }
  $whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

  $sql = "SELECT COUNT(*) AS pending_cnt
          FROM (
            SELECT 
              {$remindExpr} AS remind_date,
              (
                SELECT COUNT(*)
                FROM ltl_reminders lr
                WHERE lr.lease_id = l.lease_id
                  AND lr.reminders_type = 'Annexure 09'
                  AND lr.status = 1
                  AND lr.sent_date BETWEEN DATE_SUB({$remindExpr}, INTERVAL 45 DAY)
                      AND STR_TO_DATE(CONCAT(YEAR({$remindExpr}), '-12-31'), '%Y-%m-%d')
              ) AS annexure09_sent
            FROM beneficiaries b
            LEFT JOIN (
              SELECT l2.beneficiary_id, l2.start_date, l2.lease_id
              FROM leases l2
              INNER JOIN (
                SELECT beneficiary_id, MAX(lease_id) AS max_id
                FROM leases
                GROUP BY beneficiary_id
              ) lm ON lm.beneficiary_id = l2.beneficiary_id AND lm.max_id = l2.lease_id
            ) l ON l.beneficiary_id = b.ben_id
            $whereSql
          ) x
          WHERE x.annexure09_sent = 0
            AND x.remind_date <= CURDATE()";

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
