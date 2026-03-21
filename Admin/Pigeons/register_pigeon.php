<?php
/**
 * Pigeon & Race Entry Management - Restructured & Fixed
 */
session_start();

// 1. SECURITY: Role Check & CSRF
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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    // --- HANDLE BATCH PIGEON REGISTRATION ---
    if (isset($_POST['register_pigeon'])) {
        $member_id = $_POST['member_id'] ?? null;
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $category = $_POST['category'] ?? 'Young Bird';
        $ring_numbers = $_POST['ring_numbers'] ?? []; 

        if (!$member_id || !$year || empty($ring_numbers)) {
            $error_msg = "Owner, Year, and at least one Ring Number are required.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, year, category) VALUES (?, ?, ?, ?)");
                
                $registered_count = 0;
                $rings_logged = []; 

                foreach ($ring_numbers as $ring) {
                    $ring = strtoupper(trim($ring));
                    if (!empty($ring)) {
                        $stmt->bind_param("isss", $member_id, $ring, $year, $category);
                        $stmt->execute();
                        $registered_count++;
                        $rings_logged[] = $ring;
                    }
                }

                if ($registered_count > 0) {
                    $success_msg = "Successfully registered $registered_count bird(s)!";

                    // LOGGING LOGIC
                    $log_user_id = $_SESSION['user_id']; 
                    $log_action = "Pigeon Registration";
                    $log_details = "Registered $registered_count birds: " . implode(", ", $rings_logged) . " for member ID: " . $member_id;

                    $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                    $log_stmt->bind_param("iss", $log_user_id, $log_action, $log_details);
                    $log_stmt->execute();
                }

            } catch (mysqli_sql_exception $e) {
                $error_msg = ($e->getCode() == 1062) ? "Error: One or more ring numbers already exist." : "Database Error: " . $e->getMessage();
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
                $dist_stmt = $conn->prepare("SELECT distance_km FROM races WHERE id = ?");
                $dist_stmt->bind_param("i", $race_id);
                $dist_stmt->execute();
                $race_dist = $dist_stmt->get_result()->fetch_assoc()['distance_km'] ?? 0;

                $values = []; $types = ""; $params = [];
                foreach ($pigeon_ids as $p_id) {
                    $values[] = "(?, ?, ?)";
                    $types .= "iid";
                    array_push($params, $race_id, $p_id, $race_dist);
                }
                
                $query = "INSERT IGNORE INTO race_entries (race_id, pigeon_id, distance_km) VALUES " . implode(',', $values);
                $stmt_entry = $conn->prepare($query);
                $stmt_entry->bind_param($types, ...$params);
                
                if ($stmt_entry->execute()) {
                    $count = count($pigeon_ids);
                    $success_msg = "$count birds processed for the race.";

                    // LOGGING LOGIC FOR RACE ENTRY
                    $log_user_id = $_SESSION['user_id']; 
                    $log_action = "Race Assignment";
                    $log_details = "Assigned $count birds to Race ID: $race_id";

                    $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                    $log_stmt->bind_param("iss", $log_user_id, $log_action, $log_details);
                    $log_stmt->execute();
                }
            } catch (Exception $e) {
                $error_msg = "Entry Error: " . $e->getMessage();
            }
        }
    }
}

/**
 * 3. DATA FETCHING
 */
$members = $conn->query("SELECT m.id, u.full_name, m.loft_name FROM members m JOIN users u ON m.user_id = u.id ORDER BY u.full_name ASC")->fetch_all(MYSQLI_ASSOC);
$races = $conn->query("SELECT id, race_name, status FROM races WHERE status != 'Completed' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$pigeons = $conn->query("SELECT p.id, p.ring_number, m.loft_name FROM pigeons p JOIN members m ON p.member_id = m.id ORDER BY p.ring_number ASC")->fetch_all(MYSQLI_ASSOC);

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
        <?php if($error_msg): ?>
            <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-700 rounded shadow-sm">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $error_msg ?>
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
                        <label class="block text-sm font-medium text-slate-600 mb-1">Search & Select Owner</label>
                        <input type="text" id="memberSearchInput" placeholder="Type name or loft..." class="w-full p-2.5 mb-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-sky-500">
                        <select name="member_id" id="memberSelect" required class="w-full p-2.5 border rounded-lg outline-none focus:ring-2 focus:ring-sky-500">
                            <option value="">-- Select Member --</option>
                            <?php foreach($members as $m): ?>
                                <option value="<?= $m['id'] ?>" data-search="<?= strtolower(htmlspecialchars($m['full_name'] . ' ' . $m['loft_name'])) ?>">
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['loft_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Category</label>
                            <select name="category" class="w-full p-2.5 border rounded-lg outline-none focus:ring-2 focus:ring-sky-500">
                                <option value="Young Bird">Young Bird</option>
                                <option value="Old Bird">Old Bird</option>
                                <option value="Off Color">Off Color</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Year</label>
                            <input type="number" name="year" value="<?= date('Y') ?>" class="w-full p-2.5 border rounded-lg outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                    </div>

                    <div id="ring-container" class="space-y-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ring Number(s)</label>
                        <div class="flex gap-2">
                            <input type="text" name="ring_numbers[]" required class="w-full p-2.5 border rounded-lg outline-none focus:ring-2 focus:ring-sky-500" placeholder="EX: 2026-123">
                            <button type="button" onclick="addRingField()" class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 rounded-lg transition-colors">
                                <i class="fa-solid fa-plus"></i>
                            </button>
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
                    <select name="race_id" required class="w-full p-2.5 border rounded-lg mb-4 outline-none focus:ring-2 focus:ring-rose-500">
                        <option value="">-- Select Active Race --</option>
                        <?php foreach($races as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['race_name']) ?> (<?= $r['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-slate-50 p-2 border-b flex justify-between items-center">
                            <input type="text" id="pigeonSearch" placeholder="Filter birds..." class="text-sm p-1 border rounded w-1/2 outline-none">
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
    // Handle dynamic ring fields
    function addRingField() {
        const container = document.getElementById('ring-container');
        const div = document.createElement('div');
        div.className = "flex gap-2 mt-2";
        div.innerHTML = `
            <input type="text" name="ring_numbers[]" required class="w-full p-2.5 border rounded-lg outline-none focus:ring-2 focus:ring-sky-500" placeholder="Next Ring...">
            <button type="button" onclick="this.parentElement.remove()" class="bg-rose-500 hover:bg-rose-600 text-white px-3 rounded-lg transition-colors">
                <i class="fa-solid fa-minus"></i>
            </button>`;
        container.appendChild(div);
    }

    // Owner search
    document.getElementById('memberSearchInput').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const options = document.getElementById('memberSelect').options;
        for (let i = 1; i < options.length; i++) {
            const searchAttr = options[i].getAttribute('data-search');
            options[i].style.display = searchAttr.includes(term) ? "" : "none";
        }
    });

    // Pigeon filter
    document.getElementById('pigeonSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.pigeon-item').forEach(item => {
            item.style.display = item.textContent.toLowerCase().includes(term) ? 'flex' : 'none';
        });
    });

    function toggleAllPigeons() {
        const checkboxes = document.querySelectorAll('input[name="pigeon_ids[]"]');
        const allChecked = Array.from(checkboxes).every(c => c.checked);
        checkboxes.forEach(cb => {
            if (cb.closest('.pigeon-item').style.display !== 'none') cb.checked = !allChecked;
        });
    }
</script>
</body>
</html>