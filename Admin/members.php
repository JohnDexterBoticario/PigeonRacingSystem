<?php
/**
 * Member Management - Pigeon Racing System
 * This file handles both adding new members and displaying the registered members list.
 */
session_start();

// Enable strict error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database configuration
require_once "../Config/database.php"; 

// Access control: Ensure only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// --- 1. HANDLE FORM SUBMISSION (Add New Member) ---
if (isset($_POST['save_member'])) {
    $fullname = htmlspecialchars(trim($_POST['full_name'] ?? ''));
    $loft_name = htmlspecialchars(trim($_POST['loft_name'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : 0.00;
    $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : 0.00;

    if (empty($fullname) || empty($loft_name)) {
        $error_msg = "Full Name and Loft Name are required.";
    } else {
        $conn->begin_transaction();
        try {
            $username = strtolower(preg_replace('/\s+/', '', $fullname)) . rand(100, 999);
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $role = 'member';

            $stmt_user = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
            $stmt_user->bind_param("ssss", $username, $password, $role, $fullname);
            $stmt_user->execute();
            $user_id = $conn->insert_id;

            $stmt_member = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
            $stmt_member->bind_param("iss", $user_id, $loft_name, $phone);
            $stmt_member->execute();
            $member_id = $conn->insert_id;

            $stmt_loft = $conn->prepare("INSERT INTO lofts (member_id, latitude, longitude) VALUES (?, ?, ?)");
            $stmt_loft->bind_param("idd", $member_id, $lat, $lng);
            $stmt_loft->execute();

            $conn->commit();
            $success_msg = "Member successfully added with ID: #$member_id";

        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// --- 2. FETCH REGISTERED MEMBERS DATA ---
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

$query = "
    SELECT 
        m.id AS member_id, 
        m.loft_name,
        m.phone,
        u.full_name,
        l.latitude,
        l.longitude
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN lofts l ON m.id = l.member_id
    WHERE m.loft_name LIKE ? 
       OR m.phone LIKE ? 
       OR u.full_name LIKE ?
    ORDER BY m.id DESC
";

$stmt_fetch = $conn->prepare($query);
$stmt_fetch->bind_param("sss", $search_param, $search_param, $search_param);
$stmt_fetch->execute();
$members_result = $stmt_fetch->get_result();

// Include your existing sidebar
include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management - Pigeon Racing</title>
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Linking the separated CSS file -->
    <link rel="stylesheet" href="../Assets/Css/members.css">
</head>
<body class="font-sans antialiased text-slate-800">

<div class="md:ml-64 p-4 md:p-8">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Section: Add New Member -->
        <div class="custom-card p-6 md:p-10">
            <h2 class="text-2xl font-bold flex items-center gap-3 mb-8">
                <i class="fa-solid fa-user-plus text-[#8a6b49]"></i>
                Add New Member
            </h2>

            <?php if($success_msg): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-3">
                    <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if($error_msg): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-600">Full Name</label>
                        <input type="text" name="full_name" id="full_name_input" required 
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] focus:border-transparent outline-none transition-all">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-600">Loft Name</label>
                        <input type="text" name="loft_name" required 
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] focus:border-transparent outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-600">Phone Number</label>
                        <input type="text" name="phone" id="phone_input" maxlength="11" placeholder="09123456789"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-600">Latitude</label>
                        <input type="number" step="any" name="latitude" placeholder="0.00"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-600">Longitude</label>
                        <input type="number" step="any" name="longitude" placeholder="0.00"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                </div>

                <button type="submit" name="save_member" class="btn-primary w-full text-white font-bold py-4 rounded-xl shadow-lg mt-4">
                    Save Member
                </button>
            </form>
        </div>

        <!-- Section: Registered Members Table -->
        <div class="custom-card p-6 md:p-10">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <h3 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fa-solid fa-users text-slate-800"></i>
                    Registered Members
                </h3>
                
                <form method="GET" class="relative w-full md:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search members..."
                           class="w-full pl-12 pr-4 py-2 bg-slate-100 border-none rounded-full text-sm focus:ring-2 focus:ring-[#8a6b49] outline-none">
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-4 font-bold text-sm text-slate-600 uppercase">ID</th>
                            <th class="px-6 py-4 font-bold text-sm text-slate-600 uppercase">Owner Name</th>
                            <th class="px-6 py-4 font-bold text-sm text-slate-600 uppercase">Loft Name</th>
                            <th class="px-6 py-4 font-bold text-sm text-slate-600 uppercase">Phone</th>
                            <th class="px-6 py-4 font-bold text-sm text-slate-600 uppercase text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if ($members_result && $members_result->num_rows > 0): ?>
                            <?php while($row = $members_result->fetch_assoc()): ?>
                                <tr class="table-row-hover transition-colors group">
                                    <td class="px-6 py-4">
                                        <span class="member-id-badge">
                                            #<?= $row['member_id'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-800"><?= htmlspecialchars($row['full_name'] ?? 'Unknown') ?></td>
                                    <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($row['loft_name']) ?></td>
                                    <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center gap-3">
                                            <a href="edit_member.php?id=<?= $row['member_id'] ?>" 
                                               class="text-[#8a6b49] hover:text-[#765d3f] p-2 hover:bg-amber-50 rounded-lg transition-all"
                                               title="Edit Member">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">
                                    No members found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 text-xs font-bold uppercase tracking-widest text-slate-400 flex justify-between">
                <span>Total: <?= $members_result->num_rows ?> Members</span>
                <span>System Live</span>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('full_name_input').addEventListener('input', function() {
        this.value = this.value.replace(/[0-9]/g, ''); 
    });

    document.getElementById('phone_input').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, ''); 
    });
</script>

</body>
</html>