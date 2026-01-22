<?php
require_once "../../Config/database.php"; // make sure the path matches

// Handle form submission
if(isset($_POST['save_race'])) {

    $race_name = $_POST['race_name'];
    $release_point = $_POST['release_point'];
    $release_lat = $_POST['release_lat'];
    $release_lng = $_POST['release_lng'];
    $release_datetime = $_POST['release_datetime'];

    // Insert into races table (status = Pending)
    $stmt = $conn->prepare("
        INSERT INTO races (race_name, release_point, release_lat, release_lng, release_datetime, status)
        VALUES (?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->bind_param("ssdds", $race_name, $release_point, $release_lat, $release_lng, $release_datetime);
    $stmt->execute();

    echo "<script>showAlert('Race created successfully!', 'success');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Race</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/script.js" defer></script>
</head>
<body>

<h2>Create New Race</h2>

<form method="POST">
    <label>Race Name</label><br>
    <input type="text" name="race_name" required><br><br>

    <label>Release Point Name</label><br>
    <input type="text" name="release_point" required><br><br>

    <label>Release Latitude</label><br>
    <input type="number" step="0.000001" name="release_lat" required><br><br>

    <label>Release Longitude</label><br>
    <input type="number" step="0.000001" name="release_lng" required><br><br>

    <label>Release Date & Time</label><br>
    <input type="datetime-local" name="release_datetime" required><br><br>

    <button type="submit" name="save_race">Save Race</button>
</form>

</body>
</html>
