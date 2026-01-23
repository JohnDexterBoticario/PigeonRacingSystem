<?php
session_start();
require_once "../Config/database.php"; 

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../Auth/login.php");
    exit();
}

include "../Includes/sidebar.php"; 

$user_id = $_SESSION['user_id'];

// 2. Fetch Member Details
$member_query = $conn->prepare("SELECT id, loft_name FROM members WHERE user_id = ?");
$member_query->bind_param("i", $user_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

$member_id = $member['id'] ?? 0;
$loft_name = $member['loft_name'] ?? "Not Set";

// 3. Fetch Pigeons owned by this member
$pigeons_result = $conn->prepare("SELECT ring_number, year, color, gender FROM pigeons WHERE member_id = ?");
$pigeons_result->bind_param("i", $member_id);
$pigeons_result->execute();
$pigeons = $pigeons_result->get_result();

// 4. Fetch Personal Race History
$results_query = $conn->prepare("
    SELECT r.race_name, p.ring_number, res.arrival_time, res.speed_mpm, res.rank 
    FROM race_results res
    JOIN races r ON res.race_id = r.id
    JOIN pigeons p ON res.pigeon_id = p.id
    WHERE p.member_id = ? 
    ORDER BY res.arrival_time DESC
");
$results_query->bind_param("i", $member_id);
$results_query->execute();
$results = $results_query->get_result();

// 5. LIVE FEED LOGIC: Fetch current active race
$active_race_query = $conn->query("SELECT id, race_name FROM races WHERE status = 'Released' LIMIT 1");
$active_race = $active_race_query->fetch_assoc();
$live_results = null;

if ($active_race) {
    $race_id = $active_race['id'];
    // Fetch top 5 recent arrivals from ANY member for this race
    $live_results = $conn->query("
        SELECT p.ring_number, m.loft_name, res.arrival_time, res.speed_mpm
        FROM race_results res
        JOIN pigeons p ON res.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE res.race_id = $race_id
        ORDER BY res.arrival_time DESC LIMIT 5
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard - Pigeon Racing</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(45deg, #00dbde 0%, #fc00ff 100%); min-height: 100vh; }
        .main-content { margin-left: 260px; padding: 40px; } 
        .dashboard-card { background: white !important; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f8f9fa; color: #333; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; color: #555; }
        .welcome-header { margin-bottom: 30px; color: white; }
        .rank-badge { background: #8a6b49; color: white; padding: 4px 10px; border-radius: 10px; font-weight: bold; }
        /* Live Indicator Style */
        .live-tag { background: #ff0000; color: white; padding: 2px 8px; border-radius: 5px; font-size: 12px; font-weight: bold; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>
    <?php if($active_race): ?><meta http-equiv="refresh" content="60"><?php endif; ?>
</head>
<body>

<div class="main-content">
    <div class="welcome-header">
        <h1 style="margin: 0;">Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?></h1>
        <p style="opacity: 0.9;"><i class="fa-solid fa-house-chimney"></i> Loft: <strong><?= htmlspecialchars($loft_name); ?></strong></p>
    </div>

    <?php if ($active_race && $live_results): ?>
    <div class="dashboard-card" style="border-left: 5px solid #ff0000; padding: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #333;">
                <span class="live-tag">LIVE</span> Arrivals: <?= htmlspecialchars($active_race['race_name']) ?>
            </h3>
            <a href="clocking.php" style="color: #fc00ff; text-decoration: none; font-weight: bold; font-size: 14px;">Go to Clocking Page →</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Loft Name</th>
                    <th>Ring Number</th>
                    <th>Time</th>
                    <th>Speed (MPM)</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $live_results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['loft_name']); ?></td>
                    <td><strong><?= htmlspecialchars($row['ring_number']); ?></strong></td>
                    <td><?= date('H:i:s', strtotime($row['arrival_time'])); ?></td>
                    <td style="font-weight: bold; color: #8a6b49;"><?= number_format($row['speed_mpm'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="dashboard-card" style="margin-bottom: 0; padding: 20px; text-align: center;">
            <h4 style="color: #888; margin: 0;">Total Pigeons</h4>
            <h2 style="font-size: 32px; color: #8a6b49; margin: 10px 0;"><?= $pigeons->num_rows ?></h2>
        </div>
        <div class="dashboard-card" style="margin-bottom: 0; padding: 20px; text-align: center;">
            <h4 style="color: #888; margin: 0;">Races Joined</h4>
            <h2 style="font-size: 32px; color: #fc00ff; margin: 10px 0;"><?= $results->num_rows ?></h2>
        </div>
    </div>

    <div class="dashboard-card">
        <h3 style="color: #333;"><i class="fa-solid fa-dove" style="color: #8a6b49;"></i> My Registered Pigeons</h3>
        <table>
            <thead>
                <tr><th>Ring Number</th><th>Year</th><th>Color</th><th>Gender</th></tr>
            </thead>
            <tbody>
                <?php if($pigeons->num_rows > 0): ?>
                    <?php while($row = $pigeons->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['ring_number']); ?></strong></td>
                        <td><?= htmlspecialchars($row['year']); ?></td>
                        <td><?= htmlspecialchars($row['color']); ?></td>
                        <td><?= htmlspecialchars($row['gender']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 30px;">No pigeons found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="dashboard-card">
        <h3 style="color: #333;"><i class="fa-solid fa-trophy" style="color: #ffd700;"></i> Performance History</h3>
        <table>
            <thead>
                <tr><th>Race Name</th><th>Pigeon</th><th>Arrival</th><th>Speed</th><th>Rank</th></tr>
            </thead>
            <tbody>
                <?php if($results->num_rows > 0): ?>
                    <?php while($res = $results->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($res['race_name']); ?></td>
                        <td><span style="background: #f0f0f0; padding: 3px 8px; border-radius: 5px;"><?= htmlspecialchars($res['ring_number']); ?></span></td>
                        <td><?= date('M d, H:i', strtotime($res['arrival_time'])); ?></td>
                        <td style="font-weight: 600;"><?= number_format($res['speed_mpm'], 2); ?> <small>MPM</small></td>
                        <td><span class="rank-badge">#<?= $res['rank']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 30px;">No race results recorded for your loft yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>