<?php

declare(strict_types=1);

const PAYMENT_EPSILON = 0.005;

function fetchLeaseDiscountRate(mysqli $con, ?int $leaseTypeId = null, ?int $leaseId = null): float
{
    if ($leaseTypeId !== null) {
        $sql = "SELECT discount_rate FROM lease_master WHERE lease_type_id = ? LIMIT 1";
        if ($stmt = $con->prepare($sql)) {
            $stmt->bind_param('i', $leaseTypeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if ($row && isset($row['discount_rate'])) {
                return max(0.0, floatval($row['discount_rate']) / 100.0);
            }
        }
        return 0.0;
    }

    if ($leaseId !== null) {
        $sql = "SELECT lm.discount_rate
                FROM leases l
                LEFT JOIN lease_master lm ON l.lease_type_id = lm.lease_type_id
                WHERE l.lease_id = ?
                LIMIT 1";
        if ($stmt = $con->prepare($sql)) {
            $stmt->bind_param('i', $leaseId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if ($row && isset($row['discount_rate'])) {
                return max(0.0, floatval($row['discount_rate']) / 100.0);
            }
        }
    }

    return 0.0;
}

function loadLeaseSchedulesForPayment(mysqli $con, int $leaseId): array
{
    $sql = "SELECT schedule_id, lease_id, schedule_year, start_date, end_date,
                   annual_amount, panalty, panalty_paid, premium, premium_paid,
                   paid_rent, discount_apply
            FROM lease_schedules
            WHERE lease_id = ?  
            ORDER BY start_date ASC, schedule_id ASC";

    if (!$stmt = $con->prepare($sql)) {
        throw new RuntimeException('Failed to prepare schedule query');
    }

    $stmt->bind_param('i', $leaseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    return $schedules;
}

function determineCurrentScheduleId(array $schedules, int $paymentTs): ?int
{
    foreach ($schedules as $schedule) {
        $startTs = $schedule['start_date'] ? strtotime($schedule['start_date']) : false;
        $endTs = $schedule['end_date'] ? strtotime($schedule['end_date']) : false;
        if ($startTs !== false && $endTs !== false && $paymentTs >= $startTs && $paymentTs <= $endTs) {
            return intval($schedule['schedule_id']);
        }
    }

    foreach ($schedules as $schedule) {
        $startTs = $schedule['start_date'] ? strtotime($schedule['start_date']) : false;
        if ($startTs !== false && $paymentTs < $startTs) {
            return intval($schedule['schedule_id']);
        }
    }

    if (!empty($schedules)) {
        $last = end($schedules);
        return intval($last['schedule_id']);
    }

    return null;
}

function allocateLeasePayment(array $schedules, string $paymentDate, float $amount, float $discountRate): array
{
    $paymentTs = strtotime($paymentDate);
    if ($paymentTs === false) {
        throw new InvalidArgumentException('Invalid payment date');
    }

    $updatedSchedules = [];
    foreach ($schedules as $schedule) {
        $updatedSchedules[] = [
            'schedule_id'        => intval($schedule['schedule_id']),
            'start_date'         => $schedule['start_date'],
            'end_date'           => $schedule['end_date'],
            'start_ts'           => $schedule['start_date'] ? strtotime($schedule['start_date']) : null,
            'annual_amount'      => floatval($schedule['annual_amount'] ?? 0),
            'panalty'            => floatval($schedule['panalty'] ?? 0),
            'panalty_paid'       => floatval($schedule['panalty_paid'] ?? 0),
            'premium'            => floatval($schedule['premium'] ?? 0),
            'premium_paid'       => floatval($schedule['premium_paid'] ?? 0),
            'paid_rent'          => floatval($schedule['paid_rent'] ?? 0),
            'discount_apply'     => floatval($schedule['discount_apply'] ?? 0),
        ];
    }

    $currentScheduleId = determineCurrentScheduleId($updatedSchedules, $paymentTs);
    if ($currentScheduleId === null) {
        throw new RuntimeException('No schedule found for payment');
    }

    $remaining = $amount;
    $allocations = [];
    $totals = [
        'rent' => 0.0,
        'penalty' => 0.0,
        'premium' => 0.0,
        'discount' => 0.0,
        'current_year_payment' => 0.0,
    ];

    foreach ($updatedSchedules as &$schedule) {
        $dueTs = $schedule['start_ts']; // Use start date as due date proxy
        $penaltyAllowed = ($dueTs !== null) && ($paymentTs > $dueTs);
        $schedule['pen_out'] = $penaltyAllowed
            ? max(0.0, $schedule['panalty'] - $schedule['panalty_paid'])
            : 0.0;

        $schedule['prem_out'] = max(0.0, $schedule['premium'] - $schedule['premium_paid']);
        $schedule['rent_out'] = max(0.0, $schedule['annual_amount'] - $schedule['paid_rent'] - $schedule['discount_apply']);
        $schedule['discount_cap'] = max(0.0, $schedule['annual_amount'] * $discountRate);
        $schedule['discount_remaining'] = max(0.0, $schedule['discount_cap'] - $schedule['discount_apply']);
        $schedule['within_window'] = $schedule['start_ts'] !== null && $paymentTs <= ($schedule['start_ts'] + 30 * 86400);
    }
    unset($schedule);

    // Phase 1: clear penalties across all schedules (global priority)
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= PAYMENT_EPSILON || $schedule['pen_out'] <= PAYMENT_EPSILON) {
            continue;
        }
        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $pay = min($remaining, $schedule['pen_out']);
        $schedule['pen_out'] -= $pay;
        $schedule['panalty_paid'] += $pay;
        $allocations[$sid]['penalty'] += $pay;
        $allocations[$sid]['total_paid'] += $pay;
        $totals['penalty'] += $pay;
        $remaining -= $pay;
    }
    unset($schedule);

    // Phase 2: clear premiums across all schedules (after penalties)
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= PAYMENT_EPSILON || $schedule['prem_out'] <= PAYMENT_EPSILON) {
            continue;
        }
        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $pay = min($remaining, $schedule['prem_out']);
        $schedule['prem_out'] -= $pay;
        $schedule['premium_paid'] += $pay;
        $allocations[$sid]['premium'] += $pay;
        $allocations[$sid]['total_paid'] += $pay;
        $totals['premium'] += $pay;
        $remaining -= $pay;
    }
    unset($schedule);

    // Phase 3: settle rent (with discount rules), only after penalty/premium are cleared
    $priorOutstanding = 0.0;
    foreach ($updatedSchedules as &$schedule) {
        if ($remaining <= PAYMENT_EPSILON) {
            $priorOutstanding += $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
            continue;
        }
        // Do not pay rent on a schedule that still has penalty or premium outstanding
        if ($schedule['pen_out'] > PAYMENT_EPSILON || $schedule['prem_out'] > PAYMENT_EPSILON) {
            $priorOutstanding += $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
            continue;
        }

        $sid = $schedule['schedule_id'];
        if (!isset($allocations[$sid])) {
            $allocations[$sid] = [
                'rent' => 0.0, 'penalty' => 0.0, 'premium' => 0.0,
                'discount' => 0.0, 'current_year_payment' => 0.0, 'total_paid' => 0.0,
            ];
        }
        $alloc = $allocations[$sid];
        $noOutstandingBefore = ($priorOutstanding <= PAYMENT_EPSILON);

        $canApplyDiscount = $discountRate > 0.0
            && $schedule['discount_remaining'] > PAYMENT_EPSILON
            && $schedule['within_window']
            && $noOutstandingBefore
            && $schedule['pen_out'] <= PAYMENT_EPSILON
            && $schedule['prem_out'] <= PAYMENT_EPSILON;

        while ($remaining > PAYMENT_EPSILON && $schedule['rent_out'] > PAYMENT_EPSILON) {
            if ($canApplyDiscount) {
                $discountCap = min($schedule['discount_remaining'], $schedule['rent_out']);
                $maxPaymentWithDiscount = max(0.0, $schedule['rent_out'] - $discountCap);
                if ($maxPaymentWithDiscount <= PAYMENT_EPSILON) {
                    $canApplyDiscount = false;
                    continue;
                }

                $paymentPortion = min($remaining, $maxPaymentWithDiscount);
                if ($paymentPortion <= PAYMENT_EPSILON) {
                    break;
                }

                $ratio = $discountRate >= 0.999 ? 0.0 : $discountRate / (1.0 - $discountRate);
                if ($ratio <= 0.0) {
                    $canApplyDiscount = false;
                    continue;
                }

                $discountForPortion = $paymentPortion * $ratio;
                if ($discountForPortion > $schedule['discount_remaining']) {
                    $discountForPortion = $schedule['discount_remaining'];
                    $paymentPortion = $discountForPortion / $ratio;
                }

                $totalCredit = $paymentPortion + $discountForPortion;
                if ($totalCredit > $schedule['rent_out'] + PAYMENT_EPSILON) {
                    $target = $schedule['rent_out'];
                    $paymentPortion = $target / (1.0 + $ratio);
                    $discountForPortion = $target - $paymentPortion;
                    if ($paymentPortion > $remaining) {
                        $paymentPortion = $remaining;
                        $discountForPortion = min($schedule['discount_remaining'], $paymentPortion * $ratio);
                    }
                }

                if ($paymentPortion <= PAYMENT_EPSILON) {
                    break;
                }

                $remaining -= $paymentPortion;
                $schedule['rent_out'] -= ($paymentPortion + $discountForPortion);
                if ($schedule['rent_out'] < 0.0) {
                    $schedule['rent_out'] = 0.0;
                }
                $schedule['paid_rent'] += $paymentPortion;
                $schedule['discount_apply'] += $discountForPortion;
                $schedule['discount_remaining'] = max(0.0, $schedule['discount_remaining'] - $discountForPortion);

                $alloc['rent'] += $paymentPortion;
                $alloc['discount'] += $discountForPortion;
                $alloc['total_paid'] += $paymentPortion;
                $totals['rent'] += $paymentPortion;
                $totals['discount'] += $discountForPortion;

                if ($sid === $currentScheduleId) {
                    $alloc['current_year_payment'] += $paymentPortion;
                    $totals['current_year_payment'] += $paymentPortion;
                }

                if ($schedule['discount_remaining'] <= PAYMENT_EPSILON) {
                    $canApplyDiscount = false;
                }
                continue;
            }

            $pay = min($remaining, $schedule['rent_out']);
            if ($pay <= PAYMENT_EPSILON) {
                break;
            }

            $remaining -= $pay;
            $schedule['rent_out'] -= $pay;
            if ($schedule['rent_out'] < 0.0) {
                $schedule['rent_out'] = 0.0;
            }
            $schedule['paid_rent'] += $pay;

            $alloc['rent'] += $pay;
            $alloc['total_paid'] += $pay;
            $totals['rent'] += $pay;

            if ($sid === $currentScheduleId) {
                $alloc['current_year_payment'] += $pay;
                $totals['current_year_payment'] += $pay;
            }
        }

        $allocations[$sid] = $alloc;
        $priorOutstanding = $schedule['pen_out'] + $schedule['prem_out'] + $schedule['rent_out'];
    }
    unset($schedule);

    foreach ($allocations as $sid => $alloc) {
        $allocations[$sid] = [
            'rent' => round($alloc['rent'], 2),
            'penalty' => round($alloc['penalty'], 2),
            'premium' => round($alloc['premium'], 2),
            'discount' => round($alloc['discount'], 2),
            'current_year_payment' => round($alloc['current_year_payment'], 2),
            'total_paid' => round($alloc['total_paid'], 2),
        ];
    }

    $totals = [
        'rent' => round($totals['rent'], 2),
        'penalty' => round($totals['penalty'], 2),
        'premium' => round($totals['premium'], 2),
        'discount' => round($totals['discount'], 2),
        'current_year_payment' => round($totals['current_year_payment'], 2),
    ];

    return [
        'allocations' => $allocations,
        'totals' => $totals,
        'schedules' => $updatedSchedules,
        'current_schedule_id' => $currentScheduleId,
        'remaining' => $remaining,
    ];
}
