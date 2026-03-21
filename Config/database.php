<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "pigeon_racing";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Global Distance Function (Haversine Formula)
 * Calculates the great-circle distance between two points (Release Point vs Loft)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Kilometers

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 4); // Returns distance in KM
} // Added missing closing brace for calculateDistance

/**
 * Global Logging Function
 * Records system actions into the system_logs table
 */
function logActivity($conn, $user_id, $action, $details) {
    // Ensure we have a valid connection and user_id isn't empty
    if (!$conn) return false;

    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details) VALUES (?, ?, ?)");
    
    // If user_id is not set in session, we use NULL (for system actions)
    $uid = !empty($user_id) ? $user_id : null;
    
    $stmt->bind_param("iss", $uid, $action, $details);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>