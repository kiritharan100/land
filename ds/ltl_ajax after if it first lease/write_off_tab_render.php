<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$canManage = (isset($_SESSION['permissions']) && in_array(8, $_SESSION['permissions']));

$md5 = $_GET['id'] ?? '';
$lease = null; $error='';
if ($md5 !== '') {
  if ($st = mysqli_prepare($con, 'SELECT ben_id FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st,'s',$md5);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    if ($res && ($ben = mysqli_fetch_assoc($res))) {
      $ben_id = (int)$ben['ben_id'];
      if ($st2 = mysqli_prepare($con,'SELECT land_id FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
        mysqli_stmt_bind_param($st2,'i',$ben_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
          $land_id = (int)$land['land_id'];
          if ($st3 = mysqli_prepare($con,'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')) {
            mysqli_stmt_bind_param($st3,'i',$land_id);
            mysqli_stmt_execute($st3);
            $r3 = mysqli_stmt_get_result($st3);
            if ($r3) { $lease = mysqli_fetch_assoc($r3); }
            mysqli_stmt_close($st3);
          }
          if (!$lease) { $error='No lease found.'; }
        } else { $error='No land found.'; }
        mysqli_stmt_close($st2);
      }
    } else { $error='Invalid beneficiary'; }
    mysqli_stmt_close($st);
  }
} else { $error='Missing id'; }

$rows=[];
if (!$error && $lease) {
  $lease_id = (int)$lease['lease_id'];
  $sql = "SELECT w.id, w.schedule_id, w.write_off_amount, w.created_by, w.created_on, w.status,
                 s.start_date, s.end_date, s.panalty, s.panalty_paid,
                 u.i_name
          FROM ltl_write_off w
          JOIN lease_schedules s ON s.schedule_id=w.schedule_id AND s.lease_id=w.lease_id
          LEFT JOIN user_license u ON u.usr_id=w.created_by
          WHERE w.lease_id=?";
  if (!$canManage) { $sql .= " AND w.status=1"; }
  $sql .= " ORDER BY w.created_on DESC, w.id DESC";
  if ($stW = mysqli_prepare($con,$sql)) {
    mysqli_stmt_bind_param($stW,'i',$lease_id);
    mysqli_stmt_execute($stW);
    $resW = mysqli_stmt_get_result($stW);
    if ($resW) { $rows = mysqli_fetch_all($resW, MYSQLI_ASSOC); }
    mysqli_stmt_close($stW);
  }
}
?>
<div class="table-responsive">
  <?php if ($error): ?>
    <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
  <?php elseif (!$lease): ?>
    <div class="alert alert-info mb-0">Lease context unavailable.</div>
  <?php else: ?>
    <table class="table table-bordered table-sm">
      <thead class="bg-light">
        <tr>
          <th>#</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Penalty (Current)</th>
          <th>Write-Off Amount</th>
          <th>Created On</th>
          <th>Created By</th>
          <th>Status</th>
          <?php if ($canManage): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?= $canManage?9:8 ?>" class="text-center">No write-offs recorded.</td></tr>
        <?php else: $i=1; foreach ($rows as $r):
          $isCancelled = ((int)$r['status']===0);
          $trClass = $isCancelled ? 'table-danger' : '';
          $outstandingPenalty = max(0, (float)$r['panalty'] - (float)$r['panalty_paid']);
        ?>
        <tr class="<?= $trClass ?>">
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($r['start_date']) ?></td>
          <td><?= htmlspecialchars($r['end_date']) ?></td>
          <td class="text-right"><?= number_format((float)$r['panalty'],2) ?></td>
          <td class="text-right"><?= number_format((float)$r['write_off_amount'],2) ?></td>
          <td><?= htmlspecialchars($r['created_on']) ?></td>
          <td><?= htmlspecialchars($r['i_name'] ?? 'User #' . (int)$r['created_by']) ?></td>
          <td><?= $isCancelled ? '<span class="badge bg-danger">Cancelled</span>' : '<span class="badge bg-success">Active</span>' ?></td>
          <?php if ($canManage): ?>
            <td>
              <?php if (!$isCancelled): ?>
                <button class="btn btn-sm btn-outline-danger wo-cancel-btn"
                        data-id="<?= (int)$r['id'] ?>"
                        data-schedule-id="<?= (int)$r['schedule_id'] ?>"
                        data-lease-id="<?= (int)$lease['lease_id'] ?>"
                        data-amount="<?= number_format((float)$r['write_off_amount'],2,'.','') ?>">
                  Cancel
                </button>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <?php
    // Premium change history table
    $premiumRows = [];
    if ($lease) {
      $premiumSql = "SELECT pc.id, pc.lease_id, pc.schedule_id, pc.old_amount, pc.new_amount, pc.created_by, pc.record_on, pc.status,
                            s.start_date, s.end_date, u.i_name
                     FROM ltl_premium_change pc
                     JOIN lease_schedules s ON s.schedule_id=pc.schedule_id AND s.lease_id=pc.lease_id
                     LEFT JOIN user_license u ON u.usr_id=pc.created_by
                     WHERE pc.lease_id=?";
      if (!$canManage) { $premiumSql .= " AND pc.status=1"; }
      $premiumSql .= " ORDER BY pc.record_on DESC, pc.id DESC";
      if ($stP = mysqli_prepare($con, $premiumSql)) {
        mysqli_stmt_bind_param($stP, 'i', $lease['lease_id']);
        mysqli_stmt_execute($stP);
        $resP = mysqli_stmt_get_result($stP);
        if ($resP) { $premiumRows = mysqli_fetch_all($resP, MYSQLI_ASSOC); }
        mysqli_stmt_close($stP);
      }
    }
    ?>
    <?php if (!empty($premiumRows)): ?>
    <hr>
    <h5 class="mt-3">Premium Change History</h5>
    <table class="table table-bordered table-sm">
      <thead class="bg-light">
        <tr>
          <th>#</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Old Amount</th>
          <th>New Amount</th>
          <th>Created By</th>
          <th>Record On</th>
          <th>Status</th>
          <?php if ($canManage): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $j=1;
        // Find last active record index
        $lastActiveIdx = null;
        foreach ($premiumRows as $idx => $pr) {
          if ((int)$pr['status'] === 1) { $lastActiveIdx = $lastActiveIdx === null ? $idx : $lastActiveIdx; }
        }
        foreach ($premiumRows as $idx => $pr):
          $isCancelled = ((int)$pr['status']===0);
          $trClass = $isCancelled ? 'table-danger' : '';
        ?>
        <tr class="<?= $trClass ?>">
          <td><?= $j++ ?></td>
          <td><?= htmlspecialchars($pr['start_date']) ?></td>
          <td><?= htmlspecialchars($pr['end_date']) ?></td>
          <td class="text-right"><?= number_format((float)$pr['old_amount'],2) ?></td>
          <td class="text-right"><?= number_format((float)$pr['new_amount'],2) ?></td>
          <td><?= htmlspecialchars($pr['i_name'] ?? 'User #' . (int)$pr['created_by']) ?></td>
          <td><?= htmlspecialchars($pr['record_on']) ?></td>
          <td><?= $isCancelled ? '<span class="badge bg-danger">Cancelled</span>' : '<span class="badge bg-success">Active</span>' ?></td>
          <?php if ($canManage): ?><td>
            <?php if (!$isCancelled && $idx === $lastActiveIdx): ?>
              <button class="btn btn-sm btn-outline-danger pc-cancel-btn"
                      data-id="<?= (int)$pr['id'] ?>"
                      data-schedule-id="<?= (int)$pr['schedule_id'] ?>"
                      data-lease-id="<?= (int)$pr['lease_id'] ?>"
                      data-old="<?= number_format((float)$pr['old_amount'],2,'.','') ?>"
                      data-new="<?= number_format((float)$pr['new_amount'],2,'.','') ?>">
                Cancel
              </button>
            <?php endif; ?>
          </td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script>
// ...existing code...
</script>
