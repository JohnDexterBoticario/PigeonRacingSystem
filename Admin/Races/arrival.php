<?php
/**
 * Manual Arrival Entry & Race Finalization
 * Handles manual input of arrival times, speed calculations, and final ranking.
 */

session_start();
require_once "../../Config/database.php"; 

/**
 * Function: checkAdminAccess
 * Ensures only authorized administrators can finalize races.
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

/**
 * Logic: Handle Finish Race & Final Ranking
 * Marks the race as 'Completed' and assigns ranks based on Speed (MPM) descending.
 */
if (isset($_POST['finish_race'])) {
    $race_id = intval($_POST['race_id']);

    $conn->begin_transaction();
    try {
        // Fetch all results for this race ordered by speed descending
        $results = $conn->query("SELECT id FROM race_results WHERE race_id = $race_id ORDER BY speed_mpm DESC");
        
        $rank = 1;
        while ($r = $results->fetch_assoc()) {
            $stmt_rank = $conn->prepare("UPDATE race_results SET rank = ? WHERE id = ?");
            $stmt_rank->bind_param("ii", $rank, $r['id']);
            $stmt_rank->execute();
            $rank++;
        }

        // Update race status to publicize results
        $stmt_finish = $conn->prepare("UPDATE races SET status = 'Completed' WHERE id = ?");
        $stmt_finish->bind_param("i", $race_id);
        $stmt_finish->execute();

        $conn->commit();
        echo "<script>alert('Race finalized! Rankings have been calculated.'); window.location.href='arrival.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error finalizing race: " . $e->getMessage();
    }
}

/**
 * Logic: Save Manual Arrivals
 * Calculates Speed MPM based on the release datetime and arrival time provided.
 */
