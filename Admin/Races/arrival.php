<?php
session_start();
// Absolute path to prevent 404/stream errors
require_once "../../Config/database.php"; 

// 1. Admin Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit();
}

$success_msg = "";

// 2. Handle Finish Race & Final Ranking
if (isset($_POST['finish_race'])) {
    $race_id = $_POST['race_id'];

    // Rank Pigeons by Speed MPM DESC
    $results = $conn->query("SELECT id FROM race_results WHERE race_id=$race_id ORDER BY speed_mpm DESC");
    $rank = 1;
    while ($r = $results->fetch_assoc()) {
        $stmt_rank = $conn->prepare("UPDATE race_results SET rank=? WHERE id=?");
        $stmt_rank->bind_param("ii", $rank, $r['id']);
        $stmt_rank->execute();
        $rank++;
    }

    // Mark race as Completed
    $stmt_finish = $conn->prepare("UPDATE races SET status='Completed' WHERE id=?");
    $stmt_finish->bind_param("i", $race_id);
    
    if ($stmt_finish->execute()) {
        echo "<script>alert('Race closed. Results are now public.'); window.location.href='arrival.php';</script>";
    }
}

// 3. Handle Saving Manual Arrivals & Stickers
if (isset($_POST['save_arrivals'])) {
    $race_id = $_POST['race_id'];
    $arrival_times = $_POST['arrival_time']; 
    $sticker_codes = $_POST['sticker_code'];

    // Fetch Release Time
    $r_stmt = $conn->prepare("SELECT release_time FROM races WHERE id=?");
    $r_stmt->bind_param("i", $race_id);
    $r_stmt->execute();
    $race_start = $r_stmt->get_result()->fetch_assoc()['release_time'];

    foreach ($arrival_times as $entry_id => $arrival_time) {
        if (empty($arrival_time)) continue; // Skip birds not arrived

        $stmt2 = $conn->prepare("SELECT distance_km, pigeon_id FROM race_entries WHERE id=?");
        $stmt2->bind_param("i", $entry_id);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        
        $distance_km = $row['distance_km'];
        $pigeon_id = $row['pigeon_id'];
        $s_code = $sticker_codes[$entry_id] ?? null;

        // Calculate Speed
        $minutes = (strtotime($arrival_time) - strtotime($race_start)) / 60;
        $speed = ($distance_km * 1000) / max($minutes, 1);

        // Update Pigeon with Sticker Code
        $upd_pigeon = $conn->prepare("UPDATE pigeons SET sticker_code = ? WHERE id = ?");
        $upd_pigeon->bind_param("si", $s_code, $pigeon_id);
        $upd_pigeon->execute();

        // Save Result
        $stmt3 = $conn->prepare("
            INSERT INTO race_results (race_id, pigeon_id, arrival_time, speed_mpm, distance_km)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE arrival_time=VALUES(arrival_time), speed_mpm=VALUES(speed_mpm)
        ");
        $stmt3->bind_param("iisdd", $race_id, $pigeon_id, $arrival_time, $speed, $distance_km);
        $stmt3->execute();
    }
    $success_msg = "Manual arrivals and sticker codes saved!";
}

// 4. Load Active Races & Entries
$active_races = $conn->query("SELECT id, race_name FROM races WHERE status='Released'")->fetch_all(MYSQLI_ASSOC);
$entries = [];
if (isset($_POST['load_race'])) {
    $race_id = $_POST['race_id'];
    $stmt = $conn->prepare("
        SELECT re.id, p.ring_number, p.sticker_code, re.distance_km 
        FROM race_entries re 
        JOIN pigeons p ON re.pigeon_id = p.id 
        WHERE re.race_id = ?
    ");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include "../../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manual Arrival Entry - Admin</title>
    <link rel="stylesheet" href="../../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content { margin-left: 260px; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .arrival-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .arrival-table th { background: #8a6b49; color: white; padding: 12px; text-align: left; }
        .arrival-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .input-field { padding: 8px; border: 1px solid #ddd; border-radius: 5px; width: 100%; box-sizing: border-box; }
        .action-btns { display: flex; gap: 10px; margin-top: 25px; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="card">
        <h2><i class="fa-solid fa-stopwatch"></i> Manual Arrival Entry</h2>
        
        <?php if($success_msg): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;"><?= $success_msg ?></div>
        <?php endif; ?>

        <form method="POST" style="display: flex; gap: 10px; margin-bottom: 30px;">
            <select name="race_id" required class="input-field" style="flex: 1;">
                <option value="">-- Select Released Race --</option>
                <?php foreach($active_races as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (isset($race_id) && $race_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['race_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="load_race" style="background: #333; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Load Birds</button>
        </form>

        <?php if($entries): ?>
        <form method="POST">
            <input type="hidden" name="race_id" value="<?= $race_id ?>">
            <table class="arrival-table">
                <thead>
                    <tr>
                        <th>Ring Number</th>
                        <th>Sticker Code</th>
                        <th>Distance (KM)</th>
                        <th>Arrival Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($entries as $e): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($e['ring_number']) ?></strong></td>
                        <td>
                            <input type="text" name="sticker_code[<?= $e['id'] ?>]" value="<?= htmlspecialchars($e['sticker_code'] ?? '') ?>" class="input-field" placeholder="Sticker #">
                        </td>
                        <td><?= number_format($e['distance_km'], 3) ?> km</td>
                        <td>
                            <input type="datetime-local" name="arrival_time[<?= $e['id'] ?>]" class="input-field">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="action-btns">
                <button type="submit" name="save_arrivals" style="flex: 2; background: #8a6b49; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: bold; cursor: pointer;">SAVE ARRIVALS</button>
                <button type="submit" name="finish_race" onclick="return confirm('Complete race and move to history?')" style="flex: 1; background: #2c3e50; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: bold; cursor: pointer;">FINISH RACE</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>