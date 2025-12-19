<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__) . '/ajax/payment_allocator.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $locationId = resolveLocationId($con);
    $input = collectLeaseInput();

    $oldLease = loadOldLease($con, $input['lease_id']);
    $paymentsCount = countActivePayments($con, $input['lease_id']);

    // Recompute annual %, rent, and premium
    $annualPct = adjustAnnualPercent($con, $input['lease_type_id'], $input['valuation_amount'], $input['annual_rent_percentage']);
    $initialRent = $input['valuation_amount'] * ($annualPct / 100.0);
    $premium = computePremium($con, $input['lease_type_id'], $initialRent, $input['start_date']);
    $input['annual_rent_percentage'] = $annualPct;

    $changes = detectAllChanges($oldLease, $input, $premium);
    $needRebuild = shouldRebuildSchedules($oldLease, $input);

    // When valuation date missing, wipe penalties and skip recalculation
    $skipPenalty = empty($input['valuation_date']) || $input['valuation_date'] === '0000-00-00';
    if ($skipPenalty) {
        mysqli_query($con, "UPDATE lease_schedules SET panalty = 0, panalty_paid = 0 WHERE lease_id = {$input['lease_id']}");
    }

    updateLeaseRow($con, $locationId, $input, $premium, $initialRent);

    $note = handleSchedulesAndPayments(
        $con,
        $input['lease_id'],
        $initialRent,
        $premium,
        $input['revision_period'],
        $input['revision_percentage'],
        $input['start_date'],
        $input['duration_years'],
        $paymentsCount,
        $needRebuild
    );

    if (!$skipPenalty) {
        runPenalty($input['lease_id']);
    }

    if (function_exists('UserLog') && count($changes) > 0) {
        $logMsg = 'Lease ID=' . $input['lease_id'] . ' | Lease No=' . $input['lease_number'] . ' | Changes: ' . implode(' | ', $changes);
        @UserLog(2, 'LTL Lease Updated', $logMsg, $input['beneficiary_id']);
    }

    $response['success'] = true;
    $response['lease_id'] = $input['lease_id'];
    $response['message'] = 'Lease updated successfully!' . $note;

} catch (Exception $ex) {
    $response['message'] = $ex->getMessage();
}

echo json_encode($response);

/* ----------------------- Helpers ----------------------- */

function resolveLocationId(mysqli $con): int {
    $locationId = 0;
    if (isset($_COOKIE['client_cook'])) {
        $md5Client = $_COOKIE['client_cook'];
        $sql = "SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1";
        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 's', $md5Client);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $locationId = (int)$row['c_id'];
            }
            mysqli_stmt_close($stmt);
        }
    }
    return $locationId;
}

function collectLeaseInput(): array {
    $leaseId = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    if ($leaseId <= 0) {
        throw new Exception('Missing lease, land or beneficiary');
    }

    return [
        'lease_id'              => $leaseId,
        'land_id'               => isset($_POST['land_id']) ? (int)$_POST['land_id'] : 0,
        'beneficiary_id'        => isset($_POST['beneficiary_id']) ? (int)$_POST['beneficiary_id'] : 0,
        'valuation_amount'      => floatval($_POST['valuation_amount'] ?? 0),
        'valuation_date'        => $_POST['valuation_date'] ?? '',
        'value_date'            => $_POST['value_date'] ?? '',
        'approved_date'         => $_POST['approved_date'] ?? '',
        'annual_rent_percentage'=> floatval($_POST['annual_rent_percentage'] ?? 0),
        'revision_period'       => (int)($_POST['revision_period'] ?? 0),
        'revision_percentage'   => floatval($_POST['revision_percentage'] ?? 0),
        'start_date'            => $_POST['start_date'] ?? '',
        'end_date'              => $_POST['end_date'] ?? '',
        'duration_years'        => (int)($_POST['duration_years'] ?? 0),
        'lease_type_id'         => isset($_POST['lease_type_id1']) ? (int)$_POST['lease_type_id1'] : 0,
        'type_of_project'       => isset($_POST['type_of_project']) ? trim($_POST['type_of_project']) : '',
        'name_of_the_project'   => isset($_POST['name_of_the_project']) ? trim($_POST['name_of_the_project']) : '',
        'lease_number'          => isset($_POST['lease_number']) ? trim($_POST['lease_number']) : '',
        'file_number'           => isset($_POST['file_number']) ? trim($_POST['file_number']) : '',
    ];
}

