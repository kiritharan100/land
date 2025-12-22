<?php
include '../db.php';
date_default_timezone_set('Asia/Colombo');

$today = date('Y-m-d');
$xtoday = date('Y-m-d', strtotime('+365 days'));
// $today = date('Y-m-d', strtotime('+365 days'));
//delete today 


// Check if specific lease_id is requested for penalty regeneration
$specific_lease_id = isset($_REQUEST['lease_id']) ? intval($_REQUEST['lease_id']) : null;

// Fetch active leases with their valuation date
if ($specific_lease_id) {
    // For specific lease, regenerate penalty even if already calculated
    $leaseQuery = "
        SELECT lease_id, valuation_date, start_date, end_date
        FROM leases
        WHERE status = 1 AND lease_id = '$specific_lease_id'
    ";
} else {
    // For all leases, only calculate if not already done
    $leaseQuery = "
        SELECT lease_id, valuation_date, start_date, end_date
        FROM leases
        WHERE status = 1
    ";
}
$leaseResult = mysqli_query($con, $leaseQuery);

while ($lease = mysqli_fetch_assoc($leaseResult)) {
    $lease_id = $lease['lease_id'];
    $valuation_date = $lease['valuation_date'];



    // Skip penalty calculation if valuation_date is empty or '0000-00-00'
    if (empty($valuation_date) || $valuation_date == '0000-00-00') {

    // Reset penalty for all schedules of this lease
    $resetNoValuation = "
        UPDATE lease_schedules
        SET panalty = 0,
            penalty_last_calc = NULL,
            penalty_remarks = 'No valuation date — penalty not applicable'
        WHERE lease_id = '$lease_id'
    ";
    mysqli_query($con, $resetNoValuation);

    // Skip this lease completely
    continue;
}








    // Compute first penalty date as 1 year after valuation_date if available
    if (!empty($valuation_date) && strtotime($valuation_date) !== false) {
        $first_penalty_date = date('Y-m-d', strtotime('+0 year', strtotime($valuation_date)));
        
    } else {
        // no valuation date available — disable first_penalty_date check
        $first_penalty_date = null;
    }

    // Determine which field to treat as 'from_date' for schedules: prefer 'from_date' column if present, else fall back to 'start_date'
    $from_field = 'start_date';
    $colCheck = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lease_schedules' AND column_name = 'from_date'");
    if ($colCheck) {
        $colCountRow = mysqli_fetch_assoc($colCheck);
        if (!empty($colCountRow['cnt']) && intval($colCountRow['cnt']) > 0) {
            $from_field = 'from_date';
        }
    }

    // If specific lease_id requested, reset existing penalties first
    if ($specific_lease_id && $specific_lease_id == $lease_id) {
        $resetQuery = "
            UPDATE lease_schedules 
            SET panalty = 0, 
                penalty_last_calc = NULL, 
                penalty_remarks = NULL 
            WHERE lease_id = '$lease_id'
        ";
        mysqli_query($con, $resetQuery);
    }

    // Get related schedules that need penalty calculation based on end_date
    if ($specific_lease_id && $specific_lease_id == $lease_id) {
        // For specific lease, calculate only for schedules whose end_date is before today (past schedules)
        $scheduleQuery = "
            SELECT * FROM lease_schedules
            WHERE lease_id = '$lease_id'
              AND DATE_ADD(start_date, INTERVAL 30 DAY) < '$today'
              AND status = 1
            ORDER BY schedule_year
        ";
    } else {
        // For bulk calculation, only calculate where penalty is not set and end_date is before today
        $scheduleQuery = "
            SELECT * FROM lease_schedules
            WHERE lease_id = '$lease_id'
              AND DATE_ADD(start_date, INTERVAL 30 DAY) < '$today'
              AND status = 1
              AND (panalty = 0 OR panalty IS NULL)
            ORDER BY schedule_year
        ";
    }
    $scheduleResult = mysqli_query($con, $scheduleQuery);

    while ($sch = mysqli_fetch_assoc($scheduleResult)) {
        $schedule_id = $sch['schedule_id'];
        $current_due_date = $sch['end_date'];
        $current_year = $sch['schedule_year'];
        $penalty_rate = !empty($sch['penalty_rate']) ? $sch['penalty_rate'] : 10;
        
        // Calculate cumulative outstanding from all schedules before or on this due_date
        $cumulativeQuery = "
            SELECT 
                SUM(annual_amount) as total_annual_amount,
                SUM(paid_rent) as total_paid_rent,
                (SUM(annual_amount) - SUM(paid_rent)) as cumulative_outstanding,
                (SUM(premium)-SUM(premium_paid)) as cumulative_premium_outstanding

            FROM lease_schedules
            WHERE lease_id = '$lease_id'
              AND end_date <= '$current_due_date'
              AND status = 1
        ";
        $cumulativeResult = mysqli_query($con, $cumulativeQuery);
        $cumulativeData = mysqli_fetch_assoc($cumulativeResult);
        $cumulative_outstanding = $cumulativeData['cumulative_outstanding']+$cumulativeData['cumulative_premium_outstanding'];

    // Check if this schedule end_date is after the first penalty date (if set), in the past (before today), and there is cumulative outstanding
    if ($first_penalty_date && strtotime($sch['end_date']) > strtotime($first_penalty_date) && strtotime($sch['end_date']) < strtotime($today) && $cumulative_outstanding > 0) {
            // Find the next schedule after this schedule's end (use from_field/start_date)
            $nextScheduleQuery = "SELECT s_next.schedule_id, s_next." . $from_field . " AS next_from, s_next.end_date AS next_end_date
                                  FROM lease_schedules s_next
                                  WHERE s_next.lease_id = '$lease_id'
                                    AND s_next." . $from_field . " > '" . $sch['end_date'] . "'
                                    AND s_next.status = 1";
            if (!($specific_lease_id && $specific_lease_id == $lease_id)) {
                $nextScheduleQuery .= " AND (s_next.panalty = 0 OR s_next.panalty IS NULL)";
            }
            $nextScheduleQuery .= " ORDER BY s_next." . $from_field . " ASC LIMIT 1";
            $nextScheduleResult = mysqli_query($con, $nextScheduleQuery);

            if ($nextScheduleRow = mysqli_fetch_assoc($nextScheduleResult)) {
                $next_schedule_id = $nextScheduleRow['schedule_id'];
                $next_end_date = $nextScheduleRow['next_end_date'];

                // Only apply penalty to the target schedule if its end_date is not in the future
                if (!empty($next_end_date) && strtotime($next_end_date) <= strtotime($xtoday)) {
                    // Calculate penalty on cumulative outstanding amount
                    $penalty = round($cumulative_outstanding * ($penalty_rate / 100), 2);

                    // Update penalty for the NEXT schedule
                    $update = "UPDATE lease_schedules
                               SET panalty = '$penalty',
                                   penalty_last_calc = '$xtoday',
                                   penalty_remarks = 'Penalty from Year $current_year - Cumulative Outstanding: $cumulative_outstanding'
                               WHERE schedule_id = '$next_schedule_id' AND  DATE_ADD(start_date, INTERVAL 30 DAY) < '$today'";
                    mysqli_query($con, $update);
                }
            }
        }
    }

    // Carry first year unpaid penalty to second year schedule - based on cumulative outstanding
    // Determine which field to treat as 'from_date' for schedules: prefer 'from_date' column if present, else fall back to 'start_date'
    $from_field = 'start_date';
    $colCheck = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lease_schedules' AND column_name = 'from_date'");
    if ($colCheck) {
        $colCountRow = mysqli_fetch_assoc($colCheck);
        if (!empty($colCountRow['cnt']) && intval($colCountRow['cnt']) > 0) {
            $from_field = 'from_date';
        }
    }

    // Find the next schedule whose from_date/start_date is after the first penalty date
    $carryResult = null;
    if ($first_penalty_date) {
        $carryBase = "SELECT s2.schedule_id AS next_schedule, s2." . $from_field . " AS next_from_date
                      FROM lease_schedules s2
                      WHERE s2.lease_id = '$lease_id'
                        AND s2." . $from_field . " > '$first_penalty_date'
                        AND s2.status = 1";

        // For specific lease regeneration, allow overwriting existing penalty
        if (!($specific_lease_id && $specific_lease_id == $lease_id)) {
            $carryBase .= " AND (s2.panalty = 0 OR s2.panalty IS NULL)";
        }

        $carryBase .= " ORDER BY s2." . $from_field . " ASC LIMIT 1";
        $carryResult = mysqli_query($con, $carryBase);
    }

    if ($carryResult && ($carryRow = mysqli_fetch_assoc($carryResult))) {
        // Calculate cumulative outstanding up to the next schedule's from_date
        $carryDate = $carryRow['next_from_date'];
        if (!empty($carryDate)) {
            $cumulativeCarryQuery = "
                SELECT (SUM(annual_amount) - SUM(paid_rent)) AS cumulative_outstanding_carry
                FROM lease_schedules
                WHERE lease_id = '$lease_id'
                  AND end_date <= '" . $carryDate . "'
                  AND status = 1
            ";
            $cumulativeCarryResult = mysqli_query($con, $cumulativeCarryQuery);
            $cumulativeCarryData = mysqli_fetch_assoc($cumulativeCarryResult);
            $cumulativeOutstanding = $cumulativeCarryData['cumulative_outstanding_carry'];

            if ($cumulativeOutstanding > 0) {
                $carryPenalty = round($cumulativeOutstanding * ($penalty_rate / 100), 2);
                mysqli_query($con, "
                    UPDATE lease_schedules
                    SET panalty = '$carryPenalty',
                        penalty_last_calc = '$today',
                        penalty_remarks = 'Carry penalty - Cumulative Outstanding: $cumulativeOutstanding'
                    WHERE schedule_id = '{$carryRow['next_schedule']}' and DATE_ADD(start_date, INTERVAL 30 DAY) < '$today'
                ");
            }
        }
    }
}

if ($specific_lease_id) {
   echo "Penalty calculation completed for lease ID: $specific_lease_id on $today.";
   echo ".";
  
} else {
   echo "Penalty calculation completed for all leases on $today.";
}
?>