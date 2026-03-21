<?php
require_once "../../Config/database.php";

/* =========================
    FETCH DATA INTO ARRAYS
========================= */
// Fetch all pending races - including the release_point column
$races_result = $conn->query("SELECT id, race_name, release_point FROM races WHERE status='Pending'");
$races = [];
while($row = $races_result->fetch_assoc()){
    $races[] = $row;
}

// Fetch all pigeons
$pigeons_result = $conn->query("SELECT id, ring_number FROM pigeons");
$pigeons = [];
while($row = $pigeons_result->fetch_assoc()){
    $pigeons[] = $row;
}

/* =========================
    SAVE ENTRY
========================= */
if (isset($_POST['add_entry'])) {
    $race_id = $_POST['race_id'];
    $pigeon_id = $_POST['pigeon_id'];

    $stmt = $conn->prepare("INSERT INTO race_entries (race_id, pigeon_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $race_id, $pigeon_id);

    if ($stmt->execute()) {
        echo "<script>alert('Pigeon added to race successfully!'); window.location.href='register_pigeon.php';</script>";
    } else {
        echo "<script>alert('Failed to add pigeon!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Pigeons to Race</title>
    <link rel="stylesheet" href="../../assets/Css/style.css">
    <style>
        /* Small styling for the release point display to match your UI */
        .release-info {
            margin-top: 10px;
            padding: 10px;
            background-color: #f0f7ff;
            border-left: 4px solid #007bff;
            border-radius: 4px;
            font-size: 0.9rem;
            display: none; /* Hidden until a race is selected */
        }
        .release-info b { color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>Add Pigeon to Race</h2>

    <form method="POST">
        <div class="form-group">
            <label>Select Active Race</label><br>
            <select name="race_id" id="race_select" required onchange="updateReleasePoint()">
                <option value="">-- Select Active Race --</option>
                <?php foreach($races as $r) { ?>
                    <option value="<?= $r['id'] ?>" data-location="<?= htmlspecialchars($r['release_point']) ?>">
                        <?= htmlspecialchars($r['race_name']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div id="release_display" class="release-info">
            <b>Release Point:</b> <span id="location_text"></span>
        </div>

        <br>

        <div class="form-group">
            <label>Select Pigeon (Ring Number)</label><br>
            <select name="pigeon_id" required>
                <option value="">-- Select Pigeon --</option>
                <?php foreach($pigeons as $p) { ?>
                    <option value="<?= $p['id'] ?>">
                        <?= htmlspecialchars($p['ring_number']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <br>

        <button type="submit" name="add_entry" class="btn-confirm">Confirm Entries</button>
    </form>
</div>

<script>
/**
 * Updates the Release Point text based on the selected dropdown option
 */
function updateReleasePoint() {
    const select = document.getElementById('race_select');
    const displayDiv = document.getElementById('release_display');
    const locationSpan = document.getElementById('location_text');
    
    // Get the selected option element
    const selectedOption = select.options[select.selectedIndex];
    
    // Pull the release_point from the data-location attribute
    const location = selectedOption.getAttribute('data-location');

    if (location && location.trim() !== "") {
        locationSpan.textContent = location;
        displayDiv.style.display = 'block'; // Show the box
    } else {
        displayDiv.style.display = 'none'; // Hide if no race/location
    }
}
</script>

</body>
</html>