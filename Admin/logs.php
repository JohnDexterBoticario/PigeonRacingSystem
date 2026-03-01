<?php
/**
 * System Activity Logs
 * Displays a chronological list of actions performed by users and the system.
 */

session_start();
require_once "../Config/database.php"; 

/**
 * Function: checkAdminSession
 * Ensures only authorized administrators can view system activity.
 */
function checkAdminSession() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../Auth/login.php");
        exit();
    }
}
checkAdminSession();

/**
 * Logic: Fetch Activity Data
 * Joins 'system_logs' with 'users' to display the username and role alongside the action.
 * Limited to last 50 entries for performance.
 */
function getSystemLogs($conn) {
    $query = "SELECT l.*, u.username, u.role 
              FROM system_logs l 
              LEFT JOIN users u ON l.user_id = u.id 
              ORDER BY l.created_at DESC LIMIT 50";
    return $conn->query($query);
}

$logs = getSystemLogs($conn);

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="../Assets/Css/logs.css">
</head>
<body>

<div class="main-content">
    <div class="log-card">
        <header class="mb-4">
            <h2 class="h4 text-dark">
                <i class="fa-solid fa-clipboard-list me-2" style="color: #8a6b49;"></i> 
                System Activity Logs
            </h2>
            <p class="text-muted small">Track all administrative actions, registrations, and race schedules.</p>
        </header>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Timestamp</th>
                        <th scope="col">User</th>
                        <th scope="col">Action</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td class="log-timestamp">
                                <i class="fa-regular fa-clock me-1 text-muted"></i>
                                <?= date('M d, Y | g:i A', strtotime($row['created_at'])) ?>
                            </td>

                            <td>
                                <div class="d-flex align-items-center">
                                    <strong class="text-dark"><?= htmlspecialchars($row['username'] ?? 'System') ?></strong>
                                    <span class="role-pill"><?= htmlspecialchars($row['role'] ?? 'System') ?></span>
                                </div>
                            </td>

                            <td class="log-action">
                                <?= htmlspecialchars($row['action']) ?>
                            </td>

                            <td class="text-muted" style="font-size: 13px;">
                                <?= htmlspecialchars($row['details']) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox d-block mb-2 fs-2"></i>
                                No activity logs found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>