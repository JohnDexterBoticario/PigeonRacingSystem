<?php
session_start();
require_once "../Config/database.php"; 

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

// 2. Filter Logic
$filter_action = $_GET['filter_action'] ?? '';
$search_query = $_GET['search'] ?? '';

function getSystemLogs($conn, $action, $search) {
    // Join with users table to get the username
    $sql = "SELECT l.*, u.username AS display_name, u.role 
            FROM system_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            WHERE 1=1";
    
    if (!empty($action)) { 
        $sql .= " AND l.action = '" . $conn->real_escape_string($action) . "'"; 
    }
    if (!empty($search)) { 
        $sql .= " AND l.details LIKE '%" . $conn->real_escape_string($search) . "%'"; 
    }
    
    $sql .= " ORDER BY l.created_at DESC LIMIT 100";
    return $conn->query($sql);
}

$logs = getSystemLogs($conn, $filter_action, $search_query);
$action_types = $conn->query("SELECT DISTINCT action FROM system_logs");

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | Admin</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="main-content">
    <div class="log-header-flex no-print">
        <div>
            <h1 class="page-title"><i class="fa-solid fa-shield-halved"></i> Audit Logs</h1>
            <p class="page-subtitle">Monitoring all administrative activities across the system.</p>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn-print">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <div class="filter-card no-print">
        <form method="GET" class="filter-form">
            <div class="input-group">
                <i class="fa-solid fa-filter"></i>
                <select name="filter_action" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <?php while($type = $action_types->fetch_assoc()): ?>
                        <option value="<?= $type['action'] ?>" <?= $filter_action == $type['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['action']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="input-group search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search details..." value="<?= htmlspecialchars($search_query) ?>">
            </div>

            <button type="submit" class="btn-search">Apply</button>
            
            <?php if($filter_action || $search_query): ?>
                <a href="logs.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="log-card">
        <div class="print-only-header">
            <h2>Pigeon Racing System - Activity Report</h2>
            <p>Generated on: <?= date('F j, Y, g:i a') ?></p>
        </div>

        <div class="table-responsive">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $badge_class = strtolower(str_replace(' ', '-', $row['action'] ?? 'default'));
                        ?>
                        <tr>
                            <td class="col-time">
                                <span class="date-text"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                                <span class="time-text"><?= date('h:i A', strtotime($row['created_at'])) ?></span>
                            </td>
                            <td class="col-user">
                                <div class="user-pill">
                                    <i class="fa-solid fa-circle-user"></i>
                                    <strong><?= htmlspecialchars($row['display_name'] ?? 'System') ?></strong>
                                </div>
                            </td>
                            <td class="col-action">
                                <span class="action-tag tag-<?= $badge_class ?>">
                                    <?= htmlspecialchars($row['action']) ?>
                                </span>
                            </td>
                            <td class="col-details">
                                <?= htmlspecialchars($row['details']) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <p>No logs found matching your criteria.</p>
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