<?php
session_start();
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__) . '/ajax/payment_allocator.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {

    /* =========================================================
       1. Resolve Location (Client)
    ========================================================= */
    $location_id = 0;

    if (!empty($_COOKIE['client_cook'])) {
        $stmt = $con->prepare(
            "SELECT c_id FROM client_registration WHERE md5_client=? LIMIT 1"
        );
        $stmt->bind_param("s", $_COOKIE['client_cook']);
        $stmt->execute();
        $stmt->bind_result($location_id);
        $stmt->fetch();
        $stmt->close();
    }

    /* =========================================================
       2. Read Required IDs
    ========================================================= */
    $lease_id       = (int)($_POST['lease_id'] ?? 0);
    $beneficiary_id = (int)($_POST['beneficiary_id'] ?? 0);

    if ($lease_id <= 0) {
        throw new Exception("Invalid Lease ID");
    }

    /* =========================================================
       3. Load Existing Lease (for change detection)
    ========================================================= */
    $oldLease = null;
    $rs = mysqli_query($con, "
        SELECT valuation_amount, start_date, annual_rent_percentage,
               revision_period, revision_percentage, duration_years,
               premium, lease_number, file_number
        FROM leases
        WHERE lease_id = $lease_id
        LIMIT 1
    ");

    if ($rs && mysqli_num_rows($rs)) {
        $oldLease = mysqli_fetch_assoc($rs);
    }

    /* =========================================================
       4. Read Incoming Form Values
    ========================================================= */
    $valuation_amount       = (float)($_POST['valuation_amount'] ?? 0);
    $valuation_date         = $_POST['valuation_date'] ?? '';
    $value_date             = $_POST['value_date'] ?? '';
    $approved_date          = $_POST['approved_date'] ?? '';
    $annual_rent_percentage = (float)($_POST['annual_rent_percentage'] ?? 0);
    $revision_period        = (int)($_POST['revision_period'] ?? 0);
    $revision_percentage    = (float)($_POST['revision_percentage'] ?? 0);
    $start_date             = $_POST['start_date'] ?? '';
    $end_date               = $_POST['end_date'] ?? '';
    $duration_years         = (int)($_POST['duration_years'] ?? 0);
    $lease_type_id          = (int)($_POST['lease_type_id1'] ?? 0);
    $lease_number           = $_POST['lease_number'] ?? '';
    $file_number            = $_POST['file_number'] ?? '';
    $project_name           = $_POST['name_of_the_project'] ?? '';

    /* =========================================================
       5. Detect Changes (for logging)
    ========================================================= */
    $changes = [];

    function detectChange($label, $old, $new, &$changes)
    {
        if ((string)$old !== (string)$new) {
            $changes[] = "$label: $old → $new";
        }
    }

    if ($oldLease) {
        detectChange('Valuation', $oldLease['valuation_amount'], $valuation_amount, $changes);
        detectChange('Annual %', $oldLease['annual_rent_percentage'], $annual_rent_percentage, $changes);
        detectChange('Revision Period', $oldLease['revision_period'], $revision_period, $changes);
        detectChange('Revision %', $oldLease['revision_percentage'], $revision_percentage, $changes);
        detectChange('Duration', $oldLease['duration_years'], $duration_years, $changes);
    }

    /* =========================================================
       6. Count Existing Payments
    ========================================================= */
    $payments_count = 0;
    $stmt = $con->prepare(
        "SELECT COUNT(*) FROM lease_payments WHERE lease_id=? AND status=1"
    );
    $stmt->bind_param("i", $lease_id);
    $stmt->execute();
    $stmt->bind_result($payments_count);
    $stmt->fetch();
    $stmt->close();

    /* =========================================================
       7. Calculate Effective Rent Percentage
    ========================================================= */
    $effective_pct = $annual_rent_percentage;

    if ($lease_type_id > 0) {
        $rs = mysqli_query($con, "
            SELECT base_rent_percent, economy_rate, economy_valuvation
            FROM lease_master
            WHERE lease_type_id = $lease_type_id
            LIMIT 1
        ");

        if ($lm = mysqli_fetch_assoc($rs)) {
            if (
                $valuation_amount <= $lm['economy_valuvation'] &&
                $lm['economy_rate'] > 0
            ) {
                $effective_pct = $lm['economy_rate'];
            } elseif ($lm['base_rent_percent'] > 0) {
                $effective_pct = $lm['base_rent_percent'];
            }
        }
    }

    $annual_rent_percentage = $effective_pct;
    $initial_annual_rent = $valuation_amount * ($annual_rent_percentage / 100);

    /* =========================================================
       8. Calculate Premium (Pre-2020 rule)
    ========================================================= */
    $premium = 0;

    if (strtotime($start_date) < strtotime('2020-01-01')) {
        $rs = mysqli_query($con, "
            SELECT premium_times
            FROM lease_master
            WHERE lease_type_id = $lease_type_id
            LIMIT 1
        ");
        if ($row = mysqli_fetch_assoc($rs)) {
            $premium = $initial_annual_rent * (float)$row['premium_times'];
        }
    }

    /* =========================================================
       9. Decide if Schedules Must Be Rebuilt
    ========================================================= */
    $need_rebuild = false;

    if ($oldLease) {
        $need_rebuild =
            round($oldLease['valuation_amount'], 2) != round($valuation_amount, 2) ||
            $oldLease['start_date'] !== $start_date ||
            round($oldLease['annual_rent_percentage'], 4) != round($annual_rent_percentage, 4) ||
            $oldLease['revision_period'] != $revision_period ||
            round($oldLease['revision_percentage'], 4) != round($revision_percentage, 4) ||
            $oldLease['duration_years'] != $duration_years;
    }

    /* =========================================================
       10. Update Lease Record
    ========================================================= */
    $stmt = $con->prepare("
        UPDATE leases SET
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
        WHERE lease_id=?
    ");

    $uid = $_SESSION['user_id'] ?? 0;

    $stmt->bind_param(
        "iissdsssddidssisii",
        $beneficiary_id, $location_id,
        $lease_number, $file_number,
        $valuation_amount, $valuation_date, $value_date, $approved_date,
        $premium, $annual_rent_percentage,
        $revision_period, $revision_percentage,
        $start_date, $end_date, $duration_years,
        $project_name, $uid, $lease_id
    );

    $stmt->execute();
    $stmt->close();

    /* =========================================================
       11. Schedule Handling
    ========================================================= */
    if ($need_rebuild) {

        rebuildSchedulesAndReapplyPayments(
            $con, $lease_id, $initial_annual_rent,
            $premium, $revision_period,
            $revision_percentage, $start_date, $duration_years
        );

        $note = "Schedules rebuilt & payments replayed";

    } elseif ($payments_count == 0) {

        mysqli_query($con, "DELETE FROM lease_schedules WHERE lease_id=$lease_id");

        generateLeaseSchedules(
            $con, $lease_id, $initial_annual_rent,
            $premium, $revision_period,
            $revision_percentage, $start_date, $duration_years
        );

        $note = "Schedules regenerated (no payments)";
    } else {
        $note = "Payments exist, schedules untouched";
    }

    /* =========================================================
       12. Penalty Calculation
    ========================================================= */
    if (!empty($valuation_date)) {
        $_REQUEST['lease_id'] = $lease_id;
        include __DIR__ . '/../cal_panalty.php';
    }

    /* =========================================================
       13. Logging
    ========================================================= */
    if (!empty($changes) && function_exists('UserLog')) {
        UserLog(
            "2",
            "LTL Lease Updated",
            implode(" | ", $changes),
            $beneficiary_id
        );
    }

    $response['success'] = true;
    $response['message'] = "Lease updated successfully. $note";

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);