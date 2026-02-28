<?php
session_start();
require_once "../Config/database.php"; // Assuming $conn is defined here

// 1. Admin Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

include "../Includes/sidebar.php"; 

// 2. Fetch System Stats
$total_members = $conn->query("SELECT COUNT(*) as total FROM members")->fetch_assoc()['total'];
$total_pigeons = $conn->query("SELECT COUNT(*) as total FROM pigeons")->fetch_assoc()['total'];
$total_races = $conn->query("SELECT COUNT(*) as total FROM races")->fetch_assoc()['total'];

// 3. Check for any Live (Released) Races
$active_race = $conn->query("SELECT id, race_name, release_point, distance_km, release_datetime FROM races WHERE status = 'Released' ORDER BY id DESC LIMIT 1")->fetch_assoc();

// 4. Fetch Recent Administrative Activity
$recent_logs = $conn->query("SELECT action, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Pigeon Racing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 40px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #8a6b49; }
        .stat-card h3 { margin: 0; color: #888; font-size: 14px; text-transform: uppercase; }
        .stat-card p { margin: 10px 0 0; font-size: 32px; font-weight: bold; color: #2c3e50; }
        .dashboard-row { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; border: none; }
        .action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .btn-action { 
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 20px; background: #fdfdfd; border: 2px dashed #ddd; 
            border-radius: 10px; text-decoration: none; color: #2c3e50; font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-action:hover { border-color: #8a6b49; background: #fffaf5; color: #8a6b49; }
    </style>
</head>
<body>

<div class="main-content">
    <div style="margin-bottom: 30px;">
        <h1 style="margin: 0; color: #2c3e50;">Admin Dashboard</h1>
        <p style="color: #7f8c8d;">System overview and quick management links.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><h3>Total Members</h3><p><?= $total_members ?></p></div>
        <div class="stat-card"><h3>Total Pigeons</h3><p><?= $total_pigeons ?></p></div>
        <div class="stat-card"><h3>Races Managed</h3><p><?= $total_races ?></p></div>
    </div>

    <div class="dashboard-row">
        <div class="left-col">
            <div class="card">
                <h3 style="margin-top: 0;"><i class="fa-solid fa-screwdriver-wrench"></i> Quick Management</h3>
                <div class="action-btns">
                    <a href="Members/register_member.php" class="btn-action"><i class="fa-solid fa-user-plus"></i> Register Player</a>
                    <a href="Pigeons/register_pigeon.php" class="btn-action"><i class="fa-solid fa-dove"></i> Register Pigeon</a>
                    <a href="Forecast.php" class="btn-action"><i class="fa-solid fa-magnifying-glass-chart"></i> Forecast</a>
                </div>
            </div>

            <div class="card p-0" style="overflow: hidden;">
                <div class="bg-primary p-3 text-white d-flex justify-content-between align-items-center">
                    <h4 class="m-0" style="font-size: 18px;"><i class="fa-solid fa-clock"></i> Quick Forecast</h4>
                    <?php if($active_race): ?>
                        <span class="badge bg-light text-dark"><?= number_format($active_race['distance_km'], 2) ?> KM</span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($active_race): ?>
                        <p class="text-muted mb-3">Target: <strong><?= htmlspecialchars($active_race['release_point']) ?></strong></p>
                        <div class="row text-center">
                            <?php 
                            $speeds = [1300, 1200, 1100, 1000];
                            foreach($speeds as $speed): 
                                $total_min = ($active_race['distance_km'] * 1000) / $speed;
                                $arrival = date('h:i A', strtotime($active_race['release_datetime'] . " + " . round($total_min) . " minutes"));
                            ?>
                                <div class="col-3 border-end">
                                    <small class="text-muted d-block"><?= $speed ?> mpm</small>
                                    <strong class="text-primary"><?= $arrival ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No active race data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="right-col">
            <div class="card">
                <h3 style="margin-top: 0;"><i class="fa-solid fa-list-ul"></i> Recent Activity</h3>
                <ul style="list-style: none; padding: 0; margin-top: 15px;">
                    <?php if($recent_logs->num_rows > 0): ?>
                        <?php while($log = $recent_logs->fetch_assoc()): ?>
                            <li style="padding: 10px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                                <span style="color: #2c3e50; font-weight: 600;"><?= htmlspecialchars($log['action']) ?></span><br>
                                <small style="color: #bdc3c7;"><?= date('M d, g:i A', strtotime($log['created_at'])) ?></small>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li style="padding: 10px 0; color: #999; font-size: 13px;">No recent activity logged.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

</body>
</html>