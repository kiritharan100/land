<?php
// Renders the Land Information tab partial with required context, for AJAX lazy-loading
// Inputs: GET id (md5_ben_id) or ben_id; uses cookie client_cook to resolve location context

header('Content-Type: text/html; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/db.php';

// Resolve client/location context from cookie (same as header.php)
$selected_client = isset($_COOKIE['client_cook']) ? $_COOKIE['client_cook'] : '';
$location_id = '';
$client_name = '';
$coordinates = '[]';
if ($selected_client !== '') {
    $sqlClient = "SELECT c_id, client_name, coordinates FROM client_registration WHERE md5_client = ? LIMIT 1";
    if ($stmtC = mysqli_prepare($con, $sqlClient)) {
        mysqli_stmt_bind_param($stmtC, 's', $selected_client);
        mysqli_stmt_execute($stmtC);
        $resC = mysqli_stmt_get_result($stmtC);
        if ($resC) {
            if ($rowC = mysqli_fetch_assoc($resC)) {
                $location_id = $rowC['c_id'];
                $client_name = $rowC['client_name'];
                $coordinates = $rowC['coordinates'] !== '' ? $rowC['coordinates'] : '[]';
            }
        }
        mysqli_stmt_close($stmtC);
    }
}

// Resolve beneficiary ID
$ben_id = null;
if (isset($_GET['ben_id']) && ctype_digit($_GET['ben_id'])) {
    $ben_id = (int) $_GET['ben_id'];
} else {
    $md5_ben_id = isset($_GET['id']) ? $_GET['id'] : '';
    if ($md5_ben_id !== '') {
        $sqlBen = "SELECT ben_id FROM beneficiaries WHERE md5_ben_id = ? LIMIT 1";
        if ($stmtB = mysqli_prepare($con, $sqlBen)) {
            mysqli_stmt_bind_param($stmtB, 's', $md5_ben_id);
            mysqli_stmt_execute($stmtB);
            $resB = mysqli_stmt_get_result($stmtB);
            if ($resB) {
                if ($rowB = mysqli_fetch_assoc($resB)) { $ben_id = (int) $rowB['ben_id']; }
            }
            mysqli_stmt_close($stmtB);
        }
    }
}

// Expose variables for the included partial
// $con is available from db.php; provide $location_id, $client_name, $coordinates, $ben_id
include __DIR__ . '/tab_land_infomation.php';
