<?php
// 1. Start the session at the very top
session_start();

// 2. Security Check: Redirect if not logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../Auth/login.php"); 
    exit(); 
}

// 3. Include Database and Sidebar
require_once "../Config/database.php"; 
include "../Includes/sidebar.php"; 

// 4. Fetch User Data
// Using the 'user_id' from the session to find the matching member record
$user_id = $_SESSION['user_id'];

// SQL FIX: Ensures we pull 'fullname', 'loft_name', and 'phone' from the 'members' table
$stmt = $conn->prepare("SELECT u.username, u.full_name, m.loft_name, m.phone 
                        FROM users u 
                        LEFT JOIN members m ON u.id = m.user_id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Pigeon Racing</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Updated to match your Sidebar's dark aesthetic with a subtle gradient */
        body { 
            background: #f4f7f6; 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .main-content { 
            margin-left: 260px; /* Offset for your sidebar */
            padding: 60px 20px; 
            display: flex; 
            justify-content: center; 
        }
        .container { width: 100%; max-width: 650px; }
        
        /* Header styling */
        .profile-header { 
            background: #2c3e50; 
            color: white; 
            padding: 20px; 
            border-radius: 15px 15px 0 0; 
            text-align: center;
        }
        
        /* Card styling */
        .profile-card { 
            background: white !important; 
            padding: 30px 40px; 
            border-radius: 0 0 15px 15px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        }
        .info-row { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0; 
            border-bottom: 1px solid #eee; 
        }
        .info-label { color: #7f8c8d; font-weight: 600; display: flex; align-items: center; }
        .info-label i { width: 30px; color: #3498db; }
        .info-value { color: #2c3e50; font-weight: 500; }
        
        /* Button styling */
        .edit-btn {
            background: #3498db; 
            color: white; 
            border: none; 
            padding: 15px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            width: 100%;
            margin-top: 25px;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .edit-btn:hover { background: #2980b9; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container">
        <div class="profile-header">
            <h2 style="margin:0;"><i class="fa-solid fa-user-gear"></i> Account Profile</h2>
        </div>
        
        <?php if ($user): ?>
        <div class="profile-card">
            <div class="info-row">
                <span class="info-label"><i class="fa-solid fa-id-card"></i> Full Name</span>
                <span class="info-value"><?= htmlspecialchars($user['full_name']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label"><i class="fa-solid fa-house-chimney"></i> Loft Name</span>
                <span class="info-value">
              <?= !empty($user['loft_name']) ? htmlspecialchars($user['loft_name']) : '<i style="color:gray;">Not Set</i>' ?>
            </span>
            </div>

            <div class="info-row">
                <span class="info-label"><i class="fa-solid fa-phone"></i> Phone Number</span>
                <span class="info-value"><?= htmlspecialchars($user['phone']) ?></span>
            </div>

            <div class="info-row" style="border-bottom: none;">
                <span class="info-label"><i class="fa-solid fa-at"></i> Username</span>
                <span class="info-value" style="color: #e67e22;"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            
            <a href="edit_profile.php" style="text-decoration: none;">
                <button class="edit-btn">
                    <i class="fa-solid fa-user-pen"></i> Update Information
                </button>
            </a>
        </div>
        <?php else: ?>
            <div class="profile-card" style="text-align: center;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; color: #e74c3c;"></i>
                <p style="color: #e74c3c; margin-top: 15px;"><strong>Profile data missing.</strong><br>Please ensure your account is linked to a member record.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>