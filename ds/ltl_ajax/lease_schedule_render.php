 <?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

// Permission: write-off indicator (perm id 8)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$canWriteOff = (isset($_SESSION['permissions']) && in_array(8, $_SESSION['permissions']));

// Ensure discount column exists
if ($con) {
  $q = mysqli_query($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME='lease_schedules' 
      AND COLUMN_NAME='discount_apply' LIMIT 1");

  if ($q && mysqli_num_rows($q) === 0) {
    @mysqli_query($con, "ALTER TABLE `lease_schedules` 
       ADD COLUMN `discount_apply` DECIMAL(12,2) NOT NULL DEFAULT 0");
  }
}

$md5 = $_GET['id'] ?? '';
$ben = null; $land = null; $lease = null; $error = '';

if ($md5 !== '') {

  if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {

    mysqli_stmt_bind_param($stmt, 's', $md5);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && ($ben = mysqli_fetch_assoc($res))) {

      $ben_id = (int)$ben['ben_id'];

      // Last land record
      if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address 
                                       FROM ltl_land_registration 
                                       WHERE ben_id=? 
                                       ORDER BY land_id DESC LIMIT 1')) {

        mysqli_stmt_bind_param($st2, 'i', $ben_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);

        if ($r2 && ($land = mysqli_fetch_assoc($r2))) {

          $land_id = (int)$land['land_id'];

          // Latest Lease
          if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases 
                                           WHERE land_id=? 
                                           ORDER BY created_on DESC, lease_id DESC LIMIT 1')) {

            mysqli_stmt_bind_param($st3, 'i', $land_id);
            mysqli_stmt_execute($st3);
            $r3 = mysqli_stmt_get_result($st3);

            if ($r3) { $lease = mysqli_fetch_assoc($r3); }

            mysqli_stmt_close($st3);
          }

          if (!$lease) { $error = 'No lease found for this land.'; }

        } else {
          $error = 'No land found. Complete Land Information.';
        }

        mysqli_stmt_close($st2);
      }

    } else {
      $error = 'Invalid beneficiary';
    }

    mysqli_stmt_close($stmt);
  }

} else {
  $error = 'Missing id';
}

?>
 <style>
/* GREEN ROW FOR CURRENT SCHEDULE */
.current-schedule {
    background: #28a745 !important;
    color: white !important;
    font-weight: bold;
}

