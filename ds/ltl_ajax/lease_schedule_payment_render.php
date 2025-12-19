<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$md5 = $_GET['id'] ?? '';
$ben = null; $land = null; $lease = null; $error = '';

// Resolve beneficiary, land, and lease
if ($md5 !== '') {
    if ($stmt = mysqli_prepare($con, 'SELECT ben_id, name FROM beneficiaries WHERE md5_ben_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $md5);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($ben = mysqli_fetch_assoc($res))) {
            $ben_id = (int)$ben['ben_id'];
            if ($st2 = mysqli_prepare($con, 'SELECT land_id, ben_id, land_address FROM ltl_land_registration WHERE ben_id=? ORDER BY land_id DESC LIMIT 1')) {
                mysqli_stmt_bind_param($st2, 'i', $ben_id);
                mysqli_stmt_execute($st2);
                $r2 = mysqli_stmt_get_result($st2);
                if ($r2 && ($land = mysqli_fetch_assoc($r2))) {
                    $land_id = (int)$land['land_id'];
                    if ($st3 = mysqli_prepare($con, 'SELECT * FROM leases WHERE land_id=? ORDER BY created_on DESC, lease_id DESC LIMIT 1')) {
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

// Load schedules
$schedules = [];
if ($lease && $lease['lease_id']) {
    if ($stS = mysqli_prepare($con, 'SELECT * FROM lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
        mysqli_stmt_bind_param($stS, 'i', $lease['lease_id']);
        mysqli_stmt_execute($stS);
        $rs = mysqli_stmt_get_result($stS);
        $schedules = mysqli_fetch_all($rs, MYSQLI_ASSOC);
        mysqli_stmt_close($stS);
    }
}

// Load all payments (status=1)
$payments = [];
if ($lease && $lease['lease_id']) {
    if ($stP = mysqli_prepare($con, 'SELECT * FROM lease_payments WHERE lease_id=? AND status=1 ORDER BY payment_date, payment_id')) {
        mysqli_stmt_bind_param($stP, 'i', $lease['lease_id']);
        mysqli_stmt_execute($stP);
        $rp = mysqli_stmt_get_result($stP);
        $payments = mysqli_fetch_all($rp, MYSQLI_ASSOC);
        mysqli_stmt_close($stP);
    }
}

// Build a fast lookup of schedules for date assignment
$scheduleById = [];
foreach ($schedules as $sch) {
    $scheduleById[(int)$sch['schedule_id']] = $sch;
}

// Aggregate payments per schedule: prefer schedule_id when present; otherwise assign by date range.
$payTotals = [];
foreach ($payments as $pay) {
    $sid = (int)($pay['schedule_id'] ?? 0);
    $assigned = false;
    if ($sid > 0 && isset($scheduleById[$sid])) {
        $assigned = true;
    } else {
        // assign by payment_date falling within schedule range
        $pdate = $pay['payment_date'];
        foreach ($schedules as $sch) {
            if (!empty($sch['start_date']) && !empty($sch['end_date']) &&
                $pdate >= $sch['start_date'] && $pdate <= $sch['end_date']) {
                $sid = (int)$sch['schedule_id'];
                $assigned = true;
                break;
            }
        }
    }
    if (!$assigned) {
        continue; // can't place payment, skip
    }
    if (!isset($payTotals[$sid])) {
        $payTotals[$sid] = [
            'rent' => 0.0,
            'penalty' => 0.0,
            'premium' => 0.0,
            'discount' => 0.0,
            'count' => 0,
        ];
    }
    $payTotals[$sid]['rent'] += floatval($pay['rent_paid'] ?? 0);
    $payTotals[$sid]['penalty'] += floatval($pay['panalty_paid'] ?? 0);
    $payTotals[$sid]['premium'] += floatval($pay['premium_paid'] ?? 0);
    $payTotals[$sid]['discount'] += floatval($pay['discount_apply'] ?? 0);
    $payTotals[$sid]['count']++;
}

// Determine current schedule for highlighting
$today = date('Y-m-d');
$currentScheduleId = null;
foreach ($schedules as $sch) {
    if ($sch['start_date'] <= $today && $sch['end_date'] >= $today) {
        $currentScheduleId = (int)$sch['schedule_id'];
        break;
    }
}
?>
<style>
.current-schedule-row { background: #8DF78D !important; }
.old-schedule-row { background: #FCEADC !important; }
</style>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-header-text mb-0">Payment-Based Schedule</h5>
        <div>
            <?php if ($lease): 
           $token = function_exists('encrypt_id') 
                    ? encrypt_id($lease['lease_id']) 
                    : $lease['lease_id']; ?>
            <a class="btn btn-outline-primary btn-sm" href="print_schedule_payment.php?token=<?= urlencode($token) ?>"
                target="_blank">
                <i class="fa fa-print"></i> Print Payment-Based Schedule
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-block" style="padding: 1rem;">
        <?php if ($error): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
        <?php
            $count = 1;
            $prev_balance_rent = 0.0;
            $prev_balance_penalty = 0.0;
            $prev_premium_balance = 0.0;

            // Determine column visibility
            $showPremiumCols = false;
            if ($lease && isset($lease['start_date'])) {
                $showPremiumCols = (strtotime($lease['start_date']) < strtotime('2020-01-01'));
            }
            $showDiscountCol = false;
            foreach ($schedules as $tmp) {
                if ((float)($tmp['discount_apply'] ?? 0) > 0) { $showDiscountCol = true; break; }
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$schedules): ?>
                    <tr>
                        <td colspan="<?= $colspan ?>" class="text-center">No schedules found</td>
                    </tr>
                    <?php else: foreach ($schedules as $schedule):
                        $sid = (int)$schedule['schedule_id'];
                        $paid = $payTotals[$sid] ?? ['rent'=>0,'penalty'=>0,'premium'=>0,'discount'=>0];

                        $annual = (float)$schedule['annual_amount'];
                        $discount = (float)$schedule['discount_apply'];
                        $effective_due = $annual - $discount;
                        $paid_rent = (float)$paid['rent'];
                        $balance_rent = $prev_balance_rent + ($effective_due - $paid_rent);
                        $prev_balance_rent = $balance_rent;

                        $penalty_due = (float)$schedule['panalty'];
                        $pen_paid = (float)$paid['penalty'];
                        $balance_penalty = $prev_balance_penalty + ($penalty_due - $pen_paid);
                        $prev_balance_penalty = $balance_penalty;

                        $premium_due = (float)$schedule['premium'];
                        $premium_paid = (float)$paid['premium'];
                        if ($showPremiumCols) {
                            $prev_premium_balance += ($premium_due - $premium_paid);
                        }

                        $total_paid = $paid_rent + $pen_paid + ($showPremiumCols ? $premium_paid : 0);
                        $total_outstanding = $balance_rent + $balance_penalty + ($showPremiumCols ? $prev_premium_balance : 0);

                        $rowClass = '';
                        if ($sid === $currentScheduleId) {
                            $rowClass = 'current-schedule-row';
                        } elseif ($schedule['end_date'] < $today) {
                            $rowClass = 'old-schedule-row';
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center"><?= $count++ ?></td>
                        <td align="center"><?= htmlspecialchars($schedule['start_date']) ?></td>
                        <td align="center"><?= htmlspecialchars($schedule['end_date']) ?></td>

                        <?php if ($showPremiumCols): ?>
                        <td class="text-right"><?= number_format($premium_due,2) ?></td>
                        <td class="text-right"><?= number_format($premium_paid,2) ?></td>
                        <td class="text-right"><?= number_format($prev_premium_balance,2) ?></td>
                        <?php endif; ?>

                        <td class="text-right"><?= number_format($annual,2) ?></td>
                        <td class="text-right"><?= number_format($paid_rent,2) ?></td>

                        <?php if ($showDiscountCol): ?>
                        <td class="text-right"><?= number_format($discount,2) ?></td>
                        <?php endif; ?>

                        <td class="text-right"><?= number_format($balance_rent,2) ?></td>

                        <td class="text-right"><?= number_format($penalty_due,2) ?></td>
                        <td class="text-right"><?= number_format($pen_paid,2) ?></td>
                        <td class="text-right"><?= number_format($balance_penalty,2) ?></td>

                        <td class="text-right"><?= number_format($total_paid,2) ?></td>
                        <td class="text-right"><?= number_format($total_outstanding,2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
