<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

$response = ['success'=>false,'message'=>''];
try{
  // Resolve location_id from cookie
  $location_id = 0;
  if(isset($_COOKIE['client_cook'])){
    $selected_client = $_COOKIE['client_cook'];
    $sel_query = "SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1";
    if ($stmtC = mysqli_prepare($con, $sel_query)){
      mysqli_stmt_bind_param($stmtC, 's', $selected_client);
      mysqli_stmt_execute($stmtC);
      $resC = mysqli_stmt_get_result($stmtC);
      if ($resC && ($rowC = mysqli_fetch_assoc($resC))) { $location_id = (int)$rowC['c_id']; }
      mysqli_stmt_close($stmtC);
    }
  }

  $land_id = isset($_POST['land_id']) ? (int)$_POST['land_id'] : 0;
  $beneficiary_id = isset($_POST['beneficiary_id']) ? (int)$_POST['beneficiary_id'] : 0;
  if ($land_id<=0 || $beneficiary_id<=0) { throw new Exception('Missing land or beneficiary'); }

  $valuation_amount = floatval($_POST['valuation_amount'] ?? 0);
  $valuation_date = $_POST['valuation_date'] ?? '';
  $value_date = $_POST['value_date'] ?? '';
  $approved_date = $_POST['approved_date'] ?? '';
  $first_lease = isset($_POST['first_lease']) ? (int)$_POST['first_lease'] : 1;
  $last_lease_annual_value = isset($_POST['last_lease_annual_value']) ? floatval($_POST['last_lease_annual_value']) : 0.0;
  if ($first_lease !== 0) {
    $first_lease = 1;
    $last_lease_annual_value = 0.0;
  }
   
  $annual_rent_percentage = floatval($_POST['annual_rent_percentage'] ?? 0);
  $revision_period = (int)($_POST['revision_period'] ?? 0);
  $initial_annual_rent = $_POST['initial_annual_rent']  ?? 0 ;
  $revision_percentage = floatval($_POST['revision_percentage'] ?? 0);
  $lease_start_date = $start_date = $_POST['start_date'] ?? '';
  $end_date = $_POST['end_date'] ?? '';
  $duration_years = (int)($_POST['duration_years'] ?? 0);
  $lease_type_id = isset($_POST['lease_type_id']) ? (int)$_POST['lease_type_id'] : 0;
  $type_of_project = isset($_POST['type_of_project']) ? mysqli_real_escape_string($con, $_POST['type_of_project']) : '';
  $name_of_the_project = isset($_POST['name_of_the_project']) ? mysqli_real_escape_string($con, $_POST['name_of_the_project']) : '';
  $premium_input = isset($_POST['premium']) ? floatval(str_replace(',', '', $_POST['premium'])) : 0.0;

  $lease_number = isset($_POST['lease_number']) && trim($_POST['lease_number']) !== '' ? mysqli_real_escape_string($con, $_POST['lease_number']) : ("LEASE-" . date('Ymd-His'));
  $file_number = isset($_POST['file_number']) && trim($_POST['file_number']) !== '' ? mysqli_real_escape_string($con, $_POST['file_number']) : $lease_number;

  // Server-side safeguard: derive effective annual % based on valuation vs economy threshold


  $effective_pct = $annual_rent_percentage;
  // if ($lease_type_id > 0) {
  //   $q = "SELECT base_rent_percent, economy_rate, economy_valuvation FROM lease_master WHERE lease_type_id=$lease_type_id LIMIT 1";
  //   if ($rs = mysqli_query($con, $q)) {
  //     if ($lm = mysqli_fetch_assoc($rs)) {
  //       $base_pct = isset($lm['base_rent_percent']) ? floatval($lm['base_rent_percent']) : 0.0;
  //       $eco_rate = isset($lm['economy_rate']) ? floatval($lm['economy_rate']) : 0.0;
  //       $eco_val  = isset($lm['economy_valuvation']) ? floatval($lm['economy_valuvation']) : 0.0;
  //       if ($valuation_amount > 0 && $eco_val > 0 && $eco_rate > 0 && $valuation_amount <= $eco_val) {
  //         $effective_pct = $eco_rate;
  //       } else {
  //         $effective_pct = $base_pct > 0 ? $base_pct : $annual_rent_percentage;
  //       }
  //     }
  //     mysqli_free_result($rs);
  //   }
  // }
  // $annual_rent_percentage = $effective_pct;


  // $initial_annual_rent = $valuation_amount * ($annual_rent_percentage / 100.0);
  $premium = 0.0;
  if (!empty($start_date) && strtotime($start_date) < strtotime('2020-01-01')) {

     $sql = "SELECT * FROM lease_master WHERE lease_type_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $lease_type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $premium_times = floatval($row['premium_times']); 
        } else {
            $premium_times = 0.00;  // default if not found.
        }


    $premium = $initial_annual_rent * $premium_times;
  }

    $sql = "INSERT INTO leases (land_id, initial_annual_rent,beneficiary_id, location_id, lease_number, file_number, valuation_amount, valuation_date, value_date, approved_date, premium, annual_rent_percentage, revision_period, revision_percentage, start_date, end_date, duration_years, lease_type_id, type_of_project, name_of_the_project, first_lease, last_lease_annual_value, created_by, status, created_on)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
  if ($stmt = mysqli_prepare($con, $sql)){
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
      mysqli_stmt_bind_param($stmt, 'idiissdsssddidssiissidi',
        $land_id,$initial_annual_rent ,$beneficiary_id, $location_id, $lease_number, $file_number,
        $valuation_amount, $valuation_date, $value_date, $approved_date, $premium, $annual_rent_percentage, $revision_period,
        $revision_percentage, $start_date, $end_date, $duration_years, $lease_type_id,
        $type_of_project, $name_of_the_project, $first_lease, $last_lease_annual_value, $uid
      );
    if (!mysqli_stmt_execute($stmt)){
      throw new Exception('Error creating lease: ' . mysqli_error($con));
    }
    $lease_id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    // Generate schedules
    generateLeaseSchedules($lease_id, $initial_annual_rent, $premium, $revision_period, $revision_percentage, $start_date, $duration_years
          ,$first_lease,
          $valuation_amount,
          $annual_rent_percentage,
          $lease_type_id,
          $last_lease_annual_value);

    // Initialize penalties using existing script (buffer output)
    // try {
    //   $_REQUEST['lease_id'] = $lease_id;
    //   ob_start();
    //   include __DIR__ . '/../cal_panalty.php';
    //   ob_end_clean();
    // } catch (Exception $e) { /* non-fatal */ }
    if (!empty($valuation_date) && $valuation_date != '0000-00-00') {
    try {
        $_REQUEST['lease_id'] = $lease_id;
        ob_start();
        include __DIR__ . '/../cal_panalty.php';
        ob_end_clean();
    } catch (Exception $e) {
        // non-fatal
    }
  }
 


    if (function_exists('UserLog')) { @UserLog(2, 'LTL Create Lease', 'Created lease: ' . $lease_number.' File No: ' . $file_number,$beneficiary_id); }

    $response['success'] = true;
    $response['lease_id'] = $lease_id;
    $response['message'] = 'Lease created successfully!';
  } else {
    throw new Exception('DB error: ' . mysqli_error($con));
  }
} catch (Exception $ex){
  $response['success'] = false;
  $response['message'] = $ex->getMessage();
}

