<?php
/**
 * Race Results - Pigeon Racing System
 * This file handles displaying race results and leaderboards.
 */
session_start();
require_once "../Config/database.php"; 

// 1. Initialize variables with unique names to prevent sidebar conflicts
// Renamed from $currentRace to $activeRaceInfo to avoid collision with sidebar.php
$activeRaceInfo = [
    'id' => 0,
    'release_datetime' => date('Y-m-d H:i:s'), 
    'release_point' => 'Not Specified', 
    'race_name' => 'Race Results',
    'status' => 'No Race Selected',
    'weather' => 'Clear Skies / Calm'
];
$totalEntriesCount = 0;
$totalArrivedCount = 0;
$raceLeaderboard = [];
$clubName = "Pigeon Racing Club International";

// 2. Fetch all races to populate the dropdown
$races_query = $conn->query("SELECT id, race_name, release_datetime, status FROM races ORDER BY release_datetime DESC");
$races_list = $races_query ? $races_query->fetch_all(MYSQLI_ASSOC) : [];

// 3. Determine which race to display (defaults to the latest race in the list)
$selected_race_id = $_GET['race_id'] ?? ($races_list[0]['id'] ?? null);

if ($selected_race_id) {
    // Fetch Race Details using Prepared Statements
    $stmt = $conn->prepare("SELECT * FROM races WHERE id = ?");
    $stmt->bind_param("i", $selected_race_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ensure we only overwrite if data is actually found
    if($result && $result->num_rows > 0) {
        $race_data = $result->fetch_assoc();
        // Merge fetched data with our defaults
        $activeRaceInfo = array_merge($activeRaceInfo, $race_data);
    }

    // Fetch Participation Stats (Total Entry)
    // IMPORTANT: Verify that rows in 'race_entries' have race_id = $selected_race_id
    $stmt_entry = $conn->prepare("SELECT COUNT(*) as total FROM race_entries WHERE race_id = ?");
    $stmt_entry->bind_param("i", $selected_race_id);
    $stmt_entry->execute();
    $entry_res = $stmt_entry->get_result()->fetch_assoc();
    $totalEntriesCount = $entry_res['total'] ?? 0;

    // Fetch Participation Stats (Total Arrived)
    $stmt_arrived = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $stmt_arrived->bind_param("i", $selected_race_id);
    $stmt_arrived->execute();
    $arrived_res = $stmt_arrived->get_result()->fetch_assoc();
    $totalArrivedCount = $arrived_res['total'] ?? 0;

    // Fetch Leaderboard with Member/Loft Joins
    $results_stmt = $conn->prepare("
        SELECT 
            res.rank, 
            p.ring_number, 
            m.loft_name, 
            res.arrival_time, 
            res.speed_mpm, 
            res.distance_km
        FROM race_results res
        JOIN pigeons p ON res.pigeon_id = p.id
        JOIN members m ON p.member_id = m.id
        WHERE res.race_id = ?
        ORDER BY res.rank ASC
    ");
    $results_stmt->bind_param("i", $selected_race_id);
    $results_stmt->execute();
    $raceLeaderboard = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Sidebar - Including this AFTER fetching data is fine as long as variable names are unique
include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Race Results - <?= htmlspecialchars($clubName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(45deg, #00dbde 0%, #fc00ff 100%);
            min-height: 100vh;
        }
        .custom-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .status-badge {
            background-color: #f1f5f9;
            color: #8a6b49;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            border: 1px solid #e2e8f0;
        }
        .ring-badge {
            background: #2c3e50;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .custom-card { box-shadow: none !important; border: 1px solid #eee; padding: 20px !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="font-sans antialiased text-slate-800">

<div class="md:ml-64 p-4 md:p-8 main-content">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Filter Bar -->
        <div class="custom-card p-4 px-8 no-print flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-[#8a6b49] font-bold uppercase tracking-wider">
                <i class="fa-solid fa-trophy"></i> 
                Race History
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" class="flex items-center">
                    <select name="race_id" onchange="this.form.submit()" 
                            class="bg-slate-50 border border-slate-200 rounded-full px-4 py-2 text-sm font-semibold text-slate-600 outline-none focus:ring-2 focus:ring-[#8a6b49]">
                        <?php if(empty($races_list)): ?>
                            <option value="">No races available</option>
                        <?php else: ?>
                            <?php foreach ($races_list as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $selected_race_id == $r['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['race_name'] ?? 'Untitled Race') ?> (<?= date('M d, Y', strtotime($r['release_datetime'] ?? 'now')) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </form>
                
                <button onclick="window.print()" class="bg-[#8a6b49] hover:bg-[#765d3f] text-white px-5 py-2 rounded-full font-bold text-sm flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-file-export"></i> Export Result
                </button>
            </div>
        </div>

        <!-- Official Result Report -->
        <div class="custom-card p-8 md:p-12" id="printArea">
            <div class="text-center border-b-2 border-slate-100 pb-8 mb-8">
                <h1 class="text-3xl font-black text-[#8a6b49] uppercase tracking-tighter mb-1">
                    <?= htmlspecialchars($clubName) ?>
                </h1>
                <div class="flex items-center justify-center gap-3 mb-2">
                    <h2 class="text-xl font-bold text-slate-800">Official Race Result</h2>
                    <span class="status-badge"><?= htmlspecialchars($activeRaceInfo['status'] ?? 'Unknown') ?></span>
                </div>
                <p class="text-slate-400 font-medium">
                    Race Date: <strong class="text-slate-600"><?= date('F j, Y', strtotime($activeRaceInfo['release_datetime'] ?? 'now')) ?></strong>
                </p>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 bg-slate-50 p-6 rounded-2xl border border-slate-100">
                <div class="space-y-3">
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm text-[#8a6b49]">📍</span>
                        <span class="font-medium text-sm">Release Point:</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($activeRaceInfo['release_point'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm text-[#8a6b49]">⏰</span>
                        <span class="font-medium text-sm">Time Release:</span>
                        <span class="font-bold text-slate-800"><?= date('h:i:s A', strtotime($activeRaceInfo['release_datetime'] ?? 'now')) ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm text-[#8a6b49]">🌤</span>
                        <span class="font-medium text-sm">Weather:</span>
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($activeRaceInfo['weather'] ?? 'Clear Skies') ?></span>
                    </div>
                </div>
                <div class="md:border-l border-slate-200 md:pl-10 space-y-3">
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="font-medium text-sm">📊 Total Entry:</span>
                        <span class="font-bold text-slate-800"><?= (int)$totalEntriesCount ?> Birds</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="font-medium text-sm">✅ Total Arrived:</span>
                        <span class="font-bold text-slate-800"><?= (int)$totalArrivedCount ?> Birds</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600">
                        <span class="font-medium text-sm">📉 Min. Speed:</span>
                        <span class="font-bold text-slate-800">
                            <?= !empty($raceLeaderboard) ? number_format((float)end($raceLeaderboard)['speed_mpm'], 2) . ' MPM' : '0.00 MPM' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Leaderboard Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800 text-white">
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Ring Number</th>
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Owner / Loft</th>
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Distance (KM)</th>
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Arrival Time</th>
                            <th class="px-6 py-4 font-bold text-xs uppercase tracking-wider">Speed (MPM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if(!empty($raceLeaderboard)): ?>
                            <?php foreach ($raceLeaderboard as $row): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 text-xl font-black text-slate-800">#<?= $row['rank'] ?></td>
                                <td class="px-6 py-4"><span class="ring-badge"><?= htmlspecialchars($row['ring_number'] ?? 'N/A') ?></span></td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['loft_name'] ?? 'Unknown') ?></td>
                                <td class="px-6 py-4 text-slate-600 font-medium"><?= number_format((float)($row['distance_km'] ?? 0), 3) ?></td>
                                <td class="px-6 py-4 text-slate-500 font-mono text-sm"><?= date('H:i:s', strtotime($row['arrival_time'] ?? 'now')) ?></td>
                                <td class="px-6 py-4 text-lg font-bold text-[#8a6b49]"><?= number_format((float)($row['speed_mpm'] ?? 0), 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center text-slate-400 italic font-medium">
                                    No bird standings available for this race yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer (Print Only) -->
            <div class="mt-12 text-center text-slate-300 text-[10px] font-bold uppercase tracking-[0.2em]">
                Generated by Pigeon Racing System © <?= date('Y') ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>