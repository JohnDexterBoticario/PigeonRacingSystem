<?php
/**
 * Race Results - Pigeon Racing System
 * This file handles displaying race results and leaderboards.
 */
session_start();
require_once "../Config/database.php"; 

// 1. Initialize variables with unique names to prevent sidebar conflicts
// Renamed from $currentRace to $activeRaceInfo to avoid collision with sidebar.php
$activeRaceInfo = [
    'id' => 0,
    'release_datetime' => date('Y-m-d H:i:s'), 
    'release_point' => 'Not Specified', 
    'race_name' => 'Race Results',
    'status' => 'No Race Selected',
    'weather' => 'Clear Skies / Calm'
];
$totalEntriesCount = 0;
$totalArrivedCount = 0;
$raceLeaderboard = [];
$clubName = "Pigeon Racing Club International";

// 2. Fetch all races to populate the dropdown
$races_query = $conn->query("SELECT id, race_name, release_datetime, status FROM races ORDER BY release_datetime DESC");
$races_list = $races_query ? $races_query->fetch_all(MYSQLI_ASSOC) : [];

// 3. Determine which race to display (defaults to the latest race in the list)
$selected_race_id = $_GET['race_id'] ?? ($races_list[0]['id'] ?? null);

if ($selected_race_id) {
    // Fetch Race Details using Prepared Statements
    $stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
    $stmt->bind_param("i", $selected_race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ensure we only overwrite if data is actually found
    if($result && $result->num_rows > 0) {
        $race_data = $result->fetch_assoc();
        // Merge fetched data with our defaults
        $activeRaceInfo = array_merge($activeRaceInfo, $race_data);
    }

    // Fetch Participation Stats (Total Entry)
    // IMPORTANT: Verify that rows in 'race_entries' have race_id = $selected_race_id
    $stmt_entry = $conn->prepare("SELECT COUNT(*) as total FROM race_entries WHERE race_id = ?");
    $stmt_entry->bind_param("i", $selected_race_id);
    $stmt_entry->execute();
    $entry_res = $stmt_entry->get_result()->fetch_assoc();
    $totalEntriesCount = $entry_res['total'] ?? 0;

    // Fetch Participation Stats (Total Arrived)
    $stmt_arrived = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $stmt_arrived->bind_param("i", $selected_race_id);
    $stmt_arrived->execute();
    $arrived_res = $stmt_arrived->get_result()->fetch_assoc();
    $totalArrivedCount = $arrived_res['total'] ?? 0;

    // Fetch Leaderboard with Member/Loft Joins
    $results_stmt = $conn->prepare("
        SELECT 
            res.rank, 
            p.ring_number, 
            m.loft_name, 
            res.arrival_time, 
            res.speed_mpm, 
            res.distance_km
        FROM race_results res
        JOIN pigeons p ON res.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE res.race_id = ?
        ORDER BY res.rank ASC
    ");
    $results_stmt->bind_param("i", $selected_race_id);
    $results_stmt->execute();
    $raceLeaderboard = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Sidebar - Including this AFTER fetching data is fine as long as variable names are unique
include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Race Results - <?= htmlspecialchars($clubName) ?></title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/RaceResults.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="main-content">
    
    <div class="report-card no-print" style="padding: 1rem 2rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="font-weight: 800; color: var(--slate-600); text-transform: uppercase; font-size: 0.8rem;">
            <i class="fa-solid fa-trophy"></i> Race Archives
        </div>
        
        <div style="display: flex; gap: 10px;">
            <form method="GET">
                <select name="race_id" onchange="this.form.submit()" 
                        style="padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; font-weight: 600; cursor: pointer;">
                    <?php foreach ($races_list as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $selected_race_id == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['race_name']) ?> (<?= date('M d', strtotime($r['release_datetime'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button onclick="window.print()" style="background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 20px; font-weight: 700; cursor: pointer;">
                <i class="fa-solid fa-print"></i> PRINT
            </button>
        </div>
    </div>

    <div class="report-card">
        <header class="report-header">
            <h1 class="club-name"><?= htmlspecialchars($clubName) ?></h1>
            <div style="font-weight: 700; color: var(--slate-800); font-size: 1.2rem; margin-top: 10px;">
                OFFICIAL RACE RESULT
            </div>
            <div style="color: var(--slate-400); font-size: 0.9rem; margin-top: 5px;">
                Race Date: <?= date('F j, Y', strtotime($activeRaceInfo['release_datetime'])) ?>
            </div>
        </header>

        <div class="stats-banner">
            <div class="stat-group" style="border-right: 1px solid #e2e8f0;">
                <div class="stat-line">
                    <span class="stat-label">Release Point</span>
                    <span class="stat-value"><?= htmlspecialchars($activeRaceInfo['release_point']) ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Release Time</span>
                    <span class="stat-value"><?= date('h:i:s A', strtotime($activeRaceInfo['release_datetime'])) ?></span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Weather</span>
                    <span class="stat-value"><?= htmlspecialchars($activeRaceInfo['weather']) ?></span>
                </div>
            </div>
            <div class="stat-group">
                <div class="stat-line">
                    <span class="stat-label">Total Entry</span>
                    <span class="stat-value"><?= $totalEntriesCount ?> Birds</span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Total Arrived</span>
                    <span class="stat-value"><?= $totalArrivedCount ?> Birds</span>
                </div>
                <div class="stat-line">
                    <span class="stat-label">Winning Speed</span>
                    <span class="stat-value" style="color: var(--primary);">
                        <?= !empty($raceLeaderboard) ? number_format($raceLeaderboard[0]['speed_mpm'], 2) : '0.00' ?> MPM
                    </span>
                </div>
            </div>
        </div>

        <table class="results-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Ring Number</th>
                    <th>Owner / Loft</th>
                    <th>Distance</th>
                    <th>Arrival</th>
                    <th>Speed (MPM)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($raceLeaderboard)): ?>
                    <?php foreach ($raceLeaderboard as $row): ?>
                    <tr>
                        <td class="rank-text">#<?= $row['rank'] ?></td>
                        <td><span class="ring-tag"><?= htmlspecialchars($row['ring_number']) ?></span></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($row['loft_name']) ?></td>
                        <td><?= number_format($row['distance_km'], 3) ?> km</td>
                        <td><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                        <td class="speed-text"><?= number_format($row['speed_mpm'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 50px; color: var(--slate-400);">
                            No bird standings available for this race.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <footer style="padding: 2rem; text-align: center; color: var(--slate-400); font-size: 0.7rem; letter-spacing: 0.1em;">
            GENERATED BY PIGEON RACING SYSTEM &copy; <?= date('Y') ?>
        </footer>
    </div>
</div>

</body>
</html>