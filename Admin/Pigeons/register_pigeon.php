<?php
/**
 * Pigeon & Race Entry Management - Pigeon Racing System
 * This file handles:
 * 1. Registering a new pigeon to a member (pigeons table)
 * 2. Assigning pigeons to a specific race (race_entries table)
 */
session_start();

// Enable strict error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database configuration - FIXED PATH: Going up two levels to reach root
require_once "../../Config/database.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- 1. HANDLE NEW PIGEON REGISTRATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_pigeon'])) {
    $member_id = $_POST['member_id'] ?? null;
    $year = htmlspecialchars(trim($_POST['year'] ?? ''));
    $ring_number = htmlspecialchars(trim($_POST['ring_number'] ?? ''));

    if (empty($member_id) || empty($year) || empty($ring_number)) {
        $error_msg = "All fields are required for pigeon registration.";
    } else {
        try {
            $check_stmt = $conn->prepare("SELECT id FROM pigeons WHERE ring_number = ?");
            $check_stmt->bind_param("s", $ring_number);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error_msg = "Error: Ring number already registered.";
            } else {
                $stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, year, category) VALUES (?, ?, ?, 'Young Bird')");
                $stmt->bind_param("iss", $member_id, $ring_number, $year);
                $stmt->execute();
                $success_msg = "Pigeon registered successfully! Ring: " . htmlspecialchars($ring_number);
            }
        } catch (Exception $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// --- 2. HANDLE RACE ENTRY (Connecting Pigeons to a Race) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_race'])) {
    $race_id = $_POST['race_id'] ?? null;
    $selected_pigeons = $_POST['pigeon_ids'] ?? [];

    if (empty($race_id) || empty($selected_pigeons)) {
        $error_msg = "Please select a race and at least one pigeon to enter.";
    } else {
        try {
            $conn->begin_transaction();
            // Get race distance for the entry record
            $dist_stmt = $conn->prepare("SELECT distance_km FROM races WHERE id = ?");
            $dist_stmt->bind_param("i", $race_id);
            $dist_stmt->execute();
            $race_dist_res = $dist_stmt->get_result()->fetch_assoc();
            $race_dist = $race_dist_res['distance_km'] ?? 0;

            foreach ($selected_pigeons as $p_id) {
                // Prevent duplicate entries
                $check = $conn->prepare("SELECT id FROM race_entries WHERE race_id = ? AND pigeon_id = ?");
                $check->bind_param("ii", $race_id, $p_id);
                $check->execute();
                if ($check->get_result()->num_rows == 0) {
                    $stmt = $conn->prepare("INSERT INTO race_entries (race_id, pigeon_id, distance_km) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $race_id, $p_id, $race_dist);
                    $stmt->execute();
                }
            }
            $conn->commit();
            $success_msg = count($selected_pigeons) . " birds entered into the race successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Entry Error: " . $e->getMessage();
        }
    }
}

// --- 3. DATA FETCHING ---
// Members for dropdown
$members_res = $conn->query("SELECT m.id, u.full_name, m.loft_name FROM members m JOIN users u ON m.user_id = u.id ORDER BY u.full_name ASC");

// Active Races for dropdown
$races_res = $conn->query("SELECT id, race_name, status FROM races WHERE status != 'Completed' ORDER BY id DESC");

// Registered Pigeons for the selection list
$pigeons_res = $conn->query("
    SELECT p.id, p.ring_number, m.loft_name, u.full_name 
    FROM pigeons p 
    JOIN members m ON p.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    ORDER BY u.full_name ASC
");

// Sidebar inclusion
include "../../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pigeon & Race Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(45deg, #00dbde 0%, #fc00ff 100%); min-height: 100vh; }
        .custom-card { background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    </style>
</head>
<body class="font-sans antialiased text-slate-800">

<div class="md:ml-64 p-4 md:p-8">
    <div class="max-w-4xl mx-auto space-y-8 pb-10">
        
        <?php if($success_msg): ?>
            <div class="p-4 bg-white/90 border-l-4 border-green-500 text-green-700 rounded-xl shadow-lg flex items-center gap-3">
                <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="p-4 bg-white/90 border-l-4 border-red-500 text-red-700 rounded-xl shadow-lg flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-8">
            
            <div class="custom-card p-8 md:p-10">
                <div class="mb-6">
                    <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                        <i class="fa-solid fa-plus-circle text-[#8a6b49]"></i> 
                        1. Register New Bird
                    </h2>
                    <p class="text-slate-500 text-sm">Add a new pigeon to the database and assign it to an owner.</p>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2 space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Select Owner</label>
                        <div class="relative mb-2">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="ownerSearch" placeholder="Type member name to filter..." 
                                   class="w-full pl-9 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                        </div>
                        <select name="member_id" id="ownerSelect" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none">
                            <option value="">-- Choose Member --</option>
                            <?php while($m = $members_res->fetch_assoc()): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['loft_name']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Bird Year</label>
                        <input type="number" name="year" value="<?= date('Y') ?>" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Ring Number</label>
                        <input type="text" name="ring_number" placeholder="NHPC-2025-XXXXX" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none">
                    </div>
                    <button type="submit" name="register_pigeon" class="md:col-span-2 bg-[#8a6b49] hover:bg-[#765d3f] text-white font-bold py-4 rounded-xl shadow-lg transition-all active:scale-95">
                        Register Pigeon
                    </button>
                </form>
            </div>

            <div class="custom-card p-8 md:p-10 border-t-8 border-[#8a6b49]">
                <div class="mb-6">
                    <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                        <i class="fa-solid fa-list-check text-[#8a6b49]"></i> 
                        2. Assign Birds to Race
                    </h2>
                    <p class="text-slate-500 text-sm">Select a race and check the pigeons that will participate.</p>
                </div>

                <form method="POST" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Target Race</label>
                        <select name="race_id" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none">
                            <option value="">-- Select Active Race --</option>
                            <?php while($r = $races_res->fetch_assoc()): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['race_name']) ?> (<?= $r['status'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-500 uppercase">Select Participants</label>
                            <input type="text" id="pigeonSearch" placeholder="Search Ring or Loft..." 
                                   class="px-3 py-1 bg-slate-100 border border-slate-200 rounded-lg text-[10px] outline-none focus:ring-1 focus:ring-[#8a6b49] w-48">
                        </div>
                        <div id="pigeonList" class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-64 overflow-y-auto p-4 bg-slate-50 rounded-2xl border border-slate-100 shadow-inner">
                            <?php while($p = $pigeons_res->fetch_assoc()): ?>
                                <label class="pigeon-item flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100 hover:border-[#8a6b49] transition-all cursor-pointer group">
                                    <input type="checkbox" name="pigeon_ids[]" value="<?= $p['id'] ?>" class="w-5 h-5 accent-[#8a6b49]">
                                    <div class="text-xs">
                                        <div class="ring-num font-bold text-slate-800 group-hover:text-[#8a6b49]"><?= htmlspecialchars($p['ring_number']) ?></div>
                                        <div class="loft-name text-slate-400 font-medium"><?= htmlspecialchars($p['loft_name']) ?></div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_to_race" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-4 rounded-xl shadow-lg transition-all active:scale-95 uppercase tracking-widest text-sm">
                        Confirm Race Entries
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center text-white/50 text-xs font-bold tracking-widest uppercase">
            Pigeon Racing System © 2026
        </div>
    </div>
</div>

<script>
    // Live Search for Register New Bird (Dropdown)
    document.getElementById('ownerSearch').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const select = document.getElementById('ownerSelect');
        const options = select.options;

        for (let i = 1; i < options.length; i++) {
            const text = options[i].text.toLowerCase();
            options[i].style.display = text.includes(filter) ? 'block' : 'none';
        }
    });

    // Live Search for Assign Birds to Race (Checkboxes)
    document.getElementById('pigeonSearch').addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const items = document.querySelectorAll('.pigeon-item');

        items.forEach(item => {
            const ring = item.querySelector('.ring-num').textContent.toLowerCase();
            const loft = item.querySelector('.loft-name').textContent.toLowerCase();
            
            if (ring.includes(filter) || loft.includes(filter)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
</script>

</body>
</html>