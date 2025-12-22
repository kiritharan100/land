<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Colombo');

$res = [
  'success' => false,
  'location_id' => null,
  'as_at' => null,
  'rent_component' => 0.0,
  'penalty_component' => 0.0,
  'premium_component' => 0.0,
  'total_outstanding' => 0.0,
  'message' => ''
];

try {
  $locParam = null;
  if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
    $locParam = (int)$_GET['location_id'];
    if ($locParam <= 0) { $locParam = null; }
  }
  $asAt = isset($_GET['as_at']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['as_at']) ? $_GET['as_at'] : date('Y-m-d');
  $leaseType = isset($_GET['lease_type']) ? $_GET['lease_type'] : 'All';
  $res['location_id'] = $locParam;
  $res['as_at'] = $asAt;

  $filters = ["l.status!='cancelled'"];
  $types = '';
  $params = [];
  if ($locParam !== null) { $filters[] = "l.location_id=?"; $types .= 'i'; $params[] = $locParam; }
  if ($leaseType !== 'All') { $filters[] = "l.type_of_project=?"; $types .= 's'; $params[] = $leaseType; }
  $filterSql = $filters ? implode(' AND ', $filters) : '1=1';

  $rentDue = $penaltyDue = $premiumDue = 0.0;
  $rentPaid = $penaltyPaid = $premiumPaid = 0.0;

  // Outstanding based on lease_schedules (same logic as long_term_lease view)
  $dueSql = "SELECT
                COALESCE(SUM(ls.annual_amount - COALESCE(ls.discount_apply,0)),0) AS rent_due,
                COALESCE(SUM(ls.panalty),0) AS penalty_due,
                COALESCE(SUM(ls.premium),0) AS premium_due
             FROM lease_schedules ls
             INNER JOIN leases l ON l.lease_id = ls.lease_id
             WHERE ls.start_date<=? AND $filterSql";
  if ($dueStmt = mysqli_prepare($con, $dueSql)) {
    $bindTypes = 's' . $types;
    $bindValues = array_merge([$asAt], $params);
    mysqli_stmt_bind_param($dueStmt, $bindTypes, ...$bindValues);
    mysqli_stmt_execute($dueStmt);
    mysqli_stmt_bind_result($dueStmt, $rentDue, $penaltyDue, $premiumDue);
    mysqli_stmt_fetch($dueStmt);
    mysqli_stmt_close($dueStmt);
  } else {
    throw new Exception('Unable to prepare outstanding due query');
  }

  $paidSql = "SELECT
                COALESCE(SUM(ls.paid_rent),0) AS rent_paid,
                COALESCE(SUM(ls.panalty_paid),0) AS penalty_paid,
                COALESCE(SUM(ls.premium_paid),0) AS premium_paid
              FROM lease_schedules ls
              INNER JOIN leases l ON l.lease_id = ls.lease_id
              WHERE $filterSql";
  if ($paidStmt = mysqli_prepare($con, $paidSql)) {
    if ($types !== '') { mysqli_stmt_bind_param($paidStmt, $types, ...$params); }
    mysqli_stmt_execute($paidStmt);
    mysqli_stmt_bind_result($paidStmt, $rentPaid, $penaltyPaid, $premiumPaid);
    mysqli_stmt_fetch($paidStmt);
    mysqli_stmt_close($paidStmt);
  } else {
    throw new Exception('Unable to prepare outstanding paid query');
  }

  $rentOutstanding = max(0, $rentDue - $rentPaid);
  $penaltyOutstanding = max(0, $penaltyDue - $penaltyPaid);
  $premiumOutstanding = max(0, $premiumDue - $premiumPaid);

  $res['rent_component'] = round($rentOutstanding, 2);
  $res['penalty_component'] = round($penaltyOutstanding, 2);
  $res['premium_component'] = round($premiumOutstanding, 2);
  $res['total_outstanding'] = round($rentOutstanding + $penaltyOutstanding + $premiumOutstanding, 2);
  $res['success'] = true;
  $res['message'] = 'OK';

} catch (Throwable $e) {
  $res['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($res);
