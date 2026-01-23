<?php
session_start();
require_once "../Config/database.php"; 

// 1. Initialize variables to prevent undefined warnings
$race = ['release_time' => date('Y-m-d H:i:s'), 'release_point' => 'N/A', 'race_name' => 'Race Results'];
$total_entry = 0;
$total_arrived = 0;
$leaderboard = [];
$clubName = "Pigeon Racing Club International";

// 2. Fetch completed races for the improved dropdown
// Check for both possible column names to be safe
$races_query = $conn->query("SELECT id, race_name, release_datetime FROM races WHERE status='Completed' ORDER BY release_datetime DESC");

if (!$races_query) {
    // Fallback if you haven't renamed 'release_datetime' yet
    $races_query = $conn->query("SELECT id, race_name, release_time as release_datetime FROM races WHERE status='Completed' ORDER BY release_datetime DESC");
}
$races_list = $races_query->fetch_all(MYSQLI_ASSOC);

// Determine which race to display (defaults to the latest completed race)
$selected_race_id = $_GET['race_id'] ?? ($races_list[0]['id'] ?? null);

if ($selected_race_id) {
    // Fetch Race Details
    $stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
    $stmt->bind_param("i", $selected_race_id);
    $stmt->execute();
    $race_data = $stmt->get_result()->fetch_assoc();
    if($race_data) $race = $race_data;

    // Fetch Participation Stats
    $res_entry = $conn->prepare("SELECT COUNT(*) as total FROM race_entries WHERE race_id = ?");
    $res_entry->bind_param("i", $selected_race_id);
    $res_entry->execute();
    $total_entry = $res_entry->get_result()->fetch_assoc()['total'] ?? 0;

    $res_arrived = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $res_arrived->bind_param("i", $selected_race_id);
    $res_arrived->execute();
    $total_arrived = $res_arrived->get_result()->fetch_assoc()['total'] ?? 0;

    // Fetch Leaderboard with Member/Loft Joins
    $results_stmt = $conn->prepare("
        SELECT res.rank, p.ring_number, m.loft_name, res.arrival_time, res.speed_mpm, res.distance_km
        FROM race_results res
        JOIN pigeons p ON res.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE res.race_id = ?
        ORDER BY res.rank ASC
    ");
    $results_stmt->bind_param("i", $selected_race_id);
    $results_stmt->execute();
    $leaderboard = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include "../Includes/sidebar.php"; //
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Race Results - <?= htmlspecialchars($clubName) ?></title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Modern Select Styling */
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #fff;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        select.race-selector {
            padding: 10px 20px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: #fdfdfd;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            outline: none;
        }
        /* Printable Table */
        .results-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .results-table th { background: #2c3e50; color: white; padding: 15px; text-transform: uppercase; font-size: 13px; }
        .results-table td { padding: 15px; border-bottom: 1px solid #eee; text-align: center; }
        .ring-badge { background: #2c3e50; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600; }
        
        /* Print Logic */
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; }
            .sidebar, .filter-container, .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container" style="max-width: 1100px; margin: auto;">
        
        <div class="filter-container no-print">
            <div style="color: #8a6b49; font-weight: bold;"><i class="fa-solid fa-trophy"></i> RACE HISTORY</div>
            <div style="display: flex; gap: 15px;">
                <form method="GET">
                    <select name="race_id" onchange="this.form.submit()" class="race-selector">
                        <?php foreach ($races_list as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $selected_race_id == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['race_name']) ?> (<?= date('M d, Y', strtotime($r['release_time'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button onclick="window.print()" style="background: #8a6b49; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: bold;">
                    <i class="fa-solid fa-file-pdf"></i> Export Result
                </button>
            </div>
        </div>

        <div class="card" id="printArea" style="background: white; padding: 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
            <div style="text-align: center; border-bottom: 3px double #eee; padding-bottom: 20px; margin-bottom: 30px;">
                <h1 style="color: #8a6b49; margin: 0; font-size: 28px; text-transform: uppercase;"><?= htmlspecialchars($clubName) ?></h1>
                <h2 style="margin: 5px 0; color: #333;">Official Race Result</h2>
                <p style="color: #777;">Race Date: <strong><?= date('F j, Y', strtotime($race['release_time'])) ?></strong></p>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 40px; background: #fcfcfc; padding: 20px; border-radius: 10px; border: 1px solid #f0f0f0;">
                <div style="line-height: 1.8;">
                    <p><strong>📍 Release Point:</strong> <?= htmlspecialchars($race['release_point']) ?></p>
                    <p><strong>⏰ Time Release:</strong> <?= date('H:i:s A', strtotime($race['release_time'])) ?></p>
                    <p><strong>🌤 Weather:</strong> Clear Skies / Calm</p>
                </div>
                <div style="line-height: 1.8; border-left: 2px solid #eee; padding-left: 30px;">
                    <p><strong>📊 Total Entry:</strong> <?= $total_entry ?> Birds</p>
                    <p><strong>✅ Total Arrived:</strong> <?= $total_arrived ?> Birds</p>
                    <p><strong>📉 Min. Speed:</strong> <?= !empty($leaderboard) ? end($leaderboard)['speed_mpm'] . ' MPM' : '0 MPM' ?></p>
                </div>
            </div>

            <table class="results-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Ring Number</th>
                        <th>Owner / Loft</th>
                        <th>Distance (KM)</th>
                        <th>Arrival Time</th>
                        <th>Speed (MPM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($leaderboard)): ?>
                        <?php foreach ($leaderboard as $row): ?>
                        <tr>
                            <td><strong style="font-size: 18px;">#<?= $row['rank'] ?></strong></td>
                            <td><span class="ring-badge"><?= htmlspecialchars($row['ring_number']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['loft_name']) ?></strong></td>
                            <td><?= number_format($row['distance_km'], 3) ?></td>
                            <td><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                            <td><strong style="color: #2c3e50;"><?= number_format($row['speed_mpm'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 40px; color: #999;">No bird standings available for this race.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 50px; text-align: center; color: #bbb; font-size: 12px;" class="no-print">
                Generated by Pigeon Racing System © 2026
            </div>
        </div>
    </div>
</div>

</body>
</html>