.current-schedule td {
    color: white !important;
}
 </style>

 <div class="card">
     <div class="card-header d-flex justify-content-between align-items-center">
         <h5 class="card-header-text mb-0">Lease Payment Schedule</h5>
         <div>
             <?php if ($lease): 
           $token = function_exists('encrypt_id') 
                    ? encrypt_id($lease['lease_id']) 
                    : $lease['lease_id']; ?>
             <a class="btn btn-outline-primary btn-sm" href="print_schedule.php?token=<?= urlencode($token) ?>"
                 target="_blank">
                 <i class="fa fa-print"></i> Print Schedule
             </a>
             <button type='button' class='btn btn-info btn-sm' id='ltl-regenerate-penalty-btn'
                 data-lease-id='<?= (int)($lease['lease_id'] ?? 0) ?>'> Regenerate Penalty </button>
             <?php endif; ?>
         </div>
     </div>

     <div class="card-block" style="padding: 1rem;">

         <?php if ($error): ?>
         <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>

         <?php else: ?>

         <div class="row mb-2">
             <div class="col-sm-12">
                 <strong>Lease:</strong> <?= htmlspecialchars($lease['lease_number']) ?> |
                 <strong>Lessee:</strong> <?= htmlspecialchars($ben['name']) ?> |
                 <strong>Land:</strong> <?= htmlspecialchars($land['land_address']) ?><br>
                 <strong>Start:</strong> <?= htmlspecialchars($lease['start_date']) ?> |
                 <strong>End:</strong> <?= htmlspecialchars($lease['end_date']) ?>
                 <?php
            // Compute current year lease schedule start date (earliest schedule start in current year)
            $currentYearStart = '';
            $currentYear = date('Y');
            foreach ($schedules as $sch) {
              if (isset($sch['start_date']) && substr($sch['start_date'],0,4) === $currentYear) {
                if ($currentYearStart === '' || $sch['start_date'] < $currentYearStart) {
                  $currentYearStart = $sch['start_date'];
                }
              }
            }
            if ($currentYearStart !== '') {
              echo '| <strong>Current Year Start:</strong> ' . htmlspecialchars($currentYearStart);
            }
          ?>
             </div>
         </div>

         <?php
      // Load schedules
      $schedules = [];
      if ($stS = mysqli_prepare($con, 'SELECT * FROM lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
        mysqli_stmt_bind_param($stS, 'i', $lease['lease_id']);
        mysqli_stmt_execute($stS);
        $rs = mysqli_stmt_get_result($stS);
        $schedules = mysqli_fetch_all($rs, MYSQLI_ASSOC);
        mysqli_stmt_close($stS);
      }

      $prev_balance_rent = 0;
      $prev_balance_penalty = 0;
      $prev_premium_balance = 0;
      $count = 1;

      $showPremiumCols = (strtotime($lease['start_date']) < strtotime('2020-01-01'));
      $showDiscountCol = false;
      foreach ($schedules as $tmp) {
        if ((float)$tmp['discount_apply'] > 0) $showDiscountCol = true;
      }

      $colspan = 12 + ($showPremiumCols ? 3 : 0) + ($showDiscountCol ? 1 : 0);
      ?>

         <div class="table-responsive">
             <table class="table table-bordered table-sm">
                 <thead class="bg-light">
                     <tr>
                         <th>#</th>
                         <th>Start</th>
                         <th>End</th>

                         <?php if ($showPremiumCols): ?>
                         <th>Premium</th>
                         <th>Premium Paid</th>
                         <th>Premium Bal</th>
                         <?php endif; ?>

                         <th>Annual Lease</th>
                         <th>Paid Rent</th>

                         <?php if ($showDiscountCol): ?>
                         <th>Discount</th>
                         <?php endif; ?>

                         <th>Rent Bal</th>
                         <th>Penalty</th>
                         <th>Penalty Paid</th>
                         <th>Penalty Bal</th>
                         <th>Total Paid</th>
                         <th>Total Outst</th>
                         <th>Status</th>
                     </tr>
                 </thead>

                 <tbody>
                     <?php if (!$schedules): ?>
                     <tr>
                         <td colspan="<?= $colspan ?>" class="text-center">No schedules found</td>
                     </tr>

                     <?php else: foreach ($schedules as $schedule):

          // Calculate running balances
          $paid_rent = (float)$schedule['paid_rent'];
          $annual = (float)$schedule['annual_amount'];
          $discount = (float)$schedule['discount_apply'];

          $effective_due = $annual - $discount;
          $balance_rent = $prev_balance_rent + ($effective_due - $paid_rent);
          $prev_balance_rent = $balance_rent;

          $penalty = (float)$schedule['panalty'];
          $penalty_paid = (float)$schedule['panalty_paid'];

          $balance_penalty = $prev_balance_penalty + ($penalty - $penalty_paid);
          $prev_balance_penalty = $balance_penalty;

          $premium = (float)$schedule['premium'];
          $premium_paid = (float)$schedule['premium_paid'];

          if ($showPremiumCols) {
            $prev_premium_balance += ($premium - $premium_paid);
          }

          $total_payment = $paid_rent + $penalty_paid + ($showPremiumCols ? $premium_paid : 0);
          $total_outstanding = $balance_rent + $balance_penalty + ($showPremiumCols ? $prev_premium_balance : 0);

          // CHECK IF CURRENT SCHEDULE
          $today = date('Y-m-d');
          $isCurrent = ($schedule['start_date'] <= $today && $schedule['end_date'] >= $today);

          if ($isCurrent) {
              $status1 = '<span class="badge" style="background:#006400;color:white;">P</span>';
              $rowClass = 'current-schedule';
          } else {

              // Your original logic
              if ($schedule['end_date'] < $today && $total_outstanding <= 0) {
                  $status1 = '<i class="fa fa-check text-success"></i>';
                  $rowClass = '';
              }
              elseif ($schedule['end_date'] < $today && $total_outstanding > 0) {
                  $status1 = '<span class="badge badge-danger">Overdue</span>';
                  $rowClass = 'table-danger';
              }
              else {
                  $status1 = '<span class="badge badge-warning">Pending</span>';
                  $rowClass = '';
              }
          }
      ?>

                     <tr class="<?= $rowClass ?>">
                         <td class="text-center"><?= $count++ ?></td>

                         <td align='center'><?= htmlspecialchars($schedule['start_date']) ?></td>
                         <td align='center'><?= htmlspecialchars($schedule['end_date']) ?></td>

                         <?php if ($showPremiumCols): ?>
                         <td class="text-right">
                             <?php // Make premium amount clickable for edit when permission 8
          if ($canWriteOff && $premium > 0):
            $schedule_id = (int)($schedule['schedule_id'] ?? 0);
            $lease_id = isset($lease['lease_id']) ? (int)$lease['lease_id'] : 0;
          ?>
                             <span class="premium-edit" data-schedule-id="<?= $schedule_id ?>"
                                 data-lease-id="<?= $lease_id ?>"
                                 data-current-premium="<?= number_format($premium,2,'.','') ?>"
                                 style="cursor:pointer; text-decoration:underline;">
                                 <?= number_format($premium,2) ?>
                             </span>
                             <?php else: ?>
                             <?= number_format($premium,2) ?>
                             <?php endif; ?>
                         </td>
                         <td class="text-right"><?= number_format($premium_paid,2) ?></td>
                         <td class="text-right"><?= number_format($prev_premium_balance,2) ?></td>
                         <?php endif; ?>

                         <td class="text-right"><?= number_format($annual,2) ?></td>
                         <td class="text-right"><?= number_format($paid_rent,2) ?></td>

                         <?php if ($showDiscountCol): ?>
                         <td class="text-right"><?= number_format($discount,2) ?></td>
                         <?php endif; ?>

                         <td class="text-right"><?= number_format($balance_rent,2) ?></td>

                         <td class="text-right">
                             <?php if ($canWriteOff && $penalty > 0): ?>
                             <?php
                                  $schedule_id = (int)($schedule['schedule_id'] ?? 0);
                                  $lease_id = isset($lease['lease_id']) ? (int)$lease['lease_id'] : 0;
                                  $penalty_due = max(0, (float)$penalty - (float)$penalty_paid);
                                  if($penalty_due > 0){
                                ?>

                             <span class="badge writeoff-badge" id="<?= $schedule_id ?>"
                                 data-schedule-id="<?= $schedule_id ?>" data-lease-id="<?= $lease_id ?>"
                                 data-default-amount="<?= number_format($penalty_due, 2, '.', '') ?>"
                                 style="background:#006400;color:white; cursor:pointer;">W
                             </span>



                             <?php } endif; ?>
                             <span class="penalty-amount"
                                 data-schedule-id="<?= (int)($schedule['schedule_id'] ?? 0) ?>"><?= number_format($penalty,2) ?></span>
                         </td>
                         <td class="text-right"><?= number_format($penalty_paid,2) ?></td>
                         <td class="text-right"><?= number_format($balance_penalty,2) ?></td>

                         <td class="text-right"><?= number_format($total_payment,2) ?></td>
                         <td class="text-right"><?= number_format($total_outstanding,2) ?></td>

                         <td class="text-center"><?= $status1 ?></td>
                     </tr>

                     <?php endforeach; endif; ?>
                 </tbody>
             </table>
         </div>

         <?php endif; ?>

     </div>
 </div>

 <script>
// Click handler for Write-off badge; only present when user has permission 8
(function() {
    try {
        document.addEventListener('click', function(ev) {
            var el = ev.target.closest && ev.target.closest('.writeoff-badge');
            if (!el) return;
            var sid = el.getAttribute('data-schedule-id') || el.id || '';
            var lid = el.getAttribute('data-lease-id') || '';
            var defAmt = el.getAttribute('data-default-amount') || '0.00';
            if (typeof Swal !== 'undefined' && Swal && Swal.fire) {
                Swal.fire({
                    icon: 'question',
                    title: 'Write off Penalty?',
                    html: 'Lease ID: <b>' + String(lid) + '</b><br>Schedule ID: <b>' + String(sid) +
                        '</b><br><br>' +
                        '<div style="text-align:left">Amount to write off</div>' +
                        '<input id="swal-writeoff-amount" type="number" step="0.01" min="0" class="swal2-input" style="width: 80%;" value="' +
                        String(defAmt) + '">',
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Submit',
                    cancelButtonText: 'Cancel',
                    preConfirm: function() {
                        var v = document.getElementById('swal-writeoff-amount').value;
                        var num = parseFloat(v);
                        if (!(num >= 0)) {
                            Swal.showValidationMessage('Please enter a valid amount');
                            return false;
                        }
                        return {
                            amount: num
                        };
                    }
                }).then(function(result) {
                    if (result && result.isConfirmed) {
                        var amt = result.value && result.value.amount !== undefined ? result.value
                            .amount : parseFloat(defAmt);
                        fetch('ltl_ajax/write_off_penalty.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    lease_id: lid,
                                    schedule_id: sid,
                                    amount: amt.toFixed(2)
                                }).toString()
                            })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp && resp.success) {
                                    // Update displayed penalty value
                                    var span = document.querySelector(
                                        '.penalty-amount[data-schedule-id="' + sid + '"]');
                                    if (span) {
                                        span.textContent = (resp.new_panalty !== undefined ?
                                            parseFloat(resp.new_panalty).toFixed(2) : (
                                                parseFloat(span.textContent.replace(/,/g,
                                                    '')) - amt).toFixed(2));
                                    }
                                    // Update default amount on badge (new outstanding)
                                    var newOutstanding = resp.outstanding !== undefined ?
                                        parseFloat(resp.outstanding).toFixed(2) : '0.00';
                                    el.setAttribute('data-default-amount', newOutstanding);
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Saved',
                                        text: 'Penalty write-off recorded.'
                                    });
                                    try {
                                        document.dispatchEvent(new CustomEvent(
                                            'ltl:schedule-updated', {
                                                detail: {
                                                    leaseId: lid,
                                                    scheduleId: sid,
                                                    type: 'penalty'
                                                }
                                            }));
                                    } catch (e) {}
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Failed',
                                        text: (resp && resp.message) || 'Update failed'
                                    });
                                }
                            })
                            .catch((e) => Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Network error: ' + (e && e.message ? e.message :
                                    'request failed')
                            }));
                    }
                });
            } else {
                var amt = prompt('Lease ID: ' + String(lid) + '\nSchedule ID: ' + String(sid) +
                    '\nEnter amount to write off:', String(defAmt));
                if (amt !== null) {
                    var num = parseFloat(amt);
                    if (num >= 0) {
                        fetch('ltl_ajax/write_off_penalty.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    lease_id: lid,
                                    schedule_id: sid,
                                    amount: num.toFixed(2)
                                }).toString()
                            })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp && resp.success) {
                                    var span = document.querySelector(
                                        '.penalty-amount[data-schedule-id="' + sid + '"]');
                                    if (span) {
                                        span.textContent = (resp.new_panalty !== undefined ? parseFloat(
                                            resp.new_panalty).toFixed(2) : (parseFloat(span
                                            .textContent.replace(/,/g, '')) - num).toFixed(2));
                                    }
                                    var newOutstanding = resp.outstanding !== undefined ? parseFloat(
                                        resp.outstanding).toFixed(2) : '0.00';
                                    el.setAttribute('data-default-amount', newOutstanding);
                                    alert('Penalty write-off recorded.');
                                    try {
                                        document.dispatchEvent(new CustomEvent('ltl:schedule-updated', {
                                            detail: {
                                                leaseId: lid,
                                                scheduleId: sid,
                                                type: 'penalty'
                                            }
                                        }));
                                    } catch (e) {}
                                } else {
                                    alert('Update failed: ' + ((resp && resp.message) ||
                                        'Unknown error'));
                                }
                            })
                            .catch((e) => alert('Network error: ' + (e && e.message ? e.message :
                                'request failed')));
                    }
                }
            }
        });
    } catch (e) {
        /* silent */
    }
})();
 </script>
 <script>