echo json_encode($response);


function generateLeaseSchedules(
    $lease_id,
    $initial_rent,
    $premium,
    $revision_period,
    $revision_percentage,
    $start_date,
    $duration_years = 30,
    $first_lease,
    $valuation_amount,
    $annual_rent_percentage,
    $lease_type_id,
    $last_lease_annual_value
){
    global $con;

    $start_ts    = strtotime($start_date);
    if (!$start_ts) {
        throw new Exception("Invalid start_date for schedule generation");
    }

    $boundary_ts = strtotime('2020-01-01');
    $start_year  = (int)date('Y', $start_ts);
    $duration    = (int)$duration_years;

    $current_rent    = (float)$initial_rent;
    $revision_number = 0;

    // --------------------------------------------
    // APPLY RULE: Pre-2020 50% every 5 years ONLY if revision_period > 0
    // --------------------------------------------
    $use_pre_rules = ($start_ts < $boundary_ts && $revision_period > 0);

    $pre_period_years = 5;        // fixed rule
    $pre_pct          = 50.0;     // 50% increment

    $post_period_years = ($revision_period > 0) ? (int)$revision_period : 0;
    $post_pct          = (float)$revision_percentage;

    // Determine FIRST revision timestamp
    if ($use_pre_rules) {
        $next_rev_ts = strtotime("+{$pre_period_years} years", $start_ts);
    } else {
        $next_rev_ts = ($post_period_years > 0)
            ? strtotime("+{$post_period_years} years", $start_ts)
            : null;
    }




       //new change for 2020
            $first_lease  = $first_lease ;
            $valuation_amount = $valuation_amount;
            $start_date = $start_date;
            $annual_rent_percentage = $annual_rent_percentage;
            $lease_type_id =$lease_type_id;
            $sql_master_lease = "
                SELECT economy_valuvation, economy_rate
                FROM lease_master
                WHERE lease_type_id = $lease_type_id
            ";
            $result_master_lease = mysqli_query($con, $sql_master_lease);
            if ($result_master_lease && mysqli_num_rows($result_master_lease) > 0) {
                $lease_master = mysqli_fetch_assoc($result_master_lease);
                $economy_rate       = (float)$lease_master['economy_rate'];
                $economy_valuvation = (float)$lease_master['economy_valuvation'];
            } else { 
                $economy_rate = 0;
                $economy_valuvation = 0;
            }
           if($valuation_amount <= $economy_valuvation && $economy_rate < $annual_rent_percentage && $economy_rate > 0) {
            $economy_rate_applicable = 1; 
                if($first_lease == 1){
                    $revised_initital_rent =  $valuation_amount * $economy_rate /100;
                }  else {
                    $revised_initital_rent = $last_lease_annual_value;
                }  
           } else {
            $economy_rate_applicable = 0;
            $revised_initital_rent = 0;
           } //new change for 2020
                          //  $resultxx = "val_amount-".$valuation_amount."ecoValuvation-".$economy_valuvation."ecoreate-".$economy_rate."rent.".$annual_rent_percentage ;

    // --------------------------------------------
    // LOOP THROUGH EACH YEAR
    // --------------------------------------------
    for ($year = 0; $year < $duration; $year++) {

        $year_start_ts = strtotime("+{$year} years", $start_ts);
        $year_end_ts   = strtotime("+1 year -1 day", $year_start_ts);

        $schedule_year   = (int)date('Y', $year_start_ts);
        $year_start_date = date('Y-m-d', $year_start_ts);
        $year_end_date   = date('Y-m-d', $year_end_ts);
        $due_date        = date('Y-m-d', strtotime($schedule_year . '-03-31'));

        $is_revision_year = 0;

        // --------------------------------------------
        // APPLY INCREMENT AT REVISION POINTS
        // --------------------------------------------
        if ($next_rev_ts && $year_start_ts >= $next_rev_ts) {

            $is_revision_year = 1;
            $revision_number++;

            // Which rule applies?
            $applied_pre_rule = ($use_pre_rules && $next_rev_ts < $boundary_ts);

            if ($applied_pre_rule) {
                // Apply 50% increment
                $current_rent *= 1.50;

                // Compute next revision point
                $candidate = strtotime("+{$pre_period_years} years", $next_rev_ts);

                if ($candidate < $boundary_ts) {
                    $next_rev_ts = $candidate;  // keep pre rules
                } else {
                    // switch to post-2020 rule
                    $next_rev_ts = ($post_period_years > 0)
                        ? strtotime("+{$post_period_years} years", $next_rev_ts)
                        : null;
                }

            } else {
                // APPLY POST-2020 Rule
                if ($post_pct > 0) {
                    $current_rent *= (1 + ($post_pct / 100.0));
                }

                $next_rev_ts = ($post_period_years > 0)
                    ? strtotime("+{$post_period_years} years", $next_rev_ts)
                    : null;
            }
        }

        // --------------------------------------------
        // FIRST YEAR PREMIUM (pre-2020 only)
        // --------------------------------------------
        $first_year_premium = ($year === 0 && $start_ts < $boundary_ts)
            ? $premium
            : 0.0;


                  //new change for 2020
                if($economy_rate_applicable > 0){
                    if($is_revision_year == 1){
                        $revised_initital_rent = $revised_initital_rent * 1.2;
                        }else{
                        $revised_initital_rent = $revised_initital_rent;
                        }

                    if($schedule_year == 2020){
                        $current_rent = $revised_initital_rent;
                    } 
                }
               //new change for 2020
        // --------------------------------------------
        // INSERT SCHEDULE ROW
        // --------------------------------------------
        $sql = "INSERT INTO lease_schedules (
                    lease_id,
                    schedule_year,
                    start_date,
                    end_date,
                    due_date,
                    base_amount,
                    premium,
                    premium_paid,
                    annual_amount,
                    revision_number,
                    is_revision_year,
                    status,
                    created_on
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', NOW()
                )";

        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param(
                $st,
                'iisssdddii',
                $lease_id,
                $schedule_year,
                $year_start_date,
                $year_end_date,
                $due_date,
                $initial_rent,
                $first_year_premium,
                $current_rent,
                $revision_number,
                $is_revision_year
            );

            if (!mysqli_stmt_execute($st)) {
                $err = mysqli_error($con);
                mysqli_stmt_close($st);
                throw new Exception("Schedule generation failed: " . $err);
            }

            mysqli_stmt_close($st);

        } else {
            throw new Exception("Schedule statement prepare error: " . mysqli_error($con));
        }
    }

    return true;
}