function loadOldLease(mysqli $con, int $leaseId): ?array {
    $sql = "SELECT valuation_amount, valuation_date, value_date, approved_date, start_date,
                   annual_rent_percentage, end_date, revision_period, revision_percentage,
                   duration_years, premium, lease_number, file_number
            FROM leases WHERE lease_id=? LIMIT 1";

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $leaseId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
    return null;
}

function adjustAnnualPercent(mysqli $con, int $leaseTypeId, float $valuationAmount, float $fallback): float {
    $effective = $fallback;
    if ($leaseTypeId > 0) {
        $sql = "SELECT base_rent_percent, economy_rate, economy_valuvation 
                FROM lease_master WHERE lease_type_id=? LIMIT 1";
        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $leaseTypeId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $basePct = floatval($row['base_rent_percent'] ?? 0);
                $ecoRate = floatval($row['economy_rate'] ?? 0);
                $ecoVal  = floatval($row['economy_valuvation'] ?? 0);
                if ($valuationAmount > 0 && $ecoVal > 0 && $ecoRate > 0 && $valuationAmount <= $ecoVal) {
                    $effective = $ecoRate;
                } else {
                    $effective = $basePct > 0 ? $basePct : $fallback;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    return $effective;
}

function computePremium(mysqli $con, int $leaseTypeId, float $initialRent, string $startDate): float {
    if (empty($startDate) || strtotime($startDate) >= strtotime('2020-01-01')) {
        return 0.0;
    }
    $premiumTimes = 0.0;
    if ($leaseTypeId > 0) {
        $sql = "SELECT premium_times FROM lease_master WHERE lease_type_id = ? LIMIT 1";
        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $leaseTypeId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $premiumTimes = floatval($row['premium_times']);
            }
            mysqli_stmt_close($stmt);
        }
    }
    return $initialRent * $premiumTimes;
}

function detectAllChanges(?array $oldLease, array $input, float $premium): array {
    $changes = [];
    if (!$oldLease) { return $changes; }

    detectChange('valuation_amount',       $oldLease['valuation_amount'] ?? '', $input['valuation_amount'], $changes);
    detectChange('valuation_date',         $oldLease['valuation_date'] ?? '',   $input['valuation_date'], $changes);
    detectChange('value_date',             $oldLease['value_date'] ?? '',       $input['value_date'], $changes);
    detectChange('approved_date',          $oldLease['approved_date'] ?? '',    $input['approved_date'], $changes);
    detectChange('annual_rent_percentage', $oldLease['annual_rent_percentage'] ?? '', $input['annual_rent_percentage'], $changes);
    detectChange('revision_period',        $oldLease['revision_period'] ?? '',  $input['revision_period'], $changes);
    detectChange('revision_percentage',    $oldLease['revision_percentage'] ?? '', $input['revision_percentage'], $changes);
    detectChange('start_date',             $oldLease['start_date'] ?? '',       $input['start_date'], $changes);
    detectChange('end_date',               $oldLease['end_date'] ?? '',         $input['end_date'], $changes);
    detectChange('duration_years',         $oldLease['duration_years'] ?? '',   $input['duration_years'], $changes);
    detectChange('premium',                $oldLease['premium'] ?? '',          $premium, $changes);
    detectChange('lease_number',           $oldLease['lease_number'] ?? '',     $input['lease_number'], $changes);
    detectChange('file_number',            $oldLease['file_number'] ?? '',      $input['file_number'], $changes);

    return $changes;
}

function shouldRebuildSchedules(?array $oldLease, array $input): bool {
    if (!$oldLease) { return false; }
    $oldVal   = floatval($oldLease['valuation_amount'] ?? 0);
    $oldStart = $oldLease['start_date'] ?? '';
    $oldPct   = floatval($oldLease['annual_rent_percentage'] ?? 0);

    return (
        round($oldVal, 2) != round($input['valuation_amount'], 2) ||
        $oldStart !== $input['start_date'] ||
        round($oldPct, 4) != round($input['annual_rent_percentage'], 4) ||
        (int)($oldLease['revision_period'] ?? 0) != (int)$input['revision_period'] ||
        round($oldLease['revision_percentage'] ?? 0, 4) != round($input['revision_percentage'], 4) ||
        (int)($oldLease['duration_years'] ?? 0) != (int)$input['duration_years']
    );
}

