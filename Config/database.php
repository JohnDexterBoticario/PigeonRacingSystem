<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "pigeon_racing";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Global Distance Function (Haversine Formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Radius in Kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}
?>