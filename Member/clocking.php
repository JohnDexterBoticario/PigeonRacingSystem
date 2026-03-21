<?php
session_start();
// 1. Force local timezone to prevent "clocked before release" errors
date_default_timezone_set('Asia/Manila'); 
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

// 3. Stats Logic
$stats_race_id = $_GET['race_id'] ?? null;
$my_pooling_count = 0;
$total_birds_clocked = 0;

if ($stats_race_id) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM race_entries re JOIN pigeons p ON re.pigeon_id = p.id JOIN members m ON p.member_id = m.id WHERE re.race_id = ? AND m.user_id = ?");
    $count_stmt->bind_param("ii", $stats_race_id, $user_id);
    $count_stmt->execute();
    $my_pooling_count = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;

    $clocked_stmt = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $clocked_stmt->bind_param("i", $stats_race_id);
    $clocked_stmt->execute();
    $total_birds_clocked = $clocked_stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

// 4. Clocking Submission Handler
if (isset($_POST['clock_bird'])) {
    $race_id = $_POST['race_id'];
    $identifier = trim($_POST['identifier']);
    $arrival_time = date('Y-m-d H:i:s'); 

    $stmt = $conn->prepare("
        SELECT re.pigeon_id, r.release_datetime, r.race_name, 
               r.distance_km, 
               p.ring_number, p.category
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
        // FIXED: Removed the loft_lat/loft_long check because we use fixed distance_km
        $dist = $data['distance_km']; 
        $release_time = strtotime($data['release_datetime']);
        $clock_time = strtotime($arrival_time);
        $diff_seconds = $clock_time - $release_time;

        if ($diff_seconds <= 0) {
            $error_msg = "Error: Bird clocked before race release time!";
        } else {
            $diff_minutes = $diff_seconds / 60;
            
            // Formula matches your Forecast: (115 * 1000) / Minutes
            $speed = ($dist * 1000) / $diff_minutes;
            
            if ($speed > 2500) { 
                $error_msg = "Unrealistic Speed: " . number_format($speed, 2) . " MPM. <br>" . 
                             "Dist: " . number_format($dist, 3) . "km | Time: " . round($diff_minutes, 2) . " min.";
            } else {
                $save = $conn->prepare("
                    INSERT INTO race_results (race_id, pigeon_id, arrival_time, speed_mpm, distance_km) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE arrival_time=VALUES(arrival_time), speed_mpm=VALUES(speed_mpm), distance_km=VALUES(distance_km)
                ");
                $save->bind_param("iisdd", $race_id, $data['pigeon_id'], $arrival_time, $speed, $dist);
                
                if ($save->execute()) {
                    $update_ranks = $conn->prepare("
        UPDATE race_results r1
        JOIN (
            SELECT id, RANK() OVER (ORDER BY speed_mpm DESC) as new_rank
            FROM race_results
            WHERE race_id = ?
        ) r2 ON r1.id = r2.id
        SET r1.rank = r2.new_rank
    ");
    $update_ranks->bind_param("i", $race_id);
    $update_ranks->execute();
                    $success_msg = "Successfully Clocked: " . $data['ring_number'] . " at " . number_format($speed, 2) . " MPM";
                    $sms_data = [
                        'ring' => $data['ring_number'], 
                        'race' => $data['race_name'], 
                        'time' => date('H:i:s'), 
                        'speed' => number_format($speed, 2)
                    ];
                }
            }
        }
    } else {
        $error_msg = "Invalid Identifier or bird not registered for this race.";
    }
}


// 5. Recent Results Fetch
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clocking System - GPFC Club</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/clocking.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="main-content">
    <div class="clocking-container">

        <?php if($success_msg): ?>
            <div class="clock-card" style="border-left: 5px solid var(--success); background: #ecfdf5;">
                <div style="color: #065f46; font-weight: 700;">
                    <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="clock-card" style="border-left: 5px solid var(--danger); background: #fef2f2;">
                <div style="color: #991b1b; font-weight: 700;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="clock-card">
            <div class="card-header"><i class="fa-solid fa-chart-pie"></i> Race Overview</div>
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-lbl">My Birds in Pooling</span>
                    <span class="stat-val"><?= $my_pooling_count ?></span>
                </div>
                <div class="stat-item" style="border-color: var(--accent);">
                    <span class="stat-lbl">Total Birds Clocked</span>
                    <span class="stat-val" style="color: var(--accent);"><?= $total_birds_clocked ?></span>
                </div>
            </div>
        </div>

        <div class="clock-card">
            <div class="card-header"><i class="fa-solid fa-bolt-lightning"></i> Instant Clocking</div>
            <form method="POST">
                <div class="input-group">
                    <label>Active Race</label>
                    <select name="race_id" required onchange="window.location.href='?race_id=' + this.value">
                        <option value="">-- Select Race --</option>
                        <?php 
                        $active = $conn->query("SELECT id, race_name FROM races ORDER BY id DESC"); 
                        while($r = $active->fetch_assoc()): ?>
                            <option value="<?= $r['id'] ?>" <?= ($stats_race_id == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['race_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Ring Number / Sticker Code</label>
                    <input type="text" name="identifier" placeholder="e.g. PHA 12345" required autocomplete="off">
                </div>

                <button type="submit" name="clock_bird" class="btn-clock">
                    SUBMIT ARRIVAL
                </button>
            </form>
        </div>

        <div class="clock-card">
            <div class="card-header"><i class="fa-solid fa-history"></i> My Recent Arrivals</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ring Number</th>
                            <th>Category</th>
                            <th>Distance</th>
                            <th>Arrival Time</th>
                            <th>Speed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_clocks->num_rows > 0): ?>
                            <?php while($row = $recent_clocks->fetch_assoc()): 
                                $badge = ($row['category'] == 'Old Bird') ? 'bg-old' : (($row['category'] == 'Off Color') ? 'bg-off' : 'bg-young');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['ring_number']) ?></strong></td>
                                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($row['category']) ?></span></td>
                                <td style="color: var(--text-muted);"><?= number_format($row['distance_km'], 3) ?> km</td>
                                <td><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                                <td style="font-weight: 800; color: var(--primary);"><?= number_format($row['speed_mpm'], 2) ?> <small>MPM</small></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding:40px; text-align:center; color: var(--text-muted);">Ready for your first arrival...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($sms_data): ?>
<div class="sms-overlay" id="smsOverlay">
    <div class="phone-container">
        <div class="phone-inner">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 40px; height: 5px; background: #e5e7eb; border-radius: 10px; margin: 0 auto 10px;"></div>
                <span style="font-size: 10px; color: var(--text-muted); text-transform: uppercase;">Message Delivered</span>
            </div>
            
            <div style="background: #f3f4f6; padding: 15px; border-radius: 15px; font-size: 14px; border-bottom-left-radius: 2px;">
                <strong>GPFC CLUB:</strong><br>
                Bird <strong><?= htmlspecialchars($sms_data['ring']) ?></strong> successfully clocked!<br><br>
                Race: <?= htmlspecialchars($sms_data['race']) ?><br>
                Speed: <span style="color: var(--primary); font-weight: bold;"><?= $sms_data['speed'] ?> MPM</span>
            </div>

            <div style="flex-grow: 1;"></div>
            <button onclick="document.getElementById('smsOverlay').style.display='none'" 
                    style="width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">
                CONTINUE CLOCKING
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>