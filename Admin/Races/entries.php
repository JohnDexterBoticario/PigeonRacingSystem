<?php
require_once "../../Config/database.php";

/* =========================
   FETCH DATA INTO ARRAYS
========================= */
// Fetch all pending races
$races_result = $conn->query("SELECT id, race_name FROM races WHERE status='Pending'");
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
        echo "<script>showAlert('Pigeon added to race!', 'success');</script>";
    } else {
        echo "<script>showAlert('Failed to add pigeon!', 'error');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Pigeons to Race</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/script.js" defer></script>
</head>
<body>

<h2>Add Pigeon to Race</h2>

<form method="POST">

    <label>Select Race</label><br>
    <select name="race_id" required>
        <option value="">-- Select Race --</option>
        <?php foreach($races as $r) { ?>
            <option value="<?= $r['id'] ?>">
                <?= $r['race_name'] ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label>Select Pigeon (Ring Number)</label><br>
    <select name="pigeon_id" required>
        <option value="">-- Select Pigeon --</option>
        <?php foreach($pigeons as $p) { ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['ring_number'] ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <button type="submit" name="add_entry">Add to Race</button>

</form>

</body>
</html>
