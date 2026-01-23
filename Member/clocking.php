<?php
session_start();
require_once "../Config/database.php";

// 2. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'member';
$success_msg = "";
$error_msg = "";
$sms_data = null;

// 3. Determine Selected Race for Stats
$stats_race_id = $_GET['race_id'] ?? null;
$my_pooling_count = 0;
$total_birds_clocked = 0;

if ($stats_race_id) {
    // Count user's birds in this race
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM race_entries re
        JOIN pigeons p ON re.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE re.race_id = ? AND m.user_id = ?
    ");
    $count_stmt->bind_param("ii", $stats_race_id, $user_id);
    $count_stmt->execute();
    $my_pooling_count = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Count total birds clocked globally
    $clocked_stmt = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $clocked_stmt->bind_param("i", $stats_race_id);
    $clocked_stmt->execute();
    $total_birds_clocked = $clocked_stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

// 4. Handle Clocking Logic
if (isset($_POST['clock_bird'])) {
    $race_id = $_POST['race_id'];
    $identifier = trim($_POST['identifier']);
    $arrival_time = date('Y-m-d H:i:s'); 

    $stmt = $conn->prepare("
        SELECT re.pigeon_id, r.release_lat, r.release_lng, r.release_datetime, r.race_name, 
               p.ring_number, p.category, m.loft_latitude, m.loft_longitude
        FROM race_entries re
        JOIN pigeons p ON re.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        JOIN races r ON re.race_id = r.id
        WHERE m.user_id = ? AND (p.ring_number = ? OR p.sticker_code = ?) AND re.race_id = ?
    ");
    $stmt->bind_param("issi", $user_id, $identifier, $identifier, $race_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data) {
        // Calculate Distance
        $dist = calculateDistance(
            $data['release_lat'], 
            $data['release_lng'], 
            $data['loft_latitude'], 
            $data['loft_longitude']
        );
        
        // Calculate Speed (Meters Per Minute)
        $release_time = strtotime($data['release_datetime']);
        $clock_time = strtotime($arrival_time);
        
        // Calculate the difference in seconds
        $diff_seconds = $clock_time - $release_time;

        if ($diff_seconds <= 0) {
            // Bird clocked before or at release time
            $speed = 0;
            $error_msg = "Error: Bird clocked before race release time!";
        } else {
            $diff_minutes = $diff_seconds / 60;
            $speed = ($dist * 1000) / $diff_minutes;
            
            // Safety cap: If speed is over 3000 MPM, something is likely wrong with coordinates
            if ($speed > 3000) {
                $speed = 0; 
                $error_msg = "Warning: Speed calculated is unrealistic. Please check loft coordinates.";
            }
        }

        if (empty($error_msg)) {
            // Save to race_results
            $save = $conn->prepare("
                INSERT INTO race_results (race_id, pigeon_id, arrival_time, speed_mpm, distance_km) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE arrival_time=VALUES(arrival_time), speed_mpm=VALUES(speed_mpm), distance_km=VALUES(distance_km)
            ");
            $save->bind_param("iisdd", $race_id, $data['pigeon_id'], $arrival_time, $speed, $dist);
            
            if ($save->execute()) {
                $success_msg = "Successfully Clocked: " . $data['ring_number'];
                $sms_data = [
                    'ring' => $data['ring_number'], 
                    'race' => $data['race_name'], 
                    'time' => date('H:i:s'), 
                    'speed' => number_format($speed, 2)
                ];
            }
        }
    } else {
        $error_msg = "Identifier invalid or bird not registered for this race.";
    }
}
// 5. Fetch Recent Clocks for the Member
$my_results = $conn->prepare("
    SELECT p.ring_number, p.category, res.arrival_time, res.speed_mpm, res.distance_km
    FROM race_results res
    JOIN pigeons p ON res.pigeon_id = p.id
    JOIN members m ON p.member_id = m.id
    WHERE m.user_id = ? 
    ORDER BY res.arrival_time DESC LIMIT 10
");
$my_results->bind_param("i", $user_id);
$my_results->execute();
$recent_clocks = $my_results->get_result();

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clocking System - GPFC Club</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(45deg, #00dbde 0%, #fc00ff 100%); min-height: 100vh; margin: 0; }
        .main-content { margin-left: 260px; padding: 40px; }
        .card { background: white !important; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; border: 1px dashed #8a6b49; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white; }
        .bg-young { background: #27ae60; } .bg-old { background: #2980b9; } .bg-off { background: #e67e22; }
        .sms-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999; }
        .phone-mock { background: #1a1a1a; padding: 15px; border-radius: 40px; width: 300px; border: 4px solid #333; }
        .phone-screen { background: #fff; border-radius: 25px; padding: 20px; height: 350px; display: flex; flex-direction: column; overflow: hidden; }
        .sms-bubble { background: #e9e9eb; padding: 10px; border-radius: 15px; font-size: 13px; line-height: 1.4; color: #000; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container" style="max-width: 900px; margin: auto;">

        <?php if($success_msg): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3 style="margin-bottom: 15px;"><i class="fa-solid fa-chart-line"></i> Race Overview</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <small>My Birds in Pooling</small>
                    <div style="font-size: 24px; font-weight: bold; color: #8a6b49;"><?= $my_pooling_count ?></div>
                </div>

                <div class="stat-box" style="border-color: #fc00ff;">
                    <small style="color: #666;">Total Birds Clocked (Live)</small>
                    <div style="font-size: 24px; font-weight: bold; color: #fc00ff;"><?= $total_birds_clocked ?></div>
                    <?php if($role === 'admin'): ?>
                        <a href="live_arrivals.php?race_id=<?= $stats_race_id ?>" style="font-size: 10px; color: #888;">View Full List <i class="fa-solid fa-external-link"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="color: #8a6b49; text-align: center;"><i class="fa-solid fa-bolt"></i> Instant Clocking</h2>
            <form method="POST">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <select name="race_id" required onchange="window.location.href='?race_id=' + this.value" style="padding: 12px; border-radius: 8px; border: 1px solid #ddd;">
                        <option value="">-- Select Active Race --</option>
                        <?php 
                        $active = $conn->query("SELECT id, race_name FROM races WHERE status = 'Released'");
                        while($r = $active->fetch_assoc()): ?>
                            <option value="<?= $r['id'] ?>" <?= ($stats_race_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['race_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="identifier" placeholder="Enter Ring Number or Sticker Code" required style="padding: 12px; border-radius: 8px; border: 1px solid #ddd;">
                    <button type="submit" name="clock_bird" style="background: #8a6b49; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px;">CLOCK NOW</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-list-check"></i> My Recent Arrivals</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                    <thead>
                        <tr style="background:#f8f9fa; text-align: left;">
                            <th style="padding:12px;">Ring Number</th>
                            <th style="padding:12px;">Category</th>
                            <th style="padding:12px;">Distance</th>
                            <th style="padding:12px;">Time</th>
                            <th style="padding:12px;">Speed (MPM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_clocks->num_rows > 0): ?>
                            <?php while($row = $recent_clocks->fetch_assoc()): 
                                $badge = ($row['category'] == 'Old Bird') ? 'bg-old' : (($row['category'] == 'Off Color') ? 'bg-off' : 'bg-young');
                            ?>
                            <tr>
                                <td style="padding:12px; border-bottom: 1px solid #eee;"><strong><?= htmlspecialchars($row['ring_number']) ?></strong></td>
                                <td style="padding:12px; border-bottom: 1px solid #eee;"><span class="badge <?= $badge ?>"><?= htmlspecialchars($row['category']) ?></span></td>
                                <td style="padding:12px; border-bottom: 1px solid #eee;"><?= number_format($row['distance_km'], 3) ?> km</td>
                                <td style="padding:12px; border-bottom: 1px solid #eee;"><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                                <td style="padding:12px; border-bottom: 1px solid #eee; font-weight:bold;"><?= number_format($row['speed_mpm'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding:30px; text-align:center; color:#999;">No birds clocked yet for this account.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($sms_data): ?>
<div class="sms-overlay" id="smsOverlay">
    <div class="phone-mock">
        <div class="phone-screen">
            <div style="text-align: center; font-size: 11px; color: #aaa; margin-bottom: 10px;">Messages • Now</div>
            <div class="sms-bubble">
                <strong>GPFC CLUB SMS:</strong><br>
                Bird [<?= htmlspecialchars($sms_data['ring']) ?>] clocked for <?= htmlspecialchars($sms_data['race']) ?>.<br>
                Speed: <strong><?= $sms_data['speed'] ?> MPM</strong>.
            </div>
            <div style="flex-grow: 1;"></div>
            <button onclick="document.getElementById('smsOverlay').style.display='none'" style="width: 100%; padding: 10px; background: #007aff; color: white; border: none; border-radius: 10px; cursor: pointer;">OK</button>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>