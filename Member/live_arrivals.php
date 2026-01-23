<?php
session_start();
require_once "../Config/database.php";

// Security: Only admins or authorized members should see the global feed
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

$race_id = $_GET['race_id'] ?? null;

// Fetch all arrivals for this specific race, joining member and loft details
$query = "
    SELECT 
        m.fullname as member_name, 
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

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fa-solid fa-rss"></i> Live Arrival Feed</h3>
        <a href="clocking.php?race_id=<?= $race_id ?>" class="btn-back">Back</a>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-top:20px;">
        <thead>
            <tr style="background:#8a6b49; color: white;">
                <th style="padding:12px;">Member Name</th>
                <th style="padding:12px;">Loft Name</th>
                <th style="padding:12px;">Ring Number</th>
                <th style="padding:12px;">Category</th>
                <th style="padding:12px;">Clock Time</th>
                <th style="padding:12px;">Speed</th>
            </tr>
        </thead>
        <tbody>
            <?php if($arrivals->num_rows > 0): ?>
                <?php while($row = $arrivals->fetch_assoc()): ?>
                    <tr style="border-bottom: 1px solid #eee; text-align: center;">
                        <td style="padding:12px;"><?= htmlspecialchars($row['member_name']) ?></td>
                        <td style="padding:12px;"><?= htmlspecialchars($row['loft_name']) ?></td>
                        <td style="padding:12px;"><strong><?= htmlspecialchars($row['ring_number']) ?></strong></td>
                        <td style="padding:12px;"><?= htmlspecialchars($row['category']) ?></td>
                        <td style="padding:12px;"><?= date('H:i:s', strtotime($row['arrival_time'])) ?></td>
                        <td style="padding:12px; font-weight:bold; color:#fc00ff;"><?= number_format($row['speed_mpm'], 2) ?> MPM</td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="padding:40px; text-align:center; color:#999;">
                        Waiting for first arrival...
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>