// Premium edit handler (similar to penalty write-off), gated by permission 8
(function() {
    try {
        document.addEventListener('click', function(ev) {
            var pEl = ev.target.closest && ev.target.closest('.premium-edit');
            if (!pEl) return;
            var sid = pEl.getAttribute('data-schedule-id') || '';
            var lid = pEl.getAttribute('data-lease-id') || '';
            var cur = pEl.getAttribute('data-current-premium') || '0.00';
            if (typeof Swal !== 'undefined' && Swal && Swal.fire) {
                Swal.fire({
                    icon: 'question',
                    title: 'Edit Premium Amount',
                    html: 'Lease ID: <b>' + String(lid) + '</b><br>Schedule ID: <b>' + String(sid) +
                        '</b><br><br>' +
                        '<div style="text-align:left">Enter the premium amount and save which you want to edit</div>' +
                        '<input id="swal-premium-amount" type="number" step="0.01" min="0" class="swal2-input" style="width: 80%;" value="' +
                        String(cur) + '">',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    preConfirm: function() {
                        var v = document.getElementById('swal-premium-amount').value;
                        var num = parseFloat(v);
                        if (!(num >= 0)) {
                            Swal.showValidationMessage('Enter valid premium');
                            return false;
                        }
                        return {
                            amount: num
                        };
                    }
                }).then(function(result) {
                    if (result && result.isConfirmed) {
                        var amt = result.value && result.value.amount !== undefined ? result.value
                            .amount : parseFloat(cur);
                        // Dispatch custom event for future persistence logic
                        // Persist change via AJAX
                        fetch('ltl_ajax/update_premium.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    lease_id: lid,
                                    schedule_id: sid,
                                    amount: amt.toFixed(2)
                                }).toString()
                            })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp && resp.success) {
                                    pEl.textContent = (resp.new_amount !== undefined ?
                                        parseFloat(resp.new_amount).toFixed(2) : amt
                                        .toFixed(2));
                                    pEl.setAttribute('data-current-premium', pEl.textContent);
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Saved',
                                        text: 'Premium updated.'
                                    });
                                    try {
                                        document.dispatchEvent(new CustomEvent(
                                            'ltl:schedule-updated', {
                                                detail: {
                                                    leaseId: lid,
                                                    scheduleId: sid,
                                                    type: 'premium'
                                                }
                                            }));
                                    } catch (e) {}
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Failed',
                                        text: (resp && resp.message) || 'Update failed'
                                    });
                                }
                            })
                            .catch((e) => Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Network error: ' + (e && e.message ? e.message :
                                    'request failed')
                            }));
                    }
                });
            } else {
                var amt = prompt('Lease ID: ' + String(lid) + '\nSchedule ID: ' + String(sid) +
                    '\nEnter premium amount:', String(cur));
                if (amt !== null) {
                    var num = parseFloat(amt);
                    if (num >= 0) {
                        fetch('ltl_ajax/update_premium.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    lease_id: lid,
                                    schedule_id: sid,
                                    amount: num.toFixed(2)
                                }).toString()
                            })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp && resp.success) {
                                    pEl.textContent = (resp.new_amount !== undefined ? parseFloat(resp
                                        .new_amount).toFixed(2) : num.toFixed(2));
                                    pEl.setAttribute('data-current-premium', pEl.textContent);
                                    alert('Premium updated.');
                                    try {
                                        document.dispatchEvent(new CustomEvent('ltl:schedule-updated', {
                                            detail: {
                                                leaseId: lid,
                                                scheduleId: sid,
                                                type: 'premium'
                                            }
                                        }));
                                    } catch (e) {}
                                } else {
                                    alert('Update failed: ' + ((resp && resp.message) ||
                                        'Unknown error'));
                                }
                            })
                            .catch((e) => alert('Network error: ' + (e && e.message ? e.message :
                                'request failed')));
                    }
                }
            }
        });
    } catch (e) {
        /* silent */
    }
})();
 </script>
 <script>
