<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}

$land_id = isset($_POST['land_id']) && $_POST['land_id'] !== '' ? (int)$_POST['land_id'] : null;
$ben_id = isset($_POST['ben_id']) ? (int)$_POST['ben_id'] : 0;
$ds_id = isset($_POST['ds_id']) ? (int)$_POST['ds_id'] : 0;
$gn_id = isset($_POST['gn_id']) && $_POST['gn_id'] !== '' ? (int)$_POST['gn_id'] : null;
$land_address = trim($_POST['land_address'] ?? '');
$landBoundary = trim($_POST['landBoundary'] ?? ''); // Expect JSON string
$sketch_plan_no = trim($_POST['sketch_plan_no'] ?? '');
$plc_plan_no = trim($_POST['plc_plan_no'] ?? '');
$survey_plan_no = trim($_POST['survey_plan_no'] ?? '');
$extent = trim($_POST['extent'] ?? '');
$extent_unit = trim($_POST['extent_unit'] ?? 'hectares');
$extent_ha = trim($_POST['extent_ha'] ?? '');
// Development status (Not Developed by default)
$developed_status = trim($_POST['developed_status'] ?? '');
if ($developed_status === '') { $developed_status = 'Not Developed'; }
// Whitelist values
$allowed_statuses = ['Not Developed', 'Partially Developed', 'Developed'];
if (!in_array($developed_status, $allowed_statuses, true)) {
    $developed_status = 'Not Developed';
}
// Fallback server-side calculation if client didn't send extent_ha
if ($extent_ha === '' && $extent !== '') {
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
    $val = floatval($extent) * ($factors[$extent_unit] ?? 1);
    $extent_ha = $extent !== '' ? number_format($val, 6, '.', '') : '';
}
// UI hides status; set a safe default on insert if DB requires it
$status = 'Active';

if ($ben_id <= 0) json_error('Beneficiary (ben_id) is required');
if ($ds_id <= 0) json_error('DS Division is required');
if ($land_address === '') json_error('Land address is required');

// Validate boundary JSON if provided
if ($landBoundary !== '') {
    json_decode($landBoundary, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid land boundary format');
    }
}

try {
    if ($land_id === null) {
        // INSERT
        $sql = "INSERT INTO ltl_land_registration (ben_id, ds_id, gn_id, land_address, landBoundary, status, developed_status, sketch_plan_no, plc_plan_no, survey_plan_no, extent, extent_unit, extent_ha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) json_error('DB error (prepare insert): ' . mysqli_error($con));
        // 3 ints + 10 strings
        mysqli_stmt_bind_param($stmt, 'iiissssssssss', $ben_id, $ds_id, $gn_id, $land_address, $landBoundary, $status, $developed_status, $sketch_plan_no, $plc_plan_no, $survey_plan_no, $extent, $extent_unit, $extent_ha);
        if (!mysqli_stmt_execute($stmt)) {
            json_error('DB error (execute insert): ' . mysqli_stmt_error($stmt));
        }
        $new_id = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        UserLog(
        "2",
        "LTL Add Land",
        "Land ID=$new_id | Ben ID=$ben_id  | Address=$land_address | Extent=$extent $extent_unit",$ben_id
    );

        echo json_encode(['success' => true, 'message' => 'Land information saved', 'land_id' => $new_id]);
        
        
        
        exit;
    } else {


 
    // ------------------------------
    // STEP 1: Fetch OLD land record
    // ------------------------------
    $old = mysqli_fetch_assoc(
        mysqli_query($con, "SELECT * FROM ltl_land_registration WHERE land_id = $land_id")
    );

    if (!$old) json_error("Old land record not found");

    // ------------------------------
    // STEP 2: Build NEW values array
    // ------------------------------
    $new = [
        'ben_id'           => $ben_id,
        'ds_id'            => $ds_id,
        'gn_id'            => $gn_id,
        'land_address'     => $land_address,
        'landBoundary'     => $landBoundary,
        'developed_status' => $developed_status,
        'sketch_plan_no'   => $sketch_plan_no,
        'plc_plan_no'      => $plc_plan_no,
        'survey_plan_no'   => $survey_plan_no,
        'extent'           => $extent,
        'extent_unit'      => $extent_unit,
        'extent_ha'        => $extent_ha
    ];

    // ------------------------------
    // STEP 3: Normalization function
    // ------------------------------
    function normalizeL($v) {
        if ($v === null) return "";
        $v = (string)$v;
        // remove hidden unicode whitespace
        $v = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $v);
        // replace line breaks/tabs with space
        $v = str_replace(["\r","\n","\t"], " ", $v);
        // collapse multiple spaces
        $v = preg_replace('/\s+/u', ' ', $v);
        return trim($v);
    }

    // ------------------------------
    // STEP 4: Detect CHANGES only
    // ------------------------------
    $changes = [];

    foreach ($new as $field => $new_value_raw) {
        $old_value_raw = $old[$field] ?? '';

        $oldV = normalizeL($old_value_raw);
        $newV = normalizeL($new_value_raw);

        if ($oldV !== $newV) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $changes[] = "$label: $oldV > $newV";
        }
    }

    // Convert changes to text
    $change_text = count($changes) ? implode(" | ", $changes) : "";



        // UPDATE
        $sql = "UPDATE ltl_land_registration
                   SET ben_id = ?,
                       ds_id = ?,
                       gn_id = ?,
                       land_address = ?,
                       landBoundary = ?,
                       developed_status = ?,
                       sketch_plan_no = ?,
                       plc_plan_no = ?,
                       survey_plan_no = ?,
                       extent = ?,
                       extent_unit = ?,
                       extent_ha = ?
                 WHERE land_id = ?";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) json_error('DB error (prepare update): ' . mysqli_error($con));
        mysqli_stmt_bind_param($stmt, 'iiisssssssssi', $ben_id, $ds_id, $gn_id, $land_address, $landBoundary, $developed_status, $sketch_plan_no, $plc_plan_no, $survey_plan_no, $extent, $extent_unit, $extent_ha, $land_id);
        if (!mysqli_stmt_execute($stmt)) {
            json_error('DB error (execute update): ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        if ($change_text !== "") {
                UserLog("2", "LTL Edit Land", "Land ID=$land_id | $change_text",$ben_id);
            }


        echo json_encode(['success' => true, 'message' => 'Land information updated', 'land_id' => $land_id]);
        exit;
    }
} catch (Throwable $e) {
    json_error('Server error: ' . $e->getMessage(), 500);
}
