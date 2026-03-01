<?php
session_start();
require_once "../Config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

include "../Includes/sidebar.php"; 

// --- Data Fetching ---
$total_members = $conn->query("SELECT COUNT(*) as total FROM members")->fetch_assoc()['total'];
$total_pigeons = $conn->query("SELECT COUNT(*) as total FROM pigeons")->fetch_assoc()['total'];
$total_races = $conn->query("SELECT COUNT(*) as total FROM races")->fetch_assoc()['total'];

$active_status_races = $conn->query("SELECT * FROM races WHERE status != 'Completed' ORDER BY release_datetime ASC");
$active_race = $conn->query("SELECT id, race_name, release_point, distance_km, release_datetime FROM races WHERE status = 'Released' ORDER BY id DESC LIMIT 1")->fetch_assoc();
$recent_logs = $conn->query("SELECT action, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pigeon Racing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/dashboard.css">
</head>
<body>

<div class="main-content">
    <header class="mb-5">
        <h1 class="fw-bold text-dark">Admin Dashboard</h1>
        <p class="text-muted">Real-time system statistics and club management.</p>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Members</h3>
            <p><?= number_format($total_members) ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Pigeons</h3>
            <p><?= number_format($total_pigeons) ?></p>
        </div>
        <div class="stat-card">
            <h3>Races Managed</h3>
            <p><?= number_format($total_races) ?></p>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="left-col">
            <div class="card mb-4">
                <h3><i class="fa-solid fa-bolt-lightning"></i> Quick Actions</h3>
                <div class="action-btns">
                    <a href="AdminRegistration.php" class="btn-action">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Register Member</span>
                    </a>
                    <a href="Pigeons/register_pigeon.php" class="btn-action">
                        <i class="fa-solid fa-dove"></i>
                        <span>Register Pigeon</span>
                    </a>
                    <a href="Races/create.php" class="btn-action">
                        <i class="fa-solid fa-calendar-plus"></i>
                        <span>Schedule Race</span>
                    </a>
                    <a href="javascript:void(0)" onclick="loadForecast()" class="btn-action">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <span>Race Forecast</span>
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <h3 class="mb-3"><i class="fa-solid fa-tower-broadcast"></i> Current Race Status</h3>
                <div class="row">
                    <?php if ($active_status_races && $active_status_races->num_rows > 0): ?>
                        <?php while($race = $active_status_races->fetch_assoc()): 
                            $status_class = strtolower($race['status']);
                            $target_url = ($race['status'] === 'Released') 
                                ? "/pigeon-racing-system/Member/live_arrivals.php?race_id=" . $race['id'] 
                                : "/pigeon-racing-system/Public/RaceResult.php?race_id=" . $race['id'];
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="race-card border p-3 rounded shadow-sm">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="h6 fw-bold text-primary mb-0"><?= htmlspecialchars($race['race_name']) ?></h5>
                                    <span class="badge rounded-pill bg-<?= ($status_class === 'released') ? 'success' : 'warning' ?>">
                                        <?= $race['status'] ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-3">
                                    <p class="mb-1"><i class="fa-solid fa-location-dot"></i> Point: <?= htmlspecialchars($race['release_point']) ?></p>
                                    <p class="mb-1"><i class="fa-solid fa-clock"></i> Released: <?= date('M d, g:i a', strtotime($race['release_datetime'])) ?></p>
                                    <p class="mb-0"><i class="fa-solid fa-cloud-sun"></i> Weather: <?= htmlspecialchars($race['weather'] ?? 'Clear') ?></p>
                                </div>
                                <a href="<?= $target_url ?>" class="btn btn-view btn-sm w-100">View Details</a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-4 text-muted">
                            <p class="m-0 small">No active races currently found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-0 overflow-hidden">
                <div class="bg-primary p-3 text-white d-flex justify-content-between align-items-center">
                    <h4 class="m-0 h6 fw-bold"><i class="fa-solid fa-clock"></i> Active Race Forecast</h4>
                    <?php if($active_race): ?>
                        <span class="badge bg-light text-dark"><?= number_format($active_race['distance_km'], 2) ?> KM</span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <?php if ($active_race): ?>
                        <p class="text-muted mb-4 small">Current Race: <strong><?= htmlspecialchars($active_race['race_name']) ?></strong></p>
                        <div class="row text-center g-2">
                            <?php 
                            $speeds = [1300, 1200, 1100, 1000];
                            foreach($speeds as $speed): 
                                $total_min = ($active_race['distance_km'] * 1000) / $speed;
                                $arrival = date('h:i A', strtotime($active_race['release_datetime'] . " + " . round($total_min) . " minutes"));
                            ?>
                                <div class="col-3 border-end last-child-border-0">
                                    <small class="text-muted d-block" style="font-size: 10px;"><?= $speed ?> mpm</small>
                                    <strong class="text-primary" style="font-size: 13px;"><?= $arrival ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="fa-solid fa-info-circle mb-2"></i>
                            <p class="m-0">No active race currently released.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="right-col">
            <div class="card">
                <h3><i class="fa-solid fa-list-ul"></i> Recent Activity</h3>
                <ul class="list-unstyled mt-3 mb-0">
                    <?php if($recent_logs && $recent_logs->num_rows > 0): ?>
                        <?php while($log = $recent_logs->fetch_assoc()): ?>
                            <li class="pb-3 mb-3 border-bottom">
                                <span class="text-dark small fw-bold d-block"><?= htmlspecialchars($log['action']) ?></span>
                                <small class="text-muted" style="font-size: 11px;">
                                    <i class="fa-regular fa-clock"></i> <?= date('M d, g:i A', strtotime($log['created_at'])) ?>
                                </small>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="text-center py-4 text-muted small">No logs found.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="forecastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fa-solid fa-magnifying-glass-chart me-2"></i>Race Arrival Forecast</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="forecastContent">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ADDED: AJAX function to load get_forecast.php content into the modal
function loadForecast() {
    const forecastModal = new bootstrap.Modal(document.getElementById('forecastModal'));
    forecastModal.show();

    // Reset loader while fetching
    document.getElementById('forecastContent').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></div>';

    fetch('get_forecast.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('forecastContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('forecastContent').innerHTML = '<div class="alert alert-danger">Error loading data. Check if get_forecast.php exists.</div>';
        });
}
</script>

</body>
</html>