// function generateLeaseSchedules($lease_id, $initial_rent, $premium, $revision_period, $revision_percentage, $start_date, $duration_years = 30){
//   global $con;
//   $start_ts = strtotime($start_date);
//   $boundary_ts = strtotime('2020-01-01');
//   $start_year = (int)date('Y', $start_ts);
//   $duration = (int)$duration_years;
//   $current_rent = (float)$initial_rent;
//   $revision_number = 0;

//   // Determine first revision date and rule set
//   $use_pre_rules = ($start_ts < $boundary_ts);
//   $pre_period_years = 5; $pre_pct = 50.0;
//   $post_period_years = max(0, (int)$revision_period); $post_pct = (float)$revision_percentage;
//   // next revision date based on start and appropriate rule
//   if ($use_pre_rules) {
//     $next_rev_ts = strtotime('+' . $pre_period_years . ' years', $start_ts);
//   } else {
//     $next_rev_ts = $post_period_years > 0 ? strtotime('+' . $post_period_years . ' years', $start_ts) : null;
//   }

//   for ($year=0; $year<$duration; $year++){
//     $year_start_ts = strtotime('+' . $year . ' years', $start_ts);
//     $year_end_ts = strtotime('+1 year -1 day', $year_start_ts);
//     $schedule_year = (int)date('Y', $year_start_ts);
//     $year_start_date = date('Y-m-d', $year_start_ts);
//     $year_end_date = date('Y-m-d', $year_end_ts);
//     $due_date = date('Y-m-d', strtotime($schedule_year . '-03-31'));

