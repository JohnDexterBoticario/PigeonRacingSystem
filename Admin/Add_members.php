<?php
/**
 * Member Management - Pigeon Racing System
 * Handles administrative member creation and the searchable member directory.
 */

session_start();
require_once "../Config/database.php"; 

/**
 * Function: checkAdminAccess
 * Restricts the management portal to authorized admin accounts.
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

/**
 * Logic: Handle Member Registration
 * Creates a user account, a member profile, and loft coordinates in one transaction.
 */
if (isset($_POST['save_member'])) {
    $fullname  = htmlspecialchars(trim($_POST['full_name'] ?? ''));
    $loft_name = htmlspecialchars(trim($_POST['loft_name'] ?? ''));
    $phone     = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $lat       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 0.00;
    $lng       = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 0.00;

    if (empty($fullname) || empty($loft_name)) {
        $error_msg = "Full Name and Loft Name are required.";
    } else {
        $conn->begin_transaction();
        try {
            // Generate a default username and password for the new member
            $username = strtolower(preg_replace('/\s+/', '', $fullname)) . rand(100, 999);
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $role     = 'member';

            // 1. Insert into Users Table
            $stmt_u = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
            $stmt_u->bind_param("ssss", $username, $password, $role, $fullname);
            $stmt_u->execute();
            $user_id = $conn->insert_id;

            // 2. Insert into Members Table
            $stmt_m = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
            $stmt_m->bind_param("iss", $user_id, $loft_name, $phone);
            $stmt_m->execute();
            $member_id = $conn->insert_id;

            // 3. Insert into Lofts Table
            $stmt_l = $conn->prepare("INSERT INTO lofts (member_id, latitude, longitude) VALUES (?, ?, ?)");
            $stmt_l->bind_param("idd", $member_id, $lat, $lng);
            $stmt_l->execute();

            $conn->commit();
            $success_msg = "Member successfully added! Username: $username";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error creating member: " . $e->getMessage();
        }
    }
}

/**
 * Logic: Fetch and Filter Members
 * Supports real-time searching by Name, Loft, or Phone Number.
 */
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

$query = "SELECT m.id AS member_id, m.loft_name, m.phone, u.full_name, l.latitude, l.longitude
          FROM members m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN lofts l ON m.id = l.member_id
          WHERE m.loft_name LIKE ? OR m.phone LIKE ? OR u.full_name LIKE ?
          ORDER BY m.id DESC";

$stmt_f = $conn->prepare($query);
$stmt_f->bind_param("sss", $search_param, $search_param, $search_param);
$stmt_f->execute();
$members_result = $stmt_f->get_result();

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management - Pigeon Racing</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../Assets/Css/members.css">
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div class="md:ml-64 p-4 md:p-8">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <section class="custom-card p-6 md:p-10">
            <header class="mb-8">
                <h2 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fa-solid fa-user-plus text-[#8a6b49]"></i>
                    Member Registration
                </h2>
                <p class="text-slate-500 text-sm mt-1">Register new lofts and generate default login credentials.</p>
            </header>

            <?php if($success_msg): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-3 animate-pulse">
                    <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if($error_msg): ?>
                <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Full Name</label>
                        <input type="text" name="full_name" id="full_name_input" required 
                               placeholder="e.g. John Doe"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Loft Name</label>
                        <input type="text" name="loft_name" required 
                               placeholder="e.g. Blue Sky Loft"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Phone Number</label>
                        <input type="text" name="phone" id="phone_input" maxlength="11" placeholder="09XXXXXXXXX"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Latitude</label>
                        <input type="number" step="any" name="latitude" placeholder="0.000000"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Longitude</label>
                        <input type="number" step="any" name="longitude" placeholder="0.000000"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#8a6b49] outline-none transition-all">
                    </div>
                </div>

                <button type="submit" name="save_member" class="btn-primary w-full text-white font-bold py-4 rounded-xl shadow-lg">
                    Confirm Registration
                </button>
            </form>
        </section>

        <section class="custom-card overflow-hidden">
            <div class="p-6 md:p-10 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h3 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fa-solid fa-users text-slate-800"></i>
                    Registered Members
                </h3>
                
                <form method="GET" class="relative w-full md:w-80">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or loft..."
                           class="w-full pl-12 pr-4 py-2 bg-slate-100 border-none rounded-full text-sm focus:ring-2 focus:ring-[#8a6b49] outline-none">
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">ID</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Owner</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Loft Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Contact</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if ($members_result && $members_result->num_rows > 0): ?>
                            <?php while($row = $members_result->fetch_assoc()): ?>
                                <tr class="table-row-hover transition-all">
                                    <td class="px-6 py-5">
                                        <span class="member-id-badge">#<?= $row['member_id'] ?></span>
                                    </td>
                                    <td class="px-6 py-5 font-semibold text-slate-800"><?= htmlspecialchars($row['full_name'] ?? 'System User') ?></td>
                                    <td class="px-6 py-5 text-slate-600"><?= htmlspecialchars($row['loft_name']) ?></td>
                                    <td class="px-6 py-5 text-slate-500 font-mono text-sm"><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-5">
                                        <div class="flex justify-center">
                                            <a href="edit_member.php?id=<?= $row['member_id'] ?>" 
                                               class="text-[#8a6b49] hover:bg-amber-50 p-2 rounded-lg transition-colors"
                                               title="Modify Profile">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-20 text-center">
                                    <i class="fa-solid fa-user-slash text-4xl text-slate-200 block mb-3"></i>
                                    <p class="text-slate-400 italic">No members found matching your search.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="bg-slate-50 px-6 py-4 text-xs font-bold uppercase tracking-widest text-slate-400 flex justify-between">
                <span>Database: <?= $members_result->num_rows ?> Lofts Registered</span>
                <span class="text-emerald-500"><i class="fa-solid fa-circle text-[8px] mr-1"></i> Live</span>
            </footer>
        </section>
    </div>
</div>

<script>
    /**
     * Helper: Restrict Full Name input to letters and spaces only.
     */
    document.getElementById('full_name_input').addEventListener('input', function() {
        this.value = this.value.replace(/[0-9]/g, ''); 
    });

    /**
     * Helper: Restrict Phone Number input to digits only.
     */
    document.getElementById('phone_input').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, ''); 
    });
</script>

</body>
</html>