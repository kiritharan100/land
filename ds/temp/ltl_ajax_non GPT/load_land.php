<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';

$land_id = isset($_GET['land_id']) ? (int)$_GET['land_id'] : 0;
$ben_id = isset($_GET['ben_id']) ? (int)$_GET['ben_id'] : 0;

// Helper to ensure extent_ha is populated using the same conversion factors used in the UI
function with_extent_ha(array $row): array {
    if (!isset($row['extent_ha']) || $row['extent_ha'] === '' || $row['extent_ha'] === null) {
        $factors = [
            'hectares' => 1,
            'sqft' => 0.0000092903,
            'sqyd' => 0.0000836127,
            'perch' => 0.0252929,
            'rood' => 0.1011714,
            'acre' => 0.4046856,
            'cent' => 0.00404686,
            'ground' => 0.0023237,
            'sqm' => 0.0001
        ];
        $extent = isset($row['extent']) ? floatval($row['extent']) : 0;
        $unit = isset($row['extent_unit']) ? strtolower((string)$row['extent_unit']) : 'hectares';
        $ha = $extent * ($factors[$unit] ?? 1);
        $row['extent_ha'] = $extent ? number_format($ha, 6, '.', '') : '';
    }
    return $row;
}

if ($ben_id > 0) {
    // Load latest record for this beneficiary
    $sql = "SELECT land_id, ben_id, ds_id, gn_id, land_address, landBoundary, status, developed_status, sketch_plan_no, plc_plan_no, survey_plan_no, extent, extent_unit, extent_ha
            FROM ltl_land_registration WHERE ben_id = ? ORDER BY land_id DESC LIMIT 1";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $ben_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $row = with_extent_ha($row);
            if (!isset($row['developed_status']) || $row['developed_status'] === null || $row['developed_status'] === '') {
                $row['developed_status'] = 'Not Developed';
            }
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No land record for beneficiary']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($land_id > 0) {
    $sql = "SELECT land_id, ben_id, ds_id, gn_id, land_address, landBoundary, status, developed_status, sketch_plan_no, plc_plan_no, survey_plan_no, extent, extent_unit, extent_ha
            FROM ltl_land_registration WHERE land_id = ? LIMIT 1";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $land_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $row = with_extent_ha($row);
            if (!isset($row['developed_status']) || $row['developed_status'] === null || $row['developed_status'] === '') {
                $row['developed_status'] = 'Not Developed';
            }
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($con)]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Provide ben_id or land_id']);
