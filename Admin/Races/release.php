<?php
require_once "../../Config/database.php";

/**
 * Move the function outside the POST handler so it's only defined once.
 */
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Radius of Earth in KM
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// ---------- FETCH PENDING RACES ----------
$races_result = $conn->query("SELECT id, race_name FROM races WHERE status='Pending'");
$races = [];
while($row = $races_result->fetch_assoc()){
    $races[] = $row;
}

// ---------- HANDLE RACE RELEASE ----------
if (isset($_POST['release_race'])) {
    $race_id = $_POST['race_id'];

    // 1️⃣ FIXED: Use the correct column names from your database
    $stmt = $conn->prepare("SELECT lat_release, long_release FROM races WHERE id=?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $race = $stmt->get_result()->fetch_assoc();
    
    $release_lat = $race['lat_release']; // Changed from release_lat
    $release_lng = $race['long_release']; // Changed from release_lng

    // Validation: Stop if coordinates are missing
    if (empty($release_lat) || empty($release_lng)) {
        echo "<script>alert('Error: Release coordinates are 0 or empty for this race!');</script>";
        exit();
    }

    // 2️⃣ Get all pigeons in race with their loft coordinates
    // Ensure 'lofts' table or 'members' table is where you store coordinates
    $stmt2 = $conn->prepare("
        SELECT re.id as entry_id, m.latitude, m.longitude
        FROM race_entries re
        JOIN pigeons p ON re.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE re.race_id = ?
    ");
    $stmt2->bind_param("i", $race_id);
    $stmt2->execute();
    $entries_result = $stmt2->get_result();

    // 4️⃣ Loop to calculate & update distance
    while($row = $entries_result->fetch_assoc()) {
        // Double check your loft coordinates aren't empty
        if(!empty($row['latitude']) && !empty($row['longitude'])) {
            $distance = haversine($release_lat, $release_lng, $row['latitude'], $row['longitude']);

            $stmt3 = $conn->prepare("UPDATE race_entries SET distance_km=? WHERE id=?");
            $stmt3->bind_param("di", $distance, $row['entry_id']);
            $stmt3->execute();
        }
    }

    // 5️⃣ Lock race (mark as Released) and set the release time to NOW
    $release_time = date('Y-m-d H:i:s');
    $stmt4 = $conn->prepare("UPDATE races SET status='Released', release_datetime=? WHERE id=?");
    $stmt4->bind_param("si", $release_time, $race_id);
    $stmt4->execute();

    echo "<script>showAlert('Race released! Distances calculated and locked.', 'success');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Release Race</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/script.js" defer></script>
</head>
<body>

<h2>Release Race</h2>

<form method="POST">
    <label>Select Race to Release</label><br>
    <select name="race_id" required>
        <option value="">-- Select Race --</option>
        <?php foreach($races as $r) { ?>
            <option value="<?= $r['id'] ?>">
                <?= $r['race_name'] ?>
            </option>
        <?php } ?>
    </select><br><br>

    <button type="submit" name="release_race">Release Race</button>
</form>

</body>
</html>
