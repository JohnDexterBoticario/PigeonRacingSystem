<?php
session_start();
require_once "../Config/database.php"; 

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

// Fetch Members with Search
$search = $_GET['search'] ?? '';
$search_param = "%$search%";
$query = "SELECT u.id AS user_id, m.id AS member_id, m.loft_name, m.phone, u.full_name, l.latitude, l.longitude
          FROM members m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN lofts l ON m.id = l.member_id
          WHERE m.loft_name LIKE ? OR m.phone LIKE ? OR u.full_name LIKE ?
          ORDER BY u.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Members - Pigeon Racing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-card { background: white; border-radius: 24px; border: 1px solid #f1f5f9; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
        .swal2-popup { font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: 20px !important; }
    </style>
</head>
<body class="bg-slate-50">

<div class="md:ml-64 p-4 md:p-8">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <section class="custom-card overflow-hidden">
            <div class="p-6 md:p-10 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h3 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fa-solid fa-users text-slate-800"></i>
                    Registered Members
                </h3>
                
                <form method="GET" class="relative w-full md:w-80">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or loft..."
                           class="w-full pl-12 pr-4 py-2 bg-slate-100 border-none rounded-full text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Owner</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Loft Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Contact</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 transition-all">
                                    <td class="px-6 py-5 font-semibold text-slate-700"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-6 py-5 text-slate-500"><?= htmlspecialchars($row['loft_name']) ?></td>
                                    <td class="px-6 py-5 text-slate-500 font-mono text-sm"><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-5">
                                        <div class="flex justify-center gap-3">
                                            <button onclick='openEditModal(<?= json_encode($row) ?>)' 
                                                    class="text-emerald-500 hover:bg-emerald-50 p-2 rounded-lg transition-colors"
                                                    title="Edit Member">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $row['user_id'] ?>, '<?= addslashes($row['full_name']) ?>')" 
                                                    class="text-rose-500 hover:bg-rose-50 p-2 rounded-lg transition-colors"
                                                    title="Delete Member">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <i class="fa-solid fa-user-slash text-4xl text-slate-200 block mb-3"></i>
                                    <p class="text-slate-400 italic">No members found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[9999] p-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-8">
        <h3 class="text-2xl font-extrabold mb-1">Edit Member</h3>
        <p class="text-slate-400 text-sm mb-6">Update member profile details.</p>
        
        <form action="process_member.php" method="POST" class="space-y-4">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="space-y-1">
                <label class="text-xs font-bold uppercase text-slate-400 ml-1">Full Name</label>
                <input type="text" name="full_name" id="edit_name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold uppercase text-slate-400 ml-1">Loft Name</label>
                <input type="text" name="loft_name" id="edit_loft" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" name="update_member" class="flex-1 bg-emerald-500 text-white py-3 rounded-xl font-bold hover:bg-emerald-600 transition-all">SAVE CHANGES</button>
                <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(data) {
        document.getElementById('edit_user_id').value = data.user_id;
        document.getElementById('edit_name').value = data.full_name;
        document.getElementById('edit_loft').value = data.loft_name;
        document.getElementById('editModal').classList.replace('hidden', 'flex');
    }

    function closeModal() {
        document.getElementById('editModal').classList.replace('flex', 'hidden');
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Delete Member?',
            text: `Remove ${name} permanently? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            confirmButtonText: 'YES, DELETE',
            cancelButtonText: 'CANCEL'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `process_member.php?delete_id=${id}`;
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('success')) {
        Swal.fire('Updated!', urlParams.get('success'), 'success');
    }
</script>

</body>
</html>