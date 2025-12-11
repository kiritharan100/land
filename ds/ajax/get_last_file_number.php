<?php
require_once '../../db.php';

 $selected_client = $_COOKIE['client_cook'];
            $sel_query = "SELECT c_id FROM client_registration WHERE md5_client='$selected_client'";
            $result = mysqli_query($con, $sel_query);
            $row = mysqli_fetch_assoc($result);
            $location = $row['c_id'] ?? '0';

$sql = "SELECT file_number
        FROM leases
        WHERE location_id = '$location'
        ORDER BY lease_id DESC
        LIMIT 1";

$res = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($res);

if ($row) {

    $last = $row['file_number']; // Example: EP/TRI/RES/123

    // Split by "/"
    $parts = explode('/', $last);

    // Remove last element (123)
    array_pop($parts);

    // Join back with "/" and add trailing slash
    $prefix = implode('/', $parts) . '/';

    echo $prefix;
} else {
    // No record → return empty prefix
    echo '';
}