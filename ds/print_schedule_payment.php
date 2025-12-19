<?php
include '../db.php';
include '../auth.php';

// Accept either encrypted token or plain lease_id
if (isset($_GET['token'])) {
    if (!function_exists('decrypt_id')) { echo 'Token decryption not available'; exit; }
    $dec = decrypt_id($_GET['token']);
    if ($dec === false) { echo 'Invalid token'; exit; }
    $lease_id = (int)$dec;
} elseif (isset($_GET['lease_id'])) {
    $lease_id = (int)$_GET['lease_id'];
} else {
    echo 'Missing lease_id';
    exit;
}

// Lease + beneficiary + land info
$lease_sql = "SELECT 
    l.*, 
    ben.name AS beneficiary_name,
    ben.address AS beneficiary_address,
    ben.telephone AS beneficiary_phone,
    ben.district AS beneficiary_district,
    land.land_address,
    land.ds_id,
    land.gn_id,
    COALESCE(cr.client_name, ben.ds_division_text) AS ds_division_name,
    COALESCE(gn.gn_name, ben.gn_division_text) AS gn_division_name
    FROM leases l
    LEFT JOIN beneficiaries ben ON l.beneficiary_id = ben.ben_id
    LEFT JOIN ltl_land_registration land ON l.land_id = land.land_id
    LEFT JOIN client_registration cr ON land.ds_id = cr.c_id
    LEFT JOIN gn_division gn ON land.gn_id = gn.gn_id
    WHERE l.lease_id = ?";
$stmt = $con->prepare($lease_sql);
$stmt->bind_param('i', $lease_id);
$stmt->execute();
$lease = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Schedules
$schedules = [];
if ($stS = $con->prepare('SELECT * FROM lease_schedules WHERE lease_id=? ORDER BY schedule_year')) {
    $stS->bind_param('i', $lease_id);
    $stS->execute();
    $rs = $stS->get_result();
    $schedules = $rs->fetch_all(MYSQLI_ASSOC);
    $stS->close();
}

// Payments (status=1)
$payments = [];
if ($stP = $con->prepare('SELECT * FROM lease_payments WHERE lease_id=? AND status=1 ORDER BY payment_date, payment_id')) {
    $stP->bind_param('i', $lease_id);
    $stP->execute();
    $rp = $stP->get_result();
    $payments = $rp->fetch_all(MYSQLI_ASSOC);
    $stP->close();
}

// Aggregate payments per schedule (by schedule_id if set, otherwise by date range)
$scheduleById = [];
foreach ($schedules as $sch) { $scheduleById[(int)$sch['schedule_id']] = $sch; }

