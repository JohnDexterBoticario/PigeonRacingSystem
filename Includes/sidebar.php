<?php
// Start session if not already started to access user roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database to check for active races
require_once $_SERVER['DOCUMENT_ROOT'] . "/pigeon-racing-system/Config/database.php";

// Safely get user info
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$fullName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Guest';

// --- NEW MEMBER QUERIES ---
$count_query = $conn->query("SELECT COUNT(*) as total FROM members");
$total_members = ($count_query) ? $count_query->fetch_assoc()['total'] : 0;
$members_list = $conn->query("SELECT full_name, loft_name FROM members ORDER BY id DESC LIMIT 5");

// --- FIXED: ADDED MISSING QUERY FOR RACE STATUS BOARD ---
$active_status_races = $conn->query("SELECT * FROM races WHERE status != 'Completed' ORDER BY release_datetime ASC");

// Check for Active Race and Count Clocked Birds
$active_check = $conn->query("SELECT id FROM races WHERE status = 'Released' LIMIT 1");
$has_active_race = ($active_check && $active_check->num_rows > 0);

$birds_clocked_count = 0;
if ($has_active_race && $role === 'admin') {
    $active_race = $active_check->fetch_assoc();
    $race_id = $active_race['id'];
    
    $count_query = $conn->prepare("SELECT COUNT(*) as total FROM race_results WHERE race_id = ?");
    $count_query->bind_param("i", $race_id);
    $count_query->execute();
    $birds_clocked_count = $count_query->get_result()->fetch_assoc()['total'];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="/pigeon-racing-system/Assets/Css/nav.css">
<link rel="stylesheet" href="/pigeon-racing-system/Assets/Css/sidebar.css">

<div class="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-dove"></i> 
        <span>Pigeon Racing</span>
    </div>
    
    <div class="user-info">
        <p>Welcome, <strong><?php echo htmlspecialchars($fullName); ?></strong></p>
        <span class="role-badge"><?php echo ucfirst($role); ?></span>
    </div>

    <div class="sidebar-search-container">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass search-icon-inline"></i>
            <input type="text" id="memberSidebarSearch" class="sidebar-search-input" placeholder="Search members...">
        </div>
    </div>

    <div class="member-stats">
        <i class="fa-solid fa-users"></i> Total Members: <strong><?= $total_members ?></strong>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= ($_SESSION['role'] === 'admin') ? '/pigeon-racing-system/Admin/AdminDashboard.php' : '/pigeon-racing-system/Member/Dashboard.php' ?>">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="/pigeon-racing-system/Member/clocking.php" class="<?= $has_active_race ? 'active-race-nav' : '' ?>">
            <i class="fa-solid fa-stopwatch <?= $has_active_race ? 'glow-icon' : '' ?>"></i> 
            Clocking 
            <?php if($has_active_race): ?>
                <span class="live-badge">LIVE</span>
                <?php if($role === 'admin'): ?>
                    <span class="clocked-counter" title="Birds Clocked"><?= $birds_clocked_count ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </a>

        <a href="/pigeon-racing-system/Member/profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="/pigeon-racing-system/Public/RaceResult.php"><i class="fa-solid fa-trophy"></i> Race Results</a>
        <a href="/pigeon-racing-system/Member/training.php"><i class="fa-solid fa-rocket"></i> Pigeon Training</a>

        <?php if ($role === 'admin'): ?>
            <div class="nav-divider">Admin Tools</div>
            <a href="/pigeon-racing-system/Admin/Races/create.php"><i class="fa-solid fa-plus"></i> Create Race</a>
            <a href="/pigeon-racing-system/Admin/AdminRegistration.php"><i class="fa-solid fa-clipboard-list"></i> Member Registration</a>
            <a href="/pigeon-racing-system/Admin/Pigeons/register_pigeon.php"><i class="fa-solid fa-dove"></i> Pigeon Registration</a>
            <a href="/pigeon-racing-system/Admin/Add_members.php"><i class="fa-solid fa-users-gear"></i> Manage Members</a>
            <a href="/pigeon-racing-system/Admin/logs.php"><i class="fa-solid fa-list-ul"></i> System Logs</a>
        <?php endif; ?>

        <a href="/pigeon-racing-system/Auth/logout.php" class="nav-link logout-link" onclick="return confirm('Are you sure you want to log out?')">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</div>

<script>
// Logic for Member Search Modal
const searchInput = document.getElementById('memberSidebarSearch');
const modal = document.getElementById('searchModal');
const resultsList = document.getElementById('modalResultsList');
const closeModal = document.getElementById('closeModal');

searchInput.addEventListener('input', function() {
    let query = this.value;
    if (query.length > 0) {
        fetch(`/pigeon-racing-system/Admin/search_handler.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                modal.style.display = "block";
                resultsList.innerHTML = ''; 
                if (data.length > 0) {
                    data.forEach(member => {
                        let item = document.createElement('div');
                        item.className = "search-result-item";
                        item.innerHTML = `
                            <div>
                                <strong class="result-name">${member.full_name} (@${member.username})</strong><br>
                                <small class="result-meta">Loft: ${member.loft_name} | ID: ${member.member_id} | Phone: ${member.phone}</small>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color: #7f8c8d;"></i>`;
                        item.onclick = () => window.location.href = `/pigeon-racing-system/Admin/members.php?search=${encodeURIComponent(member.full_name)}`;
                        resultsList.appendChild(item);
                    });
                } else {
                    resultsList.innerHTML = '<p class="no-races-text" style="text-align:center;">No matches found.</p>';
                }
            });
    } else { modal.style.display = "none"; }
});
closeModal.onclick = () => modal.style.display = "none";
window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; };
</script>