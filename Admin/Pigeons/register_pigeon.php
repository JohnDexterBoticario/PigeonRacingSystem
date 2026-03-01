<?php
/**
 * Pigeon & Race Entry Management - Optimized
 */
session_start();

// 1. SECURITY: Role Check & CSRF Token Generation
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once "../../Config/database.php"; 

$success_msg = "";
$error_msg = "";

/**
 * 2. ACTION HANDLERS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // --- HANDLE NEW PIGEON REGISTRATION ---
    if (isset($_POST['register_pigeon'])) {
        $member_id = $_POST['member_id'] ?? null;
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $ring_number = strtoupper(trim($_POST['ring_number'] ?? ''));

        if (!$member_id || !$year || !$ring_number) {
            $error_msg = "All fields are required.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, year, category) VALUES (?, ?, ?, 'Young Bird')");
                $stmt->bind_param("iss", $member_id, $ring_number, $year);
                $stmt->execute();
                $success_msg = "Pigeon $ring_number registered successfully!";
            } catch (mysqli_sql_exception $e) {
                $error_msg = ($e->getCode() == 1062) ? "Error: Ring number already exists." : "Database Error.";
            }
        }
    }

    // --- HANDLE BATCH RACE ENTRY ---
    if (isset($_POST['assign_to_race'])) {
        $race_id = $_POST['race_id'] ?? null;
        $pigeon_ids = $_POST['pigeon_ids'] ?? [];

        if (!$race_id || empty($pigeon_ids)) {
            $error_msg = "Select a race and at least one bird.";
        } else {
            try {
                // Fetch race distance once
                $dist_stmt = $conn->prepare("SELECT distance_km FROM races WHERE id = ?");
                $dist_stmt->bind_param("i", $race_id);
                $dist_stmt->execute();
                $race_dist = $dist_stmt->get_result()->fetch_assoc()['distance_km'] ?? 0;

                // Optimization: Use a single query for multiple inserts
                // 'INSERT IGNORE' skips duplicates automatically if you have a UNIQUE constraint on (race_id, pigeon_id)
                $values = [];
                $types = "";
                $params = [];
                
                foreach ($pigeon_ids as $p_id) {
                    $values[] = "(?, ?, ?)";
                    $types .= "iid";
                    array_push($params, $race_id, $p_id, $race_dist);
                }
                
                $query = "INSERT IGNORE INTO race_entries (race_id, pigeon_id, distance_km) VALUES " . implode(',', $values);
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                $success_msg = count($pigeon_ids) . " birds processed for the race.";
            } catch (Exception $e) {
                $error_msg = "Entry Error: " . $e->getMessage();
            }
        }
    }
}

/**
 * 3. DATA FETCHING (Using optimized queries)
 */
$members = $conn->query("SELECT m.id, u.full_name, m.loft_name FROM members m JOIN users u ON m.user_id = u.id ORDER BY u.full_name ASC")->fetch_all(MYSQLI_ASSOC);
$races = $conn->query("SELECT id, race_name, status FROM races WHERE status != 'Completed' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$pigeons = $conn->query("
    SELECT p.id, p.ring_number, m.loft_name 
    FROM pigeons p 
    JOIN members m ON p.member_id = m.id 
    ORDER BY p.ring_number ASC
")->fetch_all(MYSQLI_ASSOC);

include "../../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pigeon Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; min-height: 100vh; }
        .glass-card { background: white; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e2e8f0; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="text-slate-900">

<div class="md:ml-64 p-6">
    <div class="max-w-5xl mx-auto space-y-6">
        
        <?php if($success_msg): ?>
            <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded shadow-sm">
                <i class="fa-solid fa-check-circle mr-2"></i> <?= $success_msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="glass-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-feather text-sky-500"></i> Register New Bird
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Owner / Loft</label>
                        <select name="member_id" required class="w-full p-2.5 border rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                            <option value="">-- Select Member --</option>
                            <?php foreach($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['loft_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Year</label>
                            <input type="number" name="year" value="<?= date('Y') ?>" class="w-full p-2.5 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Ring Number</label>
                            <input type="text" name="ring_number" required class="w-full p-2.5 border rounded-lg" placeholder="EX: 2026-123">
                        </div>
                    </div>

                    <button type="submit" name="register_pigeon" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold py-2.5 rounded-lg transition-colors">
                        Add to System
                    </button>
                </form>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-stopwatch text-rose-500"></i> Race Entry
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div>
                        <select name="race_id" required class="w-full p-2.5 border rounded-lg mb-4">
                            <option value="">-- Select Active Race --</option>
                            <?php foreach($races as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['race_name']) ?> (<?= $r['status'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-slate-50 p-2 border-b flex justify-between items-center">
                            <input type="text" id="pigeonSearch" placeholder="Filter birds..." class="text-sm p-1 border rounded w-1/2">
                            <button type="button" onclick="toggleAllPigeons()" class="text-xs text-sky-600 font-bold uppercase">Select All</button>
                        </div>
                        <div id="pigeonList" class="max-h-60 overflow-y-auto p-2 custom-scrollbar grid grid-cols-1 gap-1">
                            <?php foreach($pigeons as $p): ?>
                                <label class="pigeon-item flex items-center gap-3 p-2 hover:bg-slate-50 rounded cursor-pointer border border-transparent hover:border-slate-200">
                                    <input type="checkbox" name="pigeon_ids[]" value="<?= $p['id'] ?>" class="w-4 h-4 rounded text-sky-600">
                                    <span class="text-sm">
                                        <b class="ring-num"><?= htmlspecialchars($p['ring_number']) ?></b> 
                                        <span class="text-slate-400 ml-2 loft-name text-xs"><?= htmlspecialchars($p['loft_name']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_to_race" class="w-full bg-slate-900 hover:bg-black text-white font-semibold py-2.5 rounded-lg transition-colors">
                        Confirm Entries
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    // Improved search filter
    document.getElementById('pigeonSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.pigeon-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(term) ? 'flex' : 'none';
        });
    });

    // Select all helper
    function toggleAllPigeons() {
        const checkboxes = document.querySelectorAll('input[name="pigeon_ids[]"]');
        const firstValue = checkboxes[0].checked;
        checkboxes.forEach(cb => {
            if (cb.closest('.pigeon-item').style.display !== 'none') {
                cb.checked = !firstValue;
            }
        });
    }
</script>
</body>
</html>