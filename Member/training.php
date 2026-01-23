<?php
// 1. Session and Security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../Config/database.php"; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";

// 2. Handle Training Log Submission
if (isset($_POST['log_training'])) {
    $p_id = $_POST['pigeon_id'];
    $dist = $_POST['distance'];
    $date = $_POST['date'];
    
    // Subquery ensures data integrity by linking the correct member_id
    $stmt = $conn->prepare("INSERT INTO training_logs (member_id, pigeon_id, training_date, distance_km) 
                            VALUES ((SELECT id FROM members WHERE user_id = ?), ?, ?, ?)");
    $stmt->bind_param("iisd", $user_id, $p_id, $date, $dist);
    
    if ($stmt->execute()) {
        $success_msg = "Training session logged successfully!";
    }
}

// 3. Fetch Pigeons for Dropdown
$query = "SELECT p.id, p.ring_number 
          FROM pigeons p 
          JOIN members m ON p.member_id = m.id 
          WHERE m.user_id = " . intval($user_id);
$pigeons = $conn->query($query);

// 4. NEW: Fetch Recent Training Logs to display in the table
$history_query = "
    SELECT t.distance_km, t.training_date, p.ring_number 
    FROM training_logs t
    JOIN pigeons p ON t.pigeon_id = p.id
    JOIN members m ON t.member_id = m.id
    WHERE m.user_id = " . intval($user_id) . "
    ORDER BY t.training_date DESC LIMIT 5";
$history = $conn->query($history_query);

// 5. Include Sidebar
include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pigeon Training - System</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/training.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="main-content"> 
    <div class="container" style="max-width: 600px; margin: auto;">
        <div class="card"> 
            <h2><i class="fa-solid fa-rocket" style="color: #fc00ff;"></i> Pigeon Training Log</h2>
            <p style="color: #666; margin-bottom: 25px;">Record your daily flight distances to track bird performance.</p>
            
            <?php if($success_msg): ?>
                <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fa-solid fa-check-circle"></i> <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Select Pigeon</label>
                    <select name="pigeon_id" required>
                        <?php if($pigeons && $pigeons->num_rows > 0): ?>
                            <?php while($p = $pigeons->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['ring_number']) ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No pigeons registered yet</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Training Distance (KM)</label>
                    <input type="number" step="0.01" name="distance" placeholder="Enter distance in KM" required>
                </div>

                <div class="input-group">
                    <label>Date of Training</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" name="log_training" class="btn-primary" style="width: 100%;">
                    Log Training Session
                </button>
            </form>
        </div>

        <div class="card" style="margin-top: 30px;">
            <h3>Recent Activities</h3>
            <table style="font-size: 14px; width: 100%;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 10px; text-align: left;">Pigeon</th>
                        <th style="padding: 10px; text-align: left;">Distance</th>
                        <th style="padding: 10px; text-align: left;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history && $history->num_rows > 0): ?>
                        <?php while($h = $history->fetch_assoc()): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($h['ring_number']) ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= $h['distance_km'] ?> KM</td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= date('M d, Y', strtotime($h['training_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center; padding: 20px;">No training logs found. Submit your first log above!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>