function updateLeaseRow(mysqli $con, int $locationId, array $input, float $premium, float $initialRent): void {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $sql = "UPDATE leases SET 
                beneficiary_id=?,
                location_id=?,
                lease_number=?,
                file_number=?,
                valuation_amount=?,
                valuation_date=?,
                value_date=?,
                approved_date=?,
                premium=?,
                annual_rent_percentage=?,
                revision_period=?,
                revision_percentage=?,
                start_date=?,
                end_date=?,
                duration_years=?,
                name_of_the_project=?,
                updated_by=?,
                updated_on=NOW()
            WHERE lease_id=?";

    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        throw new Exception('DB error: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iissdsssddidssisii',
        $input['beneficiary_id'],
        $locationId,
        $input['lease_number'],
        $input['file_number'],
        $input['valuation_amount'],
        $input['valuation_date'],
        $input['value_date'],
        $input['approved_date'],
        $premium,
        $input['annual_rent_percentage'],
        $input['revision_period'],
        $input['revision_percentage'],
        $input['start_date'],
        $input['end_date'],
        $input['duration_years'],
        $input['name_of_the_project'],
        $uid,
        $input['lease_id']
    );

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_error($con);
        mysqli_stmt_close($stmt);
        throw new Exception('Error updating lease: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

function handleSchedulesAndPayments(
    mysqli $con,
    int $leaseId,
    float $initialRent,
    float $premium,
    int $revisionPeriod,
    float $revisionPct,
    string $startDate,
    int $durationYears,
    int $paymentsCount,
    bool $needRebuild
): string {
    if ($needRebuild) {
        if (!rebuildSchedulesAndReapplyPayments(
            $con,
            $leaseId,
            $initialRent,
            $premium,
            $revisionPeriod,
            $revisionPct,
            $startDate,
            $durationYears
        )) {
            throw new Exception('Failed to rebuild schedules and reprocess payments.');
        }
        return ' Schedules regenerated and payments reprocessed (because lease values changed).';
    }

    if ($paymentsCount === 0) {
        deleteSchedules($con, $leaseId);
        generateLeaseSchedules($con, $leaseId, $initialRent, $premium, $revisionPeriod, $revisionPct, $startDate, $durationYears);
        return ' Schedules regenerated (no payments exist).';
    }

    return ' Payments exist. Schedules NOT regenerated.';
}

function countActivePayments(mysqli $con, int $leaseId): int {
    $count = 0;
    if ($stmt = mysqli_prepare($con, 'SELECT COUNT(*) AS cnt FROM lease_payments WHERE lease_id=? AND status=1')) {
        mysqli_stmt_bind_param($stmt, 'i', $leaseId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $count = (int)$row['cnt'];
        }
        mysqli_stmt_close($stmt);
    }
    return $count;
}

function deleteSchedules(mysqli $con, int $leaseId): void {
    if ($stmt = mysqli_prepare($con, 'DELETE FROM lease_schedules WHERE lease_id=?')) {
        mysqli_stmt_bind_param($stmt, 'i', $leaseId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function generateLeaseSchedules(
    mysqli $con,
    int $leaseId,
    float $initialRent,
    float $premium,
    int $revisionPeriod,
    float $revisionPct,
    string $startDate,
    int $durationYears = 30
): bool {
    $startTs = strtotime($startDate);
    if (!$startTs) {
        throw new Exception('Invalid start_date for schedule generation');
    }

    $boundaryTs = strtotime('2020-01-01');
    $duration = (int)$durationYears;

    $usePreRules = ($startTs < $boundaryTs && $revisionPeriod > 0);
    $prePeriodYears = 5;
    $prePct = 50.0;

    $postPeriodYears = ($revisionPeriod > 0) ? (int)$revisionPeriod : 0;
    $postPct = (float)$revisionPct;

    $nextRevTs = $usePreRules
        ? strtotime("+{$prePeriodYears} years", $startTs)
        : (($postPeriodYears > 0) ? strtotime("+{$postPeriodYears} years", $startTs) : null);

    $currentRent = (float)$initialRent;
    $revisionNumber = 0;

    for ($year = 0; $year < $duration; $year++) {
        $yearStartTs = strtotime("+{$year} years", $startTs);
        $yearEndTs = strtotime("+1 year -1 day", $yearStartTs);
        $scheduleYear = (int)date('Y', $yearStartTs);
        $yearStart = date('Y-m-d', $yearStartTs);
        $yearEnd = date('Y-m-d', $yearEndTs);
        $dueDate = date('Y-m-d', strtotime($scheduleYear . '-03-31'));

        $isRevisionYear = 0;

        if ($nextRevTs && $yearStartTs >= $nextRevTs) {
            $isRevisionYear = 1;
            $revisionNumber++;

            $appliedPreRule = ($usePreRules && $nextRevTs < $boundaryTs);
            if ($appliedPreRule) {
                $currentRent *= 1.50;
                $candidate = strtotime("+{$prePeriodYears} years", $nextRevTs);
                $nextRevTs = ($candidate < $boundaryTs)
                    ? $candidate
                    : (($postPeriodYears > 0) ? strtotime("+{$postPeriodYears} years", $nextRevTs) : null);
            } else {
                if ($postPct > 0) {
                    $currentRent *= (1 + ($postPct / 100.0));
                }
                $nextRevTs = ($postPeriodYears > 0) ? strtotime("+{$postPeriodYears} years", $nextRevTs) : null;
            }
        }

        $firstYearPremium = ($year === 0 && $startTs < $boundaryTs) ? $premium : 0.0;

        $sql = "INSERT INTO lease_schedules (
                    lease_id, schedule_year, start_date, end_date, due_date,
                    base_amount, premium, premium_paid, annual_amount,
                    revision_number, is_revision_year, status, created_on
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', NOW()
                )";

        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                'iisssdddii',
                $leaseId,
                $scheduleYear,
                $yearStart,
                $yearEnd,
                $dueDate,
                $initialRent,
                $firstYearPremium,
                $currentRent,
                $revisionNumber,
                $isRevisionYear
            );

            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_error($con);
                mysqli_stmt_close($stmt);
                throw new Exception('Schedule generation failed: ' . $err);
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Schedule statement prepare error: ' . mysqli_error($con));
        }
    }

    return true;
}

function rebuildSchedulesAndReapplyPayments(
    mysqli $con,
    int $leaseId,
    float $initialRent,
    float $premium,
    int $revisionPeriod,
    float $revisionPct,
    string $startDate,
    int $durationYears = 30
): bool {
    $payments = [];
    $sql = "SELECT * FROM lease_payments 
            WHERE lease_id=? AND status=1 
            ORDER BY payment_date ASC, payment_id ASC";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $leaseId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($rs)) {
            $payments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    deleteSchedules($con, $leaseId);
    if (!generateLeaseSchedules($con, $leaseId, $initialRent, $premium, $revisionPeriod, $revisionPct, $startDate, $durationYears)) {
        return false;
    }

    $discountRate = fetchLeaseDiscountRate($con, null, $leaseId);
    $scheduleState = loadLeaseSchedulesForPayment($con, $leaseId);

    $updatePaymentSql = "UPDATE lease_payments SET 
            schedule_id=?,
            rent_paid=?,
            panalty_paid=?,
            premium_paid=?,
            discount_apply=?,
            current_year_payment=?,
            payment_type=?
         WHERE payment_id=?";
    $updatePaymentStmt = $con->prepare($updatePaymentSql);
    if (!$updatePaymentStmt) { return false; }

    $updateScheduleSql = "UPDATE lease_schedules SET 
            paid_rent = paid_rent + ?,
            panalty_paid = panalty_paid + ?,
            premium_paid = premium_paid + ?,
            total_paid = total_paid + ?,
            discount_apply = discount_apply + ?
         WHERE schedule_id = ?";
    $updateScheduleStmt = $con->prepare($updateScheduleSql);
    if (!$updateScheduleStmt) { $updatePaymentStmt->close(); return false; }

    $insertDetailSql = "INSERT INTO lease_payments_detail (
            payment_id, schedule_id, rent_paid, penalty_paid, premium_paid,
            discount_apply, current_year_payment, status
        ) VALUES (?,?,?,?,?,?,?,?)";
    $insertDetailStmt = $con->prepare($insertDetailSql);
    if (!$insertDetailStmt) { $updateScheduleStmt->close(); $updatePaymentStmt->close(); return false; }

    $deleteDetailStmt = $con->prepare("DELETE FROM lease_payments_detail WHERE payment_id = ?");
    if (!$deleteDetailStmt) { $insertDetailStmt->close(); $updateScheduleStmt->close(); $updatePaymentStmt->close(); return false; }

    foreach ($payments as $pay) {
        $paymentId = intval($pay['payment_id']);
        $amount = floatval($pay['amount'] ?? 0);
        $paymentDate = $pay['payment_date'];
        if ($amount <= 0) { continue; }

        $allocation = allocateLeasePayment($scheduleState, $paymentDate, $amount, $discountRate);
        $allocations = $allocation['allocations'];
        $totals = $allocation['totals'];
        $currentScheduleId = $allocation['current_schedule_id'];
        $remainingAfter = $allocation['remaining'];

        if ($remainingAfter > 0.01) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        if (empty($allocations)) {
            $scheduleState = $allocation['schedules'];
            continue;
        }

        $totalActual = $totals['rent'] + $totals['penalty'] + $totals['premium'];
        if (abs($totalActual - $amount) > 0.01) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        $paymentType = 'mixed';
        $newRent = $totals['rent'];
        $newPenalty = $totals['penalty'];
        $newPremium = $totals['premium'];
        $newDiscount = $totals['discount'];
        $newCurrentYear = $totals['current_year_payment'];

        $updatePaymentStmt->bind_param(
            'idddddsi',
            $currentScheduleId,
            $newRent,
            $newPenalty,
            $newPremium,
            $newDiscount,
            $newCurrentYear,
            $paymentType,
            $paymentId
        );
        if (!$updatePaymentStmt->execute()) {
            $deleteDetailStmt->close();
            $insertDetailStmt->close();
            $updateScheduleStmt->close();
            $updatePaymentStmt->close();
            return false;
        }

        $deleteDetailStmt->bind_param('i', $paymentId);
        $deleteDetailStmt->execute();

        foreach ($allocations as $sid => $alloc) {
            $scheduleId = intval($sid);
            $rentInc = $alloc['rent'];
            $penInc = $alloc['penalty'];
            $premInc = $alloc['premium'];
            $discInc = $alloc['discount'];
            $curYearInc = $alloc['current_year_payment'];
            $totalPaidSchedule = $alloc['total_paid'];

            $updateScheduleStmt->bind_param(
                'dddddi',
                $rentInc,
                $penInc,
                $premInc,
                $totalPaidSchedule,
                $discInc,
                $scheduleId
            );
            if (!$updateScheduleStmt->execute()) {
                $deleteDetailStmt->close();
                $insertDetailStmt->close();
                $updateScheduleStmt->close();
                $updatePaymentStmt->close();
                return false;
            }

            $hasDetail = ($rentInc > 0) || ($penInc > 0) || ($premInc > 0) || ($discInc > 0);
            if ($hasDetail) {
                $status = 1;
                $insertDetailStmt->bind_param(
                    'iidddddi',
                    $paymentId,
                    $scheduleId,
                    $rentInc,
                    $penInc,
                    $premInc,
                    $discInc,
                    $curYearInc,
                    $status
                );
                if (!$insertDetailStmt->execute()) {
                    $deleteDetailStmt->close();
                    $insertDetailStmt->close();
                    $updateScheduleStmt->close();
                    $updatePaymentStmt->close();
                    return false;
                }
            }
        }

        $scheduleState = $allocation['schedules'];
    }

    $deleteDetailStmt->close();
    $insertDetailStmt->close();
    $updateScheduleStmt->close();
    $updatePaymentStmt->close();

    // Final pass: recalc penalties now that payments have been reapplied to the regenerated schedules.
    runPenalty($leaseId);

    return true;
}

function detectChange(string $label, $oldValue, $newValue, array &$changes): void {
    $o = trim((string)$oldValue);
    $n = trim((string)$newValue);

    $nullValues = ['', 'null', 'NULL', '0.00', '0', '0000-00-00'];
    if (in_array($o, $nullValues, true)) { $o = 'null'; }
    if (in_array($n, $nullValues, true)) { $n = 'null'; }

    if ($o === 'null' && $n === 'null') { return; }

    if (is_numeric($o) && is_numeric($n)) {
        if (floatval($o) == floatval($n)) { return; }
        $changes[] = "$label: " . number_format(floatval($o), 2, '.', '') . " > " . number_format(floatval($n), 2, '.', '');
        return;
    }

    if ($o !== $n) {
        $changes[] = "$label: $o > $n";
    }
}

function runPenalty(int $leaseId): void {
    try {
        // cal_panalty.php expects $con in global scope
        global $con;
        $_REQUEST['lease_id'] = $leaseId;
        ob_start();
        include __DIR__ . '/../cal_panalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // non-fatal
    }
}
