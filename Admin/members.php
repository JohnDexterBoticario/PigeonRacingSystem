<?php
session_start();
require_once "../Config/database.php"; 

// 1. Admin Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// 2. Handle Form Submission
if (isset($_POST['save_member'])) {
    $fullname = trim($_POST['full_name'] ?? '');
    $loft_name = trim($_POST['loft_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lat = $_POST['latitude'] ?? NULL;
    $lng = $_POST['longitude'] ?? NULL;

    if (empty($fullname) || empty($loft_name)) {
        $error_msg = "Full Name and Loft Name are required.";
    } else {
        $conn->begin_transaction();
        try {
            // STEP 1: Create the record in the 'users' table
            $username = strtolower(str_replace(' ', '', $fullname)) . rand(10, 99);
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $role = 'member';

            $stmt_user = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt_user->bind_param("sss", $username, $password, $role);
            $stmt_user->execute();
            $user_id = $conn->insert_id;

            // STEP 2: Insert into 'members' table using consolidated columns
            $stmt_member = $conn->prepare("INSERT INTO members (user_id, full_name, loft_name, phone, loft_latitude, loft_longitude) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_member->bind_param("isssdd", $user_id, $fullname, $loft_name, $phone, $lat, $lng);
            $stmt_member->execute();

            $conn->commit();
            $success_msg = "Member added successfully! Default Username: <strong>$username</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// 3. Fetch All Members (Fixed JOIN and column names)
$search = $_GET['search'] ?? '';
$query = "SELECT m.*, u.username FROM members m 
          LEFT JOIN users u ON m.user_id = u.id
          WHERE m.loft_name LIKE ? OR m.phone LIKE ? OR m.full_name LIKE ?";

$stmt_fetch = $conn->prepare($query);
$search_param = "%$search%";
$stmt_fetch->bind_param("sss", $search_param, $search_param, $search_param);
$stmt_fetch->execute();
$members_list = $stmt_fetch->get_result();

include "../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Management - Admin</title>
    <link rel="stylesheet" href="../Assets/Css/nav.css">
    <link rel="stylesheet" href="../Assets/Css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: linear-gradient(45deg, #00dbde 0%, #fc00ff 100%); min-height: 100vh; margin: 0;">

<div class="main-content">
    <div class="container" style="max-width: 1000px; margin: auto; padding: 20px;">
        
        <div class="card" style="background: white; padding: 35px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px;"> 
            <h2 style="color: #333;"><i class="fa-solid fa-user-plus" style="color: #8a6b49;"></i> Add New Member</h2>
            
            <?php if($success_msg): ?> <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?= $success_msg ?></div> <?php endif; ?>
            <?php if($error_msg): ?> <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?= $error_msg ?></div> <?php endif; ?>

            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="input-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Full Name</label>
                        <input type="text" name="full_name" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div class="input-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Loft Name</label>
                        <input type="text" name="loft_name" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div class="input-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Phone</label>
                        <input type="text" name="phone" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div class="input-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Latitude</label>
                        <input type="number" step="any" name="latitude" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div class="input-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Longitude</label>
                        <input type="number" step="any" name="longitude" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>
                <button type="submit" name="save_member" style="background: #8a6b49; color: white; border: none; padding: 15px; border-radius: 8px; width: 100%; margin-top: 25px; font-weight: bold; cursor: pointer;">Save Member</button>
            </form>
        </div>

        <div class="card" style="background: white; padding: 35px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <h3 style="color: #333;"><i class="fa-solid fa-users"></i> Registered Members</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr style="background: #f8f9fa; text-align: left;">
                        <th style="padding: 12px;">Owner Name</th>
                        <th style="padding: 12px;">Loft Name</th>
                        <th style="padding: 12px;">Phone</th>
                        <th style="padding: 12px;">Coordinates</th>
                        <th style="padding: 12px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $members_list->fetch_assoc()): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($row['loft_name']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($row['phone']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <small><?= $row['loft_latitude'] ?? 'N/A' ?>, <?= $row['loft_longitude'] ?? 'N/A' ?></small>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                            <a href="edit_member.php?id=<?= $row['id'] ?>" style="color: #8a6b49;"><i class="fa-solid fa-pen"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>