<?php
session_start();
require_once "../Config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $loft = mysqli_real_escape_string($conn, $_POST['loft_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    $sql = "INSERT INTO members (user_id, loft_name, phone) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE loft_name = ?, phone = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $loft, $phone, $loft, $phone);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Profile updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-error'>Update failed: " . $conn->error . "</div>";
    }
}

// Fetch current user details
$query = "SELECT u.full_name, u.username, m.loft_name, m.phone 
          FROM users u 
          LEFT JOIN members m ON u.id = m.user_id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
include "../Includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Profile - Pigeon Racing</title>
    <link rel="stylesheet" href="../Assets/Css/profile.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="main-container">
    <div class="profile-card">
        <div class="card-header">
            <i class="fas fa-user-cog"></i> Account Profile
        </div>

        <div class="form-body">
            <?php echo $message; ?>

            <form action="profile.php" method="POST">
                <div class="input-group">
                    <label><i class="fas fa-id-card"></i> Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly class="readonly-field">
                </div>

                <div class="input-group">
                    <label><i class="fas fa-home"></i> Loft Name</label>
                    <input type="text" name="loft_name" value="<?php echo htmlspecialchars($user['loft_name'] ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <label><i class="fas fa-at"></i> Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly class="readonly-field username-display">
                </div>

                <button type="submit" name="update_profile" class="update-btn">
                    <i class="fas fa-sync-alt"></i> Update Information
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>