<?php
session_start();
require_once "../Config/database.php"; //

// 1. Admin Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

// 2. Fetch System Logs
$query = "SELECT l.*, u.username, u.role 
          FROM system_logs l 
          LEFT JOIN users u ON l.user_id = u.id 
          ORDER BY l.created_at DESC LIMIT 50";
$logs = $conn->query($query);

// 3. Include Sidebar
include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs - Admin</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-action { font-weight: 600; color: #8a6b49; }
        .log-timestamp { font-size: 12px; color: #888; }
        .role-pill { font-size: 10px; padding: 2px 8px; border-radius: 10px; background: #eee; }
    </style>
</head>
<body>

<div class="main-content"> <div class="card"> <h2><i class="fa-solid fa-clipboard-list" style="color: #8a6b49;"></i> System Activity Logs</h2>
        <p style="color: #666; margin-bottom: 25px;">Track all administrative actions, registrations, and race schedules.</p>

        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if($logs && $logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="log-timestamp"><?= date('M d, Y H:i:s', strtotime($row['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['username'] ?? 'System') ?></strong>
                            <span class="role-pill"><?= $row['role'] ?? 'Bot' ?></span>
                        </td>
                        <td class="log-action"><?= htmlspecialchars($row['action']) ?></td>
                        <td style="font-size: 13px; color: #555;"><?= htmlspecialchars($row['details']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;">No activity logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>