$payTotals = [];
foreach ($payments as $pay) {
    $sid = (int)($pay['schedule_id'] ?? 0);
    $assigned = false;
    if ($sid > 0 && isset($scheduleById[$sid])) {
        $assigned = true;
    } else {
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
    if (!$assigned) { continue; }
    if (!isset($payTotals[$sid])) {
        $payTotals[$sid] = ['rent'=>0.0,'penalty'=>0.0,'premium'=>0.0,'discount'=>0.0];
    }
    $payTotals[$sid]['rent'] += (float)($pay['rent_paid'] ?? 0);
    $payTotals[$sid]['penalty'] += (float)($pay['panalty_paid'] ?? 0);
    $payTotals[$sid]['premium'] += (float)($pay['premium_paid'] ?? 0);
    $payTotals[$sid]['discount'] += (float)($pay['discount_apply'] ?? 0);
}

$valuation_amount = isset($lease['valuation_amount']) && $lease['valuation_amount'] !== '' ? (float)$lease['valuation_amount'] : null;
$annual_pct = isset($lease['annual_rent_percentage']) && $lease['annual_rent_percentage'] !== '' ? (float)$lease['annual_rent_percentage'] : null;
$annual_rent_amount = ($valuation_amount !== null && $annual_pct !== null) ? $valuation_amount * ($annual_pct / 100) : null;
$type_of_project = $lease['type_of_project'] ?? '';
$project_name = $lease['name_of_the_project'] ?? '';
$lease_status = $lease['lease_status'] ?? ($lease['status'] ?? '');
$lease_number = $lease['lease_number'] ?? '';
$ds_division = $lease['ds_division_name'] ?? '';
$gn_division = $lease['gn_division_name'] ?? '';
$contact = $lease['beneficiary_phone'] ?? '';
$lease_holder = $lease['beneficiary_name'] ?? '';
$ben_address = $lease['beneficiary_address'] ?? '';
$land_address = $lease['land_address'] ?? '';
$approved_date = $lease['approved_date'] ?? '';
$lease_start_date = $lease['start_date'] ?? '';
$file_no = $lease['file_number'] ?? '';

$showPremiumCols = (!empty($lease['start_date']) && strtotime($lease['start_date']) < strtotime('2020-01-01'));
$showDiscountCol = false;
foreach ($schedules as $sc) {
    if (!empty($sc['discount_apply']) && (float)$sc['discount_apply'] > 0) { $showDiscountCol = true; break; }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Payment-Based Schedule - <?= htmlspecialchars($lease_number) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    @page { size: A4 landscape; margin: 6mm; }
    body { font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; padding:8px; }
    table { border-collapse: collapse; width:100%; margin-bottom:8px; }
    table, th, td { border: 1px solid #333; }
    th, td { padding: 4px 6px; font-size: 11px; line-height:1.1; }
    thead th { background:#f5f5f5; }
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    .controls { margin-bottom:6px; }
    .lease-info { width:100%; border-collapse: collapse; table-layout: fixed; margin: 6px 0 10px; }
    .lease-info td { border:none; padding:3px 6px; font-size:10px; vertical-align: top; line-height:1.2; }
    .lease-info .label { font-weight:600; letter-spacing:0.4px; color:#444; margin-right:4px; }
    .lease-info .value { font-weight:600; color:#000; }
    @media print { .controls { display:none; } }
    .current-row { background:#8DF78D !important; }
    .old-row { background:#FCEADC !important; }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="window.print();" style="padding:6px 10px;">Print</button>
        <button onclick="window.close();" style="padding:6px 10px;">Close</button>
    </div>

    <div align="center"><h3>Payment-Based Schedule</h3></div>
    <table class="lease-info">
        <tr>
            <td><span class="label">Lease Number</span><span class="value"><?= htmlspecialchars($lease_number ?: '-') ?></span></td>
            <td><span class="label">File No</span><span class="value"><?= htmlspecialchars($file_no ?: '-') ?></span></td>
            <td><span class="label">Approved Date</span><span class="value"><?= htmlspecialchars($approved_date ?: '-') ?></span></td>
            <td><span class="label">Start Date</span><span class="value"><?= htmlspecialchars($lease_start_date ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">Lease Holder</span><span class="value"><?= htmlspecialchars($lease_holder ?: '-') ?></span></td>
            <td><span class="label">Contact</span><span class="value"><?= htmlspecialchars($contact ?: '-') ?></span></td>
            <td><span class="label">Lessee Address</span><span class="value"><?= htmlspecialchars($ben_address ?: '-') ?></span></td>
            <td><span class="label">Land Address</span><span class="value"><?= htmlspecialchars($land_address ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">Valuation Amount</span><span class="value"><?= $valuation_amount !== null ? number_format($valuation_amount, 2) : '-' ?></span></td>
            <td><span class="label">Annual Rent</span><span class="value"><?= $annual_rent_amount !== null ? number_format($annual_rent_amount, 2) : '-' ?></span></td>
            <td><span class="label">Type of Project</span><span class="value"><?= htmlspecialchars($type_of_project ?: '-') ?></span></td>
            <td><span class="label">Project Name</span><span class="value"><?= htmlspecialchars($project_name ?: '-') ?></span></td>
        </tr>
        <tr>
            <td><span class="label">DS Division</span><span class="value"><?= htmlspecialchars($ds_division ?: '-') ?></span></td>
            <td><span class="label">GN Division</span><span class="value"><?= htmlspecialchars($gn_division ?: '-') ?></span></td>
            <td><span class="label">Lease Status</span><span class="value"><?= htmlspecialchars($lease_status ?: '-') ?></span></td>
            <td></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">Start</th>
                <th class="text-center">End</th>
                <?php if ($showPremiumCols): ?>
                <th class="text-center">Premium</th>
                <th class="text-center">Premium Paid</th>
                <th class="text-center">Premium Bal</th>
                <?php endif; ?>
                <th class="text-center">Annual Lease</th>
                <th class="text-center">Paid Rent</th>
                <?php if ($showDiscountCol): ?>
                <th class="text-center">Discount</th>
                <?php endif; ?>
                <th class="text-center">Rent Bal</th>
                <th class="text-center">Penalty</th>
                <th class="text-center">Penalty Paid</th>
                <th class="text-center">Penalty Bal</th>
                <th class="text-center">Total Paid</th>
                <th class="text-center">Total Outst</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $count = 1;
            $prev_balance_rent = 0.0;
            $prev_balance_penalty = 0.0;
            $prev_premium_balance = 0.0;
            $today = date('Y-m-d');
            foreach ($schedules as $schedule):
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
                if ($schedule['start_date'] <= $today && $schedule['end_date'] >= $today) {
                    $rowClass = 'current-row';
                } elseif ($schedule['end_date'] < $today) {
                    $rowClass = 'old-row';
                }
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="text-center"><?= $count++ ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['start_date']) ?></td>
                <td class="text-center"><?= htmlspecialchars($schedule['end_date']) ?></td>
                <?php if ($showPremiumCols): ?>
                <td class="text-right"><?= number_format($premium_due, 2) ?></td>
                <td class="text-right"><?= number_format($premium_paid, 2) ?></td>
                <td class="text-right"><?= number_format($prev_premium_balance, 2) ?></td>
                <?php endif; ?>
                <td class="text-right"><?= number_format($annual, 2) ?></td>
                <td class="text-right"><?= number_format($paid_rent, 2) ?></td>
                <?php if ($showDiscountCol): ?>
                <td class="text-right"><?= number_format($discount, 2) ?></td>
                <?php endif; ?>
                <td class="text-right"><?= number_format($balance_rent, 2) ?></td>
                <td class="text-right"><?= number_format($penalty_due, 2) ?></td>
                <td class="text-right"><?= number_format($pen_paid, 2) ?></td>
                <td class="text-right"><?= number_format($balance_penalty, 2) ?></td>
                <td class="text-right"><strong><?= number_format($total_paid, 2) ?></strong></td>
                <td class="text-right"><strong><?= number_format($total_outstanding, 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Payment History</h3>
    <table style="width:60%;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Receipt No</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
            <tr><td colspan="3" class="text-center">No payments recorded</td></tr>
            <?php else: foreach ($payments as $pay): ?>
            <tr>
                <td class="text-center"><?= htmlspecialchars($pay['payment_date']) ?></td>
                <td><?= htmlspecialchars($pay['receipt_number'] ?? $pay['reference_number'] ?? '') ?></td>
                <td class="text-right"><?= number_format((float)($pay['amount'] ?? 0), 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <script>
    window.onload = function() { setTimeout(function(){ window.print(); }, 200); };
    </script>
</body>
</html>