if (isset($_POST['save_arrivals'])) {
    $race_id = intval($_POST['race_id']);
    $arrival_times = $_POST['arrival_time']; 
    $sticker_codes = $_POST['sticker_code'];

    // Get the release timestamp for speed calculation
    $r_stmt = $conn->prepare("SELECT release_datetime FROM races WHERE id = ?");
    $r_stmt->bind_param("i", $race_id);
    $r_stmt->execute();
    $race_start = $r_stmt->get_result()->fetch_assoc()['release_datetime'];

    foreach ($arrival_times as $entry_id => $arrival_time) {
        if (empty($arrival_time)) continue; 

        // Fetch bird details and distance
        $stmt2 = $conn->prepare("SELECT distance_km, pigeon_id FROM race_entries WHERE id = ?");
        $stmt2->bind_param("i", $entry_id);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        
        if (!$row) continue;

        $distance_km = $row['distance_km'];
        $pigeon_id = $row['pigeon_id'];

        // Calculate Speed (Meters / Minutes)
        $diff_seconds = strtotime($arrival_time) - strtotime($race_start);
        $minutes = $diff_seconds / 60;
        
        // Protect against zero or negative duration
        $speed = ($minutes > 0) ? ($distance_km * 1000) / $minutes : 0;

        // Save or update result
        $stmt3 = $conn->prepare("
            INSERT INTO race_results (race_id, pigeon_id, arrival_time, speed_mpm, distance_km)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                arrival_time = VALUES(arrival_time), 
                speed_mpm = VALUES(speed_mpm), 
                distance_km = VALUES(distance_km)
        ");
        $stmt3->bind_param("iisdd", $race_id, $pigeon_id, $arrival_time, $speed, $distance_km);
        $stmt3->execute();
    }
    $success_msg = "Manual arrivals saved successfully!";
}

// Data Fetching: Active Races
$active_races = $conn->query("SELECT id, race_name FROM races WHERE status = 'Released'")->fetch_all(MYSQLI_ASSOC);

// Data Fetching: Entries for specific race
$entries = [];
if (isset($_POST['load_race']) || isset($_POST['save_arrivals'])) {
    $race_id = intval($_POST['race_id']);
    $stmt = $conn->prepare("
        SELECT re.id, p.ring_number, re.sticker_code, re.distance_km, rr.arrival_time
        FROM race_entries re 
        JOIN pigeons p ON re.pigeon_id = p.id 
        LEFT JOIN race_results rr ON (re.race_id = rr.race_id AND re.pigeon_id = rr.pigeon_id)
        WHERE re.race_id = ?
    ");
    $stmt->bind_param("i", $race_id);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include "../../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Arrival Entry - Admin</title>
    
    <!-- Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .main-content { margin-left: 260px; padding: 40px; background: #f8f9fa; min-height: 100vh; }
        .entry-card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .table-input { border-radius: 8px; border: 1px solid #dee2e6; padding: 5px 10px; font-size: 0.9rem; }
        .table-input:focus { border-color: #8a6b49; outline: none; box-shadow: 0 0 0 0.2rem rgba(138, 107, 73, 0.25); }
        .speed-calc-info { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container-fluid">
        <header class="mb-4">
            <h1 class="h3 fw-bold text-dark"><i class="fa-solid fa-stopwatch text-primary me-2"></i> Manual Arrival Entry</h1>
            <p class="text-muted">Enter arrival times for races without electronic clocking systems.</p>
        </header>

        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="alert alert-danger rounded-4 shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error_msg ?>
            </div>
        <?php endif; ?>

        <!-- Race Selector -->
        <div class="card entry-card p-4 mb-4">
            <form method="POST" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label small fw-bold text-uppercase tracking-wider text-muted">Select Active Race</label>
                    <select name="race_id" required class="form-select rounded-pill shadow-sm">
                        <option value="">-- Choose Released Race --</option>
                        <?php foreach($active_races as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= (isset($race_id) && $race_id == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['race_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mt-md-4 pt-md-2">
                    <button type="submit" name="load_race" class="btn btn-dark w-100 rounded-pill shadow-sm">
                        <i class="fa-solid fa-download me-2"></i> Load Registered Birds
                    </button>
                </div>
            </form>
        </div>

        <?php if($entries): ?>
        <form method="POST">
            <input type="hidden" name="race_id" value="<?= $race_id ?>">
            
            <div class="card entry-card p-0 overflow-hidden shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Ring Number</th>
                                <th>Sticker / Security</th>
                                <th>Distance</th>
                                <th style="width: 300px;">Arrival Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($entries as $e): ?>
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($e['ring_number']) ?></span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm w-75">
                                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-tag text-muted"></i></span>
                                        <input type="text" value="<?= htmlspecialchars($e['sticker_code'] ?? '') ?>" class="form-control border-start-0 bg-light" readonly>
                                    </div>
                                    <input type="hidden" name="sticker_code[<?= $e['id'] ?>]" value="<?= htmlspecialchars($e['sticker_code'] ?? '') ?>">
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill"><?= number_format($e['distance_km'], 3) ?> KM</span>
                                </td>
                                <td class="pe-4">
                                    <input type="datetime-local" 
                                           name="arrival_time[<?= $e['id'] ?>]" 
                                           class="form-control table-input" 
                                           value="<?= $e['arrival_time'] ? date('Y-m-d\TH:i', strtotime($e['arrival_time'])) : '' ?>"
                                           required>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer Actions -->
                <div class="bg-light p-4 border-top">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <button type="submit" name="save_arrivals" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow">
                                <i class="fa-solid fa-floppy-disk me-2"></i> SAVE ARRIVAL TIMES
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="finish_race" 
                                    class="btn btn-outline-danger btn-lg w-100 rounded-pill fw-bold"
                                    onclick="return confirm('Calculate rankings and close this race for public viewing?')">
                                <i class="fa-solid fa-flag-checkered me-2"></i> FINISH & RANK
                            </button>
                        </div>
                    </div>
                    <p class="speed-calc-info mt-3 text-center">
                        <i class="fa-solid fa-circle-info me-1"></i> 
                        Speeds are calculated as: (Distance in Meters) / (Flight Duration in Minutes)
                    </p>
                </div>
            </div>
        </form>
        <?php elseif(isset($race_id)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-dove text-muted fs-1 mb-3"></i>
                <p class="text-muted">No birds have been registered for this race yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>