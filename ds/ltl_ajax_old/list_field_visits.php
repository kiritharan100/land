<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

$lease_id = isset($_GET['lease_id']) ? (int)$_GET['lease_id'] : 0;
if ($lease_id <= 0){ echo '<tr><td colspan="5" class="text-center text-danger">Invalid lease</td></tr>'; exit; }

$rows = [];
if ($st = mysqli_prepare($con, 'SELECT id, lease_id, `date`, officers_visited, visite_status, status FROM ltl_feald_visits WHERE lease_id=? ORDER BY `date` DESC, id ASC')){
  mysqli_stmt_bind_param($st,'i',$lease_id);
  mysqli_stmt_execute($st);
  $res = mysqli_stmt_get_result($st);
  if ($res){ while($r = mysqli_fetch_assoc($res)) $rows[] = $r; }
  mysqli_stmt_close($st);
}

if (!$rows){ echo '<tr><td colspan="5" class="text-center">Loading...</td></tr>'; exit; }

$i = 1;
foreach ($rows as $r){
  $isCancelled = (string)($r['status'] ?? '1') === '0';
  echo '<tr class="'.($isCancelled?'fv-row-cancelled':'').'">';
  echo '<td>'.($i++).'</td>';
  echo '<td>'.htmlspecialchars($r['date']).'</td>';
  echo '<td>'.htmlspecialchars($r['officers_visited']).'</td>';
  echo '<td>'.htmlspecialchars($r['visite_status']).'</td>';
  echo '<td>';
  if ($isCancelled){
    echo '<span class="badge-cancelled">Cancelled</span>';
  } else {
    echo '<button type="button" class="btn btn-outline-danger btn-sm fv-cancel-btn" data-id="'.(int)$r['id'].'"><i class="fa fa-times"></i> Cancel</button>';
  }
  echo '</td>';
  echo '</tr>';
}
