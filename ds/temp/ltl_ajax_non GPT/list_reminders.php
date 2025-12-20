<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
$lease_id = isset($_GET['lease_id']) ? (int)$_GET['lease_id'] : 0;
if ($lease_id <= 0) {
  echo '<tr><td colspan="5" class="text-danger text-center">Invalid lease.</td></tr>';
  exit;
}
$sql = 'SELECT id, lease_id, reminders_type, sent_date, status FROM ltl_reminders WHERE lease_id=? ORDER BY sent_date DESC';
$sql = 'SELECT id, lease_id, reminders_type, sent_date, status, created_by, created_on FROM ltl_reminders WHERE lease_id=? ORDER BY sent_date DESC';
if ($st = mysqli_prepare($con,$sql)) {
  mysqli_stmt_bind_param($st,'i',$lease_id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $sn = 1;
  if ($rs && mysqli_num_rows($rs)>0) {
    while($row = mysqli_fetch_assoc($rs)) {
      $cancelled = ((int)$row['status'] === 0);
      echo '<tr class="'.($cancelled?'rem-row-cancelled':'').'">';
      echo '<td>'.($sn++).'</td>';
      echo '<td>'.htmlspecialchars($row['sent_date']).'</td>';
      echo '<td>'.htmlspecialchars($row['reminders_type']).'</td>';
      echo '<td>' . ($cancelled ? '<span class="badge badge-danger">Cancelled</span>' : '<span class="badge badge-success">Active</span>') . '</td>';
      echo '<td>';
      if (!$cancelled) {
        echo '<button class="btn btn-outline-danger btn-sm rem-cancel-btn" data-id="'.(int)$row['id'].'"><i class="fa fa-times"></i> Cancel</button>';
      } else {
        echo '-';
      }
      echo '</td>';
      echo '</tr>';
    }
  } else {
    echo '<tr><td colspan="5" class="text-center">No reminders found.</td></tr>';
  }
  mysqli_stmt_close($st);
} else {
  echo '<tr><td colspan="5" class="text-danger text-center">Query failed.</td></tr>';
}
