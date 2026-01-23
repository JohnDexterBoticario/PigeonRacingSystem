<?php
require_once "../Config/database.php"; //

// Fetch active races (Pending or Released)
$active_races = $conn->query("SELECT * FROM races WHERE status != 'Completed' ORDER BY release_datetime ASC"); //
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Race Status</title>
    <link rel="stylesheet" href="../Assets/Css/style.css"> 
    <link rel="stylesheet" href="../Assets/Css/admin.css">
</head>
<body>
    <div class="container">
        <h2>📡 Live Race Status Board</h2>
        
        <?php if ($active_races->num_rows > 0): ?>
            <?php while($race = $active_races->fetch_assoc()): ?>
                <div class="admin-card">
                    <h3><?= htmlspecialchars($race['race_name']) ?></h3>
                    <p><strong>Release Point:</strong> <?= htmlspecialchars($race['release_point']) ?></p>
                    <p><strong>Status:</strong> <span class="status-<?= strtolower($race['status']) ?>"><?= $race['status'] ?></span></p>
                    <p><strong>Release Time:</strong> <?= date('F j, Y, g:i a', strtotime($race['release_datetime'])) ?></p>
                    
                    <?php if ($race['status'] === 'Released'): ?>
                        <div class="speed-badge">Birds are currently in flight!</div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No active races at the moment. Check the <a href="Leaderboard.php">Leaderboard</a> for past results.</p>
        <?php endif; ?>
    </div>
</body>
</html>