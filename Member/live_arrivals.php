<?php
session_start();
require_once "../Config/database.php";

// 1. Security Check: Only logged-in members or admins can view the feed
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

// 2. Sidebar and Header logic
include "../Includes/sidebar.php";

$race_id = $_GET['race_id'] ?? null;

// 3. Fetch all arrivals for this specific race, joining member and loft details
$query = "
    SELECT 
        m.full_name as member_name, 
        m.loft_name, 
        p.ring_number, 
        p.category, 
        res.arrival_time, 
        res.speed_mpm
    FROM race_results res
    JOIN pigeons p ON res.pigeon_id = p.id
    JOIN members m ON p.member_id = m.id
    WHERE res.race_id = ?
    ORDER BY res.speed_mpm DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $race_id);
$stmt->execute();
$arrivals = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Arrival Feed - Pigeon Racing</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta http-equiv="refresh" content="30"> 
</head>
<body>

<div class="main-content">
    <div class="container" style="max-width: 1000px; margin: auto;">
        <div class="card" style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #2c3e50;"><i class="fa-solid fa-rss" style="color: #fc00ff;"></i> Live Arrival Feed</h3>
                <a href="clocking.php?race_id=<?= $race_id ?>" class="btn" style="background: #8a6b49; color: white; text-decoration: none; padding: 8px 20px; border-radius: 5px; font-weight: bold;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Clocking
                </a>
            </div>

            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#8a6b49; color: white;">
                        <th style="padding:12px; text-align: center;">Member Name</th>
                        <th style="padding:12px; text-align: center;">Loft Name</th>
                        <th style="padding:12px; text-align: center;">Ring Number</th>
                        <th style="padding:12px; text-align: center;">Category</th>
                        <th style="padding:12px; text-align: center;">Clock Time</th>
                        <th style="padding:12px; text-align: center;">Speed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($arrivals->num_rows > 0): ?>
                        <?php while($row = $arrivals->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee; text-align: center;">
                                <td style="padding:12px;"><?= htmlspecialchars($row['member_name']) ?></td>
                                <td style="padding:12px;"><?= htmlspecialchars($row['loft_name']) ?></td>
                                <td style="padding:12px;"><strong><?= htmlspecialchars($row['ring_number']) ?></strong></td>
                                <td style="padding:12px;"><?= htmlspecialchars($row['category'] ?? 'N/A') ?></td>
                                <td style="padding:12px;"><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                                <td style="padding:12px; font-weight:bold; color:#fc00ff;"><?= number_format($row['speed_mpm'], 2) ?> MPM</td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:40px; text-align:center; color:#999;">
                                <i class="fa-solid fa-hourglass-start" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
                                Waiting for the first bird to arrive...
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>