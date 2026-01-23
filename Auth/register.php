<?php
session_start();
require_once "../Config/database.php"; 

if (isset($_POST['register'])) {
    // Sanitize basic input
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name = htmlspecialchars(trim($_POST['loft_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $role = $_POST['role']; 

    $conn->begin_transaction();

    try {
        // 1. Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (role, full_name, username, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $role, $full_name, $username, $password);
        
        if (!$stmt->execute()) {
            throw new Exception("User table insertion failed: " . $stmt->error);
        }

        $new_user_id = $conn->insert_id; 

        // 2. Insert into members table 
        $stmt2 = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $new_user_id, $loft_name, $phone);
        
        if (!$stmt2->execute()) {
            throw new Exception("Members table insertion failed: " . $stmt2->error);
        }

        $conn->commit();
        echo "<script>alert('Registration successful as " . ucfirst($role) . "!'); window.location.href='login.php';</script>";

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <form method="POST" action="">
            <h2>Register Account</h2>

            <?php if(isset($error)): ?>
                <p style="color: #ff4d4d; margin-bottom: 20px; font-size: 14px;"><?php echo $error; ?></p>
            <?php endif; ?>

            <div class="input-group">
           <label>Account Type</label>
            <div class="input-with-icon">
           <i class="fa-solid fa-user-shield"></i>
           <select name="role" required>
            <option value="member">Member (Loft Owner)</option>
            <option value="admin">System Administrator</option>
           </select>
        </div>
      </div>

            <div class="input-group">
                <label>Full Name</label>
                <div class="input-with-icon">
                    <i class="fa fa-id-card"></i>
                    <input type="text" name="full_name" placeholder="Type your full name" required>
                </div>
            </div>

            <div class="input-group">
                <label>Username</label>
                <div class="input-with-icon">
                    <i class="fa fa-user"></i>
                    <input type="text" name="username" placeholder="Type your username" required>
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-with-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" placeholder="Type your password" required>
                </div>
            </div>

            <div class="input-group">
                <label>Loft Name</label>
                <div class="input-with-icon">
                    <i class="fa fa-home"></i>
                    <input type="text" name="loft_name" placeholder="Type your loft name" required>
                </div>
            </div>

            <div class="input-group">
                <label>Phone Number</label>
                <div class="input-with-icon">
                    <i class="fa fa-phone"></i>
                    <input type="text" name="phone" placeholder="Type your phone number">
                </div>
            </div>

            <button type="submit" name="register" class="auth-btn">SIGN UP</button>

            <div class="login-link">
                <p>Have an Account?</p>
                <a href="login.php">LOGIN</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>