<?php
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

$lease_id = intval($_POST['lease_id'] ?? 0);
$date     = $_POST['date'] ?? '';
$officers = trim($_POST['officers'] ?? '');   // FIXED NAME
$vstatus  = trim($_POST['status'] ?? '');     // FIXED NAME

// Validate required fields
if ($lease_id <= 0 || $date === '' || $officers === '') {
    echo json_encode(['success'=>false,'message'=>'Missing required fields']);
    exit;
}

$ben_id = 0;
if ($lease_id > 0) {
    $q = "SELECT beneficiary_id FROM leases WHERE lease_id = ? LIMIT 1";
    if ($stmtB = mysqli_prepare($con, $q)) {
        mysqli_stmt_bind_param($stmtB, 'i', $lease_id);
        mysqli_stmt_execute($stmtB);
        $resB = mysqli_stmt_get_result($stmtB);
        if ($resB && ($rowB = mysqli_fetch_assoc($resB))) {
            $ben_id = (int)$rowB['beneficiary_id'];
        }
        mysqli_stmt_close($stmtB);
    }
}



// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
    echo json_encode(['success'=>false,'message'=>'Invalid date format']);
    exit;
}

// Prepared statement
$sql = "INSERT INTO ltl_feald_visits 
        (lease_id, `date`, officers_visited, visite_status, status)
        VALUES (?, ?, ?, ?, 1)";

$stmt = $con->prepare($sql);
$stmt->bind_param("isss", $lease_id, $date, $officers, $vstatus);

if ($stmt->execute()) {
    $new_id = mysqli_insert_id($con);
    if (function_exists('UserLog')) {
        $detail = sprintf('Added field visit: id=%d  | date=%s | officers=%s | status=%s',
            (int)$new_id, (int)$lease_id, $date, $officers, $vstatus);
        UserLog(2,'LTL Add Field Visits', $detail,$ben_id);
    }
    echo json_encode(['success'=>true, 'message'=>'Added']);
} else {
    echo json_encode(['success'=>false, 'message'=>$stmt->error]);
}

$stmt->close();
?>
