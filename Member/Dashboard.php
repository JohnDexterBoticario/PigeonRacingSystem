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
// We need the member_id to filter pigeons and race results correctly
$member_query = $conn->prepare("SELECT id, loft_name FROM members WHERE user_id = ?");
$member_query->bind_param("i", $user_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

$member_id = $member['id'] ?? 0;
$loft_name = $member['loft_name'] ?? "Not Set";

// 3. Fetch Pigeons owned by this member
// FIXED: Using placeholders for missing columns (year, color, gender) to avoid SQL errors
// Now pulling the real 'year' column from your database
$pigeons_result = $conn->prepare("SELECT ring_number, year, 'N/A' as color, 'N/A' as gender FROM pigeons WHERE member_id = ?");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Pigeon Racing</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/member_Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <?php if($active_race): ?><meta http-equiv="refresh" content="60"><?php endif; ?>
</head>
<body>

<div class="main-content">
    <header class="welcome-header">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?></h1>
        <div class="loft-info">
            <i class="fa-solid fa-house-chimney"></i> 
            Loft: <strong><?= htmlspecialchars($loft_name); ?></strong>
        </div>
    </header>

    <div class="stats-grid">
        <div class="dashboard-card stat-card">
            <div class="stat-label">Total Pigeons</div>
            <div class="stat-value"><?= $pigeons->num_rows ?></div>
        </div>
        <div class="dashboard-card stat-card">
            <div class="stat-label">Races Joined</div>
            <div class="stat-value" style="color: var(--primary);"><?= $results->num_rows ?></div>
        </div>
    </div>

    <?php if ($active_race && $live_results): ?>
    <div class="dashboard-card" style="border-top: 4px solid var(--danger);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div class="card-title" style="margin-bottom: 0;">
                <span class="live-indicator">● LIVE</span> 
                <?= htmlspecialchars($active_race['race_name']) ?> Arrivals
            </div>
            <a href="clocking.php" class="rank-badge" style="background: var(--primary); color: white; text-decoration: none;">
                Clock My Pigeon <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Loft</th><th>Ring Number</th><th>Time</th><th>Speed (MPM)</th></tr>
                </thead>
                <tbody>
                    <?php while($row = $live_results->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['loft_name']); ?></td>
                        <td><span class="ring-no"><?= htmlspecialchars($row['ring_number']); ?></span></td>
                        <td><?= date('H:i:s', strtotime($row['arrival_time'])); ?></td>
                        <td style="font-weight: bold; color: var(--success);"><?= number_format($row['speed_mpm'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <?php endif; ?>

    <div class="dashboard-card">
        <div class="card-title">
            <i class="fa-solid fa-dove" style="color: var(--primary);"></i> My Registered Pigeons
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Ring Number</th><th>Year</th><th>Color</th><th>Gender</th></tr>
                </thead>
                <tbody>
                    <?php if($pigeons->num_rows > 0): ?>
                        <?php while($row = $pigeons->fetch_assoc()): ?>
                        <tr>
                            <td><span class="ring-no"><?= htmlspecialchars($row['ring_number']); ?></span></td>
                            <td><?= htmlspecialchars($row['year']); ?></td>
                            <td><?= htmlspecialchars($row['color']); ?></td>
                            <td><?= htmlspecialchars($row['gender']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No pigeons found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>

    <div class="dashboard-card">
        <div class="card-title">
            <i class="fa-solid fa-trophy" style="color: #fbbf24;"></i> Performance History
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Race Name</th><th>Pigeon</th><th>Arrival</th><th>Speed</th><th>Rank</th></tr>
                </thead>
                <tbody>
                    <?php if($results->num_rows > 0): ?>
                        <?php while($res = $results->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($res['race_name']); ?></td>
                            <td><span class="ring-no"><?= htmlspecialchars($res['ring_number']); ?></span></td>
                            <td><?= date('M d, H:i', strtotime($res['arrival_time'])); ?></td>
                            <td style="font-weight: 600;"><?= number_format($res['speed_mpm'], 2); ?> <small>MPM</small></td>
                            <td><span class="rank-badge">Rank #<?= $res['rank']; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No race results yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>