<?php
// Checks for active records in lease_payments, ltl_write_off, ltl_premium_change for a lease
require_once dirname(__DIR__, 2) . '/db.php';
header('Content-Type: application/json');
$lease_id = isset($_GET['lease_id']) ? (int)$_GET['lease_id'] : 0;
$res = ['has_active' => false];
if ($lease_id > 0) {
    $active = 0;
    // Check lease_payments (status=1 or not cancelled)
    $q1 = "SELECT COUNT(*) AS cnt FROM lease_payments WHERE lease_id=? AND status=1";
    if ($st1 = mysqli_prepare($con, $q1)) {
        mysqli_stmt_bind_param($st1, 'i', $lease_id);
        mysqli_stmt_execute($st1);
        $r1 = mysqli_stmt_get_result($st1);
        if ($r1 && ($row1 = mysqli_fetch_assoc($r1))) {
            if ((int)$row1['cnt'] > 0) $active++;
        }
        mysqli_stmt_close($st1);
    }
    // Check ltl_write_off (status=1)
    $q2 = "SELECT COUNT(*) AS cnt FROM ltl_write_off WHERE lease_id=? AND status=1";
    if ($st2 = mysqli_prepare($con, $q2)) {
        mysqli_stmt_bind_param($st2, 'i', $lease_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        if ($r2 && ($row2 = mysqli_fetch_assoc($r2))) {
            if ((int)$row2['cnt'] > 0) $active++;
        }
        mysqli_stmt_close($st2);
    }
    // Check ltl_premium_change (status=1)
    $q3 = "SELECT COUNT(*) AS cnt FROM ltl_premium_change WHERE lease_id=? AND status=1";
    if ($st3 = mysqli_prepare($con, $q3)) {
        mysqli_stmt_bind_param($st3, 'i', $lease_id);
        mysqli_stmt_execute($st3);
        $r3 = mysqli_stmt_get_result($st3);
        if ($r3 && ($row3 = mysqli_fetch_assoc($r3))) {
            if ((int)$row3['cnt'] > 0) $active++;
        }
        mysqli_stmt_close($st3);
    }
    $res['has_active'] = $active > 0;
}
echo json_encode($res);