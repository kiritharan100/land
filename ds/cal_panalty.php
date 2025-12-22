<?php
include '../db.php';
date_default_timezone_set('Asia/Colombo');

$today = date('Y-m-d');
$xtoday = date('Y-m-d', strtotime('+365 days'));
// $today = date('Y-m-d', strtotime('+365 days'));
//delete today 

// Check if specific lease_id is requested for penalty regeneration
$penalty_calculation = 0;
$lease_id = isset($_REQUEST['lease_id']) ? intval($_REQUEST['lease_id']) : null;

    $leaseQuery = "
        SELECT lease_id, valuation_date, start_date, end_date ,leases.lease_type_id,penalty_rate
        FROM leases
        LEFT JOIN lease_master  ON leases.lease_type_id = lease_master.lease_type_id
        WHERE   lease_id = '$lease_id'
    ";
    $leaseResult = mysqli_query($con, $leaseQuery);
    $lease = mysqli_fetch_assoc($leaseResult);
    $valuation_date = $lease['valuation_date'];
    $penalty_rate = $lease['penalty_rate'];

    echo "valuvation date = $valuation_date\n";
    echo  "<br> penalty rate = $penalty_rate\n";


    // Skip penalty calculation if valuation_date is empty or '0000-00-00'
     if (empty($valuation_date) || $valuation_date == '0000-00-00') {
            $resetNoValuation = "
                UPDATE lease_schedules
                SET panalty = 0,
                    penalty_last_calc = NULL,
                    penalty_remarks = 'No valuation date'
                WHERE lease_id = '$lease_id'
            ";
            mysqli_query($con, $resetNoValuation);  
            echo "Lease ID $lease_id: No valuation date. Penalty reset to 0.\n";
            $penalty_calculation = 1;
        }

        // if penalty rate is zero ignore calculation and update 0% penalty
        if ($penalty_rate == 0 && $penalty_calculation == 0) {
            $resetZeroPenalty = "
                UPDATE lease_schedules
                SET panalty = 0,
                    penalty_last_calc = NULL,
                    penalty_remarks = '0% penalty rate'
                WHERE lease_id = '$lease_id'
            ";
            mysqli_query($con, $resetZeroPenalty);  
            echo " <br> Lease ID $lease_id: 0% penalty rate. Penalty reset to 0.\n";
            $penalty_calculation = 1;
        }

        if($penalty_calculation == 0){
                                
            
                    $resetQuery = "UPDATE lease_schedules SET panalty = '0'  WHERE  lease_id = '$lease_id'  ";
                    mysqli_query($con, $resetQuery);   




                            echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width:100%;">';
                    echo '
                        <tr style="background:#f2f2f2;">
                            <th>Schedule ID</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Premium</th>
                            <th>Premium Paid</th>
                            <th>Annual Rent</th>
                            <th>Rent Paid</th>
                            <th>Cumulative <br> outstanding</th>
                            <th>Penalty Applicable </th>
                            <th>Penalty</th>
                        </tr>
                    ';
                    $cumulative_outstanding = 0;
                    $penalty_year = 0;
                    
echo  $scheduleQuery = "
    SELECT 
        ls.schedule_id,
        ls.start_date,
        ls.end_date,
        ls.premium,
        ls.annual_amount,
        ls.panalty,

        -- summed values from multiple payment rows
        IFNULL(SUM(lp.rent_paid), 0) AS rent_paid,
        IFNULL(SUM(lp.panalty_paid), 0) AS panalty_paid,
        IFNULL(SUM(lp.premium_paid), 0) AS premium_paid,
        IFNULL(SUM(lp.discount_apply), 0) AS discount_apply,
        IFNULL(SUM(w.write_off_amount), 0) AS write_off_amount

    FROM lease_schedules ls
    LEFT JOIN lease_payments lp 
        ON ls.schedule_id = lp.schedule_id and lp.status = 1

     LEFT JOIN ltl_write_off w
        ON ls.schedule_id = w.schedule_id AND w.status = 1

    WHERE ls.lease_id = '$lease_id'
      AND DATE_ADD(ls.start_date, INTERVAL 30 DAY) < '$today'
      AND ls.status = 1

    GROUP BY  ls.schedule_id
    ORDER BY ls.schedule_year
";
 

                    $scheduleResult = mysqli_query($con, $scheduleQuery);

                    if (mysqli_num_rows($scheduleResult) > 0) {

                        while ($schedule = mysqli_fetch_assoc($scheduleResult)) {
                            // $cumulative_outstanding_last_schedule = $cumulative_outstanding;
                            $cumulative_outstanding_last_schedule = max(0, $cumulative_outstanding);
                            $cumulative_outstanding += ($schedule['annual_amount'] + $schedule['premium']- $schedule['rent_paid'] - $schedule['premium_paid'] - $schedule['discount_apply']);
                            
                            echo '<tr>';
                            echo '<td>' . $schedule['schedule_id'] . '</td>';
                            echo '<td>' . $schedule['start_date'] . '</td>';
                            echo '<td>' . $schedule['end_date'] . '</td>';
                            echo '<td style="text-align:right;">' . number_format($schedule['premium'], 2) . '</td>';
                            echo '<td style="text-align:right;">' . number_format($schedule['premium_paid'], 2) . '</td>';
                            echo '<td style="text-align:right;">' . number_format($schedule['annual_amount'], 2) . '</td>';
                            echo '<td style="text-align:right;">' . number_format($schedule['rent_paid'], 2) . '</td>';
                            echo '<td style="text-align:right;">' . number_format($cumulative_outstanding, 2) . '</td>';
                            
                                    if( $schedule['end_date'] > $valuation_date  ){ echo "<td>Y-".$schedule['write_off_amount']."</td>";  } else { echo "<td></td>"; }


                            


                            if( $schedule['end_date'] > $valuation_date  ){   
                                echo "<td>$penalty_year</td>";
                                if ($penalty_year > 0) {
                                    $write_off_amount = $schedule['write_off_amount'];
                                    $penalty_amount = ($cumulative_outstanding_last_schedule * ($penalty_rate / 100))-$write_off_amount;
                                    
                             
                                $updatePenaltyQuery = "
                                    UPDATE lease_schedules
                                    SET panalty = '$penalty_amount',
                                        penalty_last_calc = '$today',
                                        penalty_remarks = 'Penalty calculated on $today'
                                    WHERE schedule_id = '" . $schedule['schedule_id'] . "'  
                                ";
                                mysqli_query($con, $updatePenaltyQuery);    
                                }
                                $penalty_year ++;
                             } 
                             echo '<td style="text-align:right;">' . number_format($penalty_amount, 2) . '</td>';
                             
                            
                           


                            echo '</tr>';
                        }

                    } else {
                        echo '<tr><td colspan="6" style="text-align:center; padding:10px;">No records found</td></tr>';
                    }

                    echo '</table>';

            
        }


        
 