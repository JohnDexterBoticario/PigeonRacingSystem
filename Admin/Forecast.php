<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pigeon_racing"; //

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Updated Query: Added a check to see if the table exists and has data
// If your column name is different in PhpMyAdmin, change 'release_time' below
$sql = "SELECT release_point, distance_km, release_datetime FROM races ORDER BY id DESC LIMIT 1";
$entry = $conn->query($sql);

// 2. Fixed Logic: We check if $entry is FALSE to catch SQL errors (like missing columns)
if ($entry && $entry->num_rows > 0) {
    $row = $entry->fetch_assoc();
    $release_point = $row['release_point'];
    $distance_km = $row['distance_km'];
    $release_datetime = $row['release_datetime'];
} else {
    // Fallback data so the page still loads even if the database is empty or erroring
    $release_point = "Data Not Found"; 
    $distance_km = 0;
    $release_datetime = date("Y-m-d 07:00:00");
    
    // Debugging hint (Only shows if there is an error)
    if (!$entry) { echo ""; }
}

echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
echo "<h2 style='margin-bottom: 5px;'>RP: <span style='color: #2c3e50;'>$release_point</span></h2>";
echo "<h3 style='margin-top: 0;'>LD: " . number_format($distance_km, 2) . " KM</h3>";
echo "<hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>";

$speeds = [1400, 1300, 1200, 1100, 1000, 900, 800, 700];

foreach ($speeds as $speed_mpm) {
    if ($distance_km > 0) {
        $distance_meters = $distance_km * 1000;
        $total_minutes = $distance_meters / $speed_mpm;
        
        $arrival_timestamp = strtotime($release_datetime . " + " . round($total_minutes) . " minutes");
        $arrival = date('h:i A', $arrival_timestamp); // Added 'A' for AM/PM
        
        $is_day2 = (date('Y-m-d', $arrival_timestamp) > date('Y-m-d', strtotime($release_datetime))) ? " <span style='color:red;'>(D2)</span>" : "";
        
        echo "<div style='font-size: 18px; margin-bottom: 8px;'><strong>$speed_mpm</strong> - $arrival$is_day2</div>";
    } else {
        echo "<div style='color: gray;'>$speed_mpm - 00:00</div>";
    }
}
echo "</div>";

$conn->close();
?>