//     $is_revision_year = 0;
//     // Apply revisions when crossing next revision threshold
//     if ($next_rev_ts && $year_start_ts >= $next_rev_ts){
//       $is_revision_year = 1;
//       $revision_number++;
//       // Decide which rule applies for this revision
//       $applied_rule_pre = ($next_rev_ts < $boundary_ts);
//       $pct_to_apply = $applied_rule_pre ? $pre_pct : $post_pct;
//       if ($pct_to_apply > 0) {
//         $current_rent = $current_rent * (1 + ($pct_to_apply / 100.0));
//       }
//       // Move to the next revision date from the date just applied
//       if ($applied_rule_pre) {
//         // If the next computed revision still falls before boundary, continue with pre rules; otherwise switch to post rules
//         $candidate = strtotime('+' . $pre_period_years . ' years', $next_rev_ts);
//         if ($candidate < $boundary_ts) {
//           $next_rev_ts = $candidate;
//         } else {
//           $next_rev_ts = $post_period_years > 0 ? strtotime('+' . $post_period_years . ' years', $next_rev_ts) : null;
//         }
//       } else {
//         $next_rev_ts = $post_period_years > 0 ? strtotime('+' . $post_period_years . ' years', $next_rev_ts) : null;
//       }
//     }

//     $first_year_premium = ($year === 0 && $start_ts < $boundary_ts) ? $premium : 0.0;
//     $sql = "INSERT INTO lease_schedules (lease_id, schedule_year, start_date, end_date, due_date, base_amount, premium, premium_paid, annual_amount, revision_number, is_revision_year, status, created_on)
//             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', NOW())";
//     if ($st = mysqli_prepare($con, $sql)){
//       mysqli_stmt_bind_param($st, 'iisssdddii', $lease_id, $schedule_year, $year_start_date, $year_end_date, $due_date, $initial_rent, $first_year_premium, $current_rent, $revision_number, $is_revision_year);
//       if (!mysqli_stmt_execute($st)){
//         $err = mysqli_error($con);
//         mysqli_stmt_close($st);
//         throw new Exception('Schedule generation failed: ' . $err);
//       }
//       mysqli_stmt_close($st);
//     } else {
//       throw new Exception('Schedule statement error: ' . mysqli_error($con));
//     }
//   }
// }