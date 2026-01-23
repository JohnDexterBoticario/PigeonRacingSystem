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
// 1. Get the total count of members
$count_query = $conn->query("SELECT COUNT(*) as total FROM members");
$total_members = ($count_query) ? $count_query->fetch_assoc()['total'] : 0;

// 2. Fetch all members who joined (ordered by newest first)
$members_list = $conn->query("SELECT full_name, loft_name FROM members ORDER BY id DESC LIMIT 5");
// --------------------------

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

<style>
    /* Pulse animation for the live icon */
    @keyframes pulse-glow {
        0% { color: #8a6b49; text-shadow: 0 0 0px rgba(138, 107, 73, 0); }
        50% { color: #fc00ff; text-shadow: 0 0 10px rgba(252, 0, 255, 0.8); }
        100% { color: #8a6b49; text-shadow: 0 0 0px rgba(138, 107, 73, 0); }
    }

    .glow-icon { animation: pulse-glow 2s infinite; }

    .live-badge {
        background: #ff0000;
        color: white;
        font-size: 9px;
        padding: 2px 5px;
        border-radius: 4px;
        margin-left: 5px;
        font-weight: bold;
        vertical-align: middle;
        box-shadow: 0 0 5px rgba(255, 0, 0, 0.5);
    }

    .clocked-counter {
        background: #8a6b49;
        color: white;
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 50%;
        float: right;
        margin-top: 3px;
    }

    .active-race-nav {
        background: rgba(252, 0, 255, 0.05);
        border-left: 4px solid #fc00ff !important;
    }

    /* New Member List Styling */
    .member-stats {
        padding: 10px 20px;
        background: rgba(0, 0, 0, 0.2);
        margin: 10px 0;
        font-size: 12px;
        color: #bdc3c7;
    }

    .recent-members {
        padding: 0 20px;
        max-height: 150px;
        overflow-y: auto;
    }

    .member-item {
        border-bottom: 1px solid #34495e;
        padding: 5px 0;
    }

    .member-name { color: #3498db; display: block; font-size: 13px; font-weight: bold; }
    .member-loft { color: #95a5a6; font-size: 11px; }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-dove"></i> 
        <span>Pigeon Racing</span>
    </div>
    
    <div class="user-info">
        <p>Welcome, <strong><?php echo htmlspecialchars($fullName); ?></strong></p>
        <span class="role-badge"><?php echo ucfirst($role); ?></span>
    </div>
    <div style="padding: 10px 20px;">
    <div style="position: relative;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 10px; color: #7f8c8d; font-size: 12px;"></i>
        <input type="text" id="memberSearch" placeholder="Search members..." 
               style="width: 100%; padding: 8px 8px 8px 30px; background: #1a252f; border: 1px solid #34495e; color: white; border-radius: 5px; font-size: 12px;">
    </div>
</div>
    <div class="member-stats">
        <i class="fa-solid fa-users"></i> Total Members: <strong><?= $total_members ?></strong>
    </div>

    <div id="searchModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div style="position: relative; background-color: #2c3e50; margin: 10% auto; padding: 20px; border: 1px solid #3498db; width: 50%; border-radius: 15px; box-shadow: 0 5px 30px rgba(0,0,0,0.5);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #34495e; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="color: #ecf0f1; margin: 0;"><i class="fa-solid fa-users"></i> Member Search Results</h3>
            <span id="closeModal" style="color: #bdc3c7; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        </div>

        <div id="modalResultsList" style="max-height: 400px; overflow-y: auto;">
            </div>
    </div>
</div>

    <nav class="sidebar-nav">
        <a href="/pigeon-racing-system/Public/Index.php">
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

        <a href="/pigeon-racing-system/Member/profile.php">
            <i class="fa-solid fa-user"></i> Profile
        </a>
        
        <a href="/pigeon-racing-system/Public/RaceResult.php">
            <i class="fa-solid fa-trophy"></i> Race Results
        </a>
        
        <a href="/pigeon-racing-system/Member/training.php">
            <i class="fa-solid fa-rocket"></i> Pigeon Training
        </a>

        <?php if ($role === 'admin'): ?>
            <div class="nav-divider">Admin Tools</div>
            <a href="/pigeon-racing-system/Admin/Races/create.php">
                <i class="fa-solid fa-plus"></i> Create Race
            </a>
            <a href="/pigeon-racing-system/Admin/members.php">
                <i class="fa-solid fa-users-gear"></i> Manage Members
            </a>
            <a href="/pigeon-racing-system/Admin/logs.php">
                <i class="fa-solid fa-clipboard-list"></i> System Logs
            </a>
        <?php endif; ?>

        <a href="/pigeon-racing-system/Auth/logout.php" class="nav-link logout-link" onclick="return confirm('Are you sure you want to log out?')">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</div>

<script>
const searchInput = document.getElementById('memberSidebarSearch');
const modal = document.getElementById('searchModal');
const resultsList = document.getElementById('modalResultsList');
const closeModal = document.getElementById('closeModal');

searchInput.addEventListener('input', function() {
    let query = this.value;
    
    if (query.length > 0) {
        // Fetch results from the search handler
        fetch(`/pigeon-racing-system/Admin/search_handler.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                modal.style.display = "block";
                resultsList.innerHTML = ''; 

                if (data.length > 0) {
                    data.forEach(member => {
                        let item = document.createElement('div');
                        item.className = "search-result-item";
                        item.style = "padding: 15px; margin-bottom: 10px; background: #34495e; border-radius: 8px; cursor: pointer; transition: 0.3s; display: flex; justify-content: space-between; align-items: center;";
                        
                        item.innerHTML = `
                            <div>
                                <strong style="color: #3498db; font-size: 15px;">${member.full_name} (@${member.username})</strong><br>
                                <small style="color: #bdc3c7;">Loft: ${member.loft_name} | ID: ${member.member_id} | Phone: ${member.phone}</small>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color: #7f8c8d;"></i>
                        `;

                        item.onclick = () => window.location.href = `/pigeon-racing-system/Admin/members.php?search=${encodeURIComponent(member.full_name)}`;
                        item.onmouseover = () => item.style.background = "#2980b9";
                        item.onmouseout = () => item.style.background = "#34495e";

                        resultsList.appendChild(item);
                    });
                } else {
                    resultsList.innerHTML = '<p style="text-align:center; color:#bdc3c7;">No matches found for ID, Phone, or Name.</p>';
                }
            });
    } else {
        modal.style.display = "none";
    }
});

closeModal.onclick = () => modal.style.display = "none";
window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; };
</script>