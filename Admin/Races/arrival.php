<?php
require_once "../../Config/database.php";

// ---------- FETCH ALL RELEASED RACES ----------
$races_result = $conn->query("SELECT id, race_name FROM races WHERE status='Released'");
$races = [];
while ($row = $races_result->fetch_assoc()) {
    $races[] = $row;
}

// ---------- HANDLE RACE SELECTION ----------
$entries_array = [];
if (isset($_POST['select_race'])) {
    $race_id = $_POST['race_id'];

    $stmt = $conn->prepare("
        SELECT re.id as entry_id, p.ring_number, re.distance_km
        FROM race_entries re
        JOIN pigeons p ON re.pigeon_id = p.id
        WHERE re.race_id = ?
    ");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $entries_array[] = $row;
    }
}

// ---------- HANDLE ARRIVAL TIMES SUBMISSION ----------
if (isset($_POST['save_arrivals'])) {
    $race_id = $_POST['race_id'];
    $arrival_times = $_POST['arrival_time']; // entry_id => datetime-local

    // Get race release datetime
    $stmt = $conn->prepare("SELECT release_datetime FROM races WHERE id=?");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $race_start = $stmt->get_result()->fetch_assoc()['release_datetime'];

    foreach ($arrival_times as $entry_id => $arrival_time) {

        // Get distance_km
        $stmt2 = $conn->prepare("SELECT distance_km, pigeon_id FROM race_entries WHERE id=?");
        $stmt2->bind_param("i", $entry_id);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $distance_km = $row['distance_km'];
        $pigeon_id = $row['pigeon_id'];

        // Calculate minutes traveled
        $minutes = (strtotime($arrival_time) - strtotime($race_start)) / 60;
        if($minutes <= 0) $minutes = 1; // prevent division by zero

        // Calculate speed (meters per minute)
        $speed = ($distance_km * 1000) / $minutes;

        // Insert or update race_results
        $stmt3 = $conn->prepare("
            INSERT INTO race_results (race_id, pigeon_id, arrival_time, speed_mpm)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE arrival_time=VALUES(arrival_time), speed_mpm=VALUES(speed_mpm)
        ");
        $stmt3->bind_param("iisd", $race_id, $pigeon_id, $arrival_time, $speed);
        $stmt3->execute();
    }

    // ---------- RANK PIGEONS ----------
    $results = $conn->query("SELECT id, speed_mpm FROM race_results WHERE race_id=$race_id ORDER BY speed_mpm DESC");
    $rank = 1;
    while ($r = $results->fetch_assoc()) {
        $stmt4 = $conn->prepare("UPDATE race_results SET rank=? WHERE id=?");
        $stmt4->bind_param("ii", $rank, $r['id']);
        $stmt4->execute();
        $rank++;
    }

    echo "<script>showAlert('Arrival times saved & ranking calculated!', 'success');</script>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Record Arrivals</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/script.js" defer></script>
</head>
<body>

<h2>Record Arrivals</h2>

<!-- ---------- SELECT RACE FORM ---------- -->
<form method="POST">
    <label>Select Race</label><br>
    <select name="race_id" required>
        <option value="">-- Select Race --</option>
        <?php foreach ($races as $r) { ?>
            <option value="<?= $r['id'] ?>" <?= isset($race_id) && $race_id==$r['id'] ? 'selected' : '' ?>>
                <?= $r['race_name'] ?>
            </option>
        <?php } ?>
    </select>
    <button type="submit" name="select_race">Select Race</button>
</form>

<!-- ---------- ARRIVAL TIMES FORM ---------- -->
<?php if(!empty($entries_array)) { ?>
<form method="POST">
    <input type="hidden" name="race_id" value="<?= $race_id ?>">
    <table>
        <tr>
            <th>Ring Number</th>
            <th>Distance (KM)</th>
            <th>Arrival Time</th>
        </tr>
        <?php foreach($entries_array as $e) { ?>
        <tr>
            <td><?= $e['ring_number'] ?></td>
            <td><?= number_format($e['distance_km'], 2) ?></td>
            <td>
                <input type="datetime-local" name="arrival_time[<?= $e['entry_id'] ?>]" required>
            </td>
        </tr>
        <?php } ?>
    </table><br>
    <button type="submit" name="save_arrivals">Save Arrivals & Calculate Ranking</button>
</form>
<?php } ?>

</body>
</html>