// Regenerate penalty button handler
(function() {
    try {
        var rb = document.getElementById('ltl-regenerate-penalty-btn');
        if (!rb) return;
        rb.addEventListener('click', function() {
            var leaseId = this.getAttribute('data-lease-id') || '';
            if (!leaseId || leaseId === '0') {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Invalid lease id', 'error');
                else alert('Invalid lease id');
                return;
            }
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    title: 'Regenerating penalties',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            }
            fetch('cal_panalty.php?lease_id=' + encodeURIComponent(leaseId))
                .then(function(r) {
                    return r.text();
                })
                .then(function(txt) {
                    if (typeof Swal !== 'undefined' && Swal.close) Swal.close();
                    if (typeof Swal !== 'undefined' && Swal.fire) {
                        Swal.fire('Done', 'Penalties regenerated successfully', 'success').then(
                            function() {
                                try {
                                    document.dispatchEvent(new CustomEvent('ltl:schedule-updated', {
                                        detail: {
                                            leaseId: leaseId,
                                            type: 'penalty-regenerated'
                                        }
                                    }));
                                } catch (e) {}
                                try {
                                    window.dispatchEvent(new Event('ltl:payments-updated'));
                                } catch (e) {}
                            });
                    } else {
                        alert('Penalties regenerated successfully');
                        try {
                            document.dispatchEvent(new CustomEvent('ltl:schedule-updated', {
                                detail: {
                                    leaseId: leaseId,
                                    type: 'penalty-regenerated'
                                }
                            }));
                        } catch (e) {}
                        try {
                            window.dispatchEvent(new Event('ltl:payments-updated'));
                        } catch (e) {}
                    }
                })
                .catch(function() {
                    if (typeof Swal !== 'undefined' && Swal.close) Swal.close();
                    if (typeof Swal !== 'undefined' && Swal.fire) Swal.fire('Error',
                        'Failed to regenerate penalties', 'error');
                    else alert('Failed to regenerate penalties');
                });
        });
    } catch (e) {
        /* silent */
    }
})();
 </script>