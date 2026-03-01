<?php
require_once "../Config/database.php"; // Use your existing connection

$sql = "SELECT id, race_name, release_point, distance_km, release_datetime FROM races ORDER BY id DESC LIMIT 1";
$entry = $conn->query($sql);

if ($entry && $entry->num_rows > 0) {
    $row = $entry->fetch_assoc();
    $race_name = $row['race_name'];
    $release_point = $row['release_point'];
    $distance_km = $row['distance_km'];
    $release_datetime = $row['release_datetime'];
} else {
    die("<div class='text-center p-4 text-muted'>No active race found for forecasting.</div>");
}

?>
<div class="p-2">
    <div class="text-center mb-4">
        <h4 class="mb-1 fw-bold text-primary"><?= htmlspecialchars($race_name) ?></h4>
        <p class="text-muted mb-0">Point: <?= htmlspecialchars($release_point) ?> | Dist: <?= number_format($distance_km, 2) ?> KM</p>
        <hr>
    </div>

    <div class="row g-3">
        <?php
        $speeds = [1400, 1300, 1200, 1100, 1000, 900, 800, 700];
        foreach ($speeds as $speed_mpm):
            $total_minutes = ($distance_km * 1000) / $speed_mpm;
            $arrival_timestamp = strtotime($release_datetime . " + " . round($total_minutes) . " minutes");
            $arrival = date('h:i A', $arrival_timestamp);
            $is_day2 = (date('Y-m-d', $arrival_timestamp) > date('Y-m-d', strtotime($release_datetime))) ? " <span class='text-danger fw-bold'>(D2)</span>" : "";
        ?>
            <div class="col-6">
                <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-muted small"><?= $speed_mpm ?> mpm</span>
                    <span class="text-dark fw-bold"><?= $arrival . $is_day2 ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>